<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Cron;

use Contao\CoreBundle\Cron\Cron;
use Contao\CoreBundle\Exception\CronExecutionSkippedException;
use Contao\CoreBundle\ServiceAnnotation\CronJob;
use InspiredMinds\ContaoFileUsage\Finder\FileUsageFinderInterface;

/**
 * @CronJob("daily")
 */
class BuildCacheCronJob
{
    public function __construct(private readonly FileUsageFinderInterface $finder)
    {
    }

    public function __invoke(string $scope): void
    {
        if (Cron::SCOPE_CLI !== $scope) {
            if (class_exists(CronExecutionSkippedException::class)) {
                throw new CronExecutionSkippedException();
            }

            return;
        }

        // Build file usage cache
        $this->finder->find();
    }
}
