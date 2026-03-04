<?php
/**
 * Amelia Integration - Adds credit controls to Amelia admin panel
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Credit_Manager_Amelia_Integration {
    
    public function __construct() {
        // Hook into Amelia admin pages
        add_action('admin_footer', array($this, 'inject_credit_panel'));
        
        // Add custom admin page for Amelia users
        add_action('amelia_admin_after_customer_panel', array($this, 'render_customer_credits_panel'), 10, 1);
        
        // Hook into Amelia customer list
        add_filter('amelia_admin_customer_list_columns', array($this, 'add_credits_column'));
        
        // Enqueue scripts on Amelia pages
        add_action('admin_enqueue_scripts', array($this, 'enqueue_amelia_scripts'));
    }
    
    /**
     * Check if we're on an Amelia admin page
     */
    private function is_amelia_page() {
        $screen = get_current_screen();
        return $screen && (
            strpos($screen->id, 'amelia') !== false ||
            strpos($screen->id, 'wpamelia') !== false
        );
    }
    
    /**
     * Enqueue scripts on Amelia pages
     */
    public function enqueue_amelia_scripts($hook) {
        if (!$this->is_amelia_page() && strpos($hook, 'amelia') === false) {
            return;
        }
        
        wp_enqueue_style(
            'ak-credit-manager-amelia',
            AK_CREDIT_MANAGER_PLUGIN_URL . 'admin/css/amelia-integration.css',
            array(),
            AK_CREDIT_MANAGER_VERSION
        );
        
        wp_enqueue_script(
            'ak-credit-manager-amelia',
            AK_CREDIT_MANAGER_PLUGIN_URL . 'admin/js/amelia-integration.js',
            array('jquery'),
            AK_CREDIT_MANAGER_VERSION,
            true
        );
        
        wp_localize_script('ak-credit-manager-amelia', 'akCreditAmelia', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ak_credit_manager_nonce')
        ));
    }
    
    /**
     * Inject credit panel into Amelia admin
     */
    public function inject_credit_panel() {
        if (!$this->is_amelia_page()) {
            return;
        }
        
        ?>
        <div id="ak-credit-manager-panel" class="ak-amelia-credit-panel" style="display:none;">
            <div class="ak-credit-panel-header">
                <h3>💳 Credit Manager</h3>
                <button type="button" class="ak-close-panel">&times;</button>
            </div>
            <div class="ak-credit-panel-content">
                <div class="ak-credit-search">
                    <input type="text" id="ak-credit-customer-search" placeholder="Search customer by name, email, or ID...">
                    <div id="ak-credit-search-results"></div>
                </div>
                <div id="ak-credit-customer-details" style="display:none;">
                    <div class="ak-customer-info">
                        <h4 id="ak-customer-name"></h4>
                        <span id="ak-customer-email"></span>
                        <span id="ak-customer-plan"></span>
                    </div>
                    <div class="ak-credit-balances">
                        <div class="ak-balance-box">
                            <span class="ak-balance-label">SMS</span>
                            <span class="ak-balance-value" id="ak-balance-sms">0</span>
                        </div>
                        <div class="ak-balance-box">
                            <span class="ak-balance-label">Calls</span>
                            <span class="ak-balance-value" id="ak-balance-calls">0</span>
                        </div>
                        <div class="ak-balance-box">
                            <span class="ak-balance-label">Emails</span>
                            <span class="ak-balance-value" id="ak-balance-emails">0</span>
                        </div>
                    </div>
                    <div class="ak-credit-actions">
                        <h5>Quick Actions</h5>
                        <div class="ak-action-row">
                            <select id="ak-credit-type">
                                <option value="sms">SMS</option>
                                <option value="call">Calls</option>
                                <option value="email">Emails</option>
                            </select>
                            <input type="number" id="ak-credit-amount" placeholder="Amount" min="1">
                            <button type="button" class="button ak-add-credits">+ Add</button>
                            <button type="button" class="button ak-remove-credits">- Remove</button>
                        </div>
                        <div class="ak-action-row">
                            <input type="text" id="ak-credit-reason" placeholder="Reason (optional)">
                        </div>
                        <div class="ak-action-row ak-quick-buttons">
                            <button type="button" class="button button-primary ak-give-free-month">🎁 Give Free Month</button>
                            <button type="button" class="button ak-view-history">📜 View History</button>
                        </div>
                    </div>
                    <div id="ak-credit-history" style="display:none;">
                        <h5>Recent Transactions</h5>
                        <table class="ak-history-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Action</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody id="ak-history-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Floating button to open credit manager -->
        <button type="button" id="ak-open-credit-manager" class="ak-floating-btn" title="Credit Manager">
            💳
        </button>
        <?php
    }
    
    /**
     * Render customer credits panel (for Amelia integration hook)
     */
    public function render_customer_credits_panel($customer_id) {
        if (!$customer_id) return;
        
        // Get WordPress user ID from Amelia customer
        global $wpdb;
        $amelia_table = $wpdb->prefix . 'amelia_users';
        
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT externalId FROM $amelia_table WHERE id = %d AND type = 'customer'",
            $customer_id
        ));
        
        if (!$user_id) return;
        
        $credits = AK_Credit_Manager_Database::get_customer_credits($user_id);
        
        if (!$credits) return;
        
        ?>
        <div class="ak-amelia-credits-inline">
            <h4>Customer Credits</h4>
            <div class="ak-inline-balances">
                <span>📱 SMS: <strong><?php echo intval($credits->sms_credits); ?></strong></span>
                <span>📞 Calls: <strong><?php echo intval($credits->call_credits); ?></strong></span>
                <span>✉️ Emails: <strong><?php echo intval($credits->email_credits); ?></strong></span>
            </div>
            <button type="button" class="button ak-manage-credits" data-user="<?php echo esc_attr($user_id); ?>">
                Manage Credits
            </button>
        </div>
        <?php
    }
    
    /**
     * Add credits column to Amelia customer list
     */
    public function add_credits_column($columns) {
        $columns['credits'] = 'Credits';
        return $columns;
    }
}
