<?php
/**
 * Job Feed Scraper - Extracts deadlines from list pages (no detail page visits)
 * Sources: jobwebtanzania.com, fursa.co.tz, ajiranew.com
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function parseDeadlineDate($raw) {
    if (empty($raw)) return null;
    $raw = trim($raw);

    // "30/Apr/2026" or "30-Apr-2026"
    if (preg_match('/^(\d{1,2})[\/-]([A-Za-z]+)[\/-](\d{4})$/', $raw, $m)) {
        $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $month = date('m', strtotime($m[2] . ' 1'));
        return $m[3] . '-' . $month . '-' . $day . ' 23:59:59';
    }

    // "30/04/2026" or "30-04-2026"
    if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $raw, $m)) {
        return sprintf('%04d-%02d-%02d 23:59:59', $m[3], $m[2], $m[1]);
    }

    // "2026/05/11" or "2026-05-11"
    if (preg_match('/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})$/', $raw, $m)) {
        return sprintf('%04d-%02d-%02d 23:59:59', $m[1], $m[2], $m[3]);
    }

    // "May 07, 2026" or "7 May 2026"
    $ts = strtotime($raw);
    if ($ts !== false && $ts > time()) return date('Y-m-d H:i:s', $ts);

    return null;
}

function extractDeadlineFromBlock($text) {
    // "Closing Date 2026/05/11" or "Deadline: 11 May 2026"
    if (preg_match('/(?:closing\s*date|deadline|apply\s*by|expires?|application\s*deadline)\s*[:\-]?\s*(\d{1,2}[\/\-\s](?:\d{1,2}|[A-Za-z]+)[\/\-\s]\d{4})/i', $text, $m)) {
        $result = parseDeadlineDate(trim($m[1]));
        if ($result) return $result;
    }

    // "May 07, 2026" standalone
    if (preg_match('/(?:closing|deadline|apply\s*by|expires?)\s*[:\-]?\s*([A-Za-z]+\s+\d{1,2},?\s+\d{4})/i', $text, $m)) {
        $result = parseDeadlineDate(trim($m[1]));
        if ($result) return $result;
    }

    // Standalone date: "30/Apr/2026"
    if (preg_match('/(\d{1,2}[\/-][A-Za-z]+[\/-]\d{4})/', $text, $m)) {
        $result = parseDeadlineDate(trim($m[1]));
        if ($result) return $result;
    }

    // Standalone date: "30/04/2026"
    if (preg_match('/(\d{1,2}[\/-]\d{1,2}[\/-]\d{4})/', $text, $m)) {
        $result = parseDeadlineDate(trim($m[1]));
        if ($result) return $result;
    }

    return null;
}

function fetchUrl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_ENCODING, '');
    $html = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) {
        error_log("scraper.php fetchUrl error for $url: $error");
    }
    return $html;
}

function scrapeJobwebTanzania() {
    $jobs = [];
    $html = fetchUrl('https://www.jobwebtanzania.com/');
    if (!$html) return $jobs;

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();

    // Track seen URLs to avoid duplicates from repeated links
    $seenUrls = [];
    $count = 0;

    // Find job links directly - avoid picking up navigation/filter links
    $links = $dom->getElementsByTagName('a');

    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        if (strpos($href, '/jobs/') === false) continue;
        if (strpos($href, 'facebook.com') !== false || strpos($href, 'twitter.com') !== false) continue;
        if (strpos($href, 'category') !== false) continue;

        // Deduplicate
        $urlKey = preg_replace('/\/#.*$/', '', $href);
        if (isset($seenUrls[$urlKey])) continue;
        $seenUrls[$urlKey] = true;

        $title = trim($link->textContent);
        // Skip short or non-job titles
        if (strlen($title) < 15) continue;
        if (stripos($title, 'details') !== false || stripos($title, 'facebook') !== false || stripos($title, 'twitter') !== false) continue;

        $fullUrl = (strpos($href, 'http') === 0) ? $href : 'https://www.jobwebtanzania.com' . $href;

        // Walk up DOM to get the surrounding card text
        $card = $link;
        $cardText = '';
        for ($i = 0; $i < 5 && $card; $i++) {
            $cardText = $card->textContent . ' ' . $cardText;
            $card = $card->parentNode;
        }

        // Extract company from title: "Title at Company"
        $company = '';
        if (preg_match('/\bat\s+(.+)$/i', $title, $m)) {
            $company = trim($m[1]);
        }
        // Clean company: remove trailing " Job" or similar
        $company = preg_replace('/\s*Job\s*$/i', '', $company);

        // Extract location from card text
        $location = '';
        if (preg_match('/Location:\s*([^\n,]+)/i', $cardText, $m)) {
            $location = trim($m[1]);
        }
        if (empty($location) && preg_match('/(Dar es Salaam|Dodoma|Arusha|Mwanza|Iringa|Mbeya|Morogoro|Mtwara|Tanga|Zanzibar|Anywhere|Pwani)/i', $cardText, $m)) {
            $location = $m[1];
        }

        // Extract date
        $date = null;
        if (preg_match('/(\d{1,2}\/[A-Za-z]+\/\d{4})/', $cardText, $m)) {
            $date = parseDeadlineDate($m[1]);
        }
        if (!$date && preg_match('/(\d{1,2}\/\d{1,2}\/\d{4})/', $cardText, $m)) {
            $date = parseDeadlineDate($m[1]);
        }

        // Extract description from paragraph-like text
        $description = '';
        if (preg_match('/(?:is a|we are|our client|seeking|looking for|responsible)[\s\S]{20,300}/i', $cardText, $m)) {
            $description = trim(substr($m[0], 0, 300));
        }

        if ($date) {
            $cleanTitle = preg_replace('/\s*Job\s*at\s*|\s*Job\s*Vacancy\s*at\s*/i', ' at ', $title);
            $jobs[] = [
                'title' => $cleanTitle,
                'organization' => $company ?: 'Various',
                'location' => $location ?: 'Tanzania',
                'description' => $description ?: 'Vacancy posted on JobwebTanzania',
                'deadline' => $date,
                'source_url' => $fullUrl,
                'source_name' => 'JobwebTanzania'
            ];
            $count++;
            if ($count >= 15) break;
        }
    }

    return $jobs;
}

