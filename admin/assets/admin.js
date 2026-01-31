jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize admin interface
    const RealEstateSyncAdmin = {
        
        init: function() {
            this.bindEvents();
            this.checkImportProgress();
        },
        
        bindEvents: function() {
            // Manual import button (feng-shui: updated ID)
            $('#start-manual-import').on('click', this.handleManualImport.bind(this));
            
            // Test connection button
            $('#rs-test-connection').on('click', this.handleTestConnection.bind(this));
            
            // Save settings button
            $('#rs-save-settings').on('click', this.handleSaveSettings.bind(this));

            // Save email settings button
            $('#save-email-config').on('click', this.handleSaveEmailSettings.bind(this));
            
            // Form validation
            $('.rs-input[required]').on('blur', this.validateField);
        },
        
        handleManualImport: function(e) {
            e.preventDefault();

            if (!confirm('Avviare import manuale?\n\nScaricherà e processerà il file XML dal gestionale.')) {
                return;
            }

            const $button = $(e.target);
            const $logOutput = $('#manual-import-log-output');
            const $logContent = $('#manual-import-log-content');
            const markAsTest = $('#mark-as-test-manual-import').is(':checked');
            const forceUpdate = $('#force-update-manual-import').is(':checked');

            // Show log and disable button
            $logOutput.removeClass('d-none');
            $logContent.text('Avvio import manuale...');
            $button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Importando...');

            // Start import
            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: {
                    action: 'realestate_sync_manual_import',
                    nonce: realestateSync.nonce,
                    mark_as_test: markAsTest ? '1' : '0',
                    force_update: forceUpdate ? '1' : '0'
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        $logContent.text('✓ Import completato!\n\n' +
                            'Proprietà processate: ' + (data.properties_processed || 0) + '\n' +
                            'Agenzie processate: ' + (data.agencies_processed || 0) + '\n' +
                            'Durata: ' + (data.duration || 'N/A'));

                        rsToast.success('Import manuale completato con successo');
                    } else {
                        $logContent.text('✗ Errore: ' + (response.data || 'Errore sconosciuto'));
                        rsToast.error(response.data || 'Errore durante l\'import');
                    }
                },
                error: function(xhr, status, error) {
                    $logContent.text('✗ Errore di comunicazione: ' + error);
                    rsToast.error('Errore di connessione al server');
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Scarica e Importa Ora');
                }
            });
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
            const $status = $('#rs-test-connection-result');
            
            const data = {
                action: 'realestate_sync_test_connection',
                nonce: realestateSync.nonce,
                url: $('#xml_url').val(),
                username: $('#xml_user').val(),
                password: $('#xml_pass').val()
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

        handleSaveEmailSettings: function(e) {
            e.preventDefault();

            const $button = $(e.target);
            const $status = $('#email-config-status');

            const data = {
                action: 'realestate_sync_save_email_settings',
                nonce: realestateSync.nonce,
                email_enabled: $('#email-enabled').is(':checked') ? '1' : '0',
                email_to: $('#email-to').val(),
                email_cc: $('#email-cc').val()
            };

            $button.prop('disabled', true).html('<span class="rs-spinner"></span>Salvataggio...');
            $status.empty();

            $.ajax({
                url: realestateSync.ajax_url,
                type: 'POST',
                data: data,
                success: function(response) {
                    if (response.success) {
                        $status.html('<div class="rs-alert rs-alert-success">Configurazione email salvata</div>');
                    } else {
                        $status.html('<div class="rs-alert rs-alert-error">Errore nel salvataggio: ' + (response.data || 'Errore sconosciuto') + '</div>');
                    }
                },
                error: function() {
                    $status.html('<div class="rs-alert rs-alert-error">Errore durante il salvataggio</div>');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Salva Configurazione Email');
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

    // ═══════════════════════════════════════════════════════════════════════════
    // XML FILE UPLOAD IMPORT (import-xml.php widget)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Enable process button when file is selected
     */
    $('#test-xml-file').on('change', function() {
        const hasFile = $(this)[0].files.length > 0;
        $('#process-test-file').prop('disabled', !hasFile);
    });

    /**
     * Handle XML file upload and processing
     */
    $('#process-test-file').on('click', function() {
        const $button = $(this);
        const $logOutput = $('#test-log-output');
        const $logContent = $('#test-log-content');
        const fileInput = document.getElementById('test-xml-file');
        const markAsTest = $('#mark-as-test-import').is(':checked');
        const forceUpdate = $('#force-update-xml-import').is(':checked');

        if (!fileInput.files.length) {
            alert('Seleziona un file XML prima di procedere');
            return;
        }

        const file = fileInput.files[0];
        const formData = new FormData();
        formData.append('xml_file', file);
        formData.append('action', 'realestate_sync_process_test_file');
        formData.append('nonce', realestateSync.nonce);
        formData.append('mark_as_test', markAsTest ? '1' : '0');
        formData.append('force_update', forceUpdate ? '1' : '0');

        // Show log output
        $logOutput.removeClass('d-none');
        $logContent.text('Caricamento file...');

        // Disable button
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Processando...');

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $logContent.text('✓ Import completato con successo!\n\n' +
                        'Proprietà processate: ' + (response.data.properties_processed || 0) + '\n' +
                        'Agenzie processate: ' + (response.data.agencies_processed || 0));

                    // Reset file input
                    fileInput.value = '';

                    rsToast.success('File XML processato con successo');
                } else {
                    $logContent.text('✗ Errore: ' + (response.data || 'Errore sconosciuto'));
                    rsToast.error(response.data || 'Errore durante il processing');
                }
            },
            error: function(xhr, status, error) {
                $logContent.text('✗ Errore di comunicazione: ' + error);
                rsToast.error('Errore di connessione al server');
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-admin-generic"></span> Processa File XML');
            }
        });
    });

    // ✅ VERIFICATION FUNCTIONS - Global object for dashboard onclick handlers
    window.realestateSync = window.realestateSync || {};

    /**
     * Ignore single property from verification list
     * Called from dashboard widget onclick
     */
    window.realestateSync.ignoreVerification = function(propertyId) {
        if (!confirm('Ignorare questa proprietà?\n\nNon verrà più mostrata nella lista fino al prossimo import.')) {
            return;
        }

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_ignore_verification',
                nonce: realestateSync.nonce,
                property_id: propertyId
            },
            success: function(response) {
                if (response.success) {
                    // Remove row from table
                    $('#verify-row-' + propertyId).fadeOut(function() {
                        $(this).remove();

                        // If no more rows, reload page to hide widget
                        if (response.data.remaining === 0) {
                            location.reload();
                        }
                    });

                    // Show success message
                    rsToast.success('La proprietà è stata marcata come verificata');
                } else {
                    rsToast.error(response.data || 'Errore sconosciuto');
                }
            },
            error: function() {
                rsToast.error('Errore di comunicazione con il server');
            }
        });
    };

    /**
     * Clear all verification warnings
     * Called from dashboard widget onclick
     */
    window.realestateSync.clearAllVerification = function() {
        if (!confirm('Cancellare TUTTI gli avvisi di verifica?\n\nQuesta azione non può essere annullata.')) {
            return;
        }

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_clear_all_verification',
                nonce: realestateSync.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Reload page to hide widget
                    location.reload();
                } else {
                    alert('Errore: ' + (response.data || 'Errore sconosciuto'));
                }
            },
            error: function() {
                alert('Errore di comunicazione con il server');
            }
        });
    };

    // ✅ SCHEDULING CONFIGURATION HANDLERS

    /**
     * Update schedule config UI state
     */
    function updateScheduleConfigState() {
        const isEnabled = $('#schedule-enabled').is(':checked');
        const $config = $('#schedule-config');
        const $inputs = $config.find('input, select').not('#schedule-enabled');
        const $saveButton = $('#save-schedule-config');

        if (isEnabled) {
            $config.css('opacity', '1');
            $inputs.prop('disabled', false);
            $saveButton.prop('disabled', false);
        } else {
            $config.css('opacity', '0.5');
            $inputs.prop('disabled', true);
            // ✅ IMPORTANTE: Il pulsante Salva deve SEMPRE essere cliccabile
            // per permettere di salvare lo stato "disabilitato"
            $saveButton.prop('disabled', false);
        }
    }

    /**
     * Handle schedule enabled toggle
     */
    $('#schedule-enabled').on('change', function() {
        updateScheduleConfigState();
    });

    // Initialize state on page load
    updateScheduleConfigState();

    /**
     * Update frequency config sections visibility
     */
    function updateFrequencyConfigSections() {
        const frequency = $('#schedule-frequency').val();

        // Hide all config sections
        $('#weekly-config, #custom-days-config, #custom-months-config').hide();

        // Show relevant config section
        switch(frequency) {
            case 'weekly':
                $('#weekly-config').show();
                break;
            case 'custom_days':
                $('#custom-days-config').show();
                break;
            case 'custom_months':
                $('#custom-months-config').show();
                break;
        }
    }

    /**
     * Handle frequency selector change
     */
    $('#schedule-frequency').on('change', function() {
        updateFrequencyConfigSections();
    });

    // Initialize frequency sections on page load
    updateFrequencyConfigSections();

    // ✅ CLEANUP PROPRIETÀ SENZA IMMAGINI HANDLERS

    /**
     * Analyze properties without images
     */
    $('#analyze-no-images').on('click', function() {
        const $button = $(this);
        const $results = $('#no-images-analysis');
        const $content = $('#no-images-analysis-content');
        const $actions = $('#cleanup-actions');

        // Disable button
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: rotation 1s infinite linear;"></span> Analisi in corso...');

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_analyze_no_images',
                nonce: realestateSync.nonce
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;

                    if (data.total === 0) {
                        $content.html('<div style="padding: 15px; background: #d4edda; color: #155724; border-radius: 4px;">' +
                            '<strong>✓ ' + data.message + '</strong></div>');
                        $results.removeClass('rs-hidden');
                        $actions.addClass('rs-hidden');
                    } else {
                        // Build status summary
                        let statusHtml = '<div style="margin-bottom: 15px;"><strong>Totale trovate:</strong> ' + data.total + '</div>';
                        statusHtml += '<div style="margin-bottom: 15px;"><strong>Per status:</strong><ul style="margin: 5px 0; padding-left: 20px;">';

                        for (const [status, count] of Object.entries(data.by_status)) {
                            statusHtml += '<li>' + status + ': ' + count + '</li>';
                        }
                        statusHtml += '</ul></div>';

                        // Build preview
                        statusHtml += '<div style="margin-bottom: 15px;"><strong>Prime 20 proprietà:</strong></div>';
                        statusHtml += '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; max-height: 200px; overflow-y: auto; font-size: 12px;">';
                        statusHtml += data.preview.join('\n');
                        if (data.has_more) {
                            statusHtml += '\n... e altre ' + (data.total - 20) + ' proprietà';
                        }
                        statusHtml += '</pre>';

                        $content.html(statusHtml);
                        $results.removeClass('rs-hidden');
                        $actions.removeClass('rs-hidden');
                    }
                } else {
                    $content.html('<div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px;">' +
                        '<strong>✗ Errore:</strong> ' + (response.data || 'Errore sconosciuto') + '</div>');
                    $results.removeClass('rs-hidden');
                }
            },
            error: function() {
                $content.html('<div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px;">' +
                    '<strong>✗ Errore di comunicazione con il server</strong></div>');
                $results.removeClass('rs-hidden');
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Analizza Proprietà Senza Immagini');
            }
        });
    });

    /**
     * Cleanup properties - Trash
     */
    $('#cleanup-no-images-trash').on('click', function() {
        if (!confirm('Spostare nel cestino tutte le proprietà senza immagini?\n\nIl tracking verrà cancellato automaticamente.\nPotrai recuperarle dal cestino di WordPress.')) {
            return;
        }

        cleanupNoImages(false);
    });

    /**
     * Cleanup properties - Permanent
     */
    $('#cleanup-no-images-permanent').on('click', function() {
        if (!confirm('⚠️ ATTENZIONE: CANCELLAZIONE PERMANENTE\n\nQuesta azione è IRREVERSIBILE!\n\nCancellare definitivamente tutte le proprietà senza immagini?\nIl tracking verrà cancellato automaticamente.')) {
            return;
        }

        cleanupNoImages(true);
    });

    /**
     * Execute cleanup
     */
    function cleanupNoImages(forceDelete) {
        const $results = $('#cleanup-results');
        const $content = $('#cleanup-results-content');
        const $actions = $('#cleanup-actions');

        $content.html('<div style="padding: 15px; background: #fff3cd; border-radius: 4px;">' +
            '<span class="dashicons dashicons-update" style="animation: rotation 1s infinite linear;"></span> ' +
            'Cancellazione in corso...</div>');
        $results.removeClass('rs-hidden');

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_cleanup_no_images',
                nonce: realestateSync.nonce,
                force: forceDelete ? 'true' : 'false'
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;

                    let html = '<div style="padding: 15px; background: #d4edda; color: #155724; border-radius: 4px; margin-bottom: 15px;">';
                    html += '<strong>✓ Cleanup Completato!</strong></div>';

                    html += '<div style="margin-bottom: 15px;"><strong>Risultati:</strong></div>';
                    html += '<ul style="margin: 0; padding-left: 20px;">';
                    html += '<li><strong>Cancellate:</strong> ' + data.deleted + '</li>';
                    html += '<li><strong>Errori:</strong> ' + data.errors + '</li>';
                    html += '<li><strong>Totale:</strong> ' + data.total + '</li>';
                    html += '<li><strong>Modalità:</strong> ' + (data.mode === 'permanent' ? 'Permanente' : 'Cestino') + '</li>';
                    html += '</ul>';

                    html += '<div style="margin-top: 15px; padding: 10px; background: #e7f3ff; border-left: 3px solid #2271b1; border-radius: 4px;">';
                    html += '<strong>ℹ️ Il tracking è stato cancellato automaticamente dall\'hook.</strong>';
                    html += '</div>';

                    $content.html(html);

                    // Hide actions, show new analyze button
                    $actions.addClass('rs-hidden');
                    $('#no-images-analysis').addClass('rs-hidden');
                } else {
                    $content.html('<div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px;">' +
                        '<strong>✗ Errore:</strong> ' + (response.data || 'Errore sconosciuto') + '</div>');
                }
            },
            error: function() {
                $content.html('<div style="padding: 15px; background: #f8d7da; color: #721c24; border-radius: 4px;">' +
                    '<strong>✗ Errore di comunicazione con il server</strong></div>');
            }
        });
    }

    /**
     * Handle save schedule configuration
     */
    $('#save-schedule-config').on('click', function() {
        const $button = $(this);
        const $status = $('#schedule-status');

        // Disable button during save
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: rotation 1s infinite linear;"></span> Salvataggio...');

        // Collect form data
        const data = {
            action: 'realestate_sync_save_schedule',
            nonce: realestateSync.nonce,
            enabled: $('#schedule-enabled').is(':checked') ? 'true' : 'false',
            time: $('#schedule-time').val(),
            frequency: $('#schedule-frequency').val(),
            weekday: $('#schedule-weekday').val(),
            custom_days: $('#schedule-custom-days').val(),
            custom_months: $('#schedule-custom-months').val(),
            mark_test: $('#schedule-mark-test').is(':checked') ? 'true' : 'false'
        };

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    // Update next run preview
                    $('#next-run-preview').html(response.data.next_run);

                    // Show success message
                    $status.html('<div style="padding: 10px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 4px;">' +
                        '<strong>✓ ' + response.data.message + '</strong></div>');

                    // Hide message after 5 seconds
                    setTimeout(function() {
                        $status.fadeOut(function() {
                            $(this).html('').show();
                        });
                    }, 5000);
                } else {
                    $status.html('<div style="padding: 10px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                        '<strong>✗ Errore:</strong> ' + (response.data || 'Errore sconosciuto') + '</div>');
                }
            },
            error: function() {
                $status.html('<div style="padding: 10px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px;">' +
                    '<strong>✗ Errore di comunicazione con il server</strong></div>');
            },
            complete: function() {
                // Re-enable button
                $button.prop('disabled', false).html('<span class="dashicons dashicons-saved"></span> Salva Configurazione');
            }
        });
    });

    // ========================================================================
    // ✅ QUEUE MANAGEMENT HANDLERS (NEW SIMPLIFIED VERSION)
    // ========================================================================

    let currentSessionId = null;

    /**
     * Refresh import status
     */
    function refreshImportStatus() {
        // Show loading indicator
        var $btn = $('#refresh-import-status');
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Caricamento...');

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_get_queue_stats',
                nonce: realestateSync.nonce
            },
            success: function(response) {
                console.log('Queue stats response:', response);

                if (response.success) {
                    const data = response.data;

                    if (!data.has_session) {
                        $('#import-session-id').text('Nessuna sessione');
                        $('#import-start-time').text('-');
                        $('#import-process-status').html('<span style="color: #666;">Nessun import</span>');
                        $('#import-total-items').text('0');
                        $('#import-completed-items').text('0');
                        $('#import-remaining-items').text('0');
                        $('#import-progress-fill').css('width', '0%');
                        $('#import-progress-text').text('0%');
                        $('#pending-items-alert').addClass('rs-hidden');
                        return;
                    }

                    // Update table
                    currentSessionId = data.session_id;
                    $('#import-session-id').text(data.session_id);
                    $('#import-start-time').text(data.start_time);

                    // Status badge
                    if (data.is_active) {
                        $('#import-process-status').html('<span style="padding: 4px 8px; background: #10b981; color: white; border-radius: 4px; font-weight: 500;">🟢 ATTIVO</span>');
                    } else {
                        $('#import-process-status').html('<span style="padding: 4px 8px; background: #dc3545; color: white; border-radius: 4px; font-weight: 500;">🔴 CHIUSO</span>');
                    }

                    $('#import-total-items').text(data.total);
                    $('#import-completed-items').text(data.completed);
                    $('#import-remaining-items').text(data.remaining);
                    $('#import-progress-fill').css('width', data.progress_percent + '%');
                    $('#import-progress-text').text(data.progress_percent + '%');

                    // Show alert if closed and has remaining items
                    if (!data.is_active && data.remaining > 0) {
                        const msg = 'Il processo è CHIUSO ma ci sono <strong>' + data.remaining + ' elementi in sospeso</strong> (' +
                            data.pending + ' pending, ' + data.processing + ' processing, ' + data.failed + ' failed). ' +
                            'Questi elementi NON verranno più processati automaticamente.';
                        $('#pending-items-message').html(msg);
                        $('#pending-items-alert').removeClass('rs-hidden');
                    } else {
                        $('#pending-items-alert').addClass('rs-hidden');
                        $('#pending-items-list').addClass('rs-hidden');
                    }
                } else {
                    console.error('Queue stats error:', response.data);
                    alert('Errore: ' + (response.data || 'Risposta non valida dal server'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr);
                alert('Errore di connessione: ' + error + '\n\nControlla la console browser (F12) per dettagli.');
                $('#import-process-status').html('<span style="color: #dc3545;">❌ Errore caricamento</span>');
            },
            complete: function() {
                // Restore button
                $btn.prop('disabled', false).html(originalText);
            }
        });
    }

    $('#refresh-import-status').on('click', refreshImportStatus);

    // ========================================================================
    // QUEUE MANAGEMENT - Dedicated refresh function for queue-management.php widget
    // (Uses queue- prefixed IDs to avoid conflicts with monitor-import.php)
    // ========================================================================
    function refreshQueueImportStatus() {
        // Show loading indicator
        var $btn = $('#queue-refresh-import-status');
        var originalText = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Caricamento...');

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_get_queue_stats',
                nonce: realestateSync.nonce
            },
            success: function(response) {
                console.log('Queue stats response (queue-management):', response);

                if (response.success) {
                    const data = response.data;

                    if (!data.has_session) {
                        $('#queue-import-session-id').text('Nessuna sessione');
                        $('#queue-import-start-time').text('-');
                        $('#queue-import-process-status').html('<span style="color: #666;">Nessun import</span>');
                        $('#queue-import-total-items').text('0');
                        $('#queue-import-completed-items').text('0');
                        $('#queue-import-remaining-items').text('0');
                        $('#queue-import-progress-fill').css('width', '0%');
                        $('#queue-import-progress-text').text('0%');
                        $('#pending-items-alert').addClass('d-none');
                        return;
                    }

                    // Update table
                    currentSessionId = data.session_id;
                    $('#queue-import-session-id').text(data.session_id);
                    $('#queue-import-start-time').text(data.start_time);

                    // Status badge
                    if (data.is_active) {
                        $('#queue-import-process-status').html('<span style="padding: 4px 8px; background: #10b981; color: white; border-radius: 4px; font-weight: 500;">🟢 ATTIVO</span>');
                    } else {
                        $('#queue-import-process-status').html('<span style="padding: 4px 8px; background: #dc3545; color: white; border-radius: 4px; font-weight: 500;">🔴 CHIUSO</span>');
                    }

                    $('#queue-import-total-items').text(data.total);
                    $('#queue-import-completed-items').text(data.completed);
                    $('#queue-import-remaining-items').text(data.remaining);
                    $('#queue-import-progress-fill').css('width', data.progress_percent + '%');
                    $('#queue-import-progress-text').text(data.progress_percent + '%');

                    // Show alert if closed and has remaining items
                    if (!data.is_active && data.remaining > 0) {
                        const msg = 'Il processo è CHIUSO ma ci sono <strong>' + data.remaining + ' elementi in sospeso</strong> (' +
                            data.pending + ' pending, ' + data.processing + ' processing, ' + data.failed + ' failed). ' +
                            'Questi elementi NON verranno più processati automaticamente.';
                        $('#pending-items-message').html(msg);
                        $('#pending-items-alert').removeClass('d-none');
                    } else {
                        $('#pending-items-alert').addClass('d-none');
                        $('#pending-items-list').addClass('d-none');
                    }
                } else {
                    console.error('Queue stats error:', response.data);
                    alert('Errore: ' + (response.data || 'Risposta non valida dal server'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error, xhr);
                alert('Errore di connessione: ' + error + '\n\nControlla la console browser (F12) per dettagli.');
                $('#queue-import-process-status').html('<span style="color: #dc3545;">❌ Errore caricamento</span>');
            },
            complete: function() {
                // Restore button
                $btn.prop('disabled', false).html(originalText);
            }
        });
    }

    $('#queue-refresh-import-status').on('click', refreshQueueImportStatus);

    $('#show-pending-details').on('click', function() {
        const $list = $('#pending-items-list');

        if (!$list.hasClass('d-none')) {
            $list.addClass('d-none');
            $(this).html('<span class="dashicons dashicons-visibility"></span> Vedi Dettaglio');
            return;
        }

        $(this).html('Caricamento...');

        // ✨ v1.7.0+: If process is closed, show ALL sessions (not just current)
        // This allows viewing historical pending items from previous imports
        const isProcessClosed = !currentSessionId || $('#import-process-status').text().includes('CHIUSO');
        const sessionToQuery = isProcessClosed ? 'all' : currentSessionId;

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_get_failed_items',
                nonce: realestateSync.nonce,
                session_id: sessionToQuery
            },
            success: function(response) {
                if (response.success) {
                    const items = response.data.items;

                    if (items.length === 0) {
                        $list.html('<p>Nessun elemento da mostrare.</p>');
                    } else {
                        // ✨ v1.7.0+: Show Session ID column when viewing all sessions
                        const showSessionColumn = (sessionToQuery === 'all');

                        let html = '<table style="width:100%; border-collapse: collapse;">';
                        html += '<thead><tr style="background: #f5f5f5; border-bottom: 2px solid #ddd;">';
                        if (showSessionColumn) {
                            html += '<th style="padding: 8px; text-align: left;">Session</th>';
                        }
                        html += '<th style="padding: 8px; text-align: left;">Tipo</th>';
                        html += '<th style="padding: 8px; text-align: left;">ID</th>';
                        html += '<th style="padding: 8px; text-align: left;">Titolo</th>';
                        html += '<th style="padding: 8px; text-align: left;">Stato</th>';
                        html += '<th style="padding: 8px; text-align: center;">Azioni</th>';
                        html += '</tr></thead><tbody>';

                        items.forEach(function(item) {
                            // Map status values: 'error'/'retry' to display as 'failed'
                            const displayStatus = (item.status === 'error' || item.status === 'retry') ? 'failed' : item.status;
                            const statusBadge = displayStatus === 'processing' ? '<span style="color: #f0ad4e;">⏸️ Processing</span>' :
                                                displayStatus === 'failed' ? '<span style="color: #dc3545;">❌ Error</span>' :
                                                displayStatus === 'done' ? '<span style="color: #28a745;">✅ Done</span>' :
                                                '<span style="color: #856404;">⏳ Pending</span>';

                            html += '<tr style="border-bottom: 1px solid #eee;" data-item-id="' + item.id + '">';
                            if (showSessionColumn) {
                                // Show shortened session ID (last 8 chars)
                                const shortSession = item.session_id ? item.session_id.slice(-12) : 'N/A';
                                html += '<td style="padding: 8px; font-family: monospace; font-size: 11px; color: #666;" title="' + (item.session_id || '') + '">' + shortSession + '</td>';
                            }
                            html += '<td style="padding: 8px;"><strong>' + item.item_type + '</strong></td>';
                            html += '<td style="padding: 8px;">' + item.item_id + '</td>';
                            html += '<td style="padding: 8px;">' + (item.title || '-') + '</td>';
                            html += '<td style="padding: 8px;">' + statusBadge + '</td>';
                            html += '<td style="padding: 8px; text-align: center; white-space: nowrap;">';

                            // Icon buttons
                            if (item.frontend_url) {
                                html += '<a href="' + item.frontend_url + '" target="_blank" class="rs-icon-btn" title="Vedi su frontend" style="display: inline-block; padding: 6px 10px; margin: 0 2px; background: #2271b1; color: white; border-radius: 4px; text-decoration: none; cursor: pointer;"><span class="dashicons dashicons-visibility" style="font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span></a>';
                            } else {
                                html += '<span class="rs-icon-btn" style="display: inline-block; padding: 6px 10px; margin: 0 2px; background: #ccc; color: white; border-radius: 4px; cursor: not-allowed;"><span class="dashicons dashicons-visibility" style="font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span></span>';
                            }

                            html += '<button class="rs-mark-done rs-icon-btn" data-item-id="' + item.id + '" title="Segna come Completato" style="padding: 6px 10px; margin: 0 2px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;"><span class="dashicons dashicons-yes" style="font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span></button>';

                            html += '<button class="rs-retry-single rs-icon-btn" data-item-id="' + item.id + '" title="Resetta a Pending" style="padding: 6px 10px; margin: 0 2px; background: #f0ad4e; color: white; border: none; border-radius: 4px; cursor: pointer;"><span class="dashicons dashicons-update" style="font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span></button>';

                            html += '<button class="rs-delete-single rs-icon-btn" data-item-id="' + item.id + '" title="Elimina dalla Queue" style="padding: 6px 10px; margin: 0 2px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;"><span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px; line-height: 1;"></span></button>';

                            html += '</td>';
                            html += '</tr>';

                            if (item.error_message) {
                                html += '<tr><td colspan="5" style="padding: 4px 8px; background: #fff3cd; font-size: 11px; color: #856404;">⚠️ ' + item.error_message + '</td></tr>';
                            }
                        });

                        html += '</tbody></table>';
                        $list.html(html);
                    }

                    $list.removeClass('d-none');
                }
            },
            complete: function() {
                $('#show-pending-details').html('<span class="dashicons dashicons-visibility"></span> Nascondi Dettaglio');
            }
        });
    });

    $('#retry-pending-items').on('click', function() {
        if (!currentSessionId) {
            alert('Nessuna sessione attiva');
            return;
        }

        if (!confirm('Reimpostare tutti gli elementi in sospeso a "pending" per riprocessarli?\n\nIl cron li riprocesserà entro 1 minuto.')) {
            return;
        }

        const $button = $(this);
        $button.prop('disabled', true).html('Reimpostazione...');

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_retry_failed_items',
                nonce: realestateSync.nonce,
                session_id: currentSessionId
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    refreshQueueImportStatus();
                    $('#pending-items-list').addClass('d-none');
                } else {
                    alert('Errore: ' + response.data);
                }
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-controls-repeat"></span> Resetta a Pending e Riprocessa');
            }
        });
    });

    $('#delete-pending-items').on('click', function() {
        if (!currentSessionId) {
            alert('Nessuna sessione attiva');
            return;
        }

        if (!confirm('⚠️ ATTENZIONE!\n\nEliminare DEFINITIVAMENTE tutti gli elementi in sospeso dalla queue?\n\nQuesta azione è IRREVERSIBILE!')) {
            return;
        }

        const $button = $(this);
        $button.prop('disabled', true).html('Eliminazione...');

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_delete_queue_items',
                nonce: realestateSync.nonce,
                session_id: currentSessionId
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    refreshQueueImportStatus();
                    $('#pending-items-list').addClass('d-none');
                } else {
                    alert('Errore: ' + response.data);
                }
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Elimina dalla Queue');
            }
        });
    });

    // Event delegation for single item retry button
    $(document).on('click', '.rs-retry-single', function() {
        const itemId = $(this).data('item-id');
        const $row = $('tr[data-item-id="' + itemId + '"]');
        const $btn = $(this);

        if (!confirm('Reimpostare questo elemento a "pending"?\n\nIl cron lo riprocesserà entro 1 minuto.')) {
            return;
        }

        $btn.prop('disabled', true);

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_retry_single_item',
                nonce: realestateSync.nonce,
                item_id: itemId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                    refreshQueueImportStatus();
                } else {
                    alert('Errore: ' + (response.data || 'Operazione fallita'));
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                alert('Errore di connessione');
                $btn.prop('disabled', false);
            }
        });
    });

    // Event delegation for single item mark as done button
    $(document).on('click', '.rs-mark-done', function() {
        const itemId = $(this).data('item-id');
        const $row = $('tr[data-item-id="' + itemId + '"]');
        const $btn = $(this);

        if (!confirm('Segnare questo elemento come COMPLETATO?\n\nNON verrà più riprocessato.')) {
            return;
        }

        $btn.prop('disabled', true);

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_mark_single_done',
                nonce: realestateSync.nonce,
                item_id: itemId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                    refreshQueueImportStatus();
                } else {
                    alert('Errore: ' + (response.data || 'Operazione fallita'));
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                alert('Errore di connessione');
                $btn.prop('disabled', false);
            }
        });
    });

    // Event delegation for single item delete button
    $(document).on('click', '.rs-delete-single', function() {
        const itemId = $(this).data('item-id');
        const $row = $('tr[data-item-id="' + itemId + '"]');
        const $btn = $(this);

        if (!confirm('Eliminare questo elemento dalla queue?\n\n⚠️ NON elimina il post WordPress, solo dalla coda.')) {
            return;
        }

        $btn.prop('disabled', true);

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_delete_single_item',
                nonce: realestateSync.nonce,
                item_id: itemId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                    refreshQueueImportStatus();
                } else {
                    alert('Errore: ' + (response.data || 'Operazione fallita'));
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                alert('Errore di connessione');
                $btn.prop('disabled', false);
            }
        });
    });

    $('#clear-all-queue').on('click', function() {
        if (!confirm('⚠️ ATTENZIONE!\n\nQuesto svuoterà COMPLETAMENTE la queue, eliminando tutti gli elementi (pending, processing, completed, failed).\n\nSei ASSOLUTAMENTE sicuro?')) {
            return;
        }

        const $button = $(this);
        $button.prop('disabled', true).html('Svuotamento...');

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_clear_all_queue',
                nonce: realestateSync.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    currentSessionId = null;
                    refreshQueueImportStatus();
                } else {
                    alert('Errore: ' + response.data);
                }
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> Svuota Tutta la Queue');
            }
        });
    });

    // ========================================================================
    // Cleanup Orphan Posts (post senza tracking)
    // ========================================================================

    let orphanPostsData = null; // Cache dei post trovati

    $('#scan-orphan-posts').on('click', function() {
        const $button = $(this);
        const $report = $('#orphan-posts-report');
        const $cleanupButton = $('#cleanup-orphan-posts');

        $button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Scansione...');
        $report.hide();
        $cleanupButton.hide();

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_scan_orphan_posts',
                nonce: realestateSync.nonce
            },
            success: function(response) {
                if (response.success) {
                    orphanPostsData = response.data;
                    const count = orphanPostsData.orphans.length;

                    let html = '<div style="padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">';

                    if (count === 0) {
                        html += '<p style="color: #46b450; font-weight: bold;">✅ Nessun post orfano trovato!</p>';
                        html += '<p style="font-size: 13px; color: #666;">Tutti i post hanno un record nella tracking table.</p>';
                    } else {
                        html += '<p style="color: #d63638; font-weight: bold;">⚠️ Trovati ' + count + ' post orfani:</p>';
                        html += '<ul style="max-height: 200px; overflow-y: auto; margin: 10px 0; padding-left: 20px;">';

                        orphanPostsData.orphans.forEach(function(post) {
                            html += '<li style="margin: 5px 0; font-size: 13px;">';
                            html += '<strong>ID ' + post.id + '</strong>: ' + post.title;
                            if (post.import_id) {
                                html += ' <span style="color: #666;">(import_id: ' + post.import_id + ')</span>';
                            }
                            html += '</li>';
                        });

                        html += '</ul>';

                        html += '<div style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 3px solid #f0ad4e;">';
                        html += '<p style="margin: 0; font-size: 13px;"><strong>Cosa verrà cancellato:</strong></p>';
                        html += '<ul style="margin: 5px 0 0 0; padding-left: 20px; font-size: 12px;">';
                        html += '<li>' + count + ' post (estate_property)</li>';
                        html += '<li>Tutti i meta associati</li>';
                        html += '<li>Tutte le immagini caricate (hook attivo)</li>';
                        html += '<li>Eventuali tracking (hook attivo)</li>';
                        html += '</ul>';
                        html += '</div>';

                        $cleanupButton.show();
                    }

                    html += '</div>';

                    $report.html(html).slideDown();

                } else {
                    alert('Errore: ' + (response.data || 'Errore sconosciuto'));
                }
            },
            error: function() {
                alert('Errore di connessione');
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Scansiona Post Orfani');
            }
        });
    });

    $('#cleanup-orphan-posts').on('click', function() {
        if (!orphanPostsData || orphanPostsData.orphans.length === 0) {
            alert('Nessun post da cancellare');
            return;
        }

        const count = orphanPostsData.orphans.length;

        if (!confirm('⚠️ ATTENZIONE!\n\nStai per cancellare PERMANENTEMENTE ' + count + ' post orfani.\n\nQuesta azione:\n- Cancella i post dal database\n- Cancella tutte le immagini associate\n- È IRREVERSIBILE\n\nSei ASSOLUTAMENTE sicuro?')) {
            return;
        }

        const $button = $(this);
        const $report = $('#orphan-posts-report');

        $button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Cancellazione...');

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_cleanup_orphan_posts',
                nonce: realestateSync.nonce,
                post_ids: orphanPostsData.orphans.map(p => p.id)
            },
            success: function(response) {
                if (response.success) {
                    const result = response.data;

                    let html = '<div style="padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px;">';
                    html += '<p style="color: #155724; font-weight: bold; margin: 0 0 10px 0;">✅ Cleanup completato!</p>';
                    html += '<ul style="margin: 0; padding-left: 20px; font-size: 13px;">';
                    html += '<li>Post cancellati: ' + result.deleted + '</li>';
                    html += '<li>Tracking puliti: ' + result.tracking_cleaned + '</li>';
                    html += '<li>Property IDs da reimportare: ' + result.property_ids.length + '</li>';
                    html += '</ul>';

                    if (result.property_ids.length > 0) {
                        html += '<div style="margin-top: 10px; padding: 10px; background: #fff3cd; border-left: 3px solid #f0ad4e;">';
                        html += '<p style="margin: 0 0 5px 0; font-size: 13px; font-weight: bold;">📋 Property da reimportare:</p>';
                        html += '<p style="margin: 0; font-size: 12px; font-family: monospace;">' + result.property_ids.join(', ') + '</p>';
                        html += '</div>';
                    }

                    html += '</div>';

                    $report.html(html);
                    $button.hide();
                    orphanPostsData = null;

                    // Refresh import status
                    setTimeout(refreshQueueImportStatus, 1000);

                } else {
                    alert('Errore: ' + (response.data || 'Errore sconosciuto'));
                }
            },
            error: function() {
                alert('Errore di connessione');
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Cancella Post Orfani');
            }
        });
    });

    // Auto-refresh status when entering Tools tab (Bootstrap tabs)
    $('button[data-bs-target="#tools"]').on('shown.bs.tab', function() {
        setTimeout(refreshQueueImportStatus, 300);
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // TOAST NOTIFICATION SYSTEM - FASE 3 Polish
    // ═══════════════════════════════════════════════════════════════════════════
    window.rsToast = {
        container: null,

        init: function() {
            if (!this.container) {
                this.container = $('<div class="rs-toast-container"></div>');
                $('body').append(this.container);
            }
        },

        show: function(message, type = 'info', title = null, duration = 5000) {
            this.init();

            // Icon mapping
            const icons = {
                success: 'yes-alt',
                error: 'dismiss',
                warning: 'warning',
                info: 'info'
            };

            // Default titles
            const titles = {
                success: 'Successo',
                error: 'Errore',
                warning: 'Attenzione',
                info: 'Informazione'
            };

            const toastTitle = title || titles[type];
            const icon = icons[type] || icons.info;

            const toast = $(`
                <div class="rs-toast rs-toast-${type}">
                    <span class="dashicons dashicons-${icon} rs-toast-icon"></span>
                    <div class="rs-toast-content">
                        <div class="rs-toast-title">${toastTitle}</div>
                        <div class="rs-toast-message">${message}</div>
                    </div>
                    <button class="rs-toast-close" aria-label="Chiudi">&times;</button>
                </div>
            `);

            // Close button handler
            toast.find('.rs-toast-close').on('click', function() {
                rsToast.hide(toast);
            });

            // Add to container
            this.container.append(toast);

            // Auto-hide after duration
            if (duration > 0) {
                setTimeout(() => {
                    this.hide(toast);
                }, duration);
            }

            return toast;
        },

        hide: function(toast) {
            toast.addClass('rs-toast-hiding');
            setTimeout(() => {
                toast.remove();
            }, 300);
        },

        success: function(message, title = null, duration = 5000) {
            return this.show(message, 'success', title, duration);
        },

        error: function(message, title = null, duration = 7000) {
            return this.show(message, 'error', title, duration);
        },

        warning: function(message, title = null, duration = 6000) {
            return this.show(message, 'warning', title, duration);
        },

        info: function(message, title = null, duration = 5000) {
            return this.show(message, 'info', title, duration);
        }
    };

    // ═══════════════════════════════════════════════════════════════════════════
    // CLEANUP DUPLICATE PROPERTIES (by property_import_id)
    // ═══════════════════════════════════════════════════════════════════════════

    let duplicatesData = null; // Cache dei duplicati trovati

    /**
     * Scan for duplicate properties
     */
    $('#scan-duplicates').on('click', function() {
        const $button = $(this);
        const $results = $('#duplicates-results');
        const $summary = $('#duplicates-summary');
        const $list = $('#duplicates-list');
        const $actionResult = $('#duplicates-action-result');

        $button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Scansione...');
        $results.addClass('d-none');
        $actionResult.addClass('d-none');

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_scan_duplicates',
                nonce: realestateSync.nonce
            },
            success: function(response) {
                if (response.success) {
                    duplicatesData = response.data;
                    const duplicateGroups = duplicatesData.duplicate_groups;
                    const totalDuplicates = duplicatesData.total_duplicates;
                    const groupCount = duplicatesData.group_count;

                    if (totalDuplicates === 0) {
                        $summary.removeClass('alert-warning').addClass('alert-success');
                        $summary.html('<strong>✅ Nessun duplicato trovato!</strong><br>Tutti i post hanno <code>property_import_id</code> univoco.');
                        $results.removeClass('d-none');
                    } else {
                        $summary.removeClass('alert-success').addClass('alert-warning');
                        $summary.html('<strong>⚠️ Trovati ' + totalDuplicates + ' post duplicati in ' + groupCount + ' gruppi</strong><br>' +
                            'Ogni gruppo ha lo stesso <code>property_import_id</code>.');

                        // Build duplicates list
                        let html = '';

                        duplicateGroups.forEach(function(group) {
                            const importId = group.import_id || 'N/A';
                            const posts = group.posts;

                            html += '<div class="card mb-3 border-warning">';
                            html += '<div class="card-header bg-warning bg-opacity-10">';
                            html += '<strong>Import ID: <code>' + importId + '</code></strong> ';
                            html += '<span class="badge bg-warning">' + posts.length + ' duplicati</span>';
                            html += '</div>';
                            html += '<div class="card-body p-0">';
                            html += '<table class="table table-sm table-hover mb-0">';
                            html += '<thead class="table-light">';
                            html += '<tr>';
                            html += '<th>Post ID</th>';
                            html += '<th>Titolo</th>';
                            html += '<th>Data</th>';
                            html += '<th>Azioni</th>';
                            html += '</tr>';
                            html += '</thead>';
                            html += '<tbody>';

                            posts.forEach(function(post) {
                                html += '<tr data-post-id="' + post.id + '">';
                                html += '<td><strong>#' + post.id + '</strong></td>';
                                html += '<td>' + post.title + '</td>';
                                html += '<td>' + post.date + '</td>';
                                html += '<td>';
                                if (post.permalink) {
                                    html += '<a href="' + post.permalink + '" target="_blank" class="btn btn-sm btn-info me-1" title="Vedi Frontend">';
                                    html += '<span class="dashicons dashicons-visibility"></span>';
                                    html += '</a>';
                                }
                                html += '<button class="btn btn-sm btn-danger delete-single-duplicate" data-post-id="' + post.id + '" title="Cancella Post">';
                                html += '<span class="dashicons dashicons-trash"></span>';
                                html += '</button>';
                                html += '</td>';
                                html += '</tr>';
                            });

                            html += '</tbody>';
                            html += '</table>';
                            html += '</div>';
                            html += '</div>';
                        });

                        $list.html(html);
                        $results.removeClass('d-none');
                    }
                } else {
                    alert('Errore: ' + (response.data || 'Errore sconosciuto'));
                }
            },
            error: function() {
                alert('Errore di connessione');
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Cerca Duplicati');
            }
        });
    });

    /**
     * Delete single duplicate post
     */
    $(document).on('click', '.delete-single-duplicate', function() {
        const postId = $(this).data('post-id');
        const $row = $('tr[data-post-id="' + postId + '"]');
        const $button = $(this);

        if (!confirm('⚠️ ATTENZIONE!\n\nCancellare PERMANENTEMENTE il post #' + postId + '?\n\nQuesta azione:\n- Cancella il post dal database\n- Cancella tracking e immagini (hook WP)\n- È IRREVERSIBILE')) {
            return;
        }

        $button.prop('disabled', true);

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_delete_duplicate_post',
                nonce: realestateSync.nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(300, function() {
                        $(this).remove();
                    });
                    rsToast.success('Post #' + postId + ' cancellato con successo');
                } else {
                    alert('Errore: ' + (response.data || 'Cancellazione fallita'));
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                alert('Errore di connessione');
                $button.prop('disabled', false);
            }
        });
    });

    /**
     * Delete all duplicates
     */
    $('#delete-all-duplicates').on('click', function() {
        if (!duplicatesData || duplicatesData.total_duplicates === 0) {
            alert('Nessun duplicato da cancellare');
            return;
        }

        const count = duplicatesData.total_duplicates;

        if (!confirm('⚠️ ATTENZIONE!\n\nCancellare PERMANENTEMENTE TUTTI i ' + count + ' post duplicati?\n\nQuesta azione:\n- Cancella TUTTI i duplicati trovati\n- Cancella tracking e immagini (hook WP)\n- È IRREVERSIBILE\n\nSei ASSOLUTAMENTE sicuro?')) {
            return;
        }

        const $button = $(this);
        const $actionResult = $('#duplicates-action-result');

        $button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Cancellazione...');
        $actionResult.addClass('d-none');

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_delete_all_duplicates',
                nonce: realestateSync.nonce,
                mode: 'all'
            },
            success: function(response) {
                if (response.success) {
                    const result = response.data;

                    let html = '<div class="alert alert-success">';
                    html += '<strong>✅ Cleanup completato!</strong><br>';
                    html += 'Post cancellati: <strong>' + result.deleted + '</strong><br>';
                    if (result.errors > 0) {
                        html += 'Errori: <strong>' + result.errors + '</strong>';
                    }
                    html += '</div>';

                    $actionResult.html(html).removeClass('d-none');
                    $('#duplicates-results').addClass('d-none');
                    duplicatesData = null;
                } else {
                    alert('Errore: ' + (response.data || 'Errore sconosciuto'));
                }
            },
            error: function() {
                alert('Errore di connessione');
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Cancella Tutti i Duplicati');
            }
        });
    });

    /**
     * Delete old duplicates (keep newest)
     */
    $('#delete-old-duplicates').on('click', function() {
        if (!duplicatesData || duplicatesData.total_duplicates === 0) {
            alert('Nessun duplicato da cancellare');
            return;
        }

        if (!confirm('⚠️ ATTENZIONE!\n\nCancellare i duplicati VECCHI mantenendo il più recente per ogni property_import_id?\n\nQuesta azione:\n- Mantiene il post più recente per ogni import_id\n- Cancella tutti gli altri duplicati\n- Cancella tracking e immagini (hook WP)\n- È IRREVERSIBILE\n\nContinuare?')) {
            return;
        }

        const $button = $(this);
        const $actionResult = $('#duplicates-action-result');

        $button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Cancellazione...');
        $actionResult.addClass('d-none');

        $.ajax({
            url: realestateSync.ajax_url,
            type: 'POST',
            data: {
                action: 'realestate_sync_delete_all_duplicates',
                nonce: realestateSync.nonce,
                mode: 'old'
            },
            success: function(response) {
                if (response.success) {
                    const result = response.data;

                    let html = '<div class="alert alert-success">';
                    html += '<strong>✅ Cleanup completato!</strong><br>';
                    html += 'Post vecchi cancellati: <strong>' + result.deleted + '</strong><br>';
                    html += 'Post recenti mantenuti: <strong>' + result.kept + '</strong><br>';
                    if (result.errors > 0) {
                        html += 'Errori: <strong>' + result.errors + '</strong>';
                    }
                    html += '</div>';

                    $actionResult.html(html).removeClass('d-none');
                    $('#duplicates-results').addClass('d-none');
                    duplicatesData = null;
                } else {
                    alert('Errore: ' + (response.data || 'Errore sconosciuto'));
                }
            },
            error: function() {
                alert('Errore di connessione');
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-clock"></span> Cancella Vecchi (Mantieni Più Recente)');
            }
        });
    });
});
