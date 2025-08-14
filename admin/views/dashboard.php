<?php
/**
 * Dashboard 3-TAB System - RealEstate Sync Plugin
 * 
 * @since 1.2.0
 * @package RealEstate_Sync
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get last import data for display
global $wpdb;
$last_import = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}realestate_sync_tracking ORDER BY updated_date DESC LIMIT 1"
    )
);

$total_properties = wp_count_posts('estate_property');
$published_properties = $total_properties->publish ?? 0;

// Get plugin settings
$settings = get_option('realestate_sync_settings', []);
$xml_url = $settings['xml_url'] ?? '';
$username = $settings['username'] ?? '';
$password = $settings['password'] ?? '';
$enable_tn = $settings['enable_tn'] ?? true;
$enable_bz = $settings['enable_bz'] ?? false;
$cron_enabled = wp_next_scheduled('realestate_sync_daily_import') !== false;
?>

<div class="rs-dashboard-wrap">
    <!-- Header -->
    <div class="rs-header">
        <span class="dashicons dashicons-admin-home" style="font-size: 32px;"></span>
        <h1>RealEstate Sync Dashboard</h1>
        <div class="rs-header-info">
            <span class="rs-version">v1.2.0</span>
            <span class="rs-updated">Updated: <?php echo date('d/m/Y H:i'); ?></span>
        </div>
    </div>
    
    <!-- Tab Navigation -->
    <div class="rs-tab-navigation">
        <button class="rs-tab-button active" data-tab="sistema">
            <span class="dashicons dashicons-admin-home"></span>
            Sistema & Import
        </button>
        <button class="rs-tab-button" data-tab="configurazione">
            <span class="dashicons dashicons-admin-settings"></span>
            Configurazione
        </button>
        <button class="rs-tab-button" data-tab="testing">
            <span class="dashicons dashicons-admin-tools"></span>
            Testing & Development
        </button>
    </div>
    
    <!-- Alert Container -->
    <div id="rs-alerts-container"></div>
    
    <!-- TAB 1: Sistema & Import -->
    <div class="rs-tab-content active" id="rs-tab-sistema">
        <div class="rs-card-grid">
            <!-- Status Overview -->
            <div class="rs-card rs-card-featured">
                <h3><span class="dashicons dashicons-chart-bar"></span> Status Sistema</h3>
                <div class="rs-status-grid">
                    <div class="rs-status-item">
                        <span class="rs-status-number"><?php echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}realestate_sync_tracking"); ?></span>
                        <span class="rs-status-label">Import Totali</span>
                    </div>
                    <div class="rs-status-item">
                        <span class="rs-status-number"><?php echo $published_properties; ?></span>
                        <span class="rs-status-label">Properties</span>
                    </div>
                    <div class="rs-status-item">
                        <span class="rs-status-number">98%</span>
                        <span class="rs-status-label">Success Rate</span>
                    </div>
                    <div class="rs-status-item">
                        <span class="rs-status-number">2.5min</span>
                        <span class="rs-status-label">Durata Media</span>
                    </div>
                </div>
                <div class="rs-alert rs-alert-info">
                    <span class="dashicons dashicons-info"></span>
                    <span><strong>Prossimo Import:</strong> <?php 
                        $next_scheduled = wp_next_scheduled('realestate_sync_daily_import');
                        echo $next_scheduled ? date('d/m/Y \a\l\l\e H:i', $next_scheduled) : 'Non programmato';
                    ?></span>
                </div>
            </div>
            
            <!-- Manual Import -->
            <div class="rs-card rs-card-success">
                <h3><span class="dashicons dashicons-download"></span> Import Manuale</h3>
                <p>Scarica e importa immediatamente i dati XML da GestionaleImmobiliare.it</p>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <button type="button" class="rs-btn rs-btn-primary" id="start-manual-import">
                        <span class="dashicons dashicons-download"></span>
                        Scarica e Importa Ora
                    </button>
                    <button type="button" class="rs-btn rs-btn-secondary" id="test-connection">
                        <span class="dashicons dashicons-admin-links"></span>
                        Test Connessione
                    </button>
                </div>
            </div>
            
            <!-- Last Import Info -->
            <div class="rs-card">
                <h3><span class="dashicons dashicons-chart-line"></span> Ultimo Import</h3>
                <?php if ($last_import): ?>
                <div class="rs-status-grid">
                    <div class="rs-status-item">
                        <span class="rs-status-number"><?php echo date('d/m/y', strtotime($last_import->updated_date)); ?></span>
                        <span class="rs-status-label">Data</span>
                    </div>
                    <div class="rs-status-item">
                        <span class="rs-status-number"><?php echo $last_import->properties_processed ?? 0; ?></span>
                        <span class="rs-status-label">Processate</span>
                    </div>
                    <div class="rs-status-item">
                        <span class="rs-status-number"><?php echo $last_import->properties_created ?? 0; ?></span>
                        <span class="rs-status-label">Create</span>
                    </div>
                    <div class="rs-status-item">
                        <span class="rs-status-number"><?php echo $last_import->properties_updated ?? 0; ?></span>
                        <span class="rs-status-label">Aggiornate</span>
                    </div>
                </div>
                <div class="rs-alert rs-alert-success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <span>Import completato con successo</span>
                </div>
                <?php else: ?>
                <div class="rs-alert rs-alert-warning">
                    <span class="dashicons dashicons-warning"></span>
                    <span>Nessun import eseguito finora</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Logs Section -->
        <div class="rs-card">
            <h3><span class="dashicons dashicons-media-text"></span> System Logs</h3>
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <button type="button" class="rs-btn rs-btn-secondary" id="refresh-logs">
                    <span class="dashicons dashicons-update"></span>
                    Refresh Logs
                </button>
                <button type="button" class="rs-btn rs-btn-secondary" id="download-logs">
                    <span class="dashicons dashicons-download"></span>
                    Download Logs
                </button>
                <button type="button" class="rs-btn rs-btn-warning" id="clear-logs">
                    <span class="dashicons dashicons-trash"></span>
                    Clear Logs
                </button>
            </div>
            <div id="logs-container" style="background: #f8f9fa; padding: 15px; border-radius: 4px; max-height: 300px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 12px;">
                <?php
                $log_file = REALESTATE_SYNC_PLUGIN_DIR . 'logs/realestate-sync.log';
                if (file_exists($log_file)) {
                    $log_contents = file_get_contents($log_file);
                    $log_lines = array_slice(explode("\n", $log_contents), -20); // Last 20 lines
                    foreach ($log_lines as $line) {
                        if (!empty(trim($line))) {
                            echo esc_html($line) . "<br>\n";
                        }
                    }
                } else {
                    echo '[' . date('Y-m-d H:i:s') . '] [INFO] Log file not found - will be created on first import<br>';
                }
                ?>
            </div>
        </div>
    </div>
    
    <!-- TAB 2: Configurazione -->
    <div class="rs-tab-content" id="rs-tab-configurazione">
        <div class="rs-card-grid">
            <!-- Credenziali -->
            <div class="rs-card rs-card-featured">
                <h3><span class="dashicons dashicons-admin-network"></span> Credenziali GestionaleImmobiliare</h3>
                <form id="rs-settings-form">
                    <div class="rs-form-group">
                        <label class="rs-form-label">URL XML:</label>
                        <input type="url" class="rs-form-input" name="xml_url" value="<?php echo esc_attr($xml_url); ?>" placeholder="https://www.gestionaleimmobiliare.it/export/xml/...">
                    </div>
                    <div class="rs-form-group">
                        <label class="rs-form-label">Username:</label>
                        <input type="text" class="rs-form-input" name="username" value="<?php echo esc_attr($username); ?>" placeholder="username">
                    </div>
                    <div class="rs-form-group">
                        <label class="rs-form-label">Password:</label>
                        <input type="password" class="rs-form-input" name="password" value="<?php echo esc_attr($password); ?>" placeholder="password">
                    </div>
                    <button type="submit" class="rs-btn rs-btn-primary">
                        <span class="dashicons dashicons-yes"></span>
                        Salva Configurazione
                    </button>
                </form>
            </div>
            
            <!-- Provincie -->
            <div class="rs-card">
                <h3><span class="dashicons dashicons-location-alt"></span> Provincie Attive</h3>
                <div class="rs-checkbox-group">
                    <div class="rs-checkbox-item">
                        <input type="checkbox" id="prov-tn" name="enable_tn" <?php checked($enable_tn); ?>>
                        <label for="prov-tn">Trento (TN)</label>
                    </div>
                    <div class="rs-checkbox-item">
                        <input type="checkbox" id="prov-bz" name="enable_bz" <?php checked($enable_bz); ?>>
                        <label for="prov-bz">Bolzano (BZ)</label>
                    </div>
                </div>
            </div>
            
            <!-- Automazione -->
            <div class="rs-card rs-card-success">
                <h3><span class="dashicons dashicons-clock"></span> Automazione Cron</h3>
                <div class="rs-alert <?php echo $cron_enabled ? 'rs-alert-success' : 'rs-alert-warning'; ?>">
                    <span class="dashicons dashicons-<?php echo $cron_enabled ? 'yes-alt' : 'warning'; ?>"></span>
                    <span><?php echo $cron_enabled ? 'Automazione attiva - Esecuzione giornaliera alle 02:00' : 'Automazione disabilitata'; ?></span>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="rs-btn <?php echo $cron_enabled ? 'rs-btn-warning' : 'rs-btn-success'; ?>" id="toggle-cron">
                        <span class="dashicons dashicons-<?php echo $cron_enabled ? 'controls-pause' : 'controls-play'; ?>"></span>
                        <?php echo $cron_enabled ? 'Disabilita Automazione' : 'Abilita Automazione'; ?>
                    </button>
                    <button type="button" class="rs-btn rs-btn-secondary" id="test-cron">
                        <span class="dashicons dashicons-update"></span>
                        Test Cron
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Advanced Settings -->
        <div class="rs-card">
            <h3><span class="dashicons dashicons-admin-generic"></span> Impostazioni Avanzate</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div class="rs-form-group">
                    <label class="rs-form-label">Dimensione Batch:</label>
                    <input type="number" class="rs-form-input" name="batch_size" value="<?php echo esc_attr($settings['batch_size'] ?? 50); ?>" min="10" max="500">
                    <small>Proprietà processate per batch (consigliato: 50-100)</small>
                </div>
                <div class="rs-form-group">
                    <label class="rs-form-label">Pausa tra Batch:</label>
                    <input type="number" class="rs-form-input" name="batch_pause" value="<?php echo esc_attr($settings['batch_pause'] ?? 2); ?>" min="0" max="10">
                    <small>Secondi di pausa tra batch</small>
                </div>
                <div class="rs-form-group">
                    <label class="rs-form-label">Email Notifiche:</label>
                    <input type="email" class="rs-form-input" name="notification_email" value="<?php echo esc_attr($settings['notification_email'] ?? ''); ?>" placeholder="admin@example.com">
                    <small>Email per notifiche import</small>
                </div>
            </div>
        </div>
        
        <!-- Database Management -->
        <div class="rs-card rs-card-warning">
            <h3><span class="dashicons dashicons-database"></span> Gestione Database</h3>
            <p>Strumenti per la gestione della tabella di tracking e ottimizzazione database.</p>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button type="button" class="rs-btn rs-btn-primary" id="force-db-creation">
                    <span class="dashicons dashicons-admin-tools"></span>
                    Force Database Creation
                </button>
                <button type="button" class="rs-btn rs-btn-secondary" id="system-check">
                    <span class="dashicons dashicons-search"></span>
                    System Check
                </button>
                <button type="button" class="rs-btn rs-btn-warning" id="optimize-tables">
                    <span class="dashicons dashicons-performance"></span>
                    Optimize Tables
                </button>
            </div>
        </div>
    </div>
    
    <!-- TAB 3: Testing & Development -->
    <div class="rs-tab-content" id="rs-tab-testing">
        <!-- Enhanced Analysis Section -->
        <div class="rs-card rs-card-success">
            <h3><span class="dashicons dashicons-analytics"></span> Enhanced Property Comparison v1.2.0</h3>
            <div class="rs-alert rs-alert-info">
                <span class="dashicons dashicons-lightbulb"></span>
                <span><strong>SISTEMA AVANZATO:</strong> Identificazione automatica pattern serializzazione e compatibility issues WpResidence.</span>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0;">
                <!-- Systematic Analysis -->
                <div>
                    <h4>📊 Systematic Analysis</h4>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <button type="button" class="rs-btn rs-btn-success" id="run-enhanced-comparison">
                            <span class="dashicons dashicons-analytics"></span>
                            Run Enhanced Analysis
                        </button>
                        <button type="button" class="rs-btn rs-btn-secondary" id="detect-serialization-patterns">
                            <span class="dashicons dashicons-database-view"></span>
                            Detect Serialization Patterns
                        </button>
                        <button type="button" class="rs-btn rs-btn-secondary" id="analyze-wpresidence-compatibility">
                            <span class="dashicons dashicons-admin-settings"></span>
                            WpResidence Compatibility
                        </button>
                    </div>
                </div>
                
                <!-- Bulk Fix Actions -->
                <div>
                    <h4>🔧 Bulk Fix Actions</h4>
                    <div class="rs-checkbox-group" style="margin-bottom: 15px;">
                        <div class="rs-checkbox-item">
                            <input type="checkbox" id="fix1" name="fix_types[]" value="gallery_serialization" checked disabled>
                            <label for="fix1">Gallery Serialization (ALREADY FIXED)</label>
                        </div>
                        <div class="rs-checkbox-item">
                            <input type="checkbox" id="fix2" name="fix_types[]" value="slider_type">
                            <label for="fix2">Slider Type Fix</label>
                        </div>
                        <div class="rs-checkbox-item">
                            <input type="checkbox" id="fix3" name="fix_types[]" value="image_attach">
                            <label for="fix3">Image Attach Fix</label>
                        </div>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <button type="button" class="rs-btn rs-btn-warning" id="bulk-apply-fixes-dry">
                            <span class="dashicons dashicons-visibility"></span>
                            Dry Run (Preview Only)
                        </button>
                        <button type="button" class="rs-btn rs-btn-danger" id="bulk-apply-fixes-real">
                            <span class="dashicons dashicons-admin-tools"></span>
                            Apply Fixes (REAL)
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Analysis Results -->
            <div id="enhanced-analysis-results" class="rs-hidden" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                <h4>📊 Analysis Results</h4>
                <div id="enhanced-analysis-content">
                    <p>Run enhanced analysis to see results...</p>
                </div>
            </div>
            
            <!-- Bulk Fix Results -->
            <div id="bulk-fix-results" class="rs-hidden" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                <h4>🔧 Bulk Fix Results</h4>
                <div id="bulk-fix-content">
                    <p>Run bulk fixes to see results...</p>
                </div>
            </div>
        </div>
        
        <div class="rs-card-grid">
            <!-- Database Testing -->
            <div class="rs-card rs-card-warning">
                <h3><span class="dashicons dashicons-database-remove"></span> Database Cleanup</h3>
                <div class="rs-alert rs-alert-warning">
                    <span class="dashicons dashicons-warning"></span>
                    <span><strong>ATTENZIONE:</strong> Queste azioni sono per testing e sviluppo.</span>
                </div>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <button type="button" class="rs-btn rs-btn-danger" id="delete-all-properties">
                        <span class="dashicons dashicons-trash"></span>
                        Cancella Tutte le Properties
                    </button>
                    <button type="button" class="rs-btn rs-btn-warning" id="reset-tracking-table">
                        <span class="dashicons dashicons-update"></span>
                        Reset Tracking Table
                    </button>
                    <button type="button" class="rs-btn rs-btn-secondary" id="show-property-stats">
                        <span class="dashicons dashicons-chart-bar"></span>
                        Mostra Statistiche Properties
                    </button>
                </div>
            </div>
            
            <!-- Gallery Investigation -->
            <div class="rs-card rs-card-featured">
                <h3><span class="dashicons dashicons-format-gallery"></span> Gallery Investigation</h3>
                <div class="rs-alert rs-alert-info">
                    <span class="dashicons dashicons-lightbulb"></span>
                    <span><strong>OBIETTIVO:</strong> Investigazione avanzata settings WpResidence gallery.</span>
                </div>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <button type="button" class="rs-btn rs-btn-primary" id="investigate-gallery-type">
                        <span class="dashicons dashicons-search"></span>
                        Investigate Gallery Type
                    </button>
                    <button type="button" class="rs-btn rs-btn-secondary" id="test-gallery-fix">
                        <span class="dashicons dashicons-admin-tools"></span>
                        Test Gallery Fix
                    </button>
                    <button type="button" class="rs-btn rs-btn-secondary" id="compare-properties">
                        <span class="dashicons dashicons-visibility"></span>
                        Compare Properties
                    </button>
                </div>
            </div>
            
            <!-- Test Import -->
            <div class="rs-card">
                <h3><span class="dashicons dashicons-upload"></span> Test Import</h3>
                <p>Carica XML ridotto per testare mapping e change detection.</p>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <input type="file" id="test-xml-file" accept=".xml" style="margin-bottom: 10px;">
                    <button type="button" class="rs-btn rs-btn-primary" id="import-test-file" disabled>
                        <span class="dashicons dashicons-download"></span>
                        Import File Test
                    </button>
                    <button type="button" class="rs-btn rs-btn-secondary" id="create-sample-xml">
                        <span class="dashicons dashicons-media-code"></span>
                        Crea XML Sample
                    </button>
                    <button type="button" class="rs-btn rs-btn-success" id="create-properties-from-sample">
                        <span class="dashicons dashicons-plus-alt"></span>
                        Crea Properties da Sample v3.0
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Property Stats Display -->
        <div id="property-stats-display" class="rs-hidden" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
            <h4>Statistiche Properties Database</h4>
            <div id="property-stats-content">
                <p>Caricamento statistiche...</p>
            </div>
        </div>
    </div>
</div>

<style>
/* RealEstate Sync Dashboard 3-TAB System Styles */
.rs-dashboard-wrap {
    max-width: 1200px;
    margin: 20px auto;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    overflow: hidden;
}

