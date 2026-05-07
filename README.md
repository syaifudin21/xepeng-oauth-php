# Xepeng OAuth & Integration PHP SDK

[![Version](https://img.shields.io/badge/version-1.1.0-blue)](https://packagist.org/packages/xepeng/oauth-php)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-green)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-orange)](LICENSE)

Library PHP untuk integrasi dengan layanan Xepeng. SDK ini terdiri dari dua komponen utama:

- **OAuth SDK** - Autentikasi pengguna menggunakan OAuth 2.0 dengan PKCE
- **Integration SDK** - Integrasi API untuk mengelola pesanan dan tautan pembayaran

---

## Persyaratan Sistem

| Komponen | Minimum |
|----------|---------|
| PHP | 7.4 atau 8.0+ |
| Ekstensi JSON | Required |
| Guzzle HTTP | 7.0+ |

---

## Instalasi

### Via Composer

```bash
composer require xepeng/oauth-php
```

### Manual Installation

Jika tidak menggunakan Composer, unduh source dan load secara manual:

```php
require_once 'path/to/src/autoload.php';
// atau include satu per satu semua file di src/
```

---

## Quick Start - OAuth

Contoh minimal untuk integrasi OAuth dalam 10 baris:

```php
use Xepeng\OAuth\Client;

session_start();

$client = new Client([
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'redirect_uri' => 'https://your-app.com/callback',
]);

// Redirect ke halaman login Xepeng
if (!isset($_GET['code'])) {
    header('Location: ' . $client->getAuthorizationUrl());
    exit;
}

// Handle callback dan dapatkan token
$tokens = $client->handleCallback();
echo "Access Token: " . $tokens['access_token'];
```

---

# Bagian 1: OAuth SDK

## 1.1 Konfigurasi

### Parameter Konfigurasi

| Parameter | Tipe | Required | Default | Deskripsi |
|-----------|------|----------|---------|-----------|
| `client_id` | string | Ya | - | Client ID dari dashboard Xepeng |
| `client_secret` | string | Ya | - | Client Secret dari dashboard Xepeng |
| `redirect_uri` | string | Ya | - | URL callback setelah autentikasi |
| `env` | string | Tidak | `development` | `development` atau `production` |
| `scopes` | array | Tidak | `['profile', 'email']` | Scope OAuth yang diminta |
| `base_url` | string | Tidak | auto | Override base URL untuk OAuth |
| `api_base_url` | string | Tidak | auto | Override API base URL |
| `storage_adapter` | StorageInterface | Tidak | SessionStorage | Adapter storage kustom |
| `auto_refresh` | bool | Tidak | `true` | Auto-refresh token sebelum expired |
| `refresh_buffer` | int | Tidak | `300` | Detik sebelum expired untuk refresh |

### Environment URLs

```php
// Development
'base_url' => 'https://staging-app.xepeng.com'
'api_base_url' => 'https://staging-api.xepeng.com'

// Production
'base_url' => 'https://app.xepeng.com'
'api_base_url' => 'https://api.xepeng.com'
```

---

## 1.2 Alur OAuth + PKCE

SDK ini menggunakan **OAuth 2.0 PKCE (Proof Key for Code Exchange)** untuk keamanan tambahan. Berikut step-by-step alur autentikasi:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           ALUR OAUTH + PKCE                                 │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  [1] GENERATE RANDOM STATE & PKCE                                            │
│      ├── Generate random state (43 chars)                                   │
│      ├── Generate code_verifier (64 chars)                                  │
│      └── Generate code_challenge (SHA256 + base64url encode)                │
│                                                                              │
│  [2] REDIRECT KE XEPENG                                                     │
│      GET https://app.xepeng.com/oauth/authorize                             │
│      ?client_id=xxx                                                         │
│      &redirect_uri=xxx                                                      │
│      &response_type=code                                                    │
│      &scope=profile+email                                                   │
│      &state=xxx                                                             │
│      &code_challenge=xxx                                                    │
│      &code_challenge_method=S256                                            │
│                                                                              │
│  [3] USER LOGIN DI XEPENG                                                   │
│      └── User authorize aplikasi                                            │
│                                                                              │
│  [4] REDIRECT BACK TO CALLBACK                                               │
│      GET https://your-app.com/callback?code=xxx&state=xxx                   │
│                                                                              │
│  [5] EXCHANGE CODE FOR TOKEN                                                │
│      POST /oauth/token                                                       │
│      {                                                                       │
│          grant_type: 'authorization_code',                                   │
│          code: 'xxx',                                                        │
│          redirect_uri: 'xxx',                                                │
│          client_id: 'xxx',                                                   │
│          client_secret: 'xxx',                                               │
│          code_verifier: 'xxx'  ← PKCE verifier untuk verifikasi             │
│      }                                                                       │
│                                                                              │
│  [6] RESPONSE                                                               │
│      {                                                                       │
│          access_token: 'xxx',                                               │
│          refresh_token: 'xxx',                                               │
│          expires_in: 3600,                                                   │
│          token_type: 'Bearer'                                               │
│      }                                                                       │
│                                                                              │
│  [7] STORE TOKENS                                                           │
│      └── Tokens disimpan di storage (session/redis/db)                     │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Mengapa PKCE?

PKCE menambahkan lapisan keamanan dengan:
1. **Code Verifier** - Secret yang hanya diketahui client
2. **Code Challenge** - Hash dari verifier yang dikirim ke server
3. **Mencegah interception** -即使有人拦截了code，也无法兑换token tanpa verifier

---

## 1.3 Penggunaan Vanilla PHP

### Contoh Lengkap

```php
<?php

require 'vendor/autoload.php';

use Xepeng\OAuth\Client;
use Xepeng\OAuth\Exceptions\OAuthException;

session_start();

$client = new Client([
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'redirect_uri' => 'https://your-app.com/callback',
    'env' => 'production',
    'scopes' => ['profile', 'email'],
]);

// Step 1: Cek apakah ada code di callback
if (!isset($_GET['code'])) {
    // Redirect ke halaman login Xepeng
    $authUrl = $client->getAuthorizationUrl();
    header('Location: ' . $authUrl);
    exit;
}

// Step 2: Handle callback
try {
    $tokenResponse = $client->handleCallback();
    
    // Response token:
    // {
    //     "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    //     "refresh_token": "dGhpcyBpcyBhIHJlZnJlc2ggdG9rZW4...",
    //     "expires_in": 3600,
    //     "token_type": "Bearer"
    // }
    
    echo "Login berhasil!\n";
    echo "Access Token: " . $tokenResponse['access_token'] . "\n";
    echo "Expires In: " . $tokenResponse['expires_in'] . " detik\n";
    
    // Optional: Ambil info user
    // $user = $client->getUserInfo();
    // echo "User: " . $user['email'];
    
} catch (OAuthException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Error Code: " . $e->getError() . "\n";
}
```

### Contoh: Cek Status Autentikasi

```php
// Cek apakah user sudah login
if ($client->isAuthenticated()) {
    echo "User sudah login";
    
    // Ambil access token (auto-refresh jika perlu)
    $token = $client->getAccessToken();
    echo "Token: " . $token;
} else {
    echo "User belum login, redirect ke login...";
    header('Location: ' . $client->getAuthorizationUrl());
}
```

### Contoh: Logout

```php
// Revoke token di server dan clear local storage
$client->revokeTokens();

// Atau hanya clear local storage (tanpa revoke)
$client->logout();
```

---

## 1.4 Integrasi Laravel

### Step 1: Konfigurasi Environment

Tambahkan kredensial ke file `.env`:

```env
XEPENG_CLIENT_ID=your_client_id
XEPENG_CLIENT_SECRET=your_client_secret
XEPENG_REDIRECT_URI=https://your-app.com/callback/xepeng
XEPENG_ENV=development
```

### Step 2: Buat Config File

Buat file `config/xepeng.php`:

```php
<?php

return [
    'client_id' => env('XEPENG_CLIENT_ID'),
    'client_secret' => env('XEPENG_CLIENT_SECRET'),
    'redirect_uri' => env('XEPENG_REDIRECT_URI'),
    'env' => env('XEPENG_ENV', 'development'),
    'scopes' => ['profile', 'email'],
];
```

### Step 3: Buat Session Storage Adapter

Buat service `app/Services/LaravelSessionStorage.php`:

```php
<?php

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

    public function set($key, $value): void
    {
        Session::put($this->prefix . $key, $value);
    }

    public function get($key)
    {
        return Session::get($this->prefix . $key);
    }

    public function remove($key): void
    {
        Session::forget($this->prefix . $key);
    }

    public function clear(): void
    {
        Session::forget($this->prefix . 'tokens');
        Session::forget($this->prefix . 'oauth_state');
    }

    public function has($key): bool
    {
        return Session::has($this->prefix . $key);
    }
}
```

### Step 4: Buat Controller

Buat controller `app/Http/Controllers/XepengOAuthController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Xepeng\OAuth\Client;
use App\Services\LaravelSessionStorage;

class XepengOAuthController extends Controller
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'client_id' => config('xepeng.client_id'),
            'client_secret' => config('xepeng.client_secret'),
            'redirect_uri' => config('xepeng.redirect_uri'),
            'env' => config('xepeng.env'),
            'scopes' => config('xepeng.scopes'),
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
            return response()->json([
                'error' => $e->getMessage(),
                'code' => method_exists($e, 'getError') ? $e->getError() : 'unknown'
            ], 400);
        }
    }

    public function logout()
    {
        $this->client->revokeTokens();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function userInfo()
    {
        try {
            $user = $this->client->getUserInfo();
            return response()->json($user);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 401);
        }
    }
}
```

### Step 5: Tambah Routes

Tambahkan routes di `routes/web.php`:

```php
use App\Http\Controllers\XepengOAuthController;

Route::get('/login/xepeng', [XepengOAuthController::class, 'redirect']);
Route::get('/callback/xepeng', [XepengOAuthController::class, 'callback']);
Route::post('/logout/xepeng', [XepengOAuthController::class, 'logout']);
Route::get('/user/xepeng', [XepengOAuthController::class, 'userInfo']);
```

### Step 6: Register Service Provider (Optional)

Untuk penggunaan yang lebih bersih, buat Service Provider:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Xepeng\OAuth\Client;
use App\Services\LaravelSessionStorage;

class XepengOAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function ($app) {
            return new Client([
                'client_id' => config('xepeng.client_id'),
                'client_secret' => config('xepeng.client_secret'),
                'redirect_uri' => config('xepeng.redirect_uri'),
                'env' => config('xepeng.env'),
                'scopes' => config('xepeng.scopes'),
                'storage_adapter' => new LaravelSessionStorage(),
            ]);
        });
    }

    public function boot(): void
    {
        //
    }
}
```

---

## 1.5 Custom Storage

Jika kamu tidak ingin menggunakan session atau ingin menggunakan storage lain (database, Redis, file), implementasikan interface `StorageInterface`:

```php
<?php

