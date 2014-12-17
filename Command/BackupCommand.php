<?php
namespace Aoe\Deployment\SystemStorageBackupBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Class BackupCommand
 */
class BackupCommand extends ContainerAwareCommand {

    /**
     * Command configuration
     *
     * @return void
     */
    protected function configure() {
        $this
            ->setName('aoedeployment:createbackup')
            ->setDescription('Creates SQL/ZIP files as backup')
            ->addArgument('targetDirectory', InputArgument::REQUIRED, 'The directory where the backup-data should be placed in.')
            ->addOption('backupSQL', NULL, InputOption::VALUE_NONE, 'Create SQL backup.')
            ->addOption('backupAssets', NULL, InputOption::VALUE_NONE, 'Create Assets backup.')
            ->addOption('assetSources', NULL, InputOption::VALUE_REQUIRED, 'Comma separated list of backup asset files/directories.')
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
        if ($input->getOption('backupSQL')) {
            $this->createSQLBackup($input, $output);
        }

        if ($input->getOption('backupAssets')) {
            $this->createAssetsBackup($input, $output);
        }
    }

    /**
     * Create the sql backup
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \RuntimeException
     */
    protected function createSQLBackup(InputInterface $input, OutputInterface $output) {
        $targetDirectory = $input->getArgument('targetDirectory');
        $outputFile = $targetDirectory . '/database.sql.gz';
        $dbHost = $this->getContainer()->getParameter('database_host');
        $dbName = $this->getContainer()->getParameter('database_name');
        $dbUser = $this->getContainer()->getParameter('database_user');
        $dbPassword = $this->getContainer()->getParameter('database_password');

        $command = sprintf('mysqldump -h %s -u %s -p%s %s | gzip - > %s',
            $dbHost, $dbUser, $dbPassword, $dbName, $outputFile);
        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        $translator = $this->getContainer()->get('translator');
        $output->writeln($translator->trans('sql backup created: %outputFile%', array('%outputFile%' => $outputFile)));
    }

    /**
     * Create the assets backups
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \RuntimeException
     */
    protected function createAssetsBackup(InputInterface $input, OutputInterface $output) {
        $targetDirectory = $input->getArgument('targetDirectory');
        $outputFile = $targetDirectory . '/assets.tar.gz';
        $assetSources = trim(str_replace(',', ' ', $input->getOption('assetSources')));

        if (!$assetSources) {
            $assetSources = '*';
        }

        $command = sprintf('tar -czf %s %s',
            $outputFile, $assetSources);
        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        $translator = $this->getContainer()->get('translator');
        $output->writeln($translator->trans('asset backup created: %outputFile%', array('%outputFile%' => $outputFile)));
    }

}
