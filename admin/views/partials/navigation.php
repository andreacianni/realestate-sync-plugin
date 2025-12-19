<?php
/**
 * Dashboard Navigation Tabs
 * 4 tabs: Dashboard, Import, Setting, Strumenti
 */

if (!defined('ABSPATH')) exit;
?>

<!--
═══════════════════════════════════════════════════════════════════════════
NAVIGAZIONE DASHBOARD - 4 TAB
───────────────────────────────────────────────────────────────────────────
TAB 1 - DASHBOARD: Informational (storico, monitor, log)
TAB 2 - IMPORT: Operazioni manuali (import immediato, XML, prossimo auto)
TAB 3 - SETTING: Configurazioni (automatico, credenziali, email)
TAB 4 - STRUMENTI: Tools tecnici (developer mode, queue, cleanup)
═══════════════════════════════════════════════════════════════════════════
-->
<div class="nav-tab-wrapper">
    <a href="#dashboard" class="nav-tab nav-tab-active" data-tab="dashboard">
        <span class="dashicons dashicons-dashboard"></span> <?php _e('Dashboard', 'realestate-sync'); ?>
    </a>
    <a href="#import" class="nav-tab" data-tab="import">
        <span class="dashicons dashicons-download"></span> <?php _e('Import', 'realestate-sync'); ?>
    </a>
    <a href="#setting" class="nav-tab" data-tab="setting">
        <span class="dashicons dashicons-admin-settings"></span> <?php _e('Setting', 'realestate-sync'); ?>
    </a>
    <a href="#tools" class="nav-tab" data-tab="tools">
        <span class="dashicons dashicons-admin-tools"></span> <?php _e('Strumenti', 'realestate-sync'); ?>
    </a>
</div>
