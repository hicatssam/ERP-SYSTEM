# Dahab Sweets — نظام إدارة حلويات دهب

A full-stack Laravel 12 management system for Dahab Sweets covering inventory, sales, special cake orders, finance, and reporting.

---

## Requirements

| Tool | Version |
|------|---------|
| PHP | 8.2 or higher |
| Composer | 2.x |
| MySQL | 5.7+ or 8.x (recommended) |

> **No Node.js / npm required.** The frontend is pure Blade + CSS + Vanilla JS — no build step.

---

## Quick Setup (VS Code / Local)

### 1 — Clone / open the project

```bash
cd dahab
```

### 2 — Install PHP dependencies

```bash
composer install
```

### 3 — Create the MySQL database

Log in to MySQL and create the database:

```sql
CREATE DATABASE dahab_sweets CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
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
DB_DATABASE=dahab_sweets
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
php artisan serve
```

Open **http://localhost:8000** in your browser.

---

## Default Login

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

- **Charset:** All tables use `utf8mb4 / utf8mb4_unicode_ci` — required for Arabic text and emoji support.
- **Timezone:** Set to `Asia/Jerusalem`. Change `APP_TIMEZONE` in `.env` if needed.
- **Language:** Arabic RTL throughout. Validation messages in `lang/ar/`.
- **Currency:** ILS (₪) — all monetary columns use `decimal(12,2)`.
- **Strict mode:** MySQL strict mode is enabled (`'strict' => true` in `config/database.php`). If you hit GROUP BY errors on older MySQL 5.7, set `'strict' => false`.
