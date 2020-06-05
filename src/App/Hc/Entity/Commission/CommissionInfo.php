<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2020/5/6
 * Time: 15:53
 */

namespace LazyBench\TaxMultiProxy\App\Hc\Entity\Commission;

use LazyBench\TaxMultiProxy\App\Hc\Entity\Entity;

class CommissionInfo extends Entity
{
    public const path = '/openApi/enterprise/commission/commissionInfoPush';


    protected $tradeNo;//交易流水号，64位以内。一般为企业发薪记录编号或订单号，全局唯一
    protected $employeeCode;//员工编号
    protected $dateTime;//企业数据库中记录的交易发生时间yyyy-MM-dd HH:mm:ss
    protected $amount;//佣金金额，单位分
    protected $tradeType;//交易类型(1：经营所得 2：佣金提现 3：企业税前汇款个人,4：推广费 )
    protected $remark;//备注

    public function validateEmpty()
    {

    }
}