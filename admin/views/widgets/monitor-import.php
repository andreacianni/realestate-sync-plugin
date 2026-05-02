<?php
/**
 * Widget: Monitor Ultimo Import
 * Tab: Dashboard
 * User: Admin + Tecnico
 */

if (!defined('ABSPATH')) exit;
?>

<!--
╔═══════════════════════════════════════════════════════════════════╗
║ WIDGET: MONITOR ULTIMO IMPORT                                     ║
╠═══════════════════════════════════════════════════════════════════╣
║ SCOPO: Visualizza stato real-time ultimo import in esecuzione     ║
║ FONTE: wp_realestate_import_queue (status, progressione)          ║
╚═══════════════════════════════════════════════════════════════════╝
-->
<div class="card shadow-sm rounded-3 border-1 p-0">
    <div class="card-header bg-info bg-opacity-10 border-0 py-3">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <span class="dashicons dashicons-database-import me-2"></span>
            <?php _e('Monitor Ultimo Import', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <p class="text-muted mb-4">
            <?php _e('Stato dell\'ultimo processo di import in esecuzione o completato.', 'realestate-sync'); ?>
        </p>

        <div id="last-import-status">
            <table class="table table-sm">
                <tbody>
                    <tr>
                        <td class="fw-semibold">Session ID:</td>
                        <td id="import-session-id" class="text-muted">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Data Inizio:</td>
                        <td id="import-start-time" class="text-muted">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Stato Processo:</td>
                        <td id="import-process-status" class="text-muted">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Fase Sessione:</td>
                        <td id="import-session-phase" class="text-muted">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Stato Delete:</td>
                        <td id="delete-state-status" class="text-muted">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Modalita Delete:</td>
                        <td id="delete-runtime-mode" class="text-muted">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Kill Switch:</td>
                        <td id="delete-runtime-kill-switch" class="text-muted">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Cap Delete:</td>
                        <td id="delete-runtime-cap" class="text-muted">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Contatori Delete:</td>
                        <td id="delete-state-counters" class="text-muted">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Totale Elementi:</td>
                        <td id="import-total-items" class="text-muted">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Completati:</td>
                        <td id="import-completed-items" class="text-muted">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Rimanenti:</td>
                        <td id="import-remaining-items" class="text-muted">-</td>
                    </tr>
                    <tr>
                        <td class="fw-semibold">Progressione:</td>
                        <td id="import-progress-bar">
                            <div class="progress" style="height: 25px;">
                                <div id="import-progress-fill" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                            </div>
                            <span id="import-progress-text" class="small text-muted d-block mt-1">0%</span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div id="import-functional-stats" class="mt-4" style="display:none;"></div>

            <div class="d-grid mt-3">
                <button type="button" class="btn btn-outline-primary" id="refresh-import-status">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Visualizza Stato', 'realestate-sync'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
