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
use Khill\Duration\Duration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Stopwatch\Stopwatch;

class FileUsageWarmupCommand extends Command
{
    protected static $defaultName = 'contao_file_usage:warmup';

    private $fileUsageFinder;
    private $stopwatch;

    public function __construct(FileUsageFinderInterface $fileUsageFinder, ?Stopwatch $stopwatch)
    {
        $this->fileUsageFinder = $fileUsageFinder;
        $this->stopwatch = $stopwatch;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Builds the file usage reference cache.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $style = new SymfonyStyle($input, $output);
        $style->section('Warming up the file usage cache …');

        if ($this->stopwatch) {
            $this->stopwatch->start('fileusage');
        }

        $this->fileUsageFinder->find();

        if ($this->stopwatch) {
            $event = $this->stopwatch->stop('fileusage');
            $style->success('Done in '.(new Duration(round($event->getDuration() / 1000, 3)))->humanize().'!');
        }

        return 0;
    }
}
