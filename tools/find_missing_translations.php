<?php
/**
 * Untranslated Keys Finder
 *
 * Scans all PHP files in the project for lang('key') calls and compares
 * them against the language files to find missing translations.
 *
 * Usage:
 *   php tools/find_missing_translations.php
 *
 * Output:
 *   - Keys used in code but missing from language files
 *   - Keys present in English but missing from French/Kinyarwanda
 *
 * PHP 8.3
 */

// ─── Configuration ──────────────────────────────────────────────────
$projectRoot = dirname(__DIR__);
$languageDir = $projectRoot . '/languages';
$scanDirs = [
    $projectRoot . '/admin',
    $projectRoot . '/includes',
    $projectRoot . '/helpers',
    $projectRoot . '/pages',
];

$extensions = ['php'];

// ─── Scan for lang() calls ──────────────────────────────────────────
$usedKeys = [];
$pattern = '/\blang\s*\(\s*[\'"]([^\'"]+)[\'"]/';

foreach ($scanDirs as $dir) {
    if (!is_dir($dir)) continue;
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array($file->getExtension(), $extensions)) {
            $content = file_get_contents($file->getPathname());
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $key) {
                    $usedKeys[$key] = ($usedKeys[$key] ?? 0) + 1;
                }
            }
        }
    }
}

ksort($usedKeys);

// ─── Load language files ────────────────────────────────────────────
$languages = [];
$langFiles = glob($languageDir . '/*.php');
foreach ($langFiles as $file) {
    $code = basename($file, '.php');
    $languages[$code] = require $file;
}

$totalKeysInCode = count($usedKeys);
$knownLanguages = array_keys($languages);

echo "==========================================\n";
echo " Untranslated Keys Report\n";
echo "==========================================\n\n";

echo "Keys found in code:    {$totalKeysInCode}\n";
echo "Language files found:  " . implode(', ', $knownLanguages) . "\n\n";

// ─── Check each language ────────────────────────────────────────────
$hasIssues = false;

foreach ($knownLanguages as $lang) {
    $missing = [];
    foreach ($usedKeys as $key => $count) {
        if (!isset($languages[$lang][$key])) {
            $missing[] = $key;
        }
    }

    if (!empty($missing)) {
        $hasIssues = true;
        echo "--- {$lang} ---\n";
        echo count($missing) . " keys missing from {$lang}.php:\n";
        foreach ($missing as $key) {
            $enValue = $languages['en'][$key] ?? '(not in English either)';
            echo "  '{$key}' => '{$enValue}',\n";
        }
        echo "\n";
    } else {
        echo "{$lang}.php: All keys found. ✓\n\n";
    }
}

// ─── Check for unused keys in language files ────────────────────────
echo "==========================================\n";
echo " Unused Keys in Language Files\n";
echo "==========================================\n\n";

foreach ($languages as $code => $strings) {
    $unused = [];
    foreach ($strings as $key => $value) {
        if (!isset($usedKeys[$key])) {
            $unused[] = $key;
        }
    }
    if (!empty($unused)) {
        $hasIssues = true;
        echo "{$code}.php: " . count($unused) . " keys not found in code:\n";
        foreach ($unused as $key) {
            echo "  '{$key}' => '{$languages[$code][$key]}',\n";
        }
        echo "\n";
    }
}

// ─── Summary ────────────────────────────────────────────────────────
echo "==========================================\n";
if (!$hasIssues) {
    echo " All keys are translated across all languages! ✓\n";
} else {
    echo " See above for missing translations.\n";
}
echo "==========================================\n";
