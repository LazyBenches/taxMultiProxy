<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2020/5/6
 * Time: 15:45
 *
 *
 * 说明：员工数据一旦推送并验证通过后，不允许再对真实姓名和身份证做编辑操作。UPDATE操作时，员工编号必传，身份证和真实姓名不能编辑，其它字段不为空，则会进行更新。
 *
 *
 */

namespace LazyBench\TaxMultiProxy\App\Hc\Entity\Employee;


use LazyBench\TaxMultiProxy\App\Hc\Entity\Entity;

class  EmployeeInfo extends Entity
{
    public const path = '/openApi/enterprise/employee/employeeInfoPush';


    protected $employeeCode;//员工编号，唯一
    protected $employeeName;//员工真实姓名
    protected $mobile;//员工手机号，必填 ★
    protected $idCard;//身份证号，唯一。★

    protected $operationType = 'CREATE';//操作类型(CREATE/UPDATE)
    protected $status = true;//状态：true启用/不启用
    protected $acntNo;//银行收款账号，银企直连模式，必传 ★ 收款卡号，必传
    protected $acntName;//收款人姓名，银企直连模式，必传需要和员工姓名相同，必传
    protected $bankName;//收款银行，银企直连模式，必传
    protected $bankCode;//收款银行，银企直连模式，必传
    protected $alipayAccount;//支付宝收款账号，代发薪模式，支付通道为支付宝时，必填。★
    protected $weChatAccount;//微信收款账号，代发薪模式，支付通道为微信时必填。★

    public function validateEmpty()
    {
        return $this->employeeCode && $this->employeeName && $this->mobile && $this->idCard;
    }
}