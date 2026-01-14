<?php
/**
 * Verification test script for Gravity Forms External Choices Plugin.
 * Run this in a WordPress environment with the plugin activated.
 */

class GF_External_Choices_Verifier {
    public function run() {
        echo "Starting verification...\n";
        
        $this->verify_caching();
        $this->verify_csv_parsing();
        $this->verify_json_parsing();
        $this->verify_max_file_size_limit();
        
        echo "\nVerification complete.\n";
    }
    
    // Verify Caching Logic
    private function verify_caching() {
        echo "[Test] Caching Logic: ";
        $cache_manager = new GF_External_Choices_Cache_Manager();
        $test_url = 'https://example.com/test-data.csv';
        $test_data = [['text' => 'A', 'value' => '1']];
        
        // Clear old cache
        $cache_manager->clear($test_url);
        
        // Set new cache
        $cache_manager->set($test_url, $test_data);
        
        // Retrieve cache
        $result = $cache_manager->get($test_url);
        
        if ($result === $test_data) {
            echo "PASS\n";
        } else {
            echo "FAIL (Cache retrieval failed)\n";
        }
    }
    
    // Verify CSV Parsing
    private function verify_csv_parsing() {
        echo "[Test] CSV Parsing: ";
        $parser = new GF_External_Choices_CSV_Parser();
        
        // Test 1: Standard CSV
        $csv_data = "label,value\nChoice A,1\nChoice B,2";
        $results = $parser->parse($csv_data, 'label', 'value');
        
        if (count($results) === 2 && $results[0]['text'] === 'Choice A') {
            echo "PASS\n";
        } else {
            echo "FAIL (Standard parsing)\n";
        }
        
        // Test 2: Semicolon Delimiter
        echo "[Test] CSV Semicolon Delimiter: ";
        $csv_semi = "label;value\nChoice A;1\nChoice B;2";
        $results_semi = $parser->parse($csv_semi, 'label', 'value');
        
        if (count($results_semi) === 2 && $results_semi[0]['text'] === 'Choice A') {
            echo "PASS\n";
        } else {
            echo "FAIL (Delimiter detection)\n";
        }
    }
    
    // Verify JSON Parsing
    private function verify_json_parsing() {
        echo "[Test] JSON Parsing: ";
        $parser = new GF_External_Choices_JSON_Parser();
        $json_data = '[{"name": "Choice A", "id": "1"}, {"name": "Choice B", "id": "2"}]';
        
        $results = $parser->parse($json_data, 'name', 'id');
        
        if (count($results) === 2 && $results[0]['text'] === 'Choice A') {
            echo "PASS\n";
        } else {
            echo "FAIL (JSON parsing)\n";
        }
        
        // Test Nested JSON (should fail)
        echo "[Test] Nested JSON Rejection: ";
        $nested_json = '[{"person": {"name": "Choice A"}}]';
        $nested_results = $parser->parse($nested_json, 'name', 'id');
        
        if (is_wp_error($nested_results)) {
            echo "PASS\n";
        } else {
            echo "FAIL (Nested JSON should be rejected)\n";
        }
    }

    private function verify_max_file_size_limit() {
        echo "[Test] Max File Size Limit: ";
        // Mock large data
        $large_data = str_repeat("a", 10 * 1024 * 1024 + 100); // > 10MB
        $fetcher = new GF_External_Choices_Data_Fetcher();

        // Reflection to test protected logic or simulate huge response would be needed in full unit tests.
        // For this script, we'll verify the constant is correct.
        
        if (GF_External_Choices_Data_Fetcher::MAX_FILE_SIZE === 10485760) {
             echo "PASS (Constant is 10MB)\n";
        } else {
             echo "FAIL (Incorrect size limit constant)\n";
        }
    }

}

// To run:
// $verifier = new GF_External_Choices_Verifier();
// $verifier->run();