namespace App\Storage;

use Xepeng\OAuth\Storage\StorageInterface;

class DatabaseStorage implements StorageInterface
{
    private string $prefix;
    private $db;

    public function __construct($db, string $prefix = 'xepeng_oauth_')
    {
        $this->db = $db;
        $this->prefix = $prefix;
    }

    public function set($key, $value): void
    {
        $this->db->table('oauth_storage')
            ->updateOrInsert(
                ['key' => $this->prefix . $key],
                ['value' => json_encode($value)]
            );
    }

    public function get($key)
    {
        $row = $this->db->table('oauth_storage')
            ->where('key', $this->prefix . $key)
            ->first();

        return $row ? json_decode($row->value, true) : null;
    }

    public function remove($key): void
    {
        $this->db->table('oauth_storage')
            ->where('key', $this->prefix . $key)
            ->delete();
    }

    public function clear(): void
    {
        $this->db->table('oauth_storage')
            ->where('key', 'like', $this->prefix . '%')
            ->delete();
    }

    public function has($key): bool
    {
        return $this->db->table('oauth_storage')
            ->where('key', $this->prefix . $key)
            ->exists();
    }
}
```

### Penggunaan Custom Storage

```php
use Xepeng\OAuth\Client;

$client = new Client([
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'redirect_uri' => 'https://your-app.com/callback',
    'storage_adapter' => new DatabaseStorage($db),
]);
```

---

## 1.6 Referensi API - OAuth SDK

### Method Reference

| Method | Parameter | Return | Deskripsi |
|--------|-----------|--------|-----------|
| `__construct()` | `array $config` | - | Inisialisasi client |
| `getAuthorizationUrl()` | - | `string` | Generate URL untuk redirect ke login Xepeng |
| `handleCallback()` | `array\|null $queryParams` | `array` | Proses callback dari Xepeng, return tokens |
| `exchangeCodeForToken()` | `string $code, string $codeVerifier` | `array` | Tukar authorization code dengan token |
| `refreshAccessToken()` | - | `array` | Refresh access token menggunakan refresh token |
| `getUserInfo()` | - | `array` | Ambil informasi user yang login |
| `revokeTokens()` | - | `void` | Revoke token di server dan clear local |
| `logout()` | - | `void` | Clear local storage tokens |
| `isAuthenticated()` | - | `bool` | Cek apakah user sudah login dan token valid |
| `getTokens()` | - | `array\|null` | Ambil semua tokens dari storage |
| `getAccessToken()` | - | `string` | Ambil access token (auto-refresh jika perlu) |

### Exception Reference

```php
use Xepeng\OAuth\Exceptions\OAuthException;

