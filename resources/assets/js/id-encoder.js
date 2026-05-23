/**
 * ID Encoder/Decoder for JavaScript
 * Matches the PHP IdEncoder class behavior
 * Encrypts and decrypts database IDs for URL-safe usage
 */

const IdEncoder = (function() {
    const SALT = 'TMS-Secret-Key-2024-Change-This-In-Production';
    const ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
    
    /**
     * Simple CRC32 implementation (matches PHP's crc32)
     */
    function crc32(str) {
        let crc = 0 ^ (-1);
        for (let i = 0; i < str.length; i++) {
            crc = (crc >>> 8) ^ crcTable[(crc ^ str.charCodeAt(i)) & 0xFF];
        }
        return (crc ^ (-1)) >>> 0;
    }
    
    // CRC32 lookup table
    const crcTable = (function() {
        let table = [];
        for (let n = 0; n < 256; n++) {
            let c = n;
            for (let k = 0; k < 8; k++) {
                c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
            }
            table[n] = c;
        }
        return table;
    })();
    
    /**
     * Encode a numeric ID to a URL-safe string
     * @param {number} id - The database ID to encode
     * @returns {string} The encoded ID or original ID if encryption is disabled
     */
    function encode(id) {
        if (typeof id !== 'number' || id < 1 || !Number.isInteger(id)) {
            return '';
        }

        // If encryption is disabled, return the original ID
        if (typeof window !== 'undefined' && window.ENCRYPT_IDS === false) {
            return String(id);
        }

        // Simple reversible encoding: XOR with key, then hex encode
        const key = 0x5A3C8F1B;
        const xorValue = id ^ key;

        // Convert to hex and make it URL-safe
        const hex = xorValue.toString(16);
        const prefix = 'id';

        return prefix + hex;
    }
    
    /**
     * Decode a URL-safe string back to numeric ID
     * @param {string} encoded - The encoded ID
     * @returns {number|false} The decoded ID or false on failure
     */
    function decode(encoded) {
        if (!encoded || typeof encoded !== 'string') {
            return false;
        }

        // If encryption is disabled, return the original ID as a number
        if (typeof window !== 'undefined' && window.ENCRYPT_IDS === false) {
            const num = parseInt(encoded, 10);
            return !isNaN(num) && num > 0 ? num : false;
        }

        // If it's a plain numeric ID (no prefix), return it as-is for backward compatibility
        if (/^\d+$/.test(encoded)) {
            const num = parseInt(encoded, 10);
            return !isNaN(num) && num > 0 ? num : false;
        }

        // Remove prefix if present
        encoded = encoded.replace(/^id/, '');

        // Check if valid hex
        if (!/^[0-9a-fA-F]+$/.test(encoded)) {
            return false;
        }

        // Convert from hex
        const xorValue = parseInt(encoded, 16);

        // Reverse the XOR
        const key = 0x5A3C8F1B;
        const originalId = xorValue ^ key;

        return originalId > 0 ? originalId : false;
    }
    
    /**
     * Batch encode multiple IDs
     * @param {Array<number>} ids - Array of numeric IDs
     * @returns {Array<string>} Array of encoded IDs
     */
    function encodeArray(ids) {
        return ids.map(encode);
    }
    
    /**
     * Batch decode multiple IDs
     * @param {Array<string>} encoded - Array of encoded IDs
     * @returns {Array<number|false>} Array of decoded IDs (false for invalid)
     */
    function decodeArray(encoded) {
        return encoded.map(decode);
    }
    
    // Public API
    return {
        encode: encode,
        decode: decode,
        encodeArray: encodeArray,
        decodeArray: decodeArray
    };
})();

// Make it available globally
if (typeof window !== 'undefined') {
    window.IdEncoder = IdEncoder;
}
