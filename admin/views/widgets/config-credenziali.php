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
<div class="rs-card">
    <h3><span class="dashicons dashicons-admin-generic"></span> <?php _e('Configurazione Credenziali Download XML', 'realestate-sync'); ?></h3>

    <!-- Credential Source Toggle -->
    <div>
        <label>
            <span class="dashicons dashicons-admin-generic"></span> Sorgente Credenziali:
        </label>
        <?php
        $credential_source = get_option('realestate_sync_credential_source', 'hardcoded');
        ?>
        <label>
            <input type="radio" name="credential_source" value="hardcoded"
                   <?php checked($credential_source, 'hardcoded'); ?>
                   id="rs-cred-source-hardcoded">
            <strong>Usa credenziali hardcoded</strong> (sistema attuale)
        </label>
        <label>
            <input type="radio" name="credential_source" value="database"
                   <?php checked($credential_source, 'database'); ?>
                   id="rs-cred-source-database">
            <strong>Usa credenziali database</strong> (nuovo sistema)
        </label>
    </div>

    <form id="rs-xml-credentials-form" method="post">
        <?php wp_nonce_field('realestate_sync_xml_nonce', 'xml_nonce'); ?>

        <table class="rs-form-table">
            <tr>
                <th>XML URL:</th>
                <td>
                    <input type="text" id="xml_url" name="xml_url" class="rs-input"
                           value="<?php echo esc_attr(get_option('realestate_sync_xml_url', '')); ?>"
                           placeholder="https://www.gestionaleimmobiliare.it/export/xml/..."
                           readonly>
                </td>
            </tr>
            <tr>
                <th>XML Username:</th>
                <td>
                    <input type="text" id="xml_user" name="xml_user" class="rs-input"
                           value="<?php echo esc_attr(get_option('realestate_sync_xml_user', '')); ?>"
                           placeholder="username"
                           readonly>
                </td>
            </tr>
            <tr>
                <th>XML Password:</th>
                <td>
                    <input type="text" id="xml_pass" name="xml_pass" class="rs-input"
                           value="<?php echo esc_attr(get_option('realestate_sync_xml_pass', '')); ?>"
                           placeholder="password"
                           readonly>
                    <br><small>Password visibile in chiaro per facilitare verifica</small>
                </td>
            </tr>
        </table>

        <div>
            <!-- Edit Mode Buttons -->
            <button type="button" class="rs-button-secondary" id="rs-xml-edit-btn">
                <span class="dashicons dashicons-edit"></span> Modifica Credenziali
            </button>

            <!-- Save/Cancel Buttons (hidden by default) -->
            <div id="rs-xml-save-cancel-btns">
                <button type="submit" class="rs-button-primary">
                    <span class="dashicons dashicons-yes"></span> Salva Credenziali
                </button>
                <button type="button" class="rs-button-secondary" id="rs-xml-cancel-btn">
                    <span class="dashicons dashicons-no"></span> Annulla
                </button>
            </div>
        </div>
    </form>

    <!-- Test Connection Button -->
    <div>
        <button type="button" class="rs-button-secondary" id="rs-test-connection">
            <span class="dashicons dashicons-networking"></span> Test Connessione XML
        </button>
        <div id="rs-test-connection-result"></div>
    </div>
</div>
