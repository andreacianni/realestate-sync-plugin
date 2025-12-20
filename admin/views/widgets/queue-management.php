<?php
/**
 * Widget: Gestione Queue Import
 * Tab: Strumenti
 * User: Tecnico (Developer Mode)
 */

if (!defined('ABSPATH')) exit;
?>

<!--
╔═══════════════════════════════════════════════════════════════════╗
║ WIDGET: GESTIONE QUEUE IMPORT                                     ║
╠═══════════════════════════════════════════════════════════════════╣
║ UTENTE: Tecnico (debug sistema)                                  ║
║ SCOPO: Monitor e risoluzione import bloccati/in sospeso          ║
║                                                                   ║
║ AZIONI UTENTE:                                                    ║
║  - Visualizza stato ultimo import (session ID, timestamp, etc)    ║
║  - Vede elementi pending/processing/stuck                         ║
║  - Resetta elementi stuck a "pending" per riprocessare            ║
║  - Elimina elementi dalla queue                                   ║
║  - Svuota completamente la queue (reset totale)                   ║
║                                                                   ║
║ MANIPOLA:                                                         ║
║  - wp_realestate_import_queue: (read/update/delete)               ║
║  - Legge session_id, status (pending/processing/completed)        ║
║  - Modifica status da "processing" a "pending"                    ║
║  - DELETE FROM queue WHERE condition                              ║
║                                                                   ║
║ VISIBILITÀ: Sempre (ma target = developer)                        ║
║ FREQUENZA USO: Durante debug import falliti                       ║
║ CRITICO: Sì (essenziale per sbloccare import incompleti)          ║
║                                                                   ║
║ NOTE TECNICHE:                                                    ║
║  - Processing elements stuck = import chiuso senza completare     ║
║  - Reset a pending permette retry automatico                      ║
║  - Clear all queue = ricominciare da zero                         ║
╚═══════════════════════════════════════════════════════════════════╝
-->
<div class="card shadow-sm rounded-3 border-1 p-0">
    <div class="card-header bg-warning bg-opacity-10 border-0 py-3">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <span class="dashicons dashicons-list-view me-2"></span>
            <?php _e('Gestione Queue Import', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <p class="text-muted mb-4">Controlla lo stato dell'ultimo import e gestisci eventuali elementi rimasti in sospeso.</p>

        <!-- Last Import Status -->
        <div id="queue-last-import-status" class="mb-4">
            <h6 class="fw-bold text-info">
                <span class="dashicons dashicons-database-import"></span> Ultimo Import
            </h6>

            <table class="table table-sm table-bordered">
                <tbody>
                    <tr>
                        <td class="fw-semibold">Session ID:</td>
                        <td id="queue-import-session-id" class="text-muted">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Data Inizio:</td>
                        <td id="queue-import-start-time" class="text-muted">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Stato Processo:</td>
                        <td id="queue-import-process-status" class="text-muted">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Totale Elementi:</td>
                        <td id="queue-import-total-items" class="text-muted">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Completati:</td>
                        <td id="queue-import-completed-items" class="text-muted">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Rimanenti:</td>
                        <td id="queue-import-remaining-items" class="text-muted">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Progressione:</td>
                        <td id="queue-import-progress-bar">
                            <div class="progress" style="height: 20px;">
                                <div id="queue-import-progress-fill" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                            </div>
                            <span id="queue-import-progress-text" class="small text-muted">0%</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="d-grid">
                <button type="button" class="btn btn-outline-primary" id="queue-refresh-import-status">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Aggiorna Stato', 'realestate-sync'); ?>
                </button>
            </div>
        </div>

        <!-- Pending/Stuck Items Alert -->
        <div id="pending-items-alert" class="d-none mb-4">
            <div class="alert alert-warning d-flex align-items-start" role="alert">
                <span class="dashicons dashicons-warning me-2 mt-1"></span>
                <div>
                    <h6 class="alert-heading">⚠️ PROCESSO CHIUSO - ELEMENTI IN SOSPESO</h6>
                    <p id="pending-items-message" class="mb-0">
                        <!-- Populated via JS -->
                    </p>
                </div>
            </div>

            <div class="d-grid gap-2 mb-3">
                <button type="button" class="btn btn-info" id="show-pending-details">
                    <span class="dashicons dashicons-visibility"></span> Vedi Dettaglio
                </button>
                <button type="button" class="btn btn-primary" id="retry-pending-items">
                    <span class="dashicons dashicons-controls-repeat"></span> Resetta a Pending e Riprocessa
                </button>
                <button type="button" class="btn btn-danger" id="delete-pending-items">
                    <span class="dashicons dashicons-trash"></span> Elimina dalla Queue
                </button>
            </div>

            <!-- Pending Items List (expandable) -->
            <div id="pending-items-list" class="d-none p-3 bg-light rounded-2">
                <!-- Populated via JS -->
            </div>
        </div>

        <!-- Clear All Queue -->
        <div class="mb-4">
            <h6 class="fw-bold text-danger">
                <span class="dashicons dashicons-trash"></span> Pulizia Completa Queue
            </h6>
            <p class="text-muted">
                Rimuove TUTTI gli elementi dalla queue (utile per ricominciare da zero dopo aver risolto i problemi).
            </p>
            <button type="button" class="btn btn-danger" id="clear-all-queue">
                <span class="dashicons dashicons-warning"></span> Svuota Tutta la Queue
            </button>
        </div>

        <!--
        ╔═══════════════════════════════════════════════════════════════════╗
        ║ SOTTO-WIDGET: CLEANUP POST ORFANI                                 ║
        ╠═══════════════════════════════════════════════════════════════════╣
        ║ UTENTE: Tecnico                                                   ║
        ║ SCOPO: Rimuove post senza record tracking (dati inconsistenti)    ║
        ║                                                                   ║
        ║ AZIONI:                                                           ║
        ║  - Scansiona per trovare estate_property senza tracking           ║
        ║  - Mostra lista post orfani trovati                               ║
        ║  - Cancella post orfani (permanente)                              ║
        ║                                                                   ║
        ║ MANIPOLA:                                                         ║
        ║  - wp_posts: estate_property (read/delete)                        ║
        ║  - wp_realestate_sync_tracking: (read only - per find orphans)    ║
        ║  - Hook WP before_delete_post rimuove tracking e media            ║
        ║                                                                   ║
        ║ CRITICO: Sì (cancellazione permanente, no undo)                   ║
        ╚═══════════════════════════════════════════════════════════════════╝
        -->
        <div class="border-top pt-4">
            <h6 class="fw-bold text-secondary">
                <span class="dashicons dashicons-admin-tools"></span> 🧹 <?php _e('Cleanup Post Orfani', 'realestate-sync'); ?>
            </h6>
            <p class="text-muted">
                Trova e cancella tutti i post (estate_property) che <strong>NON hanno</strong> un record nella tracking table.
            </p>
            <div class="alert alert-warning d-flex align-items-start mb-3" role="alert">
                <span class="dashicons dashicons-warning me-2 mt-1"></span>
                <div>
                    <strong>ATTENZIONE:</strong> Cancellazione permanente! Gli hook WP puliscono anche tracking e immagini.
                </div>
            </div>
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-outline-secondary" id="scan-orphan-posts">
                    <span class="dashicons dashicons-search"></span> Scansiona Post Orfani
                </button>
                <button type="button" class="btn btn-danger" id="cleanup-orphan-posts">
                    <span class="dashicons dashicons-trash"></span> Cancella Post Orfani
                </button>
            </div>
            <div id="orphan-posts-report" class="mt-3"></div>
        </div>
    </div>
</div>
