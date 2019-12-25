# ApolloClient
apollo 客户端，使用 ini 文件保存配置，并提供一个获取配置的功能

[![Build Status](https://www.travis-ci.org/TwinklePHP/ApolloClient.svg?branch=master)](https://www.travis-ci.org/TwinklePHP/ApolloClient)  

## 安装

composer
```
composer require twinkle/apollo-client --prefer-dist
```

如果只是作为客户端，也可以直接下载
```
git clone --branch ${latest tag} https://github.com/TwinklePHP/ApolloClient.git
```

## 客户端
```shell script
vendor/bin/apollo --application=appId --namespace=application

或

/usr/bin/php ${DIR}/vendor/twinkle/apollo-client/bin/apollo --application=appId --namespace=application

```

## 获取配置

```php
$config = new \twinkle\apollo\Config($configDir, $appName, $application);
$dbHost = $config['DB_HOST'];
```