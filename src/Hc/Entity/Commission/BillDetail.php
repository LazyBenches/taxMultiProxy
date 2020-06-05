<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2020/5/6
 * Time: 16:32
 */

namespace LazyBench\Tax\Hc\Entity\Commission;

use LazyBench\Tax\Hc\Entity\Entity;

class BillDetail extends Entity
{
    protected $billNo;//账单号
    protected $employeeCode;//员工编号
    protected $tradeNo;//按企业已推送的数据时 交易流水号，推送明细数据时
    protected $billTime;//日结日期
    protected $commissionAmount;//佣金，该账单期间发给该员工的佣金,单位为分
    protected $tax;//个税，单位为分
    protected $remark;//备注

    public function validateEmpty()
    {

    }
}