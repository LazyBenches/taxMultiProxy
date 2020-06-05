<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2020/3/9
 * Time: 10:27
 */

namespace Application\Core\Components\Payment\Tax;

use Application\Cli\Components\Logic\QueueLogic;
use Application\Core\Components\Constants\OrderCode;
use Application\Core\Components\Exception\TransactionException;
use Application\Core\Components\Logic\MiddleWareQueueLogic;
use Application\Core\Components\Payment\PaymentInterface;
use Application\Core\Components\Traits\LogicTrait;
use Application\Core\Components\Traits\PaymentTrait;
use Application\Core\Models\CheBaYun\Orders;
use Phalcon\Mvc\Model\Message;

class Cby implements PaymentInterface
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
     *
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
        $order->orderNo = md5("{$order->id}_{$order->retryTimes}");
        $requestOrder = \RequestOrder::findFirst([
            'columns' => 'orderNo,bankCard,realName,mobile,remark,bankName,bizUserId',
            'conditions' => 'id=:id:',
            'bind' => [
                'id' => $order->requestOrderId,
            ],
        ]);
        if ($order->state === OrderCode::PAYING_STATUS) {
            if (!$this->cybBusiness($order, $requestOrder)) {
                return false;
            }
            return OrderCode::PAYING_STATUS;
        }
        if ($order->state === OrderCode::PAID_STATUS) {
            if (!$this->cybBusiness($order, $requestOrder)) {
                return false;
            }
            $order->appendMessage(new Message('订单已支付'));
            return OrderCode::PAYING_STATUS;
        }
        $cbyOrder = \CbyOrder::findFirst([
            'conditions' => 'orderId = :orderId: and state in ({state:array}) and orderNo=:orderNo:',
            'bind' => [
                'orderId' => $order->id,
                'state' => [OrderCode::PENDING_STATUS, OrderCode::PAYING_STATUS, OrderCode::PAID_STATUS],
                'orderNo' => $order->orderNo,
            ],
        ]);
        if (!$cbyOrder) {
            $cbyOrder = new \CbyOrder();
            $cbyOrder->idCard = $order->idCard;
            $cbyOrder->amount = $order->amount;
            $cbyOrder->amountTotal = $order->outTotal;
            $cbyOrder->orderId = $order->id;
            $cbyOrder->orderNo = $order->orderNo;
            $cbyOrder->bankCard = $requestOrder->bankCard;
            $cbyOrder->realName = $requestOrder->realName;
            $cbyOrder->mobile = $requestOrder->mobile;
            $cbyOrder->remark = $requestOrder->remark;
            $cbyOrder->state = OrderCode::PAYING_STATUS;
        }
        $order->state = OrderCode::PAYING_STATUS;
        $return = $order->transaction(function (\Order $order) use ($cbyOrder) {
            if (!$cbyOrder->save()) {
                throw new TransactionException($cbyOrder->getFirstError());
            }
            if (!$order->save()) {
                throw new TransactionException($order->getFirstError());
            }
            if (!(new MiddleWareQueueLogic())->payingIncrease($order)) {
                throw new TransactionException(' paying increase fail');
            }
            return true;
        });
        if (!$return) {
            $this->setErrorMessage($order->getFirstError());
            return false;
        }
        if (!$this->cybBusiness($order, $requestOrder)) {
            return false;
        }
        return $order->state;
    }

    /**
     * Author:LazyBench
     * 车吧云业务
     * @param \Order $order
     * @param \RequestOrder $requestOrder
     * @return bool
     */
    protected function cybBusiness(\Order $order, $requestOrder)
    {
        $orders = Orders::findFirst([
            'conditions' => 'no=:no:',
            'bind' => [
                'no' => $order->orderNo,
            ],
        ]);
        if (!$orders) {
            $orders = new Orders();
            $orders->no = $order->orderNo;
            $orders->company = '四川云基物衡科技有限公司';
            $orders->payee = $requestOrder->realName;
            $orders->idCard = $order->idCard;
            $orders->bankCard = $requestOrder->bankCard;
            $orders->mobile = $requestOrder->mobile;
            $orders->paidIn = $order->amount;
            $orders->realPay = $order->realAmount;
            $orders->tax = $order->realAmount - $order->amount;
            $orders->remitStatus = 'NOT';
            $orders->bank = $requestOrder->bankName;
            $orders->offline = $order->offline;
            $orders->bizUserId = $requestOrder->bizUserId;
            if (!$orders->save()) {
                return false;
            }
        }
        return true;
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
        $cbyOrder = \CbyOrder::findFirst([
            'conditions' => 'orderNo=:orderNo:',
            'bind' => [
                'orderNo' => $order->orderNo,
            ],
        ]);
        if (!$cbyOrder) {
            return false;
        }
        $cbyOrder->state = OrderCode::PAID_STATUS;
        // 总支出计算
        $order->state = OrderCode::PAID_STATUS;
        $order->paidAt = date('Y-m-d H:i:s');
        $trans = $order->transaction(function (\Order $order) use ($cbyOrder) {
            $orderForUpdate = \Order::findFirst([
                'conditions' => 'id=:id:',
                'bind' => [
                    'id' => $order->id,
                ],
                //                'for_update' => true,
            ]);
            if ($orderForUpdate->state !== OrderCode::PAYING_STATUS) {
                throw new TransactionException('订单状态错误');
            }
            if (!$order->update()) {
                return false;
            }
            if (!$cbyOrder->update()) {
                throw new TransactionException($cbyOrder->getFirstError());
            }
            // 处理统计
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
     * Author:LazyBench
     *
     * @param \Order $order
     * @param array $data
     * @return bool|string|void
     * @throws \Exception
     */
    public function orderFail(\Order $order, $data = [])
    {
        $order->state = OrderCode::PAID_FAIL_STATUS;
        $cbyOrder = \CbyOrder::findFirst([
            'conditions' => 'orderNo=:orderNo:',
            'bind' => [
                'orderNo' => $order->orderNo,
            ],
        ]);
        $cbyOrder->state = OrderCode::PAID_FAIL_STATUS;
        $order->retryTimes++;
        $trans = $order->transaction(function (\Order $order) use ($cbyOrder) {
            if (!$order->update()) {
                return false;
            }
            if (!$cbyOrder->update()) {
                throw new TransactionException($cbyOrder->getFirstError());
            }
            return true;
        });
        if (!$trans) {
            $this->setErrorMessage($order->getFirstError());
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
        $cbyOrder = \CbyOrder::findFirst([
            'conditions' => 'orderNo=:orderNo:',
            'bind' => [
                'orderNo' => $order->orderNo,
            ],
        ]);
        $order->state = OrderCode::PAID_BACK_STATUS;
        $order->errorMsg = 'aaa';
        $cbyOrder->state = OrderCode::PAID_BACK_STATUS;
        $order->retryTimes++;
        $trans = $order->transaction(function (\Order $order) use ($cbyOrder) {
            if (!$order->update()) {
                return false;
            }
            if (!$cbyOrder->update()) {
                throw new TransactionException($cbyOrder->getFirstError());
            }
            return true;
        });
        return $trans;
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
        !$order->orderNo && $order->orderNo = md5($order->id);
        $requestOrder = \RequestOrder::findFirst([
            'columns' => 'orderNo,bankCard,realName,mobile,remark,bankName,bizUserId',
            'conditions' => 'id=:id:',
            'bind' => [
                'id' => $order->requestOrderId,
            ],
        ]);
        if ($order->state === OrderCode::PAID_STATUS) {
            if (!$this->cybBusiness($order, $requestOrder)) {
                return QueueLogic::RELEASE;
            }
            $order->appendMessage(new Message('订单成功已处理'));
            return QueueLogic::SUCCESS;
        }
        if ($order->state === OrderCode::PAID_FAIL_STATUS) {
            $order->appendMessage(new Message('订单失败已处理'));
            return QueueLogic::DELETE;
        }
        if ($order->offline) {
            $state = $this->orderPaid($order, []);
        } else {
            //查询线上订单状态
            $orders = Orders::findFirst([
                'conditions' => 'no=:no:',
                'bind' => [
                    'no' => $order->orderNo,
                ],
            ]);
            if (!$orders) {
                return QueueLogic::DELETE;
            }
            if ($orders->remitStatus === 'CASHDONE') {
                $state = $this->orderPaid($order, []);
            } else {
                return QueueLogic::RELEASE;
            }
        }
        if ($state === '-1') {
            return QueueLogic::RELEASE;
        }
        if ($state === false) {
            return QueueLogic::DELETE;
        }
        if ($state === true) {
            if (!$this->cybBusiness($order, $requestOrder)) {
                return QueueLogic::RELEASE;
            }
            return QueueLogic::SUCCESS;
        }
        return QueueLogic::RELEASE;
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
     * @return mixed
     */
    public function signQuery(\UserCard $userCard)
    {
        return true;
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
        return $bizUserId ? true : false;
    }
}