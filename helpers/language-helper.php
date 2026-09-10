<?php
/**
 * Language Helper
 *
 * Provides internationalization (i18n) support for the application.
 * Loads language files from the /languages/ directory and caches them
 * in a static variable for the duration of the request.
 *
 * How it works:
 *   1. The current language code is determined by:
 *      - User preference (logged-in users: users.language column)
 *      - Session preference (guests)
 *      - System default setting (setting('default_language'))
 *      - Hardcoded fallback: 'en'
 *   2. The corresponding PHP file in /languages/ is loaded.
 *   3. The lang() function looks up a key in the loaded array.
 *      If the key is missing, it falls back to English, then to the key itself.
 *
 * Security:
 *   - Language codes are validated against a whitelist (active languages in DB).
 *   - The file path is constructed from the validated code, preventing LFI.
 *
 * Performance:
 *   - Language file is loaded once per request via a static cache.
 *   - Only one language file is loaded per request.
 *
 * Dependencies:
 *   - includes/config.php (for BASE_PATH, setting())
 *   - includes/db.php (for getDbConnection() — optional, for DB lookup)
 *
 * PHP 8.3
 */

/**
 * Get the list of active languages from the database.
 *
 * Returns an associative array: [code => name].
 * Cached in a static variable to avoid repeated queries.
 *
 * @return array  e.g. ['en' => 'English', 'fr' => 'Français']
 */
function getActiveLanguages(): array
{
    static $languages = null;

    if ($languages !== null) {
        return $languages;
    }

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SELECT code, name, flag FROM languages WHERE status = 'active' ORDER BY is_default DESC, name ASC");
        $rows = $stmt->fetchAll();
        $languages = [];
        foreach ($rows as $row) {
            $languages[$row['code']] = [
                'name' => $row['name'],
                'flag' => $row['flag'] ?? '',
            ];
        }
    } catch (\Throwable $e) {
        error_log('Failed to load languages: ' . $e->getMessage());
        $languages = ['en' => ['name' => 'English', 'flag' => '🇬🇧']];
    }

    return $languages;
}

/**
 * Determine the current language code.
 *
 * Priority:
 *   1. $_SESSION['language'] (set by language switcher)
 *   2. Logged-in user's preference (users.language column)
 *   3. System default setting (default_language)
 *   4. Hardcoded fallback: 'en'
 *
 * The result is stored in $_SESSION['language'] for quick access.
 *
 * @return string  Two-letter language code (e.g. 'en', 'fr', 'rw')
 */
function getCurrentLanguage(): string
{
    // Return cached value if already determined this request
    if (isset($_SESSION['_language_checked']) && isset($_SESSION['language'])) {
        return $_SESSION['language'];
    }

    $code = 'en'; // Hard fallback

    // 1. Check session (set by language switcher)
    if (isset($_SESSION['language']) && isValidLanguageCode($_SESSION['language'])) {
        $code = $_SESSION['language'];
    } else {
        // 2. Check logged-in user's preference
        if (isset($_SESSION['user_id'])) {
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare('SELECT language FROM users WHERE id = :id AND language IS NOT NULL AND language != \'\'');
                $stmt->execute([':id' => (int) $_SESSION['user_id']]);
                $userLang = $stmt->fetchColumn();
                if ($userLang !== false && isValidLanguageCode($userLang)) {
                    $code = $userLang;
                }
            } catch (\Throwable $e) {
                error_log('Failed to load user language: ' . $e->getMessage());
            }
        }

        // 3. Check system default
        if (!isset($code) || $code === 'en') {
            try {
                if (function_exists('setting')) {
                    $defaultLang = setting('default_language', 'en');
                    if (isValidLanguageCode($defaultLang)) {
                        $code = $defaultLang;
                    }
                }
            } catch (\Throwable $e) {
                // Silently fall through to 'en'
            }
        }
    }

    // Validate final code
    if (!isValidLanguageCode($code)) {
        $code = 'en';
    }

    $_SESSION['language'] = $code;
    $_SESSION['_language_checked'] = true;

    return $code;
}

