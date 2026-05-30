# 🍽️ Resto ERP - Restaurant Business Management Suite

<p align="center">
  <img src="https://readme-typing-svg.demolab.com?font=Inter&weight=800&size=28&duration=2600&pause=900&color=4F46E5&center=true&vCenter=true&width=760&lines=Restaurant+ERP+with+One-Click+Installer;Revenue%2C+Expenses%2C+Staff%2C+Reports;Brand+It%2C+Install+It%2C+Run+It" alt="Animated project title" />
</p>

<p align="center">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8%2B-777BB4?style=for-the-badge&logo=php&logoColor=white">
  <img alt="MySQL" src="https://img.shields.io/badge/MySQL-MariaDB-4479A1?style=for-the-badge&logo=mysql&logoColor=white">
  <img alt="Tailwind CSS" src="https://img.shields.io/badge/Tailwind-CSS-06B6D4?style=for-the-badge&logo=tailwindcss&logoColor=white">
  <img alt="Installer Ready" src="https://img.shields.io/badge/Installer-Ready-22C55E?style=for-the-badge&logo=github&logoColor=white">
</p>

<p align="center">
  A portable restaurant ERP built in PHP and MySQL. Upload it to hosting, run <code>install.php</code>, enter database and branding details, create the first admin, and start managing the business.
</p>

---

## ✨ Highlights

- 🧩 **One-click browser installer** with database, branding, and first admin setup
- 🎨 **Branding settings** for business name, mobile, email, address, license/GST, and currency
- 📊 **Executive dashboard** with revenue, expense, and profit summaries
- 💰 **Revenue and cash collection modules**
- 🧾 **Expense and vendor khata management**
- 👥 **Staff khata, salary, advance, and attendance tools**
- 📈 **Reports and analytics screens**
- ☁️ **Backup and restore manager**
- 🔐 **Password-hashed admin login**
- 🪪 **Admin profile controls** for username, password, photo, email, and mobile
- 📱 **Responsive Tailwind CSS interface**

---

## 🧭 App Modules

| Module | Purpose |
| --- | --- |
| Dashboard | Business KPIs, trend chart, quick navigation |
| Collection | Cash and daily collection tracking |
| Revenue | Daily sales records |
| Expenses | Category-wise expense records |
| Vendor Khata | Vendor balance and purchase tracking |
| Staff Hub | Staff list, attendance, salary, and advance ledger |
| Master Ledger | Combined business ledger view |
| Reports | Printable and filtered business reports |
| Analytics | Visual business intelligence screens |
| Data Shield | Backup and restore SQL files |
| Settings | Branding and business identity controls |

---

## 🚀 Quick Install

1. Download or clone this project.
2. Upload all files to your PHP hosting server.
3. Create a MySQL/MariaDB database in your hosting panel.
4. Open this URL in your browser:

```text
https://your-domain.com/install.php
```

5. Enter:
   - Database host
   - Database name
   - Database username
   - Database password
   - Business branding details
   - First admin username, password, email, and mobile
6. Click **Install Now**.
7. Login from:

```text
https://your-domain.com/login.php
```

> After installation, delete or rename `install.php` on a live server.

---

## 🛠️ Requirements

- PHP 8.0 or newer
- MySQL 5.7+ or MariaDB 10.3+
- Apache hosting recommended
- PHP `mysqli` extension enabled
- Write permission during install so `config.php` can be created

---

## 📁 Important Files

| File | Description |
| --- | --- |
| `install.php` | First-time setup wizard |
| `setup.sql` | Clean blank database schema used by installer |
| `db.php` | Database connection loader |
| `config.php` | Generated after install, ignored by Git |
| `.htaccess` | Basic Apache protection for private files |
| `INSTALL_README.txt` | Short upload/install guide |

---

## 🔐 Security Notes

- Never commit `config.php`.
- Never commit private database backups.
- Delete or rename `install.php` after production setup.
- Change default/admin passwords regularly.
- Keep hosting PHP and MySQL updated.
- The included `.htaccess` blocks direct access to SQL/config files on Apache servers.

---

## 🖼️ Branding

This ERP is designed so each restaurant, hotel, cafe, food stall, or small business can set its own identity during install or later from **Settings**.

Branding fields include:

- Business name
- Address
- Mobile number
- Email address
- License/GST number
- Currency symbol
- Footer text

---

## 📦 GitHub Upload Checklist

Before pushing to GitHub, keep only safe project files:

```bash
git init
git add .
git commit -m "Initial restaurant ERP installer release"
git branch -M main
git remote add origin https://github.com/your-username/your-repo.git
git push -u origin main
```

The `.gitignore` already excludes generated config, SQL backups, zip exports, logs, and uploaded user media.

---

## 💡 Roadmap Ideas

- Role-based users and permissions
- Multi-branch support
- Invoice/POS billing
- Product and inventory module
- Offline/PWA support
- PDF export for reports
- Dark/light theme switcher

---

## 👨‍💻 Author

<p align="center">
  <a href="https://github.com/manoranjan2050">
    <img src="https://github.com/manoranjan2050.png?size=140" width="140" height="140" alt="MANORANJAN GitHub profile photo" style="border-radius: 50%;" />
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
  <b>⭐ If this project helps you, give it a star on GitHub.</b>
</p>
