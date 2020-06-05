<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2019/8/21
 * Time: 10:13
 */

namespace LazyBench\TaxMultiProxy\Ayg\Entity;


use LazyBench\TaxMultiProxy\Ayg\Traits\RequestTrait;

class Account
{
    use RequestTrait;
    public $beginDay;
    public $endDay;
    public $reqNo;
    public $outOrderNo;
    public $notifyUrl;
    public $attach;

    /**
     * Author:LazyBench
     *
     * @return array
     */
    public function billLog()
    {
        $path = '/deliver/dlvopenapi/api/app/pay/query-by-day';
        $data = [
            'beginDay' => $this->beginDay,
            'endDay' => $this->endDay,
        ];

        return [
            'path' => $path,
            'data' => $data,
            'method' => 'ayg.salary.queryByDay',
        ];
//        return $this->client->postPay($path, $data, 'ayg.salary.queryByDay');
    }

    /**
     * Author:LazyBench
     *
     * @return array
     */
    public function balance()
    {
        $path = '/deliver/dlvopenapi/api/app/account/query-balance-list';

        return [
            'path' => $path,
            'data' => null,
            'method' => 'ayg.account.queryBalance',
        ];
//        return $this->client->postPay($path, null, 'ayg.account.queryBalance');
    }

    /**
     * Author:LazyBench
     *
     * @return array
     */
    public function billImage()
    {
        $path = '/deliver/dlvopenapi/api/app/pay/request-receipt';
        $data = [
            'reqNo' => $this->reqNo,
            'outOrderNo' => $this->outOrderNo,
            'notifyUrl' => $this->notifyUrl,
            'attach,' => $this->attach,
        ];
        return [
            'path' => $path,
            'data' => $data,
            'method' => 'ayg.salary.requestReceipt',
        ];
//        return $this->client->postPay($path, $data, 'ayg.salary.requestReceipt');
    }
}