<?php
/**
 * Plugin Name: Gravity Forms External Choices
 * Plugin URI: https://github.com/guilamu/gf-external-choices
 * Description: Populate Gravity Forms Multiple Choice fields from external CSV, JSON, or XLSX data sources.
 * Version: 1.0.1
 * Author: Guillaume
 * Author URI: https://github.com/guilamu
 * License: GPL-2.0+
 * Text Domain: gf-external-choices
 * Requires PHP: 7.4
 * Requires at least: 5.0
 * Update URI: https://github.com/guilamu/gf-external-choices/
 *
 * @package GF_External_Choices
 */

defined('ABSPATH') || exit;

define('GF_EXTERNAL_CHOICES_VERSION', '1.0.1');
define('GF_EXTERNAL_CHOICES_PATH', plugin_dir_path(__FILE__));
define('GF_EXTERNAL_CHOICES_URL', plugin_dir_url(__FILE__));

/**
 * Bootstrap class for Gravity Forms External Choices add-on.
 */
class GF_External_Choices_Bootstrap
{

    /**
     * Load the add-on when Gravity Forms is loaded.
     */
    public static function load()
    {
        if (!method_exists('GFForms', 'include_addon_framework')) {
            return;
        }

        // Include the add-on framework
        GFForms::include_addon_framework();

        // Include required files
        require_once GF_EXTERNAL_CHOICES_PATH . 'includes/class-data-fetcher.php';
        require_once GF_EXTERNAL_CHOICES_PATH . 'includes/class-csv-parser.php';
        require_once GF_EXTERNAL_CHOICES_PATH . 'includes/class-json-parser.php';
        require_once GF_EXTERNAL_CHOICES_PATH . 'includes/class-xlsx-parser.php';
        require_once GF_EXTERNAL_CHOICES_PATH . 'includes/class-cache-manager.php';
        require_once GF_EXTERNAL_CHOICES_PATH . 'includes/class-choice-validator.php';
        require_once GF_EXTERNAL_CHOICES_PATH . 'includes/class-field-settings.php';
        require_once GF_EXTERNAL_CHOICES_PATH . 'class-gf-external-choices.php';

        // Register the add-on
        GFAddOn::register('GF_External_Choices');
    }
}

// Hook into Gravity Forms loaded action
add_action('gform_loaded', array('GF_External_Choices_Bootstrap', 'load'), 5);

// Load plugin textdomain for translations
add_action('plugins_loaded', function() {
    load_plugin_textdomain('gf-external-choices', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Register with Guilamu Bug Reporter
add_action('plugins_loaded', function() {
    if (class_exists('Guilamu_Bug_Reporter')) {
        Guilamu_Bug_Reporter::register(array(
            'slug'        => 'gf-external-choices',
            'name'        => 'Gravity Forms External Choices',
            'version'     => GF_EXTERNAL_CHOICES_VERSION,
            'github_repo' => 'guilamu/gf-external-choices',
        ));
    }
}, 20);

// Add "Report a Bug" link to plugins list
add_filter('plugin_row_meta', 'gf_external_choices_plugin_row_meta', 10, 2);

function gf_external_choices_plugin_row_meta($links, $file) {
    if (plugin_basename(__FILE__) !== $file) {
        return $links;
    }

    if (class_exists('Guilamu_Bug_Reporter')) {
        $links[] = sprintf(
            '<a href="#" class="guilamu-bug-report-btn" data-plugin-slug="gf-external-choices" data-plugin-name="%s">%s</a>',
            esc_attr__('Gravity Forms External Choices', 'gf-external-choices'),
            esc_html__('🐛 Report a Bug', 'gf-external-choices')
        );
    } else {
        $links[] = sprintf(
            '<a href="%s" target="_blank">%s</a>',
            'https://github.com/guilamu/guilamu-bug-reporter/releases',
            esc_html__('🐛 Report a Bug (install Bug Reporter)', 'gf-external-choices')
        );
    }

    return $links;
}

// Include the GitHub auto-updater (loads independently of Gravity Forms)
require_once GF_EXTERNAL_CHOICES_PATH . 'includes/class-github-updater.php';

/**
 * Get the instance of the add-on.
 *
 * @return GF_External_Choices|null
 */
function gf_external_choices()
{
    if (class_exists('GF_External_Choices')) {
        return GF_External_Choices::get_instance();
    }
    return null;
}
