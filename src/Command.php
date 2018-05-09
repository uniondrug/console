<?php
/**
 * Command.php
 *
 */

namespace Uniondrug\Console;

use Phalcon\Di\InjectionAwareInterface;
use Phalcon\DiInterface;
use Phalcon\Events\EventsAwareInterface;
use Phalcon\Events\ManagerInterface;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Uniondrug\Framework\Container;
use Uniondrug\Framework\Services\ServiceTrait;

/**
 * Class Command
 *
 * @property \Phalcon\Filter|\Phalcon\FilterInterface                                               $filter
 * @property \Phalcon\Events\Manager|\Phalcon\Events\ManagerInterface                               $eventsManager
 * @property \Phalcon\Db\AdapterInterface                                                           $db
 * @property \Phalcon\Security                                                                      $security
 * @property \Phalcon\Crypt|\Phalcon\CryptInterface                                                 $crypt
 * @property \Phalcon\Escaper|\Phalcon\EscaperInterface                                             $escaper
 * @property \Phalcon\Annotations\Adapter\Memory|\Phalcon\Annotations\Adapter                       $annotations
 * @property \Phalcon\Mvc\Model\Manager|\Phalcon\Mvc\Model\ManagerInterface                         $modelsManager
 * @property \Phalcon\Mvc\Model\MetaData\Memory|\Phalcon\Mvc\Model\MetadataInterface                $modelsMetadata
 * @property \Phalcon\Mvc\Model\Transaction\Manager|\Phalcon\Mvc\Model\Transaction\ManagerInterface $transactionManager
 * @property \Phalcon\Di|\Phalcon\DiInterface                                                       $di
 *
 */
abstract class Command extends SymfonyCommand implements InjectionAwareInterface, EventsAwareInterface
{
    use ServiceTrait;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * The input interface implementation.
     *
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * The output interface implementation.
     *
     * @var OutputInterface
     */
    protected $output;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description;

    /**
     * @var DiInterface
     */
    protected $_dependencyInjector;

    /**
     * @var ManagerInterface
     */
    protected $_eventsManager;

    /**
     * The default verbosity of output commands.
     *
     * @var int
     */
    protected $verbosity = OutputInterface::VERBOSITY_NORMAL;

    /**
     * The mapping between human readable verbosity levels and Symfony's OutputInterface.
     *
     * @var array
     */
    protected $verbosityMap = [
        'v'      => OutputInterface::VERBOSITY_VERBOSE,
        'vv'     => OutputInterface::VERBOSITY_VERY_VERBOSE,
        'vvv'    => OutputInterface::VERBOSITY_DEBUG,
        'quiet'  => OutputInterface::VERBOSITY_QUIET,
        'normal' => OutputInterface::VERBOSITY_NORMAL,
    ];

    /**
     * Create a new console command instance.
     *
     * @param null $name
     */
    public function __construct($name = null)
    {
        // We will go ahead and set the name, description, and parameters on console
        // commands just to make things a little easier on the developer. This is
        // so they don't have to all be manually specified in the constructors.
        if (isset($this->signature)) {
            $this->configureUsingFluentDefinition();
        } else {
            parent::__construct($this->name);
        }
        $this->setDescription($this->description);
        if (!isset($this->signature)) {
            $this->specifyParameters();
        }
    }

    /**
     * @param DiInterface $container
     */
    public function setDI(DiInterface $container)
    {
        $this->_dependencyInjector = $container;
    }

    /**
     * @return DiInterface
     */
    public function getDI()
    {
        if (!is_object($this->_dependencyInjector)) {
            $this->_dependencyInjector = Container::getDefault();
        }

        return $this->_dependencyInjector;
    }

    /**
     * @param ManagerInterface $eventsManager
     */
    public function setEventsManager(ManagerInterface $eventsManager)
    {
        $this->_eventsManager = $eventsManager;
    }

    /**
     * @return ManagerInterface
     */
    public function getEventsManager()
    {
        if (!is_object($this->_eventsManager)) {
            $this->_eventsManager = $this->getDI()->getEventsManager();
        }

        return $this->_eventsManager;
    }

    /**
     * Magic method __get
     *
     * @param $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        $di = $this->_dependencyInjector;
        if (!is_object($di)) {
            $di = Container::getDefault();
            if (!is_object($di)) {
                throw new \RuntimeException('A dependency injection object is required to access the application services');
            }
        }

        if ($di->has($name)) {
            $service = $di->getShared($name);
            $this->$name = $service;

            return $service;
        }

        if ($name == 'di') {
            $this->di = $di;

            return $di;
        }

        if ($name == 'persistent') {
            $persistent = $di->get('sessionBag', [get_class($this)]);
            $this->persistent = $persistent;

            return $persistent;
        }

        trigger_error("Access to undefined property $name");
    }

    /**
     * Configure the console command using a fluent definition.
     */
    protected function configureUsingFluentDefinition()
    {
        list($name, $arguments, $options) = Parser::parse($this->signature);
        parent::__construct($name);
        foreach ($arguments as $argument) {
            $this->getDefinition()->addArgument($argument);
        }
        foreach ($options as $option) {
            $this->getDefinition()->addOption($option);
        }
    }

