<?php
/**
 * RealEstate Sync Plugin - Admin Dashboard 3-Tab System
 * FIXED VERSION - Complete 3-tab interface restored
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current settings
$current_settings = get_option('realestate_sync_settings', array());
$default_settings = include plugin_dir_path(__FILE__) . '../../config/default-settings.php';
$settings = wp_parse_args($current_settings, $default_settings);

// Get system status
$tracking_manager = new RealEstate_Sync_Tracking_Manager();
$import_stats = $tracking_manager->get_import_statistics();
?>

<div class="wrap realestate-sync-admin">
    <h1>
        <span class="dashicons dashicons-building" style="font-size: 28px; margin-right: 10px; color: #2271b1;"></span>
        RealEstate Sync Dashboard - 4-TAB SYSTEM WITH INFO TAB ✨
    </h1>

    <div id="rs-alerts-container"></div>

    <!-- 4-TAB NAVIGATION WITH INFO TAB -->
    <div class="nav-tab-wrapper">
        <a href="#dashboard" class="nav-tab nav-tab-active" data-tab="dashboard">
            <span class="dashicons dashicons-dashboard"></span> Dashboard
        </a>
        <a href="#info" class="nav-tab" data-tab="info">
            <span class="dashicons dashicons-info"></span> Info
        </a>
        <a href="#tools" class="nav-tab" data-tab="tools">
            <span class="dashicons dashicons-admin-tools"></span> Tools
        </a>
        <a href="#logs" class="nav-tab" data-tab="logs">
            <span class="dashicons dashicons-list-view"></span> Logs
        </a>
    </div>

    <!-- TAB 1: DASHBOARD -->
    <div id="dashboard" class="tab-content rs-tab-active">
        <div class="rs-dashboard-grid">
            
            <!-- Manual Import Section -->
            <div class="rs-card">
                <h3><span class="dashicons dashicons-download"></span> Import Manuale</h3>
                
                <div class="rs-info-box">
                    <strong>Import Immediato</strong><br>
                    Scarica e importa immediatamente i dati XML da GestionaleImmobiliare.it
                </div>
                
                <button type="button" class="rs-button-primary" id="start-manual-import">
                    <span class="dashicons dashicons-download"></span> Scarica e Importa Ora
                </button>
                
                <button type="button" class="rs-button-secondary" id="rs-test-connection">
                    <span class="dashicons dashicons-networking"></span> Test Connessione
                </button>
            </div>

            <!-- Configuration Panel -->
            <div class="rs-card">
                <h3><span class="dashicons dashicons-admin-generic"></span> Configurazione</h3>
                
                <form id="rs-quick-settings" method="post">
                    <?php wp_nonce_field('realestate_sync_nonce'); ?>
                    <table class="rs-form-table">
                        <tr>
                            <th>URL XML:</th>
                            <td>
                                <input type="url" id="xml_url" name="xml_url" class="rs-input" 
                                       value="<?php echo esc_attr($settings['xml_url']); ?>" 
                                       placeholder="https://www.gestionaleimmobiliare.it/export/xml/...">
                            </td>
                        </tr>
                        <tr>
                            <th>Username:</th>
                            <td>
                                <input type="text" id="username" name="username" class="rs-input" 
                                       value="<?php echo esc_attr($settings['username']); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th>Password:</th>
                            <td>
                                <input type="password" id="password" name="password" class="rs-input" 
                                       value="<?php echo esc_attr($settings['password']); ?>">
                            </td>
                        </tr>
                    </table>
                    
                    <div style="margin-top: 20px;">
                        <button type="submit" class="rs-button-primary">
                            <span class="dashicons dashicons-yes"></span> Salva Configurazione
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- TAB 2: INFO - CUSTOM FIELDS MANAGEMENT -->
    <div id="info" class="tab-content">
        <div class="rs-card">
            <h3><span class="dashicons dashicons-info"></span> Custom Fields Management</h3>
            
            <div class="rs-info-box">
                <h4>🔧 Property Custom Fields Status</h4>
                <p>The RealEstate Sync plugin requires 9 additional custom fields to be created manually in WpResidence.<br>
                These fields enhance property details with specialized data from GestionaleImmobiliare.it XML.</p>
            </div>
            
            <!-- Custom Fields Status Table -->
            <div class="rs-custom-fields-section">
                <h4>📋 Required Custom Fields</h4>
                
                <table class="rs-custom-fields-table">
                    <thead>
                        <tr>
                            <th>Nome Campo</th>
                            <th>Etichetta</th>
                            <th>Tipo</th>
                            <th>Descrizione</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>superficie-giardino</code></td>
                            <td>Superficie giardino (m²)</td>
                            <td><span class="rs-field-type">numeric</span></td>
                            <td>Area del giardino in metri quadrati</td>
                            <td><span class="rs-status-manual">❌ Manual</span></td>
                        </tr>
                        <tr>
                            <td><code>aree-esterne</code></td>
                            <td>Aree esterne (m²)</td>
                            <td><span class="rs-field-type">numeric</span></td>
                            <td>Superficie totale aree esterne</td>
                            <td><span class="rs-status-manual">❌ Manual</span></td>
                        </tr>
                        <tr>
                            <td><code>superficie-commerciale</code></td>
                            <td>Superficie commerciale (m²)</td>
                            <td><span class="rs-field-type">numeric</span></td>
                            <td>Metratura commerciale dell'immobile</td>
                            <td><span class="rs-status-manual">❌ Manual</span></td>
                        </tr>
                        <tr>
                            <td><code>superficie-utile</code></td>
                            <td>Superficie utile (m²)</td>
                            <td><span class="rs-field-type">numeric</span></td>
                            <td>Superficie effettivamente utilizzabile</td>
                            <td><span class="rs-status-manual">❌ Manual</span></td>
                        </tr>
                        <tr>
                            <td><code>totale-piani-edificio</code></td>
                            <td>Totale piani edificio</td>
                            <td><span class="rs-field-type">numeric</span></td>
                            <td>Numero totale di piani dell'edificio</td>
                            <td><span class="rs-status-manual">❌ Manual</span></td>
                        </tr>
                        <tr>
                            <td><code>deposito-cauzionale</code></td>
                            <td>Deposito cauzionale (€)</td>
                            <td><span class="rs-field-type">numeric</span></td>
                            <td>Importo del deposito cauzionale</td>
                            <td><span class="rs-status-manual">❌ Manual</span></td>
                        </tr>
                        <tr>
                            <td><code>distanza-mare</code></td>
                            <td>Distanza dal mare (m)</td>
                            <td><span class="rs-field-type">numeric</span></td>
                            <td>Distanza in metri dal mare</td>
                            <td><span class="rs-status-manual">❌ Manual</span></td>
                        </tr>
                        <tr>
                            <td><code>rendita-catastale</code></td>
                            <td>Rendita catastale (€)</td>
                            <td><span class="rs-field-type">numeric</span></td>
                            <td>Valore rendita catastale annuale</td>
                            <td><span class="rs-status-manual">❌ Manual</span></td>
                        </tr>
                        <tr>
                            <td><code>destinazione-catastale</code></td>
                            <td>Destinazione catastale</td>
                            <td><span class="rs-field-type">short_text</span></td>
                            <td>Classificazione catastale dell'immobile</td>
                            <td><span class="rs-status-manual">❌ Manual</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Manual Creation Instructions -->
            <div class="rs-instructions-section">
                <h4>📝 Manual Creation Guide</h4>
                
                <div class="rs-instruction-box">
                    <h5>🎯 Step-by-Step Instructions:</h5>
                    <ol class="rs-instruction-list">
                        <li><strong>Access WpResidence Admin:</strong> Go to WordPress Admin → Properties → Add Custom Details</li>
                        <li><strong>Create Each Field:</strong> Use the exact field names from the table above</li>
                        <li><strong>Field Configuration:</strong>
                            <ul>
                                <li><strong>Type:</strong> Select "numeric" for measurements and "short_text" for text</li>
                                <li><strong>Label:</strong> Use the exact labels shown in the table</li>
                                <li><strong>Slug:</strong> Use the exact field names (importante per il mapping!)</li>
                            </ul>
                        </li>
                        <li><strong>Verification:</strong> Click "Check Field Status" below after creation</li>
                    </ol>
                </div>
                
                <div class="rs-warning-box">
                    <h5>⚠️ Important Notes:</h5>
                    <ul>
                        <li><strong>Exact Names:</strong> Field names must match exactly for automatic mapping</li>
                        <li><strong>WpResidence Only:</strong> These fields can only be created through WpResidence admin</li>
                        <li><strong>One-Time Setup:</strong> Create once, used by all future imports</li>
                        <li><strong>95% Coverage:</strong> With these fields, XML mapping reaches 95%+ coverage</li>
                    </ul>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="rs-actions-section">
                <h4>🔧 Field Management Actions</h4>
                
                <div class="rs-button-group" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
                    <button type="button" class="rs-button-primary" id="check-field-status">
                        <span class="dashicons dashicons-admin-generic"></span> Check Field Status
                    </button>
                    
                    <button type="button" class="rs-button-secondary" id="view-field-mapping">
                        <span class="dashicons dashicons-networking"></span> View XML Mapping
                    </button>
                    
                    <button type="button" class="rs-button-secondary" id="test-field-population">
                        <span class="dashicons dashicons-performance"></span> Test Field Population
                    </button>
                </div>
                
                <!-- Field Status Results -->
                <div id="field-status-results" class="rs-hidden" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px; border: 1px solid #c3c4c7;">
                    <h5>Field Status Results</h5>
                    <div id="field-status-content">
                        <p>Click "Check Field Status" to verify custom fields...</p>
                    </div>
                </div>
                
                <!-- XML Mapping Display -->
                <div id="xml-mapping-display" class="rs-hidden" style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-radius: 4px; border: 1px solid #c9d6e7;">
                    <h5>XML → WordPress Field Mapping</h5>
                    <div id="xml-mapping-content">
                        <p>Click "View XML Mapping" to see the complete mapping...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 3: TOOLS -->
    <div id="tools" class="tab-content">
        <div class="rs-card">
            <h3><span class="dashicons dashicons-database-import"></span> Testing & Development</h3>
            
            <!-- Upload Test Section -->
            <div class="rs-upload-section" style="border-left: 4px solid #2271b1; padding: 20px; margin-bottom: 20px; background: #f8f9fa;">
                <h4><span class="dashicons dashicons-upload"></span> Import Test</h4>
                <p>Upload qualsiasi file XML per testare il workflow completo: Properties + Agenzie + Media</p>
                
                <div style="margin: 15px 0;">
                    <input type="file" id="test-xml-file" accept=".xml" style="margin-bottom: 10px; padding: 8px;">
                    <small style="display: block; color: #666;">Seleziona file XML (esempio: sample-con-agenzie.xml)</small>
                </div>
                
                <div style="margin: 15px 0;">
                    <button type="button" class="rs-button-primary" id="process-test-file" disabled>
                        <span class="dashicons dashicons-admin-generic"></span> Processa File XML
                    </button>
                </div>
                
                <!-- Test Log Output -->
                <div id="test-log-output" class="rs-hidden" style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; max-height: 300px; overflow-y: auto;">
                    <h5>Log Processo:</h5>
                    <pre id="test-log-content" style="margin: 0; font-family: 'Courier New', monospace; font-size: 12px; white-space: pre-wrap;">Avvio processo...</pre>
                </div>
            </div>
            
            <!-- 🚀 FORCE PROCESSING MODE SECTION -->
            <div class="rs-force-processing-section" style="border-left: 4px solid #dc3545; padding: 15px; margin-top: 20px; background: #fef2f2;">
                <h4><span class="dashicons dashicons-admin-generic"></span> 🚀 Force Processing Mode (DEBUG)</h4>
                <p><strong>Debug Mode:</strong> Bypassa change detection per testare conversion v3.0 + media/agency extraction</p>
                
                <div style="margin: 15px 0;">
                    <?php $force_enabled = get_option('realestate_sync_force_processing', false); ?>
                    <button type="button" class="<?php echo $force_enabled ? 'rs-button-danger' : 'rs-button-primary'; ?>" id="toggle-force-processing">
                        <span class="dashicons dashicons-<?php echo $force_enabled ? 'dismiss' : 'yes'; ?>"></span>
                        <?php echo $force_enabled ? 'DISABILITA Force Processing' : 'ABILITA Force Processing'; ?>
                    </button>
                    
                    <div id="force-processing-status" style="margin-top: 10px; padding: 10px; border-radius: 4px; <?php echo $force_enabled ? 'background: #fecaca; color: #7f1d1d;' : 'background: #e5e7eb; color: #374151;'; ?>">
                        <strong>Status:</strong> Force Processing <?php echo $force_enabled ? 'ENABLED 🚀' : 'DISABLED'; ?>
                        <?php if ($force_enabled): ?>
                            <br><small>⚠️ Change detection bypassed - tutti gli XML properties verranno processati</small>
                        <?php else: ?>
                            <br><small>Normal mode - properties skipped if no changes detected</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Database Tools Section -->
            <div class="rs-testing-section" style="border-left: 4px solid #f0ad4e; padding: 15px; margin-top: 20px;">
                <h4><span class="dashicons dashicons-admin-tools"></span> Database Tools</h4>
                
                <div class="rs-button-group" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
                    <button type="button" class="rs-button-primary" id="create-property-fields" style="background: #6366f1; border-color: #6366f1;">
                        <span class="dashicons dashicons-plus-alt"></span> 🔥 Create Property Fields (NEW)
                    </button>
                    
                    <button type="button" class="rs-button-primary" id="create-properties-from-sample" style="background: #00a32a; border-color: #00a32a;">
                        <span class="dashicons dashicons-plus-alt"></span> Crea Properties da Sample v3.0
                    </button>
                    
                    <button type="button" class="rs-button-secondary" id="show-property-stats">
                        <span class="dashicons dashicons-chart-bar"></span> Mostra Statistiche Properties
                    </button>
                    
                    <button type="button" class="rs-button-warning" id="cleanup-test-data" style="background: #ffc107; border-color: #ffc107; color: #000;">
                        <span class="dashicons dashicons-trash"></span> Cleanup Test Data
                    </button>
                    
                    <button type="button" class="rs-button-danger" id="cleanup-properties" style="background: #dc3545; border-color: #dc3545;">
                        <span class="dashicons dashicons-dismiss"></span> Cleanup ALL Database
                    </button>
                </div>

                <!-- Property Stats Display -->
                <div id="property-stats-display" class="rs-hidden" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                    <h4>Statistiche Properties Database</h4>
                    <div id="property-stats-content">
                        <p>Caricamento statistiche...</p>
                    </div>
                </div>
            </div>
            
            <!-- 🚀 PROFESSIONAL ACTIVATION TOOLS SECTION -->
            <div class="rs-activation-section" style="border-left: 4px solid #6366f1; padding: 15px; margin-top: 20px; background: #f8faff;">
                <h4><span class="dashicons dashicons-admin-tools"></span> 🚀 Professional Activation Tools</h4>
                <p><strong>wp_loaded System:</strong> Breakthrough activation system with perfect WordPress timing</p>
                
                <div class="rs-button-group" style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
                    <button type="button" class="rs-button-primary" id="check-activation-status" style="background: #6366f1; border-color: #6366f1;">
                        <span class="dashicons dashicons-admin-generic"></span> Check Activation Status
                    </button>
                    
                    <button type="button" class="rs-button-secondary" id="view-activation-info">
                        <span class="dashicons dashicons-info"></span> View System Info
                    </button>
                    
                    <button type="button" class="rs-button-secondary" id="test-activation-workflow">
                        <span class="dashicons dashicons-performance"></span> Test Workflow
                    </button>
                </div>

                <!-- Activation Status Display -->
                <div id="activation-status-display" class="rs-hidden" style="margin-top: 20px; padding: 15px; background: #ffffff; border-radius: 4px; border: 1px solid #e1e5e9;">
                    <h5>Activation System Status</h5>
                    <div id="activation-status-content">
                        <p>Loading activation status...</p>
                    </div>
                </div>
                
                <!-- Activation Info Display -->
                <div id="activation-info-display" class="rs-hidden" style="margin-top: 20px; padding: 15px; background: #ffffff; border-radius: 4px; border: 1px solid #e1e5e9;">
                    <h5>Professional Activation System v2.0</h5>
                    <div id="activation-info-content">
                        <div style="background: #f0f6fc; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                            <h6>💎 Breakthrough Implementation:</h6>
                            <ul style="margin: 10px 0; padding-left: 20px;">
                                <li><strong>Problem Solved:</strong> WordPress timing issues with register_activation_hook</li>
                                <li><strong>Solution:</strong> Two-phase activation via wp_loaded hook</li>
                                <li><strong>Result:</strong> Perfect timing, no manual intervention required</li>
                            </ul>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; margin-bottom: 15px;">
                            <h6>🔄 Activation Workflow:</h6>
                            <ol style="margin: 10px 0; padding-left: 20px;">
                                <li><strong>Phase 1:</strong> register_activation_hook sets activation flag</li>
                                <li><strong>Phase 2:</strong> wp_loaded completes activation when WordPress ready</li>
                                <li><strong>One-time:</strong> Flag cleanup prevents re-execution</li>
                            </ol>
                        </div>
                        
                        <div style="background: #fff3cd; padding: 15px; border-radius: 4px;">
                            <h6>✨ Benefits:</h6>
                            <ul style="margin: 10px 0; padding-left: 20px;">
                                <li>Perfect WordPress timing - no early execution issues</li>
                                <li>One-time execution - no infinite loops</li>
                                <li>Professional user experience - zero manual intervention</li>
                                <li>Resilient operation - handles edge cases gracefully</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 4: LOGS -->
    <div id="logs" class="tab-content">
        <div class="rs-card">
            <h3><span class="dashicons dashicons-list-view"></span> Log & Monitoraggio</h3>
            
            <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                <button type="button" class="rs-button-secondary" id="view-logs">
                    <span class="dashicons dashicons-media-text"></span> Visualizza Log
                </button>
                <button type="button" class="rs-button-secondary" id="download-logs">
                    <span class="dashicons dashicons-download"></span> Scarica Log
                </button>
                <button type="button" class="rs-button-secondary" id="clear-logs">
                    <span class="dashicons dashicons-trash"></span> Cancella Log
                </button>
                <button type="button" class="rs-button-secondary" id="system-check">
                    <span class="dashicons dashicons-admin-tools"></span> Verifica Sistema
                </button>
            </div>

            <!-- Log Viewer -->
            <div id="log-viewer" class="rs-hidden">
                <div style="background: #f9f9f9; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; max-height: 400px; overflow-y: auto;">
                    <pre id="log-content" style="margin: 0; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.4;">Caricamento log...</pre>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.nav-tab-wrapper { border-bottom: 1px solid #c3c4c7; margin-bottom: 20px; }
.nav-tab { border: 1px solid #c3c4c7; border-bottom: none; background: #f0f0f1; color: #50575e; text-decoration: none; padding: 10px 15px; margin-right: 2px; display: inline-block; border-radius: 3px 3px 0 0; position: relative; top: 1px; }
.nav-tab:hover { background: #fff; color: #2271b1; }
.nav-tab-active { background: #fff !important; border-bottom: 1px solid #fff !important; color: #2271b1 !important; z-index: 1; }
.tab-content { display: none; }
.tab-content.rs-tab-active { display: block; }
.rs-dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px; }
.rs-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px; box-shadow: 0 1px 1px rgba(0,0,0,0.04); }
.rs-button-primary { background: #2271b1; border-color: #2271b1; color: white; padding: 8px 16px; border-radius: 3px; cursor: pointer; border: 1px solid; text-decoration: none; display: inline-block; margin-right: 10px; }
.rs-button-primary:hover { background: #135e96; border-color: #135e96; }
.rs-button-secondary { background: #f0f0f1; border-color: #c3c4c7; color: #2c3338; padding: 8px 16px; border-radius: 3px; cursor: pointer; border: 1px solid; text-decoration: none; display: inline-block; margin-right: 10px; }
.rs-button-secondary:hover { background: #e9e9ea; border-color: #8c8f94; }
.rs-button-danger { background: #dc3545; border-color: #dc3545; color: white; padding: 8px 16px; border-radius: 3px; cursor: pointer; border: 1px solid; text-decoration: none; display: inline-block; margin-right: 10px; }
.rs-button-danger:hover { background: #c82333; border-color: #bd2130; }
.rs-alert { padding: 12px 15px; margin: 15px 0; border-radius: 4px; border-left: 4px solid; }
.rs-alert-success { background: #d1e7dd; border-color: #00a32a; color: #0f5132; }
.rs-alert-error { background: #f8d7da; border-color: #d63638; color: #842029; }
.rs-alert-warning { background: #fff3cd; border-color: #f0ad4e; color: #997404; }
.rs-alert-info { background: #cff4fc; border-color: #2271b1; color: #055160; }
.rs-form-table { width: 100%; }
.rs-form-table th { text-align: left; padding: 10px 0; width: 150px; }
.rs-form-table td { padding: 10px 0; }
.rs-input { width: 100%; padding: 8px 12px; border: 1px solid #8c8f94; border-radius: 4px; }
.rs-hidden { display: none !important; }
.rs-spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid #f3f3f3; border-top: 2px solid #2271b1; border-radius: 50%; animation: rs-spin 1s linear infinite; margin-right: 5px; }
@keyframes rs-spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
.rs-info-box { background: #f0f6fc; border: 1px solid #c9d6e7; border-radius: 4px; padding: 15px; margin: 15px 0; }
.rs-custom-fields-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
.rs-custom-fields-table th, .rs-custom-fields-table td { padding: 12px; text-align: left; border-bottom: 1px solid #c3c4c7; }
.rs-custom-fields-table th { background: #f0f0f1; font-weight: bold; }
.rs-custom-fields-table code { background: #f1f1f1; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
.rs-field-type { background: #2271b1; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
.rs-status-manual { color: #d63638; font-weight: bold; }
.rs-status-created { color: #00a32a; font-weight: bold; }
.rs-instruction-box { background: #f8f9fa; border: 1px solid #e1e5e9; border-radius: 4px; padding: 20px; margin: 15px 0; }
.rs-instruction-list { margin: 10px 0; padding-left: 25px; }
.rs-instruction-list li { margin-bottom: 8px; }
.rs-warning-box { background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; padding: 15px; margin: 15px 0; }
.rs-warning-box h5 { margin-top: 0; color: #997404; }
.rs-actions-section { margin-top: 30px; }
.rs-custom-fields-section h4, .rs-instructions-section h4, .rs-actions-section h4 { color: #2271b1; margin-top: 25px; margin-bottom: 15px; }
</style>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var targetTab = $(this).data('tab');
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-content').removeClass('rs-tab-active');
        $(this).addClass('nav-tab-active');
        $('#' + targetTab).addClass('rs-tab-active');
    });
    
    var dashboard = {
        init: function() { 
            this.bindEvents(); 
            
            // 🔄 AUTO-LOAD INFO TAB FEATURES
            this.autoLoadInfoTabFeatures();
        },
        
        // 🔄 AUTO-LOAD FEATURES FOR INFO TAB
        autoLoadInfoTabFeatures: function() {
            // Auto-check field status when Info tab is active or page loads
            var currentTab = $('.nav-tab-active').data('tab');
            if (currentTab === 'info') {
                this.autoCheckFieldStatus();
                this.autoLoadXMLMapping();
            }
            
            // Auto-load when switching to Info tab
            $('.nav-tab[data-tab="info"]').on('click', function() {
                setTimeout(function() {
                    dashboard.autoCheckFieldStatus();
                    dashboard.autoLoadXMLMapping();
                }, 100);
            });
        },
        
        // 🔄 AUTO-CHECK FIELD STATUS (Silent)
        autoCheckFieldStatus: function() {
            // Silent check - no loading indicators, just update the table
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { 
                    action: 'realestate_sync_check_field_status', 
                    nonce: realestateSync.nonce 
                },
                success: function(response) {
                    if (response.success) {
                        dashboard.updateFieldStatusTable(response.data);
                    }
                },
                error: function() {
                    // Silent fail - don't show errors for auto-check
                }
            });
        },
        
        // 🔄 AUTO-LOAD XML MAPPING (Always Expanded)
        autoLoadXMLMapping: function() {
            // Auto-load and expand XML mapping
            $('#xml-mapping-display').removeClass('rs-hidden');
            $('#xml-mapping-content').html('<p><span class="rs-spinner"></span>Loading XML mapping...</p>');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { 
                    action: 'realestate_sync_get_field_mapping', 
                    nonce: realestateSync.nonce 
                },
                success: function(response) {
                    if (response.success) {
                        dashboard.displayXMLMapping(response.data);
                    } else {
                        $('#xml-mapping-content').html('<p style="color: #d63638;">Error loading mapping: ' + response.data + '</p>');
                    }
                },
                error: function() {
                    $('#xml-mapping-content').html('<p style="color: #d63638;">Communication error loading mapping</p>');
                }
            });
        },
        
        // 🔄 UPDATE FIELD STATUS TABLE
        updateFieldStatusTable: function(data) {
            if (!data.field_details || data.field_details.length === 0) return;
            
            // Update each field status in the main table
            data.field_details.forEach(function(field) {
                var $row = $('.rs-custom-fields-table tbody tr').filter(function() {
                    return $(this).find('code').text() === field.name;
                });
                
                if ($row.length > 0) {
                    var $statusCell = $row.find('td:last-child');
                    if (field.exists) {
                        $statusCell.html('<span class="rs-status-created">✅ Created</span>');
                    } else {
                        $statusCell.html('<span class="rs-status-manual">❌ Manual</span>');
                    }
                }
            });
            
            // Update summary if available
            if (data.created_count !== undefined) {
                var summaryMessage = data.created_count + ' / 9 custom fields created';
                if (data.created_count === 9) {
                    summaryMessage += ' ✅ Complete!';
                }
                
                // Update info box if present
                $('.rs-info-box p').html(
                    'The RealEstate Sync plugin requires 9 additional custom fields to be created manually in WpResidence.<br>' +
                    'These fields enhance property details with specialized data from GestionaleImmobiliare.it XML.<br>' +
                    '<strong>Status: ' + summaryMessage + '</strong>'
                );
            }
        },
        
        // 🔄 DISPLAY XML MAPPING (Enhanced)
        displayXMLMapping: function(mapping) {
            var html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
            
            // Property Core Fields
            html += '<div><h6 style="color: #2271b1; margin-bottom: 10px;">🏠 Property Core Fields</h6>';
            html += '<table style="width: 100%; border-collapse: collapse; font-size: 12px;">';
            html += '<thead><tr style="background: #f0f0f1;"><th style="padding: 6px; border: 1px solid #ddd;">XML Field</th><th style="padding: 6px; border: 1px solid #ddd;">WordPress Field</th></tr></thead><tbody>';
            
            if (mapping.property_core) {
                Object.keys(mapping.property_core).forEach(function(xmlField) {
                    html += '<tr><td style="padding: 6px; border: 1px solid #ddd;"><code>' + xmlField + '</code></td>';
                    html += '<td style="padding: 6px; border: 1px solid #ddd;">' + mapping.property_core[xmlField] + '</td></tr>';
                });
            }
            html += '</tbody></table></div>';
            
            // Custom Fields
            html += '<div><h6 style="color: #d63638; margin-bottom: 10px;">🔧 Custom Fields (Manual Creation)</h6>';
            html += '<table style="width: 100%; border-collapse: collapse; font-size: 12px;">';
            html += '<thead><tr style="background: #f0f0f1;"><th style="padding: 6px; border: 1px solid #ddd;">XML Field</th><th style="padding: 6px; border: 1px solid #ddd;">Custom Field</th></tr></thead><tbody>';
            
            if (mapping.custom_fields) {
                Object.keys(mapping.custom_fields).forEach(function(xmlField) {
                    html += '<tr><td style="padding: 6px; border: 1px solid #ddd;"><code>' + xmlField + '</code></td>';
                    html += '<td style="padding: 6px; border: 1px solid #ddd;"><strong>' + mapping.custom_fields[xmlField] + '</strong></td></tr>';
                });
            }
            html += '</tbody></table></div>';
            html += '</div>';
            
            // Coverage Summary
            if (mapping.coverage_summary) {
                html += '<div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">';
                html += '<h6 style="margin: 0 0 10px 0;">Mapping Coverage Summary</h6>';
                html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">';
                
                html += '<div style="text-align: center;"><strong>Total XML Fields</strong><br><span style="font-size: 18px; color: #2271b1;">' + (mapping.coverage_summary.total_xml_fields || 'N/A') + '</span></div>';
                html += '<div style="text-align: center;"><strong>Mapped Fields</strong><br><span style="font-size: 18px; color: #00a32a;">' + (mapping.coverage_summary.mapped_fields || 'N/A') + '</span></div>';
                html += '<div style="text-align: center;"><strong>Coverage</strong><br><span style="font-size: 18px; color: #f59e0b;">' + (mapping.coverage_summary.coverage_percentage || 'N/A') + '%</span></div>';
                
                html += '</div></div>';
            }
            
            $('#xml-mapping-content').html(html);
        },
        bindEvents: function() {
            $('#start-manual-import').on('click', this.startManualImport);
            $('#rs-test-connection').on('click', this.testConnection);
            $('#rs-quick-settings').on('submit', this.saveSettings);
            $('#test-xml-file').on('change', this.onFileSelect);
            $('#process-test-file').on('click', this.processTestFile);
            $('#create-property-fields').on('click', this.createPropertyFields);
            $('#create-properties-from-sample').on('click', this.createPropertiesFromSampleV3);
            $('#show-property-stats').on('click', this.showPropertyStats);
            $('#cleanup-test-data').on('click', this.cleanupTestData);
            $('#cleanup-properties').on('click', this.cleanupProperties);
            $('#view-logs').on('click', this.viewLogs);
            $('#toggle-force-processing').on('click', this.toggleForceProcessing);
            
            // 🚀 PROFESSIONAL ACTIVATION TOOLS EVENTS
            $('#check-activation-status').on('click', this.checkActivationStatus);
            $('#view-activation-info').on('click', this.viewActivationInfo);
            $('#test-activation-workflow').on('click', this.testActivationWorkflow);
            
            // 📋 INFO TAB EVENTS
            $('#check-field-status').on('click', this.checkFieldStatus);
            $('#view-field-mapping').on('click', this.viewFieldMapping);
            $('#test-field-population').on('click', this.testFieldPopulation);
        },
        
        // 📋 INFO TAB METHODS
        checkFieldStatus: function(e) {
            e.preventDefault();
            
            $('#field-status-results').removeClass('rs-hidden');
            $('#field-status-content').html('<p><span class="rs-spinner"></span>Checking custom fields status...</p>');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { 
                    action: 'realestate_sync_check_field_status', 
                    nonce: realestateSync.nonce 
                },
                beforeSend: function() {
                    $('#check-field-status').prop('disabled', true).html('<span class="rs-spinner"></span>Checking...');
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;
                        
                        var html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">';
                        html += '<div style="padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #00a32a;">';
                        html += '<h5 style="margin: 0 0 5px 0; color: #00a32a;">✅ Fields Created</h5>';
                        html += '<div style="font-size: 20px; font-weight: bold;">' + (result.created_count || 0) + ' / 9</div></div>';
                        
                        html += '<div style="padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #d63638;">';
                        html += '<h5 style="margin: 0 0 5px 0; color: #d63638;">❌ Missing Fields</h5>';
                        html += '<div style="font-size: 20px; font-weight: bold;">' + (result.missing_count || 9) + ' / 9</div></div>';
                        html += '</div>';
                        
                        if (result.field_details && result.field_details.length > 0) {
                            html += '<h6>Field Status Details:</h6>';
                            html += '<table style="width: 100%; border-collapse: collapse;">';
                            html += '<thead><tr style="background: #f9f9f9;"><th style="padding: 8px; border: 1px solid #ddd;">Field Name</th><th style="padding: 8px; border: 1px solid #ddd;">Status</th><th style="padding: 8px; border: 1px solid #ddd;">Label</th></tr></thead><tbody>';
                            
                            result.field_details.forEach(function(field) {
                                var statusIcon = field.exists ? '✅' : '❌';
                                var statusColor = field.exists ? '#00a32a' : '#d63638';
                                html += '<tr><td style="padding: 8px; border: 1px solid #ddd;"><code>' + field.name + '</code></td>';
                                html += '<td style="padding: 8px; border: 1px solid #ddd; color: ' + statusColor + ';">' + statusIcon + ' ' + (field.exists ? 'Created' : 'Missing') + '</td>';
                                html += '<td style="padding: 8px; border: 1px solid #ddd;">' + (field.label || 'N/A') + '</td></tr>';
                            });
                            
                            html += '</tbody></table>';
                        }
                        
                        $('#field-status-content').html(html);
                        
                        var message = 'Field check completed. ' + (result.created_count || 0) + ' fields created, ' + (result.missing_count || 9) + ' missing.';
                        dashboard.showAlert(message, result.created_count === 9 ? 'success' : 'warning');
                        
                    } else {
                        $('#field-status-content').html('<p style="color: #d63638;">Error checking fields: ' + response.data + '</p>');
                        dashboard.showAlert('🚨 Field status check failed: ' + response.data, 'error');
                    }
                },
                error: function() { 
                    $('#field-status-content').html('<p style="color: #d63638;">Communication error</p>');
                    dashboard.showAlert('🚨 Communication error during field check', 'error'); 
                },
                complete: function() {
                    $('#check-field-status').prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> Check Field Status');
                }
            });
        },
        
        viewFieldMapping: function(e) {
            e.preventDefault();
            
            $('#xml-mapping-display').toggleClass('rs-hidden');
            
            if (!$('#xml-mapping-display').hasClass('rs-hidden')) {
                $('#xml-mapping-content').html('<p><span class="rs-spinner"></span>Loading XML mapping...</p>');
                
                $.ajax({
                    url: realestateSync.ajax_url,
                    type: 'POST',
                    data: { 
                        action: 'realestate_sync_get_field_mapping', 
                        nonce: realestateSync.nonce 
                    },
                    success: function(response) {
                        if (response.success) {
                            var mapping = response.data;
                            
                            var html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
                            
                            // Property Core Fields
                            html += '<div><h6 style="color: #2271b1; margin-bottom: 10px;">🏠 Property Core Fields</h6>';
                            html += '<table style="width: 100%; border-collapse: collapse; font-size: 12px;">';
                            html += '<thead><tr style="background: #f0f0f1;"><th style="padding: 6px; border: 1px solid #ddd;">XML Field</th><th style="padding: 6px; border: 1px solid #ddd;">WordPress Field</th></tr></thead><tbody>';
                            
                            if (mapping.property_core) {
                                Object.keys(mapping.property_core).forEach(function(xmlField) {
                                    html += '<tr><td style="padding: 6px; border: 1px solid #ddd;"><code>' + xmlField + '</code></td>';
                                    html += '<td style="padding: 6px; border: 1px solid #ddd;">' + mapping.property_core[xmlField] + '</td></tr>';
                                });
                            }
                            html += '</tbody></table></div>';
                            
                            // Custom Fields
                            html += '<div><h6 style="color: #d63638; margin-bottom: 10px;">🔧 Custom Fields (Manual Creation)</h6>';
                            html += '<table style="width: 100%; border-collapse: collapse; font-size: 12px;">';
                            html += '<thead><tr style="background: #f0f0f1;"><th style="padding: 6px; border: 1px solid #ddd;">XML Field</th><th style="padding: 6px; border: 1px solid #ddd;">Custom Field</th></tr></thead><tbody>';
                            
                            if (mapping.custom_fields) {
                                Object.keys(mapping.custom_fields).forEach(function(xmlField) {
                                    html += '<tr><td style="padding: 6px; border: 1px solid #ddd;"><code>' + xmlField + '</code></td>';
                                    html += '<td style="padding: 6px; border: 1px solid #ddd;"><strong>' + mapping.custom_fields[xmlField] + '</strong></td></tr>';
                                });
                            }
                            html += '</tbody></table></div>';
                            html += '</div>';
                            
                            $('#xml-mapping-content').html(html);
                            dashboard.showAlert('🗺️ XML mapping displayed with current coverage', 'info');
                            
                        } else {
                            $('#xml-mapping-content').html('<p style="color: #d63638;">Error loading mapping: ' + response.data + '</p>');
                            dashboard.showAlert('🚨 Failed to load XML mapping: ' + response.data, 'error');
                        }
                    },
                    error: function() {
                        $('#xml-mapping-content').html('<p style="color: #d63638;">Communication error</p>');
                        dashboard.showAlert('🚨 Communication error loading mapping', 'error');
                    }
                });
            }
        },
        
        testFieldPopulation: function(e) {
            e.preventDefault();
            
            if (!confirm('🧪 Test Custom Fields Population?\n\nThis will test the mapping of XML data to custom fields using sample data.')) return;
            
            dashboard.showAlert('🧪 Testing custom fields population...', 'info');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { 
                    action: 'realestate_sync_test_field_population', 
                    nonce: realestateSync.nonce 
                },
                beforeSend: function() {
                    $('#test-field-population').prop('disabled', true).html('<span class="rs-spinner"></span>Testing...');
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;
                        
                        var message = '🎉 Population test completed! ';
                        message += 'Fields tested: ' + (result.fields_tested || 0) + ', ';
                        message += 'Successful: ' + (result.successful_mappings || 0) + ', ';
                        message += 'Failed: ' + (result.failed_mappings || 0);
                        
                        dashboard.showAlert(message, result.failed_mappings === 0 ? 'success' : 'warning');
                        
                        console.log('🧪 Field Population Test Results:', result);
                        
                    } else {
                        dashboard.showAlert('🚨 Population test failed: ' + response.data, 'error');
                    }
                },
                error: function() { 
                    dashboard.showAlert('🚨 Communication error during population test', 'error'); 
                },
                complete: function() {
                    $('#test-field-population').prop('disabled', false).html('<span class="dashicons dashicons-performance"></span> Test Field Population');
                }
            });
        },
        createPropertyFields: function(e) {
            e.preventDefault();
            
            if (!confirm('🔥 CREATE CUSTOM FIELDS with NEW AUTOMATION METHOD?\n\nThis will create 9 Property Details fields using the AJAX mechanism discovered from cURL analysis.\n\n⚠️ SAFE TESTING: Will create test field first for validation.')) return;
            
            dashboard.showAlert('🚀 Creating Property Details with NEW automation method...', 'warning');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { 
                    action: 'realestate_sync_create_property_fields_v2', 
                    nonce: realestateSync.nonce,
                    test_mode: true  // Start with test field first
                },
                beforeSend: function() {
                    $('#create-property-fields').prop('disabled', true).html('<span class="rs-spinner"></span>🔥 Creating with NEW Method...');
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;
                        var message = result.summary_message || 'Custom fields automation completed!';
                        
                        // Enhanced success message with automation details
                        if (result.created_count > 0) {
                            message = '🎉 AUTOMATION SUCCESS: ' + result.created_count + ' custom fields created automatically!';
                            if (result.test_mode) {
                                message += ' (Test mode - validate and run again for full automation)';
                            }
                        }
                        
                        dashboard.showAlert(message, 'success');
                        
                        // Show automation details in console
                        if (result.automation_details) {
                            console.log('🔥 Custom Fields Automation Details:', result.automation_details);
                        }
                        
                        // Show next steps if test mode
                        if (result.test_mode && result.created_count > 0) {
                            setTimeout(function() {
                                dashboard.showAlert('✅ Test field created successfully! Click again to create all 9 fields.', 'info');
                            }, 3000);
                        }
                        
                    } else {
                        dashboard.showAlert('🚨 NEW METHOD ERROR: ' + response.data, 'error');
                    }
                },
                error: function() { 
                    dashboard.showAlert('🚨 Communication error with new automation method', 'error'); 
                },
                complete: function() {
                    $('#create-property-fields').prop('disabled', false).html('<span class="dashicons dashicons-plus-alt"></span> 🔥 Create Property Fields (NEW)');
                }
            });
        },
        startManualImport: function(e) {
            e.preventDefault();
            if (!confirm('Sei sicuro di voler avviare l\'import manuale?')) return;
            dashboard.showAlert('Import avviato...', 'warning');
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { action: 'realestate_sync_manual_import', nonce: realestateSync.nonce },
                success: function(response) {
                    if (response.success) {
                        dashboard.showAlert('Import completato con successo!', 'success');
                        location.reload();
                    } else {
                        dashboard.showAlert('Errore: ' + response.data, 'error');
                    }
                },
                error: function() { dashboard.showAlert('Errore di comunicazione', 'error'); }
            });
        },
        testConnection: function(e) {
            e.preventDefault();
            var url = $('#xml_url').val(), username = $('#username').val(), password = $('#password').val();
            if (!url || !username || !password) {
                dashboard.showAlert('Compila tutti i campi prima di testare', 'error');
                return;
            }
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { action: 'realestate_sync_test_connection', nonce: realestateSync.nonce, url: url, username: username, password: password },
                success: function(response) {
                    if (response.success) {
                        dashboard.showAlert('Connessione riuscita!', 'success');
                    } else {
                        dashboard.showAlert('Test fallito: ' + (response.data?.message || 'Errore sconosciuto'), 'error');
                    }
                },
                error: function() { dashboard.showAlert('Errore durante test connessione', 'error'); }
            });
        },
        saveSettings: function(e) {
            e.preventDefault();
            var formData = $('#rs-quick-settings').serialize() + '&action=realestate_sync_save_settings&nonce=' + realestateSync.nonce;
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    dashboard.showAlert(response.success ? 'Configurazione salvata!' : 'Errore salvataggio: ' + response.data, response.success ? 'success' : 'error');
                },
                error: function() { dashboard.showAlert('Errore comunicazione server', 'error'); }
            });
        },
        createPropertiesFromSampleV3: function(e) {
            e.preventDefault();
            if (!confirm('Creare properties di test con Property Mapper v3.0?')) return;
            dashboard.showAlert('Creazione properties v3.0 in corso...', 'warning');
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { action: 'realestate_sync_create_properties_from_sample', nonce: realestateSync.nonce },
                beforeSend: function() {
                    $('#create-properties-from-sample').prop('disabled', true).html('<span class="rs-spinner"></span>Creando...');
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;
                        var message = 'Properties v3.0: ' + result.created_count + ' create, ' + result.updated_count + ' aggiornate';
                        if (result.features_created > 0) message += ', ' + result.features_created + ' features create';
                        dashboard.showAlert(message, 'success');
                        if (!$('#property-stats-display').hasClass('rs-hidden')) $('#show-property-stats').click();
                    } else {
                        dashboard.showAlert('Errore: ' + response.data, 'error');
                    }
                },
                error: function() { dashboard.showAlert('Errore comunicazione', 'error'); },
                complete: function() {
                    $('#create-properties-from-sample').prop('disabled', false).html('<span class="dashicons dashicons-plus-alt"></span> Crea Properties da Sample v3.0');
                }
            });
        },
        showPropertyStats: function(e) {
            e.preventDefault();
            $('#property-stats-display').removeClass('rs-hidden');
            $('#property-stats-content').html('<p><span class="rs-spinner"></span>Caricamento...</p>');
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { action: 'realestate_sync_get_property_stats', nonce: realestateSync.nonce },
                success: function(response) {
                    if (response.success) {
                        var stats = response.data;
                        var html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
                        html += '<div style="padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #2271b1;">';
                        html += '<h5 style="margin: 0 0 5px 0;">Total Properties</h5>';
                        html += '<div style="font-size: 24px; font-weight: bold; color: #2271b1;">' + stats.total_properties + '</div></div>';
                        if (stats.by_category) {
                            html += '<div style="padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #00a32a;">';
                            html += '<h5 style="margin: 0 0 10px 0;">Per Categoria</h5>';
                            Object.keys(stats.by_category).forEach(function(category) {
                                html += '<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">';
                                html += '<span>' + category + '</span><strong>' + stats.by_category[category] + '</strong></div>';
                            });
                            html += '</div>';
                        }
                        html += '</div>';
                        $('#property-stats-content').html(html);
                    } else {
                        $('#property-stats-content').html('<p style="color: #d63638;">Errore: ' + response.data + '</p>');
                    }
                },
                error: function() { $('#property-stats-content').html('<p style="color: #d63638;">Errore comunicazione</p>'); }
            });
        },
        cleanupProperties: function(e) {
            e.preventDefault();
            var confirmation = prompt('ATTENZIONE: Cancellazione TUTTE properties.\n\nPer confermare scrivi "CANCELLA TUTTO":');
            if (confirmation !== 'CANCELLA TUTTO') { alert('Operazione annullata'); return; }
            dashboard.showAlert('Cancellazione in corso...', 'warning');
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { action: 'realestate_sync_cleanup_properties', nonce: realestateSync.nonce },
                beforeSend: function() { $('#cleanup-properties').prop('disabled', true).html('<span class="rs-spinner"></span>Cancellazione...'); },
                success: function(response) {
                    if (response.success) {
                        dashboard.showAlert('Properties cancellate: ' + response.data.deleted_count, 'success');
                        if (!$('#property-stats-display').hasClass('rs-hidden')) $('#show-property-stats').click();
                    } else {
                        dashboard.showAlert('Errore: ' + response.data, 'error');
                    }
                },
                error: function() { dashboard.showAlert('Errore comunicazione', 'error'); },
                complete: function() { $('#cleanup-properties').prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Cleanup Database'); }
            });
        },
        viewLogs: function(e) {
            e.preventDefault();
            $('#log-viewer').toggleClass('rs-hidden');
            if (!$('#log-viewer').hasClass('rs-hidden')) {
                $('#log-content').text('Caricamento log...');
                $.ajax({
                    url: realestateSync.ajax_url,
                    type: 'POST',
                    data: { action: 'realestate_sync_get_logs', nonce: realestateSync.nonce },
                    success: function(response) {
                        $('#log-content').text(response.success ? (response.data.logs || 'Nessun log') : 'Errore: ' + response.data);
                    },
                    error: function() { $('#log-content').text('Errore comunicazione'); }
                    });
                    }
                    },
        toggleForceProcessing: function(e) {
            e.preventDefault();
            
            var isCurrentlyEnabled = $('#toggle-force-processing').hasClass('rs-button-danger');
            var confirmMessage = isCurrentlyEnabled ? 
                'Disabilitare Force Processing Mode?\n\nTornerà alla normale change detection.' :
                'Abilitare Force Processing Mode?\n\nBypasserà change detection per testare media/agency conversion.';
            
            if (!confirm(confirmMessage)) return;
            
            dashboard.showAlert('Aggiornamento Force Processing Mode...', 'warning');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { 
                    action: 'realestate_sync_toggle_force_processing', 
                    nonce: realestateSync.nonce 
                },
                beforeSend: function() {
                    $('#toggle-force-processing').prop('disabled', true).html('<span class="rs-spinner"></span>Aggiornando...');
                },
                success: function(response) {
                    if (response.success) {
                        dashboard.showAlert(response.data.message, 'success');
                        // Reload page to update UI status
                        setTimeout(function() { location.reload(); }, 1500);
                    } else {
                        dashboard.showAlert('Errore toggle: ' + response.data, 'error');
                    }
                },
                error: function() { 
                    dashboard.showAlert('Errore comunicazione toggle', 'error'); 
                },
                complete: function() {
                    $('#toggle-force-processing').prop('disabled', false);
                }
            });
        },
        onFileSelect: function(e) {
            var file = e.target.files[0];
            if (file && file.name.endsWith('.xml')) {
                $('#process-test-file').prop('disabled', false);
                dashboard.showAlert('File XML selezionato: ' + file.name, 'info');
            } else {
                $('#process-test-file').prop('disabled', true);
                if (file) dashboard.showAlert('Seleziona un file XML valido', 'error');
            }
        },
        processTestFile: function(e) {
            e.preventDefault();
            var fileInput = $('#test-xml-file')[0];
            if (!fileInput.files[0]) {
                dashboard.showAlert('Seleziona un file XML prima di procedere', 'error');
                return;
            }
            
            $('#test-log-output').removeClass('rs-hidden');
            dashboard.updateTestLog('Avvio processo test import...');
            
            var formData = new FormData();
            formData.append('action', 'realestate_sync_process_test_file');
            formData.append('nonce', realestateSync.nonce);
            formData.append('test_xml_file', fileInput.files[0]);
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    $('#process-test-file').prop('disabled', true).html('<span class="rs-spinner"></span>Processando...');
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;
                        dashboard.updateTestLog(result.log_output || 'Import completato');
                        var message = 'Test completato! Props: ' + (result.properties_created || 0) + ' create, ' + 
                                     (result.properties_updated || 0) + ' aggiornate. Agenzie: ' + 
                                     (result.agencies_created || 0) + ' create, ' + (result.agencies_updated || 0) + ' aggiornate';
                        if (result.media_new || result.media_existing) {
                            message += '. Media: ' + (result.media_new || 0) + ' nuove, ' + (result.media_existing || 0) + ' esistenti';
                        }
                        dashboard.showAlert(message, 'success');
                    } else {
                        dashboard.updateTestLog('ERRORE: ' + response.data);
                        dashboard.showAlert('Errore nel processo: ' + response.data, 'error');
                    }
                },
                error: function() {
                    dashboard.updateTestLog('ERRORE: Comunicazione con il server fallita');
                    dashboard.showAlert('Errore di comunicazione', 'error');
                },
                complete: function() {
                    $('#process-test-file').prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> Processa File XML');
                }
            });
        },
        cleanupTestData: function(e) {
            e.preventDefault();
            if (!confirm('Sei sicuro di voler cancellare SOLO i dati di test?\n\nQuesto cancellerà solo properties e agenzie create durante i test.')) return;
            
            dashboard.showAlert('Cancellazione test data in corso...', 'warning');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { action: 'realestate_sync_cleanup_test_data', nonce: realestateSync.nonce },
                beforeSend: function() {
                    $('#cleanup-test-data').prop('disabled', true).html('<span class="rs-spinner"></span>Cancellando...');
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;
                        var message = 'Test data cancellati: ' + (result.properties_deleted || 0) + ' properties, ' + 
                                     (result.agencies_deleted || 0) + ' agenzie';
                        dashboard.showAlert(message, 'success');
                        if (!$('#property-stats-display').hasClass('rs-hidden')) $('#show-property-stats').click();
                    } else {
                        dashboard.showAlert('Errore cancellazione: ' + response.data, 'error');
                    }
                },
                error: function() { dashboard.showAlert('Errore comunicazione', 'error'); },
                complete: function() {
                    $('#cleanup-test-data').prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Cleanup Test Data');
                }
            });
        },
        updateTestLog: function(message) {
            var timestamp = new Date().toLocaleTimeString();
            var logLine = '[' + timestamp + '] ' + message + '\n';
            $('#test-log-content').append(logLine);
            $('#test-log-output').scrollTop($('#test-log-content')[0].scrollHeight);
        },
        
        // 🚀 PROFESSIONAL ACTIVATION TOOLS METHODS
        checkActivationStatus: function(e) {
            e.preventDefault();
            
            $('#activation-status-display').removeClass('rs-hidden');
            $('#activation-status-content').html('<p><span class="rs-spinner"></span>Checking activation status...</p>');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { 
                    action: 'realestate_sync_check_activation_status', 
                    nonce: realestateSync.nonce 
                },
                beforeSend: function() {
                    $('#check-activation-status').prop('disabled', true).html('<span class="rs-spinner"></span>Checking...');
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;
                        
                        // Update status display
                        $('#activation-status-content').html(result.status_html);
                        
                        // Show message
                        dashboard.showAlert(result.message, result.message_class.replace('rs-alert-', ''));
                        
                        // Log the check
                        console.log('🚀 Activation Status:', result);
                        
                    } else {
                        $('#activation-status-content').html('<p style="color: #d63638;">Error: ' + response.data + '</p>');
                        dashboard.showAlert('🚨 Status check failed: ' + response.data, 'error');
                    }
                },
                error: function() { 
                    $('#activation-status-content').html('<p style="color: #d63638;">Communication error</p>');
                    dashboard.showAlert('🚨 Communication error during status check', 'error'); 
                },
                complete: function() {
                    $('#check-activation-status').prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> Check Activation Status');
                }
            });
        },
        
        viewActivationInfo: function(e) {
            e.preventDefault();
            $('#activation-info-display').toggleClass('rs-hidden');
            
            if (!$('#activation-info-display').hasClass('rs-hidden')) {
                dashboard.showAlert('📚 Professional Activation System info displayed', 'info');
            }
        },
        
        testActivationWorkflow: function(e) {
            e.preventDefault();
            
            if (!confirm('🧪 Test Activation Workflow?\n\nThis will simulate the professional activation process and show how the wp_loaded system works.')) return;
            
            dashboard.showAlert('🧪 Testing activation workflow...', 'info');
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: { 
                    action: 'realestate_sync_test_activation_workflow', 
                    nonce: realestateSync.nonce 
                },
                beforeSend: function() {
                    $('#test-activation-workflow').prop('disabled', true).html('<span class="rs-spinner"></span>Testing...');
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;
                        
                        // Show test results in activation status area
                        $('#activation-status-display').removeClass('rs-hidden');
                        $('#activation-status-content').html('<h5>Workflow Test Results</h5>' + result.test_html);
                        
                        dashboard.showAlert(result.message, 'success');
                        
                        // Log test results
                        console.log('🧪 Workflow Test Results:', result.test_results);
                        
                    } else {
                        dashboard.showAlert('🚨 Workflow test failed: ' + response.data, 'error');
                    }
                },
                error: function() { 
                    dashboard.showAlert('🚨 Communication error during workflow test', 'error'); 
                },
                complete: function() {
                    $('#test-activation-workflow').prop('disabled', false).html('<span class="dashicons dashicons-performance"></span> Test Workflow');
                }
            });
        },
        showAlert: function(message, type) {
            var alertHtml = '<div class="rs-alert rs-alert-' + (type || 'info') + '">' + message + '</div>';
            $('#rs-alerts-container').html(alertHtml);
            if (type === 'success' || type === 'info') {
                setTimeout(function() { $('#rs-alerts-container .rs-alert').fadeOut(); }, 5000);
            }
        }
    };
    dashboard.init();
});
</script>
