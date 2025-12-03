# Protected Files - DO NOT MODIFY

## 🛡️ Security Tag

**Tag**: `working-import-cbbc9c0`
**Commit**: `cbbc9c0`
**Date Verified**: 30-Nov-2025
**Status**: ✅ WORKING - Agencies with logos + Properties linked

## ⚠️ CRITICAL FILES - PROTECTED

These files are part of the verified working import system.
**DO NOT MODIFY THESE FILES DIRECTLY**

Any batch system implementation must use wrapper/adapter pattern.

### 1. Agency Manager
**File**: `includes/class-realestate-sync-agency-manager.php`
**Purpose**: Creates agencies WITH logos via GestionaleImmobiliare.it API
**Method to call**: `import_agencies($agencies, $mark_as_test)`
**Returns**: Array with 'imported', 'updated', 'skipped', 'agent_ids'

**DO NOT MODIFY**:
- `import_agencies()` method
- `create_or_update_agency()` method
- Logo download logic

### 2. WP Importer API
**File**: `includes/class-realestate-sync-wp-importer-api.php`
**Purpose**: Creates properties via WPResidence API
**Method to call**: `process_property($mapped_data)`
**Returns**: Array with 'post_id', 'action', 'success'

**DO NOT MODIFY**:
- `process_property()` method
- API integration logic
- Duplicate detection

### 3. Property Mapper
**File**: `includes/class-realestate-sync-property-mapper.php`
**Purpose**: Maps XML property data to WPResidence format
**Method to call**: `map_property($property_data)`
**Returns**: Array with mapped property data

**DO NOT MODIFY**:
- `map_property()` method
- Field mappings (80+ fields)
- Taxonomy mappings

### 4. Agency Parser
**File**: `includes/class-realestate-sync-agency-parser.php`
**Purpose**: Extracts agency data from XML
**Method to call**: `extract_agencies_from_xml($xml)`
**Returns**: Array of agency data arrays

**🐛 BUG FIX EXCEPTION - 30-Nov-2025 23:30**:
- Modified `extract_agencies_from_xml()` to add province filtering
- Filter added: comune_istat check (021xxx=BZ, 022xxx=TN)
- Reason: Original code extracted ALL agencies from entire Italy (629 total)
- Bug never detected because test file only had 2 TN agencies
- Production test revealed agencies from PD, VR, VI, BS, etc.
- Fix: Skip annunci with comune_istat outside TN/BZ BEFORE extracting agency
- This matches the same logic used for property filtering
- Version updated: 1.3.0 → 1.3.1

**DO NOT MODIFY** (except for critical bug fixes):
- XML parsing logic
- Agency data extraction
- Validation methods

## ✅ How to Use These Files in Batch System

### ❌ WRONG - Modifying the files:
```php
// DON'T DO THIS
class RealEstate_Sync_Agency_Manager {
    public function import_agencies($agencies, $mark_as_test) {
        // Adding batch logic here ❌
    }
}
```

### ✅ CORRECT - Wrapper pattern:
```php
// DO THIS INSTEAD
class RealEstate_Sync_Batch_Processor {

    private $agency_manager;
    private $wp_importer;
    private $property_mapper;
    private $agency_parser;

    public function process_agency($agency_id) {
        // 1. Load XML
        $xml = simplexml_load_file($this->xml_file_path);

        // 2. Extract agencies using protected parser
        $all_agencies = $this->agency_parser->extract_agencies_from_xml($xml);

        // 3. Find specific agency
        $agency_data = null;
        foreach ($all_agencies as $agency) {
            if ($agency['id'] === $agency_id) {
                $agency_data = $agency;
                break;
            }
        }

        // 4. Import using protected manager
        $agencies_array = array($agency_data);
        $result = $this->agency_manager->import_agencies($agencies_array, false);

        return $result;
    }

    public function process_property($property_id) {
        // 1. Load XML and parse property data
        // ... XML parsing code ...

        // 2. Map using protected mapper
        $mapped_data = $this->property_mapper->map_property($property_data);

        // 3. Import using protected importer
        $result = $this->wp_importer->process_property($mapped_data);

        return $result;
    }
}
```

## 🔒 Rollback Instructions

If something breaks during batch implementation:

```bash
# Return to working code
git checkout working-import-cbbc9c0

# Or if on branch
git reset --hard working-import-cbbc9c0

# Or view the tag
git show working-import-cbbc9c0
```

## 📋 Testing Checklist

Before modifying ANY batch code, ensure:
- [ ] Protected files have NOT been modified (check git diff)
- [ ] Test file import still works (3 properties + 7 agencies)
- [ ] Agencies have logos
- [ ] Properties are linked to agencies
- [ ] If any of above fails → rollback immediately

## 🚫 Never Do This

1. ❌ Don't modify protected files directly
2. ❌ Don't copy-paste code from protected files to batch processor
3. ❌ Don't "fix" the protected methods (they work!)
4. ❌ Don't add batch logic inside protected classes
5. ❌ Don't change method signatures of protected methods

## ✅ Always Do This

1. ✅ Use wrapper/adapter pattern
2. ✅ Call protected methods as-is
3. ✅ Test with small dataset after each change
4. ✅ Commit frequently with clear messages
5. ✅ If in doubt, ask before modifying

## 🐛 Exception Policy: Bug Fixes

**Protected files CAN be modified for critical bug fixes ONLY.**

### When a Bug Fix Exception is Allowed:

1. ✅ **Critical Logic Bug**: The protected code has incorrect logic (e.g., missing filter)
2. ✅ **Not Detected in Testing**: Bug was not caught because test data didn't reveal it
3. ✅ **Affects Core Functionality**: Bug causes major issues (e.g., imports wrong data)
4. ✅ **No Workaround Possible**: Cannot be fixed via wrapper pattern

### Documentation Required:

When applying a bug fix exception, you MUST:

1. **Update File Header**: Add `@bugfix-applied` tag with date and description
2. **Comment in Code**: Explain what was wrong and why the fix is needed
3. **Update This Document**: Add entry in the protected file's section
4. **Update Version**: Increment minor version (e.g., 1.3.0 → 1.3.1)
5. **Create Bug Report**: Document in separate file (e.g., `BUGFIX_PROVINCE_FILTER.md`)

### Example - Province Filter Bug Fix (30-Nov-2025):

**Problem**:
- Agency_Parser extracted ALL agencies (629 from entire Italy)
- Should only extract agencies with properties in TN/BZ
- Bug not detected because test file only had 2 TN agencies

**Fix Applied**:
- Added comune_istat filter in `extract_agencies_from_xml()`
- Skip annunci with comune_istat outside 021xxx/022xxx
- Matches same logic used for property filtering

**Why Exception Allowed**:
- Critical logic bug (wrong data imported)
- No workaround via wrapper (parser must filter during extraction)
- Properly documented with comments and version bump

### Historical Bug Fixes:

1. **30-Nov-2025**: Province filtering in Agency_Parser (v1.3.0 → 1.3.1)
   - File: `class-realestate-sync-agency-parser.php`
   - Reason: Extracted agencies from entire Italy instead of only TN/BZ
   - Fix: Added comune_istat filter before agency extraction

---

## 📞 Contact

If you need to modify a protected file, discuss first and document WHY.
Create a new issue/document explaining the change before proceeding.

For bug fix exceptions, follow the documentation policy above.
