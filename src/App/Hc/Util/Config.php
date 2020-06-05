<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2020/5/6
 * Time: 17:10
 */

namespace LazyBench\TaxMultiProxy\App\Hc\Util;

class Config
{
    protected static $appId;
    protected static $appSecret;
    protected static $host;
    protected static $timestamp;

    public static function init(array $config)
    {
        !self::$appId && self::$appId = $config['appId'] ?? '';
        !self::$appSecret && self::$appSecret = $config['appSecret'] ?? '';
        !self::$host && self::$host = $config['host'] ?? '';
    }

    public static function getAppId()
    {
        return self::$appId;
    }

    public static function getAppSecret()
    {
        return self::$appSecret;
    }

    public static function getHost()
    {
        return self::$host;
    }

    public static function setTimestamp($timestamp = null)
    {
        self::$timestamp = $timestamp ?: date('Y-m-d H:i:s');
    }

    public static function getTimestamp()
    {
        return self::$timestamp;
    }
}