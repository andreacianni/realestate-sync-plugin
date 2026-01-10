<?php
/**
 * Widget: Stato Attuale
 * Tab: Dashboard
 * User: Admin Non Tecnico
 */

// Widget attualmente inutilizzato.
// Tenuto per riferimento o sviluppo futuro.

if (!defined('ABSPATH')) exit;

// Get next scheduled import
$cron_manager = new RealEstate_Sync_Cron_Manager();
$next_run_timestamp = $cron_manager->get_next_scheduled_import();
$schedule_enabled = get_option('realestate_sync_schedule_enabled', false);

// Get total synced properties
$tracking_manager = new RealEstate_Sync_Tracking_Manager();
$import_stats = $tracking_manager->get_import_statistics();
$total_synced = isset($import_stats['total_properties']) ? $import_stats['total_properties'] : 0;

// Get last modified tracking record as proxy for last import
global $wpdb;
$tracking_table = $wpdb->prefix . 'realestate_sync_tracking';
$last_modified = $wpdb->get_var("SELECT last_modified FROM {$tracking_table} ORDER BY last_modified DESC LIMIT 1");
?>

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
<div class="card shadow-sm rounded-3 border-1 p-0">
    <div class="card-header bg-primary bg-opacity-10 border-0 py-3">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <span class="dashicons dashicons-info me-2"></span>
            <?php _e('Stato Attuale', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <div class="row g-3">
            <!-- Total Properties -->
            <div class="col-md-4">
                <div class="p-3 bg-light rounded-2">
                    <div class="text-muted small mb-1">
                        <?php _e('Proprietà Sincronizzate', 'realestate-sync'); ?>
                    </div>
                    <div class="fs-3 fw-bold text-primary">
                        <?php echo number_format($total_synced); ?>
                    </div>
                </div>
            </div>

            <!-- Last Activity -->
            <div class="col-md-4">
                <div class="p-3 bg-light rounded-2">
                    <div class="text-muted small mb-1">
                        <?php _e('Ultima Attività', 'realestate-sync'); ?>
                    </div>
                    <div class="fw-semibold text-dark">
                        <?php
                        if ($last_modified) {
                            $time_diff = human_time_diff(strtotime($last_modified), current_time('timestamp'));
                            echo esc_html($time_diff) . ' ' . __('fa', 'realestate-sync');
                        } else {
                            echo '<span class="text-muted">' . __('Nessun dato', 'realestate-sync') . '</span>';
                        }
                        ?>
                    </div>
                    <?php if ($last_modified) : ?>
                        <div class="small text-muted mt-1">
                            <?php echo esc_html(date('d/m/Y H:i', strtotime($last_modified))); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Next Scheduled Import -->
            <div class="col-md-4">
                <div class="p-3 bg-light rounded-2">
                    <div class="text-muted small mb-1">
                        <?php _e('Prossimo Import Automatico', 'realestate-sync'); ?>
                    </div>
                    <?php if ($schedule_enabled && $next_run_timestamp) : ?>
                        <div class="fw-semibold text-success">
                            <?php
                            $time_until = human_time_diff(current_time('timestamp'), $next_run_timestamp);
                            echo __('Tra', 'realestate-sync') . ' ' . esc_html($time_until);
                            ?>
                        </div>
                        <div class="small text-muted mt-1">
                            <?php echo esc_html(date('d/m/Y H:i', $next_run_timestamp)); ?>
                        </div>
                    <?php else : ?>
                        <div class="fw-semibold text-warning">
                            <?php _e('Non programmato', 'realestate-sync'); ?>
                        </div>
                        <div class="mt-2">
                            <a href="#setting" class="btn btn-sm btn-outline-primary nav-tab-trigger" data-tab="setting">
                                <?php _e('Configura →', 'realestate-sync'); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
