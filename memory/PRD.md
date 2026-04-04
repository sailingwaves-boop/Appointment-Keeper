# AppointmentKeeper Plugin Suite - PRD

## Original Problem Statement
Build a suite of WordPress plugins for appointmentkeeper.co.uk that work with the Amelia booking plugin:
1. **Debt Ledger** - Customer-facing tool to track debts owed to them by others
2. **Credit Manager** - Admin tool to manage customer credit balances (SMS, Calls, Emails)
3. **Unified Customer Dashboard** - Single page showing appointments, credits, debt ledger, referrals
4. **Signup & Billing Flow** - Popup signup -> Email verification -> Profile form -> Stripe plan selection
5. **AppointmentKeeper Helper** - AI assistant widget (£12/mo add-on) for calls, SMS, GPS directions

## User Personas
1. **Site Admin/Staff** - Uses Credit Manager to manage customer credits and subscriptions
2. **Customers** - Use the Dashboard to see appointments, track debts, view credits, refer friends
3. **Debtors** - People who owe money to customers, receive reminders via SMS/Email

## Core Requirements
- Amelia integration for customer/appointment data
- Credit system tracking SMS, Calls, Email credits
- Debt ledger with payment tracking and reminders
- Twilio integration for SMS & Voice calls
- ElevenLabs integration for AI voice
- Stripe integration for subscriptions
- Referral system with rewards
- Four subscription tiers: Basic (£9.99), Standard (£24.99), Premium (£49.99), Enterprise (£149.99)

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
- REST API endpoint for Zapier: `/wp-json/ak-credit/v1/deduct`

### 3. AK Customer Dashboard & Billing (v2.0) - DONE ✅
**File:** `/app/ak-cd-billing-v2.zip`
**Purpose:** Unified customer dashboard + complete signup/billing flow

**Features Implemented:**

#### Signup & Authentication
- Modern popup signup form with email/password
- Google login hook (Nextend Social Login integration)
- Email verification with HTML emails
- **NEW:** Email verification success page with proper redirects
- **NEW:** Resend verification with 60-second rate limiting

#### Profile Completion Form
- First/Last name fields
- **NEW:** Business Name field (optional)
- Phone number with country code dropdown
- Preferred contact method
- "How did you hear about us?" dropdown
- **NEW:** Terms & Conditions checkbox (required)
- **NEW:** GDPR Privacy Policy checkbox (required)
- Marketing consent checkbox (optional)
- "Invite Friends" dynamic section (1-3 friends)
- Progress bar showing signup steps

#### Plan Selection & Billing
- 4-tier plan cards (Basic, Standard, Premium, Enterprise)
- 3-day free trial for all plans
- AppointmentKeeper Helper add-on (+£12/mo)
- Stripe Checkout integration
- Subscription webhooks (checkout.completed, invoice.paid, subscription.deleted)

#### Admin Settings Panel (wp-admin)
- **NEW:** Tabbed interface (Stripe, Twilio, ElevenLabs, Notifications, Referrals, Legal, Webhooks)
- Stripe API keys with Show/Hide toggle
- **NEW:** Twilio Account SID, Auth Token, Phone Number
- **NEW:** ElevenLabs API Key and Voice ID
- **NEW:** "Test Connection" buttons for all services
- **NEW:** Low credit threshold setting
- **NEW:** Auto top-up enable/disable
- **NEW:** Referral reward credits setting
- **NEW:** Terms & Privacy page selectors
- Copy-able webhook URLs

#### Referral System
- **NEW:** Unique referral codes per user
- **NEW:** Referral link tracking (cookie-based, 30 days)
- **NEW:** Click analytics
- **NEW:** Reward credits when referred user subscribes
- **NEW:** Referral dashboard widget with stats
- **NEW:** Social share buttons (WhatsApp, Twitter, Email)
- **NEW:** Email notifications on successful referrals

#### Credit Notifications
- **NEW:** Low credit warning emails (configurable threshold)
- **NEW:** Daily cron check for low balances
- **NEW:** Auto top-up option via Stripe
- **NEW:** 24-hour notification cooldown to prevent spam

#### Webhook Logging
- **NEW:** Stores last 50 webhook events
- **NEW:** Admin page to view/clear logs
- **NEW:** Payload viewer modal
- **NEW:** Status tracking (received, processed, error, ignored)

