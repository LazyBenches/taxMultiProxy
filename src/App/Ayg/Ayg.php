<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2020/5/7
 * Time: 17:04
 */

namespace LazyBench\TaxMultiProxy\App\Ayg;

use LazyBench\TaxMultiProxy\App\Ayg\Http\Client;

class Ayg
{
    /**
     * Author:LazyBench
     *
     * @var Client
     */
    protected $client;

    public function __construct($config)
    {
        $this->client = new Client($config['appId'], $config['privateKey'], $config['host'], $config['publicKey']);
    }

    public function getClient()
    {
        return $this->client;
    }
}