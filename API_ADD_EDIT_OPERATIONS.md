# API Add/Edit Operations Documentation

**Data**: 2025-10-15
**Version**: 1.4.0
**Status**: ✅ **FULLY IMPLEMENTED**

---

## 📋 Overview

This document explains how the RealEstate Sync Plugin handles **Create (Add)** and **Update (Edit)** operations for both **Properties** and **Agencies** using the WPResidence REST API.

---

## 🏗️ Architecture

### Previous Approach (Legacy)
**Direct Database Operations**:
- Used `wp_insert_post()` / `wp_update_post()` directly
- Manual meta field mapping with `update_post_meta()`
- Manual image download and featured image assignment
- No automatic execution of WPResidence hooks

**Problems**:
- Bypassed WPResidence validation logic
- Required manual mapping of all meta fields
- Complex error handling
- Inconsistent with WPResidence standards

### Current Approach (API-Based)
**WPResidence REST API**:
- Uses official WPResidence REST endpoints
- JWT authentication for security
- Automatic meta field handling by WPResidence
- Automatic image download via `featured_image` field
- WPResidence hooks execute automatically

**Benefits**:
- Consistent with WPResidence standards
- Simplified code (API handles complexity)
- Better error handling and retry logic
- Future-proof (follows official API spec)

---

## 🏠 Properties: Add/Edit Operations

### 1. Property Creation (Add)

**Endpoint**: `POST /wp-json/wpresidence/v1/property/add`

**File**: `includes/class-realestate-sync-wpresidence-api-writer.php`

**Flow**:
```
XML Property Data
    ↓
Property Mapper (format_property_for_wpresidence)
    ↓
API Writer (format_api_body)
    ↓
JWT Authentication (get_jwt_token)
    ↓
POST /property/add
    ↓
WPResidence API Response
    ↓
Property ID Returned
```

**Code Example**:
```php
// 1. Format property data
$mapped_property = $this->property_mapper->format_property_for_wpresidence($xml_property);

// 2. Format API body
$api_body = $this->api_writer->format_api_body($mapped_property);

// 3. Create via API
$result = $this->api_writer->create_property($api_body);

if ($result['success']) {
    $property_id = $result['property_id'];
    // Store XML property ID for tracking
    update_post_meta($property_id, 'xml_property_id', $xml_property['id']);
}
```

**Required Fields**:
- `property_title` - Property title
- `property_description` - Property description
- `property_price` - Property price
- `property_category` - Property category taxonomy

**Optional Fields** (all handled by API):
- `property_address` - Full address
- `property_city` - City name
- `property_area` - Area/province
- `property_country` - Country
- `property_zip` - ZIP code
- `property_size` - Size in square meters
- `property_bedrooms` - Number of bedrooms
- `property_bathrooms` - Number of bathrooms
- `property_rooms` - Total number of rooms
- `property_agent` - Agency/Agent ID
- `sidebar_agent_option` - Sidebar display setting
- `featured_image` - Main property image (full HTTPS URL)
- `property_images` - Array of additional images (full HTTPS URLs)
- And many more...

**Image Handling**:
- `featured_image`: Full HTTPS URL → WPResidence downloads and sets as featured image
- `property_images`: Array of HTTPS URLs → WPResidence downloads and creates gallery

**Authentication**:
- JWT token obtained from `/wp-json/jwt-auth/v1/token`
- Token cached for 9 minutes (with 1-minute safety margin)
- Automatic token refresh on expiration

**Error Handling**:
- Network errors: Retry with exponential backoff (3 attempts)
- Server errors (500-599): Retry with exponential backoff
- Client errors (400-499): No retry, return error immediately
- Token expiration (403): Refresh token and retry once

### 2. Property Update (Edit)

**Endpoint**: `PUT /wp-json/wpresidence/v1/property/edit/{id}`

**File**: `includes/class-realestate-sync-wpresidence-api-writer.php`

**Flow**:
```
Existing Property ID + Updated XML Data
    ↓
Property Mapper (format_property_for_wpresidence)
    ↓
API Writer (format_api_body)
    ↓
JWT Authentication (get_jwt_token)
    ↓
PUT /property/edit/{id}
    ↓
WPResidence API Response
    ↓
Property ID Returned
```

