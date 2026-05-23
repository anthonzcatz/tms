<?php
/**
 * ID Encoder/Decoder Helper
 * Encrypts and decrypts database IDs for URL-safe usage
 * Similar to Hashids but using built-in PHP functions
 */

class IdEncoder
{
    private static $salt = 'TMS-Secret-Key-2024-Change-This-In-Production';
    private static $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    private static $encryptIds = null;

    /**
     * Check if ID encryption is enabled in system settings
     *
     * @return bool True if encryption is enabled, false otherwise
     */
    private static function isEncryptionEnabled(): bool
    {
        if (self::$encryptIds !== null) {
            return self::$encryptIds;
        }

        try {
            $row = Database::fetch(
                "SELECT encrypt_ids FROM system_settings LIMIT 1"
            );
            self::$encryptIds = (bool) ($row['encrypt_ids'] ?? true);
        } catch (\Exception $e) {
            // Default to true if setting doesn't exist or query fails
            self::$encryptIds = true;
        }

        return self::$encryptIds;
    }

    /**
     * Encode a numeric ID to a URL-safe string
     *
     * @param int $id The database ID to encode
     * @return string The encoded ID or original ID if encryption is disabled
     */
    public static function encode($id)
    {
        if (!is_numeric($id) || $id < 1) {
            return '';
        }

        $id = (int) $id;

        // If encryption is disabled, return the original ID
        if (!self::isEncryptionEnabled()) {
            return (string) $id;
        }

        // Simple reversible encoding: XOR with key, then hex encode
        $key = 0x5A3C8F1B;
        $xorValue = $id ^ $key;

        // Convert to hex and make it URL-safe
        $hex = dechex($xorValue);
        $prefix = 'id';

        return $prefix . $hex;
    }
    
    /**
     * Decode a URL-safe string back to numeric ID
     *
     * @param string $encoded The encoded ID
     * @return int|false The decoded ID or false on failure
     */
    public static function decode($encoded)
    {
        if (empty($encoded) || !is_string($encoded)) {
            return false;
        }

        // If encryption is disabled, return the original ID as a number
        if (!self::isEncryptionEnabled()) {
            return is_numeric($encoded) && $encoded > 0 ? (int) $encoded : false;
        }

        // If it's a plain numeric ID (no prefix), return it as-is for backward compatibility
        if (is_numeric($encoded) && $encoded > 0) {
            return (int) $encoded;
        }

        // Remove prefix if present
        $encoded = preg_replace('/^id/', '', $encoded);

        if (!ctype_xdigit($encoded)) {
            return false;
        }

        // Convert from hex
        $xorValue = hexdec($encoded);

        // Reverse the XOR
        $key = 0x5A3C8F1B;
        $originalId = $xorValue ^ $key;

        return $originalId > 0 ? $originalId : false;
    }
    
    /**
     * Batch encode multiple IDs
     * 
     * @param array $ids Array of numeric IDs
     * @return array Array of encoded IDs
     */
    public static function encodeArray(array $ids)
    {
        return array_map([self::class, 'encode'], $ids);
    }
    
    /**
     * Batch decode multiple IDs
     * 
     * @param array $encoded Array of encoded IDs
     * @return array Array of decoded IDs (false for invalid)
     */
    public static function decodeArray(array $encoded)
    {
        return array_map([self::class, 'decode'], $encoded);
    }
}
