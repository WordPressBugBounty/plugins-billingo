<?php

namespace App\Billingo\Api;

use App\Billingo\Api\Queries\PartnerQuery;
use App\Billingo\Enums\HttpMethodEnum;
use App\Billingo\Exceptions\BadContentException;
use App\Billingo\Models\Partner\Partner;

class PartnerApi extends BillingoApi
{
    const PREFIX = 'partners';
    const DEFAULT_CONVERT_CLASS = Partner::class;

    protected static string $convertTo = self::DEFAULT_CONVERT_CLASS;

    /**
     * @return PartnerQuery
     */
    public function query(): PartnerQuery
    {
        return new PartnerQuery();
    }

    /**
     * Returns a list of your partners.
     * The partners are returned sorted by creation date, with the most recent partners appearing first.
     * @param  array  $query
     * @return self
     */
    public function getAll(array $query = []): self
    {
        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX)
            ->setContent($query);

        return $this->send();
    }

    /**
     * Create a new partner. Returns a partner object if the creation is succeeded.
     * @param  array|Partner  $partner
     * @return self
     * @throws BadContentException
     */
    public function create(array|Partner $partner): self
    {
        if (is_array($partner)) {
            $partner = new Partner($partner);
        }

        if ($partner->hasError()) {

            throw new BadContentException($partner->getSelfTest());
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::POST)
            ->setUrl(self::PREFIX)
            ->setContent($partner->toArray());

        return $this->send();
    }
}
