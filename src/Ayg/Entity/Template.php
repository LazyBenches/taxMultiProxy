<?php
namespace LazyBench\TaxMultiProxy\Ayg\Entity;

use LazyBench\TaxMultiProxy\Ayg\Traits\RequestTrait;

class Template
{
    use RequestTrait;
    public $templateId;
    public $extraSystemId;
    /**
     * 7.10 下载合同模版文件
     *
     * @param string $localFileName 本地文件路径
     * @return string
     */
    public function download($localFileName = null)
    {
        $path = '/econtract/extr/template/download';
        $params = [
            'extrSystemId' => $this->extraSystemId,
            'templateId'   => $this->templateId,
        ];
        $url = $this->client->buildUrl($path) . '?' . http_build_query($params);
        $fileData = file_get_contents($url);
        if ($localFileName && $fileData) {
            file_put_contents($localFileName, $fileData);
        }
        return $fileData;
    }
}