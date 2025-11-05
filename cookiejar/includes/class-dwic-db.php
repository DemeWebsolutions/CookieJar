<?php
namespace DWIC;
if (!defined('ABSPATH')) exit;

class DB {
    private static function table() {
        global $wpdb;
        return $wpdb->prefix.'dwic_logs';
    }

    public static function create_tables() {
        global $wpdb;
        require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ip VARCHAR(45) NOT NULL DEFAULT '',
            country CHAR(2) NOT NULL DEFAULT '',
            consent VARCHAR(12) NOT NULL DEFAULT '',
            categories TEXT NULL,
            config_version VARCHAR(64) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY consent (consent),
            KEY country (country),
            KEY created_at (created_at)
        ) {$charset};";
        dbDelta($sql);
    }

    public static function log_consent($post) {
        global $wpdb;
        $table = self::table();
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field( wp_unslash($_SERVER['REMOTE_ADDR']) ) : '';
        $consent = isset($post['consent']) ? sanitize_text_field($post['consent']) : '';
        $cats = isset($post['categories']) ? sanitize_text_field($post['categories']) : '';
        $ver = isset($post['config_version']) ? sanitize_text_field($post['config_version']) : '';
        $country = GeoTarget::get_country($ip);

        if (!$consent) return false;

        return (bool)$wpdb->insert($table, [
            'ip' => $ip,
            'country' => strtoupper(substr($country, 0, 2)),
            'consent' => $consent, // 'full' | 'partial' | 'none'
            'categories' => $cats,
            'config_version' => $ver,
            'created_at' => current_time('mysql', 1)
        ], ['%s','%s','%s','%s','%s','%s']);
    }

    public static function get_stats() {
        global $wpdb;
        $table = self::table();
        $stats = ['full'=>0,'partial'=>0,'none'=>0,'gdpr'=>0,'ccpa'=>0];

        // Consent breakdown
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query uses a known, validated table name without user input
        $rows = $wpdb->get_results("SELECT consent, COUNT(*) c FROM {$table} GROUP BY consent", ARRAY_A);
        if ($rows) {
            foreach ($rows as $r) {
                $type = $r['consent'];
                if (isset($stats[$type])) $stats[$type] = (int)$r['c'];
            }
        }

        // Jurisdiction (simple: US => CCPA, others => GDPR)
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query uses a known, validated table name without user input
        $j = $wpdb->get_row("SELECT 
            SUM(CASE WHEN country='US' THEN 1 ELSE 0 END) AS ccpa,
            SUM(CASE WHEN country<>'US' OR country='' THEN 1 ELSE 0 END) AS gdpr
        FROM {$table}", ARRAY_A);
        if ($j) {
            $stats['ccpa'] = (int)$j['ccpa'];
            $stats['gdpr'] = (int)$j['gdpr'];
        }

        return $stats;
    }

    public static function get_logs($limit = 1000) {
        global $wpdb;
        $limit = max(1, (int)$limit);
        $table = self::table();
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", $limit), ARRAY_A);
    }

    public static function prune_old_logs() {
        global $wpdb;
        $days = (int)get_option('dwic_log_retention_days', 365);
        if ($days <= 0) return;
        $table = self::table();
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created_at < (UTC_TIMESTAMP() - INTERVAL %d DAY)", $days));
    }
}