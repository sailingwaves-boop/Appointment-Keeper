<?php
/**
 * Admin Pages Class
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Debt_Ledger_Admin_Pages {
    
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
            __('AK Debt Ledger', 'ak-debt-ledger'),
            __('Debt Ledger', 'ak-debt-ledger'),
            'manage_options',
            'ak-debt-ledger',
            array($this, 'render_ledger_page'),
            'dashicons-clipboard',
            30
        );
        
        // Submenu - Ledger List
        add_submenu_page(
            'ak-debt-ledger',
            __('All Debts', 'ak-debt-ledger'),
            __('All Debts', 'ak-debt-ledger'),
            'manage_options',
            'ak-debt-ledger',
            array($this, 'render_ledger_page')
        );
        
        // Submenu - Add New
        add_submenu_page(
            'ak-debt-ledger',
            __('Add Debt', 'ak-debt-ledger'),
            __('Add Debt', 'ak-debt-ledger'),
            'manage_options',
            'ak-debt-ledger-add',
            array($this, 'render_add_edit_page')
        );
        
        // Submenu - Settings
        add_submenu_page(
            'ak-debt-ledger',
            __('Settings', 'ak-debt-ledger'),
            __('Settings', 'ak-debt-ledger'),
            'manage_options',
            'ak-debt-ledger-settings',
            array($this, 'render_settings_page')
        );
        
        // Submenu - Templates
        add_submenu_page(
            'ak-debt-ledger',
            __('Message Templates', 'ak-debt-ledger'),
            __('Templates', 'ak-debt-ledger'),
            'manage_options',
            'ak-debt-ledger-templates',
            array($this, 'render_templates_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'ak-debt-ledger') === false) {
            return;
        }
        
        wp_enqueue_style(
            'ak-debt-ledger-admin',
            AK_DEBT_LEDGER_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            AK_DEBT_LEDGER_VERSION
        );
        
        wp_enqueue_script(
            'ak-debt-ledger-admin',
            AK_DEBT_LEDGER_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery'),
            AK_DEBT_LEDGER_VERSION,
            true
        );
        
        wp_localize_script('ak-debt-ledger-admin', 'akDebtLedger', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ak_debt_ledger_nonce'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this entry?', 'ak-debt-ledger'),
                'confirmMarkPaid' => __('Mark this debt as fully paid?', 'ak-debt-ledger'),
                'confirmWriteOff' => __('Write off this debt? This cannot be undone.', 'ak-debt-ledger'),
                'confirmPayment' => __('Confirm this payment has been received?', 'ak-debt-ledger'),
                'sending' => __('Sending...', 'ak-debt-ledger'),
                'sent' => __('Sent!', 'ak-debt-ledger'),
                'error' => __('Error occurred', 'ak-debt-ledger')
            )
        ));
    }
    
    /**
     * Render main ledger page
     */
    public function render_ledger_page() {
        $entries = AK_Debt_Ledger_Database::get_all_ledger_entries();
        ?>
        <div class="wrap ak-debt-ledger-wrap">
            <h1 class="wp-heading-inline"><?php _e('Debt Ledger', 'ak-debt-ledger'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=ak-debt-ledger-add'); ?>" class="page-title-action">
                <?php _e('Add New Debt', 'ak-debt-ledger'); ?>
            </a>
            <hr class="wp-header-end">
            
            <?php if (isset($_GET['message'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php echo esc_html($_GET['message']); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="ak-ledger-filters">
                <select id="ak-status-filter">
                    <option value=""><?php _e('All Statuses', 'ak-debt-ledger'); ?></option>
                    <option value="open"><?php _e('Open', 'ak-debt-ledger'); ?></option>
                    <option value="paid"><?php _e('Paid', 'ak-debt-ledger'); ?></option>
                    <option value="written_off"><?php _e('Written Off', 'ak-debt-ledger'); ?></option>
                </select>
            </div>
            
            <table class="wp-list-table widefat fixed striped ak-ledger-table">
                <thead>
                    <tr>
                        <th class="column-customer"><?php _e('Customer', 'ak-debt-ledger'); ?></th>
                        <th class="column-phone"><?php _e('Phone', 'ak-debt-ledger'); ?></th>
                        <th class="column-amount"><?php _e('Original', 'ak-debt-ledger'); ?></th>
                        <th class="column-balance"><?php _e('Balance', 'ak-debt-ledger'); ?></th>
                        <th class="column-type"><?php _e('Type', 'ak-debt-ledger'); ?></th>
                        <th class="column-status"><?php _e('Status', 'ak-debt-ledger'); ?></th>
                        <th class="column-reminder"><?php _e('Next Reminder', 'ak-debt-ledger'); ?></th>
                        <th class="column-count"><?php _e('Reminders', 'ak-debt-ledger'); ?></th>
                        <th class="column-actions"><?php _e('Actions', 'ak-debt-ledger'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)) : ?>
                        <tr>
                            <td colspan="9"><?php _e('No debt entries found.', 'ak-debt-ledger'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($entries as $entry) : ?>
                            <tr data-id="<?php echo esc_attr($entry->id); ?>" data-status="<?php echo esc_attr($entry->status); ?>">
                                <td class="column-customer">
                                    <strong>
                                        <a href="<?php echo admin_url('admin.php?page=ak-debt-ledger-add&id=' . $entry->id); ?>">
                                            <?php echo esc_html($entry->customer_name); ?>
                                        </a>
                                    </strong>
                                    <?php if ($entry->customer_email) : ?>
                                        <br><small><?php echo esc_html($entry->customer_email); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="column-phone"><?php echo esc_html($entry->customer_phone); ?></td>
                                <td class="column-amount">
                                    <?php echo esc_html($entry->currency . number_format($entry->original_amount, 2)); ?>
                                </td>
                                <td class="column-balance">
                                    <strong class="<?php echo $entry->current_balance > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo esc_html($entry->currency . number_format($entry->current_balance, 2)); ?>
                                    </strong>
                                </td>
                                <td class="column-type">
                                    <span class="debt-type debt-type-<?php echo esc_attr($entry->debt_type); ?>">
                                        <?php echo esc_html(ucfirst($entry->debt_type)); ?>
                                    </span>
                                </td>
                                <td class="column-status">
                                    <span class="status-badge status-<?php echo esc_attr($entry->status); ?>">
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $entry->status))); ?>
                                    </span>
                                </td>
                                <td class="column-reminder">
                                    <?php 
                                    if ($entry->next_reminder_at) {
                                        echo esc_html(date('d M Y H:i', strtotime($entry->next_reminder_at)));
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                    ?>
                                </td>
                                <td class="column-count"><?php echo intval($entry->reminder_count); ?></td>
                                <td class="column-actions">
                                    <div class="row-actions">
                                        <a href="<?php echo admin_url('admin.php?page=ak-debt-ledger-add&id=' . $entry->id); ?>" 
                                           class="button button-small" title="<?php _e('Edit', 'ak-debt-ledger'); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </a>
                                        
                                        <?php if ($entry->status === 'open') : ?>
                                            <button type="button" class="button button-small ak-mark-paid" 
                                                    data-id="<?php echo esc_attr($entry->id); ?>" 
                                                    title="<?php _e('Mark as Paid', 'ak-debt-ledger'); ?>">
                                                <span class="dashicons dashicons-yes-alt"></span>
                                            </button>
                                            
                                            <button type="button" class="button button-small ak-write-off" 
                                                    data-id="<?php echo esc_attr($entry->id); ?>" 
                                                    title="<?php _e('Write Off', 'ak-debt-ledger'); ?>">
                                                <span class="dashicons dashicons-dismiss"></span>
                                            </button>
                                            
                                            <?php if ($entry->consent_to_reminders) : ?>
                                                <button type="button" class="button button-small ak-send-reminder" 
                                                        data-id="<?php echo esc_attr($entry->id); ?>" 
                                                        title="<?php _e('Send Reminder Now', 'ak-debt-ledger'); ?>">
                                                    <span class="dashicons dashicons-email-alt"></span>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="button button-small ak-delete-entry" 
                                                data-id="<?php echo esc_attr($entry->id); ?>" 
                                                title="<?php _e('Delete', 'ak-debt-ledger'); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>
                                    </div>
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
     * Render add/edit page
     */
    public function render_add_edit_page() {
        $entry = null;
        $payments = array();
        $reminder_history = array();
        $is_edit = false;
        
        if (isset($_GET['id'])) {
            $entry = AK_Debt_Ledger_Database::get_ledger_entry(intval($_GET['id']));
            if ($entry) {
                $is_edit = true;
                $payments = AK_Debt_Ledger_Database::get_payments($entry->id);
                $reminder_history = AK_Debt_Ledger_Database::get_reminder_history($entry->id);
            }
        }
        
        $amelia_active = AK_Debt_Ledger_Amelia_Integration::is_amelia_active();
        ?>
        <div class="wrap ak-debt-ledger-wrap">
            <h1><?php echo $is_edit ? __('Edit Debt', 'ak-debt-ledger') : __('Add New Debt', 'ak-debt-ledger'); ?></h1>
            
            <div class="ak-form-container">
                <form id="ak-debt-form" method="post">
                    <?php wp_nonce_field('ak_debt_ledger_save', 'ak_debt_nonce'); ?>
                    <input type="hidden" name="entry_id" value="<?php echo $is_edit ? esc_attr($entry->id) : ''; ?>">
                    
                    <div class="ak-form-section">
                        <h2><?php _e('Customer Information', 'ak-debt-ledger'); ?></h2>
                        
                        <?php if ($amelia_active) : ?>
                        <div class="ak-form-row">
                            <label for="ak-customer-search"><?php _e('Search Amelia Customer', 'ak-debt-ledger'); ?></label>
                            <input type="text" id="ak-customer-search" 
                                   placeholder="<?php _e('Type to search customers...', 'ak-debt-ledger'); ?>">
                            <div id="ak-customer-results" class="ak-search-results"></div>
                        </div>
                        <?php else : ?>
                        <div class="notice notice-warning">
                            <p><?php _e('Amelia plugin not detected. Enter customer details manually.', 'ak-debt-ledger'); ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <input type="hidden" name="amelia_customer_id" id="amelia_customer_id" 
                               value="<?php echo $is_edit ? esc_attr($entry->amelia_customer_id) : ''; ?>">
                        
                        <div class="ak-form-row">
                            <label for="customer_name"><?php _e('Customer Name', 'ak-debt-ledger'); ?> *</label>
                            <input type="text" name="customer_name" id="customer_name" required
                                   value="<?php echo $is_edit ? esc_attr($entry->customer_name) : ''; ?>">
                        </div>
                        
                        <div class="ak-form-row ak-form-row-half">
                            <div>
                                <label for="customer_phone"><?php _e('Phone Number', 'ak-debt-ledger'); ?></label>
                                <input type="tel" name="customer_phone" id="customer_phone"
                                       value="<?php echo $is_edit ? esc_attr($entry->customer_phone) : ''; ?>">
                            </div>
                            <div>
                                <label for="customer_email"><?php _e('Email Address', 'ak-debt-ledger'); ?></label>
                                <input type="email" name="customer_email" id="customer_email"
                                       value="<?php echo $is_edit ? esc_attr($entry->customer_email) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="ak-form-row">
                            <label for="customer_address"><?php _e('Address', 'ak-debt-ledger'); ?></label>
                            <textarea name="customer_address" id="customer_address" rows="2"><?php echo $is_edit ? esc_textarea($entry->customer_address) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="ak-form-section">
                        <h2><?php _e('Debt Details', 'ak-debt-ledger'); ?></h2>
                        
                        <div class="ak-form-row ak-form-row-third">
                            <div>
                                <label for="original_amount"><?php _e('Original Amount', 'ak-debt-ledger'); ?> *</label>
                                <input type="number" name="original_amount" id="original_amount" step="0.01" min="0" required
                                       value="<?php echo $is_edit ? esc_attr($entry->original_amount) : ''; ?>">
                            </div>
                            <div>
                                <label for="current_balance"><?php _e('Current Balance', 'ak-debt-ledger'); ?> *</label>
                                <input type="number" name="current_balance" id="current_balance" step="0.01" min="0" required
                                       value="<?php echo $is_edit ? esc_attr($entry->current_balance) : ''; ?>">
                            </div>
                            <div>
                                <label for="currency"><?php _e('Currency', 'ak-debt-ledger'); ?></label>
                                <select name="currency" id="currency">
                                    <option value="GBP" <?php selected($is_edit ? $entry->currency : 'GBP', 'GBP'); ?>>£ GBP</option>
                                    <option value="USD" <?php selected($is_edit ? $entry->currency : '', 'USD'); ?>>$ USD</option>
                                    <option value="EUR" <?php selected($is_edit ? $entry->currency : '', 'EUR'); ?>>€ EUR</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="ak-form-row ak-form-row-half">
                            <div>
                                <label for="debt_type"><?php _e('Debt Type', 'ak-debt-ledger'); ?></label>
                                <select name="debt_type" id="debt_type">
                                    <option value="missed" <?php selected($is_edit ? $entry->debt_type : '', 'missed'); ?>>
                                        <?php _e('Missed Payment', 'ak-debt-ledger'); ?>
                                    </option>
                                    <option value="part-paid" <?php selected($is_edit ? $entry->debt_type : '', 'part-paid'); ?>>
                                        <?php _e('Part Paid', 'ak-debt-ledger'); ?>
                                    </option>
                                    <option value="other" <?php selected($is_edit ? $entry->debt_type : 'other', 'other'); ?>>
                                        <?php _e('Other', 'ak-debt-ledger'); ?>
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label for="status"><?php _e('Status', 'ak-debt-ledger'); ?></label>
                                <select name="status" id="status">
                                    <option value="open" <?php selected($is_edit ? $entry->status : 'open', 'open'); ?>>
                                        <?php _e('Open', 'ak-debt-ledger'); ?>
                                    </option>
                                    <option value="paid" <?php selected($is_edit ? $entry->status : '', 'paid'); ?>>
                                        <?php _e('Paid', 'ak-debt-ledger'); ?>
                                    </option>
                                    <option value="written_off" <?php selected($is_edit ? $entry->status : '', 'written_off'); ?>>
                                        <?php _e('Written Off', 'ak-debt-ledger'); ?>
                                    </option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="ak-form-row">
                            <label for="notes"><?php _e('Notes', 'ak-debt-ledger'); ?></label>
                            <textarea name="notes" id="notes" rows="3"><?php echo $is_edit ? esc_textarea($entry->notes) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="ak-form-section">
                        <h2><?php _e('Reminder Settings', 'ak-debt-ledger'); ?></h2>
                        
                        <div class="ak-form-row">
                            <label class="ak-checkbox-label">
                                <input type="checkbox" name="consent_to_reminders" id="consent_to_reminders" value="1"
                                       <?php checked($is_edit ? $entry->consent_to_reminders : 0, 1); ?>>
                                <?php _e('Customer consents to receive reminders', 'ak-debt-ledger'); ?>
                            </label>
                        </div>
                        
                        <div class="ak-form-row ak-form-row-half ak-reminder-fields">
                            <div>
                                <label for="preferred_channel"><?php _e('Preferred Channel', 'ak-debt-ledger'); ?></label>
                                <select name="preferred_channel" id="preferred_channel">
                                    <option value="both" <?php selected($is_edit ? $entry->preferred_channel : 'both', 'both'); ?>>
                                        <?php _e('SMS & Email', 'ak-debt-ledger'); ?>
                                    </option>
                                    <option value="sms" <?php selected($is_edit ? $entry->preferred_channel : '', 'sms'); ?>>
                                        <?php _e('SMS Only', 'ak-debt-ledger'); ?>
                                    </option>
                                    <option value="email" <?php selected($is_edit ? $entry->preferred_channel : '', 'email'); ?>>
                                        <?php _e('Email Only', 'ak-debt-ledger'); ?>
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label for="next_reminder_at"><?php _e('Next Reminder At', 'ak-debt-ledger'); ?></label>
                                <input type="datetime-local" name="next_reminder_at" id="next_reminder_at"
                                       value="<?php echo $is_edit && $entry->next_reminder_at ? esc_attr(date('Y-m-d\TH:i', strtotime($entry->next_reminder_at))) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="ak-form-actions">
                        <button type="submit" class="button button-primary button-large">
                            <?php echo $is_edit ? __('Update Debt', 'ak-debt-ledger') : __('Add Debt', 'ak-debt-ledger'); ?>
                        </button>
                        <a href="<?php echo admin_url('admin.php?page=ak-debt-ledger'); ?>" class="button button-large">
                            <?php _e('Cancel', 'ak-debt-ledger'); ?>
                        </a>
                    </div>
                </form>
                
                <?php if ($is_edit) : ?>
                <!-- Payments Section -->
                <div class="ak-form-section ak-payments-section">
                    <h2><?php _e('Payment History', 'ak-debt-ledger'); ?></h2>
                    
                    <div class="ak-add-payment-form">
                        <h3><?php _e('Record Payment', 'ak-debt-ledger'); ?></h3>
                        <form id="ak-payment-form">
                            <input type="hidden" name="ledger_id" value="<?php echo esc_attr($entry->id); ?>">
                            
                            <div class="ak-form-row ak-form-row-quarter">
                                <div>
                                    <label for="payment_date"><?php _e('Date', 'ak-debt-ledger'); ?></label>
                                    <input type="date" name="payment_date" id="payment_date" required
                                           value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div>
                                    <label for="payment_amount"><?php _e('Amount', 'ak-debt-ledger'); ?></label>
                                    <input type="number" name="payment_amount" id="payment_amount" step="0.01" min="0.01" required>
                                </div>
                                <div>
                                    <label for="payment_method"><?php _e('Method', 'ak-debt-ledger'); ?></label>
                                    <select name="payment_method" id="payment_method">
                                        <option value="bank_transfer"><?php _e('Bank Transfer', 'ak-debt-ledger'); ?></option>
                                        <option value="cash"><?php _e('Cash', 'ak-debt-ledger'); ?></option>
                                        <option value="card"><?php _e('Card', 'ak-debt-ledger'); ?></option>
                                        <option value="other"><?php _e('Other', 'ak-debt-ledger'); ?></option>
                                    </select>
                                </div>
                                <div>
                                    <label for="payment_reference"><?php _e('Reference', 'ak-debt-ledger'); ?></label>
                                    <input type="text" name="payment_reference" id="payment_reference">
                                </div>
                            </div>
                            
                            <div class="ak-form-row">
                                <label for="payment_note"><?php _e('Note', 'ak-debt-ledger'); ?></label>
                                <input type="text" name="payment_note" id="payment_note">
                            </div>
                            
                            <button type="submit" class="button button-primary">
                                <?php _e('Add Payment', 'ak-debt-ledger'); ?>
                            </button>
                        </form>
                    </div>
                    
                    <?php if (!empty($payments)) : ?>
                    <table class="wp-list-table widefat fixed striped ak-payments-table">
                        <thead>
                            <tr>
                                <th><?php _e('Date', 'ak-debt-ledger'); ?></th>
                                <th><?php _e('Amount', 'ak-debt-ledger'); ?></th>
                                <th><?php _e('Method', 'ak-debt-ledger'); ?></th>
                                <th><?php _e('Reference', 'ak-debt-ledger'); ?></th>
                                <th><?php _e('Status', 'ak-debt-ledger'); ?></th>
                                <th><?php _e('Actions', 'ak-debt-ledger'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment) : ?>
                            <tr>
                                <td><?php echo esc_html(date('d M Y', strtotime($payment->payment_date))); ?></td>
                                <td><?php echo esc_html($entry->currency . number_format($payment->amount, 2)); ?></td>
                                <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $payment->method))); ?></td>
                                <td><?php echo esc_html($payment->reference); ?></td>
                                <td>
                                    <?php if ($payment->confirmed) : ?>
                                        <span class="status-badge status-confirmed">
                                            <?php _e('Confirmed', 'ak-debt-ledger'); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="status-badge status-pending">
                                            <?php _e('Pending', 'ak-debt-ledger'); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$payment->confirmed) : ?>
                                    <button type="button" class="button button-small ak-confirm-payment" 
                                            data-id="<?php echo esc_attr($payment->id); ?>"
                                            data-ledger="<?php echo esc_attr($entry->id); ?>">
                                        <span class="dashicons dashicons-yes"></span>
                                        <?php _e('Confirm Payment', 'ak-debt-ledger'); ?>
                                    </button>
                                    <?php else : ?>
                                    <span class="text-muted">
                                        <?php printf(__('Confirmed %s', 'ak-debt-ledger'), date('d M Y H:i', strtotime($payment->confirmed_at))); ?>
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else : ?>
                    <p class="description"><?php _e('No payments recorded yet.', 'ak-debt-ledger'); ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Reminder History Section -->
                <?php if (!empty($reminder_history)) : ?>
                <div class="ak-form-section">
                    <h2><?php _e('Reminder History', 'ak-debt-ledger'); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Sent At', 'ak-debt-ledger'); ?></th>
                                <th><?php _e('Channel', 'ak-debt-ledger'); ?></th>
                                <th><?php _e('Status', 'ak-debt-ledger'); ?></th>
                                <th><?php _e('Message', 'ak-debt-ledger'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reminder_history as $reminder) : ?>
                            <tr>
                                <td><?php echo esc_html(date('d M Y H:i', strtotime($reminder->sent_at))); ?></td>
                                <td><?php echo esc_html(strtoupper($reminder->channel)); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($reminder->status); ?>">
                                        <?php echo esc_html(ucfirst($reminder->status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo esc_html(substr($reminder->message, 0, 100)) . (strlen($reminder->message) > 100 ? '...' : ''); ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        $settings = get_option('ak_debt_ledger_settings', array());
        
        // Handle form submission
        if (isset($_POST['ak_settings_nonce']) && wp_verify_nonce($_POST['ak_settings_nonce'], 'ak_debt_ledger_settings')) {
            $settings['twilio_account_sid'] = sanitize_text_field($_POST['twilio_account_sid']);
            $settings['twilio_auth_token'] = sanitize_text_field($_POST['twilio_auth_token']);
            $settings['twilio_phone_number'] = sanitize_text_field($_POST['twilio_phone_number']);
            $settings['reminder_interval_days'] = intval($_POST['reminder_interval_days']);
            $settings['from_email'] = sanitize_email($_POST['from_email']);
            $settings['from_name'] = sanitize_text_field($_POST['from_name']);
            
            update_option('ak_debt_ledger_settings', $settings);
            
            echo '<div class="notice notice-success"><p>' . __('Settings saved successfully.', 'ak-debt-ledger') . '</p></div>';
        }
        ?>
        <div class="wrap ak-debt-ledger-wrap">
            <h1><?php _e('Debt Ledger Settings', 'ak-debt-ledger'); ?></h1>
            
            <form method="post">
                <?php wp_nonce_field('ak_debt_ledger_settings', 'ak_settings_nonce'); ?>
                
                <div class="ak-settings-section">
                    <h2><?php _e('Twilio SMS Settings', 'ak-debt-ledger'); ?></h2>
                    <p class="description">
                        <?php _e('Get your Twilio credentials from', 'ak-debt-ledger'); ?> 
                        <a href="https://console.twilio.com" target="_blank">console.twilio.com</a>
                    </p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="twilio_account_sid"><?php _e('Account SID', 'ak-debt-ledger'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="twilio_account_sid" id="twilio_account_sid" 
                                       class="regular-text" 
                                       value="<?php echo esc_attr(isset($settings['twilio_account_sid']) ? $settings['twilio_account_sid'] : ''); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="twilio_auth_token"><?php _e('Auth Token', 'ak-debt-ledger'); ?></label>
                            </th>
                            <td>
                                <input type="password" name="twilio_auth_token" id="twilio_auth_token" 
                                       class="regular-text" 
                                       value="<?php echo esc_attr(isset($settings['twilio_auth_token']) ? $settings['twilio_auth_token'] : ''); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="twilio_phone_number"><?php _e('From Phone Number', 'ak-debt-ledger'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="twilio_phone_number" id="twilio_phone_number" 
                                       class="regular-text" placeholder="+44XXXXXXXXXX"
                                       value="<?php echo esc_attr(isset($settings['twilio_phone_number']) ? $settings['twilio_phone_number'] : ''); ?>">
                                <p class="description"><?php _e('Your Twilio phone number in E.164 format', 'ak-debt-ledger'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Test Connection', 'ak-debt-ledger'); ?></th>
                            <td>
                                <button type="button" id="ak-test-twilio" class="button">
                                    <?php _e('Test Twilio Connection', 'ak-debt-ledger'); ?>
                                </button>
                                <span id="ak-twilio-test-result"></span>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="ak-settings-section">
                    <h2><?php _e('Email Settings', 'ak-debt-ledger'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="from_name"><?php _e('From Name', 'ak-debt-ledger'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="from_name" id="from_name" 
                                       class="regular-text" 
                                       value="<?php echo esc_attr(isset($settings['from_name']) ? $settings['from_name'] : get_bloginfo('name')); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="from_email"><?php _e('From Email', 'ak-debt-ledger'); ?></label>
                            </th>
                            <td>
                                <input type="email" name="from_email" id="from_email" 
                                       class="regular-text" 
                                       value="<?php echo esc_attr(isset($settings['from_email']) ? $settings['from_email'] : get_option('admin_email')); ?>">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="ak-settings-section">
                    <h2><?php _e('Reminder Settings', 'ak-debt-ledger'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="reminder_interval_days"><?php _e('Reminder Interval', 'ak-debt-ledger'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="reminder_interval_days" id="reminder_interval_days" 
                                       min="1" max="90" class="small-text" 
                                       value="<?php echo esc_attr(isset($settings['reminder_interval_days']) ? $settings['reminder_interval_days'] : 7); ?>">
                                <?php _e('days', 'ak-debt-ledger'); ?>
                                <p class="description"><?php _e('How often to send automatic reminders', 'ak-debt-ledger'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(__('Save Settings', 'ak-debt-ledger')); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render templates page
     */
    public function render_templates_page() {
        $settings = get_option('ak_debt_ledger_settings', array());
        
        // Handle form submission
        if (isset($_POST['ak_templates_nonce']) && wp_verify_nonce($_POST['ak_templates_nonce'], 'ak_debt_ledger_templates')) {
            $settings['sms_template'] = sanitize_textarea_field($_POST['sms_template']);
            $settings['email_subject_template'] = sanitize_text_field($_POST['email_subject_template']);
            $settings['email_body_template'] = sanitize_textarea_field($_POST['email_body_template']);
            
            update_option('ak_debt_ledger_settings', $settings);
            
            echo '<div class="notice notice-success"><p>' . __('Templates saved successfully.', 'ak-debt-ledger') . '</p></div>';
        }
        
        $default_sms = 'Hi {customer_name}, this is a reminder that you have an outstanding balance of {currency}{current_balance}. Please arrange payment at your earliest convenience. - AppointmentKeeper';
        $default_email_subject = 'Payment Reminder - Outstanding Balance of {currency}{current_balance}';
        $default_email_body = "Dear {customer_name},\n\nThis is a friendly reminder that you have an outstanding balance of {currency}{current_balance}.\n\nPlease arrange payment at your earliest convenience.\n\nIf you have already made this payment, please disregard this message.\n\nBest regards,\nAppointmentKeeper";
        ?>
        <div class="wrap ak-debt-ledger-wrap">
            <h1><?php _e('Message Templates', 'ak-debt-ledger'); ?></h1>
            
            <div class="ak-templates-help">
                <h3><?php _e('Available Placeholders', 'ak-debt-ledger'); ?></h3>
                <p><?php _e('Use these placeholders in your templates. They will be replaced with actual customer data:', 'ak-debt-ledger'); ?></p>
                <ul>
                    <li><code>{customer_name}</code> - <?php _e('Customer\'s full name', 'ak-debt-ledger'); ?></li>
                    <li><code>{customer_email}</code> - <?php _e('Customer\'s email address', 'ak-debt-ledger'); ?></li>
                    <li><code>{customer_phone}</code> - <?php _e('Customer\'s phone number', 'ak-debt-ledger'); ?></li>
                    <li><code>{original_amount}</code> - <?php _e('Original debt amount', 'ak-debt-ledger'); ?></li>
                    <li><code>{current_balance}</code> - <?php _e('Current outstanding balance', 'ak-debt-ledger'); ?></li>
                    <li><code>{currency}</code> - <?php _e('Currency symbol (GBP, USD, EUR)', 'ak-debt-ledger'); ?></li>
                    <li><code>{debt_type}</code> - <?php _e('Type of debt', 'ak-debt-ledger'); ?></li>
                </ul>
            </div>
            
            <form method="post">
                <?php wp_nonce_field('ak_debt_ledger_templates', 'ak_templates_nonce'); ?>
                
                <div class="ak-settings-section">
                    <h2><?php _e('SMS Template', 'ak-debt-ledger'); ?></h2>
                    <p class="description"><?php _e('Keep SMS messages short (160 characters recommended for single message).', 'ak-debt-ledger'); ?></p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="sms_template"><?php _e('SMS Message', 'ak-debt-ledger'); ?></label>
                            </th>
                            <td>
                                <textarea name="sms_template" id="sms_template" rows="4" class="large-text"><?php 
                                    echo esc_textarea(isset($settings['sms_template']) ? $settings['sms_template'] : $default_sms); 
                                ?></textarea>
                                <p class="description">
                                    <span id="sms-char-count">0</span> <?php _e('characters', 'ak-debt-ledger'); ?>
                                </p>
                                <button type="button" class="button ak-reset-template" data-target="sms_template" data-default="<?php echo esc_attr($default_sms); ?>">
                                    <?php _e('Reset to Default', 'ak-debt-ledger'); ?>
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="ak-settings-section">
                    <h2><?php _e('Email Template', 'ak-debt-ledger'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="email_subject_template"><?php _e('Email Subject', 'ak-debt-ledger'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="email_subject_template" id="email_subject_template" 
                                       class="large-text" 
                                       value="<?php echo esc_attr(isset($settings['email_subject_template']) ? $settings['email_subject_template'] : $default_email_subject); ?>">
                                <button type="button" class="button ak-reset-template" data-target="email_subject_template" data-default="<?php echo esc_attr($default_email_subject); ?>">
                                    <?php _e('Reset to Default', 'ak-debt-ledger'); ?>
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="email_body_template"><?php _e('Email Body', 'ak-debt-ledger'); ?></label>
                            </th>
                            <td>
                                <textarea name="email_body_template" id="email_body_template" rows="10" class="large-text"><?php 
                                    echo esc_textarea(isset($settings['email_body_template']) ? $settings['email_body_template'] : $default_email_body); 
                                ?></textarea>
                                <button type="button" class="button ak-reset-template" data-target="email_body_template" data-default="<?php echo esc_attr($default_email_body); ?>">
                                    <?php _e('Reset to Default', 'ak-debt-ledger'); ?>
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="ak-settings-section">
                    <h2><?php _e('Pre-made Templates', 'ak-debt-ledger'); ?></h2>
                    <p class="description"><?php _e('Click to use these pre-made templates:', 'ak-debt-ledger'); ?></p>
                    
                    <div class="ak-premade-templates">
                        <div class="ak-template-card">
                            <h4><?php _e('Friendly Reminder', 'ak-debt-ledger'); ?></h4>
                            <p><strong>SMS:</strong> Hi {customer_name}! Just a friendly reminder about your balance of {currency}{current_balance}. Please get in touch if you need help. Thanks!</p>
                            <button type="button" class="button ak-use-template" 
                                    data-sms="Hi {customer_name}! Just a friendly reminder about your balance of {currency}{current_balance}. Please get in touch if you need help. Thanks!"
                                    data-subject="Friendly Reminder - Balance of {currency}{current_balance}"
                                    data-body="Hi {customer_name},

Hope you're doing well! This is just a friendly reminder that you have an outstanding balance of {currency}{current_balance} with us.

If you've already arranged payment, thank you! If not, please get in touch and we can discuss options.

Take care,
AppointmentKeeper">
                                <?php _e('Use This Template', 'ak-debt-ledger'); ?>
                            </button>
                        </div>
                        
                        <div class="ak-template-card">
                            <h4><?php _e('Formal Notice', 'ak-debt-ledger'); ?></h4>
                            <p><strong>SMS:</strong> PAYMENT REMINDER: {customer_name}, your account shows {currency}{current_balance} outstanding. Please arrange payment promptly to avoid further action.</p>
                            <button type="button" class="button ak-use-template" 
                                    data-sms="PAYMENT REMINDER: {customer_name}, your account shows {currency}{current_balance} outstanding. Please arrange payment promptly to avoid further action."
                                    data-subject="Important: Payment Required - {currency}{current_balance} Outstanding"
                                    data-body="Dear {customer_name},

Our records indicate that you have an outstanding balance of {currency}{current_balance}.

Original amount: {currency}{original_amount}
Current balance: {currency}{current_balance}

Please arrange payment at your earliest convenience to bring your account up to date.

If you have any questions about this balance, please contact us immediately.

Regards,
AppointmentKeeper">
                                <?php _e('Use This Template', 'ak-debt-ledger'); ?>
                            </button>
                        </div>
                        
                        <div class="ak-template-card">
                            <h4><?php _e('Payment Thank You', 'ak-debt-ledger'); ?></h4>
                            <p><strong>SMS:</strong> Thank you {customer_name}! Your payment has been confirmed. Remaining balance: {currency}{current_balance}. Much appreciated!</p>
                            <button type="button" class="button ak-use-template" 
                                    data-sms="Thank you {customer_name}! Your payment has been confirmed. Remaining balance: {currency}{current_balance}. Much appreciated!"
                                    data-subject="Payment Received - Thank You!"
                                    data-body="Dear {customer_name},

Thank you for your recent payment!

Your remaining balance is now: {currency}{current_balance}

We really appreciate your prompt attention to this matter.

Best wishes,
AppointmentKeeper">
                                <?php _e('Use This Template', 'ak-debt-ledger'); ?>
                            </button>
                        </div>
                    </div>
                </div>
                
                <?php submit_button(__('Save Templates', 'ak-debt-ledger')); ?>
            </form>
        </div>
        <?php
    }
}
