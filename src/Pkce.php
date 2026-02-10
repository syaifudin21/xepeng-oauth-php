<?php

namespace Xepeng\OAuth;

class Pkce
{
    /**
     * Generate a cryptographically secure random string.
     *
     * @param int $length
     * @return string
     * @throws \Exception
     */
    public static function generateRandomString($length = 64)
    {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Generate a code verifier.
     *
     * @param int $length
     * @return string
     */
    public static function generateCodeVerifier($length = 64)
    {
        if ($length < 43 || $length > 128) {
            throw new \InvalidArgumentException('Code verifier length must be between 43 and 128');
        }

        $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~';
        $verifier = '';
        $charLen = strlen($charset);
        for ($i = 0; $i < $length; $i++) {
            $verifier .= $charset[random_int(0, $charLen - 1)];
        }
        return $verifier;
    }

    /**
     * Generate a code challenge from a code verifier.
     *
     * @param string $codeVerifier
     * @return string
     */
    public static function generateCodeChallenge($codeVerifier)
    {
        $hash = hash('sha256', $codeVerifier, true);
        return self::base64UrlEncode($hash);
    }

    /**
     * Generate a random state parameter.
     *
     * @return string
     */
    public static function generateState()
    {
        return self::generateCodeVerifier(43);
    }

    /**
     * Base64 URL encode.
     *
     * @param string $data
     * @return string
     */
    private static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
