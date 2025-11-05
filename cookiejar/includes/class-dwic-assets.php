<?php
namespace DWIC;
if (!defined('ABSPATH')) exit;

/**
 * Local asset helper (free build)
 *
 * For WordPress.org compliance, no remote downloads are performed at runtime.
 * Ensures local cookie icon exists; SVGs were sanitized at build time.
 */
class Assets {
    private const COOKIE_ICON_B64 =
      'iVBORw0KGgoAAAANSUhEUgAAAGAAAABgCAYAAABw4pVUAAAACXBIWXMAAAsSAAALEgHS3X78AAABQ0lEQVR4nO3aQW7CQBBF0f3/'
    . 'a7b2sV4gqU8fW3wq+uR9S5k7QmYw3mB9CwQeQ8c0iQ6bqg9q1mN3Qk0bZ7sVgAAAB8n0k7w5m9b6+fB+vJ3sGkZ84qB+7fQ1g5bj3sH'
    . '1m5V2fM6h0l7s3VwU1lVd2jvC3k0x8y0q8m2gq4v1g7Kz3t2p2Oq2aYwz6y3JY1Zkq8tX0YyY7gGm3VY0bq4cKxgY2r6v1s1a8o1b8'
    . 'aJb8mG4fW0b2q3nY9s0mY4Yw8bq2fY3q0mZ4Yw8Zq2fY3q0mY4Yw8bq2fY3q0mZ4Yw8fQMAAP9t8JfQW1+7qf1w8Ck4mEo5f8bJvt0'
    . 'H2q5w1sJq8sN3a4o7Yb0m5r3a0WcW8bqz3Y8b0Gm2WwAAAAAAAPzXfQ0AAAB8wTgAAAB8wTgAAAB8wTgAAAB8wTgAAACf2c4eHcH2'
    . 'f3HfQAAAABJRU5ErkJggg==';

    private static function ensure_cookie_png(){
        $img_dir_abs = DWIC_PATH.'assets/img/';
        if (!file_exists($img_dir_abs)) {
            wp_mkdir_p($img_dir_abs);
        }
        if (!is_dir($img_dir_abs) || !wp_is_writable($img_dir_abs)) return;
        $cookie_abs = $img_dir_abs.'cookiejar.png';
        if (file_exists($cookie_abs) && filesize($cookie_abs) > 0) return;
        $bin = base64_decode(self::COOKIE_ICON_B64);
        if ($bin) {
            // Prefer WP_Filesystem API
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
            global $wp_filesystem;
            if ($wp_filesystem) {
                $wp_filesystem->put_contents($cookie_abs, $bin, FS_CHMOD_FILE);
            } else {
                // Fallback
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_write
                @file_put_contents($cookie_abs, $bin);
            }
        }
    }

    public static function ensure_local_assets(){
        if (!is_admin() || !current_user_can('manage_options')) return;
        // Only ensure local placeholder icon; no remote network calls in free build
        self::ensure_cookie_png();
    }
}