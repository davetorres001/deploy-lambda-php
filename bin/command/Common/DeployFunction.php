<?php
declare(strict_types=1);

namespace UnitiWeb\DeployLambdaPhp\Command\Common;

use Aws\Lambda\LambdaClient;
use Aws\Result;
use Aws\S3\S3Client;
use Symfony\Component\Console\Output\OutputInterface;

class DeployFunction
{
    const DEFAULT = 'HIDDEN';

    const RUNTIMES = [
        'nodejs',
        'nodejs4.3',
        'nodejs6.10',
        'java8',
        'python2.7',
        'python3.6',
        'dotnetcore1.0',
        'nodejs4.3-edge'
    ];

    const REQUIRED = [
        'FunctionName',
        'Handler',
        'Role',
        'Runtime',
    ];

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var LambdaClient
     */
    protected $lambdaClient;

    /**
     * @var S3Client
     */
    protected $s3Client;

    /**
     * @var string
     */
    protected $zipPath;

    /**
     * @var string
     */
    protected $functionName;

    /**
     * @var string
     */
    protected $description;

    /**
     * @var string
     */
    protected $handler;

    /**
     * @var int
     */
    protected $memorySize;

    /**
     * @var string
     */
    protected $role;

    /**
     * @var string
     */
    protected $runtime;

    /**
     * @var int
     */
    protected $timeout;

    /**
     * @var array
     */
    protected $envVariables;

    public function __construct(DeployOutput $output, string $region, string $version)
    {
        assert(valid_num_args());

        $this->output = $output;

        $this->lambdaClient = new LambdaClient([
            'region' => $region,
            'version' => $version,
        ]);

        $this->s3Client = new S3Client([
            'region' => $region,
            'version' => $version,
        ]);
    }

    /**
     * @param string $functionName
     */
    public function setFunctionName(string $functionName)
    {
        assert(valid_num_args());

        $this->functionName = $functionName;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description)
    {
        assert(valid_num_args());

        $this->description = $description;
    }

    /**
     * @param string $handler
     */
    public function setHandler(string $handler)
    {
        assert(valid_num_args());

        $this->handler = $handler;
    }

    /**
     * @param int $memorySize
     */
    public function setMemorySize(int $memorySize)
    {
        assert(valid_num_args());

        $this->memorySize = $memorySize;
    }

    /**
     * @param string $role
     */
    public function setRole(string $role)
    {
        assert(valid_num_args());

        $this->role = $role;
    }

    /**
     * @param string $runtime
     */
    public function setRuntime(string $runtime)
    {
        assert(valid_num_args());
        assert(in_array($runtime, self::RUNTIMES));

        $this->runtime = $runtime;
    }

    /**
     * @param int $timeout
     */
    public function setTimeout(int $timeout)
    {
        assert(valid_num_args());

        $this->timeout = $timeout;
    }

    /**
     * Add environment variable
     */
    public function addEnvironmentVariable(string $key, $value)
    {
        assert(valid_num_args());

        $this->envVariables[$key] = $value;
    }

    /**
     * Deploy the function
     */
    public function deploy(string $zipPath)
    {
        assert(valid_num_args());

        $this->zipPath = $zipPath;

        $config = $this->buildConfig();

        $this->output->header('Upload Zip to S3 Bucket');
        $this->uploadToBucket($config);

        $this->output->header('Check if Lambda Function Exists');
        if (null !== ($result = $this->getFunction($config))) {
            $this->output->line('Lambda function exists');
            $this->output->line('Update Existing Lambda Function');
            $this->updateFunction($config, $result);
        } else {
            $this->output->line('Lambda function DOES NOT exist');
            $this->output->line('Create New Lambda Function');
            $this->createFunction($config);
        }

        $this->output->header('Delete Zip File from S3 Bucket');
        $this->deleteFromBucket($config);
    }

    /**
     * Build config array
     */
    protected function buildConfig() : array
    {
        assert(valid_num_args());

        $config = [
            'Code' => [
                'S3Bucket' => $this->functionName . '-lambda-deploy-bucket',
                'S3Key' => 'lambda/deploy/' . $this->functionName . '-' . date('c')
            ],
            'Description' => $this->description ?? '',
            'FunctionName' => $this->functionName,
            'Handler' => $this->handler,
            'MemorySize' => $this->memorySize ?? 128,
            'Role' => $this->role ?? '',
            'Runtime' => $this->runtime ?? 'nodejs6.10',
            'Timeout' => $this->timeout ?? 3,
            'Environment' => [
                'Variables' => []
            ]
        ];

        foreach ($this->envVariables as $key => $value) {
            $config['Environment']['Variables'][$key] = $value;
        }

        foreach (self::REQUIRED as $key) {
            if (!isset($config[$key]) || $config[$key] === '' || $config[$key] === null) {
                throw new \Exception("DeployFunction : $key is required");
            }
        }

        return $config;
    }

    /**
     * Check to see if function exists
     */
    protected function getFunction(array $config) : ?Result
    {
        assert(valid_num_args());
        assert(isset($config['FunctionName']));

        try {
            return $this->lambdaClient->getFunction(['FunctionName' => $config['FunctionName']]);
        } catch (AwsException $e) {
            return null;
        }
    }

    /**
     * Create the lambda function
     */
    protected function createFunction(array $config)
    {
        assert(valid_num_args());

        $this->lambdaClient->createFunction($config);
    }

    /**
     * Update the lambda function
     */
    protected function updateFunction(array $config, Result $result)
    {
        assert(valid_num_args());

        $resultArray = $result->toArray();

        $original = $resultArray['Configuration']['Environment']['Variables'];
        $current = $config['Environment']['Variables'];

        foreach ($current as $key => $value) {
            if ($value === self::DEFAULT && isset($original[$key])) {
                $current[$key] = $original[$key];
            }
        }

        $config['Environment']['Variables'] = $current;

        $this->lambdaClient->updateFunctionConfiguration($config);
        $this->lambdaClient->updateFunctionCode([
            'FunctionName' => $config['FunctionName'],
            'Publish' => true,
            'S3Bucket' => $config['Code']['S3Bucket'],
            'S3Key' => $config['Code']['S3Key'],
        ]);
    }

    /**
     * Create the S3 Bucket for storing the zip file
     */
    protected function createBucket(array $config)
    {
        assert(valid_num_args());
        assert(isset($config['Code']['S3Bucket']));

        $buckets = $this->s3Client->listBuckets();

        $found = false;
        foreach ($buckets['Buckets'] as $bucket){
            if ($bucket['Name'] === $config['Code']['S3Bucket']) {
                $found = true;
                break;
            }
        }

        if (false === $found) {
            $this->s3Client->createBucket([
                'Bucket' => $config['Code']['S3Bucket'],
            ]);
        }
    }

    /**
     * Upload zip file to s3 bucket
     */
    protected function uploadToBucket(array $config)
    {
        assert(valid_num_args());
        assert(isset($config['Code']['S3Bucket']) && isset($config['Code']['S3Key']));

        $this->s3Client->putObject([
            'Bucket'     => $config['Code']['S3Bucket'],
            'Key'        => $config['Code']['S3Key'],
            'SourceFile' => $this->zipPath,
        ]);
    }

    /**
     * Delete from s3 bucket
     */
    protected function deleteFromBucket(array $config)
    {
        assert(valid_num_args());
        assert(isset($config['Code']['S3Bucket']) && isset($config['Code']['S3Key']));

        $this->s3Client->deleteObject([
            'Bucket'     => $config['Code']['S3Bucket'],
            'Key'        => $config['Code']['S3Key'],
        ]);
    }
}
