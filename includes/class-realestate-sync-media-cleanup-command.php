<?php
/**
 * WP-CLI media cleanup command.
 *
 * Dry-run by default. Real deletions require --execute.
 *
 * @package RealEstate_Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

class RealEstate_Sync_Media_Cleanup_Command {

    /**
     * Command options.
     *
     * @var array
     */
    private $options = array();

    /**
     * CSV handle.
     *
     * @var resource|null
     */
    private $csv_handle = null;

    /**
     * Log handle.
     *
     * @var resource|null
     */
    private $log_handle = null;

    /**
     * Preflight global gallery IDs.
     *
     * @var array<int, bool>
     */
    private $gallery_ids_global = array();

    /**
     * Preflight global thumbnail IDs.
     *
     * @var array<int, bool>
     */
    private $thumbnail_ids_global = array();

    /**
     * Process summary.
     *
     * @var array<string, int>
     */
    private $stats = array();

    /**
     * Consecutive delete failures.
     *
     * @var int
     */
    private $consecutive_delete_errors = 0;

    /**
     * Execute the command.
     *
     * @param array $args Positional args.
     * @param array $assoc_args Associative args.
     * @return void
     */
    public function __invoke($args, $assoc_args) {
        try {
            if (!empty($args) && !empty($args[0]) && $args[0] === 'add') {
                $this->add_item($assoc_args);
                return;
            }

            if (!empty($args) && !empty($args[0]) && $args[0] === 'scan') {
                $this->scan($assoc_args);
                return;
            }

            if (!empty($args) && !empty($args[0]) && $args[0] === 'status') {
                $this->output_status();
                return;
            }

            $this->options = $this->parse_options($assoc_args);
            $this->prepare_output_files();

            $this->stats = array(
                'processed' => 0,
                'eligible' => 0,
                'deleted' => 0,
                'skipped' => 0,
                'errors' => 0,
            );

            $this->cli_log('Starting RealEstate Sync media cleanup.');
            $this->cli_log('Mode: ' . ($this->options['execute'] ? 'EXECUTE' : 'DRY-RUN'));
            $this->cli_log('Limit: ' . $this->options['limit'] . ' | Offset: ' . $this->options['offset']);

            $this->build_global_sets();

            $attachments = $this->resolve_target_attachments();

            if (empty($attachments)) {
                $this->cli_warning('No candidate attachments found for the selected scope.');
                $this->write_summary();
                return;
            }

            foreach ($attachments as $attachment_id) {
                $result = $this->process_attachment((int) $attachment_id);
                $this->stats['processed']++;

                if ($result['action'] === 'dry_run' || $result['action'] === 'deleted') {
                    $this->stats['eligible']++;
                }

                if ($result['action'] === 'deleted') {
                    $this->stats['deleted']++;
                } elseif ($result['action'] === 'skipped') {
                    $this->stats['skipped']++;
                } elseif ($result['action'] === 'error') {
                    $this->stats['errors']++;
                }

                $this->write_row($result);

                if ($result['action'] === 'error' && !empty($result['fatal'])) {
                    $this->close_handles();
                    $this->cli_error($result['reason']);
                }
            }

            $this->write_summary();
            $this->close_handles();
        } catch (Exception $e) {
            $this->close_handles();
            $this->cli_error($e->getMessage());
        }
    }

    /**
     * WP-CLI subcommand handler for status.
     *
     * @param array $args Positional args.
     * @param array $assoc_args Associative args.
     * @return void
     */
    public function status($args, $assoc_args) {
        $this->output_status();
    }

    /**
     * Add one attachment to the cleanup queue manually.
     *
     * @param array $assoc_args Associative args.
     * @return void
     */
    private function add_item($assoc_args) {
        if (empty($assoc_args['attachment-id']) || !is_numeric($assoc_args['attachment-id'])) {
            $this->cli_error('Missing required --attachment-id flag.');
        }

        $attachment_id = (int) $assoc_args['attachment-id'];
        $session_id = !empty($assoc_args['session-id']) ? (string) $assoc_args['session-id'] : 'manual';

        // TODO: validate attachment ownership/eligibility before queue insertion.
        $queue_manager = new RealEstate_Sync_Media_Cleanup_Queue_Manager();
        $result = $queue_manager->insert_item($session_id, $attachment_id);

        if (!empty($result['duplicate'])) {
            $this->cli_log('duplicate');
            return;
        }

        if (!empty($result['inserted'])) {
            $this->cli_log('inserted');
            return;
        }

        $this->cli_error('Unable to insert attachment into cleanup queue.');
    }

    /**
     * Output queue status.
     *
     * @return void
     */
    private function output_status() {
        $queue_manager = new RealEstate_Sync_Media_Cleanup_Queue_Manager();
        $counts = $queue_manager->get_status_counts();

        if ((int) $counts['total'] === 0) {
            $this->cli_log('Media cleanup queue is empty.');
            $this->cli_log('Total: 0');
            $this->cli_log('Pending: 0');
            return;
        }

        $this->cli_log('Total: ' . (int) $counts['total']);
        $this->cli_log('Pending: ' . (int) $counts['pending']);
    }

    /**
     * Run the cleanup scanner.
     *
     * @param array $assoc_args CLI args.
     * @return void
     */
    private function scan($assoc_args) {
        $scanner = new RealEstate_Sync_Media_Cleanup_Scanner($this, new RealEstate_Sync_Media_Cleanup_Queue_Manager());
        $scanner->scan($assoc_args);
    }

    /**
     * Prepare scan options.
     *
     * @param array $assoc_args Raw assoc args.
     * @return array
     */
    public function prepare_scan_options($assoc_args) {
        $options = array(
            'execute' => !empty($assoc_args['execute']),
            'limit' => isset($assoc_args['limit']) && is_numeric($assoc_args['limit']) ? (int) $assoc_args['limit'] : 1000000,
            'offset' => isset($assoc_args['offset']) && is_numeric($assoc_args['offset']) ? (int) $assoc_args['offset'] : 0,
            'session_id' => !empty($assoc_args['session-id']) ? (string) $assoc_args['session-id'] : 'scan',
            'post_id' => isset($assoc_args['post-id']) && is_numeric($assoc_args['post-id']) ? (int) $assoc_args['post-id'] : 0,
            'attachment_id' => isset($assoc_args['attachment-id']) && is_numeric($assoc_args['attachment-id']) ? (int) $assoc_args['attachment-id'] : 0,
            'after_id' => isset($assoc_args['after-id']) && is_numeric($assoc_args['after-id']) ? (int) $assoc_args['after-id'] : 0,
        );

        $this->options = array_merge($this->options, $options);

        return $this->options;
    }

    /**
     * Parse and validate command options.
     *
     * @param array $assoc_args Raw assoc args.
     * @return array
     */
    private function parse_options($assoc_args) {
        if (!array_key_exists('limit', $assoc_args) || $assoc_args['limit'] === '' || !is_numeric($assoc_args['limit'])) {
            $this->cli_error('Missing required --limit flag. Use --limit=100 (or another explicit limit).');
        }

        $limit = (int) $assoc_args['limit'];
        if ($limit <= 0) {
            $this->cli_error('--limit must be a positive integer.');
        }

        $offset = isset($assoc_args['offset']) && is_numeric($assoc_args['offset']) ? (int) $assoc_args['offset'] : 0;
        if ($offset < 0) {
            $this->cli_error('--offset must be >= 0.');
        }

        $options = array(
            'dry_run' => true,
            'execute' => !empty($assoc_args['execute']),
            'limit' => $limit,
            'offset' => $offset,
            'after_id' => isset($assoc_args['after-id']) && is_numeric($assoc_args['after-id']) ? (int) $assoc_args['after-id'] : 0,
            'post_id' => isset($assoc_args['post-id']) && is_numeric($assoc_args['post-id']) ? (int) $assoc_args['post-id'] : 0,
            'attachment_id' => isset($assoc_args['attachment-id']) && is_numeric($assoc_args['attachment-id']) ? (int) $assoc_args['attachment-id'] : 0,
            'output_csv' => !empty($assoc_args['output-csv']) ? (string) $assoc_args['output-csv'] : '',
            'log_file' => !empty($assoc_args['log-file']) ? (string) $assoc_args['log-file'] : '',
        );

        if ($options['execute']) {
            $options['dry_run'] = false;
        }

        if ($options['post_id'] && !get_post($options['post_id'])) {
            $this->cli_error('Invalid --post-id: post not found.');
        }

        if ($options['attachment_id'] && !get_post($options['attachment_id'])) {
            $this->cli_error('Invalid --attachment-id: attachment not found.');
        }

        return $options;
    }

    /**
     * Open optional output files.
     *
     * @return void
     */
    private function prepare_output_files() {
        if (!empty($this->options['output_csv'])) {
            $this->csv_handle = $this->open_file_for_write($this->options['output_csv']);
            $this->write_csv_header();
        }

        if (!empty($this->options['log_file'])) {
            $this->log_handle = $this->open_file_for_append($this->options['log_file']);
            $this->log_line('START', array(
                'mode' => $this->options['execute'] ? 'execute' : 'dry_run',
                'limit' => $this->options['limit'],
                'offset' => $this->options['offset'],
                'after_id' => $this->options['after_id'],
                'post_id' => $this->options['post_id'],
                'attachment_id' => $this->options['attachment_id'],
            ));
        }
    }

    /**
     * Build the global gallery and thumbnail sets.
     *
     * @return void
     */
    public function build_global_sets() {
        global $wpdb;

        $gallery_ids = array();
        $gallery_meta_keys = array('wpestate_property_gallery', 'property_gallery');

        foreach ($gallery_meta_keys as $meta_key) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
                    $meta_key
                ),
                ARRAY_A
            );

            if ($rows === false) {
                $this->cli_error('Failed to build gallery_ids_global for meta_key ' . $meta_key . '.');
            }

            foreach ($rows as $row) {
                $this->merge_ids_from_meta_value($gallery_ids, $row['meta_value']);
            }
        }

        $thumbnail_ids = array();
        $rows = $wpdb->get_col("SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_thumbnail_id'");

        if ($rows === false) {
            $this->cli_error('Failed to build thumbnail_ids_global.');
        }

        foreach ($rows as $value) {
            if (is_numeric($value) && (int) $value > 0) {
                $thumbnail_ids[(int) $value] = true;
            }
        }

        $this->gallery_ids_global = $gallery_ids;
        $this->thumbnail_ids_global = $thumbnail_ids;

        $this->log_line('PREPARE', array(
            'gallery_ids_global' => count($this->gallery_ids_global),
            'thumbnail_ids_global' => count($this->thumbnail_ids_global),
        ));

        $this->cli_log('Preflight sets built: gallery_ids_global=' . count($this->gallery_ids_global) . ', thumbnail_ids_global=' . count($this->thumbnail_ids_global));
    }

    /**
     * Resolve target attachment IDs.
     *
     * @return array<int>
     */
    public function resolve_target_attachments() {
        if ($this->options['attachment_id']) {
            return array($this->options['attachment_id']);
        }

        global $wpdb;

        $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%' AND post_parent > 0";
        $params = array();

        if ($this->options['post_id']) {
            $sql .= ' AND post_parent = %d';
            $params[] = $this->options['post_id'];
        }

        if ($this->options['after_id']) {
            $sql .= ' AND ID > %d';
            $params[] = $this->options['after_id'];
        }

        $sql .= ' ORDER BY ID ASC LIMIT %d OFFSET %d';
        $params[] = $this->options['limit'];
        $params[] = $this->options['offset'];

        $prepared = $wpdb->prepare($sql, $params);
        $ids = $wpdb->get_col($prepared);

        if ($ids === false) {
            $this->cli_error('Failed to resolve candidate attachments.');
        }

        return array_map('intval', $ids);
    }

    /**
     * Process a single attachment.
     *
     * @param int $attachment_id Attachment ID.
     * @return array
     */
    public function evaluate_attachment($attachment_id) {
        $post = get_post($attachment_id);

        if (!$post || $post->post_type !== 'attachment') {
            return $this->build_result($attachment_id, null, null, array(), 'error', 'attachment_not_found', true);
        }

        $parent_id = (int) $post->post_parent;
        if ($parent_id <= 0) {
            return $this->build_result($attachment_id, 0, null, array(), 'error', 'parent_missing', true);
        }

        $parent = get_post($parent_id);
        if (!$parent) {
            return $this->build_result($attachment_id, $parent_id, null, array(), 'error', 'parent_not_found', true);
        }

        if ($parent->post_type !== 'estate_property') {
            return $this->build_result($attachment_id, $parent_id, null, array(), 'skipped', 'parent_not_estate_property', false);
        }

        $property_import_id = get_post_meta($parent_id, 'property_import_id', true);
        if ($property_import_id === '' || $property_import_id === null) {
            return $this->build_result($attachment_id, $parent_id, null, array(), 'error', 'parent_missing_property_import_id', true);
        }

        $attached_file = get_post_meta($attachment_id, '_wp_attached_file', true);
        $absolute_file = get_attached_file($attachment_id);
        $path = $this->normalize_attached_path($attached_file, $absolute_file);

        $original_exists = !empty($absolute_file) && file_exists($absolute_file);
        $original_size = $original_exists ? (int) filesize($absolute_file) : 0;

        $thumb_info = $this->collect_thumbnail_info($attachment_id, $absolute_file);

        $in_gallery = isset($this->gallery_ids_global[$attachment_id]);
        $is_thumbnail = isset($this->thumbnail_ids_global[$attachment_id]);
        $referenced_in_content = $this->is_referenced_in_parent_content($parent, $attachment_id, $path, $absolute_file);
        $soft_cutoff_ok = $this->passes_cutoff_date($post->post_date);
        $soft_path_ok = $this->is_standard_upload_path($path);

        $row = $this->build_result(
            $attachment_id,
            $parent_id,
            $property_import_id,
            array(
                'title' => get_the_title($parent_id),
                'date' => $post->post_date,
                'path' => $path,
                'original_exists' => $original_exists ? 1 : 0,
                'original_size_bytes' => $original_size,
                'thumbnails_count' => $thumb_info['count'],
                'thumbnails_size_bytes' => $thumb_info['size'],
                'total_size_bytes' => $original_size + $thumb_info['size'],
                'in_gallery' => $in_gallery ? 1 : 0,
                'is_thumbnail' => $is_thumbnail ? 1 : 0,
                'referenced_in_content' => $referenced_in_content ? 1 : 0,
            )
        );

        if ($in_gallery) {
            return $this->with_action($row, 'skipped', 'in_gallery_global');
        }

        if ($is_thumbnail) {
            return $this->with_action($row, 'skipped', 'is_thumbnail');
        }

        if ($referenced_in_content) {
            return $this->with_action($row, 'skipped', 'referenced_in_content');
        }

        if (!$soft_cutoff_ok) {
            return $this->with_action($row, 'skipped', 'soft_cutoff');
        }

        if (!$soft_path_ok) {
            return $this->with_action($row, 'skipped', 'soft_path');
        }

        return $this->with_action($row, 'dry_run', 'eligible');
    }

    /**
     * Process a single attachment.
     *
     * @param int $attachment_id Attachment ID.
     * @return array
     */
    private function process_attachment($attachment_id) {
        $row = $this->evaluate_attachment($attachment_id);

        if (($row['action'] ?? '') !== 'dry_run' || empty($this->options['execute'])) {
            return $row;
        }

        $deleted = wp_delete_attachment($attachment_id, true);
        if (!$deleted) {
            $this->record_delete_failure();
            return $this->with_action($row, 'error', 'delete_failed');
        }

        if (get_post($attachment_id)) {
            $this->record_delete_failure();
            return $this->with_action($row, 'error', 'delete_verification_failed');
        }

        $this->consecutive_delete_errors = 0;
        return $this->with_action($row, 'deleted', 'deleted');
    }

    /**
     * Build a result row.
     *
     * @param int|null $attachment_id Attachment ID.
     * @param int|null $post_parent Parent post ID.
     * @param mixed $property_import_id Property import ID.
     * @param array $fields Row fields.
     * @param string $action Action.
     * @param string $reason Reason.
     * @param bool $fatal Fatal flag.
     * @return array
     */
    private function build_result($attachment_id, $post_parent, $property_import_id, $fields, $action = 'skipped', $reason = '', $fatal = false) {
        return array_merge(array(
            'attachment_id' => $attachment_id,
            'post_parent' => $post_parent,
            'property_import_id' => $property_import_id,
            'title' => '',
            'date' => '',
            'path' => '',
            'original_exists' => 0,
            'original_size_bytes' => 0,
            'thumbnails_count' => 0,
            'thumbnails_size_bytes' => 0,
            'total_size_bytes' => 0,
            'in_gallery' => 0,
            'is_thumbnail' => 0,
            'referenced_in_content' => 0,
            'action' => $action,
            'reason' => $reason,
            'fatal' => $fatal ? 1 : 0,
        ), $fields);
    }

    /**
     * Override row action and reason.
     *
     * @param array $row Row data.
     * @param string $action Action.
     * @param string $reason Reason.
     * @return array
     */
    private function with_action(array $row, $action, $reason) {
        $row['action'] = $action;
        $row['reason'] = $reason;
        return $row;
    }

    /**
     * Write CSV row and log line.
     *
     * @param array $row Row data.
     * @return void
     */
    private function write_row(array $row) {
        $csv_row = array(
            $row['attachment_id'],
            $row['post_parent'],
            $row['property_import_id'],
            $row['title'],
            $row['date'],
            $row['path'],
            $row['original_exists'],
            $row['original_size_bytes'],
            $row['thumbnails_count'],
            $row['thumbnails_size_bytes'],
            $row['total_size_bytes'],
            $row['in_gallery'],
            $row['is_thumbnail'],
            $row['referenced_in_content'],
            $row['action'],
            $row['reason'],
        );

        if (is_resource($this->csv_handle)) {
            fputcsv($this->csv_handle, $csv_row);
        }

        $this->log_line('ATTACHMENT', $row);
    }

    /**
     * Write CSV header.
     *
     * @return void
     */
    private function write_csv_header() {
        if (!is_resource($this->csv_handle)) {
            return;
        }

        fputcsv($this->csv_handle, array(
            'attachment_id',
            'post_parent',
            'property_import_id',
            'title',
            'date',
            'path',
            'original_exists',
            'original_size_bytes',
            'thumbnails_count',
            'thumbnails_size_bytes',
            'total_size_bytes',
            'in_gallery',
            'is_thumbnail',
            'referenced_in_content',
            'action',
            'reason',
        ));
    }

    /**
     * Log a structured line.
     *
     * @param string $type Line type.
     * @param array $data Payload.
     * @return void
     */
    private function log_line($type, array $data) {
        if (!is_resource($this->log_handle)) {
            return;
        }

        $payload = array(
            'timestamp' => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
            'type' => $type,
            'data' => $data,
        );

        $encoded = function_exists('wp_json_encode')
            ? wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        fwrite($this->log_handle, $encoded . PHP_EOL);
    }

    /**
     * Write final summary.
     *
     * @return void
     */
    private function write_summary() {
        $summary = array(
            'processed' => $this->stats['processed'],
            'eligible' => $this->stats['eligible'],
            'deleted' => $this->stats['deleted'],
            'skipped' => $this->stats['skipped'],
            'errors' => $this->stats['errors'],
            'mode' => $this->options['execute'] ? 'execute' : 'dry_run',
        );

        $this->log_line('SUMMARY', $summary);
        $encoded = function_exists('wp_json_encode')
            ? wp_json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->cli_success('Cleanup completed: ' . $encoded);
    }

    /**
     * Normalize attachment path for reporting.
     *
     * @param string $attached_file Relative attached file path.
     * @param string $absolute_file Absolute file path.
     * @return string
     */
    private function normalize_attached_path($attached_file, $absolute_file) {
        if (!empty($attached_file)) {
            return $attached_file;
        }

        if (empty($absolute_file)) {
            return '';
        }

        $uploads = wp_upload_dir();
        if (!empty($uploads['basedir']) && strpos($absolute_file, $uploads['basedir']) === 0) {
            return ltrim(substr($absolute_file, strlen($uploads['basedir'])), DIRECTORY_SEPARATOR);
        }

        return $absolute_file;
    }

    /**
     * Check if path follows the standard YYYY/MM pattern.
     *
     * @param string $path Relative path.
     * @return bool
     */
    private function is_standard_upload_path($path) {
        return !empty($path) && (bool) preg_match('~^\d{4}/\d{2}/~', $path);
    }

    /**
     * Check if date is on or after the soft cutoff.
     *
     * @param string $date Post date.
     * @return bool
     */
    private function passes_cutoff_date($date) {
        if (empty($date)) {
            return false;
        }

        return strtotime($date) >= strtotime('2025-12-01 00:00:00');
    }

    /**
     * Detect whether attachment is referenced in parent post content.
     *
     * @param WP_Post $parent Parent post.
     * @param int $attachment_id Attachment ID.
     * @param string $path Relative file path.
     * @param string $absolute_file Absolute file path.
     * @return bool
     */
    private function is_referenced_in_parent_content($parent, $attachment_id, $path, $absolute_file) {
        $content = isset($parent->post_content) ? (string) $parent->post_content : '';
        if ($content === '') {
            return false;
        }

        $needles = array();
        $basename = '';

        if (!empty($path)) {
            $needles[] = $path;
            $basename = basename($path);
        } elseif (!empty($absolute_file)) {
            $basename = basename($absolute_file);
        }

        if (!empty($basename)) {
            $needles[] = $basename;
        }

        $attachment_url = wp_get_attachment_url($attachment_id);
        if (!empty($attachment_url)) {
            $needles[] = $attachment_url;
            $needles[] = basename(parse_url($attachment_url, PHP_URL_PATH));
        }

        foreach (array_unique(array_filter($needles)) as $needle) {
            if (strpos($content, (string) $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Collect thumbnail file information from attachment metadata.
     *
     * @param int $attachment_id Attachment ID.
     * @param string $absolute_file Absolute original file path.
     * @return array{count:int,size:int}
     */
    private function collect_thumbnail_info($attachment_id, $absolute_file) {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!is_array($metadata)) {
            return array('count' => 0, 'size' => 0);
        }

        $files = array();
        $base_dir = !empty($absolute_file) ? dirname($absolute_file) : '';

        if (!empty($metadata['sizes']) && is_array($metadata['sizes']) && !empty($base_dir)) {
            foreach ($metadata['sizes'] as $size) {
                if (!empty($size['file'])) {
                    $files[] = $base_dir . DIRECTORY_SEPARATOR . $size['file'];
                }
            }
        }

        if (!empty($metadata['original_image']) && !empty($base_dir)) {
            $files[] = $base_dir . DIRECTORY_SEPARATOR . $metadata['original_image'];
        }

        $count = 0;
        $size = 0;

        foreach (array_unique($files) as $file) {
            if (file_exists($file)) {
                $count++;
                $size += (int) filesize($file);
            }
        }

        return array('count' => $count, 'size' => $size);
    }

    /**
     * Merge attachment IDs from a meta value into a set.
     *
     * @param array<int, bool> $set Reference set.
     * @param mixed $value Meta value.
     * @return void
     */
    private function merge_ids_from_meta_value(array &$set, $value) {
        $values = $this->extract_ids_from_value($value);
        foreach ($values as $id) {
            if ($id > 0) {
                $set[$id] = true;
            }
        }
    }

    /**
     * Extract numeric IDs from a value.
     *
     * @param mixed $value Meta value.
     * @return array<int>
     */
    private function extract_ids_from_value($value) {
        $ids = array();

        if (is_array($value)) {
            foreach ($value as $item) {
                $ids = array_merge($ids, $this->extract_ids_from_value($item));
            }
            return array_values(array_unique($ids));
        }

        if (is_object($value)) {
            return $this->extract_ids_from_value((array) $value);
        }

        if (is_numeric($value)) {
            return array((int) $value);
        }

        if (!is_string($value) || $value === '') {
            return array();
        }

        $maybe_unserialized = maybe_unserialize($value);
        if ($maybe_unserialized !== $value) {
            return $this->extract_ids_from_value($maybe_unserialized);
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return array();
        }

        if (preg_match('/^\d+$/', $trimmed)) {
            return array((int) $trimmed);
        }

        if (strpos($value, ',') !== false) {
            $parts = preg_split('/\s*,\s*/', $trimmed);
            if (is_array($parts)) {
                $csv_ids = array();
                $all_numeric = true;

                foreach ($parts as $part) {
                    if ($part === '' || !preg_match('/^\d+$/', $part)) {
                        $all_numeric = false;
                        break;
                    }

                    $csv_ids[] = (int) $part;
                }

                if ($all_numeric && !empty($csv_ids)) {
                    return array_values(array_unique($csv_ids));
                }
            }

            $this->cli_warning('Skipped non-numeric gallery meta string while building gallery_ids_global.');
            return array();
        }

        $this->cli_warning('Skipped non-numeric gallery meta string while building gallery_ids_global.');
        return array();
    }

    /**
     * Record a delete failure and stop if failures are repeated.
     *
     * @return void
     */
    private function record_delete_failure() {
        $this->consecutive_delete_errors++;

        if ($this->consecutive_delete_errors >= 3) {
            $this->cli_error('Stopping: repeated delete errors detected.');
        }
    }

    /**
     * Open a file for write, creating directories if needed.
     *
     * @param string $file_path File path.
     * @return resource
     */
    private function open_file_for_write($file_path) {
        $this->ensure_parent_directory($file_path);
        $handle = fopen($file_path, 'wb');
        if (!$handle) {
            $this->cli_error('Unable to open CSV output file: ' . $file_path);
        }
        return $handle;
    }

    /**
     * Open a file for append, creating directories if needed.
     *
     * @param string $file_path File path.
     * @return resource
     */
    private function open_file_for_append($file_path) {
        $this->ensure_parent_directory($file_path);
        $handle = fopen($file_path, 'ab');
        if (!$handle) {
            $this->cli_error('Unable to open log file: ' . $file_path);
        }
        return $handle;
    }

    /**
     * Ensure the parent directory exists.
     *
     * @param string $file_path File path.
     * @return void
     */
    private function ensure_parent_directory($file_path) {
        $directory = dirname($file_path);
        if (!is_dir($directory)) {
            if (function_exists('wp_mkdir_p')) {
                wp_mkdir_p($directory);
            } elseif (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                $this->cli_error('Unable to create directory: ' . $directory);
            }
        }
    }

    /**
     * Close file handles.
     *
     * @return void
     */
    private function close_handles() {
        if (is_resource($this->csv_handle)) {
            fclose($this->csv_handle);
            $this->csv_handle = null;
        }

        if (is_resource($this->log_handle)) {
            fclose($this->log_handle);
            $this->log_handle = null;
        }
    }

    /**
     * Emit a CLI log message.
     *
     * @param string $message Message.
     * @return void
     */
    private function cli_log($message) {
        if (class_exists('WP_CLI')) {
            WP_CLI::log($message);
            return;
        }

        echo $message . PHP_EOL;
    }

    /**
     * Emit a CLI success message.
     *
     * @param string $message Message.
     * @return void
     */
    private function cli_success($message) {
        if (class_exists('WP_CLI')) {
            WP_CLI::success($message);
            return;
        }

        echo $message . PHP_EOL;
    }

    /**
     * Emit a CLI warning message.
     *
     * @param string $message Message.
     * @return void
     */
    private function cli_warning($message) {
        if (class_exists('WP_CLI')) {
            WP_CLI::warning($message);
            return;
        }

        fwrite(STDERR, $message . PHP_EOL);
    }

    /**
     * Emit a CLI error message.
     *
     * @param string $message Message.
     * @return void
     */
    private function cli_error($message) {
        if (class_exists('WP_CLI')) {
            WP_CLI::error($message);
            return;
        }

        throw new RuntimeException($message);
    }
}
