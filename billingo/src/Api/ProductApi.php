<?php

namespace App\Billingo\Api;

use App\Billingo\Api\Queries\ProductQuery;
use App\Billingo\Enums\HttpMethodEnum;
use App\Billingo\Exceptions\BadContentException;
use App\Billingo\Models\Product\Product;

class ProductApi extends BillingoApi
{
    const PREFIX = 'products';
    const DEFAULT_CONVERT_CLASS = Product::class;

    protected static string $convertTo = self::DEFAULT_CONVERT_CLASS;

    /**
     * @return ProductQuery
     */
    public function query(): ProductQuery
    {
        return new ProductQuery();
    }

    /**
     * Returns a list of your products.
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
     * Create a new product. Returns a product object if the create is succeeded.
     * @param  array|Product  $product
     * @return self
     * @throws BadContentException
     */
    public function create(array|Product $product): self
    {
        if (is_array($product)) {
            $product = new Product($product);
        }

        if ($product->hasError()) {

            throw new BadContentException($product->getSelfTest());
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::POST)
            ->setUrl(self::PREFIX)
            ->setContent($product->toArray());

        return $this->send();
    }

    /**
     * Retrieves the details of an existing product.
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
     * Update an existing product. Returns a product object if the update is succeeded.
     * @param  int  $id
     * @param  Product|array  $product
     * @return ProductApi
     * @throws BadContentException
     */
    public function update(int $id, Product|array $product): self
    {

        if (is_array($product)) {
            $product = new Product($product);
        }

        if ($product->hasError()) {

            throw new BadContentException($product->getSelfTest());
        }

        $this->apiContext
            ->setMethod(HttpMethodEnum::PUT)
            ->setUrl(self::PREFIX."/{$id}")
            ->setContent($product->toArray());

        return $this->send();
    }

    /**
     * Delete an existing product.
     * @param  int  $id
     * @return ProductApi
     */
    public function delete(int $id): self
    {
        $this->apiContext
            ->setMethod(HttpMethodEnum::DELETE)
            ->setUrl(self::PREFIX."/{$id}");

        return $this->send();
    }
}
