<?php
/**
 * Author：Lzxyz
 * Date：2019/12/5 13:49
 * Desc：Sha1WithRsa.php
 */
namespace LazyBench\Tax\WrJs\Util;

class Sha1WithRSA
{
    private $pubKey = '';
    private $priKey = '';

    public function __construct($priKey, $pubKey)
    {
        $this->pubKey = $this->fmtPubKey($pubKey);
        $this->priKey = $this->fmtPriKey($priKey);
    }

    /**
     * 签名（私钥签名）
     * Author：Lzxyz
     * @param string $data
     * @return string
     */
    public function sign(string $data)
    {
        $key = openssl_get_privatekey($this->priKey);
        openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA1);
        openssl_free_key($key);
        $sign = base64_encode($signature);
        return $sign;
    }

    /**
     * 验证（公钥验证）
     * Author：Lzxyz
     * @param string $data
     * @param string $sign
     * @return bool
     */
    public function verify(string $data, string $sign)
    {
        $key = openssl_get_publickey($this->pubKey);
        $verify = openssl_verify($data, base64_decode($sign), $key, OPENSSL_ALGO_SHA1);
        openssl_free_key($key);
        return $verify == 1;
    }

    /**
     * 格式化公钥
     * Author：Lzxyz
     * @param $pubKey
     * @return string
     */
    private function fmtPubKey($pubKey)
    {
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split($pubKey, 64, "\n") . '-----END PUBLIC KEY-----';
    }

    /**
     * 格式化私钥
     * Author：Lzxyz
     * @param $priKey
     * @return string
     */
    private function fmtPriKey($priKey)
    {
        return "-----BEGIN PRIVATE KEY-----\n" . chunk_split($priKey, 64, "\n") . '-----END PRIVATE KEY-----';
    }
}

