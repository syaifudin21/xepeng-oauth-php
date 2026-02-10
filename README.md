# Xepeng OAuth PHP SDK

A PHP library for integrating with Xepeng OAuth authentication.

## Installation

```bash
composer require xepeng/oauth-php
```

## Basic Usage (Vanilla PHP)

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

    // Get user info (Optional)
    // $user = $client->getUserInfo();

    echo "Authentication successful!";

} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

## Laravel Integration

To use this package in a Laravel project, follow these steps to integrate gracefully with Laravel's session and routing.

### 1. Configuration

Add your Xepeng credentials to your `.env` file:

```env
XEPENG_CLIENT_ID=your_client_id
XEPENG_CLIENT_SECRET=your_client_secret
XEPENG_REDIRECT_URI=http://your-app-url/callback/xepeng
XEPENG_ENV=development
```

Create a config file `config/xepeng.php`:

```php
return [
    'client_id' => env('XEPENG_CLIENT_ID'),
    'client_secret' => env('XEPENG_CLIENT_SECRET'),
    'redirect_uri' => env('XEPENG_REDIRECT_URI'),
    'env' => env('XEPENG_ENV', 'development'),
];
```

### 2. Session Adaptor

Create a service to bridge the package's storage interface with Laravel's session.
`app/Services/LaravelSessionStorage.php`:

```php
namespace App\Services;

use Xepeng\OAuth\Storage\StorageInterface;
use Illuminate\Support\Facades\Session;

class LaravelSessionStorage implements StorageInterface
{
    private string $prefix;

    public function __construct(string $prefix = 'xepeng_oauth_')
    {
        $this->prefix = $prefix;
    }

    public function set($key, $value)
    {
        Session::put($this->prefix . $key, $value);
    }

    public function get($key)
    {
        return Session::get($this->prefix . $key);
    }

    public function remove($key)
    {
        Session::forget($this->prefix . $key);
    }

    public function clear()
    {
        Session::forget($this->prefix . 'tokens');
        Session::forget($this->prefix . 'oauth_state');
    }

    public function has($key)
    {
        return Session::has($this->prefix . $key);
    }
}
```

### 3. Controller

Create a controller to handle the flow.
`app/Http/Controllers/XepengOAuthController.php`:

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Xepeng\OAuth\Client;
use App\Services\LaravelSessionStorage;

class XepengOAuthController extends Controller
{
    private $client;

    public function __construct()
    {
        $config = config('xepeng');

        $this->client = new Client([
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $config['redirect_uri'],
            'env' => $config['env'],
            'storage_adapter' => new LaravelSessionStorage(),
        ]);
    }

    public function redirect()
    {
        return redirect()->away($this->client->getAuthorizationUrl());
    }

    public function callback(Request $request)
    {
        try {
            $tokenResponse = $this->client->handleCallback($request->all());

            return response()->json([
                'message' => 'Authentication successful',
                'tokens' => $tokenResponse
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
```

### 4. Routes

Add routes in `routes/web.php`:

```php
Route::get('/login/xepeng', [\App\Http\Controllers\XepengOAuthController::class, 'redirect']);
Route::get('/callback/xepeng', [\App\Http\Controllers\XepengOAuthController::class, 'callback']);
```

## Custom Storage

You can implement `Xepeng\OAuth\Storage\StorageInterface` to use your own storage mechanism (e.g. database, Redis).

```php
use Xepeng\OAuth\Client;
use My\Custom\Storage;

$client = new Client([
    // ... other config
    'storage_adapter' => new Storage(),
]);
```
