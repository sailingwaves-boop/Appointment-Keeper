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
3. **Team Members** - Staff who use allocated credits from their team owner
4. **Debtors** - People who owe money to customers, receive reminders via SMS/Email

## Core Requirements
- Amelia integration for customer/appointment data
- Credit system tracking SMS, Calls, Email credits
- Debt ledger with payment tracking and reminders
- Twilio integration for SMS & Voice calls
- ElevenLabs integration for AI voice
- Stripe integration for subscriptions & credit purchases
- Referral system with rewards
- Four subscription tiers: Basic (£9.99), Standard (£24.99), Premium (£49.99), Enterprise (£149.99)

---

## COMPLETED PLUGINS

### 1. AK Debt Ledger (v2.0) - DONE
**File:** `/app/ak-debt-ledger.zip`

### 2. AK Credit Manager (v1.1) - DONE
**File:** `/app/ak-credit-manager.zip`

### 3. AK Customer Dashboard & Billing (v3.0) - COMPLETE ✅
**File:** `/app/ak-cd-billing-v3-complete.zip`

---

## V3.2 NEW FEATURES

### 8. AI Outreach System (Viral Growth Engine) 
- **File:** `class-ai-outreach.php`
- **Page:** `/ai-outreach`
- **Features:**
  - Customers send AI-powered invites to their contacts
  - SMS or AI Voice Call options
  - Customizable pitch templates (admin can edit)
  - Daily limit per user (default: 5/day to prevent spam)
  - GDPR compliant with opt-out link
  - Referral integration - if contact signs up, sender gets credits
  - Full outreach history tracking
  - Conversion tracking (who signed up)
- **Credit Cost:** 2 SMS or 2 Call minutes per outreach (configurable)
- **Admin Toggle:** Settings → AppointmentKeeper → AI Outreach

### 9. Marketing Card (Shareable Landing Page) 
- **File:** `class-marketing-card.php`
- **Page:** `/get-started`
- **Purpose:** Beautiful, mobile-first landing card for social media sharing
- **Features:**
  - Stunning animated design (TikTok/Instagram ready)
  - Highlights all key features (AI calls, SMS, debt chasing, GPS)
  - Social proof section
  - Direct CTA to signup with referral tracking
  - Open Graph meta tags for social sharing
  - Mobile responsive
- **Usage:** Share `yoursite.com/get-started?ref=REFCODE` on TikTok, Instagram, etc.

---

## V3.0 FEATURES (All Complete!)

### 1. Credits Store 💳
- **Page:** `/credits-store`
- **Pricing:**
  - 50 SMS = £5 (10p per SMS)
  - 100 SMS = £9 (9p per SMS) - BEST VALUE badge
  - 200 SMS = £15 (7.5p per SMS)
  - 10 Call Minutes = £5
  - 25 Call Minutes = £10
  - Starter Bundle = £12 (50 SMS + 10 Calls + 50 Emails)
- **Features:**
  - Current balance display
  - One-click Stripe checkout
  - Credits added instantly via webhook
  - Purchase history logging

### 2. Twilio Integration 📱📞
- **File:** `class-twilio-service.php`
- **Features:**
  - Send SMS messages (deducts 1 credit)
  - Make voice calls with TwiML
  - Send GPS directions (Google Maps link)
  - Phone number formatting (E.164)
  - Auto-refund credits on failed sends
  - Usage logging to database
  - Rate limiting & error handling

### 3. ElevenLabs Integration 🎙️
- **File:** `class-elevenlabs-service.php`
- **Features:**
  - Generate natural AI speech from text
  - British voice (Sarah) as default
  - Custom voice ID support
  - Audio files stored in /wp-content/uploads/ak-audio/
  - Auto-cleanup after 1 hour
  - Quota checking
  - Seamless Twilio integration for AI calls

### 4. AppointmentKeeper Helper Widget 🤖
- **File:** `class-helper-widget.php`
- **Access:** £12/mo add-on OR included with Enterprise
- **Features:**
  - Floating chat-style widget (bottom-right)
  - Quick action buttons:
    - View Appointments (pulls from Amelia)
    - Send SMS Reminder
    - Send GPS Directions
    - Make AI Voice Call
    - Book Appointment
  - Natural language command support
  - Upgrade prompt for non-subscribers
  - Mobile responsive

### 5. Team Admin Panel 👥
- **File:** `class-team-admin.php`
- **Page:** `/team`
- **Limits:**
  - Basic/Standard: No team access
  - Premium: Up to 3 members
  - Enterprise: Unlimited members
