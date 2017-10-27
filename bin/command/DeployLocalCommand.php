<?php
declare(strict_types=1);

namespace UnitiWeb\DeployLambdaPhp\Command;

use UnitiWeb\DeployLambdaPhp\Command\Common\DeployConfiguration;
use UnitiWeb\DeployLambdaPhp\Command\Common\DeployOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class DeployLocalCommand extends Command
{
    /**
     * @var DeployOutput
     */
    protected $helper;

    /**
     * Configure the command
     */
    protected function configure()
    {
        assert(valid_num_args());

        $this
            // the name of the command (the part after "bin/console")
            ->setName('deploy:invoke:local')
            // the short description shown while running "php bin/console list"
            ->setDescription('Invokes the configured lambda function locally. Requires --env local (for example)')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command will invoke a deployed lambda function locally')
            ->addOption('env', 'e', InputOption::VALUE_REQUIRED, 'The environment to deploy. These are configured in the config folder', null)
            ->addOption('payload', 'p', InputOption::VALUE_OPTIONAL, 'The event (in the events folder without extension) json event to pass.', 'Empty')
        ;
    }

    /**
     * Execute command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        assert(valid_num_args());

        $env = $input->getOption('env');

        $this->helper = new DeployOutput($output);
        $config = new DeployConfiguration($this->helper);
        $config->load($env);

        foreach ($config->getEnvironment() as $key => $value) {
            $process = new Process('export', $key . '=' . $value);
            $process->run();
        }

        $payload = $input->getOption('payload');
        $event = $this->getEventContents($payload);
        $context = '{}';

        $process = new Process(['php', dirname(dirname(__DIR__)) . '/handler.php', $event, $context, $env]);
        $process->start();

        foreach ($process as $type => $data) {
            if ($process::OUT === $type) {
                $output->writeln($data);
            } elseif ($process::ERR === $type) {
                $this->helper->error($data);
            }
        }
    }

    /**
     * Get event file contents
     */
    protected function getEventContents(string $payload) : string
    {
        assert(valid_num_args());

        $eventPath = __DIR__ . '/Event/' . $payload . '.json';
        if (file_exists($eventPath)) {
            return file_get_contents($eventPath);
        } else {
            $this->helper->error("The event '$eventPath' does not exist. An empty json {} will be used");
            exit;
        }
    }
}
