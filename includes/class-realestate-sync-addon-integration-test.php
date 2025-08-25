<?php
/**
 * RealEstate Sync Add-On Integration Test Script
 * 
 * Test script per validare l'integrazione Add-On completa
 * Verifica tutte le funzionalità prima del deployment
 * 
 * @package RealEstate_Sync
 * @subpackage Testing
 * @version 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_AddOn_Integration_Test {
    
    private $logger;
    private $test_results;
    
    public function __construct() {
        $this->logger = new RealEstate_Sync_Logger();
        $this->test_results = [
            'overall_status' => 'running',
            'timestamp' => current_time('mysql'),
            'tests_run' => 0,
            'tests_passed' => 0,
            'tests_failed' => 0,
            'detailed_results' => []
        ];
    }
    
    /**
     * Run complete Add-On integration test suite
     * 
     * @return array Test results
     */
    public function run_complete_test_suite() {
        $this->logger->log("🧪 Starting Add-On Integration Test Suite", 'info');
        
        try {
            // Test 1: File Existence
            $this->test_addon_files_existence();
            
            // Test 2: Class Loading
            $this->test_addon_class_loading();
            
            // Test 3: Adapter Functionality
            $this->test_addon_adapter();
            
            // Test 4: Importer Components
            $this->test_addon_importer_components();
            
            // Test 5: XML Data Conversion
            $this->test_xml_data_conversion();
            
            // Test 6: WordPress Integration
            $this->test_wordpress_integration();
            
            // Final results
            $this->finalize_test_results();
            
        } catch (Exception $e) {
            $this->record_test_result('CRITICAL_ERROR', false, "Test suite failed: " . $e->getMessage());
            $this->test_results['overall_status'] = 'failed';
        }
        
        $this->logger->log("🧪 Add-On Integration Test Suite completed", 'info');
        return $this->test_results;
    }
    
    /**
     * Test 1: Verify all Add-On files exist
     */
    private function test_addon_files_existence() {
        $required_files = [
            'class-realestate-sync-addon-adapter.php',
            'class-realestate-sync-addon-importer-wrapper.php',
            'addon-integration/class-addon-main.php',
            'addon-integration/class-addon-helper.php',
            'addon-integration/class-addon-importer.php',
            'addon-integration/class-addon-importer-properties.php',
            'addon-integration/class-addon-importer-location.php',
            'addon-integration/class-addon-importer-agents.php',
            'addon-integration/class-addon-field-factory-properties.php',
            'addon-integration/class-addon-field-factory-agents.php',
            'addon-integration/class-addon-rapid-addon.php'
        ];
        
        $plugin_includes = plugin_dir_path(__FILE__);
        $missing_files = [];
        
        foreach ($required_files as $file) {
            $file_path = $plugin_includes . $file;
            if (!file_exists($file_path)) {
                $missing_files[] = $file;
            }
        }
        
        if (empty($missing_files)) {
            $this->record_test_result('FILES_EXISTENCE', true, "All " . count($required_files) . " Add-On files found");
        } else {
            $this->record_test_result('FILES_EXISTENCE', false, "Missing files: " . implode(', ', $missing_files));
        }
    }
    
    private function get_sample_xml_property() {
        return [
            'id' => '12345',
            'tipologia' => '11', // Appartamento
            'contratto' => 'vendita',
            'prezzo' => '250000',
            'comune' => 'Trento',
            'provincia' => 'TN',
            'indirizzo' => 'Via Roma 123',
            'cap' => '38122',
            'latitude' => '46.0678',
            'longitude' => '11.1210',
            'superficie_commerciale' => '85',
            'classe_energetica' => 'b',
            'anno_costruzione' => '1980',
            'piano' => '2',
            'totale_piani' => '4',
            'descrizione' => 'Bellissimo appartamento in centro storico',
            'caratteristiche' => [
                ['id' => 2, 'valore' => '3'],  // Bedrooms
                ['id' => 1, 'valore' => '2'],  // Bathrooms
                ['id' => 17, 'valore' => '1'], // Giardino
                ['id' => 13, 'valore' => '1'], // Ascensore
                ['id' => 15, 'valore' => '1']  // Arredato
            ],
            'agency_data' => [
                'id' => '100',
                'ragione_sociale' => 'Immobiliare Test SRL',
                'email' => 'info@immobiliaretest.it',
                'telefono' => '0461123456',
                'indirizzo' => 'Via Verdi 45',
                'comune' => 'Trento',
                'provincia' => 'TN'
            ],
            'file_allegati' => [
                [
                    'id' => '1',
                    'type' => 'foto',
                    'url' => 'https://www.example.com/images/property1.jpg'
                ],
                [
                    'id' => '2', 
                    'type' => 'foto',
                    'url' => 'https://www.example.com/images/property2.jpg'
                ]
            ]
        ];
    }
    
    private function record_test_result($test_name, $passed, $message) {
        $this->test_results['tests_run']++;
        
        if ($passed) {
            $this->test_results['tests_passed']++;
            $status = 'PASSED';
        } else {
            $this->test_results['tests_failed']++;
            $status = 'FAILED';
        }
        
        $this->test_results['detailed_results'][] = [
            'test' => $test_name,
            'status' => $status,
            'message' => $message,
            'timestamp' => current_time('mysql')
        ];
        
        $this->logger->log("🧪 Test {$test_name}: {$status} - {$message}", $passed ? 'info' : 'warning');
    }
}

/**
 * Quick test runner function for WordPress admin
 */
function run_addon_integration_test() {
    $test_runner = new RealEstate_Sync_AddOn_Integration_Test();
    $results = $test_runner->run_complete_test_suite();
    
    return $results;
}
