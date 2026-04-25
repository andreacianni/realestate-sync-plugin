<?php
/**
 * Attachment Cleanup Manager
 *
 * Automatically deletes all attachments (images + thumbnails) when a property or agency is deleted.
 * This prevents orphaned images from wasting disk space.
 *
 * @package RealEstate_Sync
 * @since 1.7.2
 */

class RealEstate_Sync_Attachment_Cleanup {

	/**
	 * Initialize hooks
	 */
	public static function init() {
		// Hook before post deletion (properties and agencies)
		add_action( 'before_delete_post', array( __CLASS__, 'cleanup_attachments_on_delete' ), 10, 2 );

		// Log initialization
		error_log( '[ATTACHMENT-CLEANUP] Hooks initialized - will clean attachments on post deletion' );
	}

	/**
	 * Cleanup attachments when a property or agency is deleted
	 *
	 * Triggered by WordPress before_delete_post action.
	 * Only runs for estate_property, estate_agent, and estate_agency post types.
	 *
	 * @param int     $post_id Post ID being deleted
	 * @param WP_Post $post    Post object being deleted
	 */
	public static function cleanup_attachments_on_delete( $post_id, $post ) {

		// Only process our custom post types
		if ( ! in_array( $post->post_type, array( 'estate_property', 'estate_agent', 'estate_agency' ) ) ) {
			return;
		}

		$post_type_label = ( $post->post_type === 'estate_property' ) ? 'Property' : 'Agency';
		$property_import_id = (string) get_post_meta( $post_id, 'property_import_id', true );
		if ( '' === $property_import_id ) {
			$property_import_id = (string) get_post_meta( $post_id, 'agency_xml_id', true );
		}

		error_log( "[ATTACHMENT-CLEANUP] Processing deletion of {$post_type_label} ID: {$post_id}" );

		// Get all attached media
		$attachments = get_attached_media( '', $post_id );

		if ( empty( $attachments ) ) {
			error_log( "[ATTACHMENT-CLEANUP] No attachments found for {$post_type_label} {$post_id}" );
			return;
		}

		$attachment_count = count( $attachments );
		$deleted_count = 0;
		$total_size = 0;

		error_log( "[ATTACHMENT-CLEANUP] Found {$attachment_count} attachments for {$post_type_label} {$post_id}" );

		// Delete each attachment
		foreach ( $attachments as $attachment ) {
			$attachment_id = $attachment->ID;
			$audit_before = self::build_delete_media_audit_snapshot( $post_id, $attachment_id, $property_import_id );
			self::log_delete_media_audit( $post_id, $attachment_id, 'before_wp_delete_attachment', $audit_before );
			$total_size += (int) ( $audit_before['file_size_bytes'] ?? 0 );

			// Delete attachment (force=true deletes file + all thumbnails)
			$deleted = wp_delete_attachment( $attachment_id, true );
			$audit_after = self::build_delete_media_audit_after_snapshot( $audit_before, $attachment_id );
			$audit_after['wp_delete_attachment_result'] = (bool) $deleted;
			self::log_delete_media_audit( $post_id, $attachment_id, 'after_wp_delete_attachment', $audit_after );

			if ( $deleted ) {
				$deleted_count++;
				error_log( "[ATTACHMENT-CLEANUP] Deleted attachment {$attachment_id}: " . ( $audit_before['absolute_path'] ?? '' ) );
			} else {
				error_log( "[ATTACHMENT-CLEANUP] Failed to delete attachment {$attachment_id}: " . ( $audit_before['absolute_path'] ?? '' ) );
			}
		}

		// Summary
		$mb_freed = round( $total_size / 1024 / 1024, 2 );
		error_log( "[ATTACHMENT-CLEANUP] Summary for {$post_type_label} {$post_id}:" );
		error_log( "[ATTACHMENT-CLEANUP]   Attachments deleted: {$deleted_count}/{$attachment_count}" );
		error_log( "[ATTACHMENT-CLEANUP]   Disk space freed: {$mb_freed} MB" );
	}

	/**
	 * Manual cleanup for specific post ID
	 *
	 * Can be called directly from scripts for testing or manual cleanup.
	 *
	 * @param int $post_id Post ID to cleanup
	 * @return array Statistics
	 */
	public static function manual_cleanup( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return array(
				'success' => false,
				'error' => 'Post not found'
			);
		}

