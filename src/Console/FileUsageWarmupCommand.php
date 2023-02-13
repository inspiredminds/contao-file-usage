<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) inspiredminds
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Console;

use InspiredMinds\ContaoFileUsage\Finder\FileUsageFinderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FileUsageWarmupCommand extends Command
{
    protected static $defaultName = 'contao_file_usage:warmup';

    private $fileUsageFinder;

    public function __construct(FileUsageFinderInterface $fileUsageFinder)
    {
        $this->fileUsageFinder = $fileUsageFinder;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Builds the file usage reference cache.')
            ->addArgument('uuid', InputArgument::OPTIONAL, 'The UUID of a file to find references for.')
            ->addOption('skip-cached', null, InputOption::VALUE_NONE, 'Skips files that are already cached.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $style = new SymfonyStyle($input, $output);
        $useCache = $input->getOption('skip-cached');

        if ($uuid = $input->getArgument('uuid')) {
            $style->section('Searching for references of '.$uuid.' …');

            $this->fileUsageFinder->find($uuid, $useCache);
        } else {
            $style->section('Searching for all file references …');

            $this->fileUsageFinder
                ->setOutputStyle($style)
                ->findAll($useCache)
            ;
        }

        $style->success('Done!');

        return 0;
    }
}
