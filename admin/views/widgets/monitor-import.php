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
<div class="rs-card">
    <h3><span class="dashicons dashicons-database-import"></span> <?php _e('Monitor Ultimo Import', 'realestate-sync'); ?></h3>

    <p>
        <?php _e('Stato dell\'ultimo processo di import in esecuzione o completato.', 'realestate-sync'); ?>
    </p>

    <div id="last-import-status">
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
</div>
