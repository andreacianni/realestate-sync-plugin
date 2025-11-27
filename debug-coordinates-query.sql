-- Query di debug per analizzare ESATTAMENTE come sono salvate le coordinate

-- 1. VALORE ESATTO + LUNGHEZZA + TIPO (mostra caratteri nascosti)
SELECT
    p.ID as property_id,
    p.post_title,
    pm.meta_key,
    pm.meta_value as value,
    LENGTH(pm.meta_value) as byte_length,
    CHAR_LENGTH(pm.meta_value) as char_length,
    HEX(pm.meta_value) as hex_value,  -- Mostra encoding esatto
    CAST(pm.meta_value AS DECIMAL(10,7)) as as_decimal,
    pm.meta_value + 0 as as_number  -- Conversione MySQL
FROM kre_posts p
LEFT JOIN kre_postmeta pm ON p.ID = pm.post_id
WHERE p.ID IN (59, 5660, 5671, 5677)
    AND pm.meta_key IN ('property_latitude', 'property_longitude')
ORDER BY p.ID, pm.meta_key;

-- 2. CONFRONTO ESATTO TRA MANUALE (59) E IMPORTATE (5660+)
SELECT
    'MANUAL (ID 59)' as type,
    pm.meta_key,
    pm.meta_value,
    LENGTH(pm.meta_value) as length,
    HEX(pm.meta_value) as hex
FROM kre_postmeta pm
WHERE pm.post_id = 59
    AND pm.meta_key IN ('property_latitude', 'property_longitude')

UNION ALL

SELECT
    'IMPORTED (ID 5660)' as type,
    pm.meta_key,
    pm.meta_value,
    LENGTH(pm.meta_value) as length,
    HEX(pm.meta_value) as hex
FROM kre_postmeta pm
WHERE pm.post_id = 5660
    AND pm.meta_key IN ('property_latitude', 'property_longitude');

-- 3. CERCA CARATTERI INVISIBILI O ENCODING PROBLEMS
SELECT
    p.ID,
    pm.meta_key,
    pm.meta_value,
    -- Controlla spazi bianchi
    TRIM(pm.meta_value) as trimmed,
    pm.meta_value = TRIM(pm.meta_value) as is_clean,
    -- Controlla separatore decimale
    INSTR(pm.meta_value, '.') as has_dot,
    INSTR(pm.meta_value, ',') as has_comma,
    -- Controlla caratteri non-ASCII
    pm.meta_value REGEXP '[^0-9.-]' as has_non_numeric
FROM kre_posts p
JOIN kre_postmeta pm ON p.ID = pm.post_id
WHERE p.ID IN (59, 5660, 5671, 5677)
    AND pm.meta_key IN ('property_latitude', 'property_longitude')
ORDER BY p.ID;

localhost:3306/trentinoimreit_60xngbg2ytxs7o5ogyeuxkil0c8v41ccjr0m7qgrrsemh3i/kre_postmeta/		https://pollux.artera.farm:2083/cpsess6373459360/3rdparty/phpMyAdmin/index.php?route=/database/sql&db=trentinoimreit_60xngbg2ytxs7o5ogyeuxkil0c8v41ccjr0m7qgrrsemh3i

   Mostro le righe 0 -  7 (8 del totale, La query ha impiegato 0.0012 secondi.) [meta_key: PROPERTY_LATITUDE... - PROPERTY_LONGITUDE...]



   Mostro le righe 0 -  3 (4 del totale, La query ha impiegato 0.0006 secondi.)



   Mostro le righe 0 -  7 (8 del totale, La query ha impiegato 0.0006 secondi.) [ID: 59... - 5677...]


-- Query di debug per analizzare ESATTAMENTE come sono salvate le coordinate

-- 1. VALORE ESATTO + LUNGHEZZA + TIPO (mostra caratteri nascosti)
SELECT
    p.ID as property_id,
    p.post_title,
    pm.meta_key,
    pm.meta_value as value,
    LENGTH(pm.meta_value) as byte_length,
    CHAR_LENGTH(pm.meta_value) as char_length,
    HEX(pm.meta_value) as hex_value,  -- Mostra encoding esatto
    CAST(pm.meta_value AS DECIMAL(10,7)) as as_decimal,
    pm.meta_value + 0 as as_number  -- Conversione MySQL
