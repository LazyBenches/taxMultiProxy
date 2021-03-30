<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2021/3/30
 * Time: 10:46
 */

namespace LazyBench\TaxMultiProxy\App\Yqd;


use LazyBench\TaxMultiProxy\App\Yqd\Http\Request;

class Yqd
{
    protected $host;
    protected $no;
    protected $key;

    public function __construct(array $config)
    {
        $this->config($config);
    }

    /**
     * Author:LazyBench
     *
     * @param array $config
     */
    public function config(array $config): void
    {
        $host = $config['host'] ?? '';
        $host && $this->host = $host; // 主机地址
        $no = $config['no'] ?? '';
        $no && $this->no = $no; // 商户
        $key = $config['key'] ?? '';
        $key && $this->key = $key; // 商户数据加密Key
    }

    /**
     * Author:LazyBench
     *
     * @return mixed
     */
    public function getNo(): string
    {
        return $this->no;
    }

    /**
     * Author:LazyBench
     * 1.参数去掉 key 为空的数据，然后按照字符集排序，使用 URL 键值对的格
     * 式（即 key1=value1&key2=value2…）拼接成字符串 str；
     * 2.str 最后拼接上 key 得到 strSignTemp 字符串，并对 strSignTemp 进行
     * MD5 运算，再将得到的字符串所有字符转换为大写，得到 sign 值
     * @param array $param
     * @return array
     */
    public function appendSign(array $param): array
    {
        $filter = array_filter($param);//过滤
        ksort($filter);//排序
        //        $str = urldecode(http_build_query($filter))."&key={$this->key}";
        $implode = [];
        array_walk_recursive($filter, static function ($value, $key) use (&$implode) {
            $implode[] = "{$key}={$value}";
        });
        $str = implode('&', $implode)."&key={$this->key}";
        $param['sign'] = strtoupper(md5($str));
        return $param;
    }

    /**
     * Author:LazyBench
     *
     * @param array $param
     * @return array
     * @throws \Exception
     */
    public function request(array $param): array
    {
        $query = $param['query'] ?? [];
        $data = $param['data'] ?? [];
        $isPost = $param['method'] === 'post';
        if ($isPost) {
            $data['no'] = $this->no;
            $data = $this->appendSign($data);
        } else {
            $query['no'] = $this->no;
            $query = $this->appendSign($query);
        }
        $url = "{$this->host}{$param['path']}".($query ? '?'.http_build_query($query) : '');
        $response = $isPost ? Request::post($url, $data) : Request::get($url);
        return Request::parseResponse($response);
    }
}