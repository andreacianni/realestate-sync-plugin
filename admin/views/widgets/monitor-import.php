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
            <div class="row g-4 align-items-start rs-monitor-columns">
                <div class="col-12 col-xl-7">
                    <div class="rs-monitor-panel rs-monitor-panel--technical">
                        <div class="rs-monitor-panel__eyebrow">Stato tecnico processo</div>
                        <p class="text-muted mb-3">
                            <?php _e('Stato dell\'ultimo processo di import in esecuzione o completato.', 'realestate-sync'); ?>
                        </p>

                        <table class="table table-sm mb-0 rs-monitor-table rs-monitor-table--technical">
                            <tbody>
                                <tr>
                                    <th scope="row">Session ID</th>
                                    <td id="import-session-id" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Data inizio</th>
                                    <td id="import-start-time" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Stato processo</th>
                                    <td id="import-process-status" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Fase sessione</th>
                                    <td id="import-session-phase" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Stato delete</th>
                                    <td id="delete-state-status" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Modalita delete</th>
                                    <td id="delete-runtime-mode" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Kill switch</th>
                                    <td id="delete-runtime-kill-switch" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Cap delete</th>
                                    <td id="delete-runtime-cap" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Contatori delete</th>
                                    <td id="delete-state-counters" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Totale elementi</th>
                                    <td id="import-total-items" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Completati</th>
                                    <td id="import-completed-items" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Rimanenti</th>
                                    <td id="import-remaining-items" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Progressione</th>
                                    <td id="import-progress-bar">
                                        <div class="progress" style="height: 25px;">
                                            <div id="import-progress-fill" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                                        </div>
                                        <span id="import-progress-text" class="small text-muted d-block mt-1">0%</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="d-grid mt-3">
                            <button type="button" class="btn btn-outline-primary" id="refresh-import-status">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Visualizza Stato', 'realestate-sync'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-5">
                    <div id="import-functional-stats" class="rs-monitor-panel rs-monitor-panel--functional rs-hidden"></div>
                </div>
            </div>
        </div>
    </div>
</div>
