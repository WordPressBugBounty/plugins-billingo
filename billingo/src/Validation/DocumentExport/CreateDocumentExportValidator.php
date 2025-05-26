<?php

namespace App\Billingo\Validation\DocumentExport;

use App\Billingo\Enums\DocumentExport\DocumentExportOtherOptionsEnum;
use App\Billingo\Enums\DocumentExport\DocumentExportQueryTypeEnum;
use App\Billingo\Enums\DocumentExport\DocumentExportSortByEnum;
use App\Billingo\Enums\DocumentExport\DocumentExportTypeEnum;
use App\Billingo\Enums\PaymentMethodEnum;
use App\Billingo\Models\DocumentExport\DocumentExportFilterExtra;
use App\Billingo\Validation\Validator;

class CreateDocumentExportValidator extends Validator
{

    protected function rules(): array
    {
        return [
            'query_type' => ['required', ['in', getEnumValues(DocumentExportQueryTypeEnum::class)]],
            'start_date' => ['required', ['dateFormat', 'Y-m-d']],
            'end_date' => ['required', ['dateFormat', 'Y-m-d']],
            'document_block_id' => ['optional', 'integer'],
            'export_type' => ['required', ['in', getEnumValues(DocumentExportTypeEnum::class)]],
            'number_start_year' => ['optional', 'integer'],
            'number_start_sequence' => ['optional', 'integer'],
            'number_end_year' => ['optional', 'integer'],
            'number_end_sequence' => ['optional', 'integer'],
            'payment_method' => ['optional', ['in', getEnumValues(PaymentMethodEnum::class)]],
            'sort_by' => ['optional', ['in', getEnumValues(DocumentExportSortByEnum::class)]],
            'other_options' => ['optional', ['in', getEnumValues(DocumentExportOtherOptionsEnum::class)]],
            'filter_extra' => ['optional', ['instanceOf', DocumentExportFilterExtra::class]],
        ];
    }
}
