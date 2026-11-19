# Changelog
-**4.3.5** - 2026.10.
* New feature: „Advance invoice” column on the E-mail settings tab - the advance invoice can now be sent through Billingo, attached to the WooCommerce e-mail (on-hold / processing order) or both, with its own button text and message, like the invoice, proforma and cancellation
* New feature: invoicing can be switched off per order - toggle in the Billingo box on the order page, saved instantly; while off, no document is created for the order, neither automatically (status change, proforma) nor manually; an already issued invoice can still be cancelled, and the change is recorded as an order note
* New feature: „Transaction fees get the VAT rate of the order products” switch in the VAT settings – when on, fees (e.g. cash-on-delivery fee) get the VAT rate of the order products (the highest one if there are several), the amount the customer paid stays the same; it beats the manually chosen fee rate
* New feature: „Separate VAT rate for products with given SKUs” switch in the VAT settings – items with the given SKUs (e.g. DRS; comma separated, case-insensitive, the * wildcard is supported) get the chosen rate / entitlement (e.g. TAM) regardless of the general VAT override
* New feature: automatic "Contains mediated service" flag (togglable) - checked when the invoice contains a positive shipping fee, left unchecked for free shipping
* New feature: "Shipping price on the invoice" setting (gross / net) in the VAT settings, replacing the old "Shipping price is entered net" switch; WooCommerce always stores the shipping amount without tax, so in gross mode (default) the invoice carries what the customer paid (amount + VAT), in net mode the amount without tax as a net item; the same applies to shipping coupons
* New feature: "Show tax number field at checkout" switch - the tax number field on the classic checkout never actually worked (its hooks were never registered) and the entered tax number never reached the invoice; it is now sent as the partner's tax number to Billingo (stored through HPOS-compatible order meta)
* New feature: "Fill company data from the tax number" (togglable) - after typing the tax number, the checkout shows the NAV validation result and fills the still empty fields with the name and address of the partner already stored in Billingo (Billingo returns no company data for unknown tax numbers); the endpoint is available to guests with a nonce, rate limiting and a response limited to the company name and address
* Critical fix: VAT rates are now taken from the tax WooCommerce actually charged on the order lines (products, shipping, COD/fee lines, shipping coupons) instead of being guessed from the tax class and billing country; shipping-address based taxation, compound taxes and multi-rate orders are handled correctly
* Critical fix: product sync no longer overwrites Billingo-managed stock management (is_manage), purchase price, net unit price, entitlement and ledger data on existing products (the PUT update used to switch off stock management and zero the purchase price)
* Fix: when document creation stops (e.g. invoicing switched off or an unsupported VAT rate), the "mark as paid" setting no longer causes a fatal error
* Fix: with „VAT override” switched off, a leftover enabled „Apply to shipping too” option invoiced shipping at 0% VAT (the shipping VAT was missing from the invoice); shipping now gets an overridden rate only when the override itself is on
* Fix: with the „Transaction fee VAT rate” setting the invoice carried the fee net amount instead of the gross the customer paid (the invoice total was lower than the order); the amount paid (net + the VAT WooCommerce charged) is now invoiced and only the VAT key changes
* Fix: on the Payment methods page the „-- Select! --” (no mapping) option saved its label text as the value, so invoicing stopped with the Billingo error „Payment Method contains invalid value” for orders of that payment method; an empty value is saved now and an already saved invalid value is ignored (automatic mapping / fallback payment method applies)
* Fix: with „VAT override” on, the „Transaction fee VAT rate” setting was ignored (e.g. the 10% key meant for products also set the cash-on-delivery fee to 10% although 21% was configured); the fee key now beats the general override, without a fee key the override still applies to the fee
* Fix: a final invoice after an advance invoice always uses the advance invoice partner; Billingo does not list a newly created partner for a few seconds, so a quick advance → final flow created a duplicate partner and Billingo rejected using the advance („Can not use advance(s) … has descendant(s)”)
* Fix: the discount of a coupon recognised as a „shipping coupon” was deducted twice (in the product „- discount” lines and again as a separate line); the separate line is only added when the discount is not already on the product items
* Fix: when WooCommerce recorded no tax on the shipping, the fallback VAT rate is looked up at the order tax address (before: the signed-in admin customer data, so manual and automatic invoicing could give different rates for the same order)
* Fix: an order shipped outside the EU on which WooCommerce charged no tax gets the EUK VAT code with the EAM entitlement on the invoice (before: a 27% / 10% VAT guessed from the tax class although the customer paid without VAT); the tax (shipping) address decides, a tax-exempt flagged non-EU customer is an export too; with tax calculation off (manual orders) the old logic is kept; Billingo accepts only the EAM entitlement with the EUK code, which the 0% override and the SKU rate send as well
* Fix: the fallback VAT rate of a transaction fee (when WooCommerce recorded no tax) is the rate of the order tax country, not the first row of the rate table (before: e.g. a foreign 0% rate landed on a domestic fee)
* Fix: when WooCommerce recorded tax rows on the order (even with a 0% rate), a line left without a rate is invoiced as exempt instead of with a rate guessed from the tax class
* Fix: with „Show discount as a separate item” on, a sale-price product was discounted twice (25,000 Ft product on sale for 17,500 Ft: the line already carried the sale price and the discount line deducted the difference again, so the invoice total was lower than the order); the line now carries the regular price and the discount line deducts the difference
* Fix: the cash-on-delivery (fee) amount was grossed up with VAT again when tax calculation is off or prices are entered without tax together with an exempt (e.g. AAM) VAT override (513 Ft became 651 Ft on the invoice); the fee is only grossed up when the shop really calculates tax on it
* Fix: the „Use decimals on invoice” setting being off no longer rounds other currencies (e.g. EUR) to whole amounts (12.34 EUR was invoiced as 12 EUR); the whole-amount rounding applies to HUF only
* Fix: the invoice total rounding („to 5 / 10 / 1”) is only applied to HUF; with another currency (e.g. EUR) it rounded the total to whole 5 / 10 units (a 33.15 EUR order was invoiced as 35.00 EUR)
* Fix: with „Automatic invoicing: No” every order reaching the invoicing status got a misleading „Billingo: the document was not created – no valid document type selected” order note; the deliberately switched-off automatic invoicing is now skipped silently
* Fix: partners without a tax number are created with the „Natural person without tax number” (NO_TAX_NUMBER) type (foreign ones with FOREIGN), so the partner tax type is no longer empty on the Billingo draft; the type is only set at creation, existing partners are still found as before
* Fix: the admin order list status styling is limited to WooCommerce's own status mark (mark.order-status), so other plugins' .order-status elements are no longer restyled
* Fix: the final invoice after an advance invoice was rejected by Billingo („Given Document and Advance Document VAT types do not match”) when the order was saved on the WooCommerce admin order page and contained a fee WooCommerce does not tax (e.g. a VAT-exempt shipping / handling fee); saving there writes an empty tax entry for the untaxed fee, which was read as a 27% rate while the advance invoice carried the fee as exempt; an empty tax entry is no longer treated as a tax rate (an explicit 0.00 amount still is)
* Change: the „Test mode” description is explicit now - it is not a test Billingo account (invoices go to the account of the API key), and the only thing it does is padding a missing / too short (under 2 characters) billing street address with 2 random characters so Billingo does not reject it; leave it off on a live shop
* Fix: the tax number of the „custom meta field” setting did not reach the invoice when the field is stored without the leading underscore (e.g. billing_adoszam saved as billing_adoszam, as checkout field editor plugins do); only the underscore form (_billing_adoszam) was read, so the partner had no tax number; both forms of the entered key are checked now, the underscore one first, so existing setups keep working
* Fix: setting a field on a document model after creation (e.g. the entitlement of VAT-exempt items) was silently ignored when the value was null or missing
* Fix: PHP 8.4 / 8.5 compatibility - removed the "Implicitly marking parameter as nullable is deprecated" notices (output notices could also break AJAX responses)
* Fix: untaxed (not taxed by WooCommerce) COD fees and shipping no longer get a standard VAT rate; the exempt code from the "0% VAT override entitlement" setting is used, and the fee amount is no longer inflated by a wrongly applied VAT
* Fix: if the VAT rate charged by WooCommerce is not among Billingo's supported rates (e.g. 13.5%), document generation stops and an order note explains why, instead of silently falling back to 0% / 27%
* Fix: shipping line VAT rate was always 27% instead of the destination country's rate (WooCommerce shipping tax data was misread)
* Fix: VAT-exempt (reverse-charge) EU company orders now use the "EU" VAT code with the "KBAET" entitlement instead of a rate recalculated from the product's tax class
* Fix: the 0% VAT override no longer sends the entitlement (e.g. KBAET) as a VAT code; it is sent as an entitlement instead
* Fix: partners with a foreign (non-Hungarian) tax number are created with the "Foreign" (FOREIGN) type, so Billingo accepts the invoice
* Fix: text VAT codes (EU, AM, TAM...) and decimal-comma rates (e.g. 5,5%) are handled correctly in VAT rate calculation
* Fix: the document-generation hooks (status change, proforma) can no longer cause a fatal error while saving an order; on failure an order note records the reason
* Fix: if the currency conversion rate cannot be determined, generation stops (with an order note) instead of creating a document with a wrong rate
* Fix: only an invoice can be converted from a proforma; requesting a draft, advance invoice or another proforma no longer silently becomes a real invoice
* Fix: no proforma is created for an order after its invoice was issued; zero-quantity line items are left out of the document
* Fix: the payment check no longer touches orders already in a final status, and checks the correct document for advance invoices
* Fix: unchanged bank account and invoice block ids are kept when saving the settings; multi-line invoice notes are no longer mangled
* Fix: a manually entered cancellation id no longer overwrites another row of the local records; the cancelled row can be read back after a database update
* Fix: for variations, the product-level document type and VAT overrides also consider the parent product's setting
* Fix: the default of the 0% VAT override switch now matches what the settings page shows; the manual-generation flag is read from order meta (HPOS-safe)
* Fix: product sync no longer crashes on older WooCommerce versions that lack the global unique id (EAN) getter
* Fix: an empty or malformed (non-JSON) Billingo response no longer causes a fatal error (TypeError); a non-JSON, non-PDF response (e.g. a maintenance page) is reported as an error instead of a bogus empty successful document
* Fix: after a PDF download, later API calls no longer carry an "Accept: application/pdf" header; the connection test uses the API key entered on the settings page (not yet saved) instead of the previously used one
* Fix: the readable name of "Advance invoice" (and every other document type) was missing, so the order page failed when the default manual document type was set to advance invoice; a decimal-comma 5,5% VAT rate is no longer truncated to 5%
* Fix: for taxed shipping, with the old switch off the shipping line was short by the VAT amount on the invoice
* Fix: the static conversion setting of the API classes can no longer leak from one call into the next (after a validation exception or a failed response the following call's response was converted into an empty, wrong-type model)
* Fix: the document table schema is now brought up to date by version on every site (including multisite subsites) and after every update; previously a network activation left subsites without the table, so the local record of an invoice created on Billingo was silently not saved
* Fix: Billingo API calls now have timeouts (5 s connect, 30 s response), so a Billingo outage cannot freeze checkout and admin pages
* Hardening: requests to Billingo no longer follow redirects (the API key header must not be forwarded to another host); invalid UTF-8 characters are substituted in the request body
* Security fix: log files are no longer reachable from the web - they are stored in an unguessable folder under the uploads directory (with .htaccess, web.config and index.php protection, also safe on nginx), existing logs are moved automatically, and logs are deleted on uninstall; the Support tab no longer shows guard files as logs
* Security fix: document rows no longer store the full API key, only its irreversible fingerprint; keys stored earlier are replaced automatically
* Change: the minimum required PHP version is now 8.2
* Change: the invoice settings tab is clearer: split into collapsible sections (basics, invoicing and automation, invoice language, notes and items, tax number and customer data, test mode and multisite); one section is open at a time (the „Open all” and „Close all” buttons handle all of them), two columns on wide screens; the open section is remembered in the browser. Long descriptions in the right column no longer overflow their row
* Change: HPOS (custom order tables) compatibility declaration; the original-prices flag now uses the order meta API
-**4.3.4** - 2026.08.
* New feature: automatic linking of advance invoice and final invoice (togglable) - when the order reaches the invoicing-activation status, an advance invoice is issued; once the order reaches the target status configured for its payment method's payment status check, the final invoice is generated automatically and linked to the advance invoice on Billingo
* New feature: automatic and manual payment status check against Billingo, configurable per payment method with its own target status, plus a manual "Check payment" button on the order page
* New feature: manual Billingo document ID lookup and reconciliation (togglable) - lets you match up and sync a document that was already created on Billingo (e.g. after a "duplicate vendor id" error) but never saved locally
* New feature: VAT override for the payment/shipping fee line as well, aware of whether WooCommerce prices are set as gross or net (togglable)
* New feature: EUK VAT code support in both VAT override settings
* New feature: multisite support - per-site identifier included in the vendor_id sent to Billingo (togglable)
* New feature: the customer's checkout note can be allowed to override the default invoice note (togglable) - by default the shop's fixed invoice note always appears on the invoice, even if the customer entered their own note on the order; when enabled, the customer's note overrides the default text as before
* New feature: skip invoicing for 0 Ft orders (togglable setting)
* New feature: currency-dependent invoice language (currency to language mapping)
* New feature: live NAV tax number validation at checkout (togglable setting)
* New feature: partner tax type automatically set to company when a tax number is provided
* New feature: per-product document type override (invoice/proforma/draft/advance selectable per product). Priority order: 1) manually selected type on the order page, 2) product-level override, 3) normal automatic/manual default setting
* Vendor_id logic changed so re-invoicing after a cancelled (stornó) invoice is no longer blocked by Billingo
* Product original price meta is still saved but no longer shown in order details
* Critical fix: fatal error on validation failure (SelfTest object misuse)
* Critical fix: fatal error on nested document model self-check (private trait method)
* Fix: fatal error on network errors during API communication
* Fix: invalid VAT override setting now stops document generation with an admin warning, instead of creating a wrong invoice
* Security fix: privilege escalation in email settings save
* Fix: proforma-to-invoice conversion (was always creating a new invoice instead of converting)
* Fix: cancellation (stornó) AJAX fatal error on missing data
* Fix: log directory is no longer publicly accessible over the web
* Fix: tax number check and legacy ID API endpoint URLs
* Fix: several validation/model field-name bugs (partner, item, address)
* Fix: product sync pagination (duplicate products created above 100 items)
* Fix: "Billingo" tab label readability on the WooCommerce Settings page

