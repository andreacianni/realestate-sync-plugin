<?php
/**
 * Dashboard Footer Scripts
 * Bootstrap tab enhancements (URL hash handling)
 * Main scripts are loaded from admin/assets/admin.js
 */

if (!defined('ABSPATH')) exit;
?>

<script>
/**
 * Bootstrap Tab Navigation Enhancements
 * URL hash handling and programmatic tab switching
 */
jQuery(document).ready(function($) {
    // Update URL hash when tab is shown (Bootstrap event)
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        var tabId = $(e.target).attr('data-bs-target').substring(1);
        if (history.pushState) {
            history.pushState(null, null, '#' + tabId);
        } else {
            location.hash = '#' + tabId;
        }
    });

    // Load tab from URL hash on page load
    var hash = window.location.hash;
    if (hash) {
        var triggerEl = document.querySelector('button[data-bs-target="' + hash + '"]');
        if (triggerEl) {
            var tab = new bootstrap.Tab(triggerEl);
            tab.show();
        }
    }

    // Handle nav-tab-trigger links (e.g., "Configura automazione →")
    $(document).on('click', '.nav-tab-trigger', function(e) {
        e.preventDefault();
        var targetTab = $(this).data('tab');
        var triggerEl = document.querySelector('button[data-bs-target="#' + targetTab + '"]');
        if (triggerEl) {
            var tab = new bootstrap.Tab(triggerEl);
            tab.show();
        }
    });
});
</script>
