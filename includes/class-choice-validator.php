<?php
/**
 * Choice validator class.
 *
 * @package GF_External_Choices
 */

defined( 'ABSPATH' ) || exit;

/**
 * GF_External_Choices_Validator class.
 *
 * Validates choice arrays for Gravity Forms compatibility.
 */
class GF_External_Choices_Validator {

    /**
     * Maximum number of choices allowed.
     *
     * @var int
     */
    const MAX_CHOICES = 1000;

    /**
     * Blocked characters pattern for values.
     * Blocks characters that could cause HTML/JS issues: < > " ' & \ and control characters
     *
     * @var string
     */
    const BLOCKED_CHARS_PATTERN = '/[<>"\'&\\\\\\x00-\\x1F]/u';

    /**
     * Validate an array of choices.
     *
     * @param array $choices The choices to validate.
     * @return true|WP_Error True if valid, WP_Error on failure.
     */
    public function validate( $choices ) {
        if ( ! is_array( $choices ) ) {
            return new WP_Error( 'invalid_type', __( 'Choices must be an array.', 'gf-external-choices' ) );
        }

        if ( empty( $choices ) ) {
            return new WP_Error( 'empty_choices', __( 'No choices provided.', 'gf-external-choices' ) );
        }

        // Check choice count limit
        if ( count( $choices ) > self::MAX_CHOICES ) {
            return new WP_Error(
                'too_many_choices',
                sprintf(
                    /* translators: %d: maximum number of choices */
                    __( 'Too many choices. Maximum allowed is %d.', 'gf-external-choices' ),
                    self::MAX_CHOICES
                )
            );
        }

        $values     = array();
        $duplicates = array();
        $invalid    = array();
        $empty      = array();

        foreach ( $choices as $index => $choice ) {
            $label = isset( $choice['text'] ) ? $choice['text'] : '';
            $value = isset( $choice['value'] ) ? $choice['value'] : '';

            // Check for empty labels
            if ( '' === $label || '' === trim( $label ) ) {
                $empty[] = $index + 1;
                continue;
            }

            // Check for empty values
            if ( '' === $value || '' === trim( $value ) ) {
                $empty[] = $index + 1;
                continue;
            }

            // Check value format (block dangerous characters)
            if ( preg_match( self::BLOCKED_CHARS_PATTERN, $value ) ) {
                $invalid[] = $value;
            }

            // Check for duplicate values
            if ( in_array( $value, $values, true ) ) {
                $duplicates[] = $value;
            } else {
                $values[] = $value;
            }
        }

        // Build error message if there are issues
        $errors = array();

        if ( ! empty( $empty ) ) {
            $errors[] = sprintf(
                /* translators: %s: row numbers with empty values */
                __( 'Empty labels or values found at rows: %s', 'gf-external-choices' ),
                implode( ', ', array_slice( $empty, 0, 10 ) ) . ( count( $empty ) > 10 ? '...' : '' )
            );
        }

        if ( ! empty( $duplicates ) ) {
            $unique_duplicates = array_unique( $duplicates );
            return new WP_Error(
                'duplicate_values',
                sprintf(
                    /* translators: %s: list of duplicate values */
                    __( 'Duplicate values found: %s. Each value must be unique.', 'gf-external-choices' ),
                    implode( ', ', array_slice( $unique_duplicates, 0, 5 ) ) . ( count( $unique_duplicates ) > 5 ? '...' : '' )
                )
            );
        }

        if ( ! empty( $invalid ) ) {
            $unique_invalid = array_unique( $invalid );
            return new WP_Error(
                'invalid_characters',
                sprintf(
                    /* translators: %s: list of invalid values */
                    __( 'Invalid characters in values: %s. The characters < > " \' & \\ are not allowed.', 'gf-external-choices' ),
                    implode( ', ', array_slice( $unique_invalid, 0, 5 ) ) . ( count( $unique_invalid ) > 5 ? '...' : '' )
                )
            );
        }

        // If there are only empty value warnings but the rest is valid, we've already filtered them
        return true;
    }

    /**
     * Sanitize a value to be compatible with Gravity Forms.
     *
     * @param string $value The value to sanitize.
     * @return string Sanitized value.
     */
    public function sanitize_value( $value ) {
        // Remove dangerous characters: < > " ' & \ and control characters
        $sanitized = preg_replace( self::BLOCKED_CHARS_PATTERN, '', $value );

        // Trim whitespace
        $sanitized = trim( $sanitized );

        return $sanitized;
    }

    /**
     * Check if a single value is valid.
     *
     * @param string $value The value to check.
     * @return bool True if valid (no blocked characters).
     */
    public function is_valid_value( $value ) {
        // Valid if it doesn't contain any blocked characters
        return ! preg_match( self::BLOCKED_CHARS_PATTERN, $value );
    }
}
