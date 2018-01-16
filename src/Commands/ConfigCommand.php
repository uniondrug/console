<?php
/**
 * 打印指定环境下的所有配置信息
 *
 * @author XueronNi <xueronni@uniondrug.cn>
 * @data   2018-01-01
 */

namespace UniondrugConsole\Commands;

use Phalcon\Config;
use Phalcon\Text;
use UniondrugConsole\Command;

class ConfigCommand extends Command
{
    public function configure()
    {
        parent::configure();
        $this->setName('config');
        $this->setDescription("列出所有配置文件");
    }

    public function handle()
    {
        $config = $this->getConfig();
        $header = ['Key', 'Value'];
        $values = $this->dump("", $config);
        $rows = [];
        foreach ($values as $k => $v) {
            $rows[] = [$k, $v];
        }
        $this->table($header, $rows);
    }

    /**
     * 加载配置文件
     *
     * @return \Phalcon\Config
     */
    public function getConfig()
    {
        $env = $this->di->environment();
        $config = new Config([]);
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->di->configPath()), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $item) {
            if (Text::endsWith($item, '.php', false)) {
                $name = str_replace([$this->di->configPath() . DIRECTORY_SEPARATOR, '.php'], '', $item);
                $data = include $item;

                // 默认配置组
                if (is_array($data) && isset($data['default']) && is_array($data['default'])) {
                    $config[$name] = $data['default'];
                }

                // 非空初始化
                if (!isset($config[$name])) {
                    $config[$name] = [];
                }

                // 指定环境的配置组，覆盖默认配置
                if (is_array($data) && isset($data[$env]) && is_array($data[$env])) {
                    $config->merge(new Config([$name => $data[$env]]));
                }
            }
        }

        return $config;
    }

    public function dump($key, $value)
    {
        $kv = [];
        foreach ($value as $k => $v) {
            if (!empty($key)) {
                $newKey = "$key.$k";
            } else {
                $newKey = "$k";
            }
            if ($v instanceof Config) {
                $kv = array_merge($kv, $this->dump($newKey, $v));
            } else {
                if (is_array($v)) {
                    $kv[$newKey] = implode("\n", $v);
                } elseif ($v instanceof \Closure) {
                    $kv[$newKey] = "Closure";
                } else {
                    $kv[$newKey] = is_bool($v) ? ($v ? 'true' : 'false') : $v;
                }
            }
        }
        ksort($kv);

        return $kv;
    }
}
