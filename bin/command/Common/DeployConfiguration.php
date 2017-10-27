<?php
declare(strict_types=1);

namespace UnitiWeb\DeployLambdaPhp\Command\Common;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class DeployConfiguration
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var DeployOutput
     */
    protected $output;

    public function __construct(DeployOutput $output)
    {
        assert(valid_num_args());

        $this->output = $output;
    }

    /**
     * Get configuration
     */
    public function load(string $environment)
    {
        assert(valid_num_args());

        $path = dirname(dirname(dirname(__DIR__))) . '/config';

        $container = new ContainerBuilder();
        $loader = new YamlFileLoader($container, new FileLocator($path));
        $loader->load('config.yml');

        $environments = $container->getParameter('environments');
        $environments[$environment]['ENVIRONMENT'] = $environment;

        if (!array_key_exists($environment, $environments)) {
            $this->helper->header('The environment ' . $environment . ' does not exist in the config.yml file', 'error');
            exit;
        } else {
            $this->config = $environments[$environment];
        }
    }

    /**
     * Get function configuration
     */
    public function getFunction() : ?array
    {
        assert(valid_num_args());

        return $this->config['FUNCTION'] ?? null;
    }

    /**
     * Get Environment Variables
     */
    public function getEnvironment() : array
    {
        assert(valid_num_args());

        $data = $this->config;

        if (isset($data['FUNCTION'])) {
            unset($data['FUNCTION']);
        }

        return $data;
    }

    /**
     * Get environment variable
     */
    public function getVariable(string $name, string $type = 'string')
    {
        assert(valid_num_args());

        $data = $this->config;

        if ($name === 'FUNCTION') {
            return null;
        }

        if ($type === 'int') {
            return (int) $data[$name] ?? null;
        } elseif ($type === 'bool') {
            return (bool) $data[$name] ?? null;
        } else {
            return (string) $data[$name] ?? null;
        }
    }

    /**
     * Get the function name
     */
    public function getFunctionName() : ?string
    {
        assert(valid_num_args());
        assert(null !== $this->config['FUNCTION']['function_name'] ?? null);

        return $this->config['FUNCTION']['function_name'];
    }
}
