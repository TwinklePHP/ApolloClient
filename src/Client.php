<?php

namespace twinkle\apollo;

use Exception;

/**
 * apollo 客户端
 * 官方文档 https://github.com/ctripcorp/apollo/wiki/%E5%85%B6%E5%AE%83%E8%AF%AD%E8%A8%80%E5%AE%A2%E6%88%B7%E7%AB%AF%E6%8E%A5%E5%85%A5%E6%8C%87%E5%8D%97
 *
 * Class Client
 * @package twinkle\apollo
 */
class Client
{

    protected $configServer; //apollo服务端地址
    protected $appId; //apollo配置项目的appid
    protected $cluster = 'default';
    protected $clientIp = '127.0.0.1'; //绑定IP做灰度发布用

    protected $notifications = [];
    protected $pullTimeout = 10; //获取某个namespace配置的请求超时时间
    protected $intervalTimeout = 90; //每次请求获取apollo配置变更时的超时时间,建议大于60s
    protected $saveDir; //配置保存目录

    /**
     * @var mixed 符合psr3规范的日志实例
     */
    protected $logger = null;

    public function __construct($configServer, $appId, array $namespaces)
    {
        $this->configServer = $configServer;
        $this->appId = $appId;
        foreach ($namespaces as $namespace) {
            $this->notifications[$namespace] = ['namespaceName' => $namespace, 'notificationId' => -1];
        }
        $this->saveDir = dirname($_SERVER['SCRIPT_FILENAME']);
    }

    public function setCluster($cluster)
    {
        $this->cluster = $cluster;
    }

    public function setClientIp($ip)
    {
        $this->clientIp = $ip;
    }

    public function setPullTimeout($pullTimeout)
    {
        $pullTimeout = intval($pullTimeout);
        if ($pullTimeout < 1 || $pullTimeout > 300) {
            return;
        }
        $this->pullTimeout = $pullTimeout;
    }

    public function setIntervalTimeout($intervalTimeout)
    {
        $intervalTimeout = intval($intervalTimeout);
        if ($intervalTimeout < 1 || $intervalTimeout > 300) {
            return;
        }
        $this->intervalTimeout = $intervalTimeout;
    }

    public function log($level, $msg, $context = [])
    {
        if (null == $this->logger) {
            return false;
        }

        $this->logger->{$level}($msg, $context);
    }

    /**
     * @param $dirname
     * @throws Exception
     */
    public function setSaveDir($dirname)
    {
        if (!is_dir($dirname) && !mkdir($dirname, 0666, true)) {
            throw new Exception('目录不存在,并且创建失败');
        }

        $this->saveDir = $dirname;
    }

    protected function arr2ini($configs)
    {
        $content = "";

        foreach ($configs as $key => $value) {
            $content .= $key . "=" . $value . PHP_EOL;
        }

        return $content;
    }

    /**
     * 无缓存的方式获取多个namespace的配置
     * @param array $namespaceNames
     * @return array
     * @throws Exception
     */
    public function pullConfigBatch(array $namespaceNames)
    {
        if (!$namespaceNames) return [];

        $multiCh = curl_multi_init();
        $requestList = [];
        $url = rtrim($this->configServer, '/') . '/configs/' . $this->appId . '/' . $this->cluster . '/';
        $params = [];
        if ($this->clientIp) {
            $params['ip'] = $this->clientIp;
        }

        foreach ($namespaceNames as $namespaceName) {
            $request = [];

            $config = (new Config($this->saveDir))->setNamespace($namespaceName);

            $requestUrl = $url . $namespaceName;
            $params['releaseKey'] = $config->get('apolloReleaseKey', '');
            $queryString = '?' . http_build_query($params);
            $requestUrl .= $queryString;
            $ch = curl_init($requestUrl);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->pullTimeout);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $request['ch'] = $ch;
            $request['config_file'] = $config->getConfigFile();

            $requestList[$namespaceName] = $request;
            curl_multi_add_handle($multiCh, $ch);
            unset($config);
        }

        $active = null;
        // 执行批处理句柄
        do {
            $mrc = curl_multi_exec($multiCh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        while ($active && $mrc == CURLM_OK) {
            if (curl_multi_select($multiCh) == -1) {
                usleep(100);
            }
            do {
                $mrc = curl_multi_exec($multiCh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);

        }

        // 获取结果
        $responseList = [];
        foreach ($requestList as $namespaceName => $req) {
            $responseList[$namespaceName] = true;
            $result = curl_multi_getcontent($req['ch']);
            $code = curl_getinfo($req['ch'], CURLINFO_HTTP_CODE);
            $error = curl_error($req['ch']);
            curl_multi_remove_handle($multiCh, $req['ch']);
            curl_close($req['ch']);
            if ($code == 200) {
                $result = json_decode($result, true);
                $configurations = isset($result['configurations']) ? $result['configurations'] : [];
                if (isset($result['releaseKey'])) {
                    $configurations['apolloReleaseKey'] = $result['releaseKey'];
                }
                $content = $this->arr2ini($configurations);
                file_put_contents($req['config_file'], $content);
                $this->log('info', "[{$namespaceName}] 配置更新成功");
            } elseif ($code != 304) {
                $this->log('error', 'pull config of namespace[' . $namespaceName . '] error:' . ($result ?: $error));
                $responseList[$namespaceName] = false;
            }
        }
        curl_multi_close($multiCh);
        return $responseList;
    }

    /**
     * @param $ch
     * @param null $callback
     * @throws Exception
     */
    protected function listenChange(&$ch, $callback = null)
    {
        $url = rtrim($this->configServer, '/') . '/notifications/v2?';
        $params = [];
        $params['appId'] = $this->appId;
        $params['cluster'] = $this->cluster;
        $params['notifications'] = json_encode(array_values($this->notifications));
        $query = http_build_query($params);
        do {
            curl_setopt($ch, CURLOPT_URL, $url . $query);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            if ($httpCode == 200) {
                $res = json_decode($response, true);
                $changeList = [];
                foreach ($res as $r) {
                    if ($r['notificationId'] != $this->notifications[$r['namespaceName']]['notificationId']) {
                        $changeList[$r['namespaceName']] = $r['notificationId'];
                    }
                }
                $this->log('info', "获取到配置变更", $changeList);
                $responseList = $this->pullConfigBatch(array_keys($changeList));
                foreach ($responseList as $namespaceName => $result) {
                    $result && ($this->notifications[$namespaceName]['notificationId'] = $responseList[$namespaceName]);
                }
                //如果定义了配置变更的回调，比如重新整合配置，则执行回调
                ($callback instanceof \Closure) && call_user_func($callback);
            } elseif ($httpCode != 304) {
                $this->log('warning', "[response] {$response},[error] {$error}");
                throw new Exception($response ?: $error);
            } else {
                $this->log('info', '配置暂无变更');
            }
        } while (true);
    }

    public function start($callback = null)
    {
        $this->log('info', '客户端开始启动');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->intervalTimeout);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        try {
            $this->listenChange($ch, $callback);
        } catch (Exception $e) {
            curl_close($ch);
            $msg = $e->getMessage();
            return $msg;
        }
    }
}