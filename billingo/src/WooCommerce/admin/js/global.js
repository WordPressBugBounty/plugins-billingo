jQuery(document).ready(function ($) {
    let entitlements = [
        {
            value: "AAM",
            text: "Alanyi adómentesség"
        },
        {
            value: "ANTIQUES",
            text: "Különbözet szerinti szabályozás - gyűjteménydarabok és régiségek"
        },
        {
            value: "ARTWORK",
            text: "Különbözet szerinti szabályozás - műalkotások"
        },
        {
            value: "ATK",
            text: "Áfa tv. tárgyi hatályán kívüli ügylet"
        },
        {
            value: "EAM",
            text: "Áfamentes termékexport, azzal egy tekintet alá eső értékesítések, nemzetközi közlekedéshez kapcsolódó áfamentes ügyletek (Áfa tv. 98-109. §)"
        },
        {
            value: "EUE",
            text: "EU más tagállamában áfaköteles (áfa fizetésére az értékesítő köteles)"
        },
        {
            value: "EUFAD37",
            text: "Áfa tv. 37. § (1) bekezdése alapján a szolgáltatás teljesítése helye az EU más tagállama (áfa fizetésére a vevő köteles)"
        },
        {
            value: "EUFADE",
            text: "Áfa tv. szerint egyéb rendelkezése szerint a teljesítés helye EU más tagállama (áfa fizetésére a vevő köteles)"
        },
        {
            value: "HO",
            text: "Áfa tv. szerint EU-n kívül teljesített ügylet"
        },
        {
            value: "KBAET",
            text: "Más tagállamba irányuló áfamentes termékértékesítés (Áfa tv. 89. §)"
        },
        {
            value: "NAM_1",
            text: "Áfamentes közvetítői tevékenység (Áfa tv. 110. §)"
        },
        {
            value: "NAM_2",
            text: "Termékek nemzetközi forgalmához kapcsolódó áfamentes ügylet (Áfa tv. 111-118. §)"
        },
        {
            value: "SECOND_HAND",
            text: "Különbözet szerinti szabályozás - használt cikkek"
        },
        {
            value: "TAM",
            text: "Tevékenység közérdekű jellegére vagy egyéb sajátos jellegére tekintettel áfamentes (Áfa tv. 85-87.§)"
        },
        {
            value: "TRAVEL_AGENCY",
            text: "Különbözet szerinti szabályozás - utazási irodák"
        }
    ];

    let taxEntitlements = {
        "AAM":   ["AAM"],
        "TAM":   ["TAM"],
        "EU":    ["KBAET"],
        "EUK":   ["EAM"],
        "ÁKK":   ["ATK"],
        "0%":    ["AAM", "EAM", "KBAET", "NAM_1", "NAM_2", "TAM"],
        "AM":    ["AAM", "EAM", "KBAET", "NAM_1", "NAM_2", "TAM"],
        "MAA":   ["AAM", "EAM", "KBAET", "NAM_1", "NAM_2", "TAM"],
        "ÁTHK":  ["EUE", "EUFAD37", "EUFADE", "HO"],
        "K.AFA": ["ANTIQUES", "ARTWORK", "SECOND_HAND", "TRAVEL_AGENCY"],
    };

    function updateTaxOverrideFields() {
        let selectedTaxOverride = $("#wc_billingo_tax_override").val();

        if (selectedTaxOverride === '0') {
            $("input[name='wc_billingo_tax_override_choice']").prop("disabled", true);
            $("#wc_billingo_tax_override_entitlements").prop("disabled", true);
            $("#wc_billingo_tax_override_value").prop("disabled", true);
            $("#wc_billingo_tax_override_zero_entitlements").prop("disabled", true);
        } else {
            $("input[name='wc_billingo_tax_override_choice']").prop("disabled", false);
            $("input[name='wc_billingo_tax_override_choice']:checked").trigger("change");
        }
    }

    updateTaxOverrideFields();

    jQuery("#wc_billingo_tax_override").change(function (e) {
        updateTaxOverrideFields();
    });

    jQuery("#wc_billingo_generate").click(function (e) {
        e.preventDefault();
        let r = confirm("Biztosan létrehozod a számlát?");
        if (r != true) {
            return false;
        }
        let nonce = jQuery(this).data("nonce");
        let order = jQuery(this).data("order");
        let button = jQuery("#wc_billingo_generate");
        let note = jQuery("#wc_billingo_invoice_note").val();
        let deadline = jQuery("#wc_billingo_invoice_deadline").val();
        let ignore_proforma = jQuery("#wc_billingo_ignore_proforma").val();
        let completed = jQuery("#wc_billingo_invoice_completed").val();
        let invoice_type = jQuery("#wc_billingo_invoice_type").val();

        let data = {
            action: "wc_billingo_generate_invoice",
            nonce: nonce,
            order: order,
            wc_billingo_invoice_note: note,
            wc_billingo_invoice_deadline: deadline,
            wc_billingo_invoice_completed: completed,
            wc_billingo_invoice_type: invoice_type,
            ignore_proforma: ignore_proforma
        };

        button.block({
            message: null,
            overlayCSS: {
                background: "#fff url(" + wc_billingo_params.loading + ") no-repeat center",
                backgroundSize: "16px 16px",
                opacity: 0.6
            }
        });

        jQuery.post(ajaxurl, data, function (response) {
            //Remove old messages
            jQuery(".wc-billingo-message").remove();

            //Generate the error/success messages
            if (response.data.error) {
                button.before('<div class="wc-billingo-error error wc-billingo-message"></div>');
            } else {
                button.before('<div class="wc-billingo-success updated wc-billingo-message"></div>');
            }

            //Get the error messages
            let ul = jQuery("<ul>");
            jQuery.each(response.data.messages, function (i, value) {
                let li = jQuery("<li>");
                li.append(value);
                ul.append(li);
            });
            jQuery(".wc-billingo-message").append(ul);

            //If success, hide the button
            if (!response.data.error) {
                button.slideUp();
                button.before(response.data.link);
            }

            button.unblock();
        });
    });

    jQuery("#wc_billingo_options").click(function () {
        jQuery("#wc_billingo_options_form").slideToggle();
        return false;
    });

    jQuery("#wc_billingo_already").click(function (e) {
        e.preventDefault();
        let note = prompt("Számlakészítés kikapcsolása. Mi az indok?", "Ehhez a rendeléshez nem kell számla.");
        if (!note) {
            return false;
        }

        let nonce = jQuery(this).data("nonce");
        let order = jQuery(this).data("order");
        let button = jQuery("#wc_billingo_already");

        let data = {
            action: "wc_billingo_already",
            nonce: nonce,
            order: order,
            note: note
        };

        button.block({
            message: null,
            overlayCSS: {
                background: "#fff url(" + wc_billingo_params.loading + ") no-repeat center",
                backgroundSize: "16px 16px",
                opacity: 0.6
            }
        });

        jQuery.post(ajaxurl, data, function (response) {
            //Remove old messages
            jQuery(".wc-billingo-message").remove();

            //Generate the error/success messages
            if (response.data.error) {
                button.before('<div class="wc-billingo-error error wc-billingo-message"></div>');
            } else {
                button.before('<div class="wc-billingo-success updated wc-billingo-message"></div>');
            }

            //Get the error messages
            let ul = jQuery("<ul>");
            jQuery.each(response.data.messages, function (i, value) {
                let li = jQuery("<li>");
                li.append(value);
                ul.append(li);
            });
            jQuery(".wc-billingo-message").append(ul);

            //If success, hide the button
            if (!response.data.error) {
                button.slideUp();
                button.before(response.data.link);
            }

            button.unblock();
        });
    });

    jQuery("#wc_billingo_already_back").click(function (e) {
        e.preventDefault();
        let r = confirm("Biztosan visszakapcsolod a számlakészítés ennél a rendelésnél?");
        if (r != true) {
            return false;
        }

        let nonce = jQuery(this).data("nonce");
        let order = jQuery(this).data("order");
        let button = jQuery("#wc_billingo_already_back");

        let data = {
            action: "wc_billingo_already_back",
            nonce: nonce,
            order: order
        };

        jQuery("#billingo_already_div").block({
            message: null,
            overlayCSS: {
                background: "#fff url(" + wc_billingo_params.loading + ") no-repeat center",
                backgroundSize: "16px 16px",
                opacity: 0.6
            }
        });

        jQuery.post(ajaxurl, data, function (response) {
            //Remove old messages
            jQuery(".wc-billingo-message").remove();

            //Generate the error/success messages
            if (response.data.error) {
                button.before('<div class="wc-billingo-error error wc-billingo-message"></div>');
            } else {
                button.before('<div class="wc-billingo-success updated wc-billingo-message"></div>');
            }

            //Get the error messages
            let ul = jQuery("<ul>");
            jQuery.each(response.data.messages, function (i, value) {
                let li = jQuery("<li>");
                li.append(value);
                ul.append(li);
            });
            jQuery(".wc-billingo-message").append(ul);

            //If success, show the button
            if (!response.data.error) {
                button.slideDown();
            }

            jQuery("#billingo_already_div").unblock().slideUp();
        });
    });


    jQuery("#wc_billingo_storno").click(function (e) {
        e.preventDefault();
        let r = confirm("Biztosan sztornózod a számlát?");
        if (r != true) {
            return false;
        }
        let nonce = jQuery(this).data("nonce");
        let order = jQuery(this).data("order");
        let button = jQuery("#wc_billingo_storno");

        let data = {
            action: "wc_billingo_storno_invoice",
            nonce: nonce,
            order: order
        };

        button.block({
            message: null,
            overlayCSS: {
                background: "#fff url(" + wc_billingo_params.loading + ") no-repeat center",
                backgroundSize: "16px 16px",
                opacity: 0.6
            }
        });

        jQuery.post(ajaxurl, data, function (response) {
            //Remove old messages
            jQuery(".wc-billingo-message").remove();

            //Generate the error/success messages
            if (response.data.error) {
                button.before('<div class="wc-billingo-error error wc-billingo-message"></div>');
            } else {
                button.before('<div class="wc-billingo-success updated wc-billingo-message"></div>');
            }

            //Get the error messages
            let ul = jQuery("<ul>");
            jQuery.each(response.data.messages, function (i, value) {
                let li = jQuery("<li>");
                li.append(value);
                ul.append(li);
            });
            jQuery(".wc-billingo-message").append(ul);

            //If success, hide the button
            if (!response.data.error) {
                button.slideUp();
            }

            button.unblock();
        });
    });

    function updateTaxOverrideFields() {
        let selectedTaxOverride = $("#wc_billingo_tax_override").val();

        if (selectedTaxOverride === '0') {
            $("[name='wc_billingo_tax_override_choice']").prop("disabled", true);
            $("#wc_billingo_tax_override_entitlements").prop("disabled", true);
            $("#wc_billingo_tax_override_value").prop("disabled", true);
            $("#wc_billingo_tax_override_zero_entitlements").prop("disabled", true);
        } else {
            $("[name='wc_billingo_tax_override_choice']").prop("disabled", false);
            $("[name='wc_billingo_tax_override_choice']").trigger("change");
        }
    }

    function updateTaxOverrideChoiceFields() {
        let selectedChoice = $("[name='wc_billingo_tax_override_choice']").val();

        if (selectedChoice === '0') {
            $("#wc_billingo_tax_override_entitlements").prop("disabled", true);
            $("#wc_billingo_tax_override_value").prop("disabled", true);
            $("#wc_billingo_tax_override_zero_entitlements").prop("disabled", false);
        } else {
            $("#wc_billingo_tax_override_entitlements").prop("disabled", false);
            $("#wc_billingo_tax_override_value").prop("disabled", false);
            $("#wc_billingo_tax_override_zero_entitlements").prop("disabled", true);
            //remove 0% option from the entitlements select in wc_billingo_tax_override_value
            $("#wc_billingo_tax_override_value").find("option[value='0%']").remove();
        }
    }

    updateTaxOverrideFields();
    updateTaxOverrideChoiceFields();

    $("#wc_billingo_tax_override").change(function (e) {
        updateTaxOverrideFields();
    }).trigger('change');

    $("[name='wc_billingo_tax_override_choice']").change(function (e) {
        updateTaxOverrideChoiceFields();
    });


    function handleBillingoEmailSettings(selectName, inputNames) {
        $('select[name="' + selectName + '"]').change(function() {
            var selected = $(this).val();
            var inputs = inputNames.map(function(name) {
                return 'input[name="' + name + '"]';
            }).join(',');

            if (selected === 'no' || selected === 'billingo') {
                $(inputs).prop('disabled', true);
            } else {
                $(inputs).prop('disabled', false);
            }
        }).trigger('change');
    }

    handleBillingoEmailSettings('billingo_email_settings[wc_billingo_proforma_email]', [
        'billingo_email_settings[wc_billingo_proforma_email_woo_btn]',
        'billingo_email_settings[wc_billingo_proforma_email_woo_text]'
    ]);

    handleBillingoEmailSettings('billingo_email_settings[wc_billingo_email]', [
        'billingo_email_settings[wc_billingo_email_woo_btn]',
        'billingo_email_settings[wc_billingo_email_woo_text]'
    ]);

    handleBillingoEmailSettings('billingo_email_settings[wc_billingo_storno_email]', [
        'billingo_email_settings[wc_billingo_storno_email_woo_btn]',
        'billingo_email_settings[wc_billingo_storno_email_woo_text]'
    ]);

    function dataEraserHandler() {
        let state = $('#wc_billingo_data_eraser_code').is(':checked');

        $("#wc_billingo_data_eraser_code_selector").prop("disabled", !state);
    }

    $('#wc_billingo_data_eraser_code').change(dataEraserHandler);

    dataEraserHandler();




    // add billingo logo int the woocommerce settings page to the main navigation
    function addBillingoLogoToMainNav() {
    const billingoLogoSvgElementDiv = document.createElement('div');
    billingoLogoSvgElementDiv.id = 'billingo-logo-to-main-nav';
    billingoLogoSvgElementDiv.style.display = 'none';

    billingoLogoSvgElementDiv.innerHTML = `<svg width="94" height="24" viewBox="0 0 94 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M11.1915 20.9881C16.8104 20.9881 21.3654 16.4933 21.3654 10.9486C21.3654 5.40394 16.8104 0.909088 11.1915 0.909088C5.57259 0.909088 1.01758 5.40394 1.01758 10.9486C1.01758 16.4933 5.57259 20.9881 11.1915 20.9881Z" fill="white" />
        <path d="M76.076 23.9428C75.087 23.6978 74.2884 22.6592 74.734 20.8872C75.2264 18.9295 76.7612 18.0536 77.9953 17.3438L78.0797 17.2936L77.4316 19.9405C77.0029 20.3074 76.6963 20.7934 76.5521 21.335C76.2779 22.3334 76.9672 23.1753 77.54 20.8857C77.7943 19.8632 77.863 19.6042 77.863 19.6042L78.5065 17.0441L78.6398 16.5125C77.9248 16.9 77.1241 17.1079 76.3084 17.1179C75.7638 17.134 75.2228 17.0248 74.7284 16.7989C74.234 16.573 73.7996 16.2365 73.4597 15.8163C73.1217 15.363 72.8928 14.8397 72.7903 14.2861C72.6878 13.7325 72.7144 13.1631 72.8681 12.6212C73.1722 11.4951 73.8337 10.4947 74.7557 9.76635C75.6776 9.03804 76.8116 8.62011 77.9917 8.57376C78.5361 8.55735 79.0769 8.66619 79.5714 8.89167C80.0658 9.11714 80.5003 9.45307 80.8404 9.87288C81.1785 10.3261 81.4075 10.8494 81.5101 11.403C81.6126 11.9566 81.5861 12.526 81.4325 13.068L79.3565 21.336C78.914 23.0964 77.6677 24 76.5506 24C76.3906 24.0001 76.2312 23.9809 76.076 23.9428ZM74.6836 13.0705C74.5985 13.3393 74.5767 13.6238 74.6201 13.9023C74.6635 14.1807 74.7708 14.4456 74.9339 14.6768C75.1017 14.8723 75.3129 15.027 75.5511 15.1287C75.7893 15.2305 76.0481 15.2767 76.3074 15.2636C77.0674 15.2149 77.7935 14.935 78.3856 14.4623C78.9777 13.9896 79.4069 13.3475 79.6139 12.6242C79.6992 12.3553 79.7209 12.0708 79.6775 11.7923C79.634 11.5139 79.5265 11.249 79.3631 11.0179C79.1955 10.8223 78.9843 10.6676 78.7461 10.5659C78.5078 10.4643 78.2489 10.4185 77.9897 10.4321C77.2299 10.481 76.5042 10.761 75.9122 11.2334C75.3202 11.7058 74.891 12.3476 74.6836 13.0705ZM2.5665e-05 10.9587C0.0094744 8.78392 0.67163 6.66069 1.90291 4.857C3.13418 3.0533 4.87938 1.65001 6.9182 0.824245C8.95701 -0.00151438 11.198 -0.212716 13.3584 0.217301C15.5188 0.647318 17.5016 1.69928 19.0566 3.24038C20.6116 4.78149 21.669 6.74265 22.0955 8.87631C22.5219 11.01 22.2982 13.2205 21.4525 15.2288C20.6069 17.2371 19.1773 18.9531 17.3442 20.1603C15.511 21.3675 13.3565 22.0118 11.1527 22.0117C8.18841 22.0053 5.34813 20.8372 3.25662 18.7644C1.1651 16.6915 -0.0063222 13.8838 2.5665e-05 10.9587ZM7.04495 6.40422L5.48071 12.6237C5.3268 13.1655 5.30009 13.7347 5.40261 14.2882C5.50512 14.8417 5.73417 15.3648 6.07232 15.8178C6.41219 16.238 6.84655 16.5745 7.34099 16.8004C7.83543 17.0263 8.37639 17.1355 8.92102 17.1194H11.7188C12.7386 17.0797 13.7188 16.7188 14.5156 16.0896C15.3125 15.4603 15.8842 14.5958 16.147 13.6226C16.2811 13.1475 16.3037 12.6484 16.2131 12.1633C16.1225 11.6782 15.9211 11.2199 15.6241 10.8231C15.5834 10.7729 15.5386 10.7252 15.4954 10.678C16.2533 10.1125 16.7975 9.31285 17.0413 8.4066C17.1626 7.97514 17.1826 7.5221 17.1001 7.08184C17.0175 6.64158 16.8344 6.22565 16.5647 5.8656C16.292 5.53243 15.9456 5.26536 15.552 5.08488C15.1585 4.90441 14.7282 4.81533 14.2944 4.8245H9.05888C8.59485 4.83619 8.14731 4.99669 7.78395 5.28171C7.42059 5.56673 7.16117 5.96077 7.04495 6.40422ZM82.9174 15.8178C82.5792 15.3645 82.35 14.8412 82.2474 14.2875C82.1448 13.7337 82.1715 13.1642 82.3253 12.6222C82.6296 11.4961 83.2911 10.4957 84.2132 9.7674C85.1352 9.03911 86.2693 8.62117 87.4494 8.57477C87.9938 8.55835 88.5346 8.66719 89.029 8.89267C89.5235 9.11815 89.9579 9.45408 90.2981 9.87388C90.4402 10.0574 90.5621 10.2552 90.6618 10.4642L90.6704 10.4597C90.7201 10.5638 90.7924 10.6559 90.8823 10.7293C90.9722 10.8027 91.0773 10.8557 91.1903 10.8844C91.4349 10.9381 91.6881 10.9415 91.9341 10.8945C92.1802 10.8474 92.4137 10.7508 92.6203 10.6108L93.6 12.1965C92.989 12.5771 92.2832 12.7835 91.5606 12.7929C91.3587 12.7908 91.1575 12.7703 90.9594 12.7316C90.9395 12.8446 90.9207 12.959 90.8912 13.0705C90.5874 14.1972 89.9259 15.1983 89.0036 15.9272C88.0813 16.656 86.9467 17.0741 85.7661 17.1204C85.2214 17.1364 84.6804 17.027 84.1859 16.8009C83.6915 16.5748 83.2572 16.2382 82.9174 15.8178ZM84.1434 13.069C84.058 13.3378 84.0361 13.6223 84.0794 13.9007C84.1227 14.1791 84.23 14.4441 84.3931 14.6753C84.5609 14.8708 84.7721 15.0255 85.0103 15.1272C85.2485 15.229 85.5073 15.2752 85.7666 15.2621C86.5266 15.2134 87.2527 14.9334 87.8448 14.4608C88.4369 13.9881 88.8661 13.346 89.0731 12.6227C89.1583 12.3539 89.18 12.0693 89.1367 11.7909C89.0933 11.5125 88.9859 11.2476 88.8228 11.0164C88.6552 10.8209 88.4439 10.6664 88.2056 10.5648C87.9674 10.4633 87.7085 10.4175 87.4494 10.4311C86.6896 10.4801 85.964 10.7603 85.3721 11.2329C84.7803 11.7055 84.3514 12.3475 84.1444 13.0705L84.1434 13.069ZM52.3555 15.8178C52.2803 15.7214 52.2109 15.6208 52.1474 15.5166C51.1783 16.5079 49.8532 17.0838 48.4579 17.1199C47.9132 17.136 47.3723 17.0268 46.8778 16.8009C46.3834 16.575 45.949 16.2385 45.6092 15.8183C45.5451 15.736 45.4886 15.6496 45.4326 15.5633C45.0078 16.0307 44.4923 16.4092 43.9166 16.6765C43.3408 16.9438 42.7166 17.0944 42.0808 17.1194C41.6009 17.1291 41.1253 17.0284 40.6918 16.8251C40.2582 16.6218 39.8786 16.3216 39.5832 15.9483C39.2382 15.4731 39.0073 14.9268 38.9077 14.3506C38.8081 13.7744 38.8425 13.1834 39.0083 12.6222L39.7129 9.82017L41.6622 9.81415L40.8234 13.0705C40.7288 13.3587 40.7007 13.6641 40.7411 13.9645C40.7815 14.2648 40.8893 14.5524 41.0568 14.8063C41.1787 14.9563 41.3347 15.0759 41.5122 15.1551C41.6897 15.2344 41.8837 15.2712 42.0783 15.2626C43.3093 15.2626 44.6548 14.0538 45.015 12.6232L46.332 7.3891C46.6632 6.07241 47.4852 5.10962 48.5311 4.81195C48.8605 4.70864 49.2118 4.69395 49.5489 4.7694C49.8859 4.84485 50.1964 5.0077 50.4484 5.24114C50.7979 5.57546 51.3274 6.35653 50.954 7.83686C50.3823 9.96082 49.0554 11.811 47.2156 13.0494L48.0478 9.66656C48.6082 9.02238 49.0215 8.26668 49.2596 7.45084C49.5139 6.5513 48.6863 5.54333 48.1272 7.83686C47.5681 10.1304 46.8321 13.07 46.8321 13.07C46.7466 13.3388 46.7248 13.6234 46.7682 13.9018C46.8116 14.1803 46.919 14.4452 47.0823 14.6763C47.25 14.8719 47.4612 15.0266 47.6994 15.1284C47.9377 15.2302 48.1965 15.2763 48.4558 15.2631C49.2158 15.2144 49.9419 14.9345 50.534 14.4618C51.1262 13.9891 51.5553 13.347 51.7623 12.6237L53.0794 7.3896C53.41 6.07292 54.2326 5.11012 55.2779 4.81245C55.6074 4.70915 55.9588 4.69447 56.2959 4.76991C56.633 4.84536 56.9437 5.0082 57.1957 5.24164C57.5447 5.57596 58.0747 6.35703 57.7019 7.83736C57.1299 9.96122 55.803 11.8113 53.9635 13.0499L54.8252 9.64046C55.4035 9.01991 55.8173 8.26788 56.0298 7.45134C56.307 6.33746 55.4194 5.75717 54.8969 7.83736C54.3745 9.91755 53.5799 13.0705 53.5799 13.0705C53.4947 13.3393 53.4729 13.6238 53.5162 13.9022C53.5595 14.1806 53.6667 14.4456 53.8297 14.6768C53.9975 14.8722 54.2087 15.0269 54.4469 15.1286C54.6851 15.2304 54.9439 15.2766 55.2032 15.2636C55.9632 15.215 56.6893 14.9351 57.2814 14.4624C57.8736 13.9897 58.3027 13.3475 58.5097 12.6242C58.509 12.6214 58.509 12.6185 58.5097 12.6157L59.2142 9.81465H61.1651L60.3313 13.0619C60.2367 13.3501 60.2085 13.6556 60.2489 13.9559C60.2893 14.2563 60.3972 14.5439 60.5648 14.7978C60.6864 14.9479 60.8423 15.0676 61.0198 15.147C61.1972 15.2264 61.3911 15.2632 61.5858 15.2546C62.8173 15.2546 64.1623 14.0458 64.523 12.6147L65.1415 10.155C65.2582 9.71166 65.5178 9.31779 65.8811 9.03282C66.2445 8.74786 66.692 8.58726 67.156 8.57527H69.2503C69.6988 8.56226 70.1441 8.65262 70.5511 8.83919C70.958 9.02575 71.3152 9.30337 71.5943 9.65C71.8726 10.0213 72.0614 10.4504 72.1464 10.9045C72.2315 11.3586 72.2105 11.8259 72.0852 12.2708L70.8644 17.1109H68.9211L70.2702 11.821C70.3262 11.6497 70.3419 11.468 70.316 11.2898C70.2902 11.1116 70.2236 10.9415 70.1212 10.7925C70.0149 10.6697 69.8811 10.5732 69.7303 10.5105C69.5796 10.4479 69.416 10.4208 69.2528 10.4316H67.1585C67.1146 10.4441 67.0741 10.4663 67.0401 10.4963C67.006 10.5264 66.9792 10.5636 66.9617 10.6053L66.3431 13.0614C65.7718 15.3339 63.6836 17.1109 61.5888 17.1109C61.109 17.1209 60.6335 17.0203 60.2 16.8172C59.7665 16.6141 59.387 16.314 59.0916 15.9408C59.0003 15.8238 58.9174 15.7007 58.8434 15.5723C57.8793 16.5313 56.5772 17.0859 55.2088 17.1204C54.6633 17.1371 54.1214 17.0281 53.6261 16.802C53.1308 16.5758 52.6957 16.2389 52.3555 15.8178ZM30.4078 17.1204C29.8632 17.1365 29.3222 17.0273 28.8278 16.8014C28.3333 16.5755 27.899 16.239 27.5591 15.8188C27.2212 15.3655 26.9923 14.8422 26.8898 14.2886C26.7873 13.735 26.8139 13.1657 26.9675 12.6237L28.5322 6.40422C28.6484 5.96101 28.9076 5.56715 29.2706 5.28215C29.6337 4.99716 30.0809 4.83652 30.5446 4.8245H35.7791C36.2132 4.81535 36.6436 4.90448 37.0374 5.08504C37.4311 5.2656 37.7777 5.53279 38.0505 5.8661C38.3201 6.22617 38.5032 6.6421 38.5858 7.08235C38.6684 7.52261 38.6483 7.97564 38.5271 8.40711C38.2837 9.3134 37.7398 10.1132 36.9822 10.6791C37.0254 10.7262 37.0702 10.7719 37.1114 10.8241C37.4083 11.2209 37.6096 11.6793 37.7 12.1644C37.7904 12.6495 37.7676 13.1486 37.6333 13.6236C37.3708 14.5965 36.7995 15.4608 36.0031 16.09C35.2067 16.7193 34.2271 17.0803 33.2077 17.1204H30.4078ZM30.3483 6.85349L28.7841 13.0705C28.6987 13.3393 28.6769 13.6238 28.7201 13.9022C28.7634 14.1806 28.8707 14.4456 29.0338 14.6768C29.2016 14.8723 29.4128 15.027 29.651 15.1287C29.8892 15.2305 30.148 15.2767 30.4073 15.2636H33.2051C33.8055 15.2243 34.3787 15.0026 34.8461 14.6287C35.3135 14.2549 35.6521 13.7473 35.8152 13.1759C35.8804 12.9743 35.898 12.7607 35.8667 12.5514C35.8354 12.342 35.756 12.1426 35.6347 11.9681C35.5109 11.8252 35.3557 11.7122 35.1808 11.6376C35.006 11.563 34.8162 11.5289 34.6259 11.5379C34.6025 11.5379 34.5786 11.5409 34.5552 11.5409H31.6378V11.5324L32.1023 9.68464H34.5572C34.575 9.68464 34.5939 9.68464 34.6112 9.68162H34.6305C35.1107 9.6288 35.5645 9.43715 35.9347 9.13073C36.305 8.82432 36.5752 8.4168 36.7116 7.95934C36.7634 7.8016 36.7782 7.63428 36.7548 7.47007C36.7313 7.30585 36.6703 7.14907 36.5763 7.01161C36.4787 6.89941 36.3559 6.81125 36.2177 6.75417C36.0794 6.69709 35.9296 6.67265 35.7801 6.68282H30.5446C30.5011 6.69512 30.461 6.71682 30.427 6.74634C30.393 6.77585 30.3662 6.81246 30.3483 6.85349ZM8.92153 15.2626C8.66227 15.2757 8.40345 15.2295 8.16523 15.1277C7.927 15.026 7.71579 14.8713 7.54805 14.6758C7.38493 14.4446 7.27761 14.1797 7.23431 13.9012C7.19102 13.6228 7.2129 13.3383 7.29828 13.0695L8.86252 6.85249C8.88019 6.81117 8.907 6.77427 8.94097 6.74448C8.97494 6.7147 9.01522 6.69277 9.05888 6.68031H14.2944C14.444 6.67006 14.594 6.69445 14.7324 6.75154C14.8708 6.80862 14.9938 6.89682 15.0915 7.0091C15.1853 7.14668 15.2461 7.30347 15.2695 7.46765C15.2928 7.63183 15.2781 7.79911 15.2263 7.95683C15.0899 8.41437 14.8196 8.82193 14.4492 9.12835C14.0789 9.43477 13.6251 9.62638 13.1447 9.67911H13.1259C13.1086 9.67911 13.0898 9.68213 13.072 9.68213H10.616L10.151 11.5299V11.5384H13.071C13.0949 11.5384 13.1183 11.5384 13.1417 11.5354C13.3319 11.5265 13.5217 11.5606 13.6966 11.6351C13.8714 11.7097 14.0266 11.8227 14.1504 11.9656C14.2719 12.14 14.3514 12.3395 14.3827 12.5488C14.414 12.7582 14.3963 12.9718 14.331 13.1734C14.1678 13.7448 13.8293 14.2524 13.3619 14.6262C12.8945 15 12.3212 15.2218 11.7209 15.2611L8.92153 15.2626ZM59.3882 7.69079C59.4892 7.32647 59.7041 7.00289 60.0023 6.76612C60.3005 6.52935 60.6668 6.39146 61.0491 6.37209C61.2134 6.36484 61.377 6.39737 61.5257 6.46684C61.6743 6.53632 61.8035 6.64062 61.9019 6.77067C62.0004 6.90072 62.0651 7.05255 62.0904 7.21293C62.1157 7.37331 62.1008 7.53736 62.0471 7.69079C61.9461 8.05492 61.7312 8.37827 61.4329 8.61471C61.1347 8.85116 60.7684 8.98861 60.3863 9.00747C60.2221 9.01496 60.0585 8.98267 59.9099 8.91341C59.7612 8.84415 59.6321 8.74003 59.5336 8.61015C59.4351 8.48026 59.3704 8.32856 59.3451 8.1683C59.3197 8.00804 59.3345 7.8441 59.3882 7.69079ZM39.8838 7.69079C39.9847 7.32643 40.1996 7.00281 40.4978 6.76603C40.7961 6.52924 41.1624 6.39138 41.5447 6.37209C41.709 6.36496 41.8725 6.39757 42.0211 6.46708C42.1697 6.53659 42.2988 6.64089 42.3972 6.7709C42.4956 6.90092 42.5603 7.05269 42.5857 7.21302C42.611 7.37335 42.5963 7.53737 42.5427 7.69079C42.4417 8.055 42.2267 8.3784 41.9283 8.61485C41.63 8.85131 41.2635 8.98871 40.8813 9.00747C40.717 9.01522 40.5533 8.98312 40.4044 8.91397C40.2556 8.84481 40.1262 8.74072 40.0276 8.6108C39.9289 8.48088 39.8641 8.32908 39.8387 8.1687C39.8132 8.00831 39.828 7.84422 39.8818 7.69079H39.8838Z" fill="#10355A" />
    </svg>`;

    document.body.appendChild(billingoLogoSvgElementDiv);

    const billingoLogoSvgElement = billingoLogoSvgElementDiv.querySelector('svg');
    if (!billingoLogoSvgElement) return;

    const billingoMenuElement = document.querySelector('a[href*="tab=settings_tab_billingo"]');
    if (!billingoMenuElement) return;

    billingoMenuElement.innerHTML = '';
    billingoMenuElement.appendChild(billingoLogoSvgElement.cloneNode(true));

    // if billingo menu element is active add white background to the element
    if (billingoMenuElement.classList.contains('nav-tab-active')) {
        billingoMenuElement.style.backgroundColor = '#FFF';
    }


    }
    addBillingoLogoToMainNav();

    // Handle document icons in orders admin page
    function handleOrderDocumentIcons() {
        // Only run on orders admin page and for screens wider than 370px
        if (window.location.href.indexOf('page=wc-orders') !== -1 && window.innerWidth >= 370) {
            var screenWidth = window.innerWidth;
            
            // Handle different DOM structures for different screen sizes
            if (screenWidth < 782) {
                // Below 782px: Look for icons in ANY column and move to order_number column
                jQuery('tr').each(function() {
                    var $row = jQuery(this);
                    var $orderNumberColumn = $row.find('.order_number.column-order_number');
                    var $orderStatusColumn = $row.find('.order_status.column-order_status');
                    var $statusMark = $orderNumberColumn.find('.order_status.small-screen-only .order-status');
                    
                    // Look for icons in both columns
                    var $documentIcons = $orderStatusColumn.find('.billingo-document-icons');
                    if ($documentIcons.length === 0) {
                        $documentIcons = $orderNumberColumn.find('.billingo-document-icons');
                    }
                    
                    if ($documentIcons.length > 0 && $statusMark.length > 0) {
                        // Move icons to order_number column, before the mark (if not already there)
                        if ($statusMark.prev('.billingo-document-icons').length === 0) {
                            $documentIcons.detach();
                            $statusMark.before($documentIcons);
                        }
                        
                        // Style for small screens
                        $documentIcons.css({
                            'margin-left': '0',
                            'margin-right': '5px',
                            'display': 'inline-block',
                            'vertical-align': 'middle'
                        });
                        
                        // Style the status mark to ensure proper alignment
                        $statusMark.css({
                            'display': 'inline-block',
                            'vertical-align': 'middle'
                        });
                        
                        // Show medium screen version only
                        $documentIcons.find('.billingo-docs-large-screen').css('display', 'none');
                        $documentIcons.find('.billingo-docs-medium-screen').css('display', 'inline-block');
                    }
                });
            } else {
                // Above 782px: Move icons back to order_status column
                jQuery('tr').each(function() {
                    var $row = jQuery(this);
                    var $orderNumberColumn = $row.find('.order_number.column-order_number');
                    var $orderStatusColumn = $row.find('.order_status.column-order_status');
                    var $statusMark = $orderStatusColumn.find('.order-status');
                    
                    // Look for icons in both columns
                    var $documentIcons = $orderNumberColumn.find('.billingo-document-icons');
                    if ($documentIcons.length === 0) {
                        $documentIcons = $orderStatusColumn.find('.billingo-document-icons');
                    }
                    
                    if ($documentIcons.length > 0 && $statusMark.length > 0) {
                        // Move icons to order_status column, after the mark (if not already there)
                        if ($statusMark.next('.billingo-document-icons').length === 0) {
                            $documentIcons.detach();
                            $statusMark.after($documentIcons);
                        }
                        
                        // Style for large screens
                        $documentIcons.css({
                            'margin-left': '5px',
                            'margin-right': '0',
                            'display': 'inline-block',
                            'vertical-align': 'middle'
                        });
                        
                        // Style the status mark to ensure proper alignment
                        $statusMark.css({
                            'display': 'inline-block',
                            'vertical-align': 'middle'
                        });
                        
                        // Handle responsive display for large vs medium screens
                        if (screenWidth >= 1300) {
                            // Large screens: show large screen version, hide medium screen version
                            $documentIcons.find('.billingo-docs-large-screen').css('display', 'inline-block');
                            $documentIcons.find('.billingo-docs-medium-screen').css('display', 'none');
                        } else {
                            // Medium screens: show medium screen version, hide large screen version
                            $documentIcons.find('.billingo-docs-large-screen').css('display', 'none');
                            $documentIcons.find('.billingo-docs-medium-screen').css('display', 'inline-block');
                        }
                    }
                });
            }
        } else if (window.innerWidth < 370) {
            // Hide document icons on very small screens
            jQuery('.billingo-document-icons').hide();
        }
    }

    // Run on page load
    handleOrderDocumentIcons();
    
    // Run when screen is resized
    jQuery(window).on('resize', function() {
        handleOrderDocumentIcons();
    });
});
