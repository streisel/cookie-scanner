<?php
namespace DiviCookieConsent\Frontend;

use DiviCookieConsent\Includes\Cookie_Manager;
use DiviCookieConsent\Includes\Consent_Logger;
use DiviCookieConsent\Includes\Legal_Framework;
use DiviCookieConsent\Includes\Database;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Frontend-Hauptklasse
 * Verwaltet die Frontend-Funktionalität des Plugins
 */
class Frontend {
    /**
     * @var Cookie_Manager
     */
    private $cookie_manager;
    
    /**
     * @var Consent_Logger
     */
    private $consent_logger;
    
    /**
     * @var Legal_Framework
     */
    private $legal_framework;
    
    /**
     * @var Database
     */
    private $db;
    
    /**
     * @var array
     */
    private $settings;
    
    /**
     * Konstruktor
     */
    public function __construct(Cookie_Manager $cookie_manager, Consent_Logger $consent_logger, Legal_Framework $legal_framework, Database $db) {
        $this->cookie_manager = $cookie_manager;
        $this->consent_logger = $consent_logger;
        $this->legal_framework = $legal_framework;
        $this->db = $db;
        $this->settings = get_option('dcc_settings', array());
        
        // Frontend-Hooks registrieren
        $this->register_hooks();
    }
    
    /**
     * Registriert alle notwendigen Hooks
     */
    private function register_hooks() {
        // Enqueue Scripts und Styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Banner-Rendering
        add_action('wp_footer', array($this, 'render_cookie_banner'), 100);
        
        // Output-Buffer für Script-Blocking
        add_action('template_redirect', array($this, 'start_output_buffer'), 0);
        
        // AJAX-Endpunkte
        add_action('wp_ajax_nopriv_dcc_save_consent', array($this, 'ajax_save_consent'));
        add_action('wp_ajax_dcc_save_consent', array($this, 'ajax_save_consent'));
    }
    
    /**
     * Lädt die notwendigen Scripts und Styles
     */
    public function enqueue_scripts() {
        // Hauptstylsheet laden
        wp_enqueue_style(
            'dcc-frontend',
            DCC_PLUGIN_URL . 'assets/css/dcc-frontend.css',
            array(),
            DCC_VERSION
        );
        
        // Benutzerdefiniertes CSS hinzufügen, falls vorhanden
        if (!empty($this->settings['advanced']['custom_css'])) {
            wp_add_inline_style('dcc-frontend', $this->settings['advanced']['custom_css']);
        }
        
        // Frontend JavaScript
        wp_enqueue_script(
            'dcc-frontend',
            DCC_PLUGIN_URL . 'assets/js/dcc-frontend.js',
            array('jquery'),
            DCC_VERSION,
            true
        );
        
        // Script-Blocker
        if ($this->should_block_scripts()) {
            wp_enqueue_script(
                'dcc-script-blocker',
                DCC_PLUGIN_URL . 'assets/js/dcc-script-blocker.js',
                array('jquery', 'dcc-frontend'),
                DCC_VERSION,
                true
            );
        }
        
        // Aktuelle Cookie-Version abrufen
        $version_hash = $this->cookie_manager->get_current_version_hash();
        
        // Daten für das Frontend-Script
        $script_data = array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dcc_frontend_nonce'),
            'versionHash' => $version_hash,
            'cookieCategories' => $this->get_cookie_categories(),
            'consentExpiry' => $this->get_consent_expiry(),
            'reloadAfterConsent' => !empty($this->settings['consent']['reload_after_consent']),
            'cookiePrefix' => 'dcc_',
            'texts' => $this->get_localized_texts(),
            'userRegion' => $this->legal_framework->get_current_region(),
            'requiresExplicitConsent' => $this->legal_framework->requires_explicit_consent(),
            'requiresGranularChoices' => $this->legal_framework->requires_granular_choices(),
            'requiresRejectOption' => $this->legal_framework->requires_reject_option()
        );
        
        // Daten an Frontend-JS übergeben
        wp_localize_script('dcc-frontend', 'dccData', apply_filters('dcc_frontend_script_data', $script_data));
        
