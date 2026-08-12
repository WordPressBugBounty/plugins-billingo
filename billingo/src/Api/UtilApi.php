<?php

namespace App\Billingo\Api;

use App\Billingo\Enums\HttpMethodEnum;
use App\Billingo\Exceptions\BadContentException;
use App\Billingo\Models\Util\Id;
use App\Billingo\Models\Util\ServerTime;
use App\Billingo\Models\Util\TaxNumber;

class UtilApi extends BillingoApi
{
    const PREFIX = 'utils';
    const DEFAULT_CONVERT_CLASS = '';

    protected static string $convertTo = self::DEFAULT_CONVERT_CLASS;

    /**
     * Check the given tax number format, and NAV validate.
     * @param  string  $taxNumber
     * @return self
     * @throws BadContentException
     */
    public function checkTaxNumber(string $taxNumber): self
    {
        self::$convertTo = TaxNumber::class;

        $validationCheck = new TaxNumber(['tax_number' => $taxNumber]);

        if ($validationCheck->hasError()) {

            throw new BadContentException($validationCheck->getSelfTest());
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX . "/check-tax-number/{$taxNumber}");

        return $this->send();
    }

    /**
     * Retrieves the API v3 ID.
     * @param  int  $id
     * @return self
     */
    public function convertToV3Id(int $id): self
    {
        self::$convertTo = Id::class;

        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX . "/convert-legacy-id/{$id}");

        return $this->send();
    }

    /**
     * Return the server time.
     * @return self
     */
    public function getServerTime(): self
    {
        self::$convertTo = ServerTime::class;

        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX . '/time');

        return $this->send();
    }
}
