<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2020/6/9
 * Time: 11:45
 */

namespace LazyBench\TaxMultiProxy\App\Settlement;


class Settlement
{
    /**
     * Author:LazyBench
     *
     * @var Rpc
     */
    protected $rpc;

    public function __construct(array $config)
    {
        $this->rpc = new Rpc($config);
    }

    /**
     * Author:LazyBench
     *
     * @param string $interface
     * @return $this
     */
    public function setInterface(string $interface): self
    {
        $this->rpc->setInterface($interface);
        return $this;
    }

    /**
     * Author:LazyBench
     *
     * @param $method
     * @param $data
     * @return array
     * @throws \Exception
     */
    public function handleResponse($method, $data)
    {
        $response = $this->rpc->handle($method, $data);
        if (!$response) {
            throw new \Exception('解析失败');
        }
        if (!isset($response['code'])) {
            throw new \Exception($response['msg'] ?? $response['message'], $response['code'] ?? '9999');
        }
        return $response['data'] ?? [];
    }

    /**
     * Author:LazyBench
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function apply(array $data)
    {
        return $this->handleResponse('apply', $data);
    }

    /**
     * Author:LazyBench
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function applyRecharge(array $data)
    {
        return $this->handleResponse('applyRecharge', $data);
    }

    /**
     * Author:LazyBench
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function queryRecharge(array $data)
    {
        return $this->handleResponse('queryRecharge', $data);
    }

    /**
     * Author:LazyBench
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function queryBalance(array $data)
    {
        return $this->handleResponse('queryBalance', $data);
    }

    /**
     * Author:LazyBench
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function state(array $data)
    {
        return $this->handleResponse('state', $data);
    }

    /**
     * Author:LazyBench
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function createServiceId(array $data)
    {
        return $this->setInterface(\App\Rpc\Lib\CommonInterface::class)->handleResponse('createServiceId', $data);
    }

}