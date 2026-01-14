<?php
/**
 * Cache manager class.
 *
 * @package GF_External_Choices
 */

defined( 'ABSPATH' ) || exit;

/**
 * GF_External_Choices_Cache_Manager class.
 *
 * Manages WordPress transient caching for external choices.
 * Cache is shared by URL - multiple fields using the same source share cache.
 */
class GF_External_Choices_Cache_Manager {

    /**
     * Cache key prefix.
     *
     * @var string
     */
    const CACHE_PREFIX = 'gf_ext_choices_';

    /**
     * Default cache TTL in seconds (1 day).
     *
     * @var int
     */
    const DEFAULT_TTL = DAY_IN_SECONDS;

    /**
     * Get cached choices for a URL.
     *
     * @param string $url The source URL.
     * @return array|false Cached choices or false if not cached.
     */
    public function get( $url ) {
        $key    = $this->generate_key( $url );
        $cached = get_transient( $key );

        if ( false === $cached ) {
            return false;
        }

        return $cached;
    }

    /**
     * Set cached choices for a URL.
     *
     * @param string $url     The source URL.
     * @param array  $choices The choices to cache.
     * @param int    $ttl     Optional. Cache TTL in seconds.
     * @return bool True on success.
     */
    public function set( $url, $choices, $ttl = null ) {
        if ( null === $ttl ) {
            $ttl = self::DEFAULT_TTL;
        }

        $key = $this->generate_key( $url );
        return set_transient( $key, $choices, $ttl );
    }

    /**
     * Clear cache for a URL.
     *
     * @param string $url The source URL.
     * @return bool True on success.
     */
    public function clear( $url ) {
        $key = $this->generate_key( $url );
        return delete_transient( $key );
    }

    /**
     * Generate cache key from URL.
     *
     * @param string $url The source URL.
     * @return string Cache key.
     */
    private function generate_key( $url ) {
        // Use MD5 hash to keep key length manageable
        return self::CACHE_PREFIX . md5( $url );
    }

    /**
     * Get cache status for a URL.
     *
     * @param string $url The source URL.
     * @return array Status array with 'status' and 'message' keys.
     */
    public function get_status( $url ) {
        $cached = $this->get( $url );

        if ( false === $cached ) {
            return array(
                'status'  => 'stale',
                'message' => __( 'Cache is empty or expired.', 'gf-external-choices' ),
            );
        }

        if ( is_array( $cached ) && ! empty( $cached ) ) {
            return array(
                'status'  => 'healthy',
                'message' => sprintf(
                    /* translators: %d: number of cached choices */
                    __( '%d choices cached.', 'gf-external-choices' ),
                    count( $cached )
                ),
            );
        }

        return array(
            'status'  => 'error',
            'message' => __( 'Invalid cached data.', 'gf-external-choices' ),
        );
    }

    /**
     * Get TTL constant for a frequency setting.
     *
     * @param string $frequency The frequency setting (hourly, daily, weekly).
     * @return int TTL in seconds.
     */
    public function get_ttl_for_frequency( $frequency ) {
        switch ( $frequency ) {
            case 'hourly':
                return HOUR_IN_SECONDS;
            case 'weekly':
                return WEEK_IN_SECONDS;
            case 'daily':
            default:
                return DAY_IN_SECONDS;
        }
    }
}
