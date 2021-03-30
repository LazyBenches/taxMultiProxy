<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2021/3/30
 * Time: 14:47
 */
include '../vendor/autoload.php';
$yqd = new \LazyBench\TaxMultiProxy\App\Yqd\Yqd([
    'host' => 'http://test.api.yunqiandou.com',
    'no' => '801005481551',
    'key' => 'f64203d69b1547eba87eb1af51112e39',
]);
$user = new \LazyBench\TaxMultiProxy\App\Yqd\Entity\User();
$param = $user->create([
    'realName' => '吴晓云',
    'mobile' => '15983298307',
    'idNumber' => '511025199612108565',
]);
var_dump($yqd->request($param));