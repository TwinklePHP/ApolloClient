<?php

namespace twinkle\apollo;


use Exception;

class Config implements \ArrayAccess,\Iterator
{
    protected $appId;

    protected $configDir = '';

    protected $configFile = '';

    /**
     * @var
     */
    protected $namespace = 'application';

    protected $values = [];

    protected $loadConfig = false;

    public function __construct($configDir, $appId = 'apolloConfig', $namespace = 'application')
    {
        $this->configDir = $configDir;
        $this->appId = $appId;
        $this->setNamespace($namespace);
    }

    /**
     * @param mixed $namespace
     * @return $this
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * @return $this
     */
    protected function loadConfig()
    {
        if (!$this->loadConfig) {
            $this->getConfigFile();
            if (file_exists($this->configFile)) {
                $this->values = parse_ini_file($this->configFile);
            }
            $this->loadConfig = true;
        }
        return $this;
    }

    public function getConfigFile()
    {
        $this->configFile = $this->configDir . DIRECTORY_SEPARATOR . "{$this->appId}.{$this->namespace}.ini";
        return $this->configFile;
    }

    /**
     * 获取配置
     *
     * @param $key
     * @param null $default
     * @return string | null
     * @throws Exception
     */
    public function get($key, $default = null)
    {
        $this->loadConfig();
        return isset($this->values[$key]) ? $this->values[$key] : $default;
    }

    public function offsetExists($offset)
    {
        $this->loadConfig();
        return isset($this->values[$offset]);
    }

    public function offsetGet($offset)
    {
        $this->loadConfig();
        return $this->get($offset);
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     * @throws Exception
     */
    public function offsetSet($offset, $value)
    {
        throw new Exception('不允许变更');
    }

    /**
     * @param mixed $offset
     * @throws Exception
     */
    public function offsetUnset($offset)
    {
        throw new Exception('不允许变更');
    }

    public function current()
    {
        $this->loadConfig();
        return current($this->values);
    }

    public function next()
    {
        $this->loadConfig();
        return next($this->values);
    }

    public function key()
    {
        $this->loadConfig();
        return key($this->values);
    }

    public function valid()
    {
        $this->loadConfig();
        return key($this->values) !== null;
    }

    public function rewind()
    {
        $this->loadConfig();
        return reset($this->values);
    }
}