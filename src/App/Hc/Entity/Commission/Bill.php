<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2020/5/6
 * Time: 16:16
 */

namespace LazyBench\TaxMultiProxy\App\Hc\Entity\Commission;


use LazyBench\TaxMultiProxy\App\Hc\Entity\Entity;

class Bill extends Entity
{
    protected $billNo;//账单号，唯一
    protected $billStartTime;//账单开始时间
    protected $billEndTime;//账单结束时间
    protected $billMonth;//归属账单月份,格式：yyyyMM
    protected $employeeCount;//总员工数（该账单期间有发薪的员工总数）
    protected $commissionAmount;//总佣金，该账单期间发给员工佣金总和,单位为分
    protected $billType;//账单类型（对应佣金明细中的交易类型 ）1：经营所得 2：佣金提现 3：企业税前汇款个人,4：推广费
    protected $tax;
    protected $serviceFee;
    protected $remark;

    public function validateEmpty()
    {

    }
}