<?php
/**
 * Main add-on class for Gravity Forms External Choices.
 *
 * @package GF_External_Choices
 */

defined('ABSPATH') || exit;

/**
 * GF_External_Choices class.
 *
 * Extends GFAddOn to provide external choice population for Multiple Choice fields.
 */
class GF_External_Choices extends GFAddOn
{

    /**
     * Plugin version.
     *
     * @var string
     */
    protected $_version = GF_EXTERNAL_CHOICES_VERSION;

    /**
     * Minimum Gravity Forms version required.
     *
     * @var string
     */
    protected $_min_gravityforms_version = '2.5';

    /**
     * Plugin slug.
     *
     * @var string
     */
    protected $_slug = 'gf-external-choices';

    /**
     * Plugin path relative to plugins directory.
     *
     * @var string
     */
    protected $_path = 'gf-external-choices/gf-external-choices.php';

    /**
     * Full path to this file.
     *
     * @var string
     */
    protected $_full_path = __FILE__;

    /**
     * Plugin title.
     *
     * @var string
     */
    protected $_title = 'Gravity Forms External Choices';

    /**
     * Short plugin title for menus.
     *
     * @var string
     */
    protected $_short_title = 'External Choices';

    /**
     * Singleton instance.
     *
     * @var GF_External_Choices|null
     */
    private static $_instance = null;

    /**
     * Data fetcher instance.
     *
     * @var GF_External_Choices_Data_Fetcher
     */
    private $data_fetcher;

    /**
     * Cache manager instance.
     *
     * @var GF_External_Choices_Cache_Manager
     */
    private $cache_manager;

    /**
     * Choice validator instance.
     *
     * @var GF_External_Choices_Validator
     */
    private $validator;

    /**
     * Field settings instance.
     *
     * @var GF_External_Choices_Field_Settings
     */
    private $field_settings;

    /**
     * Get singleton instance.
     *
     * @return GF_External_Choices
     */
    public static function get_instance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Initialize the add-on.
     */
    public function init()
    {
        parent::init();

        // Initialize components
        $this->data_fetcher = new GF_External_Choices_Data_Fetcher();
        $this->cache_manager = new GF_External_Choices_Cache_Manager();
        $this->validator = new GF_External_Choices_Validator();
        $this->field_settings = new GF_External_Choices_Field_Settings($this);

        // Register hooks
        $this->register_hooks();
    }

    /**
     * Register all hooks for the add-on.
     */
    private function register_hooks()
    {
        // Populate external choices on form render
        add_filter('gform_pre_render', array($this, 'populate_external_choices'), 10, 1);
        add_filter('gform_pre_submission_filter', array($this, 'populate_external_choices'), 10, 1);
        add_filter('gform_admin_pre_render', array($this, 'populate_external_choices'), 10, 1);

        // Validate submitted values against current external data
        add_filter('gform_validation', array($this, 'validate_external_choices'), 10, 1);

        // Add field settings
        add_action('gform_field_standard_settings', array($this->field_settings, 'render_settings'), 10, 2);

        // Register field settings with GF (must be inline via gform_editor_js)
        add_action('gform_editor_js', array($this, 'editor_script'));

        // Enqueue scripts and styles
        add_action('gform_editor_js', array($this, 'enqueue_editor_scripts'));

        // AJAX handlers
        add_action('wp_ajax_gf_external_choices_refresh', array($this, 'ajax_force_refresh'));
        add_action('wp_ajax_gf_external_choices_get_columns', array($this, 'ajax_get_columns'));

        // Prune invalid entries when resuming save & continue
        add_filter('gform_incomplete_submission_pre_save', array($this, 'prune_invalid_draft_entries'), 10, 3);
    }

