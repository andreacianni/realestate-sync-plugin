<?php
/**
 * GitHub Auto-Updater for RealEstate Sync Plugin
 * 
 * Integrates with WordPress update system to automatically detect and update
 * plugin from GitHub releases, similar to the system used in Toro-AG project.
 * 
 * @package RealEstateSync
 * @version 1.0.0
 * @author Andrea Cianni - Novacom
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not allowed.');
}

/**
 * RealEstate_Sync_GitHub_Updater Class
 * 
 * Handles automatic plugin updates from GitHub releases
 * Based on the successful implementation from Toro-AG project
 */
class RealEstate_Sync_GitHub_Updater {
    
    /**
     * Plugin data
     */
    private $plugin_file;
    private $plugin_data;
    private $plugin_basename;
    private $github_username = 'andreacianni';
    private $github_repository = 'realestate-sync-plugin';
    private $github_token = '';
    
    /**
     * Cache settings
     */
    private $cache_key = 'realestate_sync_github_updater_cache';
    private $cache_timeout = 12 * HOUR_IN_SECONDS; // 12 hours like toro-ag
    
    /**
     * API URLs
     */
    private $github_api_url;
    private $github_releases_url;
    
    /**
     * Constructor
     * 
     * @param string $plugin_file Main plugin file path
     */
    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        $this->plugin_basename = plugin_basename($plugin_file);
        $this->plugin_data = get_plugin_data($plugin_file);
        