// OAuthException methods
$e->getMessage();  // Pesan error
$e->getError();    // Error code string
$e->getCode();     // HTTP status code
```

### Error Codes

| Error Code | Deskripsi |
|------------|-----------|
| `invalid_callback` | Callback tidak memiliki code atau state |
| `invalid_state` | State PKCE tidak cocok atau expired |
| `no_refresh_token` | Tidak ada refresh token untuk di-refresh |
| `not_authenticated` | User belum login |
| `userinfo_failed` | Gagal mengambil info user |
| `token_error` | Error saat request token |

---

# Bagian 2: Integration SDK

## 2.1 Constructor Parameters

Integration SDK adalah client untuk API Xepeng Payment Gateway yang **tidak memerlukan OAuth**. Cocok untuk backend-to-backend integration.

```php
use Xepeng\OAuth\Integration\XepengIntegrationClient;

$client = new XepengIntegrationClient(
    string $clientId,           // Required: Client ID dari dashboard Xepeng
    string $clientSecret,       // Required: Client Secret dari dashboard Xepeng
    bool $isProduction = false, // Optional: true = production, false = staging
    ?string $baseUrl = null,    // Optional: Override base URL
    ?GuzzleClient $httpClient = null // Optional: Custom Guzzle instance
);
```

### Parameter Detail

| Parameter | Tipe | Required | Default | Deskripsi |
|-----------|------|----------|---------|-----------|
| `$clientId` | string | Ya | - | Client ID dari dashboard Xepeng |
| `$clientSecret` | string | Ya | - | Client Secret dari dashboard Xepeng |
| `$isProduction` | bool | Tidak | `false` | `true` untuk production URL |
| `$baseUrl` | string | Tidak | null (auto) | Override base URL jika perlu |
| `$httpClient` | GuzzleClient | Tidak | null | Custom Guzzle instance |

### Base URLs

```php
// Staging (development)
'https://staging-api.xepeng.com'

// Production
'https://api.xepeng.com'
```

---

## 2.2 Mekanisme Signature HMAC-SHA256

Setiap request ke Integration API diamankan menggunakan **HMAC-SHA256 signature**. SDK menangani pembuatan signature secara otomatis.

### Format String yang Di-sign

```
METHOD + PATH + TIMESTAMP + BODY
```

### Contoh Signature Generation

```php
// Contoh untuk POST /openapi/orders
$method = 'POST';
$path = '/openapi/orders';
$timestamp = time(); // Unix timestamp
$body = '{"items":[{"amount":50000,"product_name":"Kemeja Flanel"}]}';

