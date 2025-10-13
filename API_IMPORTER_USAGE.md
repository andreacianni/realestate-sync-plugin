# API-Based Importer - Usage Guide

## Overview

The **RealEstate_Sync_WP_Importer_API** is a new implementation that uses the WPResidence REST API instead of direct WordPress meta field manipulation. This approach provides better compatibility, automatic gallery handling, and significantly reduced code complexity (78% reduction: 375 lines vs 1700 lines).

## Architecture

### Key Components

1. **RealEstate_Sync_WPResidence_API_Writer** (`includes/class-realestate-sync-wpresidence-api-writer.php`)
   - Handles all REST API communication
   - JWT authentication with 9-minute token caching
   - Retry logic with exponential backoff
   - Gallery URL formatting and validation

2. **RealEstate_Sync_WP_Importer_API** (`includes/class-realestate-sync-wp-importer-api.php`)
   - Property import orchestration
   - Duplicate detection
   - Taxonomy and feature pre-creation
   - Import tracking metadata

3. **RealEstate_Sync_Import_Engine** (MODIFIED)
   - Automatic importer selection based on configuration
   - Backward compatible with legacy importer
   - Seamless switching between implementations

## Configuration

### Enable API-Based Importer

Add this option to your WordPress database or set it programmatically:

```php
update_option('realestate_sync_use_api_importer', true);
```

To disable (revert to legacy importer):

```php
update_option('realestate_sync_use_api_importer', false);
```

### API Credentials

Credentials are configured in `config/default-settings.php`:

```php
'realestate_sync_api_username' => 'accessi@prioloweb.it',
'realestate_sync_api_password' => '2#&211`%#5+z',
```

**Security Note**: These credentials are stored in the database upon plugin activation. Consider using environment variables for production deployments.

## Usage Examples

### Example 1: Enable API Importer and Run Full Import

```php
// Enable API-based importer
update_option('realestate_sync_use_api_importer', true);

// Run import as usual
$import_engine = new RealEstate_Sync_Import_Engine();
$result = $import_engine->import_from_xml('/path/to/properties.xml');

// Check results
if ($result['success']) {
    echo "Imported: " . $result['stats']['imported_properties'] . "\n";
    echo "Updated: " . $result['stats']['updated_properties'] . "\n";
    echo "Skipped: " . $result['stats']['skipped_properties'] . "\n";
}
```

### Example 2: Direct API Importer Usage (Single Property)

```php
require_once __DIR__ . '/includes/class-realestate-sync-logger.php';
require_once __DIR__ . '/includes/class-realestate-sync-wpresidence-api-writer.php';
require_once __DIR__ . '/includes/class-realestate-sync-wp-importer-api.php';

// Initialize importer
$importer = new RealEstate_Sync_WP_Importer_API();

// Prepare mapped property data (from Property Mapper)
$mapped_property = array(
    'source_data' => array(
        'import_id' => 'PROP_12345',
        'agency_id' => '5074',
    ),
    'post_data' => array(
        'post_title'   => 'Beautiful Apartment in Trento',
        'post_content' => 'Spacious 3-bedroom apartment with mountain views...',
        'post_status'  => 'publish',
        'post_type'    => 'estate_property',
    ),
    'meta_fields' => array(
        'property_price'     => '250000',
        'property_size'      => '120',
        'property_bedrooms'  => '3',
        'property_bathrooms' => '2',
        'property_address'   => 'Via Roma 123',
        'property_city'      => 'trento',
        'property_zip'       => '38121',
    ),
    'taxonomies' => array(
        'property_category' => array('appartamento'),
        'property_status'   => array('vendita'),
        'property_city'     => array('trento'),
    ),
    'features' => array(
        'balcone',
        'riscaldamento-autonomo',
    ),
    'gallery' => array(
        array('url' => 'https://example.com/image1.jpg'),
        array('url' => 'https://example.com/image2.jpg'),
    ),
    'content_hash' => md5(serialize($mapped_property)),
);

// Process property
$result = $importer->process_property($mapped_property);