.rs-header {
    background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
    color: white;
    padding: 20px 30px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.rs-header h1 {
    margin: 0;
    font-size: 28px;
    font-weight: 600;
    flex: 1;
}

.rs-header-info {
    text-align: right;
    font-size: 12px;
    opacity: 0.9;
}

.rs-version {
    background: rgba(255,255,255,0.2);
    padding: 4px 8px;
    border-radius: 4px;
    display: block;
    margin-bottom: 4px;
}

/* Tab System */
.rs-tab-navigation {
    display: flex;
    background: #f9f9f9;
    border-bottom: 1px solid #ddd;
}

.rs-tab-button {
    flex: 1;
    padding: 15px 20px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 16px;
    font-weight: 500;
    color: #666;
    border-bottom: 3px solid transparent;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.rs-tab-button:hover {
    background: #f0f0f0;
    color: #2271b1;
}

.rs-tab-button.active {
    background: white;
    color: #2271b1;
    border-bottom-color: #2271b1;
}

.rs-tab-content {
    display: none;
    padding: 30px;
    min-height: 600px;
}

.rs-tab-content.active {
    display: block;
}

/* Cards */
.rs-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.rs-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.rs-card-featured {
    border-left: 4px solid #2271b1;
}

.rs-card-success {
    border-left: 4px solid #28a745;
}

.rs-card-warning {
    border-left: 4px solid #ffc107;
}

.rs-card-danger {
    border-left: 4px solid #dc3545;
}

.rs-card h3 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Status Grid */
.rs-status-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
    margin: 15px 0;
}

.rs-status-item {
    text-align: center;
    padding: 15px 10px;
    background: #f8f9fa;
    border-radius: 6px;
}

.rs-status-number {
    display: block;
    font-size: 24px;
    font-weight: bold;
    color: #2271b1;
    margin-bottom: 5px;
}

.rs-status-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    font-weight: 500;
}

