<?php
/**
 * Widget: Configurazione Import Automatico
 * Tab: Setting
 * User: Admin Non Tecnico + Tecnico
 */

if (!defined('ABSPATH')) exit;

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
    <div>
        <strong><span class="dashicons dashicons-info"></span> Orario Server:</strong><br>
        <div>
            <strong><?php echo esc_html($server_time); ?></strong>
            <span>(Timezone: <?php echo esc_html($server_timezone); ?>)</span>
        </div>
        <small>
            Tutti gli orari configurati fanno riferimento a questo fuso orario.
        </small>
    </div>

    <!-- Enable/Disable Toggle -->
    <div>
        <label>
            <input type="checkbox" id="schedule-enabled" <?php checked($schedule_enabled); ?>
>
            <strong>
                <span class="dashicons dashicons-yes-alt"></span>
                Abilita Import Automatico Programmato
            </strong>
        </label>
        <small>
            Quando abilitato, il sistema eseguirà automaticamente l'import secondo la configurazione impostata.
        </small>
    </div>

    <!-- Schedule Configuration (visible only when enabled) -->
    <div id="schedule-config">

        <!-- Time Selection -->
        <div>
            <label>
                <span class="dashicons dashicons-clock"></span>
                Orario Esecuzione (formato 24h):
            </label>
            <input type="time" id="schedule-time" value="<?php echo esc_attr($schedule_time); ?>"
>
            <small>
                L'import verrà eseguito all'orario specificato secondo il fuso orario del server.
            </small>
        </div>

        <!-- Frequency Selection -->
        <div>
            <label>
                <span class="dashicons dashicons-calendar-alt"></span>
                Frequenza:
            </label>
            <select id="schedule-frequency">
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
        <div id="weekly-config">
            <label>
                Giorno della settimana:
            </label>
            <select id="schedule-weekday">
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
        <div id="custom-days-config">
            <label>
                Numero di giorni:
            </label>
            <input type="number" id="schedule-custom-days" value="<?php echo esc_attr($schedule_custom_days); ?>"
                   min="1" max="365">
            <small>
                L'import verrà eseguito ogni N giorni (es. 7 = settimanale, 15 = ogni 2 settimane)
            </small>
        </div>

        <!-- Custom Months Configuration (visible only when frequency=custom_months) -->
        <div id="custom-months-config">
            <label>
                Numero di mesi:
            </label>
            <input type="number" id="schedule-custom-months" value="<?php echo esc_attr($schedule_custom_months); ?>"
                   min="1" max="12">
            <small>
                L'import verrà eseguito ogni N mesi
            </small>
        </div>

        <!-- Mark as Test Option -->
        <div>
            <label>
                <input type="checkbox" id="schedule-mark-test" <?php checked($schedule_mark_test); ?>
>
                <strong>
                    <span class="dashicons dashicons-flag"></span>
                    Marca import automatici come Test
                </strong>
            </label>
            <small>
                Le proprietà importate automaticamente verranno marcate con <code>_test_import=1</code>
            </small>
        </div>

        <!-- Preview Next Run -->
        <div>
            <strong><span class="dashicons dashicons-calendar"></span> Prossima Esecuzione:</strong><br>
            <div id="next-run-preview">
                <?php
                if ($schedule_enabled && $next_run) {
                    echo esc_html(date('Y-m-d H:i:s', $next_run));
                } else {
                    echo 'Non programmato';
                }
                ?>
            </div>
            <small>
                Aggiorna automaticamente dopo aver salvato la configurazione.
            </small>
        </div>

        <!-- Save Button -->
        <button type="button" class="rs-button-primary" id="save-schedule-config">
            <span class="dashicons dashicons-saved"></span> <?php _e('Salva Configurazione', 'realestate-sync'); ?>
        </button>
    </div>

    <!-- Status Message -->
    <div id="schedule-status"></div>
</div>
