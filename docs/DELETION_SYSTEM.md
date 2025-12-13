# Deletion System (v1.7.1)

## Overview

The deletion system handles properties and agencies marked as `<deleted>1</deleted>` in the XML feed. When an item is marked as deleted in the source data, it will be removed from WordPress along with all associated media files.

## How It Works

### Workflow

```
XML Import
    ↓
Province Filter (TN/BZ only)
    ↓
[STEP 1b: DELETION HANDLING] ← NEW in v1.7.1
    ├─→ Properties with deleted=1
    │   ├─→ Find WP post
    │   ├─→ Delete all attachments (images + thumbnails)
    │   ├─→ Delete post (hard delete, bypass trash)
    │   └─→ Update tracking table
    │
    └─→ Agencies with deleted=1
        ├─→ Find WP post
        ├─→ Delete featured image
        ├─→ Delete post (hard delete, bypass trash)
        └─→ Update tracking table
    ↓
Queue Creation (only active items)
    ↓
Import Processing
```

### Key Features

1. **Hard Delete**: Uses `wp_delete_post($id, true)` to bypass trash
2. **Complete Cleanup**: Deletes all attachments and thumbnails using `wp_delete_attachment($id, true)`
3. **Dry-Run Mode**: Test deletions without actually removing anything
4. **Email Notifications**: Alerts admin for significant deletions (>10 properties or >5 agencies)
5. **Tracking Updates**: Marks items as `deleted` in tracking tables

## Dry-Run vs Live Mode

### Dry-Run Mode (Default - SAFE)

- **What happens**: Deletion logic runs, but NO posts or files are actually deleted
- **Logging**: All actions logged to `debug.log` with `[DRY-RUN]` prefix
- **Email**: Notifications sent showing what WOULD be deleted
- **Best for**: Testing deletion logic, verifying which items will be affected

### Live Mode (DANGER)

- **What happens**: Posts and images are ACTUALLY deleted (cannot be undone!)
- **Logging**: All actions logged to `debug.log`
- **Email**: Notifications sent showing what WAS deleted
- **Best for**: Production use after verifying dry-run results

## Configuration

### Toggle Deletion Mode

Use the helper script to switch modes:

```bash
# Check current mode
php toggle-deletion-mode.php

# Enable dry-run (simulation)
php toggle-deletion-mode.php dry-run

# Enable live deletion (CAUTION!)
php toggle-deletion-mode.php live
```

### Manual Configuration

Set WordPress option directly:

```php
// Enable dry-run (safe mode)
update_option('realestate_sync_deletion_dry_run', true);

// Enable live deletion
update_option('realestate_sync_deletion_dry_run', false);
```

### Via phpMyAdmin

```sql
-- Check current mode
SELECT * FROM kre_options
WHERE option_name = 'realestate_sync_deletion_dry_run';

-- Enable dry-run (value = 'b:1;' means boolean true)
UPDATE kre_options
SET option_value = 'b:1;'
WHERE option_name = 'realestate_sync_deletion_dry_run';

-- Enable live mode (value = 'b:0;' means boolean false)
UPDATE kre_options
SET option_value = 'b:0;'
WHERE option_name = 'realestate_sync_deletion_dry_run';
```

## Testing Process

### 1. Initial Test (Dry-Run)

```bash
# Enable dry-run mode
php toggle-deletion-mode.php dry-run

# Run import (via admin panel or cron)
# Check debug.log for lines like:
#   [DRY-RUN] Would delete property 98981 (WP ID: 17542)
#   [DRY-RUN] Would delete 92 attachments
```

### 2. Verify Log Output

Look for these log entries:

```
[ORCHESTRATOR] STEP 1b: Handling deletions
[ORCHESTRATOR] Deletion mode: dry_run => true, mode => SIMULATION
[DELETION-MANAGER] [DRY-RUN] Property deletion summary:
    - Properties found: 2
    - Properties deleted: 2
    - Attachments deleted: 184
    - Disk space freed: 45.2 MB
```

### 3. Enable Live Mode

Only after verifying dry-run results:

```bash
# Enable live deletion
php toggle-deletion-mode.php live
# Type 'yes' to confirm

# Run import
# Deletions will now be executed!
```

### 4. Monitor Results

Check debug.log for actual deletions:

```
[DELETION-MANAGER] Deleted property 98981 (WP ID: 17542)
[DELETION-MANAGER] Deleted 92 attachments (12.5 MB freed)
```

## What Gets Deleted

### For Properties

1. **Post**: The `estate_property` post (hard delete, bypass trash)
2. **Gallery Images**: All images in property gallery
3. **Thumbnails**: WordPress-generated image sizes (e.g., 150x150, 300x300)
4. **Files**: Physical files removed from `/wp-content/uploads/`
5. **Metadata**: All post meta fields

### For Agencies

1. **Post**: The `estate_agent` post (hard delete, bypass trash)
2. **Featured Image**: Agency logo (if exists)
3. **Thumbnails**: Logo thumbnail versions
4. **Files**: Physical files removed from disk
5. **Metadata**: All post meta fields

## Email Notifications

Automatic email sent to admin when:

- More than 10 properties deleted in single import
- More than 5 agencies deleted in single import

**Email includes**:

- Import session ID
- Number of items deleted
- Disk space freed
- Timestamp
- Mode (dry-run or live)

## Database Tracking

Deleted items are marked in tracking tables:

### Properties

```sql
UPDATE kre_realestate_sync_tracking
SET status = 'deleted'
WHERE property_id = '98981';
```

### Agencies

```sql
UPDATE kre_realestate_sync_agency_tracking
SET status = 'deleted'
WHERE agency_id = '1811';
```

This prevents re-import attempts and maintains history.

## Troubleshooting

### No items deleted despite deleted=1 in XML

**Check**:

1. Province filter - only TN/BZ properties are processed
2. Item exists in WordPress - can't delete what doesn't exist
3. Dry-run mode enabled - check with `php toggle-deletion-mode.php`

### Attachments not deleted

**Possible causes**:

1. Images not attached to post (orphaned)
2. Permission issues (check file ownership)
3. Files already deleted manually

### Email notifications not sent

**Check**:

1. Threshold not reached (<10 properties, <5 agencies)
2. WordPress email configuration
3. Spam folder

## Safety Features

1. **Dry-run default**: Plugin defaults to safe mode
2. **Province filter**: Only TN/BZ items considered
3. **Confirmation required**: Live mode requires explicit confirmation
4. **Tracking updates**: Maintains deletion history
5. **Email alerts**: Admin notified of significant deletions

## Related Files

- `includes/class-realestate-sync-deletion-manager.php` - Core deletion logic
- `includes/class-realestate-sync-batch-orchestrator.php` - Integration point (STEP 1b)
- `toggle-deletion-mode.php` - Helper script for mode switching
- `docs/DELETION_SYSTEM.md` - This documentation

## User Requirements Met

✅ "dopo il filtro per provincia, gli annunci con del=1 vanno cancellati"

✅ Hard delete (not soft delete to trash)

✅ Delete all attached images and thumbnails (properties)

✅ Delete featured image (agencies)

✅ Dry-run only for deletion process (not entire import)

✅ Email notifications for significant deletions

## Version History

- **v1.7.1** - Initial deletion system implementation
  - Property deletion with full media cleanup
  - Agency deletion with featured image cleanup
  - Dry-run mode for safe testing
  - Email notifications
  - Tracking table integration
