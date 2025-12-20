<?php
/**
 * Widget: Developer Mode Toggle
 * Tab: Strumenti
 * User: Tutti (preferenza persistente per utente)
 */

if (!defined('ABSPATH')) exit;

// Get developer mode preference from user meta
$developer_mode = get_user_meta(get_current_user_id(), 'realestate_sync_developer_mode', true);
$developer_mode = filter_var($developer_mode, FILTER_VALIDATE_BOOLEAN);
?>

<!--
╔═══════════════════════════════════════════════════════════════════╗
║ WIDGET: MODALITÀ SVILUPPATORE                                     ║
╠═══════════════════════════════════════════════════════════════════╣
║ SCOPO: Toggle visibilità strumenti tecnici avanzati              ║
║ SALVA: user_meta (persistente per utente)                        ║
╚═══════════════════════════════════════════════════════════════════╝
-->
<div class="card shadow-sm rounded-3 border-1 p-0">
    <div class="card-header bg-secondary bg-opacity-10 border-0 py-3">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <span class="dashicons dashicons-admin-tools me-2"></span>
            <?php _e('Modalità Visualizzazione', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <div class="form-check form-switch mb-3">
            <input type="checkbox" class="form-check-input" role="switch" id="developer-mode-toggle" <?php checked($developer_mode); ?>>
            <label class="form-check-label" for="developer-mode-toggle">
                <strong>
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php _e('Modalità Sviluppatore', 'realestate-sync'); ?>
                </strong>
                <div class="text-muted small mt-1">
                    <?php _e('Mostra strumenti tecnici avanzati per debug e gestione sistema', 'realestate-sync'); ?>
                </div>
            </label>
        </div>

        <div id="developer-mode-status" class="alert alert-<?php echo $developer_mode ? 'success' : 'secondary'; ?> mb-0">
            <div class="d-flex align-items-start">
                <span class="dashicons dashicons-info me-2 mt-1"></span>
                <span id="developer-mode-message">
                    <?php echo $developer_mode ? __('Modalità Sviluppatore attiva - Strumenti tecnici visibili', 'realestate-sync') : __('Modalità Utente Standard - Solo strumenti essenziali', 'realestate-sync'); ?>
                </span>
            </div>
        </div>
    </div>
</div>
