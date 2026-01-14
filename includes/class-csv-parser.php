<?php
/**
 * CSV parser class.
 *
 * @package GF_External_Choices
 */

defined( 'ABSPATH' ) || exit;

/**
 * GF_External_Choices_CSV_Parser class.
 *
 * Parses CSV data with delimiter and encoding detection.
 */
class GF_External_Choices_CSV_Parser {

    /**
     * Supported delimiters for auto-detection.
     *
     * @var array
     */
    private $delimiters = array( ',', ';', "\t" );

    /**
     * Parse CSV data into choices array.
     *
     * @param string $data         The raw CSV data.
     * @param string $label_column The column name or index for labels.
     * @param string $value_column The column name or index for values.
     * @return array|WP_Error Array of choices or error.
     */
    public function parse( $data, $label_column = '', $value_column = '' ) {
        if ( empty( $data ) ) {
            return new WP_Error( 'empty_data', __( 'CSV data is empty.', 'gf-external-choices' ) );
        }

        // Detect and convert encoding to UTF-8, normalize line endings
        $data = $this->normalize_encoding( $data );

        // Detect delimiter
        $delimiter = $this->detect_delimiter( $data );

        // Parse CSV using stream to properly handle quoted fields with embedded newlines
        $rows = $this->parse_csv_rows( $data, $delimiter );

        if ( count( $rows ) < 2 ) {
            return new WP_Error( 'no_data_rows', __( 'CSV must have a header row and at least one data row.', 'gf-external-choices' ) );
        }

        // Parse header row
        $header = array_map( 'trim', $rows[0] );

        // Determine column indices
        $label_index = $this->get_column_index( $header, $label_column, 0 );
        $value_index = $this->get_column_index( $header, $value_column, $label_index );

        // For single-column CSV, value equals label
        $single_column = ( count( $header ) === 1 );

        $choices = array();

        // Parse data rows (skip header at index 0)
        for ( $i = 1; $i < count( $rows ); $i++ ) {
            $row = $rows[ $i ];

            // Skip empty rows
            if ( empty( $row ) || ( count( $row ) === 1 && '' === trim( $row[0] ) ) ) {
                continue;
            }

            // Get label
            $label = isset( $row[ $label_index ] ) ? trim( $row[ $label_index ] ) : '';

            // Skip rows with empty labels
            if ( '' === $label ) {
                continue;
            }

            // Get value (for single column, use label as value)
            if ( $single_column ) {
                $value = $label;
            } else {
                $value = isset( $row[ $value_index ] ) ? trim( $row[ $value_index ] ) : '';
            }

            $choices[] = array(
                'text'       => $label,
                'value'      => $value,
                'isSelected' => false,
            );
        }

        if ( empty( $choices ) ) {
            return new WP_Error( 'no_choices', __( 'No valid choices found in CSV.', 'gf-external-choices' ) );
        }

        return $choices;
    }

    /**
     * Parse CSV data into rows using stream-based parsing.
     *
     * This properly handles quoted fields with embedded newlines.
     *
     * @param string $data      The CSV data.
     * @param string $delimiter The field delimiter.
     * @return array Array of rows, each row is an array of fields.
     */
    private function parse_csv_rows( $data, $delimiter ) {
        // Use php://temp stream to parse CSV properly
        $stream = fopen( 'php://temp', 'r+' );
        fwrite( $stream, $data );
        rewind( $stream );

        $rows = array();
        while ( ( $row = fgetcsv( $stream, 0, $delimiter ) ) !== false ) {
            $rows[] = $row;
        }

        fclose( $stream );

        return $rows;
    }

    /**
     * Detect and normalize encoding to UTF-8.
     *
     * @param string $data The raw data.
     * @return string UTF-8 encoded data.
     */
    private function normalize_encoding( $data ) {
        // Remove BOM if present
        $bom = pack( 'H*', 'EFBBBF' );
        $data = preg_replace( "/^$bom/", '', $data );

        // Normalize mixed line endings to \n
        $data = str_replace( "\r\n", "\n", $data );
        $data = str_replace( "\r", "\n", $data );

        // Try to detect encoding
        $encoding = mb_detect_encoding( $data, array( 'UTF-8', 'ISO-8859-1', 'Windows-1252' ), true );

        if ( $encoding && 'UTF-8' !== $encoding ) {
            $data = mb_convert_encoding( $data, 'UTF-8', $encoding );
        }

        return $data;
    }

    /**
     * Auto-detect CSV delimiter.
     *
     * @param string $data The CSV data.
     * @return string The detected delimiter.
     */
    private function detect_delimiter( $data ) {
        // Get first line for detection
        $first_line = strtok( $data, "\n" );

        $counts = array();
        foreach ( $this->delimiters as $delimiter ) {
            $counts[ $delimiter ] = substr_count( $first_line, $delimiter );
        }

        // Return delimiter with highest count
        arsort( $counts );
        $best_delimiter = key( $counts );

        // Default to comma if no delimiter found
        return $counts[ $best_delimiter ] > 0 ? $best_delimiter : ',';
    }

    /**
     * Get column index from header.
     *
     * @param array  $header       The header row.
     * @param string $column       The column name or index.
     * @param int    $default      Default index if not found.
     * @return int Column index.
     */
    private function get_column_index( $header, $column, $default = 0 ) {
        if ( '' === $column ) {
            return $default;
        }

        // Check if it's a numeric index
        if ( is_numeric( $column ) ) {
            $index = intval( $column );
            return ( $index >= 0 && $index < count( $header ) ) ? $index : $default;
        }

        // Search by column name
        $index = array_search( $column, $header, true );
        return ( false !== $index ) ? $index : $default;
    }

    /**
     * Get available columns from CSV data.
     *
     * @param string $data The CSV data.
     * @return array|WP_Error Array of column names or error.
     */
    public function get_columns( $data ) {
        if ( empty( $data ) ) {
            return new WP_Error( 'empty_data', __( 'CSV data is empty.', 'gf-external-choices' ) );
        }

        $data      = $this->normalize_encoding( $data );
        $delimiter = $this->detect_delimiter( $data );
        $first_line = strtok( $data, "\n" );
        $header    = str_getcsv( $first_line, $delimiter );

        return array_map( 'trim', $header );
    }
}
