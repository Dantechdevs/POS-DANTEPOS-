# DantePOS

[![Version](https://img.shields.io/badge/version-6.11-blue)](https://github.com/Dantechdevs/DantePOS)
[![Made in Kenya](https://img.shields.io/badge/made%20in-Kenya-006600)](https://dantechdevelopers.com)
[![License](https://img.shields.io/badge/license-All%20Rights%20Reserved-red)](#license)

**DantePOS** is a Point of Sale and business management system built for Kenyan retail, wholesale, restaurants, salons and service businesses. It combines sales, inventory, purchasing, expenses and reporting with **M-Pesa payments** and Kenya-specific tax and receipt workflows.

**Author:** [Dantechdevs](https://github.com/Dantechdevs) (DanTech Developers)
**Copyright:** © 2026 All rights reserved

---

## Table of Contents

- [Features at a Glance](#features-at-a-glance)
- [Kenya-Specific Features](#kenya-specific-features)
- [M-Pesa Integration](#m-pesa-integration)
- [Getting Started](#getting-started)
- [Core Modules](#core-modules)
- [Wholesale & Credit Sales](#wholesale--credit-sales)
- [Hardware Support](#hardware-support)
- [Security](#security)
- [Technical Details](#technical-details)
- [Module Development & Addons](#module-development--addons)
- [Roadmap](#roadmap)
- [FAQ](#faq)
- [Release Notes](#release-notes)
- [Contact & Support](#contact--support)
- [License](#license)

---

## Features at a Glance

- Retail and wholesale POS with barcode scanning and thermal receipt printing
- **M-Pesa STK Push** at checkout, with transaction tracking
- Inventory, purchases, stock transfers and stock adjustments
- Multi-branch / multi-location support
- Customers, suppliers and credit sales
- Expenses, reports and exports (PDF / Excel)
- Role-based access control
- Backups and notifications
- Modular structure for custom addons

---

## Kenya-Specific Features

| Area | Status | Notes |
|------|--------|-------|
| Currency (KES / KSh) | Available | Default currency across the dashboard, POS and reports |
| M-Pesa STK Push | Available | See [M-Pesa Integration](#m-pesa-integration) |
| VAT (16%) with inclusive / exclusive pricing | Available via tax settings | Configure rates under Tax Settings |
| Customer KRA PIN on invoices | Planned | See [Roadmap](#roadmap) |
| KRA eTIMS e-invoicing | Planned | Confirm current KRA requirements before go-live |
| M-Pesa Till / Paybill reconciliation (C2B) | Planned | Match real payments to sales automatically |
| Phone number normalisation (`2547XXXXXXXX`) | Planned | For M-Pesa and SMS |
| SMS receipts and reminders (Africa's Talking) | Planned | Receipts, credit reminders, low-stock alerts |
| Swahili interface labels | Planned | English and Swahili |

---

## M-Pesa Integration

DantePOS integrates with Safaricom's **Daraja API**.

**How it works**

1. The cashier selects **M-Pesa** on the payment screen and enters the customer's phone number.
2. DantePOS sends an **STK Push** prompt to the customer's phone.
3. The sale stays **pending** until Safaricom's callback confirms the payment.
4. On confirmation, the M-Pesa receipt number is stored against the payment and the sale is completed.

**Components**

- `app/Services/MpesaService.php` for Daraja authentication and STK Push requests
- `app/Http/Controllers/MpesaController.php` for push requests, callbacks and status checks
- `app/Models/MpesaTransaction.php` and the `mpesa_transactions` table for transaction records
- `public/plugins/mpesa/mpesa.js` for the POS checkout script

**Configuration**

Add your Daraja credentials to `.env` (never commit this file):

```env
MPESA_CONSUMER_KEY=
MPESA_CONSUMER_SECRET=
MPESA_PASSKEY=
```

See `.env.example` for the complete list of M-Pesa variables (shortcode, environment, callback URL).

**Testing locally**

Daraja needs a public HTTPS callback URL. Use a tunnel such as [ngrok](https://ngrok.com), set the tunnel URL as your callback in `.env`, then run:

```bash
php artisan config:clear
```

Do not commit the ngrok binary.

**Going live checklist**

- [ ] Switch `MPESA` environment from sandbox to production
- [ ] Use your production Till / Paybill shortcode and passkey
- [ ] Register a public HTTPS callback URL
- [ ] Confirm the callback route is excluded from CSRF and authentication
- [ ] Test a real small-value payment end to end

---

## Getting Started

**Requirements**

- PHP and Composer (see `composer.json` for the supported versions)
- MySQL / MariaDB
- Node.js and npm (for assets)
- XAMPP, Laragon or any LAMP/LEMP stack

**Installation**

```bash
git clone https://github.com/Dantechdevs/DantePOS.git
cd DantePOS
composer install
cp .env.example .env
php artisan key:generate
```

Configure database, mail and M-Pesa credentials in `.env`, then:

```bash
php artisan migrate --seed
npm install
npm run dev
php artisan serve
```

Open `http://127.0.0.1:8000` in your browser.

---

## Core Modules

### Business Management
Business details, logo, multi-branch setup, default currency, business hours and system preferences.

### User Management
Employees, staff and roles. Role-based permissions control what each user can see and do.

### Contacts
Customers, suppliers and vendors, with purchase and sales history per contact.

### Products
SKU, barcode, price and category. Variations, stock levels and reorder points.

### Purchases
Supplier purchases and purchase orders, pending deliveries and automatic stock updates.

### Sales
Invoices and receipts. Cash, card and M-Pesa payments. Taxes, discounts and promotions.

### Stock Transfers and Adjustments
Move stock between branches or warehouses, and correct stock for losses or counting errors. Every change is logged with a reason and timestamp.

### Tax Settings
Configurable tax rates applied automatically to sales and purchases.

### Payment Accounts
Bank accounts, M-Pesa Till / Paybill and other payment accounts, with default accounts per transaction type.

### Expenses
One-time and recurring expenses by category.

### Reports
Sales, purchases, stock, expenses and user activity reports. Filter by date, product, branch or user. Export to PDF or Excel.

### Backups
Scheduled database backups, stored locally or on cloud storage.

### Notifications
In-app alerts for sales, low stock and system events. Email and SMS are supported where configured.

---

## Wholesale & Credit Sales

DantePOS supports retail and wholesale in the same system:

- Separate retail and wholesale price levels per product
- Credit customers with credit limits and outstanding balances
- Customer statements and payment history
- Quotations, invoices and delivery notes
- Partial payments, including split payments (for example part cash, part M-Pesa)

---

## Hardware Support

- Thermal receipt printers (58mm and 80mm)
- Barcode scanners
- Cash drawers
- Standard A4 invoice printing

---

## Security

- Role-based access control for every module
- Credentials stored in `.env`, never in the repository
- Callback endpoints should validate the transaction before marking a sale paid, and ignore duplicate callbacks
- Keep `APP_DEBUG=false` in production
- Run behind HTTPS in production
- Back up regularly and test restores

Found a vulnerability? Please contact the author directly rather than opening a public issue.

---

## Technical Details

- **Backend:** PHP / Laravel, MySQL
- **Frontend:** JavaScript, Bootstrap, Blade templates
- **Assets:** Laravel Mix (`webpack.mix.js`)
- **Structure:** modular, so features can be added as separate modules

---

## Module Development & Addons

Features are organised so they can be extended without changing core files. Place new functionality in its own module or service class, register its routes and migrations, and keep configuration in `config/` rather than hardcoding values.

---

## Roadmap

**High priority**

- [ ] Offline mode: PWA with a local sale queue that syncs when the connection returns
- [ ] M-Pesa C2B Till / Paybill reconciliation
- [ ] Idempotent M-Pesa callback handling and a pending-payments screen
- [ ] Per-business M-Pesa credentials (encrypted) for multi-business setups
- [ ] KRA eTIMS integration and KRA PIN on invoices

**Planned**

- [ ] SMS receipts, credit reminders and low-stock alerts (Africa's Talking)
- [ ] Phone number normalisation to `2547XXXXXXXX`
- [ ] Loyalty points tied to customer phone numbers
- [ ] Swahili language support
- [ ] Accounting exports (QuickBooks, Zoho Books, CSV)
- [ ] Daily Z-report and cashier shift reports
- [ ] Supplier purchase orders with reorder suggestions
- [ ] Online ordering and WhatsApp order intake
- [ ] Mobile app (iOS and Android)

---

## FAQ

**How do I integrate M-Pesa?**
Add your Daraja credentials to `.env`, run the M-Pesa migrations, and configure your Till or Paybill. See [M-Pesa Integration](#m-pesa-integration).

**Can I test M-Pesa without real money?**
Yes. Use the Daraja sandbox credentials and a public tunnel URL for callbacks.

**How do I restore a backup?**
Use the backup module under system settings.

**How do I create users and assign roles?**
Use User Management and assign role-based permissions.

**Does it work without internet?**
Offline mode is on the roadmap. Currently the POS needs a connection to the server.

---

## Release Notes

- **V6.11:** Latest release. Includes the M-Pesa STK Push integration and Kenya-focused updates.
- Earlier versions: M-Pesa, reporting, expense management and module improvements.

---

## Contact & Support

For setup, consultancy or support:

- **WhatsApp / Phone:** +254712328150
- **Telegram:** @dantechdevs
- **Website:** [dantechdevelopers.com](https://dantechdevelopers.com)

---

## License

© 2026 Dantechdevs. All rights reserved.