-**4.3.2** - 2026.05.
* Fees tax woo update
* WP 7.0 compatible changes

-**4.3.1** - 2026.05.
* Fees tax woo update
* WP 7.0 compatible changes

-**4.3.0** - 2026.01.
* Email disable bug solved
* Fee tax update
* Variable product sku to comment field

-**4.2.9** - 2025.12.18
* Vendor_id usage logic has changed.

-**4.2.8** - 2025.12.15
* New feature add shipping address to documents comment field
* Create documents on order language update

-**4.2.7** - 2025.11.17
* Tax class modification
* Design update
* Logo bug fix

-**4.2.6** - 2025.11.10
* Item discount update
* Add new settings Decimals
* Add new feature Test mode (address valitdation turn off in the test mode)
* Email notification fix
* NAV warning check update
* Label fix for payment depend invoice turn off
* Already cancelled invoice management

-**4.2.5** - 2025.09.16
- HuCommerce detect fix
- Payment depend invoice turn off

-**4.2.4** - 2025.07.16
- vat check

-**4.2.3** - 2025.07.16
- Add negative fee logic
- Add fee tax check
- item discount update

-**4.2.2** - 2025.07.08
- extra fees update (if vat is not set in woocommerce) 
- Dataerasecode fix
- Only add the vendor ID to the invoice.

