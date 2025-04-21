<?php
namespace CookieScanner;

class GeoLocation {
    private $ip;
    private $country_code;
    private $country_name;
    private $eu_countries = array(
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'
    );
    
    private $gdpr_countries = array(
        // EU-Länder werden automatisch hinzugefügt
        'GB', // Großbritannien (UK GDPR)
        'NO', // Norwegen (EWR)
        'IS', // Island (EWR)
        'LI', // Liechtenstein (EWR)
        'CH', // Schweiz (ähnliche Datenschutzgesetze)
        'US', // USA (CCPA)
        'IL', // Israel (Privacy Protection Law)
        'AE', // VAE (Data Protection Law)
        'JP', // Japan (APPI)
        'CA'  // Kanada (PIPEDA)
    );

    public function __construct() {
        $this->ip = $this->get_client_ip();
        $this->detect_location();
    }

    /**
     * Ermittelt die IP-Adresse des Besuchers
     */
    private function get_client_ip() {
        $ip_headers = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );

        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip_array = explode(',', $_SERVER[$header]);
                return trim($ip_array[0]);
            }
        }

        return '127.0.0.1';
    }

    /**
     * Ermittelt den Standort anhand der IP-Adresse
     */
    private function detect_location() {
        // Prüfen, ob die Daten bereits im Cache sind
        $cached_location = get_transient('cookie_scanner_geo_' . md5($this->ip));
        
        if ($cached_location !== false) {
            $this->country_code = $cached_location['country_code'];
            $this->country_name = $cached_location['country_name'];
            return;
        }

        // MaxMind GeoIP2 Integration (wenn verfügbar)
        if (class_exists('\GeoIp2\Database\Reader') && file_exists(COOKIE_SCANNER_PLUGIN_DIR . 'data/GeoLite2-Country.mmdb')) {
            try {
                $reader = new \GeoIp2\Database\Reader(COOKIE_SCANNER_PLUGIN_DIR . 'data/GeoLite2-Country.mmdb');
                $record = $reader->country($this->ip);
                $this->country_code = $record->country->isoCode;
                $this->country_name = $record->country->name;
            } catch (\Exception $e) {
                // Fallback zur IP-API
                $this->use_ip_api();
            }
        } else {
            // Fallback zur IP-API
            $this->use_ip_api();
        }

        // Speichern der Daten im Cache (24 Stunden)
        set_transient('cookie_scanner_geo_' . md5($this->ip), array(
            'country_code' => $this->country_code,
            'country_name' => $this->country_name
        ), DAY_IN_SECONDS);
    }

    /**
     * Verwendet die kostenlose IP-API als Fallback
     */
    private function use_ip_api() {
        $response = wp_remote_get('http://ip-api.com/json/' . $this->ip . '?fields=countryCode,country');
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if ($data && isset($data['countryCode'])) {
                $this->country_code = $data['countryCode'];
                $this->country_name = $data['country'];
            }
        }
    }

    /**
     * Prüft, ob der Besucher aus der EU kommt
     */
    public function is_eu() {
        return in_array($this->country_code, $this->eu_countries);
    }

    /**
     * Prüft, ob der Besucher aus einem Land mit DSGVO-ähnlichen Gesetzen kommt
     */
    public function is_gdpr_country() {
        return $this->is_eu() || in_array($this->country_code, $this->gdpr_countries);
    }

    /**
     * Gibt den Ländercode zurück
     */
    public function get_country_code() {
        return $this->country_code;
    }

    /**
     * Gibt den Ländernamen zurück
     */
    public function get_country_name() {
        return $this->country_name;
    }

    /**
     * Gibt die IP-Adresse zurück
     */
    public function get_ip() {
        return $this->ip;
    }

    /**
     * Bestimmt die anzuwendenden Datenschutzrichtlinien
     */
    public function get_applicable_regulations() {
        $regulations = array();

        // DSGVO (EU)
        if ($this->is_eu()) {
            $regulations[] = 'gdpr';
        }

        // UK GDPR
        if ($this->country_code === 'GB') {
            $regulations[] = 'uk_gdpr';
        }

        // LGPD (Brasilien)
        if ($this->country_code === 'BR') {
            $regulations[] = 'lgpd';
        }

        // CCPA (Kalifornien, USA)
        if ($this->country_code === 'US') {
            $regulations[] = 'ccpa';
        }

        // PIPEDA (Kanada)
        if ($this->country_code === 'CA') {
            $regulations[] = 'pipeda';
        }

        // PDPA (Singapur)
        if ($this->country_code === 'SG') {
            $regulations[] = 'pdpa';
        }

        // Privacy Protection Law (Israel)
        if ($this->country_code === 'IL') {
            $regulations[] = 'israel_privacy';
        }

        // Data Protection Law (VAE)
        if ($this->country_code === 'AE') {
            $regulations[] = 'uae_privacy';
        }

        // APPI (Japan)
        if ($this->country_code === 'JP') {
            $regulations[] = 'appi';
        }

        return $regulations;
    }
} 