- **Features:**
  - Invite team members via email
  - Allocate SMS/Call credits (deducted from owner's pool)
  - Remove members (credits returned to owner)
  - Adjust allocations in real-time
  - Member view shows their allocated credits
  - Email invitations with accept link
  - Pending/Active status tracking

### 6. Auto-Reminders (Amelia Integration) ⏰
- **File:** `class-auto-reminders.php`
- **Features:**
  - Automatic SMS reminders before Amelia appointments
  - Configurable timing: 24 hours, 2 hours, 1 hour before
  - Customizable message templates with variables:
    - {customer_name}, {service_name}, {time}, {date}, {location}, {business_name}
  - Optional GPS directions link (Google Maps)
  - Runs hourly via WordPress cron
  - Manual trigger button for testing
  - Statistics dashboard (today, this week, total)
  - Duplicate prevention (won't send same reminder twice)
  - Automatic URL shortening for GPS links

### 7. Previous Features (v2.0)
- Profile Completion Form (Terms, GDPR, Business Name)
- Email Verification Success Page
- Admin Settings (Stripe, Twilio, ElevenLabs with test buttons)
- Referral System with tracking & rewards
- Low Credit Notifications & Auto Top-up
- Webhook Logging (last 50 events)

---

## Tech Stack
- **Platform:** WordPress Plugin Development (PHP 7.4+)
- **Frontend:** jQuery, CSS3
- **Database:** MySQL via WordPress $wpdb
- **APIs:** 
  - Twilio (SMS, Voice)
  - ElevenLabs (AI Voice)
  - Stripe (Payments, Subscriptions)
  - is.gd (URL shortening for GPS links)

## Database Tables
```
wp_ak_customer_credits - User credit balances
wp_ak_credit_transactions - Transaction history
wp_ak_debt_ledger - Debt records
wp_ak_debt_payments - Payment records
wp_ak_usage_log - SMS/Call/Email usage logs
wp_ak_webhook_log - Stripe webhook events
```

## User Meta Keys
```
// Subscription
ak_subscription_status, ak_subscription_plan
ak_stripe_customer_id, ak_stripe_subscription_id
ak_has_helper

// Profile
ak_email_verified, ak_profile_complete
ak_business_name, ak_phone, ak_country_code
ak_consent_terms, ak_consent_privacy

// Referrals
ak_referral_code, ak_referral_count
ak_referrals (array), ak_referred_by

// Team
ak_team_members (array - for owners)
ak_team_owner_id (for members)

// Notifications
ak_auto_topup_enabled, ak_auto_topup_amount
ak_last_low_credit_notification
```

---

## Pages Created by Plugin
| Page | URL | Purpose |
|------|-----|---------|
| My Dashboard | /my-dashboard | Main customer dashboard |
| Choose Plan | /choose-plan | Stripe subscription selection |
| Complete Profile | /complete-profile | Profile form after verification |
| Email Verified | /email-verified | Verification success page |
| Credits Store | /credits-store | Buy additional credits |
| Team Management | /team | Invite & manage team members |

---

## Installation Instructions

1. Download `ak-cd-billing-v3-complete.zip`
2. WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload ZIP and activate
4. Go to **Settings → AppointmentKeeper** to configure:
   - **Stripe Tab:** Add your API keys
   - **Twilio Tab:** Add Account SID, Auth Token, Phone Number
   - **ElevenLabs Tab:** Add API key
   - **Legal Tab:** Select Terms & Privacy Policy pages
5. Test connections using the "Test Connection" buttons
6. Done! Plugin auto-creates all necessary pages.

---

## API Keys Reference (User Provided)
- Twilio SID: `AC58b779cf24f0535f0c8753c0ba5258c9`
- Twilio Auth: `8baafb482a5312a97a1d522c5a13d0d8`
- Twilio Phone: `+447488894735`
- ElevenLabs: `inb648671332a93d6f7df56bb93bd1e17bae9ab53eaaee55365dec3a1c59daa5d6fo`

*These should be entered in WP Admin → Settings → AppointmentKeeper*

---

## Backlog / Future Tasks

### P1 - Next Up
- **Google Logo for OAuth** - Proper styling on Nextend login button

### P2 - Future
- Amelia Deep Integration - Pull real appointment data, auto-send reminders
- Multi-language support
- Export debts/usage to CSV/Excel
- Bulk reminder actions
- Payment gateway for debt collection

### P3 - Nice to Have
- Mobile app
- Dashboard analytics widget
- Custom ElevenLabs voice cloning

---

## Pricing Summary

### Subscription Plans
| Plan | Price | SMS | Calls | Emails | Team | Helper |
|------|-------|-----|-------|--------|------|--------|
| Basic | £9.99/mo | 50 | 20 | 100 | No | +£12 |
| Standard | £24.99/mo | 150 | 50 | 300 | No | +£12 |
| Premium | £49.99/mo | 500 | 150 | 1000 | 3 members | +£12 |
| Enterprise | £149.99/mo | 2000 | 500 | 5000 | Unlimited | Included |

### Credit Packs
| Pack | Price | Per Unit | Margin |
|------|-------|----------|--------|
| 50 SMS | £5 | 10p | 60% |
| 100 SMS | £9 | 9p | 55% |
| 200 SMS | £15 | 7.5p | 47% |
| 10 Calls | £5 | 50p | ~50% |
| 25 Calls | £10 | 40p | ~50% |
| Starter Bundle | £12 | - | ~50% |

---

## Last Updated
April 2025 - v3.2 Complete with:
- Credits Store (SMS/Call packs with Stripe)
- Twilio SMS & Voice integration (fully wired)
- ElevenLabs AI voice integration
- AppointmentKeeper Helper Widget
- Team Admin Panel (Premium/Enterprise)
- Auto-Reminders (Amelia integration)
- Booking Confirmations (Email/SMS/AI Call toggle)
- No-Show Tracker
- Debt Auto-Chase
- **NEW: AI Outreach System** - Customers invite friends via SMS or AI calls
- **NEW: Marketing Card Page** - Shareable /get-started page for TikTok/Instagram
