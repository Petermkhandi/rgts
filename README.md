# RUCU Graduate Employment Tracking & Verification System (GETS)

A full-stack web-based system enabling Ruaha Catholic University (RUCU) to track graduate employment, verify credentials via NECTA simulation, and aggregate analytics for decision-making.

## Features

- **SIMS Integration**: Automatic graduate data synchronization from university SIMS
- **NECTA Verification**: Form IV Index Number verification simulation
- **Employment Tracking**: Graduates update employment status and career progress
- **Admin Dashboard**: Analytics, reports, and graduate management
- **Job Feed**: Live job opportunities from external sources (Ajira Portal simulation)
- **Chart.js Analytics**: Employment trends, distribution, and rates
- **Password Security**: 30-day expiry policy with forced reset
- **Session Management**: 15-minute inactivity timeout

## Tech Stack

- PHP 8.0+ (PDO with prepared statements)
- MySQL 5.7+ / MariaDB 10.3+
- Bootstrap 5
- Chart.js 4
- Bootstrap Icons

## Installation

### Prerequisites
- XAMPP (or LAMP/WAMP) with PHP 8.0+ and MySQL
- Web browser (Chrome, Firefox, Edge)

### Setup Steps

1. **Copy Files**
   ```
   Copy the `rgts` folder to `C:\xampp\htdocs\`
   ```

2. **Create Database**
   - Start Apache and MySQL in XAMPP Control Panel
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Go to Import tab
   - Select `database.sql` from the `rgts` folder
   - Click "Go" to import

   OR use MySQL CLI:
   ```bash
   mysql -u root -p < C:\xampp\htdocs\rgts\database.sql
   ```

3. **Configure Database Connection**
   - Open `config/config.php`
   - Update database credentials if different:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'rucu_gets');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     ```

4. **Set Permissions**
   - Ensure `uploads/` folder is writable:
     ```bash
     chmod 777 C:\xampp\htdocs\rgts\uploads
     ```

5. **Access the System**
   - Open browser and go to: `http://localhost/rgts`

## Default Login Credentials

### Admin
- **Email**: admin@rucu.ac.tz
- **Password**: admin123

### Graduates (First-Time Login)
- **Registration Number**: RUCU/2020/001, RUCU/2020/002, etc.
- **Password**: graduate123 (for pre-setup accounts)
- **New Graduates** (RUCU/2022/005+): Will be prompted to set password on first login

## Project Structure

```
rgts/
├── index.php                 # Main login page
├── logout.php                # Logout handler
├── database.sql              # Database schema and sample data
├── .htaccess                 # Security configuration
├── config/
│   ├── config.php            # Application configuration
│   └── database.php          # Database connection (PDO)
├── includes/
│   ├── helpers.php           # Utility functions
│   ├── header.php            # Public header template
│   ├── footer.php            # Public footer template
│   ├── admin_header.php      # Admin panel header
│   ├── admin_footer.php      # Admin panel footer
│   ├── graduate_header.php   # Graduate portal header
│   └── graduate_footer.php   # Graduate portal footer
├── admin/
│   ├── dashboard.php         # Admin analytics dashboard
│   ├── graduates.php         # Graduate management
│   ├── verification.php      # Verification management
│   ├── employment.php        # Employment data view
│   ├── reports.php           # Reports & analytics
│   ├── jobs.php              # Job feed management
│   └── sims_sync.php         # SIMS synchronization
├── graduate/
│   ├── dashboard.php         # Graduate dashboard
│   ├── profile.php           # Profile management
│   ├── employment.php        # Employment update
│   ├── verification.php      # Verification status
│   ├── jobs.php              # Job opportunities
│   ├── set_password.php      # First-time password setup
│   └── reset_password.php    # Password reset/expiry
├── api/
│   ├── verification_engine.php # NECTA/employer verification
│   ├── sims_api.php          # SIMS integration API
│   ├── necta_api.php         # NECTA verification API
│   └── ajax_handler.php      # AJAX requests handler
├── jobs_feed/
│   └── scraper.php           # Job feed scraper
├── assets/
│   ├── css/style.css         # Custom styles
│   ├── js/                   # Custom JavaScript
│   └── images/               # Static images
└── uploads/                  # Profile image uploads
```

## Security Features

- **Prepared Statements (PDO)**: SQL injection prevention
- **Password Hashing**: `password_hash()` with bcrypt
- **Password Expiry**: 30-day forced reset
- **Session Management**: 15-minute inactivity timeout
- **CSRF Protection**: Token verification on all forms
- **Input Sanitization**: XSS prevention
- **Role-Based Access**: Graduate and Admin separation
- **Session Regeneration**: On login for security

## User Flows

### Graduate Flow
1. Login with Registration Number
2. Set password (first-time) or enter existing password
3. Update profile information
4. Update employment status
5. View verification status (NECTA + Employer)
6. Browse job opportunities

### Admin Flow
1. Login with email and password
2. View dashboard analytics
3. Manage graduates (search, filter, view)
4. Monitor verification logs
5. Review employment data
6. Generate reports with Chart.js
7. Sync data from SIMS
8. Manage job feed

## Database Tables

| Table | Description |
|-------|-------------|
| `graduates` | Graduate data from SIMS |
| `employment_details` | Employment status and info |
| `verification_logs` | NECTA/employer verification records |
| `admin_users` | Admin login credentials |
| `job_feed` | Cached job listings |
| `activity_logs` | System activity tracking |
| `sims_sync_log` | SIMS synchronization history |

## Troubleshooting

### Database connection failed
- Check MySQL is running in XAMPP
- Verify credentials in `config/config.php`
- Ensure database `rucu_gets` exists

### Session issues
- Ensure `session_start()` is called before any output
- Check PHP session configuration in `php.ini`

### Upload not working
- Ensure `uploads/` folder has write permissions
- Check `upload_max_filesize` in `php.ini`

## License

This system is developed for Ruaha Catholic University (RUCU) - Iringa, Tanzania.
