<?php
namespace CookieScanner\Scanner;

use CookieScanner\Database\Database;

class CookieAnalyzer {
    private $db;
    private $risk_levels = array(
        'low' => 1,
        'medium' => 2,
        'high' => 3
    );

    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Analysiert einen Cookie und gibt detaillierte Informationen zurück
     */
    public function analyze_cookie($cookie_data) {
        $analysis = array(
            'risk_level' => $this->calculate_risk_level($cookie_data),
            'compliance_issues' => $this->check_compliance($cookie_data),
            'recommendations' => $this->generate_recommendations($cookie_data),
            'technical_details' => $this->get_technical_details($cookie_data)
        );

        return $analysis;
    }

    /**
     * Berechnet das Risikoniveau eines Cookies
     */
    private function calculate_risk_level($cookie_data) {
        $risk_score = 0;

        // Drittanbieter-Cookies haben ein höheres Risiko
        if (!empty($cookie_data['is_third_party'])) {
            $risk_score += 2;
        }

        // Lange Speicherdauer erhöht das Risiko
        if (strpos(strtolower($cookie_data['duration']), 'jahr') !== false) {
            $risk_score += 2;
        } elseif (strpos(strtolower($cookie_data['duration']), 'monat') !== false) {
            $risk_score += 1;
        }

        // Tracking-Cookies haben ein höheres Risiko
        if (strpos(strtolower($cookie_data['category']), 'tracking') !== false ||
            strpos(strtolower($cookie_data['category']), 'marketing') !== false) {
            $risk_score += 2;
        }

        // Bestimme das Risikoniveau basierend auf dem Score
        if ($risk_score >= 4) {
            return 'high';
        } elseif ($risk_score >= 2) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Prüft die DSGVO-Konformität
     */
    private function check_compliance($cookie_data) {
        $issues = array();

        // Prüfe, ob notwendige Informationen vorhanden sind
        if (empty($cookie_data['description'])) {
            $issues[] = 'Keine Beschreibung des Cookie-Zwecks vorhanden';
        }

        if (empty($cookie_data['duration'])) {
            $issues[] = 'Keine Angabe zur Speicherdauer vorhanden';
        }

        if (empty($cookie_data['provider'])) {
            $issues[] = 'Kein Anbieter angegeben';
        }

        // Prüfe Drittanbieter-Cookies
        if (!empty($cookie_data['is_third_party'])) {
            $issues[] = 'Drittanbieter-Cookie erfordert explizite Einwilligung';
        }

        return $issues;
    }

    /**
     * Generiert Empfehlungen basierend auf der Analyse
     */
    private function generate_recommendations($cookie_data) {
        $recommendations = array();

        // Empfehlungen basierend auf dem Risikoniveau
        $risk_level = $this->calculate_risk_level($cookie_data);
        
        if ($risk_level === 'high') {
            $recommendations[] = 'Cookie sollte nur nach expliziter Einwilligung gesetzt werden';
            $recommendations[] = 'Regelmäßige Überprüfung der Notwendigkeit empfohlen';
        }

        // Empfehlungen für Drittanbieter-Cookies
        if (!empty($cookie_data['is_third_party'])) {
            $recommendations[] = 'Vertragliche Vereinbarungen mit dem Drittanbieter prüfen';
            $recommendations[] = 'Datenschutzerklärung des Drittanbieters verlinken';
        }

        // Empfehlungen zur Speicherdauer
        if (strpos(strtolower($cookie_data['duration']), 'jahr') !== false) {
            $recommendations[] = 'Kürzere Speicherdauer in Betracht ziehen';
        }

        return $recommendations;
    }

    /**
     * Sammelt technische Details über den Cookie
     */
    private function get_technical_details($cookie_data) {
        return array(
            'name' => $cookie_data['name'],
            'domain' => $cookie_data['domain'],
            'path' => '/',
            'secure' => true,
            'httpOnly' => true,
            'sameSite' => 'Strict'
        );
    }

    /**
     * Analysiert alle Cookies in der Datenbank
     */
    public function analyze_all_cookies() {
        $cookies = $this->db->get_all_cookies();
        $analysis_results = array();

        foreach ($cookies as $cookie) {
            $analysis_results[$cookie->id] = $this->analyze_cookie($cookie);
        }

        return $analysis_results;
    }

    /**
     * Generiert einen Compliance-Bericht
     */
    public function generate_compliance_report() {
        $cookies = $this->db->get_all_cookies();
        $report = array(
            'total_cookies' => count($cookies),
            'risk_distribution' => array(
                'low' => 0,
                'medium' => 0,
                'high' => 0
            ),
            'compliance_issues' => array(),
            'recommendations' => array()
        );

        foreach ($cookies as $cookie) {
            $analysis = $this->analyze_cookie($cookie);
            
            // Zähle Risikoniveaus
            $report['risk_distribution'][$analysis['risk_level']]++;
            
            // Sammle Compliance-Probleme
            $report['compliance_issues'] = array_merge(
                $report['compliance_issues'],
                $analysis['compliance_issues']
            );
            
            // Sammle Empfehlungen
            $report['recommendations'] = array_merge(
                $report['recommendations'],
                $analysis['recommendations']
            );
        }

        // Entferne Duplikate
        $report['compliance_issues'] = array_unique($report['compliance_issues']);
        $report['recommendations'] = array_unique($report['recommendations']);

        return $report;
    }
} 