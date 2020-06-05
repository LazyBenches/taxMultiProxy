<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2020/5/7
 * Time: 16:02
 */

namespace LazyBench\TaxMultiProxy\Traits;


trait PaymentTrait
{
    use SubTrait;

    /**
     * Author:LazyBench
     *
     * @param $amount
     * @param $lastAmount
     * @param $lastInTotal
     * @param $rate
     * @return bool|string
     */
    public function getInTotal($amount, &$lastAmount, &$lastInTotal, $rate)
    {
        // 计算额度
        $lastAmount = bcadd($amount, $lastAmount);
        $total = $this->ceil(($lastAmount * bcadd(1, bcdiv($rate, 100, 8), 8)) - $lastInTotal, 0);
        $lastInTotal = bcadd($lastInTotal, $total);
        return $total;
    }

    /**
     * 获取当前费率
     * @param $amount
     * @param $rates
     * @return bool|string
     */
    public function getRate($amount, $rates)
    {
        foreach ($rates as $rate) {
            if ($amount >= $rate->startQuota && $amount < $rate->endQuota) {
                return $rate->rate;
            }
        }
        return false;
    }

    /**
     * Author:LazyBench
     *
     * @param $amount
     * @param $lastAmountTotal
     * @param $subChannelId
     * @return string
     */
    public function getOutTotalOld($amount, $lastAmountTotal, $subChannelId)
    {
        $rates = \ChannelRate::find([
            'conditions' => 'isActive = 1 and subChannel = :subChannel:',
            'bind' => [
                'subChannel' => $subChannelId,
            ],
            'order' => 'rate asc,sort desc',
        ]);
        $total = bcadd($amount, $lastAmountTotal);
        $quota = 0;
        $lastRate = 0;
        $nowRate = 0; // 当前梯度费率
        foreach ($rates as $rate) {
            $quota = bcadd($rate->quota, $quota);
            if ((!$rate->quota || $lastAmountTotal <= $quota) && $lastRate === 0) { // 上次梯度
                $lastRate = $rate->rate;
            }
            if ((!$rate->quota || $total <= $quota) && $nowRate === 0) { // 本次梯度
                $nowRate = $rate->rate;
                break;
            }
        }
        $lastOutTotal = $this->ceil($lastAmountTotal * (1 + $lastRate / 100));
        return $this->ceil($total * (1 + $nowRate / 100) - $lastOutTotal, 0);
    }

    /**
     * Author:LazyBench
     * @param $amountTotal (当月总个人实收)
     * @param $lastOutTotal (当月总企业已纳应付)
     * @param $channelRate
     * @return int
     * @throws \Exception
     */
    public function getOutTotal($amountTotal, $lastOutTotal, $channelRate)
    {
        return (int)round($amountTotal * (1 + $channelRate / 100) - $lastOutTotal, 0);
    }

    /**
     * Author:LazyBench
     *
     * @param $idCard
     * @param $month
     * @param $subChannel
     * @return array
     */
    public function getUserMonth($idCard, $month, $subChannel)
    {
        $userMonth = \UserMonth::findFirst([
            'columns' => 'amount+monthQuota as amount',
            'conditions' => 'idCard=:idCard: and month=:month: and subChannel=:subChannel:',
            'bind' => [
                'idCard' => $idCard,
                'month' => $month,
                'subChannel' => $subChannel,
            ],
        ]);
        return [
            'amount' => $userMonth->amount ?? 0,
        ];
    }

    /**
     * Author:LazyBench
     * 匹配梯度费率
     * @param $amountTotal
     * @param $channelId
     * @return array
     * @throws \Exception
     */
    public function getOutRate($amountTotal, $channelId): array
    {
        if ($amountTotal <= 0) {
            return [
                'id' => 0,
                'rate' => 0,
            ];
        }
        $rates = \ChannelRate::find([
            'columns' => 'id,quota,rate',
            'conditions' => 'isActive = 1 and subChannel = :subChannel:',
            'bind' => [
                'subChannel' => $channelId,
            ],
            'order' => 'rate asc,sort desc',
        ]);
        $startQuota = 0;
        $nowRate = $id = 0;
        foreach ($rates as $rate) {
            $lastQuota = $rate->quota + $startQuota;
            if (($startQuota < $amountTotal) && ($amountTotal <= $lastQuota)) {//找到对应额度子渠道
                $nowRate = $rate->rate;
                $id = $rate->id;
                break;
            }
            $startQuota += $rate->quota;
        }
        if (!$nowRate) {
            throw new \Exception("{$channelId}:{$amountTotal}没有找到对应梯度费率".json_encode($rates->toArray()));
        }
        return [
            'id' => $id,
            'rate' => (string)$nowRate,
        ];
    }


    /**
     * Author:LazyBench
     *
     * @param \UserCard $userCard
     */
    public function signFmtState(\UserCard $userCard)
    {
        $signs = [
            $userCard->signWr,
            $userCard->signAyg,
        ];
        $count = count($signs);
        $total = array_count_values($signs);
        if (isset($total[1]) && $count == $total[1]) {
            $userCard->state = 1; // 全部成功
        } elseif (isset($total[2]) && $count == $total[2]) {
            $userCard->state = 2; // 全部失败
        } else {
            $userCard->state = 0; // 部分成功
        }
    }

    /**
     * Author:LazyBench
     *
     * @param $amount
     * @param $rate
     * @return string
     */
    public function getRealAmount($amount, $rate)
    {
        return (int)round($amount + bcmul($amount, bcdiv($rate, 100, 8), 8), 0);
    }

    /**
     * Author:LazyBench
     *
     * @param $realAmount
     * @param $rate
     * @return string
     */
    public function getAmount($realAmount, $rate)
    {
        return (int)round(bcdiv($realAmount, 1 + bcdiv($rate, 100, 8), 8), 0);
    }
}