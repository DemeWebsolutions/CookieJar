<?php
/**
 * Plugin Name: CookieJar
 * Description: Cookie consent banner and basic compliance tools (GDPR/CCPA) — free version.
 * Version: 1.0.0
 * Author: DemeWebSolutions.com
 * Text Domain: cookiejar
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) exit;

// Minimum requirements
define('DWIC_MIN_PHP', '7.2');

// Core constants
if (!defined('DWIC_VERSION')) define('DWIC_VERSION', '1.0.0');
if (!defined('DWIC_PATH'))    define('DWIC_PATH', plugin_dir_path(__FILE__));
if (!defined('DWIC_URL'))     define('DWIC_URL', plugin_dir_url(__FILE__));
if (!defined('DWIC_ICON_URL')) define('DWIC_ICON_URL', plugins_url('assets/img/cookie.svg', __FILE__));

// Tier system
define('COOKIEJAR_TIER_BASIC', 'basic');
define('COOKIEJAR_TIER_PRO', 'pro');
// Pro build flag (free build disables Pro features)
if (!defined('COOKIEJAR_PRO')) define('COOKIEJAR_PRO', false);

/**
 * Collect bootstrap errors to display as admin notices and avoid hard fatals.
 */
$GLOBALS['dwic_bootstrap_errors'] = [];

/**
 * Show admin notices for any bootstrap errors.
 */
function dwic_admin_bootstrap_notices() {
    if (!current_user_can('activate_plugins')) return;
    $errs = isset($GLOBALS['dwic_bootstrap_errors']) && is_array($GLOBALS['dwic_bootstrap_errors'])
        ? $GLOBALS['dwic_bootstrap_errors'] : [];
    foreach ($errs as $msg) {
        echo '<div class="notice notice-error"><p><strong>CookieJar:</strong> ' . esc_html($msg) . '</p></div>';
    }
}
add_action('admin_notices', 'dwic_admin_bootstrap_notices');

/**
 * Verify requirements early.
 */
function dwic_requirements_ok(): bool {
    // PHP version
    if (version_compare(PHP_VERSION, DWIC_MIN_PHP, '<')) {
        $GLOBALS['dwic_bootstrap_errors'][] = sprintf(
            'Requires PHP %s or higher. Current: %s.',
            DWIC_MIN_PHP,
            PHP_VERSION
        );
        return false;
    }

    // Files exist check (do not fatal if missing)
    $files = [
        DWIC_PATH . 'includes/class-cookiejar-db.php',
        DWIC_PATH . 'includes/class-cookiejar-ajax.php',
        DWIC_PATH . 'includes/class-cookiejar-wizard.php',
        DWIC_PATH . 'includes/class-cookiejar-config.php',
        DWIC_PATH . 'includes/class-cookiejar-validator.php',
        DWIC_PATH . 'includes/class-cookiejar-cache.php',
        DWIC_PATH . 'includes/class-cookiejar-logger.php',
        DWIC_PATH . 'includes/class-dwic-geotarget.php',
        DWIC_PATH . 'includes/class-dwic-localization.php',
        DWIC_PATH . 'admin/class-cookiejar-admin.php',
        DWIC_PATH . 'frontend/class-dwic-frontend.php',
    ];
    foreach ($files as $f) {
        if (!file_exists($f)) {
            $GLOBALS['dwic_bootstrap_errors'][] = sprintf(
                'Missing required file: %s',
                str_replace(WP_PLUGIN_DIR . '/', '', $f)
            );
            return false;
        }
    }
    return true;
}

/**
 * Safe loader with guards.
 */
function dwic_safe_load_files() {
    // Use require_once inside try/catch to guard unexpected parse errors
    try {
        require_once DWIC_PATH . 'includes/class-cookiejar-db.php';
        require_once DWIC_PATH . 'includes/class-cookiejar-ajax.php';
        require_once DWIC_PATH . 'includes/class-cookiejar-wizard.php';
        require_once DWIC_PATH . 'includes/class-cookiejar-config.php';
        require_once DWIC_PATH . 'includes/class-cookiejar-validator.php';
        require_once DWIC_PATH . 'includes/class-cookiejar-cache.php';
        require_once DWIC_PATH . 'includes/class-cookiejar-logger.php';
        require_once DWIC_PATH . 'includes/class-dwic-geotarget.php';
        require_once DWIC_PATH . 'includes/class-dwic-localization.php';
        // Updater is optional in free build
        if (file_exists(DWIC_PATH . 'includes/class-dwic-updater.php')) {
            require_once DWIC_PATH . 'includes/class-dwic-updater.php';
        }
        require_once DWIC_PATH . 'admin/class-cookiejar-admin.php';
        require_once DWIC_PATH . 'frontend/class-dwic-frontend.php';
    } catch (Throwable $e) {
        $GLOBALS['dwic_bootstrap_errors'][] = 'Failed to load a required file: ' . $e->getMessage();
        return false;
    }
    return true;
}

