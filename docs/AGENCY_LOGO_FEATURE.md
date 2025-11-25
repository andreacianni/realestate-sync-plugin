# Agency Logo Download Feature

**Data**: 2025-10-15
**Feature**: Automatic download e set di agency logo come featured image
**Status**: ✅ **IMPLEMENTATO**

---

## 📋 Feature Request

### User Requirements
1. Download agency logo da campo XML `<agenzia><logo>`
2. Settare logo come featured image dell'agency post
3. Verificare che `<url>` (website) venga valorizzato in `agency_website`

---

## 🔍 Analisi XML Structure

### XML Agency Fields
```xml
<agenzia>
    <id>13673</id>
    <ragione_sociale><![CDATA[Cerco Casa In Trentino Srl]]></ragione_sociale>
    <referente><![CDATA[Cerco Casa in Trentino S.r.l]]></referente>
    <iva>02307410221</iva>
    <comune istat="022029">Caderzone Terme</comune>
    <provincia>Trento</provincia>
    <indirizzo><![CDATA[Via Regina Elena 65/A]]></indirizzo>
    <email>info@cercocasaintrentino.it</email>
    <url></url>  <!-- Website agency -->
    <logo>https://media.gestionaleimmobiliare.it/logo/agenzie/13673/640x480/logo.jpg</logo>
    <telefono>0464514425</telefono>
    <cellulare>3245404646</cellulare>
</agenzia>
```

### Data Flow
```
XML <logo> field
    ↓
Import Engine (extract_agency_from_xml)
    ↓
agency_data['logo_url'] = $agency['logo']
    ↓
Agency Manager (extract_agency_data_from_xml)
    ↓
$agency_data['logo_url'] extracted
    ↓
create_agency() / update_agency()
    ↓
set_agency_logo() method
    ↓
✅ Logo downloaded + set as featured image
```

---

## ✅ Implementation Details

### 1. Data Extraction (Import Engine)

**File**: `class-realestate-sync-import-engine.php:301-302`

```php
// Method 4: Direct agency data array (from XML Parser v3.0)
if (empty($agency_data) && isset($property_data['agency_data']) && is_array($property_data['agency_data'])) {
    $agency = $property_data['agency_data'];
    $agency_data = [
        'id' => $agency['id'] ?? '',
        'name' => $agency['ragione_sociale'] ?? $agency['name'] ?? 'Agenzia Immobiliare',
        'address' => $this->build_agency_address_from_data($agency),
        'phone' => $agency['telefono'] ?? $agency['phone'] ?? '',
        'email' => $agency['email'] ?? '',
        'website' => $agency['url'] ?? $agency['website'] ?? '',  // ✅ WEBSITE MAPPING
        'logo_url' => $agency['logo'] ?? $agency['logo_url'] ?? '',  // ✅ LOGO MAPPING
        'contact_person' => $agency['referente'] ?? '',
        'vat_number' => $agency['iva'] ?? '',
        'province' => $agency['provincia'] ?? '',
        'city' => $agency['comune'] ?? '',
        'mobile' => $agency['cellulare'] ?? ''
    ];
}
```

**Status**: ✅ **Already implemented** - Both `website` and `logo_url` correctly mapped

### 2. Agency Manager Extraction

**File**: `class-realestate-sync-agency-manager.php:105-118`

```php
private function extract_agency_data_from_xml($xml_property) {
    // ...existing code...
    $agency_data['website'] = $this->get_xml_value($xml_property, 'website');
    $agency_data['logo_url'] = $this->get_xml_value($xml_property, 'logo_url');  // ✅ EXTRACTED
}
```

**Status**: ✅ **Already implemented**

### 3. Data Sanitization

**File**: `class-realestate-sync-agency-manager.php:188-209`

```php
private function sanitize_agency_data($agency_data) {
    $sanitized = array();

    // Sanitize each field
    $sanitized['name'] = sanitize_text_field($agency_data['name'] ?? '');
    $sanitized['xml_agency_id'] = sanitize_text_field($agency_data['xml_agency_id'] ?? '');
    // ... other fields ...
    $sanitized['website'] = esc_url_raw($agency_data['website'] ?? '');
    $sanitized['logo_url'] = esc_url_raw($agency_data['logo_url'] ?? '');  // ✅ ADDED
    // ... other fields ...

    return $sanitized;
}
```

**Status**: ✅ **NEW - Added in this session**

### 4. Meta Fields Mapping

**File**: `class-realestate-sync-agency-manager.php:210-212`