if ($result['success']) {
    echo "Action: " . $result['action'] . "\n"; // 'created', 'updated', or 'skipped'
    echo "Post ID: " . $result['post_id'] . "\n";
    echo "Message: " . $result['message'] . "\n";
} else {
    echo "Error: " . $result['error'] . "\n";
}
```

### Example 3: Batch Processing with API Importer

```php
$importer = new RealEstate_Sync_WP_Importer_API();

// Array of mapped properties
$mapped_properties = array(
    $property1,
    $property2,
    $property3,
    // ... more properties
);

// Batch process
$result = $importer->batch_process($mapped_properties);

// Get statistics
$stats = $result['stats'];
echo "Imported: " . $stats['imported_properties'] . "\n";
echo "Updated: " . $stats['updated_properties'] . "\n";
echo "Failed: " . $stats['failed_properties'] . "\n";

// Check individual results
foreach ($result['results'] as $index => $property_result) {
    if (!$property_result['success']) {
        echo "Property $index failed: " . $property_result['error'] . "\n";
    }
}
```

### Example 4: Testing API Importer (Standalone Test)

Use the included test script: `test-wp-importer-api.php`

**Browser Access:**
```
http://localhost/trentino-wp/wp-content/plugins/realestate-sync-plugin/test-wp-importer-api.php
```

**Security**: Delete test script after testing!

## API Endpoints Used

### 1. JWT Token Authentication
```
POST /wp-json/jwt-auth/v1/token
Content-Type: application/json

{
    "username": "accessi@prioloweb.it",
    "password": "2#&211`%#5+z"
}
```

**Response:**
```json
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "user_email": "accessi@prioloweb.it",
    "user_display_name": "Admin"
}
```

**Token Caching:** Tokens are cached for 9 minutes to reduce authentication overhead.

### 2. Create Property
```
POST /wp-json/wp/v2/property/add
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
    "title": "Property Title",
    "content": "Property description...",
    "prop_featured": "0",
    "property_price": "250000",
    "property_size": "120",
    "property_bedrooms": "3",
    "property_bathrooms": "2",
    "property_address": "Via Roma 123",
    "property_city": "trento",
    "property_zip": "38121",
    "property_category": ["appartamento"],
    "property_status": ["vendita"],
    "property_features": ["balcone"],
    "images": [
        "https://example.com/image1.jpg",
        "https://example.com/image2.jpg"
    ]
}
```

**Response:**
```json
{
    "success": true,
    "message": "Property added successfully",
    "property_id": 5197
}
```

### 3. Update Property
```
PUT /wp-json/wp/v2/property/edit/{property_id}
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
    "title": "Updated Property Title",
    "property_price": "260000",
    ...
}
```

**Response:**
```json
{
    "success": true,
    "message": "Property updated successfully",
    "property_id": 5197
}
```

## Comparison: API vs Legacy Importer

| Feature | Legacy Importer | API-Based Importer |
|---------|----------------|-------------------|
| **Lines of Code** | 1,700 | 375 (78% reduction) |
| **Gallery Handling** | Manual meta field manipulation | Automatic via API |
| **Taxonomy Assignment** | Manual `wp_set_object_terms()` | Automatic via API |
| **Featured Image** | Manual attachment creation | Automatic via API |
| **WPResidence Compatibility** | Direct meta fields (fragile) | REST API (official interface) |
| **Error Handling** | Basic | Advanced with retry logic |
| **Token Management** | N/A | Cached (9-minute expiration) |
| **API Rate Limiting** | N/A | Built-in delays (0.1s per property) |

## Key Differences

### Gallery Handling

**Legacy Importer:**
```php
// Manual gallery processing (100+ lines of code)
$attachment_id = $this->handle_image_sideload($image_url);
$gallery_ids[] = $attachment_id;
update_post_meta($post_id, 'wpestate_property_gallery', $gallery_ids);
```

**API-Based Importer:**
```php
// Automatic via API (single field)
$api_body['images'] = array(
    'https://example.com/image1.jpg',
    'https://example.com/image2.jpg'
);
// Gallery created automatically by WPResidence API
```

### Taxonomy Assignment

**Legacy Importer:**
```php
// Manual term assignment
foreach ($taxonomies as $taxonomy => $terms) {
    $term_ids = array();
    foreach ($terms as $term_slug) {
        $term = get_term_by('slug', $term_slug, $taxonomy);
        $term_ids[] = $term->term_id;
    }
    wp_set_object_terms($post_id, $term_ids, $taxonomy);
}
```

**API-Based Importer:**
```php
// Pre-create terms (API requirement)
$this->ensure_terms_exist($mapped_property);