    /**
     * Specify the arguments and options on the command.
     */
    protected function specifyParameters()
    {
        // We will loop through all of the arguments and options for the command and
        // set them all on the base command instance. This specifies what can get
        // passed into these commands as "parameters" to control the execution.
        foreach ($this->getArguments() as $arguments) {
            call_user_func_array([$this, 'addArgument'], $arguments);
        }
        foreach ($this->getOptions() as $options) {
            call_user_func_array([$this, 'addOption'], $options);
        }
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }

    /**
     * Get the output implementation.
     *
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Execute the console command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return mixed
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;

        $this->output = $output;

        $this->io = new SymfonyStyle($input, $output);

        // set env through argument --env
        if ($input->hasOption('env')) {
            $env = $input->getOption('env');
            putenv("APP_ENV=$env");
        }

        // call method defined in Command.
        return call_user_func_array([$this, 'handle'], []);
    }

    /**
     * Call another console command.
     *
     * @param string $command
     * @param array  $arguments
     *
     * @return int
     * @throws \Exception
     */
    public function call($command, array $arguments = [])
    {
        $instance = $this->getApplication()->find($command);
        $arguments['command'] = $command;

        return $instance->run(new ArrayInput($arguments), $this->output);
    }

    /**
     * Call another console command silently.
     *
     * @param string $command
     * @param array  $arguments
     *
     * @return int
     * @throws \Exception
     */
    public function callSilent($command, array $arguments = [])
    {
        $instance = $this->getApplication()->find($command);
        $arguments['command'] = $command;

        return $instance->run(new ArrayInput($arguments), new NullOutput);
    }

    /**
     * Run the console command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return int
     * @throws \Exception
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;

        $this->output = $output;

        return parent::run($input, $output);
    }

    /**
     * @return mixed
     */
    abstract public function handle();

    /**
     * Determine if the given argument is present.
     *
     * @param string|int $name
     *
     * @return bool
     */
    public function hasArgument($name)
    {
        return $this->input->hasArgument($name);
    }

    /**
     * Get the value of a command argument.
     *
     * @param string $key
     *
     * @return string|array
     */
    public function argument($key = null)
    {
        if (is_null($key)) {
            return $this->input->getArguments();
        }

        return $this->input->getArgument($key);
    }

    /**
     * Determine if the given option is present.
     *
     * @param string $name
     *
     * @return bool
     */
    public function hasOption($name)
    {
        return $this->input->hasOption($name);
    }

    /**
     * Get the value of a command option.
     *
     * @param string $key
     *
     * @return string|array
     */
    public function option($key = null)
    {
        if (is_null($key)) {
            return $this->input->getOptions();
        }

        return $this->input->getOption($key);
    }

    /**
     * Format input to textual table.
     *
     * @param array  $headers
     * @param array  $rows
     * @param string $style
     */
    public function table(array $headers, $rows, $style = 'default')
    {
        $table = new Table($this->output);
        $table->setHeaders($headers)->setRows($rows)->setStyle($style)->render();
    }

    /**
     * Write a string as standard output.
     *
     * @param string          $string
     * @param string          $style
     * @param null|int|string $verbosity
     */
    public function line($string, $style = null, $verbosity = null)
    {
        $styled = $style ? "<$style>$string</$style>" : $string;
        $this->output->writeln($styled, $this->parseVerbosity($verbosity));
    }

    /**
     * Write a string as information output.
     *
     * @param string          $string
     * @param null|int|string $verbosity
     */
    public function info($string, $verbosity = null)
    {
        $this->line($string, 'info', $verbosity);
    }

    /**
     * Write a string as comment output.
     *
     * @param string          $string
     * @param null|int|string $verbosity
     */
    public function comment($string, $verbosity = null)
    {
        $this->line($string, 'comment', $verbosity);
    }

    /**
     * Write a string as error output.
     *
     * @param string          $string
     * @param null|int|string $verbosity
     */
    public function error($string, $verbosity = null)
    {
        $this->line($string, 'error', $verbosity);
    }

    /**
     * Write a string as question output.
     *
     * @param string          $string
     * @param null|int|string $verbosity
     */
    public function question($string, $verbosity = null)
    {
        $this->line($string, 'question', $verbosity);
    }

    /**
     * Get the verbosity level in terms of Symfony's OutputInterface level.
     *
     * @param string|int $level
     *
     * @return int
     */
    protected function parseVerbosity($level = null)
    {
        if (isset($this->verbosityMap[$level])) {
            $level = $this->verbosityMap[$level];
        } elseif (!is_int($level)) {
            $level = $this->verbosity;
        }

        return $level;
    }

    /**
     * Set the verbosity level.
     *
     * @param string|int $level
     */
    protected function setVerbosity($level)
    {
        $this->verbosity = $this->parseVerbosity($level);
    }
}