-**4.2.1** - 2025.07.02
- Fees problem
- Order comment

-**4.2.0** - 2025.06.20
- item discount selectable.

-**4.1.4** - 2025.06.04
- Add module compatibility

-**4.0.5** - 2025.05.28
- Bugfix Tax override problem

-**4.0.0** - 2025.05.21
- Új SDK
- Új layout
- További részletek itt : https://support.billingo.hu/content/2996830214

-**3.6.4** - 2024-09-24
- Added data erease code fix. glodal attribute

-**3.6.3** - 2024-08-12
- Added data erease code fix.


-**3.6.2** - 2024-08-12
  - Added data erease code.

-**3.6.0** - 2023-12-01
  - Compatibility with WooCommerce's High-Performance Order Storage

- **3.5.0** - 2023-10-09
  - Experimental release of a function that tracks product ids in Billingo
  - round only to 6 digits instead of full to preserve prices
  - If a bank_account or invoice_block is missing when checking the settings, it will be reset to default as shown without needing an update

- **3.4.7** - 2023-10-08
  - Fix item price rounding

- **3.4.6** - 2023-06-26
  - Improved tax number retrieval (prioritiy order meta, ignore guest user)

- **3.4.5** - 2023-05-04
  - Option to change default behaviour of manual invoice generation to make draft or proforma

