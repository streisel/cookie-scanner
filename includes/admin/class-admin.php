<?php
namespace CookieScanner\Admin;

class Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX Actions
        add_action('wp_ajax_cookie_scanner_scan', array($this, 'handle_scan_request'));
        add_action('wp_ajax_cookie_scanner_export', array($this, 'handle_export_request'));
        add_action('wp_ajax_cookie_scanner_update', array($this, 'handle_update_request'));
        add_action('wp_ajax_cookie_scanner_delete', array($this, 'handle_delete_request'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Cookie Scanner', 'cookie-scanner'),
            __('Cookie Scanner', 'cookie-scanner'),
            'manage_options',
            'cookie-scanner',
            array($this, 'render_settings_page'),
            'dashicons-shield',
            30
        );

        add_submenu_page(
            'cookie-scanner',
            __('Einstellungen', 'cookie-scanner'),
            __('Einstellungen', 'cookie-scanner'),
            'manage_options',
            'cookie-scanner',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'cookie-scanner',
            __('Cookie-Liste', 'cookie-scanner'),
            __('Cookie-Liste', 'cookie-scanner'),
            'manage_options',
            'cookie-scanner-list',
            array($this, 'render_cookie_list_page')
        );
    }

    public function register_settings() {
        register_setting('cookie_scanner_settings', 'cookie_scanner_options');

        add_settings_section(
            'cookie_scanner_general',
            __('Allgemeine Einstellungen', 'cookie-scanner'),
            array($this, 'render_general_section'),
            'cookie_scanner_settings'
        );

        add_settings_field(
            'cookie_scanner_position',
            __('Position', 'cookie-scanner'),
            array($this, 'render_position_field'),
            'cookie_scanner_settings',
            'cookie_scanner_general'
        );

        add_settings_field(
            'cookie_scanner_color_scheme',
            __('Farbschema', 'cookie-scanner'),
            array($this, 'render_color_scheme_field'),
            'cookie_scanner_settings',
            'cookie_scanner_general'
        );
    }

    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'cookie-scanner') === false) {
            return;
        }

        wp_enqueue_style(
            'cookie-scanner-admin',
            COOKIE_SCANNER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            COOKIE_SCANNER_VERSION
        );

        wp_enqueue_script(
            'cookie-scanner-admin',
            COOKIE_SCANNER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            COOKIE_SCANNER_VERSION,
            true
        );

        wp_localize_script('cookie-scanner-admin', 'cookieScannerAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cookie_scanner_nonce'),
            'i18n' => array(
                'scanning' => __('Scanne...', 'cookie-scanner'),
                'scan' => __('Website scannen', 'cookie-scanner'),
                'exporting' => __('Exportiere...', 'cookie-scanner'),
                'export' => __('Exportieren', 'cookie-scanner'),
                'saving' => __('Speichere...', 'cookie-scanner'),
                'save' => __('Speichern', 'cookie-scanner'),
                'deleting' => __('Lösche...', 'cookie-scanner'),
                'delete' => __('Löschen', 'cookie-scanner'),
                'confirmDelete' => __('Sind Sie sicher, dass Sie dieses Cookie löschen möchten?', 'cookie-scanner'),
                'scanComplete' => sprintf(
                    __('Scan abgeschlossen. <a href="%s">Gefundene Cookies anzeigen</a>', 'cookie-scanner'),
                    admin_url('admin.php?page=cookie-scanner-list')
                )
            )
        ));
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        require_once COOKIE_SCANNER_PLUGIN_DIR . 'includes/admin/views/settings-page.php';
    }

    public function render_cookie_list_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        require_once COOKIE_SCANNER_PLUGIN_DIR . 'includes/admin/views/cookie-list-page.php';
    }

    public function render_general_section() {
        echo '<p>' . esc_html__('Allgemeine Einstellungen für den Cookie Scanner', 'cookie-scanner') . '</p>';
    }

    public function render_position_field() {
        $options = get_option('cookie_scanner_options');
        $position = isset($options['position']) ? $options['position'] : 'bottom';
        ?>
        <select name="cookie_scanner_options[position]">
            <option value="bottom" <?php selected($position, 'bottom'); ?>><?php esc_html_e('Unten', 'cookie-scanner'); ?></option>
            <option value="top" <?php selected($position, 'top'); ?>><?php esc_html_e('Oben', 'cookie-scanner'); ?></option>
        </select>
        <?php
    }

    public function render_color_scheme_field() {
        $options = get_option('cookie_scanner_options');
        $color_scheme = isset($options['color_scheme']) ? $options['color_scheme'] : 'light';
        ?>
        <select name="cookie_scanner_options[color_scheme]">
            <option value="light" <?php selected($color_scheme, 'light'); ?>><?php esc_html_e('Hell', 'cookie-scanner'); ?></option>
            <option value="dark" <?php selected($color_scheme, 'dark'); ?>><?php esc_html_e('Dunkel', 'cookie-scanner'); ?></option>
        </select>
        <?php
    }

    /**
     * Behandelt den AJAX-Request für den Scan-Vorgang
     */
    public function handle_scan_request() {
        check_ajax_referer('cookie_scanner_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sie haben keine Berechtigung für diese Aktion.', 'cookie-scanner')));
        }

        try {
            $scanner = new \CookieScanner\Scanner\CookieScanner();
            $result = $scanner->scan_website();

            if ($result) {
                wp_send_json_success(array(
                    'message' => __('Website wurde erfolgreich gescannt.', 'cookie-scanner'),
                    'data' => $result
                ));
            } else {
                wp_send_json_error(array('message' => __('Scan fehlgeschlagen. Bitte überprüfen Sie die Logs.', 'cookie-scanner')));
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Behandelt den AJAX-Request für den Export
     */
    public function handle_export_request() {
        check_ajax_referer('cookie_scanner_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sie haben keine Berechtigung für diese Aktion.', 'cookie-scanner')));
        }

        try {
            $exporter = new \CookieScanner\Export\CSVExporter();
            $exporter->export();
            // Die Methode beendet sich selbst mit exit
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Behandelt den AJAX-Request für das Update eines Cookies
     */
    public function handle_update_request() {
        check_ajax_referer('cookie_scanner_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sie haben keine Berechtigung für diese Aktion.', 'cookie-scanner')));
        }

        $cookie_id = isset($_POST['cookie_id']) ? intval($_POST['cookie_id']) : 0;
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $duration = isset($_POST['duration']) ? sanitize_text_field($_POST['duration']) : '';
        $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : '';
        $purpose = isset($_POST['purpose']) ? sanitize_textarea_field($_POST['purpose']) : '';

        try {
            $result = $this->db->update_cookie($cookie_id, array(
                'name' => $name,
                'category_id' => $category_id,
                'duration' => $duration,
                'provider' => $provider,
                'purpose' => $purpose
            ));

            if ($result) {
                wp_send_json_success(array('message' => __('Cookie wurde erfolgreich aktualisiert.', 'cookie-scanner')));
            } else {
                wp_send_json_error(array('message' => __('Cookie konnte nicht aktualisiert werden.', 'cookie-scanner')));
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Behandelt den AJAX-Request für das Löschen eines Cookies
     */
    public function handle_delete_request() {
        check_ajax_referer('cookie_scanner_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sie haben keine Berechtigung für diese Aktion.', 'cookie-scanner')));
        }

        $cookie_id = isset($_POST['cookie_id']) ? intval($_POST['cookie_id']) : 0;

        try {
            $result = $this->db->delete_cookie($cookie_id);

            if ($result) {
                wp_send_json_success(array('message' => __('Cookie wurde erfolgreich gelöscht.', 'cookie-scanner')));
            } else {
                wp_send_json_error(array('message' => __('Cookie konnte nicht gelöscht werden.', 'cookie-scanner')));
            }
        } catch (\Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    public function export_cookies() {
        check_ajax_referer('cookie_scanner_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
        }

        $exporter = new \CookieScanner\Export\CSVExporter();
        $result = $exporter->export();
        
        if ($result && isset($result['url'])) {
            wp_send_json_success($result['url']);
        } else {
            wp_send_json_error('Export fehlgeschlagen');
        }
    }
} 