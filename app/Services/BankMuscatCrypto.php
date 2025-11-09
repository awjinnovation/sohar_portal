<?php

namespace App\Services;

class BankMuscatCrypto
{
    /**
     * Encrypt data using AES-256-GCM
     *
     * @param string $plainText
     * @param string $key
     * @return string
     */
    public static function encrypt(string $plainText, string $key): string
    {
        $method = "AES-256-GCM";
        $initVector = openssl_random_pseudo_bytes(16);
        $tag = '';
        $openMode = openssl_encrypt($plainText, $method, $key, OPENSSL_RAW_DATA, $initVector, $tag);

        return bin2hex($initVector) . bin2hex($openMode . $tag);
    }

    /**
     * Decrypt data using AES-256-GCM
     *
     * @param string $encryptedText
     * @param string $key
     * @return string|false
     */
    public static function decrypt(string $encryptedText, string $key): string|false
    {
        $method = 'AES-256-GCM';
        $encryptedText = hex2bin($encryptedText);
        $iv_len = 16;
        $tag_length = 16;

        $iv = substr($encryptedText, 0, $iv_len);
        $tag = substr($encryptedText, -$tag_length, $iv_len);
        $ciphertext = substr($encryptedText, $iv_len, -$tag_length);

        return openssl_decrypt($ciphertext, $method, $key, OPENSSL_RAW_DATA, $iv, $tag);
    }
}