// API handles assignment automatically
$api_body['property_category'] = array('appartamento');
$api_body['property_status'] = array('vendita');
```

## Error Handling

### Retry Logic

The API Writer includes exponential backoff retry logic:

```php
Attempt 1: Immediate
Attempt 2: Wait 1 second
Attempt 3: Wait 2 seconds
Attempt 4: Wait 4 seconds (final attempt)
```

### Common Errors

**1. Authentication Failure**
```
Error: JWT token generation failed
```
**Solution**: Check API credentials in `config/default-settings.php`

**2. Missing Taxonomy Terms**
```
Warning: Term 'appartamento' not found in property_category
```
**Solution**: Terms are auto-created by `ensure_terms_exist()`, but check taxonomy registration.

**3. Gallery Import Failure**
```
Error: Property created but gallery import failed
```
**Solution**: Check image URLs are accessible. API validates URLs before import.

**4. API Timeout**
```
Error: API request timed out after 120 seconds
```
**Solution**: Increase timeout in API Writer or check server performance.

## Debugging

### Enable Detailed Logging

```php
// Set logger to DEBUG level
$logger = RealEstate_Sync_Logger::get_instance();
$logger->set_log_level('DEBUG');

// All API requests/responses will be logged
$importer = new RealEstate_Sync_WP_Importer_API($logger);
```

### Check API Response

```php
$result = $importer->process_property($mapped_property);

if (!$result['success']) {
    // Log full error details
    error_log('API Error: ' . print_r($result, true));

    // Check API response code
    if (isset($result['response_code'])) {
        echo "HTTP Status: " . $result['response_code'] . "\n";
    }
}
```

### Verify Tracking Metadata

```php
$post_id = 5197;

echo "Import ID: " . get_post_meta($post_id, 'property_import_id', true) . "\n";
echo "Import Hash: " . get_post_meta($post_id, 'property_import_hash', true) . "\n";
echo "Import Version: " . get_post_meta($post_id, 'property_import_version', true) . "\n";
echo "Last Sync: " . get_post_meta($post_id, 'property_last_sync', true) . "\n";
```

Expected output:
```
Import ID: PROP_12345
Import Hash: a1b2c3d4e5f6...
Import Version: 4.0-api
Last Sync: 2025-10-13 14:30:00
```

## Migration Guide

### Step 1: Test with Single Property

```php
// Enable API importer
update_option('realestate_sync_use_api_importer', true);

// Test with one property
$test_xml = '/path/to/single-property-test.xml';
$result = $import_engine->import_from_xml($test_xml);

// Verify result
var_dump($result);
```

### Step 2: Compare Results

```php
// Import same property with both importers
$property_data = $property_mapper->map_property($xml_property);

// Test with legacy
update_option('realestate_sync_use_api_importer', false);
$legacy_result = $import_engine->process_single_property($property_data);

// Test with API
update_option('realestate_sync_use_api_importer', true);
$api_result = $import_engine->process_single_property($property_data);

// Compare metadata
$legacy_post_id = $legacy_result['post_id'];
$api_post_id = $api_result['post_id'];

// Check gallery
$legacy_gallery = get_post_meta($legacy_post_id, 'wpestate_property_gallery', true);
$api_gallery = get_post_meta($api_post_id, 'wpestate_property_gallery', true);

echo "Legacy gallery count: " . count($legacy_gallery) . "\n";
echo "API gallery count: " . count($api_gallery) . "\n";
```

### Step 3: Full Migration

Once testing is successful:

```php
// Permanently enable API importer
update_option('realestate_sync_use_api_importer', true);

// Run full import
$result = $import_engine->import_from_xml('/path/to/full-catalog.xml');

