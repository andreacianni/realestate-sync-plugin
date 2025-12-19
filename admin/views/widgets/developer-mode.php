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
<div class="rs-card">
    <h3>
        <span class="dashicons dashicons-admin-tools"></span>
        <?php _e('Modalità Visualizzazione', 'realestate-sync'); ?>
    </h3>

    <label>
        <input type="checkbox" id="developer-mode-toggle" <?php checked($developer_mode); ?>
>
        <div>
            <strong>
                <span class="dashicons dashicons-admin-generic"></span>
                <?php _e('Modalità Sviluppatore', 'realestate-sync'); ?>
            </strong>
            <span>
                <?php _e('Mostra strumenti tecnici avanzati per debug e gestione sistema', 'realestate-sync'); ?>
            </span>
        </div>
    </label>

    <div id="developer-mode-status">
        <span class="dashicons dashicons-info"></span>
        <span id="developer-mode-message">
            <?php echo $developer_mode ? __('Modalità Sviluppatore attiva - Strumenti tecnici visibili', 'realestate-sync') : __('Modalità Utente Standard - Solo strumenti essenziali', 'realestate-sync'); ?>
        </span>
    </div>
</div>
