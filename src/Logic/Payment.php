<?php
/**
 * Author：Lzxyz
 * Date：2019/8/14 10:21
 * Desc：Payment.php
 * 基础支付处理（该类禁止实例化，禁止调用方法）
 */

namespace  LazyBench\TaxMultiProxy\Logic;


class Payment
{

    /**
     * Author:LazyBench
     * 初始化支付渠道
     * @param \Order $order
     * @return PaymentInterface
     * @throws \Exception
     */
    public static function init(\Order $order): PaymentInterface
    {
        $aliasConfig = Di::getDefault()->get('config')->paymentAlias;
        $class = $aliasConfig[$order->channel] ?? $aliasConfig[0];
        /**
         * @var $payment PaymentInterface
         */
        if(!class_exists($class)){
            throw new \Exception('渠道初始化失败');
        }
        $payment = new $class();
        $payment->setChannelId($order->channel);
        return $payment;
    }

}