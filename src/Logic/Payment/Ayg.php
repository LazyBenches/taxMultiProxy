<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2019/8/16
 * Time: 10:40
 */

namespace LazyBench\TaxMultiProxy\Logic\Payment;


use LazyBench\TaxMultiProxy\Traits\PaymentTrait;

class Ayg implements PaymentInterface
{
    use PaymentTrait, LogicTrait;
    protected $config;
    protected $tax;

    public function __construct()
    {
        $this->tax = new \LazyBench\Tax\Ayg\Ayg(Di::getDefault()->get('config')->ayg->toArray());
    }

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
     * 获取余额
     * @param $channelId
     * @return array|bool|mixed
     */
    public function balance($channelId)
    {
        $account = new Account();
        $data = $account->balance();
        $response = $this->tax->getClient()->postPay($data['path'], $data['data'], $data['method']);
        if ($response['code'] !== '0000') {
            return false;
        }
        $res = [];
        foreach ($response['data'] as $data) {
            $res[$data['serviceCompanyId']] = [
                'serviceCompanyId' => $data['serviceCompanyId'],
                'totalBalance' => $data['totalBalance'],
                'bankBalance' => $data['bankBalance'],
                'aliPayBalance' => $data['alipayBalance'],
                'wxBalance' => $data['wxBalance'],
                'serviceCompanyName' => $data['serviceCompanyName'],
            ];
        }
        return $res;
    }

    /**
     * Author:LazyBench
     * 订单申请
     * @param \Order $order
     * @return bool
     * @throws \Exception
     */
    public function orderApply(\Order $order)
    {
        if ($order->state === OrderCode::PAYING_STATUS) {
            $order->appendMessage(new Message('支付中'));
            return OrderCode::PAYING_STATUS;
        }
        if ($order->state === OrderCode::PAID_FAIL_STATUS) {
            $order->appendMessage(new Message('支付失败'));
            return OrderCode::PAYING_STATUS;
        }
        if ($order->state === OrderCode::PAID_STATUS) {
            $order->appendMessage(new Message('订单已支付'));
            return OrderCode::PAYING_STATUS;
        }
        $requestOrder = \RequestOrder::findFirst($order->requestOrderId);
        $order->orderNo = md5("{$order->id}_{$order->retryTimes}");
        $aygOrder = \AygOrder::findFirst([
            'conditions' => 'orderNo=:orderNo:',
            'bind' => [
                'orderNo' => $order->orderNo,
            ],
        ]);
        if (!$aygOrder) {
            $aygOrder = AygPaymentLogic::createAygOrder($order, $requestOrder);
        }
        if (!$aygOrder) {
            $order->appendMessage(new Message('支付失败'.$order->getFirstError()));
            return false;
        }
        if ($aygOrder->state === OrderCode::PAYING_STATUS) {
            $order->appendMessage(new Message('支付中'));
            return OrderCode::PAYING_STATUS;
        }
        if ($aygOrder->state !== OrderCode::PENDING_STATUS) {
            $order->appendMessage(new Message('未进入准备状态'));
            return false;
        }
        // 线下支付
        if ($order->offline) {
            $data = [
                'orderNo' => uniqid('offline_or_', true),
                'reqNo' => uniqid('offline_re_', true),
            ];
            $resultCode = AygPaymentLogic::successPushing($order, $aygOrder, $data);
            return $resultCode;
        }

        $orderPay = new OrderPay();
        $orderPay->batchNo = $aygOrder->batchNo;
        $orderPay->outOrderNo = $aygOrder->orderNo;
        $orderPay->amount = $aygOrder->amount;
        $orderPay->memo = '订单申请';
        $orderPay->accountNo = $aygOrder->bankCard;
        $orderPay->accountName = $aygOrder->realName;
        $orderPay->serviceCompanyId = $order->serviceId;
        $orderPay->bank = $requestOrder->bankName;
        $orderPay->depositBank = $requestOrder->bankZone;
        $orderPay->idCard = $aygOrder->idCard;
        $orderPay->phone = $requestOrder->mobile;
        $data = $orderPay->applyPay();
        $response = $this->tax->getClient()->postPay($data['path'], $data['data'], $data['method']);
        $code = $response['code'] ?? '-1';
        $data = $response['data'] ?? [];
        $resultCode = '-1';
        $code === '2000' && $resultCode = AygPaymentLogic::repeatPushing($order, $aygOrder);
        $code === '0000' && $resultCode = AygPaymentLogic::successPushing($order, $aygOrder, $data);
        $code === '2001' && $resultCode = AygPaymentLogic::repeatPushing($order, $aygOrder);
        $code === '9999' && $resultCode = AygPaymentLogic::failPushing($order, $aygOrder, $response['msg']);
        return $resultCode;
    }