    /**
     * Prune invalid/stale entries when a draft is resumed.
     *
     * @param array $submission_data The submission data to be saved.
     * @param string $resume_token   The resume token.
     * @param array $form            The form object.
     * @return array Modified submission data.
     */
    public function prune_invalid_draft_entries($submission_data, $resume_token, $form)
    {
        if (empty($form['fields']) || empty($submission_data['partial_entry'])) {
            return $submission_data;
        }

        $entry = $submission_data['partial_entry'];
        $modified = false;

        foreach ($form['fields'] as $field) {
            if (!$this->is_external_choice_field($field)) {
                continue;
            }

            $field_id = is_object($field) ? $field->id : rgar($field, 'id');
            $value = rgar($entry, $field_id);

            if (empty($value)) {
                continue;
            }

            // Get current valid choices
            $choices = $this->get_external_choices($field);
            if (is_wp_error($choices) || empty($choices)) {
                continue;
            }

            $valid_values = wp_list_pluck($choices, 'value');
            $submitted_values = is_array($value) ? $value : array($value);
            $new_values = array();
            $has_invalid = false;

            foreach ($submitted_values as $submitted_val) {
                if (in_array($submitted_val, $valid_values, true)) {
                    $new_values[] = $submitted_val;
                } else {
                    $has_invalid = true;
                }
            }

            if ($has_invalid) {
                // For single value fields (radio/select), clear if invalid
                if (!is_array($value)) {
                    $submission_data['partial_entry'][$field_id] = '';
                } else {
                    // For multi-select (checkbox), keep only valid ones
                    $submission_data['partial_entry'][$field_id] = $new_values;
                }
                $modified = true;
            }
        }

        return $submission_data;
    }

    /**
     * Populate external choices for Multiple Choice fields.
     *
     * @param array $form The form object.
     * @return array Modified form object.
     */
    public function populate_external_choices($form)
    {
        if (empty($form['fields'])) {
            return $form;
        }

        foreach ($form['fields'] as &$field) {
            if (!$this->is_external_choice_field($field)) {
                continue;
            }

            $choices = $this->get_external_choices($field);

            if (!is_wp_error($choices) && !empty($choices)) {
                $field->choices = $choices;
            }
        }

        return $form;
    }

    /**
     * Check if a field uses external choices.
     *
     * @param GF_Field $field The field object.
     * @return bool
     */
    private function is_external_choice_field($field)
    {
        // Only Multiple Choice fields (checkbox, radio, select, multi_choice)
        $type = is_object($field) ? $field->type : rgar($field, 'type');
        if (!in_array($type, array('checkbox', 'radio', 'select', 'multi_choice'), true)) {
            return false;
        }

        // Check if external source is configured
        $source_type = rgar($field, 'externalChoicesSourceType');
        return in_array($source_type, array('url', 'media'), true);
    }

    /**
     * Get external choices for a field.
     *
     * @param GF_Field $field The field object.
     * @return array|WP_Error Array of choices or error.
     */
    public function get_external_choices($field)
    {
        $source_type = rgar($field, 'externalChoicesSourceType');
        $source_url = '';
        $is_local = false;

        if ('url' === $source_type) {
            $source_url = rgar($field, 'externalChoicesUrl');
            // Check if URL is local (same host as site)
            $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
            $url_host = wp_parse_url($source_url, PHP_URL_HOST);
            $is_local = ($site_host === $url_host);
        } elseif ('media' === $source_type) {
            $attachment_id = absint(rgar($field, 'externalChoicesMediaId'));
            $source_url = $attachment_id ? wp_get_attachment_url($attachment_id) : '';
            $is_local = true; // Media Library files are always local
        }

        if (empty($source_url)) {
            return new WP_Error('no_source', __('No external source configured.', 'gf-external-choices'));
        }

        // Get column mappings
        $label_column = rgar($field, 'externalChoicesLabelColumn');
        $value_column = rgar($field, 'externalChoicesValueColumn');

        // Only use cache for external (non-local) sources
        // Local files are fast to read directly
        if (!$is_local) {
            $cache_key = $source_url . '|' . $label_column . '|' . $value_column;
            $cached = $this->cache_manager->get($cache_key);
            if (false !== $cached) {
                return $cached;
            }
        }

        // Fetch data
        $data = $this->data_fetcher->fetch($source_url);
        if (is_wp_error($data)) {
            return $data;
        }

        // Parse data based on format
        $format = $this->data_fetcher->detect_format($source_url);

        if ('csv' === $format) {
            $parser = new GF_External_Choices_CSV_Parser();
            $choices = $parser->parse($data, $label_column, $value_column);
        } elseif ('json' === $format) {
            $parser = new GF_External_Choices_JSON_Parser();
            $choices = $parser->parse($data, $label_column, $value_column);
        } elseif ('xlsx' === $format) {
            // XLSX requires file path, get it from media attachment
            $attachment_id = absint(rgar($field, 'externalChoicesMediaId'));
            $file_path = $attachment_id ? get_attached_file($attachment_id) : '';
            if (empty($file_path)) {
                return new WP_Error('xlsx_no_file', __('XLSX files must be uploaded to the Media Library.', 'gf-external-choices'));
            }
            $parser = new GF_External_Choices_XLSX_Parser();
            $choices = $parser->parse($file_path, $label_column, $value_column);
        } else {
            return new WP_Error('unknown_format', __('Unknown file format.', 'gf-external-choices'));
        }

        if (is_wp_error($choices)) {
            return $choices;
        }

        // Validate choices
        $validation = $this->validator->validate($choices);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Only cache external (non-local) sources
        if (!$is_local) {
            $cache_key = $source_url . '|' . $label_column . '|' . $value_column;
            $this->cache_manager->set($cache_key, $choices);
        }

        return $choices;
    }

