<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Twig;

use Contao\BackendCustom;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Routing\ScopeMatcher;
use Contao\CoreBundle\Twig\Extension\ContaoTemplateExtension;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class Extension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ContaoFramework $framework,
        private readonly ScopeMatcher $scopeMatcher,
    ) {
    }

    public function getFunctions(): array
    {
        if (class_exists(ContaoTemplateExtension::class)) {
            return [];
        }

        return [
            new TwigFunction('render_contao_backend_template', [$this, 'renderContaoBackendTemplate']),
        ];
    }

    public function renderContaoBackendTemplate(array $blocks = []): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$request || !$this->scopeMatcher->isBackendRequest($request)) {
            return '';
        }

        /** @var BackendCustom $controller */
        $controller = $this->framework->createInstance(BackendCustom::class);
        $template = $controller->getTemplateObject();

        foreach ($blocks as $key => $content) {
            $template->{$key} = $content;
        }

        $response = $controller->run();

        return $response->getContent();
    }
}
