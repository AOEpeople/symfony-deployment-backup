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
            ->addOption('backupSQLFilename', NULL, InputOption::VALUE_OPTIONAL, 'SQL backup filename.', 'database.sql.gz')
            ->addOption('backupSQLAddDropDatabase', NULL, InputOption::VALUE_NONE, 'Add --drop-database to mysqldump.')
            ->addOption('backupAssets', NULL, InputOption::VALUE_NONE, 'Create Assets backup.')
            ->addOption('backupAssetsFilename', NULL, InputOption::VALUE_OPTIONAL, 'Assets backup filename.', 'assets.tar.gz')
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
        $this->checkTargetDirectory($targetDirectory);

        $outputFile = $targetDirectory . DIRECTORY_SEPARATOR . $input->getOption('backupSQLFilename');
        $dbHost = $this->getContainer()->getParameter('database_host');
        $dbName = $this->getContainer()->getParameter('database_name');
        $dbUser = $this->getContainer()->getParameter('database_user');
        $dbPassword = $this->getContainer()->getParameter('database_password');

        // additional mysqldump parameters by command options
        $options = array();
        if ($input->getOption('backupSQLAddDropDatabase')) {
            $options[] = '--add-drop-database';
        }

        $command = sprintf('mysqldump %s -h %s -u %s -p\'%s\' %s | gzip - > %s',
            implode(' ', $options), $dbHost, $dbUser, $dbPassword, $dbName, $outputFile);

        $output->writeln($translator->trans('running cmd: %command%', array('%command%' => $command)));

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        /* @var $translator Translator */
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
        $this->checkTargetDirectory($targetDirectory);

        $outputFile = $targetDirectory . DIRECTORY_SEPARATOR . $input->getOption('backupAssetsFilename');
        $assetSources = trim(str_replace(',', ' ', $input->getOption('assetSources')));

        if (!$assetSources) {
            $assetSources = '*';
        }

        $command = sprintf('tar -czf %s %s',
            $outputFile, $assetSources);

        $output->writeln($translator->trans('running cmd: %command%', array('%command%' => $command)));

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        /* @var $translator Translator */
        $translator = $this->getContainer()->get('translator');
        $output->writeln($translator->trans('asset backup created: %outputFile%', array('%outputFile%' => $outputFile)));
    }

    /**
     * Check if $targetDirectory exists
     *
     * @param string $targetDirectory
     * @throws \RuntimeException
     */
    protected function checkTargetDirectory($targetDirectory) {
        /* @var $translator Translator */
        $translator = $this->getContainer()->get('translator');

        $fileSystem = new Filesystem();
        if (!$fileSystem->exists($targetDirectory)) {
            throw new \RuntimeException(
                $translator->trans(
                    'target directory %targetDirectory% does not exist.',
                    array(
                        '%targetDirectory%' => $targetDirectory
                    )
                ));
        }
    }

}