function scrapeFursaCoTz() {
    $jobs = [];
    $html = fetchUrl('https://fursa.co.tz/');
    if (!$html) return $jobs;

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();

    $count = 0;
    $seenUrls = [];

    // Find all links with /job/ in href
    $links = $dom->getElementsByTagName('a');

    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        if (strpos($href, '/job/') === false) continue;

        // Deduplicate
        if (isset($seenUrls[$href])) continue;
        $seenUrls[$href] = true;

        $text = trim($link->textContent);
        if (strlen($text) < 15) continue;

        // Skip non-job links
        if (stripos($text, 'apply now') !== false && strlen($text) < 20) continue;

        // Clean title: remove date suffixes
        $title = preg_replace('/\s*(?:May|April|June|July|August|September|October|November|December|January|February|March)\s+\d{4}\s*$/i', '', $text);
        $title = trim($title);
        if (strlen($title) < 10) continue;

        $detailUrl = (strpos($href, 'http') === 0) ? $href : 'https://fursa.co.tz' . $href;

        // Walk up DOM to get card text for extracting location and deadline
        $card = $link;
        $blockText = '';
        for ($i = 0; $i < 6 && $card; $i++) {
            $blockText = $card->textContent . ' ' . $blockText;
            $card = $card->parentNode;
        }

        // Extract company
        $company = '';
        if (preg_match('/at\s+([^,\.]+)/i', $title, $m)) {
            $company = trim($m[1]);
        } elseif (preg_match('/Company:\s*([^\n]+)/i', $blockText, $m)) {
            $company = trim($m[1]);
        }

        // Extract location
        $location = '';
        if (preg_match('/(Dar es Salaam|Dodoma|Arusha|Mwanza|Iringa|Mbeya|Morogoro|Mtwara|Tanga|Zanzibar|Anywhere|Pwani)/i', $blockText, $m)) {
            $location = $m[1];
        }

        // Extract deadline from URL (e.g., "...-may-2026/") or block text
        $deadline = null;
        if (preg_match('/[-\/](January|February|March|April|May|June|July|August|September|October|November|December)[-\s]+(\d{4})/i', $href, $m)) {
            $monthNum = date('m', strtotime($m[1] . ' 1'));
            $lastDay = date('t', strtotime($m[2] . '-' . $monthNum . '-01'));
            $deadline = $m[2] . '-' . $monthNum . '-' . $lastDay . ' 23:59:59';
        }
        if (!$deadline) {
            $deadline = extractDeadlineFromBlock($blockText);
        }

        if (!$deadline) continue;

        // Extract description
        $description = '';

        $jobs[] = [
            'title' => $title,
            'organization' => $company ?: 'Various',
            'location' => $location ?: 'Tanzania',
            'description' => $description ?: 'Vacancy posted on Fursa.co.tz',
            'deadline' => $deadline,
            'source_url' => $detailUrl,
            'source_name' => 'Fursa.co.tz'
        ];
        $count++;
        if ($count >= 10) break;
    }

    return $jobs;
}