**Code Example**:
```php
// 1. Find existing property
$existing_property_id = $this->find_property_by_xml_id($xml_property['id']);

// 2. Format property data
$mapped_property = $this->property_mapper->format_property_for_wpresidence($xml_property);

// 3. Format API body
$api_body = $this->api_writer->format_api_body($mapped_property);

// 4. Update via API
$result = $this->api_writer->update_property($existing_property_id, $api_body);

if ($result['success']) {
    // Property updated successfully
    // WPResidence API handles all meta updates and image updates
}
```

**What Gets Updated**:
- All property meta fields (address, price, size, etc.)
- Featured image (if `featured_image` URL provided)
- Property gallery (if `property_images` array provided)
- Property title and description
- All taxonomy terms (categories, features, etc.)

**What Doesn't Get Updated**:
- Post slug (URL) - remains unchanged
- Post ID - obviously unchanged
- Creation date - only modification date changes

---

## 🏢 Agencies: Add/Edit Operations

### 1. Agency Creation (Add)

**Endpoint**: `POST /wp-json/wpresidence/v1/agency/add`

**File**: `includes/class-realestate-sync-wpresidence-agency-api-writer.php`

**Flow**:
```
XML Agency Data (from property)
    ↓
Agency Manager (extract_agency_data_from_xml)
    ↓
Agency Manager (sanitize_agency_data)
    ↓
Agency API Writer (format_api_body)
    ↓
JWT Authentication (get_jwt_token)
    ↓
POST /agency/add
    ↓
WPResidence API Response
    ↓
Agency ID Returned
```

**Code Example**:
```php
// 1. Extract agency data from XML property
$agency_data = $this->extract_agency_data_from_xml($xml_property);

// 2. Check if agency already exists
$existing_agency_id = $this->find_existing_agency($agency_data);

if (!$existing_agency_id) {
    // 3. Format API body
    $api_body = $this->api_writer->format_api_body($agency_data);

    // 4. Create via API
    $result = $this->api_writer->create_agency($api_body);

    if ($result['success']) {
        $agency_id = $result['agency_id'];
        // Store XML agency ID for tracking
        update_post_meta($agency_id, 'xml_agency_id', $agency_data['xml_agency_id']);
    }
}
```

**Required Fields**:
- `agency_name` - Agency name (from `<ragione_sociale>`)
- `agency_email` - Agency email

**Optional Fields** (all handled by API):
- `agency_address` - Full address
- `agency_phone` - Phone number
- `agency_mobile` - Mobile number
- `agency_website` - Website URL (WITHOUT `http://` protocol)
- `agency_city` - City
- `agency_state` - Province/State
- `agency_zip` - ZIP code
- `agency_license` - License/VAT number
- `agency_languages` - Languages (default: "Italiano")
- `featured_image` - Agency logo (FULL HTTPS URL)

**URL Formatting Rules**:
- `agency_website`: Protocol removed (e.g., `"example.com"`)
  ```php
  // Remove http:// or https://
  $website = preg_replace('#^https?://#', '', $website);
  $website = rtrim($website, '/');
  ```
- `featured_image`: Full HTTPS URL (e.g., `"https://...logo.jpg"`)
  ```php
  // Ensure HTTPS
  if (strpos($logo_url, 'http://') === 0) {
      $logo_url = str_replace('http://', 'https://', $logo_url);
  }
  ```

**Logo Handling**:
- XML field: `<logo>https://media.example.com/logo.jpg</logo>`
- Mapped to: `featured_image` in API body
- WPResidence API automatically:
  - Downloads the image
  - Creates WordPress attachment
  - Sets as featured image
  - Generates thumbnails

**Authentication**: Same JWT system as Property API Writer

**Error Handling**: Same retry logic as Property API Writer

### 2. Agency Update (Edit)

**Endpoint**: `PUT /wp-json/wpresidence/v1/agency/edit/{id}`

**File**: `includes/class-realestate-sync-wpresidence-agency-api-writer.php`

**Flow**:
```
Existing Agency ID + Updated XML Data
    ↓
Agency Manager (extract_agency_data_from_xml)
    ↓
Agency Manager (sanitize_agency_data)
    ↓
Agency API Writer (format_api_body)
    ↓
JWT Authentication (get_jwt_token)
    ↓
PUT /agency/edit/{id}
    ↓
WPResidence API Response
    ↓
Agency ID Returned
```

