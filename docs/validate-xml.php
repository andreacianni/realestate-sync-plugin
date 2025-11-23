#!/usr/bin/env php
<?php
/**
 * RealEstate Sync Plugin - XML Validator
 *
 * Validates XML test files for Property Mapper v3.3 OPZIONE A
 *
 * Usage:
 *   php validate-xml.php <xml-file-path>
 *   php validate-xml.php docs/test-property-sample.xml
 *   php validate-xml.php docs/test-property-complete.xml
 *
 * @package RealEstateSync
 * @version 1.0.0
 * @author Andrea Cianni - Novacom
 */

// Color output helpers
class Colors {
    public static $enabled = true;

    public static function red($text) {
        return self::$enabled ? "\033[31m{$text}\033[0m" : $text;
    }

    public static function green($text) {
        return self::$enabled ? "\033[32m{$text}\033[0m" : $text;
    }

    public static function yellow($text) {
        return self::$enabled ? "\033[33m{$text}\033[0m" : $text;
    }

    public static function blue($text) {
        return self::$enabled ? "\033[34m{$text}\033[0m" : $text;
    }

    public static function bold($text) {
        return self::$enabled ? "\033[1m{$text}\033[0m" : $text;
    }
}

// Validator class
class XMLValidator {

    private $xml_file;
    private $errors = [];
    private $warnings = [];
    private $stats = [
        'total_properties' => 0,
        'valid_properties' => 0,
        'invalid_properties' => 0,
        'categories_found' => [],
        'micro_categories_found' => [],
        'energy_classes_found' => [],
        'agencies_found' => [],
        'total_images' => 0,
        'total_planimetrie' => 0
    ];

    // Required fields
    private $required_fields = ['id', 'price'];

    // Valid categories (from Property Mapper)
    private $valid_categories = [1, 2, 8, 9, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23, 25, 28];

