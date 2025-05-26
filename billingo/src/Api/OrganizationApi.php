<?php

namespace App\Billingo\Api;

use App\Billingo\Enums\HttpMethodEnum;
use App\Billingo\Models\Organization\OrganizationData;

class OrganizationApi extends BillingoApi
{
    const PREFIX = 'organization';
    const DEFAULT_CONVERT_CLASS = OrganizationData::class;

    protected static string $convertTo = self::DEFAULT_CONVERT_CLASS;

    /**
     * Retrieves the data of organization.
     * @return self
     */
    public function getAll(): self
    {
        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX);

        return $this->send();
    }

}
