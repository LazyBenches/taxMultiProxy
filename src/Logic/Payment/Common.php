<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2020/2/27
 * Time: 16:38
 */

namespace Application\Core\Components\Payment\Tax;


use Application\Cli\Components\Logic\QueueLogic;
use Application\Core\Components\Constants\OrderCode;
use Application\Core\Components\Exception\TransactionException;
use Application\Core\Components\Logic\MiddleWareQueueLogic;
use Application\Core\Components\Payment\PaymentInterface;
use Application\Core\Components\Traits\LogicTrait;
use Application\Core\Components\Traits\PaymentTrait;
use Phalcon\Mvc\Model\Message;

class Common implements PaymentInterface
{
    use PaymentTrait, LogicTrait;



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
     * 申请打款
     * @param \Order $order
     * @return bool
     * @throws \Exception
     */
    public function orderApply(\Order $order)
    {
        if ($order->state === OrderCode::PAYING_STATUS) {
            return OrderCode::PAYING_STATUS;
        }
        if ($order->state === OrderCode::PAID_STATUS) {
            $order->appendMessage(new Message('订单已支付'));
            return OrderCode::PAYING_STATUS;
        }
        // 线下支付
        $order->offline = 1;
        $order->orderNo = md5($order->id);
        $order->state = OrderCode::PAYING_STATUS;
        return $order->transaction(function (\Order $order) {
            $orderForUpdate = \Order::findFirst([
                'conditions' => 'id=:id:',
                'bind' => [
                    'id' => $order->id,
                ],
                //                'for_update' => true,
            ]);
            if ($orderForUpdate->state !== OrderCode::PENDING_STATUS) {
                throw new TransactionException('订单状态错误');
            }
            // 回写订单号
            if (!$order->save()) {
                throw new TransactionException($order->getFirstError());
            }
            // 数据统计
            $middle = new MiddleWareQueueLogic();
            if (!$middle->payingIncrease($order)) {
                throw new TransactionException('异常');
            }
            return true;
        });;
    }

    /**
     * 打款完成
     * @param \Order $order
     * @param array $data
     * @return bool|mixed|void
     * @throws \Exception
     */
    public function orderPaid(\Order $order, $data = [])
    {
        $order->state = OrderCode::PAID_STATUS;
        $order->paidAt = date('Y-m-d H:i:s');
        $trans = $order->transaction(function (\Order $order) {
            if (!$order->update()) {
                return false;
            }
            $middle = new MiddleWareQueueLogic();
            if (!$middle->paidIncrease($order)) {
                throw new TransactionException('处理统计失败');
            }
            return true;
        });
        if (!$trans) {
            return '-1';
        }
        return $trans;
    }

    /**
     * 打款失败
     * @param \Order $order
     * @param array $data
     * @return bool|void
     */
    public function orderFail(\Order $order, $data = [])
    {
        $order->state = OrderCode::PAID_FAIL_STATUS;
        if (!$order->update()) {
            return '-1';
        }
        return true;
    }

    /**
     * 订单退汇
     * @param \Order $order
     * @param array $data
     * @return bool|mixed|void
     * @throws \Exception
     */
    public function orderBack(\Order $order, $data = [])
    {
        $order->state = OrderCode::PAID_BACK_STATUS;
        $order->errorMsg = 'aaa';
        if (!$order->update()) {
            return false;
        }
        return true;
    }

    /**
     * 订单取消
     * @param \Order $order
     * @param array $data
     * @return bool|void
     */
    public function orderCancel(\Order $order, $data = [])
    {
        return true;
    }

    /**
     * 订单查询
     * @param \Order $order
     * @param array $data
     * @return bool|mixed|void
     * @throws \Exception
     */
    public function orderState(\Order $order, $data = [])
    {
        if ($order->state === OrderCode::PAID_STATUS) {
            $order->appendMessage(new Message('订单成功已处理'));
            return QueueLogic::SUCCESS;
        }
        if ($order->state === OrderCode::PAID_FAIL_STATUS) {
            $order->appendMessage(new Message('订单失败已处理'));
            return QueueLogic::DELETE;
        }
        $state = $this->orderPaid($order, []);
        if ($state === '-1') {
            return QueueLogic::RELEASE;
        }
        if ($state === false) {
            return QueueLogic::DELETE;
        }
        if ($state === true) {
            return QueueLogic::SUCCESS;
        }
        return QueueLogic::RELEASE;
    }

    /**
     * 电子回单
     * @param \Order $order
     * @return bool|mixed|void
     */
    public function getBillBack(\Order $order)
    {
        return [];
    }

    /**
     * Author:LazyBench
     * 拉取日流水
     * @param \Order $order
     * @return mixed
     */
    public function getBillLog(\Order $order)
    {
        return [];
    }


    /**
     * Author:LazyBench
     * 签约申请
     * @param \UserCard $userCard
     * @return mixed
     */
    public function signApply(\UserCard $userCard)
    {
        return true;
    }

    /**
     * Author:LazyBench
     * 签约查询
     * @param \UserCard $userCard
     * @return bool|mixed
     */
    public function signQuery(\UserCard $userCard)
    {
        return true;
    }

    /**
     * Author:LazyBench
     * 是否签约
     * @param \UserCard $userCard
     * @return bool
     */
    public function isSign(\UserCard $userCard = null): bool
    {
        return true;
    }

    /**
     * Author:LazyBench
     * 是否需要签约
     * @return bool
     */
    public function isNeedSign(): bool
    {
        return false;
    }

    /**
     * Author:LazyBench
     * UserCard 签约字段
     * @return string
     */
    public function getSignColumn(): string
    {
        return '';
    }

    /**
     * Author:LazyBench
     * 是否已绑卡
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