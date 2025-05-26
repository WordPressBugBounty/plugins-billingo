<?php

namespace App\Billingo\Enums\DocumentExport;

enum DocumentExportTypeEnum: string
{
    case ARMADA = 'armada';
    case AWS_BATCH = 'aws_batch';
    case EX_PANDA = 'ex_panda';
    case FORINTSOFT = 'forintsoft';
    case HESSYN = 'hessyn';
    case IMA = 'ima';
    case INFOTEKA = 'infoteka';
    case KULCS_KONYV = 'kulcs_konyv';
    case MAXITAX = 'maxitax';
    case NAGY_MACHINATOR = 'nagy_machinator';
    case NAV_PTGSZLAH = 'nav_ptgszlah';
    case NAV_STATUS = 'nav_status';
    case NAV_XML = 'nav_xml';
    case NAV_XML_ALIAS = 'nav_xml_alias';
    case NOVITAX = 'novitax';
    case PROFORMA_OUTSTANDING = 'proforma_outstanding';
    case RELAX = 'relax';
    case RLB = 'rlb';
    case RLB60 = 'rlb60';
    case RLB_DOUBLE_ENTRY = 'rlb_double_entry';
    case SIMPLE_CSV = 'simple_csv';
    case SIMPLE_EXCEL = 'simple_excel';
    case SIMPLE_EXCEL_ITEMS = 'simple_excel_items';
    case TENSOFT = 'tensoft';
    case TENSOFT_29_DOT_65 = 'tensoft_29_dot_65';
}