/**
 * Get current license tier
 */
function cookiejar_get_tier() {
    // Free build always reports Basic tier
    return COOKIEJAR_TIER_BASIC;
}

/**
 * Check if current tier is Pro
 */
function cookiejar_is_pro() {
    return cookiejar_get_tier() === COOKIEJAR_TIER_PRO;
}

/**
 * Add tier class to admin body
 */
function cookiejar_admin_body_class($classes) {
    $tier = cookiejar_get_tier();
    $classes .= ' cookiejar-tier-' . $tier;
    return $classes;
}
add_filter('admin_body_class', 'cookiejar_admin_body_class');

/**
 * Bootstrap plugin components when safe to do so.
 */
function dwic_bootstrap() {
    if (!dwic_requirements_ok()) return;
    if (!dwic_safe_load_files()) return;

    // Text domain auto-loaded by WordPress.org; no manual load here

    // Initialize enhanced systems
    try {
        // Initialize cache system
        if (class_exists('\\DWIC\\Cache')) {
            \DWIC\Cache::warm_up();
        }
        
        // Initialize logger
        if (class_exists('\\DWIC\\Logger')) {
            \DWIC\Logger::info('CookieJar plugin initialized successfully', [
                'version' => DWIC_VERSION,
                'php_version' => PHP_VERSION,
                'wordpress_version' => get_bloginfo('version')
            ]);
        }
        // Updater disabled in free build
        
    } catch (Throwable $e) {
        $GLOBALS['dwic_bootstrap_errors'][] = 'Failed to initialize enhanced systems: ' . $e->getMessage();
    }

    // Initialize AJAX endpoints (admin only)
    if (class_exists('\\DWIC\\Ajax')) {
        try {
            \DWIC\Ajax::init();
        } catch (Throwable $e) {
            $GLOBALS['dwic_bootstrap_errors'][] = 'Failed to initialize AJAX: ' . $e->getMessage();
        }
    } else {
        $GLOBALS['dwic_bootstrap_errors'][] = 'Class DWIC\\Ajax not found after include.';
    }

    // Frontend UI (always initialize)
    if (class_exists('\\DWIC\\Frontend\\Frontend')) {
        try {
            new \DWIC\Frontend\Frontend('cookiejar', DWIC_VERSION);
        } catch (Throwable $e) {
            $GLOBALS['dwic_bootstrap_errors'][] = 'Failed to initialize Frontend UI: ' . $e->getMessage();
        }
    } else {
        $GLOBALS['dwic_bootstrap_errors'][] = 'Class DWIC\\Frontend\\Frontend not found after include.';
    }

    // Admin UI
    if (is_admin()) {
        if (class_exists('\\DWIC\\Admin\\CookieJar_Admin')) {
            new \DWIC\Admin\CookieJar_Admin('cookiejar', DWIC_VERSION);
        }
        
        // Setup Wizard
        if (class_exists('\\DWIC\\Wizard')) {
            try {
                new \DWIC\Wizard();
            } catch (Throwable $e) {
                $GLOBALS['dwic_bootstrap_errors'][] = 'Failed to initialize Setup Wizard: ' . $e->getMessage();
            }
        } else {
            $GLOBALS['dwic_bootstrap_errors'][] = 'Class DWIC\\Wizard not found after include.';
        }
    }
}
add_action('plugins_loaded', 'dwic_bootstrap');

/**
 * Load plugin textdomain for translations.
 */