**Code Example**:
```php
// 1. Find existing agency
$existing_agency_id = $this->find_existing_agency($agency_data);

if ($existing_agency_id) {
    // 2. Format API body
    $api_body = $this->api_writer->format_api_body($agency_data);

    // 3. Update via API
    $result = $this->api_writer->update_agency($existing_agency_id, $api_body);

    if ($result['success']) {
        // Agency updated successfully
        // WPResidence API handles all meta updates and logo update
    }
}
```

**What Gets Updated**:
- All agency meta fields (address, phone, email, etc.)
- Featured image/logo (if `featured_image` URL provided)
- Agency name (post title)

**What Doesn't Get Updated**:
- Post slug (URL) - remains unchanged
- Post ID - obviously unchanged
- Creation date - only modification date changes

---

## 🔐 JWT Authentication System

### Shared Between Property and Agency APIs

**Authentication Endpoint**: `POST /wp-json/jwt-auth/v1/token`

**Credentials Source**: WordPress options
- Username: `get_option('realestate_sync_api_username')`
- Password: `get_option('realestate_sync_api_password')`

**Token Lifecycle**:
```
Request Token
    ↓
Receive JWT Token + Expiration
    ↓
Cache Token (9 minutes)
    ↓
Reuse Token for Subsequent Requests
    ↓
Token Expires (403 response)
    ↓
Refresh Token Automatically
    ↓
Retry Original Request
```

**Implementation** (in both API Writers):
```php
private function get_jwt_token() {
    // Check if current token is still valid (with 1 minute safety margin)
    if ($this->jwt_token && $this->jwt_expiration && time() < ($this->jwt_expiration - 60)) {
        return $this->jwt_token;
    }

    // Get credentials
    $username = get_option('realestate_sync_api_username', '');
    $password = get_option('realestate_sync_api_password', '');

    // Request new token
    $response = wp_remote_post($this->jwt_auth_url, array(
        'body'    => json_encode(array(
            'username' => $username,
            'password' => $password,
        )),
        'headers' => array('Content-Type' => 'application/json'),
        'timeout' => 30,
    ));

    // Extract token
    $body = json_decode(wp_remote_retrieve_body($response), true);
    $this->jwt_token = $body['data']['token'];
    $this->jwt_expiration = time() + (9 * 60); // 9 minutes

    return $this->jwt_token;
}
```

**Security Features**:
- Credentials stored in WordPress options (not in code)
- Token cached in memory (not in database)
- Token auto-refresh on expiration
- HTTPS required for API calls

---

## 🔄 Change Detection System

### Properties

**Tracking Method**:
- Store `xml_property_id` in post meta
- Compare XML ID to find existing properties

**Detection Logic**:
```php
// Find existing property by XML ID
$meta_query = array(
    array(
        'key' => 'xml_property_id',
        'value' => $xml_property['id'],
        'compare' => '='
    )
);

$query = new WP_Query(array(
    'post_type' => 'estate_property',
    'meta_query' => $meta_query,
));

if ($query->have_posts()) {
    // Property exists - UPDATE
    $property_id = $query->posts[0]->ID;
    $this->update_property($property_id, $xml_property);
} else {
    // Property doesn't exist - CREATE
    $this->create_property($xml_property);
}
```

### Agencies

**Tracking Method**:
- Store `xml_agency_id` in post meta
- Compare XML ID to find existing agencies
- Fallback: Search by agency name

**Detection Logic**:
```php
// 1. Try to find by XML agency ID first (most accurate)
$meta_query = array(
    array(
        'key' => 'xml_agency_id',
        'value' => $agency_data['xml_agency_id'],
        'compare' => '='
    )
);

$query = new WP_Query(array(
    'post_type' => 'estate_agency',
    'meta_query' => $meta_query,
));

if ($query->have_posts()) {
    // Agency exists - UPDATE
    $agency_id = $query->posts[0];
    $this->update_agency($agency_id, $agency_data);
} else {
    // 2. Try by name (fallback)
    $query = new WP_Query(array(
        'post_type' => 'estate_agency',
        'title' => $agency_data['name'],
    ));

    if ($query->have_posts()) {
        // Agency found by name - UPDATE
        $agency_id = $query->posts[0];
        $this->update_agency($agency_id, $agency_data);
    } else {
        // Agency doesn't exist - CREATE
        $this->create_agency($agency_data);
    }
}
```

---