    /**
     * Author:LazyBench
     * 查询订单状态
     * @param \Order $order
     * @param array $data
     * @return bool|int|mixed|string|void
     * @throws \Exception
     */
    public function orderState(\Order $order, $data = [])
    {
        if ($order->state === OrderCode::PAID_STATUS) {
            $this->setErrorMessage('order state');
            return QueueLogic::SUCCESS;
        }
        $aygOrder = \AygOrder::findFirst([
            'conditions' => 'orderNo=:orderNo:',
            'bind' => [
                'orderNo' => $order->orderNo,
            ],
        ]);
        if (!$aygOrder) {
            $this->setErrorMessage('ayg order empty');
            return QueueLogic::DELETE;
        }
        if ($order->offline) {
            $response = [
                'code' => '0000',
                'data' => [
                    'code' => '30',
                    'outOrderNo' => $order->orderNo,
                    'reqNo' => uniqid('offline_re_', true),
                    'orderNo' => uniqid('offline_or_', true),
                ],
            ];
        } else {
            $orderPay = new OrderPay();
            $orderPay->outOrderNo = $aygOrder->orderNo;
            $orderPay->responseReNo = $aygOrder->responseReNo;
            $data = $orderPay->queryPayment();
            $response = $this->tax->getClient()->postPay($data['path'], $data['data'], $data['method']);
        }
        $code = $response['code'] ?? '-1';
        if ($code !== '0000') {
            $this->setErrorMessage($response['msg'] ?? 'empty response msg');
            return QueueLogic::RELEASE;
        }
        if ($code === '2002') {
            $this->setErrorMessage($response['msg'] ?? 'empty response msg');
            return QueueLogic::RELEASE;
        }
        $responseData = $response['data'] ?? [];
        $code = $responseData['code'] ?? '-1';
        if ($code === '-1') {
            return QueueLogic::RELEASE;
        }
        if ($code === '20') {
            if ($this->orderPaying($order, $responseData) === OrderCode::PAYING_STATUS) {
                return QueueLogic::RELEASE;
            }
        }
        if ($code === '30') {
            $resultCode = $this->orderPaid($order, $responseData);
            if ($resultCode === '-1') {
                return QueueLogic::RELEASE;
            }
            if ($resultCode === false) {
                return QueueLogic::DELETE;
            }
            return QueueLogic::SUCCESS;
        }
        if ($code === '40') {
            $resultCode = $this->orderFail($order, $responseData);
            if ($resultCode === '-1') {
                return QueueLogic::RELEASE;
            }
            if ($resultCode === false) {
                return QueueLogic::DELETE;
            }
            return QueueLogic::DELETE;
        }
        return QueueLogic::RELEASE;
    }