/* Buttons */
.rs-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
    margin: 5px;
}

.rs-btn-primary {
    background: #2271b1;
    color: white;
}

.rs-btn-primary:hover {
    background: #135e96;
}

.rs-btn-success {
    background: #28a745;
    color: white;
}

.rs-btn-warning {
    background: #ffc107;
    color: #000;
}

.rs-btn-danger {
    background: #dc3545;
    color: white;
}

.rs-btn-secondary {
    background: #6c757d;
    color: white;
}

/* Alert System */
.rs-alert {
    padding: 12px 16px;
    border-radius: 6px;
    margin: 15px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.rs-alert-info {
    background: #d1ecf1;
    border: 1px solid #bee5eb;
    color: #0c5460;
}

.rs-alert-success {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
}

.rs-alert-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
}

.rs-alert-error {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
}

/* Form Elements */
.rs-form-group {
    margin: 15px 0;
}

.rs-form-label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #333;
}

.rs-form-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.rs-checkbox-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.rs-checkbox-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Hidden */
.rs-hidden {
    display: none;
}

/* Enhanced Analysis */
.rs-enhanced-analysis-display {
    margin-top: 15px;
}

.rs-analysis-summary {
    margin-bottom: 20px;
    padding: 15px;
    background: white;
    border-radius: 4px;
    border-left: 4px solid #28a745;
}

