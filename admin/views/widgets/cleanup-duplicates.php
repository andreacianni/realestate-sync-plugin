<?php
/**
 * Widget: Cleanup Duplicate Properties
 * Tab: Strumenti
 * User: Tecnico (Developer Mode)
 */

if (!defined('ABSPATH')) exit;
?>

<!--
╔═══════════════════════════════════════════════════════════════════╗
║ WIDGET: CLEANUP POST DUPLICATI                                    ║
╠═══════════════════════════════════════════════════════════════════╣
║ UTENTE: Tecnico                                                   ║
║ SCOPO: Trova e cancella post duplicati basati su property_import_id ║
║                                                                   ║
║ AZIONI:                                                           ║
║  - Scansiona per trovare estate_property con stesso import_id     ║
║  - Mostra lista duplicati con link frontend                       ║
║  - Cancella singolo post duplicato                                ║
║  - Cancella tutti i duplicati                                     ║
║  - Cancella vecchi (mantiene il più recente)                      ║
║                                                                   ║
║ MANIPOLA:                                                         ║
║  - wp_posts: estate_property (read/delete)                        ║
║  - wp_postmeta: property_import_id (read)                         ║
║  - Hook WP before_delete_post rimuove tracking e media            ║
║                                                                   ║
║ CRITICO: Sì (cancellazione permanente, no undo)                   ║
╚═══════════════════════════════════════════════════════════════════╝
-->
<div class="card shadow-sm rounded-3 border-1 p-0">
    <div class="card-header bg-danger bg-opacity-10 border-0 py-3">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <span class="dashicons dashicons-image-filter me-2"></span>
            <?php _e('Cleanup Post Duplicati', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <p class="text-muted mb-3">
            Trova e cancella post duplicati (stesso <code>property_import_id</code>).
        </p>

        <div class="alert alert-info d-flex align-items-start mb-4" role="alert">
            <span class="dashicons dashicons-info me-2 mt-1"></span>
            <div>
                <strong>Come funziona:</strong>
                <ul class="mb-0 mt-2" style="font-size: 0.9rem;">
                    <li>Cerca post <code>estate_property</code> con stesso <code>property_import_id</code></li>
                    <li>"Cancella vecchi" mantiene il post più recente (per import_id)</li>
                    <li>"Cancella tutti" rimuove TUTTI i duplicati trovati</li>
                    <li>Cancellazione permanente (hook WP pulisce tracking e immagini)</li>
                </ul>
            </div>
        </div>

        <!-- Scan Button -->
        <div class="d-grid mb-3">
            <button type="button" class="btn btn-primary" id="scan-duplicates">
                <span class="dashicons dashicons-search"></span>
                <?php _e('Cerca Duplicati', 'realestate-sync'); ?>
            </button>
        </div>

        <!-- Results Section (hidden initially) -->
        <div id="duplicates-results" class="d-none">
            <div id="duplicates-summary" class="alert alert-warning mb-3">
                <!-- Populated via JS -->
            </div>

            <!-- Duplicates List -->
            <div id="duplicates-list" class="mb-3" style="max-height: 400px; overflow-y: auto;">
                <!-- Populated via JS -->
            </div>

            <!-- Bulk Actions -->
            <div class="d-grid gap-2">
                <button type="button" class="btn btn-danger" id="delete-all-duplicates">
                    <span class="dashicons dashicons-trash"></span>
                    <?php _e('Cancella Tutti i Duplicati', 'realestate-sync'); ?>
                </button>
                <button type="button" class="btn btn-warning" id="delete-old-duplicates">
                    <span class="dashicons dashicons-clock"></span>
                    <?php _e('Cancella Vecchi (Mantieni Più Recente)', 'realestate-sync'); ?>
                </button>
            </div>
        </div>

        <!-- Action Result -->
        <div id="duplicates-action-result" class="mt-3 d-none">
            <!-- Populated via JS -->
        </div>
    </div>
</div>