#### Dashboard
- Welcome header with credit balance display
- Tabbed interface: Appointments, Debt Ledger, Usage History, Refer Friends
- Quick action buttons
- Mobile responsive design

---

## Tech Stack
- **Platform:** WordPress Plugin Development (PHP 7.4+)
- **Frontend:** jQuery, CSS3
- **Database:** MySQL via WordPress $wpdb
- **APIs:** 
  - Twilio (SMS, Voice)
  - ElevenLabs (AI Voice)
  - Stripe (Payments, Subscriptions)
  - SendGrid (Email) - via Debt Ledger
- **Hooks:** WordPress actions/filters, AJAX via admin-ajax.php, REST API

## Database Schema
```
wp_ak_customer_credits
- user_id (int)
- sms_credits (int)
- call_credits (int)
- email_credits (int)
- plan_type (varchar)

wp_ak_credit_transactions
- id, user_id, transaction_type, amount, channel, reason, timestamp

wp_ak_debt_ledger
- id, creditor_user_id, debtor_details, amount, balance, status, created_at

wp_ak_debt_payments
- id, ledger_id, amount, payment_date, note

wp_ak_usage_log
- id, user_id, usage_type, recipient_phone, recipient_email, status, created_at

wp_ak_webhook_log (NEW)
- id, event_type, event_id, payload, status, error_message, created_at
```

## User Meta Keys (NEW)
```
ak_email_verified, ak_email_verified_date
ak_verification_token, ak_verification_expires
ak_profile_complete, ak_profile_completed_date
ak_business_name, ak_phone, ak_country_code, ak_contact_method
ak_consent_terms, ak_consent_terms_date
ak_consent_privacy, ak_consent_privacy_date
ak_consent_reminders, ak_consent_marketing
ak_subscription_status, ak_subscription_plan
ak_stripe_customer_id, ak_stripe_subscription_id
ak_has_helper
ak_referral_code, ak_referral_count, ak_referral_click_count
ak_referrals (array), ak_referred_by
ak_auto_topup_enabled, ak_auto_topup_amount
ak_last_low_credit_notification
```

---

## Installation Instructions

### AK Customer Dashboard & Billing (Latest)
1. Download `ak-cd-billing-v2.zip`
2. WordPress Admin > Plugins > Add New > Upload Plugin
3. Upload ZIP and activate
4. Go to Settings > AppointmentKeeper to configure:
   - Stripe API keys (required for billing)
   - Twilio credentials (for SMS/calls)
   - ElevenLabs API key (for AI voice)
   - Set low credit threshold
   - Select Terms & Privacy pages
5. Plugin auto-creates pages:
   - /my-dashboard
   - /choose-plan
   - /complete-profile
   - /email-verified

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

### P0 - In Progress
- **Twilio & ElevenLabs Integration Logic**: Backend code to actually send SMS/make calls using stored API keys (settings UI done, logic next)
- **AppointmentKeeper Helper Widget**: AI assistant that automates appointments, SMS, calls, GPS directions

### P1 - Upcoming
- Team Admin Panel: Dashboard for Premium (3 members) and Enterprise (unlimited) to invite team members
- GPS Directions Feature: Send text with directions when postcode entered
- Google Logo for OAuth: Proper styling on Nextend login button

### P2 - Future
- Main AppointmentKeeper Plugin: Single master plugin bundling all features
- Export debts to CSV/Excel
- Bulk reminder actions
- Payment gateway for debt collection (let debtors pay directly)

### P3 - Nice to Have
- PayPal integration
- Dashboard widget with weekly stats
- Mobile app deep linking

---

## API Keys Provided (Stored Securely in WP Options)
- Twilio SID: AC58b779cf24f0535f0c8753c0ba5258c9
- Twilio Auth: 8baafb482a5312a97a1d522c5a13d0d8
- Twilio Phone: +447488894735
- ElevenLabs: inb648671332a93d6f7df56bb93bd1e17bae9ab53eaaee55365dec3a1c59daa5d6fo

---

## Last Updated
April 2025 - Completed v2.0 with:
- Profile form with Terms/GDPR/Business Name
- Email verification success page
- Admin settings for Twilio/ElevenLabs with test buttons
- Referral tracking system with rewards
- Low credit notifications and auto top-up
- Webhook event logging
