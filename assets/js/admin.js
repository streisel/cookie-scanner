jQuery(document).ready(function($) {
    'use strict';

    // Cookie-Scan-Button
    $('#cookie-scanner-scan').on('click', function(e) {
        e.preventDefault();
        const button = $(this);
        const scanningText = cookieScannerAdmin.i18n?.scanning || 'Scanne...';
        const scanText = cookieScannerAdmin.i18n?.scan || 'Website scannen';
        button.prop('disabled', true).text(scanningText);

        $.ajax({
            url: cookieScannerAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'cookie_scanner_scan',
                nonce: cookieScannerAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', cookieScannerAdmin.i18n.scanComplete);
                    setTimeout(function() {
                        window.location.reload();
                    }, 5000);
                } else {
                    showNotice('error', response.data.message || 'Ein Fehler ist aufgetreten');
                    button.prop('disabled', false).text(scanText);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                showNotice('error', 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.');
                button.prop('disabled', false).text(scanText);
            }
        });
    });

    // Cookie-Export-Button
    $('#cookie-scanner-export').on('click', function(e) {
        e.preventDefault();
        const button = $(this);
        const exportText = cookieScannerAdmin.i18n?.exporting || 'Exportiere...';
        const defaultText = cookieScannerAdmin.i18n?.export || 'Exportieren';
        button.prop('disabled', true).text(exportText);

        // Formular erstellen und absenden
        const form = $('<form>', {
            'method': 'POST',
            'action': cookieScannerAdmin.ajaxurl,
            'target': '_blank'
        }).append($('<input>', {
            'type': 'hidden',
            'name': 'action',
            'value': 'cookie_scanner_export'
        })).append($('<input>', {
            'type': 'hidden',
            'name': 'nonce',
            'value': cookieScannerAdmin.nonce
        }));

        $('body').append(form);
        form.submit();
        form.remove();

        // Button-Status zurücksetzen
        setTimeout(function() {
            button.prop('disabled', false).text(defaultText);
        }, 1000);
    });

    // Cookie-Bearbeiten
    $('.edit-cookie').on('click', function(e) {
        e.preventDefault();
        const cookieId = $(this).data('id');
        const row = $(this).closest('tr');
        
        // Formular mit Daten füllen
        $('#cookie-id').val(cookieId);
        $('#cookie-name').val(row.find('td:eq(0)').text());
        $('#cookie-category').val(row.find('td:eq(1)').data('category-id'));
        $('#cookie-duration').val(row.find('td:eq(2)').text());
        $('#cookie-provider').val(row.find('td:eq(3)').text());
        $('#cookie-purpose').val(row.find('td:eq(4)').text());

        // Modal anzeigen
        $('#cookie-edit-modal').show();
    });

    // Cookie-Bearbeiten abbrechen
    $('.cancel-edit').on('click', function(e) {
        e.preventDefault();
        $('#cookie-edit-modal').hide();
    });

    // Cookie-Bearbeiten speichern
    $('#cookie-edit-form').on('submit', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitButton = form.find('button[type="submit"]');
        const savingText = cookieScannerAdmin.i18n?.saving || 'Speichere...';
        const saveText = cookieScannerAdmin.i18n?.save || 'Speichern';
        
        submitButton.prop('disabled', true).text(savingText);

        $.ajax({
            url: cookieScannerAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'cookie_scanner_update',
                nonce: cookieScannerAdmin.nonce,
                cookie_id: $('#cookie-id').val(),
                name: $('#cookie-name').val(),
                category_id: $('#cookie-category').val(),
                duration: $('#cookie-duration').val(),
                provider: $('#cookie-provider').val(),
                purpose: $('#cookie-purpose').val()
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    setTimeout(function() {
                        window.location.reload();
                    }, 2000);
                } else {
                    showNotice('error', response.data.message || 'Speichern fehlgeschlagen');
                    submitButton.prop('disabled', false).text(saveText);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                showNotice('error', 'Speichern fehlgeschlagen. Bitte versuchen Sie es später erneut.');
                submitButton.prop('disabled', false).text(saveText);
            }
        });
    });

    // Cookie löschen
    $('.delete-cookie').on('click', function(e) {
        e.preventDefault();
        const confirmText = cookieScannerAdmin.i18n?.confirmDelete || 'Sind Sie sicher, dass Sie dieses Cookie löschen möchten?';
        if (!confirm(confirmText)) {
            return;
        }

        const button = $(this);
        const cookieId = button.data('id');
        const deletingText = cookieScannerAdmin.i18n?.deleting || 'Lösche...';
        const deleteText = cookieScannerAdmin.i18n?.delete || 'Löschen';

        button.prop('disabled', true).text(deletingText);

        $.ajax({
            url: cookieScannerAdmin.ajaxurl,
            type: 'POST',
            data: {
                action: 'cookie_scanner_delete',
                nonce: cookieScannerAdmin.nonce,
                cookie_id: cookieId
            },
            success: function(response) {
                if (response.success) {
                    button.closest('tr').fadeOut(function() {
                        $(this).remove();
                    });
                    showNotice('success', response.data.message);
                } else {
                    showNotice('error', response.data.message || 'Löschen fehlgeschlagen');
                    button.prop('disabled', false).text(deleteText);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                showNotice('error', 'Löschen fehlgeschlagen. Bitte versuchen Sie es später erneut.');
                button.prop('disabled', false).text(deleteText);
            }
        });
    });

    // Filter und Suche
    $('#cookie-category-filter, #cookie-search').on('change keyup', function() {
        const category = $('#cookie-category-filter').val();
        const search = $('#cookie-search').val().toLowerCase();

        $('.wp-list-table tbody tr').each(function() {
            const row = $(this);
            const rowCategory = row.find('td:eq(1)').data('category-id');
            const rowText = row.text().toLowerCase();

            const categoryMatch = !category || rowCategory === category;
            const searchMatch = !search || rowText.includes(search);

            row.toggle(categoryMatch && searchMatch);
        });
    });

    // Benachrichtigungen anzeigen
    function showNotice(type, message) {
        const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap > h1').after(notice);
        
        setTimeout(function() {
            notice.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    }
}); 