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
<div class="rs-card">
    <h3><span class="dashicons dashicons-download"></span> <?php _e('Import Immediato', 'realestate-sync'); ?></h3>

    <div class="rs-info-box">
        <strong>Import Immediato</strong><br>
        Scarica e importa immediatamente i dati XML da GestionaleImmobiliare.it
    </div>

    <div>
        <label>
            <input type="checkbox" id="mark-as-test-manual-import" checked>
            <span>
                <span class="dashicons dashicons-flag"></span>
                Marca come Test Import
            </span>
        </label>
        <small>
            Le proprietà, agenzie e media verranno marcate con flag <code>_test_import=1</code> per facile rimozione
        </small>
    </div>

    <button type="button" class="rs-button-primary" id="start-manual-import">
        <span class="dashicons dashicons-download"></span> <?php _e('Scarica e Importa Ora', 'realestate-sync'); ?>
    </button>

    <!-- Manual Import Log Output -->
    <div id="manual-import-log-output" class="rs-hidden">
        <h5>Log Processo:</h5>
        <pre id="manual-import-log-content">Avvio processo...</pre>
    </div>
</div>
