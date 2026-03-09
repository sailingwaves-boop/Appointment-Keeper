# AppointmentKeeper Plugin Suite - PRD

## Original Problem Statement
Build a suite of WordPress plugins for appointmentkeeper.co.uk that work with the Amelia booking plugin:
1. **Debt Ledger** - Customer-facing tool to track debts owed to them by others
2. **Credit Manager** - Admin tool to manage customer credit balances (SMS, Calls, Emails)
3. **Unified Customer Dashboard** - Single page showing appointments, credits, debt ledger, referrals

## User Personas
1. **Site Admin/Staff** - Uses Credit Manager to manage customer credits and subscriptions
2. **Customers** - Use the Dashboard to see appointments, track debts, view credits, refer friends
3. **Debtors** - People who owe money to customers, receive reminders via SMS/Email

## Core Requirements
- Amelia integration for customer/appointment data
- Credit system tracking SMS, Calls, Email credits
- Debt ledger with payment tracking and reminders
- Twilio integration for SMS & Voice calls
- SendGrid integration for emails
- Referral system with rewards
- Four subscription tiers: Basic, Standard, Premium, Enterprise

---

## COMPLETED PLUGINS

### 1. AK Debt Ledger (v2.0) - DONE
**File:** `/app/ak-debt-ledger.zip`
**Purpose:** Customer-facing debt tracking with credit-based reminders

**Features:**
- Debt CRUD operations (add, edit, delete, mark paid, write off)
- Payment recording with confirmation workflow
- Twilio SMS reminders (deducts credits)
- SendGrid email reminders (deducts credits)
- Amelia customer integration
- Referral system with free month rewards
- Customizable message templates
- Automated cron reminders

### 2. AK Credit Manager (v1.1) - DONE
**File:** `/app/ak-credit-manager.zip`
**Purpose:** Staff tool to manage customer credit balances

**Features:**
- View all customers with credit balances
- Add/remove credits manually
- Four subscription tiers with configurable limits
- Dual access: WordPress admin menu + Amelia admin widget
- Transaction logging
- Refund/free month handling

### 3. AK Customer Dashboard (v1.0) - DONE
**File:** `/app/ak-customer-dashboard.zip`
**Purpose:** Unified customer dashboard page

**Features:**
- Welcome header with credit balance display
- Tabbed interface:
  - Appointments (embeds Amelia customer panel)
  - Debt Ledger (embeds AK Debt Ledger shortcode)
  - Usage History (shows credit usage log)
  - Refer Friends (referral link sharing)
- Quick action buttons
- Login prompt for non-authenticated users
- Mobile responsive design
- Auto-creates "My Dashboard" page on activation

---

## Tech Stack
- **Platform:** WordPress Plugin Development (PHP)
- **Frontend:** jQuery, CSS
- **Database:** MySQL via WordPress $wpdb
- **APIs:** Twilio (SMS, Voice), SendGrid (Email)
- **Hooks:** WordPress actions/filters, AJAX via admin-ajax.php

## Database Schema
```
wp_ak_customer_credits
- user_id (int)
- sms_credits (int)
- call_credits (int)
- email_credits (int)

wp_ak_credit_transactions
- id, user_id, transaction_type, amount, channel, reason, timestamp

wp_ak_debt_ledger
- id, creditor_user_id, debtor_details, amount, balance, status, created_at

wp_ak_debt_payments
- id, ledger_id, amount, payment_date, note

wp_ak_usage_log
- id, user_id, usage_type, recipient_phone, recipient_email, status, created_at
```

---

## Installation Instructions

### AK Customer Dashboard
1. Download `ak-customer-dashboard.zip`
2. WordPress Admin > Plugins > Add New > Upload Plugin
3. Upload ZIP and activate
4. Plugin auto-creates "My Dashboard" page at /my-dashboard
5. Or add shortcode `[ak_customer_dashboard]` to any page

### AK Credit Manager
1. Download `ak-credit-manager.zip`
2. WordPress Admin > Plugins > Add New > Upload Plugin
3. Upload ZIP and activate
4. Access via: WordPress sidebar "Credit Manager" OR Amelia admin panel widget

### AK Debt Ledger
1. Download `ak-debt-ledger.zip`
2. WordPress Admin > Plugins > Add New > Upload Plugin
3. Upload ZIP and activate
4. Configure Twilio/SendGrid in settings
5. Customers access via shortcode `[ak_debt_ledger]`

---

## Backlog / Future Tasks

### P1 - Upcoming
- GPS Directions Feature: Send text with directions when postcode entered for appointment

### P2 - Future
- Main AppointmentKeeper Plugin: Single master plugin bundling all features for new customers
- Export debts to CSV/Excel
- Bulk reminder actions

### P3 - Nice to Have
- Payment gateway integration (Stripe, PayPal)
- REST API for external integrations
- Dashboard widget with stats

---

## Last Updated
March 2025 - Completed AK Customer Dashboard v1.0
