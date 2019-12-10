<?php

namespace twinkle\apollo;

use Exception;

/**
 * https://github.com/ctripcorp/apollo/wiki/Apollo%E5%BC%80%E6%94%BE%E5%B9%B3%E5%8F%B0
 * Class OpenApi
 * @package twinkle\apollo
 */
class OpenApi
{
    protected $token;
    protected $configServer; //apollo服务端地址
    protected $appId; //apollo配置项目的appid
    protected $env = 'dev';
    protected $clusterName = 'default';

    protected $request;

    public function __construct($token, $configServer, $appId)
    {
        $this->token = $token;
        $this->configServer = $configServer;
        $this->appId = $appId;
        $this->request = new Http(['headers' => [
            'Content-Type' => 'application/json;charset=UTF-8',
            'Authorization' => $this->token,
        ]]);
        $this->request->setBaseUri("{$this->configServer}/openapi/v1/");
    }

    /**
     * @param $env
     * @return $this
     */
    public function setEnv($env)
    {
        $this->env = $env;
        return $this;
    }

    /**
     * @param $clusterName
     * @return $this
     */
    public function setClusterName($clusterName)
    {
        $this->clusterName = $clusterName;
        return $this;
    }

    /**
     * 获取App的环境，集群信息
     * @return array | false
     * @throws Exception
     */
    public function envclusters()
    {
        $data = $this->request->get("apps/{$this->appId}/envclusters");
        return $this->checkData($data);
    }

    /**
     * 获取集群下所有Namespace信息接口
     * @return array | false
     * @throws Exception
     */
    public function getNamespaceList()
    {
        $data = $this->request->get("envs/{$this->env}/apps/{$this->appId}/clusters/{$this->clusterName}/namespaces");
        return $this->checkData($data);
    }

    /**
     * 获取某个Namespace信息接口
     * @param $namespaceName
     * @return array | false
     * @throws Exception
     */
    public function getNamespace($namespaceName) {
        $data = $this->request->get("envs/{$this->env}/apps/{$this->appId}/clusters/{$this->clusterName}/namespaces/{$namespaceName}");
        return $this->checkData($data);
    }

    /**
     * 创建Namespace
     * 调用此接口需要授予第三方APP，APP级别的权限。
     * @param $data
     * @return array | false
     * @throws Exception
     */
    public function createNamespace($data = [])
    {
        /**
         * 参数名    必选    类型    说明
         * name    true    String    Namespace的名字
         * appId    true    String    Namespace所属的AppId
         * format    true    String    Namespace的格式，只能是以下类型： properties、xml、json、yml、yaml
         * isPublic    true    boolean    是否是公共文件
         * comment    false    String    Namespace说明
         * dataChangeCreatedBy    true    String    namespace的创建人，格式为域账号，也就是sso系统的User ID
         */
        if (empty($data['name']) || empty($data['format']) || empty($data['dataChangeCreatedBy'])) {
            throw new Exception('请求不合法，必要参数为空');
        }
        if (!in_array($data['format'], ['properties', 'xml', 'json', 'yml', 'ymal'])) {
            throw new Exception('Namespace的格式不合法，只能是以下类型【properties、xml、json、yml、yaml】');
        }

        $data = $this->request->post("apps/{$this->appId}/appnamespaces", $data);
        return $this->checkData($data);
    }

    /**
     * 获取某个Namespace当前编辑人接口
     * Apollo在生产环境（PRO）有限制规则：每次发布只能有一个人编辑配置，且该次发布的人不能是该次发布的编辑人。
     * 也就是说如果一个用户A修改了某个namespace的配置，那么在这个namespace发布前，只能由A修改，其它用户无法修改。
     * 同时，该用户A无法发布自己修改的配置，必须找另一个有发布权限的人操作。
     * 这个接口就是用来获取当前namespace是否有人锁定的接口。
     * 在非生产环境（FAT、UAT），该接口始终返回没有人锁定
     * @param string $namespaceName
     * @return array | bool
     * @throws Exception
     */
    public function checkNamespaceLock($namespaceName)
    {
        $data = $this->request->get("envs/{$this->env}/apps/{$this->appId}/clusters/{$this->clusterName}/namespaces/{$namespaceName}/lock");
        return $this->checkData($data);
    }

    /**
     * 新增配置接口
     * @param array $data
     * @param string $namespaceName
     * @return bool
     * @throws Exception
     */
    public function addItems($data, $namespaceName)
    {
        if (empty($data['key']) || empty($data['value']) || empty($data['dataChangeCreatedBy'])) {
            throw new Exception('请求不合法，必要参数为空');
        }

        $data = $this->request->post("envs/{$this->env}/apps/{$this->appId}/clusters/{$this->clusterName}/namespaces/{$namespaceName}/items", $data);
        return $this->checkData($data);
    }

    /**
     * 修改配置接口
     * @param $data
     * @param $namespaceName
     * @return bool
     * @throws Exception
     */
    public function updateItems($data, $namespaceName)
    {
        if (empty($data['key']) || empty($data['value']) || empty($data['dataChangeLastModifiedBy'])) {
            throw new Exception('请求不合法，必要参数为空');
        }
        $data = $this->request->request('PUT', "envs/{$this->env}/apps/{$this->appId}/clusters/{$this->clusterName}/namespaces/{$namespaceName}/items/{$data['key']}", $data);
        return $this->checkData($data);
    }

    /**
     * 删除配置接口
     * @param string $key 配置的key。非properties格式，key固定为content
     * @param string $operator
     * @param string $namespaceName
     * @return bool
     * @throws Exception
     */
    public function deleteItems($key,$operator, $namespaceName) {
        $data = $this->request->request('DELETE',"envs/{$this->env}/apps/{$this->appId}/clusters/{$this->clusterName}/namespaces/{$namespaceName}/items/{$key}?operator={$operator}");
        return $this->checkData($data);
    }

    /**
     * 发布配置接口
     * @param array $data
     * @param $namespaceName
     * @return bool
     * @throws Exception
     */
    public function releases($data,$namespaceName) {

        $data = $this->request->post("envs/{$this->env}/apps/{$this->appId}/clusters/{$this->clusterName}/namespaces/{$namespaceName}/releases",$data);
        return $this->checkData($data);
    }


    /**
     * 获取某个Namespace当前生效的已发布配置接口
     * @param string $namespaceName
     * @return array | bool
     * @throws Exception
     */
    public function getLaReleases($namespaceName) {
        $data = $this->request->get("envs/{$this->env}/apps/{$this->appId}/clusters/{$this->clusterName}/namespaces/{$namespaceName}/releases/latest");
        return $this->checkData($data);
    }

    /**
     * @param $data
     * @return bool
     * @throws Exception
     */
    private function checkData($data)
    {
        if (200 <> $data['httpCode']) {
            $msg = $data['error_msg'];
            if (!empty($data['data']) && isset($data['data']['message'])) {
                $msg = $data['data']['message'];
            }
            throw new Exception($msg);
        }

        return json_decode($data['data'], true);
    }
}