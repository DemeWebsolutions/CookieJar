<?php
namespace DWIC;
if (!defined('ABSPATH')) exit;

class Cache {
    private static $prefix = 'dwic_';

    public static function remember($key, $seconds, $callback) {
        $tkey = self::$prefix.$key;
        $val = get_transient($tkey);
        if ($val === false) {
            $val = call_user_func($callback);
            set_transient($tkey, $val, (int)$seconds);
        }
        return $val;
    }

    public static function get($key) {
        return get_transient(self::$prefix.$key);
    }

    public static function put($key, $value, $seconds) {
        return set_transient(self::$prefix.$key, $value, (int)$seconds);
    }

    public static function clear($key) {
        return delete_transient(self::$prefix.$key);
    }

    public static function clear_all() {
        global $wpdb;
        // Purge only our transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like('_transient_'.self::$prefix).'%',
                $wpdb->esc_like('_transient_timeout_'.self::$prefix).'%'
            )
        );
    }
}