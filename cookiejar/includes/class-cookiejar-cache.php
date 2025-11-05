<?php
namespace DWIC;
if (!defined('ABSPATH')) exit;

/**
 * Enhanced caching system for CookieJar plugin.
 * 
 * Provides intelligent caching for analytics queries, database results,
 * and expensive operations to improve performance.
 * 
 * @package CookieJar
 * @since 1.0.1
 */
class Cache {
    
    const CACHE_PREFIX = 'cookiejar_cache_';
    const DEFAULT_TTL = 300; // 5 minutes
    const LONG_TTL = 3600;   // 1 hour
    const SHORT_TTL = 60;    // 1 minute
    
    /**
     * Get cached data with fallback.
     * 
     * @param string $key Cache key
     * @param callable $callback Fallback function to generate data
     * @param int $ttl Time to live in seconds
     * @return mixed Cached or generated data
     */
    public static function remember($key, $callback, $ttl = self::DEFAULT_TTL) {
        $cache_key = self::CACHE_PREFIX . $key;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $data = $callback();
        set_transient($cache_key, $data, $ttl);
        
        return $data;
    }
    
    /**
     * Cache analytics data with smart invalidation.
     * 
     * @param string $type Analytics type (stats, logs, trends)
     * @param callable $callback Data generation callback
     * @return mixed Cached analytics data
     */
    public static function analytics($type, $callback) {
        $key = "analytics_{$type}";
        $ttl = self::SHORT_TTL; // Analytics refresh frequently
        
        return self::remember($key, $callback, $ttl);
    }
    
    /**
     * Cache database query results.
     * 
     * @param string $query_hash Hash of the query
     * @param callable $callback Query execution callback
     * @param int $ttl Cache duration
     * @return mixed Query results
     */
    public static function query($query_hash, $callback, $ttl = self::DEFAULT_TTL) {
        $key = "query_{$query_hash}";
        return self::remember($key, $callback, $ttl);
    }
    
    /**
     * Cache configuration data.
     * 
     * @param string $config_key Configuration key
     * @param callable $callback Config generation callback
     * @return mixed Configuration data
     */
    public static function config($config_key, $callback) {
        $key = "config_{$config_key}";
        $ttl = self::LONG_TTL; // Config changes infrequently
        
        return self::remember($key, $callback, $ttl);
    }
    
    /**
     * Invalidate cache by pattern.
     * 
     * @param string $pattern Cache key pattern
     * @return bool Success status
     */
    public static function invalidate($pattern) {
        global $wpdb;
        
        $pattern = self::CACHE_PREFIX . $pattern;
        $like_pattern = '%' . $wpdb->esc_like($pattern) . '%';
        $transient_pattern = '_transient_%';
        $transients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 AND option_name LIKE %s",
                $like_pattern,
                $transient_pattern
            )
        );
        
        $deleted = 0;
        foreach ($transients as $transient) {
            $key = str_replace('_transient_', '', $transient->option_name);
            if (delete_transient($key)) {
                $deleted++;
            }
        }
        
        return $deleted > 0;
    }
    
    /**
     * Clear all CookieJar caches.
     * 
     * @return int Number of caches cleared
     */
    public static function clear_all() {
        return self::invalidate('*');
    }
    
    /**
     * Get cache statistics.
     * 
     * @return array Cache statistics
     */
    public static function stats() {
        global $wpdb;
        
        $pattern = self::CACHE_PREFIX . '%';
        $transient_pattern = '_transient_%';
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} 
                 WHERE option_name LIKE %s 
                 AND option_name LIKE %s",
                $pattern,
                $transient_pattern
            )
        );
        
        return [
            'total_caches' => (int) $count,
            'cache_prefix' => self::CACHE_PREFIX,
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true)
        ];
    }
    
    /**
     * Warm up critical caches.
     * 
     * @return bool Success status
     */
    public static function warm_up() {
        try {
            // Warm up analytics cache
            self::analytics('stats', function() {
                return \DWIC\DB::get_stats();
            });
            
            // Warm up configuration cache
            self::config('all', function() {
                return \DWIC\Config::all();
            });
            
            return true;
        } catch (Exception $e) {
            error_log('CookieJar cache warm-up failed: ' . $e->getMessage());
            return false;
        }
    }
}