        // Set GitHub API URLs
        $this->github_api_url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repository}";
        $this->github_releases_url = $this->github_api_url . '/releases';
        
        // Hook into WordPress update system
        $this->init_hooks();
        
        // Add admin interface
        $this->init_admin();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Plugin update hooks
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_api_info'], 20, 3);
        add_filter('upgrader_pre_download', [$this, 'upgrader_pre_download'], 10, 3);
        add_filter('upgrader_source_selection', [$this, 'upgrader_source_selection'], 10, 3);
        
        // Admin hooks
        add_action('admin_init', [$this, 'admin_init']);
    }
    
    /**
     * Initialize admin interface
     */
    private function init_admin() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
        add_action('wp_ajax_realestate_sync_refresh_github_cache', [$this, 'ajax_refresh_cache']);
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_plugins_page(
            'RealEstate Sync GitHub Updater',
            'GitHub Updater',
            'manage_options',
            'realestate-sync-github-updater',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Admin page content
     */
    public function admin_page() {
        $current_version = $this->plugin_data['Version'];
        $latest_release = $this->get_latest_release();
        $latest_version = $latest_release ? $latest_release['tag_name'] : 'Unknown';
        $is_update_available = version_compare($current_version, $latest_version, '<');
        
        ?>
        <div class="wrap">
            <h1>RealEstate Sync Plugin - GitHub Updater</h1>
            
            <div class="card">
                <h2>Plugin Status</h2>
                <table class="form-table">
                    <tr>
                        <th>Current Version</th>
                        <td><strong><?php echo esc_html($current_version); ?></strong></td>
                    </tr>
                    <tr>
                        <th>Latest Version (GitHub)</th>
                        <td>
                            <strong><?php echo esc_html($latest_version); ?></strong>
                            <?php if ($is_update_available): ?>
                                <span class="update-message notice-warning inline">
                                    <strong>Update Available!</strong>
                                </span>
                            <?php else: ?>
                                <span class="update-message notice-success inline">
                                    <strong>Up to date</strong>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Repository</th>
                        <td>
                            <a href="https://github.com/<?php echo esc_attr($this->github_username); ?>/<?php echo esc_attr($this->github_repository); ?>" target="_blank">
                                <?php echo esc_html($this->github_username); ?>/<?php echo esc_html($this->github_repository); ?>
                            </a>
                        </td>
                    </tr>
                </table>
            </div>
            
            <?php if ($latest_release): ?>
            <div class="card">
                <h2>Latest Release Info</h2>
                <p><strong>Version:</strong> <?php echo esc_html($latest_release['tag_name']); ?></p>
                <p><strong>Released:</strong> <?php echo esc_html(date('Y-m-d H:i:s', strtotime($latest_release['published_at']))); ?></p>
                <?php if (!empty($latest_release['body'])): ?>
                    <p><strong>Release Notes:</strong></p>
                    <div class="release-notes">
                        <?php echo wp_kses_post(wpautop($latest_release['body'])); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h2>Cache Management</h2>
                <p>GitHub data is cached for <?php echo esc_html($this->cache_timeout / HOUR_IN_SECONDS); ?> hours to avoid API limits.</p>
                <p>
                    <button type="button" class="button button-secondary" id="refresh-github-cache">
                        Refresh Cache Now
                    </button>
                    <span id="cache-refresh-status"></span>
                </p>
            </div>
            
            <?php if ($is_update_available): ?>
            <div class="card">
                <h2>Update Available</h2>
                <p>A new version is available. You can update the plugin from the main Plugins page or Updates page.</p>
                <p>
                    <a href="<?php echo admin_url('plugins.php'); ?>" class="button button-primary">
                        Go to Plugins
                    </a>
                    <a href="<?php echo admin_url('update-core.php'); ?>" class="button button-secondary">
                        Go to Updates
                    </a>
                </p>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h2>Auto-Update System</h2>
                <p>This plugin uses the same GitHub auto-update system as the Toro-AG project:</p>
                <ul>
                    <li>✅ Automatically detects new GitHub releases</li>
                    <li>✅ Integrates with WordPress native update system</li>
                    <li>✅ One-click updates from WordPress admin</li>
                    <li>✅ No FTP required for updates</li>
                    <li>✅ Cache system to avoid GitHub API limits</li>
                </ul>
            </div>
        </div>
        
        <style>
        .card { max-width: 800px; }
        .update-message { padding: 5px 10px; border-radius: 3px; margin-left: 10px; }
        .notice-warning { background-color: #fff3cd; border-left: 4px solid #ffc107; }
        .notice-success { background-color: #d4edda; border-left: 4px solid #28a745; }
        .release-notes { background: #f8f9fa; padding: 10px; border-left: 4px solid #007cba; margin-top: 10px; }
        </style>
        <?php
    }
    
    /**
     * Enqueue admin scripts
     */
    public function admin_enqueue_scripts($hook) {
        if ($hook !== 'plugins_page_realestate-sync-github-updater') {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', "
            jQuery(document).ready(function($) {
                $('#refresh-github-cache').on('click', function() {
                    var button = $(this);
                    var status = $('#cache-refresh-status');
                    
                    button.prop('disabled', true).text('Refreshing...');
                    status.html('<span style=\"color: #0073aa;\">Refreshing cache...</span>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'realestate_sync_refresh_github_cache',
                            nonce: '" . wp_create_nonce('realestate_sync_refresh_cache') . "'
                        },
                        success: function(response) {
                            if (response.success) {
                                status.html('<span style=\"color: #28a745;\">✅ Cache refreshed successfully!</span>');
                                setTimeout(function() { location.reload(); }, 1500);
                            } else {
                                status.html('<span style=\"color: #dc3545;\">❌ Error: ' + response.data + '</span>');
                            }
                        },
                        error: function() {
                            status.html('<span style=\"color: #dc3545;\">❌ Network error occurred</span>');
                        },
                        complete: function() {
                            button.prop('disabled', false).text('Refresh Cache Now');
                        }
                    });
                });
            });
        ");
    }
    
    /**
     * AJAX handler for cache refresh
     */
    public function ajax_refresh_cache() {
        check_ajax_referer('realestate_sync_refresh_cache', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Clear cache
        delete_transient($this->cache_key);
        
        // Fetch fresh data
        $latest_release = $this->get_latest_release(true);
        
        if ($latest_release) {
            wp_send_json_success('Cache refreshed successfully');
        } else {
            wp_send_json_error('Failed to fetch GitHub data');
        }
    }
    
    /**
     * Check for plugin updates
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Skip if our plugin is not in the checked list
        if (!isset($transient->checked[$this->plugin_basename])) {
            return $transient;
        }
        
        $latest_release = $this->get_latest_release();
        if (!$latest_release) {
            return $transient;
        }
        
        $current_version = $this->plugin_data['Version'];
        $latest_version = ltrim($latest_release['tag_name'], 'v'); // Remove 'v' prefix
        
        // Debug logging
        error_log("RealEstate Sync Updater: Current {$current_version}, Latest {$latest_version}");
        
        if (version_compare($current_version, $latest_version, '<')) {
            $transient->response[$this->plugin_basename] = (object) [
                'id' => $this->plugin_basename,
                'slug' => dirname($this->plugin_basename),
                'plugin' => $this->plugin_basename,
                'new_version' => $latest_version,
                'url' => 'https://github.com/' . $this->github_username . '/' . $this->github_repository,
                'package' => $latest_release['zipball_url'],
                'icons' => [],
                'banners' => [],
                'banners_rtl' => [],
                'tested' => $this->plugin_data['Tested up to'] ?? get_bloginfo('version'),
                'requires_php' => $this->plugin_data['Requires PHP'] ?? '7.4',
                'compatibility' => new stdClass(),
                'upgrade_notice' => $latest_release['body'] ?? ''
            ];
            
            error_log("RealEstate Sync Updater: Update available, added to transient");
        }
        
        return $transient;
    }
    
    /**
     * Provide plugin API information
     */
    public function plugin_api_info($false, $action, $response) {
        if ($action !== 'plugin_information') {
            return $false;
        }
        
        if (empty($response->slug) || $response->slug !== dirname($this->plugin_basename)) {
            return $false;
        }
        
        $latest_release = $this->get_latest_release();
        if (!$latest_release) {
            return $false;
        }
        
        $latest_version = ltrim($latest_release['tag_name'], 'v');
        
        return (object) [
            'name' => $this->plugin_data['Name'],
            'slug' => dirname($this->plugin_basename),
            'version' => $latest_version,
            'author' => $this->plugin_data['Author'],
            'author_profile' => $this->plugin_data['AuthorURI'] ?? '',
            'contributors' => [],
            'requires' => $this->plugin_data['Requires at least'] ?? '5.0',
            'tested' => $this->plugin_data['Tested up to'] ?? get_bloginfo('version'),
            'requires_php' => $this->plugin_data['Requires PHP'] ?? '7.4',
            'rating' => 0,
            'ratings' => [],
            'num_ratings' => 0,
            'support_threads' => 0,
            'support_threads_resolved' => 0,
            'downloaded' => 0,
            'last_updated' => $latest_release['published_at'],
            'added' => $latest_release['published_at'],
            'homepage' => 'https://github.com/' . $this->github_username . '/' . $this->github_repository,
            'download_link' => $latest_release['zipball_url'],
            'trunk' => $latest_release['zipball_url'],
            'sections' => [
                'description' => $this->plugin_data['Description'] ?? 'WordPress plugin for automated real estate listings import.',
                'installation' => 'Upload and activate the plugin, then configure it from the admin panel.',
                'changelog' => $latest_release['body'] ?? 'No changelog available.',
                'faq' => 'For support, please visit the GitHub repository.'
            ],
            'short_description' => $this->plugin_data['Description'] ?? '',
            'banners' => [],
            'icons' => []
        ];
    }
    
    /**
     * Handle plugin download
     */
    public function upgrader_pre_download($reply, $package, $upgrader) {
        if (strpos($package, 'github.com') === false) {
            return $reply;
        }
        
        if (!property_exists($upgrader, 'skin') || 
            !property_exists($upgrader->skin, 'plugin') || 
            $upgrader->skin->plugin !== $this->plugin_basename) {
            return $reply;
        }
        
        return $reply;
    }
    
    /**
     * Handle source selection after extraction
     */
    public function upgrader_source_selection($source, $remote_source, $upgrader) {
        global $wp_filesystem;
        
        // Check if this is our plugin update
        if (!property_exists($upgrader, 'skin') || 
            !property_exists($upgrader->skin, 'plugin') || 
            $upgrader->skin->plugin !== $this->plugin_basename) {
            return $source;
        }
        
        error_log("RealEstate Sync Updater: Source selection - Original: {$source}");
        
        // GitHub creates directories like "andreacianni-realestate-sync-plugin-abc1234"
        // We need to rename it to match the plugin directory name
        $plugin_folder = dirname($this->plugin_basename); // realestate-sync-plugin
        $new_source = dirname($source) . '/' . $plugin_folder;
        
        error_log("RealEstate Sync Updater: Source selection - Target: {$new_source}");
        
        // Use WordPress filesystem API
        if ($wp_filesystem && $wp_filesystem->move($source, $new_source)) {
            error_log("RealEstate Sync Updater: Source renamed successfully");
            return $new_source;
        }
        
        // Fallback to PHP rename
        if (rename($source, $new_source)) {
            error_log("RealEstate Sync Updater: Source renamed with PHP rename");
            return $new_source;
        }
        
        error_log("RealEstate Sync Updater: Source rename failed, using original");
        return $source;
    }
    
    /**
     * Get latest release from GitHub
     */
    private function get_latest_release($force_refresh = false) {
        if (!$force_refresh) {
            $cached_data = get_transient($this->cache_key);
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
        $args = [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
            ]
        ];
        
        // Add GitHub token if available
        if (!empty($this->github_token)) {
            $args['headers']['Authorization'] = 'token ' . $this->github_token;
        }
        
        $response = wp_remote_get($this->github_releases_url . '/latest', $args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data) || !isset($data['tag_name'])) {
            return false;
        }
        
        // Cache for 12 hours like toro-ag
        set_transient($this->cache_key, $data, $this->cache_timeout);
        
        return $data;
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Add settings link to plugin row
        add_filter('plugin_action_links_' . $this->plugin_basename, [$this, 'plugin_action_links']);
    }
    
    /**
     * Add settings link to plugin actions
     */
    public function plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('plugins.php?page=realestate-sync-github-updater'),
            __('GitHub Updater', 'realestate-sync')
        );
        
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Set GitHub token for API requests
     */
    public function set_github_token($token) {
        $this->github_token = $token;
    }
    
    /**
     * Get current plugin version
     */
    public function get_current_version() {
        return $this->plugin_data['Version'];
    }
    
    /**
     * Get latest version from GitHub
     */
    public function get_latest_version() {
        $latest_release = $this->get_latest_release();
        return $latest_release ? $latest_release['tag_name'] : false;
    }
    
    /**
     * Check if update is available
     */
    public function is_update_available() {
        $current = $this->get_current_version();
        $latest = $this->get_latest_version();
        
        if (!$latest) {
            return false;
        }
        
        return version_compare($current, $latest, '<');
    }
}
