# Champion Liquor Store Ltd

A PHP 8.3 ecommerce web application for Champion Liquor Store Ltd — a premium wine, spirits, and beverage retailer.

---

## Project Structure

```
champion-liquor-store/
│
├── admin/                  # Admin panel (password-protected)
│   ├── brands/             # Brand management (CRUD)
│   ├── categories/         # Category management (CRUD)
│   ├── products/           # Product management (CRUD)
│   ├── users/              # User management (placeholder)
│   ├── dashboard.php       # Admin dashboard with stats
│   └── index.php           # Admin panel entry point
│
├── ajax/                   # AJAX endpoint structure (not yet active)
│   ├── cart/               # Cart AJAX (add, update, remove)
│   ├── wishlist/           # Wishlist AJAX (add, remove)
│   └── search/             # Product search AJAX
│
├── api/                    # RESTful API structure (not yet active)
│   ├── cart.php            # Cart API
│   ├── wishlist.php        # Wishlist API
│   ├── checkout.php        # Checkout API
│   └── search.php          # Search API
│
├── assets/                 # Static assets
│   ├── css/                # Stylesheets (style.css)
│   ├── images/             # Images and placeholders
│   └── js/                 # JavaScript (script.js)
│
├── database/               # Database files
│   └── champion_store.sql  # Full database schema + sample data
│
├── errors/                 # HTTP error pages
│   ├── 403.php             # Forbidden
│   ├── 404.php             # Not Found
│   └── 500.php             # Internal Server Error
│
├── helpers/                # Reusable helper functions
│   ├── auth-helper.php     # Authentication checks (isLoggedIn, isAdmin, isCustomer)
│   ├── format-helper.php   # Formatting (price, date, text)
│   ├── upload-helper.php   # File uploads (uploadImage, deleteImage)
│   └── redirect-helper.php # Redirect helpers (redirect, back)
│
├── includes/               # Shared PHP components
│   ├── config.php          # Database, paths, environment config
│   ├── constants.php       # Application-wide constants (roles, statuses, paths)
│   ├── db.php              # PDO database connection singleton
│   ├── functions.php       # Legacy helper functions (backward compatible)
│   ├── header.php          # HTML <head> and opening tags
│   ├── navbar.php          # Frontend navigation bar
│   ├── admin-navbar.php    # Admin navigation bar
│   ├── footer.php          # Closing tags and scripts
│   ├── messages.php        # Flash message functions (set, get, clear)
│   ├── security.php        # CSRF protection and sanitization
│   └── session.php         # Secure session handler
│
├── middlewares/             # Request middleware
│   ├── auth.php            # Requires logged-in user
│   ├── admin-only.php      # Requires admin role
│   └── guest.php           # Redirects logged-in users away
│
├── pages/                  # Frontend pages
│   ├── cart/               # Shopping cart (placeholder)
│   ├── orders/             # Order history (placeholder)
│   ├── wishlist/           # Wishlist (placeholder)
│   ├── about.php           # About Us
│   ├── change-password.php # Change password form
│   ├── contact.php         # Contact form + Google Maps
│   ├── forgot-password.php # Password reset request (structure only)
│   ├── home.php            # Homepage (hero, categories, brands, products)
│   ├── login.php           # Login form
│   ├── logout.php          # Logout handler
│   ├── my-account.php      # Account overview
│   ├── product-details.php # Single product view
│   ├── profile.php         # Edit profile
│   ├── register.php        # Registration form
│   └── shop.php            # Product listing with search/filter
│
├── storage/                # Storage for runtime data
│   ├── cache/              # Future cache files
│   ├── logs/               # Application logs
│   │   └── errors/         # Error log files
│   └── sessions/           # Future session storage
│
├── templates/              # Email and notification templates
│   └── emails/             # HTML email templates
│       ├── welcome.php              # New user welcome
│       ├── reset-password.php       # Password reset
│       └── order-confirmation.php   # Order confirmation
│
├── uploads/                # User-uploaded files
│   ├── brands/             # Brand logo images
│   ├── categories/         # Category images
│   ├── products/           # Product images
│   └── users/              # User profile images
│
└── index.php               # Front controller (entry point)
```

---

## Folder Purposes

### `admin/` — Admin Panel
Password-protected area for store management. Only users with the `admin` role can access these pages. Each subfolder follows the same CRUD pattern: `index.php` (list + search), `create.php`, `edit.php`, `delete.php`.

### `ajax/` — AJAX Endpoints
Prepared for future JavaScript-driven features. Each file documents the expected input/output format. These are not yet functional — they return placeholder JSON responses.

### `api/` — RESTful API
Prepared for future mobile app or third-party integrations. Each file documents the HTTP methods and response formats. Not yet functional.

### `assets/` — Static Files
CSS styles, JavaScript, and images. The `style.css` file contains all custom styles (brand colors, components). The `script.js` file handles interactive features like the quantity selector, gallery thumbnails, and grid/list toggle.

### `database/` — Database Schema
The `champion_store.sql` file contains the complete MySQL schema (6 tables) with foreign keys, indexes, and sample data.

### `errors/` — Error Pages
Standalone HTML pages (no header/footer dependency) for HTTP errors. They load Bootstrap via CDN and follow the brand color scheme.

### `helpers/` — Helper Functions
New reusable function libraries organized by concern:
- **auth-helper.php** — Authentication checks using constants
- **format-helper.php** — Price, date, and text formatting
- **upload-helper.php** — Image upload and deletion
- **redirect-helper.php** — HTTP redirect utilities

