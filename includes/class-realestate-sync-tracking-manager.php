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
     * Nome tabella tracking agencies
     */
    const AGENCY_TABLE_NAME = 'realestate_sync_agency_tracking';

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
     * Default functional session metrics.
     *
     * @return array
     */
    public static function get_functional_stats_defaults() {
        return array(
            'created_new' => 0,
            'business_updates' => 0,
            'technical_updates' => 0,
            'self_healing_updates' => 0,
            'media_updates' => 0,
            'text_format_only_updates' => 0,
            'last_editor_time_only_updates' => 0,
            'deleted_properties' => 0,
            'deleted_agencies' => 0,
            'media_deleted_physical' => 0,
            'media_added' => 0,
            'media_removed_from_gallery' => 0,
        );
    }

    /**
     * Merge functional stats arrays safely.
     *
     * @param array $base Base stats.
     * @param array $addition Stats to add.
     * @return array
     */
    public static function merge_functional_stats(array $base, array $addition) {
        $merged = self::get_functional_stats_defaults();

        foreach (array($base, $addition) as $stats) {
            foreach ($stats as $key => $value) {
                if (!array_key_exists($key, $merged)) {
                    continue;
                }
                $merged[$key] += (int) $value;
            }
        }

        return $merged;
    }

    /**
     * Increment a functional stat in place.
     *
     * @param array  $stats Stats array.
     * @param string $key Stat key.
     * @param int    $amount Increment amount.
     * @return void
     */
    public static function increment_functional_stat(array &$stats, $key, $amount = 1) {
        if (!array_key_exists($key, $stats)) {
            $stats[$key] = 0;
        }

        $stats[$key] += (int) $amount;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->logger = RealEstate_Sync_Logger::get_instance();

        // Hook per creazione tabella su activation
        add_action('realestate_sync_create_tables', array($this, 'create_tracking_table'));

        // Hook per cleanup automatico quando property viene cancellata dal backend
        add_action('before_delete_post', array($this, 'cleanup_tracking_on_delete'), 10, 2);
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
     * UPDATED: Calcola hash su TUTTI i campi per rilevare qualsiasi modifica
     * Risolve issue con info_inserite fields non tracciati
     * 
     * @param array $property_data Dati property da XML
     * @return string MD5 hash
     */
    public function calculate_property_hash($property_data) {
        // HASH SU TUTTI I CAMPI - Risolve change detection failed
        $hash_data = $property_data;

        // 🔧 FIX BUG #1: Exclude agency_data from property hash calculation
        // Agency data should NOT affect property hash (agency changes shouldn't trigger property updates)
        unset($hash_data['agency_data']);
        unset($hash_data['agency_id']);  // Also exclude agency_id reference

        $tracker = RealEstate_Sync_Debug_Tracker::get_instance();

        // Normalizza array per consistency hash
        if (isset($hash_data['features']) && is_array($hash_data['features'])) {
            ksort($hash_data['features']);
        }

        if (isset($hash_data['numeric_data']) && is_array($hash_data['numeric_data'])) {
            ksort($hash_data['numeric_data']);
        }

        if (isset($hash_data['info_inserite']) && is_array($hash_data['info_inserite'])) {
            ksort($hash_data['info_inserite']);
        }

        if (isset($hash_data['dati_inseriti']) && is_array($hash_data['dati_inseriti'])) {
            ksort($hash_data['dati_inseriti']);
        }

        // Genera hash MD5 su tutti i dati
        $hash_string = serialize($hash_data);
        $hash = md5($hash_string);

        return $hash;
    }

    /**
     * Normalize a property import ID to its canonical string form.
     *
     * Keeps alphanumeric IDs unchanged, strips whitespace, and only collapses
     * numeric IDs with a trailing ".000000" suffix.
     *
     * @param mixed $property_id Raw property import ID.
     * @return string
     */
    public static function normalize_property_id($property_id) {
        $property_id = trim((string) $property_id);

        if ($property_id === '') {
            return '';
        }

        if (preg_match('/^\d+\.0+$/', $property_id)) {
            return preg_replace('/\.0+$/', '', $property_id);
        }

        return $property_id;
    }
    
    /**
     * Verifica se property esiste nel tracking
     * 
     * @param int $property_id GI Property ID
     * @return array|null Tracking record o null se non esiste
     */
    public function get_tracking_record($property_id) {
        $table_name = $this->wpdb->prefix . self::TABLE_NAME;
        $property_id = self::normalize_property_id($property_id);

        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $table_name WHERE property_id = %s",
                $property_id
            ),
            ARRAY_A
        );

        if (!$result && preg_match('/^\d+$/', $property_id)) {
            $legacy_property_id = $property_id . '.000000';
            $result = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    "SELECT * FROM $table_name WHERE property_id = %s",
                    $legacy_property_id
                ),
                ARRAY_A
            );
        }

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
        $property_id = self::normalize_property_id($property_id);
        
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
        
        // 🔧 FIX PRIORITÀ 4: Build format array dynamically based on actual fields in $data
        $formats = array();
        foreach (array_keys($data) as $key) {
            switch ($key) {
                case 'wp_post_id':
                case 'category_id':
                    $formats[] = '%d';  // Integer
                    break;
                case 'price':
                    $formats[] = '%f';  // Float
                    break;
                default:
                    $formats[] = '%s';  // String (property_hash, last_import_date, status, data_snapshot, province)
                    break;
            }
        }

        // Check se record esiste
        $existing = $this->get_tracking_record($property_id);
        $existing_property_id = $existing && isset($existing['property_id']) ? (string) $existing['property_id'] : $property_id;

        if ($existing) {
            if ($existing_property_id !== $property_id) {
                $data['property_id'] = $property_id;
                $formats[] = '%s';
            }

            // UPDATE
            $result = $this->wpdb->update(
                $table_name,
                $data,
                array('property_id' => $existing_property_id),
                $formats,      // ✅ Dynamic format specifiers
                array('%s')    // 🔧 FIX: property_id is VARCHAR(50), not INT!
            );
        } else {
            // INSERT - add property_id to data and formats
            $data['property_id'] = $property_id;
            $formats = array_merge(array('%s'), $formats);  // 🔧 FIX: property_id is VARCHAR(50)

            $result = $this->wpdb->insert(
                $table_name,
                $data,
                $formats      // ✅ Dynamic format specifiers
            );
        }
        
        if ($result === false) {
            $this->logger->log("Error updating tracking record for property $property_id: " . $this->wpdb->last_error, 'error');

            // 🔍 DEBUG: Log failure details
            $tracker = RealEstate_Sync_Debug_Tracker::get_instance();
            $tracker->log_event('ERROR', 'TRACKING_MANAGER', 'Failed to save property tracking record', array(
                'property_id' => $property_id,
                'property_hash' => $property_hash,
                'wp_post_id' => $wp_post_id,
                'wpdb_error' => $this->wpdb->last_error,
                'operation' => $existing ? 'UPDATE' : 'INSERT'
            ));

            return false;
        }

        // 🔍 DEBUG: Log successful save
        $tracker = RealEstate_Sync_Debug_Tracker::get_instance();
        $tracker->log_event('DEBUG', 'TRACKING_MANAGER', 'Property tracking record saved', array(
            'property_id' => $property_id,
            'property_hash' => $property_hash,
            'wp_post_id' => $wp_post_id,
            'operation' => $existing ? 'UPDATE' : 'INSERT',
            'status' => $status
        ));

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
        $result = $this->get_tracking_record($property_id);

        if (!$result) {
            return null;
        }

        return array(
            'property_id' => $result['property_id'] ?? null,
            'wp_post_id' => $result['wp_post_id'] ?? null,
            'status' => $result['status'] ?? null,
            'last_import_date' => $result['last_import_date'] ?? null,
        );
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

    /**
     * Cleanup tracking automatico quando property viene cancellata dal backend WP
     *
     * Previene tracking orphan che causano SKIP su re-import.
     * Risolve issue: cancellare property da backend → tracking rimane → re-import skippa perché hash uguale
     *
     * @param int $post_id WordPress Post ID being deleted
     * @param WP_Post $post WordPress Post object
     * @return void
     */
    public function cleanup_tracking_on_delete($post_id, $post) {
        // 🔧 FIX: Gestisci sia property che agency
        if ($post->post_type === 'estate_property') {
            // Delete da property tracking table
            $table_name = $this->wpdb->prefix . self::TABLE_NAME;
            $deleted = $this->wpdb->delete(
                $table_name,
                array('wp_post_id' => $post_id),
                array('%d')
            );

            if ($deleted) {
                $this->logger->log("[TRACKING-CLEANUP] Removed tracking for deleted property (wp_post_id: {$post_id})", 'info');
            } else {
                $this->logger->log("[TRACKING-CLEANUP] No tracking found for deleted property (wp_post_id: {$post_id})", 'debug');
            }

        } elseif ($post->post_type === 'estate_agency') {
            // 🆕 Delete da agency tracking table
            $table_name = $this->wpdb->prefix . self::AGENCY_TABLE_NAME;
            $deleted = $this->wpdb->delete(
                $table_name,
                array('wp_post_id' => $post_id),
                array('%d')
            );

            if ($deleted) {
                $this->logger->log("[TRACKING-CLEANUP] Removed tracking for deleted agency (wp_post_id: {$post_id})", 'info');
            } else {
                $this->logger->log("[TRACKING-CLEANUP] No tracking found for deleted agency (wp_post_id: {$post_id})", 'debug');
            }
        }
    }

    // ========================================================================
    // AGENCY TRACKING METHODS (v1.7.0+)
    // ========================================================================

    /**
     * Create agency tracking table
     *
     * Similar to property tracking but for agencies.
     * Tracks agency changes to enable pre-filtering optimization.
     *
     * @since 1.7.0
     * @return bool Success status
     */
    public function create_agency_tracking_table() {
        $table_name = $this->wpdb->prefix . self::AGENCY_TABLE_NAME;

        $charset_collate = $this->wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            agency_id varchar(50) NOT NULL,
            wp_post_id bigint(20) unsigned NULL,
            agency_hash varchar(32) NOT NULL,
            data_snapshot longtext NULL,
            last_import_date datetime NOT NULL,
            status enum('active', 'inactive', 'deleted', 'error') DEFAULT 'active',
            provincia varchar(10) NULL,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (agency_id),
            KEY wp_post_id (wp_post_id),
            KEY status (status),
            KEY provincia (provincia),
            KEY last_import_date (last_import_date),
            KEY agency_hash (agency_hash)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        $result = dbDelta($sql);

        $this->logger->log("Agency tracking table created/updated: " . $table_name, 'info');

        return true;
    }

    /**
     * Calculate MD5 hash for agency change detection
     *
     * Similar to calculate_property_hash but for agency data
     *
     * @since 1.7.0
     * @param array $agency_data Agency data from parser
     * @return string MD5 hash
     */
    public function calculate_agency_hash($agency_data) {
        // Hash on ALL fields to detect any change
        $hash_data = $agency_data;

        // Remove deleted flag from hash (not relevant for change detection)
        unset($hash_data['deleted']);

        // Normalize for consistency
        ksort($hash_data);

        // Generate MD5 hash
        $hash_string = serialize($hash_data);
        $hash = md5($hash_string);

        return $hash;
    }

    /**
     * Check if agency has changes
     *
     * Similar to check_property_changes but for agencies
     *
     * @since 1.7.0
     * @param string $agency_id Agency ID from XML
     * @param string $new_hash Newly calculated hash
     * @return array Status array with has_changed and action
     */
    public function check_agency_changes($agency_id, $new_hash) {
        $existing = $this->get_agency_tracking_record($agency_id);

        if (!$existing) {
            $fallback_wp_id = $this->lookup_agency_wp_id_by_xml_id($agency_id);

            if ($fallback_wp_id) {
                $existing = $this->get_agency_tracking_record_by_wp_post_id($fallback_wp_id);

                if ($existing) {
                    if ($existing['agency_hash'] !== $new_hash) {
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

                return array(
                    'has_changed' => true,
                    'action' => 'update',
                    'reason' => 'existing_agency_fallback',
                    'wp_post_id' => $fallback_wp_id
                );
            }

            return array(
                'has_changed' => true,
                'action' => 'insert',
                'reason' => 'new_agency'
            );
        }

        if ($existing['agency_hash'] !== $new_hash) {
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
     * Get agency tracking record
     *
     * @since 1.7.0
     * @param string $agency_id Agency ID from XML
     * @return array|null Tracking record or null if not exists
     */
    public function get_agency_tracking_record($agency_id) {
        $table_name = $this->wpdb->prefix . self::AGENCY_TABLE_NAME;

        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $table_name WHERE agency_id = %s",
                $agency_id
            ),
            ARRAY_A
        );

        return $result;
    }

    /**
     * Get agency tracking record by WordPress post ID
     *
     * @since 1.7.0
     * @param int $wp_post_id WordPress Post ID (estate_agency)
     * @return array|null Tracking record or null if not exists
     */
    private function get_agency_tracking_record_by_wp_post_id($wp_post_id) {
        $table_name = $this->wpdb->prefix . self::AGENCY_TABLE_NAME;

        $result = $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM $table_name WHERE wp_post_id = %d",
                $wp_post_id
            ),
            ARRAY_A
        );

        return $result;
    }

    /**
     * Lookup existing agency WordPress post ID by XML agency ID.
     *
     * @since 1.7.0
     * @param string $agency_id Agency ID from XML
     * @return int|false WordPress post ID if found, false otherwise
     */
    private function lookup_agency_wp_id_by_xml_id($agency_id) {
        if (empty($agency_id) || !class_exists('RealEstate_Sync_Agency_Manager')) {
            return false;
        }

        $agency_manager = new RealEstate_Sync_Agency_Manager();
        if (!method_exists($agency_manager, 'lookup_agency_by_xml_id')) {
            return false;
        }

        $wp_post_id = $agency_manager->lookup_agency_by_xml_id($agency_id);

        if ($wp_post_id) {
            $this->logger->log("Agency fallback lookup hit: xml_id $agency_id → wp_id $wp_post_id", 'debug');
        }

        return $wp_post_id;
    }

    /**
     * Update or insert agency tracking record
     *
     * @since 1.7.0
     * @param string $agency_id Agency ID from XML
     * @param string $agency_hash MD5 hash
     * @param int $wp_post_id WordPress Post ID (estate_agency)
     * @param array $agency_data Complete agency data
     * @param string $status Status (active, inactive, deleted)
     * @return bool Success status
     */
    public function update_agency_tracking_record($agency_id, $agency_hash, $wp_post_id = null, $agency_data = array(), $status = 'active') {
        $table_name = $this->wpdb->prefix . self::AGENCY_TABLE_NAME;

        $data = array(
            'agency_hash' => $agency_hash,
            'last_import_date' => current_time('mysql'),
            'status' => $status
        );

        // Add optional data if provided
        if ($wp_post_id) {
            $data['wp_post_id'] = $wp_post_id;
        }

        if (!empty($agency_data)) {
            $data['data_snapshot'] = json_encode($agency_data);
            $data['provincia'] = isset($agency_data['provincia']) ? $agency_data['provincia'] : null;
        }

        // 🔧 FIX PRIORITÀ 4: Build format array dynamically based on actual fields in $data
        $formats = array();
        foreach (array_keys($data) as $key) {
            switch ($key) {
                case 'wp_post_id':
                    $formats[] = '%d';  // Integer
                    break;
                default:
                    $formats[] = '%s';  // String (agency_hash, last_import_date, status, data_snapshot, provincia)
                    break;
            }
        }

        // Check if record exists
        $existing = $this->get_agency_tracking_record($agency_id);

        if ($existing) {
            // UPDATE
            $result = $this->wpdb->update(
                $table_name,
                $data,
                array('agency_id' => $agency_id),
                $formats,      // ✅ Dynamic format specifiers
                array('%s')    // agency_id is string
            );
        } else {
            // INSERT - add agency_id to data and formats
            $data['agency_id'] = $agency_id;
            $formats = array_merge(array('%s'), $formats);  // agency_id is string (varchar50)

            $result = $this->wpdb->insert(
                $table_name,
                $data,
                $formats      // ✅ Dynamic format specifiers
            );
        }

        if ($result === false) {
            $this->logger->log("Error updating agency tracking record for agency $agency_id: " . $this->wpdb->last_error, 'error');

            // 🔍 DEBUG: Log failure details
            $tracker = RealEstate_Sync_Debug_Tracker::get_instance();
            $tracker->log_event('ERROR', 'TRACKING_MANAGER', 'Failed to save agency tracking record', array(
                'agency_id' => $agency_id,
                'agency_hash' => $agency_hash,
                'wp_post_id' => $wp_post_id,
                'wpdb_error' => $this->wpdb->last_error,
                'operation' => $existing ? 'UPDATE' : 'INSERT'
            ));

            return false;
        }

        // 🔍 DEBUG: Log successful save
        $tracker = RealEstate_Sync_Debug_Tracker::get_instance();
        $tracker->log_event('DEBUG', 'TRACKING_MANAGER', 'Agency tracking record saved', array(
            'agency_id' => $agency_id,
            'agency_hash' => $agency_hash,
            'wp_post_id' => $wp_post_id,
            'operation' => $existing ? 'UPDATE' : 'INSERT',
            'status' => $status
        ));

        $this->logger->log("Agency tracking record updated for agency $agency_id (status: $status)", 'debug');
        return true;
    }

    /**
     * Build functional stats for a property update by comparing tracking snapshot with current XML data.
     *
     * @param string|int $property_id Property import ID.
     * @param array      $property_data Current raw XML data.
     * @return array
     */
    public function classify_property_functional_update($property_id, array $property_data) {
        return $this->classify_functional_update($property_id, $property_data, 'property');
    }

    /**
     * Build functional stats for an agency update by comparing tracking snapshot with current XML data.
     *
     * @param string|int $agency_id Agency import ID.
     * @param array      $agency_data Current agency data.
     * @return array
     */
    public function classify_agency_functional_update($agency_id, array $agency_data) {
        return $this->classify_functional_update($agency_id, $agency_data, 'agency');
    }

    /**
     * Classify a functional update for a tracked entity.
     *
     * @param string|int $entity_id Entity import ID.
     * @param array      $current_data Current source data.
     * @param string     $entity_type property|agency.
     * @return array
     */
    private function classify_functional_update($entity_id, array $current_data, $entity_type) {
        $stats = self::get_functional_stats_defaults();
        $record = ($entity_type === 'agency')
            ? $this->get_agency_tracking_record($entity_id)
            : $this->get_tracking_record($entity_id);

        if (empty($record) || empty($record['data_snapshot'])) {
            return $stats;
        }

        $old_data = json_decode($record['data_snapshot'], true);
        if (!is_array($old_data)) {
            return $stats;
        }

        $normalized_old = $this->normalize_entity_for_compare($old_data, $entity_type);
        $normalized_new = $this->normalize_entity_for_compare($current_data, $entity_type);
        $raw_diff_keys = $this->get_top_level_diff_keys($old_data, $current_data);
        $semantic_changed = ($normalized_old !== $normalized_new);

        $media_delta = $this->get_media_delta($old_data, $current_data, $entity_type);
        if (!empty($media_delta['added']) || !empty($media_delta['removed'])) {
            self::increment_functional_stat($stats, 'media_updates');
            self::increment_functional_stat($stats, 'media_added', count($media_delta['added']));
            self::increment_functional_stat($stats, 'media_removed_from_gallery', count($media_delta['removed']));
        }

        $whitespace_only_description = $this->has_whitespace_only_description_change($old_data, $current_data, $entity_type);
        $last_editor_only = $this->has_last_editor_time_only_change($raw_diff_keys, $whitespace_only_description);

        if ($semantic_changed) {
            self::increment_functional_stat($stats, 'business_updates');
        } else {
            self::increment_functional_stat($stats, 'technical_updates');

            if ($whitespace_only_description) {
                self::increment_functional_stat($stats, 'text_format_only_updates');
            }

            if ($last_editor_only) {
                self::increment_functional_stat($stats, 'last_editor_time_only_updates');
            }
        }

        return $stats;
    }

    /**
     * Normalize an entity for semantic comparison.
     *
     * @param mixed  $value Value to normalize.
     * @param string $entity_type property|agency.
     * @param string|null $current_key Current key for special handling.
     * @return mixed
     */
    private function normalize_entity_for_compare($value, $entity_type, $current_key = null) {
        if (is_array($value)) {
            $is_assoc = $this->is_assoc_array($value);

            if (!$is_assoc && in_array((string) $current_key, array('media_files', 'file_allegati'), true)) {
                $media = array();
                foreach ($value as $item) {
                    $normalized_media = $this->normalize_media_value($item);
                    if ($normalized_media !== '') {
                        $media[] = $normalized_media;
                    }
                }
                sort($media);
                return array_values(array_unique($media));
            }

            $normalized = array();
            foreach ($value as $key => $item) {
                if ((string) $key === 'last_editor_time') {
                    continue;
                }

                $normalized[$key] = $this->normalize_entity_for_compare($item, $entity_type, (string) $key);
            }

            if ($is_assoc) {
                ksort($normalized);
            } else {
                sort($normalized);
            }

            return $normalized;
        }

        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            return $this->normalize_scalar_for_compare($value, $current_key, $entity_type);
        }

        return '';
    }

    /**
     * Extract normalized media URLs from a payload.
     *
     * @param array  $old_data Old payload.
     * @param array  $current_data Current payload.
     * @param string $entity_type property|agency.
     * @return array
     */
    private function get_media_delta(array $old_data, array $current_data, $entity_type) {
        $old_media = $this->extract_media_urls($old_data, $entity_type);
        $new_media = $this->extract_media_urls($current_data, $entity_type);

        return array(
            'added' => array_values(array_diff($new_media, $old_media)),
            'removed' => array_values(array_diff($old_media, $new_media)),
        );
    }

    /**
     * Extract media URLs for property/agency payloads.
     *
     * @param array  $data Payload data.
     * @param string $entity_type property|agency.
     * @return array
     */
    private function extract_media_urls(array $data, $entity_type) {
        $urls = array();

        if ($entity_type === 'property') {
            $media_sources = array();
            if (!empty($data['media_files']) && is_array($data['media_files'])) {
                $media_sources = $data['media_files'];
            } elseif (!empty($data['file_allegati']) && is_array($data['file_allegati'])) {
                $media_sources = $data['file_allegati'];
            }

            foreach ($media_sources as $item) {
                if (is_array($item) && !empty($item['url'])) {
                    $urls[] = $this->normalize_media_value($item['url']);
                } elseif (is_string($item) && $item !== '') {
                    $urls[] = $this->normalize_media_value($item);
                }
            }
        }

        $urls = array_values(array_unique(array_filter($urls)));
        sort($urls);

        return $urls;
    }

    /**
     * Normalize media values to a stable token.
     *
     * @param mixed $value Media value.
     * @return string
     */
    private function normalize_media_value($value) {
        if (!is_string($value)) {
            return '';
        }

        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $path = parse_url($value, PHP_URL_PATH);
        if (!empty($path)) {
            $value = basename($path);
        }

        return strtolower($value);
    }

    /**
     * Detect whitespace-only changes on description fields.
     *
     * @param array  $old_data Old snapshot.
     * @param array  $new_data Current payload.
     * @param string $entity_type property|agency.
     * @return bool
     */
    private function has_whitespace_only_description_change(array $old_data, array $new_data, $entity_type) {
        if ($entity_type !== 'property') {
            return false;
        }

        foreach (array('description', 'description_de') as $field) {
            $old = isset($old_data[$field]) ? (string) $old_data[$field] : '';
            $new = isset($new_data[$field]) ? (string) $new_data[$field] : '';

            if ($old === $new) {
                continue;
            }

            if ($this->normalize_text_for_compare($old) === $this->normalize_text_for_compare($new)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect whether the only raw change is last_editor_time.
     *
     * @param array $raw_diff_keys Top-level diff keys.
     * @param bool  $description_whitespace_only Whether description changed only in whitespace.
     * @return bool
     */
    private function has_last_editor_time_only_change(array $raw_diff_keys, $description_whitespace_only) {
        if ($description_whitespace_only) {
            return false;
        }

        return count($raw_diff_keys) === 1 && in_array('last_editor_time', $raw_diff_keys, true);
    }

    /**
     * Collect top-level keys with raw differences.
     *
     * @param array $old_data Old payload.
     * @param array $new_data New payload.
     * @return array
     */
    private function get_top_level_diff_keys(array $old_data, array $new_data) {
        $keys = array_unique(array_merge(array_keys($old_data), array_keys($new_data)));
        $diff = array();

        foreach ($keys as $key) {
            $old_exists = array_key_exists($key, $old_data);
            $new_exists = array_key_exists($key, $new_data);

            if (!$old_exists || !$new_exists) {
                $diff[] = $key;
                continue;
            }

            if (serialize($old_data[$key]) !== serialize($new_data[$key])) {
                $diff[] = $key;
            }
        }

        return $diff;
    }

    /**
     * Normalize a text value for whitespace-only comparisons.
     *
     * @param string $value Text value.
     * @return string
     */
    private function normalize_text_for_compare($value) {
        $value = (string) $value;
        $value = preg_replace('/\s+/u', ' ', trim($value));

        return $value;
    }

    /**
     * Normalize a scalar for semantic comparison using key-aware rules.
     *
     * @param mixed  $value Scalar value.
     * @param string|null $current_key Current key.
     * @param string $entity_type property|agency.
     * @return string
     */
    private function normalize_scalar_for_compare($value, $current_key = null, $entity_type = 'property') {
        $value = (string) $value;
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        switch ((string) $current_key) {
            case 'email':
                return strtolower($value);

            case 'phone':
            case 'mobile':
                return preg_replace('/\D+/', '', $value);

            case 'website':
            case 'url':
                return $this->normalize_url_for_compare($value);

            case 'logo_url':
                return $this->normalize_media_value($value);

            case 'description':
            case 'description_de':
                return $this->normalize_text_for_compare($value);

            default:
                return $this->normalize_text_for_compare($value);
        }
    }

    /**
     * Normalize URLs for semantic comparison.
     *
     * @param string $value URL value.
     * @return string
     */
    private function normalize_url_for_compare($value) {
        $value = strtolower(trim($value));
        $value = preg_replace('#^https?://#', '', $value);
        $value = rtrim($value, '/');

        return $value;
    }

    /**
     * Detect associative arrays.
     *
     * @param array $value Array to inspect.
     * @return bool
     */
    private function is_assoc_array(array $value) {
        if (array() === $value) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}
