# Xepeng OAuth PHP SDK

A PHP library for integrating with Xepeng OAuth authentication.

## Installation

```bash
composer require xepeng/oauth-php
```

## Usage

### processing the redirect

```php
use Xepeng\OAuth\Client;

session_start();

$client = new Client([
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'redirect_uri' => 'http://your-app.com/callback',
    'env' => 'production', // optional, defaults to 'development'
    'scopes' => ['profile', 'email'], // optional
]);

// 1. Redirect to authorization URL
if (!isset($_GET['code'])) {
    $authUrl = $client->getAuthorizationUrl();
    header('Location: ' . $authUrl);
    exit;
}

// 2. Handle callback
try {
    $tokenResponse = $client->handleCallback();

    // Access token is automatically stored in session
    $accessToken = $tokenResponse['access_token'];

    // Get user info
    $user = $client->getUserInfo();

    echo "Hello, " . $user['name'];

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Custom Storage

You can implement `Xepeng\OAuth\Storage\StorageInterface` to use your own storage mechanism (e.g. database, Redis).

```php
use Xepeng\OAuth\Client;
use My\Custom\Storage;

$client = new Client([
    // ... other config
    'storage_adapter' => new Storage(),
]);
```