// Payload yang di-hash
$payload = strtoupper($method) . $path . (string)$timestamp . $body;

// Generate signature
$signature = hash_hmac('sha256', $payload, $clientSecret);
// Result: 'a1b2c3d4e5f6...'
```

### Headers yang Dikirim

Setiap request otomatis menyertakan header berikut:

| Header | Contoh | Deskripsi |
|--------|--------|-----------|
| `X-Client-ID` | `client_xxx` | Client ID Anda |
| `X-Timestamp` | `1715251200` | Unix timestamp request |
| `X-Signature` | `a1b2c3...` | HMAC-SHA256 signature |
| `Content-Type` | `application/json` | Request content type |
| `Accept` | `application/json` | Response content type |

### Kenapa HMAC Signature?

1. **Integritas Data** - Me确保消息在传输过程中未被篡改
2. **Autentikasi** - Hanya client dengan secret yang valid bisa membuat signature
3. **Replay Protection** - Timestamp memastikan request tidak bisa di-replay

---

## 2.3 Alur Pembayaran (PENTING!)

Ini adalah bagian **penting** yang harus dipahami. Payment link tidak bisa dibuat secara langsung - необходимо mengikuti urutan langkah berikut:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        ALUR PEMBUATAN PEMBAYARAN                             │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  STEP 1: BUAT ORDER                                                          │
│  ══════════════════════════                                                  │
│  POST /openapi/orders                                                        │
│  │                                                                          │
│  │ {                                                                         │
│  │     "items": [                                                           │
│  │         {                                                                 │
│  │             "amount": 50000,                                             │
│  │             "product_name": "Kemeja Flanel"                              │
│  │         }                                                                 │
│  │     ]                                                                     │
│  │ }                                                                         │
│  │                                                                          │
│  ↓                                                                          │
│  Response: { "data": { "uid": "ord_xxx" } }                                  │
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐ │
│  │ PENJELASAN:                                                             │ │
│  │ - Order adalah container untuk item-item yang akan dibayar              │ │
│  │ - Setiap order memiliki unique ID (uid)                                 │ │
│  │ - Status default order adalah 'pending'                                │ │
│  │ - Payment link hanya bisa di-generate untuk order dengan status 'active' │ │
│  └─────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
│  STEP 2: UPDATE ORDER STATUS KE 'active'                                     │
│  ═══════════════════════════════════════════════                             │
│  PUT /openapi/orders/{order_uid}                                             │
│  │                                                                          │
│  │ {                                                                         │
│  │     "status": "active",                                                  │
│  │     "items": [...]  // item yang sama atau berbeda                       │
│  │ }                                                                         │
│  │                                                                          │
│  ↓                                                                          │
│  Response: { "data": { "uid": "ord_xxx", "status": "active" } }             │
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐ │
│  │ PENJELASAN:                                                             │ │
│  │ - Setelah order dibuat, status masih 'pending'                          │ │
│  │ - Order harus di-update ke status 'active' sebelum bisa generate payment │ │
│  │ - Kenapa perlu langkah ini?                                             │ │
│  │   1. Memberikan kesempatan untuk modify items sebelum payment           │ │
│  │   2. Konfirmasi akhir dari merchant sebelum payment link dibuat        │ │
│  │   3. Beberapa kasus butuh approval sebelum payment                     │ │
│  └─────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
│  STEP 3: GENERATE PAYMENT LINK                                               │
│  ════════════════════════════════                                            │
│  POST /openapi/payment-links/generate                                        │
│  │                                                                          │
│  │ {                                                                         │
│  │     "order_uid": "ord_xxx",                                              │
│  │     "expired_at": "2024-05-20T18:00:00Z",                                │
│  │     "callback_url": "https://your-app.com/api/notification",            │
│  │     "success_url": "https://your-app.com/success",                       │
│  │     "cancel_url": "https://your-app.com/cancel"                          │
│  │ }                                                                         │
│  │                                                                          │
│  ↓                                                                          │
│  Response: { "data": { "payment_url": "https://pay.xepeng.com/xxx" } }      │
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────────┐ │
│  │ PENJELASAN:                                                             │ │
│  │ - Payment link bind ke order_uid tertentu                               │ │
│  │ - Order harus berstatus 'active' untuk bisa generate payment link        │ │
│  │ - Payment link memiliki expired_at (waktu kadaluarsa)                  │ │
│  │ - Callback URL untuk notifikasi payment status                         │ │
│  └─────────────────────────────────────────────────────────────────────────┘ │
│                                                                              │
│  STEP 4: REDIRECT USER KE HALAMAN PEMBAYARAN                                 │
│  ══════════════════════════════════════════════════                         │
│                                                                              │
│  header('Location: ' . $paymentUrl);                                         │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### Contoh Lengkap Full Flow

```php
<?php

require 'vendor/autoload.php';

use Xepeng\OAuth\Integration\XepengIntegrationClient;

$client = new XepengIntegrationClient(
    'YOUR_CLIENT_ID',
    'YOUR_CLIENT_SECRET',
    false // false = staging, true = production
);

