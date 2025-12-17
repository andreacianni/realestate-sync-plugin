<?php
/**
 * Import Verifier - Post-import quality check
 *
 * Confronta data_snapshot (XML) con dati effettivi salvati in WP
 * per proprietà INSERT/UPDATE e segnala discrepanze per review manuale.
 *
 * NON modifica tracking - solo evidenzia per admin.
 *
 * @package RealEstate_Sync
 * @version 1.6.0
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Import_Verifier {

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
    }

    /**
     * Verifica tutte le proprietà INSERT/UPDATE di una sessione
     *
     * Chiamato DOPO che l'import è completato.
     * Confronta data_snapshot con dati WP per evidenziare discrepanze.
     *
     * @param string $session_id Session ID
     */
    public function verify_session($session_id) {
        error_log("[VERIFICATION] ========== POST-IMPORT VERIFICATION STARTED ==========");
        error_log("[VERIFICATION] Analyzing session: {$session_id}");

        // Get queue items processed in this session
        $queue_table = $this->wpdb->prefix . 'realestate_import_queue';
        $processed_items = $this->wpdb->get_results($this->wpdb->prepare("
            SELECT item_id, item_type, status
            FROM {$queue_table}
            WHERE session_id = %s
              AND item_type = 'property'
              AND status = 'done'
        ", $session_id), ARRAY_A);

        if (empty($processed_items)) {
            error_log("[VERIFICATION] No items to verify");
            error_log("[VERIFICATION] ========== VERIFICATION ENDED (nothing to check) ==========");
            return;
        }

        error_log("[VERIFICATION] Found " . count($processed_items) . " properties to verify");

        $issues_found = [];
        $verified_count = 0;

        foreach ($processed_items as $item) {
            $property_id = $item['item_id'];
            $issues = $this->verify_property($property_id);

            if (!empty($issues)) {
                $issues_found[$property_id] = $issues;
                error_log("[VERIFICATION] ⚠️ Property {$property_id}: " . count($issues) . " issues found");
            } else {
                $verified_count++;
            }
        }

        // Save results for dashboard
        if (!empty($issues_found)) {
            $this->save_verification_results($session_id, $issues_found);
            error_log("[VERIFICATION] ⚠️ FOUND ISSUES in " . count($issues_found) . " properties");
            error_log("[VERIFICATION] ✅ Verified OK: {$verified_count} properties");
        } else {
            error_log("[VERIFICATION] ✅ ALL " . count($processed_items) . " properties verified successfully");
        }

        error_log("[VERIFICATION] ========== POST-IMPORT VERIFICATION ENDED ==========");
    }

    /**
     * Verifica singola proprietà confrontando snapshot vs WP
     *
     * Confronta campi critici:
     * - Post exists
     * - Title
     * - Price
     * - Images count
     * - Post status
     *
     * @param int $property_id GI Property ID
     * @return array Issues trovati (empty se tutto OK)
     */
    private function verify_property($property_id) {
        $tracking_table = $this->wpdb->prefix . 'realestate_sync_tracking';

        // Get tracking record con data_snapshot
        $tracking = $this->wpdb->get_row($this->wpdb->prepare("
            SELECT wp_post_id, data_snapshot
            FROM {$tracking_table}
            WHERE property_id = %d
        ", $property_id), ARRAY_A);

        if (!$tracking || empty($tracking['data_snapshot'])) {
            return [['field' => 'tracking', 'issue' => 'no_tracking_data']];
        }

        $wp_post_id = $tracking['wp_post_id'];
        $xml_data = json_decode($tracking['data_snapshot'], true);

        if (!$xml_data) {
            return [['field' => 'tracking', 'issue' => 'invalid_snapshot_json']];
        }

        $issues = [];

        // 1. Post exists?
        $post = get_post($wp_post_id);
        if (!$post) {
            return [['field' => 'post', 'issue' => 'deleted_or_missing']];
        }

        // 2. Title verificato
        $expected_title = sanitize_text_field($xml_data['title'] ?? '');
        if (!empty($expected_title) && $post->post_title !== $expected_title) {
            $issues[] = [
                'field' => 'title',
                'expected' => $expected_title,
                'actual' => $post->post_title
            ];
        }

        // 3. Price verificato
        if (!empty($xml_data['price'])) {
            $saved_price = get_post_meta($wp_post_id, 'property_price', true);
            $expected_price = floatval($xml_data['price']);
            if (abs(floatval($saved_price) - $expected_price) > 0.01) {
                $issues[] = [
                    'field' => 'price',
                    'expected' => number_format($expected_price, 2, '.', ''),
                    'actual' => number_format(floatval($saved_price), 2, '.', '')
                ];
            }
        }

        // 4. Images count
        $xml_images_count = count($xml_data['images'] ?? []);
        if ($xml_images_count > 0) {
            $thumbnail_id = get_post_thumbnail_id($wp_post_id);
            $gallery_ids = get_post_meta($wp_post_id, 'property_gallery', true);
            $wp_images_count = ($thumbnail_id ? 1 : 0) + (is_array($gallery_ids) ? count($gallery_ids) : 0);

            if ($wp_images_count < $xml_images_count) {
                $issues[] = [
                    'field' => 'images',
                    'expected' => $xml_images_count,
                    'actual' => $wp_images_count,
                    'missing' => $xml_images_count - $wp_images_count
                ];
            }
        }

        // 5. Post status
        if ($post->post_status !== 'publish') {
            $issues[] = [
                'field' => 'status',
                'expected' => 'publish',
                'actual' => $post->post_status
            ];
        }

        return $issues;
    }

    /**
     * Salva risultati verifica per dashboard
     *
     * Salva sia in transient (per storico) che in option (per ultimo import).
     *
     * @param string $session_id Session ID
     * @param array $issues_found Property ID => array di issues
     */
    private function save_verification_results($session_id, $issues_found) {
        // Get property titles for better UX
        $properties_with_titles = [];
        foreach ($issues_found as $prop_id => $issues) {
            $tracking_table = $this->wpdb->prefix . 'realestate_sync_tracking';
            $tracking = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT wp_post_id FROM {$tracking_table} WHERE property_id = %d",
                $prop_id
            ), ARRAY_A);

            $title = 'Unknown';
            if ($tracking && $tracking['wp_post_id']) {
                $post = get_post($tracking['wp_post_id']);
                if ($post) {
                    $title = $post->post_title;
                }
            }

            $properties_with_titles[$prop_id] = [
                'title' => $title,
                'issues' => $issues
            ];
        }

        $results = [
            'session_id' => $session_id,
            'timestamp' => current_time('mysql'),
            'total_issues' => count($issues_found),
            'properties' => $properties_with_titles
        ];

        // Save in transient (expires in 7 days) - storico
        set_transient('realestate_sync_verification_' . $session_id, $results, 7 * DAY_IN_SECONDS);

        // Also keep latest verification (per dashboard widget)
        update_option('realestate_sync_latest_verification', $results);

        error_log("[VERIFICATION] Results saved for session: {$session_id}");
        error_log("[VERIFICATION] Transient key: realestate_sync_verification_{$session_id}");
    }
}
