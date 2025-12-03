# Bug Fix Report: Province Filter in Agency Parser

**Date**: 30 Novembre 2025, 23:30
**Severity**: CRITICAL
**Affected File**: `includes/class-realestate-sync-agency-parser.php`
**Version**: 1.3.0 → 1.3.1
**Protected File**: ✅ YES (Exception Approved)

---

## 🐛 Bug Description

### Problem
Agency_Parser::extract_agencies_from_xml() extracted **ALL agencies from entire Italy** instead of only agencies with properties in Trentino-Alto Adige (TN/BZ).

### Impact
- Production import extracted 629 agencies total
- Agencies from: Padova, Verona, Vicenza, Brescia, Venezia, etc.
- Expected: ~50-80 agencies only from TN/BZ
- Database polluted with 175+ agencies from wrong provinces
- Wrong agencies displayed in frontend

### Why Bug Was Not Detected
- Test file `test-property-complete-fixed.xml` only contained:
  - 3 properties (all from TN)
  - 2 agencies (both from TN)
- No test data from other provinces → filter never tested
- Golden code (commit cbbc9c0) verified working with test file
- Bug only revealed during full production import (805+ items)

---

## 🔍 Root Cause Analysis

### XML Structure
```xml
<annuncio>
    <agenzia>
        <id>326</id>
        <ragione_sociale>Immobiliare Example</ragione_sociale>
        <provincia>PD</provincia>  ← Agency's office location
        <!-- ... other agency fields ... -->
    </agenzia>
    <info>
        <comune_istat>028001</comune_istat>  ← Property location (Padova!)
        <id>12345</id>
        <!-- ... property fields ... -->
    </info>
</annuncio>
```

### Original Code (WRONG)
```php
// class-realestate-sync-agency-parser.php, line 52-62
foreach ($xml_data->annuncio as $annuncio) {
    if (isset($annuncio->agenzia)) {
        $agency_data = $this->parse_agency_data($annuncio->agenzia);

        if ($agency_data && !in_array($agency_data['id'], $agency_ids_seen)) {
            $agencies[] = $agency_data;  // ❌ NO FILTER!
            // ...
        }
    }
}
```

**Problem**: No check on comune_istat before extracting agency.

### Why <provincia> Field Is Wrong
- `<provincia>` is inside `<agenzia>` section
- Represents agency's **office location**
- Does NOT represent property location
- An agency in Padova CAN have properties in Trento
- We want agencies that have **properties in TN/BZ**, not agencies located in TN/BZ

### Correct Filter Logic
An agency should be included ONLY if:
1. It appears in an `<annuncio>`
2. That annuncio has `<info><comune_istat>` starting with 021 or 022

**Rationale**: We only care about agencies that have properties in our provinces.

---

## ✅ Fix Applied

### Code Changes

**File**: `includes/class-realestate-sync-agency-parser.php`

**Line 69-80** (NEW CODE):
```php
foreach ($xml_data->annuncio as $annuncio) {
    // 🐛 BUG FIX: Filter by province BEFORE extracting agency
    // Check comune_istat in annuncio->info section
    // Only extract agencies from annunci with TN/BZ properties
    $comune_istat = (string)($annuncio->info->comune_istat ?? '');

    // Skip annunci outside Trentino-Alto Adige
    // 021xxx = Provincia di Bolzano (BZ)
    // 022xxx = Provincia di Trento (TN)
    if (!preg_match('/^(021|022)/', $comune_istat)) {
        $skipped_count++;
        continue; // Skip this annuncio and its agency
    }

    // ONLY if annuncio is in TN/BZ → extract the agency
    if (isset($annuncio->agenzia)) {
        // ... extract agency ...
    }
}
```

### Filter Logic
- Check `annuncio->info->comune_istat` BEFORE parsing agency
- Skip entire annuncio if comune_istat doesn't start with 021 or 022
- 021xxx = Provincia di Bolzano (BZ)
- 022xxx = Provincia di Trento (TN)
- Same filter pattern used for properties

### Logging Enhancement
```php
$this->logger->log("Extracted unique agency: {$agency_data['ragione_sociale']} (ID: {$agency_data['id']}) - comune_istat: {$comune_istat}", 'info');
```

