<?php
namespace DWIC;
if (!defined('ABSPATH')) exit;

class Ajax {

    public static function init(){
        add_action('wp_ajax_dwic_stats', [__CLASS__, 'dwic_stats']);
        add_action('wp_ajax_dwic_log', [__CLASS__, 'dwic_log']);
        add_action('wp_ajax_nopriv_dwic_log', [__CLASS__, 'dwic_log']);
        add_action('wp_ajax_cookiejar_recent_logs', [__CLASS__, 'recent_logs']);
        add_action('wp_ajax_cookiejar_trend_summary', [__CLASS__, 'trend_summary']);
        add_action('wp_ajax_cookiejar_status_hud', [__CLASS__, 'status_hud']);
        add_action('wp_ajax_cookiejar_save_wizard', [__CLASS__, 'save_wizard']);
        add_action('wp_ajax_cookiejar_export_settings', [__CLASS__, 'export_settings']);
        add_action('wp_ajax_cookiejar_import_settings', [__CLASS__, 'import_settings']);
        add_action('wp_ajax_cookiejar_reset_defaults', [__CLASS__, 'reset_defaults']);
        // If ever needed frontside: add_action('wp_ajax_nopriv_dwic_stats', [__CLASS__, 'dwic_stats']);
    }

    /**
     * Public consent logging endpoint (rate-limited, sanitized)
     * Note: Public endpoint - nonce verification not required per WordPress.org guidelines
     */
    public static function dwic_log(){
        // Enforce POST method for public logging endpoint
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_send_json_error(['error' => 'method_not_allowed'], 405);
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public endpoint, rate-limited
        // Simple IP-based rate limit: 1 request/sec, burst 5
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field( wp_unslash($_SERVER['REMOTE_ADDR']) ) : 'unknown';
        $key = 'dwic_log_' . md5($ip);
        $count = (int) get_transient($key);
        if ($count >= 5) {
            wp_send_json_error(['error' => 'rate_limited'], 429);
        }
        set_transient($key, $count + 1, 1);

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public endpoint, rate-limited
        $consent = isset($_POST['consent']) ? sanitize_text_field( wp_unslash($_POST['consent']) ) : '';
        $catsRaw = isset($_POST['categories']) ? sanitize_text_field( wp_unslash($_POST['categories']) ) : '';
        $config_version = isset($_POST['config_version']) ? sanitize_text_field( wp_unslash($_POST['config_version']) ) : '';

        if ($consent === '') {
            wp_send_json_error(['error' => 'invalid_consent'], 400);
        }

        $categories = [];
        if ($catsRaw !== '') {
            $parts = array_filter(array_map('sanitize_key', explode(',', $catsRaw)));
            $allowed = ['necessary','functional','analytics','ads','advertising','chatbot','donotsell'];
            foreach ($parts as $p) {
                if (in_array($p, $allowed, true)) $categories[$p] = true;
            }
        }

        // Resolve country via GeoTarget if available
        $country = '';
        if (class_exists('\\DWIC\\GeoTarget')) {
            try {
                $geo = new \DWIC\GeoTarget();
                $country = $geo->get_country($ip) ?: '';
            } catch (\Throwable $e) { $country = ''; }
        }

        // Persist
        if (class_exists('\\DWIC\\DB')) {
            \DWIC\DB::log_consent([
                'consent' => $consent,
                'categories' => array_keys(array_filter($categories)),
                'config_version' => $config_version,
                'ip' => $ip,
                'country' => $country,
            ]);
        }

        wp_send_json_success(['ok' => true]);
    }

