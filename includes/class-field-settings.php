<?php
/**
 * Field settings class.
 *
 * @package GF_External_Choices
 */

defined('ABSPATH') || exit;

/**
 * GF_External_Choices_Field_Settings class.
 *
 * Renders the field settings UI in the form editor.
 */
class GF_External_Choices_Field_Settings
{

    /**
     * Parent add-on instance.
     *
     * @var GF_External_Choices
     */
    private $addon;

    /**
     * Constructor.
     *
     * @param GF_External_Choices $addon Parent add-on instance.
     */
    public function __construct($addon)
    {
        $this->addon = $addon;
    }

    /**
     * Render the external choices settings in the field settings panel.
     *
     * @param int $position The current position in settings.
     * @param int $form_id  The form ID.
     */
    public function render_settings($position, $form_id)
    {
        // Add settings at position 25 (standard settings section)
        if (25 !== $position) {
            return;
        }
        ?>
        <li class="external_choices_settings field_setting">
            <div class="gf-external-choices-settings">
                <label class="section_label">
                    <?php esc_html_e('External Choices', 'gf-external-choices'); ?>
                    <?php gform_tooltip('external_choices_source'); ?>
                </label>

                <!-- Source Type -->
                <div class="gf-external-choices-row">
                    <label for="external_choices_source_type">
                        <?php esc_html_e('Source Type', 'gf-external-choices'); ?>
                    </label>
                    <select id="external_choices_source_type"
                        onchange="SetFieldProperty('externalChoicesSourceType', this.value); gfExternalChoicesToggleFields();">
                        <option value=""><?php esc_html_e('Manual (Default)', 'gf-external-choices'); ?></option>
                        <option value="url"><?php esc_html_e('External URL', 'gf-external-choices'); ?></option>
                        <option value="media"><?php esc_html_e('Media Library', 'gf-external-choices'); ?></option>
                    </select>
                </div>

                <!-- External URL Input -->
                <div class="gf-external-choices-row gf-external-choices-url-field" style="display:none;">
                    <label for="external_choices_url">
                        <?php esc_html_e('URL', 'gf-external-choices'); ?>
                    </label>
                    <input type="url" id="external_choices_url" class="fieldwidth-4" placeholder="https://example.com/data.csv"
                        onchange="SetFieldProperty('externalChoicesUrl', this.value);" />
                </div>

                <!-- Media Library Selector -->
                <div class="gf-external-choices-row gf-external-choices-media-field" style="display:none;">
                    <label for="external_choices_media">
                        <?php esc_html_e('Media File', 'gf-external-choices'); ?>
                    </label>
                    <div class="gf-external-choices-media-selector">
                        <input type="hidden" id="external_choices_media_id" />
                        <span id="external_choices_media_filename" class="gf-external-choices-filename">
                            <?php esc_html_e('No file selected', 'gf-external-choices'); ?>
                        </span>
                        <button type="button" class="button" id="external_choices_media_button">
                            <?php esc_html_e('Select File', 'gf-external-choices'); ?>
                        </button>
                    </div>
                </div>

                <!-- Column Mapping -->
                <div class="gf-external-choices-row gf-external-choices-mapping" style="display:none;">
                    <div class="gf-external-choices-column">
                        <label for="external_choices_label_column">
                            <?php esc_html_e('Label Column/Property', 'gf-external-choices'); ?>
                        </label>
                        <select id="external_choices_label_column" class="fieldwidth-2"
                            onchange="SetFieldProperty('externalChoicesLabelColumn', this.value);">
                            <option value=""><?php esc_html_e('-- Select Column --', 'gf-external-choices'); ?></option>
                        </select>
                    </div>
                    <div class="gf-external-choices-column">
                        <label for="external_choices_value_column">
                            <?php esc_html_e('Value Column/Property', 'gf-external-choices'); ?>
                        </label>
                        <select id="external_choices_value_column" class="fieldwidth-2"
                            onchange="SetFieldProperty('externalChoicesValueColumn', this.value);">
                            <option value=""><?php esc_html_e('-- Select Column --', 'gf-external-choices'); ?></option>
                        </select>
                    </div>
                </div>

                <!-- Refresh Frequency -->
                <div class="gf-external-choices-row gf-external-choices-refresh" style="display:none;">
                    <label><?php esc_html_e('Refresh Frequency', 'gf-external-choices'); ?></label>
                    <div class="gf-external-choices-refresh-controls">
                        <div class="gf-external-choices-column">
                            <select id="external_choices_refresh_frequency"
                                onchange="SetFieldProperty('externalChoicesRefreshFrequency', this.value);">
                                <option value="hourly"><?php esc_html_e('Hourly', 'gf-external-choices'); ?></option>
                                <option value="daily"><?php esc_html_e('Daily', 'gf-external-choices'); ?></option>
                                <option value="weekly"><?php esc_html_e('Weekly', 'gf-external-choices'); ?></option>
                            </select>
                        </div>
                        <div class="gf-external-choices-column">
                            <button type="button" class="button" id="external_choices_force_refresh">
                                <?php esc_html_e('Force Refresh', 'gf-external-choices'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Status Indicator -->
            </div>
        </li>

        <script type="text/javascript">
            // Initialize field values when field is selected
            jQuery(document).on('gform_load_field_settings', function(event, field, form) {
                // Set basic field values first
                jQuery('#external_choices_source_type').val(rgar(field, 'externalChoicesSourceType') || '');
                jQuery('#external_choices_url').val(rgar(field, 'externalChoicesUrl') || '');
                jQuery('#external_choices_media_id').val(rgar(field, 'externalChoicesMediaId') || '');
                jQuery('#external_choices_refresh_frequency').val(rgar(field, 'externalChoicesRefreshFrequency') || 'daily');

                // Update media filename display
                var mediaId = rgar(field, 'externalChoicesMediaId');
                var mediaFilename = rgar(field, 'externalChoicesMediaFilename');
                if (mediaId && mediaFilename) {
                    jQuery('#external_choices_media_filename').text(mediaFilename);
                } else if (mediaId) {
                    jQuery('#external_choices_media_filename').text('<?php esc_html_e( 'File selected (ID: ', 'gf-external-choices' ); ?>' + mediaId + ')');
                } else {
                    jQuery('#external_choices_media_filename').text('<?php esc_html_e( 'No file selected', 'gf-external-choices' ); ?>');
                }

                // Store column values to restore after loading
                var labelColumn = rgar(field, 'externalChoicesLabelColumn') || '';
                var valueColumn = rgar(field, 'externalChoicesValueColumn') || '';

                // Load columns for dropdowns, then set the stored values
                var sourceType = rgar(field, 'externalChoicesSourceType');
                var sourceUrl = rgar(field, 'externalChoicesUrl');
                
                if (sourceType && (sourceUrl || mediaId)) {
                    jQuery.ajax({
                        url: gfExternalChoices.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'gf_external_choices_get_columns',
                            nonce: gfExternalChoices.nonce,
                            source_type: sourceType,
                            source_url: sourceUrl,
                            media_id: mediaId
                        },
                        success: function (response) {
                            if (response.success && response.data.columns) {
                                var columns = response.data.columns;
                                var defaultOption = '<option value=""><?php esc_html_e( '-- Select Column --', 'gf-external-choices' ); ?></option>';
                                var options = defaultOption;

                                for (var i = 0; i < columns.length; i++) {
                                    var escaped = jQuery('<div>').text(columns[i]).html();
                                    options += '<option value="' + escaped + '">' + escaped + '</option>';
                                }

                                jQuery('#external_choices_label_column').html(options).val(labelColumn);
                                jQuery('#external_choices_value_column').html(options).val(valueColumn);
                            }
                        }
                    });
                }

                gfExternalChoicesToggleFields();
            });
        </script>
        <?php
    }
}

// Register tooltips
add_filter('gform_tooltips', function ($tooltips) {
    $tooltips['external_choices_source'] = sprintf(
        '<h6>%s</h6>%s',
        esc_html__('External Choices', 'gf-external-choices'),
        esc_html__('Populate choices from an external CSV or JSON file. The file can be from a URL or uploaded to your Media Library.', 'gf-external-choices')
    );
    return $tooltips;
});
