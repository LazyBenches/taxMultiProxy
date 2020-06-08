<?php
/**
 * Author：Lzxyz
 * Date：2019/8/13 9:49
 * Desc：DesUtil.php
 * 3des加密数据
 */

namespace LazyBench\TaxMultiProxy\App\Yzh\Util;


class DesUtil
{
    private $des3key; // 密钥向量

    function __construct($des3key)
    {
        $this->des3key = $des3key;
    }

    /**
     * 3des加密
     * @param $value
     * @return string
     */
    public function encrypt($value)
    {
        $iv = substr($this->des3key, 0, 8);
        $ret = openssl_encrypt($value, 'DES-EDE3-CBC', $this->des3key, 0, $iv);
        if (false === $ret) {
            return openssl_error_string();
        }
        return $ret;
    }

    /**
     * 3des解密
     * @param $value
     * @return string
     */
    public function decrypt($value)
    {
        $iv = substr($this->des3key, 0, 8);
        $ret = openssl_decrypt($value, 'DES-EDE3-CBC', $this->des3key, 0, $iv);
        if (false === $ret) {
            return openssl_error_string();
        }
        return $ret;
    }
}