    public static function dwic_stats(){
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['error'=>'no_perms'], 403);
        }
        $nonce = isset($_GET['_ajax_nonce']) ? sanitize_text_field( wp_unslash($_GET['_ajax_nonce']) ) : '';
        if (!wp_verify_nonce($nonce, 'cookiejar_admin')) {
            wp_send_json_error(['error'=>'bad_nonce'], 403);
        }
        $args = [];
        if (isset($_GET['start'])) $args['start'] = sanitize_text_field( wp_unslash($_GET['start']) );
        if (isset($_GET['end']))   $args['end']   = sanitize_text_field( wp_unslash($_GET['end']) );

        $stats = \DWIC\DB::get_stats($args);
        wp_send_json($stats);
    }

    public static function recent_logs(){
        $nonce = isset($_GET['_ajax_nonce']) ? sanitize_text_field( wp_unslash($_GET['_ajax_nonce']) ) : '';
        if (!current_user_can('manage_options') || !wp_verify_nonce($nonce, 'cookiejar_admin')) {
            wp_send_json_error(['error'=>'no_perms'], 403);
        }
        
        // Rate limiting check
        $user_id = get_current_user_id();
        $rate_limit_key = 'cookiejar_rate_limit_' . $user_id;
        $rate_limit_count = get_transient($rate_limit_key) ?: 0;
        
        if ($rate_limit_count > 10) { // Max 10 requests per minute
            wp_send_json_error(['error'=>'rate_limit_exceeded'], 429);
        }
        
        set_transient($rate_limit_key, $rate_limit_count + 1, 60);
        
        $count = isset($_GET['count']) ? max(1, min(50, intval($_GET['count']))) : 12;
        $logs = \DWIC\DB::get_logs($count);
        wp_send_json_success($logs);
    }

    public static function trend_summary(){
        $nonce = isset($_GET['_ajax_nonce']) ? sanitize_text_field( wp_unslash($_GET['_ajax_nonce']) ) : '';
        if (!current_user_can('manage_options') || !wp_verify_nonce($nonce, 'cookiejar_admin')) {
            wp_send_json_error(['error'=>'no_perms'], 403);
        }

        $tier = cookiejar_get_tier();
        $requested_days = isset($_GET['days']) ? intval($_GET['days']) : 0;
        $allowed_days = [7,30,90];
        if (!in_array($requested_days, $allowed_days, true)) {
            $requested_days = 0;
        }
        // Enforce tier limits: Basic fixed at 7; Pro allows 7/30/90 with default 30
        if ($tier === COOKIEJAR_TIER_BASIC) {
            $days = 7;
        } else {
            $days = $requested_days ?: 30;
        }

        $cache_key = 'cookiejar_trend_summary_' . $tier . '_' . $days;
        $cached = get_transient($cache_key);
        
        if ($cached !== false && $tier === COOKIEJAR_TIER_BASIC) {
            wp_send_json_success($cached);
        }

        $trend = \DWIC\DB::get_trend_summary($days);
        
        if ($tier === COOKIEJAR_TIER_BASIC) {
            set_transient($cache_key, $trend, DAY_IN_SECONDS);
        }
        
        wp_send_json_success($trend);
    }

    public static function status_hud(){
        $nonce = isset($_GET['_ajax_nonce']) ? sanitize_text_field( wp_unslash($_GET['_ajax_nonce']) ) : '';
        if (!current_user_can('manage_options') || !wp_verify_nonce($nonce, 'cookiejar_admin')) {
            wp_send_json_error(['error'=>'no_perms'], 403);
        }

        $tier = cookiejar_get_tier();
        $cache_key = 'cookiejar_status_hud_' . $tier;
        $cached = get_transient($cache_key);
        
        if ($cached !== false && $tier === COOKIEJAR_TIER_BASIC) {
            wp_send_json_success($cached);
        }

        $status = [
            'banner' => get_option('cookiejar_banner_enabled', 'yes') === 'yes',
            'geo' => get_option('cookiejar_geo_auto', 'yes') === 'yes',
            'gdpr' => get_option('cookiejar_gdpr_mode', 'yes') === 'yes',
            'ccpa' => get_option('cookiejar_ccpa_mode', 'yes') === 'yes',
        ];
        
        if ($tier === COOKIEJAR_TIER_BASIC) {
            set_transient($cache_key, $status, DAY_IN_SECONDS);
        }
        
        wp_send_json_success($status);
    }

    public static function save_wizard(){
        $nonce = isset($_POST['_ajax_nonce']) ? sanitize_text_field( wp_unslash($_POST['_ajax_nonce']) ) : '';
        if (!current_user_can('manage_options') || !wp_verify_nonce($nonce, 'cookiejar_admin')) {
            wp_send_json_error(['error'=>'no_perms'], 403);
        }

        // Enhanced input validation
        $step = intval(isset($_POST['step']) ? wp_unslash($_POST['step']) : 0);
        if ($step < 1 || $step > 4) {
            wp_send_json_error(['error'=>'invalid_step'], 400);
        }
        
        $data_raw = isset($_POST['data']) ? wp_unslash($_POST['data']) : [];
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Will be sanitized by Validator below
        $data = is_array($data_raw) ? $data_raw : json_decode((string) $data_raw, true);
        if (!is_array($data)) {
            wp_send_json_error(['error'=>'invalid_data'], 400);
        }
        
        // Sanitize all input data
        $data = \DWIC\Validator::sanitize($data);

        switch($step) {
            case 1: // Languages
                $languages = \DWIC\Validator::languages($data['languages'] ?? []);
                $tier = cookiejar_get_tier();
                if ($tier === COOKIEJAR_TIER_BASIC && count($languages) > 2) {
                    $languages = array_slice($languages, 0, 2);
                }
                \DWIC\Config::set('languages', $languages);
                break;
                
            case 2: // Categories
                $categories = \DWIC\Validator::categories($data['categories'] ?? []);
                $tier = cookiejar_get_tier();
                $allowed_cats = $tier === COOKIEJAR_TIER_BASIC ? 
                    ['necessary', 'functional', 'analytics', 'advertising'] : 
                    ['necessary', 'functional', 'analytics', 'advertising', 'chatbot', 'donotsell'];
                
                $categories = array_intersect($categories, $allowed_cats);
                \DWIC\Config::set('categories', $categories);
                break;
                
            case 3: // Appearance/Policy
                \DWIC\Config::set('banner_theme', [
                    'color' => \DWIC\Validator::color($data['color'] ?? '#008ed6'),
                    'bg' => \DWIC\Validator::color($data['bg'] ?? '#ffffff'),
                    'font' => \DWIC\Validator::sanitize($data['font'] ?? 'inherit'),
                    'prompt' => \DWIC\Validator::sanitize($data['prompt'] ?? '')
                ]);
                \DWIC\Config::set('policy_url', \DWIC\Validator::url($data['policy_url'] ?? ''));
                break;
                
            case 4: // Geo & Logging
                \DWIC\Config::set('geo_auto', isset($data['geo_auto']) ? 'yes' : 'no');
                $logging_mode = \DWIC\Validator::sanitize($data['logging_mode'] ?? 'cached');
                if (cookiejar_get_tier() === COOKIEJAR_TIER_BASIC) {
                    $logging_mode = 'cached'; // Force cached for Basic
                }
                \DWIC\Config::set('logging_mode', $logging_mode);
                \DWIC\Config::set('wizard_done', 'yes');
                \DWIC\Config::set('banner_enabled', 'yes');
                break;
        }

        wp_send_json_success(['message' => 'Wizard step saved']);
    }

    public static function export_settings(){
        $nonce = isset($_GET['_ajax_nonce']) ? sanitize_text_field( wp_unslash($_GET['_ajax_nonce']) ) : '';
        if (!current_user_can('manage_options') || !wp_verify_nonce($nonce, 'cookiejar_admin')) {
            wp_send_json_error(['error'=>'no_perms'], 403);
        }

        $settings = [];
        $whitelist = [
            'cookiejar_banner_enabled', 'cookiejar_policy_url', 'cookiejar_ga_advanced',
            'cookiejar_logging_mode', 'cookiejar_hash_enabled', 'cookiejar_gdpr_mode',
            'cookiejar_ccpa_mode', 'cookiejar_geo_auto', 'cookiejar_categories',
            'cookiejar_languages', 'cookiejar_banner_theme'
        ];

        foreach($whitelist as $option) {
            $value = get_option($option);
            if ($value !== false) {
                $settings[$option] = $value;
            }
        }

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="cookiejar-settings.json"');
        echo json_encode($settings, JSON_PRETTY_PRINT);
        wp_die();
    }

    public static function import_settings(){
        $nonce = isset($_POST['_ajax_nonce']) ? sanitize_text_field( wp_unslash($_POST['_ajax_nonce']) ) : '';
        if (!current_user_can('manage_options') || !wp_verify_nonce($nonce, 'cookiejar_admin')) {
            wp_send_json_error(['error'=>'no_perms'], 403);
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Will be sanitized by whitelist and Validator below
        $settings = json_decode(isset($_POST['settings']) ? wp_unslash($_POST['settings']) : '{}', true);
        if (!is_array($settings)) {
            wp_send_json_error(['error'=>'invalid_json']);
        }

        $whitelist = [
            'cookiejar_banner_enabled', 'cookiejar_policy_url', 'cookiejar_ga_advanced',
            'cookiejar_logging_mode', 'cookiejar_hash_enabled', 'cookiejar_gdpr_mode',
            'cookiejar_ccpa_mode', 'cookiejar_geo_auto', 'cookiejar_categories',
            'cookiejar_languages', 'cookiejar_banner_theme'
        ];

        foreach($settings as $key => $value) {
            if (in_array($key, $whitelist)) {
                // Remove 'cookiejar_' prefix for Config class
                $config_key = str_replace('cookiejar_', '', $key);
                
                // Apply tier restrictions
                if ($config_key === 'ga_advanced' && !cookiejar_is_pro()) {
                    $value = 'no';
                }
                if ($config_key === 'logging_mode' && !cookiejar_is_pro()) {
                    $value = 'cached';
                }
                if ($config_key === 'languages' && is_array($value) && !cookiejar_is_pro()) {
                    $value = array_slice($value, 0, 2);
                }
                
                // Sanitize value before saving
                $value = \DWIC\Validator::sanitize($value);
                \DWIC\Config::set($config_key, $value);
            }
        }

        wp_send_json_success(['message' => 'Settings imported successfully']);
    }

    public static function reset_defaults(){
        $nonce = isset($_POST['_ajax_nonce']) ? sanitize_text_field( wp_unslash($_POST['_ajax_nonce']) ) : '';
        if (!current_user_can('manage_options') || !wp_verify_nonce($nonce, 'cookiejar_admin')) {
            wp_send_json_error(['error'=>'no_perms'], 403);
        }

        // Reset to sane defaults using Config class
        $defaults = \DWIC\Config::defaults();

        foreach($defaults as $key => $value) {
            \DWIC\Config::set($key, $value);
        }

        // Clear caches
        delete_transient('cookiejar_trend_summary');
        delete_transient('cookiejar_status_hud');
        delete_transient('cookiejar_trend_summary_basic');
        delete_transient('cookiejar_status_hud_basic');

        wp_send_json_success(['message' => 'Settings reset to defaults']);
    }
}