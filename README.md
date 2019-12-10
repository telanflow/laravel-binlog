# Laravel-Binlog
![php-badge](https://img.shields.io/badge/php-%3E%3D%207.2-8892BF.svg)
[![License](https://poser.pugx.org/telanflow/laravel-binlog/license)](https://packagist.org/packages/telanflow/laravel-binlog)

该扩展实现了 mysql replication protocol。

可用于实时监听mysql数据变更、数据同步等场景

# Runtime

| PHP     | Laravel | Mysql | Swoole  |
|:-------:|:-------:|:-----:|:-------:|
| >= 7.2   | >=5.5   | 5.5/5.6/5.7  | >=4.2 |

# Install

```
composer require telanflow/laravel-binlog
```

# Publish

默认设置在 config/binlog.php 中。将此文件复制到您自己的配置目录以修改值。

你可以使用这个命令发布配置:
```
php artisan vendor:publish --provider="Telanflow\Binlog\LaravelServiceProvider"
```

# Documentation
Please see [Wiki](https://github.com/telanflow/laravel-binlog/wiki)

# Usage

```
php artisan mysql:binlog [start|stop|restart|infos|clean]
```

# 鸣谢

[php-mysql-replication](https://github.com/krowinski/php-mysql-replication)

[laravel-swoole](https://github.com/swooletw/laravel-swoole)

# License
The Laravel-Binlog package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).