FROM kre_posts p
LEFT JOIN kre_postmeta pm ON p.ID = pm.post_id
WHERE p.ID IN (59, 5660, 5671, 5677)
    AND pm.meta_key IN ('property_latitude', 'property_longitude')
ORDER BY p.ID, pm.meta_key;


-- 2. CONFRONTO ESATTO TRA MANUALE (59) E IMPORTATE (5660+)
SELECT
    'MANUAL (ID 59)' as type,
    pm.meta_key,
    pm.meta_value,
    LENGTH(pm.meta_value) as length,
    HEX(pm.meta_value) as hex
FROM kre_postmeta pm
WHERE pm.post_id = 59
    AND pm.meta_key IN ('property_latitude', 'property_longitude')

UNION ALL

SELECT
    'IMPORTED (ID 5660)' as type,
    pm.meta_key,
    pm.meta_value,
    LENGTH(pm.meta_value) as length,
    HEX(pm.meta_value) as hex
FROM kre_postmeta pm
WHERE pm.post_id = 5660
    AND pm.meta_key IN ('property_latitude', 'property_longitude');


-- 3. CERCA CARATTERI INVISIBILI O ENCODING PROBLEMS
SELECT
    p.ID,
    pm.meta_key,
    pm.meta_value,
    -- Controlla spazi bianchi
    TRIM(pm.meta_value) as trimmed,
    pm.meta_value = TRIM(pm.meta_value) as is_clean,
    -- Controlla separatore decimale
    INSTR(pm.meta_value, '.') as has_dot,
    INSTR(pm.meta_value, ',') as has_comma,
    -- Controlla caratteri non-ASCII
    pm.meta_value REGEXP '[^0-9.-]' as has_non_numeric
FROM kre_posts p
JOIN kre_postmeta pm ON p.ID = pm.post_id
WHERE p.ID IN (59, 5660, 5671, 5677)
    AND pm.meta_key IN ('property_latitude', 'property_longitude')
ORDER BY p.ID;


property_id	post_title	meta_key	value	byte_length	char_length	hex_value	as_decimal	as_number	type	meta_key	meta_value	length	hex	ID   	meta_key	meta_value	trimmed	is_clean	has_dot	has_comma	has_non_numeric	
59	Villa di Lusso con Piscina	property_latitude	46.2214703	10	10	34362E32323134373033	46.2214703	46.2214703	
59	Villa di Lusso con Piscina	property_longitude	10.8229502	10	10	31302E38323239353032	10.8229502	10.8229502	
5660	Appartamento Centro Trento - Classe A+	property_latitude	46.0664	7	7	34362E30363634	46.0664000	46.0664	
5660	Appartamento Centro Trento - Classe A+	property_longitude	11.1257	7	7	31312E31323537	11.1257000	11.1257	
5671	Villa di Prestigio con Piscina - Classe A4	property_latitude	46.085	6	6	34362E303835	46.0850000	46.085	
5671	Villa di Prestigio con Piscina - Classe A4	property_longitude	11.145	6	6	31312E313435	11.1450000	11.145	
5677	Attico Panoramico con Terrazzo 150mq	property_latitude	46.072	6	6	34362E303732	46.0720000	46.072	
5677	Attico Panoramico con Terrazzo 150mq	property_longitude	11.119	6	6	31312E313139	11.1190000	11.119	
MANUAL (ID 59)	property_latitude	46.2214703	10	34362E32323134373033	
MANUAL (ID 59)	property_longitude	10.8229502	10	31302E38323239353032	
IMPORTED (ID 5660)	property_latitude	46.0664	7	34362E30363634	
IMPORTED (ID 5660)	property_longitude	11.1257	7	31312E31323537	
59	property_latitude	46.2214703	46.2214703	1	3	0	0	
59	property_longitude	10.8229502	10.8229502	1	3	0	0	
5660	property_latitude	46.0664	46.0664	1	3	0	0	
5660	property_longitude	11.1257	11.1257	1	3	0	0	
5671	property_latitude	46.085	46.085	1	3	0	0	
5671	property_longitude	11.145	11.145	1	3	0	0	
5677	property_latitude	46.072	46.072	1	3	0	0	
5677	property_longitude	11.119	11.119	1	3	0	0	

