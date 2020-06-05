<?php
/**
 * Author：Lzxyz
 * Date：2019/12/9 9:21
 * Desc：Wrjs.php
 */

namespace LazyBench\TaxMultiProxy\App\WrJs;

use LazyBench\TaxMultiProxy\App\WrJs\Http\Client;
use LazyBench\TaxMultiProxy\App\WrJs\Util\Sha1WithRSA;

class WrJs
{
    private $host;
    private $appId;
    private $appKey;
    private $notifyUrl;
    private $rsa;

    public function __construct(array $config)
    {
        $this->host = $config['host'] ?? ''; // 主机地址
        $this->appId = $config['appId'] ?? ''; // 商户数据加密Key
        $this->appKey = $config['appKey'] ?? ''; // 商户签名Key
        $this->notifyUrl = $config['notifyUrl'] ?? ''; // 异步通知地址
        $this->rsa = new Sha1WithRSA($config['privateKey'] ?? '', $config['publicKey'] ?? '');
    }

    /**
     * 查询落地公司余额
     * Author：Lzxyz
     * @param $landingCompanyId
     * @return mixed
     */
    public function balance($landingCompanyId)
    {
        $url = '/openApi/querMoneyInLandingCompany';
        $data['landingCompanyId'] = (int)$landingCompanyId;
        return $this->http($url, $data, 'POST');
    }

    /**
     * 落地公司
     * Author：Lzxyz
     * @return array|bool|mixed|string
     */
    public function company()
    {
        $url = '/openApi/queryLandingCompanysOpen';
        return $this->http($url, [], 'POST');
    }

    /**
     * 提交单人发放数据
     * Author：Lzxyz
     * @param $data
     * @return array|bool|mixed|string
     */
    public function orderApply($data)
    {

        $url = '/openApi/paySalary';
        $data['randomCode'] = get_random_string(6);
        $data['notifyUrl'] = $this->notifyUrl;
        // 签名处理 以下参数必填
        $signParams = [
            'name' => $data['name'] ?? '',
            'identity' => $data['identity'] ?? '',
            'receiveAccount' => $data['receiveAccount'] ?? '',
            'money' => $data['money'] ?? '',
            'telephoneNum' => $data['telephoneNum'] ?? '',
            'landingCompanyId' => $data['landingCompanyId'] ?? '',
            'randomCode' => $data['randomCode'] ?? '',
        ];
        $signStr = urldecode(http_build_query($signParams));
        $data['sign'] = $this->rsa->sign($signStr);
        return $this->http($url, $data, 'POST');

    }


    /**
     * Author:LazyBench
     * 订单查询
     * @param $orderId
     * @param $payId
     * @param $orderNo
     * @return array|bool|mixed|string
     */
    public function orderQuery($orderId, $payId, $orderNo)
    {
        $url = '/openApi/queryPayDetail';
        $data['orderId'] = $orderId;
        $data['payId'] = $payId;
        $data['clientOrderId'] = $orderNo;
        return $this->http($url, $data, 'POST');
    }

    public function orderQueryByClientOrderId($clientOrderId)
    {
        $url = '/openApi/queryPayDetailByClientOrderId';
        $data['clientOrderId'] = $clientOrderId;
        return $this->http($url, $data, 'POST');
    }

    /**
     * 订单回调通知验证
     * Author：Lzxyz
     */
    public function orderCallbackVerify()
    {
        return true;
    }

    /**
     *  签约申请(一)
     * Author：Lzxyz
     */
    public function signApply($realName, $idCard, $mobile)
    {
        $url = '/openApi/new/addESignPerson';
        $data['name'] = $realName;
        $data['identity'] = $idCard;
        $data['phone'] = $mobile;
        return $this->http($url, $data, 'POST');
    }

    /**
     * 签约文件上传(二)
     * Author：Lzxyz
     * @param $data
     * @return array
     */
    public function signUpload($data)
    {
        // 处理图片
        $data['file'] = new \CURLFile($data['file']);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $data['host']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $output = curl_exec($ch);
        curl_close($ch);
        if (!$output) {
            return [
                'status' => 99,
                'msg' => 'OOS暂无响应',
            ];
        }
        $output = json_decode($output, true);
        if ($output && $output['Status'] == 'OK') {
            return [
                'status' => 1,
                'msg' => '',
            ];
        }
        return [
            'status' => 99,
            'msg' => $output['Status'] ?? '',
        ];
    }

    /**
     * 签约验证图片(三)
     * Author：Lzxyz
     * @param $data
     * @return array|bool|mixed|string
     */
    public function signValidate($data)
    {
        $params = [
            'idCardPositive' => $data['cardFront'],
            'idCardNegative' => $data['cardBack'],
            'idCardNo' => $data['idCard'],
            'userName' => $data['realName'],
            'signKey' => $data['signKey'],
        ];
        $url = '/openApi/new/apiValidation';
        return $this->http($url, $params, 'POST');
    }

    /**
     * 签约完成(四)
     * Author：Lzxyz
     * @param $data
     * @return array|bool|mixed|string
     */
    public function signSuccess($data)
    {
        $params = [
            'signKey' => $data['signKey'],
        ];
        $url = '/openApi/new/changeSignSuccess';
        return $this->http($url, $params, 'POST');
    }

    /**
     * 签约查询
     * Author：Lzxyz
     */
    public function signQuery($signId)
    {
        $url = '/openApi/new/queryStatusBySignId';
        $data['signId'] = $signId;
        return $this->http($url, $data, 'POST');
    }

    /**
     * 签约回调通知验证
     * Author：Lzxyz
     */
    public function signCallbackVerify($data)
    {
        return true;
    }

    public function http($url, $data, $method = 'GET')
    {
        $http = new Client($this->host, $this->appId, $this->appKey);
        $res = $http->request($url, $data, $method);
        if ($res) {
            $res = json_decode($res, true);
            if ($res) {
                return $res;
            } else {
                return [
                    'status' => 99,
                    'msg' => 'JSON解析错误',
                ];
            }
        }
        return [
            'status' => 99,
            'msg' => 'Api暂无响应',
        ];
    }
}