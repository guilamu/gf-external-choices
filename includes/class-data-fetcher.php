<?php
/**
 * Data fetcher class for retrieving external data.
 *
 * @package GF_External_Choices
 */

defined('ABSPATH') || exit;

/**
 * GF_External_Choices_Data_Fetcher class.
 *
 * Handles fetching data from URLs and Media Library files.
 */
class GF_External_Choices_Data_Fetcher
{

    /**
     * Maximum file size in bytes (10MB).
     *
     * @var int
     */
    const MAX_FILE_SIZE = 10485760;

    /**
     * Fetch data from a URL or Media Library file.
     *
     * @param string $url The URL to fetch.
     * @return string|WP_Error The fetched content or error.
     */
    public function fetch($url)
    {
        if (empty($url)) {
            return new WP_Error('empty_url', __('URL is empty.', 'gf-external-choices'));
        }

        // Check if this is a local media library file
        $attachment_id = attachment_url_to_postid($url);
        if ($attachment_id) {
            return $this->fetch_from_media($attachment_id);
        }

        // Fetch from external URL
        return $this->fetch_from_url($url);
    }

    /**
     * Fetch data from an external URL.
     *
     * @param string $url The URL to fetch.
     * @return string|WP_Error The fetched content or error.
     */
    public function fetch_from_url($url)
    {
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'sslverify' => true,
        ));

        if (is_wp_error($response)) {
            return new WP_Error(
                'fetch_failed',
                sprintf(
                    /* translators: %s: error message */
                    __('Failed to fetch URL: %s', 'gf-external-choices'),
                    $response->get_error_message()
                )
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if (200 !== $status_code) {
            return new WP_Error(
                'http_error',
                sprintf(
                    /* translators: %d: HTTP status code */
                    __('HTTP error: %d', 'gf-external-choices'),
                    $status_code
                )
            );
        }

        $body = wp_remote_retrieve_body($response);

        // Check file size
        $size = strlen($body);
        if ($size > self::MAX_FILE_SIZE) {
            return new WP_Error(
                'file_too_large',
                sprintf(
                    /* translators: %s: max file size */
                    __('File exceeds maximum size of %s.', 'gf-external-choices'),
                    size_format(self::MAX_FILE_SIZE)
                )
            );
        }

        return $body;
    }

    /**
     * Fetch data from a Media Library attachment.
     *
     * @param int $attachment_id The attachment ID.
     * @return string|WP_Error The file content or error.
     */
    public function fetch_from_media($attachment_id)
    {
        $file_path = get_attached_file($attachment_id);

        if (!$file_path || !file_exists($file_path)) {
            return new WP_Error('file_not_found', __('Media file not found.', 'gf-external-choices'));
        }

        // Check file size
        $size = filesize($file_path);
        if ($size > self::MAX_FILE_SIZE) {
            return new WP_Error(
                'file_too_large',
                sprintf(
                    /* translators: %s: max file size */
                    __('File exceeds maximum size of %s.', 'gf-external-choices'),
                    size_format(self::MAX_FILE_SIZE)
                )
            );
        }

        $content = file_get_contents($file_path);

        if (false === $content) {
            return new WP_Error('read_failed', __('Failed to read media file.', 'gf-external-choices'));
        }

        return $content;
    }

    /**
     * Detect file format based on URL extension.
     *
     * @param string $url The URL to check.
     * @return string|null 'csv', 'json', or null if unknown.
     */
    public function detect_format($url)
    {
        $path = wp_parse_url($url, PHP_URL_PATH);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        switch ($extension) {
            case 'csv':
                return 'csv';
            case 'json':
                return 'json';
            case 'xlsx':
                return 'xlsx';
            default:
                return null;
        }
    }
}
