<?php
declare(strict_types=1);

namespace UnitiWeb\DeployLambdaPhp\Command\Common;

use Symfony\Component\Console\Output\OutputInterface;

class DeployOutput
{
    /**
     * @var OutputInterface
     */
    protected $output;

    public function __construct(OutputInterface $output)
    {
        assert(valid_num_args());

        $this->output = $output;
    }

    /**
     * Output heading
     */
    public function header(string $text)
    {
        assert(valid_num_args());

        $this->output->writeln("Deploy: <info>$text</info>");
    }

    /**
     * Output heading
     */
    public function line(string $text)
    {
        assert(valid_num_args());

        $this->output->writeln("      : <comment>$text</comment>");
    }

    /**
     * Output an error
     */
    public function error(string $text)
    {
        assert(valid_num_args());

        $this->output->writeln("  - <error>$text</error>");
    }

    /**
     * Output a space
     */
    public function spacer()
    {
        assert(valid_num_args());

        $this->output->writeln('');
    }

    /**
     * Output heading
     */
    public function divider()
    {
        assert(valid_num_args());

        $this->output->writeln('<comment>' . str_repeat('-', 60) . '</comment>');
    }
}
