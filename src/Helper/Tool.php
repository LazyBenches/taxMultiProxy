<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2020/11/19
 * Time: 18:29
 */

namespace LazyBench\TaxMultiProxy\Helper;


class Tool
{
    /**
     * Author:LazyBench
     *
     * @param int $length
     * @return string
     * @throws \Exception
     */
    public static function getRandomString($length = 4): string
    {
        $str = '';
        $chars = 'qQwWeErRtTyYuUiIoOpPaAsSfFgGhHjJkKlLzZxXcCvVbBnNmM0123456789';
        $len = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, $len)];
        }
        return $str;
    }
}