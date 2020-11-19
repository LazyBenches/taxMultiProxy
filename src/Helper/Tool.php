<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2020/11/19
 * Time: 18:29
 */

namespace LazyBench\TaxMultiProxy\Helper;


class Tool
{
    /**
     * Author:LazyBench
     *
     * @param int $length
     * @return string
     * @throws \Exception
     */
    public static function getRandomString($length = 4): string
    {
        $str = '';
        $chars = 'qQwWeErRtTyYuUiIoOpPaAsSfFgGhHjJkKlLzZxXcCvVbBnNmM0123456789';
        $len = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, $len)];
        }
        return $str;
    }

    /**
     * Author:LazyBench
     *
     * @param $method
     * @param $headers
     * @param $url
     * @param array $params
     * @return mixed
     * @throws \Exception
     */
    public static function sendRequest($method, $headers, $url, $params = [])
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($curl);
        $errorNo = curl_errno($curl);
        $errMsg = curl_error($curl);
        curl_close($curl);
        if ($errorNo) {
            throw new \Exception($errMsg, $errorNo);
        }
        if (!$arr = json_decode($res, true)) {
            throw new \Exception(json_last_error_msg(), json_last_error());
        }
        return $arr;
    }
}