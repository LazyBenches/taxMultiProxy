<?php

namespace LazyBench\TaxMultiProxy\App\Ayg\Http;


class Client
{
    private $appId;
    private $privateKey;
    private $publicKey;
    private $baseUrl;
    private $headers = array('Content-Type: application/json');

    /**
     * Client constructor.
     * @param $appId
     * @param $privateKey
     * @param $baseUrl
     * @param $publicKey
     */
    public function __construct($appId, $privateKey, $baseUrl, $publicKey)
    {
        $this->appId = $appId;
        $this->privateKey = $privateKey;
        $this->baseUrl = $baseUrl;
        $this->publicKey = $publicKey;
    }

    /**
     * url拼接
     *
     * @param string $path
     * @return String
     */
    public function buildUrl($path)
    {
        return $this->baseUrl.ltrim($path, '/');
    }

    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    /**
     * GET
     *
     * @param string $path
     * @param array $params
     * @return void
     */
    public function get($path, $params)
    {
        if (is_array($params)) {
            $params = $this->buildQueryString($params);
        }
        $url = $this->buildUrl($path).'?'.$params;
        return $this->sendRequest('GET', $url);
    }

	
	    /**
     * 签名参数组装
     *
     * @param array $params 参数
     * @param boolean $isSubset 是否子集
     * @return void
     */
    public function buildQueryString($params, $isSubset = false)
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
            //子集按数组和键值对不同形式组装
            if ($isAssocArray) {
                return '['.implode(',', $data).']';
            } else {
                return '{'.http_build_query($data).'}';
            }
        } else {
            return http_build_query($data);
        }
    }
	
    /**
     * POST
     *
     * @param string $path
     * @param array $data
     * @return JSON
     */
    public function post($path, $data)
    {
        $params = [
            'appId' => $this->appId,
            'nonce' => $this->getRandom(32),
            'timestamp' => time(),
            'data' => $data,
        ];
        $params['sign'] = $this->rsaSign($params);
        $params = json_encode($params);
        $url = $this->buildUrl($path);
        return $this->sendRequest('POST', $url, $params);
    }

	public function getRandom($length = 4, $chars = 'abcdefghijkmnpqrstuvwxyz23456789'): string
    {
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
    /**
     * Author:LazyBench
     *
     * @param $path
     * @param $data
     * @param $method
     * @return array
     */
    public function postPay($path, $data, $method)
    {
        $timestamp = time();
        $params = [
            'appId' => $this->appId,
            'data' => $data,
            'method' => $method,
            'nonce' => get_random_string(32),
            'timestamp' => date('Y-m-d H:i:s', $timestamp),
            'signType' => 'RSA2',
            'version' => '1.0',
        ];
        $params['sign'] = $this->rsaSign($params);
        $params = json_encode($params);
        $url = $this->buildUrl($path);
        return $this->sendRequest('POST', $url, $params);
    }

    /**
     * POST File
     *
     * @param string $path
     * @param array $queryParams
     * @param array $formData
     * @return JSON
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
            $queryParams['nonce'] = get_random_string(32);
            $queryParams['timestamp'] = time();
            $signData = array_merge($signData, $queryParams);
            $queryParams['sign'] = $this->rsaSign($signData);
            $queryParams = http_build_query($queryParams);
        }

        $url = $this->buildUrl($path).'?'.$queryParams;
        return $this->sendRequest('POST', $url, $formData);
    }

    /**
     * Send
     *
     * @param string $method
     * @param string $url
     * @param array $params
     * @return array
     */
    public function sendRequest($method, $url, $params = [])
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->headers);

        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($curl);
        $errorNo = curl_errno($curl);
        if ($errorNo) {
            $errMsg = curl_error($curl);
            curl_close($curl);
            return compact('errorNo', 'errMsg');
        }
        curl_close($curl);
        return json_decode($res, true);
    }

    /**
     * Sign
     *
     * @param array $data
     * @return string
     */
    public function rsaSign($data)
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
    public function rsaVerify($sign, $data)
    {
        $query = $this->buildQueryString($data);
        $query = urldecode($query);
        $publicKey = "-----BEGIN PUBLIC KEY-----\n".wordwrap($this->publicKey, 64, "\n", true)."\n-----END PUBLIC KEY-----";
        $verify = openssl_verify($query, base64_decode($sign), $publicKey, OPENSSL_ALGO_SHA256);
        return $verify === 1;
    }
}