// ========================================
// STEP 1: Buat Order
// ========================================
$items = [
    [
        'amount' => 50000,
        'notes' => 'Pembelian Kemeja',
        'product_description' => 'Kemeja Flanel Ukuran L',
        'product_name' => 'Kemeja Flanel',
    ],
    [
        'amount' => 25000,
        'notes' => 'Ongkos kirim',
        'product_description' => 'Pengiriman via JNE',
        'product_name' => 'Ongkos Kirim',
    ],
];

$orderResponse = $client->orders()->create($items);
// Response:
// {
//     "success": true,
//     "message": "Order created successfully",
//     "data": {
//         "uid": "ord_a1b2c3d4e5f6",
//         "status": "pending",
//         "items": [...],
//         "created_at": "2024-05-15T10:30:00Z"
//     }
// }

$orderUid = $orderResponse['data']['uid'];
echo "Order dibuat dengan UID: $orderUid\n";

// ========================================
// STEP 2: Update Order ke Active
// ========================================
$updateResponse = $client->orders()->update($orderUid, $items, 'active');
// Response:
// {
//     "success": true,
//     "message": "Order updated successfully",
//     "data": {
//         "uid": "ord_a1b2c3d4e5f6",
//         "status": "active",
//         "items": [...],
//         "updated_at": "2024-05-15T10:35:00Z"
//     }
// }

echo "Order diupdate ke status: active\n";

// ========================================
// STEP 3: Generate Payment Link
// ========================================
$options = [
    'expired_at' => date('c', strtotime('+2 hours')),
    'callback_url' => 'https://your-app.com/api/xepeng/notification',
    'success_url' => 'https://your-app.com/payment/success',
    'cancel_url' => 'https://your-app.com/payment/cancel',
];

$paymentResponse = $client->paymentLinks()->generate($orderUid, $options);
// Response:
// {
//     "success": true,
//     "message": "Payment link generated successfully",
//     "data": {
//         "uid": "pl_a1b2c3d4e5f6",
//         "order_uid": "ord_a1b2c3d4e5f6",
//         "payment_url": "https://staging-pay.xepeng.com/pay/abc123xyz",
//         "amount": 75000,
//         "status": "pending",
//         "expired_at": "2024-05-15T12:35:00Z",
//         "created_at": "2024-05-15T10:35:00Z"
//     }
// }

$paymentUrl = $paymentResponse['data']['payment_url'];
echo "Payment Link: $paymentUrl\n";

// ========================================
// STEP 4: Redirect User
// ========================================
header('Location: ' . $paymentUrl);
exit;
```

---

## 2.4 Manajemen Order

Order adalah container untuk item-item yang akan dibayar. Setiap order memiliki status dan dapat di-update.

### Method: `create()`

Membuat order baru dengan item-item.

**Request:**

```php
$items = [
    [
        'amount' => 50000,
        'notes' => 'Deskripsi item',
        'product_description' => 'Kemeja Flanel Ukuran L - Merah',
        'product_name' => 'Kemeja Flanel',
    ],
    [
        'amount' => 15000,
        'notes' => 'Item tambahan',
        'product_description' => 'Dust Bag',
        'product_name' => 'Tas',
    ],
];

$response = $client->orders()->create($items);
```

**Response Sukses:**

```json
{
    "success": true,
    "message": "Order created successfully",
    "data": {
        "uid": "ord_7f8g9h0i1j2k",
        "status": "pending",
        "total_amount": 65000,
        "items": [
            {
                "amount": 50000,
                "notes": "Deskripsi item",
                "product_description": "Kemeja Flanel Ukuran L - Merah",
                "product_name": "Kemeja Flanel"
            },
            {
                "amount": 15000,
                "notes": "Item tambahan",
                "product_description": "Dust Bag",
                "product_name": "Tas"
            }
        ],
        "created_at": "2024-05-15T10:30:00Z",
        "updated_at": "2024-05-15T10:30:00Z"
    }
}
```

**Response Error:**

```json
{
    "success": false,
    "message": "Order items cannot be empty.",
    "error_code": "VALIDATION_ERROR"
}
```

**Validasi Input:**

| Field | Required | Rules |
|-------|----------|-------|
| `amount` | Ya | Positive integer (> 0) |
| `product_name` | Ya | Non-empty string |
| `notes` | Tidak | String |
| `product_description` | Tidak | String |

---

### Method: `get()`

Mengambil detail order berdasarkan UID.

**Request:**

```php
$orderUid = 'ord_7f8g9h0i1j2k';
$response = $client->orders()->get($orderUid);
```

**Response Sukses:**

```json
{
    "success": true,
    "data": {
        "uid": "ord_7f8g9h0i1j2k",
        "status": "active",
        "total_amount": 65000,
        "items": [
            {
                "amount": 50000,
                "notes": "Deskripsi item",
                "product_description": "Kemeja Flanel Ukuran L - Merah",
                "product_name": "Kemeja Flanel"
            },
            {
                "amount": 15000,
                "notes": "Item tambahan",
                "product_description": "Dust Bag",
                "product_name": "Tas"
            }
        ],
        "created_at": "2024-05-15T10:30:00Z",
        "updated_at": "2024-05-15T10:35:00Z"
    }
}
```

**Response Error:**

```json
{
    "success": false,
    "message": "Order not found",
    "error_code": "NOT_FOUND"
}
```

---

### Method: `update()`

Mengupdate order (status dan/atau items). **Wajib dipanggil** untuk mengubah status dari `pending` ke `active` sebelum bisa generate payment link.

**Request:**

```php
$orderUid = 'ord_7f8g9h0i1j2k';

