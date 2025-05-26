<?php

namespace App\Billingo\Validation\DocumentExport;

use App\Billingo\Models\DocumentExport\LedgerNumberInformation;
use App\Billingo\Validation\Validator;

class DocumentExportFilterExtraValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'tensoft_vkod' => ['optional', 'string'],
            'ledger_number' => ['optional', ['instanceOf', LedgerNumberInformation::class]],
            'forintsoft_konyvelesi_naplo_szam' => ['optional', 'string'],
            'positive_ledger_number' => ['optional', 'string'],
            'negative_ledger_number' => ['optional', 'string'],
            'rlb_kata' => ['optional', 'boolean'],
            'rlb_note' => ['optional', 'string'],
            'novitax_naplokod' => ['optional', 'string'],
            'use_gross_values' => ['optional', 'boolean'],
        ];
    }
}
