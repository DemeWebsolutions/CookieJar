<?php
namespace DWIC;
if (!defined('ABSPATH')) exit;

class Health {
    private static $results = [];

    public static function boot(){
        // Build once per admin page load
        if (!is_admin() || !current_user_can('manage_options')) return;
        self::$results = self::run_checks();
        // Surface failures as a single admin notice
        add_action('admin_notices',[__CLASS__,'notice']);
        // Make available to admin UI
        add_action('admin_init', function(){
            $GLOBALS['DWIC_HEALTH_RESULTS'] = self::$results;
        });
    }

    public static function results(){
        return self::$results;
    }

    private static function check_file($path){
        return file_exists($path) && filesize($path) > 0;
    }

    private static function no_external_hrefs($svg_abs){
        if (!file_exists($svg_abs)) return false;
        $svg = @file_get_contents($svg_abs);
        if ($svg === false) return false;
        // Ensure no external http(s) references remain in sanitized SVGs
        return !preg_match('#<image[^>]+href=["\']https?://#i', $svg);
    }

    private static function run_checks(){
        $out = [];

        // Assets presence
        $out['asset_cookie_png'] = [
            'ok' => self::check_file(DWIC_PATH.'assets/img/cookiejar.png'),
            'msg'=> 'Cookie icon PNG present'
        ];
        $out['asset_admin_svg'] = [
            'ok' => self::check_file(DWIC_PATH.'assets/img/cookiejar-admin.svg'),
            'msg'=> 'Sanitized Admin SVG present'
        ];
        $out['asset_bakery_svg'] = [
            'ok' => self::check_file(DWIC_PATH.'assets/img/cookiejar-bakery.svg'),
            'msg'=> 'Sanitized Bakery SVG present'
        ];
        $out['admin_svg_local_refs'] = [
            'ok' => self::no_external_hrefs(DWIC_PATH.'assets/img/cookiejar-admin.svg'),
            'msg'=> 'Admin SVG contains no external image links'
        ];
        $out['bakery_svg_local_refs'] = [
            'ok' => self::no_external_hrefs(DWIC_PATH.'assets/img/cookiejar-bakery.svg'),
            'msg'=> 'Bakery SVG contains no external image links'
        ];

        // Banner assets present
        $out['banner_js'] = [
            'ok' => self::check_file(DWIC_PATH.'assets/js/banner.js'),
            'msg'=> 'Banner JS present'
        ];
        $out['banner_css'] = [
            'ok' => self::check_file(DWIC_PATH.'assets/css/banner.css'),
            'msg'=> 'Banner CSS present'
        ];

        // Stats endpoint (invoke DB directly for structure)
        $stats_ok = false; $stats_msg = 'Stats returned structure';
        try{
            $stats = \DWIC\DB::get_stats();
            $stats_ok = is_array($stats)
                && array_key_exists('full',$stats)
                && array_key_exists('partial',$stats)
                && array_key_exists('none',$stats);
        }catch(\Throwable $e){
            $stats_ok = false; $stats_msg = 'Stats error: '.$e->getMessage();
        }
        $out['stats_structure'] = [
            'ok' => (bool)$stats_ok,
            'msg'=> $stats_msg
        ];

        // AJAX URL exists (string)
        $ajax = admin_url('admin-ajax.php');
        $out['ajax_url'] = [
            'ok' => is_string($ajax) && strpos($ajax,'admin-ajax.php') !== false,
            'msg'=> 'Admin AJAX URL resolved'
        ];

        // GA disabled in template mode (no functional issue from missing GA)
        $out['ga_disabled_in_template'] = [
            'ok' => defined('DWIC_TEMPLATE_MODE') && DWIC_TEMPLATE_MODE ? true : false,
            'msg'=> 'Template Mode active (GA/Consent Mode hidden)'
        ];

        return $out;
    }

    public static function notice(){
        $fails = array_filter(self::$results, function($r){ return empty($r['ok']); });
        if(!$fails) return;
        echo '<div class="notice notice-warning"><p><strong>CookieJar Health Check</strong>: Some checks failed. See details on the Dashboard.</p></div>';
    }
}