// Update status menjadi active
$response = $client->orders()->update($orderUid, $items, 'active');

// Update items tanpa ubah status
// $response = $client->orders()->update($orderUid, $newItems, 'pending');

// Update items dan ubah status sekaligus
// $response = $client->orders()->update($orderUid, $newItems, 'active');
```

**Response Sukses:**

```json
{
    "success": true,
    "message": "Order updated successfully",
    "data": {
        "uid": "ord_7f8g9h0i1j2k",
        "status": "active",
        "total_amount": 65000,
        "items": [
            {
                "amount": 50000,
                "notes": "Deskripsi item",
                "product_description": "Kemeja Flanel Ukuran L - Merah",
                "product_name": "Kemeja Flanel"
            },
            {
                "amount": 15000,
                "notes": "Item tambahan",
                "product_description": "Dust Bag",
                "product_name": "Tas"
            }
        ],
        "created_at": "2024-05-15T10:30:00Z",
        "updated_at": "2024-05-15T10:35:00Z"
    }
}
```

**Response Error (Order tidak ditemukan):**

```json
{
    "success": false,
    "message": "Order not found",
    "error_code": "NOT_FOUND"
}
```

**Response Error (UID kosong):**

```json
{
    "success": false,
    "message": "Order UID is required for update.",
    "error_code": "VALIDATION_ERROR"
}
```

**Status Order:**

| Status | Deskripsi |
|--------|-----------|
| `pending` | Order baru dibuat, belum aktif |
| `active` | Order aktif, bisa generate payment link |
| `inactive` | Order tidak aktif |

---

### Method: `list()`

Mengambil daftar order dengan pagination.

**Request:**

```php
$page = 1;
$limit = 10;

$response = $client->orders()->list($page, $limit);
```

**Response Sukses:**

```json
{
    "success": true,
    "data": {
        "orders": [
            {
                "uid": "ord_7f8g9h0i1j2k",
                "status": "active",
                "total_amount": 65000,
                "items": [...],
                "created_at": "2024-05-15T10:30:00Z"
            },
            {
                "uid": "ord_6e7f8g9h0i1j",
                "status": "pending",
                "total_amount": 120000,
                "items": [...],
                "created_at": "2024-05-14T15:20:00Z"
            }
        ],
        "pagination": {
            "current_page": 1,
            "total_pages": 5,
            "total_items": 47,
            "limit": 10
        }
    }
}
```

---

## 2.5 Manajemen Payment Link

Payment link adalah tautan pembayaran yang di-bind ke order tertentu. User akan diarahkan ke halaman pembayaran Xepeng melalui link ini.

### Method: `generate()`

Membuat payment link untuk order tertentu.

**Request:**

```php
$orderUid = 'ord_7f8g9h0i1j2k';

$options = [
    'expired_at' => date('c', strtotime('+2 hours')),  // Format ISO8601
    'callback_url' => 'https://your-app.com/api/xepeng/notification',
    'success_url' => 'https://your-app.com/payment/success?order_id=xxx',
    'cancel_url' => 'https://your-app.com/payment/cancel?order_id=xxx',
];

