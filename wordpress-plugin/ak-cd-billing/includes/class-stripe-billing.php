<?php
/**
 * Stripe Billing Handler - Subscriptions with trial period
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Stripe_Billing {
    
    private $secret_key;
    private $publishable_key;
    
    // Plan configurations
    private $plans = array(
        'basic' => array(
            'name' => 'Basic',
            'price' => 9.99,
            'sms_credits' => 50,
            'call_credits' => 20,
            'email_credits' => 100
        ),
        'standard' => array(
            'name' => 'Standard',
            'price' => 24.99,
            'sms_credits' => 150,
            'call_credits' => 50,
            'email_credits' => 300
        ),
        'premium' => array(
            'name' => 'Premium',
            'price' => 49.99,
            'sms_credits' => 500,
            'call_credits' => 150,
            'email_credits' => 1000
        ),
        'enterprise' => array(
            'name' => 'Enterprise',
            'price' => 149.99,
            'sms_credits' => 2000,
            'call_credits' => 500,
            'email_credits' => 5000
        )
    );
    
    // Trial credits
    private $trial_credits = array(
        'sms' => 5,
        'call' => 2,
        'email' => 10
    );
    
    // Helper addon
    private $helper_addon = array(
        'name' => 'AppointmentKeeper Helper',
        'price' => 12.00
    );
    
    public function __construct() {
        $this->secret_key = get_option('ak_stripe_secret_key', '');
        $this->publishable_key = get_option('ak_stripe_publishable_key', '');
        
        add_action('init', array($this, 'init'));
        add_action('wp_ajax_ak_create_checkout', array($this, 'create_checkout_session'));
        add_action('wp_ajax_nopriv_ak_create_checkout', array($this, 'create_checkout_session'));
        add_action('wp_ajax_ak_check_payment_status', array($this, 'check_payment_status'));
        add_action('wp_ajax_nopriv_ak_check_payment_status', array($this, 'check_payment_status'));
        
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
        
        add_shortcode('ak_plan_selection', array($this, 'render_plan_selection'));
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function init() {
        // Create choose-plan page if not exists
        if (get_option('ak_plan_page_created') !== 'yes') {
            $this->create_plan_page();
        }
    }
    
    private function create_plan_page() {
        $page_exists = get_page_by_path('choose-plan');
        
        if (!$page_exists) {
            $page_id = wp_insert_post(array(
                'post_title' => 'Choose Your Plan',
                'post_name' => 'choose-plan',
                'post_content' => '[ak_plan_selection]',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));
            
            if ($page_id) {
                update_option('ak_plan_page_id', $page_id);
            }
        }
        
        update_option('ak_plan_page_created', 'yes');
    }
    
    public function enqueue_assets() {
        if (!is_page('choose-plan')) {
            return;
        }
        
        wp_enqueue_style(
            'ak-billing',
            AK_DASHBOARD_PLUGIN_URL . 'assets/billing.css',
            array(),
            AK_DASHBOARD_VERSION
        );
        
        wp_enqueue_script(
            'ak-billing',
            AK_DASHBOARD_PLUGIN_URL . 'assets/billing.js',
            array('jquery'),
            AK_DASHBOARD_VERSION,
            true
        );
        
        wp_localize_script('ak-billing', 'akBillingData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ak_billing_nonce'),
            'publishableKey' => $this->publishable_key,
            'dashboardUrl' => home_url('/my-dashboard')
        ));
    }
    
    public function register_webhook_endpoint() {
        register_rest_route('ak-billing/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Render plan selection page
     */
    public function render_plan_selection() {
        if (!is_user_logged_in()) {
            return '<div class="ak-notice">Please <a href="' . home_url('/?ak_login=1') . '">sign in</a> to choose a plan.</div>';
        }
        
        $user_id = get_current_user_id();
        $current_plan = get_user_meta($user_id, 'ak_subscription_plan', true);
        
        ob_start();
        ?>
        <div class="ak-billing-page">
            <div class="ak-billing-header">
                <h1>Choose Your Plan</h1>
                <p>Start your 3-day free trial. Cancel anytime.</p>
                <p class="ak-trial-note">Trial includes: 5 SMS, 2 Calls, 10 Emails to test the system</p>
            </div>
            
            <div class="ak-plans-grid">
                <?php foreach ($this->plans as $plan_id => $plan): ?>
                <div class="ak-plan-card <?php echo $plan_id === 'standard' ? 'ak-popular' : ''; ?>" data-plan="<?php echo esc_attr($plan_id); ?>">
                    <?php if ($plan_id === 'standard'): ?>
                    <div class="ak-popular-badge">Most Popular</div>
                    <?php endif; ?>
                    
                    <h3 class="ak-plan-name"><?php echo esc_html($plan['name']); ?></h3>
                    <div class="ak-plan-price">
                        <span class="ak-currency">£</span>
                        <span class="ak-amount"><?php echo number_format($plan['price'], 2); ?></span>
                        <span class="ak-period">/month</span>
                    </div>
                    
                    <ul class="ak-plan-features">
                        <li><?php echo intval($plan['sms_credits']); ?> SMS credits</li>
                        <li><?php echo intval($plan['call_credits']); ?> Call credits</li>
                        <li><?php echo intval($plan['email_credits']); ?> Email credits</li>
                        <li>Amelia integration</li>
                        <li>Debt tracking</li>
                        <li>Customer dashboard</li>
                    </ul>
                    
                    <button class="ak-select-plan-btn" data-plan="<?php echo esc_attr($plan_id); ?>">
                        Start Free Trial
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Helper Addon Section -->
            <div class="ak-addon-section">
                <h2>Supercharge Your Experience</h2>
                <div class="ak-addon-card">
                    <div class="ak-addon-info">
                        <h3>AppointmentKeeper Helper</h3>
                        <p>Your AI assistant that manages appointments, sends reminders, and provides GPS directions - all through simple chat commands.</p>
                        <ul class="ak-addon-features">
                            <li>Make appointments via chat</li>
                            <li>Organise call & text reminders</li>
                            <li>GPS directions to appointments</li>
                            <li>Birthday & anniversary messages</li>
                        </ul>
                    </div>
                    <div class="ak-addon-price">
                        <span class="ak-addon-amount">+£12</span>
                        <span class="ak-addon-period">/month</span>
                        <label class="ak-addon-toggle">
                            <input type="checkbox" id="ak-helper-addon" name="helper_addon">
                            <span class="ak-toggle-slider"></span>
                            <span class="ak-toggle-label">Add Helper</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="ak-order-summary" style="display: none;">
                <h3>Order Summary</h3>
                <div class="ak-summary-row">
                    <span class="ak-summary-label">Plan:</span>
                    <span class="ak-summary-value" id="ak-selected-plan-name">-</span>
                </div>
                <div class="ak-summary-row">
                    <span class="ak-summary-label">Plan Price:</span>
                    <span class="ak-summary-value" id="ak-selected-plan-price">-</span>
                </div>
                <div class="ak-summary-row ak-addon-row" style="display: none;">
                    <span class="ak-summary-label">Helper Addon:</span>
                    <span class="ak-summary-value">£12.00/month</span>
                </div>
                <div class="ak-summary-row ak-total-row">
                    <span class="ak-summary-label">Total after trial:</span>
                    <span class="ak-summary-value" id="ak-total-price">-</span>
                </div>
                <p class="ak-trial-reminder">You won't be charged during your 3-day trial</p>
                <button id="ak-checkout-btn" class="ak-checkout-btn">
                    Start 3-Day Free Trial
                </button>
            </div>
            
            <div class="ak-billing-footer">
                <p>Secure payment powered by Stripe. Cancel anytime.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Create Stripe checkout session
     */
    public function create_checkout_session() {
        check_ajax_referer('ak_billing_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please sign in first.'));
        }
        
        $plan_id = sanitize_text_field($_POST['plan']);
        $include_helper = isset($_POST['include_helper']) && $_POST['include_helper'] === 'true';
        
        if (!isset($this->plans[$plan_id])) {
            wp_send_json_error(array('message' => 'Invalid plan selected.'));
        }
        
        $plan = $this->plans[$plan_id];
        $user = wp_get_current_user();
        
        // Calculate total
        $total = $plan['price'];
        if ($include_helper) {
            $total += $this->helper_addon['price'];
        }
        
        // Create Stripe checkout session
        $stripe_data = array(
            'payment_method_types' => array('card'),
            'mode' => 'subscription',
            'customer_email' => $user->user_email,
            'subscription_data' => array(
                'trial_period_days' => 3,
                'metadata' => array(
                    'user_id' => $user->ID,
                    'plan_id' => $plan_id,
                    'include_helper' => $include_helper ? 'yes' : 'no'
                )
            ),
            'line_items' => array(
                array(
                    'price_data' => array(
                        'currency' => 'gbp',
                        'product_data' => array(
                            'name' => 'AppointmentKeeper ' . $plan['name'] . ' Plan',
                            'description' => $plan['sms_credits'] . ' SMS, ' . $plan['call_credits'] . ' Calls, ' . $plan['email_credits'] . ' Emails per month'
                        ),
                        'unit_amount' => intval($plan['price'] * 100),
                        'recurring' => array(
                            'interval' => 'month'
                        )
                    ),
                    'quantity' => 1
                )
            ),
            'success_url' => home_url('/choose-plan/?payment=success&session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => home_url('/choose-plan/?payment=cancelled'),
            'metadata' => array(
                'user_id' => $user->ID,
                'plan_id' => $plan_id,
                'include_helper' => $include_helper ? 'yes' : 'no'
            )
        );
        
        // Add helper addon if selected
        if ($include_helper) {
            $stripe_data['line_items'][] = array(
                'price_data' => array(
                    'currency' => 'gbp',
                    'product_data' => array(
                        'name' => 'AppointmentKeeper Helper',
                        'description' => 'AI assistant for managing appointments'
                    ),
                    'unit_amount' => intval($this->helper_addon['price'] * 100),
                    'recurring' => array(
                        'interval' => 'month'
                    )
                ),
                'quantity' => 1
            );
        }
        
        // Make Stripe API call
        $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => $this->build_stripe_body($stripe_data)
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Payment system error. Please try again.'));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            wp_send_json_error(array('message' => $body['error']['message']));
        }
        
        // Store pending transaction
        update_user_meta($user->ID, 'ak_pending_checkout', $body['id']);
        update_user_meta($user->ID, 'ak_pending_plan', $plan_id);
        update_user_meta($user->ID, 'ak_pending_helper', $include_helper ? 'yes' : 'no');
        
        // Add trial credits immediately
        $this->add_trial_credits($user->ID);
        
        wp_send_json_success(array(
            'checkout_url' => $body['url'],
            'session_id' => $body['id']
        ));
    }
    
    /**
     * Build Stripe API body from nested array
     */
    private function build_stripe_body($data, $prefix = '') {
        $result = array();
        
        foreach ($data as $key => $value) {
            $new_key = $prefix ? $prefix . '[' . $key . ']' : $key;
            
            if (is_array($value)) {
                $result = array_merge($result, $this->build_stripe_body($value, $new_key));
            } else {
                $result[$new_key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Add trial credits
     */
    private function add_trial_credits($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return;
        }
        
        // Check if user already has credits
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        if ($existing) {
            // Add trial credits to existing
            $wpdb->update(
                $table,
                array(
                    'sms_credits' => $existing->sms_credits + $this->trial_credits['sms'],
                    'call_credits' => $existing->call_credits + $this->trial_credits['call'],
                    'email_credits' => $existing->email_credits + $this->trial_credits['email']
                ),
                array('user_id' => $user_id),
                array('%d', '%d', '%d'),
                array('%d')
            );
        } else {
            // Create new record with trial credits
            $wpdb->insert(
                $table,
                array(
                    'user_id' => $user_id,
                    'sms_credits' => $this->trial_credits['sms'],
                    'call_credits' => $this->trial_credits['call'],
                    'email_credits' => $this->trial_credits['email'],
                    'plan_type' => 'trial'
                ),
                array('%d', '%d', '%d', '%d', '%s')
            );
        }
        
        // Mark as trial
        update_user_meta($user_id, 'ak_subscription_status', 'trial');
    }
    
    /**
     * Check payment status
     */
    public function check_payment_status() {
        check_ajax_referer('ak_billing_nonce', 'nonce');
        
        $session_id = sanitize_text_field($_POST['session_id']);
        
        if (empty($session_id)) {
            wp_send_json_error(array('message' => 'Invalid session.'));
        }
        
        // Get session from Stripe
        $response = wp_remote_get('https://api.stripe.com/v1/checkout/sessions/' . $session_id, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->secret_key
            )
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Could not verify payment.'));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            wp_send_json_error(array('message' => $body['error']['message']));
        }
        
        $status = $body['status'];
        $payment_status = isset($body['payment_status']) ? $body['payment_status'] : 'unknown';
        
        if ($status === 'complete') {
            // Activate subscription
            $user_id = intval($body['metadata']['user_id']);
            $plan_id = $body['metadata']['plan_id'];
            $include_helper = $body['metadata']['include_helper'] === 'yes';
            
            update_user_meta($user_id, 'ak_subscription_status', 'active');
            update_user_meta($user_id, 'ak_subscription_plan', $plan_id);
            update_user_meta($user_id, 'ak_has_helper', $include_helper ? 'yes' : 'no');
            update_user_meta($user_id, 'ak_stripe_customer_id', $body['customer']);
            update_user_meta($user_id, 'ak_stripe_subscription_id', $body['subscription']);
            
            wp_send_json_success(array(
                'status' => 'complete',
                'redirect' => home_url('/my-dashboard')
            ));
        }
        
        wp_send_json_success(array(
            'status' => $status,
            'payment_status' => $payment_status
        ));
    }
    
    /**
     * Handle Stripe webhook
     */
    public function handle_webhook($request) {
        $payload = $request->get_body();
        $sig_header = $request->get_header('stripe-signature');
        
        $event = json_decode($payload, true);
        
        if (!$event || !isset($event['type'])) {
            return new WP_REST_Response(array('error' => 'Invalid payload'), 400);
        }
        
        switch ($event['type']) {
            case 'checkout.session.completed':
                $this->handle_checkout_completed($event['data']['object']);
                break;
                
            case 'invoice.paid':
                $this->handle_invoice_paid($event['data']['object']);
                break;
                
            case 'customer.subscription.deleted':
                $this->handle_subscription_cancelled($event['data']['object']);
                break;
        }
        
        return new WP_REST_Response(array('received' => true), 200);
    }
    
    /**
     * Handle checkout completed
     */
    private function handle_checkout_completed($session) {
        $user_id = intval($session['metadata']['user_id']);
        $plan_id = $session['metadata']['plan_id'];
        $include_helper = $session['metadata']['include_helper'] === 'yes';
        
        update_user_meta($user_id, 'ak_subscription_status', 'active');
        update_user_meta($user_id, 'ak_subscription_plan', $plan_id);
        update_user_meta($user_id, 'ak_has_helper', $include_helper ? 'yes' : 'no');
        update_user_meta($user_id, 'ak_stripe_customer_id', $session['customer']);
        update_user_meta($user_id, 'ak_stripe_subscription_id', $session['subscription']);
    }
    
    /**
     * Handle invoice paid - add monthly credits
     */
    private function handle_invoice_paid($invoice) {
        // Skip trial invoices (amount = 0)
        if ($invoice['amount_paid'] === 0) {
            return;
        }
        
        $customer_id = $invoice['customer'];
        
        // Find user by Stripe customer ID
        $users = get_users(array(
            'meta_key' => 'ak_stripe_customer_id',
            'meta_value' => $customer_id,
            'number' => 1
        ));
        
        if (empty($users)) {
            return;
        }
        
        $user = $users[0];
        $plan_id = get_user_meta($user->ID, 'ak_subscription_plan', true);
        
        if (!isset($this->plans[$plan_id])) {
            return;
        }
        
        $plan = $this->plans[$plan_id];
        
        // Add monthly credits
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user->ID
        ));
        
        if ($existing) {
            $wpdb->update(
                $table,
                array(
                    'sms_credits' => $existing->sms_credits + $plan['sms_credits'],
                    'call_credits' => $existing->call_credits + $plan['call_credits'],
                    'email_credits' => $existing->email_credits + $plan['email_credits'],
                    'plan_type' => $plan_id
                ),
                array('user_id' => $user->ID)
            );
        }
    }
    
    /**
     * Handle subscription cancelled
     */
    private function handle_subscription_cancelled($subscription) {
        $customer_id = $subscription['customer'];
        
        $users = get_users(array(
            'meta_key' => 'ak_stripe_customer_id',
            'meta_value' => $customer_id,
            'number' => 1
        ));
        
        if (empty($users)) {
            return;
        }
        
        $user = $users[0];
        
        update_user_meta($user->ID, 'ak_subscription_status', 'cancelled');
    }
}
