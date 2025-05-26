<?php

namespace App\Billingo\Enums\Document;

enum LanguageEnum: string
{
    case DE = 'de';
    case EN = 'en';
    case FR = 'fr';
    case HR = 'hr';
    case HU = 'hu';
    case IT = 'it';
    case RO = 'ro';
    case SK = 'sk';
    case US = 'us';

    public function getReadableText(): string
    {
        return match ($this) {
            self::DE => trans('language.de'),
            self::EN => trans('language.en'),
            self::FR => trans('language.fr'),
            self::HR => trans('language.hr'),
            self::HU => trans('language.hu'),
            self::IT => trans('language.it'),
            self::RO => trans('language.ro'),
            self::SK => trans('language.sk'),
            self::US => trans('language.us'),
        };
    }
}
