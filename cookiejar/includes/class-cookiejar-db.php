<?php
namespace DWIC;
if (!defined('ABSPATH')) exit;

/**
 * Enhanced data layer with tier-aware masking and custom table support.
 * Falls back to options storage for demo/dev.
 */
class DB {

    protected static function ensure_arrays(){
        $logs = get_option('dwic_logs');
        if (!is_array($logs)) {
            $logs = [];
            update_option('dwic_logs', $logs, false);
        }
        $meta = get_option('dwic_stats_meta');
        if (!is_array($meta)) {
            $meta = [
                'pageviews' => 0,
                'cookies_total' => 0,
                'avg_decision_ms' => 0
            ];
            update_option('dwic_stats_meta', $meta, false);
        }
    }

    /**
     * Create database tables, add indexes for performance.
     * Unified table supporting both consent logs and system logs.
     */
    public static function create_tables() {
        global $wpdb;
        $table = $wpdb->prefix . 'cookiejar_logs';
        $charset_collate = $wpdb->get_charset_collate();

        // Check if table exists and needs migration
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) == $table;
        
        if ($table_exists) {
            // Check if migration is needed
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL introspection on known-safe table name
            $columns = $wpdb->get_results("SHOW COLUMNS FROM $table", ARRAY_A);
            $column_names = array_column($columns, 'Field');
            
            if (!in_array('log_type', $column_names)) {
                // Migrate existing table
                self::migrate_table_schema($table);
            }
        } else {
            // Create new table
            $sql = "CREATE TABLE $table (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                log_type varchar(20) NOT NULL DEFAULT 'consent',
                level varchar(10) DEFAULT NULL,
                message text DEFAULT NULL,
                context text DEFAULT NULL,
                user_id bigint(20) unsigned DEFAULT NULL,
                ip_address varchar(45) DEFAULT NULL,
                ip varchar(45) NOT NULL DEFAULT '',
                country varchar(4) NOT NULL DEFAULT '',
                consent varchar(16) NOT NULL DEFAULT '',
                categories text NOT NULL,
                config_version varchar(12) NOT NULL DEFAULT '',
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                INDEX log_type_idx (log_type),
                INDEX level_idx (level),
                INDEX consent_idx (consent),
                INDEX country_idx (country),
                INDEX created_at_idx (created_at),
                INDEX consent_country_idx (consent, country),
                INDEX created_at_consent_idx (created_at, consent),
                INDEX log_type_created_idx (log_type, created_at)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Migrate existing table schema to support both consent and system logs.
     */
    private static function migrate_table_schema($table) {
        global $wpdb;
        
        // Add new columns for system logging
        $alter_queries = [
            "ALTER TABLE $table ADD COLUMN log_type varchar(20) NOT NULL DEFAULT 'consent' AFTER id",
            "ALTER TABLE $table ADD COLUMN level varchar(10) DEFAULT NULL AFTER log_type",
            "ALTER TABLE $table ADD COLUMN message text DEFAULT NULL AFTER level",
            "ALTER TABLE $table ADD COLUMN context text DEFAULT NULL AFTER message",
            "ALTER TABLE $table ADD COLUMN user_id bigint(20) unsigned DEFAULT NULL AFTER context",
            "ALTER TABLE $table ADD COLUMN ip_address varchar(45) DEFAULT NULL AFTER user_id"
        ];
        
        // Add indexes
        $index_queries = [
            "ALTER TABLE $table ADD INDEX log_type_idx (log_type)",
            "ALTER TABLE $table ADD INDEX level_idx (level)",
            "ALTER TABLE $table ADD INDEX log_type_created_idx (log_type, created_at)"
        ];
        
        // Execute migration
        foreach ($alter_queries as $query) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL statements with safe table name
            $wpdb->query($query);
        }
        
        foreach ($index_queries as $query) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- DDL statements with safe table name
            $wpdb->query($query);
        }
        
