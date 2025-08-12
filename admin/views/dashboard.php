<?php
/**
* RealEstate Sync Plugin - Admin Dashboard
* 
* Single-page admin interface per gestione completa del plugin.
* Include status overview, manual import, settings e logs.
*
* @package RealEstateSync
* @subpackage Admin\Views
* @since 0.9.0
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
$last_import = null; // TODO: Implementare get_last_import_data() method
$import_stats = $tracking_manager->get_import_statistics();
$next_scheduled = wp_next_scheduled('realestate_sync_daily_import');
$cron_manager = new RealEstate_Sync_Cron_Manager();

// Check if import is running
$import_in_progress = get_transient('realestate_sync_import_in_progress');
?>

<div class="wrap realestate-sync-admin">
<h1>
<span class="dashicons dashicons-building" style="font-size: 28px; margin-right: 10px; color: #2271b1;"></span>
RealEstate Sync Dashboard
</h1>

<div id="rs-alerts-container"></div>

<!-- Dashboard Grid -->
<div class="rs-dashboard-grid">

<!-- Main Status Panel -->
<div class="rs-card">
<h3><span class="dashicons dashicons-dashboard"></span> Status Sistema</h3>

<!-- Status Overview -->
<div class="rs-status-grid">
<div class="rs-status-item">
<span class="rs-status-number"><?php echo $import_stats['total_imports'] ?? 0; ?></span>
<span class="rs-status-label">Import Totali</span>
</div>
<div class="rs-status-item">
<span class="rs-status-number"><?php echo $import_stats['properties_imported'] ?? 0; ?></span>
<span class="rs-status-label">Proprietà Importate</span>
</div>
<div class="rs-status-item">
<span class="rs-status-number"><?php echo $import_stats['success_rate'] ?? 0; ?>%</span>
<span class="rs-status-label">Tasso Successo</span>
</div>
<div class="rs-status-item">
<span class="rs-status-number"><?php echo $import_stats['avg_duration'] ?? 0; ?>min</span>
<span class="rs-status-label">Durata Media</span>
</div>
</div>

<!-- Last Import Info -->
<?php if ($last_import): ?>
    <div class="rs-last-import">
    <h4>Ultimo Import</h4>
    <div class="rs-import-stats">
    <div class="rs-stat-item">
    <div class="rs-stat-value"><?php echo date('d/m/Y H:i', strtotime($last_import['import_date'])); ?></div>
    <div class="rs-stat-label">Data</div>
    </div>
    <div class="rs-stat-item">
    <div class="rs-stat-value"><?php echo $last_import['properties_processed'] ?? 0; ?></div>
    <div class="rs-stat-label">Processate</div>
    </div>
    <div class="rs-stat-item">
    <div class="rs-stat-value"><?php echo $last_import['properties_created'] ?? 0; ?></div>
    <div class="rs-stat-label">Create</div>
    </div>
    <div class="rs-stat-item">
    <div class="rs-stat-value"><?php echo $last_import['properties_updated'] ?? 0; ?></div>
    <div class="rs-stat-label">Aggiornate</div>
    </div>
    <div class="rs-stat-item">
    <div class="rs-stat-value"><?php echo $last_import['duration'] ?? 0; ?>s</div>
    <div class="rs-stat-label">Durata</div>
    </div>
    <div class="rs-stat-item">
    <div class="rs-stat-value">
    <span class="rs-status-badge rs-status-<?php echo $last_import['status']; ?>">
    <?php echo ucfirst($last_import['status'] ?? 'unknown'); ?>
    </span>
    </div>
    <div class="rs-stat-label">Status</div>
    </div>
    </div>
    </div>
    <?php endif; ?>
    
    <!-- Manual Import Section -->
    <div class="rs-import-section">
    <h4><span class="dashicons dashicons-download"></span> Import Manuale</h4>
    
    <?php if ($import_in_progress): ?>
        <div class="rs-alert rs-alert-warning">
        <strong>Import in corso...</strong> Un import è attualmente in esecuzione.
        </div>
        
        <div class="rs-progress-bar">
        <div class="rs-progress-fill" id="import-progress-bar" style="width: 0%"></div>
        </div>
        <div class="rs-progress-text" id="import-progress-text">Avvio import...</div>
        
        <button type="button" class="rs-button-secondary" id="refresh-progress">
        <span class="dashicons dashicons-update"></span> Aggiorna Stato
        </button>
        <?php else: ?>
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
            <?php endif; ?>
            </div>
            </div>
            
            <!-- Configuration Panel -->
            <div class="rs-card">
            <h3><span class="dashicons dashicons-admin-generic"></span> Configurazione</h3>
            
            <!-- Next Scheduled Import -->
            <div class="rs-info-box">
            <strong>Prossimo Import Automatico:</strong><br>
            <?php if ($next_scheduled): ?>
                <?php echo date('d/m/Y alle H:i', $next_scheduled); ?>
                <br><small>Tra <?php echo human_time_diff(time(), $next_scheduled); ?></small>
                <?php else: ?>
                    <span style="color: #d63638;">Non programmato</span>
                    <?php endif; ?>
                    </div>
                    
                    <!-- Quick Settings -->
                    <form id="rs-quick-settings" method="post">
                    <?php wp_nonce_field('realestate_sync_nonce'); ?>
                    <table class="rs-form-table">
                    <tr>
                    <th>URL XML:</th>
                    <td>
                    <div class="rs-field-container">
                    <input type="url" id="xml_url" name="xml_url" class="rs-input rs-field-readonly" 
                    value="<?php echo esc_attr($settings['xml_url']); ?>" 
                    placeholder="https://www.gestionaleimmobiliare.it/export/xml/..." readonly>
                    <button type="button" class="rs-edit-btn" data-field="xml_url" title="Modifica URL">
                    <span class="dashicons dashicons-edit"></span>
                    </button>
                    </div>
                    </td>
                    </tr>
                    <tr>
                    <th>Username:</th>
                    <td>
                    <div class="rs-field-container">
                    <input type="text" id="username" name="username" class="rs-input rs-field-readonly" 
                    value="<?php echo esc_attr($settings['username']); ?>" readonly>
                    <button type="button" class="rs-edit-btn" data-field="username" title="Modifica Username">
                    <span class="dashicons dashicons-edit"></span>
                    </button>
                    </div>
                    </td>
                    </tr>
                    <tr>
                    <th>Password:</th>
                    <td>
                    <div class="rs-field-container">
                    <input type="password" id="password" name="password" class="rs-input rs-field-readonly" 
                    value="<?php echo !empty($settings['password']) ? '••••••••••••' : ''; ?>" 
                    data-original="<?php echo esc_attr($settings['password']); ?>" readonly>
                    <button type="button" class="rs-edit-btn" data-field="password" title="Modifica Password">
                    <span class="dashicons dashicons-edit"></span>
                    </button>
                    </div>
                    </td>
                    </tr>
                    <tr>
                    <th>Email Notifiche:</th>
                    <td>
                    <input type="email" id="notification_email" name="notification_email" class="rs-input" 
                    value="<?php echo esc_attr($settings['notification_email']); ?>" 
                    placeholder="admin@example.com">
                    </td>
                    </tr>
                    <tr>
                    <th>Provincie Attive:</th>
                    <td>
                    <div class="rs-checkbox-group">
                    <?php
                    $available_provinces = array('TN' => 'Trento', 'BZ' => 'Bolzano');
                    $enabled_provinces = $settings['enabled_provinces'] ?? array('TN');
                    foreach ($available_provinces as $code => $name):
                        ?>
                        <div class="rs-checkbox-item">
                        <input type="checkbox" id="province_<?php echo $code; ?>" 
                        name="enabled_provinces[]" value="<?php echo $code; ?>"
                        <?php checked(in_array($code, $enabled_provinces)); ?>>
                        <label for="province_<?php echo $code; ?>"><?php echo $name; ?> (<?php echo $code; ?>)</label>
                        </div>
                        <?php endforeach; ?>
                        </div>
                        </td>
                        </tr>
                        </table>
                        
                        <div style="margin-top: 20px;">
                        <button type="submit" class="rs-button-primary">
                        <span class="dashicons dashicons-yes"></span> Salva Configurazione
                        </button>
                        
                        <button type="button" class="rs-button-secondary" id="toggle-advanced">
                        <span class="dashicons dashicons-admin-settings"></span> Impostazioni Avanzate
                        </button>
                        </div>
                        </form>
                        
                        <!-- Advanced Settings (Hidden by default) -->
                        <div id="advanced-settings" class="rs-hidden" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #c3c4c7;">
                        <h4>Impostazioni Avanzate</h4>
                        <table class="rs-form-table">
                        <tr>
                        <th>Dimensione Batch:</th>
                        <td>
                        <input type="number" id="chunk_size" name="chunk_size" class="rs-input" 
                        value="<?php echo esc_attr($settings['chunk_size'] ?? 50); ?>" 
                        min="10" max="500" style="width: 100px;">
                        <small>Proprietà processate per batch (consigliato: 50-100)</small>
                        </td>
                        </tr>
                        <tr>
                        <th>Pausa tra Batch:</th>
                        <td>
                        <input type="number" id="sleep_seconds" name="sleep_seconds" class="rs-input" 
                        value="<?php echo esc_attr($settings['sleep_seconds'] ?? 2); ?>" 
                        min="0" max="10" style="width: 100px;">
                        <small>Secondi di pausa tra batch (per ridurre carico server)</small>
                        </td>
                        </tr>
                        <tr>
                        <th>Automazione:</th>
                        <td>
                        <?php $cron_enabled = $cron_manager->is_scheduled(); ?>
                        <button type="button" class="rs-button-<?php echo $cron_enabled ? 'secondary' : 'primary'; ?>" 
                        id="toggle-automation">
                        <?php if ($cron_enabled): ?>
                            <span class="dashicons dashicons-pause"></span> Disabilita Automazione
                            <?php else: ?>
                                <span class="dashicons dashicons-controls-play"></span> Abilita Automazione
                                <?php endif; ?>
                                </button>
                                <small>Import automatico giornaliero alle 02:00</small>
                                </td>
                                </tr>
                                </table>
                                </div>
                                </div>
                                </div>
                                <!-- Aggiungi questa sezione PRIMA di "Logs & Monitoring" nel dashboard.php -->
                                
                                <!-- Testing & Development Section -->
                                <div class="rs-card" style="border-left: 4px solid #f0ad4e;">
                                <h3><span class="dashicons dashicons-admin-tools"></span> Testing & Development</h3>
                                
                                <div class="rs-testing-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                
                                <!-- Database Actions -->
                                <div class="rs-testing-section">
                                <h4><span class="dashicons dashicons-database-remove"></span> Database Cleanup</h4>
                                <div class="rs-info-box" style="margin-bottom: 15px;">
                                <strong>⚠️ ATTENZIONE:</strong> Queste azioni sono per testing e sviluppo. Cancellano dati definitivamente.
                                </div>
                                
                                <div class="rs-button-group" style="display: flex; flex-direction: column; gap: 10px;">
                                <button type="button" class="rs-button-danger" id="cleanup-properties" style="background: #dc3545; border-color: #dc3545;">
                                <span class="dashicons dashicons-trash"></span> Cancella Tutte le Properties
                                </button>
                                
                                <button type="button" class="rs-button-warning" id="reset-tracking" style="background: #ffc107; border-color: #ffc107; color: #000;">
                                <span class="dashicons dashicons-update"></span> Reset Tracking Table
                                </button>
                                
                                <button type="button" class="rs-button-secondary" id="show-property-stats">
                                <span class="dashicons dashicons-chart-bar"></span> Mostra Statistiche Properties
                                </button>
                                </div>
                                </div>
                                
                                <!-- Test Import -->
                                <div class="rs-testing-section">
                                <h4><span class="dashicons dashicons-upload"></span> Test Import</h4>
                                <div class="rs-info-box" style="margin-bottom: 15px;">
                                <strong>📁 File Test:</strong> Carica XML ridotto per testare mapping e change detection.
                                </div>
                                
                                <div class="rs-upload-section" style="margin-bottom: 15px;">
                                <input type="file" id="test-xml-file" accept=".xml" style="margin-bottom: 10px;">
                                <small>Seleziona file XML test (3-5 properties raccomandate)</small>
                                </div>
                                
                                <div class="rs-button-group" style="display: flex; flex-direction: column; gap: 10px;">
                                <button type="button" class="rs-button-primary" id="import-test-file" disabled>
                                <span class="dashicons dashicons-download"></span> Import File Test
                                </button>
                                
                                <button type="button" class="rs-button-secondary" id="create-sample-xml">
                                <span class="dashicons dashicons-media-code"></span> Crea XML Sample
                                </button>
                                
                                <button type="button" class="rs-button-secondary" id="validate-mapping">
                                <span class="dashicons dashicons-yes-alt"></span> Valida Mapping
                                </button>
                                
                                <button type="button" class="rs-button-primary" id="create-properties-from-sample" style="background: #00a32a; border-color: #00a32a;">
                                <span class="dashicons dashicons-plus-alt"></span> Crea Properties da Sample v3.0
                                </button>
                                </div>
                                </div>
                                </div>
                                
                                <!-- Property Stats Display (Initially Hidden) -->
                                <div id="property-stats-display" class="rs-hidden" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                                <h4>Statistiche Properties Database</h4>
                                <div id="property-stats-content">
                                <p>Caricamento statistiche...</p>
                                </div>
                                </div>
                                
                                <!-- Mapping Validation Results (Initially Hidden) -->
                                <div id="mapping-validation-display" class="rs-hidden" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                                <h4>Risultati Validazione Mapping</h4>
                                <div id="mapping-validation-content">
                                <p>Avvia validazione mapping...</p>
                                </div>
                                </div>
                                </div>
                                
                                <!-- JavaScript per Testing Functions -->
                                <script type="text/javascript">
                                jQuery(document).ready(function($) {
                                    
                                    // Testing functions
                                    var testing = {
                                        
                                        init: function() {
                                            this.bindEvents();
                                        },
                                        
                                        bindEvents: function() {
                                            // Database actions
                                            $('#cleanup-properties').on('click', this.cleanupProperties);
                                            $('#reset-tracking').on('click', this.resetTracking);
                                            $('#show-property-stats').on('click', this.showPropertyStats);
                                            
                                            // Test import
                                            $('#test-xml-file').on('change', this.onFileSelect);
                                            $('#import-test-file').on('click', this.importTestFile);
                                            $('#create-sample-xml').on('click', this.createSampleXML);
                                            $('#validate-mapping').on('click', this.validateMapping);
                                            $('#create-properties-from-sample').on('click', this.createPropertiesFromSampleV3);
                                        },
                                        
                                        cleanupProperties: function(e) {
                                            e.preventDefault();
                                            
                                            var confirmation = prompt(
                                                'ATTENZIONE: Questa azione cancellerà TUTTE le properties dal database.\n\n' +
                                                'Per confermare, scrivi "CANCELLA TUTTO" (senza virgolette):'
                                            );
                                            
                                            if (confirmation !== 'CANCELLA TUTTO') {
                                                alert('Operazione annullata');
                                                return;
                                            }
                                            
                                            testing.showAlert('Cancellazione properties in corso...', 'warning');
                                            
                                            $.ajax({
                                                url: realestateSync.ajax_url,
                                                type: 'POST',
                                                data: {
                                                    action: 'realestate_sync_cleanup_properties',
                                                    nonce: realestateSync.nonce
                                                },
                                                beforeSend: function() {
                                                    $('#cleanup-properties').prop('disabled', true).html('<span class="rs-spinner"></span>Cancellazione...');
                                                },
                                                success: function(response) {
                                                    if (response.success) {
                                                        testing.showAlert('Properties cancellate: ' + response.data.deleted_count + ' properties eliminate', 'success');
                                                        testing.refreshPropertyStats();
                                                    } else {
                                                        testing.showAlert('Errore cancellazione: ' + response.data, 'error');
                                                    }
                                                },
                                                error: function() {
                                                    testing.showAlert('Errore di comunicazione con il server', 'error');
                                                },
                                                complete: function() {
                                                    $('#cleanup-properties').prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Cancella Tutte le Properties');
                                                }
                                            });
                                        },
                                        
                                        resetTracking: function(e) {
                                            e.preventDefault();
                                            
                                            if (!confirm('Sei sicuro di voler resettare la tabella di tracking? Questo resetterà il change detection.')) {
                                                return;
                                            }
                                            
                                            $.ajax({
                                                url: realestateSync.ajax_url,
                                                type: 'POST',
                                                data: {
                                                    action: 'realestate_sync_reset_tracking',
                                                    nonce: realestateSync.nonce
                                                },
                                                beforeSend: function() {
                                                    $('#reset-tracking').prop('disabled', true).html('<span class="rs-spinner"></span>Reset...');
                                                },
                                                success: function(response) {
                                                    if (response.success) {
                                                        testing.showAlert('Tracking table resettata: ' + response.data.cleared_records + ' record eliminati', 'success');
                                                    } else {
                                                        testing.showAlert('Errore reset: ' + response.data, 'error');
                                                    }
                                                },
                                                error: function() {
                                                    testing.showAlert('Errore di comunicazione con il server', 'error');
                                                },
                                                complete: function() {
                                                    $('#reset-tracking').prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Reset Tracking Table');
                                                }
                                            });
                                        },
                                        
                                        showPropertyStats: function(e) {
                                            e.preventDefault();
                                            
                                            $('#property-stats-display').removeClass('rs-hidden');
                                            $('#property-stats-content').html('<p><span class="rs-spinner"></span>Caricamento statistiche...</p>');
                                            
                                            $.ajax({
                                                url: realestateSync.ajax_url,
                                                type: 'POST',
                                                data: {
                                                    action: 'realestate_sync_get_property_stats',
                                                    nonce: realestateSync.nonce
                                                },
                                                success: function(response) {
                                                    if (response.success) {
                                                        testing.displayPropertyStats(response.data);
                                                    } else {
                                                        $('#property-stats-content').html('<p style="color: #d63638;">Errore: ' + response.data + '</p>');
                                                    }
                                                },
                                                error: function() {
                                                    $('#property-stats-content').html('<p style="color: #d63638;">Errore di comunicazione con il server</p>');
                                                }
                                            });
                                        },
                                        
                                        displayPropertyStats: function(stats) {
                                            var html = '<div class="rs-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
                                            
                                            // Total Properties
                                            html += '<div class="rs-stat-card" style="padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #2271b1;">';
                                            html += '<h5 style="margin: 0 0 5px 0;">Total Properties</h5>';
                                            html += '<div style="font-size: 24px; font-weight: bold; color: #2271b1;">' + stats.total_properties + '</div>';
                                            html += '</div>';
                                            
                                            // By Category
                                            if (stats.by_category) {
                                                html += '<div class="rs-stat-card" style="padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #00a32a;">';
                                                html += '<h5 style="margin: 0 0 10px 0;">Per Categoria</h5>';
                                                Object.keys(stats.by_category).forEach(function(category) {
                                                    html += '<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">';
                                                    html += '<span>' + category + '</span><strong>' + stats.by_category[category] + '</strong>';
                                                    html += '</div>';
                                                });
                                                html += '</div>';
                                            }
                                            
                                            // By Province
                                            if (stats.by_province) {
                                                html += '<div class="rs-stat-card" style="padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #f0ad4e;">';
                                                html += '<h5 style="margin: 0 0 10px 0;">Per Provincia</h5>';
                                                Object.keys(stats.by_province).forEach(function(province) {
                                                    html += '<div style="display: flex; justify-content: space-between; margin-bottom: 5px;">';
                                                    html += '<span>' + province + '</span><strong>' + stats.by_province[province] + '</strong>';
                                                    html += '</div>';
                                                });
                                                html += '</div>';
                                            }
                                            
                                            // Tracking Info
                                            if (stats.tracking_info) {
                                                html += '<div class="rs-stat-card" style="padding: 15px; background: white; border-radius: 4px; border-left: 4px solid #d63638;">';
                                                html += '<h5 style="margin: 0 0 10px 0;">Tracking Status</h5>';
                                                html += '<div>Tracked: <strong>' + stats.tracking_info.tracked_count + '</strong></div>';
                                                html += '<div>Last Import: <strong>' + (stats.tracking_info.last_import || 'Mai') + '</strong></div>';
                                                html += '</div>';
                                            }
                                            
                                            html += '</div>';
                                            
                                            $('#property-stats-content').html(html);
                                        },
                                        
                                        refreshPropertyStats: function() {
                                            if (!$('#property-stats-display').hasClass('rs-hidden')) {
                                                $('#show-property-stats').click();
                                            }
                                        },
                                        
                                        onFileSelect: function(e) {
                                            var file = e.target.files[0];
                                            if (file && file.name.endsWith('.xml')) {
                                                $('#import-test-file').prop('disabled', false);
                                                testing.showAlert('File XML selezionato: ' + file.name, 'info');
                                            } else {
                                                $('#import-test-file').prop('disabled', true);
                                                if (file) {
                                                    testing.showAlert('Seleziona un file XML valido', 'error');
                                                }
                                            }
                                        },
                                        
                                        importTestFile: function(e) {
                                            e.preventDefault();
                                            
                                            var fileInput = $('#test-xml-file')[0];
                                            if (!fileInput.files[0]) {
                                                testing.showAlert('Seleziona un file XML prima di procedere', 'error');
                                                return;
                                            }
                                            
                                            var formData = new FormData();
                                            formData.append('action', 'realestate_sync_import_test_file');
                                            formData.append('nonce', realestateSync.nonce);
                                            formData.append('test_xml_file', fileInput.files[0]);
                                            
                                            $.ajax({
                                                url: realestateSync.ajax_url,
                                                type: 'POST',
                                                data: formData,
                                                processData: false,
                                                contentType: false,
                                                beforeSend: function() {
                                                    $('#import-test-file').prop('disabled', true).html('<span class="rs-spinner"></span>Import Test...');
                                                },
                                                success: function(response) {
                                                    if (response.success) {
                                                        testing.showAlert('Test import completato! Properties: ' + response.data.imported_count, 'success');
                                                        testing.refreshPropertyStats();
                                                    } else {
                                                        testing.showAlert('Errore test import: ' + response.data, 'error');
                                                    }
                                                },
                                                error: function() {
                                                    testing.showAlert('Errore di comunicazione con il server', 'error');
                                                },
                                                complete: function() {
                                                    $('#import-test-file').prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Import File Test');
                                                }
                                            });
                                        },
                                        
                                        createSampleXML: function(e) {
                                            e.preventDefault();
                                            
                                            $.ajax({
                                                url: realestateSync.ajax_url,
                                                type: 'POST',
                                                data: {
                                                    action: 'realestate_sync_create_sample_xml',
                                                    nonce: realestateSync.nonce
                                                },
                                                beforeSend: function() {
                                                    $('#create-sample-xml').prop('disabled', true).html('<span class="rs-spinner"></span>Creando...');
                                                },
                                                success: function(response) {
                                                    if (response.success) {
                                                        // Trigger download
                                                        var element = document.createElement('a');
                                                        element.setAttribute('href', 'data:text/xml;charset=utf-8,' + encodeURIComponent(response.data.xml_content));
                                                        element.setAttribute('download', 'sample-test.xml');
                                                        element.style.display = 'none';
                                                        document.body.appendChild(element);
                                                        element.click();
                                                        document.body.removeChild(element);
                                                        
                                                        testing.showAlert('XML sample creato e scaricato: ' + response.data.properties_count + ' properties', 'success');
                                                    } else {
                                                        testing.showAlert('Errore creazione sample: ' + response.data, 'error');
                                                    }
                                                },
                                                error: function() {
                                                    testing.showAlert('Errore di comunicazione con il server', 'error');
                                                },
                                                complete: function() {
                                                    $('#create-sample-xml').prop('disabled', false).html('<span class="dashicons dashicons-media-code"></span> Crea XML Sample');
                                                }
                                            });
                                        },
                                        
                                        validateMapping: function(e) {
                                            e.preventDefault();
                                            
                                            $('#mapping-validation-display').removeClass('rs-hidden');
                                            $('#mapping-validation-content').html('<p><span class="rs-spinner"></span>Validazione mapping in corso...</p>');
                                            
                                            $.ajax({
                                                url: realestateSync.ajax_url,
                                                type: 'POST',
                                                data: {
                                                    action: 'realestate_sync_validate_mapping',
                                                    nonce: realestateSync.nonce
                                                },
                                                success: function(response) {
                                                    if (response.success) {
                                                        testing.displayMappingValidation(response.data);
                                                    } else {
                                                        $('#mapping-validation-content').html('<p style="color: #d63638;">Errore validazione: ' + response.data + '</p>');
                                                    }
                                                },
                                                error: function() {
                                                    $('#mapping-validation-content').html('<p style="color: #d63638;">Errore di comunicazione con il server</p>');
                                                }
                                            });
                                        },
                                        
                                        createPropertiesFromSampleV3: function(e) {
                                            e.preventDefault();
                                            
                                            if (!confirm('Sei sicuro di voler creare properties di test con Property Mapper v3.0?\n\nQuesto utilizzerà il mapping avanzato con tutte le funzionalità implementate.')) {
                                                return;
                                            }
                                            
                                            testing.showAlert('Creazione properties da sample v3.0 in corso...', 'warning');
                                            
                                            $.ajax({
                                                url: realestateSync.ajax_url,
                                                type: 'POST',
                                                data: {
                                                    action: 'realestate_sync_create_properties_from_sample', // FIXED: Use correct action
                                                    nonce: realestateSync.nonce
                                                },
                                                beforeSend: function() {
                                                    $('#create-properties-from-sample').prop('disabled', true).html('<span class="rs-spinner"></span>Creando v3.0...');
                                                },
                                                success: function(response) {
                                                    if (response.success) {
                                                        var result = response.data;
                                                        var message = 'Properties v3.0 create: ' + result.created_count + ' created, ' + 
                                                                     result.updated_count + ' updated, ' + result.skipped_count + ' skipped';
                                                        
                                                        if (result.features_created > 0) {
                                                            message += '\n' + result.features_created + ' nuove features create';
                                                        }
                                                        
                                                        testing.showAlert(message, 'success');
                                                        testing.refreshPropertyStats();
                                                        
                                                        // Show detailed results
                                                        if (result.processing_details && result.processing_details.length > 0) {
                                                            console.log('Properties v3.0 Details:', result.processing_details);
                                                        }
                                                    } else {
                                                        testing.showAlert('Errore creazione properties v3.0: ' + response.data, 'error');
                                                    }
                                                },
                                                error: function() {
                                                    testing.showAlert('Errore di comunicazione con il server', 'error');
                                                },
                                                complete: function() {
                                                    $('#create-properties-from-sample').prop('disabled', false).html('<span class="dashicons dashicons-plus-alt"></span> Crea Properties da Sample v3.0');
                                                }
                                            });
                                        },
                                        
                                        // Add showAlert method to testing object
                                        showAlert: function(message, type) {
                                            var alertClass = 'rs-alert-' + (type || 'info');
                                            var alertHtml = '<div class="rs-alert ' + alertClass + '">' + message + '</div>';
                                            
                                            $('#rs-alerts-container').html(alertHtml);
                                            
                                            // Auto-hide success and info alerts after 5 seconds
                                            if (type === 'success' || type === 'info') {
                                                setTimeout(function() {
                                                    $('#rs-alerts-container .rs-alert').fadeOut();
                                                }, 5000);
                                            }
                                        },
                                        
                                        displayMappingValidation: function(validation) {
                                            var html = '<div class="rs-validation-results">';
                                            
                                            // Summary
                                            html += '<div style="margin-bottom: 20px; padding: 15px; background: ' + 
                                            (validation.overall_score >= 80 ? '#d1e7dd' : validation.overall_score >= 60 ? '#fff3cd' : '#f8d7da') + 
                                            '; border-radius: 4px;">';
                                            html += '<h5 style="margin: 0 0 10px 0;">Punteggio Mapping: ' + validation.overall_score + '%</h5>';
                                            html += '<p style="margin: 0;">' + validation.summary + '</p>';
                                            html += '</div>';
                                            
                                            // Detailed Results
                                            if (validation.tests) {
                                                html += '<h5>Risultati Dettagliati:</h5>';
                                                validation.tests.forEach(function(test) {
                                                    var iconClass = test.passed ? 'dashicons-yes-alt' : 'dashicons-warning';
                                                    var statusColor = test.passed ? '#00a32a' : '#d63638';
                                                    
                                                    html += '<div style="display: flex; align-items: center; margin-bottom: 10px; padding: 10px; background: white; border-radius: 4px; border-left: 4px solid ' + statusColor + ';">';
                                                    html += '<span class="dashicons ' + iconClass + '" style="color: ' + statusColor + '; margin-right: 10px;"></span>';
                                                    html += '<div style="flex: 1;">';
                                                    html += '<strong>' + test.name + '</strong><br>';
                                                    html += '<small>' + test.description + '</small>';
                                                    if (test.details) {
                                                        html += '<br><small style="color: #666;">Dettagli: ' + test.details + '</small>';
                                                    }
                                                    html += '</div>';
                                                    html += '</div>';
                                                });
                                            }
                                            
                                            html += '</div>';
                                            
                                            $('#mapping-validation-content').html(html);
                                        }
                                    };
                                    
                                    // Initialize testing functions
                                    testing.init();
                                    
                                    // Extend dashboard object with testing methods
                                    if (typeof dashboard !== 'undefined') {
                                        dashboard.testing = testing;
                                    }
                                });
                                </script>
                                
                                <style>
                                .rs-button-danger {
                                    background-color: #dc3545;
                                    border-color: #dc3545;
                                    color: white;
                                }
                                
                                .rs-button-danger:hover {
                                    background-color: #c82333;
                                    border-color: #bd2130;
                                }
                                
                                .rs-button-warning {
                                    background-color: #ffc107;
                                    border-color: #ffc107;
                                    color: #212529;
                                }
                                
                                .rs-button-warning:hover {
                                    background-color: #e0a800;
                                    border-color: #d39e00;
                                }
                                
                                .rs-testing-section {
                                    padding: 15px;
                                    background: #f8f9fa;
                                    border-radius: 4px;
                                    border: 1px solid #dee2e6;
                                }
                                
                                .rs-button-group button {
                                    width: 100%;
                                    text-align: left;
                                }
                                
                                .rs-stat-card {
                                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                                }
                                
                                .rs-validation-results .dashicons {
                                    font-size: 16px;
                                }
                                </style>
                                <!-- Logs & Monitoring Section -->
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
                                <button type="button" class="rs-button-secondary" id="force-database-creation">
                                <span class="dashicons dashicons-database"></span> Crea Tabella Database
                                </button>
                                </div>
                                
                                <!-- Log Viewer (Hidden by default) -->
                                <div id="log-viewer" class="rs-hidden">
                                <div style="background: #f9f9f9; border: 1px solid #c3c4c7; border-radius: 4px; padding: 15px; max-height: 400px; overflow-y: auto;">
                                <pre id="log-content" style="margin: 0; font-family: 'Courier New', monospace; font-size: 12px; line-height: 1.4;">Caricamento log...</pre>
                                </div>
                                </div>
                                
                                <!-- System Status -->
                                <div id="system-status" class="rs-hidden">
                                <h4>Stato Sistema</h4>
                                <div id="system-check-results">
                                <p>Verifica in corso...</p>
                                </div>
                                </div>
                                </div>
                                </div>
                                
                                <!-- Hidden form for automation toggle -->
                                <form id="automation-toggle-form" style="display: none;">
                                <input type="hidden" name="action" value="realestate_sync_toggle_automation">
                                <?php wp_nonce_field('realestate_sync_nonce'); ?>
                                </form>
                                
                                <script type="text/javascript">
                                jQuery(document).ready(function($) {
                                    
                                    // Initialize dashboard
                                    var dashboard = {
                                        
                                        init: function() {
                                            this.bindEvents();
                                            this.checkImportProgress();
                                            this.initializeEditMode();
                                        },
                                        
                                        bindEvents: function() {
                                            // Manual import
                                            $('#start-manual-import').on('click', this.startManualImport);
                                            $('#rs-test-connection').on('click', this.testConnection);
                                            $('#refresh-progress').on('click', this.checkImportProgress);
                                            
                                            // Settings
                                            $('#rs-quick-settings').on('submit', this.saveSettings);
                                            $('#toggle-advanced').on('click', this.toggleAdvanced);
                                            $('#toggle-automation').on('click', this.toggleAutomation);
                                            
                                            // Edit mode
                                            $('.rs-edit-btn').on('click', this.toggleEditMode);
                                            
                                            // Logs
                                            $('#view-logs').on('click', this.viewLogs);
                                            $('#download-logs').on('click', this.downloadLogs);
                                            $('#clear-logs').on('click', this.clearLogs);
                                            $('#system-check').on('click', this.systemCheck);
                                            $('#force-database-creation').on('click', this.forceDatabaseCreation);
                                        },
                                        
                                        startManualImport: function(e) {
                                            e.preventDefault();
                                            
                                            if (!confirm(realestateSync.strings.confirm_import)) {
                                                return;
                                            }
                                            
                                            dashboard.showAlert('Import avviato. Questo potrebbe richiedere alcuni minuti...', 'warning');
                                            
                                            $.ajax({
                                                url: realestateSync.ajax_url,
                                                type: 'POST',
                                                data: {
                                                    action: 'realestate_sync_manual_import',
                                                    nonce: realestateSync.nonce
                                                },
                                                beforeSend: function() {
                                                    $('#start-manual-import').prop('disabled', true).html('<span class="rs-spinner"></span>Import in corso...');
                                                },
                                                success: function(response) {
                                                    if (response.success) {
                                                        dashboard.showAlert('Import completato con successo!', 'success');
                                                        location.reload(); // Refresh to show updated stats
                                                    } else {
                                                        dashboard.showAlert('Errore: ' + response.data, 'error');
                                                    }
                                                },
                                                error: function() {
                                                    dashboard.showAlert('Errore di comunicazione con il server', 'error');
                                                },
                                                complete: function() {
                                                    $('#start-manual-import').prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Scarica e Importa Ora');
                                                }
                                            });
                                        },
                                        
                                        testConnection: function(e) {
                                            e.preventDefault();
                                            
                                            var url = $('#xml_url').val();
                                            var username = $('#username').val();
                                            var password = $('#password').val();
                                            
                                            if (!url || !username || !password) {
                                                dashboard.showAlert('Compila tutti i campi di configurazione prima di testare', 'error');
                                                return;
                                            }
                                            
                                            $.ajax({
                                                url: realestateSync.ajax_url,
                                                type: 'POST',
                                                data: {
                                                    action: 'realestate_sync_test_connection',
                                                    nonce: realestateSync.nonce,
                                                    url: url,
                                                    username: username,
                                                    password: password
                                                },
                                                beforeSend: function() {
                                                    $('#rs-test-connection').prop('disabled', true).html('<span class="rs-spinner"></span>Testing...');
                                                },
                                                success: function(response) {
                                                    if (response.success) {
                                                        dashboard.showAlert('Connessione riuscita! Server raggiungibile.', 'success');
                                                    } else {
                                                        var errorMsg = 'Test fallito';
                                                        if (response.data) {
                                                            if (response.data.message) {
                                                                errorMsg += ': ' + response.data.message;
                                                            } else if (response.data.http_code) {
                                                                if (response.data.http_code === 401) {
                                                                    errorMsg += ': HTTP 401 - Credenziali non autorizzate. Verifica username/password in GestionaleImmobiliare.it';
                                                                } else {
                                                                    errorMsg += ': HTTP ' + response.data.http_code;
                                                                }
                                                            } else {
                                                                errorMsg += ': Errore sconosciuto';
                                                            }
                                                        }
                                                        dashboard.showAlert(errorMsg, 'error');
                                                    }
                                                },
                                                error: function() {
                                                    dashboard.showAlert('Errore durante il test di connessione', 'error');
                                                },
                                                complete: function() {
                                                    $('#rs-test-connection').prop('disabled', false).html('<span class="dashicons dashicons-networking"></span> Test Connessione');
                                                }
                                            });
                                        },
                                        
                                        saveSettings: function(e) {
                                            e.preventDefault();
                                            
                                            var formData = $('#rs-quick-settings, #advanced-settings').serialize();
                                            formData += '&action=realestate_sync_save_settings&nonce=' + realestateSync.nonce;
                                            
                                            $.ajax({
                                                url: realestateSync.ajax_url,
                                                type: 'POST',
                                                data: formData,
                                                beforeSend: function() {
                                                    $('#rs-quick-settings button[type="submit"]').prop('disabled', true).html('<span class="rs-spinner"></span>Salvataggio...');
                                                },
                                                success: function(response) {
                                                    if (response.success) {
                                                        dashboard.showAlert('Configurazione salvata con successo!', 'success');
                                                        dashboard.setReadOnlyMode();
                                                    } else {
                                                        dashboard.showAlert('Errore nel salvataggio: ' + response.data, 'error');
                                                    }
                                                },
                                                error: function() {
                                                    dashboard.showAlert('Errore di comunicazione con il server', 'error');
                                                },
                                                complete: function() {
                                                    $('#rs-quick-settings button[type="submit"]').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Salva Configurazione');
                                                }
                                            });
                                        },
                                        
                                        toggleEditMode: function(e) {
                                            e.preventDefault();
                                            
                                            var fieldName = $(this).data('field');
                                            var input = $('#' + fieldName);
                                            var button = $(this);
                                            
                                            if (input.prop('readonly')) {
                                                // Switch to edit mode
                                                input.removeClass('rs-field-readonly').prop('readonly', false).focus();
                                                
                                                // For password field, show original value
                                                if (fieldName === 'password') {
                                                    var originalValue = input.data('original');
                                                    input.val(originalValue);
                                                }
                                                
                                                button.html('<span class="dashicons dashicons-yes"></span>').attr('title', 'Conferma');
                                                button.removeClass('rs-edit-btn').addClass('rs-confirm-btn');
                                            } else {
                                                // Switch back to readonly mode
                                                dashboard.setFieldReadOnly(fieldName);
                                            }
                                        },
                                        
                                        setReadOnlyMode: function() {
                                            $('.rs-field-readonly').prop('readonly', true);
                                            $('.rs-confirm-btn').each(function() {
                                                var fieldName = $(this).data('field');
                                                dashboard.setFieldReadOnly(fieldName);
                                            });
                                        },
                                        
                                        setFieldReadOnly: function(fieldName) {
                                            var input = $('#' + fieldName);
                                            var button = $('[data-field="' + fieldName + '"]');
                                            
                                            input.addClass('rs-field-readonly').prop('readonly', true);
                                            
                                            // For password field, show dots
                                            if (fieldName === 'password' && input.val()) {
                                                input.val('••••••••••••');
                                            }
                                            
                                            button.html('<span class="dashicons dashicons-edit"></span>').attr('title', 'Modifica ' + fieldName);
                                            button.removeClass('rs-confirm-btn').addClass('rs-edit-btn');
                                        },
                                        
                                        initializeEditMode: function() {
                                            // Set readonly mode on page load if settings exist
                                            var hasSettings = $('#xml_url').val() || $('#username').val() || $('#password').data('original');
                                            if (hasSettings) {
                                                this.setReadOnlyMode();
                                            }
                                        },
                                        
                                        toggleAdvanced: function(e) {
                                            e.preventDefault();
                                            $('#advanced-settings').toggleClass('rs-hidden');
                                            var isVisible = !$('#advanced-settings').hasClass('rs-hidden');
                                            $(this).html(isVisible ? 
                                            '<span class="dashicons dashicons-arrow-up"></span> Nascondi Avanzate' : 
                                            '<span class="dashicons dashicons-admin-settings"></span> Impostazioni Avanzate'
                                        );
                                    },
                                    
                                    toggleAutomation: function(e) {
                                        e.preventDefault();
                                        // Implementation would depend on server-side automation toggle
                                        dashboard.showAlert('Funzione in sviluppo', 'warning');
                                    },
                                    
                                    viewLogs: function(e) {
                                        e.preventDefault();
                                        
                                        $('#log-viewer').toggleClass('rs-hidden');
                                        
                                        if (!$('#log-viewer').hasClass('rs-hidden')) {
                                            // Load logs via AJAX
                                            $('#log-content').text('Caricamento log...');
                                            
                                            $.ajax({
                                                url: realestateSync.ajax_url,
                                                type: 'POST',
                                                data: {
                                                    action: 'realestate_sync_get_logs',
                                                    nonce: realestateSync.nonce
                                                },
                                                success: function(response) {
                                                    if (response.success) {
                                                        $('#log-content').text(response.data.logs || 'Nessun log disponibile');
                                                    } else {
                                                        $('#log-content').text('Errore nel caricamento dei log: ' + response.data);
                                                    }
                                                },
                                                error: function() {
                                                    $('#log-content').text('Errore di comunicazione con il server');
                                                }
                                            });
                                        }
                                    },
                                    
                                    downloadLogs: function(e) {
                                        e.preventDefault();
                                        // Create download link for logs
                                        var downloadUrl = realestateSync.ajax_url + '?action=realestate_sync_download_logs&nonce=' + realestateSync.nonce;
                                        window.open(downloadUrl, '_blank');
                                    },
                                    
                                    clearLogs: function(e) {
                                        e.preventDefault();
                                        
                                        if (!confirm('Sei sicuro di voler cancellare tutti i log?')) {
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
                                                    dashboard.showAlert('Log cancellati con successo', 'success');
                                                    $('#log-content').text('Log cancellati');
                                                } else {
                                                    dashboard.showAlert('Errore nella cancellazione: ' + response.data, 'error');
                                                }
                                            },
                                            error: function() {
                                                dashboard.showAlert('Errore di comunicazione con il server', 'error');
                                            }
                                        });
                                    },
                                    
                                    systemCheck: function(e) {
                                        e.preventDefault();
                                        
                                        $('#system-status').removeClass('rs-hidden');
                                        $('#system-check-results').html('<p><span class="rs-spinner"></span>Verifica sistema in corso...</p>');
                                        
                                        $.ajax({
                                            url: realestateSync.ajax_url,
                                            type: 'POST',
                                            data: {
                                                action: 'realestate_sync_system_check',
                                                nonce: realestateSync.nonce
                                            },
                                            success: function(response) {
                                                if (response.success) {
                                                    $('#system-check-results').html(response.data.html);
                                                } else {
                                                    $('#system-check-results').html('<p style="color: #d63638;">Errore nella verifica: ' + response.data + '</p>');
                                                }
                                            },
                                            error: function() {
                                                $('#system-check-results').html('<p style="color: #d63638;">Errore di comunicazione con il server</p>');
                                            }
                                        });
                                    },
                                    
                                    forceDatabaseCreation: function(e) {
                                        e.preventDefault();
                                        
                                        if (!confirm('Sei sicuro di voler forzare la creazione della tabella database?')) {
                                            return;
                                        }
                                        
                                        dashboard.showAlert('Creazione tabella database in corso...', 'warning');
                                        
                                        $.ajax({
                                            url: realestateSync.ajax_url,
                                            type: 'POST',
                                            data: {
                                                action: 'realestate_sync_force_database_creation',
                                                nonce: realestateSync.nonce
                                            },
                                            beforeSend: function() {
                                                $('#force-database-creation').prop('disabled', true).html('<span class="rs-spinner"></span>Creando tabella...');
                                            },
                                            success: function(response) {
                                                if (response.success) {
                                                    dashboard.showAlert('Tabella database creata: ' + response.data.table_name, 'success');
                                                    console.log('Database creation result:', response.data);
                                                } else {
                                                    dashboard.showAlert('Errore creazione tabella: ' + response.data.message, 'error');
                                                    console.error('Database creation error:', response.data);
                                                }
                                            },
                                            error: function() {
                                                dashboard.showAlert('Errore di comunicazione con il server', 'error');
                                            },
                                            complete: function() {
                                                $('#force-database-creation').prop('disabled', false).html('<span class="dashicons dashicons-database"></span> Crea Tabella Database');
                                            }
                                        });
                                    },
                                    
                                    checkImportProgress: function() {
                                        $.ajax({
                                            url: realestateSync.ajax_url,
                                            type: 'POST',
                                            data: {
                                                action: 'realestate_sync_get_progress',
                                                nonce: realestateSync.nonce
                                            },
                                            success: function(response) {
                                                if (response.success && response.data) {
                                                    dashboard.updateProgress(response.data);
                                                    // Check again in 5 seconds if import is still running
                                                    if (response.data.status === 'running') {
                                                        setTimeout(dashboard.checkImportProgress, 5000);
                                                    }
                                                }
                                            }
                                        });
                                    },
                                    
                                    updateProgress: function(progress) {
                                        var percentage = Math.round((progress.processed / progress.total) * 100);
                                        $('#import-progress-bar').css('width', percentage + '%');
                                        $('#import-progress-text').text(
                                            'Processate ' + progress.processed + ' di ' + progress.total + 
                                            ' proprietà (' + percentage + '%) - ' + progress.message
                                        );
                                    },
                                    
                                    showAlert: function(message, type) {
                                        var alertClass = 'rs-alert-' + (type || 'info');
                                        var alertHtml = '<div class="rs-alert ' + alertClass + '">' + message + '</div>';
                                        
                                        $('#rs-alerts-container').html(alertHtml);
                                        
                                        // Auto-hide success and info alerts after 5 seconds
                                        if (type === 'success' || type === 'info') {
                                            setTimeout(function() {
                                                $('#rs-alerts-container .rs-alert').fadeOut();
                                            }, 5000);
                                        }
                                    }
                                };
                                
                                // Initialize dashboard
                                dashboard.init();
                            });
                            </script>
                            
                            <style>
                            .rs-status-badge {
                                padding: 2px 8px;
                                border-radius: 12px;
                                font-size: 11px;
                                font-weight: 600;
                                text-transform: uppercase;
                            }
                            
                            .rs-status-success {
                                background: #d1e7dd;
                                color: #0f5132;
                            }
                            
                            .rs-status-error {
                                background: #f8d7da;
                                color: #842029;
                            }
                            
                            .rs-status-warning {
                                background: #fff3cd;
                                color: #997404;
                            }
                            
                            .rs-status-running {
                                background: #cff4fc;
                                color: #055160;
                            }
                            </style>
                            