<?php
/**
 * Batch Orchestrator
 *
 * Shared batch processing logic used by all entry points.
 * Handles the complete workflow: Index → Filter → Queue → Process → Continuation
 *
 * This class ensures all three entry points (A: upload, B: manual download, C: cron download)
 * execute IDENTICAL processing logic after file acquisition.
 *
 * @package    RealEstate_Sync
 * @subpackage RealEstate_Sync/includes
 * @since      2.0.0
 */

class RealEstate_Sync_Batch_Orchestrator {

	/**
	 * Process XML file using batch system
	 *
	 * This is the SHARED function called by all entry points.
	 * Entry points differ ONLY in how they acquire the XML file.
	 * After acquisition, this function handles everything identically.
	 *
	 * Workflow:
	 * 1. Index XML and filter TN/BZ properties
	 * 2. Create queue in database
	 * 3. Process first batch immediately (10 items)
	 * 4. Setup cron continuation if needed
	 *
	 * @param string $xml_file      Absolute path to XML file
	 * @param bool   $mark_as_test  Whether to mark items as test (default: false)
	 * @param bool   $force_update  Whether to force updates (default: false)
	 * @return array Processing results with session_id, counts, and status
	 */
	public static function process_xml_batch( $xml_file, $mark_as_test = false, $force_update = false ) {

		// 🔍 Start debug trace
		$tracker = RealEstate_Sync_Debug_Tracker::get_instance();
		$trace_id = $tracker->start_trace('BATCH_ORCHESTRATOR', array(
			'xml_file' => basename($xml_file),
			'mark_as_test' => $mark_as_test,
			'force_update' => $force_update
		));

		// Generate unique session ID for this import
		$session_id = 'import_' . uniqid( '', true );

		$tracker->log_event('INFO', 'ORCHESTRATOR', 'Starting batch import', array(
			'session_id' => $session_id,
			'trace_id' => $trace_id,
			'xml_file' => $xml_file,
			'mark_as_test' => $mark_as_test,
			'force_update' => $force_update
		));

		// 💾 Save trace metadata for background continuation
		update_option('realestate_sync_current_trace_id', $trace_id, false);
		update_option('realestate_sync_current_trace_start_time', microtime(true), false);
		update_option('realestate_sync_current_trace_context', array(
			'session_id' => $session_id,
			'xml_file' => basename($xml_file),
			'mark_as_test' => $mark_as_test,
			'force_update' => $force_update
		), false);

		// ═════════════════════════════════════════════════════════
		// STEP 1: INDEX & FILTER (TN/BZ only)
		// ═════════════════════════════════════════════════════════
		$tracker->log_event('INFO', 'ORCHESTRATOR', 'STEP 1: Indexing XML and filtering TN/BZ');

		// Load XML
		$xml = simplexml_load_file( $xml_file );
		if ( ! $xml ) {
			$tracker->log_event('ERROR', 'ORCHESTRATOR', 'Failed to load XML file', array('file' => $xml_file));
			$tracker->end_trace('error', array('error' => 'Failed to load XML file'));
			return array(
				'success' => false,
				'error'   => 'Failed to load XML file',
			);
		}

		// Get enabled provinces from settings
		$settings          = get_option( 'realestate_sync_settings', array() );
		$enabled_provinces = $settings['enabled_provinces'] ?? array( 'TN', 'BZ' );

		$tracker->log_event('INFO', 'ORCHESTRATOR', 'Enabled provinces', array('provinces' => $enabled_provinces));

		// Index agencies (with province filter applied by Agency_Parser)
		$agency_parser = new RealEstate_Sync_Agency_Parser();
		$agencies      = $agency_parser->extract_agencies_from_xml( $xml );

		$tracker->log_event('INFO', 'ORCHESTRATOR', 'Agencies found', array('count' => count($agencies)));

		// ✨ v1.7.1: Index deleted agencies for removal (AFTER province filter)
		// Agency Parser skips deleted=1, but we need to track them for deletion
		$agencies_deleted = array();
		$agency_ids_seen = array();

		foreach ( $xml->annuncio as $annuncio ) {
			// Filter by province (same logic as Agency_Parser)
			$comune_istat = (string) ( $annuncio->info->comune_istat ?? '' );
			$prefix = substr( $comune_istat, 0, 3 );
			$is_tn = ( $prefix === '022' && in_array( 'TN', $enabled_provinces ) );
			$is_bz = ( $prefix === '021' && in_array( 'BZ', $enabled_provinces ) );

			if ( ! $is_tn && ! $is_bz ) {
				continue; // Not in enabled provinces
			}

			// Check if agency exists and is deleted
			if ( isset( $annuncio->agenzia ) ) {
				$agency_id = (string) ( $annuncio->agenzia->id ?? '' );
				$is_deleted = ( (string) ( $annuncio->agenzia->deleted ?? '' ) === '1' );

				if ( ! empty( $agency_id ) && $is_deleted && ! isset( $agency_ids_seen[ $agency_id ] ) ) {
					$agencies_deleted[] = $agency_id;
					$agency_ids_seen[ $agency_id ] = true;

					$tracker->log_event('DEBUG', 'ORCHESTRATOR', 'Agency marked for deletion', array(
						'agency_id' => $agency_id
					));
				}
			}
		}

		$tracker->log_event('INFO', 'ORCHESTRATOR', 'Deleted agencies found', array('count' => count($agencies_deleted)));

		// Index properties (filter by comune_istat)
		// ✅ OPTIMIZATION: Parse property data NOW to avoid re-loading 324MB XML later
		$properties      = array();      // Active properties (will be queued)
		$properties_data = array();      // Store FULL property data
		$properties_deleted = array();   // ✨ v1.7.1: Deleted properties (will be removed)
		$skipped_count   = 0;
		$deleted_count   = 0;

		// Initialize XML Parser for property parsing
		$xml_parser = new RealEstate_Sync_XML_Parser();

		foreach ( $xml->annuncio as $annuncio ) {

			$property_id = (string) $annuncio->info->id;
			if ( empty( $property_id ) ) {
				continue;
			}

			// Filter by province FIRST (before checking deleted status)
			$comune_istat = (string) ( $annuncio->info->comune_istat ?? '' );
			$prefix       = substr( $comune_istat, 0, 3 );

			// Check if property is in enabled provinces
			// 022xxx = Provincia di Trento (TN)
			// 021xxx = Provincia di Bolzano (BZ)
			$is_tn = ( $prefix === '022' && in_array( 'TN', $enabled_provinces ) );
			$is_bz = ( $prefix === '021' && in_array( 'BZ', $enabled_provinces ) );

			if ( ! $is_tn && ! $is_bz ) {
				// Not in enabled provinces - skip completely
				$skipped_count++;
				continue;
			}

			// ✨ v1.7.1: Separate active vs deleted properties (AFTER province filter)
			$is_deleted = ( (string) $annuncio->info->deleted === '1' );

			if ( $is_deleted ) {
				// Deleted property in TN/BZ - collect for deletion
				$properties_deleted[] = $property_id;
				$deleted_count++;

				$tracker->log_event('DEBUG', 'ORCHESTRATOR', 'Property marked for deletion', array(
					'property_id' => $property_id,
					'comune_istat' => $comune_istat
				));
			} else {
				// Active property - add to queue list
				$properties[] = $property_id;

				// ✅ Parse FULL property data NOW (avoid re-loading XML later)
				$property_data = $xml_parser->parse_annuncio_xml( $annuncio->asXML() );
				if ( $property_data ) {
					$properties_data[ $property_id ] = $property_data;
				}
			}
		}

		$tracker->log_event('INFO', 'ORCHESTRATOR', 'Properties indexed', array(
			'active' => count($properties),
			'deleted' => count($properties_deleted),
			'skipped_provinces' => $skipped_count
		));

		// ═════════════════════════════════════════════════════════
		// STEP 1b: HANDLE DELETIONS (Before queue creation)
		// ═════════════════════════════════════════════════════════
		// ✨ v1.7.1: Delete properties and agencies marked as deleted=1 in XML
		// User requirement: "dopo il filtro per provincia, gli annunci con del=1 vanno cancellati"

		$deletion_stats = array();

		if ( count($properties_deleted) > 0 || count($agencies_deleted) > 0 ) {
			$tracker->log_event('INFO', 'ORCHESTRATOR', 'STEP 1b: Handling deletions', array(
				'properties_to_delete' => count($properties_deleted),
				'agencies_to_delete' => count($agencies_deleted)
			));

			// Initialize Deletion Manager (always LIVE deletion)
			require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-realestate-sync-deletion-manager.php';
			$deletion_manager = new RealEstate_Sync_Deletion_Manager();

			// Handle deleted properties (with attachments)
			if ( count($properties_deleted) > 0 ) {
				$tracker->log_event('INFO', 'ORCHESTRATOR', 'Processing deleted properties', array(
					'count' => count($properties_deleted)
				));

				$property_stats = $deletion_manager->handle_deleted_properties( $properties_deleted, $session_id );
				$deletion_stats['properties'] = $property_stats;

				$tracker->log_event('INFO', 'ORCHESTRATOR', 'Properties deletion complete', $property_stats);
			}

			// Handle deleted agencies (with featured images)
			if ( count($agencies_deleted) > 0 ) {
				$tracker->log_event('INFO', 'ORCHESTRATOR', 'Processing deleted agencies', array(
					'count' => count($agencies_deleted)
				));

				$agency_stats = $deletion_manager->handle_deleted_agencies( $agencies_deleted, $session_id );
				$deletion_stats['agencies'] = $agency_stats;

				$tracker->log_event('INFO', 'ORCHESTRATOR', 'Agencies deletion complete', $agency_stats);
			}
		} else {
			$tracker->log_event('INFO', 'ORCHESTRATOR', 'STEP 1b: No deletions needed', array(
				'properties_deleted' => 0,
				'agencies_deleted' => 0
			));
		}

		// ═════════════════════════════════════════════════════════
		// STEP 2: CREATE QUEUE
		// ═════════════════════════════════════════════════════════
		$tracker->log_event('INFO', 'ORCHESTRATOR', 'STEP 2: Creating queue');

		$queue_manager = new RealEstate_Sync_Queue_Manager();

		// Clear any existing queue for this session
		$queue_manager->clear_session_queue( $session_id );

		// ✨ Initialize tracking manager for hash pre-filtering (v1.7.0+)
		$tracking_manager = new RealEstate_Sync_Tracking_Manager();

		// Add agencies to queue (WITH HASH PRE-FILTERING OPTIMIZATION)
		// ✨ v1.7.0+: Only queue agencies that have actual changes
		$agencies_queued = 0;
		$agencies_failed = 0;
		$agencies_skipped_no_changes = 0;

		foreach ( $agencies as $agency ) {
			// ✨ OPTIMIZATION: Calculate hash and check for changes BEFORE queueing
			try {
				$hash = $tracking_manager->calculate_agency_hash( $agency );
				$change_check = $tracking_manager->check_agency_changes( $agency['id'], $hash );

				// ✨ Only queue if agency has changes
				if ( $change_check['has_changed'] ) {
					$result = $queue_manager->add_agency( $session_id, $agency['id'] );
					if ( $result ) {
						$agencies_queued++;

						$tracker->log_event('DEBUG', 'ORCHESTRATOR', 'Agency queued', array(
							'agency_id' => $agency['id'],
							'action' => $change_check['action'],
							'reason' => $change_check['reason']
						));
					} else {
						$agencies_failed++;
						$tracker->log_event('ERROR', 'ORCHESTRATOR', 'Failed to add agency to queue', array(
							'agency_id' => $agency['id'],
							'wpdb_error' => $GLOBALS['wpdb']->last_error
						));
					}
				} else {
					// ✨ Skip agencies with no changes (optimization!)
					$agencies_skipped_no_changes++;

					$tracker->log_event('DEBUG', 'ORCHESTRATOR', 'Agency skipped (no changes)', array(
						'agency_id' => $agency['id'],
						'reason' => $change_check['reason']
					));
				}
			} catch ( Exception $e ) {
				// Fallback: if hash check fails, queue anyway to avoid data loss
				$tracker->log_event('ERROR', 'ORCHESTRATOR', 'Agency hash check failed - queueing as fallback', array(
					'agency_id' => $agency['id'],
					'error' => $e->getMessage()
				));

				$result = $queue_manager->add_agency( $session_id, $agency['id'] );
				if ( $result ) {
					$agencies_queued++;
				}
			}
		}

		// ✨ Log agency optimization statistics
		if ( count( $agencies ) > 0 ) {
			$tracker->log_event('INFO', 'ORCHESTRATOR', 'Agency pre-filtering complete', array(
				'total_agencies' => count( $agencies ),
				'queued' => $agencies_queued,
				'skipped_no_changes' => $agencies_skipped_no_changes,
				'failed' => $agencies_failed,
				'optimization_rate' => round( ( $agencies_skipped_no_changes / count( $agencies ) ) * 100, 2 ) . '%'
			));

			error_log( "[BATCH-ORCHESTRATOR] ✅ Agency optimization: Skipped $agencies_skipped_no_changes agencies (no changes detected)" );
			error_log( "[BATCH-ORCHESTRATOR] ✅ Queued $agencies_queued agencies (have changes)" );
		}

		// Add properties to queue (WITH HASH PRE-FILTERING OPTIMIZATION)
		// ✨ v1.7.0: Only queue properties that have actual changes
		$properties_queued = 0;
		$properties_failed = 0;
		$properties_skipped_no_changes = 0;

		foreach ( $properties as $property_id ) {
			// Get parsed property data (already available from line 116-119)
			$property_data = $properties_data[ $property_id ] ?? null;

			if ( ! $property_data ) {
				$tracker->log_event('WARNING', 'ORCHESTRATOR', 'Property data not found for pre-filtering', array(
					'property_id' => $property_id
				));
				// Fallback: queue it anyway to avoid missing items
				$result = $queue_manager->add_property( $session_id, $property_id );
				if ( $result ) {
					$properties_queued++;
				}
				continue;
			}

			// ✨ OPTIMIZATION: Calculate hash and check for changes BEFORE queueing
			try {
				$hash = $tracking_manager->calculate_property_hash( $property_data );
				$change_check = $tracking_manager->check_property_changes( $property_id, $hash );

				// ✨ Only queue if property has changes
				if ( $change_check['has_changed'] ) {
					$result = $queue_manager->add_property( $session_id, $property_id );
					if ( $result ) {
						$properties_queued++;

						$tracker->log_event('DEBUG', 'ORCHESTRATOR', 'Property queued', array(
							'property_id' => $property_id,
							'action' => $change_check['action'],
							'reason' => $change_check['reason']
						));
					} else {
						$properties_failed++;
						$tracker->log_event('ERROR', 'ORCHESTRATOR', 'Failed to add property to queue', array(
							'property_id' => $property_id,
							'wpdb_error' => $GLOBALS['wpdb']->last_error
						));
					}
				} else {
					// ✨ Skip properties with no changes (optimization!)
					$properties_skipped_no_changes++;

					$tracker->log_event('DEBUG', 'ORCHESTRATOR', 'Property skipped (no changes)', array(
						'property_id' => $property_id,
						'reason' => $change_check['reason']
					));
				}
			} catch ( Exception $e ) {
				// Fallback: if hash check fails, queue anyway to avoid data loss
				$tracker->log_event('ERROR', 'ORCHESTRATOR', 'Hash check failed - queueing as fallback', array(
					'property_id' => $property_id,
					'error' => $e->getMessage()
				));

				$result = $queue_manager->add_property( $session_id, $property_id );
				if ( $result ) {
					$properties_queued++;
				}
			}
		}

		// ✨ Log optimization statistics
		$tracker->log_event('INFO', 'ORCHESTRATOR', 'Pre-filtering complete', array(
			'total_properties' => count( $properties ),
			'queued' => $properties_queued,
			'skipped_no_changes' => $properties_skipped_no_changes,
			'failed' => $properties_failed,
			'optimization_rate' => count( $properties ) > 0 ? round( ( $properties_skipped_no_changes / count( $properties ) ) * 100, 2 ) . '%' : '0%'
		));

		error_log( "[BATCH-ORCHESTRATOR] ✅ Queue optimization: Skipped $properties_skipped_no_changes properties (no changes detected)" );
		error_log( "[BATCH-ORCHESTRATOR] ✅ Queued $properties_queued properties (have changes)" );

		$total_queued = $agencies_queued + $properties_queued;

		$tracker->log_event('INFO', 'ORCHESTRATOR', 'Queue created', array(
			'agencies' => $agencies_queued,
			'agencies_skipped' => $agencies_skipped_no_changes, // ✨ v1.7.0+
			'properties' => $properties_queued,
			'properties_skipped' => $properties_skipped_no_changes, // ✨ v1.7.0
			'total' => $total_queued,
			'agencies_failed' => $agencies_failed,
			'properties_failed' => $properties_failed,
			'optimization_enabled' => true // ✨ v1.7.0
		));

		// ═════════════════════════════════════════════════════════
		// STEP 2b: SAVE PARSED DATA (Avoid re-loading 324MB XML)
		// ═════════════════════════════════════════════════════════
		$tracker->log_event('INFO', 'ORCHESTRATOR', 'Saving parsed data to database');

		// Store agencies and properties data for Batch Processor
		update_option( "realestate_sync_batch_data_{$session_id}", array(
			'agencies'   => $agencies,
			'properties' => $properties_data,
		), false ); // autoload = false (don't load on every page)

		$tracker->log_event('INFO', 'ORCHESTRATOR', 'Parsed data saved', array(
			'agencies_count' => count($agencies),
			'properties_count' => count($properties_data)
		));

		// ═════════════════════════════════════════════════════════
		// STEP 3: PROCESS FIRST BATCH (Immediate)
		// ═════════════════════════════════════════════════════════
		$tracker->log_event('INFO', 'ORCHESTRATOR', 'STEP 3: Processing first batch (immediate)');

		// Save import progress metadata
		update_option( 'realestate_sync_background_import_progress', array(
			'session_id'    => $session_id,
			'xml_file_path' => $xml_file,
			'mark_as_test'  => $mark_as_test,
			'start_time'    => time(),
			'status'        => 'processing',
			'total_items'   => $total_queued,
		) );

		// Process first batch immediately (max 10 items)
		$batch_processor   = new RealEstate_Sync_Batch_Processor( $session_id, $xml_file, $mark_as_test );
		$first_batch_result = $batch_processor->process_next_batch();

		$tracker->log_event('INFO', 'ORCHESTRATOR', 'First batch complete', array(
			'processed' => $first_batch_result['processed'],
			'agencies' => $first_batch_result['agencies_processed'] ?? 0,
			'properties' => $first_batch_result['properties_processed'] ?? 0,
			'complete' => $first_batch_result['complete']
		));

		// ═════════════════════════════════════════════════════════
		// STEP 4: SETUP CONTINUATION (Cron)
		// ═════════════════════════════════════════════════════════
		if ( ! $first_batch_result['complete'] ) {
			error_log( "[BATCH-ORCHESTRATOR] STEP 4: Continuation setup - queue is source of truth" );

			// ✅ NO TRANSIENT NEEDED - Cron checks queue directly!
			// Queue with pending items = continuation will happen automatically
			// More robust than transients (no cache dependency)

			error_log( "[BATCH-ORCHESTRATOR] Remaining items in queue: " . ( $total_queued - $first_batch_result['processed'] ) );
			error_log( "[BATCH-ORCHESTRATOR] Cron will continue processing on next run" );
		} else {
			error_log( "[BATCH-ORCHESTRATOR] All items processed in first batch - COMPLETE!" );

			// Update progress to completed
			update_option( 'realestate_sync_background_import_progress', array(
				'session_id'    => $session_id,
				'xml_file_path' => $xml_file,
				'mark_as_test'  => $mark_as_test,
				'start_time'    => time(),
				'status'        => 'completed',
				'total_items'   => $total_queued,
			) );
		}

		// 🔍 Log orchestrator completion (but keep trace OPEN for background batches)
		$tracker->log_event('INFO', 'ORCHESTRATOR', 'Orchestrator phase complete, background continuation will follow', array(
			'session_id' => $session_id,
			'total_queued' => $total_queued,
			'agencies_queued' => $agencies_queued,
			'properties_queued' => $properties_queued,
			'first_batch_processed' => $first_batch_result['processed'],
			'complete' => $first_batch_result['complete'],
			'remaining' => $total_queued - $first_batch_result['processed'],
			'deletion_stats' => $deletion_stats // ✨ v1.7.1
		));

		// ⚠️ DO NOT call end_trace() here!
		// The trace will be resumed by background batch processor
		// and closed only when ALL batches are complete.

		// Return results
		return array(
			'success'                => true,
			'session_id'             => $session_id,
			'total_queued'           => $total_queued,
			'agencies_queued'        => $agencies_queued,
			'properties_queued'      => $properties_queued,
			'first_batch_processed'  => $first_batch_result['processed'],
			'agencies_processed'     => $first_batch_result['agencies_processed'] ?? 0,
			'properties_processed'   => $first_batch_result['properties_processed'] ?? 0,
			'complete'               => $first_batch_result['complete'],
			'remaining'              => $total_queued - $first_batch_result['processed'],
			'deletion_stats'         => $deletion_stats, // ✨ v1.7.1: Deletion statistics
		);
	}
}
