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
<div class="rs-queue-section">
    <h4><span class="dashicons dashicons-list-view"></span> <?php _e('Gestione Queue Import', 'realestate-sync'); ?></h4>
    <p>Controlla lo stato dell'ultimo import e gestisci eventuali elementi rimasti in sospeso.</p>

    <!-- Last Import Status -->
    <div id="last-import-status">
        <h5>
            <span class="dashicons dashicons-database-import"></span> Ultimo Import
        </h5>

        <table>
            <tr>
                <td>Session ID:</td>
                <td id="import-session-id">-</td>
            </tr>
            <tr>
                <td>Data Inizio:</td>
                <td id="import-start-time">-</td>
            </tr>
            <tr>
                <td>Stato Processo:</td>
                <td id="import-process-status">-</td>
            </tr>
            <tr>
                <td>Totale Elementi:</td>
                <td id="import-total-items">-</td>
            </tr>
            <tr>
                <td>Completati:</td>
                <td id="import-completed-items">-</td>
            </tr>
            <tr>
                <td>Rimanenti:</td>
                <td id="import-remaining-items">-</td>
            </tr>
            <tr>
                <td>Progressione:</td>
                <td id="import-progress-bar">
                    <div>
                        <div id="import-progress-fill"></div>
                    </div>
                    <span id="import-progress-text">0%</span>
                </td>
            </tr>
        </table>

        <div>
            <button type="button" class="rs-button-secondary" id="refresh-import-status">
                <span class="dashicons dashicons-update"></span> <?php _e('Aggiorna Stato', 'realestate-sync'); ?>
            </button>
        </div>
    </div>

    <!-- Pending/Stuck Items Alert -->
    <div id="pending-items-alert" class="rs-hidden">
        <h5>
            <span class="dashicons dashicons-warning"></span> ⚠️ PROCESSO CHIUSO - ELEMENTI IN SOSPESO
        </h5>
        <p id="pending-items-message">
            <!-- Populated via JS -->
        </p>

        <div>
            <button type="button" class="rs-button-primary" id="show-pending-details">
                <span class="dashicons dashicons-visibility"></span> Vedi Dettaglio
            </button>
            <button type="button" class="rs-button-primary" id="retry-pending-items">
                <span class="dashicons dashicons-controls-repeat"></span> Resetta a Pending e Riprocessa
            </button>
            <button type="button" class="rs-button-danger" id="delete-pending-items">
                <span class="dashicons dashicons-trash"></span> Elimina dalla Queue
            </button>
        </div>

        <!-- Pending Items List (expandable) -->
        <div id="pending-items-list" class="rs-hidden">
            <!-- Populated via JS -->
        </div>
    </div>

    <!-- Clear All Queue -->
    <div>
        <h5>
            <span class="dashicons dashicons-trash"></span> Pulizia Completa Queue
        </h5>
        <p>
            Rimuove TUTTI gli elementi dalla queue (utile per ricominciare da zero dopo aver risolto i problemi).
        </p>
        <button type="button" class="rs-button-danger" id="clear-all-queue">
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
    <div>
        <h5>
            <span class="dashicons dashicons-admin-tools"></span> 🧹 <?php _e('Cleanup Post Orfani', 'realestate-sync'); ?>
        </h5>
        <p>
            Trova e cancella tutti i post (estate_property) che <strong>NON hanno</strong> un record nella tracking table.
        </p>
        <p>
            ⚠️ <strong>ATTENZIONE:</strong> Cancellazione permanente! Gli hook WP puliscono anche tracking e immagini.
        </p>
        <div>
            <button type="button" class="rs-button-secondary" id="scan-orphan-posts">
                <span class="dashicons dashicons-search"></span> Scansiona Post Orfani
            </button>
            <button type="button" class="rs-button-danger" id="cleanup-orphan-posts">
                <span class="dashicons dashicons-trash"></span> Cancella Post Orfani
            </button>
        </div>
        <div id="orphan-posts-report"></div>
    </div>
</div>