    /**
     * Validate submitted values against current external data.
     *
     * @param array $validation_result The validation result.
     * @return array Modified validation result.
     */
    public function validate_external_choices($validation_result)
    {
        $form = $validation_result['form'];

        foreach ($form['fields'] as &$field) {
            if (!$this->is_external_choice_field($field)) {
                continue;
            }

            $field_id = is_object($field) ? $field->id : rgar($field, 'id');
            $submitted_value = rgpost('input_' . $field_id);
            if (empty($submitted_value)) {
                continue;
            }

            // Get choices - will use cache if available, otherwise fetches fresh data
            $choices = $this->get_external_choices($field);
            if (is_wp_error($choices) || empty($choices)) {
                // Fail-safe: reject submission if we cannot verify the choices
                $validation_result['is_valid'] = false;
                if (is_object($field)) {
                    $field->failed_validation = true;
                    $field->validation_message = __('Unable to verify your selection. Please try again.', 'gf-external-choices');
                }
                continue;
            }

            // Check if submitted value exists in current choices
            $valid_values = wp_list_pluck($choices, 'value');
            $submitted_values = is_array($submitted_value) ? $submitted_value : array($submitted_value);

            foreach ($submitted_values as $value) {
                if (!in_array($value, $valid_values, true)) {
                    $validation_result['is_valid'] = false;
                    if (is_object($field)) {
                        $field->failed_validation = true;
                        $field->validation_message = __('The selected option is no longer available. Please refresh and select again.', 'gf-external-choices');
                    }
                    break;
                }
            }
        }

        $validation_result['form'] = $form;
        return $validation_result;
    }

    /**
     * Output inline JavaScript to register field settings with Gravity Forms editor.
     * This must be inline via gform_editor_js to execute at the correct GF initialization time.
     */
    public function editor_script()
    {
        ?>
        <script type="text/javascript">
            // Register field settings with Gravity Forms editor
            // This must be inline as it needs to execute at the correct GF initialization time
            if (typeof fieldSettings !== 'undefined') {
                fieldSettings.checkbox += ', .external_choices_settings';
                fieldSettings.radio += ', .external_choices_settings';
                fieldSettings.select += ', .external_choices_settings';
                if (fieldSettings.multi_choice !== undefined) {
                    fieldSettings.multi_choice += ', .external_choices_settings';
                }
            }
        </script>
        <?php
    }