- **3.4.4** - 2023-04-24
  - Fix tax entitlement getting stuck

- **3.4.3** - 3024-04-04
  - Fix shipping tax compatibility issue where there was an empty entry with the wrong tax type. (Encountered with FoxPost plugin.)

- **3.4.2** - 2023-03-22
  - Fix line tax issue where it's below 0.5 (woocommerce rounds it down to 0)

- **3.4.1** - 2023-01-10
  - PHP version compatibility fix

- **3.4.0** - 2022-09-16
  - Security update

- **3.3.9** - 2022-07-28
  - Option to enable the "send via Billingo" option for drafts
  - PDF download function included in library

- **3.3.8** - 2022-06-15
  - Option to overwrite the unit parameter

- **3.3.7** - 2022-05-27
  - Fix error caused by missing Woocommerce when updating

- **3.3.6** - 2022-03-12
  - Fix log directory missing on new/clean installs issue

- **3.3.5** - 2022-02-23
  - Prevent invoice/proforma generation when order is opened multiple times

- **3.3.4** - 2022-01-24
  - Added option to ignore previous proforma when manually generating invoice
  - Tested with WP 5.9
  - Added plugin version to admin JS to help with cache

- **3.3.3** - 2022-01-13
  - Fix -0% tax for discount

- **3.3.2** - 2021-11-22
  - Fix entitlement dropdown resetting issue

