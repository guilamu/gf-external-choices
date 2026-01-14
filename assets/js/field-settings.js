/**
 * Field settings JavaScript for Gravity Forms External Choices.
 *
 * @package GF_External_Choices
 */

(function ($) {
    'use strict';

    /**
     * Toggle visibility of fields based on source type selection.
     */
    window.gfExternalChoicesToggleFields = function () {
        var sourceType = $('#external_choices_source_type').val();
        var isExternal = sourceType === 'url' || sourceType === 'media';
        var isRemoteUrl = sourceType === 'url'; // Only external URLs need caching/refresh

        // Toggle URL field
        $('.gf-external-choices-url-field').toggle(sourceType === 'url');

        // Toggle Media field
        $('.gf-external-choices-media-field').toggle(sourceType === 'media');

        // Toggle common external fields (always show for both URL and Media)
        $('.gf-external-choices-mapping').toggle(isExternal);

        // Toggle refresh controls - only show for external URLs (not for local/media files)
        // Local files don't use caching so refresh controls are not needed
        $('.gf-external-choices-refresh').toggle(isRemoteUrl);
    };

    /**
     * Force refresh cache.
     */
    function forceRefresh() {
        var sourceType = $('#external_choices_source_type').val();
        var sourceUrl = $('#external_choices_url').val();
        var mediaId = $('#external_choices_media_id').val();

        var url = sourceUrl;
        if (sourceType === 'media' && mediaId) {
            url = 'media:' + mediaId;
        }

        if (!url) {
            return;
        }

        $('#external_choices_force_refresh').prop('disabled', true).text(gfExternalChoices.strings.refreshing || 'Refreshing...');

        $.ajax({
            url: gfExternalChoices.ajaxUrl,
            type: 'POST',
            data: {
                action: 'gf_external_choices_refresh',
                nonce: gfExternalChoices.nonce,
                source_url: url
            },
            success: function (response) {
                $('#external_choices_force_refresh').prop('disabled', false).text(gfExternalChoices.strings.refresh || 'Force Refresh');
                if (response.success) {
                    loadColumns();
                }
            },
            error: function () {
                $('#external_choices_force_refresh').prop('disabled', false).text(gfExternalChoices.strings.refresh || 'Force Refresh');
            }
        });
    }

    /**
     * Initialize Media Library selector.
     */
    function initMediaSelector() {
        var mediaFrame;

        $('#external_choices_media_button').on('click', function (e) {
            e.preventDefault();

            if (mediaFrame) {
                mediaFrame.open();
                return;
            }

            mediaFrame = wp.media({
                title: gfExternalChoices.strings.selectFile || 'Select CSV or JSON File',
                button: {
                    text: gfExternalChoices.strings.useFile || 'Use This File'
                },
                library: {
                    type: ['text/csv', 'application/json', 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
                },
                multiple: false
            });

            mediaFrame.on('select', function () {
                var attachment = mediaFrame.state().get('selection').first().toJSON();

                var filename = attachment.filename || attachment.title;
                $('#external_choices_media_id').val(attachment.id);
                SetFieldProperty('externalChoicesMediaId', attachment.id);
                SetFieldProperty('externalChoicesMediaFilename', filename);

                $('#external_choices_media_filename').text(filename);

                // Load columns for the new file
                loadColumns();
            });

            mediaFrame.open();
        });
    }

    /**
     * Load columns from the source file and populate dropdowns.
     */
    function loadColumns() {
        var sourceType = $('#external_choices_source_type').val();
        var sourceUrl = $('#external_choices_url').val();
        var mediaId = $('#external_choices_media_id').val();

        // Store current selections
        var currentLabelColumn = $('#external_choices_label_column').val();
        var currentValueColumn = $('#external_choices_value_column').val();

        // Clear dropdowns
        var defaultOption = '<option value="">-- Select Column --</option>';
        $('#external_choices_label_column').html(defaultOption);
        $('#external_choices_value_column').html(defaultOption);

        if (!sourceType || (sourceType === 'url' && !sourceUrl) || (sourceType === 'media' && !mediaId)) {
            return;
        }

        $.ajax({
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
                    var options = defaultOption;

                    for (var i = 0; i < columns.length; i++) {
                        options += '<option value="' + escapeHtml(columns[i]) + '">' + escapeHtml(columns[i]) + '</option>';
                    }

                    $('#external_choices_label_column').html(options);
                    $('#external_choices_value_column').html(options);

                    // Restore previous selections if they still exist
                    if (currentLabelColumn) {
                        $('#external_choices_label_column').val(currentLabelColumn);
                    }
                    if (currentValueColumn) {
                        $('#external_choices_value_column').val(currentValueColumn);
                    }
                }
            }
        });
    }

    /**
     * Escape HTML special characters.
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize on document ready
    $(document).ready(function () {
        initMediaSelector();

        // Force refresh button click
        $('#external_choices_force_refresh').on('click', function (e) {
            e.preventDefault();
            forceRefresh();
        });

        // Auto-load columns when URL changes
        $('#external_choices_url').on('change', function () {
            if ($(this).val()) {
                loadColumns();
            }
        });

        // Auto-load columns when source type changes
        $('#external_choices_source_type').on('change', function () {
            loadColumns();
        });
    });

})(jQuery);
