<?php
/**
 * 命令行工具应用的容器。
 *
 * @author XueronNi <xueronni@uniondrug.cn>
 * @date   2018-01-16
 *
 */

namespace Uniondrug\Console;

use Exception;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Uniondrug\Console\Commands\ConfigCommand;
use Uniondrug\Console\Commands\MakeCommandCommand;
use Uniondrug\Framework\Container;

class Console extends SymfonyApplication
{
    /**
     * @var \Uniondrug\Framework\Container
     */
    protected $app;

    public function __construct(Container $app)
    {
        $this->app = $app;
        $version = Container::VERSION;
        $name = <<<LOGO

   __  __      _             ____                  
  / / / /___  (_)___  ____  / __ \_______  ______ _
 / / / / __ \/ / __ \/ __ \/ / / / ___/ / / / __ `/
/ /_/ / / / / / /_/ / / / / /_/ / /  / /_/ / /_/ / 
\____/_/ /_/_/\____/_/ /_/_____/_/   \__,_/\__, /  
                                          /____/   Console
LOGO;
        parent::__construct($name, $version);

        restore_exception_handler();

        $this->registerCommands();
    }

    public function registerCommands()
    {
        // 本项目自带的命令行工具
        $this->addCommands([
            new ConfigCommand(),
            new MakeCommandCommand(),
        ]);

        // 通过配置文件定义的命令，可以将其他模块提供的命令添加进来
        $commands = $this->app->getConfig()->path('commands', []);
        $commands = array_unique($commands);
        foreach ($commands as $command) {
            $this->add(new $command());
        }

        // 项目默认默认目录里面定义的工具
        if (false !== ($files = glob($this->app->appPath() . '/Commands/*Command.php', GLOB_NOSORT | GLOB_NOESCAPE))) {
            foreach ($files as $file) {
                $command = '\\App\\Commands\\' . pathinfo($file, PATHINFO_FILENAME);
                $this->add(new $command());
            }
        }
    }

    /**
     * Get the default input definitions for the applications.
     *
     * This is used to add the --env option to every available command.
     *
     * @return \Symfony\Component\Console\Input\InputDefinition
     */
    protected function getDefaultInputDefinition()
    {
        $definition = parent::getDefaultInputDefinition();

        $definition->addOption($this->getEnvironmentOption());

        return $definition;
    }

    /**
     * Get the global environment option for the definition.
     *
     * @return \Symfony\Component\Console\Input\InputOption
     */
    protected function getEnvironmentOption()
    {
        $message = 'The environment the command should run under.';

        return new InputOption('--env', '-e', InputOption::VALUE_OPTIONAL, $message, 'development');
    }

    /**
     * 重写doRun方法，出错时，日志记录
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws \Throwable
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        try {
            return parent::doRun($input, $output);
        } catch (\Exception $exception) {
            $this->handleException($exception);
        }

        throw $exception;
    }

    /**
     * @param $e
     */
    public function handleException($e)
    {
        if (!$e instanceof Exception) {
            $e = new FatalThrowableError($e);
        }

        try {
            $trace = call_user_func($this->app->getConfig()->path('exception.log'), $e);
        } catch (Exception $exception) {
            $trace = [
                'original' => explode("\n", $e->getTraceAsString()),
                'handler'  => explode("\n", $exception->getTraceAsString()),
            ];
        }

        $this->app->getLogger('console')->error($e->getMessage(), $trace);
    }
}