$response = $client->paymentLinks()->generate($orderUid, $options);
```

**Options Reference:**

| Option | Tipe | Required | Deskripsi |
|--------|------|----------|-----------|
| `expired_at` | string (ISO8601) | Tidak | Waktu kadaluarsa payment link. Format: `2024-05-15T18:00:00Z` |
| `callback_url` | string (URL) | Tidak | URL untuk menerima notification payment |
| `success_url` | string (URL) | Tidak | URL redirect setelah payment berhasil |
| `cancel_url` | string (URL) | Tidak | URL redirect jika user cancel payment |

**Response Sukses:**

```json
{
    "success": true,
    "message": "Payment link generated successfully",
    "data": {
        "uid": "pl_m3n4o5p6q7r",
        "order_uid": "ord_7f8g9h0i1j2k",
        "payment_url": "https://staging-pay.xepeng.com/pay/s8t9u0v1w2x3y4z",
        "amount": 65000,
        "status": "pending",
        "expired_at": "2024-05-15T12:30:00Z",
        "callback_url": "https://your-app.com/api/xepeng/notification",
        "success_url": "https://your-app.com/payment/success?order_id=ord_7f8g9h0i1j2k",
        "cancel_url": "https://your-app.com/payment/cancel?order_id=ord_7f8g9h0i1j2k",
        "created_at": "2024-05-15T10:35:00Z"
    }
}
```

**Response Error (Order UID kosong):**

```json
{
    "success": false,
    "message": "Order UID is required to generate payment link.",
    "error_code": "VALIDATION_ERROR"
}
```

**Response Error (Order tidak aktif):**

```json
{
    "success": false,
    "message": "Order must be active to generate payment link.",
    "error_code": "INVALID_ORDER_STATUS"
}
```

---

### Method: `get()`

Mengambil detail payment link berdasarkan UID.

**Request:**

```php
$paymentLinkUid = 'pl_m3n4o5p6q7r';
$response = $client->paymentLinks()->get($paymentLinkUid);
```

**Response Sukses:**

```json
{
    "success": true,
    "data": {
        "uid": "pl_m3n4o5p6q7r",
        "order_uid": "ord_7f8g9h0i1j2k",
        "payment_url": "https://staging-pay.xepeng.com/pay/s8t9u0v1w2x3y4z",
        "amount": 65000,
        "status": "pending",
        "expired_at": "2024-05-15T12:30:00Z",
        "callback_url": "https://your-app.com/api/xepeng/notification",
        "success_url": "https://your-app.com/payment/success?order_id=ord_7f8g9h0i1j2k",
        "cancel_url": "https://your-app.com/payment/cancel?order_id=ord_7f8g9h0i1j2k",
        "created_at": "2024-05-15T10:35:00Z",
        "paid_at": null,
        "payment_method": null
    }
}
```

**Status Payment Link:**

| Status | Deskripsi |
|--------|-----------|
| `pending` | Menunggu pembayaran |
| `paid` | Sudah dibayar |
| `expired` | Kadaluarsa |
| `cancelled` | Dibatalkan |

---

### Method: `list()`

Mengambil daftar payment link.

**Request:**

```php
$response = $client->paymentLinks()->list();
```

**Response Sukses:**

```json
{
    "success": true,
    "data": {
        "payment_links": [
            {
                "uid": "pl_m3n4o5p6q7r",
                "order_uid": "ord_7f8g9h0i1j2k",
                "payment_url": "https://staging-pay.xepeng.com/pay/s8t9u0v1w2x3y4z",
                "amount": 65000,
                "status": "pending",
                "expired_at": "2024-05-15T12:30:00Z",
                "created_at": "2024-05-15T10:35:00Z"
            },
            {
                "uid": "pl_k1l2m3n4o5p",
                "order_uid": "ord_j0i1h2g3f4e",
                "payment_url": "https://staging-pay.xepeng.com/pay/d5e6f7g8h9i0",
                "amount": 120000,
                "status": "paid",
                "expired_at": "2024-05-14T18:00:00Z",
                "created_at": "2024-05-14T14:00:00Z",
                "paid_at": "2024-05-14T15:30:00Z",
                "payment_method": "bank_transfer"
            }
        ],
        "pagination": {
            "current_page": 1,
            "total_pages": 3,
            "total_items": 25,
            "limit": 10
        }
    }
}
```

---

### Method: `inactivate()`

Membatalkan (menonaktifkan) payment link.

**Request:**

```php
$paymentLinkUid = 'pl_m3n4o5p6q7r';
$response = $client->paymentLinks()->inactivate($paymentLinkUid);
```

**Response Sukses:**

```json
{
    "success": true,
    "message": "Payment link inactivated successfully",
    "data": {
        "uid": "pl_m3n4o5p6q7r",
        "order_uid": "ord_7f8g9h0i1j2k",
        "status": "inactive",
        "updated_at": "2024-05-15T11:00:00Z"
    }
}
```

**Response Error:**

```json
{
    "success": false,
    "message": "Payment link not found",
    "error_code": "NOT_FOUND"
}
```

---

## 2.6 Integrasi Laravel

### Konfigurasi

Tambahkan konfigurasi di `config/services.php`:

```php
'xepeng' => [
    'client_id' => env('XEPENG_CLIENT_ID'),
    'client_secret' => env('XEPENG_CLIENT_SECRET'),
    'is_production' => env('XEPENG_PRODUCTION', false),
],
```

Tambahkan di `.env`:

```env
XEPENG_CLIENT_ID=your_client_id
XEPENG_CLIENT_SECRET=your_client_secret
XEPENG_PRODUCTION=false
```

### Service Class

Buat service class untuk mengelola client:

```php
<?php

namespace App\Services;

use Xepeng\OAuth\Integration\XepengIntegrationClient;

class XepengService
{
    private ?XepengIntegrationClient $client = null;

    public function client(): XepengIntegrationClient
    {
        if ($this->client === null) {
            $this->client = new XepengIntegrationClient(
                config('services.xepeng.client_id'),
                config('services.xepeng.client_secret'),
                config('services.xepeng.is_production')
            );
        }

        return $this->client;
    }

    public function createOrder(array $items): array
    {
        return $this->client()->orders()->create($items);
    }

    public function activateOrder(string $orderUid, array $items): array
    {
        return $this->client()->orders()->update($orderUid, $items, 'active');
    }

    public function generatePaymentLink(string $orderUid, array $options = []): array
    {
        return $this->client()->paymentLinks()->generate($orderUid, $options);
    }

    public function getPaymentStatus(string $paymentLinkUid): array
    {
        return $this->client()->paymentLinks()->get($paymentLinkUid);
    }
}
```

### Controller Contoh

```php
<?php

namespace App\Http\Controllers;

