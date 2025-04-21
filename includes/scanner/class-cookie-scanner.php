<?php
namespace CookieScanner\Scanner;

use CookieScanner\Database\Database;

class CookieScanner {
    private $db;
    private $known_cookies = array(
        // Google Analytics
        'google-analytics' => array(
            'pattern' => '/^_ga|^_gid|^_gat|^_gtag/',
            'name' => 'Google Analytics',
            'category' => 'Analyse-Cookies',
            'description' => 'Diese Cookies werden von Google Analytics verwendet, um Besucherstatistiken zu erstellen.',
            'duration' => '2 Jahre',
            'provider' => 'Google LLC',
            'is_third_party' => true
        ),
        // Facebook Pixel
        'facebook' => array(
            'pattern' => '/^_fbp|^_fbc|^fr/',
            'name' => 'Facebook Pixel',
            'category' => 'Marketing-Cookies',
            'description' => 'Diese Cookies werden von Facebook verwendet, um Werbung zu optimieren.',
            'duration' => '3 Monate',
            'provider' => 'Meta Platforms, Inc.',
            'is_third_party' => true
        ),
        // WordPress
        'wordpress' => array(
            'pattern' => '/^wp-|^wordpress_/',
            'name' => 'WordPress',
            'category' => 'Notwendige Cookies',
            'description' => 'Diese Cookies sind für die grundlegende Funktionalität der Website erforderlich.',
            'duration' => 'Session',
            'provider' => 'WordPress',
            'is_third_party' => false
        ),
        // Hotjar
        'hotjar' => array(
            'pattern' => '/^_hjid|^_hjAbsoluteSessionInProgress/',
            'name' => 'Hotjar',
            'category' => 'Analyse-Cookies',
            'description' => 'Diese Cookies werden von Hotjar verwendet, um Benutzerinteraktionen zu analysieren.',
            'duration' => '1 Jahr',
            'provider' => 'Hotjar Ltd',
            'is_third_party' => true
        ),
        // Matomo (Piwik)
        'matomo' => array(
            'pattern' => '/^_pk_id|^_pk_ses/',
            'name' => 'Matomo',
            'category' => 'Analyse-Cookies',
            'description' => 'Diese Cookies werden von Matomo verwendet, um Besucherstatistiken zu erstellen.',
            'duration' => '13 Monate',
            'provider' => 'Matomo',
            'is_third_party' => true
        ),
        // Google AdSense
        'adsense' => array(
            'pattern' => '/^__gads|^test_cookie/',
            'name' => 'Google AdSense',
            'category' => 'Marketing-Cookies',
            'description' => 'Diese Cookies werden von Google AdSense verwendet, um Werbung zu optimieren.',
            'duration' => '1 Jahr',
            'provider' => 'Google LLC',
            'is_third_party' => true
        ),
        // WooCommerce
        'woocommerce' => array(
            'pattern' => '/^woocommerce_|^wp_woocommerce_session/',
            'name' => 'WooCommerce',
            'category' => 'Notwendige Cookies',
            'description' => 'Diese Cookies sind für die E-Commerce-Funktionalität erforderlich.',
            'duration' => 'Session',
            'provider' => 'WooCommerce',
            'is_third_party' => false
        ),
        // Divi
        'divi' => array(
            'pattern' => '/^et-|^divi_/',
            'name' => 'Divi',
            'category' => 'Notwendige Cookies',
            'description' => 'Diese Cookies sind für die Divi-Theme-Funktionalität erforderlich.',
            'duration' => 'Session',
            'provider' => 'Elegant Themes',
            'is_third_party' => false
        )
    );

    public function __construct() {
        $this->db = new Database();
        
        // Cron-Job für automatische Scans registrieren
        add_action('init', array($this, 'register_cron'));
        add_action('cookie_scanner_daily_scan', array($this, 'run_scheduled_scan'));
        
        // Sicherheitsmaßnahmen
        add_action('admin_init', array($this, 'security_checks'));
    }

