<?php

declare(strict_types=1);

/*
 * This file is part of the kreativsoehne/contao-request-token-bundle package.
 *
 * (c) Kreativ&Söhne GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KreativSoehne\ContaoRequestTokenBundle\DependencyInjection;

use KreativSoehne\ContaoRequestTokenBundle\EventListener\RequestTokenScriptListener;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\HttpKernel\KernelEvents;

final class ContaoRequestTokenExtension extends Extension
{
    /**
     * @param array<mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->register(RequestTokenScriptListener::class)
            ->addTag('kernel.event_listener', ['event' => KernelEvents::RESPONSE, 'priority' => -100]);
    }
}
