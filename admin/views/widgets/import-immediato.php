<?php
/**
 * Widget: Import Immediato
 * Tab: Import
 * User: Admin Non Tecnico
 */

if (!defined('ABSPATH')) exit;
?>

<!--
╔═══════════════════════════════════════════════════════════════════╗
║ WIDGET: IMPORT IMMEDIATO                                          ║
╠═══════════════════════════════════════════════════════════════════╣
║ UTENTE: Admin Non Tecnico                                         ║
║ SCOPO: Trigger manuale download e import da gestionale            ║
║ AZIONI: Scarica XML + Processa + Importa Media                    ║
║ MANIPOLA: Posts, Tracking, Queue, Media Library                   ║
║ FREQUENZA: Occasionale (backup automazione)                       ║
╚═══════════════════════════════════════════════════════════════════╝
-->
<div class="card shadow-sm rounded-3 border-1 p-0">
    <div class="card-header bg-success bg-opacity-10 border-0 py-3">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <span class="dashicons dashicons-download me-2"></span>
            <?php _e('Import Immediato', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <div class="mb-3" role="alert">
            Scarica e importa immediatamente i dati XML da GestionaleImmobiliare.it
        </div>

        <div class="alert alert-warning mb-3 small">
            <div class="form-check d-flex align-items-start">
                <input type="checkbox" class="form-check-input mt-1" id="mark-as-test-manual-import">
                <label class="form-check-label ms-2" for="mark-as-test-manual-import">
                    <span class="dashicons dashicons-flag"></span>
                    <strong>Marca come Test Import</strong>
                </label>
            </div>
            <div class="form-text ms-4">
                Le proprieta, agenzie e media verranno marcate con flag <code>_test_import=1</code> per facile rimozione
            </div>
        </div>

        <button type="button" class="btn btn-success btn-lg w-100" id="start-manual-import">
            <span class="dashicons dashicons-download"></span>
            <?php _e('Scarica e Importa Ora', 'realestate-sync'); ?>
        </button>

        <!-- Manual Import Log Output -->
        <div id="manual-import-log-output" class="d-none mt-3">
            <h6 class="text-muted">Log Processo:</h6>
            <pre class="p-3 bg-light rounded-2 border" id="manual-import-log-content">Avvio processo...</pre>
        </div>
    </div>
</div>

