<?php
// Analytics Dashboard Partial
if (!defined('ABSPATH')) exit;

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'cookiejar' ) );
}

use DWIC\Config;
use DWIC\DB;

$stats = class_exists('DWIC\DB') ? DB::get_stats() : [];
$total_consents = ($stats['full'] ?? 0) + ($stats['partial'] ?? 0) + ($stats['none'] ?? 0);
?>
<div class="cookiejar-analytics-dashboard">
    <h2><?php esc_html_e('Analytics Dashboard', 'cookiejar'); ?></h2>
    
    <?php if (!cookiejar_is_pro()): ?>
        <div class="cookiejar-pro-notice">
            <p><?php esc_html_e('Advanced analytics are not available in the free version.', 'cookiejar'); ?></p>
        </div>
    <?php endif; ?>
    
    <div class="cookiejar-stats-grid">
        <div class="cookiejar-stat-card">
            <h3><?php esc_html_e('Total Consents', 'cookiejar'); ?></h3>
            <div class="cookiejar-stat-number"><?php echo esc_html($total_consents); ?></div>
        </div>
        
        <div class="cookiejar-stat-card">
            <h3><?php esc_html_e('Full Consent', 'cookiejar'); ?></h3>
            <div class="cookiejar-stat-number"><?php echo esc_html($stats['full'] ?? 0); ?></div>
            <div class="cookiejar-stat-percentage">
                <?php echo esc_html( $total_consents > 0 ? round(($stats['full'] ?? 0) / $total_consents * 100, 1) : 0 ); ?>%
            </div>
        </div>
        
        <div class="cookiejar-stat-card">
            <h3><?php esc_html_e('Partial Consent', 'cookiejar'); ?></h3>
            <div class="cookiejar-stat-number"><?php echo esc_html($stats['partial'] ?? 0); ?></div>
            <div class="cookiejar-stat-percentage">
                <?php echo esc_html( $total_consents > 0 ? round(($stats['partial'] ?? 0) / $total_consents * 100, 1) : 0 ); ?>%
            </div>
        </div>
        
        <div class="cookiejar-stat-card">
            <h3><?php esc_html_e('No Consent', 'cookiejar'); ?></h3>
            <div class="cookiejar-stat-number"><?php echo esc_html($stats['none'] ?? 0); ?></div>
            <div class="cookiejar-stat-percentage">
                <?php echo esc_html( $total_consents > 0 ? round(($stats['none'] ?? 0) / $total_consents * 100, 1) : 0 ); ?>%
            </div>
        </div>
    </div>
    
    <?php if (cookiejar_is_pro()): ?>
        <div class="cookiejar-region-stats">
            <h3><?php esc_html_e('Regional Compliance', 'cookiejar'); ?></h3>
            <div class="cookiejar-region-grid">
                <div class="cookiejar-region-card">
                    <h4><?php esc_html_e('GDPR Region', 'cookiejar'); ?></h4>
                    <div class="cookiejar-region-number"><?php echo esc_html($stats['gdpr'] ?? 0); ?></div>
                </div>
                <div class="cookiejar-region-card">
                    <h4><?php esc_html_e('CCPA Region', 'cookiejar'); ?></h4>
                    <div class="cookiejar-region-number"><?php echo esc_html($stats['ccpa'] ?? 0); ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.cookiejar-analytics-dashboard {
    margin-top: 20px;
}

.cookiejar-pro-notice {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 20px;
}

.cookiejar-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.cookiejar-stat-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    text-align: center;
}

.cookiejar-stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.cookiejar-stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #008ed6;
    margin-bottom: 5px;
}

.cookiejar-stat-percentage {
    font-size: 14px;
    color: #6c757d;
}

.cookiejar-region-stats {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
}

.cookiejar-region-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.cookiejar-region-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 15px;
    text-align: center;
}

.cookiejar-region-card h4 {
    margin: 0 0 10px 0;
    font-size: 12px;
    color: #6c757d;
    text-transform: uppercase;
}

.cookiejar-region-number {
    font-size: 24px;
    font-weight: bold;
    color: #008ed6;
}
</style>
