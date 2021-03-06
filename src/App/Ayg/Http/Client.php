<?php

namespace LazyBench\TaxMultiProxy\App\Ayg\Http;


use LazyBench\TaxMultiProxy\Helper\Tool;

class Client
{
    private $appId;
    private $privateKey;
    private $publicKey;
    private $host;
    private $headers = array('Content-Type: application/json');

    /**
     * Client constructor.
     * @param $appId
     * @param $privateKey
     * @param $host
     * @param $publicKey
     */
    public function __construct($appId, $privateKey, $host, $publicKey)
    {
        $this->appId = $appId;
        $this->privateKey = $privateKey;
        $this->host = $host;
        $this->publicKey = $publicKey;
    }

    /**
     * Author:LazyBench
     *
     * @param $path
     * @return string
     */
    public function buildUrl($path): string
    {
        return $this->host.ltrim($path, '/');
    }

    public function setHeaders($headers): void
    {
        $this->headers = $headers;
    }

    /**
     * Author:LazyBench
     *
     * @param $path
     * @param $params
     * @return mixed
     * @throws \Exception
     */
    public function get($path, $params)
    {
        if (is_array($params)) {
            $params = '?'.$this->buildQueryString($params);
        }
        $url = $this->buildUrl($path).$params;
        return Tool::sendRequest('GET', $this->headers, $url);
    }


    /**
     * Author:LazyBench
     *
     * @param array $params
     * @param false $isSubset
     * @return string
     */
    public function buildQueryString(array $params, $isSubset = false): string
    {
        $data = [];
        ksort($params, SORT_NATURAL);
        $isAssocArray = true;
        $count = 0;
        foreach ($params as $key => $value) {
            //判断是否顺序数组
            if ($isAssocArray) {
                if ($count != $key) {
                    $isAssocArray = false;
                }
                $count++;
            }
            if ($value === null) {
                continue;
            }
            if (is_array($value)) {
                $value = $this->buildQueryString($value, true);
            }
            $data[$key] = urldecode($value);
        }
        if ($isSubset) {
            if ($isAssocArray) {
                return '['.implode(',', $data).']';
            }
            return '{'.http_build_query($data).'}';
        }
        return http_build_query($data);
    }

    /**
     * Author:LazyBench
     *
     * @param $path
     * @param $data
     * @return mixed
     * @throws \Exception
     */
    public function post($path, $data)
    {
        $params = [
            'appId' => $this->appId,
            'nonce' => Tool::getRandomString(32),
            'timestamp' => time(),
            'data' => $data,
        ];
        $params['sign'] = $this->rsaSign($params);
        $params = json_encode($params);
        $url = $this->buildUrl($path);
        return Tool::sendRequest('POST', $this->headers, $url, $params);
    }


    /**
     * Author:LazyBench
     *
     * @param $path
     * @param $data
     * @param $method
     * @return mixed
     * @throws \Exception
     */
    public function postPay($path, $data, $method)
    {
        $timestamp = time();
        $params = [
            'appId' => $this->appId,
            'data' => $data,
            'method' => $method,
            'nonce' => Tool::getRandomString(32),
            'timestamp' => date('Y-m-d H:i:s', $timestamp),
            'signType' => 'RSA2',
            'version' => '1.0',
        ];
        $params['sign'] = $this->rsaSign($params);
        $params = json_encode($params);
        $url = $this->buildUrl($path);
        return Tool::sendRequest('POST', $this->headers, $url, $params);
    }

    /**
     * Author:LazyBench
     *
     * @param $path
     * @param $queryParams
     * @param $formData
     * @return mixed
     * @throws \Exception
     */
    public function multipartPost($path, $queryParams, $formData)
    {
        $signData = [];
        if (is_array($formData)) {
            foreach ($formData as $key => $item) {
                if (is_file($item)) {
                    $signData[$key] = md5_file($item);
                    $formData[$key] = curl_file_create(realpath($item), mime_content_type($item));
                }
            }
        }
        if (is_array($queryParams)) {
            $queryParams['appId'] = $this->appId;
            $queryParams['nonce'] = Tool::getRandomString(32);
            $queryParams['timestamp'] = time();
            $signData = array_merge($signData, $queryParams);
            $queryParams['sign'] = $this->rsaSign($signData);
            $queryString = '?'.http_build_query($queryParams);
        } else {
            $queryString = $queryParams ? "?{$queryParams}" : '';
        }
        $url = $this->buildUrl($path).$queryString;
        return Tool::sendRequest('POST', $this->headers, $url, $formData);
    }


    /**
     * Sign
     *
     * @param array $data
     * @return string
     */
    public function rsaSign(array $data): string
    {
        unset($data['signType']);
        $query = $this->buildQueryString($data);
        $query = urldecode($query);
        $privateKey = "-----BEGIN RSA PRIVATE KEY-----\n".wordwrap($this->privateKey, 64, "\n", true)."\n-----END RSA PRIVATE KEY-----";
        openssl_sign($query, $sign, $privateKey, OPENSSL_ALGO_SHA256);
        //base64编码
        $sign = base64_encode($sign);
        return $sign;
    }

    /**
     * Author:LazyBench
     * 私钥解密
     * @param $sign
     * @param $data
     * @return bool
     */
    public function rsaVerify($sign, $data): bool
    {
        $query = $this->buildQueryString($data);
        $query = urldecode($query);
        $publicKey = "-----BEGIN PUBLIC KEY-----\n".wordwrap($this->publicKey, 64, "\n", true)."\n-----END PUBLIC KEY-----";
        $verify = openssl_verify($query, base64_decode($sign), $publicKey, OPENSSL_ALGO_SHA256);
        return $verify === 1;
    }
}