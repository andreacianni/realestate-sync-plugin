<?php
/**
 * RealEstate Sync Plugin - XML Downloader
 * 
 * Gestisce il download autenticato del file XML da GestionaleImmobiliare.it
 * con decompressione automatica e gestione errori.
 *
 * @package RealEstateSync
 * @subpackage Core
 * @since 0.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_XML_Downloader {
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * Download configuration
     */
    private $config;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = RealEstate_Sync_Logger::get_instance();
        $this->init_config();
    }
    
    /**
     * Initialize configuration
     */
    private function init_config() {
        $this->config = array(
            'timeout' => 300, // 5 minutes
            'user_agent' => 'RealEstate-Sync-Plugin/0.9.0',
            'temp_dir' => wp_upload_dir()['basedir'] . '/realestate-sync-temp/',
            'max_file_size' => 500 * 1024 * 1024, // 500MB
            'verify_ssl' => true
        );
        
        // Create temp directory if not exists
        if (!file_exists($this->config['temp_dir'])) {
            wp_mkdir_p($this->config['temp_dir']);
        }
    }
    
    /**
     * Download XML file with authentication
     * 
     * @param string $url XML download URL
     * @param string $username Authentication username
     * @param string $password Authentication password
     * @return string|false Path to downloaded XML file or false on failure
     */
    public function download_xml($url, $username, $password) {
        $this->logger->log("Starting XML download from: $url", 'info');
        
        try {
            // Prepare download
            $temp_file = $this->config['temp_dir'] . 'xml_download_' . time() . '.tar.gz';
            
            // Setup cURL
            $ch = curl_init();
            curl_setopt_array($ch, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FILE => fopen($temp_file, 'w'),
                CURLOPT_TIMEOUT => $this->config['timeout'],
                CURLOPT_USERAGENT => $this->config['user_agent'],
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $username . ':' . $password,
                CURLOPT_SSL_VERIFYPEER => $this->config['verify_ssl'],
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_PROGRESSFUNCTION => array($this, 'download_progress_callback'),
                CURLOPT_NOPROGRESS => false
            ));
            
            // Execute download
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            curl_close($ch);
            
            if ($result === false || $http_code !== 200) {
                throw new Exception("Download failed: HTTP $http_code - $error");
            }
            
            // Verify file size
            $file_size = filesize($temp_file);
            if ($file_size === false || $file_size === 0) {
                throw new Exception("Downloaded file is empty");
            }
            
            if ($file_size > $this->config['max_file_size']) {
                throw new Exception("Downloaded file is too large: " . size_format($file_size));
            }
            
            $this->logger->log("XML file downloaded successfully: " . size_format($file_size), 'info');
            
            // Extract if compressed
            if (pathinfo($temp_file, PATHINFO_EXTENSION) === 'gz') {
                return $this->extract_xml_file($temp_file);
            }
            
            return $temp_file;
            
        } catch (Exception $e) {
            $this->logger->log("XML download failed: " . $e->getMessage(), 'error');
            
            // Cleanup on error
            if (isset($temp_file) && file_exists($temp_file)) {
                unlink($temp_file);
            }
            
            return false;
        }
    }
    
    /**
     * Extract XML from compressed file
     * 
     * @param string $compressed_file Path to compressed file
     * @return string|false Path to extracted XML file
     */
    private function extract_xml_file($compressed_file) {
        try {
            $xml_file = $this->config['temp_dir'] . 'extracted_' . time() . '.xml';
            
            // Try different extraction methods
            if (class_exists('PharData')) {
                // Use PharData for .tar.gz files
                $phar = new PharData($compressed_file);
                $phar->extractTo($this->config['temp_dir'], null, true);
                
                // Find XML file in extracted content
                $extracted_files = glob($this->config['temp_dir'] . '*.xml');
                if (!empty($extracted_files)) {
                    $xml_file = $extracted_files[0];
                }
                
            } elseif (function_exists('gzopen')) {
                // Fallback to gzopen for simple .gz files
                $gz_handle = gzopen($compressed_file, 'rb');
                $xml_handle = fopen($xml_file, 'wb');
                
                if ($gz_handle && $xml_handle) {
                    while (!gzeof($gz_handle)) {
                        fwrite($xml_handle, gzread($gz_handle, 4096));
                    }
                    gzclose($gz_handle);
                    fclose($xml_handle);
                } else {
                    throw new Exception("Failed to open compressed file");
                }
                
            } else {
                throw new Exception("No extraction method available");
            }
            
            // Verify extracted file
            if (!file_exists($xml_file) || filesize($xml_file) === 0) {
                throw new Exception("XML extraction failed");
            }
            
            // Cleanup compressed file
            unlink($compressed_file);
            
            $this->logger->log("XML file extracted successfully: " . size_format(filesize($xml_file)), 'info');
            
            return $xml_file;
            
        } catch (Exception $e) {
            $this->logger->log("XML extraction failed: " . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Download progress callback
     */
    public function download_progress_callback($resource, $download_size, $downloaded, $upload_size, $uploaded) {
        if ($download_size > 0) {
            $percent = round(($downloaded / $download_size) * 100);
            
            // Log progress every 10%
            if ($percent % 10 === 0 && $percent > 0) {
                $this->logger->log("Download progress: {$percent}% (" . size_format($downloaded) . "/" . size_format($download_size) . ")", 'debug');
            }
        }
    }
    
    /**
     * Test XML URL connectivity
     * 
     * @param string $url XML URL
     * @param string $username Username
     * @param string $password Password
     * @return array Test result
     */
    public function test_connection($url, $username, $password) {
        $this->logger->log("Testing XML connection: $url", 'info');
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true, // HEAD request only
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => $this->config['user_agent'],
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $username . ':' . $password,
            CURLOPT_SSL_VERIFYPEER => $this->config['verify_ssl']
        ));
        
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $content_length = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        
        curl_close($ch);
        
        $result = array(
            'success' => $http_code === 200,
            'http_code' => $http_code,
            'error' => $error,
            'content_type' => $content_type,
            'content_length' => $content_length,
            'content_length_formatted' => $content_length > 0 ? size_format($content_length) : 'Unknown'
        );
        
        if ($result['success']) {
            $this->logger->log("XML connection test successful", 'info');
        } else {
            $this->logger->log("XML connection test failed: HTTP {$http_code} - {$error}", 'error');
        }
        
        return $result;
    }
    
    /**
     * Cleanup old download files
     * 
     * @param int $hours Age in hours
     * @return int Number of files cleaned
     */
    public function cleanup_old_files($hours = 24) {
        $cutoff_time = time() - ($hours * 3600);
        $cleaned = 0;
        
        $files = glob($this->config['temp_dir'] . '*');
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                unlink($file);
                $cleaned++;
            }
        }
        
        if ($cleaned > 0) {
            $this->logger->log("Cleaned up $cleaned old download files", 'info');
        }
        
        return $cleaned;
    }
}
