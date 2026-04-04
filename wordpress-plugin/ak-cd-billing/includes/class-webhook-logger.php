<?php
/**
 * Stripe Webhook Logging
 * Stores last 50 webhook events for debugging
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_Webhook_Logger {
    
    private static $table_name;
    
    public function __construct() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'ak_webhook_log';
        
        // Create table on activation
        add_action('init', array($this, 'maybe_create_table'));
        
        // Hook into webhook handler
        add_action('ak_webhook_received', array($this, 'log_webhook'), 10, 3);
        
        // Admin page
        add_action('admin_menu', array($this, 'add_admin_page'));
        
        // AJAX for clearing logs
        add_action('wp_ajax_ak_clear_webhook_logs', array($this, 'clear_logs'));
    }
    
    /**
     * Create webhook log table
     */
    public function maybe_create_table() {
        global $wpdb;
        
        if (get_option('ak_webhook_table_version') === '1.0') {
            return;
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS " . self::$table_name . " (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(100) NOT NULL,
            event_id VARCHAR(100) DEFAULT NULL,
            payload LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'received',
            error_message TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        update_option('ak_webhook_table_version', '1.0');
    }
    
    /**
     * Log a webhook event
     */
    public function log_webhook($event_type, $event_id, $payload, $status = 'received', $error = null) {
        global $wpdb;
        
        $wpdb->insert(
            self::$table_name,
            array(
                'event_type' => $event_type,
                'event_id' => $event_id,
                'payload' => is_array($payload) ? json_encode($payload) : $payload,
                'status' => $status,
                'error_message' => $error
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
        
        // Keep only last 50 entries
        $this->cleanup_old_logs();
    }
    
    /**
     * Static method for external logging
     */
    public static function log($event_type, $event_id, $payload, $status = 'received', $error = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'ak_webhook_log';
        
        $wpdb->insert(
            $table,
            array(
                'event_type' => $event_type,
                'event_id' => $event_id,
                'payload' => is_array($payload) ? json_encode($payload) : $payload,
                'status' => $status,
                'error_message' => $error
            ),
            array('%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Remove old log entries (keep last 50)
     */
    private function cleanup_old_logs() {
        global $wpdb;
        
        $count = $wpdb->get_var("SELECT COUNT(*) FROM " . self::$table_name);
        
        if ($count > 50) {
            $to_delete = $count - 50;
            $wpdb->query("DELETE FROM " . self::$table_name . " ORDER BY created_at ASC LIMIT " . intval($to_delete));
        }
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_page() {
        add_submenu_page(
            'options-general.php',
            'Webhook Logs',
            'Webhook Logs',
            'manage_options',
            'ak-webhook-logs',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        global $wpdb;
        
        $logs = $wpdb->get_results(
            "SELECT * FROM " . self::$table_name . " ORDER BY created_at DESC LIMIT 50"
        );
        
        ?>
        <div class="wrap">
            <h1>Webhook Event Logs</h1>
            <p>Last 50 webhook events received from Stripe and other services.</p>
            
            <form method="post" style="margin-bottom:20px;">
                <?php wp_nonce_field('ak_clear_logs', 'ak_clear_logs_nonce'); ?>
                <button type="submit" name="ak_clear_logs" class="button">Clear All Logs</button>
            </form>
            
            <?php if (isset($_POST['ak_clear_logs']) && wp_verify_nonce($_POST['ak_clear_logs_nonce'], 'ak_clear_logs')): 
                $wpdb->query("TRUNCATE TABLE " . self::$table_name);
                echo '<div class="notice notice-success"><p>Logs cleared.</p></div>';
                $logs = array();
            endif; ?>
            
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Event Type</th>
                        <th>Event ID</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5">No webhook events logged yet.</td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html(date('d M Y H:i:s', strtotime($log->created_at))); ?></td>
                        <td><code><?php echo esc_html($log->event_type); ?></code></td>
                        <td><small><?php echo esc_html($log->event_id ?: '-'); ?></small></td>
                        <td>
                            <?php
                            $status_colors = array(
                                'received' => '#2196f3',
                                'processed' => '#4caf50',
                                'error' => '#f44336',
                                'ignored' => '#9e9e9e'
                            );
                            $color = isset($status_colors[$log->status]) ? $status_colors[$log->status] : '#666';
                            ?>
                            <span style="background:<?php echo $color; ?>;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;">
                                <?php echo esc_html(ucfirst($log->status)); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log->error_message): ?>
                                <span style="color:#f44336;"><?php echo esc_html($log->error_message); ?></span>
                            <?php else: ?>
                                <button type="button" class="button button-small ak-view-payload" data-payload="<?php echo esc_attr($log->payload); ?>">View Payload</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div id="ak-payload-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:99999;">
            <div style="background:#fff;max-width:700px;margin:50px auto;padding:20px;border-radius:8px;max-height:80vh;overflow:auto;">
                <h3 style="margin-top:0;">Webhook Payload</h3>
                <pre id="ak-payload-content" style="background:#f5f5f5;padding:15px;overflow-x:auto;font-size:12px;"></pre>
                <button type="button" class="button" onclick="document.getElementById('ak-payload-modal').style.display='none'">Close</button>
            </div>
        </div>
        
        <script>
        document.querySelectorAll('.ak-view-payload').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var payload = this.getAttribute('data-payload');
                try {
                    var formatted = JSON.stringify(JSON.parse(payload), null, 2);
                    document.getElementById('ak-payload-content').textContent = formatted;
                } catch(e) {
                    document.getElementById('ak-payload-content').textContent = payload;
                }
                document.getElementById('ak-payload-modal').style.display = 'block';
            });
        });
        
        document.getElementById('ak-payload-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
        </script>
        <?php
    }
    
    /**
     * AJAX clear logs
     */
    public function clear_logs() {
        check_ajax_referer('ak_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }
        
        global $wpdb;
        $wpdb->query("TRUNCATE TABLE " . self::$table_name);
        
        wp_send_json_success();
    }
}

// Initialize
new AK_Webhook_Logger();