		$attachments = get_attached_media( '', $post_id );
		$stats = array(
			'post_id' => $post_id,
			'post_type' => $post->post_type,
			'post_title' => $post->post_title,
			'attachments_found' => count( $attachments ),
			'attachments_deleted' => 0,
			'disk_space_freed' => 0,
			'errors' => 0
		);

		foreach ( $attachments as $attachment ) {
			$property_import_id = (string) get_post_meta( $post_id, 'property_import_id', true );
			if ( '' === $property_import_id ) {
				$property_import_id = (string) get_post_meta( $post_id, 'agency_xml_id', true );
			}
			$audit_before = self::build_delete_media_audit_snapshot( $post_id, $attachment->ID, $property_import_id );
			self::log_delete_media_audit( $post_id, $attachment->ID, 'before_wp_delete_attachment', $audit_before );
			$stats['disk_space_freed'] += (int) ( $audit_before['file_size_bytes'] ?? 0 );

			$deleted = wp_delete_attachment( $attachment->ID, true );
			$audit_after = self::build_delete_media_audit_after_snapshot( $audit_before, $attachment->ID );
			$audit_after['wp_delete_attachment_result'] = (bool) $deleted;
			self::log_delete_media_audit( $post_id, $attachment->ID, 'after_wp_delete_attachment', $audit_after );

			if ( $deleted ) {
				$stats['attachments_deleted']++;
			} else {
				$stats['errors']++;
			}
		}

		$stats['disk_space_freed_mb'] = round( $stats['disk_space_freed'] / 1024 / 1024, 2 );

