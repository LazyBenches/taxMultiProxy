<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2021/3/30
 * Time: 11:16
 */

namespace LazyBench\TaxMultiProxy\App\Yqd\Entity;

class User
{
    /**
     * Author:LazyBench
     * 新增用户
     * @param array $param
     * @return array
     */
    public function create(array $param): array
    {
        return [
            'method' => 'post',
            'path' => '/api/user',
            'query' => [],
            'data' => [
                'realName' => $param['realName'],
                'mobile' => $param['mobile'],
                'idNumber' => $param['idNumber'],
            ],
        ];
    }
}