use App\Services\XepengService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    private XepengService $xepeng;

    public function __construct(XepengService $xepeng)
    {
        $this->xepeng = $xepeng;
    }

    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.amount' => 'required|numeric|min:1',
            'items.*.product_name' => 'required|string',
        ]);

        // Step 1: Buat Order
        $orderResponse = $this->xepeng->createOrder($request->items);
        $orderUid = $orderResponse['data']['uid'];

        // Step 2: Update ke Active
        $this->xepeng->activateOrder($orderUid, $request->items);

        // Step 3: Generate Payment Link
        $paymentResponse = $this->xepeng->generatePaymentLink($orderUid, [
            'expired_at' => now()->addHours(2)->toIso8601String(),
            'callback_url' => url('/api/xepeng/webhook'),
            'success_url' => url('/payment/success'),
            'cancel_url' => url('/payment/cancel'),
        ]);

        return response()->json([
            'success' => true,
            'payment_url' => $paymentResponse['data']['payment_url'],
            'order_uid' => $orderUid,
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        // Handle payment notification
        // $request->all() berisi data payment status

        return response()->json(['received' => true]);
    }
}
```

### Route Webhook

```php
Route::post('/api/xepeng/webhook', [PaymentController::class, 'webhook']);
```

---

## 3. Error Handling

### OAuth SDK Exceptions

```php
use Xepeng\OAuth\Exceptions\OAuthException;

try {
    $client = new Client([...]);
    $tokens = $client->handleCallback();
} catch (OAuthException $e) {
    // Handle OAuth errors
    $message = $e->getMessage();  // "Invalid state parameter"
    $error = $e->getError();      // "invalid_state"
    $code = $e->getCode();        // HTTP status code
}
```

### Integration SDK Exceptions

```php
use Xepeng\OAuth\Integration\Exceptions\XepengException;

try {
    $client = new XepengIntegrationClient(...);
    $response = $client->orders()->create($items);
} catch (XepengException $e) {
    // Handle integration errors
    $message = $e->getMessage();  // "Order items cannot be empty."
    $code = $e->getCode();        // HTTP status code atau 0
}
```

### Common Error Scenarios

| Skenario | Error | Solusi |
|----------|-------|--------|
| Order items kosong | `Order items cannot be empty.` | Pastikan array items tidak kosong |
| Amount <= 0 | `Item at index X must have a positive amount.` | Gunakan amount > 0 |
| Product name kosong | `Item at index X must have a product_name.` | Pastikan setiap item punya product_name |
| Order UID kosong | `Order UID is required for update.` | Berikan order_uid yang valid |
| Order belum active | `Order must be active to generate payment link.` | Panggil update() dengan status 'active' |
| PKCE state mismatch | `PKCE state mismatch or expired.` | State tidak cocok atau sudah expired, mulai ulang flow |
| Missing code/state | `Missing code or state in callback` | Cek parameter callback dari Xepeng |

---

## 4. FAQ (Pertanyaan yang Sering Diajukan)

### Q: Apa perbedaan OAuth SDK dan Integration SDK?

**A:**
- **OAuth SDK** - Digunakan untuk autentikasi end-user (user login dengan akun Xepeng). Menggunakan OAuth 2.0 dengan PKCE.
- **Integration SDK** - Digunakan untuk backend-to-backend integration. Tidak memerlukan login user, langsung pakai client credentials (client_id + client_secret) dengan signature HMAC.

### Q: Kapan harus pakai OAuth SDK?

**A:** Gunakan OAuth SDK ketika kamu ingin:
- User login menggunakan akun Xepeng
- Akses data user (profile, email, dll)
- User melakukan autentikasi, bukan hanya payment

### Q: Kapan harus pakai Integration SDK?

**A:** Gunakan Integration SDK ketika kamu:
- Backend system yang perlu memproses payment
- Tidak memerlukan user login
- Sudah punya sistem autentikasi sendiri
- Contoh: e-commerce, membership site, dll

### Q: Kenapa harus buat order duluan?

**A:** Order adalah representasi dari transaksi yang akan dibayar. Payment link di-bind ke order tertentu. Ini memastikan:
1. Tracking transaksi yang jelas
2. Items sudah didefinisikan sebelum payment
3. Amount sudah fix sebelum payment link dibuat

### Q: Kenapa harus update order ke 'active'?

**A:** Status `pending` → `active` memberikan kesempatan untuk:
1. Modify items sebelum finalize
2. Konfirmasi akhir dari merchant
3. Approval workflow jika diperlukan
4. Beberapa sistem butuh validasi sebelum payment

### Q: Berapa lama payment link aktif?

**A:** Default tidak ada expiry. Gunakan option `expired_at` untuk membatasi waktu validity. Contoh: 2 jam setelah generated.

### Q: Apakah bisa generate ulang payment link untuk order yang sama?

**A:** Ya, bisa. Order dengan status `active` bisa generate multiple payment links.

### Q: Apakah bisa ubah items setelah order dibuat?

**A:** Ya, gunakan method `update()` untuk ubah items dan/atau status.

### Q: Error "PKCE state mismatch" - bagaimana cara fix?

**A:** Error ini terjadi karena:
1. State tidak cocok - kemungkinan ada lebih dari satu callback request
2. State expired - state sudah kadaluarsa (> 10 menit)

Solusi: Mulai ulang OAuth flow dari awal (redirect ke getAuthorizationUrl lagi).

### Q: Apakah SDK ini thread-safe?

**A:** Tidak ada mekanisme locking internal. Jika menggunakan multi-thread/concurrent, pastikan masing-masing thread punya storage terpisah atau gunakan database storage dengan proper locking.

---

## 5. Lisensi

MIT License - Xepeng 2024

---

## 6. Dukungan

Untuk pertanyaan dan laporan bug:
- Email: hello@xepeng.com
- Website: https://xepeng.com
