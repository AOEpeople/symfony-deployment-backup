<?php
namespace Aoe\Deployment\SystemStorageBackupBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Class AbstractCommand
 */
abstract class AbstractCommand extends ContainerAwareCommand
{
    /**
     * @param string          $command Command to run
     * @param InputInterface  $input   Input
     * @param OutputInterface $output  Output
     * @return void
     * @throws \RuntimeException
     */
    protected function runCommand($command, InputInterface $input, OutputInterface $output)
    {
        $output->writeln('<comment>running command: ' . $command . '</comment>');

        $timeout = $this->getTimeout($input);

        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->start();
        while ($process->isRunning()) {
            if ($timeout) {
                $process->checkTimeout();
            }

            // sleep for 2 seconds and check again
            usleep(2 * 1000 * 1000);
        }

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        } else {
            $output->writeln($process->getOutput());
        }
    }

    /**
     * @param InputInterface $input Input interface
     * @return null|int
     */
    protected function getTimeout(InputInterface $input)
    {
        if (!$input->hasOption('timeout')) {
            return null;
        }

        $result = (int) $input->getOption('timeout');

        return $result ? null : $result;
    }
}
