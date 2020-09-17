<?php
namespace GG\Cache;

class Redis
{
    protected static $CACHE = array();

    /**
     * @return String or Bool: If key didn't exist, FALSE is returned. Otherwise, the value related to this key is returned
     */
    public static function GET($key, $clusterId = 'default', $enableMethodCache = TRUE)
    {
        if ($enableMethodCache && isset(self::$CACHE['GET'][$key])) {
            $value = self::$CACHE['GET'][$key];
            log_message('Cache_Redis GET from method cache. key:' . $key . ' value:' . json_encode($value), LOG_DEBUG);
            return $value;
        }
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $value = $redis->get($key);
        if ($enableMethodCache) {
            self::$CACHE['GET'][$key] = $value;
        }
        log_message('Cache_Redis GET. key:' . $key . ' value:' . $value, LOG_DEBUG);
        return $value;
    }

    /**
     * @return Bool TRUE if the command is successful.
     */
    public static function SET($key, $value, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->set($key, $value);
        log_message('Cache_Redis SET. key:' . $key . ' value:' . $value . ' result:' . $result, LOG_DEBUG);
        return $result;
    }

    /**
     * @return Bool TRUE if the command is successful.
     */
    public static function SETEX($key, $value, $cacheTime, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->setex($key, $cacheTime, $value);
        log_message('Cache_Redis SETEX. key:' . $key . ' value:' . $value . ' ttl:' . $cacheTime . ' result:' . $result, LOG_DEBUG);
        return $result;
    }

    /**
     * @param string $key
     * @param int $cacheTime ttl. The key's remaining Time To Live, in seconds
     * @return BOOL: TRUE in case of success, FALSE in case of failure.
     */
    public static function EXPIRE($key, $cacheTime, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->expire($key, $cacheTime);
        log_message('Cache_Redis EXPIRE. key:' . $key . ' ttl:' . $cacheTime . ' result:' . $result, LOG_DEBUG);
        return $result;
    }

    public static function EXPIREAT($key, $timestamp, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->expireAt($key, $timestamp);
        return $result;
    }

    public static function KEYS($pattern, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->keys($pattern);
        return $result;
    }

    /**
     * @return int Size of the value after the append
     */
    public static function APPEND($key, $value, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->append($key, $value);
        return $result;
    }

    public static function GETRANGE($key, $start, $end, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->getRange($key, $start, $end);
        return $result;
    }

    public static function SETRANGE($key, $offset, $value, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->setRange($key, $offset, $value);
        return $result;
    }

    public static function STRLEN($key, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->strlen($key);
        return $result;
    }

    public static function GETBIT($key, $offset, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->getBit($key, $offset);
        return $result;
    }

    /**
     * @param $value bool or int (1 or 0)
     */
    public static function SETBIT($key, $offset, $value, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->setBit($key, $offset, $value);
        return $result;
    }

    public static function TTL($key, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->ttl($key);
        return $result;
    }

    public static function PERSIST($key, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->persist($key);
        return $result;
    }

    public static function MSET(array $pairs, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->mset($pairs);
        return $result;
    }

    public static function MSETNX(array $pairs, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->msetnx($pairs);
        return $result;
    }

    /**
     * 多个key用array
     * @return 删除key的数量
     */
    public static function DEL($key, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->delete($key);
        log_message('Cache_Redis DEL. key:' . $key . ' result:' . $result, LOG_DEBUG);
        return $result;
    }

    /**
     * @return If the key exists, return TRUE, otherwise return FALSE.
     */
    public static function EXISTS($key, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->exists($key);
        log_message('Cache_Redis EXISTS. key:' . $key . ' result:' . $result, LOG_DEBUG);
        return $result;
    }

    public static function INCR($key, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->incr($key);
        log_message('Cache_Redis INCR. key:'. $key. ' result:' . $result, LOG_DEBUG);
        return $result;
    }

    public static function INCRBY($key, $increment, $clusterId = 'default')
    {
        $increment = intval($increment);
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->incrBy($increment);
        log_message('Cache_Redis INCRBY. key:'. $key. ' decrement:' .$increment .' result:' . $result, LOG_DEBUG);
        return $result;
    }

    public static function DECR($key, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->decr($key);
        log_message('Cache_Redis DECR. key:'. $key. ' result:' . $result, LOG_DEBUG);
        return $result;
    }

    public static function DECRBY($key, $decrement, $clusterId = 'default')
    {
        $decrement = intval($decrement);
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->decrBy($key, $decrement);
        log_message('Cache_Redis DECRBY. key:'. $key. ' decrement:' .$decrement .' result:' . $result, LOG_DEBUG);
        return $result;
    }

