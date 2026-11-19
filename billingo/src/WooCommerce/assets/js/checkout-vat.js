/**
 * Billingo: adószám ellenőrzése és (ismert partnernél) a cégadatok kitöltése a klasszikus pénztár oldalon.
 * Az adatokat a szerver adja (admin-ajax), a böngészőbe API-kulcs nem kerül.
 */
(function ($) {
    'use strict';

    if (typeof wc_billingo_vat_lookup === 'undefined') {
        return;
    }

    var cfg = wc_billingo_vat_lookup;
    var timer = null;
    var request = null;
    var lastDigits = '';

    function field() {
        return $('#billing_vat_number');
    }

    function showStatus(text, kind) {
        var $status = $('#wc-billingo-vat-status');

        if (!$status.length) {
            $status = $('<span id="wc-billingo-vat-status" role="status" aria-live="polite" style="display:block;margin-top:4px;"></span>')
                .insertAfter(field());
        }

        $status.text(text || '').css('color', kind === 'invalid' ? '#b32d2e' : (kind === 'valid' ? '#1a7f37' : 'inherit'));
    }

    // Csak az üres mezőket töltjük ki: a vevő által már beírt adatot nem írjuk felül.
    function fillIfEmpty(selector, value) {
        var $el = $(selector);

        if (!$el.length || !value || $.trim($el.val()) !== '') {
            return;
        }

        $el.val(value).trigger('change');
    }

    function fillCompany(company) {
        var $country = $('#billing_country');

        // Más országnál megadott címhez nem töltünk magyar címet.
        if ($country.length && company.country && $country.val() && $country.val() !== company.country) {
            return;
        }

        fillIfEmpty('#billing_company', company.name);
        fillIfEmpty('#billing_postcode', company.postcode);
        fillIfEmpty('#billing_city', company.city);
        fillIfEmpty('#billing_address_1', company.address);

        $(document.body).trigger('update_checkout');
    }

    function lookup() {
        var value = $.trim(field().val());
        var digits = value.replace(/\D/g, '');

        if (digits.length !== 11) {
            lastDigits = '';
            showStatus('', '');
            return;
        }

        // Ugyanarra az adószámra nem kérdezünk újra.
        if (digits === lastDigits) {
            return;
        }

        lastDigits = digits;

        if (request) {
            request.abort();
        }

        showStatus(cfg.checking, '');

        request = $.post(cfg.ajax_url, {
            action: cfg.action,
            nonce: cfg.nonce,
            vat_number: value
        }).done(function (response) {
            if (!response || !response.success || !response.data) {
                showStatus(response && response.data && response.data.message ? response.data.message : '', '');
                return;
            }

            showStatus(response.data.message, response.data.status);

            if (cfg.autofill && response.data.company) {
                fillCompany(response.data.company);
            }
        }).fail(function (xhr, status) {
            // Megszakított kérésnél (új gépelés) nincs teendő; hálózati hibánál a vásárlás nem akad meg.
            if (status !== 'abort') {
                showStatus('', '');
            }
        });
    }

    function schedule() {
        window.clearTimeout(timer);
        timer = window.setTimeout(lookup, 600);
    }

    $(document.body).on('input change blur', '#billing_vat_number', schedule);
})(jQuery);
