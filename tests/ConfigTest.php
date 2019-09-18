<?php
/**
 * Created by PhpStorm.
 * User: xiehuanjin
 * Date: 2019/1/23
 * Time: 13:50
 */

namespace twinkle\apollo;

use Exception;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{

    /**
     * @var Config
     */
    protected $config;

    protected function setUp()
    {
        $configDir = CONF_DIR;
        $namespace = CONF_NAMESPACE;
        $this->config = (new Config($configDir))->setNamespace($namespace);
        parent::setUp();
    }

    public function testGetConfigFile()
    {
        $file = $this->config->getConfigFile();
        $this->assertTrue(file_exists($file));
    }

    public function testGet()
    {
        $config = $this->config;
        $this->assertEquals($config['key2'], 'test2');
    }

    public function testOffsetExists()
    {
        $config = $this->config;
        $this->assertTrue(isset($config['key1']));
    }

    public function testOffsetGet()
    {
        $config = $this->config;
        $this->assertEquals($config['key1'], 'test1');
    }

    public function testOffsetSet() {
        $config = $this->config;
        $this->expectException(Exception::class);
        $config['test'] = 11;
    }

    public function testOffsetUnset() {
        $config = $this->config;
        $this->expectException(Exception::class);
        $config['test'] = 11;
    }
}