<?php

namespace LazyBench\TaxMultiProxy\App\WrJs\Http;

use LazyBench\TaxMultiProxy\Helper\Tool;

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
     * @return array
     * @throws \Exception
     */
    public function request($url, $params = [], $method = 'GET'): array
    {
        $header = array(
            'Version: YJ 1.0',
        );
        $params['appId'] = $this->appId;
        $params['appKey'] = $this->appKey;
        $url = "{$this->host}{$url}";
        if ($method === 'GET') {
            $url .= strpos('?', $url) ? '&' : '?'.http_build_query($params);
            return Tool::sendRequest('GET', $header, $url, $params);
        }
        return Tool::sendRequest('POST', $header, $url, $params);
    }

}