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
            <?php _e('Prossimo Import Automatico', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <?php if ($schedule_enabled && $next_run_timestamp) : ?>
            <!-- Scheduled -->
            <div class="text-muted mb-3">
                <?php _e('Programmato per il:', 'realestate-sync'); ?>
                <?php echo esc_html(date('d/m/Y H:i', $next_run_timestamp)); ?>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=realestate-sync#setting')); ?>" class="btn btn-outline-primary w-100 nav-tab-trigger" data-tab="setting">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php _e('Modifica programmazione', 'realestate-sync'); ?>
            </a>
        <?php else : ?>
            <!-- Not Scheduled -->
            <div class="alert alert-warning d-flex align-items-start" role="alert">
                <span class="dashicons dashicons-warning me-2 mt-1"></span>
                <div>
                    <strong><?php _e('Nessun Import Programmato', 'realestate-sync'); ?></strong>
                    <p class="mb-0"><?php _e('Gli import automatici sono disabilitati', 'realestate-sync'); ?></p>
                </div>
            </div>
            <a href="#setting" class="btn btn-primary w-100 nav-tab-trigger" data-tab="setting">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php _e('Configura Import Automatico', 'realestate-sync'); ?>
            </a>
        <?php endif; ?>
    </div>
</div>
