# DantePOS - V6.11

[![Version](https://img.shields.io/badge/version-6.11-blue)](https://github.com/Dantechdevs/DantePOS)  
[![License](https://img.shields.io/badge/license-All%20Rights%20Reserved-red)](https://github.com/Dantechdevs/DantePOS)  

**DantePOS** is a full-featured Point of Sale (POS) and business management system designed for retail stores, restaurants, salons, service centers, and more. It includes M-Pesa integration, inventory management, reporting, user management, and modular addons for easy expansion.  

**Author:** Dantechdevs  
**Copyright:** © 2026 All rights reserved  

---

## Table of Contents

- [Getting Started](#getting-started)  
- [Business Management](#business-management)  
- [User Management](#user-management)  
- [Contact Management](#contact-management)  
- [Products](#products)  
- [Purchases](#purchases)  
- [Stock Transfer](#stock-transfer)  
- [Sales / Sell](#sales--sell)  
- [Tax Settings](#tax-settings)  
- [Payment Accounts](#payment-accounts)  
- [Stock Adjustment](#stock-adjustment)  
- [Reports](#reports)  
- [Backups](#backups)  
- [Expense Management](#expense-management)  
- [Notifications](#notifications)  
- [Settings](#settings)  
- [Hardware Support](#hardware-support)  
- [Technical Details](#technical-details)  
- [Other Features](#other-features)  
- [Module Development & Addons](#module-development--addons)  
- [Frequently Asked Questions](#frequently-asked-questions)  
- [Feature List](#feature-list)  
- [Release Notes & Version Log](#release-notes--version-log)  
- [Mobile App](#mobile-app)  
- [Contact & Support](#contact--support)  

---

## Getting Started

1. Clone the repository:  
```bash
git clone https://github.com/Dantechdevs/DantePOS.git
Configure your .env file with database, email, and M-Pesa credentials.

Run migrations and seeders to set up tables:

bash
Copy code
php artisan migrate --seed
Access the system via your browser: http://localhost/dantepos or your hosted URL.

Business Management
Manage business details, logos, and multi-branch setups.

Set default currency, business hours, and system preferences.

User Management
Add, edit, and assign roles for employees and staff.

Role-based access ensures security and controlled access to modules.

Contact Management
Add and manage customers, suppliers, and vendors.

Track purchase and sales history per contact.

Products
Add products with SKU, barcode, price, and category.

Manage variations, stock levels, and reorder points.

Purchases
Record supplier purchases and generate purchase orders.

Track pending deliveries and automatic stock updates.

Stock Transfer
Transfer stock between stores or warehouses.

Automatic logs with audit trail for transparency.

Sales / Sell
Generate sales invoices and receipts.

Accept cash, card, and M-Pesa payments.

Apply taxes, discounts, and promotions.

Tax Settings
Configure tax rules and rates.

Automatically apply taxes on sales and purchases.

Payment Accounts
Manage bank accounts, M-Pesa till/paybill numbers, and other gateways.

Configure default accounts for transactions.

Stock Adjustment
Adjust stock quantities for corrections or losses.

Log reasons and timestamps for auditing purposes.

Reports
Generate sales, purchases, stock, and user activity reports.

Filter by date, product, or user.

Export reports to PDF or Excel.

Backups
Enable automatic backups locally or on AWS S3.

Schedule daily, weekly, or monthly backups.

Restore backups via system settings.

Expense Management
Track and categorize business expenses.

Record one-time or recurring expenses.

Notifications
Receive real-time alerts for sales, low stock, or system events.

Supports email, SMS, and in-app notifications.

Settings
Configure system preferences, language, currency, and security settings.

Hardware Support
Compatible with receipt printers, barcode scanners, and cash drawers.

Technical Details
Built using PHP, MySQL, and JavaScript.

Frontend powered by Bootstrap 5 and Tailwind CSS.

Modular structure allows adding custom addons and modules.

Other Features
Tables, service staff, bookings, modifiers, kitchen modules (for restaurants).

Modules for salons, service centers, and other business types.

Custom payment methods and pay-via options.

Module Development & Addons
Available Addons:

Essentials & HRM Module

Asset Management Module

Accounting & Bookkeeping Module

Developers can create and extend modules using the modular structure.

Frequently Asked Questions
How to integrate M-Pesa? Configure paybill/till number and credentials in settings.

How to restore backups? Use the backup module under system settings.

How to create users and assign roles? Use the User Management module and assign role-based permissions.

Feature List
Complete POS with M-Pesa integration

Inventory, sales, purchases, and stock management

Expense tracking, notifications, and reporting

Multi-store support and hardware integration

Modular addon system for easy expansion

Release Notes & Version Log
V6.11 – Latest release

Previous versions include updates for M-Pesa, reporting, expense management, and modular addons.

Mobile App
DantePOS mobile app available for iOS and Android.

Syncs with web POS for real-time sales, inventory, and reporting.

Contact & Support
For full setup, consultancy, or support for DantePOS:

WhatsApp: Click to Chat

Phone: +254712328150

Telegram: @dantechdevs

© 2026 Dantechdevs. All rights reserved.