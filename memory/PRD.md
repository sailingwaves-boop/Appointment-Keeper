# AppointmentKeeper AK Debt Ledger - PRD

## Original Problem Statement
WordPress plugin for billing ledger to track debts between parties (e.g., Peter owes Paul £100). Features needed:
- Track who owes money and how much
- Record payments with confirmation workflow
- Integration with Amelia booking plugin for customer search
- Send SMS/email notifications via Twilio when payments are confirmed
- Automated reminder system
- Form fields: name, address, telephone, amount, balance, currency, debt type, status, notes, consent, preferred channel, next reminder

## User Personas
1. **Business Owner/Admin** - Uses the plugin to track customer debts, record payments, send reminders
2. **Customers (Amelia Users)** - Receive SMS/email reminders about outstanding balances

## Core Requirements
- Amelia integration for customer search
- Debt ledger with CRUD operations
- Payment recording with confirmation workflow
- Twilio SMS integration for notifications
- WordPress email for notifications
- Customizable message templates with placeholders
- Automated cron-based reminder system
- Status management (open, paid, written_off)

## What's Been Implemented (Jan 2026)
- ✅ Complete WordPress plugin structure
- ✅ Database tables (ledger, payments, reminders) auto-created on activation
- ✅ Amelia customer search integration
- ✅ Debt management (add, edit, delete, mark paid, write off)
- ✅ Payment recording with confirmation workflow
- ✅ Twilio SMS integration
- ✅ WordPress email integration
- ✅ Customizable message templates with placeholders
- ✅ Pre-made template library
- ✅ Automated cron reminders (hourly)
- ✅ Settings page for Twilio credentials
- ✅ Status filtering on ledger list
- ✅ Reminder history tracking
- ✅ Payment confirmation with SMS/email notifications

## Tech Stack
- WordPress Plugin (PHP)
- jQuery for admin UI
- Twilio API for SMS
- WordPress wp_mail() for email
- WordPress AJAX for async operations
- WordPress Cron for automated reminders

## Plugin Files
```
/app/wordpress-plugin/ak-debt-ledger/
├── ak-debt-ledger.php (main plugin file)
├── readme.txt (WordPress plugin readme)
├── admin/
│   ├── css/admin.css
│   └── js/admin.js
└── includes/
    ├── class-database.php
    ├── class-amelia-integration.php
    ├── class-twilio-sms.php
    ├── class-email-sender.php
    ├── class-reminder-cron.php
    ├── class-admin-pages.php
    └── class-ajax-handlers.php
```

## Installation Instructions
1. Download ak-debt-ledger.zip from /app/wordpress-plugin/
2. Go to WordPress Admin > Plugins > Add New > Upload Plugin
3. Upload the ZIP file and activate
4. Configure Twilio credentials in Debt Ledger > Settings
5. Customize templates in Debt Ledger > Templates

## Backlog / Future Enhancements
- P1: Export debts to CSV/Excel
- P1: Bulk actions (send reminders to multiple, mark multiple as paid)
- P2: Dashboard widget with summary stats
- P2: REST API for external integrations
- P3: Customer portal for self-service payments
- P3: Integration with payment gateways (Stripe, PayPal)
