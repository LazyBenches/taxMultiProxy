<?php

namespace LazyBench\Tax\Yzh\Http;

class Client
{

    private $host;
    private $dealerId;
    private $appKey;
    private $des3Key;
    private $timeout = 30;

    public function __construct($host, $dealerId, $appKey, $des3Key)
    {
        $this->host = $host;
        $this->dealerId = $dealerId;
        $this->appKey = $appKey;
        $this->des3Key = $des3Key;
    }

    public function request($requestId, $url, $params = [], $method = 'GET')
    {
        $header = array(
            "dealer-id: {$this->dealerId}",
            "request-id: {$requestId}",
        );
        // 处理URL
        $url = "{$this->host}{$url}";
        if ($method == 'GET') {
            return $this->get($url, $params, $header);
        } else {
            return $this->post($url, $params, $header);
        }
    }

    private function get($url, $params, $header)
    {
        if ($params) {
            $query = http_build_query($params);
            $url .= strpos('?', $url) ? "&" : "?" . $query;
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($curl);
        curl_close($curl);
        return $content;
    }

    private function post($url, $params, $header)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($curl);
        curl_close($curl);
        return $content;
    }
}