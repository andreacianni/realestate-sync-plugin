<?php
/**
 * Generate COMPLETE ISTAT Lookup Table
 *
 * Extracts ALL municipalities with codes 021 (Bolzano) and 022 (Trento)
 * from full ISTAT database - 282 total comuni
 */

// Load full ISTAT database
$istat_full = json_decode(file_get_contents(__DIR__ . '/../data/comuni-istat-full.json'), true);

echo "📊 Loaded " . count($istat_full) . " total comuni from ISTAT database\n";

// Filter for Trentino-Alto Adige (codes 021 and 022)
$lookup = [];
$count_021 = 0;
$count_022 = 0;

foreach ($istat_full as $comune) {
    $code = $comune['codice'];

    // Check if code starts with 021 or 022
    if (substr($code, 0, 3) === '021' || substr($code, 0, 3) === '022') {
        $lookup[$code] = [
            'nome' => $comune['nome'],
            'provincia' => $comune['provincia']['nome'],
            'regione' => $comune['regione']['nome'],
            'cap' => $comune['cap'][0] ?? '', // First CAP
            'sigla_provincia' => $comune['provincia']['codice'] ?? ''
        ];

        if (substr($code, 0, 3) === '021') {
            $count_021++;
        } else {
            $count_022++;
        }
    }
}

echo "✅ Extracted Trentino-Alto Adige comuni:\n";
echo "   - Bolzano (021): $count_021 comuni\n";
echo "   - Trento (022): $count_022 comuni\n";
echo "   - TOTAL: " . count($lookup) . " comuni\n\n";

// Sort by code
ksort($lookup);

// Generate PHP array format
$php_code = "<?php\n";
$php_code .= "/**\n";
$php_code .= " * ISTAT Lookup Table - Trentino-Alto Adige (COMPLETE)\n";
$php_code .= " * \n";
$php_code .= " * Auto-generated from GitHub: matteocontrini/comuni-json\n";
$php_code .= " * Date: " . date('Y-m-d H:i:s') . "\n";
$php_code .= " * \n";
$php_code .= " * Coverage: ALL municipalities in Trentino-Alto Adige\n";
$php_code .= " * - Provincia di Bolzano/Bozen (021): $count_021 comuni\n";
$php_code .= " * - Provincia di Trento (022): $count_022 comuni\n";
$php_code .= " * - Total: " . count($lookup) . " comuni\n";
$php_code .= " * \n";
$php_code .= " * Structure:\n";
$php_code .= " * 'ISTAT_CODE' => [\n";
$php_code .= " *     'nome' => 'Municipality name',\n";
$php_code .= " *     'provincia' => 'Province name',\n";
$php_code .= " *     'regione' => 'Region name',\n";
$php_code .= " *     'cap' => 'Postal code',\n";
$php_code .= " *     'sigla_provincia' => 'Province code'\n";
$php_code .= " * ]\n";
$php_code .= " * \n";
$php_code .= " * Sources:\n";
$php_code .= " * - ISTAT official codes\n";
$php_code .= " * - CAP from official postal codes\n";
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

echo "✅ COMPLETE lookup table saved to: data/istat-lookup-tn-bz.php\n";
echo "📊 File size: " . number_format(filesize($output_file) / 1024, 2) . " KB\n";

// Show sample entries from both provinces
echo "\n📋 Sample entries:\n";
echo "\n🔵 Bolzano/Bozen (021):\n";
$sample_021 = 0;
foreach ($lookup as $code => $data) {
    if (substr($code, 0, 3) === '021' && $sample_021++ < 5) {
        echo "  $code => {$data['nome']}, CAP: {$data['cap']}\n";
    }
}

echo "\n🔴 Trento (022):\n";
$sample_022 = 0;
foreach ($lookup as $code => $data) {
    if (substr($code, 0, 3) === '022' && $sample_022++ < 5) {
        echo "  $code => {$data['nome']}, CAP: {$data['cap']}\n";
    }
}

echo "\n✅ Ready for production!\n";
