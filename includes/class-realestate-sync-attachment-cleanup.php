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

		// Get all attached media
		$attachments = get_attached_media( '', $post_id );

		if ( empty( $attachments ) ) {
			return;
		}

		$attachment_count = count( $attachments );
		$deleted_count = 0;
		$total_size = 0;

		// Delete each attachment
		foreach ( $attachments as $attachment ) {
			$attachment_id = $attachment->ID;
			$file_path = get_attached_file( $attachment_id );

			// Get file size before deletion
			if ( file_exists( $file_path ) ) {
				$file_size = filesize( $file_path );
				$total_size += $file_size;
			}

			// Delete attachment (force=true deletes file + all thumbnails)
			$deleted = wp_delete_attachment( $attachment_id, true );

			if ( $deleted ) {
				$deleted_count++;
			} else {
				error_log( "[ATTACHMENT-CLEANUP] Failed to delete attachment {$attachment_id}: {$file_path}" );
			}
		}

		// Summary
		$mb_freed = round( $total_size / 1024 / 1024, 2 );
		error_log( "[ATTACHMENT-CLEANUP] {$post_type_label} {$post_id}: {$deleted_count}/{$attachment_count} attachments deleted, {$mb_freed} MB freed" );
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
			$file_path = get_attached_file( $attachment->ID );

			if ( file_exists( $file_path ) ) {
				$stats['disk_space_freed'] += filesize( $file_path );
			}

			$deleted = wp_delete_attachment( $attachment->ID, true );

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

		foreach ( $orphaned_ids as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );

			if ( file_exists( $file_path ) ) {
				$stats['disk_space_freed'] += filesize( $file_path );
			}

			if ( $dry_run ) {
				// dry run: no-op
			} else {
				$deleted = wp_delete_attachment( $attachment_id, true );

				if ( $deleted ) {
					$stats['deleted']++;
				} else {
					$stats['errors']++;
					error_log( "[ATTACHMENT-CLEANUP] Failed to delete attachment {$attachment_id}" );
				}
			}
		}

		$stats['disk_space_freed_mb'] = round( $stats['disk_space_freed'] / 1024 / 1024, 2 );

		error_log( "[ATTACHMENT-CLEANUP] Cleanup summary: orphaned_found={$stats['orphaned_found']} deleted={$stats['deleted']} freed={$stats['disk_space_freed_mb']} MB" );

		return $stats;
	}
}
