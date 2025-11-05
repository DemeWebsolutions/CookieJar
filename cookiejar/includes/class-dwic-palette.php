<?php
namespace DWIC;
if (!defined('ABSPATH')) exit;

class Palette {
    public static function suggest($client = []) {
        // Very simple palette suggestion from existing settings
        $colors = get_option('dwic_custom_colors', []);
        $primary = isset($colors['primary']) ? $colors['primary'] : '#008ed6';
        $accent  = isset($colors['accent'])  ? $colors['accent']  : '#57bff3';

        return [
            'light' => [
                'primary' => $primary,
                'background' => '#ffffff',
                'text' => '#0f172a',
                'accent' => $accent,
            ],
            'dark' => [
                'primary' => $primary,
                'background' => '#0b1220',
                'text' => '#e5e7eb',
                'accent' => $accent,
            ]
        ];
    }
}