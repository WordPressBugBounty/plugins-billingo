<?php

namespace App\Billingo\Api;

use App\Billingo\Api\Queries\CurrencyQuery;
use App\Billingo\Enums\HttpMethodEnum;
use App\Billingo\Models\Currency\ConversationRate;

class CurrencyApi extends BillingoApi
{
    const PREFIX = 'currencies';
    const DEFAULT_CONVERT_CLASS = ConversationRate::class;

    protected static string $convertTo = self::DEFAULT_CONVERT_CLASS;

    /**
     * This method creates and returns a new instance of the DocumentQuery class,
     * which can be used to query currencies.
     * @return CurrencyQuery
     */
    public function query(): CurrencyQuery
    {
        return new CurrencyQuery();
    }

    /**
     * Return with the exchange value of given currencies.
     * @param  array  $content
     * @return CurrencyApi
     */
    public function getConversationRate(array $content): self
    {
        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX)
            ->setContent($content);

        return $this->send();
    }
}