### `includes/` — Shared Components
Core PHP files used across the application:
- **config.php** — Loads first. Defines database credentials, paths, environment, and loads session/constants.
- **constants.php** — Application-wide constants (ADMIN_ROLE, ACTIVE_STATUS, upload paths, default images).
- **session.php** — Secure session start with httponly + SameSite cookies and periodic ID regeneration.
- **messages.php** — Flash message system for one-time notifications.
- **security.php** — CSRF token generation/validation and input sanitization.
- **functions.php** — Legacy helpers kept for backward compatibility.

### `middlewares/` — Request Middleware
Includable PHP files that perform checks before page content loads:
- **auth.php** — Redirects unauthenticated users to login.
- **admin-only.php** — Shows 403 page for non-admin users.
- **guest.php** — Redirects logged-in users away from login/register pages.

### `pages/` — Frontend Pages
All customer-facing pages. Each page includes `header.php` and `footer.php` for consistent layout. Protected pages use the middleware system.

### `storage/` — Runtime Storage
Prepared directories for logs, cache, and session files. These will be used in future iterations for debugging and performance optimization.

### `templates/emails/` — Email Templates
Beautiful HTML email templates with brand styling. These are structure-only — email sending is not yet implemented.

### `uploads/` — User Uploads
Uploaded images organized by type. The `users/` directory is prepared for future profile picture uploads.

---

## Current Completed Modules

| Module | Status | Description |
|--------|--------|-------------|
| **Authentication** | ✅ Complete | Register, login, logout, password change, profile edit, password reset (structure) |
| **Dashboard** | ✅ Complete | Admin stats overview (product/category/brand/user counts) |
| **Categories** | ✅ Complete | Full CRUD with auto-generated codes, image upload, search, pagination |
| **Brands** | ✅ Complete | Full CRUD with auto-generated codes, logo upload, search, pagination |
| **Products** | ✅ Complete | Full CRUD with auto-generated codes, image upload, category/brand dropdowns, search, pagination |
| **Frontend** | ✅ Complete | Homepage (carousel, categories, brands, products, stats, testimonials), Shop (search, filter, sort, grid/list toggle), Product Details (gallery, share buttons, related products), About, Contact (Google Maps) |
| **Security** | ✅ Complete | Secure sessions (httponly, SameSite, ID regeneration), CSRF protection (structure), middleware system |
| **Error Handling** | ✅ Complete | Custom 403, 404, 500 error pages |

## Future Modules (Structure Ready)

| Module | Status | Description |
|--------|--------|-------------|
| **Shopping Cart** | 🚧 Structure ready | AJAX endpoints + API + page placeholders created |
| **Checkout** | 🚧 Structure ready | API endpoint + page placeholders created |
| **Orders** | 🚧 Structure ready | List + details pages created |
| **Wishlist** | 🚧 Structure ready | AJAX endpoints + API + page placeholder created |
| **Reports** | 📋 Planned | Future admin analytics |
| **Inventory** | 📋 Planned | Future stock management |
| **Analytics** | 📋 Planned | Future sales and traffic analysis |

---

## Brand Colors

| Color | Hex Code | Usage |
|-------|----------|-------|
| Navy | `#001F5B` | Primary brand color, headings, buttons |
| Gold | `#C9A227` | Accent, call-to-action buttons, highlights |
| Green | `#0B6B2F` | Success states, confirmations |
| White | `#FFFFFF` | Backgrounds, cards |
| Light | `#F5F6FA` | Page backgrounds (admin), sections |

---

## Tech Stack

- **PHP 8.3** — Strict types, declare(strict_types=1)
- **MySQL** — InnoDB, utf8mb4, foreign keys
- **PDO** — Prepared statements, EMULATE_PREPARES = false
- **Bootstrap 5** — CDN, responsive grid, components
- **Bootstrap Icons** — CDN, icon library
- **JavaScript** — Vanilla JS for interactive features
- **HTML/CSS** — Semantic HTML, custom brand styles

---

## Getting Started

1. **Clone the project** to your web server root (e.g., `htdocs/champion-liquor-store`).

2. **Create the database**:
   ```sql
   CREATE DATABASE champion_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Import the schema**:
   ```bash
   mysql -u root -p champion_store < database/champion_store.sql
   ```

4. **Configure** `includes/config.php`:
   - Update `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` if needed.
   - Set `ENVIRONMENT` to `'production'` when live.

5. **Set folder permissions**:
   Ensure the web server can write to:
   - `uploads/brands/`
   - `uploads/categories/`
   - `uploads/products/`
   - `uploads/users/`

6. **Access the site** at `http://localhost/champion-liquor-store/`.

7. **Admin panel** at `http://localhost/champion-liquor-store/admin/`.

### Default Admin Login
- **Email**: admin@championliquorstore.com
- **Password**: admin123

---

## Coding Conventions

- **PHP 8.3** with `declare(strict_types=1)` on all files.
- **PDO** with named placeholders (`:name`) and `EMULATE_PREPARES = false`.
- **Constants** over hardcoded strings: use `ADMIN_ROLE` instead of `'admin'`.
- **Comments** explain *why*, not *what*.
- **File structure** follows concern-separated organization.
- **No closing `?>` PHP tag** at end of pure PHP files.

---

## License

Proprietary — Champion Liquor Store Ltd
