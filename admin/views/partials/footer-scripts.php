<?php
/**
 * Dashboard Footer Scripts
 * Minimal inline JavaScript for tab initialization
 * Main scripts are loaded from admin/assets/admin.js
 */

if (!defined('ABSPATH')) exit;
?>

<script>
/**
 * Tab Navigation
 * Handle tab switching without page reload
 */
jQuery(document).ready(function($) {
    // Tab click handler
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();

        var tabId = $(this).data('tab');

        // Update nav tabs
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // Update tab content
        $('.tab-content').removeClass('rs-tab-active');
        $('#' + tabId).addClass('rs-tab-active');

        // Update URL hash without scrolling
        if (history.pushState) {
            history.pushState(null, null, '#' + tabId);
        } else {
            location.hash = '#' + tabId;
        }
    });

    // Load tab from URL hash on page load
    var hash = window.location.hash.substring(1);
    if (hash && $('#' + hash).length) {
        $('.nav-tab[data-tab="' + hash + '"]').trigger('click');
    }

    // Handle nav-tab-trigger links (e.g., "Configura automazione →")
    $(document).on('click', '.nav-tab-trigger', function(e) {
        e.preventDefault();
        var targetTab = $(this).data('tab');
        $('.nav-tab[data-tab="' + targetTab + '"]').trigger('click');
    });
});
</script>
