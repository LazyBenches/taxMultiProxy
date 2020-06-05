<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2020/5/6
 * Time: 17:39
 */

namespace LazyBench\Tax\Hc\Entity;

interface EntityInterface
{

    public function toArray();

    public function validateEmpty();
}