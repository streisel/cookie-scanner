<?php
namespace CookieScanner\Database;

class Install {
    /**
     * Führt die Installation der Datenbanktabellen durch
     */
    public static function install() {
        global $wpdb;
        
        $table_cookies = $wpdb->prefix . 'cookie_scanner_cookies';
        $table_settings = $wpdb->prefix . 'cookie_scanner_settings';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Cookie-Tabelle
        $sql_cookies = "CREATE TABLE IF NOT EXISTS $table_cookies (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            provider varchar(255),
            duration varchar(255),
            domain varchar(255),
            is_third_party tinyint(1) DEFAULT 0,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Einstellungen-Tabelle
        $sql_settings = "CREATE TABLE IF NOT EXISTS $table_settings (
            setting_key varchar(255) NOT NULL,
            setting_value longtext,
            PRIMARY KEY  (setting_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_cookies);
        dbDelta($sql_settings);
        
        // Standard-Kategorien einfügen
        self::insert_default_categories();
        
        // Version in den Optionen speichern
        update_option('cookie_scanner_db_version', '1.0.0');
        
        // Tabelle aktualisieren
        self::update_tables();
    }
    
    /**
     * Fügt Standard-Kategorien ein
     */
    private static function insert_default_categories() {
        global $wpdb;
        $table_categories = $wpdb->prefix . 'cookie_scanner_categories';
        
        $default_categories = array(
            array(
                'name' => 'Notwendige Cookies',
                'description' => 'Diese Cookies sind für die grundlegende Funktionalität der Website erforderlich.',
                'is_necessary' => 1
            ),
            array(
                'name' => 'Analyse-Cookies',
                'description' => 'Diese Cookies helfen uns, die Nutzung der Website zu verstehen und zu verbessern.',
                'is_necessary' => 0
            ),
            array(
                'name' => 'Marketing-Cookies',
                'description' => 'Diese Cookies werden verwendet, um Werbung für Sie relevanter zu machen.',
                'is_necessary' => 0
            ),
            array(
                'name' => 'Funktionale Cookies',
                'description' => 'Diese Cookies ermöglichen erweiterte Funktionalitäten und Personalisierung.',
                'is_necessary' => 0
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
    
    /**
     * Aktualisiert die Datenbanktabellen
     */
    private static function update_tables() {
        global $wpdb;
        
        $table_cookies = $wpdb->prefix . 'cookie_scanner_cookies';
        
        // Prüfe ob Spalten existieren
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_cookies");
        
        // Füge is_first_party hinzu, falls nicht vorhanden
        if (!in_array('is_first_party', $columns)) {
            $wpdb->query("ALTER TABLE $table_cookies ADD COLUMN is_first_party tinyint(1) DEFAULT 1");
        }
        
        // Füge found_at hinzu, falls nicht vorhanden
        if (!in_array('found_at', $columns)) {
            $wpdb->query("ALTER TABLE $table_cookies ADD COLUMN found_at datetime DEFAULT CURRENT_TIMESTAMP");
        }
    }
    
    /**
     * Führt die Deinstallation durch
     */
    public static function uninstall() {
        global $wpdb;
        
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cookie_scanner_cookies");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cookie_scanner_settings");
        
        // Optionen löschen
        delete_option('cookie_scanner_db_version');
        delete_option('cookie_scanner_settings');
    }
} 