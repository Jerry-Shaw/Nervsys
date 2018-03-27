<?php

/**
 * Memcached Extension
 *
 * Author tggtzbh <tggtzbh@sina.coms>
 *
 */

namespace ext;

class memcached
{
    /**
     * Default settings for Memcache
     */
    public static $host           = '127.0.0.1';
    public static $port           = 11211;
    public static $compression    = false;
    public static $timeout        = 1;
    private static $memcached     = null;


    /**
     * Init Memcache
     *
     * @return \Memcache
     * @throws \Exception
     */
    private static function init(): \Memcached
    {
        $mem=new \Memcached();
        $mem->addServer(self::$host,self::$port);
        $mem->setOption($mem::OPT_COMPRESSION, self::$compression);
        $mem->setOption($mem::OPT_CONNECT_TIMEOUT, self::$timeout*1000);
        if($mem->getStats()===false)
        {
            throw new \Exception('Memcached: Host or Port ERROR!');
        }
        self::$memcached=$mem;
        return $mem;
    }

    /**
     * Get cache
     *
     * @param string $key
     *
     * @return mixed
     */
    public static function get($key)
    {
        if(self::$memcached===null)
        {
            self::init();
        }
        $mem=self::$memcached;
        $ret=$mem->get($key);
        if($mem->getResultCode()===$mem::RES_NOTFOUND)
        {
            $ret=null;
        }
        return $ret;
    }

    /**
     * Set cache
     *
     * @param string $key
     * @param mixed $value
     *
     * @return bool
     */
    public static function set($key,$value) :bool
    {
        if(self::$memcached===null)
        {
            self::init();
        }
        $mem=self::$memcached;
        return $mem->set($key,$value);
    }
}