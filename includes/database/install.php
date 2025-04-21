<?php
namespace CookieScanner\Database;

class Installer {
    public static function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Tabelle für Cookie-Kategorien
        $table_categories = $wpdb->prefix . 'cookie_scanner_categories';
        $sql_categories = "CREATE TABLE IF NOT EXISTS $table_categories (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            description text,
            is_required tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Tabelle für gefundene Cookies
        $table_cookies = $wpdb->prefix . 'cookie_scanner_cookies';
        $sql_cookies = "CREATE TABLE IF NOT EXISTS $table_cookies (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            category_id mediumint(9) NOT NULL,
            name varchar(255) NOT NULL,
            domain varchar(255),
            description text,
            duration varchar(100),
            provider varchar(255),
            is_third_party tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY category_id (category_id),
            FOREIGN KEY (category_id) REFERENCES $table_categories(id) ON DELETE CASCADE
        ) $charset_collate;";

        // Tabelle für Plugin-Einstellungen
        $table_settings = $wpdb->prefix . 'cookie_scanner_settings';
        $sql_settings = "CREATE TABLE IF NOT EXISTS $table_settings (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";

        // Datenbank-Updates durchführen
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_categories);
        dbDelta($sql_cookies);
        dbDelta($sql_settings);

        // Standard-Kategorien einfügen
        self::insert_default_categories();

        // Version in der Datenbank speichern
        update_option('cookie_scanner_version', COOKIE_SCANNER_VERSION);
    }

    private static function insert_default_categories() {
        global $wpdb;
        $table_categories = $wpdb->prefix . 'cookie_scanner_categories';

        $default_categories = array(
            array(
                'name' => 'Notwendige Cookies',
                'description' => 'Diese Cookies sind für die Grundfunktionen der Website erforderlich.',
                'is_required' => 1
            ),
            array(
                'name' => 'Funktionale Cookies',
                'description' => 'Diese Cookies ermöglichen erweiterte Funktionalitäten und Personalisierung.',
                'is_required' => 0
            ),
            array(
                'name' => 'Analyse-Cookies',
                'description' => 'Diese Cookies helfen uns, die Nutzung der Website zu verstehen und zu verbessern.',
                'is_required' => 0
            ),
            array(
                'name' => 'Marketing-Cookies',
                'description' => 'Diese Cookies werden verwendet, um Werbung relevanter für Sie zu machen.',
                'is_required' => 0
            )
        );

        foreach ($default_categories as $category) {
            $wpdb->insert(
                $table_categories,
                $category,
                array('%s', '%s', '%d')
            );
        }
    }
} 