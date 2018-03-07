<?php
namespace Aoe\Deployment\SystemStorageBackupBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Class BackupCommand
 */
class BackupCommand extends AbstractCommand
{

    /**
     * Command configuration
     *
     * @return void
     */
    protected function configure()
    {
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
            ->addOption('assetSources', null, InputOption::VALUE_REQUIRED, 'Comma separated list of backup asset files/directories.')
            ->addOption('ignoreTables', null, InputOption::VALUE_OPTIONAL, 'Comma separated list of tables to ignore.')
            ->addOption('timeout', null, InputOption::VALUE_OPTIONAL, 'Optional backup process timeout (in seconds).');

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
        if ($input->getOption('backupSQL')) {
            $output->writeln('<info>Backup Database</info>');
            $this->createSQLBackup($input, $output);
        }

        if ($input->getOption('backupAssets')) {
            $output->writeln('<info>Backup Assets</info>');
            $this->createAssetsBackup($input, $output);
        }
    }

    /**
     * Create the sql backup
     *
     * @param InputInterface  $input  Input
     * @param OutputInterface $output Output
     * @return void
     * @throws \RuntimeException
     */
    protected function createSQLBackup(InputInterface $input, OutputInterface $output)
    {
        $targetDirectory = $input->getArgument('targetDirectory');
        $this->checkTargetDirectory($targetDirectory);

        $outputFile = $targetDirectory . DIRECTORY_SEPARATOR . $input->getOption('backupSQLFilename');
        $dbHost = $this->getContainer()->getParameter('database_host');
        $dbName = $this->getContainer()->getParameter('database_name');
        $dbUser = $this->getContainer()->getParameter('database_user');
        $dbPassword = $this->getContainer()->getParameter('database_password');

        $ignoreTables = $input->getOption('ignoreTables');
        $ignoreTablesString = '';
        if (!empty($ignoreTables)) {
            $ignoreTablesArray = explode(',', $ignoreTables);
            foreach ($ignoreTablesArray as $tableToIgnore) {
                $ignoreTablesString .= " --ignore-table=$dbName.$tableToIgnore";
            }
            $ignoreTablesString = ltrim($ignoreTablesString);
        }

        $command = sprintf(
            'mysqldump -h %s -u %s -p%s %s %s | gzip - > %s',
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            escapeshellarg($dbPassword),
            escapeshellarg($dbName),
            (($ignoreTablesString) ? escapeshellarg($ignoreTablesString) : ''),
            escapeshellarg($outputFile)
        );

        $this->runCommand($command, $input, $output);

        $output->writeln("<info>sql backup created: $outputFile</info>");
    }

    /**
     * Create the assets backups
     *
     * @param InputInterface  $input  Input
     * @param OutputInterface $output Output
     * @return void
     * @throws \RuntimeException
     */
    protected function createAssetsBackup(InputInterface $input, OutputInterface $output)
    {
        // Possible Tar Options
        $options = [];

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

        $command = sprintf(
            'cd %s && tar -czf %s %s %s',
            escapeshellarg($rootDir),
            escapeshellarg($outputFile),
            implode(' ', $options),
            $assetSources
        );

        $this->runCommand($command, $input, $output);

        $output->writeln("<info>asset backup created: $outputFile</info>");
    }

    /**
     * Check if $targetDirectory exists
     *
     * @param string $targetDirectory Target Directory
     * @return void
     * @throws \RuntimeException
     */
    protected function checkTargetDirectory($targetDirectory)
    {
        $fileSystem = new Filesystem();
        if (!$fileSystem->exists($targetDirectory)) {
            throw new \RuntimeException("target directory $targetDirectory does not exist.");
        }
    }
}
