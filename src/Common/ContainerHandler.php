<?php
declare(strict_types=1);

namespace Unitiweb\DeployLambdaPhp\Common;

use Symfony\Component\DependencyInjection\ContainerInterface;

class ContainerHandler
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var array
     */
    protected $context;

    public function __construct(ContainerInterface $container, string $context)
    {
        assert(valid_num_args());

        $this->container = $container;
        $this->context = json_decode($context, true);
    }

    /**
     * Gets a service.
     */
    public function get(string $id)
    {
        assert(valid_num_args());

        return $this->container->get($id);
    }

    /**
     * Returns true if the given service is defined.
     */
    public function has(string $id) : bool
    {
        assert(valid_num_args());

        return $this->container->has($id);
    }

    /**
     * Gets a parameter.
     */
    public function getParameter(string $name)
    {
        assert(valid_num_args());

        return $this->container->getParameter($name);
    }

    /**
     * Checks if a parameter exists.
     */
    public function hasParameter(string $name) : bool
    {
        assert(valid_num_args());

        return $this->container->hasParameter($name);
    }

    /**
     * Memory limit in MB for the Lambda function
     */
    public function getMemoryLimitInMB() : ?string
    {
        assert(valid_num_args());

        return $this->context['memoryLimitInMB'] ?? null;
    }

    /**
     * Name of the Lambda function that is running
     */
    public function getFunctionName() : ?string
    {
        assert(valid_num_args());

        return $this->context['functionName'] ?? null;
    }

    /**
     * The Lambda function version that is executing
     */
    public function getFunctionVersion() : ?string
    {
        assert(valid_num_args());

        return $this->context['functionVersion'] ?? null;
    }

    /**
     * The ARN used to invoke this function
     */
    public function getInvokedFunctionArn() : ?string
    {
        assert(valid_num_args());

        return $this->context['invokedFunctionArn'] ?? null;
    }

    /**
     * AWS request ID associated with the request
     */
    public function getAwsRequestId() : ?string
    {
        assert(valid_num_args());

        return $this->context['awsRequestId'] ?? null;
    }

    /**
     * The CloudWatch log stream name
     */
    public function getLogStreamName() : ?string
    {
        assert(valid_num_args());

        return $this->context['logStreamName'] ?? null;
    }

    /**
     * The CloudWatch log group name
     */
    public function getLogGroupName() : ?string
    {
        assert(valid_num_args());

        return $this->context['logGroupName'] ?? null;
    }

    /**
     * Mobile SDK context information
     */
    public function getClientContext() : ?string
    {
        assert(valid_num_args());

        return $this->context['clientContext'] ?? null;
    }

    /**
     * Mobile SDK Cognito identity information
     */
    public function getIdentity() : ?string
    {
        assert(valid_num_args());

        return $this->context['identity'] ?? null;
    }
}
