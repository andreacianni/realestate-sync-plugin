<?php
/**
 * Generate ISTAT Lookup Table
 *
 * Extracts municipalities from full ISTAT database that match codes in feed
 * Creates optimized lookup array for Trentino-Alto Adige comuni
 */

// Load full ISTAT database
$istat_full = json_decode(file_get_contents(__DIR__ . '/../data/comuni-istat-full.json'), true);

// Load codes from feed
$feed_codes_file = 'C:\\Users\\Andrea\\OneDrive\\Lavori\\novacom\\Trentino-immobiliare\\lavoro\\XML massivo\\comune_istat-021-022.txt';
$feed_codes_raw = file($feed_codes_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

// Remove header and clean codes
$feed_codes = array_filter(array_map('trim', $feed_codes_raw), function($code) {
    return $code !== 'comune_istat' && !empty($code);
});

echo "📊 Loaded " . count($istat_full) . " total comuni from ISTAT database\n";
echo "📋 Found " . count($feed_codes) . " unique codes in feed\n\n";

// Build lookup table
$lookup = [];
$found = 0;
$missing = [];

foreach ($feed_codes as $code) {
    $found_comune = null;

    foreach ($istat_full as $comune) {
        if ($comune['codice'] === $code) {
            $found_comune = $comune;
            break;
        }
    }

    if ($found_comune) {
        $lookup[$code] = [
            'nome' => $found_comune['nome'],
            'provincia' => $found_comune['provincia']['nome'],
            'regione' => $found_comune['regione']['nome'],
            'cap' => $found_comune['cap'][0] ?? '', // First CAP
            'sigla_provincia' => $found_comune['provincia']['codice'] ?? ''
        ];
        $found++;
    } else {
        $missing[] = $code;
    }
}

echo "✅ Matched: $found comuni\n";
echo "❌ Missing: " . count($missing) . " comuni\n";

if (!empty($missing)) {
    echo "\n⚠️  Missing ISTAT codes:\n";
    foreach ($missing as $code) {
        echo "  - $code\n";
    }
}

// Sort by code
ksort($lookup);

// Generate PHP array format
$php_code = "<?php\n";
$php_code .= "/**\n";
$php_code .= " * ISTAT Lookup Table - Trentino-Alto Adige\n";
$php_code .= " * \n";
$php_code .= " * Auto-generated from GitHub: matteocontrini/comuni-json\n";
$php_code .= " * Date: " . date('Y-m-d H:i:s') . "\n";
$php_code .= " * Total comuni: " . count($lookup) . "\n";
$php_code .= " * \n";
$php_code .= " * Structure:\n";
$php_code .= " * 'ISTAT_CODE' => [\n";
$php_code .= " *     'nome' => 'Municipality name',\n";
$php_code .= " *     'provincia' => 'Province name',\n";
$php_code .= " *     'regione' => 'Region name',\n";
$php_code .= " *     'cap' => 'Postal code',\n";
$php_code .= " *     'sigla_provincia' => 'Province code'\n";
$php_code .= " * ]\n";
$php_code .= " */\n\n";
$php_code .= "return [\n";

foreach ($lookup as $code => $data) {
    $php_code .= "    '$code' => [\n";
    $php_code .= "        'nome' => " . var_export($data['nome'], true) . ",\n";
    $php_code .= "        'provincia' => " . var_export($data['provincia'], true) . ",\n";
    $php_code .= "        'regione' => " . var_export($data['regione'], true) . ",\n";
    $php_code .= "        'cap' => " . var_export($data['cap'], true) . ",\n";
    $php_code .= "        'sigla_provincia' => " . var_export($data['sigla_provincia'], true) . "\n";
    $php_code .= "    ],\n";
}

$php_code .= "];\n";

// Save PHP lookup file
$output_file = __DIR__ . '/../data/istat-lookup-tn-bz.php';
file_put_contents($output_file, $php_code);

echo "\n✅ Lookup table saved to: data/istat-lookup-tn-bz.php\n";
echo "📊 File size: " . number_format(filesize($output_file) / 1024, 2) . " KB\n";

// Show sample
echo "\n📋 Sample entries:\n";
$sample_count = 0;
foreach ($lookup as $code => $data) {
    if ($sample_count++ >= 5) break;
    echo "  $code => {$data['nome']}, {$data['provincia']}, CAP: {$data['cap']}\n";
}