        // Benutzerdefiniertes JavaScript hinzufügen, falls vorhanden
        if (!empty($this->settings['advanced']['custom_js'])) {
            wp_add_inline_script('dcc-frontend', $this->settings['advanced']['custom_js']);
        }
    }
    
    /**
     * Rendert das Cookie-Banner im Footer
     */
    public function render_cookie_banner() {
        // Banner nur anzeigen, wenn keine gültige Einwilligung vorhanden ist
        // oder wenn der Visual Builder aktiv ist
        $is_vb_active = function_exists('et_core_is_fb_enabled') && et_core_is_fb_enabled();
        
        if ($this->has_valid_consent() && !$is_vb_active) {
            // Nur den "Einstellungen"-Button anzeigen
            echo '<div class="dcc-settings-button-wrapper dcc-show">';
            echo '<button id="dcc-open-settings" class="dcc-settings-button dcc-button dcc-button-secondary">';
            echo esc_html($this->get_localized_texts()['settings_button']);
            echo '</button>';
            echo '</div>';
            return;
        }
        
        // Action vor Banner-Anzeige
        do_action('dcc_before_banner_display');
        
        // Banner-Position aus den Einstellungen abrufen
        $settings = $this->settings;
        $position = isset($settings['appearance']['position']) ? $settings['appearance']['position'] : 'bottom';
        $layout = isset($settings['appearance']['layout']) ? $settings['appearance']['layout'] : 'bar';
        $theme = isset($settings['appearance']['theme']) ? $settings['appearance']['theme'] : 'light';
        $animation = isset($settings['appearance']['animation']) ? $settings['appearance']['animation'] : 'slide';
        
        // Banner-HTML generieren
        $banner_html = $this->get_banner_html($position, $layout, $theme, $animation);
        
        // Banner ausgeben
        echo apply_filters('dcc_cookie_banner_html', $banner_html, $position);
        
        // Action nach Banner-Anzeige
        do_action('dcc_after_banner_display');
    }
    
    /**
     * Generiert das HTML für das Cookie-Banner
     */
    private function get_banner_html($position = 'bottom', $layout = 'bar', $theme = 'light', $animation = 'slide') {
        // CSS-Klassen für das Banner
        $banner_classes = array(
            'dcc-cookie-banner',
            'dcc-layout-' . esc_attr($layout),
            'dcc-theme-' . esc_attr($theme),
            'dcc-position-' . esc_attr($position),
            'dcc-animation-' . esc_attr($animation),
        );
        
        // Überprüfen, ob eine Barrierefreiheits-Option aktiviert ist
        if (!empty($this->settings['advanced']['enable_accessibility'])) {
            $banner_classes[] = 'dcc-a11y-enabled';
        }
        
        // Regionsabhängige Klassen hinzufügen
        $region = $this->legal_framework->get_current_region();
        $banner_classes[] = 'dcc-region-' . esc_attr($region);
        
        // Banner-Start mit Klassen
        $html = '<div id="dcc-cookie-banner" class="' . esc_attr(implode(' ', $banner_classes)) . '" role="dialog" aria-live="polite" aria-labelledby="dcc-banner-heading">';
        
        // Banner-Inhalt basierend auf Layout
        $html .= $this->get_banner_content_by_layout($layout);
        
        // Banner schließen
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Generiert den Inhalt des Banners basierend auf dem Layout
     */
    private function get_banner_content_by_layout($layout) {
        $texts = $this->get_localized_texts();
        $categories = $this->get_cookie_categories();
        
        // HTML basierend auf Layout
        switch ($layout) {
            case 'popup':
                return $this->get_popup_layout_html($texts, $categories);
            
            case 'box':
                return $this->get_box_layout_html($texts, $categories);
            
            case 'bar':
            default:
                return $this->get_bar_layout_html($texts, $categories);
        }
    }
    
    /**
     * Generiert HTML für das Bar-Layout
     */
    private function get_bar_layout_html($texts, $categories) {
        $html = '<div class="dcc-banner-container">';
        
        // Überschrift und Beschreibung
        $html .= '<div class="dcc-banner-content">';
        $html .= '<h2 class="dcc-banner-heading">' . esc_html($texts['banner_heading']) . '</h2>';
        $html .= '<p class="dcc-banner-description">' . wp_kses_post($this->process_privacy_policy_link($texts['banner_description'])) . '</p>';
        $html .= '</div>';
        
        // Buttons
        $html .= '<div class="dcc-banner-actions">';
        
        // Sicherstellen, dass die Buttons die richtigen CSS-Klassen haben
        $equal_prominence = $this->legal_framework->requires_equal_prominence();
        
        if ($equal_prominence) {
            $html .= '<div class="dcc-equal-buttons">';
        }
        
        // Button "Einstellungen"
        if ($this->legal_framework->requires_granular_choices()) {
            $html .= '<button type="button" class="dcc-button dcc-button-secondary dcc-settings-button" aria-haspopup="dialog">' . esc_html($texts['settings_button']) . '</button>';
        }
        
        // Button "Ablehnen", wenn erforderlich
        if ($this->legal_framework->requires_reject_option()) {
            $html .= '<button type="button" class="dcc-button dcc-button-decline dcc-decline-button">' . esc_html($texts['decline_button']) . '</button>';
        }
        
        // Button "Alle akzeptieren"
        $html .= '<button type="button" class="dcc-button dcc-button-primary dcc-accept-all-button">' . esc_html($texts['accept_all_button']) . '</button>';
        
        if ($equal_prominence) {
            $html .= '</div>'; // .dcc-equal-buttons
        }
        
        $html .= '</div>'; // .dcc-banner-actions
        $html .= '</div>'; // .dcc-banner-container
        
        // Einstellungs-Popup (ausgeblendet)
        $html .= $this->get_settings_popup_html($texts, $categories);
        
        return $html;
    }
    
    
    /**
     * Generiert HTML für das Box-Layout
     */
    private function get_box_layout_html($texts, $categories) {
        $html = '<div class="dcc-banner-container">';
        
        // Überschrift
        $html .= '<h2 class="dcc-banner-heading">' . esc_html($texts['banner_heading']) . '</h2>';
        
        // Beschreibung
        $html .= '<div class="dcc-banner-content">';
        $html .= '<p class="dcc-banner-description">' . wp_kses_post($this->process_privacy_policy_link($texts['banner_description'])) . '</p>';
        
        // Link zur Datenschutzerklärung
        if (!empty($texts['privacy_policy_text'])) {
            $html .= '<p class="dcc-privacy-policy-text">' . wp_kses_post($this->process_privacy_policy_link($texts['privacy_policy_text'])) . '</p>';
        }
        $html .= '</div>';
        
        // Buttons
        $html .= '<div class="dcc-banner-actions">';
        
        // Sicherstellen, dass die Buttons die richtigen CSS-Klassen haben
        $equal_prominence = $this->legal_framework->requires_equal_prominence();
        
        if ($equal_prominence) {
            $html .= '<div class="dcc-equal-buttons">';
        }
        
        // Button "Ablehnen", wenn erforderlich
        if ($this->legal_framework->requires_reject_option()) {
            $html .= '<button type="button" class="dcc-button dcc-button-decline dcc-decline-button">' . esc_html($texts['decline_button']) . '</button>';
        }
        
        // Button "Einstellungen"
        if ($this->legal_framework->requires_granular_choices()) {
            $html .= '<button type="button" class="dcc-button dcc-button-secondary dcc-settings-button" aria-haspopup="dialog">' . esc_html($texts['settings_button']) . '</button>';
        }
        
        // Button "Alle akzeptieren"
        $html .= '<button type="button" class="dcc-button dcc-button-primary dcc-accept-all-button">' . esc_html($texts['accept_all_button']) . '</button>';
        
        if ($equal_prominence) {
            $html .= '</div>'; // .dcc-equal-buttons
        }
        
        $html .= '</div>'; // .dcc-banner-actions
        $html .= '</div>'; // .dcc-banner-container
        
        // Einstellungs-Popup (ausgeblendet)
        $html .= $this->get_settings_popup_html($texts, $categories);
        
        return $html;
    }
    
    /**
     * Generiert HTML für das Popup-Layout
     */
    private function get_popup_layout_html($texts, $categories) {
        $html = '<div class="dcc-banner-container">';
        
        // Überschrift mit Schließen-Button
        $html .= '<div class="dcc-banner-header">';
        $html .= '<h2 class="dcc-banner-heading">' . esc_html($texts['banner_heading']) . '</h2>';
        $html .= '</div>';
        
        // Hauptinhalt mit Beschreibung und Kategorien
        $html .= '<div class="dcc-banner-content">';
        $html .= '<p class="dcc-banner-description">' . wp_kses_post($this->process_privacy_policy_link($texts['banner_description'])) . '</p>';
        
        // Cookie-Kategorien direkt anzeigen
        $html .= '<div class="dcc-cookie-categories">';
        
        foreach ($categories as $id => $category) {
            $is_disabled = !empty($category['required']);
            $is_checked = !empty($category['required']) || !empty($category['default_enabled']);
            
            $html .= '<div class="dcc-cookie-category">';
            $html .= '<div class="dcc-category-header">';
            $html .= '<label class="dcc-category-label">';
            $html .= '<input type="checkbox" name="dcc-cookie-category[]" value="' . esc_attr($id) . '" 
                      ' . ($is_checked ? 'checked' : '') . ' ' . ($is_disabled ? 'disabled' : '') . '>';
            $html .= '<span class="dcc-category-title">' . esc_html($category['title']) . '</span>';
            $html .= '</label>';
            $html .= '<button type="button" class="dcc-category-toggle" aria-expanded="false" aria-controls="dcc-category-desc-' . esc_attr($id) . '">';
            $html .= '<span class="screen-reader-text">' . esc_html__('Details anzeigen/ausblenden', 'divi-cookie-consent') . '</span>';
            $html .= '</button>';
            $html .= '</div>';
            
            // Beschreibung (ausgeblendet)
            $html .= '<div id="dcc-category-desc-' . esc_attr($id) . '" class="dcc-category-description" hidden>';
            $html .= '<p>' . esc_html($category['description']) . '</p>';
            $html .= '</div>';
            $html .= '</div>'; // .dcc-cookie-category
        }
        
        $html .= '</div>'; // .dcc-cookie-categories
        
        // Datenschutzhinweis
        if (!empty($texts['privacy_policy_text'])) {
            $html .= '<p class="dcc-privacy-policy-text">' . wp_kses_post($this->process_privacy_policy_link($texts['privacy_policy_text'])) . '</p>';
        }
        
        $html .= '</div>'; // .dcc-banner-content
        
        // Buttons
        $html .= '<div class="dcc-banner-actions">';
        
        // Sicherstellen, dass die Buttons die richtigen CSS-Klassen haben
        $equal_prominence = $this->legal_framework->requires_equal_prominence();
        
        if ($equal_prominence) {
            $html .= '<div class="dcc-equal-buttons">';
        }
        
        // Button "Ablehnen", wenn erforderlich
        if ($this->legal_framework->requires_reject_option()) {
            $html .= '<button type="button" class="dcc-button dcc-button-decline dcc-decline-button">' . esc_html($texts['decline_button']) . '</button>';
        }
        
        // Button "Auswahl akzeptieren"
        $html .= '<button type="button" class="dcc-button dcc-button-secondary dcc-accept-selected-button">' . esc_html($texts['accept_selected_button']) . '</button>';
        
        // Button "Alle akzeptieren"
        $html .= '<button type="button" class="dcc-button dcc-button-primary dcc-accept-all-button">' . esc_html($texts['accept_all_button']) . '</button>';
        
        if ($equal_prominence) {
            $html .= '</div>'; // .dcc-equal-buttons
        }
        
        $html .= '</div>'; // .dcc-banner-actions
        $html .= '</div>'; // .dcc-banner-container
        
        return $html;
    }
    
    /**
     * Generiert HTML für das Einstellungs-Popup
     */
    private function get_settings_popup_html($texts, $categories) {
        $html = '<div id="dcc-settings-popup" class="dcc-settings-popup" hidden>';
        $html .= '<div class="dcc-settings-container">';
        
        // Überschrift mit Schließen-Button
        $html .= '<div class="dcc-settings-header">';
        $html .= '<h2 class="dcc-settings-heading">' . esc_html__('Cookie-Einstellungen', 'divi-cookie-consent') . '</h2>';
        $html .= '<button type="button" class="dcc-settings-close">';
        $html .= '<span class="screen-reader-text">' . esc_html__('Schließen', 'divi-cookie-consent') . '</span>';
        $html .= '</button>';
        $html .= '</div>';
        
        // Cookie-Kategorien
        $html .= '<div class="dcc-settings-content">';
        $html .= '<div class="dcc-cookie-categories">';
        
        foreach ($categories as $id => $category) {
            $is_disabled = !empty($category['required']);
            $is_checked = !empty($category['required']) || !empty($category['default_enabled']);
            
            $html .= '<div class="dcc-cookie-category">';
            $html .= '<div class="dcc-category-header">';
            $html .= '<label class="dcc-category-label">';
            $html .= '<input type="checkbox" name="dcc-cookie-category[]" value="' . esc_attr($id) . '" 
                      ' . ($is_checked ? 'checked' : '') . ' ' . ($is_disabled ? 'disabled' : '') . '>';
            $html .= '<span class="dcc-category-title">' . esc_html($category['title']) . '</span>';
            $html .= '</label>';
            $html .= '<button type="button" class="dcc-category-toggle" aria-expanded="false">';
            $html .= '<span class="screen-reader-text">' . esc_html__('Details anzeigen/ausblenden', 'divi-cookie-consent') . '</span>';
            $html .= '</button>';
            $html .= '</div>';
            
            // Beschreibung (ausgeblendet)
            $html .= '<div class="dcc-category-description" hidden>';
            $html .= '<p>' . esc_html($category['description']) . '</p>';
            $html .= '</div>';
            $html .= '</div>'; // .dcc-cookie-category
        }
        
        $html .= '</div>'; // .dcc-cookie-categories
        $html .= '</div>'; // .dcc-settings-content
        
        // Buttons
        $html .= '<div class="dcc-settings-actions">';
        
        // Button "Ablehnen", wenn erforderlich
        if ($this->legal_framework->requires_reject_option()) {
            $html .= '<button type="button" class="dcc-decline-button">' . esc_html($texts['decline_button']) . '</button>';
        }
        
        // Button "Auswahl akzeptieren"
        $html .= '<button type="button" class="dcc-save-settings-button">' . esc_html($texts['save_button']) . '</button>';
        
        // Button "Alle akzeptieren"
        $html .= '<button type="button" class="dcc-accept-all-button">' . esc_html($texts['accept_all_button']) . '</button>';
        
        $html .= '</div>'; // .dcc-settings-actions
        $html .= '</div>'; // .dcc-settings-container
        $html .= '</div>'; // #dcc-settings-popup
        
        return $html;
    }
    
    /**
     * Prüft, ob eine gültige Einwilligung vorhanden ist
     */
    private function has_valid_consent() {
        // Cookie-Name für die Einwilligung
        $cookie_name = 'dcc_consent_id';
        
        // Prüfen, ob das Cookie existiert
        if (!isset($_COOKIE[$cookie_name])) {
            return false;
        }
        
        // Consent-ID aus dem Cookie abrufen
        $consent_id = sanitize_text_field($_COOKIE[$cookie_name]);
        
        // Einwilligung aus der Datenbank abrufen
        $consent = $this->consent_logger->get_consent_by_id($consent_id);
        
        // Prüfen, ob die Einwilligung gültig ist
        if (empty($consent)) {
            return false;
        }
        
        // Prüfen, ob die Einwilligung abgelaufen ist
        $expiry_date = strtotime($consent['expiry_date']);
        if ($expiry_date < time()) {
            return false;
        }
        
        // Prüfen, ob die Cookie-Version sich geändert hat
        $current_version = $this->cookie_manager->get_current_version_hash();
        if ($consent['version_hash'] !== $current_version) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Startet den Output-Buffer für Script-Blocking
     */
    public function start_output_buffer() {
        // Prüfen, ob Script-Blocking aktiv ist
        if ($this->should_block_scripts()) {
            // Action vor Script-Blocking
            do_action('dcc_before_script_blocking');
            
            // Output-Buffer starten
            ob_start(array($this, 'process_output_buffer'));
        }
    }
    
    /**
     * Verarbeitet den Output-Buffer für Script-Blocking
     */
    public function process_output_buffer($buffer) {
        // Prüfen, ob eine gültige Einwilligung vorhanden ist
        if ($this->has_valid_consent()) {
            return $buffer;
        }
        
        // Blocker-Regeln abrufen
        $rules = $this->db->get_blocker_rules(array('is_active' => 1));
        
        // Keine Regeln, Buffer unverändert zurückgeben
        if (empty($rules)) {
            return $buffer;
        }
        
        // HTML-Parser initialisieren
        if (!class_exists('simple_html_dom')) {
            require_once DCC_PLUGIN_DIR . 'includes/lib/simple_html_dom.php';
        }
        
        $html = new \simple_html_dom();
        $html->load($buffer, true, false);
        
        // Skript-Tags verarbeiten
        $this->process_script_tags($html, $rules);
        
        // Inline-Skript-Tags verarbeiten
        $this->process_inline_scripts($html, $rules);
        
        // iframes verarbeiten
        $this->process_iframes($html, $rules);
        
        // Links verarbeiten
        $this->process_tracking_links($html, $rules);
        
        // Action nach Script-Blocking
        do_action('dcc_after_script_blocking');
        
        // Verarbeiteten Inhalt zurückgeben
        return $html->save();
    }
    
    /**
     * Verarbeitet Script-Tags mit src-Attribut
     */
    private function process_script_tags(&$html, $rules) {
        // Alle Skript-Tags mit src-Attribut finden
        $scripts = $html->find('script[src]');
        
        foreach ($scripts as $script) {
            $src = $script->src;
            
            foreach ($rules as $rule) {
                if ($rule['rule_type'] === 'script' && $this->match_rule($src, $rule)) {
                    // Kategorie der Regel
                    $category = $rule['category'];
                    
                    // Skript blockieren
                    $script->type = 'text/plain';
                    $script->setAttribute('data-cookiecategory', $category);
                    
                    break; // Nach dem ersten Treffer abbrechen
                }
            }
        }
    }
    
    /**
     * Verarbeitet Inline-Script-Tags
     */
    private function process_inline_scripts(&$html, $rules) {
        // Alle Inline-Skript-Tags finden (ohne src-Attribut)
        $scripts = $html->find('script:not([src])');
        
        foreach ($scripts as $script) {
            $content = $script->innertext;
            
            foreach ($rules as $rule) {
                if ($rule['rule_type'] === 'inline' && $this->match_rule($content, $rule)) {
                    // Kategorie der Regel
                    $category = $rule['category'];
                    
                    // Skript blockieren
                    $script->type = 'text/plain';
                    $script->setAttribute('data-cookiecategory', $category);
                    
                    break; // Nach dem ersten Treffer abbrechen
                }
            }
        }
    }
    
    /**
     * Verarbeitet iframes
     */
    private function process_iframes(&$html, $rules) {
        // Alle iframes finden
        $iframes = $html->find('iframe');
        
        foreach ($iframes as $iframe) {
            $src = $iframe->src;
            
            foreach ($rules as $rule) {
                if ($rule['rule_type'] === 'iframe' && $this->match_rule($src, $rule)) {
                    // Kategorie der Regel
                    $category = $rule['category'];
                    
                    // Originaldaten speichern
                    $iframe->setAttribute('data-src', $src);
                    $iframe->setAttribute('data-cookiecategory', $category);
                    
                    // Ersatz-Typ bestimmen
                    switch ($rule['replacement_type']) {
                        case 'placeholder':
                            // Platzhalter-Bild einfügen
                            $iframe->src = $rule['replacement_value'];
                            break;
                        
                        case 'clean':
                        default:
                            // Src-Attribut leeren
                            $iframe->src = 'about:blank';
                            break;
                    }
                    
                    // Placeholder-DIV einfügen
                    $parent = $iframe->parent();
                    $placeholder = $html->createElement('div');
                    $placeholder->class = 'dcc-iframe-placeholder';
                    $placeholder->setAttribute('data-category', $category);
                    
                    // Nachricht
                    $message = sprintf(
                        __('Dieser Inhalt wird von %s bereitgestellt. Durch das Laden des Inhalts akzeptieren Sie %s Cookies.', 'divi-cookie-consent'),
                        $this->extract_domain_from_url($src),
                        $category
                    );
                    
                    $placeholder->innertext = '<p>' . esc_html($message) . '</p>';
                    $placeholder->innertext .= '<button type="button" class="dcc-load-content" data-category="' . esc_attr($category) . '">';
                    $placeholder->innertext .= esc_html__('Inhalt laden', 'divi-cookie-consent');
                    $placeholder->innertext .= '</button>';
                    
                    // Nach dem iFrame einfügen
                    $iframe->outertext = $iframe->outertext . $placeholder->outertext;
                    
                    // iFrame verstecken
                    $iframe->style = 'display: none;';
                    
                    break; // Nach dem ersten Treffer abbrechen
                }
            }
        }
    }
    
    /**
     * Verarbeitet Links mit Tracking-Parametern
     */
    private function process_tracking_links(&$html, $rules) {
        // Alle Links finden
        $links = $html->find('a[href]');
        
        foreach ($links as $link) {
            $href = $link->href;
            
            foreach ($rules as $rule) {
                if ($rule['rule_type'] === 'link' && $this->match_rule($href, $rule)) {
                    // Kategorie der Regel
                    $category = $rule['category'];
                    
                    // Originaldaten speichern
                    $link->setAttribute('data-href', $href);
                    $link->setAttribute('data-cookiecategory', $category);
                    
                    // Klick-Event hinzufügen
                    $onclick = $link->onclick;
                    $new_onclick = "return dccHandleTrackedLink(this, '$category');";
                    
                    if (!empty($onclick)) {
                        $link->onclick = $new_onclick . ' ' . $onclick;
                    } else {
                        $link->onclick = $new_onclick;
                    }
                    
                    break; // Nach dem ersten Treffer abbrechen
                }
            }
        }
    }
    
    /**
     * Prüft, ob eine Regel auf einen Wert zutrifft
     */
    private function match_rule($value, $rule) {
        $match_type = $rule['match_type'];
        $match_value = $rule['match_value'];
        
        switch ($match_type) {
            case 'exact':
                return $value === $match_value;
            
            case 'contains':
                return strpos($value, $match_value) !== false;
            
            case 'startswith':
                return strpos($value, $match_value) === 0;
            
            case 'endswith':
                return substr($value, -strlen($match_value)) === $match_value;
            
            case 'regex':
                return preg_match($match_value, $value) === 1;
            
            default:
                return false;
        }
    }
    
    /**
     * Extrahiert den Domain-Namen aus einer URL
     */
    private function extract_domain_from_url($url) {
        $parsed_url = parse_url($url);
        
        if (isset($parsed_url['host'])) {
            return $parsed_url['host'];
        }
        
        return __('Extern', 'divi-cookie-consent');
    }
    
    /**
     * Prüft, ob Script-Blocking aktiviert sein sollte
     */
    private function should_block_scripts() {
        // Einstellungen abrufen
        $settings = $this->settings;
        $advanced = isset($settings['advanced']) ? $settings['advanced'] : array();
        
        // Blockierungsmethode prüfen
        $blocking_method = isset($advanced['script_blocking_method']) ? $advanced['script_blocking_method'] : 'auto';
        
        // Keine Blockierung, wenn explizit deaktiviert
        if ($blocking_method === 'none') {
            return false;
        }
        
        // Keine Blockierung, wenn eine gültige Einwilligung vorhanden ist
        if ($this->has_valid_consent()) {
            return false;
        }
        
        // Keine Blockierung im Admin-Bereich oder für Administratoren
        if (is_admin() || current_user_can('manage_options')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Ruft lokalisierte Texte ab
     */
    private function get_localized_texts() {
        // Standardtexte
        $default_texts = array(
            'banner_heading' => __('Wir verwenden Cookies', 'divi-cookie-consent'),
            'banner_description' => __('Diese Website verwendet Cookies, um Ihre Erfahrung zu verbessern, während Sie durch die Website navigieren.', 'divi-cookie-consent'),
            'accept_all_button' => __('Alle akzeptieren', 'divi-cookie-consent'),
            'accept_selected_button' => __('Auswahl akzeptieren', 'divi-cookie-consent'),
            'decline_button' => __('Ablehnen', 'divi-cookie-consent'),
            'settings_button' => __('Cookie-Einstellungen', 'divi-cookie-consent'),
            'save_button' => __('Einstellungen speichern', 'divi-cookie-consent'),
            'privacy_policy_text' => __('Weitere Informationen finden Sie in unserer [privacy_policy].', 'divi-cookie-consent'),
            'privacy_policy_link_text' => __('Datenschutzerklärung', 'divi-cookie-consent'),
        );
        
        // Gespeicherte Texte abrufen
        $texts = isset($this->settings['texts']) ? $this->settings['texts'] : array();
        
        // Standardtexte mit gespeicherten Texten zusammenführen
        return wp_parse_args($texts, $default_texts);
    }
    
    /**
     * Verarbeitet den Datenschutzlink im Text
     */
    private function process_privacy_policy_link($text) {
        // Datenschutzseite abrufen
        $privacy_page_id = (int) get_option('wp_page_for_privacy_policy');
        
        // Link-Text aus den Einstellungen
        $texts = $this->get_localized_texts();
        $link_text = $texts['privacy_policy_link_text'];
        
        // Wenn eine Datenschutzseite existiert
        if ($privacy_page_id > 0) {
            $privacy_link = get_permalink($privacy_page_id);
            
            // Platzhalter durch Link ersetzen
            $text = str_replace('[privacy_policy]', '<a href="' . esc_url($privacy_link) . '" target="_blank" rel="noopener noreferrer">' . esc_html($link_text) . '</a>', $text);
        } else {
            // Kein Link verfügbar, Platzhalter durch Text ersetzen
            $text = str_replace('[privacy_policy]', esc_html($link_text), $text);
        }
        
        return $text;
    }
    
    /**
     * Ruft die Cookie-Kategorien ab
     */
    private function get_cookie_categories() {
        // Standardkategorien
        $default_categories = array(
            'necessary' => array(
                'enabled' => true,
                'required' => true,
                'title' => __('Notwendig', 'divi-cookie-consent'),
                'description' => __('Notwendige Cookies helfen dabei, eine Website nutzbar zu machen.', 'divi-cookie-consent'),
                'default_enabled' => true,
            ),
            'functional' => array(
                'enabled' => true,
                'required' => false,
                'title' => __('Funktional', 'divi-cookie-consent'),
                'description' => __('Funktionale Cookies ermöglichen erweiterte Funktionen und Personalisierung.', 'divi-cookie-consent'),
                'default_enabled' => false,
            ),
            'analytics' => array(
                'enabled' => true,
                'required' => false,
                'title' => __('Analyse', 'divi-cookie-consent'),
                'description' => __('Analyse-Cookies helfen Website-Besitzern zu verstehen, wie Besucher mit der Website interagieren.', 'divi-cookie-consent'),
                'default_enabled' => false,
            ),
            'marketing' => array(
                'enabled' => true,
                'required' => false,
                'title' => __('Marketing', 'divi-cookie-consent'),
                'description' => __('Marketing-Cookies werden verwendet, um Besucher auf Websites zu verfolgen und personalisierte Anzeigen zu schalten.', 'divi-cookie-consent'),
                'default_enabled' => false,
            ),
        );
        
        // Gespeicherte Kategorien abrufen
        $categories = isset($this->settings['cookie_categories']) ? $this->settings['cookie_categories'] : array();
        
        // Standardkategorien mit gespeicherten Kategorien zusammenführen
        $merged_categories = wp_parse_args($categories, $default_categories);
        
        // Mit Filter zurückgeben
        return apply_filters('dcc_cookie_categories', $merged_categories);
    }
    
    /**
     * Ruft die Ablaufzeit für Einwilligungen ab
     */
    private function get_consent_expiry() {
        $settings = $this->settings;
        $consent_settings = isset($settings['consent']) ? $settings['consent'] : array();
        $default_expiry = 365; // 1 Jahr
        
        $expiry_days = isset($consent_settings['consent_expiry']) ? (int) $consent_settings['consent_expiry'] : $default_expiry;
        
        // Region-spezifische Anforderungen berücksichtigen
        $region = $this->legal_framework->get_current_region();
        $consent_type = 'explicit'; // Standard-Einwilligungstyp
        
        // Mit Filter zurückgeben
        return apply_filters('dcc_consent_expiry', $expiry_days, $consent_type);
    }
    
    /**
     * AJAX-Handler zum Speichern einer Einwilligung
     */
    public function ajax_save_consent() {
        // Sicherheitscheck
        check_ajax_referer('dcc_frontend_nonce', 'nonce');
        
        // Einwilligungsdaten aus dem Request lesen
        $consent_type = isset($_POST['consent_type']) ? sanitize_text_field($_POST['consent_type']) : 'explicit';
        $version_hash = isset($_POST['version_hash']) ? sanitize_text_field($_POST['version_hash']) : '';
        $cookie_categories = isset($_POST['cookie_categories']) ? $_POST['cookie_categories'] : array();
        
        // Kategorien validieren und bereinigen
        if (is_array($cookie_categories)) {
            $cookie_categories = array_map('sanitize_text_field', $cookie_categories);
        } else {
            $cookie_categories = array();
        }
        
        // Consent-ID generieren
        $consent_id = $this->consent_logger->generate_consent_id();
        
        // Einwilligung speichern
        $consent_data = array(
            'consent_id' => $consent_id,
            'consent_type' => $consent_type,
            'version_hash' => $version_hash,
            'cookie_categories' => $cookie_categories,
            'ip_address' => $this->consent_logger->get_anonymized_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
        );
        
        // Wenn Benutzer angemeldet ist, Benutzer-ID hinzufügen
        if (is_user_logged_in()) {
            $consent_data['user_id'] = get_current_user_id();
        }
        
        $result = $this->consent_logger->save_consent($consent_data);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Einwilligung erfolgreich gespeichert.', 'divi-cookie-consent'),
                'consent_id' => $consent_id,
                'expiry_days' => $this->get_consent_expiry(),
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Fehler beim Speichern der Einwilligung.', 'divi-cookie-consent'),
            ));
        }
    }
}