<?php
/**
 * XLSX parser class.
 *
 * @package GF_External_Choices
 */

defined('ABSPATH') || exit;

/**
 * GF_External_Choices_XLSX_Parser class.
 *
 * Parses XLSX files using native PHP ZipArchive and SimpleXML.
 * XLSX files are ZIP archives containing XML files with spreadsheet data.
 */
class GF_External_Choices_XLSX_Parser
{

    /**
     * Shared strings from the workbook.
     *
     * @var array
     */
    private $shared_strings = array();

    /**
     * Parse XLSX file into choices array.
     *
     * @param string $file_path    The path to the XLSX file.
     * @param string $label_column The column name or index for labels.
     * @param string $value_column The column name or index for values.
     * @return array|WP_Error Array of choices or error.
     */
    public function parse($file_path, $label_column = '', $value_column = '')
    {
        $rows = $this->extract_rows($file_path);

        if (is_wp_error($rows)) {
            return $rows;
        }

        if (count($rows) < 2) {
            return new WP_Error('no_data_rows', __('XLSX must have a header row and at least one data row.', 'gf-external-choices'));
        }

        // Parse header row
        $header = array_map('trim', $rows[0]);

        // Determine column indices
        $label_index = $this->get_column_index($header, $label_column, 0);
        $value_index = $this->get_column_index($header, $value_column, $label_index);

        // For single-column XLSX, value equals label
        $single_column = (count($header) === 1);

        $choices = array();

        // Parse data rows (skip header at index 0)
        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            // Skip empty rows
            if (empty($row) || (count($row) === 1 && '' === trim($row[0]))) {
                continue;
            }

            // Get label
            $label = isset($row[$label_index]) ? trim($row[$label_index]) : '';

            // Skip rows with empty labels
            if ('' === $label) {
                continue;
            }

            // Get value (for single column, use label as value)
            if ($single_column) {
                $value = $label;
            } else {
                $value = isset($row[$value_index]) ? trim($row[$value_index]) : '';
            }

            $choices[] = array(
                'text' => $label,
                'value' => $value,
                'isSelected' => false,
            );
        }

        if (empty($choices)) {
            return new WP_Error('no_choices', __('No valid choices found in XLSX.', 'gf-external-choices'));
        }

