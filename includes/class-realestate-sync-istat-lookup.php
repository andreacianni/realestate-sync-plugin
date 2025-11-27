<?php
/**
 * ISTAT Lookup Service
 *
 * Provides municipality data lookup from ISTAT codes
 * Optimized for Trentino-Alto Adige comuni
 *
 * @package RealEstateSync
 * @since 1.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_ISTAT_Lookup {

    /**
     * Lookup table cache
     */
    private static $lookup_table = null;

    /**
     * Get municipality data from ISTAT code
     *
     * @param string $istat_code ISTAT code (6 digits)
     * @return array|null Municipality data or null if not found
     *
     * Returns:
     * [
     *     'nome' => 'Municipality name',
     *     'provincia' => 'Province name',
     *     'regione' => 'Region name',
     *     'cap' => 'Postal code',
     *     'sigla_provincia' => 'Province code'
     * ]
     */
    public static function get_comune_data($istat_code) {
        if (empty($istat_code)) {
            return null;
        }

        // Load lookup table if not cached
        if (self::$lookup_table === null) {
            self::load_lookup_table();
        }

        // Return data if exists
        return self::$lookup_table[$istat_code] ?? null;
    }

    /**
     * Get municipality name from ISTAT code
     *
     * @param string $istat_code ISTAT code (6 digits)
     * @return string Municipality name or empty string
     */
    public static function get_comune_name($istat_code) {
        $data = self::get_comune_data($istat_code);
        return $data['nome'] ?? '';
    }

    /**
     * Get province name from ISTAT code
     *
     * @param string $istat_code ISTAT code (6 digits)
     * @return string Province name or empty string
     */
    public static function get_provincia_name($istat_code) {
        $data = self::get_comune_data($istat_code);
        return $data['provincia'] ?? '';
    }

    /**
     * Get region name from ISTAT code
     *
     * @param string $istat_code ISTAT code (6 digits)
     * @return string Region name or empty string
     */
    public static function get_regione_name($istat_code) {
        $data = self::get_comune_data($istat_code);
        return $data['regione'] ?? '';
    }

    /**
     * Get postal code (CAP) from ISTAT code
     *
     * @param string $istat_code ISTAT code (6 digits)
     * @return string Postal code or empty string
     */
    public static function get_cap($istat_code) {
        $data = self::get_comune_data($istat_code);
        return $data['cap'] ?? '';
    }

    /**
     * Get province code from ISTAT code
     *
     * @param string $istat_code ISTAT code (6 digits)
     * @return string Province code (e.g., '022' for Trento) or empty string
     */
    public static function get_provincia_code($istat_code) {
        $data = self::get_comune_data($istat_code);
        return $data['sigla_provincia'] ?? '';
    }

    /**
     * Check if ISTAT code is supported
     *
     * @param string $istat_code ISTAT code (6 digits)
     * @return bool True if code exists in lookup table
     */
    public static function is_supported($istat_code) {
        if (self::$lookup_table === null) {
            self::load_lookup_table();
        }

        return isset(self::$lookup_table[$istat_code]);
    }

    /**
     * Get all supported ISTAT codes
     *
     * @return array Array of supported ISTAT codes
     */
    public static function get_supported_codes() {
        if (self::$lookup_table === null) {
            self::load_lookup_table();
        }

        return array_keys(self::$lookup_table);
    }

    /**
     * Get statistics about lookup table
     *
     * @return array Statistics
     */
    public static function get_stats() {
        if (self::$lookup_table === null) {
            self::load_lookup_table();
        }

        $bolzano_count = 0;
        $trento_count = 0;

        foreach (self::$lookup_table as $code => $data) {
            if (substr($code, 0, 3) === '021') {
                $bolzano_count++;
            } elseif (substr($code, 0, 3) === '022') {
                $trento_count++;
            }
        }

        return [
            'total_comuni' => count(self::$lookup_table),
            'bolzano_comuni' => $bolzano_count,
            'trento_comuni' => $trento_count,
            'cache_loaded' => self::$lookup_table !== null
        ];
    }

    /**
     * Load lookup table from file
     *
     * @return void
     */
    private static function load_lookup_table() {
        // Support both WordPress and standalone usage
        if (function_exists('plugin_dir_path')) {
            $lookup_file = plugin_dir_path(__FILE__) . '../data/istat-lookup-tn-bz.php';
        } else {
            $lookup_file = dirname(__FILE__) . '/../data/istat-lookup-tn-bz.php';
        }

        if (!file_exists($lookup_file)) {
            error_log('ISTAT Lookup: Lookup table file not found: ' . $lookup_file);
            self::$lookup_table = [];
            return;
        }

        self::$lookup_table = require $lookup_file;

        if (!is_array(self::$lookup_table)) {
            error_log('ISTAT Lookup: Invalid lookup table format');
            self::$lookup_table = [];
        }
    }

    /**
     * Clear lookup table cache
     * Useful for testing or forcing reload
     *
     * @return void
     */
    public static function clear_cache() {
        self::$lookup_table = null;
    }
}
