<?php
/**
 * Author：Lzxyz
 * Date：2019/8/13 9:24
 * Desc：Yzh.php
 * 云账户
 */

namespace LazyBench\TaxMultiProxy\App\Yzh;

use LazyBench\TaxMultiProxy\App\Yzh\Http\Client;
use LazyBench\TaxMultiProxy\App\Yzh\Util\DesUtil;
use LazyBench\TaxMultiProxy\App\Yzh\Util\SignUtil;

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
     * Author:LazyBench
     *
     * @param $data
     * @return array|bool|mixed|string
     * @throws \Exception
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
     * Author:LazyBench
     *
     * @return array|bool|mixed|string
     * @throws \Exception
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
     * Author:LazyBench
     *
     * @return array|bool|mixed|string
     * @throws \Exception
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
     * Author:LazyBench
     *
     * @param $data
     * @return array|bool|mixed|string
     * @throws \Exception
     */
    public function queryRealtimeOrder($data)
    {
        $data['channel'] = $data['dealer_id'] ?? '银行卡';
        $data['data_type'] = $data['data_type'] ?? '';
        $url = '/api/payment/v1/query-realtime-order';
        return $this->http($url, $data, 'GET');
    }

    /**
     * Author:LazyBench
     *
     * @return array|bool|mixed|string
     * @throws \Exception
     */
    public function queryAccounts()
    {
        $url = '/api/payment/v1/query-accounts';
        $data['dealer_id'] = $this->dealerId;
        return $this->http($url, $data, 'GET');
    }

    /**
     * Author:LazyBench
     *
     * @param $data
     * @return array|bool|mixed|string
     * @throws \Exception
     */
    public function receiptFile($data)
    {
        $url = '/api/payment/v1/receipt/file';
        return $this->http($url, $data, 'GET');
    }

    /**
     * Author:LazyBench
     *
     * @return array|bool|mixed|string
     * @throws \Exception
     */
    public function orderFail()
    {
        $url = '/api/payment/v1/receipt/file';
        $data['dealer_id'] = $data['dealer_id'] ?? $this->dealerId;
        return $this->http($url, $data, 'GET');
    }


    /**
     * Author:LazyBench
     *
     * @param $data
     * @return array|bool|mixed|string
     * @throws \Exception
     */
    public function userWhiteCheck($data)
    {
        $url = '/api/payment/v1/user/white/check';
        return $this->http($url, $data, 'POST');
    }

    /**
     * Author:LazyBench
     *
     * @param $data
     * @return array|bool|mixed|string
     * @throws \Exception
     */
    public function verifyIdCard($data)
    {
        $url = '/authentication/verify-id';
        return $this->http($url, $data, 'POST');
    }

    /**
     * Author:LazyBench
     *
     * @param $data
     * @return array|bool|mixed|string
     * @throws \Exception
     */
    public function verifyBankThree($data)
    {
        $url = '/authentication/verify-bankcard-three-factor';
        return $this->http($url, $data, 'POST');
    }

    /**
     * Author:LazyBench
     *
     * @param $data
     * @return array|bool|mixed|string
     * @throws \Exception
     */
    public function verifyBankFour($data)
    {
        $url = '/authentication/verify-bankcard-four-factor';
        return $this->http($url, $data, 'POST');
    }

    /**
     * Author:LazyBench
     *
     * @param $data
     * @return array|bool|mixed|string
     * @throws \Exception
     */
    public function verifyBankFourRequest($data)
    {
        $url = '/authentication/verify-request';
        return $this->http($url, $data, 'POST');
    }

    /**
     * Author:LazyBench
     *
     * @param $data
     * @return array|bool|mixed|string
     * @throws \Exception
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
     * Author:LazyBench
     *
     * @param $data
     * @return array|bool|mixed|string
     * @throws \Exception
     */
    public function orderDownloadUrl($data)
    {
        $url = '/api/dataservice/v1/order/downloadurl';
        return $this->http($url, $data, 'GET');
    }

    /**
     * Author:LazyBench
     *
     * @param $data
     * @return array|bool|mixed|string
     * @throws \Exception
     */
    public function billDownloadUrl($data)
    {
        $url = '/api/dataservice/v1/order/downloadurl';
        return $this->http($url, $data, 'GET');
    }

    /**
     * Author:LazyBench
     *
     * @param $data
     * @return array|bool|mixed|string
     * @throws \Exception
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
     * Author:LazyBench
     * 云账户请求
     * @param $url
     * @param $data
     * @param string $method
     * @return array|bool|mixed|string
     * @throws \Exception
     */
    public function http($url, $data, $method = 'GET')
    {
        $http = new Client($this->host, $this->dealerId);
        // 创建加密数据
        $param = [
            'data' => $this->desUtil()->encrypt(json_encode($data)),
            'mess' => random_int(1000, 9999),
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