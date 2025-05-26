<?php

namespace App\Billingo\Api;

use App\Billingo\Api\Queries\BankAccountQuery;
use App\Billingo\Enums\HttpMethodEnum;
use App\Billingo\Exceptions\BadContentException;
use App\Billingo\Models\BankAccount\BankAccount;

class BankAccountApi extends BillingoApi
{
    const PREFIX = 'bank-accounts';
    const DEFAULT_CONVERT_CLASS = BankAccount::class;

    protected static string $convertTo = self::DEFAULT_CONVERT_CLASS;

    /**
     * This method creates and returns a new instance of the DocumentQuery class,
     * which can be used to query bank accounts.
     * @return BankAccountQuery
     */
    public function query(): BankAccountQuery
    {
        return new BankAccountQuery();
    }

    /**
     * Returns a list of your bank accounts.
     * The bank accounts are returned sorted by creation date, with the most recent bank account appearing first.
     * @param  array  $content
     * @return BankAccountApi
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
     * Create a new bank account. Returns a bank account object if the create is succeeded.
     * @param  array|BankAccount  $bankAccount
     * @return BankAccountApi
     * @throws BadContentException
     */
    public function create(array|BankAccount $bankAccount): self
    {
        if (is_array($bankAccount)) {
            $bankAccount = new BankAccount($bankAccount);
        }

        if ($bankAccount->hasError()) {

            throw new BadContentException($bankAccount->getSelfTest());
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::POST)
            ->setUrl(self::PREFIX)
            ->setContent($bankAccount->toArray());

        return $this->send();
    }

    /**
     * Retrieves the details of an existing bank account.
     * @param  int  $id
     * @return self
     */
    public function getById(int $id): self
    {
        $this->apiContext
            ->setMethod(HttpMethodEnum::GET)
            ->setUrl(self::PREFIX."/{$id}");

        return $this->send();
    }

    /**
     * Update an existing bank accounts. Returns a bank account object if the update is succeeded.
     * @param  int  $id
     * @param  BankAccount|array  $bankAccount
     * @return BankAccountApi
     * @throws BadContentException
     */
    public function update(int $id, BankAccount|array $bankAccount): self
    {

        if (is_array($bankAccount)) {
            $bankAccount = new BankAccount($bankAccount);
        }

        if ($bankAccount->hasError()) {

            throw new BadContentException($bankAccount->getSelfTest());
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::PUT)
            ->setUrl(self::PREFIX."/{$id}")
            ->setContent($bankAccount->toArray());

        return $this->send();
    }

    /**
     * Delete an existing bank account.
     * @param  int  $id
     * @return BankAccountApi
     */
    public function delete(int $id): self
    {
        $this->apiContext
            ->setMethod(HttpMethodEnum::DELETE)
            ->setUrl(self::PREFIX."/{$id}");

        return $this->send();
    }
}
