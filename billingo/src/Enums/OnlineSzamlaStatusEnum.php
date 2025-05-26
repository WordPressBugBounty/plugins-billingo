<?php

namespace App\Billingo\Enums;

enum OnlineSzamlaStatusEnum: string
{
    case ABORTED = 'aborted';
    case DONE = 'done';
    case DONT_SEND_EXTERNAL_IMPORT = 'dont_send_external_import';
    case DONT_SEND_FOREIGN = 'dont_send_foreign';
    case DONT_SEND_OSS = 'dont_send_oss';
    case DONT_SEND_OTHER = 'dont_send_other';
    case EMPTY_ORGANIZATION_COUNTRY_CODE = 'empty_organization_country_code';
    case EMPTY_PARTNER_COUNTRY_CODE = 'empty_partner_country_code';
    case EMPTY_TAX = 'empty_tax';
    case FORBIDDEN = 'forbidden';
    case INVALID_ADDRESS = 'invalid_address';
    case INVALID_CLIENT = 'invalid_client';
    case INVALID_CONVERSION_RATE = 'invalid_conversion_rate';
    case INVALID_CUSTOMER = 'invalid_customer';
    case INVALID_INVOICE_REFERENCE = 'invalid_invoice_reference';
    case INVALID_POSTALCODE = 'invalid_postalcode';
    case INVALID_REQUEST_SIGNATURE = 'invalid_request_signature';
    case INVALID_SECURITY_USER = 'invalid_security_user';
    case INVALID_TAX = 'invalid_tax';
    case INVALID_TAX_NUMBER = 'invalid_tax_number';
    case INVALID_USER_RELATION = 'invalid_user_relation';
    case INVALID_VAT_DATA = 'invalid_vat_data';
    case INVOICE_NUMBER_NOT_UNIQUE = 'invoice_number_not_unique';
    case KOBAK_PROCESSING = 'kobak_processing';
    case MISSING_DOCUMENT_ITEM_NAME = 'missing_document_item_name';
    case NAV_WARN = 'nav_warn';
    case NO_ONLINE_SZAMLA_SETTINGS = 'no_online_szamla_settings';
    case NO_SEND_BY_USER = 'no_send_by_user';
    case NON_EXIST_TAX_NUMBER = 'non_exist_tax_number';
    case NOT_UNIQUE = 'not_unique';
    case NOT_CHECKED = 'not_checked';
    case NOT_REGISTERED_CUSTOMER = 'not_registered_customer';
    case PROCESSING = 'processing';
    case RECEIVED = 'received';
    case SAVED = 'saved';
    case SEND_FAILED = 'send_failed';
    case SENT = 'sent';
    case STARTED = 'started';
    case TECHNICAL_ERROR = 'technical_error';
    case UNDER_TAX_LIMIT = 'under_tax_limit';
    case USER_HAS_INVALID_KOBAK = 'user_has_invalid_kobak';
    case USER_HASNOT_KOBAK = 'user_hasnot_kobak';
    case VALIDATION_OK = 'validation_ok';
}
