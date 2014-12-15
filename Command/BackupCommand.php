<?php
namespace Acme\DemoBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GreetCommand extends ContainerAwareCommand
{
    protected function configure() {
        $this
            ->setName('aoedeployment:createbackup')
            ->setDescription('Creates SQL/ZIP files as backup')
            ->addArgument('targetDirectory', InputArgument::REQUIRED, 'The directory where the backup-data should be placed in.')
            ->addOption('backupSQL', TRUE, InputOption::VALUE_NONE, 'Create SQL backup.')
            ->addOption('backupAssets', TRUE, InputOption::VALUE_NONE, 'Create Assets backup.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $output->writeln($text);
    }
}