/**
 * Load the language file for the given code.
 *
 * The file is loaded once per request and cached in a static variable.
 *
 * @param string $code  Language code (e.g. 'en')
 * @return array  Associative array of language strings
 */
function loadLanguage(string $code = ''): array
{
    static $loaded = [];

    if ($code === '') {
        $code = getCurrentLanguage();
    }

    // Return cached if already loaded
    if (isset($loaded[$code])) {
        return $loaded[$code];
    }

    // Fallback chain
    $files = [
        BASE_PATH . 'languages' . DIRECTORY_SEPARATOR . $code . '.php',
        BASE_PATH . 'languages' . DIRECTORY_SEPARATOR . 'en.php',
    ];

    $strings = [];
    foreach ($files as $file) {
        if (file_exists($file)) {
            $strings = require $file;
            if (is_array($strings)) {
                break;
            }
        }
    }

    // Ensure we always have an array
    if (!is_array($strings)) {
        $strings = [];
    }

    $loaded[$code] = $strings;
    return $strings;
}

/**
 * Get a translated string by key.
 *
 * Usage:
 *   echo lang('home');            // "Home" / "Accueil" / "Ahabanza"
 *   echo lang('welcome', ['name' => 'John']);  // "Welcome, John!"
 *
 * If the key is not found in the current language:
 *   1. Falls back to English
 *   2. Falls back to the key itself as a last resort
 *
 * @param string $key      The translation key
 * @param array  $replace  Optional. Key-value pairs for placeholder replacement.
 *                         Placeholders in the string are :placeholder format.
 * @return string  The translated string (or English fallback / key)
 */
function lang(string $key, array $replace = []): string
{
    static $fallbackEn = null;
    static $currentLang = '';
    static $strings = [];

    $langCode = getCurrentLanguage();

    // Reload if language changed mid-request (unlikely but possible)
    if ($langCode !== $currentLang) {
        $strings = loadLanguage($langCode);
        $currentLang = $langCode;
    }

    // Get the string — try current language, then English fallback, then key
    $string = $strings[$key] ?? null;

    if ($string === null) {
        // Load English fallback
        if ($fallbackEn === null) {
            $fallbackEn = loadLanguage('en');
        }
        $string = $fallbackEn[$key] ?? $key;
    }

    // Replace placeholders (:key → value)
    if (!empty($replace)) {
        foreach ($replace as $placeholder => $value) {
            $string = str_replace(':' . $placeholder, (string) $value, $string);
        }
    }

    return $string;
}

/**
 * Set the current language for the session.
 *
 * For logged-in users, the preference is also saved to the database
 * (users.language column).
 *
 * @param string $code  Language code (e.g. 'en', 'fr', 'rw')
 * @return bool  TRUE on success
 */
function setLanguage(string $code): bool
{
    if (!isValidLanguageCode($code)) {
        return false;
    }

    $_SESSION['language'] = $code;
    $_SESSION['_language_checked'] = true;

    // Save preference for logged-in users
    if (isset($_SESSION['user_id'])) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare('UPDATE users SET language = :lang WHERE id = :id');
            $stmt->execute([':lang' => $code, ':id' => (int) $_SESSION['user_id']]);
        } catch (\Throwable $e) {
            error_log('Failed to save language preference: ' . $e->getMessage());
            // Session preference still works even if DB save fails
        }
    }

    return true;
}

/**
 * Validate a language code against the database.
 *
 * Only codes matching an active language in the database are allowed.
 * This prevents loading arbitrary files via LFI.
 *
 * @param string $code  Language code to validate
 * @return bool
 */
function isValidLanguageCode(string $code): bool
{
    if ($code === '' || !preg_match('/^[a-z]{2,5}$/', $code)) {
        return false;
    }

    // Check against active languages in DB
    $active = getActiveLanguages();
    return isset($active[$code]);
}
