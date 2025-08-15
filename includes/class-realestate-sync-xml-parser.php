<?php
/**
 * RealEstate Sync Plugin - Streaming XML Parser
 * 
 * XMLReader-based streaming parser per gestire file XML grandi (264MB+)
 * senza max execution time. Utilizza chunked processing e memory management
 * ottimizzato per import production-ready.
 *
 * @package RealEstateSync
 * @subpackage Core
 * @since 0.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_XML_Parser {
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * XMLReader instance
     */
    private $reader;
    
    /**
     * Configurazione chunk processing
     */
    private $chunk_size = 25; // Properties per chunk (conservative)
    private $sleep_seconds = 1; // Pausa tra chunks
    private $max_memory_mb = 256; // Memory limit warning
    
    /**
     * Progress tracking
     */
    private $total_processed = 0;
    private $chunk_processed = 0;
    private $start_time;
    private $last_checkpoint;
    
    /**
     * Error handling
     */
    private $max_errors = 10;
    private $error_count = 0;
    
    /**
     * Callback functions per processing
     */
    private $property_callback;
    private $chunk_callback;
    private $progress_callback;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = RealEstate_Sync_Logger::get_instance();
        $this->start_time = microtime(true);
        $this->last_checkpoint = $this->start_time;
    }
    
    /**
     * Configura parametri chunk processing
     * 
     * @param array $config Configuration array
     */
    public function configure($config = array()) {
        if (isset($config['chunk_size'])) {
            $this->chunk_size = max(1, intval($config['chunk_size']));
        }
        
        if (isset($config['sleep_seconds'])) {
            $this->sleep_seconds = max(0, intval($config['sleep_seconds']));
        }
        
        if (isset($config['max_memory_mb'])) {
            $this->max_memory_mb = max(64, intval($config['max_memory_mb']));
        }
        
        if (isset($config['max_errors'])) {
            $this->max_errors = max(1, intval($config['max_errors']));
        }
        
        $this->logger->log("Streaming parser configured: chunk_size={$this->chunk_size}, sleep={$this->sleep_seconds}s, max_memory={$this->max_memory_mb}MB", 'info');
    }
    
    /**
     * Imposta callback per processing properties
     * 
     * @param callable $callback Function(property_data, property_index)
     */
    public function set_property_callback($callback) {
        $this->property_callback = $callback;
    }
    
    /**
     * Imposta callback per fine chunk
     * 
     * @param callable $callback Function(chunk_data, chunk_index)
     */
    public function set_chunk_callback($callback) {
        $this->chunk_callback = $callback;
    }
    
    /**
     * Imposta callback per progress updates
     * 
     * @param callable $callback Function(progress_data)
     */
    public function set_progress_callback($callback) {
        $this->progress_callback = $callback;
    }
    
    /**
     * Parse XML file con streaming e chunked processing
     * 
     * @param string $xml_file_path Path al file XML
     * @return array Results summary
     */
    public function parse_xml_file($xml_file_path) {
        if (!file_exists($xml_file_path)) {
            throw new Exception("XML file not found: $xml_file_path");
        }
        
        $this->logger->log("Starting streaming XML parse: $xml_file_path", 'info');
        
        // Initialize XMLReader
        $this->reader = new XMLReader();
        if (!$this->reader->open($xml_file_path)) {
            throw new Exception("Cannot open XML file: $xml_file_path");
        }
        
        $results = array(
            'total_processed' => 0,
            'chunks_processed' => 0,
            'errors' => 0,
            'start_time' => $this->start_time,
            'end_time' => null,
            'duration' => null,
            'memory_peak' => 0
        );
        
        try {
            // Find dataset root
            $this->move_to_element('dataset');
            
            // Process annunci in chunks
            $chunk_data = array();
            $chunk_index = 0;
            
            while ($this->reader->read()) {
                if ($this->reader->nodeType === XMLReader::ELEMENT && $this->reader->localName === 'annuncio') {
                    
                    try {
                        // Parse single property
                        $property_data = $this->parse_single_property();
                        
                        if ($property_data) {
                            $chunk_data[] = $property_data;
                            $this->total_processed++;
                            
                            // Call property callback if set
                            if ($this->property_callback) {
                                call_user_func($this->property_callback, $property_data, $this->total_processed);
                            }
                            
                            // Check se chunk è completo
                            if (count($chunk_data) >= $this->chunk_size) {
                                $this->process_chunk($chunk_data, $chunk_index);
                                $chunk_data = array(); // Reset chunk
                                $chunk_index++;
                                
                                // Memory e performance management
                                $this->manage_resources();
                            }
                        }
                        
                    } catch (Exception $e) {
                        $this->error_count++;
                        $this->logger->log("Error parsing property at position {$this->total_processed}: " . $e->getMessage(), 'error');
                        
                        if ($this->error_count >= $this->max_errors) {
                            throw new Exception("Too many parsing errors ({$this->error_count}), aborting");
                        }
                    }
                }
            }
            
            // Process ultimo chunk se non vuoto
            if (!empty($chunk_data)) {
                $this->process_chunk($chunk_data, $chunk_index);
                $chunk_index++;
            }
            
            // Final results
            $results['total_processed'] = $this->total_processed;
            $results['chunks_processed'] = $chunk_index;
            $results['errors'] = $this->error_count;
            $results['end_time'] = microtime(true);
            $results['duration'] = $results['end_time'] - $results['start_time'];
            $results['memory_peak'] = memory_get_peak_usage(true) / 1024 / 1024; // MB
            
            $this->logger->log("Streaming parse completed: {$results['total_processed']} properties in {$results['chunks_processed']} chunks", 'info');
            
        } finally {
            $this->reader->close();
        }
        
        return $results;
    }
    
    /**
     * Parse singola property da XMLReader position
     * 
     * @return array|null Property data o null se errore
     */
    private function parse_single_property() {
        if ($this->reader->localName !== 'annuncio') {
            return null;
        }
        
        // Leggi tutto il nodo annuncio
        $annuncio_xml = $this->reader->readOuterXML();
        
        if (!$annuncio_xml) {
            return null;
        }
        
        // Parse con DOMDocument per singolo annuncio (efficiente)
        $dom = new DOMDocument();
        if (!$dom->loadXML($annuncio_xml)) {
            return null;
        }
        
        $xpath = new DOMXPath($dom);
        $property_data = array();
        
        // Parse dati base da <info>
        $info_nodes = $xpath->query('//info');
        if ($info_nodes->length > 0) {
            $info = $info_nodes->item(0);
            
            foreach ($info->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $property_data[$child->nodeName] = trim($child->textContent);
                }
            }
        }
        
        // Parse features da <info_inserite>
        $features = array();
        $feature_nodes = $xpath->query('//info_inserite/info');
        foreach ($feature_nodes as $feature) {
            $feature_id = $feature->getAttribute('id');
            $feature_value = $xpath->query('valore_assegnato', $feature)->item(0);
            if ($feature_value) {
                $features[$feature_id] = trim($feature_value->textContent);
            }
        }
        $property_data['features'] = $features;
        
        // Parse dati numerici da <dati_inseriti>
        $numeric_data = array();
        $data_nodes = $xpath->query('//dati_inseriti/dati');
        foreach ($data_nodes as $data) {
            $data_id = $data->getAttribute('id');
            $data_value = $xpath->query('valore_assegnato', $data)->item(0);
            if ($data_value) {
                $numeric_data[$data_id] = trim($data_value->textContent);
            }
        }
        $property_data['numeric_data'] = $numeric_data;
        
        // Parse media files da <file_allegati>
        $media_files = array();
        $media_nodes = $xpath->query('//file_allegati/allegato');
        foreach ($media_nodes as $media) {
            $media_id = $media->getAttribute('id');
            $media_type = $media->getAttribute('type');
            $file_path = $xpath->query('file_path', $media)->item(0);
            if ($file_path) {
                $media_files[] = array(
                    'id' => $media_id,
                    'type' => $media_type,
                    'url' => trim($file_path->textContent)
                );
            }
        }
        $property_data['media_files'] = $media_files;
        
        // 🏢 ENHANCED: Parse agency data da <agenzia>
        $agency_data = null;
        $agenzia_nodes = $xpath->query('agenzia'); // Fixed XPath - direct child
        if ($agenzia_nodes->length > 0) {
            $agenzia = $agenzia_nodes->item(0);
            $agency_data = array();
            
            foreach ($agenzia->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $agency_data[$child->nodeName] = trim($child->textContent);
                }
            }
            
            // Extract attributes from comune if present
            $comune_node = $xpath->query('comune', $agenzia)->item(0);
            if ($comune_node && $comune_node->hasAttribute('istat')) {
                $agency_data['comune_istat'] = $comune_node->getAttribute('istat');
            }
            
            // Only include agency if has valid ID
            if (!empty($agency_data['id']) && intval($agency_data['id']) > 0) {
                $property_data['agency_data'] = $agency_data;
                
                // Debug log successful extraction
                error_log("🏢 XML PARSER: Agency data extracted successfully - ID: {$agency_data['id']}, Name: " . ($agency_data['ragione_sociale'] ?? 'Unknown'));
            } else {
                error_log("🏢 XML PARSER: Agency found but invalid ID or missing data");
            }
        } else {
            error_log("🏢 XML PARSER: No <agenzia> section found in this annuncio");
        }
        
        // Validate required fields
        if (!isset($property_data['id']) || empty($property_data['id'])) {
            return null; // Skip properties senza ID
        }
        
        return $property_data;
    }
    
    /**
     * Processa chunk di properties
     * 
     * @param array $chunk_data Array di property data
     * @param int $chunk_index Indice chunk
     */
    private function process_chunk($chunk_data, $chunk_index) {
        $chunk_size = count($chunk_data);
        $this->logger->log("Processing chunk $chunk_index with $chunk_size properties", 'debug');
        
        // Call chunk callback se impostato
        if ($this->chunk_callback) {
            call_user_func($this->chunk_callback, $chunk_data, $chunk_index);
        }
        
        // Progress update
        $this->update_progress($chunk_index, $chunk_size);
        
        // Sleep per evitare server overload
        if ($this->sleep_seconds > 0) {
            sleep($this->sleep_seconds);
        }
    }
    
    /**
     * Update progress e call progress callback
     * 
     * @param int $chunk_index Current chunk
     * @param int $chunk_size Size of current chunk
     */
    private function update_progress($chunk_index, $chunk_size) {
        $current_time = microtime(true);
        $elapsed = $current_time - $this->start_time;
        $chunk_elapsed = $current_time - $this->last_checkpoint;
        
        $progress_data = array(
            'total_processed' => $this->total_processed,
            'current_chunk' => $chunk_index,
            'chunk_size' => $chunk_size,
            'elapsed_seconds' => $elapsed,
            'chunk_duration' => $chunk_elapsed,
            'properties_per_second' => $chunk_size / max($chunk_elapsed, 0.1),
            'memory_usage_mb' => memory_get_usage(true) / 1024 / 1024,
            'errors' => $this->error_count
        );
        
        // Call progress callback
        if ($this->progress_callback) {
            call_user_func($this->progress_callback, $progress_data);
        }
        
        $this->last_checkpoint = $current_time;
    }
    
    /**
     * Memory e resource management
     */
    private function manage_resources() {
        $memory_mb = memory_get_usage(true) / 1024 / 1024;
        
        if ($memory_mb > $this->max_memory_mb) {
            $this->logger->log("High memory usage detected: {$memory_mb}MB", 'warning');
            
            // Force garbage collection
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
            
            // Reduce chunk size se memoria troppo alta
            if ($memory_mb > $this->max_memory_mb * 1.5) {
                $this->chunk_size = max(5, intval($this->chunk_size * 0.8));
                $this->logger->log("Reduced chunk size to {$this->chunk_size} due to memory pressure", 'info');
            }
        }
        
        // Check execution time (conservative approach)
        $elapsed = microtime(true) - $this->start_time;
        if ($elapsed > 600) { // 10 minutes warning
            $this->logger->log("Long running process detected: {$elapsed}s elapsed", 'warning');
        }
    }
    
    /**
     * Move XMLReader to specific element
     * 
     * @param string $element_name Nome elemento da trovare
     * @return bool Success
     */
    private function move_to_element($element_name) {
        while ($this->reader->read()) {
            if ($this->reader->nodeType === XMLReader::ELEMENT && $this->reader->localName === $element_name) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Ottieni statistics parsing corrente
     * 
     * @return array Current stats
     */
    public function get_current_stats() {
        $current_time = microtime(true);
        $elapsed = $current_time - $this->start_time;
        
        return array(
            'total_processed' => $this->total_processed,
            'elapsed_seconds' => $elapsed,
            'properties_per_second' => $this->total_processed / max($elapsed, 0.1),
            'memory_usage_mb' => memory_get_usage(true) / 1024 / 1024,
            'errors' => $this->error_count,
            'chunk_size' => $this->chunk_size
        );
    }
    
    /**
     * Cleanup resources
     */
    public function cleanup() {
        if ($this->reader) {
            $this->reader->close();
        }
        
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->cleanup();
    }
}
