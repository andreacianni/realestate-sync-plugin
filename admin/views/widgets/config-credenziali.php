<?php
/**
 * Widget: Configurazione Credenziali Download XML
 * Tab: Setting
 * User: Admin Non Tecnico + Tecnico
 */

if (!defined('ABSPATH')) exit;
?>

<!--
╔═══════════════════════════════════════════════════════════════════╗
║ WIDGET: CONFIGURAZIONE CREDENZIALI DOWNLOAD XML                   ║
╠═══════════════════════════════════════════════════════════════════╣
║ UTENTE: Admin Non Tecnico + Tecnico (setup iniziale)             ║
║ SCOPO: Configura credenziali per scaricare XML dal gestionale    ║
║                                                                   ║
║ AZIONI UTENTE:                                                    ║
║  - Modifica URL, username, password per XML feed                  ║
║  - Seleziona sorgente credenziali (hardcoded/database)            ║
║  - Testa connessione al server XML                                ║
║                                                                   ║
║ MANIPOLA:                                                         ║
║  - wp_options: realestate_sync_xml_url (read/write)               ║
║  - wp_options: realestate_sync_xml_user (read/write)              ║
║  - wp_options: realestate_sync_xml_pass (read/write)              ║
║  - wp_options: realestate_sync_credential_source (read/write)     ║
║                                                                   ║
║ VISIBILITÀ: Sempre                                                ║
║ FREQUENZA USO: Setup iniziale, poi raramente                      ║
║ CRITICO: Sì (credenziali errate = import falliti)                 ║
╚═══════════════════════════════════════════════════════════════════╝
-->
<div class="card shadow-sm rounded-3 border-1 p-0">
    <div class="card-header bg-danger bg-opacity-10 border-0 py-3">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <span class="dashicons dashicons-admin-generic me-2"></span>
            <?php _e('Configurazione Credenziali Download XML', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <div class="alert alert-info mb-4">
            <span class="dashicons dashicons-database"></span>
            Le credenziali sono salvate e lette dal database di WordPress.
        </div>

        <form id="rs-xml-credentials-form" method="post">
            <?php wp_nonce_field('realestate_sync_xml_nonce', 'xml_nonce'); ?>

            <div class="mb-3">
                <label for="xml_url" class="form-label fw-semibold">XML URL:</label>
                <input type="text" id="xml_url" name="xml_url" class="form-control" value="<?php echo esc_attr(get_option('realestate_sync_xml_url', '')); ?>" placeholder="https://www.gestionaleimmobiliare.it/export/xml/..." readonly>
            </div>

            <div class="mb-3">
                <label for="xml_user" class="form-label fw-semibold">XML Username:</label>
                <input type="text" id="xml_user" name="xml_user" class="form-control" value="<?php echo esc_attr(get_option('realestate_sync_xml_user', '')); ?>" placeholder="username" readonly>
            </div>

            <div class="mb-4">
                <label for="xml_pass" class="form-label fw-semibold">XML Password:</label>
                <input type="text" id="xml_pass" name="xml_pass" class="form-control" value="<?php echo esc_attr(get_option('realestate_sync_xml_pass', '')); ?>" placeholder="password" readonly>
                <div class="form-text">Password visibile in chiaro per facilitare verifica</div>
            </div>

            <div class="d-grid gap-2 mb-3">
                <!-- Edit Mode Buttons -->
                <button type="button" class="btn btn-outline-secondary" id="rs-xml-edit-btn">
                    <span class="dashicons dashicons-edit"></span> Modifica Credenziali
                </button>

                <!-- Save/Cancel Buttons (hidden by default) -->
                <div id="rs-xml-save-cancel-btns" class="d-grid gap-2">
                    <button type="submit" class="btn btn-danger">
                        <span class="dashicons dashicons-yes"></span> Salva Credenziali
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="rs-xml-cancel-btn">
                        <span class="dashicons dashicons-no"></span> Annulla
                    </button>
                </div>
            </div>
        </form>

        <!-- Test Connection Button -->
        <div class="d-grid">
            <button type="button" class="btn btn-outline-primary" id="rs-test-connection">
                <span class="dashicons dashicons-networking"></span> Test Connessione XML
            </button>
            <div id="rs-test-connection-result" class="mt-2"></div>
        </div>
    </div>
</div>
