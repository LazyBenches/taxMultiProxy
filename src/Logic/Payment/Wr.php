<?php
/**
 * 万融
 * Author：Lzxyz
 * Date：2019/12/5 14:35
 * Desc：WrjsPayment.php
 */

namespace Application\Core\Components\Payment\Tax;

use Application\Cli\Components\Logic\QueueLogic;
use Application\Core\Components\Constants\ApiCode;
use Application\Core\Components\Constants\Code;
use Application\Core\Components\Constants\OrderCode;
use Application\Core\Components\Exception\TransactionException;
use Application\Core\Components\Logic\MiddleWareQueueLogic;
use Application\Core\Components\Payment\PaymentInterface;
use Application\Core\Components\Queue\Log;
use Application\Core\Components\Traits\LogicTrait;
use Application\Core\Components\Traits\PaymentTrait;
use LazyBench\Tax\WrJs\WrJs;
use Phalcon\Di;
use Phalcon\Mvc\Model\Message;

class Wr implements PaymentInterface
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
        $this->tax = new WrJs(Di::getDefault()->get('config')->wrjs->toArray());
    }

    /**
     * Author:LazyBench
     *
     * @param $channelId
     * @return bool|mixed
     */
    public function balance($channelId)
    {
        $channels = \OrderChannel::find([
            'conditions' => 'pid = :pid: AND isActive = 1',
            'bind' => [
                'pid' => $channelId,
            ],
        ]);
        $data = [];
        foreach ($channels as $channel) {
            if ($channel->serviceId > 0) {
                $wrRes = $this->tax->balance($channel->serviceId);
                if ((string)$wrRes['status'] !== '1') {
                    return false;
                }
                $data[$channel->serviceId] = [
                    'serviceCompanyId' => $channel->serviceId,
                    'totalBalance' => (string)$wrRes['data']['money'],
                    'bankBalance' => (string)$wrRes['data']['money'],
                    'aliPayBalance' => '0.00',
                    'wxBalance' => '0.00',
                ];
            }
        }
        return $data;
    }

    /**
     * 订单申请
     * Author：Lzxyz
     * @param \Order $order
     * @return bool|string
     * @throws \Exception
     */
    public function orderApply(\Order $order)
    {
        $requestOrder = \RequestOrder::findFirst($order->requestOrderId);
        if (!$requestOrder) {
            $this->setErrorCode(ApiCode::ORDER_NOT_EXIST);
            $order->appendMessage(new Message('订单不存在'));
            return false;
        }
        $order->orderNo = md5("{$order->id}_{$order->retryTimes}");
        // 查询订单编号是否存在
        $wrOrder = \WrOrder::findFirst([
            'conditions' => 'orderId = :orderId: and status in ({status:array}) and orderNo=:orderNo:',
            'bind' => [
                'orderId' => $order->id,
                'status' => ['PAYING', 'WAIT', 'PAID'],
                'orderNo' => $order->orderNo,
            ],
        ]);
        if (!$wrOrder) {
            // 创建打款订单
            $wrOrder = new \WrOrder();
            $wrOrder->orderNo = $order->orderNo;
            $wrOrder->orderId = $order->id;
            $wrOrder->amount = $order->amount;
            $wrOrder->realName = $requestOrder->realName;
            $wrOrder->idCard = $requestOrder->idCard;
            $wrOrder->bankCard = $requestOrder->bankCard;
            $wrOrder->mobile = $requestOrder->mobile;
            $wrOrder->status = 'WAIT';
            if (!$wrOrder->save()) {
                $this->setErrorCode(ApiCode::SERVER_BUSY);
                $order->appendMessage(new Message('服务异常'));
                return false;
            }
        }
        if ($wrOrder->status === 'SUCCESS') {
            $order->appendMessage(new Message('订单已支付'));
            return OrderCode::PAYING_STATUS;
        }
        // 判断订单是否未支付
        if ($wrOrder->status !== 'WAIT') {
            $this->setErrorCode(ApiCode::ORDER_NOT_EXIST);
            $order->appendMessage(new Message('订单不待支付中'));
            return OrderCode::PAYING_STATUS;
        }
        if ($wrOrder->wrOrderId && ($order->state === OrderCode::PAYING_STATUS)) {
            return OrderCode::PAYING_STATUS;
        }
        if ($order->state === OrderCode::PAID_STATUS) {
            $order->appendMessage(new Message('订单已支付'));
            return OrderCode::PAYING_STATUS;
        }
        // 线下支付
        if ($order->offline) {
            $res = [
                'data' => [
                    'orderId' => uniqid('or', true),
                    'payId' => uniqid('opi', true),
                ],
            ];
        } else {
            $params = [
                'name' => $wrOrder->realName,
                'identity' => $wrOrder->idCard,
                'receiveAccount' => $wrOrder->bankCard,
                'money' => integerToDecimal($wrOrder->amount),
                'telephoneNum' => $wrOrder->mobile,
                'landingCompanyId' => $order->serviceId,
                'clientOrderId' => $wrOrder->orderNo,
            ];
            $res = $this->tax->orderApply($params);
            Log::cmdOut('res:'.json_encode($res));
            if (empty($res) || $res['status'] !== 1) {
                $order->appendMessage(new Message($res['msg']));
                return false;
            }
            $wrOrder->remark = $res['msg'];
        }
        // 更新渠道订单状态
        $tran = $order->transaction(function (\Order $order) use ($wrOrder, $res) {
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
            $wrOrder->wrOrderId = $res['data']['orderId'];
            $wrOrder->wrPayId = $res['data']['payId'];
            $wrOrder->status = 'PAYING';
            if (!$wrOrder->save()) {
                throw new TransactionException($wrOrder->getFirstError());
            }
            // 回写订单号
            $order->orderNo = $wrOrder->orderNo;
            $order->state = OrderCode::PAYING_STATUS;
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
            $order->appendMessage(new Message($wrOrder->getFirstError()));
            return false;
        }
        return OrderCode::PAYING_STATUS;
    }

    /**
     * Author:LazyBench
     * 订单完成
     * @param \Order $order
     * @param array $data
     * @return bool|mixed|string|void
     * @throws \Exception
     */
    public function orderPaid(\Order $order, $data = [])
    {

        $wrOrder = \WrOrder::findFirst([
            'conditions' => 'orderNo = :orderNo:',
            'bind' => [
                'orderNo' => $order->orderNo,
            ],
        ]);
        if (empty($wrOrder)) {
            $order->appendMessage(new Message('订单不存在'));
            return false;
        }
        if ($wrOrder->status === 'PAID') {
            $order->appendMessage(new Message('订单已处理'));
            return true;
        }
        $order->paidAt = date('Y-m-d H:i:s');
        $trans = $order->transaction(function (\Order $order) use ($wrOrder, $data) {
            $wrOrder->bankBill = $data['bankFlowNumber'] ?? '';
            $wrOrder->wrStatus = $data['tradeStatus'];
            $wrOrder->status = 'PAID';
            if (!$wrOrder->update()) {
                $this->setErrorCode(ApiCode::SERVER_BUSY);
                return false;
            }
            // 总支出计算
            $order->state = OrderCode::PAID_STATUS;
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
     * 订单失败
     * @param \Order $order
     * @param array $data
     * @return bool|mixed|void
     * @throws \Exception
     */
    public function orderFail(\Order $order, $data = [])
    {
        $wrOrder = \WrOrder::findFirst([
            'conditions' => 'orderNo = :orderNo:',
            'bind' => [
                'orderNo' => $order->orderNo,
            ],
        ]);
        if (!$wrOrder) {
            $order->appendMessage(new Message('订单不存在'));
            return false;
        }
        $wrOrder->wrStatus = $data['tradeStatus'];
        $wrOrder->remark = $data['remark'];
        $wrOrder->status = 'FAIL';
        $order->state = 4;
        $order->errorMsg = $wrOrder->remark;
        $order->retryTimes++;
        return $order->transaction(function (\Order $order) use ($wrOrder) {
            if (!$wrOrder->update()) {
                $this->setErrorCode(ApiCode::SERVER_BUSY);
                $order->appendMessage(new Message($wrOrder->getFirstError()));
                return '-1';
            }
            if (!$order->update()) {
                return '-1';
            }
            return true;
        });
    }

    public function orderCancel(\Order $order, $data = [])
    {
        return true;
    }

    /**
     * 退汇处理
     * Author：Lzxyz
     * @param \Order $order
     * @param array $data
     * @return bool|mixed|void
     * @throws \Exception
     */
    public function orderBack(\Order $order, $data = [])
    {
        // 查询订单是否入账
        $wrOrder = \WrOrder::findFirst([
            'conditions' => 'orderNo = :orderNo:',
            'bind' => [
                'orderNo' => $order->orderNo,
            ],
        ]);
        if (empty($wrOrder)) {
            $order->appendMessage(new Message('订单不存在'));
            return false;
        }
        if ($wrOrder->status === 'BACK') {
            $order->appendMessage(new Message('订单已处理'));
            return true;
        }
        $trans = $order->transaction(function () use ($order, $wrOrder, $data) {
            $wrOrder->wrStatus = $data['tradeStatus'];
            $wrOrder->remark = $data['remark'];
            $wrOrder->status = 'BACK';
            if (!$wrOrder->update()) {
                $order->appendMessage(new Message($wrOrder->getFirstError()));
                return false;
            }
            $order->state = 5;
            $order->errorMsg = $wrOrder->remark;
            $order->retryTimes++;
            if (!$order->update()) {
                return false;
            }
            return true;
        });
        return $trans;
    }

    /**
     * Author:LazyBench
     *
     * @param \Order $order
     * @param array $data
     * @return mixed|string|void
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
        $wrOrder = \WrOrder::findFirst([
            'conditions' => 'orderId = :orderId: and orderNo=:orderNo:',
            'bind' => [
                'orderId' => $order->id,
                'orderNo' => $order->orderNo,
            ],
        ]);
        if (!$wrOrder) {
            $order->appendMessage(new Message('渠道订单不存在'));
            return QueueLogic::DELETE;
        }
        if ($wrOrder->status === 'PAID') {
            return QueueLogic::SUCCESS;
        }
        if ($order->offline) {
            $res = [
                'status' => 1,
                'data' => [
                    'tradeStatus' => '发放成功',
                    'bankFlowNumber' => '',
                ],
            ];
        } else {
            $res = $this->tax->orderQueryByClientOrderId($wrOrder->orderNo);
        }
        if (empty($res) || $res['status'] !== 1) {
            $order->appendMessage(new Message($res['msg']));
            return QueueLogic::RELEASE;
        }
        if ($res['data']['tradeStatus'] === '发放成功') { // 成功
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
        if ($res['data']['tradeStatus'] === '发放失败') { // 打款失败
            $state = $this->orderFail($order, $res['data']);
            if ($state === '-1') {
                return QueueLogic::RELEASE;
            }
            return QueueLogic::DELETE;
        }
        if ($res['data']['tradeStatus'] === '未发放') { // 未发放
            return QueueLogic::RELEASE;
        }
        return QueueLogic::RELEASE;
    }

    public function getBillBack(\Order $order)
    {
        return true;
    }

    public function getBillLog(\Order $order)
    {
        return true;
    }

    /**
     * 签约请求
     * Author：Lzxyz
     * @param \UserCard $userCard
     * @return bool|mixed
     * @throws \Exception
     */
    public function signApply(\UserCard $userCard)
    {
        if ($userCard->signWr) {
            return QueueLogic::DELETE;
        }
        $wrSignOrder = \WrSignOrder::findFirst([
            'conditions' => 'idCard = :idCard:',
            'bind' => [
                'idCard' => $userCard->idCard,
            ],
        ]);
        if (empty($wrSignOrder)) {
            $wrSignOrder = new \WrSignOrder();
            $wrSignOrder->idCard = $userCard->idCard;
        }
        if ($wrSignOrder && (in_array($wrSignOrder->status, [Code::SIGN_ING, Code::SIGN_OK]))) {
            $this->setErrorMessage('存在签约成功或签约中订单');
            return QueueLogic::DELETE;
        }
        // 发起签约请求
        $wrSignOrder->applyAt = date('Y-m-d H:i:s');
        $wrSignOrder->status = Code::SIGN_ING;
        $wrSignOrder->wrSignStatus = 'wait';
        $apply = $this->tax->signApply($userCard->realName, $userCard->idCard, $userCard->mobile);
        if ($apply['status'] != 1) {
            $this->setErrorMessage($apply['msg']);
            // 系统错误重试
            return $apply['status'] == 99 ? QueueLogic::RELEASE : QueueLogic::DELETE;
        }
        Log::cmdOut('上传口令通过');
        $wrSignOrder->wrSignId = $apply['data']['signId'];
        // 上传身份证正面
        $apply['data']['ossUploadInfoForFace']['file'] = STORAGE_PATH."idcard/{$userCard->idCard}/{$userCard->cardFront}";
        $front = $this->tax->signUpload($apply['data']['ossUploadInfoForFace']);
        if ($front['status'] != 1) {
            $this->setErrorMessage($front['msg']);
            return $front['status'] == 99 ? QueueLogic::RELEASE : QueueLogic::DELETE;
        }
        Log::cmdOut('上传正面通过');
        // 上传身份证反面
        $apply['data']['ossUploadInfoForBack']['file'] = STORAGE_PATH."idcard/{$userCard->idCard}/{$userCard->cardBack}";
        $back = $this->tax->signUpload($apply['data']['ossUploadInfoForBack']);
        if ($back['status'] != 1) {
            $this->setErrorMessage($back['msg']);
            return $back['status'] == 99 ? QueueLogic::RELEASE : QueueLogic::DELETE;
        }
        Log::cmdOut('上传反面通过');
        // 验证身份证
        sleep(5);
        $params = $userCard->toArray(['idCard', 'realName', 'cardBack', 'cardFront']);
        $params['signKey'] = $apply['data']['signKey'];
        $validate = $this->tax->signValidate($params);
        if ($validate['status'] != 1) {
            $this->setErrorMessage($validate['msg']);
            // 处理验证失败
            if ($validate['status'] == -1) {
                $userCard->signWr = Code::SIGN_FAIL;
                $wrSignOrder->status = Code::SIGN_FAIL;
                $wrSignOrder->wrError = $validate['msg'];
                return $this->signUpdate($userCard, $wrSignOrder);
            }
            return $validate['status'] == 99 ? QueueLogic::RELEASE : QueueLogic::DELETE;
        }
        Log::cmdOut('验证和实名通过');
        // 静默签约
        $sign = $this->tax->signSuccess($params);
        if ($sign['status'] != 1) {
            $this->setErrorMessage($sign['msg']);
            return $sign['status'] == 99 ? QueueLogic::RELEASE : QueueLogic::DELETE;
        }
        if (!$wrSignOrder->save()) {
            $this->setErrorMessage($wrSignOrder->getFirstError());
            return QueueLogic::RELEASE;
        }
        $userCard->signWr = Code::SIGN_ING;
        return $this->signUpdate($userCard, $wrSignOrder);
    }

    /**
     *  保存签约信息
     * Author：Lzxyz
     * @param \UserCard $userCard
     * @param \WrSignOrder $signOrder
     * @return string
     * @throws \Exception
     */
    private function signUpdate(\UserCard $userCard, \WrSignOrder $signOrder)
    {
        $trans = $userCard->transaction(function () use ($userCard, $signOrder) {
            if (!$signOrder->save()) {
                throw new TransactionException($signOrder->getFirstError());
            }
            if (!$userCard->save()) {
                return false;
            }
            return true;
        });
        if (!$trans) {
            $this->setErrorMessage($userCard->getFirstError());
            return QueueLogic::RELEASE;
        }
        return QueueLogic::DELETE;
    }


    /**
     * 签约查询
     * Author：Lzxyz
     * @param \UserCard $userCard
     * @return bool|mixed|string
     * @throws \Exception
     */
    public function signQuery(\UserCard $userCard)
    {
        // 签约最终状态
        if ($userCard->signWr == Code::SIGN_OK || $userCard->signWr == Code::SIGN_FAIL) {
            return QueueLogic::DELETE;
        }
        $wrSignOrder = \WrSignOrder::findFirst([
            'conditions' => 'idCard = :idCard:',
            'bind' => [
                'idCard' => $userCard->idCard,
            ],
            'order' => 'id desc',
        ]);
        if (empty($wrSignOrder) || empty($wrSignOrder->wrSignId)) {
            $userCard->appendMessage(new Message('签约订单不存在'));
            return QueueLogic::BURY;
        }
        $res = $this->tax->signQuery($wrSignOrder->wrSignId);
        if ($res['status'] != 1) {
            $userCard->appendMessage(new Message($res['msg'] ?? 'API错误未定义'));
            return QueueLogic::RELEASE;
        }
        if ($res['data']['signStatus'] == 'success') {
            $wrSignOrder->status = 1;
            $wrSignOrder->wrContractUrl = $res['data']['viewContractUrl'];
            $wrSignOrder->wrSignTime = $res['data']['modifyTime'] ?? '';
            $userCard->signWr = 1;
        }
        Log::cmdOut(json_encode($res));
        $wrSignOrder->wrSignStatus = $res['data']['signStatus'];
        // 格式化状态
        $this->signFmtState($userCard);
        return $this->signUpdate($userCard, $wrSignOrder);
    }

    /**
     * 签约验证
     * Author：Lzxyz
     * @param $idCard
     * @return bool
     */
    private function checkSign($idCard)
    {
        $wrSignOrder = \WrSignOrder::findFirst([
            'conditions' => 'idCard = :idCard: AND status = :status:',
            'bind' => [
                'idCard' => $idCard,
                'status' => 1,
            ],
        ]);
        return $wrSignOrder ? true : false;
    }

    /**
     * Author:LazyBench
     * 是否签约
     * @param \UserCard $userCard
     * @return bool
     */
    public function isSign(\UserCard $userCard = null): bool
    {
        return isset($userCard->signWr) && $userCard->signWr === Code::SIGN_OK;
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
        return 'signWr';
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