(function($) {
    'use strict';

    // Namespace für den Cookie-Manager
    window.CookieScanner = window.CookieScanner || {};
    window.CookieScanner.Manager = window.CookieScanner.Manager || {};

    // Cookie-Manager-Klasse
    class CookieManager {
        constructor() {
            try {
                // Überprüfen, ob die notwendigen DOM-Elemente existieren
                this.cookieManager = $('.cookie-manager');
                if (this.cookieManager.length === 0) {
                    console.log('Cookie-Manager: Keine .cookie-manager Elemente gefunden');
                    return; // Beende die Initialisierung, wenn keine Elemente gefunden wurden
                }

                this.overlay = null;
                
                // Überprüfen, ob cookieManagerData definiert ist
                if (typeof cookieManagerData === 'undefined') {
                    console.error('Cookie-Manager: cookieManagerData ist nicht definiert');
                    return;
                }
                
                this.translations = cookieManagerData.translations || {};
                this.geo = cookieManagerData.geo || {};
                
                this.init();
                this.initializeRequiredCookies();
            } catch (error) {
                console.error('Cookie-Manager Initialisierungsfehler:', error);
            }
        }

        initializeRequiredCookies() {
            try {
                // Setze notwendige Cookies standardmäßig auf aktiviert
                this.cookieManager.find('input[type="checkbox"][data-required="true"]').each((i, el) => {
                    const checkbox = $(el);
                    checkbox.prop('checked', true);
                    checkbox.prop('disabled', true);
                    checkbox.closest('.cookie-category').addClass('required-category');
                });
            } catch (error) {
                console.error('Fehler beim Initialisieren der notwendigen Cookies:', error);
            }
        }

        init() {
            try {
                this.setupEventListeners();
                this.checkCookieConsent();
                this.setupModal();
            } catch (error) {
                console.error('Fehler bei der Initialisierung:', error);
            }
        }

        setupEventListeners() {
            try {
                // Button-Events
                this.cookieManager.on('click', '.accept-all', () => this.handleAcceptAll());
                this.cookieManager.on('click', '.reject-all', () => this.handleRejectAll());

                // Schieberegler-Events
                this.cookieManager.on('change', '.switch input', (e) => {
                    const checkbox = $(e.target);
                    if (!checkbox.is('[data-required="true"]')) {
                        this.updateCategoryState(checkbox);
                    } else {
                        // Verhindere das Deaktivieren notwendiger Cookies
                        checkbox.prop('checked', true);
                    }
                });
            } catch (error) {
                console.error('Fehler beim Einrichten der Event-Listener:', error);
            }
        }

        setupModal() {
            try {
                if (this.cookieManager.hasClass('cookie-manager-modal')) {
                    this.overlay = $('<div class="cookie-manager-overlay"></div>');
                    $('body').append(this.overlay);
                }
            } catch (error) {
                console.error('Fehler beim Einrichten des Modals:', error);
            }
        }

        checkCookieConsent() {
            try {
                const consent = this.getCookie('cookie_consent');
                if (!consent) {
                    this.showCookieManager();
                } else {
                    try {
                        const preferences = JSON.parse(consent);
                        // Prüfe ob die Einstellungen gültig sind
                        if (preferences && Array.isArray(preferences.accepted)) {
                            this.applyCookieSettings(preferences);
                        } else {
                            this.showCookieManager();
                        }
                    } catch (e) {
                        console.error('Fehler beim Parsen der Cookie-Einstellungen:', e);
                        this.showCookieManager();
                    }
                }
            } catch (error) {
                console.error('Fehler beim Überprüfen der Cookie-Einwilligung:', error);
                this.showCookieManager();
            }
        }

        showCookieManager() {
            try {
                // Prüfe ob der Layer bereits angezeigt wird
                if (this.cookieManager.is(':visible')) {
                    return;
                }
                this.cookieManager.show();
                if (this.overlay) {
                    this.overlay.show();
                }
                $('body').addClass('cookie-manager-active');
            } catch (error) {
                console.error('Fehler beim Anzeigen des Cookie-Managers:', error);
            }
        }

        hideCookieManager() {
            try {
                this.cookieManager.hide();
                if (this.overlay) {
                    this.overlay.hide();
                }
                $('body').removeClass('cookie-manager-active');
            } catch (error) {
                console.error('Fehler beim Ausblenden des Cookie-Managers:', error);
            }
        }

        handleAcceptAll() {
            try {
                // Alle Kategorien aktivieren
                this.cookieManager.find('input[type="checkbox"]').each((i, el) => {
                    const checkbox = $(el);
                    if (!checkbox.is('[data-required="true"]')) {
                        checkbox.prop('checked', true);
                    }
                });
                this.savePreferences();
                this.hideCookieManager();
            } catch (error) {
                console.error('Fehler beim Akzeptieren aller Cookies:', error);
            }
        }

        handleRejectAll() {
            try {
                // Nur nicht-notwendige Kategorien deaktivieren
                this.cookieManager.find('input[type="checkbox"]').each((i, el) => {
                    const checkbox = $(el);
                    if (!checkbox.is('[data-required="true"]')) {
                        checkbox.prop('checked', false);
                    }
                });
                this.savePreferences();
                this.hideCookieManager();
            } catch (error) {
                console.error('Fehler beim Ablehnen aller Cookies:', error);
            }
        }

        savePreferences() {
            try {
                const preferences = this.getPreferences();
                this.sendPreferences(preferences);
                
                // Speichere die Einstellungen im Cookie
                this.setCookie('cookie_consent', JSON.stringify(preferences), 365);
            } catch (error) {
                console.error('Fehler beim Speichern der Präferenzen:', error);
            }
        }

        sendPreferences(preferences) {
            try {
                $.ajax({
                    url: cookieManagerData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cookie_scanner_save_preferences',
                        nonce: cookieManagerData.nonce,
                        categories: preferences.accepted
                    },
                    success: (response) => {
                        if (response.success) {
                            this.applyCookieSettings(preferences);
                            this.triggerSaveEvent(preferences);
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('AJAX-Fehler beim Speichern der Präferenzen:', status, error);
                    }
                });
            } catch (error) {
                console.error('Fehler beim Senden der Präferenzen:', error);
            }
        }

        getPreferences() {
            try {
                const preferences = {
                    accepted: [],
                    rejected: [],
                    timestamp: new Date().toISOString(),
                    country: this.geo.country || '',
                    regulations: this.geo.regulations || []
                };

                // Sortiere die Kategorien: Notwendige zuerst, dann Funktionale
                const switches = this.cookieManager.find('.switch input').get();
                switches.sort((a, b) => {
                    const aRequired = $(a).is('[data-required="true"]');
                    const bRequired = $(b).is('[data-required="true"]');
                    if (aRequired && !bRequired) return -1;
                    if (!aRequired && bRequired) return 1;
                    return 0;
                });

                switches.forEach(el => {
                    const checkbox = $(el);
                    const categoryId = checkbox.val();
                    if (checkbox.is(':checked')) {
                        preferences.accepted.push(categoryId);
                    } else if (!checkbox.is('[data-required="true"]')) {
                        preferences.rejected.push(categoryId);
                    }
                });

                return preferences;
            } catch (error) {
                console.error('Fehler beim Abrufen der Präferenzen:', error);
                return { accepted: [], rejected: [], timestamp: new Date().toISOString() };
            }
        }

        applyCookieSettings(preferences) {
            try {
                // Cookies löschen für abgelehnte Kategorien
                preferences.rejected.forEach(categoryId => {
                    this.deleteCookiesForCategory(categoryId);
                });

                // Scripts blockieren/aktivieren
                this.updateScriptBlocking(preferences);
            } catch (error) {
                console.error('Fehler beim Anwenden der Cookie-Einstellungen:', error);
            }
        }

        updateCategoryState(checkbox) {
            try {
                const categoryId = checkbox.val();
                const isChecked = checkbox.is(':checked');

                // Aktualisiere abhängige Cookies und Scripts
                if (!isChecked) {
                    this.deleteCookiesForCategory(categoryId);
                }
            } catch (error) {
                console.error('Fehler beim Aktualisieren des Kategorie-Status:', error);
            }
        }

        deleteCookiesForCategory(categoryId) {
            try {
                $.ajax({
                    url: cookieManagerData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cookie_scanner_delete_cookies',
                        category_id: categoryId,
                        nonce: cookieManagerData.nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            console.log(`Cookies für Kategorie ${categoryId} wurden gelöscht`);
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('AJAX-Fehler beim Löschen der Cookies:', status, error);
                    }
                });
            } catch (error) {
                console.error('Fehler beim Löschen der Cookies für Kategorie:', error);
            }
        }

        updateScriptBlocking(preferences) {
            try {
                $.ajax({
                    url: cookieManagerData.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'cookie_scanner_update_script_blocking',
                        preferences: preferences,
                        nonce: cookieManagerData.nonce
                    },
                    success: (response) => {
                        if (response.success) {
                            // Seite nur neu laden, wenn es explizit angefordert wird
                            // und wir nicht bereits im Reload-Prozess sind
                            if (response.data.reload && !sessionStorage.getItem('cookieManagerReloading')) {
                                sessionStorage.setItem('cookieManagerReloading', 'true');
                                window.location.reload();
                            }
                        }
                    },
                    error: (xhr, status, error) => {
                        console.error('AJAX-Fehler beim Aktualisieren der Script-Blockierung:', status, error);
                    }
                });
            } catch (error) {
                console.error('Fehler beim Aktualisieren der Script-Blockierung:', error);
            }
        }

        triggerSaveEvent(preferences) {
            try {
                // Event für externe Skripte auslösen
                const event = new CustomEvent('cookiePreferencesSaved', {
                    detail: preferences
                });
                document.dispatchEvent(event);
                
                // Reload-Flag zurücksetzen
                sessionStorage.removeItem('cookieManagerReloading');
            } catch (error) {
                console.error('Fehler beim Auslösen des Save-Events:', error);
            }
        }

        // Cookie-Hilfsfunktionen
        setCookie(name, value, days) {
            try {
                const date = new Date();
                date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
                const expires = `expires=${date.toUTCString()}`;
                document.cookie = `${name}=${value};${expires};path=/;SameSite=Strict`;
            } catch (error) {
                console.error('Fehler beim Setzen des Cookies:', error);
            }
        }

        getCookie(name) {
            try {
                const nameEQ = `${name}=`;
                const ca = document.cookie.split(';');
                for (let i = 0; i < ca.length; i++) {
                    let c = ca[i];
                    while (c.charAt(0) === ' ') {
                        c = c.substring(1, c.length);
                    }
                    if (c.indexOf(nameEQ) === 0) {
                        return c.substring(nameEQ.length, c.length);
                    }
                }
                return null;
            } catch (error) {
                console.error('Fehler beim Abrufen des Cookies:', error);
                return null;
            }
        }

        deleteCookie(name) {
            try {
                document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/`;
            } catch (error) {
                console.error('Fehler beim Löschen des Cookies:', error);
            }
        }
    }

    // Initialisierung
    $(document).ready(() => {
        try {
            // Überprüfen, ob wir im Divi Builder sind
            const isDiviBuilder = typeof window.ETBuilderBackend !== 'undefined';
            
            // Nur initialisieren, wenn wir nicht im Divi Builder sind oder wenn der Cookie-Manager explizit angefordert wurde
            if (!isDiviBuilder || (isDiviBuilder && $('.cookie-manager').length > 0)) {
                window.CookieScanner.Manager.instance = new CookieManager();
            }
        } catch (error) {
            console.error('Fehler bei der Initialisierung des Cookie-Managers:', error);
        }
    });

})(jQuery); 