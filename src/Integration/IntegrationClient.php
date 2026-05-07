<?php

namespace Xepeng\OAuth\Integration;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Xepeng\OAuth\Integration\Resources\Order;
use Xepeng\OAuth\Integration\Resources\PaymentLink;
use Xepeng\OAuth\Integration\Exceptions\XepengException;

class XepengIntegrationClient
{
    protected string $clientId;
    protected string $clientSecret;
    protected string $baseUrl;
    protected GuzzleClient $httpClient;

    public function __construct(string $clientId, string $clientSecret, bool $isProduction = false, ?string $baseUrl = null, ?GuzzleClient $httpClient = null)
    {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        
        if ($baseUrl) {
            $this->baseUrl = rtrim($baseUrl, '/');
        } else {
            $this->baseUrl = $isProduction 
                ? 'https://api.xepeng.com' 
                : 'https://staging-api.xepeng.com';
        }

        $this->httpClient = $httpClient ?: new GuzzleClient([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ],
        ]);
    }

    public function orders(): Order
    {
        return new Order($this);
    }

    public function paymentLinks(): PaymentLink
    {
        return new PaymentLink($this);
    }

    /**
     * @throws XepengException
     */
    public function request(string $method, string $path, array $options = []): array
    {
        $method = strtoupper($method);
        $timestamp = time();
        
        $body = '';
        if (isset($options['json'])) {
            $body = json_encode($options['json'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $options['body'] = $body;
            unset($options['json']);
        }

        $signature = $this->generateSignature($method, $path, $timestamp, $body);

        $options['headers'] = array_merge($options['headers'] ?? [], [
            'X-Client-ID' => $this->clientId,
            'X-Timestamp' => (string) $timestamp,
            'X-Signature' => $signature,
        ]);

        try {
            $response = $this->httpClient->request($method, $path, $options);
            $contents = $requestBody = $response->getBody()->getContents();
            $data = json_decode($contents, true);
            
            return is_array($data) ? $data : [];
        } catch (GuzzleException $e) {
            throw new XepengException($e->getMessage(), $e->getCode(), $e);
        }
    }

    public function generateSignature(string $method, string $path, int $timestamp, string $body): string
    {
        // Format: METHOD + PATH + TIMESTAMP + BODY
        $payload = strtoupper($method) . $path . (string)$timestamp . $body;
        return hash_hmac('sha256', $payload, $this->clientSecret);
    }
}
