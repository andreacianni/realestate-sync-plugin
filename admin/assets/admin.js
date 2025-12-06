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
                    alert('Proprietà ignorata con successo');
                } else {
                    alert('Errore: ' + (response.data || 'Errore sconosciuto'));
                }
            },
            error: function() {
                alert('Errore di comunicazione con il server');
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

        if (isEnabled) {
            $config.css({
                'opacity': '1',
                'pointer-events': 'auto'
            });
        } else {
            $config.css({
                'opacity': '0.5',
                'pointer-events': 'none'
            });
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

    $('#show-pending-details').on('click', function() {
        const $list = $('#pending-items-list');

        if (!$list.hasClass('rs-hidden')) {
            $list.addClass('rs-hidden');
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

                    $list.removeClass('rs-hidden');
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
                    refreshImportStatus();
                    $('#pending-items-list').addClass('rs-hidden');
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
                    refreshImportStatus();
                    $('#pending-items-list').addClass('rs-hidden');
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
                    refreshImportStatus();
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
                    refreshImportStatus();
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
                    refreshImportStatus();
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
                    refreshImportStatus();
                } else {
                    alert('Errore: ' + response.data);
                }
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-warning"></span> Svuota Tutta la Queue');
            }
        });
    });

    // Auto-refresh status when entering Tools tab
    $('.nav-tab[data-tab="tools"]').on('click', function() {
        setTimeout(refreshImportStatus, 300);
    });
});
