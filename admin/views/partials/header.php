<?php
/**
 * Dashboard Header
 * H1 + Alerts Container
 */

if (!defined('ABSPATH')) exit;
?>

<h1>
    <span class="dashicons dashicons-building" style="font-size: 28px; margin-right: 10px; color: #2271b1;"></span>
    <?php printf(__('Pannello di controllo di RealEstate Sync (versione %s)', 'realestate-sync'), REALESTATE_SYNC_VERSION); ?>
</h1>

<div id="rs-alerts-container"></div>
