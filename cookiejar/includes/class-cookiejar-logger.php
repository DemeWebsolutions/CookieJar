<?php
namespace DWIC;
if (!defined('ABSPATH')) exit;

/**
 * Enhanced logging system for CookieJar plugin.
 * 
 * Provides structured logging with different levels, context,
 * and automatic log rotation for better error tracking.
 * 
 * @package CookieJar
 * @since 1.0.1
 */
class Logger {
    
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';
    
    const LOG_PREFIX = 'cookiejar_log_';
    const MAX_LOG_SIZE = 1048576; // 1MB
    const MAX_LOG_FILES = 5;
    
    /**
     * Log a message with context.
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Success status
     */
    public static function log($level, $message, $context = []) {
        if (!self::should_log($level)) {
            return false;
        }
        
        $log_entry = self::format_log_entry($level, $message, $context);
        
        // Write to WordPress debug log if enabled
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log($log_entry);
        }
        
        // Write to custom log file
        self::write_to_file($log_entry);
        
        // Store in database for admin viewing
        self::store_in_database($level, $message, $context);
        
        return true;
    }
    
    /**
     * Log debug message.
     * 
     * @param string $message Debug message
     * @param array $context Additional context
     * @return bool Success status
     */
    public static function debug($message, $context = []) {
        return self::log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Log info message.
     * 
     * @param string $message Info message
     * @param array $context Additional context
     * @return bool Success status
     */
    public static function info($message, $context = []) {
        return self::log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log warning message.
     * 
     * @param string $message Warning message
     * @param array $context Additional context
     * @return bool Success status
     */
    public static function warning($message, $context = []) {
        return self::log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Log error message.
     * 
     * @param string $message Error message
     * @param array $context Additional context
     * @return bool Success status
     */
    public static function error($message, $context = []) {
        return self::log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Log critical message.
     * 
     * @param string $message Critical message
     * @param array $context Additional context
     * @return bool Success status
     */
    public static function critical($message, $context = []) {
        return self::log(self::LEVEL_CRITICAL, $message, $context);
    }
    
    /**
     * Check if we should log at this level.
     * 
     * @param string $level Log level
     * @return bool Should log
     */
    private static function should_log($level) {
        $log_levels = [
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_ERROR => 3,
            self::LEVEL_CRITICAL => 4
        ];
        
        $current_level = get_option('cookiejar_log_level', self::LEVEL_INFO);
        $current_level_value = $log_levels[$current_level] ?? 1;
        $message_level_value = $log_levels[$level] ?? 1;
        
        return $message_level_value >= $current_level_value;
    }
    
    /**
     * Format log entry with timestamp and context.
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @return string Formatted log entry
     */
    private static function format_log_entry($level, $message, $context) {
        $timestamp = current_time('Y-m-d H:i:s');
        $user_id = get_current_user_id();
        $ip = self::get_client_ip();
        
        $log_entry = sprintf(
            '[%s] %s: %s | User: %d | IP: %s',
            $timestamp,
            strtoupper($level),
            $message,
            $user_id,
            $ip
        );
        
        if (!empty($context)) {
            $log_entry .= ' | Context: ' . json_encode($context);
        }
        
        return $log_entry;
    }
    
    /**
     * Write log entry to file.
     * 
     * @param string $log_entry Formatted log entry
     * @return bool Success status
     */
    private static function write_to_file($log_entry) {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/cookiejar-logs';
        
        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }
        
        $log_file = $log_dir . '/cookiejar.log';
        
        // Rotate log if it's too large
        if (file_exists($log_file) && filesize($log_file) > self::MAX_LOG_SIZE) {
            self::rotate_log($log_file);
        }
        
        // Prefer WP_Filesystem when available
        $fs = self::ensure_fs();
        global $wp_filesystem;
        if ($fs && $wp_filesystem) {
            $existing = '';
            if ($wp_filesystem->exists($log_file)) {
                $existing = (string) $wp_filesystem->get_contents($log_file);
            }
            return $wp_filesystem->put_contents($log_file, $existing . $log_entry . PHP_EOL, FS_CHMOD_FILE);
        }
        // Fallback to direct write
        return file_put_contents($log_file, $log_entry . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
    }
    
    /**
     * Rotate log files.
     * 
     * @param string $log_file Current log file path
     * @return bool Success status
     */
    private static function ensure_fs() {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
        global $wp_filesystem;
        return $wp_filesystem;
    }

    private static function rotate_log($log_file) {
        $log_dir = dirname($log_file);
        $fs = self::ensure_fs();
        global $wp_filesystem;

        // Remove oldest log file
        $oldest_file = $log_dir . '/cookiejar.log.' . self::MAX_LOG_FILES;
        if (file_exists($oldest_file)) {
            if ($fs && $wp_filesystem) {
                $wp_filesystem->delete($oldest_file);
            } else {
                wp_delete_file($oldest_file);
            }
        }
        
        // Shift existing log files
        for ($i = self::MAX_LOG_FILES - 1; $i >= 1; $i--) {
            $old_file = $log_dir . '/cookiejar.log.' . $i;
            $new_file = $log_dir . '/cookiejar.log.' . ($i + 1);
            
            if (file_exists($old_file)) {
                if ($fs && $wp_filesystem) {
                    // Suppress errors if file exists at destination
                    $wp_filesystem->delete($new_file);
                    $wp_filesystem->move($old_file, $new_file, true);
                } else {
                    // Fallback if FS API not available
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.rename_rename
                    @unlink($new_file);
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
                    @rename($old_file, $new_file);
                }
            }
        }
        
        // Move current log to .1
        $new_file = $log_dir . '/cookiejar.log.1';
        if ($fs && $wp_filesystem) {
            $wp_filesystem->delete($new_file);
            $wp_filesystem->move($log_file, $new_file, true);
            return true;
        }
        // Fallback
        // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
        return @rename($log_file, $new_file);
    }
    
    /**
     * Store log entry in database.
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Success status
     */
    private static function store_in_database($level, $message, $context) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'cookiejar_logs';
        
        // Check if logs table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) != $table) {
            return false;
        }
        
        $data = [
            'log_type' => 'system',
            'level' => $level,
            'message' => $message,
            'context' => json_encode($context),
            'user_id' => get_current_user_id(),
            'ip_address' => self::get_client_ip(),
            'created_at' => current_time('mysql')
        ];
        
        return $wpdb->insert($table, $data) !== false;
    }
    
    /**
     * Get client IP address.
     * 
     * @return string Client IP address
     */
    private static function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- IP validation via filter_var below
                $raw = wp_unslash($_SERVER[$key]);
                foreach (explode(',', $raw) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return sanitize_text_field($ip);
                    }
                }
            }
        }
        
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- IP validation via filter_var above
        return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field( wp_unslash($_SERVER['REMOTE_ADDR']) ) : 'unknown';
    }
    
    /**
     * Get recent log entries.
     * 
     * @param int $limit Number of entries to retrieve
     * @param string $level Filter by log level
     * @return array Log entries
     */
    public static function get_recent($limit = 50, $level = null) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'cookiejar_logs';
        
        // Check if logs table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) != $table) {
            return [];
        }
        
        $where = '';
        $params = [];
        
        if ($level) {
            $where = 'WHERE level = %s';
            $params[] = $level;
        }
        
        $query = "SELECT * FROM {$wpdb->prefix}cookiejar_logs $where ORDER BY created_at DESC LIMIT %d";
        $params[] = $limit;
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic WHERE clause built safely from validated parameters
        return $wpdb->get_results($wpdb->prepare($query, ...$params));
    }
    
    /**
     * Clear old log entries.
     * 
     * @param int $days Number of days to keep
     * @return int Number of entries deleted
     */
    public static function clear_old($days = 30) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'cookiejar_logs';
        
        // Check if logs table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) != $table) {
            return 0;
        }
        
        $cutoff_date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE created_at < %s",
                $cutoff_date
            )
        );
    }
}
