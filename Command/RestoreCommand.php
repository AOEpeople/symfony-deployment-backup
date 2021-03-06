<?php
namespace Aoe\Deployment\SystemStorageBackupBundle\Command;


use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class RestoreCommand
 * @package Aoe\Deployment\SystemStorageBackupBundle\Command
 */
class RestoreCommand extends AbstractCommand
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
                'mysql -h %s -u %s -p%s -e "DROP DATABASE IF EXISTS %s; CREATE DATABASE %s;"',
                escapeshellarg($dbHost),
                escapeshellarg($dbUser),
                escapeshellarg($dbPassword),
                $dbName,
                $dbName
            );
            $this->runCommand($command, $input, $output);
        }

        $command = sprintf(
            'gunzip < %s | mysql -h %s -u %s -p%s %s',
            escapeshellarg($backupFile),
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            escapeshellarg($dbPassword),
            escapeshellarg($dbName)
        );

        $this->runCommand($command, $input, $output);

        $output->writeln("<info>sql backup restored from $backupFile</info>");
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
                $output->writeln('<error>' . $e->getMessage() . '</error>');
            }
        }

        $command = sprintf('tar %s -xzf %s ', implode(' ', $options), $backupFile);

        $this->runCommand($command, $input, $output);

        $output->writeln("<info>assets backup restored from $backupFile</info>");
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
}
