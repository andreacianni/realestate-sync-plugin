<?php
/**
 * Widget: Log & Monitoraggio
 * Tab: Dashboard
 * User: Admin + Tecnico
 */

if (!defined('ABSPATH')) exit;
?>

<!--
╔═══════════════════════════════════════════════════════════════════╗
║ WIDGET: LOG & MONITORAGGIO                                        ║
╠═══════════════════════════════════════════════════════════════════╣
║ UTENTE: Entrambi (Admin + Tecnico)                               ║
║ SCOPO: Visualizza log sistema per troubleshooting                ║
║                                                                   ║
║ AZIONI UTENTE:                                                    ║
║  - Visualizza log file in browser                                 ║
║  - Scarica log file sul computer                                  ║
║  - Cancella log file (reset)                                      ║
║  - System Check: verifica configurazione WP                       ║
║                                                                   ║
║ MANIPOLA:                                                         ║
║  - wp-content/debug.log (read/write/delete)                       ║
║  - Nessuna manipolazione database                                 ║
║                                                                   ║
║ VISIBILITÀ: Sempre                                                ║
║ FREQUENZA USO: Troubleshooting, post-import verification          ║
║ CRITICO: No (informativo, no modifiche dati)                      ║
╚═══════════════════════════════════════════════════════════════════╝
-->
<div class="card shadow-sm rounded-3 border-1 p-0">
    <div class="card-header bg-info bg-opacity-10 border-0 py-3">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <span class="dashicons dashicons-list-view me-2"></span>
            <?php _e('Log & Monitoraggio', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <div class="d-grid gap-2 mb-3">
            <button type="button" class="btn btn-outline-primary" id="view-logs">
                <span class="dashicons dashicons-media-text"></span>
                <?php _e('Visualizza Log', 'realestate-sync'); ?>
            </button>
            <button type="button" class="btn btn-outline-secondary" id="download-logs">
                <span class="dashicons dashicons-download"></span>
                <?php _e('Scarica Log', 'realestate-sync'); ?>
            </button>
            <button type="button" class="btn btn-outline-warning" id="clear-logs">
                <span class="dashicons dashicons-trash"></span>
                <?php _e('Cancella Log', 'realestate-sync'); ?>
            </button>
            <button type="button" class="btn btn-outline-info" id="system-check">
                <span class="dashicons dashicons-admin-tools"></span>
                <?php _e('Verifica Sistema', 'realestate-sync'); ?>
            </button>
        </div>

        <!-- Log Viewer -->
        <div id="log-viewer" class="d-none">
            <pre class="p-3 bg-dark text-light rounded-2" id="log-content" style="max-height: 400px; overflow-y: auto;">Caricamento log...</pre>
        </div>
    </div>
</div>
