<?php

namespace Xepeng\OAuth\Integration\Resources;

use Xepeng\OAuth\Integration\XepengIntegrationClient;
use Xepeng\OAuth\Integration\Exceptions\XepengException;

class PaymentLink
{
    protected XepengIntegrationClient $client;

    public function __construct(XepengIntegrationClient $client)
    {
        $this->client = $client;
    }

    /**
     * @throws XepengException
     */
    public function generate(string $orderUid, array $options = []): array
    {
        if (empty(trim($orderUid))) {
            throw new XepengException("Order UID is required to generate payment link.");
        }

        return $this->client->request('POST', '/openapi/payment-links/generate', [
            'json' => array_merge([
                'order_uid' => $orderUid,
            ], $options)
        ]);
    }

    public function get(string $uid): array
    {
        return $this->client->request('GET', '/openapi/payment-links/' . $uid);
    }

    public function list(): array
    {
        return $this->client->request('GET', '/openapi/payment-links');
    }

    public function inactivate(string $uid): array
    {
        return $this->client->request('PUT', '/openapi/payment-links/' . $uid . '/inactivate');
    }
}
