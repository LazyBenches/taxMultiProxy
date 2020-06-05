<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2019/8/14
 * Time: 16:08
 */

namespace LazyBench\TaxMultiProxy\App\Ayg\Entity;

use LazyBench\TaxMultiProxy\App\Ayg\Traits\RequestTrait;

class BankCard
{
    use RequestTrait;
    public $bankName;
    public $idCard;
    public $mobile;
    public $realName;
    public $accountNo;
    public $accountType = 'BANK_CARDNO';
    public $validType = 3;
    public $notifyUrl;
    public $responseId;

    protected function init()
    {
    }

    /**
     * Author:LazyBench
     * 银行认证
     * @return array
     */
    public function authorize()
    {
        $path = '/prepare/asyn/certification';
        $data = [
            'bankName' => $this->bankName,
            'idcard' => $this->idCard,
            'mobile' => $this->mobile,
            'name' => $this->realName,
            'payAccount' => $this->accountNo,
            'payAccountType' => $this->accountType,
            'validType' => $this->validType,
            'notifyUrl' => $this->notifyUrl,
        ];
        return [
            'path' => $path,
            'data' => $data,
        ];
//        return $this->client->post($path, $data);
    }

    /**
     * Author:LazyBench
     * 银行认证
     * @return array
     */
    public function authorizeSync()
    {
        $path = '/prepare/sync/certification';
        $data = [
            'bankName' => $this->bankName,
            'idcard' => $this->idCard,
            'mobile' => $this->mobile,
            'name' => $this->realName,
            'payAccount' => $this->accountNo,
            'payAccountType' => $this->accountType,
            'validType' => $this->validType,
        ];
        return [
            'path' => $path,
            'data' => $data,
        ];
//        return $this->client->post($path, $data);
    }

    /**
     * Author:LazyBench
     * 认证查询
     * @return array
     */
    public function query()
    {
        $path = '/prepare/certification/result';
        $data = [
            'cerRequestId' => $this->responseId,
        ];
        return [
            'path' => $path,
            'data' => $data,
        ];
//        return $this->client->post($path, $data);
    }
}