function scrapeAjiraNew() {
    $jobs = [];
    $html = fetchUrl('https://ajiranew.com/');
    if (!$html) return $jobs;

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();

    $links = $dom->getElementsByTagName('a');
    $count = 0;
    $seenUrls = [];

    foreach ($links as $link) {
        $href = $link->getAttribute('href');
        $text = trim($link->textContent);

        // Match links: URL has month+year OR href contains vacancy/job related path
        // AND text contains vacancy/vacancies/ajira
        $urlMatch = (
            strpos($href, '-202') !== false ||
            strpos($href, '/january') !== false || strpos($href, '/february') !== false ||
            strpos($href, '/march') !== false || strpos($href, '/april') !== false ||
            strpos($href, '/may') !== false || strpos($href, '/june') !== false ||
            strpos($href, '/july') !== false || strpos($href, '/august') !== false ||
            strpos($href, '/september') !== false || strpos($href, '/october') !== false ||
            strpos($href, '/november') !== false || strpos($href, '/december') !== false
        );

        $textMatch = (
            stripos($text, 'vacanc') !== false ||
            stripos($text, 'ajira') !== false
        );

        if (!$urlMatch || !$textMatch) continue;
        if (strlen($text) < 15 || strlen($text) > 250) continue;

        // Deduplicate
        if (isset($seenUrls[$href])) continue;
        $seenUrls[$href] = true;

        $fullUrl = (strpos($href, 'http') === 0) ? $href : 'https://ajiranew.com' . $href;

        // Extract deadline from title text (e.g., "New Vacancies At Ecobank Tanzania, May 2026")
        $deadline = null;
        if (preg_match('/(?:,|\s)(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})/i', $text, $m)) {
            $monthNum = date('m', strtotime($m[1] . ' 1'));
            $lastDay = date('t', strtotime($m[2] . '-' . $monthNum . '-01'));
            $deadline = $m[2] . '-' . $monthNum . '-' . $lastDay . ' 23:59:59';
        }

        // Try to extract from URL (e.g., "-may-2026/")
        if (!$deadline && preg_match('/[-\/](January|February|March|April|May|June|July|August|September|October|November|December)[-\s\/]+(\d{4})/i', $href, $m)) {
            $monthNum = date('m', strtotime($m[1] . ' 1'));
            $lastDay = date('t', strtotime($m[2] . '-' . $monthNum . '-01'));
            $deadline = $m[2] . '-' . $monthNum . '-' . $lastDay . ' 23:59:59';
        }

        if (!$deadline) continue;

        // Extract organization from title
        $org = 'Various';
        if (preg_match('/(?:at|by)\s+([^,\.]+)/i', $text, $m)) {
            $org = trim($m[1]);
            $org = preg_replace('/\s*,\s*(?:May|April|June|July|August|September|October|November|December|January|February|March)\s+\d{4}\s*$/i', '', $org);
        }

        $jobs[] = [
            'title' => $text,
            'organization' => $org,
            'location' => 'Tanzania',
            'description' => 'Vacancies posted via AjiraNew. Check the full listing for application details.',
            'deadline' => $deadline,
            'source_url' => $fullUrl,
            'source_name' => 'AjiraNew'
        ];
        $count++;
        if ($count >= 10) break;
    }

    return $jobs;
}

function scrapeJobs() {
    $db = getDB();
    $db->exec("UPDATE job_feed SET status = 'expired' WHERE deadline < NOW() AND status = 'active'");

    $allJobs = [];
    $allJobs = array_merge($allJobs, scrapeJobwebTanzania());
    $allJobs = array_merge($allJobs, scrapeFursaCoTz());
    $allJobs = array_merge($allJobs, scrapeAjiraNew());

    $added = 0;
    foreach ($allJobs as $job) {
        // Skip jobs without a real deadline
        if (empty($job['deadline'])) continue;

        // Check duplicate
        $stmt = $db->prepare("SELECT COUNT(*) FROM job_feed WHERE title = ? AND organization = ?");
        $stmt->execute([$job['title'], $job['organization']]);
        if ($stmt->fetchColumn() == 0) {
            $status = (strtotime($job['deadline']) >= time()) ? 'active' : 'expired';
            $stmt = $db->prepare("INSERT INTO job_feed (title, organization, location, description, deadline, source_url, source_name, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $job['title'],
                $job['organization'],
                $job['location'],
                $job['description'],
                $job['deadline'],
                $job['source_url'],
                $job['source_name'],
                $status
            ]);
            $added++;
        }
    }

    $db->exec("DELETE FROM job_feed WHERE status = 'expired' AND deadline < DATE_SUB(NOW(), INTERVAL 60 DAY)");

    $total = $db->query("SELECT COUNT(*) FROM job_feed")->fetchColumn();
    $active = $db->query("SELECT COUNT(*) FROM job_feed WHERE status = 'active' AND deadline >= NOW()")->fetchColumn();

    return [
        'added' => $added,
        'total' => $total,
        'active' => $active,
        'sources' => 3,
    ];
}

if (basename($_SERVER['PHP_SELF']) === 'scraper.php' && isset($_GET['run'])) {
    $result = scrapeJobs();
    echo json_encode($result);
}
