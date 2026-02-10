<?php

namespace Xepeng\OAuth;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\GuzzleException;
use Xepeng\OAuth\Exceptions\OAuthException;
use Xepeng\OAuth\Storage\SessionStorage;
use Xepeng\OAuth\Storage\StorageInterface;

class Client
{
    private $config;
    private $storage;
    private $httpClient;

    const DEFAULT_CONFIG = [
        'base_url' => 'https://staging-app.xepeng.com',
        'api_base_url' => 'https://staging-api.xepeng.com',
        'scopes' => ['profile', 'email'],
        'storage' => 'session',
        'auto_refresh' => true,
        'refresh_buffer' => 300,
    ];

    public function __construct(array $config)
    {
        $this->config = array_merge(self::DEFAULT_CONFIG, $config);
        
        if (isset($this->config['storage_adapter']) && $this->config['storage_adapter'] instanceof StorageInterface) {
            $this->storage = $this->config['storage_adapter'];
        } else {
            $this->storage = new SessionStorage();
        }

        $this->httpClient = new HttpClient([
            'base_uri' => $this->config['api_base_url'],
            'timeout'  => 30.0,
        ]);
    }

    /**
     * Get the authorization URL to redirect the user to.
     *
     * @return string
     * @throws \Exception
     */
    public function getAuthorizationUrl()
    {
        $state = Pkce::generateState();
        $codeVerifier = Pkce::generateCodeVerifier();
        $codeChallenge = Pkce::generateCodeChallenge($codeVerifier);

        $this->storeOAuthState($state, $codeVerifier, $codeChallenge);

        $params = [
            'client_id' => $this->config['client_id'],
            'redirect_uri' => $this->config['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(' ', $this->config['scopes']),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        return $this->config['base_url'] . '/oauth/authorize?' . http_build_query($params);
    }

    /**
     * Handle the callback from the OAuth provider.
     * 
     * @param array|null $queryParams Optional query params (default $_GET)
     * @return array Token response
     * @throws OAuthException
     */
    public function handleCallback($queryParams = null)
    {
        if ($queryParams === null) {
            $queryParams = $_GET;
        }

        $code = $queryParams['code'] ?? null;
        $state = $queryParams['state'] ?? null;
        $error = $queryParams['error'] ?? null;

        if ($error) {
            $desc = $queryParams['error_description'] ?? $error;
            throw new OAuthException($desc, $error);
        }

        if (!$code || !$state) {
            throw new OAuthException('Missing code or state in callback', 'invalid_callback');
        }

        $storedState = $this->retrieveOAuthState();
        if (!$storedState || $storedState['state'] !== $state) {
            throw new OAuthException('PKCE state mismatch or expired', 'invalid_state');
        }

        return $this->exchangeCodeForToken($code, $storedState['codeVerifier']);
    }

    /**
     * Exchange authorization code for tokens.
     *
     * @param string $code
     * @param string $codeVerifier
     * @return array
     * @throws OAuthException
     */
    public function exchangeCodeForToken($code, $codeVerifier)
    {
        return $this->fetchToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->config['redirect_uri'],
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'code_verifier' => $codeVerifier,
        ]);
    }

    /**
     * Refresh the access token.
     *
     * @return array
     * @throws OAuthException
     */
    public function refreshAccessToken()
    {
        $tokens = $this->storage->get('tokens');
        if (!isset($tokens['refresh_token'])) {
            throw new OAuthException('No refresh token available', 'no_refresh_token');
        }

        return $this->fetchToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $tokens['refresh_token'],
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
        ]);
    }

    /**
     * Get user information.
     *
     * @return array
     * @throws OAuthException
     * @throws GuzzleException
     */
    public function getUserInfo()
    {
        $tokens = $this->getTokens();
        if (!$tokens) {
            throw new OAuthException('Not authenticated', 'not_authenticated');
        }

        try {
            $response = $this->httpClient->get('/oauth/userinfo', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $tokens['access_token'],
                ],
            ]);

            return json_decode($response->getBody(), true);
        } catch (GuzzleException $e) {
             throw new OAuthException('Failed to fetch user info: ' . $e->getMessage(), 'userinfo_failed', $e->getCode(), $e);
        }
    }

    /**
     * Revoke tokens and logout.
     *
     * @return void
     */
    public function revokeTokens()
    {
        $tokens = $this->getTokens();
        if ($tokens) {
            try {
                $this->httpClient->post('/oauth/revoke', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $tokens['access_token'],
                        'Content-Type' => 'application/json',
                    ],
                    'json' => ['client_id' => $this->config['client_id']],
                ]);
            } catch (\Exception $e) {
                // Ignore revocation errors
            }
        }
        $this->logout();
    }

    public function logout()
    {
        $this->storage->clear();
        $this->clearOAuthState();
    }

    public function isAuthenticated()
    {
        $tokens = $this->storage->get('tokens');
        if (!$tokens) return false;
        
        // Check if expired
        return time() < ($tokens['expires_at'] ?? 0);
    }

    public function getTokens()
    {
        return $this->storage->get('tokens');
    }

    public function getAccessToken()
    {
        $tokens = $this->getTokens();
        if (!$tokens) {
            throw new OAuthException('Not authenticated', 'not_authenticated');
        }

        if ($this->shouldRefreshToken($tokens)) {
            $tokens = $this->refreshAccessToken();
        }

        return $tokens['access_token'];
    }

    private function fetchToken(array $params)
    {
        try {
            $response = $this->httpClient->post('/oauth/token', [
                'form_params' => $params,
            ]);

            $data = json_decode($response->getBody(), true);
            $this->storeTokens($data);
            return $data;
        } catch (GuzzleException $e) {
             // Try to parse error response
             $responseBody = $e->getResponse() ? (string) $e->getResponse()->getBody() : null;
             $errorData = $responseBody ? json_decode($responseBody, true) : [];
             
             $msg = $errorData['message'] ?? $e->getMessage();
             $code = $errorData['error'] ?? 'token_error';
             
             throw new OAuthException($msg, $code, $e->getCode(), $e);
        }
    }

    private function storeTokens(array $response)
    {
        $expiresAt = time() + ($response['expires_in'] ?? 3600);
        $tokens = [
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? null,
            'expires_at' => $expiresAt,
        ];
        
        // Preserve old refresh token if new one not provided (some providers do this)
        if (empty($tokens['refresh_token'])) {
            $oldTokens = $this->storage->get('tokens');
            if ($oldTokens && isset($oldTokens['refresh_token'])) {
                $tokens['refresh_token'] = $oldTokens['refresh_token'];
            }
        }

        $this->storage->set('tokens', $tokens);
    }

    private function shouldRefreshToken(array $tokens)
    {
        if (!isset($tokens['expires_at'])) return true;
        $bufferTime = $this->config['refresh_buffer'];
        return time() >= ($tokens['expires_at'] - $bufferTime);
    }

    private function storeOAuthState($state, $codeVerifier, $codeChallenge)
    {
        $data = [
            'state' => $state,
            'codeVerifier' => $codeVerifier,
            'codeChallenge' => $codeChallenge,
            'redirectUri' => $this->config['redirect_uri'],
        ];
        $this->storage->set('oauth_state', $data);
    }

    private function retrieveOAuthState()
    {
        $data = $this->storage->get('oauth_state');
        $this->storage->remove('oauth_state');
        return $data;
    }

    private function clearOAuthState()
    {
        $this->storage->remove('oauth_state');
    }
}
