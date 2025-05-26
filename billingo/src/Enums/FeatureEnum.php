<?php

namespace App\Billingo\Enums;

enum FeatureEnum: string
{
    case API_LIMIT_BASIC = 'api_limit_basic';
    case API_LIMIT_MAX = 'api_limit_max';
    case API_LIMIT_PRO = 'api_limit_pro';
    case API_LIMIT_PRO_LIMITED = 'api_limit_pro_limited';
    case API_LIMIT_STANDARD = 'api_limit_standard';
    case BANKSYNC_PLUS = 'banksync_plus';
    case BILLINGO_BUSINESS = 'billingo_business';
    case BILLINGO_ONE = 'billingo_one';
    case CAMPAIGN_MANAGER_BASIC = 'campaign_manager_basic';
    case CAMPAIGN_MANAGER_STANDARD = 'campaign_manager_standard';
    case CEGINFO_ENTERPRISE = 'ceginfo_enterprise';
    case CEGINFO_PRO = 'ceginfo_pro';
    case CEGINFO_STANDARD = 'ceginfo_standard';
    case FLAT_TAX_PLUS = 'flat_tax_plus';
    case INNOVATOR = 'innovator';
    case INVENTORY = 'inventory';
    case LIMIT_INCREASE = 'limit_increase';
    case MASTER = 'master';
    case PARTNERMONITOR_BASIC = 'partnermonitor_basic';
    case PARTNERMONITOR_PRO = 'partnermonitor_pro';
    case PARTNERMONITOR_STANDARD = 'partnermonitor_standard';
    case SOFTPOS = 'softpos';
    case SUBSCRIPTION_BASIC = 'subscription_basic';
    case SUBSCRIPTION_PRO = 'subscription_pro';
    case SUBSCRIPTION_STANDARD = 'subscription_standard';
    case TENDERMONITOR_STANDARD = 'tendermonitor_standard';
    case TENDERMONITOR_VIP = 'tendermonitor_vip';
    case WHITELABEL = 'whitelabel';
}
