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
	 * @return array Processing results with session_id, counts, and status
	 */
	public static function process_xml_batch( $xml_file, $mark_as_test = false ) {

		// 🔍 Start debug trace
		$tracker = RealEstate_Sync_Debug_Tracker::get_instance();
		$trace_id = $tracker->start_trace('BATCH_ORCHESTRATOR', array(
			'xml_file' => basename($xml_file),
			'mark_as_test' => $mark_as_test
		));

		// Generate unique session ID for this import
		$session_id = 'import_' . uniqid( '', true );

		$tracker->log_event('INFO', 'ORCHESTRATOR', 'Starting batch import', array(
			'session_id' => $session_id,
			'trace_id' => $trace_id,
			'xml_file' => $xml_file,
			'mark_as_test' => $mark_as_test
		));

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

		// Index properties (filter by comune_istat)
		$properties      = array();
		$skipped_count   = 0;
		$deleted_count   = 0;

		foreach ( $xml->annuncio as $annuncio ) {

			// Skip deleted items
			if ( (string) $annuncio->deleted === '1' ) {
				$deleted_count++;
				continue;
			}

			// Filter by province (comune_istat)
			$comune_istat = (string) ( $annuncio->info->comune_istat ?? '' );
			$prefix       = substr( $comune_istat, 0, 3 );

			// Check if property is in enabled provinces
			// 022xxx = Provincia di Trento (TN)
			// 021xxx = Provincia di Bolzano (BZ)
			$is_tn = ( $prefix === '022' && in_array( 'TN', $enabled_provinces ) );
			$is_bz = ( $prefix === '021' && in_array( 'BZ', $enabled_provinces ) );

			if ( $is_tn || $is_bz ) {
				$property_id = (string) $annuncio->info->id;
				if ( ! empty( $property_id ) ) {
					$properties[] = $property_id;
				}
			} else {
				$skipped_count++;
			}
		}

		$tracker->log_event('INFO', 'ORCHESTRATOR', 'Properties indexed', array(
			'found' => count($properties),
			'skipped_provinces' => $skipped_count,
			'deleted' => $deleted_count
		));

		// ═════════════════════════════════════════════════════════
		// STEP 2: CREATE QUEUE
		// ═════════════════════════════════════════════════════════
		$tracker->log_event('INFO', 'ORCHESTRATOR', 'STEP 2: Creating queue');

		$queue_manager = new RealEstate_Sync_Queue_Manager();

		// Clear any existing queue for this session
		$queue_manager->clear_session_queue( $session_id );

		// Add agencies to queue (higher priority - process first)
		$agencies_queued = 0;
		foreach ( $agencies as $agency ) {
			$queue_manager->add_agency( $session_id, $agency['id'] );
			$agencies_queued++;
		}

		// Add properties to queue
		$properties_queued = 0;
		foreach ( $properties as $property_id ) {
			$queue_manager->add_property( $session_id, $property_id );
			$properties_queued++;
		}

		$total_queued = $agencies_queued + $properties_queued;

		$tracker->log_event('INFO', 'ORCHESTRATOR', 'Queue created', array(
			'agencies' => $agencies_queued,
			'properties' => $properties_queued,
			'total' => $total_queued
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
			error_log( "[BATCH-ORCHESTRATOR] STEP 4: Setting up cron continuation" );

			// Set transient for cron to pick up
			// Transient expires in 300 seconds (5 minutes)
			set_transient( 'realestate_sync_pending_batch', $session_id, 300 );

			error_log( "[BATCH-ORCHESTRATOR] Transient set - cron will continue processing" );
			error_log( "[BATCH-ORCHESTRATOR] Remaining items: " . ( $total_queued - $first_batch_result['processed'] ) );
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

		// 🔍 End debug trace
		$tracker->end_trace('completed', array(
			'session_id' => $session_id,
			'total_queued' => $total_queued,
			'agencies_queued' => $agencies_queued,
			'properties_queued' => $properties_queued,
			'first_batch_processed' => $first_batch_result['processed'],
			'complete' => $first_batch_result['complete'],
			'remaining' => $total_queued - $first_batch_result['processed']
		));

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
		);
	}
}
