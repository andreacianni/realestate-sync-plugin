<?php
/**
 * UI helper: allowed admins for gated widgets (UI-only)
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('rs_allowed_admin_ids')) {
    function rs_allowed_admin_ids() {
        $default_ids = array(59);
        $ids = apply_filters('realestate_sync_allowed_admin_ids', $default_ids);
        if (!is_array($ids)) {
            $ids = $default_ids;
        }
        $ids = array_map('intval', $ids);
        return array_values(array_filter($ids, function($id) {
            return $id > 0;
        }));
    }
}

if (!function_exists('rs_current_user_is_allowed_admin')) {
    function rs_current_user_is_allowed_admin() {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }
        return in_array((int) $user_id, rs_allowed_admin_ids(), true);
    }
}
