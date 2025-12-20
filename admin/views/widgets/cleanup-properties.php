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
<div class="card shadow-sm rounded-3 border-1 p-0">
    <div class="card-header bg-danger bg-opacity-10 border-0 py-3">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <span class="dashicons dashicons-trash me-2"></span>
            <?php _e('Cleanup Proprietà Senza Immagini', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <p class="text-muted mb-4">Trova e rimuovi proprietà senza featured image. Il tracking viene cancellato automaticamente.</p>

        <!-- Step 1: Analisi -->
        <div class="mb-4">
            <h6 class="fw-bold text-success">Step 1: Analisi (Safe)</h6>
            <p class="small text-muted">Prima controlla quante proprietà senza immagini ci sono - nessuna cancellazione.</p>
            <button type="button" class="btn btn-outline-primary" id="analyze-no-images">
                <span class="dashicons dashicons-search"></span>
                Analizza Proprietà Senza Immagini
            </button>
        </div>

        <!-- Analysis Results -->
        <div id="no-images-analysis" class="d-none mb-4">
            <h6 class="fw-bold">Risultati Analisi:</h6>
            <div id="no-images-analysis-content" class="p-3 bg-light rounded-2"></div>
        </div>

        <!-- Step 2: Cancellazione -->
        <div id="cleanup-actions" class="d-none">
            <h6 class="fw-bold text-danger">Step 2: Cancellazione</h6>
            <div class="alert alert-warning d-flex align-items-start mb-3" role="alert">
                <span class="dashicons dashicons-warning me-2 mt-1"></span>
                <div>
                    <strong>ATTENZIONE:</strong> Questa azione cancellerà le proprietà trovate.
                    Il tracking verrà rimosso automaticamente.
                </div>
            </div>
            <div class="d-grid gap-2 mb-3">
                <button type="button" class="btn btn-danger" id="cleanup-no-images-trash">
                    <span class="dashicons dashicons-trash"></span>
                    Sposta nel Cestino
                </button>
                <button type="button" class="btn btn-outline-danger" id="cleanup-no-images-permanent">
                    <span class="dashicons dashicons-dismiss"></span>
                    Cancellazione Permanente
                </button>
            </div>
            <div class="form-text">
                <strong>Cestino:</strong> Recuperabili da WP Admin > Cestino<br>
                <strong>Permanente:</strong> Non recuperabili (usare con cautela!)
            </div>
        </div>

        <!-- Cleanup Results -->
        <div id="cleanup-results" class="d-none mt-4">
            <h6 class="fw-bold">Risultati Cleanup:</h6>
            <div id="cleanup-results-content" class="p-3 bg-light rounded-2"></div>
        </div>
    </div>
</div>
