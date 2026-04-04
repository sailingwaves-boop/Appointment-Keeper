<?php
/**
 * Credits Store - Buy additional credit packs
 * Pricing: 50 SMS = £5, 100 SMS = £9, 200 SMS = £15
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Credits_Store {
    
    private $credit_packs = array(
        'sms_50' => array(
            'name' => '50 SMS Credits',
            'credits' => 50,
            'type' => 'sms',
            'price' => 5.00,
            'popular' => false
        ),
        'sms_100' => array(
            'name' => '100 SMS Credits',
            'credits' => 100,
            'type' => 'sms',
            'price' => 9.00,
            'popular' => true
        ),
        'sms_200' => array(
            'name' => '200 SMS Credits',
            'credits' => 200,
            'type' => 'sms',
            'price' => 15.00,
            'popular' => false
        ),
        'calls_10' => array(
            'name' => '10 Call Minutes',
            'credits' => 10,
            'type' => 'call',
            'price' => 5.00,
            'popular' => false
        ),
        'calls_25' => array(
            'name' => '25 Call Minutes',
            'credits' => 25,
            'type' => 'call',
            'price' => 10.00,
            'popular' => false
        ),
        'combo_starter' => array(
            'name' => 'Starter Bundle',
            'credits' => array('sms' => 50, 'call' => 10, 'email' => 50),
            'type' => 'combo',
            'price' => 12.00,
            'popular' => false
        )
    );
    
    public function __construct() {
        add_action('init', array($this, 'create_store_page'));
        add_shortcode('ak_credits_store', array($this, 'render_store'));
        add_action('wp_ajax_ak_purchase_credits', array($this, 'create_checkout'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Handle successful purchase webhook
        add_action('ak_credit_purchase_completed', array($this, 'process_credit_purchase'), 10, 2);
    }
    
    public function create_store_page() {
        if (get_option('ak_store_page_created') === 'yes') {
            return;
        }
        
        $page_exists = get_page_by_path('credits-store');
        
        if (!$page_exists) {
            wp_insert_post(array(
                'post_title' => 'Buy Credits',
                'post_name' => 'credits-store',
                'post_content' => '[ak_credits_store]',
                'post_status' => 'publish',
                'post_type' => 'page'
            ));
        }
        
        update_option('ak_store_page_created', 'yes');
    }
    
    public function enqueue_assets() {
        if (!is_page('credits-store') && !has_shortcode(get_post()->post_content ?? '', 'ak_credits_store')) {
            return;
        }
        
        wp_enqueue_style(
            'ak-credits-store',
            AK_DASHBOARD_PLUGIN_URL . 'assets/credits-store.css',
            array(),
            AK_DASHBOARD_VERSION
        );
        
        wp_enqueue_script(
            'ak-credits-store',
            AK_DASHBOARD_PLUGIN_URL . 'assets/credits-store.js',
            array('jquery'),
            AK_DASHBOARD_VERSION,
            true
        );
        
        wp_localize_script('ak-credits-store', 'akStoreData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ak_store_nonce')
        ));
    }
    
    public function render_store() {
        if (!is_user_logged_in()) {
            return '<div class="ak-notice">Please <a href="' . home_url('/?ak_login=1') . '">sign in</a> to purchase credits.</div>';
        }
        
        $user_id = get_current_user_id();
        $current_credits = $this->get_user_credits($user_id);
        
        ob_start();
        ?>
        <div class="ak-store-page">
            <div class="ak-store-header">
                <h1>Buy More Credits</h1>
                <p>Top up your account instantly. Credits never expire!</p>
                
                <div class="ak-current-balance">
                    <span class="ak-balance-label">Your Current Balance:</span>
                    <div class="ak-balance-pills">
                        <span class="ak-pill ak-sms-pill">💬 <?php echo intval($current_credits['sms']); ?> SMS</span>
                        <span class="ak-pill ak-call-pill">📞 <?php echo intval($current_credits['call']); ?> Calls</span>
                        <span class="ak-pill ak-email-pill">✉️ <?php echo intval($current_credits['email']); ?> Emails</span>
                    </div>
                </div>
            </div>
            
            <!-- SMS Packs -->
            <div class="ak-store-section">
                <h2>📱 SMS Credit Packs</h2>
                <div class="ak-packs-grid">
                    <?php foreach ($this->credit_packs as $pack_id => $pack): 
                        if ($pack['type'] !== 'sms') continue;
                        $per_credit = round($pack['price'] / $pack['credits'], 3);
                    ?>
                    <div class="ak-pack-card <?php echo $pack['popular'] ? 'ak-popular' : ''; ?>">
                        <?php if ($pack['popular']): ?>
                        <div class="ak-popular-badge">Best Value</div>
                        <?php endif; ?>
                        <div class="ak-pack-amount"><?php echo intval($pack['credits']); ?></div>
                        <div class="ak-pack-type">SMS Credits</div>
                        <div class="ak-pack-price">£<?php echo number_format($pack['price'], 2); ?></div>
                        <div class="ak-pack-per-credit"><?php echo number_format($per_credit * 100, 1); ?>p per SMS</div>
                        <button class="ak-buy-btn" data-pack="<?php echo esc_attr($pack_id); ?>">
                            Buy Now
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Call Packs -->
            <div class="ak-store-section">
                <h2>📞 Call Credit Packs</h2>
                <div class="ak-packs-grid">
                    <?php foreach ($this->credit_packs as $pack_id => $pack): 
                        if ($pack['type'] !== 'call') continue;
                        $per_credit = round($pack['price'] / $pack['credits'], 2);
                    ?>
                    <div class="ak-pack-card">
                        <div class="ak-pack-amount"><?php echo intval($pack['credits']); ?></div>
                        <div class="ak-pack-type">Call Minutes</div>
                        <div class="ak-pack-price">£<?php echo number_format($pack['price'], 2); ?></div>
                        <div class="ak-pack-per-credit">£<?php echo number_format($per_credit, 2); ?> per minute</div>
                        <button class="ak-buy-btn" data-pack="<?php echo esc_attr($pack_id); ?>">
                            Buy Now
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Combo Packs -->
            <div class="ak-store-section">
                <h2>🎁 Bundle Deals</h2>
                <div class="ak-packs-grid">
                    <?php foreach ($this->credit_packs as $pack_id => $pack): 
                        if ($pack['type'] !== 'combo') continue;
                    ?>
                    <div class="ak-pack-card ak-combo-card">
                        <div class="ak-pack-amount">Starter</div>
                        <div class="ak-pack-type">Bundle</div>
                        <div class="ak-combo-contents">
                            <span>50 SMS</span>
                            <span>10 Calls</span>
                            <span>50 Emails</span>
                        </div>
                        <div class="ak-pack-price">£<?php echo number_format($pack['price'], 2); ?></div>
                        <div class="ak-pack-savings">Save £3!</div>
                        <button class="ak-buy-btn" data-pack="<?php echo esc_attr($pack_id); ?>">
                            Buy Now
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="ak-store-footer">
                <p>💳 Secure payment powered by Stripe</p>
                <p>Credits are added instantly after payment</p>
                <a href="<?php echo home_url('/my-dashboard'); ?>" class="ak-back-link">← Back to Dashboard</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function get_user_credits($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        
        $credits = $wpdb->get_row($wpdb->prepare(
            "SELECT sms_credits, call_credits, email_credits FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        return array(
            'sms' => $credits ? $credits->sms_credits : 0,
            'call' => $credits ? $credits->call_credits : 0,
            'email' => $credits ? $credits->email_credits : 0
        );
    }
    
    public function create_checkout() {
        check_ajax_referer('ak_store_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'Please sign in first.'));
        }
        
        $pack_id = sanitize_text_field($_POST['pack']);
        
        if (!isset($this->credit_packs[$pack_id])) {
            wp_send_json_error(array('message' => 'Invalid pack selected.'));
        }
        
        $pack = $this->credit_packs[$pack_id];
        $user = wp_get_current_user();
        $secret_key = get_option('ak_stripe_secret_key');
        
        if (!$secret_key) {
            wp_send_json_error(array('message' => 'Payment system not configured.'));
        }
        
        // Build description
        if ($pack['type'] === 'combo') {
            $description = '50 SMS + 10 Calls + 50 Emails';
        } else {
            $description = $pack['credits'] . ' ' . ucfirst($pack['type']) . ' credits';
        }
        
        // Create Stripe checkout session (one-time payment)
        $stripe_data = array(
            'payment_method_types' => array('card'),
            'mode' => 'payment',
            'customer_email' => $user->user_email,
            'line_items' => array(
                array(
                    'price_data' => array(
                        'currency' => 'gbp',
                        'product_data' => array(
                            'name' => $pack['name'],
                            'description' => $description
                        ),
                        'unit_amount' => intval($pack['price'] * 100)
                    ),
                    'quantity' => 1
                )
            ),
            'success_url' => home_url('/credits-store/?purchase=success&session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => home_url('/credits-store/?purchase=cancelled'),
            'metadata' => array(
                'user_id' => $user->ID,
                'pack_id' => $pack_id,
                'purchase_type' => 'credit_pack'
            )
        );
        
        $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => $this->build_stripe_body($stripe_data)
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'Payment error. Please try again.'));
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($body['error'])) {
            wp_send_json_error(array('message' => $body['error']['message']));
        }
        
        wp_send_json_success(array(
            'checkout_url' => $body['url']
        ));
    }
    
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
     * Process credit purchase after successful payment
     */
    public function process_credit_purchase($user_id, $pack_id) {
        if (!isset($this->credit_packs[$pack_id])) {
            return;
        }
        
        $pack = $this->credit_packs[$pack_id];
        
        global $wpdb;
        $table = $wpdb->prefix . 'ak_customer_credits';
        
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d",
            $user_id
        ));
        
        if ($pack['type'] === 'combo') {
            // Combo pack - add multiple credit types
            $sms_add = $pack['credits']['sms'];
            $call_add = $pack['credits']['call'];
            $email_add = $pack['credits']['email'];
        } else {
            $sms_add = $pack['type'] === 'sms' ? $pack['credits'] : 0;
            $call_add = $pack['type'] === 'call' ? $pack['credits'] : 0;
            $email_add = $pack['type'] === 'email' ? $pack['credits'] : 0;
        }
        
        if ($existing) {
            $wpdb->update(
                $table,
                array(
                    'sms_credits' => $existing->sms_credits + $sms_add,
                    'call_credits' => $existing->call_credits + $call_add,
                    'email_credits' => $existing->email_credits + $email_add
                ),
                array('user_id' => $user_id)
            );
        } else {
            $wpdb->insert(
                $table,
                array(
                    'user_id' => $user_id,
                    'sms_credits' => $sms_add,
                    'call_credits' => $call_add,
                    'email_credits' => $email_add
                )
            );
        }
        
        // Log the purchase
        $this->log_purchase($user_id, $pack_id, $pack);
    }
    
    private function log_purchase($user_id, $pack_id, $pack) {
        $purchases = get_user_meta($user_id, 'ak_credit_purchases', true);
        if (!is_array($purchases)) {
            $purchases = array();
        }
        
        $purchases[] = array(
            'pack_id' => $pack_id,
            'pack_name' => $pack['name'],
            'price' => $pack['price'],
            'date' => current_time('mysql')
        );
        
        update_user_meta($user_id, 'ak_credit_purchases', $purchases);
    }
}

// Initialize
new AK_Credits_Store();
