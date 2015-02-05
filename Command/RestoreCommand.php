<?php
namespace Aoe\Deployment\SystemStorageBackupBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Translation\Translator;

/**
 * Class RestoreCommand
 * @package Aoe\Deployment\SystemStorageBackupBundle\Command
 */
class RestoreCommand extends ContainerAwareCommand {

    /**
     * Command configuration
     *
     * @return void
     */
    protected function configure() {
        $this
            ->setName('aoedeployment:restorebackup')
            ->setDescription('Restores SQL/ZIP backup files to database and asset folders.')
            ->addArgument('backupDirectory', InputArgument::REQUIRED, 'The directory of the backup files.')
            ->addOption('restoreSQL', NULL, InputOption::VALUE_NONE, 'Restore SQL backup file to database.')
            ->addOption('restoreSQLDropAndCreate', NULL, InputOption::VALUE_NONE, 'Perform Drop and Create on Database before Restore is performed.')
            ->addOption('restoreSQLFilename', NULL, InputOption::VALUE_OPTIONAL, 'SQL backup filename.', 'database.sql.gz')
            ->addOption('restoreAssets', NULL, InputOption::VALUE_NONE, 'Restore assets backup file to the current working directory.')
            ->addOption('restoreUnlinkBefore', NULL, InputOption::VALUE_NONE, 'Have tar unlink existing resources first.')
            ->addOption('restoreAssetsFilename', NULL, InputOption::VALUE_OPTIONAL, 'Assets backup filename.', 'assets.tar.gz')
        ;
    }

    /**
     * Execute the console command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
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
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \RuntimeException
     */
    protected function restoreSQLBackup(InputInterface $input, OutputInterface $output) {
        $backupDirectory = $input->getArgument('backupDirectory');
        $backupFile = $backupDirectory . DIRECTORY_SEPARATOR . $input->getOption('restoreSQLFilename');
        $this->checkBackupFile($backupFile);

        $dbHost = $this->getContainer()->getParameter('database_host');
        $dbName = $this->getContainer()->getParameter('database_name');
        $dbUser = $this->getContainer()->getParameter('database_user');
        $dbPassword = $this->getContainer()->getParameter('database_password');

        $command='';
        if ($input->getOption('restoreSQLDropAndCreate')) {
            $command = sprintf('mysql -h %s -u %s -p\'%s\' -e "DROP DATABASE IF EXISTS %s; CREATE DATABASE %s;"',
                $dbHost, $dbUser, $dbPassword, $dbName, $dbName);
        }

        $process = new Process($command);
        $process->run();

        $command = sprintf('gunzip < %s | mysql -h %s -u %s -p\'%s\' %s',
            $backupFile, $dbHost, $dbUser, $dbPassword, $dbName);

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        /* @var $translator Translator */
        $translator = $this->getContainer()->get('translator');
        $output->writeln($translator->trans(
            'sql backup restored from %backupFile%',
            array('%backupFile%' => $backupFile)));
    }

    /**
     * Restore assets backup to target folder
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \RuntimeException
     */
    protected function restoreAssetsBackup(InputInterface $input, OutputInterface $output) {
        // Possible Tar Options
        $options = array();

        $backupDirectory = $input->getArgument('backupDirectory');
        $backupFile = $backupDirectory . DIRECTORY_SEPARATOR . $input->getOption('restoreAssetsFilename');
        $this->checkBackupFile($backupFile);

        $unlink = $input->getOption('restoreUnlinkBefore');

        array_push($options, '--overwrite');
        if ($unlink) {
            array_push($options, '--unlink-first');
            array_push($options, '--recursive-unlink');
        }

        $command = sprintf('tar %s -xzf %s ', implode(' ', $options), $backupFile);
        $output->writeln($command);

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        /* @var $translator Translator */
        $translator = $this->getContainer()->get('translator');
        $output->writeln($translator->trans(
            'assets backup restored from %backupFile%',
            array('%backupFile%' => $backupFile)));
    }

    /**
     * Check if $backupFile exists
     *
     * @param $backupFile string
     * @throws \RuntimeException
     */
    protected function checkBackupFile($backupFile) {
        /* @var $translator Translator */
        $translator = $this->getContainer()->get('translator');

        $fileSystem = new Filesystem();
        if (!$fileSystem->exists($backupFile)) {
            throw new \RuntimeException(
                $translator->trans(
                    'backup file %backupFile% does not exist',
                    array('%backupFile%' => $backupFile)));
        }

    }

}
