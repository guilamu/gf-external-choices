<?php
/**
 * JSON parser class.
 *
 * @package GF_External_Choices
 */

defined( 'ABSPATH' ) || exit;

/**
 * GF_External_Choices_JSON_Parser class.
 *
 * Parses JSON data (flat objects only, no nested support in v1).
 */
class GF_External_Choices_JSON_Parser {

    /**
     * Parse JSON data into choices array.
     *
     * @param string $data           The raw JSON data.
     * @param string $label_property The property name for labels.
     * @param string $value_property The property name for values.
     * @return array|WP_Error Array of choices or error.
     */
    public function parse( $data, $label_property = '', $value_property = '' ) {
        if ( empty( $data ) ) {
            return new WP_Error( 'empty_data', __( 'JSON data is empty.', 'gf-external-choices' ) );
        }

        $parsed = json_decode( $data, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error(
                'json_parse_error',
                sprintf(
                    /* translators: %s: JSON error message */
                    __( 'JSON parse error: %s', 'gf-external-choices' ),
                    json_last_error_msg()
                )
            );
        }

        // Must be an array at root level
        if ( ! is_array( $parsed ) ) {
            return new WP_Error( 'invalid_structure', __( 'JSON must be an array at the root level.', 'gf-external-choices' ) );
        }

        // Check if array is empty
        if ( empty( $parsed ) ) {
            return new WP_Error( 'empty_array', __( 'JSON array is empty.', 'gf-external-choices' ) );
        }

        // Check that first item is an object/associative array (not nested)
        $first_item = reset( $parsed );
        if ( ! is_array( $first_item ) || $this->is_nested( $first_item ) ) {
            return new WP_Error(
                'nested_not_supported',
                __( 'JSON must contain flat objects only. Nested objects are not supported in this version.', 'gf-external-choices' )
            );
        }

        // Get available properties from first item
        $properties = array_keys( $first_item );

        // Determine which properties to use for label/value
        $label_prop = $this->get_property_name( $properties, $label_property, 0 );
        $value_prop = $this->get_property_name( $properties, $value_property, count( $properties ) > 1 ? 1 : 0 );

        // Build choices array
        $choices = array();

        foreach ( $parsed as $item ) {
            if ( ! is_array( $item ) ) {
                continue;
            }

            $label = isset( $item[ $label_prop ] ) ? trim( strval( $item[ $label_prop ] ) ) : '';
            $value = isset( $item[ $value_prop ] ) ? trim( strval( $item[ $value_prop ] ) ) : '';

            // Skip items with empty labels
            if ( '' === $label ) {
                continue;
            }

            // For single-property objects, use label as value
            if ( $label_prop === $value_prop || '' === $value ) {
                $value = $label;
            }

            $choices[] = array(
                'text'       => $label,
                'value'      => $value,
                'isSelected' => false,
            );
        }

        if ( empty( $choices ) ) {
            return new WP_Error( 'no_choices', __( 'No valid choices found in JSON.', 'gf-external-choices' ) );
        }

        return $choices;
    }

    /**
     * Check if an array contains nested objects.
     *
     * @param array $item The item to check.
     * @return bool True if nested.
     */
    private function is_nested( $item ) {
        foreach ( $item as $value ) {
            if ( is_array( $value ) || is_object( $value ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get property name from available properties.
     *
     * @param array  $properties Available properties.
     * @param string $requested  Requested property name.
     * @param int    $default    Default index.
     * @return string Property name.
     */
    private function get_property_name( $properties, $requested, $default = 0 ) {
        if ( '' !== $requested && in_array( $requested, $properties, true ) ) {
            return $requested;
        }

        // Use default index
        if ( isset( $properties[ $default ] ) ) {
            return $properties[ $default ];
        }

        // Fall back to first property
        return isset( $properties[0] ) ? $properties[0] : '';
    }

    /**
     * Get available properties from JSON data.
     *
     * @param string $data The JSON data.
     * @return array|WP_Error Array of property names or error.
     */
    public function get_properties( $data ) {
        if ( empty( $data ) ) {
            return new WP_Error( 'empty_data', __( 'JSON data is empty.', 'gf-external-choices' ) );
        }

        $parsed = json_decode( $data, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_parse_error', json_last_error_msg() );
        }

        if ( ! is_array( $parsed ) || empty( $parsed ) ) {
            return new WP_Error( 'invalid_structure', __( 'JSON must be a non-empty array.', 'gf-external-choices' ) );
        }

        $first_item = reset( $parsed );

        if ( ! is_array( $first_item ) ) {
            return new WP_Error( 'invalid_item', __( 'JSON array must contain objects.', 'gf-external-choices' ) );
        }

        return array_keys( $first_item );
    }

    /**
     * Get available columns/properties from JSON data.
     * Alias for get_properties() to maintain consistent API with CSV parser.
     *
     * @param string $data The JSON data.
     * @return array|WP_Error Array of property names or error.
     */
    public function get_columns( $data ) {
        return $this->get_properties( $data );
    }
}
