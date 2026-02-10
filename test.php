<?php

require 'vendor/autoload.php';

use Xepeng\OAuth\Client;
use Xepeng\OAuth\Pkce;
use Xepeng\OAuth\Exceptions\OAuthException;

echo "Loaded Xepeng OAuth PHP SDK\n";

// Test PKCE
echo "Generating PKCE...\n";
$codeVerifier = Pkce::generateCodeVerifier();
$codeChallenge = Pkce::generateCodeChallenge($codeVerifier);
echo "Verifier: $codeVerifier\n";
echo "Challenge: $codeChallenge\n";

if (strlen($codeVerifier) !== 64) {
    echo "ERROR: Verifier length mismatch!\n";
    exit(1);
}

// Test Client instantiation
echo "Instantiating Client...\n";
$client = new Client([
    'client_id' => 'test-client',
    'client_secret' => 'test-secret',
    'redirect_uri' => 'http://localhost/callback',
]);

// Test Authorization URL
echo "Generating Authorization URL...\n";
$url = $client->getAuthorizationUrl();
echo "URL: $url\n";

if (strpos($url, 'code_challenge=') === false) {
    echo "ERROR: URL missing code_challenge!\n";
    exit(1);
}

echo "PASSED: Basic structure verification successful.\n";
