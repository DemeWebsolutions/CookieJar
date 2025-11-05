<?php
namespace DWIC;
if (!defined('ABSPATH')) exit;

class Updater {
    public static function init($args = []) {
        $plugin_file = isset($args['plugin_file']) ? $args['plugin_file'] : plugin_basename(dirname(__FILE__, 2) . '/cookiejar.php');

        // Auto-update toggle driven by option cookiejar_auto_updates (yes/no)
        add_filter('auto_update_plugin', function($update, $item) use ($plugin_file) {
            if (isset($item->plugin) && $item->plugin === $plugin_file) {
                return get_option('cookiejar_auto_updates', 'no') === 'yes';
            }
            return $update;
        }, 10, 2);

        // Add action link in Plugins list for quick toggle
        add_filter('plugin_action_links_' . $plugin_file, function($links) {
            if (!current_user_can('manage_options')) return $links;
            $enabled = get_option('cookiejar_auto_updates', 'no') === 'yes';
            $label = $enabled ? __('Disable auto-updates', 'cookiejar') : __('Enable auto-updates', 'cookiejar');
            $url = wp_nonce_url(admin_url('admin-ajax.php?action=cookiejar_toggle_auto_updates'), 'cookiejar_admin');
            $links[] = '<a href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
            return $links;
        });

        // AJAX toggle handler
        add_action('wp_ajax_cookiejar_toggle_auto_updates', function() {
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field( wp_unslash($_GET['_wpnonce']) ) : '';
            if (!current_user_can('manage_options') || !wp_verify_nonce($nonce, 'cookiejar_admin')) {
                wp_die('forbidden');
            }
            $enabled = get_option('cookiejar_auto_updates', 'no') === 'yes';
            update_option('cookiejar_auto_updates', $enabled ? 'no' : 'yes', false);
            wp_safe_redirect(admin_url('plugins.php'));
            exit;
        });
    }
}