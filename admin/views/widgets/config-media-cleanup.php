<?php
/**
 * Widget: Configurazione Media Cleanup
 * Tab: Setting
 */

if (!defined('ABSPATH')) exit;

$cleanup_enabled = (bool) get_option('realestate_cleanup_enabled', false);
$cleanup_window_start = (string) get_option('realestate_cleanup_window_start', '08:00');
$cleanup_window_end = (string) get_option('realestate_cleanup_window_end', '23:59');
$cleanup_limit = min(20, max(1, (int) get_option('realestate_cleanup_limit', 5)));
$cleanup_max_runtime = min(60, max(5, (int) get_option('realestate_cleanup_max_runtime', 30)));
$cleanup_pause_on_import = (bool) get_option('realestate_cleanup_pause_on_import', true);
?>

<div class="card shadow-sm rounded-3 border-1 p-0" id="media-cleanup-settings-card">
    <div class="card-header bg-primary bg-opacity-10 border-0 py-3">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <span class="dashicons dashicons-images-alt2 me-2"></span>
            <?php _e('Configurazione Media Cleanup', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <p class="text-muted mb-4">
            <?php _e('Configura in modo semplice il comportamento del cleanup automatico dei media.', 'realestate-sync'); ?>
        </p>

        <div class="form-check form-switch mb-4">
            <input type="checkbox" class="form-check-input" role="switch" id="media-cleanup-enabled" <?php checked($cleanup_enabled); ?>>
            <label class="form-check-label" for="media-cleanup-enabled">
                <strong><?php _e('Abilita cleanup automatico', 'realestate-sync'); ?></strong>
                <div class="form-text">
                    <?php _e('Abilita o disabilita completamente il processo di cleanup.', 'realestate-sync'); ?>
                </div>
            </label>
        </div>

        <div class="mb-3 row">
            <label for="media-cleanup-window-start" class="col-sm-4 col-form-label fw-semibold">
                <?php _e('Finestra operativa inizio', 'realestate-sync'); ?>:
            </label>
            <div class="col-sm-8">
                <input type="time" class="form-control" id="media-cleanup-window-start" value="<?php echo esc_attr($cleanup_window_start); ?>">
                <div class="form-text">
                    <?php _e('Il cleanup viene eseguito solo in questa fascia oraria.', 'realestate-sync'); ?>
                </div>
            </div>
        </div>

        <div class="mb-3 row">
            <label for="media-cleanup-window-end" class="col-sm-4 col-form-label fw-semibold">
                <?php _e('Finestra operativa fine', 'realestate-sync'); ?>:
            </label>
            <div class="col-sm-8">
                <input type="time" class="form-control" id="media-cleanup-window-end" value="<?php echo esc_attr($cleanup_window_end); ?>">
            </div>
        </div>

        <div class="mb-3 row">
            <label for="media-cleanup-limit" class="col-sm-4 col-form-label fw-semibold">
                <?php _e('Limite per ciclo', 'realestate-sync'); ?>:
            </label>
            <div class="col-sm-8">
                <input type="number" class="form-control" id="media-cleanup-limit" min="1" max="20" value="<?php echo esc_attr($cleanup_limit); ?>">
                <div class="form-text">
                    <?php _e('Numero massimo di media processati per ogni esecuzione del cron.', 'realestate-sync'); ?>
                </div>
            </div>
        </div>

        <div class="mb-3 row">
            <label for="media-cleanup-max-runtime" class="col-sm-4 col-form-label fw-semibold">
                <?php _e('Runtime massimo', 'realestate-sync'); ?>:
            </label>
            <div class="col-sm-8">
                <input type="number" class="form-control" id="media-cleanup-max-runtime" min="5" max="60" value="<?php echo esc_attr($cleanup_max_runtime); ?>">
                <div class="form-text">
                    <?php _e('Durata massima in secondi di ogni ciclo di cleanup.', 'realestate-sync'); ?>
                </div>
            </div>
        </div>

        <div class="form-check form-switch mb-4">
            <input type="checkbox" class="form-check-input" role="switch" id="media-cleanup-pause-on-import" <?php checked($cleanup_pause_on_import); ?>>
            <label class="form-check-label" for="media-cleanup-pause-on-import">
                <strong><?php _e('Pausa durante import', 'realestate-sync'); ?></strong>
                <div class="form-text">
                    <?php _e('Se attivo, il cleanup si ferma automaticamente durante l’import.', 'realestate-sync'); ?>
                </div>
            </label>
        </div>

        <div class="d-grid gap-2">
            <button type="button" class="btn btn-primary" id="save-media-cleanup-settings">
                <span class="dashicons dashicons-saved"></span>
                <?php _e('Salva Configurazione Media Cleanup', 'realestate-sync'); ?>
            </button>
        </div>

        <div id="media-cleanup-config-status" class="mt-3"></div>
    </div>
</div>
