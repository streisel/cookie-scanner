<?php
namespace CookieScanner\Modules\CookieManager;

class CookieManager {
    private $db;
    private $geo;
    private $lang;
    private $slug = 'cookie_manager';
    private $cookie_db = null;
    private $test_mode = false;

    /**
     * Gibt die statischen Cookie-Kategorien zurück
     */
    public static function get_categories() {
        return array(
            (object)array(
                'id' => 'necessary',
                'name' => esc_html__('notwendige Cookies', 'cookie-scanner'),
                'description' => esc_html__('Diese Cookies sind für die Grundfunktionen der Website erforderlich. Sie können nicht deaktiviert werden.', 'cookie-scanner'),
                'is_required' => true
            ),
            (object)array(
                'id' => 'analysis',
                'name' => esc_html__('Analyse-Cookies', 'cookie-scanner'),
                'description' => esc_html__('Diese Cookies helfen uns, die Nutzung der Website zu verstehen und zu verbessern.', 'cookie-scanner'),
                'is_required' => false
            ),
            (object)array(
                'id' => 'marketing',
                'name' => esc_html__('Marketing-Cookies', 'cookie-scanner'),
                'description' => esc_html__('Diese Cookies werden verwendet, um Werbung für Sie relevanter zu machen.', 'cookie-scanner'),
                'is_required' => false
            )
        );
    }

    public function __construct() {
        // Frontend-Assets registrieren
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // Divi-Modul registrieren
        add_action('init', array($this, 'register_module'));

        // AJAX-Handler registrieren
        add_action('wp_ajax_cookie_scanner_save_preferences', array($this, 'handle_save_preferences'));
        add_action('wp_ajax_nopriv_cookie_scanner_save_preferences', array($this, 'handle_save_preferences'));
        add_action('wp_ajax_cookie_scanner_delete_cookies', array($this, 'handle_delete_cookies'));
        add_action('wp_ajax_nopriv_cookie_scanner_delete_cookies', array($this, 'handle_delete_cookies'));
        add_action('wp_ajax_cookie_scanner_update_script_blocking', array($this, 'handle_update_script_blocking'));
        add_action('wp_ajax_nopriv_cookie_scanner_update_script_blocking', array($this, 'handle_update_script_blocking'));

        // Initialisiere Hilfsklassen
        $this->db = new \CookieScanner\Database\Database();
        $this->geo = new \CookieScanner\GeoLocation();
        $this->lang = new \CookieScanner\LanguageManager();

        // Lade Cookie-DB
        $this->load_cookie_db();
    }

    private function load_cookie_db() {
        $db_path = COOKIE_SCANNER_PLUGIN_DIR . 'data/cookies.json';
        
        // Erstelle das Verzeichnis, falls es nicht existiert
        if (!file_exists(COOKIE_SCANNER_PLUGIN_DIR . 'data')) {
            mkdir(COOKIE_SCANNER_PLUGIN_DIR . 'data', 0755, true);
        }
        
        // Verschiebe die Datei, falls sie noch im alten Verzeichnis liegt
        $old_path = COOKIE_SCANNER_PLUGIN_DIR . 'assets/cookie-db/cookies.json';
        if (file_exists($old_path) && !file_exists($db_path)) {
            rename($old_path, $db_path);
        }
        
        if (file_exists($db_path)) {
            $json_content = file_get_contents($db_path);
            $this->cookie_db = json_decode($json_content, true);
        }
    }

    private function get_cookie_info($cookie_name) {
        if (!$this->cookie_db) {
            return null;
        }

        // Suche nach dem Cookie in der DB
        foreach ($this->cookie_db as $cookie) {
            if (strtolower($cookie['name']) === strtolower($cookie_name)) {
                return $cookie;
            }
        }

        return null;
    }

    public function register_module() {
        if (class_exists('ET_Builder_Module')) {
            require_once __DIR__ . '/CookieManagerModule.php';
            new CookieManagerModule();
        }
    }

