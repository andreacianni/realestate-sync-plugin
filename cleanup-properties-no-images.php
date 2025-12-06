<?php
/**
 * Cleanup Properties Without Images
 *
 * Trova e cancella proprietà senza featured image.
 * Il tracking viene cancellato automaticamente dall'hook before_delete_post.
 *
 * Usage:
 * 1. DRY RUN (solo conteggio):
 *    php cleanup-properties-no-images.php
 *
 * 2. DELETE (cancellazione effettiva):
 *    php cleanup-properties-no-images.php --delete
 *
 * @package RealEstate_Sync
 */

// Load WordPress
define('WP_USE_THEMES', false);
require_once(dirname(__FILE__) . '/../../../wp-load.php');

// Parse arguments
$dry_run = !in_array('--delete', $argv);
$force_delete = in_array('--force', $argv); // Skip trash, permanent delete

echo "\n";
echo "========================================\n";
echo "  CLEANUP PROPRIETÀ SENZA IMMAGINI\n";
echo "========================================\n";
echo "\n";

if ($dry_run) {
    echo "⚠️  MODALITÀ: DRY RUN (solo analisi)\n";
    echo "Per cancellare effettivamente, esegui con: --delete\n";
} else {
    echo "🔥 MODALITÀ: DELETE (cancellazione effettiva)\n";
    if ($force_delete) {
        echo "⚠️  FORCE MODE: Cancellazione permanente (no trash)\n";
    } else {
        echo "ℹ️  Le proprietà verranno spostate nel cestino\n";
    }
}
echo "\n";

// Find properties without images
global $wpdb;

$query = "
    SELECT
        p.ID,
        p.post_title,
        p.post_status,
        t.property_id as gi_property_id,
        t.last_import_date
    FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->prefix}realestate_sync_tracking t ON p.ID = t.wp_post_id
    WHERE p.post_type = 'estate_property'
      AND p.post_status IN ('publish', 'draft')
      AND NOT EXISTS (
          SELECT 1
          FROM {$wpdb->postmeta} pm
          WHERE pm.post_id = p.ID
            AND pm.meta_key = '_thumbnail_id'
            AND pm.meta_value != ''
      )
    ORDER BY p.ID
";

echo "🔍 Ricerca proprietà senza featured image...\n";
$properties = $wpdb->get_results($query);

if (empty($properties)) {
    echo "✅ Nessuna proprietà senza immagini trovata!\n";
    echo "\n";
    exit(0);
}

$total = count($properties);
echo "⚠️  Trovate {$total} proprietà senza immagini:\n";
echo "\n";

// Show summary
$by_status = array();
foreach ($properties as $prop) {
    if (!isset($by_status[$prop->post_status])) {
        $by_status[$prop->post_status] = 0;
    }
    $by_status[$prop->post_status]++;
}

echo "Riepilogo per status:\n";
foreach ($by_status as $status => $count) {
    echo "  - {$status}: {$count}\n";
}
echo "\n";

// Show first 10 examples
echo "Prime 10 proprietà (esempio):\n";
$examples = array_slice($properties, 0, 10);
foreach ($examples as $prop) {
    $gi_id = $prop->gi_property_id ? "GI:{$prop->gi_property_id}" : "no-tracking";
    echo sprintf(
        "  - WP:%d | %s | %s | %s\n",
        $prop->ID,
        $gi_id,
        $prop->post_status,
        substr($prop->post_title, 0, 40)
    );
}

if ($total > 10) {
    echo "  ... e altre " . ($total - 10) . " proprietà\n";
}
echo "\n";

// DRY RUN: stop here
if ($dry_run) {
    echo "========================================\n";
    echo "DRY RUN COMPLETATO\n";
    echo "========================================\n";
    echo "\n";
    echo "Per CANCELLARE queste {$total} proprietà:\n";
    echo "  php cleanup-properties-no-images.php --delete\n";
    echo "\n";
    echo "Per cancellazione PERMANENTE (no cestino):\n";
    echo "  php cleanup-properties-no-images.php --delete --force\n";
    echo "\n";
    echo "⚠️  NOTA: Il tracking verrà cancellato automaticamente!\n";
    echo "\n";
    exit(0);
}

// DELETE MODE: ask confirmation
echo "========================================\n";
echo "⚠️  ATTENZIONE: CANCELLAZIONE EFFETTIVA\n";
echo "========================================\n";
echo "\n";
echo "Stai per cancellare {$total} proprietà.\n";
echo "Il tracking verrà rimosso automaticamente.\n";
echo "\n";
echo "Vuoi continuare? [y/N]: ";

$handle = fopen("php://stdin", "r");
$line = fgets($handle);
$confirm = trim(strtolower($line));
fclose($handle);

if ($confirm !== 'y' && $confirm !== 'yes') {
    echo "\n❌ Cancellazione annullata dall'utente.\n\n";
    exit(0);
}

echo "\n";
echo "🗑️  Cancellazione in corso...\n";
echo "\n";

$deleted = 0;
$errors = 0;

foreach ($properties as $prop) {
    $post_id = $prop->ID;

    // Delete post (hook will auto-delete tracking)
    $result = wp_delete_post($post_id, $force_delete);

    if ($result) {
        $deleted++;
        echo sprintf(
            "  ✓ Cancellato WP:%d | %s\n",
            $post_id,
            substr($prop->post_title, 0, 50)
        );

        // Log tracking cleanup (hook does this automatically)
        error_log("[CLEANUP-NO-IMAGES] Deleted property WP:{$post_id} (no featured image)");
    } else {
        $errors++;
        echo sprintf(
            "  ✗ ERRORE WP:%d | %s\n",
            $post_id,
            substr($prop->post_title, 0, 50)
        );
        error_log("[CLEANUP-NO-IMAGES] ERROR deleting property WP:{$post_id}");
    }

    // Progress every 50 items
    if ($deleted > 0 && $deleted % 50 === 0) {
        echo "\n  ... {$deleted}/{$total} cancellate ...\n\n";
    }
}

echo "\n";
echo "========================================\n";
echo "CLEANUP COMPLETATO\n";
echo "========================================\n";
echo "\n";
echo "Risultati:\n";
echo "  ✓ Cancellate: {$deleted}\n";
echo "  ✗ Errori: {$errors}\n";
echo "  📊 Totale: {$total}\n";
echo "\n";
echo "ℹ️  Il tracking è stato cancellato automaticamente dall'hook.\n";
echo "\n";

// Verify tracking cleanup
$tracking_table = $wpdb->prefix . 'realestate_sync_tracking';
$orphaned = $wpdb->get_var("
    SELECT COUNT(*)
    FROM {$tracking_table} t
    WHERE NOT EXISTS (
        SELECT 1 FROM {$wpdb->posts} p
        WHERE p.ID = t.wp_post_id
        AND p.post_type = 'estate_property'
    )
");

if ($orphaned > 0) {
    echo "⚠️  ATTENZIONE: {$orphaned} tracking orfani rilevati!\n";
    echo "Esegui pulizia tracking con: php cleanup-orphaned-tracking.php\n";
    echo "\n";
} else {
    echo "✅ Nessun tracking orfano rilevato.\n";
    echo "\n";
}

exit(0);
