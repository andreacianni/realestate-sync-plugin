<?php
/**
 * RealEstate Sync AddOn Integration Main Class
 * 
 * Integrates WP Residence Add-On functionality into RealEstate Sync Plugin
 * Originally based on WP All Import - WP Residence Add-On v1.3.1
 * 
 * @package RealEstate_Sync
 * @subpackage AddOn_Integration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if ( ! class_exists( 'RealEstate_Sync_AddOn_Main' ) ) {

    final class RealEstate_Sync_AddOn_Main {

        /**
         * Instance of this class
         * @var RealEstate_Sync_AddOn_Main
         */
        protected static $instance;

        /**
         * RapidAddon instance
         * @var RapidAddon
         */
        protected $add_on;
        
        /**
         * Current post type being processed
         * @var string
         */
        public $post_type = '';

        /**
         * Get singleton instance
         * @return RealEstate_Sync_AddOn_Main
         */
        static public function get_instance() {
            if ( self::$instance == NULL ) {
                self::$instance = new self();
            }
            
            return self::$instance;
        }

        /**
         * Constructor
         */
        protected function __construct() {
            $this->constants();
            $this->includes();
            $this->init();
        }

        /**
         * Initialize the Add-On integration
         */
        public function init() {
            // Helper functions to get post type and other things.
            $helper = new RealEstate_Sync_AddOn_Helper();
            
            // For our plugin, we always work with estate_property
            $this->post_type = 'estate_property';
            
            // Initialize the property fields (we don't need agent fields for now)
            $this->property_fields();
        }

        /**
         * Get the add-on instance
         * @return RapidAddon|null
         */
        public function get_add_on() {
            return $this->add_on;
        }
        
        /**
         * Get current post type
         * @return string
         */
        public function get_post_type() {
            return $this->post_type;
        }

        /**
         * Setup property fields (estate_property post type)
         * This initializes all the field factories for property import
         */
        public function property_fields() {
            // We don't need RapidAddon for our direct usage
            // We'll use the classes directly in our import process
            
            // Fields will be used via:
            // - RealEstate_Sync_AddOn_Importer_Properties
            // - RealEstate_Sync_AddOn_Importer_Location  
            // - RealEstate_Sync_AddOn_Helper
        }

        /**
         * Main import function - adapted for our XML processing
         * 
         * @param int $post_id WordPress post ID
         * @param array $data Converted XML data in Add-On format
         * @param array $import_options Import configuration options
         * @param array $article XML article data
         */
        public function import( $post_id, $data, $import_options = array(), $article = array() ) {
            
            $importer = new RealEstate_Sync_AddOn_Importer( $this->add_on, $this->post_type );
            $importer->import( $post_id, $data, $import_options, $article );

        }

        /**
         * Include all necessary files
         */
        public function includes() {
            $addon_dir = dirname( __FILE__ ) . '/';
            
            // Core Add-On files  
            require_once $addon_dir . 'class-addon-rapid-addon.php';
            require_once $addon_dir . 'class-addon-helper.php';
            require_once $addon_dir . 'class-addon-importer.php';
            require_once $addon_dir . 'class-addon-importer-properties.php';
            require_once $addon_dir . 'class-addon-importer-agents.php';
            require_once $addon_dir . 'class-addon-importer-location.php';
            require_once $addon_dir . 'class-addon-field-factory-properties.php';
            require_once $addon_dir . 'class-addon-field-factory-agents.php';
        }

        /**
         * Define constants for Add-On integration
         */
        public function constants() {
            if ( ! defined( 'REALESTATE_SYNC_ADDON_DIR_PATH' ) ) {
                // Dir path for Add-On integration files
                define( 'REALESTATE_SYNC_ADDON_DIR_PATH', dirname( __FILE__ ) . '/' );
            }
            
            if ( ! defined( 'REALESTATE_SYNC_ADDON_ROOT_DIR' ) ) {
                // Root directory for the Add-On integration.
                define( 'REALESTATE_SYNC_ADDON_ROOT_DIR', str_replace( '\\', '/', dirname( __FILE__ ) ) );
            }

            if ( ! defined( 'REALESTATE_SYNC_ADDON_FIELD_PREFIX' ) ) {
                define( 'REALESTATE_SYNC_ADDON_FIELD_PREFIX', 'realestate_sync_addon_' );
            }
        }
    }
}
