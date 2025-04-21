<?php
namespace CookieScanner;

class LanguageManager {
    private $current_language;
    private $default_language = 'de';
    private $available_languages = array(
        'de' => 'Deutsch',
        'en' => 'English',
        'fr' => 'Français',
        'es' => 'Español',
        'it' => 'Italiano',
        'nl' => 'Nederlands',
        'pl' => 'Polski',
        'ru' => 'Русский',
        'ja' => '日本語',
        'zh' => '中文'
    );

    private $translations = array(
        'cookie_notice' => array(
            'de' => array(
                'title' => 'Cookie-Einstellungen',
                'description' => 'Diese Website verwendet Cookies, um Ihre Erfahrung zu verbessern. Einige sind für den Betrieb der Website erforderlich, während andere uns helfen, diese Website und Ihre Erfahrung zu optimieren.',
                'accept_all' => 'Alle akzeptieren',
                'reject_all' => 'Alle ablehnen',
                'save_settings' => 'Einstellungen speichern',
                'necessary_cookies' => 'Notwendige Cookies',
                'functional_cookies' => 'Funktionale Cookies',
                'analytics_cookies' => 'Analyse-Cookies',
                'marketing_cookies' => 'Marketing-Cookies',
                'privacy_policy' => 'Datenschutzerklärung',
                'cookie_policy' => 'Cookie-Richtlinie',
                'more_information' => 'Weitere Informationen'
            ),
            'en' => array(
                'title' => 'Cookie Settings',
                'description' => 'This website uses cookies to enhance your experience. Some are necessary for the website to function, while others help us improve this website and your experience.',
                'accept_all' => 'Accept All',
                'reject_all' => 'Reject All',
                'save_settings' => 'Save Settings',
                'necessary_cookies' => 'Necessary Cookies',
                'functional_cookies' => 'Functional Cookies',
                'analytics_cookies' => 'Analytics Cookies',
                'marketing_cookies' => 'Marketing Cookies',
                'privacy_policy' => 'Privacy Policy',
                'cookie_policy' => 'Cookie Policy',
                'more_information' => 'More Information'
            ),
            // Weitere Sprachen hier...
        ),
        'cookie_categories' => array(
            'de' => array(
                'necessary' => array(
                    'title' => 'Notwendige Cookies',
                    'description' => 'Diese Cookies sind für die Grundfunktionen der Website erforderlich und können nicht deaktiviert werden.'
                ),
                'functional' => array(
                    'title' => 'Funktionale Cookies',
                    'description' => 'Diese Cookies ermöglichen erweiterte Funktionalitäten und Personalisierung.'
                ),
                'analytics' => array(
                    'title' => 'Analyse-Cookies',
                    'description' => 'Diese Cookies helfen uns, die Nutzung der Website zu verstehen und zu verbessern.'
                ),
                'marketing' => array(
                    'title' => 'Marketing-Cookies',
                    'description' => 'Diese Cookies werden verwendet, um Werbung relevanter für Sie zu machen.'
                )
            ),
            'en' => array(
                'necessary' => array(
                    'title' => 'Necessary Cookies',
                    'description' => 'These cookies are required for the basic functions of the website and cannot be disabled.'
                ),
                'functional' => array(
                    'title' => 'Functional Cookies',
                    'description' => 'These cookies enable enhanced functionality and personalization.'
                ),
                'analytics' => array(
                    'title' => 'Analytics Cookies',
                    'description' => 'These cookies help us understand and improve website usage.'
                ),
                'marketing' => array(
                    'title' => 'Marketing Cookies',
                    'description' => 'These cookies are used to make advertising more relevant to you.'
                )
            ),
            // Weitere Sprachen hier...
        )
    );

    public function __construct() {
        $this->detect_language();
    }

    /**
     * Erkennt die Sprache des Besuchers
     */
    private function detect_language() {
        // WordPress Multisite Spracheinstellung prüfen
        if (is_multisite() && defined('WPLANG')) {
            $site_lang = WPLANG;
            if (!empty($site_lang)) {
                $this->current_language = substr($site_lang, 0, 2);
                return;
            }
        }

        // WPML-Integration
        if (defined('ICL_LANGUAGE_CODE')) {
            $this->current_language = ICL_LANGUAGE_CODE;
            return;
        }

        // Polylang-Integration
        if (function_exists('pll_current_language')) {
            $current_lang = pll_current_language();
            if ($current_lang) {
                $this->current_language = $current_lang;
                return;
            }
        }

        // Browser-Spracheinstellung prüfen
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browser_lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
            if (isset($this->available_languages[$browser_lang])) {
                $this->current_language = $browser_lang;
                return;
            }
        }

        // Standardsprache verwenden
        $this->current_language = $this->default_language;
    }

    /**
     * Gibt eine Übersetzung zurück
     */
    public function get_translation($key, $category = 'cookie_notice') {
        if (isset($this->translations[$category][$this->current_language][$key])) {
            return $this->translations[$category][$this->current_language][$key];
        }
        
        // Fallback zur Standardsprache
        if (isset($this->translations[$category][$this->default_language][$key])) {
            return $this->translations[$category][$this->default_language][$key];
        }
        
        return $key;
    }

    /**
     * Gibt die aktuelle Sprache zurück
     */
    public function get_current_language() {
        return $this->current_language;
    }

    /**
     * Gibt alle verfügbaren Sprachen zurück
     */
    public function get_available_languages() {
        return $this->available_languages;
    }

    /**
     * Setzt die aktuelle Sprache
     */
    public function set_language($language) {
        if (isset($this->available_languages[$language])) {
            $this->current_language = $language;
            return true;
        }
        return false;
    }

    /**
     * Fügt eine neue Übersetzung hinzu
     */
    public function add_translation($category, $language, $key, $value) {
        if (!isset($this->translations[$category])) {
            $this->translations[$category] = array();
        }
        
        if (!isset($this->translations[$category][$language])) {
            $this->translations[$category][$language] = array();
        }
        
        $this->translations[$category][$language][$key] = $value;
    }

    /**
     * Gibt alle Übersetzungen für eine Kategorie zurück
     */
    public function get_translations($category) {
        return isset($this->translations[$category]) ? $this->translations[$category] : array();
    }
} 