    public static function MGET(array $key, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->getMultiple($key);
        return $result;
    }

    public static function GETSET($key, $value, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->getSet($key, $value);
        return $result;
    }

    //list api
    /**
     * @return 失败返回false,成功返回list长度
     */
    public static function LPUSH($key, $value, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->lPush($key, $value);
        return $result;
    }

    public static function LPUSHX($key, $value, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->lPushx($key, $value);
        return $result;
    }

    public static function RPUSH($key, $value, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->rPush($key, $value);
        return $result;
    }

    public static function RPUSHX($key, $value, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->rPushx($key, $value);
        return $result;
    }

    public static function RPOP($key, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->rPop($key);
        return $result;
    }

    public static function BLPOP($key, $timeout = 5, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->blPop($key, $timeout);
        return $result;
    }

    public static function BRPOP($key, $timeout = 5, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->brPop($key, $timeout);
        return $result;
    }

    public static function LLEN($key, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->lSize($key);
        return $result;
    }

    public static function LINDEX($key, $index, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->lGet($key, $index);
        return $result;
    }

    /**
     * @return BOOL TRUE if the new value is setted. FALSE if the index is out of range, or data type identified by key is not a list.
     */
    public static function LSET($key, $index, $value, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->lSet($key, $index, $value);
        return $result;
    }

    public static function LRANGE($key, $start, $end, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->lRange($key, $start, $end);
        return $result;
    }

    public static function LTRIM($key, $start, $end, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->lTrim($key, $start, $end);
        return $result;
    }

    public static function LREM($key, $value, $count, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->lRem($key, $value, $count);
        return $result;
    }

    /**
     *
     * @param $position Redis::BEFORE  Redis::AFTER
     */
    public static function LINSERT($key, $position, $pivot, $value, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->lRem($key, $value, $count);
        return $result;
    }

    //set api
    //hash api
    /**
     * @return LONG 1 if value didn't exist and was added successfully, 0 if the value was already present and was replaced, FALSE if there was an error.
     */
    public static function HSET($key, $field, $value, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->hSet($key, $field, $value);
        return $result;
    }

    /**
     * @return BOOL TRUE if the field was set, FALSE if it was already present.
     */
    public static function HSETNX($key, $field, $value, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->hSetNx($key, $field, $value);
        return $result;
    }

    public static function HGET($key, $field, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->hGet($key, $field);
        return $result;
    }

    public static function HLEN($key, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->hLen($key);
        return $result;
    }

    public static function HDEL($key, $field, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->hDel($key, $field);
        return $result;
    }

    public static function HKEYS($key, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->hKeys($key);
        return $result;
    }

    public static function HVALS($key, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->hVals($key);
        return $result;
    }

    /**
     * @return An array of elements, the contents of the hash.
     */
    public static function HGETALL($key, $clusterId = 'default', $enableMethodCache = TRUE)
    {
        if ($enableMethodCache && isset(self::$CACHE['HGETALL'][$key])) {
            $result = self::$CACHE['HGETALL'][$key];
            log_message('Cache_Redis HGETALL from method cache. key:' . $key . ' result:' . json_encode($result), LOG_DEBUG);
            return $result;
        }
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->hGetAll($key);
        if ($enableMethodCache) {
            self::$CACHE['HGETALL'][$key] = $result;
        }
        log_message('Cache_Redis HGETALL. key:' . $key . ' result:' . json_encode($result), LOG_DEBUG);
        return $result;
    }

    /**
     * @return  If the member exists in the hash table, return TRUE, otherwise return FALSE
     */
    public static function HEXISTS($key, $field, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->hExists($key, $field);
        log_message('Cache_Redis HEXISTS. key:' . $key . ' field:' . $field . ' result:' . $result, LOG_DEBUG);
        return $result;
    }

    public static function HINCRBY($key, $field, $increment, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->hIncrBy($key, $field, $increment);
        return $result;
    }

    public static function HMGET($key, $fieldList, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->hMGet($key, $fieldList);
        return $result;
    }

    /**
     * @param array $hashTable key → value array
     * @return BOOL
     */
    public static function HMSET($key, $hashTable, $clusterId = 'default')
    {
        $redis = \GG\Db\Redis::getInstance($clusterId);
        $result = $redis->hMset($key, $hashTable);
        log_message('Cache_Redis HMSET. key:' . $key . ' hashtable:' . json_encode($hashTable) . ' result:' . json_encode($result), LOG_DEBUG);
        return $result;
    }
}
