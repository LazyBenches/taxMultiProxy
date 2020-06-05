<?php
/**
 * Author：Lzxyz
 * Date：2019/8/13 9:49
 * Desc：SignUtil.php
 * 签名算法
 */

namespace LazyBench\TaxMultiProxy\Yzh\Util;


class SignUtil
{
    private $appKey = '';

    public function __construct($appKey)
    {
        $this->appKey = $appKey;
    }

    public function sign($data = '', $mess = '', $timestamp = '')
    {
        return hash_hmac('sha256', "data={$data}&mess={$mess}&timestamp={$timestamp}&key={$this->appKey}", $this->appKey);
    }

    public function verify($params)
    {
        $data = $params['data'] ?? '';
        $mess = $params['mess'] ?? '';
        $timestamp = $params['timestamp'] ?? '';
        $sign = $params['sign'] ?? '';
        $signStr = hash_hmac('sha256', "data={$data}&mess={$mess}&timestamp={$timestamp}&key={$this->appKey}", $this->appKey);
        return $sign == $signStr ? true : false;
    }
}