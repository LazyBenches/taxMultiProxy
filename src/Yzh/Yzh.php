<?php
/**
 * Author：Lzxyz
 * Date：2019/8/13 9:24
 * Desc：Yzh.php
 * 云账户
 */

namespace LazyBench\Tax\Yzh;

use LazyBench\Tax\Yzh\Http\Client;
use LazyBench\Tax\Yzh\Util\DesUtil;
use LazyBench\Tax\Yzh\Util\SignUtil;

class Yzh
{
    private $host;
    private $appKey;
    private $des3Key;
    private $dealerId;
    private $brokerId;
    private $notifyUrl;

    public function __construct(array $config)
    {
        $this->host = $config['host'] ?? ''; // 主机地址
        $this->appKey = $config['appKey'] ?? ''; // 商户签名Key
        $this->des3Key = $config['des3Key'] ?? ''; // 商户数据加密Key
        $this->dealerId = $config['dealerId'] ?? ''; // 商户ID
        $this->brokerId = $config['brokerId'] ?? ''; // 代征主体ID
        $this->notifyUrl = $config['notifyUrl'] ?? ''; // 通知地址
    }

    /**
     * 银行卡实时下单
     * @param $data
     * @return bool|mixed|string
     */
    public function orderRealtime($data)
    {
        $url = '/api/payment/v1/order-realtime';
        $data['dealer_id'] = $data['dealer_id'] ?? $this->dealerId;
        $data['broker_id'] = $data['broker_id'] ?? $this->brokerId;
        $data['notify_url'] = $data['notify_url'] ?? $this->notifyUrl;
        return $this->http($url, $data, 'POST');
    }

    /**
     * 支付宝实时下单
     * @return bool|mixed|string
     */
    public function orderAliPay()
    {
        $url = '/api/payment/v1/order-alipay';
        $data['dealer_id'] = $data['dealer_id'] ?? $this->dealerId;
        $data['broker_id'] = $data['broker_id'] ?? $this->brokerId;
        $data['notify_url'] = $data['notify_url'] ?? $this->notifyUrl;
        return $this->http($url, $data, 'POST');
    }

    /**
     * 微信红包实时下单
     * @return bool|mixed|string
     */
    public function orderWxPay()
    {
        $url = '/api/payment/v1/order-wxpay';
        $data['dealer_id'] = $data['dealer_id'] ?? $this->dealerId;
        $data['broker_id'] = $data['broker_id'] ?? $this->brokerId;
        $data['notify_url'] = $data['notify_url'] ?? $this->notifyUrl;
        return $this->http($url, $data, 'POST');
    }

    /**
     * 查询一个订单
     * @param $data
     * @return bool|mixed|string
     */
    public function queryRealtimeOrder($data)
    {
        $data['channel'] = $data['dealer_id'] ?? '银行卡';
        $data['data_type'] = $data['data_type'] ?? '';
        $url = '/api/payment/v1/query-realtime-order';
        return $this->http($url, $data, 'GET');
    }

    /**
     * 查询商户余额
     * @return bool|mixed|string
     */
    public function queryAccounts()
    {
        $url = '/api/payment/v1/query-accounts';
        $data['dealer_id'] = $this->dealerId;
        return $this->http($url, $data, 'GET');
    }

    /**
     * 查询电子回单
     * @return bool|mixed|string
     */
    public function receiptFile($data)
    {
        $url = '/api/payment/v1/receipt/file';
        return $this->http($url, $data, 'GET');
    }

    /**
     * 取消待打款订单
     * @return bool|mixed|string
     */
    public function orderFail()
    {
        $url = '/api/payment/v1/receipt/file';
        $data['dealer_id'] = $data['dealer_id'] ?? $this->dealerId;
        return $this->http($url, $data, 'GET');
    }


    /**
     * 白名单验证
     * @param $data
     * @return bool|mixed|string
     */
    public function userWhiteCheck($data)
    {
        $url = '/api/payment/v1/user/white/check';
        return $this->http($url, $data, 'POST');
    }

    /**
     * 实名认证
     * @param $data
     * @return bool|mixed|string
     */
    public function verifyIdCard($data)
    {
        $url = '/authentication/verify-id';
        return $this->http($url, $data, 'POST');
    }

    /**
     * 银行卡三要素验证
     * @param $data
     * @return bool|mixed|string
     */
    public function verifyBankThree($data)
    {
        $url = '/authentication/verify-bankcard-three-factor';
        return $this->http($url, $data, 'POST');
    }

    /**
     * 银行卡四要素验证
     * @param $data
     * @return bool|mixed|string
     */
    public function verifyBankFour($data)
    {
        $url = '/authentication/verify-bankcard-four-factor';
        return $this->http($url, $data, 'POST');
    }

    /**
     * 银行卡四要素-请求鉴权
     * @param $data
     * @return bool|mixed|string
     */
    public function verifyBankFourRequest($data)
    {
        $url = '/authentication/verify-request';
        return $this->http($url, $data, 'POST');
    }

    /**
     * 银行卡四要素-确认鉴权
     * @param $data
     * @return bool|mixed|string
     */
    public function verifyBankFourConfirm($data)
    {
        $url = '/authentication/verify-confirm';
        return $this->http($url, $data, 'POST');
    }

    /**
     *  查询商户充值记录
     */
    public function rechargeRecord($data)
    {
        $url = '/api/dataservice/v2/recharge-record';
        return $this->http($url, $data, 'GET');
    }

    /**
     * 查询日订单文件
     * @param $data
     * @return bool|mixed|string
     */
    public function orderDownloadUrl($data)
    {
        $url = '/api/dataservice/v1/order/downloadurl';
        return $this->http($url, $data, 'GET');
    }

    /**
     * 查询日流⽔文件
     * @param $data
     * @return bool|mixed|string
     */
    public function billDownloadUrl($data)
    {
        $url = '/api/dataservice/v1/order/downloadurl';
        return $this->http($url, $data, 'GET');
    }

    /**
     * 查询商户已开具发票金额和待开具发票金额
     * @return bool|mixed|string
     */
    public function invoiceStat($data)
    {
        $url = '/api/payment/v1/invoice-stat';
        return $this->http($url, $data, 'GET');
    }

    /**
     * 签名工具
     * @return SignUtil
     */
    public function signUtil()
    {
        return new SignUtil($this->appKey);
    }

    /**
     * 3des加密工具
     * @return DesUtil
     */
    public function desUtil()
    {
        return new DesUtil($this->des3Key);
    }

    /**
     * 云账户请求
     * @param $url
     * @param $data
     * @param string $method
     * @return bool|mixed|string
     */
    public function http($url, $data, $method = 'GET')
    {
        $http = new Client($this->host, $this->dealerId, $this->appKey, $this->des3Key);
        // 创建加密数据
        $param = [
            'data' => $this->desUtil()->encrypt(json_encode($data)),
            'mess' => mt_rand(1000, 9999),
            'timestamp' => time(),
        ];
        $param['sign'] = $this->signUtil()->sign($param['data'], $param['mess'], $param['timestamp']);
        $param['sign_type'] = 'sha256';
        $requestId = uniqid(date('ymd'), false);
        $res = $http->request($requestId, $url, $param, $method);
        if ($res) {
            $res = json_decode($res, true);
            if ($res) {
                return $res;
            }
            return [
                'code' => 99,
                'message' => '解析响应数据失败',
            ];
        }
        return [
            'code' => 99,
            'message' => '不存在响应数据',
        ];
    }
}