        // Log migration
        if (class_exists('\\DWIC\\Logger')) {
            \DWIC\Logger::info('Database table migrated to support unified logging');
        }
    }

    /**
     * Log consent in the database with caching.
     * @param array $data Array with keys: consent, categories, config_version, ip, country
     * @return bool True on success, false on failure
     */
    public static function log_consent($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'cookiejar_logs';

        // Validate database connection
        if (!$wpdb->db_connect()) {
            if (class_exists('\\DWIC\\Logger')) {
                \DWIC\Logger::error('Database connection failed');
            }
            return false;
        }

        // Validate required fields
        $required_fields = ['consent', 'ip', 'country'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                if (class_exists('\\DWIC\\Logger')) {
                    \DWIC\Logger::error("Missing required field: $field", $data);
                }
                return false;
            }
        }

        // Prepare data for insertion
        $insert_data = [
            'log_type' => 'consent',
            'ip' => sanitize_text_field($data['ip']),
            'country' => sanitize_text_field($data['country']),
            'consent' => sanitize_text_field($data['consent']),
            'categories' => is_array($data['categories'] ?? []) ? json_encode($data['categories']) : '',
            'config_version' => sanitize_text_field($data['config_version'] ?? ''),
            'created_at' => current_time('mysql')
        ];

        // Insert into database
        $result = $wpdb->insert($table, $insert_data);
        
        if ($result === false) {
            if (class_exists('\\DWIC\\Logger')) {
                \DWIC\Logger::error('Failed to insert consent log: ' . $wpdb->last_error, $data);
            }
            return false;
        }

        // Invalidate related caches
        if (class_exists('\\DWIC\\Cache')) {
            \DWIC\Cache::invalidate('analytics_*');
            \DWIC\Cache::invalidate('query_*');
        }

        // Prune old logs if configured
        self::prune_old_logs();
        
        // Log successful consent
        if (class_exists('\\DWIC\\Logger')) {
            \DWIC\Logger::info('Consent logged successfully', [
                'consent' => $data['consent'],
                'country' => $data['country'],
                'categories_count' => count($data['categories'] ?? [])
            ]);
        }
        
        return true;
    }

    /**
     * Prune old consent logs based on configured retention period.
     */
    public static function prune_old_logs() {
        global $wpdb;
        $table = $wpdb->prefix . 'cookiejar_logs';
        
        // Get retention period from config
        $retention_days = \DWIC\Config::prune_days();
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
        
        // Delete old records
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < %s",
            $cutoff_date
        ));
        
        if ($deleted > 0) {
            self::log_info("Pruned $deleted old consent logs");
        }
    }

    public static function get_logs($count = 12){
        // Check for custom logs table first
        global $wpdb;
        $table_name = $wpdb->prefix . 'cookiejar_logs';
        
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name) {
            return self::get_logs_from_table($count);
        }
        
        // Fall back to options storage
        self::ensure_arrays();
        $logs = get_option('dwic_logs');
        if (!is_array($logs)) return [];
        
        // Sort by created_at desc
        usort($logs, function($a,$b){
            $ta = isset($a['created_at']) ? strtotime($a['created_at']) : 0;
            $tb = isset($b['created_at']) ? strtotime($b['created_at']) : 0;
            return $tb <=> $ta;
        });
        
        $logs = array_slice($logs, 0, max(1,(int)$count));
        return self::apply_tier_masking($logs);
    }

    protected static function get_logs_from_table($count) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cookiejar_logs';
        
        // Use cache if available
        if (class_exists('\\DWIC\\Cache')) {
            $cache_key = 'logs_table_' . $count;
            return \DWIC\Cache::query($cache_key, function() use ($wpdb, $table_name, $count) {
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT * FROM $table_name WHERE log_type = 'consent' ORDER BY created_at DESC LIMIT %d",
                    max(1, (int)$count)
                ), ARRAY_A);
                
                return self::apply_tier_masking($results ?: []);
            }, 60); // Cache for 1 minute
        }
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE log_type = 'consent' ORDER BY created_at DESC LIMIT %d",
            max(1, (int)$count)
        ), ARRAY_A);
        
        return self::apply_tier_masking($results ?: []);
    }

    protected static function apply_tier_masking($logs) {
        $tier = cookiejar_get_tier();
        
        if ($tier === COOKIEJAR_TIER_BASIC) {
            // Basic tier: mask IPs, reduce timestamps to date-only
            foreach ($logs as &$log) {
                if (isset($log['ip'])) {
                    $log['ip'] = substr($log['ip'], 0, 3) . '.***.***.' . substr($log['ip'], -1);
                }
                if (isset($log['created_at'])) {
                    $log['created_at'] = gmdate('Y-m-d', strtotime($log['created_at']));
                }
            }
        }
        
        return $logs;
    }

    /**
     * Log error message.
     * @param string $message
     */
    protected static function log_error($message) {
        error_log("[CookieJar DB] ERROR: $message");
    }

    /**
     * Log info message.
     * @param string $message
     */
    protected static function log_info($message) {
        error_log("[CookieJar DB] INFO: $message");
    }

    public static function get_trend_summary($days = 7) {
        // Generate mock trend data for demo purposes
        // In production, this would query actual consent data
        $trend = [];
        $now = current_time('timestamp');
        
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = gmdate('Y-m-d', $now - ($i * DAY_IN_SECONDS));
            $trend[] = [
                'date' => $date,
                'full' => wp_rand(10, 50),
                'partial' => wp_rand(5, 25),
                'none' => wp_rand(2, 15)
            ];
        }
        
        return $trend;
    }

    public static function add_log($row){
        self::ensure_arrays();
        $logs = get_option('dwic_logs', []);
        $row['created_at'] = isset($row['created_at']) ? $row['created_at'] : gmdate('c');
        $logs[] = $row;
        update_option('dwic_logs', $logs, false);
        return true;
    }

    /**
     * Compute stats, optionally filtered by start/end ISO date strings.
     * Returns: full, partial, none, gdpr, ccpa, cookies_total, pageviews, avg_decision_ms
     */
    public static function get_stats($args = []){
        self::ensure_arrays();

        $start = isset($args['start']) ? strtotime($args['start']) : null;
        $end   = isset($args['end'])   ? strtotime($args['end'])   : null;

        $logs = get_option('dwic_logs', []);
        $full = $partial = $none = 0;
        $eu_hit = $us_hit = false;

        foreach ($logs as $row){
            $ts = isset($row['created_at']) ? strtotime($row['created_at']) : 0;
            if ($start && $ts < $start) continue;
            if ($end && $ts > $end) continue;

            $c = isset($row['consent']) ? strtolower($row['consent']) : '';
            if ($c === 'full' || $c === 'accept') $full++;
            elseif ($c === 'partial') $partial++;
            elseif ($c === 'none' || $c === 'reject') $none++;

            $cc = strtoupper($row['country'] ?? '');
            if (in_array($cc, ['DE','FR','ES','IT','NL','BE','PL','SE','FI','DK','IE','AT','PT','RO','HU','CZ','GR'])) $eu_hit = true;
            if ($cc === 'US') $us_hit = true;
        }

        $meta = get_option('dwic_stats_meta', [
            'pageviews' => 0,
            'cookies_total' => 0,
            'avg_decision_ms' => 0
        ]);

        return [
            'full' => $full,
            'partial' => $partial,
            'none' => $none,
            'gdpr' => $eu_hit ? 1 : 0,
            'ccpa' => $us_hit ? 1 : 0,
            'pageviews' => (int)($meta['pageviews'] ?? 0),
            'cookies_total' => (int)($meta['cookies_total'] ?? 0),
            'avg_decision_ms' => (int)($meta['avg_decision_ms'] ?? 0),
        ];
    }
}