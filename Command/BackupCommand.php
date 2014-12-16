<?php
namespace Aoe\Deployment\SystemStorageBackupBundle\Command;

use Akeneo\Bundle\BatchBundle\Job\RuntimeErrorException;
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
            ->addArgument('backupAssets', InputArgument::OPTIONAL, 'Comma separated list of backup asset directories.')
            ->addOption('backupSQL', NULL, InputOption::VALUE_NONE, 'Create SQL backup.')
            ->addOption('backupAssets', NULL, InputOption::VALUE_NONE, 'Create Assets backup.')
        ;
    }


    /**
     * excecutes the console command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws RuntimeErrorException
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
     * creates the sql backup
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws RuntimeErrorException
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
            throw new RuntimeErrorException($process->getErrorOutput());
        }

        $translator = $this->getContainer()->get('translator');
        $output->writeln($translator->trans('sql backup created: %outputFile%', array('%outputFile%' => $outputFile)));
    }

    /**
     * creates the assets backups
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function createAssetsBackup(InputInterface $input, OutputInterface $output) {
        $targetDirectory = $input->getArgument('targetDirectory');
        $outputFile = $targetDirectory . '/assets.tar.gz';
        $backupAssets = trim(str_replace(',', ' ', $input->getArgument('backupAssets')));

        if (!$backupAssets) {
            throw new RuntimeErrorException('backup assets folders missing');
        }

        $command = sprintf('tar czf %s %s',
            $outputFile, $backupAssets);
        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeErrorException($process->getErrorOutput());
        }

        $translator = $this->getContainer()->get('translator');
        $output->writeln($translator->trans('asset backup created: %outputFile%', array('%outputFile%' => $outputFile)));
    }

}