		return $stats;
	}

	/**
	 * Find orphaned attachments
	 *
	 * Returns attachments with post_parent that doesn't exist.
	 *
	 * @return array Array of attachment IDs
	 */
	public static function find_orphaned_attachments() {
		global $wpdb;

		// Find attachments where post_parent doesn't exist
		$orphaned = $wpdb->get_col( "
			SELECT a.ID
			FROM {$wpdb->posts} a
			LEFT JOIN {$wpdb->posts} p ON a.post_parent = p.ID
			WHERE a.post_type = 'attachment'
			AND a.post_parent > 0
			AND p.ID IS NULL
		" );

		return $orphaned;
	}

	/**
	 * Cleanup orphaned attachments
	 *
	 * WARNING: Use with caution! Deletes attachments with non-existent parent.
	 *
	 * @param bool $dry_run If true, only reports what would be deleted
	 * @return array Statistics
	 */
	public static function cleanup_orphaned_attachments( $dry_run = true ) {
		$orphaned_ids = self::find_orphaned_attachments();

		$stats = array(
			'dry_run' => $dry_run,
			'orphaned_found' => count( $orphaned_ids ),
			'deleted' => 0,
			'disk_space_freed' => 0,
			'errors' => 0
		);

		error_log( "[ATTACHMENT-CLEANUP] Orphaned attachments cleanup: " . ( $dry_run ? 'DRY-RUN' : 'LIVE' ) );
		error_log( "[ATTACHMENT-CLEANUP] Found {$stats['orphaned_found']} orphaned attachments" );

		foreach ( $orphaned_ids as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );

			if ( file_exists( $file_path ) ) {
				$stats['disk_space_freed'] += filesize( $file_path );
			}

			if ( $dry_run ) {
				error_log( "[ATTACHMENT-CLEANUP] [DRY-RUN] Would delete attachment {$attachment_id}: {$file_path}" );
			} else {
				$deleted = wp_delete_attachment( $attachment_id, true );

				if ( $deleted ) {
					$stats['deleted']++;
					error_log( "[ATTACHMENT-CLEANUP] Deleted orphaned attachment {$attachment_id}: {$file_path}" );
				} else {
					$stats['errors']++;
					error_log( "[ATTACHMENT-CLEANUP] Failed to delete attachment {$attachment_id}" );
				}
			}
		}

		$stats['disk_space_freed_mb'] = round( $stats['disk_space_freed'] / 1024 / 1024, 2 );

		error_log( "[ATTACHMENT-CLEANUP] Cleanup summary:" );
		error_log( "[ATTACHMENT-CLEANUP]   Orphaned found: {$stats['orphaned_found']}" );
		error_log( "[ATTACHMENT-CLEANUP]   Deleted: {$stats['deleted']}" );
		error_log( "[ATTACHMENT-CLEANUP]   Space freed: {$stats['disk_space_freed_mb']} MB" );

		return $stats;
	}

	/**
	 * Build a media audit snapshot for one attachment.
	 *
	 * @param int $post_id Post ID being deleted.
	 * @param int $attachment_id Attachment ID.
	 * @return array
	 */
	private static function build_delete_media_audit_snapshot( $post_id, $attachment_id, $property_import_id = '' ) {
		$attached_file = (string) get_post_meta( $attachment_id, '_wp_attached_file', true );
		$absolute_path = (string) get_attached_file( $attachment_id );
		$file_exists = '' !== $absolute_path ? file_exists( $absolute_path ) : false;
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$metadata = is_array( $metadata ) ? $metadata : array();
		$sizes = isset( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ? $metadata['sizes'] : array();
		$size_paths = array();
		$size_exists = array();

		foreach ( $sizes as $size_name => $size_data ) {
			$size_file = isset( $size_data['file'] ) ? (string) $size_data['file'] : '';
			$size_path = '' !== $absolute_path && '' !== $size_file
				? trailingslashit( dirname( $absolute_path ) ) . $size_file
				: '';

			$size_paths[ $size_name ] = $size_path;
			$size_exists[ $size_name ] = '' !== $size_path ? file_exists( $size_path ) : false;
		}

		return array(
			'property_post_id' => (int) $post_id,
			'property_import_id' => (string) $property_import_id,
			'attachment_id' => (int) $attachment_id,
			'_wp_attached_file' => $attached_file,
			'absolute_path' => $absolute_path,
			'file_exists_before' => $file_exists,
			'file_size_bytes' => ( $file_exists && '' !== $absolute_path ) ? (int) filesize( $absolute_path ) : 0,
			'metadata_sizes' => $sizes,
			'metadata_size_paths' => $size_paths,
			'metadata_size_exists_before' => $size_exists,
		);
	}

	/**
	 * Recheck attachment state after delete.
	 *
	 * @param array $snapshot Pre-delete snapshot.
	 * @param int   $attachment_id Attachment ID.
	 * @return array
	 */
	private static function build_delete_media_audit_after_snapshot( array $snapshot, $attachment_id ) {
		$after_size_exists = array();

		foreach ( ( $snapshot['metadata_size_paths'] ?? array() ) as $size_name => $size_path ) {
			$after_size_exists[ $size_name ] = '' !== $size_path ? file_exists( $size_path ) : false;
		}

		$absolute_path = isset( $snapshot['absolute_path'] ) ? (string) $snapshot['absolute_path'] : '';

		return array_merge( $snapshot, array(
			'attachment_post_exists_after' => (bool) get_post( $attachment_id ),
			'file_exists_after' => '' !== $absolute_path ? file_exists( $absolute_path ) : false,
			'metadata_size_exists_after' => $after_size_exists,
		) );
	}

	/**
	 * Write temporary audit log line.
	 *
	 * @param int    $post_id Property post ID.
	 * @param int    $attachment_id Attachment ID.
	 * @param string $stage Audit stage.
	 * @param array  $payload Audit payload.
	 * @return void
	 */
	private static function log_delete_media_audit( $post_id, $attachment_id, $stage, array $payload ) {
		error_log( '[DELETE-MEDIA-AUDIT] ' . $stage . ' ' . wp_json_encode( array(
			'property_post_id' => (int) $post_id,
			'property_import_id' => isset( $payload['property_import_id'] ) ? (string) $payload['property_import_id'] : '',
			'attachment_id' => (int) $attachment_id,
			'_wp_attached_file' => $payload['_wp_attached_file'] ?? '',
			'absolute_path' => $payload['absolute_path'] ?? '',
			'file_exists_before' => $payload['file_exists_before'] ?? null,
			'file_exists_after' => $payload['file_exists_after'] ?? null,
			'metadata_sizes' => $payload['metadata_sizes'] ?? array(),
			'metadata_size_paths' => $payload['metadata_size_paths'] ?? array(),
			'metadata_size_exists_before' => $payload['metadata_size_exists_before'] ?? array(),
			'metadata_size_exists_after' => $payload['metadata_size_exists_after'] ?? array(),
			'wp_delete_attachment_result' => $payload['wp_delete_attachment_result'] ?? null,
			'attachment_post_exists_after' => $payload['attachment_post_exists_after'] ?? null,
			'file_size_bytes' => $payload['file_size_bytes'] ?? 0,
		) ) );
	}
}
