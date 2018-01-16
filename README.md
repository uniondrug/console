# UniondrugConsole 命令行工具

## 安装

```
composer requre uniondrug/console
```

## 使用
```php
$ php console
   __  __      _             ____                  
  / / / /___  (_)___  ____  / __ \_______  ______ _
 / / / / __ \/ / __ \/ __ \/ / / / ___/ / / / __ `/
/ /_/ / / / / / /_/ / / / / /_/ / /  / /_/ / /_/ / 
\____/_/ /_/_/\____/_/ /_/_____/_/   \__,_/\__, /  
                                          /____/   Console 1.0.0

Usage:
  command [options] [arguments]

Options:
  -h, --help            Display this help message
  -q, --quiet           Do not output any message
  -V, --version         Display this application version
      --ansi            Force ANSI output
      --no-ansi         Disable ANSI output
  -n, --no-interaction  Do not ask any interactive question
  -e, --env[=ENV]       The environment the command should run under. [default: "development"]
  -v|vv|vvv, --verbose  Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug

Available commands:
  config      列出所有配置文件
  help        Displays help for a command
  list        Lists commands

```

## 命令

### 默认命令
`config` 列出所有配置文件信息。使用如下：
```php
$ php console config -e production
+-----------------------------------+----------------------+
| Key                               | Value                |
+-----------------------------------+----------------------+
| key.k1.k2                         | value                |
+-----------------------------------+----------------------+
```

### 自定义命令

在项目`app\Commands`目录下，创建命令，默认命名空间是`App\Commands`，命令类必须以`Command`结尾，继承`UniondrugConsole\Command`.

比如：
```php
namespace App\Commands;

use UniondrugConsole\Command;

class LocalCommand extends Command
{
    public function configure()
    {
        $this->setName('local:name');
    }

    public function handle()
    {
        $this->line("Hello world");
    }
}
``` 