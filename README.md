# ERP System — نظام إدارة أعمال قابل للتخصيص

A Laravel 12 ERP with configurable business identity, permissions, locations, and modules. Sales, inventory, finance, employees, and reporting are core workflows; cake and bakery workflows can be enabled for relevant businesses.

---

## Requirements

| Tool | Version |
|------|---------|
| PHP | 8.4 or higher (required by the current `composer.lock`) |
| Composer | 2.x |
| Node.js | 22 (frontend build) |
| MySQL | 5.7+ or 8.x (recommended) |

The current frontend build uses Vite. Run `npm install` and `npm run build` for deployment.

---

## Quick Setup (VS Code / Local)

### 1 — Clone / open the project

```bash
cd erp-system
```

### 2 — Install PHP dependencies

```bash
composer install
```

### 3 — Create the MySQL database

Log in to MySQL and create the database:

```sql
CREATE DATABASE erp_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4 — Copy the environment file

```bash
cp .env.example .env
```

### 5 — Set your database credentials in `.env`

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=erp_system
DB_USERNAME=root
DB_PASSWORD=your_password_here
```

### 6 — Generate the application key

```bash
php artisan key:generate
```

### 7 — Run migrations

```bash
php artisan migrate
```

### 8 — Seed the database (roles, admin user, sample data)

```bash
php artisan db:seed
```

### 9 — Start the development server

```bash
npm install
npm run build
php artisan serve
```

Open **http://localhost:8000** in your browser.

---

## Default Login

These seeded credentials are for local development. Change or disable demo accounts before a customer deployment.

| Field    | Value         |
|----------|---------------|
| Username | `admin`       |
| Password | `Admin@2024!` |

---

## Project Structure

```
dahab/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/          # Locations, Employees, Users, Products, Roles, Settings
│   │   │   ├── Auth/           # Login, ChangePassword
│   │   │   ├── Finance/        # Invoices, CashSessions, FinancialPeriods, Dashboard
│   │   │   ├── Inventory/      # Inventory, StockMovements, StockCounts, Requests, Transfers
│   │   │   ├── Reports/        # ReportController
│   │   │   └── Sales/          # Customers, Orders, SpecialCakeOrders
│   │   └── Middleware/
│   ├── Models/                 # 38 Eloquent models
│   ├── Services/               # Business logic (Inventory, Orders, Payments, Invoices…)
│   └── Providers/
├── database/
│   ├── migrations/             # 26 migrations
│   └── seeders/                # DatabaseSeeder — branches, roles, admin user, products
├── public/
│   └── assets/
│       ├── css/app.css         # RTL gold design system
│       ├── js/app.js           # Sidebar, toasts, modals
│       └── images/logo.png
├── resources/views/
│   ├── layouts/app.blade.php   # Main sidebar + topbar shell
│   ├── auth/                   # login, change-password
│   ├── admin/                  # locations, employees, users, products, categories, roles, settings
│   ├── inventory/              # stock counts, requests, transfers, movements
│   ├── sales/                  # orders, cake-orders, customers
│   ├── finance/                # invoices, cash-sessions, periods, dashboard
│   ├── reports/
│   └── profile/
└── routes/web.php
```

---

## Key Packages

| Package | Purpose |
|---------|---------|
| `spatie/laravel-permission` | Role-based access control (12 built-in roles) |
| `maatwebsite/excel` | Excel export for reports |
| `mpdf/mpdf` | PDF generation for invoices |

---

## Roles & Permissions

The seeder creates 12 roles:

| Role | Description |
|------|-------------|
| Admin | Full unrestricted access (Gate::before bypass) |
| Branch Manager | Full branch-level access |
| Sales | Orders, customers, invoices |
| Cashier | POS + cash sessions |
| Inventory Manager | Full inventory control |
| Warehouse | Stock counts and transfers |
| Finance | Financial dashboard, periods, invoices |
| Production | Special cake orders |
| Accountant | Reports + financial dashboard |
| HR Manager | Employee management |
| Reports Viewer | Read-only reports |
| Viewer | Read-only dashboard |

---

## VS Code Extensions (Recommended)

- **PHP Intelephense** — PHP intellisense
- **Laravel Blade Snippets** — Blade syntax highlighting
- **Laravel Artisan** — Run artisan commands from the command palette
- **MySQL** (by cweijan) — Browse and query your MySQL database inside VS Code

---

## Notes

### AI assistant (read-only sprint)

For Groq's limited free API tier, create a key in the [Groq console](https://console.groq.com/keys) and put these values in the installation's real `.env` file (never `.env.example`):

```dotenv
ERP_AI_PROVIDER=groq
GROQ_API_KEY=your-own-groq-key
```

The default Groq model is `openai/gpt-oss-20b`, which supports strict structured JSON output. `ERP_AI_GROQ_MODEL` can override it with another Groq model that supports strict schema output. The free plan has per-organization request and token limits, so each customer installation should use that customer's own key; it is not an unlimited production allocation. For OpenAI instead, set `ERP_AI_PROVIDER=openai` and set `OPENAI_API_KEY` (or `ERP_AI_API_KEY` to override it), optionally with `ERP_AI_MODEL` (default: `gpt-5-mini`).

After updating `.env`, run `php artisan config:clear` or rebuild the configuration cache. The **المساعد الذكي** page appears in the ERP sidebar and uses the customer's `system_name` and theme settings. Never place a live key in frontend assets, `.env.example`, or git. A key exposed in chat or source control must be revoked and replaced.

The model receives the user's question and returns a structured intent. The server runs only predefined read queries, checks the user's existing permissions and branch scope again, and shows suggested questions only for enabled modules. ERP records are not sent to the model. This sprint does not create, confirm, edit, or delete records. With the current architecture, deploy a separate installation and database per customer; shared-database SaaS requires tenant isolation before launch.

Assistant permissions are managed from **Users → Edit user → Assistant permissions**. An administrator can disable the assistant for one account, inherit the user's normal ERP permissions, or select a custom topic allowlist (cake orders, inventory, sales, transfers, reports, attendance, payroll and priorities). The allowlist can only reduce existing ERP access; it can never grant access to a module or another branch. The system settings **المساعد الذكي** group controls the global switch, suggested questions, read-only mode and the maximum number of result cards.

---

- **Charset:** All tables use `utf8mb4 / utf8mb4_unicode_ci` — required for Arabic text and emoji support.
- **Timezone:** Set to `Asia/Jerusalem`. Change `APP_TIMEZONE` in `.env` if needed.
- **Language:** Arabic RTL throughout. Validation messages in `lang/ar/`.
- **Currency:** ILS (₪) — all monetary columns use `decimal(12,2)`.
- **Strict mode:** MySQL strict mode is enabled (`'strict' => true` in `config/database.php`). If you hit GROUP BY errors on older MySQL 5.7, set `'strict' => false`.
