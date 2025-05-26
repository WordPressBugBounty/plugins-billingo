<?php

namespace App\Billingo\Enums\DocumentExport;

enum DocumentExportStatusStateEnum: string
{
    case FAIL = 'fail';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case SUCCESS = 'success';
    case WARNING = 'warning';
}
