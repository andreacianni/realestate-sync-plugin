<?php
/**
 * Widget: Prossimo Import Automatico
 * Tab: Import
 * User: Admin (informativo, read-only)
 */

if (!defined('ABSPATH')) exit;

// Get next scheduled import
$cron_manager = new RealEstate_Sync_Cron_Manager();
$next_run_timestamp = $cron_manager->get_next_scheduled_import();
$schedule_enabled = get_option('realestate_sync_schedule_enabled', false);
?>

<!--
╔═══════════════════════════════════════════════════════════════════╗
║ WIDGET: PROSSIMO IMPORT AUTOMATICO                               ║
╠═══════════════════════════════════════════════════════════════════╣
║ SCOPO: Mostra quando verrà eseguito il prossimo import auto      ║
║ FONTE: WP Cron (next scheduled event)                            ║
║ READ-ONLY: Solo visualizzazione, configurazione in Tab Setting   ║
╚═══════════════════════════════════════════════════════════════════╝
-->
<div class="rs-card">
    <h3>
        <span class="dashicons dashicons-clock"></span>
        <?php _e('Prossimo Import Automatico', 'realestate-sync'); ?>
    </h3>

    <div>
        <?php if ($schedule_enabled && $next_run_timestamp) : ?>
            <!-- Scheduled -->
            <div>
                <div>
                    <?php _e('Programmato tra', 'realestate-sync'); ?>
                </div>
                <div>
                    <?php
                    $time_until = human_time_diff(current_time('timestamp'), $next_run_timestamp);
                    echo esc_html($time_until);
                    ?>
                </div>
                <div>
                    <?php echo esc_html(date('d/m/Y H:i', $next_run_timestamp)); ?>
                </div>
            </div>
        <?php else : ?>
            <!-- Not Scheduled -->
            <div>
                <span class="dashicons dashicons-warning"></span>
                <div>
                    <?php _e('Nessun Import Programmato', 'realestate-sync'); ?>
                </div>
                <div>
                    <?php _e('Gli import automatici sono disabilitati', 'realestate-sync'); ?>
                </div>
                <a href="#setting" class="rs-button-primary nav-tab-trigger" data-tab="setting">
                    <span class="dashicons dashicons-admin-settings"></span>
                    <?php _e('Configura Import Automatico', 'realestate-sync'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
