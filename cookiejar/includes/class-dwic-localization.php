<?php
namespace DWIC;
if (!defined('ABSPATH')) exit;

class Localization {
    public static function current_lang() {
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
        return strtolower(substr($locale, 0, 2));
    }
}