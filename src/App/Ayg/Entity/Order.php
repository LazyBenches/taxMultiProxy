<?php

namespace LazyBench\TaxMultiProxy\App\Ayg\Entity;

use LazyBench\TaxMultiProxy\App\Ayg\Traits\RequestTrait;

class Order
{
    use RequestTrait;
    public $templateId;
    public $templateGroupId;
    public $extraSystemId;
    public $notifyUrl;
    public $list = [];
    public $orderId;
    public $extraOrderId;
    public $identity;
    public $identityType;
    public $name;
    public $personalMobile;
    public $serviceCompanyId;

    protected function init()
    {
    }

    /**
     * 7.3 批量提交请求签约合同-异步
     *
     * @return JSON
     */
    public function batchSubmit()
    {
        $path = '/econtract/extr/order/batchsubmit';
        $data = [
            'templateId' => $this->templateId,
            'notifyUrl' => $this->notifyUrl,
            'list' => $this->list,
        ];
        return $this->client->post($path, $data);

    }

    /**
     * 批量添加签约信息
     *
     * @param string $extraOrderId 外部订单号
     * @param string $identity 证件号
     * @param string $name 姓名
     * @param string $identityType 证件类型
     * @param string $personalMobile 手机
     * @param string $extraUserId 外部用户标识
     * @return void
     */
    public function appendListItem($extraOrderId, $identity, $name, $identityType, $personalMobile, $extraUserId = null)
    {
        $item = [
            'extrOrderId' => $extraOrderId,
            'identity' => $identity,
            'name' => $name,
            'identityType' => $identityType,
            'personalMobile' => $personalMobile,
        ];
        if ($extraUserId) {
            $item['extrUserId'] = $extraUserId;
        }
        $this->list[] = $item;
    }


    /**
     * 7.4 查询合同订单信息
     *
     * @return JSON
     */
    public function queryOrder()
    {
        $path = '/econtract/extr/order/qry';
        $data = [
            'orderId' => $this->orderId,
            'extrOrderId' => $this->extraOrderId,
        ];
        return $this->client->post($path, $data);
    }


    /**
     * 7.5 实时查询签约状态信息
     *
     * @return JSON
     */
    public function realtimeQueryOrder()
    {
        $path = '/econtract/extr/order/rtqry';
        $data = [
            'orderId' => $this->orderId,
            'extrOrderId' => $this->extraOrderId,
        ];
        return $this->client->post($path, $data);
    }

    /**
     * 7.6 单笔提交请求签约合同
     *
     * @return JSON
     */
    public function submit()
    {
        $path = '/econtract/extr/order/submit';
        $data = [
            'templateId' => $this->templateId,
            'notifyUrl' => $this->notifyUrl,
            'extrOrderId' => $this->extraOrderId,
            'identity' => $this->identity,
            'name' => $this->name,
            'identityType' => $this->identityType,
            'personalMobile' => $this->personalMobile,
        ];
        return $this->client->post($path, $data);
    }


    /**
     * Author:LazyBench
     * 7.7 多服务商电子签约API
     * @return array
     */
    public function batchTemplateGroupSubmit()
    {
        $path = '/econtract/extr/order/templategroup-submit';
        $data = [
            'templateGroupId' => $this->templateGroupId,
            'notifyUrl' => $this->notifyUrl,
            'list' => $this->list,
        ];
        return $this->client->post($path, $data);
    }


    /**
     * 7.8 取消签约流程
     *
     * @return JSON
     */
    public function cancelSign()
    {
        $path = '/econtract/extr/order/cancelsign';
        $data = [
            'extrSystemId' => $this->extraSystemId,
            'orderId' => $this->orderId,
            'extrOrderId' => $this->extraOrderId,
        ];
        return $this->client->post($path, $data);
    }


    /**
     * Author:LazyBench
     * 7.11 外部电子签约订单同步接口【同步】
     * @param $data
     * @return JSON
     */
    public function outerSync($data)
    {
        $path = '/econtract/extr/order/outer-sync';
        return $this->client->post($path, $data);
    }

    /**
     * Author:LazyBench
     * 查询合同组订单信息
     * @return array
     */
    public function queryOrderGroup()
    {
        $path = '/econtract/extr/order/templategroup-qry';
        $data = [
            'templateGroupId' => $this->templateGroupId,
            'extrOrderId' => $this->extraOrderId,
            'name' => $this->name,
            'identity' => $this->identity,
        ];
        return $this->client->post($path, $data);
    }

    /**
     * Author:LazyBench
     *
     * @return array
     */
    public function queryUserSign()
    {
        $path = '/econtract/extr/usersign/qry';
        $data = [
            'serviceCompanyId' => $this->serviceCompanyId,
            'templateId' => $this->templateId,
            'idcard' => $this->identity,
        ];
        return $this->client->post($path, $data);
    }

}