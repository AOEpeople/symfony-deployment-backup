<?php
namespace Aoe\Deployment\SystemStorageBackupBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Class RestoreCommand
 * @package Aoe\Deployment\SystemStorageBackupBundle\Command
 */
class RestoreCommand extends ContainerAwareCommand
{
    /**
     * Command configuration
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('aoedeployment:restorebackup')
            ->setDescription('Restores SQL/ZIP backup files to database and asset folders.')
            ->addArgument('backupDirectory', InputArgument::REQUIRED, 'The directory of the backup files.')
            ->addOption('restoreSQL', null, InputOption::VALUE_NONE, 'Restore SQL backup file to database.')
            ->addOption('restoreSQLDropAndCreate', null, InputOption::VALUE_NONE, 'Perform Drop and Create on Database before Restore is performed.')
            ->addOption('restoreSQLFilename', null, InputOption::VALUE_OPTIONAL, 'SQL backup filename.', 'database.sql.gz')
            ->addOption('restoreAssets', null, InputOption::VALUE_NONE, 'Restore assets backup file to the current working directory.')
            ->addOption('restoreUnlinkBefore', null, InputOption::VALUE_NONE, 'Have tar unlink existing resources first.')
            ->addOption('changeToDir', null, InputOption::VALUE_OPTIONAL, 'Restore in desired directory')
            ->addOption('restoreAssetsFilename', null, InputOption::VALUE_OPTIONAL, 'Assets backup filename.', 'assets.tar.gz')
            ->addOption('timeout', null, InputOption::VALUE_OPTIONAL, 'Optional restore process timeout (in seconds).');
    }

    /**
     * Execute the console command
     *
     * @param InputInterface  $input  Input
     * @param OutputInterface $output Output
     * @return void
     * @throws \RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('restoreSQL')) {
            $this->restoreSQLBackup($input, $output);
        }

        if ($input->getOption('restoreAssets')) {
            $this->restoreAssetsBackup($input, $output);
        }
    }

    /**
     * Restore sql backup to database
     *
     * @param InputInterface  $input  Input
     * @param OutputInterface $output Output
     * @return void
     * @throws \RuntimeException
     */
    protected function restoreSQLBackup(InputInterface $input, OutputInterface $output)
    {
        $backupDirectory = $input->getArgument('backupDirectory');
        $backupFile = $backupDirectory . DIRECTORY_SEPARATOR . $input->getOption('restoreSQLFilename');
        $this->checkBackupFile($backupFile);

        $dbHost = $this->getContainer()->getParameter('database_host');
        $dbName = $this->getContainer()->getParameter('database_name');
        $dbUser = $this->getContainer()->getParameter('database_user');
        $dbPassword = $this->getContainer()->getParameter('database_password');

        if ($input->getOption('restoreSQLDropAndCreate')) {
            $command = sprintf(
                'mysql -h %s -u %s -p\'%s\' -e "DROP DATABASE IF EXISTS %s; CREATE DATABASE %s;"',
                $dbHost,
                $dbUser,
                $dbPassword,
                $dbName,
                $dbName
            );
            $this->runCommand($command, $input, $output);
        }

        $command = sprintf(
            'gunzip < %s | mysql -h %s -u %s -p\'%s\' %s',
            $backupFile,
            $dbHost,
            $dbUser,
            $dbPassword,
            $dbName
        );

        $this->runCommand($command, $input, $output);

        $output->writeln("sql backup restored from $backupFile");
    }

    /**
     * Restore assets backup to target folder
     *
     * @param InputInterface  $input  Input
     * @param OutputInterface $output Output
     * @return void
     * @throws \RuntimeException
     */
    protected function restoreAssetsBackup(InputInterface $input, OutputInterface $output)
    {
        $backupDirectory = $input->getArgument('backupDirectory');
        $backupFile = $backupDirectory . DIRECTORY_SEPARATOR . $input->getOption('restoreAssetsFilename');
        $this->checkBackupFile($backupFile);


        $options = [];
        $options[] = '--overwrite';

        $unlink = $input->getOption('restoreUnlinkBefore');
        if ($unlink) {
            $options[] = '--unlink-first';
            $options[] = '--recursive-unlink';
        }

        $rootDir = realpath($this->getApplication()->getKernel()->getRootDir() . DIRECTORY_SEPARATOR . '..');
        $changeToDir = $input->getOption('changeToDir');
        if ($changeToDir) {
            $directory = $rootDir . DIRECTORY_SEPARATOR . $changeToDir;
            try {
                $this->verfifyDir($directory, $output);
                $options[] = sprintf('--directory %s', $directory);
            } catch (\Exception $e) {
                $output->writeln($e->getMessage());
            }
        }

        $command = sprintf('tar %s -xzf %s ', implode(' ', $options), $backupFile);

        $this->runCommand($command, $input, $output);

        $output->writeln("assets backup restored from $backupFile");
    }

    /**
     * Check if $backupFile exists
     *
     * @param string $backupFile Backup file
     * @return void
     * @throws \RuntimeException
     */
    protected function checkBackupFile($backupFile)
    {
        $fileSystem = new Filesystem();
        if (!$fileSystem->exists($backupFile)) {
            throw new \RuntimeException("backup file $backupFile does not exist");
        }
    }

    /**
     * Check if Directory exists and create if its missing
     *
     * @param string          $directory Directory to verify
     * @param OutputInterface $output    Output
     * @return void
     * @throws \RuntimeException
     */
    protected function verfifyDir($directory, OutputInterface $output)
    {
        if (!file_exists($directory) && !is_dir($directory)) {
            if (!mkdir($directory)) {
                throw new \RuntimeException(sprintf('Could not create directory: %s', $directory));
            } else {
                $output->writeln("Directory $directory was missing, created it.");
            }
        }
    }

    /**
     * @param string          $command Command to run
     * @param InputInterface  $input   Input
     * @param OutputInterface $output  Output
     * @return void
     * @throws \RuntimeException
     */
    protected function runCommand($command, InputInterface $input, OutputInterface $output)
    {
        $output->writeln('running command: ' . $command);

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