localhost:3306/trentinoimreit_60xngbg2ytxs7o5ogyeuxkil0c8v41ccjr0m7qgrrsemh3i/kre_postmeta/		https://pollux.artera.farm:2083/cpsess6373459360/3rdparty/phpMyAdmin/index.php?route=/database/sql&db=trentinoimreit_60xngbg2ytxs7o5ogyeuxkil0c8v41ccjr0m7qgrrsemh3i

   Mostro le righe 0 -  7 (8 del totale, La query ha impiegato 0.0012 secondi.) [meta_key: PROPERTY_LATITUDE... - PROPERTY_LONGITUDE...]



   Mostro le righe 0 -  3 (4 del totale, La query ha impiegato 0.0006 secondi.)



   Mostro le righe 0 -  7 (8 del totale, La query ha impiegato 0.0006 secondi.) [ID: 59... - 5677...]


-- Query di debug per analizzare ESATTAMENTE come sono salvate le coordinate

-- 1. VALORE ESATTO + LUNGHEZZA + TIPO (mostra caratteri nascosti)
SELECT
    p.ID as property_id,
    p.post_title,
    pm.meta_key,
    pm.meta_value as value,
    LENGTH(pm.meta_value) as byte_length,
    CHAR_LENGTH(pm.meta_value) as char_length,
    HEX(pm.meta_value) as hex_value,  -- Mostra encoding esatto
    CAST(pm.meta_value AS DECIMAL(10,7)) as as_decimal,
    pm.meta_value + 0 as as_number  -- Conversione MySQL
FROM kre_posts p
LEFT JOIN kre_postmeta pm ON p.ID = pm.post_id
WHERE p.ID IN (59, 5660, 5671, 5677)
    AND pm.meta_key IN ('property_latitude', 'property_longitude')
ORDER BY p.ID, pm.meta_key;


-- 2. CONFRONTO ESATTO TRA MANUALE (59) E IMPORTATE (5660+)
SELECT
    'MANUAL (ID 59)' as type,
    pm.meta_key,
    pm.meta_value,
    LENGTH(pm.meta_value) as length,
    HEX(pm.meta_value) as hex
FROM kre_postmeta pm
WHERE pm.post_id = 59
    AND pm.meta_key IN ('property_latitude', 'property_longitude')

UNION ALL

SELECT
    'IMPORTED (ID 5660)' as type,
    pm.meta_key,
    pm.meta_value,
    LENGTH(pm.meta_value) as length,
    HEX(pm.meta_value) as hex
FROM kre_postmeta pm
WHERE pm.post_id = 5660
    AND pm.meta_key IN ('property_latitude', 'property_longitude');


-- 3. CERCA CARATTERI INVISIBILI O ENCODING PROBLEMS
SELECT
    p.ID,
    pm.meta_key,
    pm.meta_value,
    -- Controlla spazi bianchi
    TRIM(pm.meta_value) as trimmed,
    pm.meta_value = TRIM(pm.meta_value) as is_clean,
    -- Controlla separatore decimale
    INSTR(pm.meta_value, '.') as has_dot,
    INSTR(pm.meta_value, ',') as has_comma,
    -- Controlla caratteri non-ASCII
    pm.meta_value REGEXP '[^0-9.-]' as has_non_numeric
FROM kre_posts p
JOIN kre_postmeta pm ON p.ID = pm.post_id
WHERE p.ID IN (59, 5660, 5671, 5677)
    AND pm.meta_key IN ('property_latitude', 'property_longitude')
ORDER BY p.ID;


