<?php
namespace GG;

class Session
{
    private $domain;
    private $cookieName;

    public $userID = NULL;
    public $extraData = NULL;

    public static $config = array();

    public function __construct($config = array())
    {
        $this->domain = d(@$config['domain'], @$_SERVER['HTTP_HOST']);
        $this->cookieName = d(@$config['cookieName'], Config::get('cookie.session'));
    }

    public static function getInstance()
    {
        static $instance = NULL;
        if ($instance == NULL) {
            $instance = new self(self::$config);
        }
        return $instance;
    }

    public function isValid()
    {
        return ! empty($this->userID);
    }

    public function clearUserID()
    {
        $this->userID = NULL;
        $this->extraData = NULL;
        self::deleteCookie($this->cookieName, $this->domain);
    }

    public function setUserID($userID, $persist = FALSE, $expireTime = NULL, $extraData = NULL)
    {
        $this->userID = $userID;
        $this->extraData = $extraData;

        if ($persist !== FALSE) {
            // Note: 2592000 is 60*60*24*30 or 30 days
            if ($expireTime === NULL) {
                $expireTime = 2592000;
            }
            $expiration = $expire = time() + $expireTime;
        } else {
            // Note: 172800 is 60*60*24*2 or 2 days
            if ($expireTime === NULL) {
                $expireTime = 172800;
            }
            $expiration = time() + $expireTime;
            // Note: setting $Expire to 0 will cause the cookie to die when the browser closes.
            $expire = 0;
        }

        $cookieName = $this->cookieName;
        $domain = $this->domain;
        $keyData = $userID.'-'.$expiration;
        $data = $userID;
        if ( ! is_null($extraData)) {
            $data .= '-' . $extraData;
        }
        self::setCookie($cookieName, $keyData, array($data, $expiration), $expire, $domain);
    }

    public function getExtraData()
    {
        if ($this->getUserID()) {
            return $this->extraData;
        }

        return NULL;
    }

    public function getUserID()
    {
        if (! is_null($this->userID)) {
            return $this->userID;
        }

        if ($this->checkCookie()) {
            $cookie = $this->parseCookie();
            list($hashKey, $cookieHash, $time, $data, $expire) = $cookie;
            $data = explode('-', $data, 2);
            $userID = $data[0];
            if ($userID) {
                $this->userID = $userID;
                if (count($data) > 1) {
                    $this->extraData = $data[1];
                }

                //Refresh expire time
                if ($expire - $time < 86400 && time() - $time > 300) {
                    $this->setUserID($userID, FALSE, $expire - $time, $this->extraData);
                }

                return $userID;
            }
        }

        return 0;
    }

    protected function parseCookie()
    {
        $cookieName = $this->cookieName;
        if (empty($_COOKIE[$cookieName])) {
            return FALSE;
        }

        $cookie = explode('.', $_COOKIE[$cookieName], 4);
        if (count($cookie) < 4) {
            self::deleteCookie($cookieName, $this->domain);
            return FALSE;
        }

        $dataAndExpiretion = array_pop($cookie);
        if ( ! preg_match('@^(.+)\.(\d+)$@', $dataAndExpiretion, $ma)) {
            self::deleteCookie($cookieName, $this->domain);
            return FALSE;
        }

        $cookie[] = $ma[1];
        $cookie[] = $ma[2];

        return $cookie;
    }

    protected static function getHashSalt()
    {
        #$salt = Config::get('cookie.salt') . @get_client_ip() . @$_SERVER['HTTP_USER_AGENT'];
        $salt = Config::get('cookie.salt') . @$_SERVER['HTTP_USER_AGENT'];

        return $salt;
    }

    protected function checkCookie()
    {
        $cookie = $this->parseCookie();
        if ( ! $cookie) {
            return FALSE;
        }

        $hashMethod = Config::get('cookie.hash_method');
        $cookieSalt = self::getHashSalt();

        list($hashKey, $cookieHash, $time, $data, $expire) = $cookie;

        $cookieName = $this->cookieName;
        if ($expire < time() && $expire != 0) {
            self::deleteCookie($cookieName, $this->domain);
            return FALSE;
        }

        $key = self::hashMAC($hashMethod, $hashKey, $cookieSalt);
        $generatedHash = self::hashMAC($hashMethod, $hashKey, $key);

        if ($generatedHash != $cookieHash) {
            self::deleteCookie($cookieName, $this->domain);
            return FALSE;
        }

        return TRUE;
    }

    public function getCookie()
    {
        return @$_COOKIE[$this->cookieName];
    }

    public function getCookieName()
    {
        return $this->cookieName;
    }

    public static function setCookie($cookieName, $keyData, $cookieContents, $expire, $domain)
    {
        $cookieSalt = self::getHashSalt();
        $hashMethod = Config::get('cookie.hash_method');
        $key = self::hashMAC($hashMethod, $keyData, $cookieSalt);
        $hash = self::hashMAC($hashMethod, $keyData, $key);
        $cookie = array($keyData, $hash, time());
        if (! is_null($cookieContents)) {
            if (! is_array($cookieContents)) {
                $cookieContents = array($cookieContents);
            }
            $cookie = array_merge($cookie, $cookieContents);
        }
        $cookieContents = implode('.', $cookie);
        header("P3P: CP=CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR");
        setcookie($cookieName, $cookieContents, $expire, '/', $domain);
        $_COOKIE[$cookieName] = $cookieContents;
    }

    public static function deleteCookie($cookieName, $domain)
    {
        $expire = strtotime('one year ago');

        header("P3P: CP=CURa ADMa DEVa PSAo PSDo OUR BUS UNI PUR INT DEM STA PRE COM NAV OTC NOI DSP COR");
        setcookie($cookieName, '', $expire, '/', $domain);
        unset($_COOKIE[$cookieName]);
    }

    protected static function hashMAC($hashMethod, $data, $key)
    {
        $packFormats = array('md5' => 'H32', 'sha1' => 'H40');

        if (!isset($packFormats[$hashMethod]))
            return FALSE;

        $packFormat = $packFormats[$hashMethod];
        if (isset($key[63]))
            $key = pack($packFormat, $hashMethod($key));
        else
            $key = str_pad($key, 64, chr(0));

        $innerPad = (substr($key, 0, 64) ^ str_repeat(chr(0x36), 64));
        $outerPad = (substr($key, 0, 64) ^ str_repeat(chr(0x5C), 64));

        return $hashMethod($outerPad . pack($packFormat, $hashMethod($innerPad . $data)));
    }
}
