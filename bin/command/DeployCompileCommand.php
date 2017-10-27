<?php
declare(strict_types=1);

namespace UnitiWeb\DeployLambdaPhp\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use UnitiWeb\DeployLambdaPhp\Command\Common\DeployOutput;

class DeployCompileCommand extends Command
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
            ->setName('deploy:compile')
            // the short description shown while running "php bin/console list"
            ->setDescription('Re-Compiles the php binary')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command will rebuild the php binary with the settings in buildphp.sh and dockerfile.buildphp')
        ;
    }

    /**
     * Execute command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        assert(valid_num_args());

        $this->helper = new DeployOutput($output);

        $process = new Process(['sh', './buildphp.sh']);
        $process->start();

        $this->helper->spacer();
        $this->helper->divider();
        $this->helper->header('Start PHP Build');
        $this->helper->divider();

        foreach ($process as $type => $data) {
            if ($process::OUT === $type) {
                $data = trim($data);
                if ($data !== '') {
                    $parts = explode("\n", $data);
                    foreach ($parts as $part) {
                        $this->helper->line(trim($part));
                    }
                }
            } elseif ($process::ERR === $type) {
                $this->helper->error($data);
            }
        }

        $this->helper->divider();
        $this->helper->header('Build Complete');
        $this->helper->divider();
    }
}
