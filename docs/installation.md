# YBT Management Software — Installation & Setup Guide

## Requirements
- **Web Server:** Apache (via XAMPP, WAMP, or standalone)
- **PHP:** PHP 8.2 or PHP 8.3+ with `pdo_mysql`, `mbstring`, `openssl`, `json` extensions
- **Database:** MySQL 8.0+ or MariaDB 10.4+

---

## Installation Steps (XAMPP on Windows)

### 1. Place Codebase in htdocs
Ensure the project is located at:
```text
C:\xampp\htdocs\YBT Management Software\
```

### 2. Start MySQL and Apache
Open the **XAMPP Control Panel** and start **Apache** and **MySQL**.
Or execute via PowerShell:
```powershell
Start-Process "c:\xampp\mysql\bin\mysqld.exe" -ArgumentList "--defaults-file=c:\xampp\mysql\bin\my.ini --standalone"
```

### 3. Initialize Database & Seed Demo Data
Open a terminal in the project directory and run:
```powershell
c:\xampp\php\php.exe database/seed.php
```
This automatically imports `database/schema.sql` (33 tables) and populates realistic demo records from `database/demo-data.sql`.

### 4. Open in Web Browser
Navigate to:
```text
http://localhost/YBT Management Software/
```
You will be directed to the login portal with pre-configured demo logins.
