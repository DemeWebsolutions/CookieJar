<?php
namespace DWIC;
if (!defined('ABSPATH')) exit;

/**
 * CookieJar configuration manager.
 * 
 * Handles loading, saving and default configuration values for the CookieJar plugin.
 * Provides a centralized interface for managing plugin settings with proper sanitization.
 * 
 * @package CookieJar
 * @since 1.0.0
 */
class Config {
    /**
     * Get a configuration value from the database.
     * 
     * Retrieves a configuration value with the 'cookiejar_' prefix from WordPress options.
     * Returns the default value if the option doesn't exist.
     * 
     * @param string $key Configuration key (without 'cookiejar_' prefix)
     * @param mixed $default Default value if option doesn't exist
     * @return mixed The configuration value or default
     * @since 1.0.0
     */
    public static function get($key, $default = null) {
        $value = get_option('cookiejar_' . $key, $default);
        
        // Validate array-type values
        if ($key === 'banner_theme' && !is_array($value)) {
            $value = [];
        }
        if ($key === 'categories' && !is_array($value)) {
            $value = [];
        }
        if ($key === 'languages' && !is_array($value)) {
            $value = [];
        }
        
        return $value;
    }

    /**
     * Set a configuration value in the database.
     * 
     * Stores a configuration value with the 'cookiejar_' prefix in WordPress options.
     * 
     * @param string $key Configuration key (without 'cookiejar_' prefix)
     * @param mixed $value Value to store
     * @return bool True on success, false on failure
     * @since 1.0.0
     */
    public static function set($key, $value) {
        return update_option('cookiejar_' . $key, $value);
    }

    /**
     * Get all configuration options.
     * 
     * Retrieves all known configuration keys and their current values.
     * 
     * @return array Associative array of configuration key-value pairs
     * @since 1.0.0
     */
    public static function all() {
        $keys = [
            'banner_enabled','policy_url','ga_advanced','logging_mode','hash_enabled','gdpr_mode',
            'ccpa_mode','geo_auto','categories','languages','banner_theme','wizard_done','log_prune_days','license_key'
        ];
        $config = [];
        foreach ($keys as $key) {
            $config[$key] = self::get($key);
        }
        return $config;
    }

    /**
     * Get default configuration values.
     * 
     * Returns the default values for all configuration options.
     * These are used when the plugin is first installed or when resetting to defaults.
     * 
     * @return array Associative array of default configuration values
     * @since 1.0.0
     */
    public static function defaults() {
        return [
            'banner_enabled'=>'yes',
            'policy_url'=>'',
            'ga_advanced'=>'no',
            'logging_mode'=>'cached',
            'hash_enabled'=>'yes',
            'gdpr_mode'=>'yes',
            'ccpa_mode'=>'yes',
            'geo_auto'=>'yes',
            'categories'=>['necessary','functional','analytics','advertising'],
            'languages'=>['en'],
            'banner_theme'=>['color'=>'#008ed6','bg'=>'#ffffff','font'=>'inherit','prompt'=>''],
            'wizard_done'=>'no',
            'log_prune_days'=>365,
            'license_key'=>''
        ];
    }

    /**
     * Get the number of days to keep logs before pruning.
     * 
     * Returns the configured number of days to retain consent logs.
     * Default is 365 days if not configured.
     * 
     * @return int Number of days to keep logs
     * @since 1.0.0
     */
    public static function prune_days() {
        return (int) self::get('log_prune_days', 365);
    }

    /**
     * Sanitize all configuration values using Validator.
     * 
     * Processes an array of configuration values through the Validator class
     * to ensure all values are properly sanitized before storage.
     * 
     * @param array $settings Array of configuration key-value pairs
     * @return array Sanitized configuration array
     * @since 1.0.0
     */
    public static function sanitize_all($settings) {
        if (!is_array($settings)) return [];
        $sanitized = [];
        foreach ($settings as $k => $v) {
            $sanitized[$k] = \DWIC\Validator::sanitize($v);
        }
        return $sanitized;
    }

    /**
     * Check if the plugin is running in Pro mode.
     * 
     * Determines if the Pro version features are available.
     * This can be extended to check for license keys, premium features, etc.
     * 
     * @return bool True if Pro features are available
     * @since 1.0.0
     */
    public static function is_pro() {
        // Check for Pro constant or license key
        if (defined('COOKIEJAR_PRO') && COOKIEJAR_PRO) {
            return true;
        }
        
        // Check for license key in options
        $license_key = self::get('license_key', '');
        if (!empty($license_key)) {
            // Here you could validate the license key with your server
            return true;
        }
        
        return false;
    }
}
