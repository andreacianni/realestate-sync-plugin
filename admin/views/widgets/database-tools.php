<?php
/**
 * Widget: Database Tools (Cleanup Test Data)
 * Tab: Strumenti
 * User: Admin + Tecnico
 */

if (!defined('ABSPATH')) exit;
?>

<!--
╔═══════════════════════════════════════════════════════════════════╗
║ WIDGET: DATABASE TOOLS (Cleanup Test Data)                        ║
╠═══════════════════════════════════════════════════════════════════╣
║ UTENTE: Entrambi (Admin + Tecnico)                               ║
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
║ VISIBILITÀ: Sempre                                                ║
║ FREQUENZA USO: Dopo testing                                       ║
║ CRITICO: No (solo per pulizia test, non dati produzione)          ║
╚═══════════════════════════════════════════════════════════════════╝
-->
<div class="rs-testing-section">
    <h4><span class="dashicons dashicons-admin-tools"></span> <?php _e('Database Tools', 'realestate-sync'); ?></h4>

    <div class="rs-button-group">
        <button type="button" class="rs-button-warning" id="cleanup-test-data">
            <span class="dashicons dashicons-trash"></span> Cleanup Test Data
        </button>
    </div>
</div>
