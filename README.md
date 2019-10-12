# Laravel-Binlog
![php-badge](https://img.shields.io/badge/php-%3E%3D%207.2-8892BF.svg)
[![License](https://poser.pugx.org/telanflow/laravel-binlog/license)](https://packagist.org/packages/telanflow/laravel-binlog)

该扩展实现了 mysql replication protocol。

可用于实时监听mysql数据变更、数据同步等场景

# 版本兼容性

| PHP     | Laravel | Mysql | Swoole  |
|:-------:|:-------:|:-----:|:-------:|
| >= 7.2   | >=5.5   | 5.5/5.6/5.7  | >=4.2 |

# 安装

```
composer require telanflow/laravel-binlog
```

# 配置

默认设置在 config/binlog.php 中。将此文件复制到您自己的配置目录以修改值。

你可以使用这个命令发布配置:
```
php artisan vendor:publish --provider="Telanflow\Binlog\LaravelServiceProvider"
```

# Mysql配置

开启mysql binlog支持，并且指定格式为row，如下配置
```
[mysqld]
server-id        = 1
log_bin          = /var/log/mysql/mysql-bin.log
expire_logs_days = 10
max_binlog_size  = 100M
binlog-format    = row #Very important if you want to receive write, update and delete row events
```

# 文档 （Documentation)
Please see [Wiki](https://github.com/telanflow/laravel-binlog/wiki)

# Usage

```
php artisan mysql:binlog [start|stop|restart|infos]
```

# 鸣谢

[php-mysql-replication](https://github.com/krowinski/php-mysql-replication)

[laravel-swoole](https://github.com/swooletw/laravel-swoole)

# License
The Laravel-Swoole package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).