```php
private function prepare_agency_meta_fields($agency_data) {
    $meta_fields = array();

    // ... other fields ...

    if (!empty($agency_data['website'])) {
        $meta_fields['agency_website'] = $agency_data['website'];  // ✅ WEBSITE SAVED
    }

    // ... other fields ...
}
```

**Status**: ✅ **Already implemented**

### 5. Logo Download on Agency Creation

**File**: `class-realestate-sync-agency-manager.php:317-319`

```php
private function create_agency($agency_data) {
    // ... wp_insert_post ...

    // Download and set agency logo as featured image
    if (!empty($agency_data['logo_url'])) {
        $this->set_agency_logo($agency_id, $agency_data['logo_url']);  // ✅ LOGO DOWNLOADED
    }

    return $agency_id;
}
```

**Status**: ✅ **NEW - Added in this session**

### 6. Logo Update on Agency Update

**File**: `class-realestate-sync-agency-manager.php:357-363`

```php
private function update_agency($agency_id, $agency_data) {
    // ... wp_update_post + meta updates ...

    // Update agency logo if URL has changed
    if (!empty($agency_data['logo_url'])) {
        $current_logo_url = get_post_meta($agency_id, '_agency_logo_url', true);
        if ($current_logo_url !== $agency_data['logo_url']) {
            $this->set_agency_logo($agency_id, $agency_data['logo_url']);  // ✅ LOGO UPDATED
        }
    }

    return $agency_id;
}
```

**Status**: ✅ **NEW - Added in this session**

### 7. Logo Download Method

**File**: `class-realestate-sync-agency-manager.php:647-808`

```php
private function set_agency_logo($agency_id, $logo_url) {
    // 1. URL Validation
    if (empty($logo_url) || !filter_var($logo_url, FILTER_VALIDATE_URL)) {
        return false;
    }

    // 2. HTTP → HTTPS Conversion
    if (strpos($logo_url, 'http://') === 0) {
        $logo_url = str_replace('http://', 'https://', $logo_url);
    }

    // 3. HTTPS-only Security
    if (strpos($logo_url, 'https://') !== 0) {
        return false;
    }

    // 4. Download with wp_safe_remote_get (timeout 30s)
    $temp_file = wp_tempnam();
    $response = wp_safe_remote_get($logo_url, [
        'timeout' => 30,
        'stream' => true,
        'filename' => $temp_file,
        'sslverify' => true,
    ]);

    // 5. File Size Validation (max 2MB)
    $filesize = filesize($temp_file);
    $max_size = 2 * 1024 * 1024;
    if ($filesize > $max_size || $filesize <= 0) {
        return false;
    }

    // 6. MIME Type Validation
    $valid_mime_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $detected_mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $temp_file);
    if (!in_array($detected_mime, $valid_mime_types, true)) {
        return false;
    }

    // 7. Image Validation with getimagesize()
    $image_info = @getimagesize($temp_file);
    if ($image_info === false) {
        return false;
    }

    // 8. Move to WordPress uploads directory
    $upload_dir = wp_upload_dir();
    $filename = wp_unique_filename($upload_dir['path'], sanitize_file_name(basename($logo_url)));
    $filepath = $upload_dir['path'] . '/' . $filename;
    copy($temp_file, $filepath);

    // 9. Create WordPress attachment
    $attachment_id = wp_insert_attachment([
        'guid' => $upload_dir['url'] . '/' . $filename,
        'post_mime_type' => wp_check_filetype($filename)['type'],
        'post_title' => 'Logo ' . get_the_title($agency_id),
        'post_parent' => $agency_id,
        'post_status' => 'inherit',
    ], $filepath, $agency_id);

    // 10. Generate attachment metadata
    wp_generate_attachment_metadata($attachment_id, $filepath);

    // 11. Set as featured image
    set_post_thumbnail($agency_id, $attachment_id);

    // 12. Store logo URL for change detection
    update_post_meta($agency_id, '_agency_logo_url', $logo_url);

    return $attachment_id;
}
```

**Status**: ✅ **NEW - Fully implemented with security validations**

---

## 🔒 Security Features

### URL Validation
- ✅ Validate URL format with `filter_var(FILTER_VALIDATE_URL)`
- ✅ Convert HTTP → HTTPS automatically
- ✅ **HTTPS-only policy** - HTTP URLs rejected

### File Size Limits
- ✅ Max 2MB per logo
- ✅ Minimum size check (> 0 bytes)

### MIME Type Validation
- ✅ Strict MIME type check with `finfo`
- ✅ Allowed: JPEG, PNG, GIF, WebP
- ✅ Rejected: All other file types

