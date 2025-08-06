jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize admin interface
    const RealEstateSyncAdmin = {
        
        init: function() {
            this.bindEvents();
            this.checkImportProgress();
        },
        
        bindEvents: function() {
            // Manual import button
            $('#rs-manual-import').on('click', this.handleManualImport.bind(this));
            
            // Test connection button
            $('#rs-test-connection').on('click', this.handleTestConnection.bind(this));
            
            // Save settings button
            $('#rs-save-settings').on('click', this.handleSaveSettings.bind(this));
            
            // Form validation
            $('.rs-input[required]').on('blur', this.validateField);
        },
        
        handleManualImport: function(e) {
            e.preventDefault();
            
            if (!confirm(realestateSync.strings.confirm_import)) {
                return;
            }
            
            const $button = $(e.target);
            const $progress = $('#rs-import-progress');
            const $status = $('#rs-import-status');
            
            // Disable button and show progress
            $button.prop('disabled', true).html('<span class="rs-spinner"></span>' + realestateSync.strings.importing);
            $progress.removeClass('rs-hidden');
            $status.empty();
            
            // Start import
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_manual_import',
                    nonce: realestateSync.nonce
                },
                success: this.handleImportSuccess.bind(this),
                error: this.handleImportError.bind(this),
                complete: function() {
                    $button.prop('disabled', false).text('Avvia Import Manuale');
                    $progress.addClass('rs-hidden');
                }
            });
            
            // Start progress polling
            this.startProgressPolling();
        },
        
        handleImportSuccess: function(response) {
            const results = response.data.results;
            const stats = results.statistics;
            
            let html = '<div class="rs-alert rs-alert-success">';
            html += '<strong>' + realestateSync.strings.success + '</strong><br>';
            html += 'Proprietà elaborate: ' + stats.total_processed + '<br>';
            html += 'Nuove: ' + stats.new_properties + ' | ';
            html += 'Aggiornate: ' + stats.updated_properties + ' | ';
            html += 'Eliminate: ' + stats.deleted_properties + '<br>';
            html += 'Durata: ' + results.duration_formatted;
            html += '</div>';
            
            $('#rs-import-status').html(html);
            
            // Refresh page after 3 seconds
            setTimeout(function() {
                location.reload();
            }, 3000);
        },
        
        handleImportError: function(xhr) {
            let errorMsg = realestateSync.strings.error;
            
            if (xhr.responseJSON && xhr.responseJSON.data) {
                errorMsg += ': ' + xhr.responseJSON.data;
            }
            
            $('#rs-import-status').html('<div class="rs-alert rs-alert-error">' + errorMsg + '</div>');
        },
        
        handleTestConnection: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $status = $('#rs-connection-status');
            
            const data = {
                action: 'realestate_sync_test_connection',
                nonce: realestateSync.nonce,
                url: $('#xml_url').val(),
                username: $('#username').val(),
                password: $('#password').val()
            };
            
            $button.prop('disabled', true).html('<span class="rs-spinner"></span>Test in corso...');
            $status.empty();
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    console.log('Test connection response:', response); // DEBUG
                    
                    if (response && response.success && response.data) {
                        $status.html('<div class="rs-alert rs-alert-success">Connessione riuscita! Dimensione file: ' + (response.data.content_length_formatted || 'Unknown') + '</div>');
                    } else if (response && response.data) {
                        $status.html('<div class="rs-alert rs-alert-error">Test fallito: HTTP ' + (response.data.http_code || 'Unknown') + ' - ' + (response.data.error || 'Errore sconosciuto') + '</div>');
                    } else {
                        $status.html('<div class="rs-alert rs-alert-error">Test fallito: Risposta non valida</div>');
                    }
                },
                error: function() {
                    $status.html('<div class="rs-alert rs-alert-error">Errore durante il test di connessione</div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Testa Connessione');
                }
            });
        },
        
        handleSaveSettings: function(e) {
            e.preventDefault();
            
            const $button = $(e.target);
            const $status = $('#rs-settings-status');
            
            // Collect form data
            const data = {
                action: 'realestate_sync_save_settings',
                nonce: realestateSync.nonce,
                xml_url: $('#xml_url').val(),
                username: $('#username').val(),
                password: $('#password').val(),
                notification_email: $('#notification_email').val(),
                enabled_provinces: [],
                chunk_size: $('#chunk_size').val() || 25,
                sleep_seconds: $('#sleep_seconds').val() || 1
            };
            
            // Collect enabled provinces
            $('input[name="enabled_provinces[]"]:checked').each(function() {
                data.enabled_provinces.push($(this).val());
            });
            
            $button.prop('disabled', true).html('<span class="rs-spinner"></span>Salvataggio...');
            $status.empty();
            
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $status.html('<div class="rs-alert rs-alert-success">Impostazioni salvate con successo</div>');
                    } else {
                        $status.html('<div class="rs-alert rs-alert-error">Errore nel salvataggio: ' + response.data + '</div>');
                    }
                },
                error: function() {
                    $status.html('<div class="rs-alert rs-alert-error">Errore durante il salvataggio</div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Salva Impostazioni');
                    
                    // Hide status after 3 seconds
                    setTimeout(function() {
                        $status.fadeOut();
                    }, 3000);
                }
            });
        },
        
        startProgressPolling: function() {
            this.progressInterval = setInterval(this.checkImportProgress.bind(this), 2000);
        },
        
        stopProgressPolling: function() {
            if (this.progressInterval) {
                clearInterval(this.progressInterval);
                this.progressInterval = null;
            }
        },
        
        checkImportProgress: function() {
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_get_progress',
                    nonce: realestateSync.nonce
                },
                success: this.updateProgress.bind(this),
                error: function() {
                    // Silent fail - no import in progress
                }
            });
        },
        
        updateProgress: function(response) {
            if (!response.success) {
                this.stopProgressPolling();
                return;
            }
            
            const progress = response.data;
            const $progressBar = $('#rs-progress-bar');
            const $progressText = $('#rs-progress-text');
            
            if ($progressBar.length && progress.total_processed > 0) {
                const percentage = Math.min((progress.current_chunk * 25) / progress.total_processed * 100, 100);
                
                $progressBar.find('.rs-progress-fill').css('width', percentage + '%');
                $progressText.text(
                    'Chunk ' + progress.current_chunk + ' | ' +
                    'Elaborate: ' + progress.total_processed + ' | ' +
                    'Nuove: ' + progress.new_properties + ' | ' +
                    'Aggiornate: ' + progress.updated_properties + ' | ' +
                    'Memoria: ' + Math.round(progress.memory_mb) + 'MB'
                );
            }
        },
        
        validateField: function() {
            const $field = $(this);
            const value = $field.val().trim();
            
            if ($field.prop('required') && !value) {
                $field.addClass('rs-error');
            } else {
                $field.removeClass('rs-error');
            }
        }
    };
    
    // Initialize
    RealEstateSyncAdmin.init();
});
