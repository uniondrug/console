<?php
/**
 * MakeCommandCommand.php
 *
 */

namespace Uniondrug\Console\Commands;

use Phalcon\Text;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Uniondrug\Console\Command;

class MakeCommandCommand extends Command
{
    protected $template = <<<'TEMP'
<?php
/**
 * @ClassName@
 *
 * Command Description
 */

namespace App\Commands;

use Uniondrug\Console\Command;

class @ClassName@ extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = '@CommandName@';
    
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Description of this command';

    /**
     * @inheritdoc
     */
    public function handle()
    {
        $this->output->writeln("Hello World from @CommandName@");
    }
}
TEMP;

    /**
     * @inheritdoc
     */
    public function configure()
    {
        parent::configure();
        $this->setName('make:command');
        $this->setDescription('Create command');
        $this->addArgument('name', InputArgument::REQUIRED, 'The command name. e.g.: order:list, will generate file OrderListCommand');
    }

    /**
     * @inheritdoc
     */
    public function handle()
    {
        $commandName = $this->argument('name');
        $className = Text::camelize($commandName, ':') . 'Command';
        $dirName   = $this->getDI()->appPath() . DIRECTORY_SEPARATOR . 'Commands';
        $fileName  = $dirName . DIRECTORY_SEPARATOR . $className . '.php';
        if (!file_exists($dirName)) {
            @mkdir($dirName, 0755, true);
        }
        if (file_exists($fileName)) {
            throw new RuntimeException("File $fileName exists!");
        }

        $contents = $this->template;
        $contents = str_replace('@CommandName@', $commandName, $contents);
        $contents = str_replace('@ClassName@', $className, $contents);

        @file_put_contents($fileName, $contents);

        $this->output->writeln("File: $fileName created");
    }
}
