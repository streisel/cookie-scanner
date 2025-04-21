<?php
namespace CookieScanner\Database;

class Database {
    private $wpdb;
    private $table_cookies;
    private $table_settings;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_cookies = $wpdb->prefix . 'cookie_scanner_cookies';
        $this->table_settings = $wpdb->prefix . 'cookie_scanner_settings';
    }

    /**
     * Cookie-Operationen
     */
    public function add_cookie($data) {
        return $this->wpdb->insert(
            $this->table_cookies,
            $data,
            array('%d', '%s', '%s', '%s', '%s', '%s', '%d')
        );
    }

    public function update_cookie($id, $data) {
        return $this->wpdb->update(
            $this->table_cookies,
            $data,
            array('id' => $id),
            array('%d', '%s', '%s', '%s', '%s', '%s', '%d'),
            array('%d')
        );
    }

    public function delete_cookie($id) {
        return $this->wpdb->delete(
            $this->table_cookies,
            array('id' => $id),
            array('%d')
        );
    }

    public function get_cookie($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_cookies} WHERE id = %d",
                $id
            )
        );
    }

    public function get_cookies($category_id = null) {
        if ($category_id) {
            return $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT * FROM {$this->table_cookies} WHERE category_id = %d",
                    $category_id
                )
            );
        }
        return $this->wpdb->get_results("SELECT * FROM {$this->table_cookies}");
    }

    /**
     * Einstellungs-Operationen
     */
    public function set_setting($key, $value) {
        $existing = $this->get_setting($key);
        
        if ($existing) {
            return $this->wpdb->update(
                $this->table_settings,
                array('setting_value' => maybe_serialize($value)),
                array('setting_key' => $key),
                array('%s'),
                array('%s')
            );
        }

        return $this->wpdb->insert(
            $this->table_settings,
            array(
                'setting_key' => $key,
                'setting_value' => maybe_serialize($value)
            ),
            array('%s', '%s')
        );
    }

    public function get_setting($key) {
        $result = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT setting_value FROM {$this->table_settings} WHERE setting_key = %s",
                $key
            )
        );
        return maybe_unserialize($result);
    }

    public function delete_setting($key) {
        return $this->wpdb->delete(
            $this->table_settings,
            array('setting_key' => $key),
            array('%s')
        );
    }

    /**
     * Hilfsfunktionen
     */
    public function clear_all_cookies() {
        return $this->wpdb->query("TRUNCATE TABLE {$this->table_cookies}");
    }

    public function get_cookies_count() {
        return $this->wpdb->get_var("SELECT COUNT(*) FROM {$this->table_cookies}");
    }

    public function get_third_party_cookies() {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table_cookies} WHERE is_third_party = 1"
        );
    }

    public function get_cookies_by_domain($domain) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_cookies} WHERE domain LIKE %s",
                '%' . $this->wpdb->esc_like($domain) . '%'
            )
        );
    }
} 