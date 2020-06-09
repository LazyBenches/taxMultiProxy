<?php
/**
 * Created by PhpStorm.
 * Email:jwy226@qq.com
 * User: LazyBench
 * Date: 2020/6/9
 * Time: 11:02
 */

namespace LazyBench\TaxMultiProxy\App\Settlement;


class Rpc
{
    private $host = '';
    private $version = '1.0';
    private $interface = 'App\Rpc\Lib\OrderInterface';

    public function __construct(array $config)
    {
        isset($config['host']) && $this->host = $config['host'];
        isset($config['version']) && $this->version = $config['version'];
        isset($config['interface']) && $this->setInterface($config['interface']);
    }

    /**
     * Author:LazyBench
     *
     * @param string $interface
     * @return Rpc
     */
    public function setInterface(string $interface): self
    {
        $this->interface = $interface;
        return $this;
    }

    /**
     * Author:LazyBench
     * @param string $method
     * @param array $data
     * @return mixed
     * @throws \Exception
     */
    function handle(string $method, array $data): array
    {
        $fp = stream_socket_client($this->host, $errno, $errStr);
        if (!$fp) {
            throw new \Exception("bank stream_socket_client fail errno={$errno} errStr={$errStr}");
        }
        $req = [
            'jsonrpc' => '2.0',
            'method' => sprintf('%s::%s::%s', $this->version, $this->interface, $method),
            'params' => [
                $data,
            ],
            'id' => '',
            'ext' => [],
        ];
        $string = json_encode($req)."\r\n\r\n";
        try {
            fwrite($fp, $string);
            $result = '';
            while (!feof($fp)) {
                $tmp = stream_socket_recvfrom($fp, 1024);
                if ($pos = strpos($tmp, "\r\n\r\n")) {
                    $result .= substr($tmp, 0, $pos);
                    break;
                }
                $result .= $tmp;
            }
            fclose($fp);
            $response = json_decode($result, true);
            return $response['result'] ?? $response;
        } catch (\Exception $e) {
            return [
                'code' => '9999',
                'data' => [],
                'msg' => $e->getMessage(),
            ];
        }
    }
}