add_action('init', function(){
    load_plugin_textdomain('cookiejar', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

/**
 * Plugin links (Plugins list row)
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links){
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=cookiejar-control')) . '">' . esc_html__('Settings','cookiejar') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

add_filter('plugin_row_meta', function($links, $file){
    if ($file === plugin_basename(__FILE__)) {
        $links[] = '<a href="' . esc_url('https://wordpress.org/plugins/cookiejar/') . '">' . esc_html__('Plugin Page','cookiejar') . '</a>';
        $links[] = '<a href="' . esc_url('https://wordpress.org/support/plugin/cookiejar/') . '">' . esc_html__('Support','cookiejar') . '</a>';
    }
    return $links;
}, 10, 2);

/**
 * Activation: seed demo data safely and verify environment.
 */
function cookiejar_activation() {
    // Hard-stop activation if requirements not met
    if (version_compare(PHP_VERSION, DWIC_MIN_PHP, '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html( sprintf('CookieJar requires PHP %s or higher. Current: %s.', DWIC_MIN_PHP, PHP_VERSION) ),
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }

    try {
        // Set default tier if not set (free build defaults to BASIC)
        if (!get_option('cookiejar_license_tier')) {
            add_option('cookiejar_license_tier', COOKIEJAR_TIER_BASIC, '', 'yes');
        }

        // Set HASH default to enabled
        if (!get_option('cookiejar_hash_enabled')) {
            add_option('cookiejar_hash_enabled', 'yes', '', 'yes');
        }

        // Create database tables
        require_once DWIC_PATH . 'includes/class-cookiejar-db.php';
        if (class_exists('\\DWIC\\DB')) {
            \DWIC\DB::create_tables();
        }

        // Trigger wizard on first activation
        if (!get_option('cookiejar_wizard_done')) {
            add_option('cookiejar_wizard_done', 'no', '', 'yes');
            // Set transient to redirect to wizard after activation
            set_transient('cookiejar_activation_redirect', 'yes', 30);
        }

        // No demo data seeding in production
    } catch (Throwable $e) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html( 'CookieJar activation failed: ' . $e->getMessage() ),
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }
}
register_activation_hook(__FILE__, 'cookiejar_activation');

/**
 * Deactivation: clean up transients
 */
function cookiejar_deactivation() {
    // Clear tier-related transients
    delete_transient('cookiejar_trend_summary');
    delete_transient('cookiejar_status_hud');
    delete_transient('cookiejar_trend_summary_basic');
    delete_transient('cookiejar_status_hud_basic');
    
    // Clear enhanced caches
    if (class_exists('\\DWIC\\Cache')) {
        \DWIC\Cache::clear_all();
    }
    
    // Log deactivation
    if (class_exists('\\DWIC\\Logger')) {
        \DWIC\Logger::info('CookieJar plugin deactivated');
    }
}
register_deactivation_hook(__FILE__, 'cookiejar_deactivation');

/**
 * Uninstall: remove all plugin data
 */
function cookiejar_uninstall_cleanup() {
    // Remove all cookiejar_* options
    global $wpdb;
    $pattern = $wpdb->esc_like('cookiejar_') . '%';
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $pattern
        )
    );
    
    // Remove demo data
    delete_option('dwic_logs');
    delete_option('dwic_stats_meta');
    
    // Clear all transients
    $pattern_transient = $wpdb->esc_like('_transient_cookiejar_') . '%';
    $pattern_transient_timeout = $wpdb->esc_like('_transient_timeout_cookiejar_') . '%';
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $pattern_transient
        )
    );
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $pattern_transient_timeout
        )
    );
    
    // Clear enhanced caches
    if (class_exists('\\DWIC\\Cache')) {
        \DWIC\Cache::clear_all();
    }
    
    // Clean up log files
    $upload_dir = wp_upload_dir();
    $log_dir = $upload_dir['basedir'] . '/cookiejar-logs';
    if (is_dir($log_dir)) {
        $files = glob($log_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Uninstall cleanup, WP_Filesystem not available in this context
                unlink($file);
            }
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Uninstall cleanup, WP_Filesystem not available in this context
        rmdir($log_dir);
    }
    
    // Log uninstall
    if (class_exists('\\DWIC\\Logger')) {
        \DWIC\Logger::info('CookieJar plugin uninstalled and cleaned up');
    }
}
register_uninstall_hook(__FILE__, 'cookiejar_uninstall_cleanup');