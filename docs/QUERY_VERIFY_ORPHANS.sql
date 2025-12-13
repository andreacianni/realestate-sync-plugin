-- ============================================================================
-- QUERY 1: Post con property_import_id ma SENZA tracking
-- ============================================================================
-- Questi sono i post "orfani" che esistono su WP ma non nella tracking table

SELECT
    p.ID as wp_post_id,
    pm.meta_value as property_id,
    p.post_title,
    p.post_date,
    p.post_status,
    CASE
        WHEN t.property_id IS NULL THEN '❌ NO TRACKING'
        ELSE '✅ HAS TRACKING'
    END as tracking_status
FROM kre_posts p
JOIN kre_postmeta pm ON p.ID = pm.post_id
LEFT JOIN kre_realestate_sync_tracking t ON t.property_id = pm.meta_value
WHERE p.post_type = 'estate_property'
AND pm.meta_key = 'property_import_id'
AND pm.meta_value IN (
    '4488739', '4488831', '4554722', '4555668', '4562568',
    '4589478', '4611751', '4613963', '4626683', '4633033',
    '4633661', '4644206', '4645634', '4646499', '4648845',
    '4685330', '4695079'
)
ORDER BY pm.meta_value, p.ID;


-- ============================================================================
-- QUERY 2: Riepilogo per property_id (quanti post, quanti con tracking)
-- ============================================================================

SELECT
    pm.meta_value as property_id,
    COUNT(DISTINCT p.ID) as total_posts,
    COUNT(DISTINCT t.wp_post_id) as posts_with_tracking,
    COUNT(DISTINCT p.ID) - COUNT(DISTINCT t.wp_post_id) as orphan_posts,
    GROUP_CONCAT(DISTINCT p.ID ORDER BY p.ID ASC) as all_post_ids,
    MIN(p.ID) as oldest_post_id,
    MAX(p.ID) as newest_post_id
FROM kre_posts p
JOIN kre_postmeta pm ON p.ID = pm.post_id
LEFT JOIN kre_realestate_sync_tracking t ON (
    t.property_id = pm.meta_value
    AND t.wp_post_id = p.ID
)
WHERE p.post_type = 'estate_property'
AND pm.meta_key = 'property_import_id'
AND pm.meta_value IN (
    '4488739', '4488831', '4554722', '4555668', '4562568',
    '4589478', '4611751', '4613963', '4626683', '4633033',
    '4633661', '4644206', '4645634', '4646499', '4648845',
    '4685330', '4695079'
)
GROUP BY pm.meta_value
ORDER BY total_posts DESC, property_id;


-- ============================================================================
-- QUERY 3: Post duplicati (stesso property_id, più post)
-- ============================================================================

SELECT
    pm.meta_value as property_id,
    COUNT(*) as duplicate_count,
    MIN(p.ID) as keep_post_id,
    GROUP_CONCAT(p.ID ORDER BY p.ID ASC) as all_duplicate_ids,
    MIN(p.post_date) as first_created,
    MAX(p.post_date) as last_created,
    p.post_title
FROM kre_posts p
JOIN kre_postmeta pm ON p.ID = pm.post_id
WHERE p.post_type = 'estate_property'
AND pm.meta_key = 'property_import_id'
AND p.post_status != 'trash'
GROUP BY pm.meta_value, p.post_title
HAVING COUNT(*) > 1
ORDER BY duplicate_count DESC, property_id;


-- ============================================================================
-- QUERY 4: Verifica tracking table per questi property_id
-- ============================================================================

SELECT
    t.property_id,
    t.wp_post_id,
    t.property_hash,
    t.status,
    t.created_date,
    t.updated_date
    CASE
        WHEN p.ID IS NULL THEN '❌ POST NON ESISTE'
        WHEN p.post_status = 'trash' THEN '🗑️ POST IN TRASH'
        ELSE '✅ POST OK'
    END as post_status
FROM kre_realestate_sync_tracking t
LEFT JOIN kre_posts p ON p.ID = t.wp_post_id
WHERE t.property_id IN (
    '4488739', '4488831', '4554722', '4555668', '4562568',
    '4589478', '4611751', '4613963', '4626683', '4633033',
    '4633661', '4644206', '4645634', '4646499', '4648845',
    '4685330', '4695079'
)
ORDER BY t.property_id;
