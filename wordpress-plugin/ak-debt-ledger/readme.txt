=== AppointmentKeeper AK Debt Ledger ===
Contributors: appointmentkeeper
Tags: debt, ledger, billing, payments, amelia, twilio, sms, reminders
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A billing ledger plugin for tracking debts, payments, and sending reminders via Twilio SMS and email. Integrates with Amelia booking plugin.

== Description ==

AppointmentKeeper AK Debt Ledger is a comprehensive debt tracking and management plugin designed to work seamlessly with the Amelia booking plugin. Track customer debts, record payments, and send automated reminders via SMS (Twilio) and email.

**Key Features:**

* **Amelia Integration** - Search and select customers directly from your Amelia customer database
* **Debt Management** - Track original amounts, current balances, and payment history
* **Payment Recording** - Record payments with confirmation workflow
* **Payment Confirmation** - Creditors can confirm payments with a single click, triggering notifications
* **SMS Reminders** - Send SMS reminders via Twilio API
* **Email Reminders** - Send email reminders using WordPress mail
* **Customizable Templates** - Create and customize message templates with placeholders
* **Automated Reminders** - Schedule automatic reminders via WordPress cron
* **Multiple Currencies** - Support for GBP, USD, EUR
* **Consent Management** - Track customer consent for receiving reminders

== Installation ==

1. Upload the `ak-debt-ledger` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Debt Ledger' > 'Settings' to configure Twilio credentials
4. Go to 'Debt Ledger' > 'Templates' to customize your message templates
5. Start adding debts!

== Configuration ==

**Twilio Setup:**

1. Create a Twilio account at https://www.twilio.com/
2. Get your Account SID and Auth Token from the Twilio Console
3. Purchase a phone number in Twilio
4. Enter these credentials in Debt Ledger > Settings

**Email Setup:**

The plugin uses WordPress's built-in wp_mail() function. Ensure your WordPress installation can send emails (consider using an SMTP plugin for reliability).

== Frequently Asked Questions ==

= Does this plugin require Amelia? =

No, Amelia is optional. If Amelia is not installed, you can manually enter customer details.

= How do automatic reminders work? =

The plugin uses WordPress cron to check for due reminders every hour. Customers with consent enabled and a scheduled reminder time will automatically receive notifications.

= What SMS provider does this use? =

The plugin integrates with Twilio for SMS messaging. You'll need a Twilio account with credits to send SMS.

== Screenshots ==

1. Main debt ledger list
2. Add/Edit debt form
3. Payment recording
4. Settings page
5. Message templates

== Changelog ==

= 1.0.0 =
* Initial release
* Amelia customer integration
* Debt and payment tracking
* Twilio SMS integration
* Email reminders
* Customizable templates
* Automated cron reminders

== Upgrade Notice ==

= 1.0.0 =
Initial release of AppointmentKeeper AK Debt Ledger.
