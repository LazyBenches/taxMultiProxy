<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2019/8/16
 * Time: 11:31
 */

namespace LazyBench\TaxMultiProxy\App\Ayg\Entity;


use LazyBench\TaxMultiProxy\App\Ayg\Traits\RequestTrait;

class OrderPay
{
    use RequestTrait;

    public $batchNo;
    public $outOrderNo;
    public $amount;
    public $memo;
    public $accountNo;
    public $accountName;
    public $serviceCompanyId;
    public $bank;
    public $depositBank;
    public $idCard;
    public $foreignNationality;
    public $phone;
    public $attach;
    public $extInfo;
    public $serviceType;
    public $responseReNo;

    /**
     * Author:LazyBench
     *
     * @return array
     */
    public function applyPay()
    {
        $path = '/deliver/dlvopenapi/api/app/pay/unified-order';
        $data = [
            'batchNo' => $this->batchNo,
            'outOrderNo' => $this->outOrderNo,
            'amount' => integerToDecimal($this->amount),
            'memo' => $this->memo,
            'accountNo' => $this->accountNo,
            'accountName' => $this->accountName,
            'serviceCompanyId' => $this->serviceCompanyId,
            'bank' => $this->bank,
            'depositBank' => $this->depositBank,
            'idCard' => $this->idCard,
            'foreignNationality' => $this->foreignNationality,
            'phone' => $this->phone,
            'attach' => $this->attach,
            'extInfo' => $this->extInfo,
            'serviceType' => $this->serviceType,
        ];
        return [
            'path' => $path,
            'data' => $data,
            'method' => 'ayg.salary.pay',
        ];
//        return $this->client->postPay($path, $data, 'ayg.salary.pay');
    }

    /**
     * Author:LazyBench
     *
     * @return array
     */
    public function queryPayment()
    {
        $path = '/deliver/dlvopenapi/api/app/pay/query';
        $data = [
            'reqNo' => $this->responseReNo,
            'outOrderNo' => $this->outOrderNo,
        ];
        return [
            'path' => $path,
            'data' => $data,
            'method' => 'ayg.salary.payQuery',
        ];
//        return $this->client->postPay($path, $data, 'ayg.salary.payQuery');
    }

    /**
     * Author:LazyBench
     * 取消
     * @return \Application\Core\Components\Ayg\Http\JSON
     */
    public function cancelPayment()
    {
        $path = '/deliver/dlvopenapi/api/app/pay/cancel-unified-order';
        $data = [
            'reqNo' => $this->responseReNo,
            'outOrderNo' => $this->outOrderNo,
        ];
        return $this->client->postPay($path, $data, 'ayg.salary.cancelUnifiedOrder');
    }
}