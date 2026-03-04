<?php
/**
 * Admin Pages Class - Standalone Credit Manager interface
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Credit_Manager_Admin_Pages {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_pages'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Add menu pages
     */
    public function add_menu_pages() {
        // Main menu
        add_menu_page(
            __('Credit Manager', 'ak-credit-manager'),
            __('Credit Manager', 'ak-credit-manager'),
            'manage_options',
            'ak-credit-manager',
            array($this, 'render_dashboard'),
            'dashicons-money-alt',
            31
        );
        
        // Dashboard
        add_submenu_page(
            'ak-credit-manager',
            __('Dashboard', 'ak-credit-manager'),
            __('Dashboard', 'ak-credit-manager'),
            'manage_options',
            'ak-credit-manager',
            array($this, 'render_dashboard')
        );
        
        // Customers
        add_submenu_page(
            'ak-credit-manager',
            __('Customers', 'ak-credit-manager'),
            __('Customers', 'ak-credit-manager'),
            'manage_options',
            'ak-credit-manager-customers',
            array($this, 'render_customers')
        );
        
        // Transaction Log
        add_submenu_page(
            'ak-credit-manager',
            __('Transaction Log', 'ak-credit-manager'),
            __('Transaction Log', 'ak-credit-manager'),
            'manage_options',
            'ak-credit-manager-log',
            array($this, 'render_log')
        );
        
        // Settings
        add_submenu_page(
            'ak-credit-manager',
            __('Settings', 'ak-credit-manager'),
            __('Settings', 'ak-credit-manager'),
            'manage_options',
            'ak-credit-manager-settings',
            array($this, 'render_settings')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'ak-credit-manager') === false) {
            return;
        }
        
        wp_enqueue_style(
            'ak-credit-manager-admin',
            AK_CREDIT_MANAGER_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            AK_CREDIT_MANAGER_VERSION
        );
        
        wp_enqueue_script(
            'ak-credit-manager-admin',
            AK_CREDIT_MANAGER_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            AK_CREDIT_MANAGER_VERSION,
            true
        );
        
        wp_localize_script('ak-credit-manager-admin', 'akCreditManager', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ak_credit_manager_nonce'),
            'strings' => array(
                'confirmAdd' => __('Add credits to this customer?', 'ak-credit-manager'),
                'confirmRemove' => __('Remove credits from this customer?', 'ak-credit-manager'),
                'confirmFreeMonth' => __('Give this customer a free month?', 'ak-credit-manager'),
                'confirmBulk' => __('Apply this action to all selected customers?', 'ak-credit-manager'),
                'success' => __('Success!', 'ak-credit-manager'),
                'error' => __('Error occurred', 'ak-credit-manager')
            )
        ));
    }
    
    /**
     * Render dashboard
     */
    public function render_dashboard() {
        $stats = AK_Credit_Manager_Database::get_credit_stats();
        ?>
        <div class="wrap ak-credit-manager-wrap">
            <h1><?php _e('Credit Manager Dashboard', 'ak-credit-manager'); ?></h1>
            
            <!-- Stats Cards -->
            <div class="ak-stats-grid">
                <div class="ak-stat-card">
                    <span class="ak-stat-icon">👥</span>
                    <span class="ak-stat-number"><?php echo intval($stats['total_customers']); ?></span>
                    <span class="ak-stat-label"><?php _e('Total Customers', 'ak-credit-manager'); ?></span>
                </div>
                <div class="ak-stat-card ak-stat-sms">
                    <span class="ak-stat-icon">💬</span>
                    <span class="ak-stat-number"><?php echo number_format($stats['total_sms']); ?></span>
                    <span class="ak-stat-label"><?php _e('Total SMS Credits', 'ak-credit-manager'); ?></span>
                </div>
                <div class="ak-stat-card ak-stat-calls">
                    <span class="ak-stat-icon">📞</span>
                    <span class="ak-stat-number"><?php echo number_format($stats['total_calls']); ?></span>
                    <span class="ak-stat-label"><?php _e('Total Call Credits', 'ak-credit-manager'); ?></span>
                </div>
                <div class="ak-stat-card ak-stat-emails">
                    <span class="ak-stat-icon">✉️</span>
                    <span class="ak-stat-number"><?php echo number_format($stats['total_emails']); ?></span>
                    <span class="ak-stat-label"><?php _e('Total Email Credits', 'ak-credit-manager'); ?></span>
                </div>
                <div class="ak-stat-card ak-stat-warning">
                    <span class="ak-stat-icon">⚠️</span>
                    <span class="ak-stat-number"><?php echo intval($stats['low_credit_customers']); ?></span>
                    <span class="ak-stat-label"><?php _e('Low Credit Alerts', 'ak-credit-manager'); ?></span>
                </div>
            </div>
            
            <!-- Quick Search -->
            <div class="ak-quick-search-section">
                <h2><?php _e('Quick Customer Search', 'ak-credit-manager'); ?></h2>
                <div class="ak-search-box">
                    <input type="text" id="ak-quick-search" placeholder="<?php _e('Search by name, email, or ID...', 'ak-credit-manager'); ?>">
                    <div id="ak-quick-search-results" class="ak-search-results"></div>
                </div>
            </div>
            
            <!-- Customer Details Panel (shown after search) -->
            <div id="ak-customer-panel" class="ak-customer-panel" style="display:none;">
                <div class="ak-panel-header">
                    <h3 id="ak-panel-customer-name"></h3>
                    <span id="ak-panel-customer-email"></span>
                    <span class="ak-plan-badge" id="ak-panel-plan"></span>
                </div>
                
                <div class="ak-credit-balances">
                    <div class="ak-balance-card ak-sms">
                        <span class="ak-balance-icon">💬</span>
                        <span class="ak-balance-value" id="ak-panel-sms">0</span>
                        <span class="ak-balance-label">SMS</span>
                    </div>
                    <div class="ak-balance-card ak-calls">
                        <span class="ak-balance-icon">📞</span>
                        <span class="ak-balance-value" id="ak-panel-calls">0</span>
                        <span class="ak-balance-label">Calls</span>
                    </div>
                    <div class="ak-balance-card ak-emails">
                        <span class="ak-balance-icon">✉️</span>
                        <span class="ak-balance-value" id="ak-panel-emails">0</span>
                        <span class="ak-balance-label">Emails</span>
                    </div>
                </div>
                
                <div class="ak-credit-controls">
                    <h4><?php _e('Manage Credits', 'ak-credit-manager'); ?></h4>
                    <input type="hidden" id="ak-panel-user-id">
                    
                    <div class="ak-control-row">
                        <select id="ak-panel-credit-type">
                            <option value="sms"><?php _e('SMS', 'ak-credit-manager'); ?></option>
                            <option value="call"><?php _e('Calls', 'ak-credit-manager'); ?></option>
                            <option value="email"><?php _e('Emails', 'ak-credit-manager'); ?></option>
                        </select>
                        <input type="number" id="ak-panel-amount" placeholder="<?php _e('Amount', 'ak-credit-manager'); ?>" min="1">
                        <input type="text" id="ak-panel-reason" placeholder="<?php _e('Reason (optional)', 'ak-credit-manager'); ?>">
                    </div>
                    
                    <div class="ak-control-buttons">
                        <button type="button" class="button button-primary ak-btn-add-credits">
                            ➕ <?php _e('Add Credits', 'ak-credit-manager'); ?>
                        </button>
                        <button type="button" class="button ak-btn-remove-credits">
                            ➖ <?php _e('Remove Credits', 'ak-credit-manager'); ?>
                        </button>
                        <button type="button" class="button button-secondary ak-btn-free-month">
                            🎁 <?php _e('Give Free Month', 'ak-credit-manager'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="ak-transaction-history">
                    <h4><?php _e('Recent Transactions', 'ak-credit-manager'); ?></h4>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Date', 'ak-credit-manager'); ?></th>
                                <th><?php _e('Action', 'ak-credit-manager'); ?></th>
                                <th><?php _e('Type', 'ak-credit-manager'); ?></th>
                                <th><?php _e('Amount', 'ak-credit-manager'); ?></th>
                                <th><?php _e('Balance After', 'ak-credit-manager'); ?></th>
                                <th><?php _e('Reason', 'ak-credit-manager'); ?></th>
                                <th><?php _e('By', 'ak-credit-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="ak-panel-history"></tbody>
                    </table>
                </div>
            </div>
            
            <!-- Today's Usage -->
            <?php if ($stats['used_today']) : ?>
            <div class="ak-today-usage">
                <h3><?php _e('Credits Used Today', 'ak-credit-manager'); ?></h3>
                <div class="ak-usage-stats">
                    <span>💬 <?php echo intval($stats['used_today']->sms); ?> SMS</span>
                    <span>📞 <?php echo intval($stats['used_today']->calls); ?> Calls</span>
                    <span>✉️ <?php echo intval($stats['used_today']->emails); ?> Emails</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render customers list
     */
    public function render_customers() {
        $customers = AK_Credit_Manager_Database::get_all_customers_with_credits(array('limit' => 100));
        $settings = get_option('ak_credit_manager_settings', array());
        $threshold = isset($settings['low_credit_threshold']) ? intval($settings['low_credit_threshold']) : 10;
        ?>
        <div class="wrap ak-credit-manager-wrap">
            <h1><?php _e('All Customers', 'ak-credit-manager'); ?></h1>
            
            <div class="ak-filters">
                <label>
                    <input type="checkbox" id="ak-filter-low-credits"> 
                    <?php _e('Show only low credit customers', 'ak-credit-manager'); ?>
                </label>
            </div>
            
            <div class="ak-bulk-actions">
                <select id="ak-bulk-action">
                    <option value=""><?php _e('Bulk Actions', 'ak-credit-manager'); ?></option>
                    <option value="add_sms"><?php _e('Add SMS Credits', 'ak-credit-manager'); ?></option>
                    <option value="add_calls"><?php _e('Add Call Credits', 'ak-credit-manager'); ?></option>
                    <option value="add_emails"><?php _e('Add Email Credits', 'ak-credit-manager'); ?></option>
                    <option value="free_month"><?php _e('Give Free Month', 'ak-credit-manager'); ?></option>
                </select>
                <input type="number" id="ak-bulk-amount" placeholder="<?php _e('Amount', 'ak-credit-manager'); ?>" min="1" style="display:none;">
                <button type="button" class="button" id="ak-apply-bulk"><?php _e('Apply', 'ak-credit-manager'); ?></button>
            </div>
            
            <table class="wp-list-table widefat fixed striped ak-customers-table">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="ak-select-all"></th>
                        <th><?php _e('Customer', 'ak-credit-manager'); ?></th>
                        <th><?php _e('Email', 'ak-credit-manager'); ?></th>
                        <th><?php _e('Plan', 'ak-credit-manager'); ?></th>
                        <th><?php _e('SMS', 'ak-credit-manager'); ?></th>
                        <th><?php _e('Calls', 'ak-credit-manager'); ?></th>
                        <th><?php _e('Emails', 'ak-credit-manager'); ?></th>
                        <th><?php _e('Actions', 'ak-credit-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)) : ?>
                        <tr><td colspan="8"><?php _e('No customers found.', 'ak-credit-manager'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($customers as $customer) : 
                            $is_low = ($customer->sms_credits <= $threshold || $customer->call_credits <= $threshold || $customer->email_credits <= $threshold);
                        ?>
                        <tr data-user-id="<?php echo esc_attr($customer->ID); ?>" class="<?php echo $is_low ? 'ak-low-credits' : ''; ?>">
                            <td><input type="checkbox" class="ak-customer-select" value="<?php echo esc_attr($customer->ID); ?>"></td>
                            <td>
                                <strong><?php echo esc_html($customer->display_name); ?></strong>
                                <br><small>ID: <?php echo esc_html($customer->ID); ?></small>
                            </td>
                            <td><?php echo esc_html($customer->user_email); ?></td>
                            <td><span class="ak-plan-badge"><?php echo esc_html(ucfirst($customer->plan_type)); ?></span></td>
                            <td class="<?php echo $customer->sms_credits <= $threshold ? 'ak-low' : ''; ?>">
                                <?php echo intval($customer->sms_credits); ?>
                            </td>
                            <td class="<?php echo $customer->call_credits <= $threshold ? 'ak-low' : ''; ?>">
                                <?php echo intval($customer->call_credits); ?>
                            </td>
                            <td class="<?php echo $customer->email_credits <= $threshold ? 'ak-low' : ''; ?>">
                                <?php echo intval($customer->email_credits); ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small ak-manage-btn" data-user="<?php echo esc_attr($customer->ID); ?>">
                                    <?php _e('Manage', 'ak-credit-manager'); ?>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render transaction log
     */
    public function render_log() {
        $transactions = AK_Credit_Manager_Database::get_all_transactions(array('limit' => 100));
        ?>
        <div class="wrap ak-credit-manager-wrap">
            <h1><?php _e('Transaction Log', 'ak-credit-manager'); ?></h1>
            
            <div class="ak-log-filters">
                <select id="ak-log-filter-action">
                    <option value=""><?php _e('All Actions', 'ak-credit-manager'); ?></option>
                    <option value="add"><?php _e('Add', 'ak-credit-manager'); ?></option>
                    <option value="deduct"><?php _e('Deduct', 'ak-credit-manager'); ?></option>
                    <option value="refund"><?php _e('Refund', 'ak-credit-manager'); ?></option>
                    <option value="free_month"><?php _e('Free Month', 'ak-credit-manager'); ?></option>
                    <option value="manual_adjust"><?php _e('Manual Adjust', 'ak-credit-manager'); ?></option>
                </select>
                <select id="ak-log-filter-type">
                    <option value=""><?php _e('All Types', 'ak-credit-manager'); ?></option>
                    <option value="sms"><?php _e('SMS', 'ak-credit-manager'); ?></option>
                    <option value="call"><?php _e('Calls', 'ak-credit-manager'); ?></option>
                    <option value="email"><?php _e('Emails', 'ak-credit-manager'); ?></option>
                </select>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'ak-credit-manager'); ?></th>
                        <th><?php _e('Customer', 'ak-credit-manager'); ?></th>
                        <th><?php _e('Action', 'ak-credit-manager'); ?></th>
                        <th><?php _e('Type', 'ak-credit-manager'); ?></th>
                        <th><?php _e('Amount', 'ak-credit-manager'); ?></th>
                        <th><?php _e('Before', 'ak-credit-manager'); ?></th>
                        <th><?php _e('After', 'ak-credit-manager'); ?></th>
                        <th><?php _e('Reason', 'ak-credit-manager'); ?></th>
                        <th><?php _e('By', 'ak-credit-manager'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)) : ?>
                        <tr><td colspan="9"><?php _e('No transactions found.', 'ak-credit-manager'); ?></td></tr>
                    <?php else : ?>
                        <?php foreach ($transactions as $tx) : ?>
                        <tr class="ak-action-<?php echo esc_attr($tx->action); ?>">
                            <td><?php echo esc_html(date('d M Y H:i', strtotime($tx->created_at))); ?></td>
                            <td><?php echo esc_html($tx->user_name ?: 'User #' . $tx->user_id); ?></td>
                            <td>
                                <span class="ak-action-badge ak-action-<?php echo esc_attr($tx->action); ?>">
                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $tx->action))); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html(strtoupper($tx->credit_type)); ?></td>
                            <td class="<?php echo in_array($tx->action, array('deduct')) ? 'ak-negative' : 'ak-positive'; ?>">
                                <?php echo in_array($tx->action, array('deduct')) ? '-' : '+'; ?><?php echo intval($tx->amount); ?>
                            </td>
                            <td><?php echo intval($tx->balance_before); ?></td>
                            <td><?php echo intval($tx->balance_after); ?></td>
                            <td><?php echo esc_html($tx->reason ?: '-'); ?></td>
                            <td><?php echo esc_html($tx->performed_by_name ?: 'System'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings() {
        $settings = get_option('ak_credit_manager_settings', array());
        
        // Handle form submission
        if (isset($_POST['ak_credit_settings_nonce']) && wp_verify_nonce($_POST['ak_credit_settings_nonce'], 'ak_credit_manager_settings')) {
            $settings['free_month_sms'] = intval($_POST['free_month_sms']);
            $settings['free_month_calls'] = intval($_POST['free_month_calls']);
            $settings['free_month_emails'] = intval($_POST['free_month_emails']);
            $settings['low_credit_threshold'] = intval($_POST['low_credit_threshold']);
            
            // Update plans
            $settings['plans'] = array(
                'basic' => array(
                    'name' => 'Basic',
                    'sms_credits' => intval($_POST['basic_sms']),
                    'call_credits' => intval($_POST['basic_calls']),
                    'email_credits' => intval($_POST['basic_emails']),
                    'price' => floatval($_POST['basic_price'])
                ),
                'standard' => array(
                    'name' => 'Standard',
                    'sms_credits' => intval($_POST['standard_sms']),
                    'call_credits' => intval($_POST['standard_calls']),
                    'email_credits' => intval($_POST['standard_emails']),
                    'price' => floatval($_POST['standard_price'])
                ),
                'premium' => array(
                    'name' => 'Premium',
                    'sms_credits' => intval($_POST['premium_sms']),
                    'call_credits' => intval($_POST['premium_calls']),
                    'email_credits' => intval($_POST['premium_emails']),
                    'price' => floatval($_POST['premium_price'])
                ),
                'enterprise' => array(
                    'name' => 'Enterprise',
                    'sms_credits' => intval($_POST['enterprise_sms']),
                    'call_credits' => intval($_POST['enterprise_calls']),
                    'email_credits' => intval($_POST['enterprise_emails']),
                    'price' => floatval($_POST['enterprise_price'])
                )
            );
            
            update_option('ak_credit_manager_settings', $settings);
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved.', 'ak-credit-manager') . '</p></div>';
        }
        
        $plans = isset($settings['plans']) ? $settings['plans'] : array();
        ?>
        <div class="wrap ak-credit-manager-wrap">
            <h1><?php _e('Credit Manager Settings', 'ak-credit-manager'); ?></h1>
            
            <form method="post">
                <?php wp_nonce_field('ak_credit_manager_settings', 'ak_credit_settings_nonce'); ?>
                
                <div class="ak-settings-section">
                    <h2><?php _e('Free Month Credits', 'ak-credit-manager'); ?></h2>
                    <p class="description"><?php _e('Credits given when a customer receives a free month (referrals, promotions, etc.)', 'ak-credit-manager'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th><?php _e('SMS Credits', 'ak-credit-manager'); ?></th>
                            <td><input type="number" name="free_month_sms" value="<?php echo intval($settings['free_month_sms'] ?? 50); ?>" min="0"></td>
                        </tr>
                        <tr>
                            <th><?php _e('Call Credits', 'ak-credit-manager'); ?></th>
                            <td><input type="number" name="free_month_calls" value="<?php echo intval($settings['free_month_calls'] ?? 20); ?>" min="0"></td>
                        </tr>
                        <tr>
                            <th><?php _e('Email Credits', 'ak-credit-manager'); ?></th>
                            <td><input type="number" name="free_month_emails" value="<?php echo intval($settings['free_month_emails'] ?? 100); ?>" min="0"></td>
                        </tr>
                    </table>
                </div>
                
                <div class="ak-settings-section">
                    <h2><?php _e('Alert Settings', 'ak-credit-manager'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Low Credit Threshold', 'ak-credit-manager'); ?></th>
                            <td>
                                <input type="number" name="low_credit_threshold" value="<?php echo intval($settings['low_credit_threshold'] ?? 10); ?>" min="1">
                                <p class="description"><?php _e('Send alert when any credit type falls to this number or below', 'ak-credit-manager'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="ak-settings-section">
                    <h2><?php _e('Plan Configurations', 'ak-credit-manager'); ?></h2>
                    <p class="description"><?php _e('Configure the credits for each subscription plan', 'ak-credit-manager'); ?></p>
                    
                    <table class="ak-plans-table widefat">
                        <thead>
                            <tr>
                                <th><?php _e('Plan', 'ak-credit-manager'); ?></th>
                                <th><?php _e('SMS', 'ak-credit-manager'); ?></th>
                                <th><?php _e('Calls', 'ak-credit-manager'); ?></th>
                                <th><?php _e('Emails', 'ak-credit-manager'); ?></th>
                                <th><?php _e('Price (£)', 'ak-credit-manager'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong><?php _e('Basic', 'ak-credit-manager'); ?></strong></td>
                                <td><input type="number" name="basic_sms" value="<?php echo intval($plans['basic']['sms_credits'] ?? 50); ?>" min="0"></td>
                                <td><input type="number" name="basic_calls" value="<?php echo intval($plans['basic']['call_credits'] ?? 20); ?>" min="0"></td>
                                <td><input type="number" name="basic_emails" value="<?php echo intval($plans['basic']['email_credits'] ?? 100); ?>" min="0"></td>
                                <td><input type="number" name="basic_price" value="<?php echo floatval($plans['basic']['price'] ?? 9.99); ?>" min="0" step="0.01"></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Standard', 'ak-credit-manager'); ?></strong></td>
                                <td><input type="number" name="standard_sms" value="<?php echo intval($plans['standard']['sms_credits'] ?? 150); ?>" min="0"></td>
                                <td><input type="number" name="standard_calls" value="<?php echo intval($plans['standard']['call_credits'] ?? 50); ?>" min="0"></td>
                                <td><input type="number" name="standard_emails" value="<?php echo intval($plans['standard']['email_credits'] ?? 300); ?>" min="0"></td>
                                <td><input type="number" name="standard_price" value="<?php echo floatval($plans['standard']['price'] ?? 24.99); ?>" min="0" step="0.01"></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Premium', 'ak-credit-manager'); ?></strong></td>
                                <td><input type="number" name="premium_sms" value="<?php echo intval($plans['premium']['sms_credits'] ?? 500); ?>" min="0"></td>
                                <td><input type="number" name="premium_calls" value="<?php echo intval($plans['premium']['call_credits'] ?? 150); ?>" min="0"></td>
                                <td><input type="number" name="premium_emails" value="<?php echo intval($plans['premium']['email_credits'] ?? 1000); ?>" min="0"></td>
                                <td><input type="number" name="premium_price" value="<?php echo floatval($plans['premium']['price'] ?? 49.99); ?>" min="0" step="0.01"></td>
                            </tr>
                            <tr>
                                <td><strong><?php _e('Enterprise', 'ak-credit-manager'); ?></strong></td>
                                <td><input type="number" name="enterprise_sms" value="<?php echo intval($plans['enterprise']['sms_credits'] ?? 2000); ?>" min="0"></td>
                                <td><input type="number" name="enterprise_calls" value="<?php echo intval($plans['enterprise']['call_credits'] ?? 500); ?>" min="0"></td>
                                <td><input type="number" name="enterprise_emails" value="<?php echo intval($plans['enterprise']['email_credits'] ?? 5000); ?>" min="0"></td>
                                <td><input type="number" name="enterprise_price" value="<?php echo floatval($plans['enterprise']['price'] ?? 149.99); ?>" min="0" step="0.01"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <?php submit_button(__('Save Settings', 'ak-credit-manager')); ?>
            </form>
        </div>
        <?php
    }
}
