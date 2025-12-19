<?php
/**
 * Widget: Import XML (Upload File Manuale)
 * Tab: Import
 * User: Admin + Tecnico
 */

if (!defined('ABSPATH')) exit;
?>

<!--
╔═══════════════════════════════════════════════════════════════════╗
║ WIDGET: IMPORT XML (Upload File Manuale)                          ║
╠═══════════════════════════════════════════════════════════════════╣
║ UTENTE: Entrambi (Admin + Tecnico)                               ║
║ SCOPO: Importa da file XML locale invece che da server remoto    ║
║                                                                   ║
║ AZIONI UTENTE:                                                    ║
║  - Upload file XML dal computer                                   ║
║  - Marca import come test (_test_import=1) opzionale              ║
║  - Processa XML con stesso flusso import remoto                   ║
║                                                                   ║
║ MANIPOLA:                                                         ║
║  - Stesso del Import Immediato                                    ║
║  - wp_posts: estate_property (create/update)                      ║
║  - wp_realestate_sync_tracking: (create/update)                   ║
║  - wp_realestate_import_queue: (populate)                         ║
║  - Media library: (download/attach images)                        ║
║                                                                   ║
║ VISIBILITÀ: Sempre                                                ║
║ FREQUENZA USO: Testing, import one-off                            ║
║ CRITICO: No (utile ma non essenziale per workflow quotidiano)     ║
╚═══════════════════════════════════════════════════════════════════╝
-->
<div class="rs-upload-section">
    <h4><span class="dashicons dashicons-upload"></span> <?php _e('Import XML', 'realestate-sync'); ?></h4>
    <p>Upload file XML per importare properties e agenzie (utile sia per test che per produzione)</p>

    <div>
        <input type="file" id="test-xml-file" accept=".xml">
        <small>Seleziona file XML (esempio: sample-con-agenzie.xml)</small>
    </div>

    <div>
        <label>
            <input type="checkbox" id="mark-as-test-import" checked>
            <span>
                <span class="dashicons dashicons-flag"></span>
                Marca come import di test
            </span>
        </label>
        <small>
            ✓ Se attivo: le proprietà avranno flag <code>_test_import=1</code> e potrai cancellarle con "Cleanup Test Data"<br>
            ✗ Se disattivo: import normale (produzione), le proprietà non saranno marcate come test
        </small>
    </div>

    <div>
        <button type="button" class="rs-button-primary" id="process-test-file" disabled>
            <span class="dashicons dashicons-admin-generic"></span> Processa File XML
        </button>
    </div>

    <!-- Test Log Output -->
    <div id="test-log-output" class="rs-hidden">
        <h5>Log Processo:</h5>
        <pre id="test-log-content">Avvio processo...</pre>
    </div>
</div>
