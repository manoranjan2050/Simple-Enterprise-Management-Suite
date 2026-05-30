# Simple Enterprise Management Suite

<p align="center">
  <img src="https://readme-typing-svg.demolab.com?font=Inter&weight=800&size=28&duration=2600&pause=900&color=4F46E5&center=true&vCenter=true&width=820&lines=Simple+Enterprise+Management+Suite;Open+Source+PHP+%2B+MySQL+Business+ERP;Install%2C+Brand%2C+Manage%2C+Grow" alt="Animated Simple Enterprise Management Suite title" />
</p>

<p align="center">
  <b>SEMS</b> is a lightweight, installable PHP and MySQL management suite for small businesses, restaurants, cafes, shops, service teams, and local enterprises.
</p>

<p align="center">
  <a href="https://github.com/manoranjan2050/Simple-Enterprise-Management-Suite">
    <img alt="GitHub repo" src="https://img.shields.io/badge/GitHub-Simple--Enterprise--Management--Suite-181717?style=for-the-badge&logo=github&logoColor=white">
  </a>
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8%2B-777BB4?style=for-the-badge&logo=php&logoColor=white">
  <img alt="MySQL" src="https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white">
  <img alt="Tailwind CSS" src="https://img.shields.io/badge/Tailwind-CSS-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white">
  <img alt="Open Source" src="https://img.shields.io/badge/Open%20Source-Ready-22C55E?style=for-the-badge&logo=opensourceinitiative&logoColor=white">
</p>

---

## What It Does

Simple Enterprise Management Suite helps small businesses track daily money flow, expenses, staff records, vendor balances, reports, analytics, and backups from one clean browser-based dashboard.

It includes a first-time installer, so anyone can upload the files to hosting, open `install.php`, enter database and branding details, create the first admin account, and start using the system.

## Features

- One-click browser installer
- Business branding setup
- Admin username, password, email, mobile, and profile photo controls
- Revenue and collection tracking
- Expense category and detail management
- Vendor khata / vendor ledger
- Staff list, attendance, advance, and salary ledger
- Master ledger view
- Reports and analytics
- SQL backup and restore manager
- Responsive Tailwind CSS interface
- GitHub-ready `.gitignore` for safe open-source publishing

## Modules

| Module | Purpose |
| --- | --- |
| Dashboard | Business KPIs, trend chart, and quick navigation |
| Collection | Cash and daily collection tracking |
| Revenue | Daily sales and income records |
| Expenses | Category-wise expense entries |
| Vendor Khata | Vendor balances and payment tracking |
| Staff Hub | Staff profiles, attendance, salary, and advances |
| Master Ledger | Combined business ledger view |
| Reports | Filtered and printable business reports |
| Analytics | Visual business intelligence screens |
| Data Shield | SQL backup and restore tools |
| Settings | Branding and business identity controls |

## Quick Install

1. Clone or download this repository.
2. Upload all files to your PHP hosting server.
3. Create a MySQL or MariaDB database from your hosting panel.
4. Open this URL in your browser:

```text
https://your-domain.com/install.php
```

5. Enter database details, business branding, and first admin account details.
6. Click `Install Now`.
7. Login from:

```text
https://your-domain.com/login.php
```

After installation on a live server, delete or rename `install.php`.

## Requirements

- PHP 8.0 or newer
- MySQL 5.7+ or MariaDB 10.3+
- PHP `mysqli` extension
- Apache hosting recommended
- Folder write permission during install so `config.php` can be generated

## Important Files

| File | Description |
| --- | --- |
| `install.php` | First-time setup wizard |
| `setup.sql` | Clean blank database schema |
| `db.php` | Database connection loader |
| `config.php` | Generated after install and ignored by Git |
| `.htaccess` | Apache protection for private files |
| `INSTALL_README.txt` | Short hosting install guide |

## Security Notes

- Never commit `config.php`.
- Never commit private SQL backups.
- Delete or rename `install.php` after production setup.
- Use a strong admin password.
- Keep PHP, MySQL, and hosting tools updated.
- The included `.htaccess` blocks direct access to SQL/config files on Apache hosting.

## Git Setup

Repository:

```text
https://github.com/manoranjan2050/Simple-Enterprise-Management-Suite
```

Useful commands:

```bash
git add .
git commit -m "Update Simple Enterprise Management Suite"
git push
```

## Roadmap Ideas

- Role-based users and permissions
- POS billing and printable receipts
- Product/menu and inventory management
- Customer khata / customer due ledger
- PDF and Excel export
- Multi-branch support
- Demo data option during install
- Theme color and logo upload

## Author

<p align="center">
  <a href="https://github.com/manoranjan2050">
    <img src="https://github.com/manoranjan2050.png?size=140" width="140" height="140" alt="MANORANJAN GitHub profile photo" />
  </a>
</p>

<h3 align="center">Developed by MANORANJAN</h3>

<p align="center">
  <a href="https://github.com/manoranjan2050">
    <img alt="GitHub Profile" src="https://img.shields.io/badge/GitHub-manoranjan2050-181717?style=for-the-badge&logo=github&logoColor=white">
  </a>
  <a href="https://manoranjan.dev/">
    <img alt="Website" src="https://img.shields.io/badge/Website-manoranjan.dev-4F46E5?style=for-the-badge&logo=googlechrome&logoColor=white">
  </a>
</p>

<p align="center">
  GitHub: <a href="https://github.com/manoranjan2050">github.com/manoranjan2050</a><br>
  Website: <a href="https://manoranjan.dev/">manoranjan.dev</a>
</p>

<p align="center">
  <b>If this project helps you, please star the repository.</b>
</p>
