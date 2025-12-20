<?php
/**
 * Widget: Storico Import
 * Tab: Dashboard
 * User: Admin + Tecnico
 */

if (!defined('ABSPATH')) exit;

// Check if import_sessions table exists
global $wpdb;
$sessions_table = $wpdb->prefix . 'realestate_import_sessions';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$sessions_table'") === $sessions_table;

if ($table_exists) {
    // Get last 10 import sessions
    $recent_sessions = $wpdb->get_results("
        SELECT *
        FROM {$sessions_table}
        ORDER BY started_at DESC
        LIMIT 10
    ", ARRAY_A);
}
?>

<!--
╔═══════════════════════════════════════════════════════════════════╗
║ WIDGET: STORICO IMPORT                                            ║
╚═══════════════════════════════════════════════════════════════════╝
-->
<?php if ($table_exists && !empty($recent_sessions)) : ?>
<div class="card shadow-sm rounded-3 border-1 p-0">
    <div class="card-header bg-secondary bg-opacity-10 border-0 py-3">
        <h5 class="card-title mb-0 d-flex align-items-center">
            <span class="dashicons dashicons-calendar-alt me-2"></span>
            <?php _e('Storico Import', 'realestate-sync'); ?>
        </h5>
    </div>

    <div class="card-body">
        <p class="text-muted mb-3">
            <?php _e('Cronologia degli ultimi import eseguiti', 'realestate-sync'); ?>
        </p>

        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th><?php _e('Data/Ora', 'realestate-sync'); ?></th>
                        <th><?php _e('Tipo', 'realestate-sync'); ?></th>
                        <th><?php _e('Stato', 'realestate-sync'); ?></th>
                        <th><?php _e('Durata', 'realestate-sync'); ?></th>
                        <th><?php _e('Dettagli', 'realestate-sync'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_sessions as $session) :
                        $started = strtotime($session['started_at']);
                        $completed = $session['completed_at'] ? strtotime($session['completed_at']) : null;
                        $duration = $completed ? ($completed - $started) : null;

                        // Status badge
                        $status_class = 'secondary';
                        $status_text = ucfirst($session['status']);
                        if ($session['status'] === 'completed') {
                            $status_class = 'success';
                            $status_text = __('Completato', 'realestate-sync');
                        } elseif ($session['status'] === 'failed') {
                            $status_class = 'danger';
                            $status_text = __('Fallito', 'realestate-sync');
                        } elseif ($session['status'] === 'running') {
                            $status_class = 'warning';
                            $status_text = __('In corso', 'realestate-sync');
                        }

                        // Type badge
                        $type_text = $session['type'] === 'manual' ? __('Manuale', 'realestate-sync') : __('Automatico', 'realestate-sync');
                        $type_icon = $session['type'] === 'manual' ? 'admin-users' : 'clock';
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html(date('d/m/Y', $started)); ?></strong><br>
                            <small class="text-muted"><?php echo esc_html(date('H:i:s', $started)); ?></small>
                        </td>
                        <td>
                            <span class="dashicons dashicons-<?php echo $type_icon; ?>"></span>
                            <?php echo esc_html($type_text); ?>
                            <?php if ($session['marked_as_test']) : ?>
                                <br><span class="badge bg-warning">
                                    <span class="dashicons dashicons-flag"></span> Test
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $status_class; ?>">
                                <?php echo esc_html($status_text); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($duration !== null) : ?>
                                <span class="badge bg-info">
                                    <?php
                                    $minutes = floor($duration / 60);
                                    $seconds = $duration % 60;
                                    echo sprintf('%d:%02d', $minutes, $seconds);
                                    ?>
                                </span>
                            <?php else : ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($session['status'] === 'completed') : ?>
                                <span class="badge bg-success">✓ <?php echo esc_html($session['new_properties']); ?></span> nuove,
                                <span class="badge bg-info">↻ <?php echo esc_html($session['updated_properties']); ?></span> aggiornate
                                <?php if ($session['failed_properties'] > 0) : ?>
                                    , <span class="badge bg-danger">✗ <?php echo esc_html($session['failed_properties']); ?></span> fallite
                                <?php endif; ?>
                            <?php elseif ($session['status'] === 'failed') : ?>
                                <small class="text-danger">
                                    <?php echo esc_html(substr($session['error_log'], 0, 100)); ?>
                                    <?php if (strlen($session['error_log']) > 100) echo '...'; ?>
                                </small>
                            <?php elseif ($session['status'] === 'running') : ?>
                                <span class="badge bg-warning">
                                    <?php echo esc_html($session['processed_items']); ?>/<?php echo esc_html($session['total_items']); ?> processati
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
