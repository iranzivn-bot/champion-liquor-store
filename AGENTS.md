# Champion Liquor Store — Project Summary

## Goal
Build and maintain a PHP/MySQL ecommerce admin panel with full CRUD, auth, RBAC, Shopping Cart, Checkout, Order Management, Delivery Tracking, Wishlist, Reports, Inventory, BI Reporting, Audit Log, Analytics Dashboard, System Settings, i18n multi-language support, and complete Security Hardening.

## Constraints & Preferences
- PHP 8.3, MySQL, PDO (`EMULATE_PREPARES = false`), Bootstrap 5 CDN, Bootstrap Icons v1.13.1, Fetch API for AJAX, Chart.js 4.4.1
- Colors: navy `#001F5B`, gold `#C9A227`, green `#0B6B2F`, white `#FFFFFF`, light bg `#F5F6FA` / `#f8f9fa`
- Images upload to `uploads/categories/`, `uploads/brands/`, `uploads/products/`, `uploads/settings/`
- `SITE_URL` dynamically detected; auto-generated codes (CAT-XXX, BRD-XXX, PRD-XXX)
- RBAC roles: `super_admin` (red badge), `admin` (blue badge), `customer` (gray badge)
- All admin pages guarded by `requireAdmin()` + `requirePermission('module.action')`; sidebar items auto-hide via `hasPermission()`
- Constants centralize all roles (`SUPER_ADMIN_ROLE`, `ADMIN_ROLE`, `CUSTOMER_ROLE`), statuses, currency (`RWF`), upload paths, default images, order/payment/delivery statuses, inventory movement types
- Helpers: `helpers/csrf.php` (canonical CSRF token), `helpers/permission-helper.php` (RBAC), `helpers/settings-helper.php` (system settings), `helpers/language-helper.php` (i18n), `helpers/validation-helper.php` (input sanitization), `helpers/format-helper.php`, `helpers/audit-helper.php`, `helpers/auth-helper.php`
- Flash messages via `setFlashMessage()` / `getFlashMessage()` in `includes/messages.php`
- DB connection via `getDbConnection()` singleton PDO with `EMULATE_PREPARES = false`
- Order number format: `CLS-2026-000001` sequential per year
- Login rate limiting: 3 failed attempts locks account for 15 minutes
- Session idle timeout: 30 minutes
- Password policy: min 8 chars, uppercase, lowercase, digit, special character
- File upload MIME validation via `finfo`
- Supported languages: English (default), French, Kinyarwanda; future-ready for Swahili
- Language keys defined in `languages/{code}.php`; `lang('key')` for all UI text; missing keys fall back to English → raw key

## Progress

