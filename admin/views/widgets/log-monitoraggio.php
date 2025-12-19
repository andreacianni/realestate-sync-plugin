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
<div class="rs-card">
    <h3><span class="dashicons dashicons-list-view"></span> <?php _e('Log & Monitoraggio', 'realestate-sync'); ?></h3>

    <div>
        <button type="button" class="rs-button-secondary" id="view-logs">
            <span class="dashicons dashicons-media-text"></span> <?php _e('Visualizza Log', 'realestate-sync'); ?>
        </button>
        <button type="button" class="rs-button-secondary" id="download-logs">
            <span class="dashicons dashicons-download"></span> <?php _e('Scarica Log', 'realestate-sync'); ?>
        </button>
        <button type="button" class="rs-button-secondary" id="clear-logs">
            <span class="dashicons dashicons-trash"></span> <?php _e('Cancella Log', 'realestate-sync'); ?>
        </button>
        <button type="button" class="rs-button-secondary" id="system-check">
            <span class="dashicons dashicons-admin-tools"></span> <?php _e('Verifica Sistema', 'realestate-sync'); ?>
        </button>
    </div>

    <!-- Log Viewer -->
    <div id="log-viewer" class="rs-hidden">
        <div>
            <pre id="log-content">Caricamento log...</pre>
        </div>
    </div>
</div>