    /**
     * Author:LazyBench
     * 支付成功
     * @param \Order $order
     * @param array $data
     * @return bool|int|string|void
     * @throws \Exception
     */
    public function orderPaid(\Order $order, $data = [])
    {
        $aygOrder = \AygOrder::findFirst([
            'conditions' => 'orderNo=:orderNo:',
            'bind' => [
                'orderNo' => $data['outOrderNo'],
            ],
        ]);
        if (!$aygOrder) {
            $this->setErrorMessage('ayg order empty');
            return false;
        }
        $requestOrderSwitch = \RequestOrderSwitch::findFirst([
            'conditions' => 'requestOrderId=:requestOrderId:',
            'bind' => [
                'requestOrderId' => $order->requestOrderId,
            ],
        ]);
        if (!$requestOrderSwitch) {
            return false;
        }
        $order->state = OrderCode::PAID_STATUS;
        $order->paidAt = date('Y-m-d H:i:s');
        $aygOrder->state = OrderCode::PAID_STATUS;
        $aygOrder->responseReNo = $data['reqNo'];
        $aygOrder->responseOrderNo = $data['orderNo'];
        $aygOrderId = $order->transaction(function (\Order $order) use ($aygOrder) {
            $lockOrder = $order->findFirst([
                'conditions' => 'id=:id:',
                'bind' => [
                    'id' => $order->id,
                ],
                //                'for_update' => true,
            ]);
            if ($lockOrder->state !== OrderCode::PAYING_STATUS) {
                return false;
            }
            if (!$order->update()) {
                return false;
            }
            if (!$aygOrder->update()) {
                return false;
            }
            if (!(new MiddleWareQueueLogic())->paidIncrease($order)) {
                return false;
            }
            return $aygOrder->id;
        });
        return $aygOrderId ? OrderCode::PAID_STATUS : -1;
    }

    /**
     * Author:LazyBench
     * 支付失败
     * @param \Order $order
     * @param array $data
     * @return bool|int|string|void
     * @throws \Exception
     */
    public function orderFail(\Order $order, $data = [])
    {
        $aygOrder = \AygOrder::findFirst([
            'conditions' => 'orderNo=:orderNo:',
            'bind' => [
                'orderNo' => $order->orderNo,
            ],
        ]);
        if (!$aygOrder) {
            return false;
        }
        $aygOrder->state = $data['exceptionCode'] === '8021' ? OrderCode::CANCEL_STATUS : OrderCode::PAID_FAIL_STATUS;
        $order->state = OrderCode::PAID_FAIL_STATUS;
        $order->errorMsg = $data['msg'];
        $aygOrder->errorMsg = $data['msg'];
        $aygOrder->responseReNo = $data['reqNo'];
        $aygOrder->responseOrderNo = $data['orderNo'];
        $order->retryTimes++;
        $res = $order->transaction(function (\Order $order) use ($aygOrder) {
            if (!$order->update()) {
                return false;
            }
            if (!$aygOrder->update()) {
                throw new TransactionException($aygOrder->getFirstError());
            }
            return true;
        });
        return $res ? OrderCode::PAID_FAIL_STATUS : -1;
    }

    /**
     * Author:LazyBench
     * 支付中
     * @param \Order $order
     * @param array $data
     * @return string
     */
    public function orderPaying(\Order $order, $data = [])
    {
        return OrderCode::PAYING_STATUS;
    }

