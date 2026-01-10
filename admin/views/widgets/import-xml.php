<?php
/**
 * Widget: Import XML (Upload File Manuale)
 * Tab: Import
 * User: Admin + Tecnico
 */

// NOTE: Dev/debug-only widget; currently buggy.
// Must be fixed before any production use.



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
<div class="card shadow-sm rounded-3 border-1 p-0">
    <div class="card-header bg-warning bg-opacity-10 border-0 py-3">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <span class="dashicons dashicons-upload me-2"></span>
            <?php _e('Import XML', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <p class="text-muted mb-3">Upload file XML per importare properties e agenzie (utile sia per test che per produzione)</p>

        <div class="mb-3">
            <label for="test-xml-file" class="form-label fw-semibold">
                <span class="dashicons dashicons-media-document"></span>
                Seleziona File XML
            </label>
            <input type="file" class="form-control" id="test-xml-file" accept=".xml">
            <div class="form-text">Seleziona file XML (esempio: sample-con-agenzie.xml)</div>
        </div>

        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="mark-as-test-import" checked>
            <label class="form-check-label" for="mark-as-test-import">
                <span class="dashicons dashicons-flag"></span>
                <strong>Marca come import di test</strong>
            </label>
            <div class="form-text">
                <span class="badge bg-success">✓ Attivo</span> Le proprietà avranno flag <code>_test_import=1</code> e potrai cancellarle con "Cleanup Test Data"<br>
                <span class="badge bg-secondary">✗ Disattivo</span> Import normale (produzione), le proprietà non saranno marcate come test
            </div>
        </div>

        <button type="button" class="btn btn-warning w-100" id="process-test-file" disabled>
            <span class="dashicons dashicons-admin-generic"></span>
            Processa File XML
        </button>

        <!-- Test Log Output -->
        <div id="test-log-output" class="d-none mt-3">
            <h6 class="text-muted">Log Processo:</h6>
            <pre class="p-3 bg-light rounded-2 border" id="test-log-content">Avvio processo...</pre>
        </div>
    </div>
</div>