- **3.3.1** - 2021-11-16
  - Improved shipping tax detection
  - Add trim to company name to prevent " " names...

- **3.3.0** - 2021-10-20
  - Added option to select bank account for HUF and EUR
  - Invoice Block now can be selected instead of having to copy over the ID
  - Can mark paid invoices as "without_financial_fulfillment", draft invoice uses the paid settings from real ones

- **3.2.7** - 2021-09-24
  - Switched to wp_date() from date() to prevent timezone related issues messing with dates (01:15 CEST is 23:15 UTC previous day)
  - Moved logs to wp-content/uploads/billingo to prevent deletion when updating the plugin

- **3.2.6** - 2021-09-21
  - WooCommerce Subscriptions email attachment compatibility

- **3.2.5** - 2021-08-11
  - Fix get_plugin_data() function missing sometimes

- **3.2.4** - 2021-08-03
  - Added 9.5% VAT support

- **3.2.3** - 2021-07-07
  - Fix admin order list proforma display

- **3.2.2** - 2021-07-02
  - Add variant info to product name

- **3.2.1** - 2021-06-01
  - Self-repair issue caused by wordpress plugin update

- **3.2.0** - 2021-05-21
  - Fix automatic generation not making draft invoice if set
  - Added draft option to manual invoice generation

- **3.1.0** - 2021-04-21
  - Add API KEY to partner storage, to prevent 403 errors after api key change
  - Fees net/gross price handling improvement
  - Shipping tax fixed
  - Option to block child order invoicing

- **3.0.0** - 2021-04-16
  - Replace swagger "SDK" with simple library, prevent composer conflicts and bugs
  - Improve tax rate calculation

- **2.6.0** - 2021-04-12
  - If proforma exists, invoice generation happens through createDocumentFromProforma

- **2.5.1** - 2021-03-30
  - Fix fee tax type

- **2.5.0** - 2021-02-16
  - Billingo API update to 3.0.13
  - Add tax entitlement

- **2.4.0** - 2021-02-16
  - Billingo API update to 3.0.12
  - Fix partner duplication

- **2.3.2** - 2021-02-04
  - Fix fulfillment_date
  - Fix custom order state compatibility

- **2.3.1** - 2021-01-05
  - Fix some missing translations

- **2.3.0** - 2020-11-24
  - E-mail sending reworked, fixed duplication on mass edit and missing buttons
  - Storno generation fixed (conversion had wrong condition)

- **2.2.0** - 2020-10-29
  - Billingo API update to 3.0.10
  - Convert V2 invoice ID if doing storno on V3 API
  - Option to display carrier even if cost is 0
  - Fix fallback payment method
  - Selectable fallback payment method
  - Fix division by zero on 0 value fees
  - Fix incorrect links in AJAX response

- **2.1.1** - 2020-10-19
  - SKU support for variable products