property_id	post_title	meta_key	value	byte_length	char_length	hex_value	as_decimal	as_number	type	meta_key	meta_value	length	hex	ID   	meta_key	meta_value	trimmed	is_clean	has_dot	has_comma	has_non_numeric	
59	Villa di Lusso con Piscina	property_latitude	46.2214703	10	10	34362E32323134373033	46.2214703	46.2214703	
59	Villa di Lusso con Piscina	property_longitude	10.8229502	10	10	31302E38323239353032	10.8229502	10.8229502	
5660	Appartamento Centro Trento - Classe A+	property_latitude	46.0664	7	7	34362E30363634	46.0664000	46.0664	
5660	Appartamento Centro Trento - Classe A+	property_longitude	11.1257	7	7	31312E31323537	11.1257000	11.1257	
5671	Villa di Prestigio con Piscina - Classe A4	property_latitude	46.085	6	6	34362E303835	46.0850000	46.085	
5671	Villa di Prestigio con Piscina - Classe A4	property_longitude	11.145	6	6	31312E313435	11.1450000	11.145	
5677	Attico Panoramico con Terrazzo 150mq	property_latitude	46.072	6	6	34362E303732	46.0720000	46.072	
5677	Attico Panoramico con Terrazzo 150mq	property_longitude	11.119	6	6	31312E313139	11.1190000	11.119	
MANUAL (ID 59)	property_latitude	46.2214703	10	34362E32323134373033	
MANUAL (ID 59)	property_longitude	10.8229502	10	31302E38323239353032	
IMPORTED (ID 5660)	property_latitude	46.0664	7	34362E30363634	
IMPORTED (ID 5660)	property_longitude	11.1257	7	31312E31323537	
59	property_latitude	46.2214703	46.2214703	1	3	0	0	
59	property_longitude	10.8229502	10.8229502	1	3	0	0	
5660	property_latitude	46.0664	46.0664	1	3	0	0	
5660	property_longitude	11.1257	11.1257	1	3	0	0	
5671	property_latitude	46.085	46.085	1	3	0	0	
5671	property_longitude	11.145	11.145	1	3	0	0	
5677	property_latitude	46.072	46.072	1	3	0	0	
5677	property_longitude	11.119	11.119	1	3	0	0	

localhost:3306/trentinoimreit_60xngbg2ytxs7o5ogyeuxkil0c8v41ccjr0m7qgrrsemh3i/kre_postmeta/		https://pollux.artera.farm:2083/cpsess6373459360/3rdparty/phpMyAdmin/index.php?route=/database/sql&db=trentinoimreit_60xngbg2ytxs7o5ogyeuxkil0c8v41ccjr0m7qgrrsemh3i

   Mostro le righe 0 -  7 (8 del totale, La query ha impiegato 0.0012 secondi.) [meta_key: PROPERTY_LATITUDE... - PROPERTY_LONGITUDE...]



   Mostro le righe 0 -  3 (4 del totale, La query ha impiegato 0.0006 secondi.)



   Mostro le righe 0 -  7 (8 del totale, La query ha impiegato 0.0006 secondi.) [ID: 59... - 5677...]


-- Query di debug per analizzare ESATTAMENTE come sono salvate le coordinate

-- 1. VALORE ESATTO + LUNGHEZZA + TIPO (mostra caratteri nascosti)
SELECT
    p.ID as property_id,
    p.post_title,
    pm.meta_key,
    pm.meta_value as value,
    LENGTH(pm.meta_value) as byte_length,
    CHAR_LENGTH(pm.meta_value) as char_length,
    HEX(pm.meta_value) as hex_value,  -- Mostra encoding esatto
    CAST(pm.meta_value AS DECIMAL(10,7)) as as_decimal,
    pm.meta_value + 0 as as_number  -- Conversione MySQL
FROM kre_posts p
LEFT JOIN kre_postmeta pm ON p.ID = pm.post_id
WHERE p.ID IN (59, 5660, 5671, 5677)
    AND pm.meta_key IN ('property_latitude', 'property_longitude')
