<?php
/**
 * Widget: Database Tools (Cleanup Test Data)
 * Tab: Strumenti
 * User: Admin + Tecnico
 */

if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/../partials/allowed-admins.php';
if (!rs_current_user_is_allowed_admin()) {
    return;
}
?>

<!--
╔═══════════════════════════════════════════════════════════════════╗
║ WIDGET: DATABASE TOOLS (Cleanup Test Data)                        ║
╠═══════════════════════════════════════════════════════════════════╣
║ UTENTE: Admin autorizzati                               ║
║ SCOPO: Rimuovi dati di test marcati con _test_import=1           ║
║                                                                   ║
║ AZIONI UTENTE:                                                    ║
║  - Cleanup Test Data: rimuove tutte le proprietà marcate test     ║
║                                                                   ║
║ MANIPOLA:                                                         ║
║  - wp_posts: estate_property WHERE meta_key='_test_import' (del)  ║
║  - wp_postmeta: _test_import=1 (read/delete)                      ║
║  - wp_realestate_sync_tracking: (delete via hook)                 ║
║  - Media library: (delete via hook)                               ║
║                                                                   ║
║ VISIBILITÀ: Solo admin autorizzati                                                ║
║ FREQUENZA USO: Dopo testing                                       ║
║ CRITICO: No (solo per pulizia test, non dati produzione)          ║
╚═══════════════════════════════════════════════════════════════════╝
-->
<div class="card shadow-sm rounded-3 border-1 p-0">
    <div class="card-header bg-warning bg-opacity-10 border-0 py-3">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <span class="dashicons dashicons-admin-tools me-2"></span>
            <?php _e('Database Tools', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <p class="text-muted mb-3">Rimuovi dati di test marcati con <code>_test_import=1</code></p>

        <div class="d-grid">
            <button type="button" class="btn btn-warning btn-lg" id="cleanup-test-data">
                <span class="dashicons dashicons-trash"></span>
                Cleanup Test Data
            </button>
        </div>
    </div>
</div>
