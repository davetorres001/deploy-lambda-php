<?php

$loader = require __DIR__ . '/vendor/autoload.php';

use Leaguefolio\Lambda\Common\Context;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Leaguefolio\Lambda\Common\ContainerHandler;

/**
 * Setup the service container
 */
$container = new ContainerBuilder();
$loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/config'));
$loader->load('config.yml');
$loader->load('services.yml');

/**
 * Set environment variables
 */
$env = getenv('ENVIRONMENT') ? getenv('ENVIRONMENT') : $argv[3];
$envs = $container->getParameter('environments');
$environment = $envs[$env];
foreach ($environment as $key => $value) {
    if ($key !== 'FUNCTION') {
        $container->setParameter($key, getenv($key) ? getenv($key) : $value);
    }
}

/**
 * Get the lambda context object
 */
$container->setParameter('context', $argv[2]);

/**
 * Get the handler service and execute
 */
$handler = $container->get('lambda.handler');
$response = $handler->handle(json_decode($argv[1], true) ?: []);

/**
 * Send data back to the shim
 */
fwrite(STDOUT, json_encode($response));
