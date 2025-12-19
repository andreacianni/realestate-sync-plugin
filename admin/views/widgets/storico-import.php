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
<div class="rs-card">
    <h3><span class="dashicons dashicons-calendar-alt"></span> <?php _e('Storico Import', 'realestate-sync'); ?></h3>

    <p>
        <?php _e('Cronologia degli ultimi import eseguiti', 'realestate-sync'); ?>
    </p>

    <table class="widefat">
        <thead>
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
                $status_color = 'gray';
                $status_text = ucfirst($session['status']);
                if ($session['status'] === 'completed') {
                    $status_color = '#00a32a';
                    $status_text = __('Completato', 'realestate-sync');
                } elseif ($session['status'] === 'failed') {
                    $status_color = '#d63638';
                    $status_text = __('Fallito', 'realestate-sync');
                } elseif ($session['status'] === 'running') {
                    $status_color = '#f0ad4e';
                    $status_text = __('In corso', 'realestate-sync');
                }

                // Type badge
                $type_text = $session['type'] === 'manual' ? __('Manuale', 'realestate-sync') : __('Automatico', 'realestate-sync');
                $type_icon = $session['type'] === 'manual' ? 'admin-users' : 'clock';
            ?>
            <tr>
                <td>
                    <strong><?php echo esc_html(date('d/m/Y', $started)); ?></strong><br>
                    <small><?php echo esc_html(date('H:i:s', $started)); ?></small>
                </td>
                <td>
                    <span class="dashicons dashicons-<?php echo $type_icon; ?>"></span>
                    <?php echo esc_html($type_text); ?>
                    <?php if ($session['marked_as_test']) : ?>
                        <br><span>
                            <span class="dashicons dashicons-flag"></span> Test
                        </span>
                    <?php endif; ?>
                </td>
                <td>
                    <span>
                        <?php echo esc_html($status_text); ?>
                    </span>
                </td>
                <td>
                    <?php if ($duration !== null) : ?>
                        <?php
                        $minutes = floor($duration / 60);
                        $seconds = $duration % 60;
                        echo sprintf('%d:%02d', $minutes, $seconds);
                        ?>
                    <?php else : ?>
                        <span>-</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($session['status'] === 'completed') : ?>
                        <span>✓ <?php echo esc_html($session['new_properties']); ?></span> nuove,
                        <span>↻ <?php echo esc_html($session['updated_properties']); ?></span> aggiornate
                        <?php if ($session['failed_properties'] > 0) : ?>
                            , <span>✗ <?php echo esc_html($session['failed_properties']); ?></span> fallite
                        <?php endif; ?>
                    <?php elseif ($session['status'] === 'failed') : ?>
                        <span>
                            <?php echo esc_html(substr($session['error_log'], 0, 100)); ?>
                            <?php if (strlen($session['error_log']) > 100) echo '...'; ?>
                        </span>
                    <?php elseif ($session['status'] === 'running') : ?>
                        <span>
                            <?php echo esc_html($session['processed_items']); ?>/<?php echo esc_html($session['total_items']); ?> processati
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