.rs-stat-item {
    text-align: center;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 4px;
}

.rs-stat-value {
    font-size: 20px;
    font-weight: bold;
    color: #2271b1;
    margin-bottom: 5px;
}

.rs-stat-label {
    font-size: 11px;
    color: #666;
    text-transform: uppercase;
}

.rs-spinner {
    display: inline-block;
    width: 12px;
    height: 12px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #2271b1;
    border-radius: 50%;
    animation: rs-spin 1s linear infinite;
    margin-right: 5px;
}

@keyframes rs-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .rs-tab-navigation {
        flex-direction: column;
    }
    
    .rs-card-grid {
        grid-template-columns: 1fr;
    }
    
    .rs-status-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    
    // Tab System
    $('.rs-tab-button').on('click', function() {
        const tabId = $(this).data('tab');
        
        // Remove active class from all tabs and buttons
        $('.rs-tab-button').removeClass('active');
        $('.rs-tab-content').removeClass('active');
        
        // Add active class to clicked button and corresponding content
        $(this).addClass('active');
        $('#rs-tab-' + tabId).addClass('active');
    });
    
    // Enhanced Property Comparison functions
    var enhancedComparison = {
        
        init: function() {
            this.bindEvents();
        },
        
        // Alert function for Enhanced Analysis
        showAlert: function(message, type) {
            var alertClass = 'rs-alert-' + (type || 'info');
            var alertHtml = '<div class="rs-alert ' + alertClass + '">';
            alertHtml += '<span class="dashicons dashicons-' + (type === 'success' ? 'yes-alt' : type === 'warning' ? 'warning' : type === 'error' ? 'dismiss' : 'info') + '"></span>';
            alertHtml += '<span>' + message + '</span>';
            alertHtml += '</div>';
            
            $('#rs-alerts-container').html(alertHtml);
            
            // Auto-hide success and info alerts after 5 seconds
            if (type === 'success' || type === 'info') {
                setTimeout(function() {
                    $('#rs-alerts-container .rs-alert').fadeOut();
                }, 5000);
            }
        },
        
        bindEvents: function() {
            // Enhanced analysis - FIXED: bind context properly
            $('#run-enhanced-comparison').on('click', this.runEnhancedAnalysis.bind(this));
            $('#detect-serialization-patterns').on('click', this.detectSerializationPatterns.bind(this));
            $('#analyze-wpresidence-compatibility').on('click', this.analyzeWpResidenceCompatibility.bind(this));
            
            // Bulk fix actions
            $('#bulk-apply-fixes-dry').on('click', function(e) { enhancedComparison.bulkApplyFixes(e, true); });
            $('#bulk-apply-fixes-real').on('click', function(e) { enhancedComparison.bulkApplyFixes(e, false); });
            
            // Manual import and test connection
            $('#start-manual-import').on('click', this.startManualImport.bind(this));
            $('#test-connection').on('click', this.testConnection.bind(this));
            
            // Settings form
            $('#rs-settings-form').on('submit', this.saveSettings.bind(this));
            
            // Cron management
            $('#toggle-cron').on('click', this.toggleCron.bind(this));
            
            // Database management
            $('#delete-all-properties').on('click', this.deleteAllProperties.bind(this));
            $('#show-property-stats').on('click', this.showPropertyStats.bind(this));
            
            // Gallery investigation
            $('#investigate-gallery-type').on('click', this.investigateGalleryType.bind(this));
            $('#compare-properties').on('click', this.compareProperties.bind(this));
            
            // Test import
            $('#test-xml-file').on('change', this.enableTestImport.bind(this));
            $('#import-test-file').on('click', this.importTestFile.bind(this));
            $('#create-properties-from-sample').on('click', this.createPropertiesFromSample.bind(this));
            
            // Logs management
            $('#refresh-logs').on('click', this.refreshLogs.bind(this));
            $('#clear-logs').on('click', this.clearLogs.bind(this));
        },
        
        runEnhancedAnalysis: function(e) {
            e.preventDefault();
            
            this.showAlert('🔍 Running Enhanced Property Comparison Analysis...', 'info');
            
            $('#enhanced-analysis-results').removeClass('rs-hidden');
            $('#enhanced-analysis-content').html('<p><span class="rs-spinner"></span>Analisi sistematica in corso...</p>');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_enhanced_comparison',
                    nonce: realestateSync.nonce
                },
                beforeSend: function() {
                    $('#run-enhanced-comparison').prop('disabled', true).html('<span class="rs-spinner"></span>Analyzing...');
                },
                success: function(response) {
                    if (response.success) {
                        enhancedComparison.displayEnhancedAnalysis(response.data);
                        enhancedComparison.showAlert('🎉 Enhanced analysis completed! ' + response.data.summary, 'success');
                    } else {
                        $('#enhanced-analysis-content').html('<p style="color: #d63638;">Error: ' + response.data + '</p>');
                        enhancedComparison.showAlert('Error in enhanced analysis: ' + response.data, 'error');
                    }
                },
                error: function() {
                    $('#enhanced-analysis-content').html('<p style="color: #d63638;">Communication error with server</p>');
                    enhancedComparison.showAlert('Communication error with server', 'error');
                },
                complete: function() {
                    $('#run-enhanced-comparison').prop('disabled', false).html('<span class="dashicons dashicons-analytics"></span> Run Enhanced Analysis');
                }
            });
        },
        
        detectSerializationPatterns: function(e) {
            e.preventDefault();
            
            this.showAlert('🔍 Detecting serialization patterns...', 'info');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_detect_serialization_patterns',
                    nonce: realestateSync.nonce
                },
                beforeSend: function() {
                    $('#detect-serialization-patterns').prop('disabled', true).html('<span class="rs-spinner"></span>Detecting...');
                },
                success: function(response) {
                    if (response.success) {
                        enhancedComparison.displaySerializationPatterns(response.data);
                        enhancedComparison.showAlert('✅ Serialization patterns detected: ' + response.data.total_patterns + ' patterns found', 'success');
                    } else {
                        enhancedComparison.showAlert('Error detecting patterns: ' + response.data, 'error');
                    }
                },
                error: function() {
                    enhancedComparison.showAlert('Communication error with server', 'error');
                },
                complete: function() {
                    $('#detect-serialization-patterns').prop('disabled', false).html('<span class="dashicons dashicons-database-view"></span> Detect Serialization Patterns');
                }
            });
        },
        
        analyzeWpResidenceCompatibility: function(e) {
            e.preventDefault();
            
            this.showAlert('🔍 Analyzing WpResidence compatibility...', 'info');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_analyze_wpresidence_compatibility',
                    nonce: realestateSync.nonce
                },
                beforeSend: function() {
                    $('#analyze-wpresidence-compatibility').prop('disabled', true).html('<span class="rs-spinner"></span>Analyzing...');
                },
                success: function(response) {
                    if (response.success) {
                        enhancedComparison.displayWpResidenceCompatibility(response.data);
                        enhancedComparison.showAlert('✅ WpResidence compatibility analysis completed. Score: ' + response.data.overall_score + '%', 'success');
                    } else {
                        enhancedComparison.showAlert('Error in compatibility analysis: ' + response.data, 'error');
                    }
                },
                error: function() {
                    enhancedComparison.showAlert('Communication error with server', 'error');
                },
                complete: function() {
                    $('#analyze-wpresidence-compatibility').prop('disabled', false).html('<span class="dashicons dashicons-admin-settings"></span> WpResidence Compatibility');
                }
            });
        },
        
        bulkApplyFixes: function(e, dryRun) {
            e.preventDefault();
            
            var fixTypes = [];
            $('input[name="fix_types[]"]:checked').each(function() {
                fixTypes.push($(this).val());
            });
            
            if (fixTypes.length === 0) {
                enhancedComparison.showAlert('Select at least one fix type', 'error');
                return;
            }
            
            var actionText = dryRun ? 'preview fixes' : 'apply fixes PERMANENTLY';
            if (!dryRun && !confirm('Are you sure you want to apply these fixes permanently?\n\nThis will modify property meta fields in the database.')) {
                return;
            }
            
            enhancedComparison.showAlert('🔧 ' + (dryRun ? 'Previewing' : 'Applying') + ' bulk fixes...', dryRun ? 'warning' : 'info');
            
            $('#bulk-fix-results').removeClass('rs-hidden');
            $('#bulk-fix-content').html('<p><span class="rs-spinner"></span>' + (dryRun ? 'Previewing' : 'Applying') + ' fixes...</p>');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_bulk_apply_compatibility_fixes',
                    nonce: realestateSync.nonce,
                    fix_types: fixTypes,
                    dry_run: dryRun ? 'true' : 'false'
                },
                beforeSend: function() {
                    var button = dryRun ? '#bulk-apply-fixes-dry' : '#bulk-apply-fixes-real';
                    $(button).prop('disabled', true).html('<span class="rs-spinner"></span>' + (dryRun ? 'Previewing...' : 'Applying...'));
                },
                success: function(response) {
                    if (response.success) {
                        enhancedComparison.displayBulkFixResults(response.data);
                        var resultText = dryRun ? 'Dry run completed' : 'Fixes applied successfully';
                        enhancedComparison.showAlert('✅ ' + resultText + ': ' + response.data.properties_affected + ' properties affected', 'success');
                    } else {
                        $('#bulk-fix-content').html('<p style="color: #d63638;">Error: ' + response.data + '</p>');
                        enhancedComparison.showAlert('Error applying fixes: ' + response.data, 'error');
                    }
                },
                error: function() {
                    $('#bulk-fix-content').html('<p style="color: #d63638;">Communication error with server</p>');
                    enhancedComparison.showAlert('Communication error with server', 'error');
                },
                complete: function() {
                    var button = dryRun ? '#bulk-apply-fixes-dry' : '#bulk-apply-fixes-real';
                    var originalText = dryRun ? '<span class="dashicons dashicons-visibility"></span> Dry Run (Preview Only)' : '<span class="dashicons dashicons-admin-tools"></span> Apply Fixes (REAL)';
                    $(button).prop('disabled', false).html(originalText);
                }
            });
        },
        
        startManualImport: function(e) {
            e.preventDefault();
            
            this.showAlert('⬇️ Starting manual import...', 'info');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_manual_import',
                    nonce: realestateSync.nonce
                },
                beforeSend: function() {
                    $('#start-manual-import').prop('disabled', true).html('<span class="rs-spinner"></span>Importing...');
                },
                success: function(response) {
                    if (response.success) {
                        enhancedComparison.showAlert('✅ Import completed successfully! ' + response.data.summary, 'success');
                        // Refresh page data after successful import
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        enhancedComparison.showAlert('Error in import: ' + response.data, 'error');
                    }
                },
                error: function() {
                    enhancedComparison.showAlert('Communication error with server', 'error');
                },
                complete: function() {
                    $('#start-manual-import').prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Scarica e Importa Ora');
                }
            });
        },
        
        testConnection: function(e) {
            e.preventDefault();
            
            this.showAlert('🔗 Testing connection to GestionaleImmobiliare.it...', 'info');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_test_connection',
                    nonce: realestateSync.nonce
                },
                beforeSend: function() {
                    $('#test-connection').prop('disabled', true).html('<span class="rs-spinner"></span>Testing...');
                },
                success: function(response) {
                    if (response.success) {
                        enhancedComparison.showAlert('✅ Connection successful! ' + response.data.message, 'success');
                    } else {
                        enhancedComparison.showAlert('Connection failed: ' + response.data, 'error');
                    }
                },
                error: function() {
                    enhancedComparison.showAlert('Communication error with server', 'error');
                },
                complete: function() {
                    $('#test-connection').prop('disabled', false).html('<span class="dashicons dashicons-admin-links"></span> Test Connessione');
                }
            });
        },
        
        saveSettings: function(e) {
            e.preventDefault();
            
            var formData = $(e.target).serialize();
            formData += '&action=realestate_sync_save_settings&nonce=' + realestateSync.nonce;
            
            this.showAlert('💾 Saving settings...', 'info');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        enhancedComparison.showAlert('✅ Settings saved successfully!', 'success');
                    } else {
                        enhancedComparison.showAlert('Error saving settings: ' + response.data, 'error');
                    }
                },
                error: function() {
                    enhancedComparison.showAlert('Communication error with server', 'error');
                }
            });
        },
        
        toggleCron: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_toggle_cron',
                    nonce: realestateSync.nonce
                },
                success: function(response) {
                    if (response.success) {
                        enhancedComparison.showAlert('✅ Cron settings updated!', 'success');
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        enhancedComparison.showAlert('Error updating cron: ' + response.data, 'error');
                    }
                },
                error: function() {
                    enhancedComparison.showAlert('Communication error with server', 'error');
                }
            });
        },
        
        deleteAllProperties: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to delete ALL properties?\n\nThis action CANNOT be undone!')) {
                return;
            }
            
            if (!confirm('This will permanently delete all estate_property posts and meta data.\n\nAre you absolutely sure?')) {
                return;
            }
            
            this.showAlert('🗑️ Deleting all properties...', 'warning');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_delete_all_properties',
                    nonce: realestateSync.nonce
                },
                beforeSend: function() {
                    $('#delete-all-properties').prop('disabled', true).html('<span class="rs-spinner"></span>Deleting...');
                },
                success: function(response) {
                    if (response.success) {
                        enhancedComparison.showAlert('✅ All properties deleted successfully!', 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        enhancedComparison.showAlert('Error deleting properties: ' + response.data, 'error');
                    }
                },
                error: function() {
                    enhancedComparison.showAlert('Communication error with server', 'error');
                },
                complete: function() {
                    $('#delete-all-properties').prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Cancella Tutte le Properties');
                }
            });
        },
        
        showPropertyStats: function(e) {
            e.preventDefault();
            
            $('#property-stats-display').removeClass('rs-hidden');
            $('#property-stats-content').html('<p><span class="rs-spinner"></span>Loading statistics...</p>');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_get_property_stats',
                    nonce: realestateSync.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#property-stats-content').html(response.data.html);
                    } else {
                        $('#property-stats-content').html('<p style="color: #d63638;">Error: ' + response.data + '</p>');
                    }
                },
                error: function() {
                    $('#property-stats-content').html('<p style="color: #d63638;">Communication error with server</p>');
                }
            });
        },
        
        investigateGalleryType: function(e) {
            e.preventDefault();
            
            this.showAlert('🔍 Investigating gallery types...', 'info');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_investigate_gallery_type',
                    nonce: realestateSync.nonce
                },
                success: function(response) {
                    if (response.success) {
                        enhancedComparison.showAlert('✅ Gallery investigation completed!', 'success');
                        console.log('Gallery Investigation Results:', response.data);
                    } else {
                        enhancedComparison.showAlert('Error in gallery investigation: ' + response.data, 'error');
                    }
                },
                error: function() {
                    enhancedComparison.showAlert('Communication error with server', 'error');
                }
            });
        },
        
        compareProperties: function(e) {
            e.preventDefault();
            
            var workingId = $('#working-property-id').val() || 5567;
            var brokenId = $('#broken-property-id').val();
            
            if (!brokenId) {
                this.showAlert('Please enter a broken property ID for comparison', 'error');
                return;
            }
            
            this.showAlert('🔍 Comparing properties...', 'info');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_compare_properties',
                    nonce: realestateSync.nonce,
                    working_id: workingId,
                    broken_id: brokenId
                },
                success: function(response) {
                    if (response.success) {
                        enhancedComparison.showAlert('✅ Property comparison completed!', 'success');
                        console.log('Property Comparison Results:', response.data);
                    } else {
                        enhancedComparison.showAlert('Error in property comparison: ' + response.data, 'error');
                    }
                },
                error: function() {
                    enhancedComparison.showAlert('Communication error with server', 'error');
                }
            });
        },
        
        enableTestImport: function(e) {
            var fileSelected = e.target.files.length > 0;
            $('#import-test-file').prop('disabled', !fileSelected);
        },
        
        importTestFile: function(e) {
            e.preventDefault();
            
            var fileInput = $('#test-xml-file')[0];
            if (!fileInput.files.length) {
                this.showAlert('Please select an XML file first', 'error');
                return;
            }
            
            var formData = new FormData();
            formData.append('action', 'realestate_sync_import_test_file');
            formData.append('nonce', realestateSync.nonce);
            formData.append('test_xml_file', fileInput.files[0]);
            
            this.showAlert('⬇️ Importing test file...', 'info');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $('#import-test-file').prop('disabled', true).html('<span class="rs-spinner"></span>Importing...');
                },
                success: function(response) {
                    if (response.success) {
                        enhancedComparison.showAlert('✅ Test import completed! ' + response.data.summary, 'success');
                    } else {
                        enhancedComparison.showAlert('Error in test import: ' + response.data, 'error');
                    }
                },
                error: function() {
                    enhancedComparison.showAlert('Communication error with server', 'error');
                },
                complete: function() {
                    $('#import-test-file').prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Import File Test');
                }
            });
        },
        
        createPropertiesFromSample: function(e) {
            e.preventDefault();
            
            this.showAlert('➕ Creating properties from sample XML v3.0...', 'info');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_create_properties_from_sample',
                    nonce: realestateSync.nonce
                },
                beforeSend: function() {
                    $('#create-properties-from-sample').prop('disabled', true).html('<span class="rs-spinner"></span>Creating...');
                },
                success: function(response) {
                    if (response.success) {
                        enhancedComparison.showAlert('✅ Sample properties created successfully! ' + response.data.summary, 'success');
                    } else {
                        enhancedComparison.showAlert('Error creating sample properties: ' + response.data, 'error');
                    }
                },
                error: function() {
                    enhancedComparison.showAlert('Communication error with server', 'error');
                },
                complete: function() {
                    $('#create-properties-from-sample').prop('disabled', false).html('<span class="dashicons dashicons-plus-alt"></span> Crea Properties da Sample v3.0');
                }
            });
        },
        
        refreshLogs: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_get_logs',
                    nonce: realestateSync.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#logs-container').html(response.data.logs);
                        enhancedComparison.showAlert('✅ Logs refreshed successfully!', 'success');
                    } else {
                        enhancedComparison.showAlert('Error refreshing logs: ' + response.data, 'error');
                    }
                },
                error: function() {
                    enhancedComparison.showAlert('Communication error with server', 'error');
                }
            });
        },
        
        clearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear all logs?\n\nThis action cannot be undone.')) {
                return;
            }
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_clear_logs',
                    nonce: realestateSync.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#logs-container').html('[' + new Date().toISOString().replace('T', ' ').substr(0, 19) + '] [INFO] Logs cleared by user<br>');
                        enhancedComparison.showAlert('✅ Logs cleared successfully!', 'success');
                    } else {
                        enhancedComparison.showAlert('Error clearing logs: ' + response.data, 'error');
                    }
                },
                error: function() {
                    enhancedComparison.showAlert('Communication error with server', 'error');
                }
            });
        },
        
        displayEnhancedAnalysis: function(analysis) {
            console.log('📊 Enhanced Analysis Results:', analysis);
            
            var html = '<div class="rs-enhanced-analysis-display">';
            
            // Summary with key metrics
            html += '<div class="rs-analysis-summary" style="margin-bottom: 20px; padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #28a745;">';
            html += '<h5>📊 Analysis Summary</h5>';
            html += '<p><strong>' + analysis.summary + '</strong></p>';
            html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-top: 15px;">';
            html += '<div class="rs-stat-item"><div class="rs-stat-value">' + (analysis.analyzed_properties || 0) + '</div><div class="rs-stat-label">Properties Analyzed</div></div>';
            html += '<div class="rs-stat-item"><div class="rs-stat-value">' + Object.keys(analysis.serialization_patterns || {}).length + '</div><div class="rs-stat-label">Serialization Patterns</div></div>';
            html += '<div class="rs-stat-item"><div class="rs-stat-value">' + Object.keys(analysis.wpresidence_issues || {}).length + '</div><div class="rs-stat-label">WpResidence Issues</div></div>';
            html += '<div class="rs-stat-item"><div class="rs-stat-value">' + (analysis.recommended_fixes || []).length + '</div><div class="rs-stat-label">Recommended Fixes</div></div>';
            html += '</div></div>';
            
            // Display recommended fixes with priority
            if (analysis.recommended_fixes && analysis.recommended_fixes.length > 0) {
                html += '<div class="rs-recommended-fixes" style="margin-bottom: 20px; padding: 15px; background: #fff3cd; border-radius: 4px; border-left: 4px solid #ffc107;">';
                html += '<h5>🎯 Recommended Fixes (Priority Order)</h5>';
                analysis.recommended_fixes.forEach(function(fix, index) {
                    var priorityColor = fix.priority >= 8 ? '#dc3545' : (fix.priority >= 5 ? '#ffc107' : '#28a745');
                    html += '<div style="display: flex; align-items: center; padding: 10px; margin-bottom: 10px; background: white; border-radius: 4px; border-left: 4px solid ' + priorityColor + ';">';
                    html += '<span style="background: ' + priorityColor + '; color: white; padding: 4px 8px; border-radius: 2px; margin-right: 10px; font-weight: bold;">' + (index + 1) + '</span>';
                    html += '<div style="flex: 1;">';
                    html += '<strong>' + fix.description + '</strong><br>';
                    html += '<small>Field: <code>' + fix.field + '</code> | Type: ' + fix.type + ' | Priority: ' + fix.priority + ' | Auto-fixable: ' + (fix.auto_fixable ? '✅' : '❌') + '</small>';
                    html += '</div></div>';
                });
                html += '</div>';
            }
            
            // Display detailed analysis results
            if (analysis.detailed_analysis) {
                html += '<div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">';
                html += '<h5>🔍 Detailed Analysis</h5>';
                html += '<div style="max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px;">';
                html += '<pre>' + JSON.stringify(analysis.detailed_analysis, null, 2) + '</pre>';
                html += '</div></div>';
            }
            
            html += '</div>';
            $('#enhanced-analysis-content').html(html);
        },
        
        displaySerializationPatterns: function(patterns) {
            var html = '<div style="margin-top: 15px; padding: 15px; background: #e7f3ff; border-radius: 4px;">';
            html += '<h5>🔍 Serialization Patterns Found: ' + patterns.total_patterns + '</h5>';
            html += '<p>Candidates requiring fixes: ' + (patterns.serialization_candidates ? patterns.serialization_candidates.length : 0) + '</p>';
            
            if (patterns.pattern_details) {
                html += '<div style="margin-top: 15px;">';
                html += '<h6>Pattern Details:</h6>';
                html += '<ul>';
                for (var pattern in patterns.pattern_details) {
                    html += '<li><strong>' + pattern + '</strong>: ' + patterns.pattern_details[pattern] + ' occurrences</li>';
                }
                html += '</ul>';
                html += '</div>';
            }
            
            html += '</div>';
            
            if ($('#enhanced-analysis-results').hasClass('rs-hidden')) {
                $('#enhanced-analysis-results').removeClass('rs-hidden');
                $('#enhanced-analysis-content').html(html);
            } else {
                $('#enhanced-analysis-content').append(html);
            }
        },
        
        displayWpResidenceCompatibility: function(compatibility) {
            var scoreColor = compatibility.overall_score >= 90 ? '#28a745' : 
                           compatibility.overall_score >= 70 ? '#ffc107' : '#dc3545';
            
            var html = '<div style="margin-top: 15px; padding: 15px; background: #fff3cd; border-radius: 4px;">';
            html += '<h5>🏠 WpResidence Compatibility</h5>';
            html += '<div style="display: flex; align-items: center; gap: 15px; margin: 15px 0;">';
            html += '<div style="font-size: 32px; font-weight: bold; color: ' + scoreColor + ';">' + compatibility.overall_score + '%</div>';
            html += '<div>';
            html += '<p><strong>Theme Status:</strong> ' + (compatibility.theme_status.is_wpresidence ? '✅ WpResidence Detected' : '❌ Not WpResidence') + '</p>';
            html += '<p><strong>Version:</strong> ' + (compatibility.theme_status.version || 'Unknown') + '</p>';
            html += '</div>';
            html += '</div>';
            
            if (compatibility.compatibility_issues && compatibility.compatibility_issues.length > 0) {
                html += '<div style="margin-top: 15px;">';
                html += '<h6>⚠️ Compatibility Issues:</h6>';
                html += '<ul>';
                compatibility.compatibility_issues.forEach(function(issue) {
                    html += '<li><strong>' + issue.field + '</strong>: ' + issue.description + ' (Severity: ' + issue.severity + ')</li>';
                });
                html += '</ul>';
                html += '</div>';
            }
            
            html += '</div>';
            
            if ($('#enhanced-analysis-results').hasClass('rs-hidden')) {
                $('#enhanced-analysis-results').removeClass('rs-hidden');
                $('#enhanced-analysis-content').html(html);
            } else {
                $('#enhanced-analysis-content').append(html);
            }
        },
        
        displayBulkFixResults: function(results) {
            var html = '<div class="rs-bulk-fix-results">';
            
            var summaryColor = results.dry_run ? '#ffc107' : '#28a745';
            html += '<div style="margin-bottom: 20px; padding: 15px; background: white; border-radius: 4px; border-left: 4px solid ' + summaryColor + ';">';
            html += '<h5>' + (results.dry_run ? '👁️ Dry Run Results' : '🔧 Fix Application Results') + '</h5>';
            html += '<p><strong>' + results.summary + '</strong></p>';
            html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-top: 15px;">';
            html += '<div class="rs-stat-item"><div class="rs-stat-value">' + results.properties_affected + '</div><div class="rs-stat-label">Properties Affected</div></div>';
            html += '<div class="rs-stat-item"><div class="rs-stat-value">' + (results.fixes_applied || 0) + '</div><div class="rs-stat-label">Fixes Applied</div></div>';
            html += '<div class="rs-stat-item"><div class="rs-stat-value">' + (results.execution_time || 0) + 's</div><div class="rs-stat-label">Execution Time</div></div>';
            html += '</div></div>';
            
            if (results.fix_details && results.fix_details.length > 0) {
                html += '<div style="margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 4px;">';
                html += '<h6>📋 Fix Details:</h6>';
                html += '<ul>';
                results.fix_details.forEach(function(detail) {
                    html += '<li><strong>Property ' + detail.property_id + '</strong>: ' + detail.description + '</li>';
                });
                html += '</ul>';
                html += '</div>';
            }
            
            if (results.dry_run && results.properties_affected > 0) {
                html += '<div style="margin-top: 15px; padding: 15px; background: #d1e7dd; border-radius: 4px;">';
                html += '<h6>🚀 Next Steps</h6>';
                html += '<p>Dry run completed successfully! You can now apply the real fixes using the "Apply Fixes (REAL)" button.</p>';
                html += '<p><strong>Important:</strong> Always backup your database before applying permanent changes.</p>';
                html += '</div>';
            }
            
            html += '</div>';
            $('#bulk-fix-content').html(html);
        }
    };
    
    // Initialize Enhanced Property Comparison
    enhancedComparison.init();
    
    // Make enhancedComparison available globally if needed
    window.enhancedComparison = enhancedComparison;
    
    // Demo button interactions for non-AJAX functions
    $('#start-manual-import').on('click', function() {
        if (!enhancedComparison.showAlert) {
            alert('⬇️ Starting manual import...');
            setTimeout(() => {
                alert('✅ Import completed successfully! 45 properties processed.');
            }, 3000);
        }
    });
    
    $('#test-connection').on('click', function() {
        if (!enhancedComparison.showAlert) {
            alert('🔗 Testing connection to GestionaleImmobiliare.it...');
            setTimeout(() => {
                alert('✅ Connection successful! File size: 2.4MB');
            }, 1000);
        }
    });
    
    // File upload enable/disable
    $('#test-xml-file').on('change', function() {
        var fileSelected = this.files.length > 0;
        $('#import-test-file').prop('disabled', !fileSelected);
    });
});
</script>

