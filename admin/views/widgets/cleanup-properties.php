<?php
/**
 * Widget: Cleanup Proprietà Senza Immagini
 * Tab: Strumenti
 * User: Admin + Tecnico
 */

if (!defined('ABSPATH')) exit;
?>

<!--
╔═══════════════════════════════════════════════════════════════════╗
║ WIDGET: CLEANUP PROPRIETÀ SENZA IMMAGINI                          ║
╠═══════════════════════════════════════════════════════════════════╣
║ UTENTE: Entrambi (Admin + Tecnico)                               ║
║ SCOPO: Rimuovi proprietà senza featured image                    ║
║                                                                   ║
║ AZIONI UTENTE:                                                    ║
║  - Step 1: Analizza (safe, no deletion)                           ║
║  - Step 2: Cancella (trash o permanente)                          ║
║                                                                   ║
║ MANIPOLA:                                                         ║
║  - wp_posts: estate_property WHERE _thumbnail_id IS NULL (del)    ║
║  - wp_realestate_sync_tracking: (delete via hook)                 ║
║  - Media library: (delete via hook)                               ║
║                                                                   ║
║ VISIBILITÀ: Sempre                                                ║
║ FREQUENZA USO: Manutenzione periodica qualità dati                ║
║ CRITICO: Medio (cancellazione permanente se scelta)               ║
╚═══════════════════════════════════════════════════════════════════╝
-->
<div class="rs-cleanup-section">
    <h4><span class="dashicons dashicons-trash"></span> <?php _e('Cleanup Proprietà Senza Immagini', 'realestate-sync'); ?></h4>
    <p>Trova e rimuovi proprietà senza featured image. Il tracking viene cancellato automaticamente.</p>

    <!-- Step 1: Analisi -->
    <div>
        <strong>Step 1: Analisi (Safe)</strong>
        <p>Prima controlla quante proprietà senza immagini ci sono - nessuna cancellazione.</p>
        <button type="button" class="rs-button-secondary" id="analyze-no-images">
            <span class="dashicons dashicons-search"></span> Analizza Proprietà Senza Immagini
        </button>
    </div>

    <!-- Analysis Results -->
    <div id="no-images-analysis" class="rs-hidden">
        <h5>Risultati Analisi:</h5>
        <div id="no-images-analysis-content"></div>
    </div>

    <!-- Step 2: Cancellazione -->
    <div id="cleanup-actions" class="rs-hidden">
        <strong>Step 2: Cancellazione</strong>
        <p>
            ⚠️ <strong>ATTENZIONE:</strong> Questa azione cancellerà le proprietà trovate.
            Il tracking verrà rimosso automaticamente.
        </p>
        <div>
            <button type="button" class="rs-button-danger" id="cleanup-no-images-trash">
                <span class="dashicons dashicons-trash"></span> Sposta nel Cestino
            </button>
            <button type="button" class="rs-button-danger" id="cleanup-no-images-permanent">
                <span class="dashicons dashicons-dismiss"></span> Cancellazione Permanente
            </button>
        </div>
        <small>
            <strong>Cestino:</strong> Recuperabili da WP Admin > Cestino<br>
            <strong>Permanente:</strong> Non recuperabili (usare con cautela!)
        </small>
    </div>

    <!-- Cleanup Results -->
    <div id="cleanup-results" class="rs-hidden">
        <h5>Risultati Cleanup:</h5>
        <div id="cleanup-results-content"></div>
    </div>
</div>
