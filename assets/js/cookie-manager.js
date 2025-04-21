jQuery(document).ready(function($) {
    'use strict';
    
    // Namespace für den Cookie-Manager
    window.CookieScanner = window.CookieScanner || {};
    window.CookieScanner.Legacy = window.CookieScanner.Legacy || {};
    
    // Überprüfen, ob wir im Divi Builder sind
    const isDiviBuilder = typeof window.ETBuilderBackend !== 'undefined';
    
    // Nur initialisieren, wenn wir nicht im Divi Builder sind oder wenn der Cookie-Manager explizit angefordert wurde
    if (!isDiviBuilder || (isDiviBuilder && $('.cookie-manager').length > 0)) {
        try {
            const cookieManager = $(".cookie-manager");
            
            // Beende die Initialisierung, wenn keine Elemente gefunden wurden
            if (cookieManager.length === 0) {
                console.log('Cookie-Manager (Legacy): Keine .cookie-manager Elemente gefunden');
                return;
            }
            
            // Initialisiere notwendige Cookies
            cookieManager.find(".switch input[data-required=true]").each(function() {
                try {
                    $(this).prop("checked", true);
                    $(this).prop("disabled", true);
                } catch (error) {
                    console.error('Fehler beim Initialisieren der notwendigen Cookies:', error);
                }
            });
            
            // Button-Events
            cookieManager.on("click", ".accept-all", function() {
                try {
                    cookieManager.find(".switch input").prop("checked", true);
                    savePreferences();
                } catch (error) {
                    console.error('Fehler beim Akzeptieren aller Cookies:', error);
                }
            });
            
            cookieManager.on("click", ".reject-all", function() {
                try {
                    cookieManager.find(".switch input:not([data-required=true])").prop("checked", false);
                    savePreferences();
                } catch (error) {
                    console.error('Fehler beim Ablehnen aller Cookies:', error);
                }
            });
            
            // Schieberegler-Events
            cookieManager.on("change", ".switch input", function(e) {
                try {
                    if ($(this).is("[data-required=true]")) {
                        $(this).prop("checked", true);
                    }
                } catch (error) {
                    console.error('Fehler beim Ändern des Schiebereglers:', error);
                }
            });
            
            function savePreferences() {
                try {
                    // Stelle sicher, dass notwendige Cookies aktiviert sind
                    cookieManager.find(".switch input[data-required=true]").prop("checked", true);
                    
                    const preferences = {
                        accepted: [],
                        rejected: [],
                        timestamp: new Date().toISOString()
                    };
                    
                    cookieManager.find(".switch input").each(function() {
                        const categoryId = $(this).val();
                        if ($(this).is(":checked")) {
                            preferences.accepted.push(categoryId);
                        } else if (!$(this).is("[data-required=true]")) {
                            preferences.rejected.push(categoryId);
                        }
                    });
                    
                    // Cookie setzen
                    document.cookie = "cookie_consent=" + JSON.stringify(preferences) + ";path=/;max-age=31536000";
                    
                    // Cookie-Manager ausblenden
                    cookieManager.hide();
                    $(".cookie-manager-overlay").hide();
                    $("body").removeClass("cookie-manager-active");
                } catch (error) {
                    console.error('Fehler beim Speichern der Präferenzen:', error);
                }
            }
            
            // Prüfe Cookie-Consent
            try {
                const consent = document.cookie.split(";").find(c => c.trim().startsWith("cookie_consent="));
                if (!consent) {
                    cookieManager.show();
                    if (cookieManager.hasClass("cookie-manager-modal")) {
                        $("body").append("<div class=\"cookie-manager-overlay\"></div>");
                        $(".cookie-manager-overlay").show();
                        $("body").addClass("cookie-manager-active");
                    }
                }
            } catch (error) {
                console.error('Fehler beim Überprüfen der Cookie-Einwilligung:', error);
            }
            
            // Speichere die Instanz im Namespace
            window.CookieScanner.Legacy.instance = {
                cookieManager: cookieManager,
                savePreferences: savePreferences
            };
        } catch (error) {
            console.error('Fehler bei der Initialisierung des Cookie-Managers (Legacy):', error);
        }
    }
}); 