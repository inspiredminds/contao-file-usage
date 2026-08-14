<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

use Contao\EasyCodingStandard\Fixer\CommentLengthFixer;
use Contao\EasyCodingStandard\Set\SetList;
use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use PhpCsFixer\Fixer\Whitespace\MethodChainingIndentationFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withSets([SetList::CONTAO])
    ->withPaths([
        __DIR__.'/contao',
        __DIR__.'/src',
        __DIR__.'/templates',
        __DIR__.'/ecs.php',
        __DIR__.'/rector.php',
    ])
    ->withSkip([
        MethodChainingIndentationFixer::class => [
            '*/DependencyInjection/Configuration.php',
        ],
        CommentLengthFixer::class,
    ])
    ->withConfiguredRule(HeaderCommentFixer::class, [
        'header' => "This file is part of the Contao File Usage extension.\n\n(c) INSPIRED MINDS\n\n@license LGPL-3.0-or-later",
    ])
    ->withParallel()
    ->withSpacing(lineEnding: "\n")
    ->withCache(sys_get_temp_dir().'/ecs_default_cache')
;
