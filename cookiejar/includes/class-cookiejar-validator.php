<?php
namespace DWIC;
if (!defined('ABSPATH')) exit;

/**
 * CookieJar input validation utilities.
 */
class Validator {
    /**
     * Sanitize a string or array of strings with enhanced security.
     * @param mixed $value
     * @return mixed
     */
    public static function sanitize($value) {
        if (is_array($value)) {
            return array_map([self::class, 'sanitize'], $value);
        }
        
        // Enhanced sanitization for strings
        if (is_string($value)) {
            // Remove null bytes and control characters
            $value = str_replace(["\0", "\x00"], '', $value);
            
            // Trim whitespace
            $value = trim($value);
            
            // Apply WordPress sanitization
            $value = sanitize_text_field($value);
            
            // Additional security: remove potential XSS vectors
            $value = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $value);
            $value = preg_replace('/javascript:/i', '', $value);
            $value = preg_replace('/on\w+\s*=/i', '', $value);
        }
        
        return $value;
    }

    /**
     * Sanitize a URL with enhanced security checks.
     * @param string $url
     * @return string
     */
    public static function url($url) {
        if (empty($url)) {
            return '';
        }
        
        $url = trim($url);
        
        // Basic URL validation
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }
        
        // Security checks
        $parsed = wp_parse_url($url);
        if (!$parsed) {
            return '';
        }
        
        // Only allow http and https protocols
        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            return '';
        }
        
        // Check for suspicious patterns
        $suspicious_patterns = [
            'javascript:',
            'data:',
            'vbscript:',
            'file:',
            'ftp:'
        ];
        
        foreach ($suspicious_patterns as $pattern) {
            if (stripos($url, $pattern) !== false) {
                return '';
            }
        }
        
        return esc_url_raw($url);
    }

    /**
     * Sanitize a color with enhanced validation.
     * @param string $hex
     * @return string
     */
    public static function color($hex) {
        if (empty($hex)) {
            return '';
        }
        
        $hex = trim($hex);
        
        // Standard hex color validation
        if (preg_match('/^#[a-fA-F0-9]{6}$/', $hex)) {
            return sanitize_hex_color($hex);
        }
        
        // Allow 3-character hex colors
        if (preg_match('/^#[a-fA-F0-9]{3}$/', $hex)) {
            return sanitize_hex_color($hex);
        }
        
        // Allow CSS color names (basic set)
        $valid_colors = [
            'red', 'green', 'blue', 'white', 'black', 'yellow', 'orange',
            'purple', 'pink', 'brown', 'gray', 'grey', 'transparent'
        ];
        
        if (in_array(strtolower($hex), $valid_colors)) {
            return sanitize_text_field($hex);
        }
        
        // Allow rgb() and rgba() formats
        if (preg_match('/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(,\s*[\d.]+\s*)?\)$/', $hex)) {
            return sanitize_text_field($hex);
        }
        
        return '';
    }

    /**
     * Sanitize cookie categories (array or comma-separated string).
     * @param mixed $data
     * @return array
     */
    public static function categories($data) {
        if (is_string($data)) {
            $data = explode(',', $data);
        }
        return array_unique(array_map('sanitize_text_field', (array) $data));
    }

    /**
     * Sanitize languages (array or comma-separated string).
     * @param mixed $arr
     * @return array
     */
    public static function languages($arr) {
        if (is_string($arr)) $arr = explode(',', $arr);
        return array_unique(array_map('sanitize_text_field', (array)$arr));
    }

    /**
     * Sanitize JSON string into array.
     * @param string $json
     * @return array
     */
    public static function json($json) {
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}