### Image Integrity
- ✅ `getimagesize()` validation
- ✅ Ensures file is valid image format

### WordPress Best Practices
- ✅ Uses `wp_safe_remote_get()` (WordPress HTTP API)
- ✅ Timeout protection (30 seconds)
- ✅ SSL verification enabled
- ✅ Proper file permissions
- ✅ WordPress attachment system
- ✅ Automatic thumbnail generation

---

## 📊 Change Detection System

### Logo URL Tracking
```php
// Store logo URL in post meta for change detection
update_post_meta($agency_id, '_agency_logo_url', $logo_url);
```

### Update Logic
```php
// On agency update, download logo only if URL changed
$current_logo_url = get_post_meta($agency_id, '_agency_logo_url', true);
if ($current_logo_url !== $agency_data['logo_url']) {
    $this->set_agency_logo($agency_id, $agency_data['logo_url']);
}
```

**Benefit**: Avoids re-downloading same logo on every update, saves bandwidth and processing time

---

## 🧪 Testing Recommendations

### Test Case 1: New Agency with Logo
1. Import property with agency containing `<logo>` URL
2. **Expected**: Agency created with logo as featured image
3. **Verify**:
   ```bash
   php -r "require 'wp-load.php'; \
   $agency_id = AGENCY_ID; \
   $thumb_id = get_post_thumbnail_id($agency_id); \
   $logo_url = get_post_meta($agency_id, '_agency_logo_url', true); \
   echo 'Thumbnail ID: ' . $thumb_id . PHP_EOL; \
   echo 'Logo URL: ' . $logo_url . PHP_EOL;"
   ```

### Test Case 2: Agency without Logo
1. Import property with agency WITHOUT `<logo>` field
2. **Expected**: Agency created without featured image (graceful handling)

### Test Case 3: Logo URL Change
1. Update XML with different `<logo>` URL for same agency
2. Import property again
3. **Expected**: New logo downloaded and set as featured image

### Test Case 4: Same Logo URL
1. Re-import property with same agency + same logo URL
2. **Expected**: Logo NOT re-downloaded (change detection working)

### Test Case 5: Invalid Logo URL
1. Test with HTTP URL (should convert to HTTPS)
2. Test with invalid URL (should log warning, continue without logo)
3. Test with non-image file (should reject, continue without logo)

### Test Case 6: Website Field
1. Import agency with `<url>` field populated
2. **Expected**: `agency_website` meta field populated
3. **Verify**:
   ```bash
   php -r "require 'wp-load.php'; \
   $website = get_post_meta(AGENCY_ID, 'agency_website', true); \
   echo 'Website: ' . $website . PHP_EOL;"
   ```

---

## 📝 Database Fields

### Agency Post Meta
| Meta Key | Source XML | Example Value | Notes |
|----------|------------|---------------|-------|
| `agency_website` | `<url>` | `https://example.com` | Agency website |
| `_agency_logo_url` | `<logo>` | `https://.../logo.jpg` | Logo URL for change detection |
| `_thumbnail_id` | (computed) | `12345` | Featured image attachment ID |

---

## 🎯 Implementation Summary

### Files Modified
1. ✅ `class-realestate-sync-agency-manager.php`:
   - Added `logo_url` to `sanitize_agency_data()` (line 199)
   - Added `contact_person` to `sanitize_agency_data()` (line 206)
   - Added `set_agency_logo()` call in `create_agency()` (lines 317-319)
   - Added conditional logo update in `update_agency()` (lines 357-363)
   - Implemented complete `set_agency_logo()` method (lines 647-808)

2. ℹ️ `class-realestate-sync-import-engine.php`:
   - **No changes needed** - Already mapping `logo_url` and `website` correctly

### New Methods Added
- `set_agency_logo($agency_id, $logo_url)` - Complete logo download and featured image system

### Security Validations
- ✅ URL validation
- ✅ HTTPS enforcement
- ✅ File size limits
- ✅ MIME type validation
- ✅ Image integrity checks

---

## ✅ Requirements Checklist

- [x] Download agency logo da `<logo>` field
- [x] Set logo come featured image
- [x] Verify `<url>` → `agency_website` mapping (already working)
- [x] Security validations (HTTPS, size, MIME type)
- [x] Error handling and logging
- [x] Change detection system
- [x] Update existing agencies on logo URL change

---

**Conclusione**: Feature completamente implementata con validazioni di sicurezza robuste. Il logo dell'agenzia verrà scaricato automaticamente e settato come featured image durante l'import. Il website (`<url>`) era già correttamente mappato.
