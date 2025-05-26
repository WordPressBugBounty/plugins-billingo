<?php

namespace App\Billingo\Api;

use App\Billingo\Api\Queries\SpendingQuery;
use App\Billingo\Enums\HttpMethodEnum;
use App\Billingo\Exceptions\BadContentException;
use App\Billingo\Models\Spending\Spending;
use App\Billingo\Models\Spending\SpendingSave;

class SpendingApi extends BillingoApi
{
    const PREFIX = 'spendings';
    const DEFAULT_CONVERT_CLASS = Spending::class;

    protected static string $convertTo = self::DEFAULT_CONVERT_CLASS;

    /**
     * This method creates and returns a new instance of the DocumentQuery class,
     * which can be used to query spending's.
     * @return SpendingQuery
     */
    public function query(): SpendingQuery
    {
        return new SpendingQuery();
    }

    /**
     * Returns a list of your spending items, ordered by the due date.
     * @param  array  $content
     * @return self
     */
    public function getAll(array $content = []): self
    {
        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX)
            ->setContent($content);

        return $this->send();
    }

    /**
     * Create a new spending. Returns a spending object if the creation is succeeded.
     * @param  array|SpendingSave  $spendingSave
     * @return SpendingApi
     * @throws BadContentException
     */
    public function create(array|SpendingSave $spendingSave): self
    {
        if (is_array($spendingSave)) {
            $spendingSave = new SpendingSave($spendingSave);
        }

        if ($spendingSave->hasError()) {

            throw new BadContentException($spendingSave->getSelfTest());
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::POST)
            ->setUrl(self::PREFIX)
            ->setContent($spendingSave->toArray());

        return $this->send();
    }

    /**
     * Retrieves the spending identified by the given ID in path.
     * @param  int  $id
     * @return SpendingApi
     */
    public function getById(int $id): self
    {
        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX . "/{$id}");

        return $this->send();
    }

    /**
     * Updates the spending item identified by the ID given in path.
     * @param  int  $id
     * @param  SpendingSave|array  $spendingSave
     * @return SpendingApi
     * @throws BadContentException
     */
    public function update(int $id, SpendingSave|array $spendingSave): self
    {

        if (is_array($spendingSave)) {
            $spendingSave = new SpendingSave($spendingSave);
        }

        if ($spendingSave->hasError()) {

            throw new BadContentException($spendingSave->getSelfTest());
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::PUT)
            ->setUrl(self::PREFIX . "/{$id}")
            ->setContent($spendingSave->toArray());

        return $this->send();
    }

    /**
     * Deletes the spending identified by the ID given in path.
     * @param  int  $id
     * @return SpendingApi
     */
    public function delete(int $id): self
    {
        $this->apiContext
            ->setMethod(HttpMethodEnum::DELETE)
            ->setUrl(self::PREFIX . "/{$id}");

        return $this->send();
    }
}
