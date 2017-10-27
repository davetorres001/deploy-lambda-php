<?php
declare(strict_types=1);

namespace UnitiWeb\DeployLambdaPhp\Command;

use Aws\AwsClient;
use Aws\Lambda\LambdaClient;
use Aws\S3\S3Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessBuilder;
use UnitiWeb\DeployLambdaPhp\Command\Common\DeployConfiguration;
use UnitiWeb\DeployLambdaPhp\Command\Common\DeployFunction;
use UnitiWeb\DeployLambdaPhp\Command\Common\DeployOutput;

class DeployExecuteCommand extends Command
{
    /**
     * @var ContainerBuilder
     */
    protected $container;

    /**
     * @var AwsClient
     */
    protected $aws;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var string
     */
    protected $environment;

    /**
     * @var DeployConfiguration
     */
    protected $config;

    /**
     * @var bool
     */
    protected $continue = true;

    /**
     * @var string
     */
    protected $deployPath;

    /**
     * @var string
     */
    protected $configPath;

    /**
     * @var LambdaClient
     */
    protected $lambdaClient;

    /**
     * @var S3Client
     */
    protected $s3Client;

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
            ->setName('deploy:execute')
            // the short description shown while running "php bin/console list"
            ->setDescription('Deploy to lambda. Example deploy -e prod (or dev or whatever)')
            // the full command description shown when running the command with
            // the "--help" option
            ->setHelp('This command will deploy this function to lambda')
            ->addOption('env', 'e', InputOption::VALUE_REQUIRED, 'The environment to deploy. These are configured in the config folder', null)
        ;
    }

    /**
     * Execute command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        assert(valid_num_args());

        $this->input = $input;
        $this->output = $output;
        $this->helper = new DeployOutput($this->output);
        $this->deployPath = dirname(dirname(__DIR__)) . '/.deploy/deploy.zip';

        // Space the beginning
        $this->helper->spacer();
        $this->helper->header('Loading Configuration');

        $environment = $this->input->getOption('env');
        $this->config = new DeployConfiguration($this->helper);
        $this->config->load($environment);

        if (!$this->config->getFunction()) {
            $this->helper->error('Environment doesn\'t have a Function setting in the config.yml');
            exit;
        }

        $this->helper->header('Prepareing to Deploy');
        $this->prepare();

        $this->helper->header('Composer Install -o --no-dev');
        $this->helper->divider();
        $this->composer();
        $this->helper->divider();

        $this->helper->header('Zip Package');
        $this->zip();
        $this->helper->divider();

        $this->helper->header('Reset State');
        $this->helper->divider();
        $this->reset();
        $this->helper->divider();

        $this->helper->header('Lambda Function');
        $this->createFunction();

        // Space the end
        $this->helper->spacer();
        $this->helper->divider();
        $this->helper->header('Deploy Complete');
        $this->helper->divider();
        $this->helper->spacer();
    }

    /**
     * Prepair to deploy
     */
    protected function prepare()
    {
        assert(valid_num_args());

        $deplyDirectory = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . '.deploy';

        if (false === file_exists($deplyDirectory)) {
            $this->helper->line('Create deploy cache directory', 'comment');
            if (false === mkdir($deplyDirectory)) {
                $this->helper->line('Could not create .deploy directory', 'error');
            }
        }
    }

    /**
     * Run composer install
     */
    protected function composer()
    {
        assert(valid_num_args());

        $helper = $this->getHelper('process');
        $process = ProcessBuilder::create(['composer', 'install', '-o', '--no-dev'])->getProcess();

        $helper->run($this->output, $process, 'Could not run composer install', function($type, $data) {
            if (Process::ERR === $type) {
                $this->output->write($data);
            } else {
                $this->output->write($data);
            }
        });
    }

    /**
     * Create Zip Package
     */
    protected function zip()
    {
        assert(valid_num_args());

        if (file_exists($this->deployPath)) {
            unlink($this->deployPath);
        }

        $command = ['zip', '-9r', $this->deployPath, 'config', 'src', 'vendor', 'handler.js', 'handler.php', 'php'];
        $process = new Process($command);
        $process->start();

        $count = 1;
        foreach ($process as $type => $data) {
            if ($process::OUT === $type) {
                if ($count % 50 === 0) {
                    $this->output->write(".");
                }
                $count++;
            } elseif ($process::ERR === $type) {
                $this->helper->error($data);
            }
        }

        $this->helper->spacer();
    }

    /**
     * Create the lambda function
     */
    protected function createFunction()
    {
        assert(valid_num_args());

        $functionConfig = $this->config->getFunction();
        $function = new DeployFunction(
            $this->helper,
            $this->config->getVariable('REGION'),
            $this->config->getVariable('VERSION')
        );

        $function->setHandler($functionConfig['handler'] ?? 'handler.handle');
        $function->setFunctionName($functionConfig['function_name'] ?? '');
        $function->setDescription($functionConfig['description'] ?? '');
        $function->setMemorySize($functionConfig['memory_size'] ?? 128);
        $function->setRole($functionConfig['role'] ?? '');
        $function->setRuntime($functionConfig['runtime'] ?? 'nodejs6.10');
        $function->setTimeout($functionConfig['timeout'] ?? 3);
//        $function->addEnvironmentVariable('ENVIRONMENT', $this->environment);

        foreach ($this->config->getEnvironment() as $key => $value) {
            if ($key !== 'FUNCTION') {
                $function->addEnvironmentVariable($key, $value);
            }
        }

        $function->deploy($this->deployPath);
    }

    /**
     * Reset the state with composer install
     */
    protected function reset()
    {
        assert(valid_num_args());

        $helper = $this->getHelper('process');
        $process = ProcessBuilder::create(['composer', 'install'])->getProcess();

        $helper->run($this->output, $process, 'Could not run composer install', function($type, $data) {
            $this->output->write($data);
        });
    }
}
