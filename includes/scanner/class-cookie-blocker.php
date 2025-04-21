<?php
namespace CookieScanner\Scanner;

use CookieScanner\Database\Database;

class CookieBlocker {
    private $db;
    private $blocked_scripts = array();
    private $cookie_categories = array();
    private static $instance = null;
    private static $categories_loaded = false;

    public function __construct() {
        $this->db = new Database();
        $this->load_cookie_categories();
        $this->init_hooks();
    }

    /**
     * Lädt die Cookie-Kategorien aus der Datenbank mit Caching
     */
    private function load_cookie_categories() {
        // Lade Kategorien nur einmal pro Request
        if (!self::$categories_loaded) {
            $this->cookie_categories = \CookieScanner\Modules\CookieManager\CookieManager::get_categories();
            self::$categories_loaded = true;
        }
    }

    /**
     * Initialisiert die WordPress-Hooks
     */
    private function init_hooks() {
        // Script-Blocking nur im Frontend aktivieren
        if (!is_admin() && !$this->is_divi_builder()) {
            // Output-Buffer für Script-Blocking
            add_action('template_redirect', function() {
                ob_start();
            }, 0);
            
            add_action('shutdown', function() {
                $html = ob_get_clean();
                echo $this->process_output_buffer($html);
            }, 999);
            
            // Cookie-Blocking
            add_action('init', array($this, 'block_cookies'), 1);
            
            // Header-Modifikation
            add_action('send_headers', array($this, 'modify_headers'), 1);
        }
    }

    /**
     * Prüft, ob wir uns im Divi Builder oder Customizer befinden
     */
    private function is_divi_builder() {
        return (
            (isset($_GET['et_fb']) && $_GET['et_fb'] === '1') || // Divi Builder
            (isset($_GET['et_pb_preview']) && $_GET['et_pb_preview'] === 'true') || // Divi Builder Preview
            (isset($_GET['customize_changeset_uuid']) || isset($_GET['customize_theme'])) || // WordPress Customizer
            (function_exists('et_core_is_fb_enabled') && et_core_is_fb_enabled()) || // Divi Builder aktiv
            (isset($_GET['page_id']) && get_post_type($_GET['page_id']) === 'et_pb') // Divi Modul
        );
    }

    /**
     * Verarbeitet den Output-Buffer
     */
    private function process_output_buffer($buffer) {
        if (empty($buffer)) {
            return $buffer;
        }

        // Debug-Logging
        error_log('Buffer length: ' . strlen($buffer));
        error_log('blocked_scripts: '.print_r($this->blocked_scripts, true));
        error_log('accepted_categories: '.print_r($this->get_accepted_categories(), true));

        // Wenn keine Scripts blockiert werden sollen, beende hier
        if (empty($this->blocked_scripts) || (is_array($this->blocked_scripts) && count($this->blocked_scripts) == 0)) {
            return $buffer;
        }

        // Hole die akzeptierten Kategorien aus dem Cookie
        $accepted_categories = $this->get_accepted_categories();
        
        // Wenn alle Kategorien akzeptiert wurden, blockiere keine Scripts
        if (in_array('all', $accepted_categories)) {
            return $buffer;
        }

        // Verarbeite Script-Tags
        $this->process_script_tags($buffer, $accepted_categories);
        
        // Verarbeite Inline-Scripts
        $this->process_inline_scripts($buffer, $accepted_categories);
        
        // Verarbeite IFrames
        $this->process_iframes($buffer, $accepted_categories);

        return $buffer;
    }

    /**
     * Verarbeitet Script-Tags
     */
    private function process_script_tags(&$html, $accepted_categories) {
        $html = preg_replace_callback('/<script[^>]*src=[\'"]([^\'"]*)[\'"][^>]*>/i', function($matches) use ($accepted_categories) {
            $script_url = $matches[1];
            $full_tag = $matches[0];
error_log(print_r([__LINE__,$html,$script_url],true));
            // Überspringe Divi-spezifische Scripts
            if (strpos($script_url, 'et-') === 0 || strpos($script_url, 'divi') !== false) {
                return $full_tag;
            }

            // Hole die Kategorie des Scripts
            $script_category = $this->get_script_category($script_url);
            
            // Wenn die Kategorie nicht akzeptiert wurde, entferne das Script
            if ($script_category && !in_array($script_category, $accepted_categories)) {
                return '';
            }

            return $full_tag;
        }, $html);
    }

