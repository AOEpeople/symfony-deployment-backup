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
class BackupCommand extends ContainerAwareCommand
{

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
            ->addOption('backupSQL', null, InputOption::VALUE_NONE, 'Create SQL backup.')
            ->addOption('backupSQLFilename', null, InputOption::VALUE_OPTIONAL, 'SQL backup filename.', 'database.sql.gz')
            ->addOption('backupAssets', null, InputOption::VALUE_NONE, 'Create Assets backup.')
            ->addOption('backupDereferenceSymlinks', null, InputOption::VALUE_NONE, 'Dereference Symlinks.')
            ->addOption('backupAssetsFilename', null, InputOption::VALUE_OPTIONAL, 'Assets backup filename.', 'assets.tar.gz')
            ->addOption('changeToDir', null, InputOption::VALUE_OPTIONAL, 'Backup inside desired directory')
            ->addOption('assetSources', null, InputOption::VALUE_REQUIRED, 'Comma separated list of backup asset files/directories.');
    }


    /**
     * Execute the console command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \RuntimeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
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
    protected function createSQLBackup(InputInterface $input, OutputInterface $output)
    {
        /* @var $translator Translator */
        $translator = $this->getContainer()->get('translator');

        $targetDirectory = $input->getArgument('targetDirectory');
        $this->checkTargetDirectory($targetDirectory);

        $outputFile = $targetDirectory . DIRECTORY_SEPARATOR . $input->getOption('backupSQLFilename');
        $dbHost = $this->getContainer()->getParameter('database_host');
        $dbName = $this->getContainer()->getParameter('database_name');
        $dbUser = $this->getContainer()->getParameter('database_user');
        $dbPassword = $this->getContainer()->getParameter('database_password');

        $command = sprintf(
            'mysqldump -h %s -u %s -p\'%s\' %s | gzip - > %s',
            $dbHost, $dbUser, $dbPassword, $dbName, $outputFile
        );

        $output->writeln($translator->trans('running cmd: %command%', array('%command%' => $command)));

        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

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
    protected function createAssetsBackup(InputInterface $input, OutputInterface $output)
    {
        // Possible Tar Options
        $options = [];

        /* @var $translator Translator */
        $translator = $this->getContainer()->get('translator');

        $targetDirectory = $input->getArgument('targetDirectory');
        $this->checkTargetDirectory($targetDirectory);

        $outputFile = $targetDirectory . DIRECTORY_SEPARATOR . $input->getOption('backupAssetsFilename');
        $assetSources = trim(str_replace(',', ' ', $input->getOption('assetSources')));

        if (!$assetSources) {
            $assetSources = '.';
        }

        $dereference = $input->getOption('backupDereferenceSymlinks');
        if ($dereference) {
            $options[] = '--dereference';
        }

        $rootDir = realpath($this->getApplication()->getKernel()->getRootDir() . DIRECTORY_SEPARATOR . '..');
        $changeToDir = $input->getOption('changeToDir');
        if ($changeToDir) {
            $options[] = sprintf('--directory %s', $rootDir . DIRECTORY_SEPARATOR . $changeToDir);
        }

        $command = sprintf('cd %s && tar -czf %s %s %s', $rootDir, $outputFile, implode(' ', $options), $assetSources);

        $output->writeln($translator->trans('running cmd: %command%', array('%command%' => $command)));

        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }

        $output->writeln($translator->trans('asset backup created: %outputFile%', array('%outputFile%' => $outputFile)));
    }

    /**
     * Check if $targetDirectory exists
     *
     * @param string $targetDirectory
     * @throws \RuntimeException
     */
    protected function checkTargetDirectory($targetDirectory)
    {
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
