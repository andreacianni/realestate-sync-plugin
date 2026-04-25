<?php
/**
 * Widget: Delete Guardrails for missing_from_feed
 * Tab: Setting
 */

if (!defined('ABSPATH')) exit;

$settings = get_option('realestate_sync_settings', array());
$settings = is_array($settings) ? $settings : array();

$delete_mode = isset($settings['missing_from_feed_delete_mode']) ? sanitize_key($settings['missing_from_feed_delete_mode']) : 'dry_run';
if (!in_array($delete_mode, array('dry_run', 'soft', 'live'), true)) {
    $delete_mode = 'dry_run';
}

$delete_cap = isset($settings['missing_from_feed_delete_cap']) ? max(1, intval($settings['missing_from_feed_delete_cap'])) : 10;
$kill_switch = array_key_exists('missing_from_feed_delete_kill_switch', $settings) ? (bool) $settings['missing_from_feed_delete_kill_switch'] : true;
$kill_switch_label = $kill_switch ? __('ATTIVO - delete bloccate', 'realestate-sync') : __('SPENTO - delete abilitate', 'realestate-sync');
$kill_switch_class = $kill_switch ? 'alert-warning' : 'alert-success';
?>

<div class="card shadow-sm rounded-3 border-1 p-0">
    <div class="card-header bg-warning bg-opacity-10 border-0 py-3">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <span class="dashicons dashicons-shield me-2"></span>
            <?php _e('Delete Guardrails Missing From Feed', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <div class="alert <?php echo esc_attr($kill_switch_class); ?> mb-4">
            <strong>Kill switch:</strong> <?php echo esc_html($kill_switch_label); ?>
        </div>

        <div class="mb-3 row">
            <label for="missing_from_feed_delete_mode" class="col-sm-4 col-form-label fw-semibold">
                Modalità delete:
            </label>
            <div class="col-sm-8">
                <select class="form-select" id="missing_from_feed_delete_mode">
                    <option value="dry_run" <?php selected($delete_mode, 'dry_run'); ?>>dry_run</option>
                    <option value="soft" <?php selected($delete_mode, 'soft'); ?>>soft</option>
                    <option value="live" <?php selected($delete_mode, 'live'); ?>>live</option>
                </select>
                <div class="form-text">`dry_run` sicuro. `soft` primo reale. `live` piena delete.</div>
            </div>
        </div>

        <div class="mb-3 row">
            <label for="missing_from_feed_delete_cap" class="col-sm-4 col-form-label fw-semibold">
                Cap delete:
            </label>
            <div class="col-sm-8">
                <input type="number" class="form-control" id="missing_from_feed_delete_cap" min="1" max="9999" value="<?php echo esc_attr($delete_cap); ?>">
                <div class="form-text">Limite massimo post processati per tick delete.</div>
            </div>
        </div>

        <div class="alert alert-danger mb-4">
            <div class="form-check d-flex align-items-center">
                <input type="checkbox" class="form-check-input" id="missing_from_feed_delete_kill_switch" <?php checked($kill_switch); ?>>
                <label class="form-check-label ms-2" for="missing_from_feed_delete_kill_switch">
                    <strong>Kill switch attivo</strong>
                </label>
            </div>
            <div class="form-text ms-4">
                Se attivo, la delete phase resta bloccata anche con mode `soft` o `live`.
            </div>
        </div>

        <div class="d-grid gap-2">
            <button type="button" class="btn btn-primary" id="rs-save-settings">
                <span class="dashicons dashicons-saved"></span> Salva Impostazioni
            </button>
        </div>

        <div id="rs-settings-status" class="mt-3"></div>
    </div>
</div>
