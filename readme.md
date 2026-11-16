# DantePOS

[![Version](https://img.shields.io/badge/version-2.1-blue)](https://github.com/Dantechdevs/POS-DANTEPOS-)
[![Laravel](https://img.shields.io/badge/laravel-9.x-red)](https://laravel.com)
[![Made in Kenya](https://img.shields.io/badge/made%20in-Kenya-006600)](https://dantechdevelopers.com)
[![License](https://img.shields.io/badge/license-All%20Rights%20Reserved-red)](#license)

**DantePOS** is a point of sale and business management system built for Kenyan retail, wholesale, restaurants, salons and service businesses. It combines sales, inventory, purchasing, expenses and reporting with **M-Pesa payments**, **KRA eTIMS submission**, **SMS (Africa's Talking)**, gift cards, festive-season loyalty points and an offline-tolerant till.

**Author:** [Dantechdevs](https://github.com/Dantechdevs) (DanTech Developers)
**Copyright:** © 2026. All rights reserved.

---

## Table of Contents

- [Features](#features)
- [Kenya-Specific Features](#kenya-specific-features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Core Modules](#core-modules)
- [Payments](#payments)
- [KRA eTIMS](#kra-etims)
- [SMS and Notifications](#sms-and-notifications)
- [Festive Season Points](#festive-season-points)
- [Gift Cards](#gift-cards)
- [Products and Smart Scanner](#products-and-smart-scanner)
- [Stocktake and Inventory Control](#stocktake-and-inventory-control)
- [Wholesale and Credit Sales](#wholesale-and-credit-sales)
- [Multi-Device Sync and Offline Till](#multi-device-sync-and-offline-till)
- [Receipts and Hardware](#receipts-and-hardware)
- [Deployment](#deployment)
- [Scheduled Tasks and Commands](#scheduled-tasks-and-commands)
- [Backups](#backups)
- [Troubleshooting](#troubleshooting)
- [Security](#security)
- [Technical Details](#technical-details)
- [Development Workflow](#development-workflow)
- [Roadmap](#roadmap)
- [FAQ](#faq)
- [Release Notes](#release-notes)
- [Contact and Support](#contact-and-support)
- [License](#license)

---

## Features

- Retail and wholesale POS with barcode scanning, thermal receipt printing and several receipt layouts
- **M-Pesa STK Push** at checkout, with transaction tracking, reconciliation and till-number settings per business
- **KRA eTIMS** invoice queue and submission
- **SMS** receipts, debt reminders, low-stock alerts and a daily owner summary (Africa's Talking)
- **Smart Scanner**: scan a barcode to register a product or add stock, with online product lookup
- **Gift cards** that can be sold and redeemed at the till
- **Festive-season loyalty points** with a season window and a yearly expiry date
- **Stocktake**, reorder suggestions, expiry watch and stock adjustments
- Inventory, purchases, purchase orders and requisitions, stock transfers
- Multi-branch / multi-location support
- Customers, suppliers, credit sales, debtors and payables ageing
- Expenses, accounts, margin reports and many other reports, with export to PDF / Excel
- Role-based access control
- Verified, compressed database backups with a pre-flight health check (`dantepos:check`)
- Primary / satellite sales sync and an offline sales queue
- Modular structure for custom addons

---

## Kenya-Specific Features

| Area | Status | Notes |
|------|--------|-------|
| Currency (KES / KSh) | Available | Default currency across the dashboard, POS and reports |
| M-Pesa STK Push | Available | See [Payments](#payments) |
| M-Pesa reconciliation and payment guards | Available | Prevents unverified or duplicate M-Pesa payments from completing a sale |
| VAT (16%) with inclusive / exclusive pricing | Available | Configure rates under Tax Settings |
| KRA eTIMS | Available, needs KRA credentials | Queued sales are submitted with `etims:submit`. Test with the KRA sandbox before go-live and confirm current KRA requirements |
| SMS via Africa's Talking | Available, needs credentials | Receipts, credit reminders, low-stock alerts, owner summary |
| Loyalty points with seasonal rules | Available | See [Festive Season Points](#festive-season-points) |
| Gift cards | Available | See [Gift Cards](#gift-cards) |
| Customer KRA PIN on invoices | Planned | See [Roadmap](#roadmap) |
| M-Pesa Till / Paybill C2B auto-matching | Planned | Reconciliation screens exist; fully automatic matching is on the roadmap |
| Phone number normalisation (`2547XXXXXXXX`) | Planned | For M-Pesa and SMS |
| Swahili interface labels | Planned | English and Swahili |

---

## Requirements

- PHP 8.0 or newer and Composer
- Laravel 9.x (installed by Composer)
- MySQL / MariaDB
- A web server (XAMPP, Laragon, or any LAMP/LEMP stack)
- A cron daemon in production, see [Deployment](#deployment)

Front-end assets (CSS, fonts, JavaScript) are committed under `public/`, so **Node.js and npm are not needed** to run or deploy the application.

---

## Installation

```bash
git clone https://github.com/Dantechdevs/POS-DANTEPOS-.git
cd POS-DANTEPOS-
composer install
cp .env.example .env
php artisan key:generate
```

Set the database credentials in `.env`, then:

```bash
php artisan migrate --seed
php artisan serve
```

Open `http://127.0.0.1:8000` in your browser. If the first-run installer appears, follow its steps.

After installing, run the health check:

```bash
php artisan dantepos:check
```

It checks the database, migrations, permissions, configuration, broken code references, backups and the scheduler.

---

## Configuration

All secrets live in `.env`, which must never be committed. `.env.example` lists every variable. DantePOS adds these groups to the base application settings:

| Group | Variables | Purpose |
|-------|-----------|---------|
| Application | `APP_ENV`, `APP_DEBUG`, `APP_URL`, `APP_TIMEZONE` | Use `APP_DEBUG=false` in production |
| Database | `DB_*` | MySQL / MariaDB connection |
| M-Pesa | `MPESA_ENV`, `MPESA_CONSUMER_KEY`, `MPESA_CONSUMER_SECRET`, `MPESA_SHORTCODE`, `MPESA_PASSKEY`, `MPESA_TRANSACTION_TYPE`, `MPESA_CALLBACK_URL`, `MPESA_TILL_NUMBER` | Daraja credentials and callback |
| SMS | `AT_ENV`, `AT_USERNAME`, `AT_API_KEY`, `AT_SENDER_ID`, `SMS_RECEIPT_ENABLED`, `SMS_RECEIPT_MODE`, `SMS_PAY_INSTRUCTIONS`, `OWNER_PHONES` | Africa's Talking and SMS behaviour |
| eTIMS | `ETIMS_ENABLED`, `ETIMS_ENV`, `ETIMS_BASE_URL`, `ETIMS_TIN`, `ETIMS_BRANCH_ID`, `ETIMS_CMC_KEY`, `ETIMS_ITEM_CLASS`, `ETIMS_ZERO_CLASS` | KRA eTIMS connection |
| Backups | `BACKUP_DISK`, `BACKUP_MYSQLDUMP`, `BACKUP_KEEP_DAYS`, `BACKUP_COPY_PATH` | Backup storage and retention |
| Till | `PRICE_GUARD_MODE`, `OFFLINE_TILL_METHOD`, `OFFLINE_TILL_LABEL`, `GIFT_CARD_METHOD`, `GIFT_CARD_PREFIX` | Till guards, offline payment method and gift card payment method |
| Sync | `DANTE_ROLE`, `DANTE_DEVICE_ID`, `DANTE_DEVICE_TOKEN`, `DANTE_SYNC_URL` | Primary / satellite sync |

Other gateway keys inherited from the base platform (Stripe, PayPal, Paystack, Pesapal, MyFatoorah, Razorpay, Flutterwave, AWS, Google Maps) are optional and unused unless you enable them.

Business-level settings (business details, tax, reward points, payment channels, invoice layouts) are managed in the app under **Settings**. Application settings added by DantePOS are in `config/dante.php`, `config/dantepos_mpesa.php`, `config/etims.php`, `config/africastalking.php`, `config/sync.php`, `config/credit.php`, `config/pricing.php` and `config/summary.php`.

---

## Core Modules

| Module | What it covers |
|--------|----------------|
| Business Management | Business details, logo, locations, currency, time zone, preferences |
| User Management | Users, roles and permissions |
| Contacts | Customers, suppliers, customer groups, ledgers and statements |
| Products | SKU, barcode, units, brands, categories, variations, price groups, labels, import |
| Purchases | Purchases, purchase orders, requisitions, returns |
| Sales | POS, invoices, sales orders, quotations, returns, commission agents, bookings |
| Stock | Transfers, adjustments, stocktakes, reorder and expiry watch |
| Accounts | Payment accounts, account types and account reports |
| Expenses | One-time and recurring expenses by category |
| Cash Register | Opening and closing the register, cash movements |
| Reports | Sales, purchases, stock, expenses, margin, debtors and payables ageing, user activity |
| Notifications | In-app alerts and notification templates |
| Restaurant | Tables, modifiers and types of service (optional) |

---

## Payments

DantePOS integrates with Safaricom's **Daraja API**.

**How it works**

1. The cashier selects **M-Pesa** on the payment screen and enters the customer's phone number.
2. DantePOS sends an **STK Push** prompt to the customer's phone.
3. The sale stays **pending** until Safaricom's callback confirms the payment.
4. On confirmation, the M-Pesa receipt number is stored against the payment and the sale is completed.

**Components**

- `app/Services/MpesaService.php`: Daraja authentication and STK Push requests
- `app/Services/MpesaPaymentGuard.php`, `MpesaSaleGuard.php`, `MpesaDueGuard.php`: checks that an M-Pesa payment is verified before a sale or due payment completes
- `app/Http/Controllers/MpesaController.php` and `MpesaReconciliationController.php`: push requests, callbacks, status checks and reconciliation
- `app/Services/PaymentChannelService.php` and the `dantepos_payment_channels`, `dantepos_bank_accounts` and `mpesa_settings` tables: payment channels, bank accounts and per-business M-Pesa settings
- `public/plugins/mpesa/`: POS checkout script

**Configuration**

```env
MPESA_ENV=sandbox
MPESA_CONSUMER_KEY=
MPESA_CONSUMER_SECRET=
MPESA_SHORTCODE=
MPESA_PASSKEY=
MPESA_CALLBACK_URL=
MPESA_TILL_NUMBER=
```

**Testing locally**

Daraja needs a public HTTPS callback URL. Use a tunnel such as [ngrok](https://ngrok.com), set the tunnel URL as the callback in `.env`, then run `php artisan config:clear`. Do not commit the tunnel binary.

**Going live checklist**

- [ ] Switch `MPESA_ENV` from sandbox to the production value
- [ ] Use your production Till / Paybill shortcode, till number and passkey
- [ ] Register a public HTTPS callback URL
- [ ] Confirm the callback route is excluded from CSRF and authentication
- [ ] Test a real small-value payment end to end

---

## KRA eTIMS

- Sales are queued in the `etims_invoices` table by `EtimsService`, and `EtimsInvoiceController` shows their status.
- `php artisan etims:submit` submits queued sales to KRA. It is **not** in the default schedule: add a cron entry for it once your credentials are tested.
- Configure the `ETIMS_*` variables and `config/etims.php`.
- Test with `ETIMS_ENV` set to the sandbox before submitting real sales, and confirm the current KRA requirements.

---

## SMS and Notifications

SMS uses **Africa's Talking** (`AT_*` variables). Until credentials are set, no SMS is sent.

| Feature | Command / setting | Notes |
|---------|-------------------|-------|
| SMS receipts | `SMS_RECEIPT_ENABLED`, `SMS_RECEIPT_MODE` | Logged in the `sms_receipts` table |
| Debt reminders | `php artisan debts:remind` | Texts customers their overdue credit balances. Options: `--business`, `--grace`, `--every` (default 7 days between reminders), `--min`, `--dry-run` |
| Payment reminders | `pos:autoSendPaymentReminder` | Uses the notification templates |
| Daily owner summary | `reports:daily-summary` | Sends sales, collections, debts and alerts to `OWNER_PHONES` |
| Low-stock alerts | `report:low-stock` | Options: `--dry-run`, `--to` |

Use `--dry-run` to see exactly what would be sent before enabling any scheduled SMS job.

---

## Festive Season Points

Reward customers during a festive season, with points that expire on a fixed date.

**Settings** (Business Settings, Reward Points)

| Setting | What it does |
|---------|--------------|
| Enable reward points | Turns the points feature on, including redemption at the till |
| Points name | Label shown to customers and on receipts |
| Points per 1,000 spent | Points earned per KSh 1,000. If empty, the "Amount spent for one point" rule is used |
| Award points only during the festive season | Points are earned only for sales dated inside the season window |
| Season starts / ends | The earning window (inclusive) |
| Points expire on | Points are valid through this date |
| Max points per order | Optional cap on points earned in one sale |
| Redemption settings | Value of a point, minimum order and minimum points to redeem |

**Behaviour**

- Points are earned on final sales to registered customers. The walk-in customer earns nothing.
- A sale's points follow its transaction date, so editing a sale inside the season keeps its points.
- On the day after the expiry date, `points:expire-season` clears **all** of each customer's remaining points and records the amount in `total_rp_expired`. Set the expiry date again each year.
- An expiry date more than 60 days in the past is treated as a typing mistake and skipped.
- Receipts show points earned, the customer's balance and an "expires" line. In the **Rahisi** layout these are separate lines; in other layouts they are added to the footer text.

```bash
php artisan points:expire-season --dry-run   # show what would expire, change nothing
php artisan points:expire-season             # run the expiry now (also scheduled daily at 00:10)
```

Run the dry run a few days before the expiry date to see how many customers and points will be cleared.

---

## Gift Cards

- Gift cards are managed under **Gift Cards** (`/gift-cards`) and can be redeemed at the till through a dedicated payment method (`GIFT_CARD_METHOD`, `GIFT_CARD_PREFIX`).
- Code lives in `GiftCardController`, `app/Services/GiftCardService.php`, `app/Services/GiftCardTill.php` and `public/plugins/giftcard/`. Data is in the `gift_cards` and `gift_card_transactions` tables.
- After deploying, make sure the gift card migrations have run and that the service files above are on the server. A missing file shows as a `500` error on the Gift Cards page, and `php artisan dantepos:check` reports broken code references.

---

## Products and Smart Scanner

**Smart Scanner** (`Products, Smart Scanner`) speeds up stocking:

1. Scan a barcode.
2. If the product exists, the card shows its stock and prices. Enter how many you are adding, the buying price and the selling price, and press **Add stock**.
3. If it does not exist, the system looks it up in online barcode databases, and you confirm the name, prices and stock. A product name can also be read from a photo in the browser.
4. Stock is recorded as an opening-stock entry at the buying price, so reports and FIFO costing stay correct.

Safeguards:

- A selling price of 0 is rejected when adding stock, and a zero buying price asks for confirmation.
- The **Add Product** page rejects a selling price of 0 and has a **Stock you have** box, so stock can be saved with the product.

Products can also be imported from a spreadsheet, and prices can be updated in bulk under **Update Price**. `PriceGuard` and the `PRICE_GUARD_MODE` setting control price checks at the till.

---

## Stocktake and Inventory Control

- **Stocktake:** count stock by location and post the differences as adjustments (`stocktakes`, `stocktake_lines`).
- **Reorder:** reorder suggestions based on alert quantities.
- **Expiry watch:** products nearing expiry.
- **Stock transfers and adjustments:** every change is logged with a reason and timestamp.

---

## Wholesale and Credit Sales

- Separate retail and wholesale price levels per product, and selling price groups
- Credit customers with credit limits and outstanding balances
- Debtors and payables ageing reports, customer statements, and ledger discounts
- Quotations, invoices and delivery notes
- Partial payments, including split payments (for example part cash, part M-Pesa)

---

## Multi-Device Sync and Offline Till

- `DANTE_ROLE` sets a device as **primary** or **satellite**. A satellite queues its sales in `sync_outbox`, and `sync:push` uploads them to the primary server (`DANTE_SYNC_URL`, `DANTE_DEVICE_TOKEN`). The primary turns them into real sales with `sync:apply`.
- Register a satellite device with `php artisan sync:register-device "Shop PC"`, and check connectivity with `sync:ping`. `sync:status` shows the role, queue sizes and connected devices.
- The **offline till** records sales that were taken while the connection was down (`offline_sales`, `OfflinePosController`, `OFFLINE_TILL_METHOD`).
- A full browser-based (PWA) offline mode is on the [roadmap](#roadmap).

---

## Receipts and Hardware

- Thermal receipt printers (58mm and 80mm) and standard A4 invoice printing
- Barcode scanners and cash drawers
- Receipt layouts, chosen per business location under **Invoice Settings**: Classic, Columnize Taxes, Delivery Note, Detailed, Elegant, Elegant Modified, English-Arabic, Packing Slip, **Rahisi**, Slim and Slim 2
- **Rahisi** is a narrow till-roll receipt with a points block, footer text and a Code 128 barcode of the receipt number

---

## Deployment

1. Back up the database first: `php artisan db:backup`.
2. Pull the code and install dependencies:
   ```bash
   git pull origin develop
   composer install --no-dev --optimize-autoloader
   php artisan migrate
   php artisan optimize:clear
   ```
3. Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`.
4. Serve over HTTPS.
5. Add the cron entries in [Scheduled Tasks](#scheduled-tasks-and-commands).
6. Run `php artisan dantepos:check` and fix anything it reports.
7. Test one real sale, one M-Pesa payment (if used) and one receipt print.

Check `php artisan migrate --pretend` before migrating a live server, so you see exactly what will run.

---

## Scheduled Tasks and Commands

The schedule is defined in `app/Console/Kernel.php`. Running the Laravel scheduler every minute starts every job below:

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Instead of the scheduler, you can add individual cron lines for only the jobs you want. For example, on a server where SMS is not yet connected:

```cron
10 0 * * * cd /path/to/app && php artisan points:expire-season >> /dev/null 2>&1
0 21 * * * cd /path/to/app && php artisan db:backup >> /dev/null 2>&1
```

| Command | Schedule | Description |
|---------|----------|-------------|
| `sync:push` | Every minute | Upload queued sales from a satellite to the primary server |
| `points:expire-season` | Daily 00:10 | Clears points the day after the expiry date |
| `backup:clean` | Daily 01:00 | Removes old backups |
| `backup:run` | Daily 01:30 | Full backup |
| `db:backup` | Daily 21:00 | Verified, compressed database dump |
| `pos:generateRecurringExpense` | Daily 02:00 | Creates recurring expenses |
| `pos:generateSubscriptionInvoices` | Daily 23:30 | Creates subscription invoices |
| `pos:updateRewardPoints` | Daily 23:45 | Built-in reward points expiry by period |
| `report:low-stock` | Daily 07:30 | SMS low-stock alerts |
| `pos:autoSendPaymentReminder` | Daily 08:00 | Payment reminders |
| `debts:remind` | Daily 09:00 | SMS overdue credit balances |
| `reports:daily-summary` | Daily 20:00 | SMS the owner a daily summary |

Commands not in the schedule: `etims:submit`, `sync:apply`, `sync:ping`, `sync:status`, `sync:register-device`, `dantepos:check`, `pos:mapPurchaseSell`.

Commands that send SMS support `--dry-run`. Use it before enabling them.

---

## Backups

- `php artisan db:backup` writes a compressed, verified `.sql.gz` file to `storage/app/backups` and prunes old files (`BACKUP_KEEP_DAYS`). `BACKUP_COPY_PATH` can copy each file to a second location.
- `backup:run`, `backup:list`, `backup:clean` and `backup:monitor` come from the backup package and are configured in `config/backup.php`.
- Check the free disk space regularly, copy backups off the server, and test a restore before relying on them.

---

## Troubleshooting

| Symptom | What to do |
|---------|------------|
| A page shows `500 Server Error` | Read the newest entry in `storage/logs/laravel.log`. Then run `php artisan dantepos:check` for missing migrations, permissions or files |
| A new page crashes only on the live server | The code or a migration is not deployed. Compare `git log` on both machines and run `php artisan migrate --pretend` |
| Changes do not appear | `php artisan optimize:clear`, and Ctrl+F5 in the browser |
| Points do not appear on receipts | Check that points are enabled, the sale is to a registered customer, and the location uses a layout that prints them (Rahisi shows separate lines) |
| M-Pesa sales stay pending | Check the callback URL is public HTTPS and excluded from CSRF, and look at the reconciliation screen |
| Products sell at KSh 0 | Products imported without prices have a selling price of 0. Load prices under **Update Price** or with the bulk import |
| Scheduled jobs do not run | Confirm the cron entry exists with `crontab -l` and that `php` points to the right binary |

---

## Security

- Role-based access control for every module
- Credentials stored in `.env`, never in the repository
- Callback endpoints validate the transaction before marking a sale paid, and M-Pesa guards ignore unverified or duplicate payments
- Keep `APP_DEBUG=false` in production, and run behind HTTPS
- Back up regularly and test restores

Found a vulnerability? Please contact the author directly rather than opening a public issue.

---

## Technical Details

- **Backend:** PHP 8 / Laravel 9, MySQL
- **Frontend:** JavaScript, Bootstrap, Blade templates, with built assets committed under `public/`
- **Structure:** controllers in `app/Http/Controllers`, DantePOS services in `app/Services`, scheduled commands in `app/Console/Commands`, configuration in `config/`, migrations in `database/migrations`
- **Key tables added by DantePOS:** `mpesa_transactions`, `mpesa_settings`, `dantepos_settings`, `dantepos_payment_channels`, `dantepos_payment_config`, `dantepos_bank_accounts`, `etims_invoices`, `sms_receipts`, `debt_reminder_logs`, `gift_cards`, `gift_card_transactions`, `stocktakes`, `stocktake_lines`, `offline_sales`, `sync_devices`, `sync_inbox`, `sync_outbox`

---

## Development Workflow

- Do work on a `feature/...` branch, merge it into `develop`, and deploy from `develop`.
- Keep migrations safe to re-run: check that a table or column exists before creating it.
- Keep secrets out of commits, and do not commit environment-specific changes.
- Before deploying, run `php artisan dantepos:check` locally and test the change on a development copy.

---

## Roadmap

**High priority**

- [ ] Browser-based offline mode (PWA) with a local sale queue
- [ ] Fully automatic M-Pesa Till / Paybill (C2B) matching
- [ ] Per-business M-Pesa credentials (encrypted) for multi-business setups
- [ ] Customer KRA PIN on invoices, and a scheduled eTIMS submission

**Planned**

- [ ] Phone number normalisation to `2547XXXXXXXX`
- [ ] Customer-facing points balance (SMS or link)
- [ ] Swahili language support
- [ ] Accounting exports (QuickBooks, Zoho Books, CSV)
- [ ] Daily Z-report and cashier shift reports
- [ ] Online ordering and WhatsApp order intake
- [ ] Mobile app (iOS and Android)

**Done**

- [x] M-Pesa STK Push, reconciliation and payment guards
- [x] KRA eTIMS queue and submission
- [x] SMS receipts, reminders and summaries (needs Africa's Talking credentials)
- [x] Seasonal loyalty points with expiry
- [x] Smart Scanner, stocktake and gift cards
- [x] Primary / satellite sync and an offline sales queue

---

## FAQ

**How do I integrate M-Pesa?**
Add your Daraja credentials to `.env`, run the migrations, and configure your Till or Paybill. See [Payments](#payments).

**Can I test M-Pesa without real money?**
Yes. Use the Daraja sandbox credentials and a public tunnel URL for callbacks.

**How do I set up festive points?**
Business Settings, Reward Points. See [Festive Season Points](#festive-season-points).

**Why didn't points expire on the expiry date?**
Check that the cron entry for `points:expire-season` (or the scheduler) is running. The command runs on the day after the expiry date.

**How do I add stock quickly?**
Use **Smart Scanner**: scan the barcode, enter the quantity, buying price and selling price. See [Products and Smart Scanner](#products-and-smart-scanner).

**How do I restore a backup?**
Import the `.sql.gz` file from `storage/app/backups` into the database, after taking a fresh backup of the current state.

**How do I create users and assign roles?**
Use User Management and assign role-based permissions.

**Does it work without internet?**
The POS needs a connection to its server. Satellite devices and the offline till can queue sales and sync them later; a full offline PWA is on the roadmap.

---

## Release Notes

- **V2.1:** Smart Scanner, stocktake, gift cards, KRA eTIMS queue, SMS notifications, M-Pesa reconciliation and guards, festive-season points with yearly expiry, the Rahisi receipt layout, primary / satellite sync, the `dantepos:check` health check, and price guards on Add Product and Smart Scanner.
- Earlier versions: M-Pesa, reporting, expense management and module improvements.

---

## Contact and Support

For setup, consultancy or support:

- **WhatsApp / Phone:** +254712328150
- **Telegram:** @dantechdevs
- **Website:** [dantechdevelopers.com](https://dantechdevelopers.com)

---

## License

© 2026 Dantechdevs. All rights reserved for DantePOS additions. Third-party components are covered by their own licences.