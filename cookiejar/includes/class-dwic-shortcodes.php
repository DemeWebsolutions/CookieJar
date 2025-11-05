<?php
namespace DWIC;
if (!defined('ABSPATH')) exit;

class Shortcodes {
    public static function register() {
        add_shortcode('dwic_policy_table', [__CLASS__, 'policy_table']);
    }

    public static function policy_table($atts = [], $content = '') {
        $days = (int)get_option('dwic_days', 180);
        $cats = [
            'necessary' => 365,
            'functional' => 365,
            'analytics' => 180,
            'ads' => 180,
            'chatbot' => 90,
            'donotsell' => 365,
        ];
        $out = '<table class="dwic-policy-table" style="width:100%;border-collapse:collapse">';
        $out .= '<thead><tr><th style="text-align:left;border-bottom:1px solid #e2e8f0;padding:6px 8px">Category</th><th style="text-align:left;border-bottom:1px solid #e2e8f0;padding:6px 8px">Retention (days)</th></tr></thead><tbody>';
        foreach ($cats as $k=>$v) {
            $name = ucfirst($k);
            $out .= '<tr><td style="padding:6px 8px;border-bottom:1px solid #f1f5f9">'.esc_html($name).'</td><td style="padding:6px 8px;border-bottom:1px solid #f1f5f9">'.(int)$v.'</td></tr>';
        }
        $out .= '<tr><td style="padding:6px 8px" colspan="2"><small>Default consent lifetime: '.(int)$days.' days.</small></td></tr>';
        $out .= '</tbody></table>';
        return $out;
    }
}