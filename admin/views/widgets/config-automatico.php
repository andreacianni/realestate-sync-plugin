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
<div class="card shadow-sm rounded-3 border-1 p-0">
    <div class="card-header bg-primary bg-opacity-10 border-0 py-3">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <span class="dashicons dashicons-clock me-2"></span>
            <?php _e('Configurazione Import Automatico', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <!-- Enable/Disable Toggle -->
        <div class="form-check form-switch mb-4">
            <input type="checkbox" class="form-check-input" role="switch" id="schedule-enabled" <?php checked($schedule_enabled); ?>>
            <label class="form-check-label" for="schedule-enabled">
                <strong>
                    <span class="dashicons dashicons-yes-alt"></span>
                    Abilita Import Automatico Programmato
                </strong>
                <div class="form-text">
                    Quando abilitato, il sistema eseguirà automaticamente l'import secondo la configurazione impostata.
                </div>
            </label>
        </div>

        <!-- Server Time Info -->
        <div class="alert alert-info d-flex align-items-start mb-4" role="alert">
            <span class="dashicons dashicons-info me-2 mt-1"></span>
            <div>
                <div class="mt-1">
                    <strong>Orario Server: <?php echo esc_html($server_time); ?></strong>
                    <span class="text-muted small">(Timezone: <?php echo esc_html($server_timezone); ?>)</span>
                </div>
                <div class="form-text small">
                    Tutti gli orari configurati fanno riferimento a questo fuso orario.
                </div>
            </div>
        </div>

        <!-- Schedule Configuration (visible only when enabled) -->
        <div id="schedule-config">

            <!-- Time Selection -->
            <div class="mb-3 row">
                <label for="schedule-time" class="col-sm-4 col-form-label fw-semibold">
                    <span class="dashicons dashicons-clock"></span>
                    Orario Esecuzione <span class="small">(formato 24h)</span>:
                </label>
                <div class="col-sm-8">
                    <input type="time" class="form-control" id="schedule-time" value="<?php echo esc_attr($schedule_time); ?>">
                </div>
            </div>

            <!-- Frequency Selection -->
            <div class="mb-3 row">
                <label for="schedule-frequency" class="col-sm-4 col-form-label fw-semibold">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    Frequenza:
                </label>
                <div class="col-sm-8">
                    <select class="form-select" id="schedule-frequency">
                        <option value="daily" <?php selected($schedule_frequency, 'daily'); ?>>Ogni giorno</option>
                        <option value="weekly" <?php selected($schedule_frequency, 'weekly'); ?>>Un giorno specifico della settimana</option>
                        <option value="custom_days" <?php selected($schedule_frequency, 'custom_days'); ?>>Ogni X giorni</option>
                        <option value="custom_months" <?php selected($schedule_frequency, 'custom_months'); ?>>Ogni X mesi</option>
                    </select>
                </div>
            </div>

            <!-- Weekly Configuration (visible only when frequency=weekly) -->
            <div id="weekly-config" class="mb-3 row small">
                <label for="schedule-weekday" class="col-sm-4 col-form-label fw-semibold">Giorno della settimana:</label>
                <div class="col-sm-8">
                    <select class="form-select" id="schedule-weekday">
                        <option value="0" <?php selected($schedule_weekday, 0); ?>>Domenica</option>
                        <option value="1" <?php selected($schedule_weekday, 1); ?>>Lunedì</option>
                        <option value="2" <?php selected($schedule_weekday, 2); ?>>Martedì</option>
                        <option value="3" <?php selected($schedule_weekday, 3); ?>>Mercoledì</option>
                        <option value="4" <?php selected($schedule_weekday, 4); ?>>Giovedì</option>
                        <option value="5" <?php selected($schedule_weekday, 5); ?>>Venerdì</option>
                        <option value="6" <?php selected($schedule_weekday, 6); ?>>Sabato</option>
                    </select>
                </div>
            </div>

            <!-- Custom Days Configuration (visible only when frequency=custom_days) -->
            <div id="custom-days-config" class="mb-3 row small">
                <label for="schedule-custom-days" class="col-sm-4 col-form-label fw-semibold">Numero di giorni:</label>
                <div class="col-sm-8">
                    <input type="number" class="form-control" id="schedule-custom-days" value="<?php echo esc_attr($schedule_custom_days); ?>" min="1" max="365">
                </div>
            </div>

            <!-- Custom Months Configuration (visible only when frequency=custom_months) -->
            <div id="custom-months-config" class="mb-3 row small">
                <label for="schedule-custom-months" class="col-sm-4 col-form-label fw-semibold">Numero di mesi:</label>
                <div class="col-sm-8">
                    <input type="number" class="form-control" id="schedule-custom-months" value="<?php echo esc_attr($schedule_custom_months); ?>" min="1" max="12">
                </div>
            </div>

            <!-- Preview Next Run -->
            <div class="alert alert-secondary mb-4">
                <strong><span class="dashicons dashicons-calendar"></span> Prossima Esecuzione:</strong>
                <div id="next-run-preview" class="mt-2 fs-5 fw-bold text-success">
                    <?php
                    if ($schedule_enabled && $next_run) {
                        echo esc_html(date('Y-m-d H:i:s', $next_run));
                    } else {
                        echo 'Non programmato';
                    }
                    ?>
                </div>
                <div class="form-text small">
                    Aggiorna automaticamente dopo aver salvato la configurazione.
                </div>
            </div>

                        <!-- Mark as Test Option -->
            <div class="alert alert-warning mb-4">
                <div class="form-check d-flex align-items-center">
                    <input type="checkbox" class="form-check-input" id="schedule-mark-test" <?php checked($schedule_mark_test); ?>>
                    <label class="form-check-label ms-2" for="schedule-mark-test">
                        <strong>
                            <span class="dashicons dashicons-flag"></span>
                            Marca import automatici come Test
                        </strong>
                    </label>
                </div>
                <div class="form-text ms-4">
                    Le proprieta importate automaticamente verranno marcate con <code>_test_import=1</code>
                </div>
            </div>

            <!-- Save Button -->
            <button type="button" class="btn btn-primary w-100" id="save-schedule-config">
                <span class="dashicons dashicons-saved"></span>
                <?php _e('Salva Configurazione', 'realestate-sync'); ?>
            </button>
        </div>

        <!-- Status Message -->
        <div id="schedule-status" class="mt-3"></div>
    </div>
</div>