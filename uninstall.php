<?php
// Wenn WordPress dies nicht direkt aufruft, abbrechen
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Datenbank-Tabellen löschen
global $wpdb;
$tables = array(
    $wpdb->prefix . 'cookie_scanner_cookies',
    $wpdb->prefix . 'cookie_scanner_settings'
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// Optionen löschen
$options = array(
    'cookie_scanner_version',
    'cookie_scanner_settings',
    'cookie_scanner_last_scan',
    'cookie_scanner_scan_results'
);

foreach ($options as $option) {
    delete_option($option);
}

// Transients löschen
delete_transient('cookie_scanner_scan_results');
delete_transient('cookie_scanner_geo_location');

// Benutzer-Meta löschen
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'cookie_scanner_%'");

// Cache leeren
wp_cache_flush(); 