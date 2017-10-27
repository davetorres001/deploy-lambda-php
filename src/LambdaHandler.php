<?php

namespace Unitiweb\DeployLambdaPhp;

use Psr\Log\LoggerInterface;
use Unitiweb\DeployLambdaPhp\Common\BaseHandler;
use Unitiweb\DeployLambdaPhp\Common\Container;
use Unitiweb\DeployLambdaPhp\Common\ContainerHandler;
use Unitiweb\DeployLambdaPhp\Common\HandlerController;
use Symfony\Component\DependencyInjection\ContainerInterface;

class LambdaHandler
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(ContainerHandler $container, LoggerInterface $logger)
    {
        assert(valid_num_args());

        $this->container = $container;
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $event)
    {
        assert(valid_num_args());

        $aws = $this->container->get('lambda.client');

//        $logger = $this->container->get('logger');
        $this->logger->info('Region is : ' . $this->container->getParameter('REGION'));

        return [
            'REGION' => $this->container->getParameter('REGION'),
            'VERSION' => $this->container->getParameter('VERSION'),
            'DATABASE_PASSWORD' => $this->container->getParameter('DATABASE_PASSWORD'),
            'statusCode' => 200,
            'message' => 'Your lambda function deploy was a success',
        ];
    }
}
