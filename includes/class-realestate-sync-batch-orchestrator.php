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

		// Generate unique session ID for this import
		$session_id = 'import_' . uniqid( '', true );

		error_log( "[BATCH-ORCHESTRATOR] ========================================" );
		error_log( "[BATCH-ORCHESTRATOR] Starting batch import: {$session_id}" );
		error_log( "[BATCH-ORCHESTRATOR] XML file: {$xml_file}" );
		error_log( "[BATCH-ORCHESTRATOR] Mark as test: " . ( $mark_as_test ? 'YES' : 'NO' ) );
		error_log( "[BATCH-ORCHESTRATOR] ========================================" );

		// ═════════════════════════════════════════════════════════
		// STEP 1: INDEX & FILTER (TN/BZ only)
		// ═════════════════════════════════════════════════════════
		error_log( "[BATCH-ORCHESTRATOR] STEP 1: Indexing XML and filtering TN/BZ" );

		// Load XML
		$xml = simplexml_load_file( $xml_file );
		if ( ! $xml ) {
			error_log( "[BATCH-ORCHESTRATOR] ❌ ERROR: Failed to load XML file" );
			return array(
				'success' => false,
				'error'   => 'Failed to load XML file',
			);
		}

		// Get enabled provinces from settings
		$settings          = get_option( 'realestate_sync_settings', array() );
		$enabled_provinces = $settings['enabled_provinces'] ?? array( 'TN', 'BZ' );

		error_log( "[BATCH-ORCHESTRATOR] Enabled provinces: " . implode( ', ', $enabled_provinces ) );

		// Index agencies (with province filter applied by Agency_Parser)
		$agency_parser = new RealEstate_Sync_Agency_Parser();
		$agencies      = $agency_parser->extract_agencies_from_xml( $xml );

		error_log( "[BATCH-ORCHESTRATOR] Agencies found: " . count( $agencies ) );

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

		error_log( "[BATCH-ORCHESTRATOR] Properties found (TN/BZ): " . count( $properties ) );
		error_log( "[BATCH-ORCHESTRATOR] Properties skipped (other provinces): {$skipped_count}" );
		error_log( "[BATCH-ORCHESTRATOR] Deleted items skipped: {$deleted_count}" );

		// ═════════════════════════════════════════════════════════
		// STEP 2: CREATE QUEUE
		// ═════════════════════════════════════════════════════════
		error_log( "[BATCH-ORCHESTRATOR] STEP 2: Creating queue" );

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

		error_log( "[BATCH-ORCHESTRATOR] Queue created: {$agencies_queued} agencies + {$properties_queued} properties = {$total_queued} total items" );

		// ═════════════════════════════════════════════════════════
		// STEP 3: PROCESS FIRST BATCH (Immediate)
		// ═════════════════════════════════════════════════════════
		error_log( "[BATCH-ORCHESTRATOR] STEP 3: Processing first batch (immediate)" );

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

		error_log( "[BATCH-ORCHESTRATOR] First batch complete:" );
		error_log( "[BATCH-ORCHESTRATOR] - Processed: {$first_batch_result['processed']}" );
		error_log( "[BATCH-ORCHESTRATOR] - Agencies: " . ( $first_batch_result['agencies_processed'] ?? 0 ) );
		error_log( "[BATCH-ORCHESTRATOR] - Properties: " . ( $first_batch_result['properties_processed'] ?? 0 ) );
		error_log( "[BATCH-ORCHESTRATOR] - Complete: " . ( $first_batch_result['complete'] ? 'YES' : 'NO' ) );

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

		error_log( "[BATCH-ORCHESTRATOR] ========================================" );
		error_log( "[BATCH-ORCHESTRATOR] Batch orchestration complete" );
		error_log( "[BATCH-ORCHESTRATOR] ========================================" );

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
