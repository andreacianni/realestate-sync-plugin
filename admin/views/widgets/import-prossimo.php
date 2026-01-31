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
<div class="card shadow-sm rounded-3 border-1 p-0">
    <div class="card-header bg-info bg-opacity-10 border-0 py-3">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <span class="dashicons dashicons-clock me-2"></span>
            <?php _e('Import automatico', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <div class="d-flex align-items-center">
            <span class="dashicons dashicons-controls-play me-2"></span>
            <strong>
                <?php _e('Import automatico:', 'realestate-sync'); ?>
                <?php echo $schedule_enabled ? esc_html__('Abilitato', 'realestate-sync') : esc_html__('Disabilitato', 'realestate-sync'); ?>
            </strong>
        </div>
        <a href="<?php echo esc_url(admin_url('admin.php?page=realestate-sync#setting')); ?>" class="btn <?php echo $schedule_enabled ? 'btn-outline-primary' : 'btn-primary'; ?> w-100 nav-tab-trigger mt-3" data-tab="setting">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php echo $schedule_enabled ? esc_html__('Modifica programmazione', 'realestate-sync') : esc_html__('Configura Import Automatico', 'realestate-sync'); ?>
        </a>
    </div>
</div>
