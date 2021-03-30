<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2021/3/30
 * Time: 11:20
 */

namespace LazyBench\TaxMultiProxy\App\Yqd\Http;


class Request
{
    protected static $response;
    protected static $debug = false;

    /**
     * Author:LazyBench
     *
     * @param string $url
     * @param array $query
     * @param array $param
     * @param int $flags
     * @return array
     * @throws \Exception
     */
    public static function request(string $url, array $query = [], array $param = [], int $flags = JSON_UNESCAPED_UNICODE): array
    {
        $url = "$url?".http_build_query($query);
        return self::parseResponse($param ? self::post($url, $param, $flags) : self::get($url));
    }

    /**
     * Author:LazyBench
     *
     * @param string $responseString
     * @return array
     * @throws \Exception
     */
    public static function parseResponse(string $responseString): array
    {
        $response = json_decode($responseString, true);
        if (json_last_error()) {
            throw new \RuntimeException(json_last_error_msg());
        }
        $code = $response['code'] ?? 5000;
        if ($code !== 0) {
            throw new \RuntimeException($response['msg'] ?? Code::code[$code], $code);
        }
        return $response['data'];
    }

    /**
     * Author:LazyBench
     *
     * @param $buffer
     * @return mixed
     * @throws \Exception
     */
    public static function parseResponseBuffer($buffer)
    {
        if (empty($buffer) || '{' === $buffer[0]) {
            throw new \RuntimeException('Invalid media response content.');
        }
        return $buffer;
    }

    /**
     * Author:LazyBench
     *
     * @param string $url
     * @return bool|string
     * @throws \Exception
     */
    public static function get(string $url): string
    {
        $ch = self::initCurl($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        return self::curl($ch);
    }

    /**
     * Author:LazyBench
     *
     * @param string $url
     * @param array|string $param
     * @param int $flags
     * @return bool|string
     * @throws \Exception
     */
    public static function post(string $url, $param, $flags = JSON_UNESCAPED_UNICODE)
    {
        var_dump($url, json_encode($param, $flags));
        $ch = self::initCurl($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($param, $flags));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=UTF-8']);
        return self::curl($ch);
    }

    /**
     * Author:LazyBench
     *
     * @param string $url
     * @return false|resource
     * @throws \Exception
     */
    protected static function initCurl(string $url)
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('missing curl extend');
        }
        $ch = curl_init();
        if (stripos($url, 'https://') !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSLVERSION, 1);
        }
        return $ch;
    }

    /**
     * Author:LazyBench
     *
     * @param \CurlHandle|false|resource $ch
     * @return bool|string
     * @throws \Exception
     */
    protected static function curl($ch)
    {
        $output = curl_exec($ch);
        $status = curl_getinfo($ch);
        curl_close($ch);
        if (!$output) {
            throw new \RuntimeException('network error');
        }
        if ((int)$status['http_code'] !== 200) {
            throw new \RuntimeException("unexpected http code {$status['http_code']}");
        }
        return $output;
    }
}