## 📊 Comparison: Direct DB vs API Approach

| Aspect | Direct Database (Legacy) | REST API (Current) |
|--------|--------------------------|-------------------|
| **Code Complexity** | High (manual everything) | Low (API handles it) |
| **Meta Field Mapping** | Manual `update_post_meta()` for each field | Automatic via API body |
| **Image Handling** | Manual download + attachment creation | Automatic via `featured_image` |
| **Validation** | Manual validation required | WPResidence validates |
| **Hooks Execution** | Bypassed (direct DB write) | Executed automatically |
| **Error Handling** | Basic | Robust (retry logic) |
| **Future Compatibility** | May break on theme updates | Future-proof (follows API spec) |
| **Authentication** | N/A (direct DB access) | JWT token (secure) |
| **Logging** | Manual logging needed | Built-in API logging |
| **Performance** | Slightly faster (no HTTP) | Slightly slower (HTTP overhead) |
| **Consistency** | May diverge from theme standards | Always consistent with theme |

**Recommendation**: Use REST API approach for all new development and migrations.

---

## 🔧 API Request Format Examples

### Property Creation Request

```http
POST /wp-json/wpresidence/v1/property/add HTTP/1.1
Host: trentino-immobiliare.it
Content-Type: application/json
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci...

{
  "property_title": "Appartamento in Centro Storico",
  "property_description": "Bellissimo appartamento...",
  "property_price": "350000",
  "property_address": "Via Roma 123, Trento TN 38122, Italia",
  "property_city": "Trento",
  "property_area": "Trento",
  "property_country": "Italia",
  "property_zip": "38122",
  "property_size": "120",
  "property_bedrooms": "3",
  "property_bathrooms": "2",
  "property_rooms": "5",
  "property_agent": "5179",
  "sidebar_agent_option": "global",
  "featured_image": "https://media.example.com/photo1.jpg",
  "property_images": [
    "https://media.example.com/photo2.jpg",
    "https://media.example.com/photo3.jpg"
  ]
}
```

**Response**:
```json
{
  "status": "success",
  "property_id": 5254,
  "message": "Property created successfully"
}
```

### Agency Creation Request

```http
POST /wp-json/wpresidence/v1/agency/add HTTP/1.1
Host: trentino-immobiliare.it
Content-Type: application/json
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGci...

{
  "agency_name": "Cerco Casa In Trentino Srl",
  "agency_email": "info@cercocasaintrentino.it",
  "agency_address": "Via Regina Elena 65/A",
  "agency_phone": "0464514425",
  "agency_mobile": "3245404646",
  "agency_website": "cercocasaintrentino.it",
  "agency_city": "Caderzone Terme",
  "agency_state": "Trento",
  "agency_license": "02307410221",
  "agency_languages": "Italiano",
  "featured_image": "https://media.gestionaleimmobiliare.it/logo/agenzie/13673/640x480/logo.jpg"
}
```

**Response**:
```json
{
  "status": "success",
  "agency_id": 5179,
  "message": "Agency created successfully"
}
```

---

## 🐛 Error Handling

### Common Errors and Solutions

#### 1. JWT Authentication Failed
**Error**: `Failed to obtain JWT authentication token`

**Causes**:
- Invalid credentials in WordPress options
- JWT plugin not installed/activated
- User permissions insufficient

**Solution**:
```bash
# Verify credentials are set
php -r "require 'wp-load.php';
echo 'Username: ' . get_option('realestate_sync_api_username') . PHP_EOL;
echo 'Password set: ' . (!empty(get_option('realestate_sync_api_password')) ? 'Yes' : 'No') . PHP_EOL;"

# Test JWT endpoint manually
curl -X POST "http://localhost/trentino-wp/wp-json/jwt-auth/v1/token" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"your_password"}'
```

#### 2. API Request Timeout
**Error**: `API request failed: timeout`

**Causes**:
- Slow network connection
- Large image downloads
- Server overload

**Solution**:
- Automatic retry with exponential backoff (built-in)
- Increase timeout in API Writer (default: 120 seconds)

#### 3. Invalid Image URL
**Error**: `Failed to download property image`

**Causes**:
- HTTP URL (HTTPS required)
- Broken/expired URL
- Image too large (>5MB for properties, >2MB for agencies)