    /**
     * Führt Sicherheitsüberprüfungen durch
     */
    public function security_checks() {
        // Überprüfe SSL
        if (!is_ssl() && !defined('FORCE_SSL_ADMIN')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p>' . 
                     esc_html__('Cookie Scanner: SSL wird empfohlen für sichere Cookie-Verwaltung.', 'cookie-scanner') . 
                     '</p></div>';
            });
        }

        // Überprüfe Berechtigungen
        if (!current_user_can('manage_options')) {
            wp_die(
                esc_html__('Sie haben keine ausreichenden Berechtigungen, um auf diese Seite zuzugreifen.', 'cookie-scanner'),
                esc_html__('Zugriff verweigert', 'cookie-scanner'),
                array('response' => 403)
            );
        }
    }

    /**
     * Registriert Cron-Jobs für automatische Scans
     */
    public function register_cron() {
        $settings = get_option('cookie_scanner_settings', array());
        $auto_scan = isset($settings['auto_scan']) ? $settings['auto_scan'] : false;
        
        if ($auto_scan) {
            // Bestehenden Cron-Job löschen
            wp_clear_scheduled_hook('cookie_scanner_daily_scan');
            
            // Neuen Cron-Job planen
            $scan_interval = isset($settings['scan_interval']) ? $settings['scan_interval'] : 'weekly';
            
            if (!wp_next_scheduled('cookie_scanner_daily_scan')) {
                if ($scan_interval === 'daily') {
                    wp_schedule_event(time(), 'daily', 'cookie_scanner_daily_scan');
                } elseif ($scan_interval === 'weekly') {
                    wp_schedule_event(time(), 'weekly', 'cookie_scanner_daily_scan');
                } elseif ($scan_interval === 'monthly') {
                    wp_schedule_event(time(), 'monthly', 'cookie_scanner_daily_scan');
                }
            }
        }
    }

    /**
     * Führt den geplanten automatischen Scan aus
     */
    public function run_scheduled_scan() {
        // Führe den Scan aus
        $results = $this->scan_website();
        
        // Speichere ein Scan-Log
        $this->db->save_scan_log(array(
            'scan_type' => 'auto',
            'cookies_found' => count($results['all_cookies']),
            'scan_status' => 'completed',
            'scan_details' => $results,
        ));
    }

    /**
     * Startet einen vollständigen Scan der Website
     */
    public function scan_website($url = '') {
        if (empty($url)) {
            $url = get_site_url();
        }

        // Bestehende Cookies löschen
        $this->db->clear_all_cookies();

        // Sammle URLs von wichtigen Seiten
        $urls_to_scan = $this->get_important_urls();
        $urls_to_scan[] = $url;
        $urls_to_scan = array_unique($urls_to_scan);
        
        // Scanne jede URL
        $all_cookies = array();
        
        foreach ($urls_to_scan as $scan_url) {
            $page_cookies = $this->scan_url($scan_url);
            $all_cookies = array_merge($all_cookies, $page_cookies);
        }
        
        // Entferne Duplikate basierend auf Name, Domain und Pfad
        $unique_cookies = array();
        $cookie_keys = array();
        
        foreach ($all_cookies as $cookie) {
            $key = $cookie['name'] . '|' . $cookie['domain'] . '|' . $cookie['path'];
            
            if (!in_array($key, $cookie_keys)) {
                $cookie_keys[] = $key;
                $unique_cookies[] = $cookie;
            }
        }

        // Cookies in die Datenbank importieren
        foreach ($unique_cookies as $cookie) {
            $this->analyze_and_store_cookie($cookie);
        }

        return array(
            'all_cookies' => $unique_cookies,
            'stats' => array(
                'total' => count($unique_cookies),
                'by_category' => $this->count_cookies_by_category($unique_cookies)
            )
        );
    }

    /**
     * Scannt eine URL nach Cookies
     */
    private function scan_url($url) {
        // Validiere URL
        $url = esc_url_raw($url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return array();
        }

        // Rate Limiting
        $rate_limit_key = 'cookie_scanner_rate_limit_' . md5($url);
        $rate_limit = get_transient($rate_limit_key);
        if ($rate_limit !== false) {
            return array();
        }
        set_transient($rate_limit_key, true, 60); // 1 Minute Limit

        $cookies = array();
        
        // Verwende WordPress HTTP API für den Request
        $response = wp_remote_get($url, array(
            'timeout' => 60,
            'sslverify' => true,
            'cookies' => array(),
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ),
        ));
        
        if (is_wp_error($response)) {
            error_log('Cookie Scanner Error: ' . $response->get_error_message());
            return $cookies;
        }
        
        // Extrahiere Cookies aus der Antwort
        $response_cookies = wp_remote_retrieve_cookies($response);
        $host = parse_url($url, PHP_URL_HOST);
        
        foreach ($response_cookies as $cookie) {
            $cookie_data = array(
                'name' => $cookie->name,
                'domain' => !empty($cookie->domain) ? $cookie->domain : $host,
                'path' => !empty($cookie->path) ? $cookie->path : '/',
                'provider' => $host,
                'type' => 'http',
                'category' => $this->guess_cookie_category($cookie->name, $host),
                'description' => $this->get_cookie_description($cookie->name, $host),
                'duration' => $this->get_cookie_duration($cookie->expires),
                'is_necessary' => $this->is_necessary_cookie($cookie->name, $host) ? 1 : 0,
            );
            
            $cookies[] = $cookie_data;
        }
        
        // Analysiere die Seite auf JavaScript-Cookies, LocalStorage usw.
        $html = wp_remote_retrieve_body($response);
        $js_cookies = $this->scan_html_for_cookies($html, $url);
        
        return array_merge($cookies, $js_cookies);
    }

    /**
     * Scannt HTML-Inhalte nach Cookies und Trackers in JavaScript
     */
    private function scan_html_for_cookies($html, $url) {
        $cookies = array();
        $host = parse_url($url, PHP_URL_HOST);
        
        // Bekannte Cookie- und Tracking-Muster
        $patterns = array(
            // Google Analytics
            '/(?:\'|")?_(?:ga|gid|gat|gtag)(?:\'|")?/i' => array(
                'provider' => 'Google',
                'category' => 'Analyse-Cookies',
                'type' => 'http',
                'duration' => '2 Jahre',
                'description' => 'Google Analytics Tracking-Cookie.',
            ),
            // Facebook Pixel
            '/(?:\'|")?_fbp|fr(?:\'|")?/i' => array(
                'provider' => 'Facebook',
                'category' => 'Marketing-Cookies',
                'type' => 'http',
                'duration' => '3 Monate',
                'description' => 'Facebook Pixel Tracking-Cookie.',
            ),
            // Google AdSense
            '/(?:\'|")?__gads|test_cookie(?:\'|")?/i' => array(
                'provider' => 'Google',
                'category' => 'Marketing-Cookies',
                'type' => 'http',
                'duration' => '1 Jahr',
                'description' => 'Google AdSense Werbe-Cookie.',
            ),
            // Hotjar
            '/(?:\'|")?_hjid|_hjAbsoluteSessionInProgress(?:\'|")?/i' => array(
                'provider' => 'Hotjar',
                'category' => 'Analyse-Cookies',
                'type' => 'http',
                'duration' => '1 Jahr',
                'description' => 'Hotjar Analyse-Cookie.',
            ),
            // Matomo (Piwik)
            '/(?:\'|")?_pk_id|_pk_ses(?:\'|")?/i' => array(
                'provider' => 'Matomo',
                'category' => 'Analyse-Cookies',
                'type' => 'http',
                'duration' => '13 Monate',
                'description' => 'Matomo Analyse-Cookie.',
            ),
            // WordPress cookie (Kommentar-/Session-Cookies)
            '/(?:\'|")?wordpress_(?:[a-z0-9]+)|wordpress_logged_in|wp-settings(?:\'|")?/i' => array(
                'provider' => 'WordPress',
                'category' => 'Notwendige Cookies',
                'type' => 'http',
                'duration' => 'Session',
                'description' => 'WordPress Kernfunktionalitäts-Cookie.',
                'is_necessary' => 1,
            ),
            // LocalStorage
            '/localStorage\.setItem\(\s*(?:\'|")([^\'"]*)(?:\'|")/i' => array(
                'provider' => $host,
                'category' => 'Funktionale Cookies',
                'type' => 'localstorage',
                'duration' => 'Persistent',
                'description' => 'Website-Funktionalitätsdaten, die im Browser LocalStorage gespeichert sind.',
            ),
            // SessionStorage
            '/sessionStorage\.setItem\(\s*(?:\'|")([^\'"]*)(?:\'|")/i' => array(
                'provider' => $host,
                'category' => 'Funktionale Cookies',
                'type' => 'sessionstorage',
                'duration' => 'Session',
                'description' => 'Website-Funktionalitätsdaten, die im Browser SessionStorage gespeichert sind.',
            ),
        );
        
        // Externe JS-Skriptverweise scannen
        $script_patterns = array(
            // Google Analytics
            '/google-analytics\.com|googletagmanager\.com/i' => array(
                'name' => 'Google Analytics',
                'provider' => 'Google',
                'category' => 'Analyse-Cookies',
                'type' => 'html',
                'duration' => 'Session',
                'description' => 'Google Analytics Tracking-Skript.',
            ),
            // Facebook
            '/connect\.facebook\.net/i' => array(
                'name' => 'Facebook Pixel',
                'provider' => 'Facebook',
                'category' => 'Marketing-Cookies',
                'type' => 'html',
                'duration' => 'Session',
                'description' => 'Facebook Tracking-Skript.',
            ),
            // Google Tag Manager
            '/googletagmanager\.com/i' => array(
                'name' => 'Google Tag Manager',
                'provider' => 'Google',
                'category' => 'Funktionale Cookies',
                'type' => 'html',
                'duration' => 'Session',
                'description' => 'Google Tag Manager Skript für Website-Funktionalität und Tracking.',
            ),
            // HotJar
            '/static\.hotjar\.com/i' => array(
                'name' => 'Hotjar',
                'provider' => 'Hotjar',
                'category' => 'Analyse-Cookies',
                'type' => 'html',
                'duration' => 'Session',
                'description' => 'Hotjar Analyse- und Feedback-Skript.',
            ),
            // Google AdSense
            '/pagead2\.googlesyndication\.com|adservice\.google\./i' => array(
                'name' => 'Google AdSense',
                'provider' => 'Google',
                'category' => 'Marketing-Cookies',
                'type' => 'html',
                'duration' => 'Session',
                'description' => 'Google AdSense Werbe-Skript.',
            ),
            // Google Fonts
            '/fonts\.googleapis\.com/i' => array(
                'name' => 'Google Fonts',
                'provider' => 'Google',
                'category' => 'Notwendige Cookies',
                'type' => 'html',
                'duration' => 'Session',
                'description' => 'Google Fonts Ressource für Website-Styling.',
                'is_necessary' => 1,
            ),
        );
        
        // Extrahiere alle <script>-Tags
        preg_match_all('/<script\b[^>]*(?:src=[\'"](.*?)[\'"])?[^>]*>(.*?)<\/script>/si', $html, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $script_src = isset($match[1]) ? $match[1] : '';
            $script_content = isset($match[2]) ? $match[2] : '';
            
            // Überprüfe externe Skriptquellen
            if (!empty($script_src)) {
                foreach ($script_patterns as $pattern => $cookie_info) {
                    if (preg_match($pattern, $script_src)) {
                        $cookie_data = array_merge(array(
                            'name' => $cookie_info['name'],
                            'domain' => parse_url($script_src, PHP_URL_HOST) ?: $host,
                            'path' => '/',
                        ), $cookie_info);
                        
                        $cookies[] = $cookie_data;
                    }
                }
            }
            
            // Überprüfe Skriptinhalte nach Cookie-Mustern
            if (!empty($script_content)) {
                foreach ($patterns as $pattern => $cookie_info) {
                    preg_match_all($pattern, $script_content, $cookie_matches);
                    
                    if (!empty($cookie_matches[0])) {
                        foreach ($cookie_matches[0] as $cookie_match) {
                            $name = isset($cookie_matches[1][0]) ? $cookie_matches[1][0] : str_replace(array('"', "'"), '', $cookie_match);
                            
                            $cookie_data = array_merge(array(
                                'name' => $name,
                                'domain' => $host,
                                'path' => '/',
                            ), $cookie_info);
                            
                            $cookies[] = $cookie_data;
                        }
                    }
                }
            }
        }
        
        return $cookies;
    }

    /**
     * Versucht, die Kategorie eines Cookies anhand seines Namens und seiner Domain zu erraten
     */
    private function guess_cookie_category($name, $domain) {
        // Bekannte Cookie-Namen und deren Kategorien
        $known_cookies = $this->get_known_cookies();
        
        // Prüfen auf genaue Übereinstimmung
        $cookie_key = strtolower($name);
        if (isset($known_cookies[$cookie_key])) {
            return $known_cookies[$cookie_key]['category'];
        }
        
        // Namensbasierte Vermutungen
        $name_lower = strtolower($name);
        
        // Notwendige Cookies
        if (strpos($name_lower, 'wordpress_') === 0 || 
             strpos($name_lower, 'wp-') === 0 ||
             strpos($name_lower, 'woocommerce_') === 0 ||
             $name_lower === 'gdpr' || 
             $name_lower === 'cookie_notice_accepted' ||
             strpos($name_lower, 'consent') !== false ||
             strpos($name_lower, 'necessary') !== false ||
             strpos($name_lower, 'divi_') === 0) {
            return 'Notwendige Cookies';
        }
        
        // Analytik-Cookies
        if (strpos($name_lower, '_ga') === 0 || 
             strpos($name_lower, '_gid') === 0 || 
             strpos($name_lower, '_gat') === 0 ||
             strpos($name_lower, 'analytics') !== false ||
             strpos($name_lower, '_hjid') === 0 ||
             strpos($name_lower, '_pk_') === 0 ||
             strpos($name_lower, 'statcounter') !== false ||
             strpos($name_lower, 'matomo') !== false) {
            return 'Analyse-Cookies';
        }
        
        // Marketing-Cookies
        if (strpos($name_lower, '_fbp') === 0 || 
             strpos($name_lower, 'fr') === 0 ||
             strpos($name_lower, '__gads') === 0 ||
             strpos($name_lower, 'ads') !== false ||
             strpos($name_lower, 'doubleclick') !== false ||
             strpos($name_lower, 'pixel') !== false) {
            return 'Marketing-Cookies';
        }
        
        // Funktionale Cookies (Fallback)
        if (strpos($name_lower, 'prefs') !== false ||
             strpos($name_lower, 'settings') !== false ||
             strpos($name_lower, 'language') !== false ||
             strpos($name_lower, 'session') !== false) {
            return 'Funktionale Cookies';
        }
        
        // Domainbasierte Vermutungen
        $domain_lower = strtolower($domain);
        
        if (strpos($domain_lower, 'google') !== false || 
             strpos($domain_lower, 'doubleclick') !== false) {
            return 'Marketing-Cookies';
        }
        
        if (strpos($domain_lower, 'facebook') !== false || 
             strpos($domain_lower, 'instagram') !== false) {
            return 'Marketing-Cookies';
        }
        
        if (strpos($domain_lower, 'hotjar') !== false || 
             strpos($domain_lower, 'analytics') !== false) {
            return 'Analyse-Cookies';
        }
        
        // Standard-Kategorie, wenn keine Übereinstimmung gefunden wurde
        return 'Funktionale Cookies';
    }

    /**
     * Prüft, ob ein Cookie als notwendig eingestuft werden sollte
     */
    private function is_necessary_cookie($name, $domain) {
        // Bekannte notwendige Cookie-Namen
        $necessary_patterns = array(
            '/^wordpress_/',
            '/^wp-settings-/',
            '/^woocommerce_/',
            '/^gdpr/',
            '/^cookie_notice_accepted/',
            '/^divi_/',
            '/^et_/',
            '/^_secure_session_id/'
        );
        
        $name_lower = strtolower($name);
        
        foreach ($necessary_patterns as $pattern) {
            if (preg_match($pattern, $name_lower)) {
                return true;
            }
        }
        
        // Bekannte Cookie-Namen und deren Kategorien
        $known_cookies = $this->get_known_cookies();
        
        // Prüfen auf genaue Übereinstimmung
        $cookie_key = strtolower($name);
        if (isset($known_cookies[$cookie_key]) && $known_cookies[$cookie_key]['category'] === 'Notwendige Cookies') {
            return true;
        }
        
        return false;
    }

    /**
     * Formatiert die Cookie-Dauer für die Anzeige
     */
    private function get_cookie_duration($expires) {
        if (empty($expires) || $expires === 0) {
            return 'Session';
        }
        
        // Überprüfen, ob es sich um einen Unix-Zeitstempel handelt
        if (is_numeric($expires)) {
            $duration_seconds = $expires - time();
            
            if ($duration_seconds <= 0) {
                return 'Session';
            }
            
            // Umrechnung in verschiedene Zeiteinheiten
            $minutes = floor($duration_seconds / 60);
            $hours = floor($minutes / 60);
            $days = floor($hours / 24);
            $months = floor($days / 30);
            $years = floor($days / 365);
            
            if ($years > 0) {
                return $years . ' ' . _n('Jahr', 'Jahre', $years, 'cookie-scanner');
            } elseif ($months > 0) {
                return $months . ' ' . _n('Monat', 'Monate', $months, 'cookie-scanner');
            } elseif ($days > 0) {
                return $days . ' ' . _n('Tag', 'Tage', $days, 'cookie-scanner');
            } elseif ($hours > 0) {
                return $hours . ' ' . _n('Stunde', 'Stunden', $hours, 'cookie-scanner');
            } else {
                return $minutes . ' ' . _n('Minute', 'Minuten', $minutes, 'cookie-scanner');
            }
        }
        
        return 'Unbekannt';
    }

    /**
     * Ruft eine Beschreibung für bekannte Cookies ab
     */
    private function get_cookie_description($name, $domain) {
        // Bekannte Cookie-Namen und deren Beschreibungen
        $known_cookies = $this->get_known_cookies();
        
        // Prüfen auf genaue Übereinstimmung
        $cookie_key = strtolower($name);
        if (isset($known_cookies[$cookie_key])) {
            return $known_cookies[$cookie_key]['description'];
        }
        
        // Standard-Beschreibungen basierend auf Mustern
        $name_lower = strtolower($name);
        
        if (strpos($name_lower, 'wordpress_') === 0) {
            return __('WordPress-Cookie für eingeloggte Benutzer.', 'cookie-scanner');
        }
        
        if (strpos($name_lower, 'wp-settings-') === 0) {
            return __('WordPress-Cookie für Benutzereinstellungen.', 'cookie-scanner');
        }
        
        if (strpos($name_lower, 'woocommerce_') === 0) {
            return __('WooCommerce-Cookie für Einkaufsfunktionen.', 'cookie-scanner');
        }
        
        if (strpos($name_lower, '_ga') === 0) {
            return __('Google Analytics-Cookie zur Besucheridentifikation.', 'cookie-scanner');
        }
        
        if (strpos($name_lower, '_gid') === 0) {
            return __('Google Analytics-Cookie zur Benutzerunterscheidung.', 'cookie-scanner');
        }
        
        if (strpos($name_lower, '_gat') === 0) {
            return __('Google Analytics-Cookie zur Drosselung der Anforderungsrate.', 'cookie-scanner');
        }
        
        if (strpos($name_lower, '_fbp') === 0) {
            return __('Facebook-Cookie für Werbeverfolgung.', 'cookie-scanner');
        }
        
        return __('Keine Beschreibung verfügbar.', 'cookie-scanner');
    }

    /**
     * Gibt eine Liste wichtiger URLs der Website zurück
     */
    private function get_important_urls() {
        $urls = array();
        
        // Startseite und Blog-Seite
        $urls[] = home_url();
        $urls[] = get_permalink(get_option('page_for_posts'));
        
        // Wichtige WordPress-Seiten
        $important_pages = array(
            'privacy-policy' => __('Datenschutzerklärung', 'cookie-scanner'),
            'imprint' => __('Impressum', 'cookie-scanner'),
            'contact' => __('Kontakt', 'cookie-scanner'),
            'about' => __('Über uns', 'cookie-scanner'),
        );
        
        foreach ($important_pages as $slug => $title) {
            // Suche nach Seiten mit ähnlichem Slug oder Titel
            $page = get_page_by_path($slug);
            
            if (!$page) {
                $pages = get_posts(array(
                    'post_type' => 'page',
                    'posts_per_page' => 1,
                    's' => $title,
                ));
                
                if (!empty($pages)) {
                    $page = $pages[0];
                }
            }
            
            if ($page) {
                $urls[] = get_permalink($page->ID);
            }
        }
        
        // Neueste Beiträge
        $recent_posts = get_posts(array(
            'posts_per_page' => 3,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        
        foreach ($recent_posts as $post) {
            $urls[] = get_permalink($post->ID);
        }
        
        // WooCommerce-Seiten, falls aktiviert
        if (class_exists('WooCommerce')) {
            $urls[] = wc_get_page_permalink('shop');
            $urls[] = wc_get_page_permalink('cart');
            $urls[] = wc_get_page_permalink('checkout');
            $urls[] = wc_get_page_permalink('myaccount');
            
            // Einige Produktkategorien
            $product_cats = get_terms(array(
                'taxonomy' => 'product_cat',
                'hide_empty' => true,
                'number' => 3,
            ));
            
            if (!is_wp_error($product_cats)) {
                foreach ($product_cats as $cat) {
                    $urls[] = get_term_link($cat);
                }
            }
            
            // Einige Produkte
            $products = wc_get_products(array(
                'limit' => 3,
                'orderby' => 'date',
                'order' => 'DESC',
            ));
            
            foreach ($products as $product) {
                $urls[] = get_permalink($product->get_id());
            }
        }
        
        // URLs filtern und leere/fehlerhafte entfernen
        $urls = array_filter($urls);
        
        // Auf maximal 10 URLs beschränken, um Überlastung zu vermeiden
        return array_slice($urls, 0, 10);
    }

    /**
     * Gibt eine Liste bekannter Cookies und ihrer Informationen zurück
     */
    private function get_known_cookies() {
        return array(
            // WordPress
            'wordpress_logged_in' => array(
                'category' => 'Notwendige Cookies',
                'description' => __('Cookie für eingeloggte WordPress-Benutzer.', 'cookie-scanner'),
            ),
            'wordpress_test_cookie' => array(
                'category' => 'Notwendige Cookies',
                'description' => __('WordPress-Test-Cookie.', 'cookie-scanner'),
            ),
            'wp-settings' => array(
                'category' => 'Notwendige Cookies',
                'description' => __('WordPress-Cookie zur Personalisierung der Admin-Oberfläche.', 'cookie-scanner'),
            ),
            
            // Google Analytics
            '_ga' => array(
                'category' => 'Analyse-Cookies',
                'description' => __('Google Analytics-Cookie zur Unterscheidung von Benutzern.', 'cookie-scanner'),
            ),
            '_gid' => array(
                'category' => 'Analyse-Cookies',
                'description' => __('Google Analytics-Cookie zur Unterscheidung von Benutzern.', 'cookie-scanner'),
            ),
            '_gat' => array(
                'category' => 'Analyse-Cookies',
                'description' => __('Google Analytics-Cookie zur Drosselung der Anforderungsrate.', 'cookie-scanner'),
            ),
            
            // Facebook
            '_fbp' => array(
                'category' => 'Marketing-Cookies',
                'description' => __('Facebook-Pixel-Cookie, das für die Werbeverfolgung verwendet wird.', 'cookie-scanner'),
            ),
            'fr' => array(
                'category' => 'Marketing-Cookies',
                'description' => __('Facebook-Cookie für Werbezwecke.', 'cookie-scanner'),
            ),
            
            // Divi
            'et-editor' => array(
                'category' => 'Notwendige Cookies',
                'description' => __('Divi-Editor-Cookie zur Speicherung des Editor-Status.', 'cookie-scanner'),
            ),
            'et-pb-recent-items' => array(
                'category' => 'Notwendige Cookies',
                'description' => __('Divi-Cookie zur Speicherung kürzlich verwendeter Elemente.', 'cookie-scanner'),
            ),
            
            // WooCommerce
            'woocommerce_cart_hash' => array(
                'category' => 'Notwendige Cookies',
                'description' => __('WooCommerce-Cookie für den Warenkorb.', 'cookie-scanner'),
            ),
            'woocommerce_items_in_cart' => array(
                'category' => 'Notwendige Cookies',
                'description' => __('WooCommerce-Cookie für den Warenkorb.', 'cookie-scanner'),
            ),
            'wp_woocommerce_session' => array(
                'category' => 'Notwendige Cookies',
                'description' => __('WooCommerce-Sitzungs-Cookie.', 'cookie-scanner'),
            ),
            
            // Sonstiges
            'cookie_notice_accepted' => array(
                'category' => 'Notwendige Cookies',
                'description' => __('Cookie, das die Akzeptanz des Cookie-Hinweises speichert.', 'cookie-scanner'),
            ),
            'gdpr' => array(
                'category' => 'Notwendige Cookies',
                'description' => __('Cookie, das DSGVO-Einstellungen speichert.', 'cookie-scanner'),
            ),
        );
    }

    /**
     * Zählt Cookies nach Kategorien
     */
    private function count_cookies_by_category($cookies) {
        $counts = array();
        
        foreach ($cookies as $cookie) {
            $category = $cookie['category'];
            
            if (!isset($counts[$category])) {
                $counts[$category] = 0;
            }
            
            $counts[$category]++;
        }
        
        return $counts;
    }

    /**
     * Analysiert und speichert einen gefundenen Cookie
     */
    private function analyze_and_store_cookie($cookie_data) {
        $cookie_info = $this->identify_cookie($cookie_data['name']);
        
        if ($cookie_info) {
            $category = $this->get_category_id($cookie_info['category']);
            
            if ($category) {
                $this->db->add_cookie(array(
                    'category_id' => $category->id,
                    'name' => $cookie_data['name'],
                    'domain' => $cookie_data['domain'],
                    'description' => $cookie_info['description'],
                    'duration' => $cookie_info['duration'],
                    'provider' => $cookie_info['provider'],
                    'is_third_party' => $cookie_info['is_third_party']
                ));
            }
        }
    }

    /**
     * Identifiziert einen Cookie anhand seines Namens
     */
    private function identify_cookie($name) {
        foreach ($this->known_cookies as $cookie_type) {
            if (preg_match($cookie_type['pattern'], $name)) {
                return $cookie_type;
            }
        }
        
        // Unbekannter Cookie
        return array(
            'name' => 'Unbekannt',
            'category' => 'Funktionale Cookies',
            'description' => 'Ein Cookie mit unbekanntem Zweck.',
            'duration' => 'Unbekannt',
            'provider' => 'Unbekannt',
            'is_third_party' => false
        );
    }

    /**
     * Holt die Kategorie-ID anhand des Namens
     */
    private function get_category_id($category_name) {
        $categories = $this->db->get_categories();
        foreach ($categories as $category) {
            if ($category->name === $category_name) {
                return $category;
            }
        }
        return null;
    }

    /**
     * Gibt die Scan-Ergebnisse zurück
     */
    public function get_scan_results() {
        $results = array(
            'total_cookies' => $this->db->get_cookies_count(),
            'third_party_cookies' => count($this->db->get_third_party_cookies()),
            'cookies_by_category' => array()
        );

        $categories = $this->db->get_categories();
        foreach ($categories as $category) {
            $cookies = $this->db->get_cookies($category->id);
            $results['cookies_by_category'][$category->name] = array(
                'count' => count($cookies),
                'cookies' => $cookies
            );
        }

        return $results;
    }

    /**
     * AJAX-Handler zum Starten eines Scans
     */
    public function ajax_start_scan() {
        // Erweiterte Sicherheitsüberprüfungen
        if (!check_ajax_referer('cookie_scanner_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => esc_html__('Sicherheitsüberprüfung fehlgeschlagen.', 'cookie-scanner')
            ));
        }
        
        // Rate Limiting für AJAX-Anfragen
        $user_id = get_current_user_id();
        $rate_limit_key = 'cookie_scanner_ajax_' . $user_id;
        if (get_transient($rate_limit_key)) {
            wp_send_json_error(array(
                'message' => esc_html__('Bitte warten Sie einen Moment, bevor Sie einen neuen Scan starten.', 'cookie-scanner')
            ));
        }
        set_transient($rate_limit_key, true, 300); // 5 Minuten Limit
        
        // Prüfen, ob Benutzer die erforderlichen Rechte hat
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => esc_html__('Unzureichende Berechtigungen.', 'cookie-scanner')
            ));
        }
        
        // Validiere Eingabedaten
        $url = isset($_POST['url']) ? esc_url_raw($_POST['url']) : '';
        
        // Scan starten
        $results = $this->scan_website($url);
        
        // Scan-Log speichern
        $this->db->save_scan_log(array(
            'scan_type' => 'manual',
            'cookies_found' => count($results['all_cookies']),
            'scan_status' => 'completed',
            'scan_details' => $results,
            'user_id' => $user_id,
            'ip_address' => $this->get_client_ip(),
        ));
        
        // Erfolg melden
        wp_send_json_success(array(
            'message' => sprintf(
                esc_html__('Scan abgeschlossen. %d Cookies gefunden.', 'cookie-scanner'),
                count($results['all_cookies'])
            ),
            'cookies' => $results['all_cookies'],
        ));
    }

    /**
     * Ermittelt die Client-IP-Adresse
     */
    private function get_client_ip() {
        $ip = '';
        
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_CLIENT_IP']);
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = sanitize_text_field($_SERVER['HTTP_X_FORWARDED_FOR']);
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = sanitize_text_field($_SERVER['REMOTE_ADDR']);
        }
        
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }
} 