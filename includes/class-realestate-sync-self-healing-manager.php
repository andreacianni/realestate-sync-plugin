<?php
/**
 * Self-Healing Manager
 *
 * Sistema autoregolante che:
 * - Previene duplicazioni (idempotenza)
 * - Auto-corregge tracking mancanti
 * - Skip intelligente se hash uguale
 *
 * @package RealEstate_Sync
 * @version 1.8.0
 * @since 1.8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Self_Healing_Manager {

    private $wpdb;
    private $tracking_manager;
    private $logger;

    public function __construct($tracking_manager, $logger) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->tracking_manager = $tracking_manager;
        $this->logger = $logger;
    }

    /**
     * 🩹 SELF-HEALING: Trova o crea post in modo idempotente
     *
     * Questa funzione implementa la logica autoregolante:
     * 1. Cerca se esiste già un post con questo import_id
     * 2. Se esiste → verifica tracking e hash
     * 3. Se non esiste → crea nuovo post
     * 4. Ritorna azione da fare: 'create', 'update', 'skip', 'heal'
     *
     * @param string $property_id Import ID della proprietà
     * @param string $new_hash Hash calcolato dei dati attuali
     * @return array ['action' => string, 'wp_post_id' => int|null, 'reason' => string]
     */
    public function resolve_property_action($property_id, $new_hash) {

        $this->logger->log("🩹 [SELF-HEALING] Resolving action for property {$property_id}", 'debug');

        // STEP 1: Cerca post esistente by import_id
        $existing_post_id = $this->find_post_by_import_id($property_id);

        if (!$existing_post_id) {
            // ✅ Post NON esiste → CREATE
            $this->logger->log("🩹 [SELF-HEALING] No existing post found → CREATE", 'debug');
            return [
                'action' => 'create',
                'wp_post_id' => null,
                'reason' => 'new_property'
            ];
        }

        $this->logger->log("🩹 [SELF-HEALING] Found existing post {$existing_post_id}", 'debug');

        // STEP 2: Post esiste → verifica tracking
        $tracking_record = $this->tracking_manager->get_tracking_record($property_id);

        if (!$tracking_record) {
            // 🔧 Tracking MANCANTE → SELF-HEAL
            $this->logger->log("🩹 [SELF-HEALING] Tracking missing → HEAL (rebuild tracking)", 'warning', [
                'property_id' => $property_id,
                'wp_post_id' => $existing_post_id
            ]);

            // Ricostruisci tracking
            $this->rebuild_tracking_record($property_id, $existing_post_id, $new_hash);

            return [
                'action' => 'heal',
                'wp_post_id' => $existing_post_id,
                'reason' => 'tracking_missing_rebuilt'
            ];
        }

        // STEP 3: Tracking esiste → confronta hash
        $old_hash = $tracking_record['property_hash'] ?? null;

        if ($old_hash === $new_hash) {
            // ✅ Hash UGUALE → SKIP
            $this->logger->log("🩹 [SELF-HEALING] Hash unchanged → SKIP", 'debug', [
                'property_id' => $property_id,
                'wp_post_id' => $existing_post_id,
                'hash' => $new_hash
            ]);

            return [
                'action' => 'skip',
                'wp_post_id' => $existing_post_id,
                'reason' => 'no_changes'
            ];
        }

        // 🔄 Hash DIVERSO → UPDATE
        $this->logger->log("🩹 [SELF-HEALING] Hash changed → UPDATE", 'debug', [
            'property_id' => $property_id,
            'wp_post_id' => $existing_post_id,
            'old_hash' => $old_hash,
            'new_hash' => $new_hash
        ]);

        return [
            'action' => 'update',
            'wp_post_id' => $existing_post_id,
            'reason' => 'property_changed'
        ];
    }

    /**
     * Cerca post esistente by property_import_id
     *
     * @param string $property_id Import ID
     * @return int|null wp_post_id se trovato
     */
    private function find_post_by_import_id($property_id) {
        $sql = $this->wpdb->prepare("
            SELECT p.ID
            FROM {$this->wpdb->posts} p
            JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE pm.meta_key = 'property_import_id'
            AND pm.meta_value = %s
            AND p.post_type = 'estate_property'
            AND p.post_status != 'trash'
            ORDER BY p.ID ASC
            LIMIT 1
        ", $property_id);

        $post_id = $this->wpdb->get_var($sql);

        return $post_id ? intval($post_id) : null;
    }

    /**
     * 🔧 Ricostruisce record tracking mancante (SELF-HEAL)
     *
     * @param string $property_id Import ID
     * @param int $wp_post_id WordPress post ID
     * @param string $property_hash Hash calcolato
     */
    private function rebuild_tracking_record($property_id, $wp_post_id, $property_hash) {

        $this->logger->log("🔧 [SELF-HEALING] Rebuilding tracking record", 'info', [
            'property_id' => $property_id,
            'wp_post_id' => $wp_post_id,
            'hash' => $property_hash
        ]);

        $tracking_table = $this->wpdb->prefix . 'realestate_sync_tracking';

        $result = $this->wpdb->insert(
            $tracking_table,
            [
                'property_id' => $property_id,
                'wp_post_id' => $wp_post_id,
                'property_hash' => $property_hash,
                'status' => 'active',
                'last_sync' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%s', '%s']
        );

        if ($result === false) {
            $this->logger->log("❌ [SELF-HEALING] Failed to rebuild tracking", 'error', [
                'property_id' => $property_id,
                'wp_post_id' => $wp_post_id,
                'wpdb_error' => $this->wpdb->last_error
            ]);
        } else {
            $this->logger->log("✅ [SELF-HEALING] Tracking rebuilt successfully", 'info', [
                'property_id' => $property_id,
                'wp_post_id' => $wp_post_id
            ]);
        }
    }

    /**
     * 🔍 Detect e risolvi duplicati esistenti
     *
     * Trova post duplicati (stesso import_id, più post) e li risolve:
     * - Mantiene il più vecchio (primo creato)
     * - Sposta gli altri in trash
     * - Aggiorna/crea tracking per quello mantenuto
     *
     * @param string $property_id Import ID (opzionale, se null controlla tutti)
     * @return array Report delle operazioni
     */
    public function resolve_duplicates($property_id = null) {

        $this->logger->log("🔍 [SELF-HEALING] Scanning for duplicates", 'info');

        $where_clause = $property_id ? $this->wpdb->prepare("AND pm.meta_value = %s", $property_id) : "";

        $duplicates = $this->wpdb->get_results("
            SELECT
                pm.meta_value as property_id,
                COUNT(*) as duplicate_count,
                MIN(p.ID) as keep_id,
                GROUP_CONCAT(p.ID ORDER BY p.ID ASC) as all_ids
            FROM {$this->wpdb->posts} p
            JOIN {$this->wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'estate_property'
            AND pm.meta_key = 'property_import_id'
            AND p.post_status != 'trash'
            {$where_clause}
            GROUP BY pm.meta_value
            HAVING COUNT(*) > 1
        ", ARRAY_A);

        $report = [
            'duplicates_found' => count($duplicates),
            'posts_trashed' => 0,
            'tracking_fixed' => 0,
            'details' => []
        ];

        foreach ($duplicates as $dup) {
            $prop_id = $dup['property_id'];
            $keep_id = intval($dup['keep_id']);
            $all_ids = array_map('intval', explode(',', $dup['all_ids']));

            $this->logger->log("🔧 [SELF-HEALING] Resolving duplicate for property {$prop_id}", 'warning', [
                'keep_id' => $keep_id,
                'trash_ids' => array_diff($all_ids, [$keep_id])
            ]);

            // Trash tutti tranne il primo
            foreach ($all_ids as $post_id) {
                if ($post_id != $keep_id) {
                    wp_trash_post($post_id);
                    $report['posts_trashed']++;
                    $this->logger->log("🗑️ [SELF-HEALING] Trashed duplicate post {$post_id}", 'info');
                }
            }

            // Verifica/ricostruisci tracking per quello mantenuto
            $tracking = $this->tracking_manager->get_tracking_record($prop_id);

            if (!$tracking || $tracking['wp_post_id'] != $keep_id) {
                // Tracking mancante o punta al post sbagliato → rebuild

                // Prima cancella tracking obsoleto
                if ($tracking) {
                    $this->wpdb->delete(
                        $this->wpdb->prefix . 'realestate_sync_tracking',
                        ['property_id' => $prop_id],
                        ['%s']
                    );
                }

                // Calcola hash dal post esistente (approssimativo)
                $temp_hash = md5($prop_id . $keep_id . current_time('mysql'));
                $this->rebuild_tracking_record($prop_id, $keep_id, $temp_hash);

                $report['tracking_fixed']++;
            }

            $report['details'][] = [
                'property_id' => $prop_id,
                'kept_post' => $keep_id,
                'trashed_posts' => array_diff($all_ids, [$keep_id])
            ];
        }

        $this->logger->log("✅ [SELF-HEALING] Duplicate resolution complete", 'info', $report);

        return $report;
    }
}
