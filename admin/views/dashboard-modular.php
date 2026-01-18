<?php
/**
 * RealEstate Sync Plugin - Modular Dashboard
 * Reorganized structure with widgets in separate files
 *
 * Tab Structure:
 * 1. DASHBOARD - Informational (storico, monitor, log)
 * 2. IMPORT - Operational (import immediato, XML, prossimo)
 * 3. SETTING - Configurations (auto, credenziali, email)
 * 4. STRUMENTI - Tools (developer mode, queue, cleanup)
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap realestate-sync-admin bootstrap-scope">
    <?php include __DIR__ . '/partials/header.php'; ?>

    <?php include __DIR__ . '/partials/navigation.php'; ?>

    <!-- Bootstrap Tab Content Container -->
    <div class="tab-content">
        <!--TAB 1: DASHBOARD - Informational Only -->
        <div id="dashboard" class="tab-pane fade show active" role="tabpanel" aria-labelledby="dashboard-tab">
            <div class="row g-3">
                <!-- <div class="col-md-6"><?php /* include __DIR__ . '/widgets/stato-attuale.php'; */ ?></div> -->
                <div class="col-md-6"><?php include __DIR__ . '/widgets/monitor-import.php'; ?></div>
                <!-- <div class="col-md-6"><?php /* include __DIR__ . '/widgets/storico-import.php'; */ ?></div> -->
                <!-- <div class="col-md-6"><?php /* include __DIR__ . '/widgets/log-monitoraggio.php'; */ ?></div> -->
            </div>
        </div>

        <!-- TAB 2: IMPORT - Operational (Manual Operations) -->
        <div id="import" class="tab-pane fade" role="tabpanel" aria-labelledby="import-tab">
            <div class="row g-3">
                <div class="col-md-6"><?php include __DIR__ . '/widgets/import-prossimo.php'; ?></div>
                <div class="col-md-6"><?php include __DIR__ . '/widgets/import-immediato.php'; ?></div>
                <div class="col-md-6"><?php include __DIR__ . '/widgets/import-xml.php'; ?></div>
                
            </div>
        </div>

        <!-- TAB 3: SETTING - Configurations -->
        <div id="setting" class="tab-pane fade" role="tabpanel" aria-labelledby="setting-tab">
            <div class="row g-3">
                <div class="col-md-6"><?php include __DIR__ . '/widgets/config-automatico.php'; ?></div>
                <div class="col-md-6"><?php include __DIR__ . '/widgets/config-email.php'; ?></div>
                <div class="col-md-6"><?php include __DIR__ . '/widgets/config-credenziali.php'; ?></div>
            </div>
        </div>

        <!-- TAB 4: STRUMENTI - Tools & Cleanup-->
        <div id="tools" class="tab-pane fade" role="tabpanel" aria-labelledby="tools-tab">
            <div class="row g-3">
                <div class="col-md-12"><?php include __DIR__ . '/widgets/developer-mode.php'; ?></div>

                <?php
                // Get developer mode preference
                $developer_mode = get_user_meta(get_current_user_id(), 'realestate_sync_developer_mode', true);
                $developer_mode = filter_var($developer_mode, FILTER_VALIDATE_BOOLEAN);
                ?>

                <!-- Queue Management (Developer Mode Only) -->
                <div class="col-md-12 <?php echo !$developer_mode ? 'd-none' : ''; ?>" data-developer-section="queue-management">
                    <?php include __DIR__ . '/widgets/queue-management.php'; ?>
                </div>

                <!-- Cleanup Duplicates (Developer Mode Only) -->
                <div class="col-md-12 <?php echo !$developer_mode ? 'd-none' : ''; ?>" data-developer-section="cleanup-duplicates">
                    <?php include __DIR__ . '/widgets/cleanup-duplicates.php'; ?>
                </div>

                <div class="col-md-6"><?php include __DIR__ . '/widgets/database-tools.php'; ?></div>
                <div class="col-md-6"><?php include __DIR__ . '/widgets/cleanup-properties.php'; ?></div>
            </div>
        </div>
    </div><!-- .tab-content -->

</div><!-- .wrap -->

<?php include __DIR__ . '/partials/footer-scripts.php'; ?>
