<?php

namespace LazyBench\Tax\Ayg\Entity;

use LazyBench\Tax\Ayg\Traits\RequestTrait;

class Identity
{
    use RequestTrait;
    public $identity;
    public $name;
    public $identityType = '0';
    public $notifyUrl;
    public $frontFile;
    public $backFile;

    /**
     * Author:LazyBench
     * 7.2 上传身份证正反面(上传文件-同步接口)
     * @return array
     */
    public function upload()
    {
        $path = '/econtract/extr/identity/upload';
        $queryParams = [
            'name' => $this->name,
            'identity' => $this->identity,
            'identityType' => $this->identityType,
        ];
        $formData = [
            'frontfile' => $this->frontFile,
            'backfile' => $this->backFile,
        ];

        return [
            'path' => $path,
            'queryParams' => $queryParams,
            'formData' => $formData,
        ];
//        $this->client->setHeaders(['multipart/form-data']);
//        return $this->client->multipartPost($path, $queryParams, $formData);
    }

    /**
     * Author:LazyBench
     * 7.9 上传身份证正反面(上传文件-异步接口)
     * @return array
     */
    public function asyncUpload()
    {
        $path = '/econtract/extr/identity/asyn/upload';
        $queryParams = [
            'name' => $this->name,
            'identity' => $this->identity,
            'identityType' => $this->identityType,
            'notifyUrl' => $this->notifyUrl,
        ];
        $formData = [
            'frontfile' => $this->frontFile,
            'backfile' => $this->backFile,
        ];
        return [
            'path' => $path,
            'queryParams' => $queryParams,
            'formData' => $formData,
        ];
//        $this->client->setHeaders(['multipart/form-data']);
//        return $this->client->multipartPost($path, $queryParams, $formData);
    }
}