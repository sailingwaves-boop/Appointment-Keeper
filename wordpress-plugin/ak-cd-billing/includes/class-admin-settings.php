<?php
/**
 * Admin Settings for AK Customer Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Dashboard_Admin_Settings {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function add_settings_page() {
        add_options_page(
            'AppointmentKeeper Settings',
            'AppointmentKeeper',
            'manage_options',
            'ak-dashboard-settings',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('ak_dashboard_settings', 'ak_stripe_secret_key');
        register_setting('ak_dashboard_settings', 'ak_stripe_publishable_key');
    }
    
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>AppointmentKeeper Settings</h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('ak_dashboard_settings'); ?>
                
                <h2>Stripe API Keys</h2>
                <p>Enter your Stripe API keys to enable billing.</p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Publishable Key</th>
                        <td>
                            <input type="text" name="ak_stripe_publishable_key" 
                                   value="<?php echo esc_attr(get_option('ak_stripe_publishable_key')); ?>" 
                                   class="regular-text" placeholder="pk_live_...">
                            <p class="description">Starts with pk_live_ or pk_test_</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Secret Key</th>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <input type="password" name="ak_stripe_secret_key" id="ak_secret_key"
                                       value="<?php echo esc_attr(get_option('ak_stripe_secret_key')); ?>" 
                                       class="regular-text" placeholder="sk_live_...">
                                <button type="button" onclick="var f=document.getElementById('ak_secret_key');f.type=f.type==='password'?'text':'password';this.textContent=f.type==='password'?'Show':'Hide';" style="cursor:pointer;">Show</button>
                            </div>
                            <p class="description">Starts with sk_live_ or sk_test_</p>
                        </td>
                    </tr>
                </table>
                
                <h2>Webhook URL</h2>
                <p>Add this URL to your Stripe webhook settings:</p>
                <code style="background:#f0f0f0;padding:10px;display:block;margin:10px 0;">
                    <?php echo esc_url(rest_url('ak-billing/v1/webhook')); ?>
                </code>
                <p class="description">Events to listen for: checkout.session.completed, invoice.paid, customer.subscription.deleted</p>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

// Initialize if in admin
if (is_admin()) {
    new AK_Dashboard_Admin_Settings();
}
