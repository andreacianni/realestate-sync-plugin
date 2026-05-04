<?php
/**
 * Widget: Monitor Media Cleanup
 * Tab: Dashboard
 * User: Admin + Tecnico
 */

if (!defined('ABSPATH')) exit;
?>

<!--
╔═══════════════════════════════════════════════════════════════════╗
║ WIDGET: MONITOR MEDIA CLEANUP                                     ║
╠═══════════════════════════════════════════════════════════════════╣
║ SCOPO: Visualizza stato read-only della cleanup queue media       ║
║ FONTE: realestate_media_cleanup_queue + option/lock di runtime    ║
╚═══════════════════════════════════════════════════════════════════╝
-->
<div class="card shadow-sm rounded-3 border-1 p-0">
    <div class="card-header bg-success bg-opacity-10 border-0 py-3">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <span class="dashicons dashicons-images-alt2 me-2"></span>
            <?php _e('Monitor Media Cleanup', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <p class="text-muted mb-4">
            <?php _e('Stato read-only della queue di media cleanup. Nessuna azione distruttiva da questa card.', 'realestate-sync'); ?>
        </p>

        <div id="media-cleanup-status">
            <div class="row g-4 align-items-start rs-monitor-columns">
                <div class="col-12 col-xl-7">
                    <div class="rs-monitor-panel rs-monitor-panel--technical">
                        <div class="rs-monitor-panel__eyebrow">Stato tecnico processo</div>
                        <p class="text-muted mb-3">
                            <?php _e('Stato sintetico derivato dalla queue, dalle option di runtime e dal lock worker.', 'realestate-sync'); ?>
                        </p>

                        <table class="table table-sm mb-0 rs-monitor-table rs-monitor-table--technical">
                            <tbody>
                                <tr>
                                    <th scope="row">Stato processo</th>
                                    <td id="media-cleanup-process-status" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Enabled</th>
                                    <td id="media-cleanup-monitor-enabled" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Total queue</th>
                                    <td id="media-cleanup-total" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Pending</th>
                                    <td id="media-cleanup-pending" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Processing</th>
                                    <td id="media-cleanup-processing" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Remaining</th>
                                    <td id="media-cleanup-remaining" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Done</th>
                                    <td id="media-cleanup-done" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Skipped</th>
                                    <td id="media-cleanup-skipped" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Error</th>
                                    <td id="media-cleanup-error" class="text-muted">-</td>
                                </tr>
                                <tr>
                                    <th scope="row">Ultima attività</th>
                                    <td id="media-cleanup-last-run" class="text-muted">n/d</td>
                                </tr>
                            </tbody>
                        </table>

                        <div class="d-grid mt-3">
                            <button type="button" class="btn btn-outline-primary" id="refresh-media-cleanup-status">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Visualizza Stato', 'realestate-sync'); ?>
                            </button>
                            <button type="button" class="btn btn-link text-decoration-none mt-2" id="open-media-cleanup-settings">
                                <?php _e('Modifica configurazione', 'realestate-sync'); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-5">
                    <div id="media-cleanup-summary" class="rs-monitor-panel rs-monitor-panel--functional rs-hidden">
                        <div class="rs-monitor-panel__eyebrow">Sintesi</div>
                        <div id="media-cleanup-summary-body" class="mt-2"></div>
                    </div>
                </div>
            </div>

            <div id="media-cleanup-note" class="mt-3 small text-muted">
                <?php _e('Ultimo aggiornamento manuale completato.', 'realestate-sync'); ?>
            </div>
        </div>
    </div>
</div>
