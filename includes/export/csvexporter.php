<?php
namespace CookieScanner\Export;

class CSVExporter {
    public function export() {
        global $wpdb;
        
        // Cookies aus der Datenbank abrufen
        $cookies = $wpdb->get_results("
            SELECT c.*, cat.name as category_name 
            FROM {$wpdb->prefix}cookie_scanner_cookies c
            LEFT JOIN {$wpdb->prefix}cookie_scanner_categories cat 
                ON c.category_id = cat.id
            ORDER BY c.domain, c.name
        ");
        
        if (empty($cookies)) {
            return false;
        }
        
        // Temporäre Datei erstellen
        $upload_dir = wp_upload_dir();
        $filename = 'cookie-export-' . date('Y-m-d-H-i-s') . '.csv';
        $filepath = $upload_dir['path'] . '/' . $filename;
        
        // CSV Header
        $headers = array(
            'Name',
            'Domain',
            'Kategorie',
            'Speicherdauer',
            'Beschreibung',
            'First-Party',
            'Gefunden am'
        );
        
        // Datei öffnen und Header schreiben
        $fp = fopen($filepath, 'w');
        
        // BOM für Excel UTF-8 Kompatibilität
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header schreiben
        fputcsv($fp, $headers, ';');
        
        // Cookie-Daten schreiben
        foreach ($cookies as $cookie) {
            $row = array(
                $cookie->name,
                $cookie->domain,
                $cookie->category_name,
                $cookie->duration,
                $cookie->description,
                $cookie->is_first_party ? 'Ja' : 'Nein',
                $cookie->found_at
            );
            fputcsv($fp, $row, ';');
        }
        
        fclose($fp);
        
        // Header setzen für Download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Datei ausgeben und beenden
        readfile($filepath);
        unlink($filepath);
        exit;
    }
} 