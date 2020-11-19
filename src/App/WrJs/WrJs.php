<?php
/**
 * Author：Lzxyz
 * Date：2019/12/9 9:21
 * Desc：Wrjs.php
 */

namespace LazyBench\TaxMultiProxy\App\WrJs;

use LazyBench\TaxMultiProxy\App\WrJs\Http\Client;
use LazyBench\TaxMultiProxy\App\WrJs\Util\Sha1WithRSA;
use LazyBench\TaxMultiProxy\Helper\Tool;

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
     * Author:LazyBench
     * 查询落地公司余额
     * @param $landingCompanyId
     * @return array
     * @throws \Exception
     */
    public function balance($landingCompanyId)
    {
        $url = '/openApi/querMoneyInLandingCompany';
        $data['landingCompanyId'] = (int)$landingCompanyId;
        return $this->http($url, $data, 'POST');
    }

    /**
     * Author:LazyBench
     * 落地公司
     * @return array
     * @throws \Exception
     */
    public function company()
    {
        $url = '/openApi/queryLandingCompanysOpen';
        return $this->http($url, [], 'POST');
    }

    /**
     * Author:LazyBench
     * 提交单人发放数据
     * @param $data
     * @return array
     * @throws \Exception
     */
    public function orderApply($data)
    {

        $url = '/openApi/paySalary';
        $data['randomCode'] = Tool::getRandomString(6);
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
     * @return array
     * @throws \Exception
     */
    public function orderQuery($orderId, $payId, $orderNo)
    {
        $url = '/openApi/queryPayDetail';
        $data['orderId'] = $orderId;
        $data['payId'] = $payId;
        $data['clientOrderId'] = $orderNo;
        return $this->http($url, $data, 'POST');
    }

    /**
     * Author:LazyBench
     *
     * @param $clientOrderId
     * @return array
     * @throws \Exception
     */
    public function orderQueryByClientOrderId($clientOrderId)
    {
        $url = '/openApi/queryPayDetailByClientOrderId';
        $data['clientOrderId'] = $clientOrderId;
        return $this->http($url, $data, 'POST');
    }

    /**
     * Author:LazyBench
     * 订单回调通知验证
     * @return bool
     */
    public function orderCallbackVerify()
    {
        return true;
    }

    /**
     * Author:LazyBench
     * 签约申请(一)
     * @param $realName
     * @param $idCard
     * @param $mobile
     * @return array
     * @throws \Exception
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
     * Author:LazyBench
     * 签约文件上传(二)
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
        if ($output && $output['Status'] === 'OK') {
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
     * Author:LazyBench
     * 签约验证图片(三)
     * @param $data
     * @return array
     * @throws \Exception
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
     * Author:LazyBench
     * 签约完成(四)
     * @param $data
     * @return array
     * @throws \Exception
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
     * Author:LazyBench
     * 签约查询
     * @param $signId
     * @return array
     * @throws \Exception
     */
    public function signQuery($signId)
    {
        $url = '/openApi/new/queryStatusBySignId';
        $data['signId'] = $signId;
        return $this->http($url, $data, 'POST');
    }

    /**
     * Author:LazyBench
     * 签约回调通知验证
     * @param $data
     * @return bool
     */
    public function signCallbackVerify($data)
    {
        return true;
    }

    /**
     * Author:LazyBench
     *
     * @param $url
     * @param $data
     * @param string $method
     * @return array
     * @throws \Exception
     */
    public function http($url, $data, $method = 'GET'): array
    {
        $http = new Client($this->host, $this->appId, $this->appKey);
        if (!$res = $http->request($url, $data, $method)) {
            return [
                'status' => 99,
                'msg' => 'Api暂无响应',
            ];
        }
        $res = json_decode($res, true);
        if (json_last_error()) {
            throw new \Exception(json_last_error(), json_last_error_msg());
        }
        return $res;
    }
}