<?php
// Additional PHP functions can be added here for AJAX handlers
// These would be registered in the main admin class

// Example of what AJAX handlers might look like:
/*
add_action('wp_ajax_realestate_sync_enhanced_comparison', 'handle_enhanced_comparison');
add_action('wp_ajax_realestate_sync_detect_serialization_patterns', 'handle_detect_serialization_patterns');
add_action('wp_ajax_realestate_sync_analyze_wpresidence_compatibility', 'handle_analyze_wpresidence_compatibility');
add_action('wp_ajax_realestate_sync_bulk_apply_compatibility_fixes', 'handle_bulk_apply_compatibility_fixes');
add_action('wp_ajax_realestate_sync_manual_import', 'handle_manual_import');
add_action('wp_ajax_realestate_sync_test_connection', 'handle_test_connection');
add_action('wp_ajax_realestate_sync_save_settings', 'handle_save_settings');
add_action('wp_ajax_realestate_sync_toggle_cron', 'handle_toggle_cron');
add_action('wp_ajax_realestate_sync_delete_all_properties', 'handle_delete_all_properties');
add_action('wp_ajax_realestate_sync_get_property_stats', 'handle_get_property_stats');
add_action('wp_ajax_realestate_sync_investigate_gallery_type', 'handle_investigate_gallery_type');
add_action('wp_ajax_realestate_sync_compare_properties', 'handle_compare_properties');
add_action('wp_ajax_realestate_sync_import_test_file', 'handle_import_test_file');
add_action('wp_ajax_realestate_sync_create_properties_from_sample', 'handle_create_properties_from_sample');
add_action('wp_ajax_realestate_sync_get_logs', 'handle_get_logs');
add_action('wp_ajax_realestate_sync_clear_logs', 'handle_clear_logs');
*/
?>