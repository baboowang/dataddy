<?php
/**
 * AES Cipher Library
 * Based on Federal Information Processing Standards Publication 197 - 26th November 2001
 * @author Marcin F. Wiśniowski <marcin.wisniowski@mfw.pl>
 * @version 1.0.5
 * @license http://opensource.org/licenses/lgpl-3.0.html The GNU Lesser General Public License
 * @package AES
 */

/**
 * @see AES
 */
require_once(dirname(__FILE__) . '/AES.php');

/**
 * Text cipher class
 * This class is using AES crypt algoritm
 * @author Marcin F. Wiśniowski <marcin.wisniowski@mfw.pl>
 * @version 1.0.0
 * @license http://opensource.org/licenses/lgpl-3.0.html The GNU Lesser General Public License
 */
class AESCipher {
/** @var object An AES cipher object. */
    private $_cipher;
/** @var int Key strenght */
    private $_strenght;
/** @const int Maximum number of bytes in encryption chunk */
    const   BYTE_LIMIT = 16;
/**
 * Class constructor
 * It initialize cipher object with proper key lenght. By default it uses 128bit.
 * @param int Key strength, Takes AES Class const values
 * @see AES::AES128
 */
    public function __construct($strength=AES::AES128) {
        $this->_cipher = new AES($strength);
        $this->_strenght = $strength;
    }

    public static function getInstance()
    {
        static $instance = NULL;

        if (is_null($instance)) {
            $instance = new self();
        }

        return $instance;
    }
/**
 * Generates Hexadecimal key from inserted pass phrase
 * @param string Pass phrase
 * @return string Hexadecimal key
 */
    private function _generateKey($password) {
        switch ($this->_strenght) {
            case AES::AES256:
                return md5($password).md5($password.'1');
            case AES::AES192:
                return sha1($password);
            case AES::AES128:
            default:
                return md5($password);
        }
    }
/**
 * Encrypt method
 * @param string input string
 * @param string Pass phrase
 * @return string Cryptext
 */
    public function encrypt($content, $password) {
        $key = $this->_generateKey($password);
        $input = str_split($this->_cipher->stringToHex($content), self::BYTE_LIMIT*2);
        $output = '';
        foreach ($input as $chunk)
            $output .= $this->_cipher->encrypt($chunk, $key);
        return $output;
    }
/**
 * Decrypt method
 * @param string Cryptext
 * @param string Pass phrase
 * @return string Decoded message
 */
    public function decrypt($cryptext, $password) {
        $key = $this->_generateKey($password);
        $input = str_split($cryptext, self::BYTE_LIMIT*2);
        $output = '';
        foreach ($input as $chunk)
            $output .= $this->_cipher->decrypt($chunk, $key);
        return $this->_cipher->hexToString($output);
    }
}
?>