ORDER BY p.ID, pm.meta_key;


-- 2. CONFRONTO ESATTO TRA MANUALE (59) E IMPORTATE (5660+)
SELECT
    'MANUAL (ID 59)' as type,
    pm.meta_key,
    pm.meta_value,
    LENGTH(pm.meta_value) as length,
    HEX(pm.meta_value) as hex
FROM kre_postmeta pm
WHERE pm.post_id = 59
    AND pm.meta_key IN ('property_latitude', 'property_longitude')

UNION ALL

SELECT
    'IMPORTED (ID 5660)' as type,
    pm.meta_key,
    pm.meta_value,
    LENGTH(pm.meta_value) as length,
    HEX(pm.meta_value) as hex
FROM kre_postmeta pm
WHERE pm.post_id = 5660
    AND pm.meta_key IN ('property_latitude', 'property_longitude');


-- 3. CERCA CARATTERI INVISIBILI O ENCODING PROBLEMS
SELECT
    p.ID,
    pm.meta_key,
    pm.meta_value,
    -- Controlla spazi bianchi
    TRIM(pm.meta_value) as trimmed,
    pm.meta_value = TRIM(pm.meta_value) as is_clean,
    -- Controlla separatore decimale
    INSTR(pm.meta_value, '.') as has_dot,
    INSTR(pm.meta_value, ',') as has_comma,
    -- Controlla caratteri non-ASCII
    pm.meta_value REGEXP '[^0-9.-]' as has_non_numeric
FROM kre_posts p
JOIN kre_postmeta pm ON p.ID = pm.post_id
WHERE p.ID IN (59, 5660, 5671, 5677)
    AND pm.meta_key IN ('property_latitude', 'property_longitude')
ORDER BY p.ID;


property_id	post_title	meta_key	value	byte_length	char_length	hex_value	as_decimal	as_number	type	meta_key	meta_value	length	hex	ID   	meta_key	meta_value	trimmed	is_clean	has_dot	has_comma	has_non_numeric	
59	Villa di Lusso con Piscina	property_latitude	46.2214703	10	10	34362E32323134373033	46.2214703	46.2214703	
59	Villa di Lusso con Piscina	property_longitude	10.8229502	10	10	31302E38323239353032	10.8229502	10.8229502	
5660	Appartamento Centro Trento - Classe A+	property_latitude	46.0664	7	7	34362E30363634	46.0664000	46.0664	
5660	Appartamento Centro Trento - Classe A+	property_longitude	11.1257	7	7	31312E31323537	11.1257000	11.1257	
5671	Villa di Prestigio con Piscina - Classe A4	property_latitude	46.085	6	6	34362E303835	46.0850000	46.085	
5671	Villa di Prestigio con Piscina - Classe A4	property_longitude	11.145	6	6	31312E313435	11.1450000	11.145	
5677	Attico Panoramico con Terrazzo 150mq	property_latitude	46.072	6	6	34362E303732	46.0720000	46.072	
5677	Attico Panoramico con Terrazzo 150mq	property_longitude	11.119	6	6	31312E313139	11.1190000	11.119	
MANUAL (ID 59)	property_latitude	46.2214703	10	34362E32323134373033	
MANUAL (ID 59)	property_longitude	10.8229502	10	31302E38323239353032	
IMPORTED (ID 5660)	property_latitude	46.0664	7	34362E30363634	
IMPORTED (ID 5660)	property_longitude	11.1257	7	31312E31323537	
59	property_latitude	46.2214703	46.2214703	1	3	0	0	
59	property_longitude	10.8229502	10.8229502	1	3	0	0	
5660	property_latitude	46.0664	46.0664	1	3	0	0	
5660	property_longitude	11.1257	11.1257	1	3	0	0	
5671	property_latitude	46.085	46.085	1	3	0	0	
5671	property_longitude	11.145	11.145	1	3	0	0	
5677	property_latitude	46.072	46.072	1	3	0	0	
5677	property_longitude	11.119	11.119	1	3	0	0	