    public function get_fields() {
        return array(
            'title' => array(
                'label'           => esc_html__('Titel', 'cookie-scanner'),
                'type'            => 'text',
                'option_category' => 'basic_option',
                'description'     => esc_html__('Der Titel des Cookie-Managers.', 'cookie-scanner'),
                'default'         => esc_html__('Cookie-Einstellungen', 'cookie-scanner'),
            ),
            'description' => array(
                'label'           => esc_html__('Beschreibung', 'cookie-scanner'),
                'type'            => 'textarea',
                'option_category' => 'basic_option',
                'description'     => esc_html__('Die Beschreibung des Cookie-Managers.', 'cookie-scanner'),
                'default'         => esc_html__('Diese Website verwendet Cookies, um Ihre Erfahrung zu verbessern.', 'cookie-scanner'),
            ),
            'layout_style' => array(
                'label'           => esc_html__('Layout-Stil', 'cookie-scanner'),
                'type'            => 'select',
                'option_category' => 'layout',
                'options'         => array(
                    'banner' => esc_html__('Banner', 'cookie-scanner'),
                    'modal'  => esc_html__('Modal', 'cookie-scanner'),
                ),
                'default'         => 'banner',
            ),
            'position' => array(
                'label'           => esc_html__('Position', 'cookie-scanner'),
                'type'            => 'select',
                'option_category' => 'layout',
                'options'         => array(
                    'top'    => esc_html__('Oben', 'cookie-scanner'),
                    'bottom' => esc_html__('Unten', 'cookie-scanner'),
                ),
                'default'         => 'bottom',
            ),
            'color_scheme' => array(
                'label'           => esc_html__('Farbschema', 'cookie-scanner'),
                'type'            => 'select',
                'option_category' => 'color_option',
                'options'         => array(
                    'light' => esc_html__('Hell', 'cookie-scanner'),
                    'dark'  => esc_html__('Dunkel', 'cookie-scanner'),
                ),
                'default'         => 'light',
            ),
            'show_categories' => array(
                'label'           => esc_html__('Kategorien anzeigen', 'cookie-scanner'),
                'type'            => 'yes_no_button',
                'option_category' => 'configuration',
                'options'         => array(
                    'on'  => esc_html__('Ja', 'cookie-scanner'),
                    'off' => esc_html__('Nein', 'cookie-scanner'),
                ),
                'default'         => 'on',
            ),
            'show_details' => array(
                'label'           => esc_html__('Details anzeigen', 'cookie-scanner'),
                'type'            => 'yes_no_button',
                'option_category' => 'configuration',
                'options'         => array(
                    'on'  => esc_html__('Ja', 'cookie-scanner'),
                    'off' => esc_html__('Nein', 'cookie-scanner'),
                ),
                'default'         => 'on',
            ),
        );
    }

    public function enqueue_frontend_assets() {
        // Nur im Frontend laden
        if (is_admin() && !wp_doing_ajax()) {
            return;
        }
        
        wp_enqueue_style('cookie-manager-style', COOKIE_SCANNER_PLUGIN_URL . 'assets/css/style.css', array(), COOKIE_SCANNER_VERSION);
        wp_enqueue_script('cookie-manager-script', COOKIE_SCANNER_PLUGIN_URL . 'assets/js/script.js', array('jquery'), COOKIE_SCANNER_VERSION, true);
        
        // Einfaches Lokalisierungs-Array ohne Abhängigkeiten
        $localize_data = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cookie_scanner_nonce'),
        );
        
        // Füge bedingt Daten hinzu, wenn die Hilfsobjekte vorhanden sind
        if ($this->lang && method_exists($this->lang, 'get_translations')) {
            $localize_data['translations'] = $this->lang->get_translations('cookie_notice');
        }
        
        if ($this->geo) {
            $localize_data['geo'] = array();
            
            if (method_exists($this->geo, 'get_country_code')) {
                $localize_data['geo']['country'] = $this->geo->get_country_code();
            }
            
            if (method_exists($this->geo, 'get_applicable_regulations')) {
                $localize_data['geo']['regulations'] = $this->geo->get_applicable_regulations();
            }
        }
        
