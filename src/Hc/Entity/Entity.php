<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2020/3/3
 * Time: 15:27
 */

namespace LazyBench\Tax\Hc\Entity;

abstract class Entity implements EntityInterface
{

    public function __construct($param)
    {
        $class = get_class($this);
        $vars = get_object_vars($this);
        foreach ($vars as $key => $value) {
            isset($param[$key]) && !in_array($param[$key], [null, false, ''], true) && $this->{$key} = $param[$key];
        }
        if (!$this->validateEmpty()) {
            throw new \Exception($class.':参数不能为空');
        }
    }


    public function toArray(): array
    {
        $vars = get_object_vars($this);
        foreach ($vars as $key => $var) {
            if ($var === null) {
                unset($vars[$key]);
            }
        }
        return $vars;
    }
}
