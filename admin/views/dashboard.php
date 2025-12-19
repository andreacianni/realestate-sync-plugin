<?php
/**
 * RealEstate Sync Plugin - Admin Dashboard 3-Tab System
 * FIXED VERSION - Complete 3-tab interface restored
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$current_settings = get_option('realestate_sync_settings', array());
$default_settings = include plugin_dir_path(__FILE__) . '../../config/default-settings.php';
$settings = wp_parse_args($current_settings, $default_settings);

// Get system status
$tracking_manager = new RealEstate_Sync_Tracking_Manager();
$import_stats = $tracking_manager->get_import_statistics();
?>

<div class="wrap realestate-sync-admin">
    <h1>
        <span class="dashicons dashicons-building" style="font-size: 28px; margin-right: 10px; color: #2271b1;"></span>
        RealEstate Sync Dashboard - 4-TAB SYSTEM WITH INFO TAB ✨
    </h1>

    <div id="rs-alerts-container"></div>

    <!--
    ═══════════════════════════════════════════════════════════════════════════
    NAVIGAZIONE DASHBOARD - 4 TAB
    ───────────────────────────────────────────────────────────────────────────
    TAB 1 - IMPORT: Operazioni quotidiane (admin non tecnico)
    TAB 2 - AUTOMAZIONE: Configurazione schedule import automatici
    TAB 3 - STRUMENTI: Tools tecnici e pulizia database (developer mode)
    TAB 4 - STORICO: Monitoring import passati e log di sistema
    ═══════════════════════════════════════════════════════════════════════════
    -->
    <div class="nav-tab-wrapper">
        <a href="#dashboard" class="nav-tab nav-tab-active" data-tab="dashboard">
            <span class="dashicons dashicons-download"></span> <?php _e('Import', 'realestate-sync'); ?>
        </a>
        <a href="#automazione" class="nav-tab" data-tab="automazione">
            <span class="dashicons dashicons-clock"></span> <?php _e('Automazione', 'realestate-sync'); ?>
        </a>
        <a href="#tools" class="nav-tab" data-tab="tools">
            <span class="dashicons dashicons-admin-tools"></span> <?php _e('Strumenti', 'realestate-sync'); ?>
        </a>
        <a href="#logs" class="nav-tab" data-tab="logs">
            <span class="dashicons dashicons-chart-line"></span> <?php _e('Storico & Log', 'realestate-sync'); ?>
        </a>
    </div>

    <!--
    ═══════════════════════════════════════════════════════════════════════════
    TAB 1: IMPORT - Operazioni Quotidiane
    ═══════════════════════════════════════════════════════════════════════════
    -->
    <div id="dashboard" class="tab-content rs-tab-active">
        <div class="rs-dashboard-grid">

            <!--
            ╔═══════════════════════════════════════════════════════════════════╗
            ║ WIDGET: IMPORT IMMEDIATO                                          ║
            ╠═══════════════════════════════════════════════════════════════════╣
            ║ UTENTE: Admin Non Tecnico                                         ║
            ║ SCOPO: Trigger manuale download e import da gestionale            ║
            ║ AZIONI: Scarica XML + Processa + Importa Media                    ║
            ║ MANIPOLA: Posts, Tracking, Queue, Media Library                   ║
            ║ FREQUENZA: Occasionale (backup automazione)                       ║
            ╚═══════════════════════════════════════════════════════════════════╝
            -->
            <div class="rs-card">
                <h3><span class="dashicons dashicons-download"></span> <?php _e('Import Immediato', 'realestate-sync'); ?></h3>

                <div class="rs-info-box">
                    <strong>Import Immediato</strong><br>
                    Scarica e importa immediatamente i dati XML da GestionaleImmobiliare.it
                </div>

                <div style="margin: 15px 0; padding: 12px; background: #fff3cd; border-left: 3px solid #f0ad4e; border-radius: 4px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" id="mark-as-test-manual-import" checked style="margin: 0 8px 0 0; width: 18px; height: 18px;">
                        <span style="font-weight: 500;">
                            <span class="dashicons dashicons-flag" style="color: #f0ad4e; vertical-align: middle;"></span>
                            Marca come Test Import
                        </span>
                    </label>
                    <small style="display: block; margin-top: 5px; color: #666; padding-left: 26px;">
                        Le proprietà, agenzie e media verranno marcate con flag <code>_test_import=1</code> per facile rimozione
                    </small>
                </div>

                <button type="button" class="rs-button-primary" id="start-manual-import">
                    <span class="dashicons dashicons-download"></span> <?php _e('Scarica e Importa Ora', 'realestate-sync'); ?>
                </button>

                <!-- Manual Import Log Output -->
                <div id="manual-import-log-output" class="rs-hidden" style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; max-height: 300px; overflow-y: auto;">
                    <h5>Log Processo:</h5>
                    <pre id="manual-import-log-content" style="margin: 0; font-family: 'Courier New', monospace; font-size: 12px; white-space: pre-wrap;">Avvio processo...</pre>
                </div>
            </div>

            <!--
            ╔═══════════════════════════════════════════════════════════════════╗
            ║ WIDGET: STATO ATTUALE                                             ║
            ╠═══════════════════════════════════════════════════════════════════╣
            ║ UTENTE: Admin Non Tecnico                                         ║
            ║ SCOPO: Overview rapido stato sincronizzazione                     ║
            ║                                                                   ║
            ║ MOSTRA:                                                           ║
            ║  - Totale proprietà sincronizzate                                 ║
            ║  - Ultima modifica tracking (come proxy per ultimo import)        ║
            ║  - Prossimo import automatico programmato                         ║
            ║                                                                   ║
            ║ MANIPOLA:                                                         ║
            ║  - wp_realestate_sync_tracking: (read only - count)               ║
            ║  - WP Cron: (read only - get next scheduled)                      ║
            ║                                                                   ║
            ║ VISIBILITÀ: Sempre                                                ║
            ║ FREQUENZA USO: Vista principale dashboard                         ║
            ║ CRITICO: No (informativo)                                         ║
            ║                                                                   ║
            ║ NOTE: In futuro query wp_realestate_import_sessions per stats     ║
            ╚═══════════════════════════════════════════════════════════════════╝
            -->
            <?php
            // Get next scheduled import
            $cron_manager = new RealEstate_Sync_Cron_Manager();
            $next_run_timestamp = $cron_manager->get_next_scheduled_import();
            $schedule_enabled = get_option('realestate_sync_schedule_enabled', false);

            // Get total synced properties
            $total_synced = isset($import_stats['total_properties']) ? $import_stats['total_properties'] : 0;

            // Get last modified tracking record as proxy for last import
            global $wpdb;
            $tracking_table = $wpdb->prefix . 'realestate_sync_tracking';
            $last_modified = $wpdb->get_var("SELECT last_modified FROM {$tracking_table} ORDER BY last_modified DESC LIMIT 1");
            ?>
            <div class="rs-card" style="grid-column: 1 / -1; background: #e7f3ff; border-left: 4px solid #2271b1;">
                <h3 style="color: #135e96;">
                    <span class="dashicons dashicons-info"></span>
                    <?php _e('Stato Attuale', 'realestate-sync'); ?>
                </h3>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 15px;">
                    <!-- Total Properties -->
                    <div style="padding: 15px; background: #fff; border-radius: 4px; border: 1px solid #c9d6e7;">
                        <div style="font-size: 13px; color: #666; margin-bottom: 5px;">
                            <?php _e('Proprietà Sincronizzate', 'realestate-sync'); ?>
                        </div>
                        <div style="font-size: 32px; font-weight: 600; color: #2271b1;">
                            <?php echo number_format($total_synced); ?>
                        </div>
                    </div>

                    <!-- Last Activity -->
                    <div style="padding: 15px; background: #fff; border-radius: 4px; border: 1px solid #c9d6e7;">
                        <div style="font-size: 13px; color: #666; margin-bottom: 5px;">
                            <?php _e('Ultima Attività', 'realestate-sync'); ?>
                        </div>
                        <div style="font-size: 16px; font-weight: 500; color: #2c3338;">
                            <?php
                            if ($last_modified) {
                                $time_diff = human_time_diff(strtotime($last_modified), current_time('timestamp'));
                                echo esc_html($time_diff) . ' ' . __('fa', 'realestate-sync');
                            } else {
                                _e('Nessun dato', 'realestate-sync');
                            }
                            ?>
                        </div>
                        <?php if ($last_modified) : ?>
                            <div style="font-size: 12px; color: #999; margin-top: 3px;">
                                <?php echo esc_html(date('d/m/Y H:i', strtotime($last_modified))); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Next Scheduled Import -->
                    <div style="padding: 15px; background: #fff; border-radius: 4px; border: 1px solid #c9d6e7;">
                        <div style="font-size: 13px; color: #666; margin-bottom: 5px;">
                            <?php _e('Prossimo Import Automatico', 'realestate-sync'); ?>
                        </div>
                        <?php if ($schedule_enabled && $next_run_timestamp) : ?>
                            <div style="font-size: 16px; font-weight: 500; color: #2c3338;">
                                <?php
                                $time_until = human_time_diff(current_time('timestamp'), $next_run_timestamp);
                                echo __('Tra', 'realestate-sync') . ' ' . esc_html($time_until);
                                ?>
                            </div>
                            <div style="font-size: 12px; color: #999; margin-top: 3px;">
                                <?php echo esc_html(date('d/m/Y H:i', $next_run_timestamp)); ?>
                            </div>
                        <?php else : ?>
                            <div style="font-size: 16px; font-weight: 500; color: #d63638;">
                                <?php _e('Non programmato', 'realestate-sync'); ?>
                            </div>
                            <div style="font-size: 12px; color: #666; margin-top: 3px;">
                                <a href="#automazione" class="nav-tab-trigger" data-tab="automazione">
                                    <?php _e('Configura automazione →', 'realestate-sync'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!--
            ╔═══════════════════════════════════════════════════════════════════╗
            ║ WIDGET: PROPRIETÀ DA VERIFICARE                                   ║
            ╠═══════════════════════════════════════════════════════════════════╣
            ║ UTENTE: Admin Non Tecnico                                         ║
            ║ SCOPO: Mostra proprietà con possibili problemi post-import        ║
            ║        (immagini mancanti, dati incongruenti)                     ║
            ║                                                                   ║
            ║ AZIONI UTENTE:                                                    ║
            ║  - Visualizza proprietà in WP editor                              ║
            ║  - Cancella proprietà (sarà ricreata al prossimo import)          ║
            ║  - Ignora avviso (marca come "falso positivo OK")                 ║
            ║  - Cancella tutti gli avvisi                                      ║
            ║                                                                   ║
            ║ MANIPOLA:                                                         ║
            ║  - wp_options: realestate_sync_latest_verification (read/write)   ║
            ║  - wp_posts: estate_property (delete)                             ║
            ║  - wp_realestate_sync_tracking: (read only)                       ║
            ║                                                                   ║
            ║ VISIBILITÀ: Condizionale (solo se ci sono avvisi)                 ║
            ║ FREQUENZA USO: Dopo ogni import                                   ║
            ║ CRITICO: No (avvisi possono essere falsi positivi)                ║
            ╚═══════════════════════════════════════════════════════════════════╝
            -->
            <?php
            $verification = get_option('realestate_sync_latest_verification');
            if ($verification && !empty($verification['properties'])) :
            ?>
            <div class="rs-card" style="grid-column: 1 / -1; background: #fff3cd; border-left: 4px solid #ffc107;">
                <h3 style="color: #856404;">
                    <span class="dashicons dashicons-warning"></span>
                    <?php _e('Proprietà da Verificare', 'realestate-sync'); ?>
                </h3>

                <p style="margin: 10px 0;">
                    <strong>Import del <?php echo esc_html($verification['timestamp']); ?></strong><br>
                    Trovate <strong><?php echo count($verification['properties']); ?> proprietà</strong> con possibili problemi.
                    <em>Questi dati potrebbero essere corretti (es. immagini 404 nel feed XML) - verifica manualmente.</em>
                </p>

                <table class="widefat" style="background: #fff;">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Property ID</th>
                            <th style="width: 50%;">Problemi Rilevati</th>
                            <th style="width: 35%;">Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        global $wpdb;
                        foreach ($verification['properties'] as $prop_id => $prop_data) :
                            $tracking_table = $wpdb->prefix . 'realestate_sync_tracking';
                            $tracking = $wpdb->get_row($wpdb->prepare(
                                "SELECT wp_post_id FROM {$tracking_table} WHERE property_id = %d",
                                $prop_id
                            ), ARRAY_A);
                            $wp_post_id = $tracking['wp_post_id'] ?? null;
                            $title = $prop_data['title'] ?? 'Unknown';
                            $issues = $prop_data['issues'] ?? [];
                        ?>
                        <tr id="verify-row-<?php echo esc_attr($prop_id); ?>">
                            <td>
                                <strong>#<?php echo esc_html($prop_id); ?></strong>
                                <br><small style="color: #666;"><?php echo esc_html($title); ?></small>
                            </td>
                            <td>
                                <ul style="margin: 5px 0; padding-left: 20px;">
                                    <?php foreach ($issues as $issue) : ?>
                                        <li>
                                            <strong><?php echo esc_html(ucfirst($issue['field'])); ?>:</strong>
                                            <?php if (isset($issue['missing'])) : ?>
                                                <span style="color: #d63638;">
                                                    <?php echo esc_html($issue['missing']); ?> immagini mancanti
                                                    (<?php echo esc_html($issue['actual']); ?>/<?php echo esc_html($issue['expected']); ?>)
                                                </span>
                                            <?php elseif (isset($issue['issue'])) : ?>
                                                <span style="color: #d63638;"><?php echo esc_html($issue['issue']); ?></span>
                                            <?php else : ?>
                                                Atteso: <code><?php echo esc_html($issue['expected']); ?></code>,
                                                Salvato: <code><?php echo esc_html($issue['actual']); ?></code>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td>
                                <?php if ($wp_post_id) : ?>
                                    <a href="<?php echo get_edit_post_link($wp_post_id); ?>"
                                       class="button button-small" target="_blank">
                                        <span class="dashicons dashicons-edit"></span> Vedi
                                    </a>
                                    <button class="button button-small button-link-delete"
                                            onclick="if(confirm('Cancellare questa proprietà?\n\nAll\'import successivo verrà ricreata.')) {
                                                window.open('<?php echo get_delete_post_link($wp_post_id, '', true); ?>', '_blank');
                                            }">
                                        <span class="dashicons dashicons-trash"></span> Cancella
                                    </button>
                                    <br><br>
                                    <button class="button button-small"
                                            onclick="realestateSync.ignoreVerification(<?php echo $prop_id; ?>)">
                                        <span class="dashicons dashicons-yes"></span> Ignora (OK così)
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <p style="margin-top: 15px;">
                    <button class="button" onclick="realestateSync.clearAllVerification()">
                        <span class="dashicons dashicons-dismiss"></span> <?php _e('Cancella Tutti gli Avvisi', 'realestate-sync'); ?>
                    </button>
                </p>
            </div>
            <?php endif; ?>

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
                    <div style="margin-bottom: 20px; padding: 15px; background: #fff8e1; border-left: 3px solid #ffa000; border-radius: 4px;">
                        <label style="display: block; margin-bottom: 10px; font-weight: 600;">
                            <span class="dashicons dashicons-admin-generic"></span> Sorgente Credenziali:
                        </label>
                        <?php
                        $credential_source = get_option('realestate_sync_credential_source', 'hardcoded');
                        ?>
                        <label style="display: inline-block; margin-right: 20px;">
                            <input type="radio" name="credential_source" value="hardcoded"
                                   <?php checked($credential_source, 'hardcoded'); ?>
                                   id="rs-cred-source-hardcoded">
                            <strong>Usa credenziali hardcoded</strong> (sistema attuale)
                        </label>
                        <label style="display: inline-block;">
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
                                    <input type="text" id="xml_url" name="xml_url" class="rs-input" style="width: 100%;"
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
                                    <br><small style="color: #666;">Password visibile in chiaro per facilitare verifica</small>
                                </td>
                            </tr>
                        </table>

                        <div style="margin-top: 20px;">
                            <!-- Edit Mode Buttons -->
                            <button type="button" class="rs-button-secondary" id="rs-xml-edit-btn">
                                <span class="dashicons dashicons-edit"></span> Modifica Credenziali
                            </button>

                            <!-- Save/Cancel Buttons (hidden by default) -->
                            <div id="rs-xml-save-cancel-btns" style="display: none;">
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
                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
                    <button type="button" class="rs-button-secondary" id="rs-test-connection">
                        <span class="dashicons dashicons-networking"></span> Test Connessione XML
                    </button>
                    <div id="rs-test-connection-result" style="margin-top: 10px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!--
    ═══════════════════════════════════════════════════════════════════════════
    TAB 2: AUTOMAZIONE - Configurazione Import Automatici
    ═══════════════════════════════════════════════════════════════════════════
    -->
    <div id="automazione" class="tab-content">
        <?php
        // Get current schedule settings
        $schedule_enabled = get_option('realestate_sync_schedule_enabled', false);
        $schedule_time = get_option('realestate_sync_schedule_time', '23:00');
        $schedule_frequency = get_option('realestate_sync_schedule_frequency', 'daily');
        $schedule_weekday = get_option('realestate_sync_schedule_weekday', 1); // 1 = Monday
        $schedule_custom_days = get_option('realestate_sync_schedule_custom_days', 1);
        $schedule_custom_months = get_option('realestate_sync_schedule_custom_months', 1);
        $schedule_mark_test = get_option('realestate_sync_schedule_mark_test', false);

        // Get next scheduled run
        $cron_manager = new RealEstate_Sync_Cron_Manager();
        $next_run = $cron_manager->get_next_scheduled_import();

        // Get server timezone info
        $server_time = current_time('mysql');
        $server_timezone = wp_timezone_string();
        ?>

        <!--
        ╔═══════════════════════════════════════════════════════════════════╗
        ║ WIDGET: CONFIGURAZIONE IMPORT AUTOMATICO                          ║
        ╠═══════════════════════════════════════════════════════════════════╣
        ║ UTENTE: Admin Non Tecnico (setup) + Tecnico                      ║
        ║ SCOPO: Configura schedule automatico per import periodici        ║
        ║                                                                   ║
        ║ AZIONI UTENTE:                                                    ║
        ║  - Abilita/disabilita import automatici                           ║
        ║  - Imposta orario esecuzione (formato 24h)                        ║
        ║  - Seleziona frequenza (daily/weekly/custom days/months)          ║
        ║  - Marca import come test (flag _test_import=1)                   ║
        ║  - Vede prossima esecuzione programmata                           ║
        ║                                                                   ║
        ║ MANIPOLA:                                                         ║
        ║  - wp_options: realestate_sync_schedule_enabled (bool)            ║
        ║  - wp_options: realestate_sync_schedule_time (HH:MM)              ║
        ║  - wp_options: realestate_sync_schedule_frequency (string)        ║
        ║  - wp_options: realestate_sync_schedule_weekday (0-6)             ║
        ║  - wp_options: realestate_sync_schedule_custom_days (int)         ║
        ║  - wp_options: realestate_sync_schedule_custom_months (int)       ║
        ║  - wp_options: realestate_sync_schedule_mark_test (bool)          ║
        ║  - WP Cron: schedule/unschedule import events                     ║
        ║                                                                   ║
        ║ VISIBILITÀ: Sempre                                                ║
        ║ FREQUENZA USO: Setup iniziale, modifiche occasionali              ║
        ║ CRITICO: Sì (garantisce aggiornamento automatico proprietà)       ║
        ╚═══════════════════════════════════════════════════════════════════╝
        -->
        <div class="rs-card">
            <h3>
                <span class="dashicons dashicons-clock"></span>
                <?php _e('Configurazione Import Automatico', 'realestate-sync'); ?>
            </h3>

            <!-- Server Time Info -->
            <div style="padding: 15px; background: #e7f3ff; border-left: 4px solid #2271b1; margin-bottom: 20px; border-radius: 4px;">
                <strong><span class="dashicons dashicons-info"></span> Orario Server:</strong><br>
                <div style="margin-top: 8px; font-family: monospace; font-size: 14px;">
                    <strong style="font-size: 18px;"><?php echo esc_html($server_time); ?></strong>
                    <span style="color: #666; margin-left: 10px;">(Timezone: <?php echo esc_html($server_timezone); ?>)</span>
                </div>
                <small style="color: #666; margin-top: 5px; display: block;">
                    Tutti gli orari configurati fanno riferimento a questo fuso orario.
                </small>
            </div>

            <!-- Enable/Disable Toggle -->
            <div style="padding: 15px; background: <?php echo $schedule_enabled ? '#d4edda' : '#f8f9fa'; ?>; border-left: 4px solid <?php echo $schedule_enabled ? '#28a745' : '#6c757d'; ?>; margin-bottom: 20px; border-radius: 4px;">
                <label style="display: flex; align-items: center; cursor: pointer; font-size: 16px;">
                    <input type="checkbox" id="schedule-enabled" <?php checked($schedule_enabled); ?>
                           style="width: 20px; height: 20px; margin-right: 10px;">
                    <strong>
                        <span class="dashicons dashicons-yes-alt" style="color: #28a745;"></span>
                        Abilita Import Automatico Programmato
                    </strong>
                </label>
                <small style="display: block; margin-top: 8px; margin-left: 30px; color: #666;">
                    Quando abilitato, il sistema eseguirà automaticamente l'import secondo la configurazione impostata.
                </small>
            </div>

            <!-- Schedule Configuration (visible only when enabled) -->
            <div id="schedule-config">

                <!-- Time Selection -->
                <div style="margin-bottom: 25px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                        <span class="dashicons dashicons-clock"></span>
                        Orario Esecuzione (formato 24h):
                    </label>
                    <input type="time" id="schedule-time" value="<?php echo esc_attr($schedule_time); ?>"
                           style="padding: 8px 12px; font-size: 16px; border: 1px solid #ccc; border-radius: 4px; width: 150px;">
                    <small style="display: block; margin-top: 5px; color: #666;">
                        L'import verrà eseguito all'orario specificato secondo il fuso orario del server.
                    </small>
                </div>

                <!-- Frequency Selection -->
                <div style="margin-bottom: 25px;">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                        <span class="dashicons dashicons-calendar-alt"></span>
                        Frequenza:
                    </label>
                    <select id="schedule-frequency" style="padding: 8px 12px; font-size: 14px; border: 1px solid #ccc; border-radius: 4px; width: 300px;">
                        <option value="daily" <?php selected($schedule_frequency, 'daily'); ?>>
                            Ogni giorno
                        </option>
                        <option value="weekly" <?php selected($schedule_frequency, 'weekly'); ?>>
                            Un giorno specifico della settimana
                        </option>
                        <option value="custom_days" <?php selected($schedule_frequency, 'custom_days'); ?>>
                            Ogni X giorni
                        </option>
                        <option value="custom_months" <?php selected($schedule_frequency, 'custom_months'); ?>>
                            Ogni X mesi
                        </option>
                    </select>
                </div>

                <!-- Weekly Configuration (visible only when frequency=weekly) -->
                <div id="weekly-config" style="margin-bottom: 25px; padding-left: 20px; <?php echo $schedule_frequency !== 'weekly' ? 'display: none;' : ''; ?>">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                        Giorno della settimana:
                    </label>
                    <select id="schedule-weekday" style="padding: 8px 12px; font-size: 14px; border: 1px solid #ccc; border-radius: 4px; width: 200px;">
                        <option value="0" <?php selected($schedule_weekday, 0); ?>>Domenica</option>
                        <option value="1" <?php selected($schedule_weekday, 1); ?>>Lunedì</option>
                        <option value="2" <?php selected($schedule_weekday, 2); ?>>Martedì</option>
                        <option value="3" <?php selected($schedule_weekday, 3); ?>>Mercoledì</option>
                        <option value="4" <?php selected($schedule_weekday, 4); ?>>Giovedì</option>
                        <option value="5" <?php selected($schedule_weekday, 5); ?>>Venerdì</option>
                        <option value="6" <?php selected($schedule_weekday, 6); ?>>Sabato</option>
                    </select>
                </div>

                <!-- Custom Days Configuration (visible only when frequency=custom_days) -->
                <div id="custom-days-config" style="margin-bottom: 25px; padding-left: 20px; <?php echo $schedule_frequency !== 'custom_days' ? 'display: none;' : ''; ?>">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                        Numero di giorni:
                    </label>
                    <input type="number" id="schedule-custom-days" value="<?php echo esc_attr($schedule_custom_days); ?>"
                           min="1" max="365" style="padding: 8px 12px; font-size: 14px; border: 1px solid #ccc; border-radius: 4px; width: 100px;">
                    <small style="display: block; margin-top: 5px; color: #666;">
                        L'import verrà eseguito ogni N giorni (es. 7 = settimanale, 15 = ogni 2 settimane)
                    </small>
                </div>

                <!-- Custom Months Configuration (visible only when frequency=custom_months) -->
                <div id="custom-months-config" style="margin-bottom: 25px; padding-left: 20px; <?php echo $schedule_frequency !== 'custom_months' ? 'display: none;' : ''; ?>">
                    <label style="display: block; font-weight: 600; margin-bottom: 8px;">
                        Numero di mesi:
                    </label>
                    <input type="number" id="schedule-custom-months" value="<?php echo esc_attr($schedule_custom_months); ?>"
                           min="1" max="12" style="padding: 8px 12px; font-size: 14px; border: 1px solid #ccc; border-radius: 4px; width: 100px;">
                    <small style="display: block; margin-top: 5px; color: #666;">
                        L'import verrà eseguito ogni N mesi
                    </small>
                </div>

                <!-- Mark as Test Option -->
                <div style="margin-bottom: 25px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" id="schedule-mark-test" <?php checked($schedule_mark_test); ?>
                               style="width: 18px; height: 18px; margin-right: 8px;">
                        <strong>
                            <span class="dashicons dashicons-flag" style="color: #f0ad4e;"></span>
                            Marca import automatici come Test
                        </strong>
                    </label>
                    <small style="display: block; margin-top: 5px; margin-left: 26px; color: #666;">
                        Le proprietà importate automaticamente verranno marcate con <code>_test_import=1</code>
                    </small>
                </div>

                <!-- Preview Next Run -->
                <div style="padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; margin-bottom: 20px; border-radius: 4px;">
                    <strong><span class="dashicons dashicons-calendar"></span> Prossima Esecuzione:</strong><br>
                    <div id="next-run-preview" style="margin-top: 8px; font-family: monospace; font-size: 14px; font-weight: 600;">
                        <?php
                        if ($schedule_enabled && $next_run) {
                            echo esc_html(date('Y-m-d H:i:s', $next_run));
                        } else {
                            echo 'Non programmato';
                        }
                        ?>
                    </div>
                    <small style="color: #666; margin-top: 5px; display: block;">
                        Aggiorna automaticamente dopo aver salvato la configurazione.
                    </small>
                </div>

                <!-- Save Button -->
                <button type="button" class="rs-button-primary" id="save-schedule-config">
                    <span class="dashicons dashicons-saved"></span> <?php _e('Salva Configurazione', 'realestate-sync'); ?>
                </button>
            </div>

            <!-- Status Message -->
            <div id="schedule-status" style="margin-top: 15px;"></div>
        </div>
    </div>

    <!--
    ═══════════════════════════════════════════════════════════════════════════
    TAB 3: STRUMENTI - Tools Tecnici e Pulizia Database
    ═══════════════════════════════════════════════════════════════════════════
    -->
    <div id="tools" class="tab-content">
        <?php
        // Get developer mode preference from user meta
        $developer_mode = get_user_meta(get_current_user_id(), 'realestate_sync_developer_mode', true);
        $developer_mode = filter_var($developer_mode, FILTER_VALIDATE_BOOLEAN);
        ?>

        <!-- Developer Mode Toggle -->
        <div class="rs-card" style="background: #fff3cd; border-left: 4px solid #f0ad4e; margin-bottom: 20px;">
            <h3 style="margin: 0 0 15px 0;">
                <span class="dashicons dashicons-admin-tools"></span>
                <?php _e('Modalità Visualizzazione', 'realestate-sync'); ?>
            </h3>

            <label style="display: flex; align-items: center; cursor: pointer; padding: 10px; background: #fff; border-radius: 4px;">
                <input type="checkbox" id="developer-mode-toggle" <?php checked($developer_mode); ?>
                       style="width: 20px; height: 20px; margin: 0 12px 0 0;">
                <div>
                    <strong style="font-size: 16px; display: block; margin-bottom: 5px;">
                        <span class="dashicons dashicons-admin-generic" style="color: #f0ad4e; vertical-align: middle;"></span>
                        <?php _e('Modalità Sviluppatore', 'realestate-sync'); ?>
                    </strong>
                    <span style="font-size: 13px; color: #666;">
                        <?php _e('Mostra strumenti tecnici avanzati per debug e gestione sistema', 'realestate-sync'); ?>
                    </span>
                </div>
            </label>

            <div id="developer-mode-status" style="margin-top: 10px; padding: 8px; background: #e7f3ff; border-radius: 4px; font-size: 13px;">
                <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
                <span id="developer-mode-message">
                    <?php echo $developer_mode ? __('Modalità Sviluppatore attiva - Strumenti tecnici visibili', 'realestate-sync') : __('Modalità Utente Standard - Solo strumenti essenziali', 'realestate-sync'); ?>
                </span>
            </div>
        </div>

        <div class="rs-card">
            <h3><span class="dashicons dashicons-admin-tools"></span> <?php _e('Strumenti Amministrazione', 'realestate-sync'); ?></h3>

            <!--
            ╔═══════════════════════════════════════════════════════════════════╗
            ║ WIDGET: GESTIONE QUEUE IMPORT                                     ║
            ╠═══════════════════════════════════════════════════════════════════╣
            ║ UTENTE: Tecnico (debug sistema)                                  ║
            ║ SCOPO: Monitor e risoluzione import bloccati/in sospeso          ║
            ║                                                                   ║
            ║ AZIONI UTENTE:                                                    ║
            ║  - Visualizza stato ultimo import (session ID, timestamp, etc)    ║
            ║  - Vede elementi pending/processing/stuck                         ║
            ║  - Resetta elementi stuck a "pending" per riprocessare            ║
            ║  - Elimina elementi dalla queue                                   ║
            ║  - Svuota completamente la queue (reset totale)                   ║
            ║                                                                   ║
            ║ MANIPOLA:                                                         ║
            ║  - wp_realestate_import_queue: (read/update/delete)               ║
            ║  - Legge session_id, status (pending/processing/completed)        ║
            ║  - Modifica status da "processing" a "pending"                    ║
            ║  - DELETE FROM queue WHERE condition                              ║
            ║                                                                   ║
            ║ VISIBILITÀ: Solo Developer Mode                                   ║
            ║ FREQUENZA USO: Durante debug import falliti                       ║
            ║ CRITICO: Sì (essenziale per sbloccare import incompleti)          ║
            ║                                                                   ║
            ║ NOTE TECNICHE:                                                    ║
            ║  - Processing elements stuck = import chiuso senza completare     ║
            ║  - Reset a pending permette retry automatico                      ║
            ║  - Clear all queue = ricominciare da zero                         ║
            ╚═══════════════════════════════════════════════════════════════════╝
            -->
            <div class="rs-developer-only <?php echo !$developer_mode ? 'rs-hidden' : ''; ?>" data-developer-section="queue-management">
            <div class="rs-queue-section" style="border-left: 4px solid #2271b1; padding: 20px; margin-bottom: 20px; background: #f8f9fa;">
                <h4><span class="dashicons dashicons-list-view"></span> <?php _e('Gestione Queue Import', 'realestate-sync'); ?></h4>
                <p>Controlla lo stato dell'ultimo import e gestisci eventuali elementi rimasti in sospeso.</p>

                <!-- Last Import Status -->
                <div id="last-import-status" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                    <h5 style="margin: 0 0 15px 0; font-size: 16px;">
                        <span class="dashicons dashicons-database-import"></span> Ultimo Import
                    </h5>

                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px; font-weight: 500; width: 150px;">Session ID:</td>
                            <td style="padding: 10px;" id="import-session-id">-</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px; font-weight: 500;">Data Inizio:</td>
                            <td style="padding: 10px;" id="import-start-time">-</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px; font-weight: 500;">Stato Processo:</td>
                            <td style="padding: 10px;" id="import-process-status">-</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px; font-weight: 500;">Totale Elementi:</td>
                            <td style="padding: 10px;" id="import-total-items">-</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px; font-weight: 500;">Completati:</td>
                            <td style="padding: 10px;" id="import-completed-items">-</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px; font-weight: 500;">Rimanenti:</td>
                            <td style="padding: 10px;" id="import-remaining-items">-</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; font-weight: 500;">Progressione:</td>
                            <td style="padding: 10px;" id="import-progress-bar">
                                <div style="background: #e0e0e0; height: 20px; border-radius: 10px; overflow: hidden;">
                                    <div id="import-progress-fill" style="background: #10b981; height: 100%; width: 0%; transition: width 0.3s;"></div>
                                </div>
                                <span id="import-progress-text" style="font-size: 12px; color: #666;">0%</span>
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top: 15px; text-align: center;">
                        <button type="button" class="rs-button-secondary" id="refresh-import-status">
                            <span class="dashicons dashicons-update"></span> <?php _e('Aggiorna Stato', 'realestate-sync'); ?>
                        </button>
                    </div>
                </div>

                <!-- Pending/Stuck Items Alert -->
                <div id="pending-items-alert" class="rs-hidden" style="margin: 20px 0; padding: 20px; background: #fff3cd; border-left: 4px solid #f0ad4e; border-radius: 4px;">
                    <h5 style="margin: 0 0 10px 0; color: #856404;">
                        <span class="dashicons dashicons-warning"></span> ⚠️ PROCESSO CHIUSO - ELEMENTI IN SOSPESO
                    </h5>
                    <p style="margin: 0 0 15px 0; font-size: 14px;" id="pending-items-message">
                        <!-- Populated via JS -->
                    </p>

                    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                        <button type="button" class="rs-button-primary" id="show-pending-details">
                            <span class="dashicons dashicons-visibility"></span> Vedi Dettaglio
                        </button>
                        <button type="button" class="rs-button-primary" id="retry-pending-items">
                            <span class="dashicons dashicons-controls-repeat"></span> Resetta a Pending e Riprocessa
                        </button>
                        <button type="button" class="rs-button-danger" id="delete-pending-items">
                            <span class="dashicons dashicons-trash"></span> Elimina dalla Queue
                        </button>
                    </div>

                    <!-- Pending Items List (expandable) -->
                    <div id="pending-items-list" class="rs-hidden" style="margin-top: 15px; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px; max-height: 400px; overflow-y: auto;">
                        <!-- Populated via JS -->
                    </div>
                </div>

                <!-- Clear All Queue -->
                <div style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                    <h5 style="margin: 0 0 10px 0;">
                        <span class="dashicons dashicons-trash"></span> Pulizia Completa Queue
                    </h5>
                    <p style="margin: 0 0 15px 0; font-size: 13px; color: #666;">
                        Rimuove TUTTI gli elementi dalla queue (utile per ricominciare da zero dopo aver risolto i problemi).
                    </p>
                    <button type="button" class="rs-button-danger" id="clear-all-queue">
                        <span class="dashicons dashicons-warning"></span> Svuota Tutta la Queue
                    </button>
                </div>

                <!--
                ╔═══════════════════════════════════════════════════════════════════╗
                ║ SOTTO-WIDGET: CLEANUP POST ORFANI                                 ║
                ╠═══════════════════════════════════════════════════════════════════╣
                ║ UTENTE: Tecnico                                                   ║
                ║ SCOPO: Rimuove post senza record tracking (dati inconsistenti)    ║
                ║                                                                   ║
                ║ AZIONI:                                                           ║
                ║  - Scansiona per trovare estate_property senza tracking           ║
                ║  - Mostra lista post orfani trovati                               ║
                ║  - Cancella post orfani (permanente)                              ║
                ║                                                                   ║
                ║ MANIPOLA:                                                         ║
                ║  - wp_posts: estate_property (read/delete)                        ║
                ║  - wp_realestate_sync_tracking: (read only - per find orphans)    ║
                ║  - Hook WP before_delete_post rimuove tracking e media            ║
                ║                                                                   ║
                ║ CRITICO: Sì (cancellazione permanente, no undo)                   ║
                ╚═══════════════════════════════════════════════════════════════════╝
                -->
                <div style="margin: 20px 0; padding: 15px; background: #fff3cd; border: 1px solid #f0ad4e; border-radius: 4px;">
                    <h5 style="margin: 0 0 10px 0; color: #856404;">
                        <span class="dashicons dashicons-admin-tools"></span> 🧹 <?php _e('Cleanup Post Orfani', 'realestate-sync'); ?>
                    </h5>
                    <p style="margin: 0 0 10px 0; font-size: 13px; color: #666;">
                        Trova e cancella tutti i post (estate_property) che <strong>NON hanno</strong> un record nella tracking table.
                    </p>
                    <p style="margin: 0 0 15px 0; font-size: 12px; color: #d63638;">
                        ⚠️ <strong>ATTENZIONE:</strong> Cancellazione permanente! Gli hook WP puliscono anche tracking e immagini.
                    </p>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="rs-button-secondary" id="scan-orphan-posts">
                            <span class="dashicons dashicons-search"></span> Scansiona Post Orfani
                        </button>
                        <button type="button" class="rs-button-danger" id="cleanup-orphan-posts" style="display: none;">
                            <span class="dashicons dashicons-trash"></span> Cancella Post Orfani
                        </button>
                    </div>
                    <div id="orphan-posts-report" style="margin-top: 15px; display: none;"></div>
                </div>
            </div>
            </div>
            <!-- End of rs-developer-only: Queue Management -->
        </div>

        <div class="rs-card">
            <h3><span class="dashicons dashicons-database-import"></span> <?php _e('Testing & Development', 'realestate-sync'); ?></h3>

            <!--
            ╔═══════════════════════════════════════════════════════════════════╗
            ║ WIDGET: IMPORT XML (Upload File Manuale)                          ║
            ╠═══════════════════════════════════════════════════════════════════╣
            ║ UTENTE: Entrambi (Admin + Tecnico)                               ║
            ║ SCOPO: Importa da file XML locale invece che da server remoto    ║
            ║                                                                   ║
            ║ AZIONI UTENTE:                                                    ║
            ║  - Upload file XML dal computer                                   ║
            ║  - Marca import come test (_test_import=1) opzionale              ║
            ║  - Processa XML con stesso flusso import remoto                   ║
            ║                                                                   ║
            ║ MANIPOLA:                                                         ║
            ║  - Stesso del Import Immediato                                    ║
            ║  - wp_posts: estate_property (create/update)                      ║
            ║  - wp_realestate_sync_tracking: (create/update)                   ║
            ║  - wp_realestate_import_queue: (populate)                         ║
            ║  - Media library: (download/attach images)                        ║
            ║                                                                   ║
            ║ VISIBILITÀ: Sempre                                                ║
            ║ FREQUENZA USO: Testing, import one-off                            ║
            ║ CRITICO: No (utile ma non essenziale per workflow quotidiano)     ║
            ╚═══════════════════════════════════════════════════════════════════╝
            -->
            <div class="rs-upload-section" style="border-left: 4px solid #2271b1; padding: 20px; margin-bottom: 20px; background: #f8f9fa;">
                <h4><span class="dashicons dashicons-upload"></span> <?php _e('Import XML', 'realestate-sync'); ?></h4>
                <p>Upload file XML per importare properties e agenzie (utile sia per test che per produzione)</p>

                <div style="margin: 15px 0;">
                    <input type="file" id="test-xml-file" accept=".xml" style="margin-bottom: 10px; padding: 8px;">
                    <small style="display: block; color: #666;">Seleziona file XML (esempio: sample-con-agenzie.xml)</small>
                </div>

                <div style="margin: 15px 0; padding: 12px; background: #fff3cd; border-left: 3px solid #f0ad4e; border-radius: 4px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" id="mark-as-test-import" checked style="margin: 0 8px 0 0; width: 18px; height: 18px;">
                        <span style="font-weight: 500;">
                            <span class="dashicons dashicons-flag" style="color: #f0ad4e; vertical-align: middle;"></span>
                            Marca come import di test
                        </span>
                    </label>
                    <small style="display: block; margin-top: 5px; color: #856404;">
                        ✓ Se attivo: le proprietà avranno flag <code>_test_import=1</code> e potrai cancellarle con "Cleanup Test Data"<br>
                        ✗ Se disattivo: import normale (produzione), le proprietà non saranno marcate come test
                    </small>
                </div>

                <div style="margin: 15px 0;">
                    <button type="button" class="rs-button-primary" id="process-test-file" disabled>
                        <span class="dashicons dashicons-admin-generic"></span> Processa File XML
                    </button>
                </div>
                
                <!-- Test Log Output -->
                <div id="test-log-output" class="rs-hidden" style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; max-height: 300px; overflow-y: auto;">
                    <h5>Log Processo:</h5>
                    <pre id="test-log-content" style="margin: 0; font-family: 'Courier New', monospace; font-size: 12px; white-space: pre-wrap;">Avvio processo...</pre>
                </div>
            </div>

            <!--
            ╔═══════════════════════════════════════════════════════════════════╗
            ║ WIDGET: DATABASE TOOLS (Cleanup Test Data)                        ║
            ╠═══════════════════════════════════════════════════════════════════╣
            ║ UTENTE: Entrambi (Admin + Tecnico)                               ║
            ║ SCOPO: Rimuovi dati di test marcati con _test_import=1           ║
            ║                                                                   ║
            ║ AZIONI UTENTE:                                                    ║
            ║  - Cleanup Test Data: rimuove tutte le proprietà marcate test     ║
            ║                                                                   ║
            ║ MANIPOLA:                                                         ║
            ║  - wp_posts: estate_property WHERE meta_key='_test_import' (del)  ║
            ║  - wp_postmeta: _test_import=1 (read/delete)                      ║
            ║  - wp_realestate_sync_tracking: (delete via hook)                 ║
            ║  - Media library: (delete via hook)                               ║
            ║                                                                   ║
            ║ VISIBILITÀ: Sempre                                                ║
            ║ FREQUENZA USO: Dopo testing                                       ║
            ║ CRITICO: No (solo per pulizia test, non dati produzione)          ║
            ╚═══════════════════════════════════════════════════════════════════╝
            -->
            <div class="rs-testing-section" style="border-left: 4px solid #f0ad4e; padding: 15px; margin-top: 20px;">
                <h4><span class="dashicons dashicons-admin-tools"></span> <?php _e('Database Tools', 'realestate-sync'); ?></h4>

                <div class="rs-button-group" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
                    <button type="button" class="rs-button-warning" id="cleanup-test-data" style="background: #ffc107; border-color: #ffc107; color: #000;">
                        <span class="dashicons dashicons-trash"></span> Cleanup Test Data
                    </button>
                </div>
            </div>

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
            <div class="rs-cleanup-section" style="border-left: 4px solid #dc3545; padding: 20px; margin-top: 20px; background: #fff5f5;">
                <h4><span class="dashicons dashicons-trash"></span> <?php _e('Cleanup Proprietà Senza Immagini', 'realestate-sync'); ?></h4>
                <p>Trova e rimuovi proprietà senza featured image. Il tracking viene cancellato automaticamente.</p>

                <!-- Step 1: Analisi -->
                <div style="margin: 20px 0; padding: 15px; background: #e7f3ff; border-left: 3px solid #2271b1; border-radius: 4px;">
                    <strong>Step 1: Analisi (Safe)</strong>
                    <p style="margin: 10px 0 0 0;">Prima controlla quante proprietà senza immagini ci sono - nessuna cancellazione.</p>
                    <button type="button" class="rs-button-secondary" id="analyze-no-images" style="margin-top: 10px;">
                        <span class="dashicons dashicons-search"></span> Analizza Proprietà Senza Immagini
                    </button>
                </div>

                <!-- Analysis Results -->
                <div id="no-images-analysis" class="rs-hidden" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                    <h5>Risultati Analisi:</h5>
                    <div id="no-images-analysis-content"></div>
                </div>

                <!-- Step 2: Cancellazione -->
                <div id="cleanup-actions" class="rs-hidden" style="margin: 20px 0; padding: 15px; background: #fff3cd; border-left: 3px solid #ffc107; border-radius: 4px;">
                    <strong>Step 2: Cancellazione</strong>
                    <p style="margin: 10px 0;">
                        ⚠️ <strong>ATTENZIONE:</strong> Questa azione cancellerà le proprietà trovate.
                        Il tracking verrà rimosso automaticamente.
                    </p>
                    <div style="margin-top: 15px;">
                        <button type="button" class="rs-button-danger" id="cleanup-no-images-trash">
                            <span class="dashicons dashicons-trash"></span> Sposta nel Cestino
                        </button>
                        <button type="button" class="rs-button-danger" id="cleanup-no-images-permanent" style="margin-left: 10px; background: #dc3545;">
                            <span class="dashicons dashicons-dismiss"></span> Cancellazione Permanente
                        </button>
                    </div>
                    <small style="display: block; margin-top: 10px; color: #856404;">
                        <strong>Cestino:</strong> Recuperabili da WP Admin > Cestino<br>
                        <strong>Permanente:</strong> Non recuperabili (usare con cautela!)
                    </small>
                </div>

                <!-- Cleanup Results -->
                <div id="cleanup-results" class="rs-hidden" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                    <h5>Risultati Cleanup:</h5>
                    <div id="cleanup-results-content"></div>
                </div>
            </div>

            <!-- Professional Activation Tools REMOVED -->
        </div>
    </div>

    <!--
    ═══════════════════════════════════════════════════════════════════════════
    TAB 4: STORICO & LOG - Monitoring Import e Log Sistema
    ═══════════════════════════════════════════════════════════════════════════
    -->
    <div id="logs" class="tab-content">
        <?php
        // Check if import_sessions table exists
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'realestate_import_sessions';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$sessions_table'") === $sessions_table;

        if ($table_exists) {
            // Get last 10 import sessions
            $recent_sessions = $wpdb->get_results("
                SELECT *
                FROM {$sessions_table}
                ORDER BY started_at DESC
                LIMIT 10
            ", ARRAY_A);
        }
        ?>

        <!--
        ╔═══════════════════════════════════════════════════════════════════╗
        ║ WIDGET: STORICO IMPORT                                            ║
        ╠═══════════════════════════════════════════════════════════════════╣
        ║ UTENTE: Entrambi (Admin + Tecnico)                               ║
        ║ SCOPO: Visualizza cronologia import passati                      ║
        ║                                                                   ║
        ║ MOSTRA:                                                           ║
        ║  - Data/ora import                                                ║
        ║  - Tipo (manuale/scheduled)                                       ║
        ║  - Risultato (completato/fallito)                                 ║
        ║  - Dettagli (nuove/aggiornate/fallite)                            ║
        ║                                                                   ║
        ║ MANIPOLA:                                                         ║
        ║  - wp_realestate_import_sessions: (read only)                     ║
        ║                                                                   ║
        ║ VISIBILITÀ: Sempre                                                ║
        ║ FREQUENZA USO: Verifica post-import                               ║
        ║ CRITICO: No (informativo)                                         ║
        ╚═══════════════════════════════════════════════════════════════════╝
        -->
        <?php if ($table_exists && !empty($recent_sessions)) : ?>
        <div class="rs-card">
            <h3><span class="dashicons dashicons-calendar-alt"></span> <?php _e('Storico Import', 'realestate-sync'); ?></h3>

            <p style="margin-bottom: 20px; color: #666;">
                <?php _e('Cronologia degli ultimi import eseguiti', 'realestate-sync'); ?>
            </p>

            <table class="widefat" style="background: #fff;">
                <thead>
                    <tr>
                        <th style="width: 18%;"><?php _e('Data/Ora', 'realestate-sync'); ?></th>
                        <th style="width: 12%;"><?php _e('Tipo', 'realestate-sync'); ?></th>
                        <th style="width: 15%;"><?php _e('Stato', 'realestate-sync'); ?></th>
                        <th style="width: 15%;"><?php _e('Durata', 'realestate-sync'); ?></th>
                        <th style="width: 40%;"><?php _e('Dettagli', 'realestate-sync'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_sessions as $session) :
                        $started = strtotime($session['started_at']);
                        $completed = $session['completed_at'] ? strtotime($session['completed_at']) : null;
                        $duration = $completed ? ($completed - $started) : null;

                        // Status badge
                        $status_color = 'gray';
                        $status_text = ucfirst($session['status']);
                        if ($session['status'] === 'completed') {
                            $status_color = '#00a32a';
                            $status_text = __('Completato', 'realestate-sync');
                        } elseif ($session['status'] === 'failed') {
                            $status_color = '#d63638';
                            $status_text = __('Fallito', 'realestate-sync');
                        } elseif ($session['status'] === 'running') {
                            $status_color = '#f0ad4e';
                            $status_text = __('In corso', 'realestate-sync');
                        }

                        // Type badge
                        $type_text = $session['type'] === 'manual' ? __('Manuale', 'realestate-sync') : __('Automatico', 'realestate-sync');
                        $type_icon = $session['type'] === 'manual' ? 'admin-users' : 'clock';
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html(date('d/m/Y', $started)); ?></strong><br>
                            <small style="color: #666;"><?php echo esc_html(date('H:i:s', $started)); ?></small>
                        </td>
                        <td>
                            <span class="dashicons dashicons-<?php echo $type_icon; ?>" style="vertical-align: middle;"></span>
                            <?php echo esc_html($type_text); ?>
                            <?php if ($session['marked_as_test']) : ?>
                                <br><span style="color: #f0ad4e; font-size: 12px;">
                                    <span class="dashicons dashicons-flag" style="font-size: 12px;"></span> Test
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: 500; color: white; background: <?php echo $status_color; ?>;">
                                <?php echo esc_html($status_text); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($duration !== null) : ?>
                                <?php
                                $minutes = floor($duration / 60);
                                $seconds = $duration % 60;
                                echo sprintf('%d:%02d', $minutes, $seconds);
                                ?>
                            <?php else : ?>
                                <span style="color: #999;">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size: 13px;">
                            <?php if ($session['status'] === 'completed') : ?>
                                <span style="color: #00a32a;">✓ <?php echo esc_html($session['new_properties']); ?></span> nuove,
                                <span style="color: #2271b1;">↻ <?php echo esc_html($session['updated_properties']); ?></span> aggiornate
                                <?php if ($session['failed_properties'] > 0) : ?>
                                    , <span style="color: #d63638;">✗ <?php echo esc_html($session['failed_properties']); ?></span> fallite
                                <?php endif; ?>
                            <?php elseif ($session['status'] === 'failed') : ?>
                                <span style="color: #d63638;">
                                    <?php echo esc_html(substr($session['error_log'], 0, 100)); ?>
                                    <?php if (strlen($session['error_log']) > 100) echo '...'; ?>
                                </span>
                            <?php elseif ($session['status'] === 'running') : ?>
                                <span style="color: #f0ad4e;">
                                    <?php echo esc_html($session['processed_items']); ?>/<?php echo esc_html($session['total_items']); ?> processati
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!--
        ╔═══════════════════════════════════════════════════════════════════╗
        ║ WIDGET: LOG & MONITORAGGIO                                        ║
        ╠═══════════════════════════════════════════════════════════════════╣
        ║ UTENTE: Entrambi (Admin + Tecnico)                               ║
        ║ SCOPO: Visualizza log sistema per troubleshooting                ║
        ║                                                                   ║
        ║ AZIONI UTENTE:                                                    ║
        ║  - Visualizza log file in browser                                 ║
        ║  - Scarica log file sul computer                                  ║
        ║  - Cancella log file (reset)                                      ║
        ║  - System Check: verifica configurazione WP                       ║
        ║                                                                   ║
        ║ MANIPOLA:                                                         ║
        ║  - wp-content/debug.log (read/write/delete)                       ║
        ║  - Nessuna manipolazione database                                 ║
        ║                                                                   ║
        ║ VISIBILITÀ: Sempre                                                ║
        ║ FREQUENZA USO: Troubleshooting, post-import verification          ║
        ║ CRITICO: No (informativo, no modifiche dati)                      ║
        ║                                                                   ║
        ║ NOTE FUTURE:                                                      ║
        ║  - TODO: Aggiungere widget "Storico Import" sopra Log             ║
        ║  - Query wp_realestate_import_sessions per mostrare history       ║
        ║  - Tabella: Data | Tipo | Risultato | Dettagli                    ║
        ╚═══════════════════════════════════════════════════════════════════╝
        -->
        <div class="rs-card">
            <h3><span class="dashicons dashicons-list-view"></span> <?php _e('Log & Monitoraggio', 'realestate-sync'); ?></h3>
            
            <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                <button type="button" class="rs-button-secondary" id="view-logs">
                    <span class="dashicons dashicons-media-text"></span> <?php _e('Visualizza Log', 'realestate-sync'); ?>
                </button>
                <button type="button" class="rs-button-secondary" id="download-logs">
                    <span class="dashicons dashicons-download"></span> <?php _e('Scarica Log', 'realestate-sync'); ?>
                </button>
                <button type="button" class="rs-button-secondary" id="clear-logs">
                    <span class="dashicons dashicons-trash"></span> <?php _e('Cancella Log', 'realestate-sync'); ?>
                </button>
                <button type="button" class="rs-button-secondary" id="system-check">
                    <span class="dashicons dashicons-admin-tools"></span> <?php _e('Verifica Sistema', 'realestate-sync'); ?>
                </button>
            </div>

            <!-- Log Viewer -->
            <div id="log-viewer" class="rs-hidden">
                <div style="background: #f9f9f9; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; max-height: 400px; overflow-y: auto;">
                    <pre id="log-content" style="margin: 0; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.4;">Caricamento log...</pre>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.nav-tab-wrapper { border-bottom: 1px solid #c3c4c7; margin-bottom: 20px; }
.nav-tab { border: 1px solid #c3c4c7; border-bottom: none; background: #f0f0f1; color: #50575e; text-decoration: none; padding: 10px 15px; margin-right: 2px; display: inline-block; border-radius: 3px 3px 0 0; position: relative; top: 1px; }
.nav-tab:hover { background: #fff; color: #2271b1; }
.nav-tab-active { background: #fff !important; border-bottom: 1px solid #fff !important; color: #2271b1 !important; z-index: 1; }
.tab-content { display: none; }
.tab-content.rs-tab-active { display: block; }
.rs-dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
.rs-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04); }
.rs-button-primary { background: #2271b1; border-color: #2271b1; color: white; padding: 8px 16px; border-radius: 3px; cursor: pointer; border: 1px solid; text-decoration: none; display: inline-block; margin-right: 10px; }
.rs-button-primary:hover { background: #135e96; border-color: #135e96; }
.rs-button-secondary { background: #f0f0f1; border-color: #c3c4c7; color: #2c3338; padding: 8px 16px; border-radius: 3px; cursor: pointer; border: 1px solid; text-decoration: none; display: inline-block; margin-right: 10px; }
.rs-button-secondary:hover { background: #e9e9ea; border-color: #8c8f94; }
.rs-button-danger { background: #dc3545; border-color: #dc3545; color: white; padding: 8px 16px; border-radius: 3px; cursor: pointer; border: 1px solid; text-decoration: none; display: inline-block; margin-right: 10px; }
.rs-button-danger:hover { background: #c82333; border-color: #bd2130; }
.rs-alert { padding: 12px 15px; margin: 15px 0; border-radius: 4px; border-left: 4px solid; }
.rs-alert-success { background: #d1e7dd; border-color: #00a32a; color: #0f5132; }
.rs-alert-error { background: #f8d7da; border-color: #d63638; color: #842029; }
.rs-alert-warning { background: #fff3cd; border-color: #f0ad4e; color: #997404; }
.rs-alert-info { background: #cff4fc; border-color: #2271b1; color: #055160; }
.rs-form-table { width: 100%; }
.rs-form-table th { text-align: left; padding: 10px 0; width: 150px; }
.rs-form-table td { padding: 10px 0; }
.rs-input { width: 100%; padding: 8px 12px; border: 1px solid #8c8f94; border-radius: 4px; }
.rs-hidden { display: none !important; }
.rs-spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid #f3f3f3; border-top: 2px solid #2271b1; border-radius: 50%; animation: rs-spin 1s linear infinite; margin-right: 5px; }
@keyframes rs-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
.rs-info-box { background: #f0f6fc; border: 1px solid #c9d6e7; border-radius: 4px; padding: 15px; margin: 15px 0; }
.rs-custom-fields-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
.rs-custom-fields-table th, .rs-custom-fields-table td { padding: 12px; text-align: left; border-bottom: 1px solid #c3c4c7; }
.rs-custom-fields-table th { background: #f0f0f1; font-weight: bold; }
.rs-custom-fields-table code { background: #f1f1f1; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
.rs-field-type { background: #2271b1; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
.rs-status-manual { color: #d63638; font-weight: bold; }
.rs-status-created { color: #00a32a; font-weight: bold; }
.rs-instruction-box { background: #f8f9fa; border: 1px solid #e1e5e9; border-radius: 4px; padding: 20px; margin: 15px 0; }
.rs-instruction-list { margin: 10px 0; padding-left: 25px; }
.rs-instruction-list li { margin-bottom: 8px; }
.rs-warning-box { background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin: 15px 0; }
.rs-warning-box h5 { margin-top: 0; color: #997404; }
.rs-actions-section { margin-top: 30px; }
.rs-custom-fields-section h4, .rs-instructions-section h4, .rs-actions-section h4 { color: #2271b1; margin-top: 25px; margin-bottom: 15px; }

/* INFO TAB REFINEMENT - NEW STYLES */
.rs-info-card-fixed { border-left: 4px solid #2271b1; }
.rs-info-card-collapsible { border-left: 4px solid #f59e0b; }
.rs-info-card-expanded { border-left: 4px solid #10b981; }
.rs-info-card-actions { border-left: 4px solid #6366f1; }

.rs-collapsible-header { position: relative; cursor: pointer; }
.rs-collapsible-toggle { float: right; font-size: 12px; color: #666; font-weight: normal; }

.rs-collapsible-trigger { 
    cursor: pointer; 
    padding: 10px 15px; 
    margin: 10px 0; 
    background: #f8f9fa; 
    border: 1px solid #e1e5e9; 
    border-radius: 4px; 
    transition: background 0.2s; 
}
.rs-collapsible-trigger:hover { background: #e9ecef; }
.rs-collapsible-trigger.active { background: #e7f3ff; border-color: #2271b1; }

.rs-toggle-icon { 
    display: inline-block; 
    transition: transform 0.2s; 
    margin-right: 8px; 
    color: #2271b1; 
}
.rs-toggle-icon.expanded { transform: rotate(90deg); }

.rs-collapsible-content { 
    display: none; 
    padding: 15px; 
    background: #fafbfc; 
    border-radius: 4px; 
    margin-top: 10px; 
}
.rs-collapsible-content.expanded { display: block; }

.rs-auto-status-display { 
    background: white; 
    padding: 15px; 
    border-radius: 4px; 
    border: 1px solid #e1e5e9; 
}

.rs-mapping-table-container { 
    background: white; 
    border-radius: 4px; 
    border: 1px solid #e1e5e9; 
    overflow: hidden; 
}

.rs-mapping-table { 
    width: 100%; 
    border-collapse: collapse; 
    margin: 0; 
}
.rs-mapping-table th, 
.rs-mapping-table td { 
    padding: 12px; 
    text-align: left; 
    border-bottom: 1px solid #e1e5e9; 
    font-size: 13px; 
}
.rs-mapping-table th { 
    background: #f8f9fa; 
    font-weight: bold; 
    color: #374151; 
}
.rs-mapping-table tbody tr:hover { background: #f9fafb; }

.rs-streamlined-actions { margin-top: 20px; }
.rs-action-item { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    padding: 20px; 
    background: #f8f9fa; 
    border-radius: 4px; 
    border: 1px solid #e1e5e9; 
}
.rs-action-info h4 { margin: 0 0 5px 0; color: #374151; }
.rs-action-info p { margin: 0; color: #6b7280; font-size: 14px; }

.rs-test-results { 
    margin-top: 20px; 
    padding: 15px; 
    background: white; 
    border-radius: 4px; 
    border: 1px solid #e1e5e9; 
}
.rs-test-results h5 { margin: 0 0 10px 0; color: #374151; }
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var targetTab = $(this).data('tab');
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').removeClass('rs-tab-active');
        $(this).addClass('nav-tab-active');
        $('#' + targetTab).addClass('rs-tab-active');
    });
    
    var dashboard = {
        init: function() { 
            this.bindEvents(); 
            
            // 🔄 AUTO-LOAD INFO TAB FEATURES
            this.autoLoadInfoTabFeatures();
            
            // 🆕 INIT COLLAPSIBLE SECTIONS
            this.initCollapsibleSections();
        },
        
        // 🧪 TEST FIELD POPULATION ENHANCED
        testFieldPopulationEnhanced: function(e) {
            e.preventDefault();
            
            // Show test results area
            $('#test-results-display').show();
            $('#test-results-content').html('<p><span class="rs-spinner"></span> Running enhanced field population test...</p>');
            
            dashboard.showAlert('🧪 Testing custom fields population with enhanced validation...', 'info');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { 
                    action: 'realestate_sync_test_field_population', 
                    nonce: realestateSync.nonce,
                    enhanced: true  // Enhanced mode flag
                },
                beforeSend: function() {
                    $('#test-field-population-enhanced').prop('disabled', true).html('<span class="rs-spinner"></span>Testing...');
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;
                        
                        // Enhanced results display
                        var html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 15px;">';
                        html += '<div style="text-align: center; padding: 10px; background: #f0f6fc; border-radius: 4px;"><strong>Fields Tested</strong><br><span style="font-size: 18px; color: #2271b1;">' + (result.fields_tested || 0) + '</span></div>';
                        html += '<div style="text-align: center; padding: 10px; background: #f0fdf4; border-radius: 4px;"><strong>Successful</strong><br><span style="font-size: 18px; color: #00a32a;">' + (result.successful_mappings || 0) + '</span></div>';
                        html += '<div style="text-align: center; padding: 10px; background: #fef2f2; border-radius: 4px;"><strong>Failed</strong><br><span style="font-size: 18px; color: #d63638;">' + (result.failed_mappings || 0) + '</span></div>';
                        html += '</div>';
                        
                        if (result.test_details && result.test_details.length > 0) {
                            html += '<h6>Test Details:</h6>';
                            html += '<table style="width: 100%; border-collapse: collapse; font-size: 12px;">';
                            html += '<thead><tr style="background: #f9f9f9;"><th style="padding: 6px; border: 1px solid #ddd;">Field</th><th style="padding: 6px; border: 1px solid #ddd;">XML Value</th><th style="padding: 6px; border: 1px solid #ddd;">Status</th></tr></thead><tbody>';
                            
                            result.test_details.forEach(function(detail) {
                                var statusColor = detail.success ? '#00a32a' : '#d63638';
                                var statusIcon = detail.success ? '✅' : '❌';
                                html += '<tr><td style="padding: 6px; border: 1px solid #ddd;"><code>' + detail.field + '</code></td>';
                                html += '<td style="padding: 6px; border: 1px solid #ddd;">' + (detail.xml_value || 'N/A') + '</td>';
                                html += '<td style="padding: 6px; border: 1px solid #ddd; color: ' + statusColor + ';">' + statusIcon + ' ' + (detail.success ? 'OK' : 'Failed') + '</td></tr>';
                            });
                            
                            html += '</tbody></table>';
                        }
                        
                        $('#test-results-content').html(html);
                        
                        var message = '🎉 Enhanced test completed! Fields tested: ' + (result.fields_tested || 0) + ', Successful: ' + (result.successful_mappings || 0);
                        dashboard.showAlert(message, result.failed_mappings === 0 ? 'success' : 'warning');
                        
                    } else {
                        $('#test-results-content').html('<p style="color: #d63638;">Enhanced test failed: ' + response.data + '</p>');
                        dashboard.showAlert('😨 Enhanced population test failed: ' + response.data, 'error');
                    }
                },
                error: function() { 
                    $('#test-results-content').html('<p style="color: #d63638;">Communication error during enhanced test</p>');
                    dashboard.showAlert('😨 Communication error during enhanced test', 'error'); 
                },
                complete: function() {
                    $('#test-field-population-enhanced').prop('disabled', false).html('<span class="dashicons dashicons-performance"></span> Run Test');
                }
            });
        },
        
        // 🆕 UPDATE COLLAPSIBLE FIELD STATUS
        updateCollapsibleFieldStatus: function(data) {
            if (!data.field_details || data.field_details.length === 0) return;
            
            var html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">';
            html += '<div style="padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #00a32a;">';
            html += '<h5 style="margin: 0 0 5px 0; color: #00a32a;">✅ Fields Created</h5>';
            html += '<div style="font-size: 20px; font-weight: bold;">' + (data.created_count || 0) + ' / 9</div></div>';
            
            html += '<div style="padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #d63638;">';
            html += '<h5 style="margin: 0 0 5px 0; color: #d63638;">❌ Missing Fields</h5>';
            html += '<div style="font-size: 20px; font-weight: bold;">' + (data.missing_count || 9) + ' / 9</div></div>';
            html += '</div>';
            
            if (data.field_details && data.field_details.length > 0) {
                html += '<h6>Field Status Details:</h6>';
                html += '<table style="width: 100%; border-collapse: collapse;">';
                html += '<thead><tr style="background: #f9f9f9;"><th style="padding: 8px; border: 1px solid #ddd;">Field Name</th><th style="padding: 8px; border: 1px solid #ddd;">Status</th><th style="padding: 8px; border: 1px solid #ddd;">Label</th></tr></thead><tbody>';
                
                data.field_details.forEach(function(field) {
                    var statusIcon = field.exists ? '✅' : '❌';
                    var statusColor = field.exists ? '#00a32a' : '#d63638';
                    html += '<tr><td style="padding: 8px; border: 1px solid #ddd;"><code>' + field.name + '</code></td>';
                    html += '<td style="padding: 8px; border: 1px solid #ddd; color: ' + statusColor + ';">' + statusIcon + ' ' + (field.exists ? 'Created' : 'Missing') + '</td>';
                    html += '<td style="padding: 8px; border: 1px solid #ddd;">' + (field.label || 'N/A') + '</td></tr>';
                });
                
                html += '</tbody></table>';
            }
            
            $('#field-status-auto-display').html(html);
        },
        
        // 🆕 INIT COLLAPSIBLE SECTIONS
        initCollapsibleSections: function() {
            // Bind collapsible triggers
            $('.rs-collapsible-trigger').on('click', function() {
                var $trigger = $(this);
                var section = $trigger.data('section');
                var $content = $('#section-' + section);
                var $icon = $trigger.find('.rs-toggle-icon');
                
                // Toggle content
                $content.toggleClass('expanded');
                $trigger.toggleClass('active');
                $icon.toggleClass('expanded');
            });
        },
        
        // 🔄 AUTO-LOAD XML MAPPING FOR ALWAYS EXPANDED TABLE
        autoLoadXMLMappingTable: function() {
            // Load mapping data into the always-expanded table
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { 
                    action: 'realestate_sync_get_field_mapping_table', 
                    nonce: realestateSync.nonce 
                },
                success: function(response) {
                    if (response.success) {
                        dashboard.displayMappingTable(response.data);
                    } else {
                        $('#mapping-table-body').html('<tr><td colspan="4" style="text-align: center; color: #d63638;">Error loading mapping: ' + response.data + '</td></tr>');
                    }
                },
                error: function() {
                    $('#mapping-table-body').html('<tr><td colspan="4" style="text-align: center; color: #d63638;">Communication error loading mapping</td></tr>');
                }
            });
        },
        
        // 📊 DISPLAY MAPPING TABLE DATA
        displayMappingTable: function(mapping) {
            var html = '';
            
            // Property Core Fields
            if (mapping.property_core) {
                Object.keys(mapping.property_core).forEach(function(xmlField) {
                    html += '<tr>';
                    html += '<td><code>' + xmlField + '</code></td>';
                    html += '<td>' + mapping.property_core[xmlField] + '</td>';
                    html += '<td><span style="color: #10b981; font-weight: bold;">✅ Mappato</span></td>';
                    html += '<td>Core property field</td>';
                    html += '</tr>';
                });
            }
            
            // Custom Fields
            if (mapping.custom_fields) {
                Object.keys(mapping.custom_fields).forEach(function(xmlField) {
                    html += '<tr>';
                    html += '<td><code>' + xmlField + '</code></td>';
                    html += '<td><strong>' + mapping.custom_fields[xmlField] + '</strong></td>';
                    html += '<td><span style="color: #f59e0b; font-weight: bold;">⚠️ Manual</span></td>';
                    html += '<td>Requires manual field creation</td>';
                    html += '</tr>';
                });
            }
            
            // Taxonomy Fields
            if (mapping.taxonomies) {
                Object.keys(mapping.taxonomies).forEach(function(xmlField) {
                    html += '<tr>';
                    html += '<td><code>' + xmlField + '</code></td>';
                    html += '<td>' + mapping.taxonomies[xmlField] + '</td>';
                    html += '<td><span style="color: #10b981; font-weight: bold;">✅ Mappato</span></td>';
                    html += '<td>Taxonomy mapping</td>';
                    html += '</tr>';
                });
            }
            
            // Media Fields
            if (mapping.media) {
                Object.keys(mapping.media).forEach(function(xmlField) {
                    html += '<tr>';
                    html += '<td><code>' + xmlField + '</code></td>';
                    html += '<td>' + mapping.media[xmlField] + '</td>';
                    html += '<td><span style="color: #10b981; font-weight: bold;">✅ Mappato</span></td>';
                    html += '<td>Media processing</td>';
                    html += '</tr>';
                });
            }
            
            if (html === '') {
                html = '<tr><td colspan="4" style="text-align: center; padding: 20px;">No mapping data available</td></tr>';
            }
            
            $('#mapping-table-body').html(html);
        },
        autoLoadInfoTabFeatures: function() {
            // Auto-check field status when Info tab is active or page loads
            var currentTab = $('.nav-tab-active').data('tab');
            if (currentTab === 'info') {
                this.autoCheckFieldStatus();
                this.autoLoadXMLMappingTable();
            }
            
            // Auto-load when switching to Info tab
            $('.nav-tab[data-tab="info"]').on('click', function() {
                setTimeout(function() {
                    dashboard.autoCheckFieldStatus();
                    dashboard.autoLoadXMLMappingTable();
                }, 100);
            });
        },
        
        // 🔄 AUTO-CHECK FIELD STATUS (Silent)
        autoCheckFieldStatus: function() {
            // Silent check - no loading indicators, just update the table
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { 
                    action: 'realestate_sync_check_field_status', 
                    nonce: realestateSync.nonce 
                },
                success: function(response) {
                    if (response.success) {
                        dashboard.updateFieldStatusTable(response.data);
                        dashboard.updateCollapsibleFieldStatus(response.data);
                    }
                },
                error: function() {
                    // Silent fail - don't show errors for auto-check
                }
            });
        },
        
        // 🔄 AUTO-LOAD XML MAPPING (Always Expanded)
        autoLoadXMLMapping: function() {
            // Auto-load and expand XML mapping
            $('#xml-mapping-display').removeClass('rs-hidden');
            $('#xml-mapping-content').html('<p><span class="rs-spinner"></span>Loading XML mapping...</p>');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { 
                    action: 'realestate_sync_get_field_mapping', 
                    nonce: realestateSync.nonce 
                },
                success: function(response) {
                    if (response.success) {
                        dashboard.displayXMLMapping(response.data);
                    } else {
                        $('#xml-mapping-content').html('<p style="color: #d63638;">Error loading mapping: ' + response.data + '</p>');
                    }
                },
                error: function() {
                    $('#xml-mapping-content').html('<p style="color: #d63638;">Communication error loading mapping</p>');
                }
            });
        },
        
        // 🔄 UPDATE FIELD STATUS TABLE
        updateFieldStatusTable: function(data) {
            if (!data.field_details || data.field_details.length === 0) return;
            
            // Update each field status in the main table
            data.field_details.forEach(function(field) {
                var $row = $('.rs-custom-fields-table tbody tr').filter(function() {
                    return $(this).find('code').text() === field.name;
                });
                
                if ($row.length > 0) {
                    var $statusCell = $row.find('td:last-child');
                    if (field.exists) {
                        $statusCell.html('<span class="rs-status-created">✅ Created</span>');
                    } else {
                        $statusCell.html('<span class="rs-status-manual">❌ Manual</span>');
                    }
                }
            });
            
            // Update summary if available
            if (data.created_count !== undefined) {
                var summaryMessage = data.created_count + ' / 9 custom fields created';
                if (data.created_count === 9) {
                    summaryMessage += ' ✅ Complete!';
                }
                
                // Update info box if present
                $('.rs-info-box p').html(
                    'The RealEstate Sync plugin requires 9 additional custom fields to be created manually in WpResidence.<br>' +
                    'These fields enhance property details with specialized data from GestionaleImmobiliare.it XML.<br>' +
                    '<strong>Status: ' + summaryMessage + '</strong>'
                );
            }
        },
        
        // 🔄 DISPLAY XML MAPPING (Enhanced)
        displayXMLMapping: function(mapping) {
            var html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
            
            // Property Core Fields
            html += '<div><h6 style="color: #2271b1; margin-bottom: 10px;">🏠 Property Core Fields</h6>';
            html += '<table style="width: 100%; border-collapse: collapse; font-size: 12px;">';
            html += '<thead><tr style="background: #f0f0f1;"><th style="padding: 6px; border: 1px solid #ddd;">XML Field</th><th style="padding: 6px; border: 1px solid #ddd;">WordPress Field</th></tr></thead><tbody>';
            
            if (mapping.property_core) {
                Object.keys(mapping.property_core).forEach(function(xmlField) {
                    html += '<tr><td style="padding: 6px; border: 1px solid #ddd;"><code>' + xmlField + '</code></td>';
                    html += '<td style="padding: 6px; border: 1px solid #ddd;">' + mapping.property_core[xmlField] + '</td></tr>';
                });
            }
            html += '</tbody></table></div>';
            
            // Custom Fields
            html += '<div><h6 style="color: #d63638; margin-bottom: 10px;">🔧 Custom Fields (Manual Creation)</h6>';
            html += '<table style="width: 100%; border-collapse: collapse; font-size: 12px;">';
            html += '<thead><tr style="background: #f0f0f1;"><th style="padding: 6px; border: 1px solid #ddd;">XML Field</th><th style="padding: 6px; border: 1px solid #ddd;">Custom Field</th></tr></thead><tbody>';
            
            if (mapping.custom_fields) {
                Object.keys(mapping.custom_fields).forEach(function(xmlField) {
                    html += '<tr><td style="padding: 6px; border: 1px solid #ddd;"><code>' + xmlField + '</code></td>';
                    html += '<td style="padding: 6px; border: 1px solid #ddd;"><strong>' + mapping.custom_fields[xmlField] + '</strong></td></tr>';
                });
            }
            html += '</tbody></table></div>';
            html += '</div>';
            
            // Coverage Summary
            if (mapping.coverage_summary) {
                html += '<div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">';
                html += '<h6 style="margin: 0 0 10px 0;">Mapping Coverage Summary</h6>';
                html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">';
                
                html += '<div style="text-align: center;"><strong>Total XML Fields</strong><br><span style="font-size: 18px; color: #2271b1;">' + (mapping.coverage_summary.total_xml_fields || 'N/A') + '</span></div>';
                html += '<div style="text-align: center;"><strong>Mapped Fields</strong><br><span style="font-size: 18px; color: #00a32a;">' + (mapping.coverage_summary.mapped_fields || 'N/A') + '</span></div>';
                html += '<div style="text-align: center;"><strong>Coverage</strong><br><span style="font-size: 18px; color: #f59e0b;">' + (mapping.coverage_summary.coverage_percentage || 'N/A') + '%</span></div>';
                
                html += '</div></div>';
            }
            
            $('#xml-mapping-content').html(html);
        },
        bindEvents: function() {
            $('#start-manual-import').on('click', this.startManualImport);
            $('#rs-test-connection').on('click', this.testConnection);
            $('#rs-quick-settings').on('submit', this.saveSettings);

            // XML Credentials Edit Mode
            $('input[name="credential_source"]').on('change', this.onCredentialSourceChange);
            $('#rs-xml-edit-btn').on('click', this.enableXmlEdit);
            $('#rs-xml-cancel-btn').on('click', this.cancelXmlEdit);
            $('#rs-xml-credentials-form').on('submit', this.saveXmlCredentials);

            $('#test-xml-file').on('change', this.onFileSelect);
            $('#process-test-file').on('click', this.processTestFile);
            $('#create-property-fields').on('click', this.createPropertyFields);
            $('#create-properties-from-sample').on('click', this.createPropertiesFromSampleV3);
            $('#show-property-stats').on('click', this.showPropertyStats);
            $('#cleanup-test-data').on('click', this.cleanupTestData);
            $('#cleanup-properties').on('click', this.cleanupProperties);
            $('#view-logs').on('click', this.viewLogs);
            // Force processing toggle removed - now using normal processing mode
            
            // 🚀 PROFESSIONAL ACTIVATION TOOLS EVENTS
            $('#check-activation-status').on('click', this.checkActivationStatus);
            $('#view-activation-info').on('click', this.viewActivationInfo);
            $('#test-activation-workflow').on('click', this.testActivationWorkflow);
            
            // 📋 INFO TAB EVENTS
            $('#check-field-status').on('click', this.checkFieldStatus);
            $('#view-field-mapping').on('click', this.viewFieldMapping);
            $('#test-field-population').on('click', this.testFieldPopulation);
            $('#test-field-population-enhanced').on('click', this.testFieldPopulationEnhanced);
        },
        
        // 📋 INFO TAB METHODS
        checkFieldStatus: function(e) {
            e.preventDefault();
            
            $('#field-status-results').removeClass('rs-hidden');
            $('#field-status-content').html('<p><span class="rs-spinner"></span>Checking custom fields status...</p>');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { 
                    action: 'realestate_sync_check_field_status', 
                    nonce: realestateSync.nonce 
                },
                beforeSend: function() {
                    $('#check-field-status').prop('disabled', true).html('<span class="rs-spinner"></span>Checking...');
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;
                        
                        var html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">';
                        html += '<div style="padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #00a32a;">';
                        html += '<h5 style="margin: 0 0 5px 0; color: #00a32a;">✅ Fields Created</h5>';
                        html += '<div style="font-size: 20px; font-weight: bold;">' + (result.created_count || 0) + ' / 9</div></div>';
                        
                        html += '<div style="padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #d63638;">';
                        html += '<h5 style="margin: 0 0 5px 0; color: #d63638;">❌ Missing Fields</h5>';
                        html += '<div style="font-size: 20px; font-weight: bold;">' + (result.missing_count || 9) + ' / 9</div></div>';
                        html += '</div>';
                        
                        if (result.field_details && result.field_details.length > 0) {
                            html += '<h6>Field Status Details:</h6>';
                            html += '<table style="width: 100%; border-collapse: collapse;">';
                            html += '<thead><tr style="background: #f9f9f9;"><th style="padding: 8px; border: 1px solid #ddd;">Field Name</th><th style="padding: 8px; border: 1px solid #ddd;">Status</th><th style="padding: 8px; border: 1px solid #ddd;">Label</th></tr></thead><tbody>';
                            
                            result.field_details.forEach(function(field) {
                                var statusIcon = field.exists ? '✅' : '❌';
                                var statusColor = field.exists ? '#00a32a' : '#d63638';
                                html += '<tr><td style="padding: 8px; border: 1px solid #ddd;"><code>' + field.name + '</code></td>';
                                html += '<td style="padding: 8px; border: 1px solid #ddd; color: ' + statusColor + ';">' + statusIcon + ' ' + (field.exists ? 'Created' : 'Missing') + '</td>';
                                html += '<td style="padding: 8px; border: 1px solid #ddd;">' + (field.label || 'N/A') + '</td></tr>';
                            });
                            
                            html += '</tbody></table>';
                        }
                        
                        $('#field-status-content').html(html);
                        
                        var message = 'Field check completed. ' + (result.created_count || 0) + ' fields created, ' + (result.missing_count || 9) + ' missing.';
                        dashboard.showAlert(message, result.created_count === 9 ? 'success' : 'warning');
                        
                    } else {
                        $('#field-status-content').html('<p style="color: #d63638;">Error checking fields: ' + response.data + '</p>');
                        dashboard.showAlert('🚨 Field status check failed: ' + response.data, 'error');
                    }
                },
                error: function() { 
                    $('#field-status-content').html('<p style="color: #d63638;">Communication error</p>');
                    dashboard.showAlert('🚨 Communication error during field check', 'error'); 
                },
                complete: function() {
                    $('#check-field-status').prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> Check Field Status');
                }
            });
        },
        
        viewFieldMapping: function(e) {
            e.preventDefault();
            
            $('#xml-mapping-display').toggleClass('rs-hidden');
            
            if (!$('#xml-mapping-display').hasClass('rs-hidden')) {
                $('#xml-mapping-content').html('<p><span class="rs-spinner"></span>Loading XML mapping...</p>');
                
                $.ajax({
                    url: realestateSync.ajax_url,
                    type: 'POST',
                    data: { 
                        action: 'realestate_sync_get_field_mapping', 
                        nonce: realestateSync.nonce 
                    },
                    success: function(response) {
                        if (response.success) {
                            var mapping = response.data;
                            
                            var html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
                            
                            // Property Core Fields
                            html += '<div><h6 style="color: #2271b1; margin-bottom: 10px;">🏠 Property Core Fields</h6>';
                            html += '<table style="width: 100%; border-collapse: collapse; font-size: 12px;">';
                            html += '<thead><tr style="background: #f0f0f1;"><th style="padding: 6px; border: 1px solid #ddd;">XML Field</th><th style="padding: 6px; border: 1px solid #ddd;">WordPress Field</th></tr></thead><tbody>';
                            
                            if (mapping.property_core) {
                                Object.keys(mapping.property_core).forEach(function(xmlField) {
                                    html += '<tr><td style="padding: 6px; border: 1px solid #ddd;"><code>' + xmlField + '</code></td>';
                                    html += '<td style="padding: 6px; border: 1px solid #ddd;">' + mapping.property_core[xmlField] + '</td></tr>';
                                });
                            }
                            html += '</tbody></table></div>';
                            
                            // Custom Fields
                            html += '<div><h6 style="color: #d63638; margin-bottom: 10px;">🔧 Custom Fields (Manual Creation)</h6>';
                            html += '<table style="width: 100%; border-collapse: collapse; font-size: 12px;">';
                            html += '<thead><tr style="background: #f0f0f1;"><th style="padding: 6px; border: 1px solid #ddd;">XML Field</th><th style="padding: 6px; border: 1px solid #ddd;">Custom Field</th></tr></thead><tbody>';
                            
                            if (mapping.custom_fields) {
                                Object.keys(mapping.custom_fields).forEach(function(xmlField) {
                                    html += '<tr><td style="padding: 6px; border: 1px solid #ddd;"><code>' + xmlField + '</code></td>';
                                    html += '<td style="padding: 6px; border: 1px solid #ddd;"><strong>' + mapping.custom_fields[xmlField] + '</strong></td></tr>';
                                });
                            }
                            html += '</tbody></table></div>';
                            html += '</div>';
                            
                            $('#xml-mapping-content').html(html);
                            dashboard.showAlert('🗺️ XML mapping displayed with current coverage', 'info');
                            
                        } else {
                            $('#xml-mapping-content').html('<p style="color: #d63638;">Error loading mapping: ' + response.data + '</p>');
                            dashboard.showAlert('🚨 Failed to load XML mapping: ' + response.data, 'error');
                        }
                    },
                    error: function() {
                        $('#xml-mapping-content').html('<p style="color: #d63638;">Communication error</p>');
                        dashboard.showAlert('🚨 Communication error loading mapping', 'error');
                    }
                });
            }
        },
        
        testFieldPopulation: function(e) {
            e.preventDefault();
            
            if (!confirm('🧪 Test Custom Fields Population?\n\nThis will test the mapping of XML data to custom fields using sample data.')) return;
            
            dashboard.showAlert('🧪 Testing custom fields population...', 'info');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { 
                    action: 'realestate_sync_test_field_population', 
                    nonce: realestateSync.nonce 
                },
                beforeSend: function() {
                    $('#test-field-population').prop('disabled', true).html('<span class="rs-spinner"></span>Testing...');
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;
                        
                        var message = '🎉 Population test completed! ';
                        message += 'Fields tested: ' + (result.fields_tested || 0) + ', ';
                        message += 'Successful: ' + (result.successful_mappings || 0) + ', ';
                        message += 'Failed: ' + (result.failed_mappings || 0);
                        
                        dashboard.showAlert(message, result.failed_mappings === 0 ? 'success' : 'warning');
                        
                        console.log('🧪 Field Population Test Results:', result);
                        
                    } else {
                        dashboard.showAlert('🚨 Population test failed: ' + response.data, 'error');
                    }
                },
                error: function() { 
                    dashboard.showAlert('🚨 Communication error during population test', 'error'); 
                },
                complete: function() {
                    $('#test-field-population').prop('disabled', false).html('<span class="dashicons dashicons-performance"></span> Test Field Population');
                }
            });
        },
        createPropertyFields: function(e) {
            e.preventDefault();
            
            if (!confirm('🔥 CREATE CUSTOM FIELDS with NEW AUTOMATION METHOD?\n\nThis will create 9 Property Details fields using the AJAX mechanism discovered from cURL analysis.\n\n⚠️ SAFE TESTING: Will create test field first for validation.')) return;
            
            dashboard.showAlert('🚀 Creating Property Details with NEW automation method...', 'warning');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { 
                    action: 'realestate_sync_create_property_fields_v2', 
                    nonce: realestateSync.nonce,
                    test_mode: true  // Start with test field first
                },
                beforeSend: function() {
                    $('#create-property-fields').prop('disabled', true).html('<span class="rs-spinner"></span>🔥 Creating with NEW Method...');
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;
                        var message = result.summary_message || 'Custom fields automation completed!';
                        
                        // Enhanced success message with automation details
                        if (result.created_count > 0) {
                            message = '🎉 AUTOMATION SUCCESS: ' + result.created_count + ' custom fields created automatically!';
                            if (result.test_mode) {
                                message += ' (Test mode - validate and run again for full automation)';
                            }
                        }
                        
                        dashboard.showAlert(message, 'success');
                        
                        // Show automation details in console
                        if (result.automation_details) {
                            console.log('🔥 Custom Fields Automation Details:', result.automation_details);
                        }
                        
                        // Show next steps if test mode
                        if (result.test_mode && result.created_count > 0) {
                            setTimeout(function() {
                                dashboard.showAlert('✅ Test field created successfully! Click again to create all 9 fields.', 'info');
                            }, 3000);
                        }
                        
                    } else {
                        dashboard.showAlert('🚨 NEW METHOD ERROR: ' + response.data, 'error');
                    }
                },
                error: function() { 
                    dashboard.showAlert('🚨 Communication error with new automation method', 'error'); 
                },
                complete: function() {
                    $('#create-property-fields').prop('disabled', false).html('<span class="dashicons dashicons-plus-alt"></span> 🔥 Create Property Fields (NEW)');
                }
            });
        },
        startManualImport: function(e) {
            e.preventDefault();
            if (!confirm('Sei sicuro di voler avviare l\'import manuale?')) return;

            dashboard.showAlert('Import avviato...', 'warning');
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_manual_import',
                    nonce: realestateSync.nonce,
                    mark_as_test: $('#mark-as-test-manual-import').is(':checked') ? '1' : '0'
                },
                beforeSend: function() {
                    $('#start-manual-import').prop('disabled', true).html('<span class="rs-spinner"></span> Import in corso...');
                },
                success: function(response) {
                    if (response.success) {
                        dashboard.showAlert('Import completato con successo!', 'success');
                        location.reload();
                    } else {
                        dashboard.showAlert('Errore: ' + response.data, 'error');
                    }
                },
                complete: function() {
                    $('#start-manual-import').prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Scarica e Importa Ora');
                },
                error: function() { dashboard.showAlert('Errore di comunicazione', 'error'); }
            });
        },
        testConnection: function(e) {
            e.preventDefault();

            // Backend uses credential_source toggle to determine which credentials to use
            var credSource = $('input[name="credential_source"]:checked').val();
            var testingMsg = credSource === 'database' ? 'Testo credenziali database...' : 'Testo credenziali hardcoded...';

            dashboard.showAlert(testingMsg, 'info');

            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_test_connection',
                    nonce: realestateSync.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var msg = 'Connessione riuscita con credenziali ' + (credSource === 'database' ? 'database' : 'hardcoded') + '!';
                        dashboard.showAlert(msg, 'success');
                        $('#rs-test-connection-result').html('<div style="color: green; margin-top: 10px;">✓ ' + msg + '</div>');
                    } else {
                        var errorMsg = response.data?.message || 'Errore sconosciuto';
                        dashboard.showAlert('Test fallito: ' + errorMsg, 'error');
                        $('#rs-test-connection-result').html('<div style="color: red; margin-top: 10px;">✗ Test fallito: ' + errorMsg + '</div>');
                    }
                },
                error: function() {
                    dashboard.showAlert('Errore durante test connessione', 'error');
                    $('#rs-test-connection-result').html('<div style="color: red; margin-top: 10px;">✗ Errore comunicazione server</div>');
                }
            });
        },
        saveSettings: function(e) {
            e.preventDefault();
            var formData = $('#rs-quick-settings').serialize() + '&action=realestate_sync_save_settings&nonce=' + realestateSync.nonce;
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    dashboard.showAlert(response.success ? 'Configurazione salvata!' : 'Errore salvataggio: ' + response.data, response.success ? 'success' : 'error');
                },
                error: function() { dashboard.showAlert('Errore comunicazione server', 'error'); }
            });
        },

        // XML Credentials Management Functions
        onCredentialSourceChange: function(e) {
            var source = $('input[name="credential_source"]:checked').val();

            // Save credential source immediately
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_save_credential_source',
                    nonce: realestateSync.nonce,
                    source: source
                },
                success: function(response) {
                    if (response.success) {
                        dashboard.showAlert('Sorgente credenziali aggiornata: ' + (source === 'hardcoded' ? 'Hardcoded' : 'Database'), 'success');
                    }
                }
            });
        },

        enableXmlEdit: function(e) {
            e.preventDefault();

            // Enable input fields
            $('#xml_url, #xml_user, #xml_pass').prop('readonly', false).css('background-color', '#fff');

            // Hide Edit button, show Save/Cancel buttons
            $('#rs-xml-edit-btn').hide();
            $('#rs-xml-save-cancel-btns').show();

            // Store original values for cancel
            $('#xml_url').data('original', $('#xml_url').val());
            $('#xml_user').data('original', $('#xml_user').val());
            $('#xml_pass').data('original', $('#xml_pass').val());
        },

        cancelXmlEdit: function(e) {
            e.preventDefault();

            // Restore original values
            $('#xml_url').val($('#xml_url').data('original'));
            $('#xml_user').val($('#xml_user').data('original'));
            $('#xml_pass').val($('#xml_pass').data('original'));

            // Disable input fields
            $('#xml_url, #xml_user, #xml_pass').prop('readonly', true).css('background-color', '#f0f0f1');

            // Show Edit button, hide Save/Cancel buttons
            $('#rs-xml-edit-btn').show();
            $('#rs-xml-save-cancel-btns').hide();
        },

        saveXmlCredentials: function(e) {
            e.preventDefault();

            var xmlUrl = $('#xml_url').val();
            var xmlUser = $('#xml_user').val();
            var xmlPass = $('#xml_pass').val();

            if (!xmlUrl || !xmlUser || !xmlPass) {
                dashboard.showAlert('Compila tutti i campi XML', 'error');
                return;
            }

            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_save_xml_credentials',
                    nonce: $('input[name="xml_nonce"]').val(),
                    xml_url: xmlUrl,
                    xml_user: xmlUser,
                    xml_pass: xmlPass
                },
                beforeSend: function() {
                    $('#rs-xml-save-cancel-btns button[type="submit"]').prop('disabled', true)
                        .html('<span class="rs-spinner"></span> Salvataggio...');
                },
                success: function(response) {
                    if (response.success) {
                        dashboard.showAlert('Credenziali XML salvate con successo!', 'success');

                        // Disable input fields
                        $('#xml_url, #xml_user, #xml_pass').prop('readonly', true).css('background-color', '#f0f0f1');

                        // Show Edit button, hide Save/Cancel buttons
                        $('#rs-xml-edit-btn').show();
                        $('#rs-xml-save-cancel-btns').hide();
                    } else {
                        dashboard.showAlert('Errore salvataggio: ' + (response.data || 'Errore sconosciuto'), 'error');
                    }
                },
                error: function() {
                    dashboard.showAlert('Errore comunicazione server', 'error');
                },
                complete: function() {
                    $('#rs-xml-save-cancel-btns button[type="submit"]').prop('disabled', false)
                        .html('<span class="dashicons dashicons-yes"></span> Salva Credenziali');
                }
            });
        },

        createPropertiesFromSampleV3: function(e) {
            e.preventDefault();
            if (!confirm('Creare properties di test con Property Mapper v3.0?')) return;
            dashboard.showAlert('Creazione properties v3.0 in corso...', 'warning');
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { action: 'realestate_sync_create_properties_from_sample', nonce: realestateSync.nonce },
                beforeSend: function() {
                    $('#create-properties-from-sample').prop('disabled', true).html('<span class="rs-spinner"></span>Creando...');
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;
                        var message = 'Properties v3.0: ' + result.created_count + ' create, ' + result.updated_count + ' aggiornate';
                        if (result.features_created > 0) message += ', ' + result.features_created + ' features create';
                        dashboard.showAlert(message, 'success');
                        if (!$('#property-stats-display').hasClass('rs-hidden')) $('#show-property-stats').click();
                    } else {
                        dashboard.showAlert('Errore: ' + response.data, 'error');
                    }
                },
                error: function() { dashboard.showAlert('Errore comunicazione', 'error'); },
                complete: function() {
                    $('#create-properties-from-sample').prop('disabled', false).html('<span class="dashicons dashicons-plus-alt"></span> Crea Properties da Sample v3.0');
                }
            });
        },
        showPropertyStats: function(e) {
            e.preventDefault();
            $('#property-stats-display').removeClass('rs-hidden');
            $('#property-stats-content').html('<p><span class="rs-spinner"></span>Caricamento...</p>');
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { action: 'realestate_sync_get_property_stats', nonce: realestateSync.nonce },
                success: function(response) {
                    if (response.success) {
                        var stats = response.data;
                        var html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
                        html += '<div style="padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #2271b1;">';
                        html += '<h5 style="margin: 0 0 5px 0;">Total Properties</h5>';
                        html += '<div style="font-size: 24px; font-weight: bold; color: #2271b1;">' + stats.total_properties + '</div></div>';
                        if (stats.by_category) {
                            html += '<div style="padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #00a32a;">';
                            html += '<h5 style="margin: 0 0 10px 0;">Per Categoria</h5>';
                            Object.keys(stats.by_category).forEach(function(category) {
                                html += '<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">';
                                html += '<span>' + category + '</span><strong>' + stats.by_category[category] + '</strong></div>';
                            });
                            html += '</div>';
                        }
                        html += '</div>';
                        $('#property-stats-content').html(html);
                    } else {
                        $('#property-stats-content').html('<p style="color: #d63638;">Errore: ' + response.data + '</p>');
                    }
                },
                error: function() { $('#property-stats-content').html('<p style="color: #d63638;">Errore comunicazione</p>'); }
            });
        },
        cleanupProperties: function(e) {
            e.preventDefault();
            var confirmation = prompt('ATTENZIONE: Cancellazione TUTTE properties.\n\nPer confermare scrivi "CANCELLA TUTTO":');
            if (confirmation !== 'CANCELLA TUTTO') { alert('Operazione annullata'); return; }
            dashboard.showAlert('Cancellazione in corso...', 'warning');
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { action: 'realestate_sync_cleanup_properties', nonce: realestateSync.nonce },
                beforeSend: function() { $('#cleanup-properties').prop('disabled', true).html('<span class="rs-spinner"></span>Cancellazione...'); },
                success: function(response) {
                    if (response.success) {
                        dashboard.showAlert('Properties cancellate: ' + response.data.deleted_count, 'success');
                        if (!$('#property-stats-display').hasClass('rs-hidden')) $('#show-property-stats').click();
                    } else {
                        dashboard.showAlert('Errore: ' + response.data, 'error');
                    }
                },
                error: function() { dashboard.showAlert('Errore comunicazione', 'error'); },
                complete: function() { $('#cleanup-properties').prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Cleanup Database'); }
            });
        },
        viewLogs: function(e) {
            e.preventDefault();
            $('#log-viewer').toggleClass('rs-hidden');
            if (!$('#log-viewer').hasClass('rs-hidden')) {
                $('#log-content').text('Caricamento log...');
                $.ajax({
                    url: realestateSync.ajax_url,
                    type: 'POST',
                    data: { action: 'realestate_sync_get_logs', nonce: realestateSync.nonce },
                    success: function(response) {
                        $('#log-content').text(response.success ? (response.data.logs || 'Nessun log') : 'Errore: ' + response.data);
                    },
                    error: function() { $('#log-content').text('Errore comunicazione'); }
                    });
                    }
                    },
        // toggleForceProcessing method removed - normal processing is now default behavior
        onFileSelect: function(e) {
            var file = e.target.files[0];
            if (file && file.name.endsWith('.xml')) {
                $('#process-test-file').prop('disabled', false);
                dashboard.showAlert('File XML selezionato: ' + file.name, 'info');
            } else {
                $('#process-test-file').prop('disabled', true);
                if (file) dashboard.showAlert('Seleziona un file XML valido', 'error');
            }
        },
        processTestFile: function(e) {
            e.preventDefault();
            var fileInput = $('#test-xml-file')[0];
            if (!fileInput.files[0]) {
                dashboard.showAlert('Seleziona un file XML prima di procedere', 'error');
                return;
            }
            
            $('#test-log-output').removeClass('rs-hidden');
            dashboard.updateTestLog('Avvio processo test import...');

            var formData = new FormData();
            formData.append('action', 'realestate_sync_process_test_file');
            formData.append('nonce', realestateSync.nonce);
            formData.append('test_xml_file', fileInput.files[0]);
            formData.append('mark_as_test', $('#mark-as-test-import').is(':checked') ? '1' : '0');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $('#process-test-file').prop('disabled', true).html('<span class="rs-spinner"></span>Processando...');
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;
                        dashboard.updateTestLog(result.log_output || 'Import completato');
                        var message = 'Test completato! Props: ' + (result.properties_created || 0) + ' create, ' + 
                                     (result.properties_updated || 0) + ' aggiornate. Agenzie: ' + 
                                     (result.agencies_created || 0) + ' create, ' + (result.agencies_updated || 0) + ' aggiornate';
                        if (result.media_new || result.media_existing) {
                            message += '. Media: ' + (result.media_new || 0) + ' nuove, ' + (result.media_existing || 0) + ' esistenti';
                        }
                        dashboard.showAlert(message, 'success');
                    } else {
                        dashboard.updateTestLog('ERRORE: ' + response.data);
                        dashboard.showAlert('Errore nel processo: ' + response.data, 'error');
                    }
                },
                error: function() {
                    dashboard.updateTestLog('ERRORE: Comunicazione con il server fallita');
                    dashboard.showAlert('Errore di comunicazione', 'error');
                },
                complete: function() {
                    $('#process-test-file').prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> Processa File XML');
                }
            });
        },
        cleanupTestData: function(e) {
            e.preventDefault();
            if (!confirm('Sei sicuro di voler cancellare SOLO i dati di test?\n\nQuesto cancellerà solo properties e agenzie create durante i test.')) return;
            
            dashboard.showAlert('Cancellazione test data in corso...', 'warning');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { action: 'realestate_sync_cleanup_test_data', nonce: realestateSync.nonce },
                beforeSend: function() {
                    $('#cleanup-test-data').prop('disabled', true).html('<span class="rs-spinner"></span>Cancellando...');
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;
                        var message = 'Test data cancellati: ' + (result.properties_deleted || 0) + ' properties, ' + 
                                     (result.agencies_deleted || 0) + ' agenzie';
                        dashboard.showAlert(message, 'success');
                        if (!$('#property-stats-display').hasClass('rs-hidden')) $('#show-property-stats').click();
                    } else {
                        dashboard.showAlert('Errore cancellazione: ' + response.data, 'error');
                    }
                },
                error: function() { dashboard.showAlert('Errore comunicazione', 'error'); },
                complete: function() {
                    $('#cleanup-test-data').prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Cleanup Test Data');
                }
            });
        },
        updateTestLog: function(message) {
            var timestamp = new Date().toLocaleTimeString();
            var logLine = '[' + timestamp + '] ' + message + '\n';
            $('#test-log-content').append(logLine);
            $('#test-log-output').scrollTop($('#test-log-content')[0].scrollHeight);
        },

        updateManualImportLog: function(message) {
            var timestamp = new Date().toLocaleTimeString();
            var logLine = '[' + timestamp + '] ' + message + '\n';
            $('#manual-import-log-content').append(logLine);
            $('#manual-import-log-output').scrollTop($('#manual-import-log-content')[0].scrollHeight);
        },

        // 🚀 PROFESSIONAL ACTIVATION TOOLS METHODS
        checkActivationStatus: function(e) {
            e.preventDefault();
            
            $('#activation-status-display').removeClass('rs-hidden');
            $('#activation-status-content').html('<p><span class="rs-spinner"></span>Checking activation status...</p>');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { 
                    action: 'realestate_sync_check_activation_status', 
                    nonce: realestateSync.nonce 
                },
                beforeSend: function() {
                    $('#check-activation-status').prop('disabled', true).html('<span class="rs-spinner"></span>Checking...');
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;
                        
                        // Update status display
                        $('#activation-status-content').html(result.status_html);
                        
                        // Show message
                        dashboard.showAlert(result.message, result.message_class.replace('rs-alert-', ''));
                        
                        // Log the check
                        console.log('🚀 Activation Status:', result);
                        
                    } else {
                        $('#activation-status-content').html('<p style="color: #d63638;">Error: ' + response.data + '</p>');
                        dashboard.showAlert('🚨 Status check failed: ' + response.data, 'error');
                    }
                },
                error: function() { 
                    $('#activation-status-content').html('<p style="color: #d63638;">Communication error</p>');
                    dashboard.showAlert('🚨 Communication error during status check', 'error'); 
                },
                complete: function() {
                    $('#check-activation-status').prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> Check Activation Status');
                }
            });
        },
        
        viewActivationInfo: function(e) {
            e.preventDefault();
            $('#activation-info-display').toggleClass('rs-hidden');
            
            if (!$('#activation-info-display').hasClass('rs-hidden')) {
                dashboard.showAlert('📚 Professional Activation System info displayed', 'info');
            }
        },
        
        testActivationWorkflow: function(e) {
            e.preventDefault();
            
            if (!confirm('🧪 Test Activation Workflow?\n\nThis will simulate the professional activation process and show how the wp_loaded system works.')) return;
            
            dashboard.showAlert('🧪 Testing activation workflow...', 'info');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { 
                    action: 'realestate_sync_test_activation_workflow', 
                    nonce: realestateSync.nonce 
                },
                beforeSend: function() {
                    $('#test-activation-workflow').prop('disabled', true).html('<span class="rs-spinner"></span>Testing...');
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;
                        
                        // Show test results in activation status area
                        $('#activation-status-display').removeClass('rs-hidden');
                        $('#activation-status-content').html('<h5>Workflow Test Results</h5>' + result.test_html);
                        
                        dashboard.showAlert(result.message, 'success');
                        
                        // Log test results
                        console.log('🧪 Workflow Test Results:', result.test_results);
                        
                    } else {
                        dashboard.showAlert('🚨 Workflow test failed: ' + response.data, 'error');
                    }
                },
                error: function() { 
                    dashboard.showAlert('🚨 Communication error during workflow test', 'error'); 
                },
                complete: function() {
                    $('#test-activation-workflow').prop('disabled', false).html('<span class="dashicons dashicons-performance"></span> Test Workflow');
                }
            });
        },
        showAlert: function(message, type) {
            var alertHtml = '<div class="rs-alert rs-alert-' + (type || 'info') + '">' + message + '</div>';
            $('#rs-alerts-container').html(alertHtml);
            if (type === 'success' || type === 'info') {
                setTimeout(function() { $('#rs-alerts-container .rs-alert').fadeOut(); }, 5000);
            }
        }
    };
    dashboard.init();
});
</script>
