<?php
/**
 * Author：Lzxyz
 * Date：2019/8/14 10:22
 * Desc：PaymentInterface.php
 */

namespace LazyBench\TaxMultiProxy\Logic;


interface PaymentInterface
{

    public function setChannelId($channelId);

    public function getChannelId(): string;

    /**
     * Author:LazyBench
     *
     * @return string
     */
    public function getClassName(): string;

    /**
     * Author:LazyBench
     * 查询商户余额
     * @param $channelId
     * @return mixed
     */
    public function balance($channelId);

    /**
     * 申请订单
     */
    public function orderApply(\Order $order);


    /**
     * 查询订单状态
     * @return mixed
     */
    public function orderState(\Order $order);

    /**
     * Author:LazyBench
     * 拉取电子回单
     * @param \Order $order
     * @return mixed
     */
    public function getBillBack(\Order $order);

    /**
     * Author:LazyBench
     * 拉取日流水
     * @param \Order $order
     * @return mixed
     */
    public function getBillLog(\Order $order);

    /**
     * Author:LazyBench
     * 成本计算
     * @param $amount
     * @param $lastAmountTotal
     * @param $subChannelId
     * @return mixed
     */
    public function getOutTotalOld($amount, $lastAmountTotal, $subChannelId);


    /**
     * Author:LazyBench
     * 获取子渠道 月累计数据
     * @param $idCard
     * @param $month
     * @param $subChannel
     * @return array
     */
    public function getUserMonth($idCard, $month, $subChannel);

    /**
     * Author:LazyBench
     * 成本计算
     * @param $amount (当前月分发总金额)
     * @param $oldOutTotal (累计已纳总额)
     * @param $channelRate (子渠道费率档)
     * @return mixed
     */
    public function getOutTotal($amount, $oldOutTotal, $channelRate);

    /**
     * Author:LazyBench
     * 成本计算
     * @param $amount (当前月分发总金额)
     * @param $lastAmount (累计已纳总额)
     * @param $lastInTotal (子渠道费率档)
     * @param $rate (累计已纳总额)
     * @return mixed
     */
    public function getInTotal($amount, &$lastAmount, &$lastInTotal, $rate);

    public function getRate($amount, $rates);

    /**
     * Author:LazyBench
     * 成本计算
     * @param $amount (当前月分发总金额)
     * @param $channelId (子渠道)
     * @return array
     */
    public function getOutRate($amount, $channelId): array;

    /**
     * Author:LazyBench
     * 获取充值金额
     * @param $amount
     * @param $rate
     * @return mixed
     */
    public function getRealAmount($amount, $rate);

    /**
     * Author:LazyBench
     * 获取充值金额
     * @param $realAmount
     * @param $rate
     * @return mixed
     */
    public function getAmount($realAmount, $rate);

    /**
     * Author:LazyBench
     * 签约申请
     * @param \UserCard $userCard
     * @return mixed
     */
    public function signApply(\UserCard $userCard);

    /**
     * Author:LazyBench
     * 签约查询
     * @param \UserCard $userCard
     * @return mixed
     */
    public function signQuery(\UserCard $userCard);

    /***
     * Author:LazyBench
     *
     * @param \UserCard $userCard
     * @return bool
     */
    public function isSign(\UserCard $userCard = null): bool;

    /**
     * Author:LazyBench
     * 是否需要签约
     * @return bool
     */
    public function isNeedSign(): bool;

    /**
     * Author:LazyBench
     * UserCard 签约字段
     * @return string
     */
    public function getSignColumn(): string;

    /**
     * Author:LazyBench
     *
     * @param \BankCard $bankCard
     * @return bool
     */
    public function isBindCard(\BankCard $bankCard = null): bool;

    /**
     * Author:LazyBench
     *
     * @param string $bizUserId
     * @return bool
     */
    public function isAllInPay(string $bizUserId): bool;
}