    /**
     * Enqueue editor scripts and styles.
     */
    public function enqueue_editor_scripts()
    {
        wp_enqueue_script(
            'gf-external-choices-field-settings',
            GF_EXTERNAL_CHOICES_URL . 'assets/js/field-settings.js',
            array('jquery', 'gform_form_admin'),
            GF_EXTERNAL_CHOICES_VERSION,
            true
        );

        wp_localize_script(
            'gf-external-choices-field-settings',
            'gfExternalChoices',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('gf_external_choices'),
                'strings' => array(
                    'loading' => __('Loading...', 'gf-external-choices'),
                    'error' => __('Error loading choices', 'gf-external-choices'),
                    'noChoices' => __('No choices found', 'gf-external-choices'),
                    'choicesOf' => __('Showing %1$d of %2$d choices', 'gf-external-choices'),
                    'refresh' => __('Refresh', 'gf-external-choices'),
                    'refreshing' => __('Refreshing...', 'gf-external-choices'),
                ),
            )
        );

        wp_enqueue_style(
            'gf-external-choices-field-settings',
            GF_EXTERNAL_CHOICES_URL . 'assets/css/field-settings.css',
            array(),
            GF_EXTERNAL_CHOICES_VERSION
        );
    }

    /**
     * AJAX handler for forcing cache refresh.
     */
    public function ajax_force_refresh()
    {
        check_ajax_referer('gf_external_choices', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission denied.', 'gf-external-choices'));
        }

        $source_url = esc_url_raw(rgpost('source_url'));

        if (empty($source_url)) {
            wp_send_json_error(__('No source URL provided.', 'gf-external-choices'));
        }

        // Clear cache
        $this->cache_manager->clear($source_url);

        // Fetch fresh data
        $data = $this->data_fetcher->fetch($source_url);
        if (is_wp_error($data)) {
            wp_send_json_error($data->get_error_message());
        }

        wp_send_json_success(__('Cache refreshed successfully.', 'gf-external-choices'));
    }

    /**
     * AJAX handler for getting column headers from a file.
     */
    public function ajax_get_columns()
    {
        check_ajax_referer('gf_external_choices', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(__('Permission denied.', 'gf-external-choices'));
        }

        $source_type = sanitize_text_field(rgpost('source_type'));
        $source_url = esc_url_raw(rgpost('source_url'));
        $media_id = absint(rgpost('media_id'));

        if ('media' === $source_type && $media_id) {
            $source_url = wp_get_attachment_url($media_id);
        }

        if (empty($source_url)) {
            wp_send_json_error(__('No source URL provided.', 'gf-external-choices'));
        }

        // Fetch data
        $data = $this->data_fetcher->fetch($source_url);
        if (is_wp_error($data)) {
            wp_send_json_error($data->get_error_message());
        }

        // Get columns based on format
        $format = $this->data_fetcher->detect_format($source_url);
        $columns = array();

        if ('csv' === $format) {
            $parser = new GF_External_Choices_CSV_Parser();
            $columns = $parser->get_columns($data);
        } elseif ('json' === $format) {
            $parser = new GF_External_Choices_JSON_Parser();
            $columns = $parser->get_columns($data);
        } elseif ('xlsx' === $format && $media_id) {
            // XLSX requires file path
            $file_path = get_attached_file($media_id);
            if ($file_path) {
                $parser = new GF_External_Choices_XLSX_Parser();
                $columns = $parser->get_columns($file_path);
            }
        }

        if (is_wp_error($columns)) {
            wp_send_json_error($columns->get_error_message());
        }

        wp_send_json_success(array('columns' => $columns));
    }

    /**
     * Get the data fetcher instance.
     *
     * @return GF_External_Choices_Data_Fetcher
     */
    public function get_data_fetcher()
    {
        return $this->data_fetcher;
    }

    /**
     * Get the cache manager instance.
     *
     * @return GF_External_Choices_Cache_Manager
     */
    public function get_cache_manager()
    {
        return $this->cache_manager;
    }

    /**
     * Get the validator instance.
     *
     * @return GF_External_Choices_Validator
     */
    public function get_validator()
    {
        return $this->validator;
    }
}