- **2.1.0** - 2020-10-15
  - E-mail settings overhaul, now works with proforma and storno emails, too
  - Compatibility with order numbering plugins
  - Integrated 1.10.0

- **2.0.4** - 2020-10-08
  - HuCommerce tax number detection improvement

- **2.0.2** - 2020-09-28
  - Allow decimal quantity

- **2.0.1** - 2020-09-23
  - Fix tax override issue

- **2.0.0** - 2020-07-22
  - Updated to use API v3
  - Updated VAT Field display and notification threshold

- **1.10.0** - 2020-09-03
  - Improved shipping tax detection
  
- **1.9.9** - 2020-07-22
  - Improved log folder security.

- **1.9.8** - 2020-07-02
  - Fixed display "Download invoice" button on completed e-mails

- **1.9.7** - 2020-06-29
  - Always show tax number field, if enabled.
  - Allow float quantity
  - Payment method fallback to cash, if not paired
  - Added link to settings from plugin list page
  - Added notice to get reviews
  - Fixed deprecated function

- **1.9.6** - 2020-06-24
  - HuCommerce plugin tax number support 

- **1.9.5** - 2020-06-23
  - Added address2 to customer address

- **1.9.4** - 2020-06-05
  - Fixed EU/EUK mapping (exclude Hungary)

- **1.9.3** - 2020-05-19
  - Email button changeable via settings

- **1.9.2** - 2020-05-07
  - Email button translatable via WP

- **1.9.1** - 2020-04-02
  - Product tax handling improvement

- **1.9.0** - 2020-03-22
  - Storno option (auto and manual)
  - Configurable which order state generates invoice

- **1.8.8** - 2020-03-16
  - payment requests "mark as paid" handled separately

- **1.8.7** - 2020-02-11
  - Compatibility for only one country setup

- **1.8.6** - 2020-01-17
  - option to add SKU to item comment
  
- **1.8.5** - 20??-??-??
  - ?

- **1.8.4** - 2019-11-07
  - Wordpress security compliance
  - Unified text domain

- **1.8.3** - 2019-11-06
  - Fix tax rate matching for free shipping

- **1.8.2** - 2019-11-05
  - Disable API requests until public/private keys are set
  - Added support email address

- **1.8.1** - 2019-10-07
  - Fixed a PHP Warning when API keys were missing
  - Search taxcode field in order, if not found in client.

- **1.8.0** - 2019-09-26
  - Fixed tax value issue
  - Code optimization and library update

- **1.7.0** - 2019-09-11
  - Display coupon discounts as line comments

- **1.6.6** - 2019-08-26
  - Fix time difference issues

- **1.6.5** - 2019-08-22
  - taxcode only if not empty

- **1.6.4** - 2019-08-15
  - EU/EUK tax overrides

- **1.6.3** - 2019-07-25
  - Draft options

- **1.6.2** - 2019-07-24
  - Added more languages
  - WPML order language detection

- **1.6.1** - 2019-07-16
  - Option to add Barion Transaction ID and Order ID to Note
  - Option to use custom meta field for tax number

- **1.6.0** - 2019-07-05
  - Includes "fix" (bypass) for wrong server time issue
  - Logging always enabled
  - Option to exclude shipping from global tax override
  - Option to override tax if it's 0%
  - Debug code for support

- **1.5.0** - 2019-03-27
  - Option to send net prices
  - Tax calculation improvements

- **1.4.4** - 2019-02-03
  - WooCommerce >3.0 update

- **1.4.3** - 2019-01-27
  - Fix "flip name" option
  - Better logging
  
- **1.4.2** - ?

- **1.4.1** - 2018-12-06
  - Fix for incompatible woocommerce-order-s plugin

- **1.4.1** - 2018-11-28
  - Fix email button

- **1.4.0** - 2018-11-22
  - Option to add download link to woocommerce completed email
  - Proforma toggle for each payment method
  - Duplicate protection for proforma generation and invoice generation

- **1.3.2** - 2018-10-10
  - Some error handling

- **1.3.1** - 2018-09-14
  - Option to use only company name or company name + customer name

- **1.3.0** - 2018-09-04
  - Option to flip name (firstname/lastname)
  - Configure payment methods to mark the invoice as paid
  - Separated settings

- **1.2.5** - 2018
  - tax override

- **1.2.4** - 2018-04-22
  - Configurable rounding

- **1.2.3** - 2018-04-12
  - Use order currency
  - Include discounts in invoice
