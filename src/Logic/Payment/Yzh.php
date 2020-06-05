<?php
/**
 * Author：Lzxyz
 * Date：2019/8/14 11:50
 * Desc：YzhPayment.php
 */

namespace Application\Core\Components\Payment\Tax;


use Application\Cli\Components\Logic\QueueLogic;
use Application\Core\Components\Constants\ApiCode;
use Application\Core\Components\Constants\Code;
use Application\Core\Components\Constants\OrderCode;
use Application\Core\Components\Exception\TransactionException;
use Application\Core\Components\Logic\MiddleWareQueueLogic;
use Application\Core\Components\Payment\PaymentInterface;
use Application\Core\Components\Traits\LogicTrait;
use Application\Core\Components\Traits\PaymentTrait;
use Phalcon\Di;
use Phalcon\Mvc\Model\Message;

class Yzh implements PaymentInterface
{
    use PaymentTrait, LogicTrait;
    private $tax;


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
        $this->tax = new \LazyBench\Tax\Yzh\Yzh(Di::getDefault()->get('config')->Yzh->toArray());
    }


    /**
     * Author:LazyBench
     * 余额查询
     * @param $channelId
     * @return array|mixed
     */
    public function balance($channelId)
    {
        $yzhRes = $this->tax->queryAccounts();
        if ((string)$yzhRes['code'] !== '0000') {
            return [];
        }
        $data = [];
        foreach ($yzhRes['data']['dealer_infos'] as $key => $value) {
            $data[$value['broker_id']] = [
                'serviceCompanyId' => $value['broker_id'],
                'totalBalance' => (string)round($value['bank_card_balance'] + $value['alipay_balance'] + $value['wxpay_balance'], 2),
                'bankBalance' => $value['bank_card_balance'],
                'aliPayBalance' => $value['alipay_balance'],
                'wxBalance' => $value['wxpay_balance'],
            ];
        }
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
        $requestOrder = \RequestOrder::findFirst($order->requestOrderId);
        if (!$requestOrder) {
            $this->setErrorCode(ApiCode::ORDER_NOT_EXIST);
            $order->appendMessage(new Message('订单不存在'));
            return false;
        }
        // 回写订单号
        $order->orderNo = md5("{$order->id}_{$order->retryTimes}");
        // 查询订单编号是否存在
        $yzhOrder = \YzhOrder::findFirst([
            'conditions' => 'orderId = :orderId: and state in ({state:array}) and orderNo=:orderNo:',
            'bind' => [
                'orderId' => $order->id,
                'state' => ['PAYING', 'WAIT', 'PAID'],
                'orderNo' => $order->orderNo,
            ],
        ]);
        if (!$yzhOrder) {
            // 创建打款订单
            $yzhOrder = new \YzhOrder();
            $yzhOrder->orderNo = $order->orderNo;
            $yzhOrder->orderId = $order->id;
            $yzhOrder->amount = $order->amount;
            $yzhOrder->realName = $requestOrder->realName;
            $yzhOrder->idCard = $requestOrder->idCard;
            $yzhOrder->bankCard = $requestOrder->bankCard;
            $yzhOrder->mobile = $requestOrder->mobile;
            $yzhOrder->state = 'WAIT';
            if (!$yzhOrder->save()) {
                $this->setErrorCode(ApiCode::SERVER_BUSY);
                $order->appendMessage(new Message('服务异常'));
                return false;
            }
        }
        // 判断订单是否未支付
        if ($yzhOrder->state !== 'WAIT') {
            $this->setErrorCode(ApiCode::ORDER_NOT_EXIST);
            $order->appendMessage(new Message('订单不存在'));
            return false;
        }
        // 线下支付
        if ($order->offline) {
            $res = [
                'data' => [
                    'ref' => uniqid('offline_', true),
                ],
            ];
        } else {
            // 发起打款
            $params = [
                'broker_id' => $order->serviceId,
                'order_id' => $yzhOrder->orderNo, // 订单编号
                'id_card' => $yzhOrder->idCard,
                'real_name' => $yzhOrder->realName, // 姓名
                'card_no' => $yzhOrder->bankCard,
                'phone_no' => $yzhOrder->mobile,
                'pay' => integerToDecimal($yzhOrder->amount),
                'pay_remark' => '',
            ];
            $res = $this->tax->orderRealtime($params);
            if (empty($res) || ($res['code'] != '0000' && $res['code'] != '2002')) {
                $order->appendMessage(new Message($res['message']));
                return false;
            }
            if ($res['code'] === '99') { // 掉单重试
                return '-1';
            }
        }
        // 更新渠道订单状态

        $yzhOrder->orderNoYzh = $res['data']['ref'] ?? ''; // 2002时没有
        $yzhOrder->subStatusYzh = 0;
        $yzhOrder->state = 'PAYING';
        $order->state = OrderCode::PAYING_STATUS;
        $tran = $order->transaction(function (\Order $order) use ($yzhOrder) {
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
            if (!$yzhOrder->save()) {
                throw new TransactionException($yzhOrder->getFirstError());
            }
            if (!$order->save()) {
                throw new TransactionException($order->getFirstError());
            }
            // 数据统计
            $middle = new MiddleWareQueueLogic();
            if (!$middle->payingIncrease($order)) {
                throw new TransactionException('异常');
            }
            return true;
        });
        if (!$tran) {
            $order->appendMessage(new Message($yzhOrder->getFirstError()));
            return false;
        }
        return OrderCode::PAYING_STATUS;
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
        /**
         * @var \YzhOrder $yzhOrder
         */
        $yzhOrder = \YzhOrder::findFirst([
            'conditions' => 'orderId = :orderId: and orderNo=:orderNo:',
            'bind' => [
                'orderId' => $order->id,
                'orderNo' => $order->orderNo,
            ],
        ]);
        if (!$yzhOrder) {
            $order->appendMessage(new Message('渠道订单不存在'));
            return QueueLogic::DELETE;
        }
        if ($yzhOrder->state === 'PAID') {
            return QueueLogic::SUCCESS;
        }
        if ($order->offline) {
            $res = [
                'data' => [
                    'status' => '1',
                    'status_detail' => '1',
                    'broker_bank_bill' => '',
                    'tax' => 0,
                    'broker_fee' => 0,
                    'sys_fee' => 0,
                    'user_fee' => 0,
                    'status_detail_message' => '线下支付成功',
                ],
            ];
        } else {
            $params = [
                'order_id' => $yzhOrder->orderNo,
            ];
            $res = $this->tax->queryRealtimeOrder($params);
            if (empty($res) || $res['code'] !== '0000') {
                $order->appendMessage(new Message($res['message']));
                return QueueLogic::RELEASE;
            }
        }
        if ($res['data']['status'] === '1' && $res['data']['status_detail'] === '0') { // 成功
            $state = $this->orderPaid($order, $res['data']);
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
        if ($res['data']['status'] === '2' || $res['data']['status'] === '15') { // 打款失败
            $state = $this->orderFail($order, $res['data']);
            if ($state === '-1') {
                return QueueLogic::RELEASE;
            }
            return QueueLogic::DELETE;
        }
        if ($res['data']['status'] === '9') { // 打款失败
            $state = $this->orderBack($order, $res['data']);
            if ($state === '-1') {
                return QueueLogic::RELEASE;
            }
            return QueueLogic::DELETE;
        }
        return QueueLogic::RELEASE;
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
        // 更新
        $yzhOrder = \YzhOrder::findFirst([
            'conditions' => 'orderNo = :orderNo:',
            'bind' => [
                'orderNo' => $order->orderNo,
            ],
        ]);
        if (empty($yzhOrder)) {
            $order->appendMessage(new Message('订单不存在'));
            return false;
        }
        if ($yzhOrder->state === 'PAID') {
            $order->appendMessage(new Message('订单已处理'));
            return true;
        }
        $order->paidAt = date('Y-m-d H:i:s');
        $trans = $order->transaction(function (\Order $order) use ($yzhOrder, $data) {
            $yzhOrder->bankBill = $data['broker_bank_bill'];
            $yzhOrder->tax = decimalToInteger($data['tax']);
            $yzhOrder->brokerFee = decimalToInteger($data['broker_fee']);
            $yzhOrder->sysFee = decimalToInteger($data['sys_fee']);
            $yzhOrder->userFee = decimalToInteger($data['user_fee']);
            $yzhOrder->statusYzh = $data['status'];
            $yzhOrder->statusMsgYzh = $data['status_message'];
            $yzhOrder->subStatusYzh = $data['status_detail'];
            $yzhOrder->subStatusMsgYzh = $data['status_detail_message'];
            $yzhOrder->state = 'PAID';
            if (!$yzhOrder->update()) {
                $this->setErrorCode(ApiCode::SERVER_BUSY);
                return false;
            }
            // 总支出计算
            $total = ($yzhOrder->amount + $yzhOrder->brokerFee + $yzhOrder->sysFee + $yzhOrder->userFee + $yzhOrder->tax);
            $order->state = OrderCode::PAID_STATUS;
            $order->outTotal = $total;
            if (!$order->update()) {
                return false;
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
     * 打款失败
     * @param \Order $order
     * @param array $data
     * @return bool|mixed|void
     * @throws \Exception
     */
    public function orderFail(\Order $order, $data = [])
    {
        // 更新
        $yzhOrder = \YzhOrder::findFirst([
            'conditions' => 'orderNo = :orderNo:',
            'bind' => [
                'orderNo' => $order->orderNo,
            ],
        ]);
        if (!$yzhOrder) {
            $order->appendMessage(new Message('订单不存在'));
            return false;
        }
        $yzhOrder->statusYzh = $data['status'];
        $yzhOrder->statusMsgYzh = $data['status_message'];
        $yzhOrder->subStatusYzh = $data['status_detail'];
        $yzhOrder->subStatusMsgYzh = $data['status_detail_message'];
        $yzhOrder->state = 'FAIL';
        $order->state = 4;
        $order->errorMsg = $yzhOrder->subStatusMsgYzh;
        $order->retryTimes++;
        return $order->transaction(function (\Order $order) use ($yzhOrder) {
            if (!$yzhOrder->update()) {
                $this->setErrorCode(ApiCode::SERVER_BUSY);
                $order->appendMessage(new Message($yzhOrder->getFirstError()));
                return '-1';
            }
            if (!$order->update()) {
                return '-1';
            }
            return true;
        });
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
        // 查询订单是否入账
        $yzhOrder = \YzhOrder::findFirst([
            'conditions' => 'orderNo = :orderNo:',
            'bind' => [
                'orderNo' => $order->orderNo,
            ],
        ]);
        if (empty($yzhOrder)) {
            $order->appendMessage(new Message('订单不存在'));
            return false;
        }
        if ($yzhOrder->state === 'BACK') {
            $order->appendMessage(new Message('订单已处理'));
            return true;
        }

        $yzhOrder->bankBill = $data['broker_bank_bill'];
        $yzhOrder->statusYzh = $data['status'];
        $yzhOrder->statusMsgYzh = $data['status_message'];
        $yzhOrder->subStatusYzh = $data['status_detail'];
        $yzhOrder->subStatusMsgYzh = $data['status_detail_message'];
        $yzhOrder->state = 'BACK';
        $order->state = 5;
        $order->errorMsg = $yzhOrder->subStatusMsgYzh;
        $order->retryTimes++;
        $trans = $order->transaction(function (\Order $order) use ($yzhOrder) {
            if (!$yzhOrder->update()) {
                $order->appendMessage(new Message($yzhOrder->getFirstError()));
                return false;
            }
            if (!$order->update()) {
                return false;
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
     * 电子回单
     * @param \Order $order
     * @return bool|mixed|void
     */
    public function getBillBack(\Order $order)
    {
        if (!$order->orderNo || $order->state !== OrderCode::PAID_STATUS) {
            $order->appendMessage(new Message('订单未完成'));
            return false;
        }
        // 查询request
        $requestOrder = \RequestOrder::findFirst($order->requestOrderId);
        if (!$requestOrder) {
            $order->appendMessage(new Message('订单不存在'));
            return false;
        }
        $param = [
            'order_id' => $order->orderNo,
        ];
        $res = $this->tax->receiptFile($param);
        if ($res['code'] !== '0000') {
            $order->appendMessage(new Message($res['message']));
            return false;
        }
        // 保存文件
        $filePath = ROOT_PATH."/storage/billimage/{$requestOrder->orderNo}/";
        $fileName = "{$filePath}{$order->orderNo}.".substr($res['data']['file_name'], -3);
        return billFilePut($res['data']['url'], $filePath, $fileName);
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
        return isset($userCard->signYzh) && $userCard->signYzh === Code::SIGN_OK;
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
        return 'signYzh';
    }

    /**
     * Author:LazyBench
     * 是否已绑卡
     * @param \BankCard $bankCard
     * @return bool
     */
    public function isBindCard(\BankCard $bankCard = null): bool
    {
        return $bankCard ? true : false;
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