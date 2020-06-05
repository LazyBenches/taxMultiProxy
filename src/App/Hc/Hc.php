<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2020/5/7
 * Time: 18:46
 */

namespace LazyBench\TaxMultiProxy\App\Hc;

use LazyBench\TaxMultiProxy\App\Hc\Entity\EntityInterface;
use LazyBench\TaxMultiProxy\App\Hc\Util\Config;
use LazyBench\TaxMultiProxy\App\Hc\Util\Curl;
use LazyBench\TaxMultiProxy\App\Hc\Util\Sign;

class Hc
{
    protected $param = [];

    public function __construct(array $config)
    {
        Config::init($config);
        Config::setTimestamp();
    }

    protected function setParam(EntityInterface $entity)
    {
        $json = json_encode($entity->toArray());
        $timestamp = Config::getTimestamp();
        $appId = Config::getAppId();
        $appSecret = Config::getAppSecret();
        $this->param = [
            'params' => $json,
            'appId' => $appId,
            'digest' => Sign::encode("{$appId}+{$json}+{$timestamp}+{$appSecret}"),
            'timestamp' => $timestamp,
        ];
    }

    /**
     * Author:LazyBench
     *
     * @param EntityInterface $entity
     * @param $path
     * @return mixed
     */
    public function request(EntityInterface $entity, $path)
    {

        $this->setParam($entity);
        return (new Curl())->setHeader(['Content-Type:application/json'])->setParams($this->param)
                           ->post(Config::getHost().$path);
    }

    public function handle($response)
    {
        if ($response['httpCode'] !== '200') {
            throw new \Exception($response['error'] ?: '未返回错误信息');
        }
        if (!$content = json_decode($response['content'], true)) {
            throw new \Exception(json_last_error_msg());
        }
        //        if ($content['status'] === 'error') {
        //            throw new \Exception($content['message'], $content['errorCode'] ?? 0);
        //        }
        //        if ($content['status'] !== 'OK') {
        //            throw new \Exception('未知错误', $content['errorCode'] ?? 0);
        //        }
        //        if (!$content['signedValue']) {
        //            throw new \Exception('获取返回数据为空');
        //        }
        //        if (!$content['sign']) {
        //            throw new \Exception('获取返回签名数据为空');
        //        }
        ////        if (!AllInPayUtil::validSign($content['signedValue'], $content['sign'])) {
        ////            throw new \Exception('验签失败');
        ////        }
        //        return json_decode($content['signedValue'], true);
    }
}