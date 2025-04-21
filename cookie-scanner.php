<?php
/**
 * Plugin Name: Cookie Scanner & Manager für Divi
 * Plugin URI: https://example.com/cookie-scanner
 * Description: Ein umfassendes Cookie-Management-Modul für Divi, das DSGVO-konform ist und internationale Datenschutzrichtlinien berücksichtigt.
 * Version: 1.0.0
 * Author: Hagen Streisel
 * Author URI: https://example.com
 * Text Domain: cookie-scanner
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Wenn dieser Datei direkt aufgerufen wird, abbrechen
if (!defined('ABSPATH')) {
    exit;
}

// Plugin-Konstanten definieren
define('COOKIE_SCANNER_VERSION', '1.0.0');
define('COOKIE_SCANNER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('COOKIE_SCANNER_PLUGIN_URL', plugin_dir_url(__FILE__));
define('COOKIE_SCANNER_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Debug-Logging
function cookie_scanner_log($message) {
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        if (is_array($message) || is_object($message)) {
            error_log('Cookie Scanner Debug: ' . print_r($message, true));
        } else {
            error_log('Cookie Scanner Debug: ' . $message);
        }
    }
}

// Prüfe, ob Divi aktiv ist
function cookie_scanner_is_divi_active() {
    // 2. Gemeinsamer Divi‑Core (Theme ODER Plugin)?
    if(defined('ET_CORE_VERSION'))
        return true;
    // 3. Reines Builder‑Plugin?
    if(defined('ET_BUILDER_PLUGIN_ACTIVE')||class_exists('ET_Builder_Plugin'))
        return true;

    $theme = wp_get_theme();
    if(( 'Divi' == $theme->name || 'Divi' == $theme->parent_theme )||in_array($theme->get('Template'),['Divi','Extra'],true))
        return true;

    return false; // Nope, kein Divi im Spiel
}

// Autoloader für Klassen mit verbessertem Logging
spl_autoload_register(function ($class) {
    // Prüfen, ob die Klasse zum Plugin-Namespace gehört
    if (strpos($class, 'CookieScanner\\') !== 0) {
        return;
    }

    // Debug-Ausgabe
    cookie_scanner_log('Versuche Klasse zu laden: ' . $class);

    // Namespace in Dateipfad umwandeln
    $class_path = str_replace('CookieScanner\\', '', $class);
    $class_path = str_replace('\\', DIRECTORY_SEPARATOR, $class_path);
    
    // Versuche beide Schreibweisen - mit und ohne kleinschreibung
    $files_to_try = array(
        COOKIE_SCANNER_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . $class_path . '.php',
        COOKIE_SCANNER_PLUGIN_DIR . 'includes' . DIRECTORY_SEPARATOR . strtolower($class_path) . '.php'
    );
    
    foreach ($files_to_try as $file) {
        cookie_scanner_log('Suche Datei: ' . $file);
        
        if (file_exists($file)) {
            cookie_scanner_log('Datei gefunden, lade: ' . $file);
            require_once $file;
            return;
        }
    }
    
    cookie_scanner_log('Datei nicht gefunden für: ' . $class);
});

// Plugin aktivieren
register_activation_hook(__FILE__, function() {
    require_once COOKIE_SCANNER_PLUGIN_DIR . 'includes/database/class-install.php';
    if (class_exists('CookieScanner\Database\Install')) {
        CookieScanner\Database\Install::install();
    } else {
        cookie_scanner_log('Installationsklasse konnte nicht gefunden werden.');
    }
});

// Plugin deaktivieren
register_deactivation_hook(__FILE__, function() {
    require_once COOKIE_SCANNER_PLUGIN_DIR . 'includes/database/class-install.php';
    if (class_exists('CookieScanner\Database\Install')) {
        CookieScanner\Database\Install::uninstall();
    } else {
        cookie_scanner_log('Installationsklasse konnte nicht gefunden werden.');
    }
});

// Admin-Hinweis, wenn Divi nicht aktiviert ist
add_action('admin_notices', function() {
    if (!cookie_scanner_is_divi_active()) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php _e('Cookie Scanner & Manager benötigt das Divi Theme oder den Divi Builder, um vollständig zu funktionieren.', 'cookie-scanner'); ?></p>
        </div>
        <?php
    }
});

// Plugin initialisieren
add_action('plugins_loaded', function() {
    // Internationalisierung laden
    load_plugin_textdomain('cookie-scanner', false, dirname(COOKIE_SCANNER_PLUGIN_BASENAME) . '/languages');

    // Admin-Bereich initialisieren
    if (is_admin()) {
        $admin_file = COOKIE_SCANNER_PLUGIN_DIR . 'includes/admin/class-admin.php';
        if (file_exists($admin_file)) {
            require_once $admin_file;
            if (class_exists('CookieScanner\Admin\Admin')) {
                new CookieScanner\Admin\Admin();
            } else {
                cookie_scanner_log('Admin-Klasse konnte nicht gefunden werden.');
            }
        } else {
            cookie_scanner_log('Admin-Datei konnte nicht gefunden werden: ' . $admin_file);
        }
    }

    // Scanner initialisieren
    $scanner_file = COOKIE_SCANNER_PLUGIN_DIR . 'includes/scanner/class-cookie-scanner.php';
    if (file_exists($scanner_file)) {
        require_once $scanner_file;
        if (class_exists('CookieScanner\Scanner\CookieScanner')) {
            new CookieScanner\Scanner\CookieScanner();
        } else {
            cookie_scanner_log('Scanner-Klasse konnte nicht gefunden werden.');
        }
    } else {
        cookie_scanner_log('Scanner-Datei konnte nicht gefunden werden: ' . $scanner_file);
    }
    
    // Cookie Manager initialisieren - Hier mit variabler Dateinamen-Unterstützung
    $possible_paths = array(
        COOKIE_SCANNER_PLUGIN_DIR . 'includes/modules/cookiemanager/cookiemanager.php',
        COOKIE_SCANNER_PLUGIN_DIR . 'includes/modules/CookieManager/CookieManager.php'
    );
    
    $cookie_manager_file = null;
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $cookie_manager_file = $path;
            break;
        }
    }
    
    if ($cookie_manager_file) {
        cookie_scanner_log('Cookie Manager Datei gefunden: ' . $cookie_manager_file);
        require_once $cookie_manager_file;
        
        if (class_exists('CookieScanner\Modules\CookieManager\CookieManager')) {
            $cookie_manager = new CookieScanner\Modules\CookieManager\CookieManager();
            
            // Shortcode für den Cookie Manager registrieren - nur in der Hauptdatei
            add_shortcode('cookie_manager', array($cookie_manager, 'render_shortcode'));
            
            // Test-Shortcode für Debugging
            add_shortcode('cookie_test', function() {
                return '<div style="border: 2px solid red; padding: 10px;">Cookie Test Shortcode funktioniert!</div>';
            });
        } else {
            cookie_scanner_log('CookieManager-Klasse konnte nicht gefunden werden.');
        }
    } else {
        cookie_scanner_log('CookieManager-Datei konnte nicht gefunden werden. Gesucht in: ' . implode(', ', $possible_paths));
    }

    // Cookie Blocker initialisieren - NACH dem Cookie Manager
    $blocker_file = COOKIE_SCANNER_PLUGIN_DIR . 'includes/scanner/class-cookie-blocker.php';
    if (file_exists($blocker_file)) {
        require_once $blocker_file;
        if (class_exists('CookieScanner\Scanner\CookieBlocker')) {
            new CookieScanner\Scanner\CookieBlocker();
        } else {
            cookie_scanner_log('CookieBlocker-Klasse konnte nicht gefunden werden.');
        }
    } else {
        cookie_scanner_log('CookieBlocker-Datei konnte nicht gefunden werden: ' . $blocker_file);
    }
    
    // Divi-Hooks debuggen
    if (cookie_scanner_is_divi_active()) {
        
        add_action('wp_footer', function() {
            if (class_exists('ET_Builder_Element')) {
                $modules = ET_Builder_Element::get_modules();
                cookie_scanner_log('Registrierte Divi-Module: ' . implode(', ', array_keys($modules)));
            }
        });
    } else {
        cookie_scanner_log('Divi ist NICHT aktiv.');
    }
}, 10); // Priorität 10 ist Standard, explizit gesetzt für Klarheit