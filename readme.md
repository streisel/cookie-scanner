# Cookie Scanner & Manager für Divi

Ein umfassendes Cookie-Management-Modul für Divi, das DSGVO-konform ist und internationale Datenschutzrichtlinien berücksichtigt.

## Projektstruktur

### Hauptdateien
- `cookie-scanner.php` - Hauptplugin-Datei mit Plugin-Header und grundlegenden Funktionen
- `uninstall.php` - Bereinigung der Datenbank beim Deinstallieren des Plugins

### Divi-Modul
- `includes/modules/CookieManager/CookieManager.php` - Hauptklasse des Divi-Moduls
- `includes/modules/CookieManager/CookieManager.css` - Styling für das Frontend
- `includes/modules/CookieManager/CookieManager.js` - Frontend-Funktionalität

### Backend-Funktionalität
- `includes/admin/class-admin.php` - Backend-Administration und Einstellungen
- `includes/admin/views/settings-page.php` - Template für die Einstellungsseite
- `includes/admin/css/admin.css` - Styling für das Backend
- `includes/admin/js/admin.js` - JavaScript für das Backend

### Cookie-Scanning
- `includes/scanner/class-cookie-scanner.php` - Cookie-Scanning-Funktionalität
- `includes/scanner/class-cookie-analyzer.php` - Analyse der gefundenen Cookies
- `includes/scanner/class-cookie-blocker.php` - Blockierung von Cookies und Skripten

### Internationalisierung
- `languages/` - Verzeichnis für Übersetzungsdateien
- `includes/class-geo-location.php` - Geolocation-Funktionalität
- `includes/class-language-manager.php` - Sprachverwaltung

### Datenbank
- `includes/database/class-database.php` - Datenbankoperationen
- `includes/database/install.php` - Datenbankinstallation und Updates

### Hilfsfunktionen
- `includes/helpers/class-cookie-helper.php` - Allgemeine Cookie-Hilfsfunktionen
- `includes/helpers/class-security.php` - Sicherheitsfunktionen
- `includes/helpers/class-compliance.php` - Compliance-Prüfungen

## Funktionen
- Automatische Cookie-Erkennung und -Kategorisierung
- DSGVO-konforme Cookie-Einwilligung
- Internationale Compliance (EU, USA, Kanada, UK, etc.)
- Geolocation-basierte Anpassung
- Mehrsprachige Unterstützung
- Responsive Design
- Blockierung von nicht-essentiellen Cookies
- Detaillierte Cookie-Analyse
- Anpassbare Benutzeroberfläche
- Performance-Optimierung

## Technische Anforderungen
- WordPress 5.0+
- Divi Theme
- PHP 7.4+
- MySQL 5.6+
