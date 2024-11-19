<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Result;

interface ResultInterface
{
    /**
     * The Twig template to use when rendering the result in the back end.
     */
    public function getTemplate(): string;
}
