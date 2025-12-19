<?php
/**
 * Widget: Configurazione Email Notifiche
 * Tab: Setting
 * User: Admin
 */

if (!defined('ABSPATH')) exit;

// Get current email settings
$email_enabled = get_option('realestate_sync_email_enabled', false);
$email_attach_report = get_option('realestate_sync_email_attach_report', false);
$email_to = get_option('realestate_sync_email_to', get_option('admin_email'));
$email_cc = get_option('realestate_sync_email_cc', '');
?>

<!--
╔═══════════════════════════════════════════════════════════════════╗
║ WIDGET: CONFIGURAZIONE EMAIL NOTIFICHE                           ║
╠═══════════════════════════════════════════════════════════════════╣
║ SCOPO: Configura notifiche email per import completati/falliti   ║
║ MANIPOLA:                                                         ║
║  - wp_options: realestate_sync_email_enabled (bool)               ║
║  - wp_options: realestate_sync_email_attach_report (bool)         ║
║  - wp_options: realestate_sync_email_to (email)                   ║
║  - wp_options: realestate_sync_email_cc (comma-separated)         ║
╚═══════════════════════════════════════════════════════════════════╝
-->
<div class="rs-card">
    <h3>
        <span class="dashicons dashicons-email"></span>
        <?php _e('Configurazione Email', 'realestate-sync'); ?>
    </h3>

    <p>
        <?php _e('Ricevi notifiche email al termine di ogni import automatico.', 'realestate-sync'); ?>
    </p>

    <!-- Enable Email Notifications -->
    <div>
        <label>
            <input type="checkbox" id="email-enabled" <?php checked($email_enabled); ?>
>
            <strong>
                <span class="dashicons dashicons-email"></span>
                <?php _e('Abilita Notifiche Email', 'realestate-sync'); ?>
            </strong>
        </label>
        <small>
            <?php _e('Invia email al termine di ogni import automatico (successo o errore)', 'realestate-sync'); ?>
        </small>
    </div>

    <!-- Email Configuration -->
    <div id="email-config">

        <!-- Attach Report Option -->
        <div>
            <label>
                <input type="checkbox" id="email-attach-report" <?php checked($email_attach_report); ?>
>
                <strong>
                    <span class="dashicons dashicons-media-spreadsheet"></span>
                    <?php _e('Allega Report Dettagliato', 'realestate-sync'); ?>
                </strong>
            </label>
            <small>
                <?php _e('Allega file di log con dettagli completi dell\'import', 'realestate-sync'); ?>
            </small>
        </div>

        <!-- TO Email Field -->
        <div>
            <label>
                <span class="dashicons dashicons-email"></span>
                <?php _e('Destinatario Principale (TO):', 'realestate-sync'); ?>
            </label>
            <input type="email" id="email-to" value="<?php echo esc_attr($email_to); ?>"
                   placeholder="admin@example.com"
>
            <small>
                <?php _e('Email primaria che riceverà le notifiche', 'realestate-sync'); ?>
            </small>
        </div>

        <!-- CC Email Field -->
        <div>
            <label>
                <span class="dashicons dashicons-groups"></span>
                <?php _e('Copia Conoscenza (CC):', 'realestate-sync'); ?>
                <span>
                    (<?php _e('opzionale', 'realestate-sync'); ?>)
                </span>
            </label>
            <input type="text" id="email-cc" value="<?php echo esc_attr($email_cc); ?>"
                   placeholder="manager@example.com, developer@example.com"
>
            <small>
                <?php _e('Email aggiuntive separate da virgola (es: email1@example.com, email2@example.com)', 'realestate-sync'); ?>
            </small>
        </div>

        <!-- Save Button -->
        <div>
            <button type="button" class="rs-button-primary" id="save-email-config">
                <span class="dashicons dashicons-saved"></span> <?php _e('Salva Configurazione Email', 'realestate-sync'); ?>
            </button>
        </div>

        <!-- Status Message -->
        <div id="email-config-status"></div>
    </div>
</div>
