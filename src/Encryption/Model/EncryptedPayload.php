<?php

namespace App\Encryption\Model;

use Exception;

final class EncryptedPayload
{
    public const VERSION = 1;

    public static function pack(string $algorithm, string $encryptedKey, string $iv, string $tag, string $ciphertext): string
    {
        $algorithmBytes = function_exists('mb_convert_encoding') ? mb_convert_encoding($algorithm, 'UTF-8', 'ISO-8859-1') : $algorithm;
        $header = pack('C', self::VERSION)
            . pack('n', strlen($algorithmBytes))
            . $algorithmBytes
            . pack('n', strlen($encryptedKey))
            . $encryptedKey
            . pack('n', strlen($iv))
            . $iv
            . pack('n', strlen($tag))
            . $tag;

        return base64_encode($header . $ciphertext);
    }

    public static function unpack(string $payload): array
    {
        $data = base64_decode($payload, true);
        if ($data === false || strlen($data) < 7) {
            throw new Exception('Invalid encrypted payload.');
        }

        $offset = 0;
        $version = ord($data[$offset++]);
        if ($version !== self::VERSION) {
            throw new Exception(sprintf('Unsupported payload version: %d', $version));
        }

        $algorithmLength = unpack('n', substr($data, $offset, 2))[1];
        $offset += 2;
        $algorithm = substr($data, $offset, $algorithmLength);
        $offset += $algorithmLength;

        $encryptedKeyLength = unpack('n', substr($data, $offset, 2))[1];
        $offset += 2;
        $encryptedKey = substr($data, $offset, $encryptedKeyLength);
        $offset += $encryptedKeyLength;

        $ivLength = unpack('n', substr($data, $offset, 2))[1];
        $offset += 2;
        $iv = substr($data, $offset, $ivLength);
        $offset += $ivLength;

        $tagLength = unpack('n', substr($data, $offset, 2))[1];
        $offset += 2;
        $tag = substr($data, $offset, $tagLength);
        $offset += $tagLength;

        $ciphertext = substr($data, $offset);

        return [
            'algorithm' => $algorithm,
            'encrypted_key' => $encryptedKey,
            'iv' => $iv,
            'tag' => $tag,
            'ciphertext' => $ciphertext,
        ];
    }
}
