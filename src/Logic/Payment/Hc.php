<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2020/5/6
 * Time: 15:10
 */

namespace Application\Core\Components\Payment\Tax;

use Application\Cli\Components\Logic\QueueLogic;
use Application\Core\Components\Constants\Code;
use Application\Core\Components\Traits\LogicTrait;
use LazyBench\Tax\Hc\Entity\Employee\EmployeeInfo;
use LazyBench\Tax\Hc\Entity\EntityInterface;
use Application\Core\Components\Payment\PaymentInterface;
use Application\Core\Components\Traits\PaymentTrait;
use Phalcon\Di;

class Hc implements PaymentInterface
{
    use PaymentTrait, LogicTrait;
    /**
     * Author:LazyBench
     *
     * @var EntityInterface
     */
    protected $tax;


    protected $channelId;

    /**
     * Author:LazyBench
     *
     * @param $channelId
     */
    public function setChannelId($channelId)
    {
        $this->channelId = $channelId;
    }

    /**
     * Author:LazyBench
     *
     * @return string
     */
    public function getChannelId(): string
    {
        return $this->channelId;
    }

    /**
     * Author:LazyBench
     *
     * @return string
     */
    public function getClassName(): string
    {
        return __CLASS__;
    }


    public function __construct()
    {
        $this->tax = new \LazyBench\Tax\Hc\Hc(Di::getDefault()->get('config')->hccx->toArray());
    }

    /**
     * Author:LazyBench
     * 余额查询
     * @param $channelId
     * @return mixed
     */
    public function balance($channelId)
    {
        $data[0] = [
            'serviceCompanyId' => 0,
            'totalBalance' => 0,
            'bankBalance' => 0,
            'aliPayBalance' => 0,
            'wxBalance' => 0,
        ];
        return $data;
    }

    /**
     * Author:LazyBench
     *
     * @param \UserCard $userCard
     * @return mixed|void
     * @throws \Exception
     */
    public function signApply(\UserCard $userCard)
    {
        $param = [
            'employeeCode' => $userCard->id,
            'employeeName' => $userCard->realName,
            'status' => true,
            'mobile' => $userCard->mobile,
            'idCard' => $userCard->idCard,
        ];
        try {
            $response = $this->tax->request(new EmployeeInfo($param), EmployeeInfo::path);
            $res = $this->tax->handle($response);
        } catch (\Exception $e) {
            return false;
        }
        return $res ? QueueLogic::SUCCESS : QueueLogic::RELEASE;
    }

    /**
     * Author:LazyBench
     * 签约查询
     * @param \UserCard $userCard
     * @return mixed|void
     */
    public function signQuery(\UserCard $userCard)
    {
        $userCard->signHc = 1;
        $userCard->update();
        return $userCard->signHc ? true : false;
    }


    /**
     * 申请订单
     */
    public function orderApply(\Order $order)
    {
    }

    /*
     * 订单完成
     */
    public function orderPaid(\Order $order, $data)
    {
    }

    /*
     * 订单失败
     */
    public function orderFail(\Order $order, $data)
    {
    }

    /*
     * 取消订单
     */
    public function orderCancel(\Order $order, $data)
    {
    }

    /**
     * 退汇处理
     * @return mixed
     */
    public function orderBack(\Order $order, $data)
    {
    }

    /**
     * 查询订单状态
     * @return mixed
     */
    public function orderState(\Order $order)
    {
    }

    /**
     * Author:LazyBench
     * 拉取电子回单
     * @param \Order $order
     * @return mixed
     */
    public function getBillBack(\Order $order)
    {
        return true;
    }

    /**
     * Author:LazyBench
     * 拉取日流水
     * @param \Order $order
     * @return mixed
     */
    public function getBillLog(\Order $order)
    {
        return true;
    }


    /***
     * Author:LazyBench
     *
     * @param \UserCard $userCard
     * @return bool
     */
    public function isSign(\UserCard $userCard = null): bool
    {
        return isset($userCard->signHc) && $userCard->signHc === Code::SIGN_OK;
    }

    /**
     * Author:LazyBench
     * 是否需要签约
     * @return bool
     */
    public function isNeedSign(): bool
    {
        return true;
    }

    /**
     * Author:LazyBench
     * UserCard 签约字段
     * @return string
     */
    public function getSignColumn(): string
    {
        return 'signHc';
    }

    /**
     * Author:LazyBench
     *
     * @param \BankCard $bankCard
     * @return bool
     */
    public function isBindCard(\BankCard $bankCard = null): bool
    {
        return true;
    }

    /**
     * Author:LazyBench
     *
     * @param string $bizUserId
     * @return bool
     */
    public function isAllInPay(string $bizUserId): bool
    {
        return true;
    }
}