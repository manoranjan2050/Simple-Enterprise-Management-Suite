Simple Enterprise Management Suite Install Guide
================================================

Upload the full project folder to your PHP hosting account.

Required:
- PHP 8.0 or newer
- MySQL or MariaDB
- A database name, database username, and database password from your hosting panel

Install:
1. Open https://your-domain.com/install.php in the browser.
2. Enter DB host, DB name, DB username, and DB password.
3. Enter business branding details.
4. Enter the first admin username, password, email, and mobile.
5. Click Install Now.
6. After success, open login.php and sign in with the admin account.

Security after install:
- Delete or rename install.php on the live server.
- Keep config.php private. The included .htaccess blocks it on Apache hosting.
- Do not share old backup SQL files if they contain private business data.

Files used by installer:
- install.php: browser setup wizard
- setup.sql: clean blank database table structure
- config.php: generated automatically after install
- update.php: run once after uploading a new version to an old installed system
- telegram.php: Telegram bot webhook endpoint

Telegram:
1. Create a bot using @BotFather.
2. Paste the bot token in Settings.
3. Send a message to the bot to get your chat ID.
4. Add that chat ID in Settings > Allowed Chat IDs.
5. Set webhook to https://your-domain.com/telegram.php.
