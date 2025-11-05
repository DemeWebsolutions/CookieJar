<?php
namespace DWIC;
if (!defined('ABSPATH')) exit;

class GeoTarget {
    public static function get_country($ip) {
        // 1) Cloudflare header if present
        if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $cc = strtoupper(sanitize_text_field( wp_unslash($_SERVER['HTTP_CF_IPCOUNTRY']) ));
            if (preg_match('/^[A-Z]{2}$/', $cc)) return $cc;
        }
        // 2) Allow override via filter
        $override = apply_filters('dwic_geo_country', '', $ip);
        if (is_string($override) && preg_match('/^[A-Z]{2}$/i', $override)) {
            return strtoupper($override);
        }
        // 3) GeoIP if available (no external HTTP)
        if (function_exists('geoip_country_code_by_name') && $ip) {
            $cc = @geoip_country_code_by_name($ip);
            if ($cc && preg_match('/^[A-Z]{2}$/i', $cc)) return strtoupper($cc);
        }
        // 4) Fallback
        return 'US';
    }

    public static function law_for_country($country) {
        $cc = strtoupper((string)$country);
        if ($cc === 'US') return 'ccpa';
        return 'gdpr';
    }
}