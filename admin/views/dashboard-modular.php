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

    <!--
    ═══════════════════════════════════════════════════════════════════════════
    TAB 1: DASHBOARD - Informational Only
    ═══════════════════════════════════════════════════════════════════════════
    -->
    <div id="dashboard" class="tab-content rs-tab-active">
        <div class="rs-dashboard-grid">
            <?php include __DIR__ . '/widgets/stato-attuale.php'; ?>
            <?php include __DIR__ . '/widgets/monitor-import.php'; ?>
            <?php include __DIR__ . '/widgets/storico-import.php'; ?>
            <?php include __DIR__ . '/widgets/log-monitoraggio.php'; ?>
        </div>
    </div>

    <!--
    ═══════════════════════════════════════════════════════════════════════════
    TAB 2: IMPORT - Operational (Manual Operations)
    ═══════════════════════════════════════════════════════════════════════════
    -->
    <div id="import" class="tab-content">
        <div class="rs-dashboard-grid">
            <?php include __DIR__ . '/widgets/import-immediato.php'; ?>
            <?php include __DIR__ . '/widgets/import-xml.php'; ?>
            <?php include __DIR__ . '/widgets/import-prossimo.php'; ?>
        </div>
    </div>

    <!--
    ═══════════════════════════════════════════════════════════════════════════
    TAB 3: SETTING - Configurations
    ═══════════════════════════════════════════════════════════════════════════
    -->
    <div id="setting" class="tab-content">
        <div class="rs-dashboard-grid">
            <?php include __DIR__ . '/widgets/config-automatico.php'; ?>
            <?php include __DIR__ . '/widgets/config-credenziali.php'; ?>
            <?php include __DIR__ . '/widgets/config-email.php'; ?>
        </div>
    </div>

    <!--
    ═══════════════════════════════════════════════════════════════════════════
    TAB 4: STRUMENTI - Tools & Cleanup
    ═══════════════════════════════════════════════════════════════════════════
    -->
    <div id="tools" class="tab-content">
        <?php include __DIR__ . '/widgets/developer-mode.php'; ?>

        <?php
        // Get developer mode preference
        $developer_mode = get_user_meta(get_current_user_id(), 'realestate_sync_developer_mode', true);
        $developer_mode = filter_var($developer_mode, FILTER_VALIDATE_BOOLEAN);
        ?>

        <div class="rs-card">
            <h3><span class="dashicons dashicons-admin-tools"></span> <?php _e('Strumenti Amministrazione', 'realestate-sync'); ?></h3>

            <!-- Queue Management (Developer Mode Only) -->
            <div class="rs-developer-only <?php echo !$developer_mode ? 'rs-hidden' : ''; ?>" data-developer-section="queue-management">
                <?php include __DIR__ . '/widgets/queue-management.php'; ?>
            </div>
        </div>

        <div class="rs-card">
            <h3><span class="dashicons dashicons-database-import"></span> <?php _e('Testing & Development', 'realestate-sync'); ?></h3>

            <?php include __DIR__ . '/widgets/database-tools.php'; ?>
            <?php include __DIR__ . '/widgets/cleanup-properties.php'; ?>
        </div>
    </div>

</div><!-- .wrap -->

<?php include __DIR__ . '/partials/footer-scripts.php'; ?>
