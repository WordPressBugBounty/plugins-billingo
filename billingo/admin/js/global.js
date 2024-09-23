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

    let setTaxOverrideEntitlementsFor = function (id) {
        let selectedTaxOverride = jQuery("#" + id).val();
        let possibleEntitlements = taxEntitlements[selectedTaxOverride];
        let selectedEntitlement = jQuery("#" + id + "_entitlement").val();

        if (possibleEntitlements) {
            // If there is an entitlement given for the selected tax override
            jQuery("#" + id + "_entitlement").prop("disabled", false);
            jQuery("#" + id + "_entitlement").next().text("A választott ÁFA kulcshoz kötelező a megadott jogcímekből választani.");

            // Remove all entitlement options for a fresh start
            jQuery("#" + id + "_entitlement option").each(function () {
                jQuery(this).remove();
            });

            // Collect and push valid entitlements
            let validEntitlements = [];
            entitlements.forEach(function (entitlement) {
                if (possibleEntitlements.indexOf(entitlement.value) > -1) {
                    jQuery("#" + id + "_entitlement").append(new Option(entitlement.text, entitlement.value));
                    validEntitlements.push(entitlement.value);
                }
            });

            // If there is no valid selected entitlement, select the default one one
            if (validEntitlements.indexOf(selectedEntitlement) == -1) {
                switch (selectedTaxOverride) {
                    case "0%":    jQuery("#" + id + '_entitlement option[value="TAM"]').prop("selected", true); break;
                    case "AM":    jQuery("#" + id + '_entitlement option[value="NAM_1"]').prop("selected", true); break;
                    case "MAA":   jQuery("#" + id + '_entitlement option[value="NAM_1"]').prop("selected", true); break;
                    case "ÁTHK":  jQuery("#" + id + '_entitlement option[value="EUFAD37"]').prop("selected", true); break;
                    case "K.AFA": jQuery("#" + id + '_entitlement option[value="SECOND_HAND"]').prop("selected", true); break;
                    default:      jQuery("#" + id + '_entitlement option:eq(0)').prop("selected", true);
                }
            } else {
                jQuery("#" + id + '_entitlement option[value="' + selectedEntitlement + '"]').prop("selected", true);
            }
        } else {
            // No entitlement for the selected tax override
            jQuery("#" + id + "_entitlement").prop("disabled", true);
            jQuery("#" + id + "_entitlement").next().text("A választott ÁFA kulcshoz nem tartozik jogcím.");

            // Remove all entitlement options
            jQuery("#" + id + "_entitlement option").each(function () {
                jQuery(this).remove();
            });
        }
    };

    setTaxOverrideEntitlementsFor("wc_billingo_tax_override");
    jQuery("#wc_billingo_tax_override").change(function (e) {
        setTaxOverrideEntitlementsFor("wc_billingo_tax_override");
    });

    setTaxOverrideEntitlementsFor("wc_billingo_tax_override_zero");
    jQuery("#wc_billingo_tax_override_zero").change(function (e) {
        setTaxOverrideEntitlementsFor("wc_billingo_tax_override_zero");
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
            note: note,
            deadline: deadline,
            completed: completed,
            invoice_type: invoice_type,
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
});