    // Valid energy classes
    private $valid_energy_classes = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13];

    public function __construct($xml_file) {
        $this->xml_file = $xml_file;
    }

    public function validate() {
        echo Colors::bold("🔍 RealEstate Sync XML Validator v1.0\n");
        echo str_repeat("=", 60) . "\n\n";

        // Check file exists
        if (!file_exists($this->xml_file)) {
            $this->error("File not found: {$this->xml_file}");
            return $this->print_summary();
        }

        echo Colors::blue("📄 File: ") . $this->xml_file . "\n";
        echo Colors::blue("📊 Size: ") . $this->format_bytes(filesize($this->xml_file)) . "\n\n";

        // Load XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($this->xml_file);

        if ($xml === false) {
            $this->error("Invalid XML syntax");
            foreach (libxml_get_errors() as $error) {
                $this->error("  Line {$error->line}: {$error->message}");
            }
            libxml_clear_errors();
            return $this->print_summary();
        }

        echo Colors::green("✓ XML syntax valid\n\n");

        // Validate structure
        if (!isset($xml->annuncio)) {
            $this->error("No <annuncio> elements found in XML");
            return $this->print_summary();
        }

        echo Colors::bold("🏠 Validating Properties...\n");
        echo str_repeat("-", 60) . "\n\n";

        // Validate each property
        $property_index = 1;
        foreach ($xml->annuncio as $annuncio) {
            $this->validate_property($annuncio, $property_index);
            $property_index++;
        }

        // Print summary
        return $this->print_summary();
    }

    private function validate_property($annuncio, $index) {
        $this->stats['total_properties']++;
        $property_errors = 0;

        $property_id = (string)($annuncio->info->id ?? "Property #{$index}");

        echo Colors::bold("Property #{$index}: ") . Colors::blue($property_id) . "\n";

        // Validate <info> section
        if (!isset($annuncio->info)) {
            $this->error("  Missing <info> section", $property_id);
            $property_errors++;
        } else {
            $property_errors += $this->validate_info_section($annuncio->info, $property_id);
        }

        // Validate required fields
        foreach ($this->required_fields as $field) {
            if (!isset($annuncio->info->$field) || empty((string)$annuncio->info->$field)) {
                $this->error("  Missing required field: <{$field}>", $property_id);
                $property_errors++;
            }
        }

        // Validate category
        if (isset($annuncio->info->categorie_id)) {
            $cat_id = (int)$annuncio->info->categorie_id;
            if (!in_array($cat_id, $this->valid_categories)) {
                $this->warning("  Invalid categorie_id: {$cat_id}", $property_id);
            } else {
                $this->stats['categories_found'][$cat_id] = ($this->stats['categories_found'][$cat_id] ?? 0) + 1;
            }
        }

        // Validate micro category
        if (isset($annuncio->info->categorie_micro_id)) {
            $micro_id = (int)$annuncio->info->categorie_micro_id;
            $this->stats['micro_categories_found'][$micro_id] = ($this->stats['micro_categories_found'][$micro_id] ?? 0) + 1;
        }

        // Validate comune_istat (for province filtering)
        if (isset($annuncio->info->comune_istat)) {
            $istat = (string)$annuncio->info->comune_istat;
            if (strlen($istat) !== 6) {
                $this->warning("  Invalid comune_istat format: {$istat} (should be 6 digits)", $property_id);
            }

            $province_code = substr($istat, 0, 3);
            if ($province_code !== '022' && $province_code !== '021') {
                $this->warning("  Property not in TN/BZ provinces (istat: {$istat})", $property_id);
            }
        } else {
            $this->warning("  Missing comune_istat (required for province filtering)", $property_id);
        }

        // Validate info_inserite
        if (isset($annuncio->info_inserite)) {
            $this->validate_info_inserite($annuncio->info_inserite, $property_id);
        }

        // Validate file_allegati
        if (isset($annuncio->file_allegati)) {
            $this->validate_file_allegati($annuncio->file_allegati, $property_id);
        } else {
            $this->warning("  No <file_allegati> section (no images)", $property_id);
        }

        // Validate agency
        if (isset($annuncio->agenzia)) {
            $this->validate_agency($annuncio->agenzia, $property_id);
        } else {
            $this->warning("  No <agenzia> section (property will not be linked to agency)", $property_id);
        }

        // Validate catasto
        if (!isset($annuncio->catasto)) {
            $this->warning("  No <catasto> section", $property_id);
        }

        // Property result
        if ($property_errors === 0) {
            $this->stats['valid_properties']++;
            echo "  " . Colors::green("✓ Valid") . "\n";
        } else {
            $this->stats['invalid_properties']++;
            echo "  " . Colors::red("✗ Invalid ({$property_errors} errors)") . "\n";
        }

        echo "\n";
    }

    private function validate_info_section($info, $property_id) {
        $errors = 0;

        // Validate price
        if (isset($info->price)) {
            $price = (float)$info->price;
            if ($price <= 0) {
                $this->error("  Invalid price: {$price} (must be > 0)", $property_id);
                $errors++;
            }
        }

        // Validate mq
        if (isset($info->mq)) {
            $mq = (int)$info->mq;
            if ($mq <= 0) {
                $this->warning("  Invalid mq: {$mq}", $property_id);
            }
        }

        // Validate coordinates
        if (isset($info->latitude)) {
            $lat = (float)$info->latitude;
            if ($lat < 45 || $lat > 47) {
                $this->warning("  Suspicious latitude: {$lat} (should be ~46 for Trentino)", $property_id);
            }
        }

        if (isset($info->longitude)) {
            $lon = (float)$info->longitude;
            if ($lon < 10 || $lon > 12) {
                $this->warning("  Suspicious longitude: {$lon} (should be ~11 for Trentino)", $property_id);
            }
        }

        // Validate age (year)
        if (isset($info->age)) {
            $year = (int)$info->age;
            if ($year < 1000 || $year > 2030) {
                $this->warning("  Suspicious age/year: {$year}", $property_id);
            }
        }

        // Validate IPE
        if (isset($info->ipe)) {
            $ipe = (float)$info->ipe;
            if ($ipe < 0 || $ipe > 500) {
                $this->warning("  Suspicious IPE value: {$ipe}", $property_id);
            }
        }

        return $errors;
    }

    private function validate_info_inserite($info_inserite, $property_id) {
        if (!isset($info_inserite->info)) {
            return;
        }

        $found_features = [];

        foreach ($info_inserite->info as $info) {
            $id = (int)$info['id'];
            $value = (int)($info->valore_assegnato ?? 0);

            $found_features[] = $id;

            // Check energy class (Info[55])
            if ($id === 55 && $value > 0) {
                if (!in_array($value, $this->valid_energy_classes)) {
                    $this->warning("  Invalid energy class value Info[55]={$value}", $property_id);
                } else {
                    $this->stats['energy_classes_found'][$value] = ($this->stats['energy_classes_found'][$value] ?? 0) + 1;
                }
            }
        }

        // Check if has basic features
        $has_sale = in_array(9, $found_features);
        $has_rent = in_array(10, $found_features);

        if (!$has_sale && !$has_rent) {
            $this->warning("  No sale/rent indicator (Info[9] or Info[10])", $property_id);
        }
    }

    private function validate_file_allegati($file_allegati, $property_id) {
        if (!isset($file_allegati->allegato)) {
            return;
        }

        $images = 0;
        $planimetrie = 0;

        foreach ($file_allegati->allegato as $allegato) {
            $type = (string)($allegato['type'] ?? 'image');

            if (!isset($allegato->file_path) || empty((string)$allegato->file_path)) {
                $this->warning("  Empty <file_path> in allegato", $property_id);
                continue;
            }

            $url = (string)$allegato->file_path;

            // Validate URL format
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $this->warning("  Invalid URL format: {$url}", $property_id);
            }

            if ($type === 'planimetria') {
                $planimetrie++;
            } else {
                $images++;
            }
        }

        $this->stats['total_images'] += $images;
        $this->stats['total_planimetrie'] += $planimetrie;

        if ($images === 0) {
            $this->warning("  No images found (only planimetrie)", $property_id);
        }
    }

    private function validate_agency($agenzia, $property_id) {
        // Check required agency fields
        $required_agency_fields = ['id', 'ragione_sociale'];

        foreach ($required_agency_fields as $field) {
            if (!isset($agenzia->$field) || empty((string)$agenzia->$field)) {
                $this->warning("  Missing agency field: <{$field}>", $property_id);
            }
        }

        // Track unique agencies
        if (isset($agenzia->id)) {
            $agency_id = (string)$agenzia->id;
            $this->stats['agencies_found'][$agency_id] = ($this->stats['agencies_found'][$agency_id] ?? 0) + 1;
        }

        // Validate email
        if (isset($agenzia->email)) {
            $email = (string)$agenzia->email;
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->warning("  Invalid agency email: {$email}", $property_id);
            }
        }

        // Validate website
        if (isset($agenzia->sito_web)) {
            $website = (string)$agenzia->sito_web;
            if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
                $this->warning("  Invalid agency website: {$website}", $property_id);
            }
        }
    }

    private function error($message, $context = '') {
        $this->errors[] = ['message' => $message, 'context' => $context];
        echo Colors::red("  ✗ ERROR: {$message}") . "\n";
    }

    private function warning($message, $context = '') {
        $this->warnings[] = ['message' => $message, 'context' => $context];
        echo Colors::yellow("  ⚠ WARNING: {$message}") . "\n";
    }

    private function print_summary() {
        echo "\n";
        echo str_repeat("=", 60) . "\n";
        echo Colors::bold("📊 VALIDATION SUMMARY\n");
        echo str_repeat("=", 60) . "\n\n";

        // Overall result
        if (count($this->errors) === 0) {
            echo Colors::green(Colors::bold("✓ VALIDATION PASSED\n\n"));
        } else {
            echo Colors::red(Colors::bold("✗ VALIDATION FAILED\n\n"));
        }

        // Properties stats
        echo Colors::bold("Properties:\n");
        echo "  Total:   " . $this->stats['total_properties'] . "\n";
        echo "  Valid:   " . Colors::green($this->stats['valid_properties']) . "\n";
        echo "  Invalid: " . Colors::red($this->stats['invalid_properties']) . "\n";
        echo "\n";

        // Categories found
        if (!empty($this->stats['categories_found'])) {
            echo Colors::bold("Categories found:\n");
            ksort($this->stats['categories_found']);
            foreach ($this->stats['categories_found'] as $cat_id => $count) {
                $cat_name = $this->get_category_name($cat_id);
                echo "  [{$cat_id}] {$cat_name}: {$count} properties\n";
            }
            echo "\n";
        }

        // Micro-categories found
        if (!empty($this->stats['micro_categories_found'])) {
            echo Colors::bold("Micro-categories found:\n");
            ksort($this->stats['micro_categories_found']);
            foreach ($this->stats['micro_categories_found'] as $micro_id => $count) {
                echo "  Micro [{$micro_id}]: {$count} properties\n";
            }
            echo "\n";
        }

        // Energy classes found
        if (!empty($this->stats['energy_classes_found'])) {
            echo Colors::bold("Energy classes found:\n");
            ksort($this->stats['energy_classes_found']);
            foreach ($this->stats['energy_classes_found'] as $class_id => $count) {
                $class_name = $this->get_energy_class_name($class_id);
                echo "  [{$class_id}] {$class_name}: {$count} properties\n";
            }
            echo "\n";
        }

        // Agencies found
        if (!empty($this->stats['agencies_found'])) {
            echo Colors::bold("Agencies found:\n");
            ksort($this->stats['agencies_found']);
            foreach ($this->stats['agencies_found'] as $agency_id => $count) {
                echo "  {$agency_id}: {$count} properties\n";
            }
            echo "\n";
        }

        // Media stats
        echo Colors::bold("Media:\n");
        echo "  Images:       " . $this->stats['total_images'] . "\n";
        echo "  Planimetrie:  " . $this->stats['total_planimetrie'] . "\n";
        echo "\n";

        // Errors and warnings
        if (count($this->errors) > 0) {
            echo Colors::red(Colors::bold("Errors: " . count($this->errors) . "\n"));
        }

        if (count($this->warnings) > 0) {
            echo Colors::yellow(Colors::bold("Warnings: " . count($this->warnings) . "\n"));
        }

        echo "\n";

        // Exit code
        return count($this->errors) === 0 ? 0 : 1;
    }

    private function get_category_name($id) {
        $categories = [
            1 => 'Casa Singola',
            2 => 'Bifamiliare',
            8 => 'Garage',
            9 => 'Box',
            11 => 'Appartamento',
            12 => 'Attico',
            13 => 'Loft',
            14 => 'Negozio',
            15 => 'Capannone',
            16 => 'Laboratorio',
            17 => 'Ufficio',
            18 => 'Villa',
            19 => 'Terreno',
            20 => 'Rustico',
            21 => 'Castello',
            22 => 'Palazzo',
            23 => 'Loft/Mansarda',
            25 => 'Casa Vacanza',
            28 => 'Camera/Posto Letto'
        ];

        return $categories[$id] ?? 'Unknown';
    }

    private function get_energy_class_name($id) {
        $classes = [
            1 => 'A+',
            2 => 'A',
            3 => 'B',
            4 => 'C',
            5 => 'D',
            6 => 'E',
            7 => 'F',
            8 => 'G',
            9 => 'Non soggetto',
            10 => 'A4',
            11 => 'A3',
            12 => 'A2',
            13 => 'A1'
        ];

        return $classes[$id] ?? 'Unknown';
    }

    private function format_bytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}

// Main execution
if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line\n");
}

if ($argc < 2) {
    echo "Usage: php validate-xml.php <xml-file-path>\n";
    echo "\nExamples:\n";
    echo "  php validate-xml.php docs/test-property-sample.xml\n";
    echo "  php validate-xml.php docs/test-property-complete.xml\n";
    exit(1);
}

$xml_file = $argv[1];
$validator = new XMLValidator($xml_file);
$exit_code = $validator->validate();

exit($exit_code);
