<?php

namespace LazyBench\TaxMultiProxy\App\WrJs\Http;

class Client
{

    private $host;
    private $appId;
    private $appKey;
    private $timeout = 30;

    public function __construct($host, $appId, $appKey)
    {
        $this->host = $host;
        $this->appId = $appId;
        $this->appKey = $appKey;
    }

    /**
     * Author:LazyBench
     *
     * @param $url
     * @param array $params
     * @param string $method
     * @return string
     * @throws \Exception
     */
    public function request($url, $params = [], $method = 'GET'): string
    {
        $header = array(
            'Version: YJ 1.0',
        );
        $params['appId'] = $this->appId;
        $params['appKey'] = $this->appKey;
        $url = "{$this->host}{$url}";
        if ($method === 'GET') {
            return $this->get($url, $params, $header);
        }
        return $this->post($url, $params, $header);
    }

    /**
     * Author:LazyBench
     *
     * @param $url
     * @param $params
     * @param $header
     * @return string
     * @throws \Exception
     */
    private function get($url, $params, $header): string
    {
        if ($params) {
            $query = http_build_query($params);
            $url .= strpos('?', $url) ? '&' : '?'.$query;
        }
        $curl = curl_init();
        $this->curlInit($url, $params, $header, $curl);
        return $this->curlQuery($curl);
    }

    /**
     * Author:LazyBench
     *
     * @param $url
     * @param $params
     * @param $header
     * @return string
     * @throws \Exception
     */
    private function post($url, $params, $header): string
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POST, true);
        $this->curlInit($url, $params, $header, $curl);
        return $this->curlQuery($curl);
    }

    /**
     * Author:LazyBench
     *
     * @param $url
     * @param $params
     * @param $header
     */
    private function curlInit($url, $params, $header, $curl): void
    {
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    }

    /**
     * Author:LazyBench
     *
     * @param $curl
     * @return bool|string
     * @throws \Exception
     */
    private function curlQuery($curl): string
    {
        $content = curl_exec($curl);
        $errorNo = curl_errno($curl);
        $error = curl_error($curl);
        curl_close($curl);
        if ($error) {
            throw new \Exception($error, $errorNo);
        }
        return $content;
    }
}