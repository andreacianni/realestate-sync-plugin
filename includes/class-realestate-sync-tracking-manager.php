<?php
/**
 * RealEstate Sync Plugin - Tracking Manager
 * 
 * Gestisce il tracking delle properties per import differenziale
 * e change detection hash-based per performance ottimale.
 *
 * @package RealEstateSync
 * @subpackage Core
 * @since 0.9.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Tracking_Manager {
    
    /**
     * Nome tabella tracking
     */
    const TABLE_NAME = 'realestate_sync_tracking';
    
    /**
     * Versione database schema
     */
    const DB_VERSION = '1.0';
    
    /**
     * Logger instance
     */
    private $logger;
    
    /**
     * WordPress database instance
     */
    private $wpdb;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = RealEstate_Sync_Logger::get_instance();
        
        // Hook per creazione tabella su activation
        add_action('realestate_sync_create_tables', array($this, 'create_tracking_table'));
    }
    
    /**
     * Crea la tabella di tracking per import differenziale
     * 
     * @return bool Success status
     */
    public function create_tracking_table() {
        $table_name = $this->wpdb->prefix . self::TABLE_NAME;
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            property_id int(11) NOT NULL,
            wp_post_id bigint(20) unsigned NULL,
            property_hash varchar(32) NOT NULL,
            data_snapshot longtext NULL,
            last_import_date datetime NOT NULL,
            status enum('active', 'inactive', 'deleted', 'error') DEFAULT 'active',
            province varchar(10) NULL,
            category_id int(11) NULL,
            price decimal(15,2) NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (property_id),
            KEY wp_post_id (wp_post_id),
            KEY status (status),
            KEY province (province),
            KEY last_import_date (last_import_date),
            KEY property_hash (property_hash)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);
        
        // Salva versione DB
        update_option('realestate_sync_db_version', self::DB_VERSION);
        
        $this->logger->log("Tracking table created/updated: " . $table_name, 'info');
        
        return true;
    }
    
    /**
     * Calcola hash MD5 per change detection
     * 
     * @param array $property_data Dati property da XML
     * @return string MD5 hash
     */
    public function calculate_property_hash($property_data) {
        // Campi critici per change detection
        $critical_fields = array(
            'price',
            'mq',
            'description',
            'abstract',
            'latitude',
            'longitude',
            'indirizzo',
            'deleted',
            'categorie_id',
            'age'
        );
        
        $hash_data = array();
        
        // Estrai solo campi critici per hash
        foreach ($critical_fields as $field) {
            $hash_data[$field] = isset($property_data[$field]) ? $property_data[$field] : '';
        }
        
        // Aggiungi features e dati numerici
        if (isset($property_data['features'])) {
            ksort($property_data['features']); // Sort per consistency
            $hash_data['features'] = $property_data['features'];
        }
        
        if (isset($property_data['numeric_data'])) {
            ksort($property_data['numeric_data']); // Sort per consistency
            $hash_data['numeric_data'] = $property_data['numeric_data'];
        }
        
        // Genera hash MD5
        $hash_string = serialize($hash_data);
        return md5($hash_string);
    }
    
    /**
     * Verifica se property esiste nel tracking
     * 
     * @param int $property_id GI Property ID
     * @return array|null Tracking record o null se non esiste
     */
    public function get_tracking_record($property_id) {
        $table_name = $this->wpdb->prefix . self::TABLE_NAME;
        
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $table_name WHERE property_id = %d",
                $property_id
            ),
            ARRAY_A
        );
        
        return $result;
    }
    
    /**
     * Determina se property ha subito modifiche
     * 
     * @param int $property_id GI Property ID
     * @param string $new_hash Nuovo hash calcolato
     * @return array Status array con has_changed e action
     */
    public function check_property_changes($property_id, $new_hash) {
        $existing = $this->get_tracking_record($property_id);
        
        if (!$existing) {
            return array(
                'has_changed' => true,
                'action' => 'insert',
                'reason' => 'new_property'
            );
        }
        
        if ($existing['property_hash'] !== $new_hash) {
            return array(
                'has_changed' => true,
                'action' => 'update',
                'reason' => 'data_changed',
                'wp_post_id' => $existing['wp_post_id']
            );
        }
        
        return array(
            'has_changed' => false,
            'action' => 'skip',
            'reason' => 'no_changes',
            'wp_post_id' => $existing['wp_post_id']
        );
    }
    
    /**
     * Aggiorna o inserisce tracking record
     * 
     * @param int $property_id GI Property ID
     * @param string $property_hash MD5 hash
     * @param int $wp_post_id WordPress Post ID
     * @param array $property_data Dati completi property
     * @param string $status Status (active, inactive, deleted)
     * @return bool Success status
     */
    public function update_tracking_record($property_id, $property_hash, $wp_post_id = null, $property_data = array(), $status = 'active') {
        $table_name = $this->wpdb->prefix . self::TABLE_NAME;
        
        $data = array(
            'property_hash' => $property_hash,
            'last_import_date' => current_time('mysql'),
            'status' => $status
        );
        
        // Aggiungi dati opzionali se forniti
        if ($wp_post_id) {
            $data['wp_post_id'] = $wp_post_id;
        }
        
        if (!empty($property_data)) {
            $data['data_snapshot'] = json_encode($property_data);
            $data['province'] = isset($property_data['provincia']) ? $property_data['provincia'] : null;
            $data['category_id'] = isset($property_data['categorie_id']) ? $property_data['categorie_id'] : null;
            $data['price'] = isset($property_data['price']) ? floatval($property_data['price']) : null;
        }
        
        // Check se record esiste
        $existing = $this->get_tracking_record($property_id);
        
        if ($existing) {
            // UPDATE
            $result = $this->wpdb->update(
                $table_name,
                $data,
                array('property_id' => $property_id),
                array('%s', '%s', '%s', '%d', '%s', '%s', '%d', '%f'),
                array('%d')
            );
        } else {
            // INSERT
            $data['property_id'] = $property_id;
            $result = $this->wpdb->insert(
                $table_name,
                $data,
                array('%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%f')
            );
        }
        
        if ($result === false) {
            $this->logger->log("Error updating tracking record for property $property_id: " . $this->wpdb->last_error, 'error');
            return false;
        }
        
        $this->logger->log("Tracking record updated for property $property_id (status: $status)", 'debug');
        return true;
    }
    
    /**
     * Marca properties come deleted se non presenti nel nuovo import
     * 
     * @param array $imported_property_ids Array di property IDs importati
     * @return int Numero di properties marcate come deleted
     */
    public function mark_missing_properties_deleted($imported_property_ids) {
        if (empty($imported_property_ids)) {
            return 0;
        }
        
        $table_name = $this->wpdb->prefix . self::TABLE_NAME;
        
        // Placeholder per IN clause
        $placeholders = implode(',', array_fill(0, count($imported_property_ids), '%d'));
        
        $sql = "UPDATE $table_name 
                SET status = 'deleted', 
                    updated_date = NOW() 
                WHERE property_id NOT IN ($placeholders) 
                AND status = 'active'";
        
        $result = $this->wpdb->query(
            $this->wpdb->prepare($sql, $imported_property_ids)
        );
        
        if ($result !== false) {
            $this->logger->log("Marked $result properties as deleted (not in current import)", 'info');
        }
        
        return $result;
    }
    
    /**
     * Ottieni statistiche import per admin dashboard
     * 
     * @return array Statistics array
     */
    public function get_import_statistics() {
        $table_name = $this->wpdb->prefix . self::TABLE_NAME;
        
        $stats = array();
        
        // Total properties tracked
        $stats['total_tracked'] = $this->wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // By status
        $status_counts = $this->wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM $table_name GROUP BY status",
            ARRAY_A
        );
        
        foreach ($status_counts as $status) {
            $stats['by_status'][$status['status']] = $status['count'];
        }
        
        // Recent activity (last 7 days)
        $stats['recent_imports'] = $this->wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name 
             WHERE last_import_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // Last import date
        $stats['last_import'] = $this->wpdb->get_var(
            "SELECT MAX(last_import_date) FROM $table_name"
        );
        
        return $stats;
    }
    
    /**
     * Ottieni properties che necessitano WordPress post deletion
     * 
     * @return array Array di wp_post_ids da eliminare
     */
    public function get_posts_to_delete() {
        $table_name = $this->wpdb->prefix . self::TABLE_NAME;
        
        $post_ids = $this->wpdb->get_col(
            "SELECT wp_post_id FROM $table_name 
             WHERE status = 'deleted' 
             AND wp_post_id IS NOT NULL"
        );
        
        return array_map('intval', $post_ids);
    }
    
    /**
     * Get property tracking record by XML property ID
     * 
     * @param int $property_id XML Property ID from GestionaleImmobiliare
     * @return array|null Tracking record with wp_post_id or null if not found
     */
    public function get_property_tracking($property_id) {
        $table_name = $this->wpdb->prefix . self::TABLE_NAME;
        
        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT property_id, wp_post_id, status, last_import_date FROM $table_name WHERE property_id = %d",
                $property_id
            ),
            ARRAY_A
        );
        
        return $result;
    }
    
    /**
     * Pulisci tracking records vecchi (older than X days)
     * 
     * @param int $days Giorni di retention
     * @return int Numero di records eliminati
     */
    public function cleanup_old_tracking_records($days = 90) {
        $table_name = $this->wpdb->prefix . self::TABLE_NAME;
        
        $result = $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM $table_name 
                 WHERE status = 'deleted' 
                 AND updated_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
        
        if ($result !== false) {
            $this->logger->log("Cleaned up $result old tracking records (older than $days days)", 'info');
        }
        
        return $result;
    }
}