**Solution**:
- API Writer automatically converts HTTP to HTTPS
- Invalid URLs are logged and skipped (property/agency still created)
- No manual intervention required

#### 4. Missing Required Fields
**Error**: `Property/Agency creation failed: Missing required fields`

**Causes**:
- XML missing `<ragione_sociale>` for agency
- XML missing property title or description

**Solution**:
- Check XML structure
- Import Engine validates and logs missing fields
- Property/Agency creation skipped if required fields missing

---

## 🧪 Testing

### Test Property Creation

```bash
# 1. Import a test property XML
php -r "require 'wp-load.php';
\$xml = simplexml_load_file('test_property.xml');
\$engine = new RealEstate_Sync_Import_Engine();
\$result = \$engine->import_properties(\$xml);
print_r(\$result);"

# 2. Verify property was created via API
php -r "require 'wp-load.php';
\$property_id = 5254;
\$xml_id = get_post_meta(\$property_id, 'xml_property_id', true);
\$agent_id = get_post_meta(\$property_id, 'property_agent', true);
echo 'Property ID: ' . \$property_id . PHP_EOL;
echo 'XML ID: ' . \$xml_id . PHP_EOL;
echo 'Agent ID: ' . \$agent_id . PHP_EOL;"

# 3. Check if sidebar appears correctly
# Visit: http://localhost/trentino-wp/property/test-property/
# Verify agency contact form appears in sidebar
```

### Test Agency Creation

```bash
# 1. Import a property with agency data
php -r "require 'wp-load.php';
\$xml = simplexml_load_file('test_property_with_agency.xml');
\$engine = new RealEstate_Sync_Import_Engine();
\$result = \$engine->import_properties(\$xml);
print_r(\$result);"

# 2. Verify agency was created via API
php -r "require 'wp-load.php';
\$agency_id = 5179;
\$xml_id = get_post_meta(\$agency_id, 'xml_agency_id', true);
\$website = get_post_meta(\$agency_id, 'agency_website', true);
\$thumb_id = get_post_thumbnail_id(\$agency_id);
echo 'Agency ID: ' . \$agency_id . PHP_EOL;
echo 'XML ID: ' . \$xml_id . PHP_EOL;
echo 'Website: ' . \$website . PHP_EOL;
echo 'Logo Thumb ID: ' . \$thumb_id . PHP_EOL;"
```

---

## 📁 Related Files

### Property Operations
- `includes/class-realestate-sync-import-engine.php` - Main import orchestration
- `includes/class-realestate-sync-property-mapper.php` - XML → WP data mapping
- `includes/class-realestate-sync-wpresidence-api-writer.php` - Property API operations

### Agency Operations
- `includes/class-realestate-sync-agency-manager.php` - Agency creation/update orchestration
- `includes/class-realestate-sync-wpresidence-agency-api-writer.php` - Agency API operations

### Supporting Files
- `includes/class-realestate-sync-logger.php` - Logging system
- WPResidence Theme API: `wp-content/plugins/wpresidence-core/api/rest/`

---

## ✅ Summary

### Properties
- **Create**: `POST /property/add` via `RealEstate_Sync_WPResidence_API_Writer::create_property()`
- **Update**: `PUT /property/edit/{id}` via `RealEstate_Sync_WPResidence_API_Writer::update_property()`
- **Images**: Handled via `featured_image` and `property_images` fields
- **Agency Link**: Handled via `property_agent` and `sidebar_agent_option` fields

### Agencies
- **Create**: `POST /agency/add` via `RealEstate_Sync_WPResidence_Agency_API_Writer::create_agency()`
- **Update**: `PUT /agency/edit/{id}` via `RealEstate_Sync_WPResidence_Agency_API_Writer::update_agency()`
- **Logo**: Handled via `featured_image` field (full HTTPS URL)
- **Website**: Handled via `agency_website` field (NO protocol)

### Benefits of API Approach
- ✅ Future-proof (follows official WPResidence API spec)
- ✅ Automatic validation and sanitization
- ✅ Automatic image handling
- ✅ WPResidence hooks execute correctly
- ✅ Robust error handling with retry logic
- ✅ JWT authentication for security
- ✅ Simplified codebase

---

**Conclusione**: Il sistema ora utilizza esclusivamente le REST API di WPResidence per tutte le operazioni di creazione e aggiornamento di properties e agencies, garantendo massima compatibilità e manutenibilità futura.
