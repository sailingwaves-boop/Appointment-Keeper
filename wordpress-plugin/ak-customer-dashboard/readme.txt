=== AppointmentKeeper Customer Dashboard ===
Contributors: appointmentkeeper
Tags: dashboard, appointments, credits, amelia, customer portal
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A unified customer dashboard that brings together Amelia appointments, credits, debt ledger, and referrals in one convenient place.

== Description ==

The AppointmentKeeper Customer Dashboard provides your customers with a single, beautiful dashboard where they can:

* View and manage their Amelia appointments
* Access their Debt Ledger to track money owed to them
* See their credit balance (SMS, Calls, Emails) at a glance
* View their usage history
* Refer friends and earn rewards

**Features:**

* **Unified View** - All customer features in one tabbed interface
* **Credit Display** - Shows remaining SMS, Call, and Email credits in the header
* **Amelia Integration** - Embeds the Amelia customer panel seamlessly
* **Debt Ledger Integration** - Shows the full debt ledger if the AK Debt Ledger plugin is active
* **Usage History** - Displays a log of all credit usage
* **Referral System** - Built-in referral link sharing
* **Mobile Responsive** - Works beautifully on all devices
* **Login Protection** - Shows login prompt for non-logged-in users

== Installation ==

1. Upload the `ak-customer-dashboard` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin automatically creates a "My Dashboard" page with the shortcode
4. Or manually add the shortcode `[ak_customer_dashboard]` to any page

== Frequently Asked Questions ==

= Do I need other AppointmentKeeper plugins? =

The dashboard works best with:
- AK Debt Ledger - For debt tracking features
- AK Credit Manager - For credit balance display

However, it will gracefully show placeholder messages if these aren't installed.

= How do I customize the dashboard page? =

The dashboard is rendered via the `[ak_customer_dashboard]` shortcode. You can add it to any page and customize the page template through your theme.

= Can I change the colors? =

You can override the CSS by adding custom styles to your theme's style.css or through the WordPress Customizer.

== Changelog ==

= 1.0.0 =
* Initial release
* Unified dashboard with tabs for Appointments, Debt Ledger, Usage History, and Referrals
* Credit balance display in header
* Amelia customer panel integration
* Debt Ledger shortcode integration
* Basic usage history table
* Referral link sharing
* Mobile responsive design
* Login prompt for non-authenticated users

== Upgrade Notice ==

= 1.0.0 =
Initial release of the AppointmentKeeper Customer Dashboard.
