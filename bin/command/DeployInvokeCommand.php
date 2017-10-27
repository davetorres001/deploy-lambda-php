<?php
declare(strict_types=1);

namespace UnitiWeb\DeployLambdaPhp\Command;

use Aws\Lambda\LambdaClient;
use UnitiWeb\DeployLambdaPhp\Command\Common\DeployConfiguration;
use UnitiWeb\DeployLambdaPhp\Command\Common\DeployOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DeployInvokeCommand extends Command
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
            ->setName('deploy:invoke')
            // the short description shown while running "php bin/console list"
            ->setDescription('Invokes the configured lambda function. Requires --env development (for example)')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command will invoke a deployed lambda function. Options: --env (required), --type, --payload (event file name) --log')
            ->addOption('env', 'e', InputOption::VALUE_REQUIRED, 'The environment to deploy. These are configured in the config folder', null)
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'The invocation type. Options: RequestResponse (default), Event, or DryRun', 'RequestResponse')
            ->addOption('payload', 'p', InputOption::VALUE_OPTIONAL, 'The event (in the events folder without extension) json event to pass.', 'Empty')
            ->addOption('log', 'l', InputOption::VALUE_OPTIONAL, 'The logging type. Options: Tail (default) or None.', 'Tail')
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

        $type = $input->getOption('type');
        $log = $input->getOption('log');
        $payload = $input->getOption('payload');

        assert(in_array($type, ['Event', 'RequestResponse', 'DryRun']));
        assert(in_array($log, ['Tail', 'None']));

        $client = new LambdaClient([
            'region' => $config->getVariable('REGION'),
            'version' => $config->getVariable('VERSION'),
        ]);

        $result = $client->invoke([
            'FunctionName' => $config->getFunctionName(), // REQUIRED
            'InvocationType' => $type,
            'LogType' => $log,
            'Payload' => $this->getEventContents($payload)
        ]);

        $data = $result->toArray();

        $this->helper->spacer();
        $this->helper->divider();
        $this->helper->header('Invocation Results');

        $table = new Table($output);
        $table->addRow(['<comment>Status Code</comment>', $data['StatusCode']]);
        $table->addRow(['<comment>Function Error</comment>', $data['FunctionError']]);
        $table->addRow(['<comment>Date</comment>', $data['@metadata']['headers']['date']]);
        $table->addRow(['<comment>Content-Type</comment>', $data['@metadata']['headers']['content-type']]);
        $table->addRow(['<comment>Content-Length</comment>', $data['@metadata']['headers']['content-length']]);
        $table->addRow(['<comment>Connection</comment>', $data['@metadata']['headers']['connection']]);
        $table->setStyle('borderless');
        $table->render();

        $this->helper->divider();
        $this->helper->header('Payload');
        foreach ((array) $data['Payload'] as $key => $value) {
            if (is_array($value)) {
                foreach ((array) $value as $k => $v) {
                    if (!is_array($v)) {
                        $this->helper->line($k . ' : ' . $v);
                    }
                }
            } else {
                $this->helper->line($key . ' : ' . $value);
            }
        }

        $this->helper->divider();
        $this->helper->header('Log Results');
        $log = base64_decode($data['LogResult']);
        $logs = explode("\n", $log);

        foreach ($logs as $l) {
            $this->helper->line($l);
        }

        $this->helper->spacer();
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