// Monitor logs for any issues
$logs = $logger->get_recent_logs();
foreach ($logs as $log) {
    if ($log['level'] === 'ERROR') {
        echo "Error: " . $log['message'] . "\n";
    }
}
```

### Step 4: Remove Legacy Code (Optional)

Once confident in API importer:

1. Keep `RealEstate_Sync_WP_Importer` for backward compatibility
2. Document legacy status in code comments
3. Consider deprecation notices for future versions

## Performance Considerations

### API Rate Limiting

The batch processor includes a 0.1-second delay between properties:

```php
// In batch_process()
foreach ($mapped_properties as $property) {
    $result = $this->process_property($property);
    usleep(100000); // 0.1 seconds
}
```

**Processing Speed:**
- ~10 properties per second
- ~600 properties per minute
- ~36,000 properties per hour (theoretical)

For large catalogs (1000+ properties), consider:
- Chunked processing with progress tracking
- Scheduled cron jobs for incremental imports
- Monitoring server load during imports

### Token Caching

JWT tokens are cached for 9 minutes, reducing authentication overhead:

```php
// First property: Authenticate
// Properties 2-N (within 9 min): Use cached token
// After 9 min: Re-authenticate automatically
```

**Benefit**: ~99% reduction in authentication requests for batch imports.

## Security Notes

1. **JWT Token Security**: Tokens are stored in memory only (not persisted to database)
2. **API Credentials**: Consider using WordPress constants or environment variables:
   ```php
   define('REALESTATE_SYNC_API_USERNAME', getenv('WP_API_USERNAME'));
   define('REALESTATE_SYNC_API_PASSWORD', getenv('WP_API_PASSWORD'));
   ```
3. **Test Script**: Delete `test-wp-importer-api.php` after testing (requires admin privileges)
4. **HTTPS**: Ensure API endpoint uses HTTPS in production

## Troubleshooting

### Issue: Gallery images not appearing

**Diagnosis:**
```php
$post_id = 5197;
$gallery = get_post_meta($post_id, 'wpestate_property_gallery', true);

if (empty($gallery)) {
    echo "Gallery is empty\n";

    // Check API response
    $api_writer = new RealEstate_Sync_WPResidence_API_Writer();
    $result = $api_writer->get_last_response(); // Debug method
    var_dump($result);
}
```

**Solution:**
- Verify image URLs are accessible (not behind authentication)
- Check WPResidence API logs for image import errors
- Ensure gallery format is correct: `array('url' => 'https://...')`

### Issue: Properties not updating (always creating duplicates)

**Diagnosis:**
```php
$import_id = 'PROP_12345';
$existing = get_posts(array(
    'post_type' => 'estate_property',
    'meta_key' => 'property_import_id',
    'meta_value' => $import_id,
    'posts_per_page' => 1,
));

if (empty($existing)) {
    echo "Duplicate detection not working\n";
    // Check if import_id is being saved
}
```

**Solution:**
- Verify `property_import_id` is being saved in tracking metadata
- Check for whitespace or encoding issues in import_id
- Ensure `find_existing_property()` is being called

### Issue: API authentication keeps failing

**Diagnosis:**
```bash
curl -X POST "http://localhost/trentino-wp/wp-json/jwt-auth/v1/token" \
     -H "Content-Type: application/json" \
     -d '{"username":"accessi@prioloweb.it","password":"2#&211`%#5+z"}'
```

**Solution:**
- Verify JWT Authentication plugin is active
- Check `.htaccess` for JWT authorization header support
- Ensure credentials match WordPress user account

## Support and Resources

- **WPResidence API Documentation**: https://wpresidence.net/documentation/
- **Plugin GitHub Repository**: https://github.com/andreacianni/realestate-sync-plugin
- **Logger Class**: See `class-realestate-sync-logger.php` for logging configuration
- **Test Scripts**: Use `test-wp-importer-api.php` for validation

## Version History

- **v1.4.0** (Current): API-based importer introduced
  - New: `RealEstate_Sync_WPResidence_API_Writer`
  - New: `RealEstate_Sync_WP_Importer_API`
  - Modified: `RealEstate_Sync_Import_Engine` (switchable importers)
  - 78% code reduction vs legacy importer

- **v1.0.0 - v1.3.x**: Legacy importer (`RealEstate_Sync_WP_Importer`)
  - Direct meta field manipulation
  - Manual gallery processing
  - Still available for backward compatibility
