<?php
namespace CookieScanner\Modules\CookieManager;

class CookieManagerModule extends \ET_Builder_Module {
    public function init() {
        $this->name = esc_html__('Cookie Manager', 'cookie-scanner');
        $this->slug = 'cookie_manager';
        $this->vb_support = 'on';
        $this->main_css_element = '%%order_class%%';
        $this->icon = 'j';
        $this->category = 'general';
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
            'display_behavior' => array(
                'label'           => esc_html__('Anzeigeverhalten', 'cookie-scanner'),
                'type'            => 'select',
                'option_category' => 'configuration',
                'options'         => array(
                    'always' => esc_html__('Immer anzeigen', 'cookie-scanner'),
                    'once'   => esc_html__('Nur bei erster Entscheidung', 'cookie-scanner'),
                ),
                'description'     => esc_html__('Bestimmt, wann der Cookie-Manager angezeigt wird.', 'cookie-scanner'),
                'default'         => 'once',
            ),
        );
    }

    public function render_as_builder_data($atts, $content = null, $render_slug, $parent_address = '', $global_parent = '', $global_parent_type = '', $parent_type = '', $theme_builder_area = '') {
        return parent::render_as_builder_data($atts, $content, $render_slug, $parent_address, $global_parent, $global_parent_type, $parent_type, $theme_builder_area);
    }

    /**
     * Haupt-Render-Methode
     */
    public function render($attrs, $content = null, $render_slug) {
        // Verwende die vollständige Funktionalität des Cookie-Managers
        $cookie_manager = new CookieManager();
        return $cookie_manager->render_shortcode($this->props);
    }

    public function get_advanced_fields_config() {
        return array(
            'fonts' => array(
                'title' => array(
                    'label'       => esc_html__('Titel', 'cookie-scanner'),
                    'css'         => array(
                        'main' => "{$this->main_css_element} h2",
                    ),
                    'font_size' => array(
                        'default' => '22px',
                    ),
                    'line_height' => array(
                        'default' => '1.2em',
                    ),
                ),
                'description' => array(
                    'label'       => esc_html__('Beschreibung', 'cookie-scanner'),
                    'css'         => array(
                        'main' => "{$this->main_css_element} .cookie-manager-description",
                    ),
                    'font_size' => array(
                        'default' => '14px',
                    ),
                    'line_height' => array(
                        'default' => '1.4em',
                    ),
                ),
                'accept_button' => array(
                    'label'       => esc_html__('Akzeptieren-Button', 'cookie-scanner'),
                    'css'         => array(
                        'main' => "{$this->main_css_element} .accept-all",
                    ),
                    'font_size' => array(
                        'default' => '16px',
                    ),
                    'line_height' => array(
                        'default' => '1.4em',
                    ),
                ),
                'reject_button' => array(
                    'label'       => esc_html__('Ablehnen-Button', 'cookie-scanner'),
                    'css'         => array(
                        'main' => "{$this->main_css_element} .reject-all",
                    ),
                    'font_size' => array(
                        'default' => '16px',
                    ),
                    'line_height' => array(
                        'default' => '1.4em',
                    ),
                ),
            ),
            'background' => array(
                'settings' => array(
                    'color' => 'alpha',
                ),
                'css' => array(
                    'main' => "{$this->main_css_element}",
                ),
            ),
            'borders' => array(
                'default' => array(
                    'css' => array(
                        'main' => array(
                            'border_radii' => "{$this->main_css_element}",
                            'border_styles' => "{$this->main_css_element}",
                        ),
                    ),
                ),
            ),
            'button' => array(
                'accept_button' => array(
                    'label' => esc_html__('Akzeptieren-Button', 'cookie-scanner'),
                    'css' => array(
                        'main' => "{$this->main_css_element} .accept-all",
                    ),
                ),
                'reject_button' => array(
                    'label' => esc_html__('Ablehnen-Button', 'cookie-scanner'),
                    'css' => array(
                        'main' => "{$this->main_css_element} .reject-all",
                    ),
                ),
            ),
        );
    }
}