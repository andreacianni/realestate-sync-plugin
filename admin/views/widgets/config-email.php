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
<div class="card shadow-sm rounded-3 border-1 p-0">
    <div class="card-header bg-primary bg-opacity-10 border-0 py-3">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <span class="dashicons dashicons-email me-2"></span>
            <?php _e('Configurazione Email', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <p class="text-muted mb-4">
            <?php _e('Ricevi notifiche email al termine di ogni import automatico.', 'realestate-sync'); ?>
        </p>

        <!-- Enable Email Notifications -->
        <div class="form-check form-switch mb-4">
            <input type="checkbox" class="form-check-input" role="switch" id="email-enabled" <?php checked($email_enabled); ?>>
            <label class="form-check-label" for="email-enabled">
                <strong>
                    <span class="dashicons dashicons-email"></span>
                    <?php _e('Abilita Notifiche Email', 'realestate-sync'); ?>
                </strong>
                <div class="form-text">
                    <?php _e('Invia email al termine di ogni import automatico (successo o errore)', 'realestate-sync'); ?>
                </div>
            </label>
        </div>

        <!-- Email Configuration -->
        <div id="email-config">

            <!-- Attach Report Option -->
            <div class="form-check mb-3">
                <input type="checkbox" class="form-check-input" id="email-attach-report" <?php checked($email_attach_report); ?>>
                <label class="form-check-label" for="email-attach-report">
                    <strong>
                        <span class="dashicons dashicons-media-spreadsheet"></span>
                        <?php _e('Allega Report Dettagliato', 'realestate-sync'); ?>
                    </strong>
                    <div class="form-text">
                        <?php _e('Allega file di log con dettagli completi dell\'import', 'realestate-sync'); ?>
                    </div>
                </label>
            </div>

            <!-- TO Email Field -->
            <div class="mb-3">
                <label for="email-to" class="form-label fw-semibold">
                    <span class="dashicons dashicons-email"></span>
                    <?php _e('Destinatario Principale (TO):', 'realestate-sync'); ?>
                </label>
                <input type="email" class="form-control" id="email-to" value="<?php echo esc_attr($email_to); ?>" placeholder="admin@example.com">
                <div class="form-text">
                    <?php _e('Email primaria che riceverà le notifiche', 'realestate-sync'); ?>
                </div>
            </div>

            <!-- CC Email Field -->
            <div class="mb-4">
                <label for="email-cc" class="form-label fw-semibold">
                    <span class="dashicons dashicons-groups"></span>
                    <?php _e('Copia Conoscenza (CC):', 'realestate-sync'); ?>
                    <span class="text-muted">(<?php _e('opzionale', 'realestate-sync'); ?>)</span>
                </label>
                <input type="text" class="form-control" id="email-cc" value="<?php echo esc_attr($email_cc); ?>" placeholder="manager@example.com, developer@example.com">
                <div class="form-text">
                    <?php _e('Email aggiuntive separate da virgola (es: email1@example.com, email2@example.com)', 'realestate-sync'); ?>
                </div>
            </div>

            <!-- Save Button -->
            <button type="button" class="btn btn-primary w-100" id="save-email-config">
                <span class="dashicons dashicons-saved"></span>
                <?php _e('Salva Configurazione Email', 'realestate-sync'); ?>
            </button>

            <!-- Status Message -->
            <div id="email-config-status" class="mt-3"></div>
        </div>
    </div>
</div>
