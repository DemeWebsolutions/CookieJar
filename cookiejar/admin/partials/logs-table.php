<?php
// Logs Table Partial
if (!defined('ABSPATH')) exit;

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'cookiejar' ) );
}

use DWIC\DB;

$logs = class_exists('DWIC\DB') ? DB::get_logs(50) : [];
?>
<div class="cookiejar-logs-table">
    <h2><?php esc_html_e('Recent Consent Logs', 'cookiejar'); ?></h2>
    
    <?php if (empty($logs)): ?>
        <p><?php esc_html_e('No consent logs found.', 'cookiejar'); ?></p>
    <?php else: ?>
        <div class="cookiejar-logs-controls">
            <button type="button" class="button" id="refresh-logs"><?php esc_html_e('Refresh', 'cookiejar'); ?></button>
            <button type="button" class="button" id="export-logs"><?php esc_html_e('Export CSV', 'cookiejar'); ?></button>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('IP Address', 'cookiejar'); ?></th>
                    <th><?php esc_html_e('Country', 'cookiejar'); ?></th>
                    <th><?php esc_html_e('Consent', 'cookiejar'); ?></th>
                    <th><?php esc_html_e('Date', 'cookiejar'); ?></th>
                    <th><?php esc_html_e('Time', 'cookiejar'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log['ip'] ?? ''); ?></td>
                        <td><?php echo esc_html($log['country'] ?? ''); ?></td>
                        <td>
                            <span class="cookiejar-consent-badge cookiejar-consent-<?php echo esc_attr($log['consent'] ?? 'none'); ?>">
                                <?php echo esc_html(ucfirst($log['consent'] ?? 'none')); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(gmdate('Y-m-d', strtotime($log['created_at'] ?? ''))); ?></td>
                        <td><?php echo esc_html(gmdate('H:i:s', strtotime($log['created_at'] ?? ''))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<style>
.cookiejar-logs-table {
    margin-top: 20px;
}

.cookiejar-logs-controls {
    margin-bottom: 15px;
}

.cookiejar-logs-controls .button {
    margin-right: 10px;
}

.cookiejar-consent-badge {
    padding: 4px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.cookiejar-consent-full {
    background: #d4edda;
    color: #155724;
}

.cookiejar-consent-partial {
    background: #fff3cd;
    color: #856404;
}

.cookiejar-consent-none {
    background: #f8d7da;
    color: #721c24;
}

.cookiejar-consent-accept {
    background: #d4edda;
    color: #155724;
}

.cookiejar-consent-reject {
    background: #f8d7da;
    color: #721c24;
}
</style>
