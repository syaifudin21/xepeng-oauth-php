<?php

namespace Xepeng\OAuth\Integration\Resources;

use Xepeng\OAuth\Integration\XepengIntegrationClient;
use Xepeng\OAuth\Integration\Exceptions\XepengException;

class Order
{
    protected XepengIntegrationClient $client;

    public function __construct(XepengIntegrationClient $client)
    {
        $this->client = $client;
    }

    /**
     * @throws XepengException
     */
    public function create(array $items): array
    {
        $this->validateItems($items);

        return $this->client->request('POST', '/openapi/orders', [
            'json' => [
                'items' => $items
            ]
        ]);
    }

    /**
     * @throws XepengException
     */
    public function update(string $uid, array $items, string $status = 'active'): array
    {
        if (empty(trim($uid))) {
            throw new XepengException("Order UID is required for update.");
        }

        $this->validateItems($items);

        return $this->client->request('PUT', '/openapi/orders/' . $uid, [
            'json' => [
                'status' => $status,
                'items'  => $items
            ]
        ]);
    }

    public function get(string $uid): array
    {
        return $this->client->request('GET', '/openapi/orders/' . $uid);
    }

    public function list(int $page = 1, int $limit = 10): array
    {
        return $this->client->request('GET', '/openapi/orders', [
            'query' => [
                'page'  => $page,
                'limit' => $limit
            ]
        ]);
    }

    /**
     * Basic validation for order items
     * @throws XepengException
     */
    private function validateItems(array $items): void
    {
        if (empty($items)) {
            throw new XepengException("Order items cannot be empty.");
        }

        foreach ($items as $index => $item) {
            if (!isset($item['amount']) || $item['amount'] <= 0) {
                throw new XepengException("Item at index {$index} must have a positive amount.");
            }
            if (empty($item['product_name'])) {
                throw new XepengException("Item at index {$index} must have a product_name.");
            }
        }
    }
}