### Done
- Full CRUD admin modules: Categories, Brands, Products, Users, Orders
- Complete frontend: home hero, shop with search/filter/sort/pagination, product-details with gallery/qty/related, cart with AJAX, checkout with transaction / `FOR UPDATE` locking
- Complete authentication: register, login (Remember Me), logout, role-based redirect, profile, change password, password reset structure
- Order Status Workflow with timeline, one-direction status flow, cancel with reason, stock restoration guard, customer snapshot columns
- Delivery Tracking: `tracking_number` + `delivery_status` columns, admin delivery management card, timeline extension, dashboard stats
- Wishlist: AJAX add/remove, heart toggle on shop + product-details, navbar count badge, `.wishlist-active` CSS class
- Reports: 8 report pages with Chart.js charts, CSV/Excel export, multi-axis charts, date/status/category filters, executive BI dashboard with 18 KPI cards + 7 charts + AI Insights (SQL-calculated)
- Enterprise BI Reporting System: cost_price migration, finance/profit/tax/inventory reports, submenu sidebar, composite indexes
- Inventory Management: stock-in/adjustment/history with transaction-safe operations, stock_before/stock_after audit trail, low stock alerts, CSV/Excel export
- Audit Log: `audit_logs` table with FK to users, 4 KPI cards, filterable table with date/user/role/module/action filters, quick filters, pagination (25/page), CSV/Excel/Print export, detail view with IP + UA
- Analytics Dashboard: 6 pages (dashboard/sales/customers/inventory/products/system), 11+ KPI cards, 4-5 Chart.js charts each, period filters, AJAX auto-refresh every 60s, Excel/Print export, sidebar nav entry
- Performance Optimization: composite indexes for products (status+stock), orders (status+created, user+created), users (status, created_at)
- CSRF Protection: `helpers/csrf.php` with `generateCsrfToken()`, `validateCsrfToken()`, `csrfField()`; all 7 frontend POST forms + all 6 AJAX handlers; `window.CSRF_TOKEN` in footer; `validateCSRFTokenRequest()` for X-CSRF-Token header
- Security Headers: `addSecurityHeaders()` in `config.php` (X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy, Content-Security-Policy)
- HTTPS Redirect: automatic HTTP→HTTPS 301 when `ENVIRONMENT === 'production'`
- Deployment Readiness: `includes/error-handler.php` with logging to `storage/logs/errors/application.log`; friendly 500/503 error pages; maintenance mode toggle via `.lock` file in `storage/framework/`; `storage/` directory structure with `.gitkeep`/`.gitignore`
- Duplicate functions removed from `includes/functions.php`: `sanitizeInput()`, `formatCurrency()`, flash messages
- RBAC (Role-Based Access Control): permissions tables, helper, admin UI, permission-gated sidebar, role enforcement across all admin pages
- System Settings Module: 10 settings pages (general, company, email, orders, security, appearance, maintenance, backup, system-info, languages); `setting()` helper with static cache
- i18n (Internationalization): languages table, language files (en/fr/rw with 360+ keys each), `lang()` helper, language switcher in navbars, admin language management, user profile language preference, hreflang + lang attribute, LFI-safe language code validation, untranslated keys finder tool
- Security Hardening: login rate limiting, session idle timeout (30 min), password strength validation, input sanitization functions, `finfo` MIME validation for uploads, CSRF function redeclaration guards, `isAdmin()`/`isLoggedIn()` duplicate declaration guards, `config.php` load order fixes, RBAC/security migration SQL scripts
- Design System Phase 1: Modular CSS architecture with 15 files in `assets/css/` — tokens, base, components, layout, utilities, animations, responsive
- Updated `includes/header.php` to load `design-system.css` before `style.css`
- **Header & Navigation Redesign (Phase 2):** Complete luxury header rebuild with top announcement bar, premium main header (logo, search, wishlist, compare, account, cart), category navigation with mega menu, sticky wrapper, mobile off-canvas slide menu, mobile search overlay, and bottom navigation bar
- Added 12 new language keys (`all_categories`, `liquor_shop`, `mini_market`, `flash_sales`, `offers`, `blog`, `compare`, `help_center`, `fast_delivery`, `premium_liquor`, `mini_supermarket`, `search_products`, `free_delivery`) across all 3 language files
- Created `assets/css/components/header.css` for all header-specific styling using design system tokens
- Extended JS in `script.js` with sticky header, mobile menu toggle, search overlay, and cross-device badge syncing

### Key Decisions
- RBAC uses hybrid model: `role_permissions` for role defaults + `user_permissions` for per-user overrides; cached in `$_SESSION['_permissions']` per request
- `hasPermission()` skips DB for Super Admin (always true) and Customer (always false); Admin loads role base + user overrides
- `permissions.manage` permission is the gate for the permission management UI (only super_admin has it by default)
- Settings cached in static variable in `setting()`; returns `$default` if key not found
- File uploads for logo/favicon stored in `uploads/settings/`; validated via `finfo` MIME + extension whitelist + 2MB max
- Database backups go to `backups/` directory; `mysqldump` auto-detected via `findMysqldump()`; stderr captured separately from stdout
- Maintenance mode uses `.lock` file in `storage/framework/maintenance.lock` — Super Admin can still access admin panel
- `config.php` load order: `session.php` → `constants.php` → `error-handler.php` → `helpers/csrf.php` → `helpers/auth-helper.php` → `helpers/validation-helper.php` → `helpers/permission-helper.php` → `helpers/settings-helper.php` → `helpers/language-helper.php` → `security.php` → `messages.php`
- i18n: Language codes validated against DB whitelist (`isValidLanguageCode()`) to prevent LFI; only active languages available for switching; logged-in user preference saved to `users.language` column; guest preference in `$_SESSION['language']`
- All `catch (Exception $e)` blocks in helpers changed to `catch (\Throwable $e)` — PHP 8 throws `\Error` for undefined functions which does not extend `\Exception`
- Design System (Phase 1): Created new parallel token namespace (`--color-*`, `--space-*`, `--radius-*`, `--shadow-*`, `--transition-*`) in `tokens.css` — old variables (`--primary`, `--gold`, `--green`, etc.) remain untouched in `style.css` for backward compatibility
- Design system loads FIRST via `design-system.css`, then `style.css` overrides (same specificity, later source wins) — ensures zero regressions during transition
- Refactoring `style.css` (removing `:root`, deduping rules, deleting dead CSS) deferred to end of Phase 1, after page-by-page verification