    public function getBillBack(\Order $order)
    {
        $aygOrder = \AygOrder::findFirst([
            'conditions' => 'orderId=:orderId:',
            'bind' => [
                'orderId' => $order->id,
            ],
        ]);
        $account = new Account();
        $account->reqNo = $aygOrder->responseReNo;
        $account->outOrderNo = $aygOrder->orderNo;
        $account->notifyUrl = Di::getDefault()->get('config')->ayg->callbackUrl.'/bill/back';
        $data = $account->billImage();
        return $this->tax->getClient()->postPay($data['path'], $data['data'], $data['method']);
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
     * 申请签约
     * Author：Lzxyz
     * @param \UserCard $userCard
     * @return bool|mixed|string
     */
    public function signApply(\UserCard $userCard)
    {
        if ($userCard->signAyg == Code::SIGN_ING || $userCard->signAyg == Code::SIGN_OK) {
            $this->setErrorMessage('[爱员工]存在签约完成或签约中订单');
            return QueueLogic::DELETE;
        }
        $order = new Order();
        $order->notifyUrl = Di::getDefault()->get('config')->ayg->callbackUrl.'/electronic/contract/sign';
        $order->appendListItem(uniqid('AYG_', false), $userCard->idCard, $userCard->realName, '0', $userCard->mobile);
        $order->templateGroupId = Di::getDefault()->get('config')->ayg->groupTemplateId;
        $data = $order->batchTemplateGroupSubmit();
        $res = $this->tax->getClient()->post($data['path'], $data['data']);
        $code = $res['code'] ?? '-1';
        if ($code !== '0000') {
            $this->setErrorMessage($res['msg'] ?? '');
            $userCard->signAyg = Code::SIGN_FAIL;
            if (!$userCard->update()) {
                $this->setErrorMessage($userCard->getFirstError());
                return QueueLogic::RELEASE;
            }
            return QueueLogic::DELETE;
        }
        $userCard->signAyg = Code::SIGN_ING;
        if (!$userCard->update()) {
            return QueueLogic::RELEASE;
        }
        return QueueLogic::SUCCESS;
    }

    /**
     * 签约查询
     * Author：Lzxyz
     * @param \UserCard $userCard
     * @return bool|mixed
     */
    public function signQuery(\UserCard $userCard)
    {
        $templateIds = Di::getDefault()->get('config')->ayg->templateId;
        $success = 0;
        $count = 0;
        foreach ($templateIds as $templateId) {
            $count++;
            $result = AygSignLogic::signQuerySingleToAyg($userCard, $templateId, $this->tax->getClient());
            Log::cmdOut(json_encode($result));
            if ($result && $result['state'] === \Application\Core\Components\Constants\AygCode::CLOSED) {
                $aygSignOrder = AygSignOrder::findFirst([
                    'conditions' => 'responseOrderId = :responseOrderId:',
                    'bind' => [
                        'responseOrderId' => $result['orderId'],
                    ],
                ]);
                if ($aygSignOrder && $aygSignOrder->state === \Application\Core\Components\Constants\AygCode::CLOSED) {
                    $success++;
                    continue;
                }
                if (!$aygSignOrder) {
                    $aygSignOrder = new  AygSignOrder();
                    $aygSignOrder->requestOrderId = $result['orderId'];
                }
                $aygSignOrder->serviceCompanyId = $result['serviceCompanyId'];
                $aygSignOrder->templateId = $result['templateId'];
                $aygSignOrder->state = $result['state'];
                $aygSignOrder->idCard = $result['idcard'];
                $aygSignOrder->responseSystemOrderId = $result['extrSystemId'];
                $aygSignOrder->responseOrderId = $result['orderId'];
                $aygSignOrder->subState = $result['subState'];
                $aygSignOrder->orderId = $result['extrOrderId'];
                if (!$aygSignOrder->save()) {
                    Log::cmdOut(__LINE__.$aygSignOrder->getFirstError());
                    return QueueLogic::RELEASE;
                } else {
                    $success++;
                    Log::cmdOut('success '.$result['orderId']);
                }
                continue;
            } elseif ($result['state'] == 'NO_SIGN_RECORD') {
                $userCard->signAyg = Code::SIGN_FAIL;
                $this->signFmtState($userCard);
                if (!$userCard->save()) {
                    return QueueLogic::RELEASE;
                }
                return QueueLogic::DELETE;
            }
            Log::cmdOut(json_encode($result));
        }
        if ($success !== $count) {
            return QueueLogic::RELEASE;
        }
        $userCard->signAyg = Code::SIGN_OK;
        // 格式化状态
        $this->signFmtState($userCard);
        if (!$userCard->save()) {
            return QueueLogic::RELEASE;
        }
        return QueueLogic::DELETE;
    }

    /**
     * Author:LazyBench
     * 是否签约
     * @param \UserCard $userCard
     * @return bool
     */
    public function isSign(\UserCard $userCard = null): bool
    {
        return isset($userCard->signAyg) && $userCard->signAyg === Code::SIGN_OK;
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
        return 'signAyg';
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