    /**
     * Verarbeitet Inline-Scripts
     */
    private function process_inline_scripts(&$html, $accepted_categories) {
        $html = preg_replace_callback('/<script[^>]*>(.*?)<\/script>/is', function($matches) use ($accepted_categories) {
            $script_content = $matches[1];
            $full_tag = $matches[0];

            // Überspringe Divi-spezifische Scripts
            if (strpos($script_content, 'et-') !== false || strpos($script_content, 'divi') !== false) {
                return $full_tag;
            }

            // Prüfe auf bekannte Tracking-Scripts
            foreach ($this->blocked_scripts as $service => $data) {
                foreach ($data['patterns'] as $pattern) {
                    if (preg_match($pattern, $script_content)) {
                        $script_category = $this->get_script_category($service);
                        if ($script_category && !in_array($script_category, $accepted_categories)) {
                            return '';
                        }
                    }
                }
            }

            return $full_tag;
        }, $html);
    }

    /**
     * Verarbeitet IFrames
     */
    private function process_iframes(&$html, $accepted_categories) {
        $html = preg_replace_callback('/<iframe[^>]*src=[\'"]([^\'"]*)[\'"][^>]*>/i', function($matches) use ($accepted_categories) {
            $iframe_url = $matches[1];
            $full_tag = $matches[0];

            // Überspringe Divi-spezifische IFrames
            if (strpos($iframe_url, 'et-') !== false || strpos($iframe_url, 'divi') !== false) {
                return $full_tag;
            }

            // Prüfe auf bekannte Tracking-IFrames
            foreach ($this->blocked_scripts as $service => $data) {
                foreach ($data['patterns'] as $pattern) {
                    if (preg_match($pattern, $iframe_url)) {
                        $script_category = $this->get_script_category($service);
                        if ($script_category && !in_array($script_category, $accepted_categories)) {
                            return '';
                        }
                    }
                }
            }

            return $full_tag;
        }, $html);
    }

    /**
     * Ermittelt die Kategorie eines Scripts basierend auf der Datenbank-Konfiguration
     */
    private function get_script_category($url) {
        foreach ($this->cookie_categories as $category) {
            if (isset($category['patterns']) && is_array($category['patterns'])) {
                foreach ($category['patterns'] as $pattern) {
                    if (preg_match($pattern, $url)) {
                        return $category['slug'];
                    }
                }
            }
        }
        
        // Wenn keine Kategorie gefunden wurde, gib 'necessary' zurück
        return 'necessary';
    }

    /**
     * Blockiert das Setzen von Cookies
     */
    public function block_cookies() {
        if (!$this->are_cookies_accepted()) {
            // Setzt Headers, um Cookies zu blockieren
            header('Set-Cookie: ', true);
            
            // Löscht bestehende Cookies
            if (!empty($_COOKIE)) {
                foreach ($_COOKIE as $cookie_name => $cookie_value) {
                    if (!$this->is_essential_cookie($cookie_name)) {
                        setcookie($cookie_name, '', time() - 3600, '/');
                        unset($_COOKIE[$cookie_name]);
                    }
                }
            }
        }
    }

    /**
     * Modifiziert die HTTP-Header
     */
    public function modify_headers() {
        if (!$this->are_cookies_accepted()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
        }
    }

    /**
     * Prüft, ob ein Script blockiert werden soll
     */
    private function should_block_script($src) {
        if (!$src) {
            return false;
        }

        foreach ($this->blocked_scripts as $service => $data) {
            foreach ($data['patterns'] as $pattern) {
                if (preg_match($pattern, $src)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Prüft, ob ein Cookie essentiell ist
     */
    private function is_essential_cookie($cookie_name) {
        $essential_patterns = array(
            '/^wordpress_/',
            '/^wp-/',
            '/^PHPSESSID$/'
        );

        foreach ($essential_patterns as $pattern) {
            if (preg_match($pattern, $cookie_name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prüft, ob Cookies akzeptiert wurden
     */
    private function are_cookies_accepted() {
        // Prüft den Cookie-Consent-Status
        $consent = isset($_COOKIE['cookie_consent']) ? $_COOKIE['cookie_consent'] : '';
        
        if (empty($consent)) {
            return false;
        }

        try {
            $consent_data = json_decode($consent, true);
            return !empty($consent_data['accepted']);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Gibt die Liste der blockierten Scripts zurück
     */
    public function get_blocked_scripts() {
        return $this->blocked_scripts;
    }

    /**
     * Fügt ein neues Script zur Blockier-Liste hinzu
     */
    public function add_blocked_script($service, $patterns) {
        if (!isset($this->blocked_scripts[$service])) {
            $this->blocked_scripts[$service] = array(
                'patterns' => (array) $patterns
            );
        } else {
            $this->blocked_scripts[$service]['patterns'] = array_merge(
                $this->blocked_scripts[$service]['patterns'],
                (array) $patterns
            );
        }
    }

    private function get_accepted_categories() {
        if (isset($_COOKIE['cookie_consent'])) {
            $preferences = json_decode(stripslashes($_COOKIE['cookie_consent']), true);
            if (isset($preferences['accepted_categories'])) {
                return $preferences['accepted_categories'];
            }
        }
        return array('necessary'); // Standardmäßig nur notwendige Cookies akzeptieren
    }
} 