        return $choices;
    }

    /**
     * Get available columns from XLSX file.
     *
     * @param string $file_path The path to the XLSX file.
     * @return array|WP_Error Array of column names or error.
     */
    public function get_columns($file_path)
    {
        $rows = $this->extract_rows($file_path);

        if (is_wp_error($rows)) {
            return $rows;
        }

        if (empty($rows)) {
            return new WP_Error('empty_data', __('XLSX file is empty.', 'gf-external-choices'));
        }

        return array_map('trim', $rows[0]);
    }

    /**
     * Extract rows from XLSX file.
     *
     * @param string $file_path The path to the XLSX file.
     * @return array|WP_Error Array of rows or error.
     */
    private function extract_rows($file_path)
    {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('no_zip_support', __('PHP ZipArchive extension is required for XLSX support.', 'gf-external-choices'));
        }

        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', __('XLSX file not found.', 'gf-external-choices'));
        }

        $zip = new ZipArchive();
        $result = $zip->open($file_path);

        if (true !== $result) {
            return new WP_Error('zip_open_failed', __('Failed to open XLSX file.', 'gf-external-choices'));
        }

        // Load shared strings (text values are stored here)
        $this->shared_strings = $this->load_shared_strings($zip);

        // Load the first worksheet
        $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if (false === $sheet_xml) {
            return new WP_Error('no_worksheet', __('No worksheet found in XLSX file.', 'gf-external-choices'));
        }

        return $this->parse_sheet($sheet_xml);
    }

    /**
     * Load shared strings from the XLSX file.
     *
     * @param ZipArchive $zip The open ZIP archive.
     * @return array Array of shared strings.
     */
    private function load_shared_strings($zip)
    {
        $strings = array();
        $xml_content = $zip->getFromName('xl/sharedStrings.xml');

        if (false === $xml_content) {
            return $strings;
        }

        // Suppress XML errors and load
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);
        libxml_clear_errors();

        if (false === $xml) {
            return $strings;
        }

        // Register namespace for proper XPath queries
        $xml->registerXPathNamespace('ss', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        foreach ($xml->si as $si) {
            // Handle simple text nodes
            if (isset($si->t)) {
                $strings[] = (string) $si->t;
            }
            // Handle rich text (multiple text runs)
            elseif (isset($si->r)) {
                $text = '';
                foreach ($si->r as $run) {
                    if (isset($run->t)) {
                        $text .= (string) $run->t;
                    }
                }
                $strings[] = $text;
            } else {
                $strings[] = '';
            }
        }

        return $strings;
    }

    /**
     * Parse worksheet XML into rows.
     *
     * @param string $sheet_xml The worksheet XML content.
     * @return array Array of rows.
     */
    private function parse_sheet($sheet_xml)
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($sheet_xml);
        libxml_clear_errors();

        if (false === $xml) {
            return new WP_Error('xml_parse_failed', __('Failed to parse worksheet XML.', 'gf-external-choices'));
        }

        // Register the spreadsheet namespace for proper element access
        $namespaces = $xml->getNamespaces(true);
        if (!empty($namespaces)) {
            // Use the default namespace
            $ns = reset($namespaces);
            $xml->registerXPathNamespace('x', $ns);
        }

        $rows = array();

        // Find all row elements
        if (!isset($xml->sheetData->row)) {
            return $rows;
        }

        foreach ($xml->sheetData->row as $row_element) {
            $row_data = array();
            $max_col = 0;

            foreach ($row_element->c as $cell) {
                $cell_ref = (string) $cell['r']; // e.g., "A1", "B2"
                $col_index = $this->column_to_index($cell_ref);
                $max_col = max($max_col, $col_index);

                // Ensure array has enough elements
                while (count($row_data) <= $col_index) {
                    $row_data[] = '';
                }

                $row_data[$col_index] = $this->get_cell_value($cell);
            }

            $rows[] = $row_data;
        }

        return $rows;
    }

    /**
     * Get the value from a cell element.
     *
     * @param SimpleXMLElement $cell The cell element.
     * @return string The cell value.
     */
    private function get_cell_value($cell)
    {
        $type = (string) $cell['t']; // Cell type attribute

        // Get the cell value - handle namespaced elements
        // XLSX uses default namespace, so we need to get children properly
        $value = '';

        // Try direct access first (works in some PHP/libxml versions)
        if (isset($cell->v)) {
            $value = (string) $cell->v;
        } else {
            // Try with namespace - get the default namespace and use it
            $namespaces = $cell->getNamespaces(true);
            if (!empty($namespaces)) {
                $ns = reset($namespaces);
                $children = $cell->children($ns);
                if (isset($children->v)) {
                    $value = (string) $children->v;
                }
            }
        }

        // Handle different cell types
        switch ($type) {
            case 's': // Shared string
                $index = intval($value);
                return isset($this->shared_strings[$index]) ? $this->shared_strings[$index] : '';

            case 'inlineStr': // Inline string
                // Handle inline string with namespace
                if (isset($cell->is->t)) {
                    return (string) $cell->is->t;
                }
                $namespaces = $cell->getNamespaces(true);
                if (!empty($namespaces)) {
                    $ns = reset($namespaces);
                    $children = $cell->children($ns);
                    if (isset($children->is->t)) {
                        return (string) $children->is->t;
                    }
                }
                return '';

            case 'b': // Boolean
                return $value === '1' ? 'TRUE' : 'FALSE';

            case 'e': // Error
                return '';

            default: // Number or other
                return $value;
        }
    }

    /**
     * Convert Excel column reference to zero-based index.
     *
     * @param string $cell_ref The cell reference (e.g., "A1", "AB5").
     * @return int Zero-based column index.
     */
    private function column_to_index($cell_ref)
    {
        // Extract column letters (remove row number)
        preg_match('/^([A-Z]+)/', $cell_ref, $matches);
        $column = isset($matches[1]) ? $matches[1] : 'A';

        $index = 0;
        $length = strlen($column);

        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($column[$i]) - ord('A') + 1);
        }

        return $index - 1; // Convert to zero-based
    }

    /**
     * Get column index from header.
     *
     * @param array  $header  The header row.
     * @param string $column  The column name or index.
     * @param int    $default Default index if not found.
     * @return int Column index.
     */
    private function get_column_index($header, $column, $default = 0)
    {
        if ('' === $column) {
            return $default;
        }

        // Check if it's a numeric index
        if (is_numeric($column)) {
            $index = intval($column);
            return ($index >= 0 && $index < count($header)) ? $index : $default;
        }

        // Search by column name
        $index = array_search($column, $header, true);
        return (false !== $index) ? $index : $default;
    }
}
