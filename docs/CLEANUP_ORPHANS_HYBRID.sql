-- ============================================================================
-- HYBRID CLEANUP: Conserva singoli, cancella duplicati, reimporta
-- ============================================================================
-- Strategia: Trust but Verify
-- - Post singoli: TIENI + ricostruisci tracking
-- - Post duplicati: CANCELLA + reimporta dal gestionale
-- ============================================================================

-- ============================================================================
-- STEP 1: BACKUP (esegui PRIMA di tutto!)
-- ============================================================================

-- Backup post da cancellare
CREATE TABLE IF NOT EXISTS kre_backup_orphan_posts_20251209 AS
SELECT p.*, pm.meta_key, pm.meta_value
FROM kre_posts p
LEFT JOIN kre_postmeta pm ON p.ID = pm.post_id
WHERE p.ID IN (
    -- Duplicati da cancellare (18 post)
    88841, 88907, 89024, 89147,  -- 4589478
    93609, 93656, 93764, 93863,  -- 4644206
    93870, 93984, 94093, 94197,  -- 4645634
    95115, 95195, 95295, 95420,  -- 4685330
    84994, 85045                  -- 4488768
);

-- Verifica backup
SELECT COUNT(*) as backup_count FROM kre_backup_orphan_posts_20251209;
-- Dovrebbe essere 18 post


-- ============================================================================
-- STEP 2: RICOSTRUISCI TRACKING per POST SINGOLI (13 property)
-- ============================================================================
-- Questi post sono probabilmente OK, solo la tracking manca

INSERT INTO kre_realestate_sync_tracking
(property_id, wp_post_id, property_hash, status, created_date, updated_date)
VALUES
  ('4488739', 84889, MD5(CONCAT('4488739', '84889', NOW())), 'active', NOW(), NOW()),
  ('4488831', 85382, MD5(CONCAT('4488831', '85382', NOW())), 'active', NOW(), NOW()),
  ('4554722', 87133, MD5(CONCAT('4554722', '87133', NOW())), 'active', NOW(), NOW()),
  ('4555668', 87209, MD5(CONCAT('4555668', '87209', NOW())), 'active', NOW(), NOW()),
  ('4562568', 87478, MD5(CONCAT('4562568', '87478', NOW())), 'active', NOW(), NOW()),
  ('4611751', 90003, MD5(CONCAT('4611751', '90003', NOW())), 'active', NOW(), NOW()),
  ('4613963', 90506, MD5(CONCAT('4613963', '90506', NOW())), 'active', NOW(), NOW()),
  ('4626683', 91538, MD5(CONCAT('4626683', '91538', NOW())), 'active', NOW(), NOW()),
  ('4633033', 92480, MD5(CONCAT('4633033', '92480', NOW())), 'active', NOW(), NOW()),
  ('4633661', 92624, MD5(CONCAT('4633661', '92624', NOW())), 'active', NOW(), NOW()),
  ('4646499', 94509, MD5(CONCAT('4646499', '94509', NOW())), 'active', NOW(), NOW()),
  ('4648845', 94763, MD5(CONCAT('4648845', '94763', NOW())), 'active', NOW(), NOW()),
  ('4695079', 95984, MD5(CONCAT('4695079', '95984', NOW())), 'active', NOW(), NOW())
ON DUPLICATE KEY UPDATE
  wp_post_id = VALUES(wp_post_id),
  updated_date = NOW();

-- Verifica tracking creati
SELECT COUNT(*) as tracking_created
FROM kre_realestate_sync_tracking
WHERE property_id IN (
  '4488739', '4488831', '4554722', '4555668', '4562568',
  '4611751', '4613963', '4626683', '4633033', '4633661',
  '4646499', '4648845', '4695079'
);
-- Dovrebbe essere 13


-- ============================================================================
-- STEP 3: MARCA QUEUE ITEMS "DONE" per POST SINGOLI
-- ============================================================================
-- Ora che hanno tracking, possono essere marcati come completati

UPDATE kre_realestate_import_queue
SET status = 'done',
    wp_post_id = CASE item_id
        WHEN '4488739' THEN 84889
        WHEN '4488831' THEN 85382
        WHEN '4554722' THEN 87133
        WHEN '4555668' THEN 87209
        WHEN '4562568' THEN 87478
        WHEN '4611751' THEN 90003
        WHEN '4613963' THEN 90506
        WHEN '4626683' THEN 91538
        WHEN '4633033' THEN 92480
        WHEN '4633661' THEN 92624
        WHEN '4646499' THEN 94509
        WHEN '4648845' THEN 94763
        WHEN '4695079' THEN 95984
    END,
    processed_at = NOW()
