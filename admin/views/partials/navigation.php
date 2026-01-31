<?php
/**
 * Dashboard Navigation Tabs
 * 4 tabs: Dashboard, Import, Setting, Strumenti
 */

if (!defined('ABSPATH')) exit;
?>

<!--
═══════════════════════════════════════════════════════════════════════════
NAVIGAZIONE DASHBOARD - 4 TAB (Bootstrap nav-tabs)
───────────────────────────────────────────────────────────────────────────
TAB 1 - DASHBOARD: Informational (storico, monitor, log)
TAB 2 - IMPORT: Operazioni manuali (import immediato, XML, prossimo auto)
TAB 3 - SETTING: Configurazioni (automatico, credenziali, email)
TAB 4 - STRUMENTI: Tools tecnici (queue, cleanup)
═══════════════════════════════════════════════════════════════════════════
-->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button" role="tab" aria-controls="dashboard" aria-selected="true">
            <span class="dashicons dashicons-dashboard"></span> <?php _e('Dashboard', 'realestate-sync'); ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="import-tab" data-bs-toggle="tab" data-bs-target="#import" type="button" role="tab" aria-controls="import" aria-selected="false">
            <span class="dashicons dashicons-download"></span> <?php _e('Import', 'realestate-sync'); ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="setting-tab" data-bs-toggle="tab" data-bs-target="#setting" type="button" role="tab" aria-controls="setting" aria-selected="false">
            <span class="dashicons dashicons-admin-settings"></span> <?php _e('Setting', 'realestate-sync'); ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tools-tab" data-bs-toggle="tab" data-bs-target="#tools" type="button" role="tab" aria-controls="tools" aria-selected="false">
            <span class="dashicons dashicons-admin-tools"></span> <?php _e('Strumenti', 'realestate-sync'); ?>
        </button>
    </li>
</ul>
