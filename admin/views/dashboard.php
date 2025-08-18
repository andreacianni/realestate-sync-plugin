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
        RealEstate Sync Dashboard - 3-TAB SYSTEM RESTORED ✅
    </h1>

    <div id="rs-alerts-container"></div>

    <!-- 3-TAB NAVIGATION -->
    <div class="nav-tab-wrapper">
        <a href="#dashboard" class="nav-tab nav-tab-active" data-tab="dashboard">
            <span class="dashicons dashicons-dashboard"></span> Dashboard
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

    <!-- TAB 2: TOOLS -->
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
                        <span class="dashicons dashicons-plus-alt"></span> Create Property Fields
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
        </div>
    </div>

    <!-- TAB 3: LOGS -->
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
        init: function() { this.bindEvents(); },
        bindEvents: function() {
            $('#start-manual-import').on('click', this.startManualImport);
            $('#rs-test-connection').on('click', this.testConnection);
            $('#rs-quick-settings').on('submit', this.saveSettings);
            $('#test-xml-file').on('change', this.onFileSelect);
            $('#process-test-file').on('click', this.processTestFile);
            $('#create-properties-from-sample').on('click', this.createPropertiesFromSampleV3);
            $('#show-property-stats').on('click', this.showPropertyStats);
            $('#cleanup-test-data').on('click', this.cleanupTestData);
            $('#cleanup-properties').on('click', this.cleanupProperties);
            $('#view-logs').on('click', this.viewLogs);
            $('#toggle-force-processing').on('click', this.toggleForceProcessing);
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