WHERE item_id IN (
    '4488739', '4488831', '4554722', '4555668', '4562568',
    '4611751', '4613963', '4626683', '4633033', '4633661',
    '4646499', '4648845', '4695079'
)
AND item_type = 'property'
AND status IN ('processing', 'error', 'retry');


-- ============================================================================
-- STEP 4: SPOSTA DUPLICATI IN TRASH
-- ============================================================================
-- Questi post sono corrotti (retry loop), meglio ricrearli

UPDATE kre_posts
SET post_status = 'trash',
    post_modified = NOW(),
    post_modified_gmt = UTC_TIMESTAMP()
WHERE ID IN (
    -- 4589478 (x4)
    88841, 88907, 89024, 89147,
    -- 4644206 (x4)
    93609, 93656, 93764, 93863,
    -- 4645634 (x4)
    93870, 93984, 94093, 94197,
    -- 4685330 (x4)
    95115, 95195, 95295, 95420,
    -- 4488768 (x2)
    84994, 85045
);

-- Verifica post in trash
SELECT COUNT(*) as trashed_posts
FROM kre_posts
WHERE post_status = 'trash'
AND ID IN (
    88841, 88907, 89024, 89147,
    93609, 93656, 93764, 93863,
    93870, 93984, 94093, 94197,
    95115, 95195, 95295, 95420,
    84994, 85045
);
-- Dovrebbe essere 18


-- ============================================================================
-- STEP 5: CANCELLA TRACKING OBSOLETI (se esistono) per DUPLICATI
-- ============================================================================

DELETE FROM kre_realestate_sync_tracking
WHERE property_id IN ('4589478', '4644206', '4645634', '4685330', '4488768');


-- ============================================================================
-- STEP 6: RESETTA QUEUE per REIMPORTARE DUPLICATI
-- ============================================================================
-- Questi verranno reimportati con self-healing attivo (zero nuove duplicazioni)

UPDATE kre_realestate_import_queue
SET status = 'pending',
    wp_post_id = NULL,
    error_message = 'Duplicate posts cleaned - ready for reimport',
    retry_count = 0,
    processed_at = NULL
WHERE item_id IN ('4589478', '4644206', '4645634', '4685330', '4488768')
AND item_type = 'property';

-- Verifica queue resettate
SELECT item_id, status, error_message
FROM kre_realestate_import_queue
WHERE item_id IN ('4589478', '4644206', '4645634', '4685330', '4488768')
AND item_type = 'property';
-- Dovrebbe mostrare 5 righe con status='pending'


-- ============================================================================
-- STEP 7: VERIFICA FINALE
-- ============================================================================

-- Post singoli con tracking OK
SELECT
    'SINGLES' as type,
    COUNT(*) as count
FROM kre_realestate_sync_tracking t
JOIN kre_posts p ON p.ID = t.wp_post_id
WHERE t.property_id IN (
    '4488739', '4488831', '4554722', '4555668', '4562568',
    '4611751', '4613963', '4626683', '4633033', '4633661',
    '4646499', '4648845', '4695079'
)
AND p.post_status = 'publish'

UNION ALL

-- Post duplicati in trash
SELECT
    'DUPLICATES_TRASHED' as type,
    COUNT(*) as count
FROM kre_posts
WHERE ID IN (
    88841, 88907, 89024, 89147,
    93609, 93656, 93764, 93863,
    93870, 93984, 94093, 94197,
    95115, 95195, 95295, 95420,
    84994, 85045
)
AND post_status = 'trash'

UNION ALL

-- Property in queue pronte per reimport
SELECT
    'PENDING_REIMPORT' as type,
    COUNT(*) as count
FROM kre_realestate_import_queue
WHERE item_id IN ('4589478', '4644206', '4645634', '4685330', '4488768')
AND status = 'pending';

-- Risultato atteso:
-- SINGLES: 13
-- DUPLICATES_TRASHED: 18
-- PENDING_REIMPORT: 5


-- ============================================================================
-- DOPO QUESTO SCRIPT, FAI:
-- ============================================================================
-- 1. Implementa Self-Healing Manager
-- 2. Fix gestione timeout nel Import Engine
-- 3. Lancia import manuale o attendi cron per reimportare le 5 property
-- 4. Verifica che le 5 property vengano create SENZA duplicati
-- 5. Se tutto OK, elimina permanentemente i post in trash:
--
--    DELETE FROM kre_posts WHERE post_status = 'trash' AND ID IN (88841, ...);
--
-- ============================================================================
