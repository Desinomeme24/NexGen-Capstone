# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Setup

This is a PHP web application requiring:
- PHP 7.4+ with MySQLi extension
- Apache/Nginx web server
- MySQL database

To run locally:
1. Place the NexGen folder in your web server's document root (e.g., xampp/htdocs)
2. Import the database schema from `CODE/BACKEND/nexgen_db.sql`
3. Update database credentials in `CODE/PHP/config.php` if needed
4. Access via http://localhost/NexGen

No build step or npm dependencies are required for core functionality. Bootstrap 5 is loaded from CDN.

## Code Architecture

### Backend Structure (`CODE/PHP/`)
- **Core**: `config.php` (database connection, security helpers, session management)
- **Authentication**: `index.php` (login/signup), `login_process.php`, `signup_process.php`, `logout.php`
- **Security**: CSRF tokens, image CAPTCHA, session timeout, file upload validation, password hashing
- **Tenant Isolation**: `tenant_helper.php` enforces business-specific data access
- **Modules** (access-controlled via session permissions):
  - Dashboard: `dashboard.php` (owner/employee views)
  - Inventory: `inventory_*`
  - Sales: `sales_recording.php`, `sales_analytics.php`
  - Accounts Receivable: `accounts_receivable.php`, `receivable_*`
  - Admin: `admin_dashboard.php`, `admin_logs.php`, etc.
  - Utilities: `chatbot.php`, `about_us.php`, `settings.php`, `theme_init.php`
- **Helpers**: Audit logging, password utilities, business type helpers (`nxIsBatchTrackedType`)

### Frontend Structure
- **CSS**: `CODE/STYLE/` (dashboard.css, header.css, etc.) - custom styling with CSS variables
- **JavaScript**: `CODE/JS/` (module-specific files like dashboard.js, inventory_management.js)
- **Assets**: `IMAGES/` (logos, captcha images, illustrations)
- **Template Pattern**: PHP files mix HTML with PHP logic; shared components via `include` (header.php, theme_init.php)

### Database
- Schema in `CODE/BACKEND/nexgen_db.sql`
- Key tables: users, businesses, products, inventory, sales, accounts receivable, activity logs
- Tenant isolation via `business_id` foreign key on most tables
- Triggers maintain stock quantities and batch tracking

## Common Commands

### Database
- View schema: `cat CODE/BACKEND/nexgen_db.sql`
- Reset database: Import the SQL file into MySQL

### Development
- No build process - edit PHP/JS/CSS files directly
- Changes take effect immediately on page refresh
- For CSS/JS changes, hard refresh (Ctrl+F5) may be needed to bypass cache

### Testing
- Manual testing via web interface
- Login credentials would be in the users table (check after DB import)
- Roles: system_admin, owner, employee with different module permissions

## Security Features
- CSRF protection: `generateCsrfToken()` / `validateCsrfToken()`
- Session timeout: 10 minutes (configurable in config.php)
- Image CAPTCHA: `generateImageCaptcha()` / `validateImageCaptchaSelection()`
- File upload validation: `nxValidateSecureUpload()` with extension/MIME/type checking
- Password strength: `isStrongPassword()` (requires upper, lower, digit, special char, 8+ min)
- Admin activity logging: Hash-chained tamper-evident logs

## Important Notes
1. **Session Management**: Most PHP files start with `session_start()` and check `$_SESSION['user_id']` and `$_SESSION['role']`
2. **Business Context**: Non-admin users must have a `business_id` in session (set by `nxRequireBusinessId()`)
3. **Module Access**: Dashboard shows modules based on permission flags in session (`can_inventory`, `can_sales`, etc.)
4. **Styling**: Uses CSS variables defined in index.php's `:root` for theme colors; `theme_init.php` handles light/dark mode
5. **JavaScript**: Modules communicate via AJAX to PHP endpoints (e.g., inventory_management.js calls inventory_* PHP files)

## File Navigation Tips
- Look for `*_process.php` files for form handling (login_process.php, signup_process.php, etc.)
- `*_save.php` files typically handle create/update operations
- `*_view.php` files display records
- JavaScript files match PHP module names (dashboard.js <-> dashboard.php)
- Security helpers are in config.php; look for functions starting with `nx` or `is`