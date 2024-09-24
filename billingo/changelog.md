# Changelog
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