Added comune_istat to log for debugging.

```php
$this->logger->log("Agency extraction completed: " . count($agencies) . " unique agencies found (skipped {$skipped_count} annunci outside TN/BZ)", 'success');
```

Reports how many annunci were skipped.

---

## 📊 Expected Results

### Before Fix (Production Test 30-Nov-2025 23:00)
```
Total annunci scanned: ~3000+
Agencies extracted: 629 (ALL from entire Italy)
Agencies created: 175 (timeout after 4-5 min)
Properties created: 0 (never reached)
Provinces: PD, VR, VI, BS, VE, TN, BZ, etc.
```

### After Fix (Expected)
```
Total annunci scanned: ~3000+
Annunci TN/BZ: ~805
Agencies extracted: ~50-80 (ONLY agencies with TN/BZ properties)
Agencies created: ~50-80 (via batch system)
Properties created: ~725-730
Provinces: TN, BZ ONLY
```

---

## 🧪 Testing Plan

### Test 1: Small Dataset (Immediate)
- File: `test-property-complete-fixed.xml`
- Expected: 2 agencies (both TN)
- Verify: comune_istat logged for each agency
- Verify: No agencies from other provinces

### Test 2: Full Dataset (Production)
1. Clean database (delete 175 wrong agencies)
2. Run full import via batch system
3. Monitor logs for:
   - "skipped X annunci outside TN/BZ"
   - Agencies count ~50-80 (not 629)
   - All comune_istat start with 021 or 022
4. Verify frontend:
   - Only TN/BZ agencies visible
   - All properties have linked agencies

---

## 📋 Documentation Updates

### Files Modified
1. ✅ `class-realestate-sync-agency-parser.php`
   - Added province filter logic
   - Added detailed comments
   - Updated file header with bug fix note
   - Version bump: 1.3.0 → 1.3.1

2. ✅ `docs/PROTECTED_FILES.md`
   - Added bug fix exception section
   - Documented filter modification
   - Added exception policy guidelines
   - Added historical bug fixes list

3. ✅ `docs/BUGFIX_PROVINCE_FILTER.md` (this file)
   - Detailed bug report
   - Root cause analysis
   - Testing plan

4. ⏳ `docs/TEST_ANALYSIS_2025-11-30.md`
   - Already documented the issue discovery
   - Will update with fix results

---

## ✅ Exception Justification

### Why Protected File Was Modified

**Criteria Met**:
1. ✅ **Critical Logic Bug**: Wrong data imported (629 agencies vs 50-80)
2. ✅ **Not Detected in Testing**: Test file only had TN agencies
3. ✅ **Affects Core Functionality**: Database polluted, wrong agencies visible
4. ✅ **No Workaround Possible**: Filter must happen during extraction, not after

**Alternative Approaches Considered**:
- ❌ Filter in wrapper: Would need to re-parse XML to check comune_istat per agency
- ❌ Filter in Batch Processor: Inefficient, would extract 629 then discard 550+
- ❌ Create filtered wrapper class: Duplicates entire extraction logic
- ✅ **Fix in Agency_Parser**: Most efficient, correct location for filtering logic

**Documentation Compliance**:
- ✅ File header updated
- ✅ Code comments added
- ✅ PROTECTED_FILES.md updated
- ✅ Version incremented
- ✅ Bug report created

---

## 🔄 Rollback Plan

If the fix causes issues:

```bash
# Rollback to pre-fix version
git checkout HEAD~1 -- includes/class-realestate-sync-agency-parser.php

# Or restore from working tag
git checkout working-import-cbbc9c0 -- includes/class-realestate-sync-agency-parser.php
```

**Note**: Rollback will re-introduce the bug (extracts all agencies).

---

## 📞 Approval

**Requested By**: User (Andrea)
**Implemented By**: Claude (AI Assistant)
**Date**: 30 Novembre 2025, 23:30
**Status**: ✅ APPROVED - Bug fix exception granted

**User Quote**:
> "si e documenta la scelta commentando anche nel codice"

User explicitly approved modifying the protected file to fix the province filter bug.

---

**Created**: 30 Novembre 2025, 23:35
**Last Updated**: 30 Novembre 2025, 23:35
**Next Review**: After production testing with full dataset
