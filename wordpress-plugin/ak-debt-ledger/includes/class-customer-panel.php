<?php
/**
 * Customer Panel - Frontend views for customers to manage their ledger
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Debt_Ledger_Customer_Panel {
    
    public function __construct() {
        // Register shortcodes
        add_shortcode('ak_debt_ledger', array($this, 'render_ledger_shortcode'));
        add_shortcode('ak_credit_balance', array($this, 'render_credit_balance_shortcode'));
        add_shortcode('ak_refer_friend', array($this, 'render_refer_friend_shortcode'));
        add_shortcode('ak_usage_history', array($this, 'render_usage_history_shortcode'));
        
        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        
        // Add to Amelia panel if available
        add_action('amelia_customer_panel_after', array($this, 'add_to_amelia_panel'));
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        if (!is_user_logged_in()) return;
        
        wp_enqueue_style(
            'ak-debt-ledger-frontend',
            AK_DEBT_LEDGER_PLUGIN_URL . 'admin/css/frontend.css',
            array(),
            AK_DEBT_LEDGER_VERSION
        );
        
        wp_enqueue_script(
            'ak-debt-ledger-frontend',
            AK_DEBT_LEDGER_PLUGIN_URL . 'admin/js/frontend.js',
            array('jquery'),
            AK_DEBT_LEDGER_VERSION,
            true
        );
        
        wp_localize_script('ak-debt-ledger-frontend', 'akDebtLedgerFront', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ak_debt_ledger_nonce'),
            'strings' => array(
                'sending' => __('Sending...', 'ak-debt-ledger'),
                'sent' => __('Sent!', 'ak-debt-ledger'),
                'error' => __('Error occurred', 'ak-debt-ledger'),
                'confirmPayment' => __('Confirm this payment?', 'ak-debt-ledger')
            )
        ));
    }
    
    /**
     * Add ledger tab to Amelia customer panel
     */
    public function add_to_amelia_panel() {
        if (!is_user_logged_in()) return;
        
        echo '<div class="ak-amelia-panel-section">';
        echo '<h3>' . __('Debt Ledger', 'ak-debt-ledger') . '</h3>';
        echo do_shortcode('[ak_credit_balance]');
        echo do_shortcode('[ak_debt_ledger]');
        echo '</div>';
    }
    
    /**
     * Render credit balance shortcode
     */
    public function render_credit_balance_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p class="ak-login-required">' . __('Please log in to view your credit balance.', 'ak-debt-ledger') . '</p>';
        }
        
        $user_id = get_current_user_id();
        $credits = AK_Debt_Ledger_Database::get_customer_credits($user_id);
        $usage = AK_Debt_Ledger_Database::get_usage_summary($user_id);
        
        ob_start();
        ?>
        <div class="ak-credit-balance-widget">
            <h4><?php _e('Your Credit Balance', 'ak-debt-ledger'); ?></h4>
            <div class="ak-credits-grid">
                <div class="ak-credit-box ak-credit-sms">
                    <span class="ak-credit-icon">💬</span>
                    <span class="ak-credit-number"><?php echo intval($credits->sms_credits); ?></span>
                    <span class="ak-credit-label"><?php _e('SMS', 'ak-debt-ledger'); ?></span>
                </div>
                <div class="ak-credit-box ak-credit-call">
                    <span class="ak-credit-icon">📞</span>
                    <span class="ak-credit-number"><?php echo intval($credits->call_credits); ?></span>
                    <span class="ak-credit-label"><?php _e('Calls', 'ak-debt-ledger'); ?></span>
                </div>
                <div class="ak-credit-box ak-credit-email">
                    <span class="ak-credit-icon">✉️</span>
                    <span class="ak-credit-number"><?php echo intval($credits->email_credits); ?></span>
                    <span class="ak-credit-label"><?php _e('Emails', 'ak-debt-ledger'); ?></span>
                </div>
            </div>
            <?php if ($usage) : ?>
            <div class="ak-usage-this-month">
                <small><?php _e('Used this month:', 'ak-debt-ledger'); ?> 
                    <?php echo intval($usage->sms_used); ?> SMS, 
                    <?php echo intval($usage->calls_used); ?> calls, 
                    <?php echo intval($usage->emails_used); ?> emails
                </small>
            </div>
            <?php endif; ?>
            <?php if ($credits->sms_credits <= 10 || $credits->call_credits <= 5 || $credits->email_credits <= 10) : ?>
            <div class="ak-low-credit-warning">
                ⚠️ <?php _e('Running low on credits!', 'ak-debt-ledger'); ?> 
                <a href="#"><?php _e('Top up now', 'ak-debt-ledger'); ?></a>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render main ledger shortcode
     */
    public function render_ledger_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p class="ak-login-required">' . __('Please log in to view your debt ledger.', 'ak-debt-ledger') . '</p>';
        }
        
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $entries = AK_Debt_Ledger_Database::get_user_ledger_entries($user_id);
        
        ob_start();
        ?>
        <div class="ak-ledger-frontend">
            <div class="ak-ledger-header">
                <h3><?php _e('Who owes you money?', 'ak-debt-ledger'); ?></h3>
                <button type="button" class="ak-btn ak-btn-primary" id="ak-add-debt-btn">
                    + <?php _e('Add New Debt', 'ak-debt-ledger'); ?>
                </button>
            </div>
            
            <!-- Add Debt Form (Hidden by default) -->
            <div id="ak-add-debt-form" class="ak-form-panel" style="display:none;">
                <h4><?php _e('Add New Debt', 'ak-debt-ledger'); ?></h4>
                <form id="ak-debt-form-frontend">
                    <input type="hidden" name="user_id" value="<?php echo esc_attr($user_id); ?>">
                    
                    <div class="ak-form-section">
                        <h5><?php _e('Your Details (Creditor)', 'ak-debt-ledger'); ?></h5>
                        <div class="ak-form-row">
                            <label><?php _e('Your Name / Business', 'ak-debt-ledger'); ?></label>
                            <input type="text" name="creditor_name" value="<?php echo esc_attr($user->display_name); ?>" required>
                        </div>
                    </div>
                    
                    <div class="ak-form-section">
                        <h5><?php _e('Debtor Details (Who owes you)', 'ak-debt-ledger'); ?></h5>
                        
                        <div class="ak-form-row">
                            <label><?php _e('How many people owe this debt?', 'ak-debt-ledger'); ?></label>
                            <select name="num_debtors" id="ak-num-debtors">
                                <option value="1"><?php _e('1 Person', 'ak-debt-ledger'); ?></option>
                                <option value="2"><?php _e('2 People', 'ak-debt-ledger'); ?></option>
                                <option value="3"><?php _e('3 People', 'ak-debt-ledger'); ?></option>
                            </select>
                        </div>
                        
                        <div class="ak-debtor-fields" id="ak-debtor-1">
                            <h6><?php _e('Debtor 1 (Primary)', 'ak-debt-ledger'); ?></h6>
                            <div class="ak-form-row-inline">
                                <input type="text" name="customer_name" placeholder="<?php _e('Full Name', 'ak-debt-ledger'); ?>" required>
                                <input type="tel" name="customer_phone" placeholder="<?php _e('Phone Number', 'ak-debt-ledger'); ?>" required>
                            </div>
                            <input type="email" name="customer_email" placeholder="<?php _e('Email (optional)', 'ak-debt-ledger'); ?>">
                        </div>
                        
                        <div class="ak-debtor-fields ak-extra-debtor" id="ak-debtor-2" style="display:none;">
                            <h6><?php _e('Debtor 2', 'ak-debt-ledger'); ?></h6>
                            <div class="ak-form-row-inline">
                                <input type="text" name="debtor2_name" placeholder="<?php _e('Full Name', 'ak-debt-ledger'); ?>">
                                <input type="tel" name="debtor2_phone" placeholder="<?php _e('Phone Number', 'ak-debt-ledger'); ?>">
                            </div>
                        </div>
                        
                        <div class="ak-debtor-fields ak-extra-debtor" id="ak-debtor-3" style="display:none;">
                            <h6><?php _e('Debtor 3', 'ak-debt-ledger'); ?></h6>
                            <div class="ak-form-row-inline">
                                <input type="text" name="debtor3_name" placeholder="<?php _e('Full Name', 'ak-debt-ledger'); ?>">
                                <input type="tel" name="debtor3_phone" placeholder="<?php _e('Phone Number', 'ak-debt-ledger'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="ak-form-section">
                        <h5><?php _e('Debt Details', 'ak-debt-ledger'); ?></h5>
                        <div class="ak-form-row-inline">
                            <div>
                                <label><?php _e('Amount Owed', 'ak-debt-ledger'); ?></label>
                                <input type="number" name="original_amount" step="0.01" min="0.01" required>
                            </div>
                            <div>
                                <label><?php _e('Currency', 'ak-debt-ledger'); ?></label>
                                <select name="currency">
                                    <option value="GBP">£ GBP</option>
                                    <option value="USD">$ USD</option>
                                    <option value="EUR">€ EUR</option>
                                </select>
                            </div>
                        </div>
                        <div class="ak-form-row">
                            <label><?php _e('Notes (optional)', 'ak-debt-ledger'); ?></label>
                            <textarea name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    
                    <div class="ak-form-section">
                        <h5><?php _e('How should we remind them?', 'ak-debt-ledger'); ?></h5>
                        <p class="ak-form-help"><?php _e('Select all that apply. Each reminder uses 1 credit.', 'ak-debt-ledger'); ?></p>
                        <div class="ak-checkbox-group">
                            <label><input type="checkbox" name="remind_via_sms" value="1" checked> <?php _e('SMS Text Message', 'ak-debt-ledger'); ?></label>
                            <label><input type="checkbox" name="remind_via_email" value="1" checked> <?php _e('Email', 'ak-debt-ledger'); ?></label>
                            <label><input type="checkbox" name="remind_via_call" value="1"> <?php _e('Phone Call (AI Voice)', 'ak-debt-ledger'); ?></label>
                        </div>
                        <div class="ak-form-row">
                            <label><input type="checkbox" name="consent_to_reminders" value="1" checked> <?php _e('Send automatic reminders', 'ak-debt-ledger'); ?></label>
                        </div>
                    </div>
                    
                    <div class="ak-form-actions">
                        <button type="submit" class="ak-btn ak-btn-primary"><?php _e('Add Debt', 'ak-debt-ledger'); ?></button>
                        <button type="button" class="ak-btn ak-btn-secondary" id="ak-cancel-add"><?php _e('Cancel', 'ak-debt-ledger'); ?></button>
                    </div>
                </form>
            </div>
            
            <!-- Debt List -->
            <div class="ak-debt-list">
                <?php if (empty($entries)) : ?>
                    <p class="ak-no-debts"><?php _e('No debts recorded yet. Click "Add New Debt" to get started.', 'ak-debt-ledger'); ?></p>
                <?php else : ?>
                    <?php foreach ($entries as $entry) : ?>
                    <div class="ak-debt-card ak-status-<?php echo esc_attr($entry->status); ?>" data-id="<?php echo esc_attr($entry->id); ?>">
                        <div class="ak-debt-main">
                            <div class="ak-debt-info">
                                <h4><?php echo esc_html($entry->customer_name); ?></h4>
                                <span class="ak-debt-phone"><?php echo esc_html($entry->customer_phone); ?></span>
                                <?php if ($entry->num_debtors > 1) : ?>
                                    <span class="ak-joint-debt">+<?php echo ($entry->num_debtors - 1); ?> <?php _e('more', 'ak-debt-ledger'); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="ak-debt-amount">
                                <span class="ak-balance <?php echo $entry->current_balance > 0 ? 'ak-owing' : 'ak-paid'; ?>">
                                    <?php echo esc_html($entry->currency . number_format($entry->current_balance, 2)); ?>
                                </span>
                                <?php if ($entry->current_balance < $entry->original_amount) : ?>
                                    <small><?php _e('of', 'ak-debt-ledger'); ?> <?php echo esc_html($entry->currency . number_format($entry->original_amount, 2)); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="ak-debt-status">
                            <span class="ak-status-badge"><?php echo esc_html(ucfirst(str_replace('_', ' ', $entry->status))); ?></span>
                            <?php if ($entry->reminder_count > 0) : ?>
                                <span class="ak-reminder-count"><?php echo $entry->reminder_count; ?> <?php _e('reminders sent', 'ak-debt-ledger'); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($entry->status === 'open') : ?>
                        <div class="ak-debt-actions">
                            <button type="button" class="ak-btn ak-btn-small ak-send-reminder" data-id="<?php echo esc_attr($entry->id); ?>">
                                <?php _e('Send Reminder', 'ak-debt-ledger'); ?>
                            </button>
                            <button type="button" class="ak-btn ak-btn-small ak-btn-success ak-record-payment" data-id="<?php echo esc_attr($entry->id); ?>">
                                <?php _e('Record Payment', 'ak-debt-ledger'); ?>
                            </button>
                            <button type="button" class="ak-btn ak-btn-small ak-btn-outline ak-mark-paid" data-id="<?php echo esc_attr($entry->id); ?>">
                                <?php _e('Mark Paid', 'ak-debt-ledger'); ?>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render refer a friend shortcode
     */
    public function render_refer_friend_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p class="ak-login-required">' . __('Please log in to refer friends.', 'ak-debt-ledger') . '</p>';
        }
        
        $user_id = get_current_user_id();
        $user = wp_get_current_user();
        $referrals = AK_Debt_Ledger_Database::get_user_referrals($user_id);
        
        ob_start();
        ?>
        <div class="ak-refer-friend-widget">
            <div class="ak-refer-header">
                <h4><?php _e('Do you have friends who are late for appointments?', 'ak-debt-ledger'); ?></h4>
                <p><?php _e('If one of them signs up, you get a FREE month on AppointmentKeeper!', 'ak-debt-ledger'); ?></p>
            </div>
            
            <form id="ak-refer-form">
                <div class="ak-form-row">
                    <label><?php _e('How many friends would you like to invite?', 'ak-debt-ledger'); ?></label>
                    <select name="num_friends" id="ak-num-friends">
                        <option value="0"><?php _e('0 - No thanks', 'ak-debt-ledger'); ?></option>
                        <option value="1"><?php _e('1 Friend', 'ak-debt-ledger'); ?></option>
                        <option value="2"><?php _e('2 Friends', 'ak-debt-ledger'); ?></option>
                        <option value="3"><?php _e('3 Friends', 'ak-debt-ledger'); ?></option>
                    </select>
                </div>
                
                <div id="ak-friend-fields" style="display:none;">
                    <div class="ak-friend-field" id="ak-friend-1" style="display:none;">
                        <h5><?php _e('Friend 1', 'ak-debt-ledger'); ?></h5>
                        <div class="ak-form-row-inline">
                            <input type="text" name="friend1_name" placeholder="<?php _e('Friend\'s Name', 'ak-debt-ledger'); ?>">
                            <input type="tel" name="friend1_phone" placeholder="<?php _e('Phone Number', 'ak-debt-ledger'); ?>">
                        </div>
                    </div>
                    <div class="ak-friend-field" id="ak-friend-2" style="display:none;">
                        <h5><?php _e('Friend 2', 'ak-debt-ledger'); ?></h5>
                        <div class="ak-form-row-inline">
                            <input type="text" name="friend2_name" placeholder="<?php _e('Friend\'s Name', 'ak-debt-ledger'); ?>">
                            <input type="tel" name="friend2_phone" placeholder="<?php _e('Phone Number', 'ak-debt-ledger'); ?>">
                        </div>
                    </div>
                    <div class="ak-friend-field" id="ak-friend-3" style="display:none;">
                        <h5><?php _e('Friend 3', 'ak-debt-ledger'); ?></h5>
                        <div class="ak-form-row-inline">
                            <input type="text" name="friend3_name" placeholder="<?php _e('Friend\'s Name', 'ak-debt-ledger'); ?>">
                            <input type="tel" name="friend3_phone" placeholder="<?php _e('Phone Number', 'ak-debt-ledger'); ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="ak-btn ak-btn-primary"><?php _e('Send Invites', 'ak-debt-ledger'); ?></button>
                    <p class="ak-form-help"><?php _e('Each invite uses 1 SMS credit.', 'ak-debt-ledger'); ?></p>
                </div>
            </form>
            
            <?php if (!empty($referrals)) : ?>
            <div class="ak-referral-history">
                <h5><?php _e('Your Referrals', 'ak-debt-ledger'); ?></h5>
                <ul>
                    <?php foreach ($referrals as $ref) : ?>
                    <li>
                        <?php echo esc_html($ref->friend_name); ?> 
                        <?php if ($ref->signed_up) : ?>
                            <span class="ak-badge ak-badge-success"><?php _e('Signed up!', 'ak-debt-ledger'); ?></span>
                            <?php if ($ref->reward_given) : ?>
                                <span class="ak-badge"><?php _e('Reward claimed', 'ak-debt-ledger'); ?></span>
                            <?php endif; ?>
                        <?php elseif ($ref->invite_sent) : ?>
                            <span class="ak-badge ak-badge-pending"><?php _e('Invite sent', 'ak-debt-ledger'); ?></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render usage history shortcode
     */
    public function render_usage_history_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p class="ak-login-required">' . __('Please log in to view usage history.', 'ak-debt-ledger') . '</p>';
        }
        
        $user_id = get_current_user_id();
        $history = AK_Debt_Ledger_Database::get_usage_history($user_id, 20);
        
        ob_start();
        ?>
        <div class="ak-usage-history-widget">
            <h4><?php _e('Usage History', 'ak-debt-ledger'); ?></h4>
            
            <?php if (empty($history)) : ?>
                <p><?php _e('No usage recorded yet.', 'ak-debt-ledger'); ?></p>
            <?php else : ?>
                <table class="ak-usage-table">
                    <thead>
                        <tr>
                            <th><?php _e('Date', 'ak-debt-ledger'); ?></th>
                            <th><?php _e('Type', 'ak-debt-ledger'); ?></th>
                            <th><?php _e('Recipient', 'ak-debt-ledger'); ?></th>
                            <th><?php _e('Status', 'ak-debt-ledger'); ?></th>
                            <th><?php _e('Credits', 'ak-debt-ledger'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $item) : ?>
                        <tr class="ak-status-<?php echo esc_attr($item->status); ?>">
                            <td><?php echo esc_html(date('d M Y H:i', strtotime($item->created_at))); ?></td>
                            <td>
                                <?php 
                                $type_labels = array(
                                    'sms_sent' => '💬 SMS',
                                    'call_made' => '📞 Call',
                                    'email_sent' => '✉️ Email'
                                );
                                echo isset($type_labels[$item->usage_type]) ? $type_labels[$item->usage_type] : $item->usage_type;
                                ?>
                            </td>
                            <td><?php echo esc_html($item->recipient_phone ?: $item->recipient_email); ?></td>
                            <td>
                                <span class="ak-status-badge ak-status-<?php echo esc_attr($item->status); ?>">
                                    <?php echo esc_html(ucfirst($item->status)); ?>
                                </span>
                            </td>
                            <td><?php echo $item->status === 'failed' ? '0' : '-' . intval($item->credits_used); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