        wp_localize_script('cookie-manager-script', 'cookieManagerData', $localize_data);
    }

    /**
     * Shortcode-Rendering - sicheres Rendering mit Fallback-Werten
     */
    public function render_shortcode($atts = array()) {
        // Prüfe ob wir im Divi-Customizer sind
        if ($this->is_divi_builder()) {
            return '';
        }

        // Standardwerte für Attribute
        $defaults = array(
            'display_behavior' => 'once',
            'position' => 'bottom',
            'theme' => 'light',
            'language' => 'auto'
        );
        
        // Attribute mit Standardwerten zusammenführen
        $atts = shortcode_atts($defaults, $atts);
        
        // Prüfe ob Cookie-Einwilligung bereits erteilt wurde
        if ($atts['display_behavior'] === 'once' && isset($_COOKIE['cookie_consent'])) {
            return '';
        }
        
        // Hole die Cookies aus der Datenbank und ordne sie den Kategorien zu
        $all_cookies = $this->db->get_cookies();
        $categories = self::get_categories();
        foreach ($categories as $category) {
            $category->cookies = array();
        }
        foreach ($all_cookies as $cookie) {
            // Bestimme die Kategorie basierend auf dem Cookie-Namen oder anderen Eigenschaften
            $category_id = 'analysis'; // Standard: Analyse-Cookies
            
            if (isset($cookie->is_required) && $cookie->is_required) {
                $category_id = 'necessary';
            } elseif (isset($cookie->is_marketing) && $cookie->is_marketing) {
                $category_id = 'marketing';
            }
            
            // Füge das Cookie zur entsprechenden Kategorie hinzu
            foreach ($categories as $category) {
                if ($category->id === $category_id) {
                    $category->cookies[] = $cookie;
                    break;
                }
            }
        }
        
        // Sortiere die Kategorien: Notwendige zuerst, dann Analyse, dann Marketing
        usort($categories, function($a, $b) {
            if ($a->id === 'necessary') return -1;
            if ($b->id === 'necessary') return 1;
            
            if ($a->id === 'analysis') return -1;
            if ($b->id === 'analysis') return 1;
            
            return 0;
        });
        
        // Erstelle das HTML für die Cookie-Kategorien
        $categories_html = '';
        if ('on' === $atts['show_categories']) {
            foreach ($categories as $category) {
                $cookies = $category->cookies;
                $cookies_html = '';
                
                // Zeige Cookie-Details nur für nicht-notwendige Kategorien
                if ('on' === $atts['show_details'] && (!isset($category->is_required) || !$category->is_required)) {
                    foreach ($cookies as $cookie) {
                        // Versuche zusätzliche Informationen aus der Cookie-DB zu laden
                        $cookie_info = $this->get_cookie_info($cookie->name);
                        
                        $cookies_html .= sprintf(
                            '<div class="cookie-item">
                                <h4>%s</h4>
                                <p>%s</p>
                                <div class="cookie-details">
                                    <span>%s: %s</span>
                                    <span>%s: %s</span>
                                    %s
                                </div>
                            </div>',
                            esc_html($cookie->name),
                            esc_html($cookie->description),
                            esc_html__('Anbieter', 'cookie-scanner'),
                            esc_html($cookie->provider),
                            esc_html__('Dauer', 'cookie-scanner'),
                            esc_html($cookie->duration),
                            $cookie_info ? sprintf(
                                '<span>%s: %s</span>',
                                esc_html__('Typ', 'cookie-scanner'),
                                esc_html($cookie_info['type'])
                            ) : ''
                        );
                    }
                }
                
                $is_required = isset($category->is_required) ? $category->is_required : false;
                $required_title = $is_required ? ' title="' . esc_attr__('Diese Cookies sind erforderlich und können nicht deaktiviert werden', 'cookie-scanner') . '"' : '';
                
                $categories_html .= sprintf(
                    '<div class="cookie-category%s" role="group" aria-labelledby="category-title-%s">
                        <div class="category-header">
                            <label class="switch">
                                <input type="checkbox" name="cookie_category[]" value="%s" %s %s aria-label="%s">
                                <span class="slider"></span>
                            </label>
                            <h3 id="category-title-%s"%s>%s</h3>
                        </div>
                        <p>%s</p>
                        %s
                    </div>',
                    $is_required ? ' required-category' : '',
                    esc_attr($category->id),
                    esc_attr($category->id),
                    $is_required ? 'checked disabled' : '',
                    $is_required ? 'data-required="true"' : '',
                    esc_attr(sprintf(__('%s aktivieren', 'cookie-scanner'), $category->name)),
                    esc_attr($category->id),
                    $required_title,
                    esc_html($category->name),
                    esc_html($category->description),
                    $cookies_html ? '<div class="cookie-list">' . $cookies_html . '</div>' : ''
                );
            }
        }
        
        // Erstelle das Haupt-HTML
        $output = sprintf(
            '<div class="cookie-manager %s %s" data-position="%s" role="dialog" aria-labelledby="cookie-manager-title" aria-describedby="cookie-manager-description">
                <div class="cookie-manager-content">
                    <h2 id="cookie-manager-title">%s</h2>
                    <div id="cookie-manager-description" class="cookie-manager-description">%s</div>
                    <div class="cookie-categories" role="group" aria-label="%s">%s</div>
                    <div class="cookie-manager-actions">
                        <button class="et_pb_button accept-all" aria-label="%s">%s</button>
                        <button class="et_pb_button reject-all" aria-label="%s">%s</button>
                    </div>
                    <div class="cookie-manager-footer">
                        <a href="%s">%s</a>
                        <a href="%s">%s</a>
                    </div>
                </div>
            </div>',
            esc_attr('cookie-manager-' . $atts['layout_style']),
            esc_attr('cookie-manager-' . $atts['color_scheme']),
            esc_attr($atts['position']),
            esc_html($atts['title']),
            $atts['description'],
            esc_attr__('Cookie-Kategorien', 'cookie-scanner'),
            $categories_html,
            esc_attr__('Alle Cookies akzeptieren', 'cookie-scanner'),
            esc_html__('alle akzeptieren', 'cookie-scanner'),
            esc_attr__('Alle optionalen Cookies ablehnen', 'cookie-scanner'),
            esc_html__('alle ablehnen', 'cookie-scanner'),
            esc_url(get_privacy_policy_url()),
            esc_html__('Datenschutzerklärung', 'cookie-scanner'),
            esc_url(get_permalink(get_option('cookie_policy_page'))),
            esc_html__('Cookie-Richtlinie', 'cookie-scanner')
        );
        
        return $output;
    }

    /**
     * Prüft ob wir uns im Divi-Builder befinden
     */
    private function is_divi_builder() {
        return (
            isset($_GET['et_fb']) || 
            (function_exists('et_core_is_fb_enabled') && et_core_is_fb_enabled()) ||
            (isset($_GET['page_id']) && get_post_type($_GET['page_id']) === 'et_pb')
        );
    }

    public function handle_save_preferences() {
        check_ajax_referer('cookie_scanner_nonce', 'nonce');

        $categories = isset($_POST['categories']) ? $_POST['categories'] : array();
        
        // Hole alle Kategorien
        $all_categories = self::get_categories();
        
        // Filtere die notwendigen Kategorien
        $required_categories = array();
        foreach ($all_categories as $category) {
            if (isset($category->is_required) && $category->is_required) {
                $required_categories[] = $category->id;
            }
        }
        
        // Stelle sicher, dass notwendige Kategorien immer enthalten sind
        $categories = array_unique(array_merge($required_categories, $categories));
        
        // Speichere die Einstellungen
        $this->save_cookie_preferences($categories);
        
        wp_send_json_success();
    }

    private function save_cookie_preferences($categories) {
        // Speichere die Einstellungen in einem Cookie
        if ($this->test_mode) {
            // Im Test-Modus: Cookie nur für 1 Stunde gültig
            setcookie('cookie_consent', json_encode($categories), time() + 3600, '/', '', true, true);
        } else {
            // Normaler Modus: Cookie für 1 Jahr gültig
            setcookie('cookie_consent', json_encode($categories), time() + (365 * 24 * 60 * 60), '/', '', true, true);
        }
    }

    public function get_cookie_preferences() {
        // Hole die Einstellungen aus dem Cookie
        $consent = isset($_COOKIE['cookie_consent']) ? $_COOKIE['cookie_consent'] : null;
        if ($consent) {
            try {
                $preferences = json_decode($consent, true);
                if (is_array($preferences)) {
                    // Stelle sicher, dass notwendige Kategorien immer enthalten sind
                    $all_categories = self::get_categories();
                    $required_categories = array();
                    foreach ($all_categories as $category) {
                        if (isset($category->is_required) && $category->is_required) {
                            $required_categories[] = $category->id;
                        }
                    }
                    
                    // Füge notwendige Kategorien hinzu, falls sie fehlen
                    $preferences = array_unique(array_merge($required_categories, $preferences));
                    
                    // Prüfe ob alle Kategorien eine Entscheidung haben
                    $all_category_ids = array_map(function($category) {
                        return $category->id;
                    }, $all_categories);
                    
                    $missing_categories = array_diff($all_category_ids, $preferences);
                    if (empty($missing_categories)) {
                        return $preferences;
                    }
                }
            } catch (Exception $e) {
                // Bei ungültigem JSON ein leeres Array zurückgeben
            }
        }
        
        // Wenn kein Cookie, ungültige Daten oder fehlende Kategorien, gib ein leeres Array zurück
        return array();
    }

    public function checkCookieConsent() {
        // Hole die Einstellungen aus dem Cookie
        $consent = isset($_COOKIE['cookie_consent']) ? $_COOKIE['cookie_consent'] : null;
        if ($consent) {
            try {
                $preferences = json_decode($consent, true);
                if (is_array($preferences)) {
                    // Stelle sicher, dass notwendige Kategorien immer enthalten sind
                    $all_categories = self::get_categories();
                    $required_categories = array();
                    foreach ($all_categories as $category) {
                        if (isset($category->is_required) && $category->is_required) {
                            $required_categories[] = $category->id;
                        }
                    }
                    
                    // Füge notwendige Kategorien hinzu, falls sie fehlen
                    $preferences = array_unique(array_merge($required_categories, $preferences));
                    
                    // Prüfe ob alle Kategorien eine Entscheidung haben
                    $all_category_ids = array_map(function($category) {
                        return $category->id;
                    }, $all_categories);
                    
                    $missing_categories = array_diff($all_category_ids, $preferences);
                    if (empty($missing_categories)) {
                        return true;
                    }
                }
            } catch (Exception $e) {
                // Bei ungültigem JSON false zurückgeben
            }
        }
        
        return false;
    }

    public function handle_delete_cookies() {
        check_ajax_referer('cookie_scanner_nonce', 'nonce');

        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        // Prüfe, ob die Kategorie notwendig ist
        $categories = self::get_categories();
        $category = null;
        foreach ($categories as $cat) {
            if ($cat->id === $category_id) {
                $category = $cat;
                break;
            }
        }
        
        if ($category && isset($category->is_required) && $category->is_required) {
            wp_send_json_error('Notwendige Cookies können nicht gelöscht werden.');
            return;
        }

        // Lösche die Cookies für die Kategorie
        $cookies = $this->db->get_cookies($category_id);
        foreach ($cookies as $cookie) {
            setcookie($cookie->name, '', time() - 3600, '/', '', true, true);
        }

        wp_send_json_success();
    }

    public function handle_update_script_blocking() {
        check_ajax_referer('cookie_scanner_nonce', 'nonce');

        $preferences = isset($_POST['preferences']) ? $_POST['preferences'] : array();
        
        // Stelle sicher, dass notwendige Kategorien immer aktiviert sind
        $all_categories = self::get_categories();
        $required_categories = array();
        foreach ($all_categories as $category) {
            if (isset($category->is_required) && $category->is_required) {
                $required_categories[] = $category->id;
            }
        }
        
        // Füge notwendige Kategorien zu den Präferenzen hinzu
        $preferences['accepted'] = array_unique(array_merge($required_categories, $preferences['accepted']));
        
        // Aktualisiere die Script-Blockierung
        $this->update_script_blocking($preferences);
        
        wp_send_json_success(array(
            'reload' => true
        ));
    }

    private function update_script_blocking($preferences) {
        // Speichere die Script-Blockierung in einem Cookie
        if ($this->test_mode) {
            // Im Test-Modus: Cookie nur für 1 Stunde gültig
            setcookie('script_blocking', json_encode($preferences), time() + 3600, '/', '', true, true);
        } else {
            // Normaler Modus: Cookie für 1 Jahr gültig
            setcookie('script_blocking', json_encode($preferences), time() + (365 * 24 * 60 * 60), '/', '', true, true);
        }
    }

    public function enable_test_mode() {
        $this->test_mode = true;
    }

    public function disable_test_mode() {
        $this->test_mode = false;
    }
}