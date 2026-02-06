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

    billingoLogoSvgElementDiv.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="94" height="24" viewBox="0 0 302 96" fill="none">
<path fill-rule="evenodd" clip-rule="evenodd" d="M86.1567 41.7349C86.2534 41.2203 86.3561 40.7571 86.4562 40.3571L93.4029 12.0741C95.3877 4.46575 99.8534 0 107.958 0C114.243 0 119.04 4.13495 119.04 9.92388C119.04 14.7204 117.055 19.1862 112.424 24.1481L95.1503 43.0357C93.569 50.6624 97.3863 53.0928 101.507 53.0928C109.524 53.0928 114.554 46.6674 116.595 41.2023C116.684 40.9077 116.782 40.6254 116.889 40.3571L124.002 12.0741C125.986 4.46575 130.452 0 138.557 0C144.842 0 149.638 4.13495 149.638 9.92388C149.638 14.7204 147.653 19.1862 143.022 24.1481L125.753 43.0321C124.21 50.6616 128.55 53.0928 134.256 53.0928C139.848 53.0928 145.303 50.0544 147.842 42.0845L152.119 24.9751H162.043L157.081 44.6575C155.758 49.9502 157.247 53.0928 161.382 53.0928C166.343 53.0928 168.328 51.6042 170.313 43.6651L172.629 34.072C174.448 26.4637 179.079 24.3135 186.522 24.3135H196.28C203.062 24.3135 208.189 29.937 206.535 36.7184L200.25 62.0243H190.326L196.611 37.0492C197.273 34.5682 196.28 33.245 193.8 33.245H186.191C184.206 33.245 183.049 34.2374 182.552 36.0568L179.244 49.7848C177.425 57.0623 172.132 62.0243 163.532 62.0243H161.382C156.297 62.0243 152.033 59.6601 149.565 55.3371C146.267 58.9457 141.282 62.0243 133.429 62.0243C125.502 62.0243 120.753 58.496 118.264 54.1052C114.503 58.3099 109.066 62.0243 101.507 62.0243C94.4648 62.0243 90.4209 59.5708 88.1863 56.2562C85.1004 59.6023 80.7126 62.0243 74.3821 62.0243C67.4354 62.0243 57.3461 58.5509 61.4811 42.1765L65.7814 24.9751H75.7053L70.9088 43.1689C69.0894 49.9502 71.7358 53.0928 76.5323 53.0928C82.6279 53.0928 85.1153 46.1436 86.1567 41.7349ZM129.433 28.139L135.745 19.517C139.384 14.555 140.541 13.0664 140.541 11.5779C140.541 10.0893 139.218 8.9315 137.73 8.9315C135.579 8.9315 133.925 10.2547 132.933 14.0588L129.433 28.139ZM98.8352 28.139L105.146 19.517C108.785 14.555 109.943 13.0664 109.943 11.5779C109.943 10.0893 108.62 8.9315 107.131 8.9315C104.981 8.9315 103.327 10.2547 102.334 14.0588L98.8352 28.139ZM46.7277 30.9273C52.642 33.5528 54.0382 37.871 54.0382 43.0035C54.0382 53.589 45.2721 62.6859 29.7247 62.6859H21.7855C6.89972 62.6859 -3.52036 55.0776 1.11079 37.0492L6.89972 13.728C10.2077 1.98478 11.6963 0.496194 26.4167 0.496194H38.4907C49.0762 0.496194 57.1807 5.12734 57.1807 15.8782C57.1807 21.2037 54.5684 27.3603 46.7277 30.9273ZM34.1904 35.0644H22.7779L25.0935 26.1329H33.8596C42.9565 26.1329 46.9261 22.9903 46.9261 16.8706C46.9261 11.2471 43.6181 9.42769 36.1752 9.42769H25.5897C18.4776 9.42769 17.9814 9.75848 16.3274 16.3744L10.7039 38.207C8.3883 47.6346 12.5233 53.7544 23.6049 53.7544H29.5593C39.8139 53.7544 43.7835 48.7924 43.7835 43.1689C43.7835 37.5454 41.7987 35.0644 34.1904 35.0644ZM235.543 60.8531C232.971 61.9894 229.729 62.8513 225.225 62.8513C216.128 62.8513 208.189 54.5814 210.505 42.6727C212.655 31.9218 221.421 24.3135 232.668 24.3135C244.411 24.3135 251.689 33.245 248.877 44.1613L238.788 83.1952C236.307 92.1267 231.51 95.7655 224.398 95.7655C215.467 95.7655 210.836 89.3149 212.655 81.7066C214.997 72.9266 224.458 68.4191 235.543 60.8531ZM227.21 53.7544C233.495 53.7544 237.299 50.281 238.788 43.0035C239.78 37.38 236.803 33.5758 231.345 33.5758C225.225 33.5758 221.752 37.7108 220.759 43.0035C219.271 49.2886 222.413 53.7544 227.21 53.7544ZM233.034 71.2227C227.319 76.9686 223.712 80.1289 222.91 82.5336C222.083 84.8492 223.737 86.6686 225.391 86.6686C227.21 86.6686 228.699 85.3454 230.022 81.3758L233.034 71.2227ZM287.243 28.4485H301.804V36.3876H291.538C292.026 38.767 292.046 41.3919 291.55 44.1613C289.73 55.4083 280.799 62.8513 269.056 62.8513C257.312 62.8513 250.696 54.9122 253.177 42.6727C255.328 32.2526 264.424 24.3135 275.837 24.3135C280.614 24.3135 284.494 25.8496 287.243 28.4485ZM269.883 53.7544C275.341 53.7544 279.972 51.4388 281.46 43.0035C282.453 37.38 279.972 33.5758 274.183 33.5758C268.229 33.5758 264.424 37.7108 263.267 43.0035C261.944 49.2886 265.086 53.7544 269.883 53.7544ZM73.5551 19.1862C69.5856 19.1862 66.443 16.0436 66.443 12.0741C66.443 8.1045 69.5856 4.96194 73.5551 4.96194C77.5247 4.96194 80.6673 8.1045 80.6673 12.0741C80.6673 16.0436 77.5247 19.1862 73.5551 19.1862ZM159.893 19.1862C155.923 19.1862 152.781 16.0436 152.781 12.0741C152.781 8.1045 155.923 4.96194 159.893 4.96194C163.862 4.96194 167.005 8.1045 167.005 12.0741C167.005 16.0436 163.862 19.1862 159.893 19.1862Z" fill="#EFF3F5"/>
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
