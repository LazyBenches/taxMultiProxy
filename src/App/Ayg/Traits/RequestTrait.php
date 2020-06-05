<?php

namespace LazyBench\TaxMultiProxy\App\Ayg\Traits;


/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2019/8/12
 * Time: 12:47
 */
trait RequestTrait
{

    public function __construct()
    {
        method_exists($this, 'init') && $this->init();
    }
}