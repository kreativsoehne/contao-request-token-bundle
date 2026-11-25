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

namespace KreativSoehne\ContaoRequestTokenBundle\ContaoManager;

use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use KreativSoehne\ContaoRequestTokenBundle\ContaoRequestTokenBundle;

/**
 * Registers the bundle in the Contao Managed Edition, so that "composer require"
 * is all an installation needs.
 */
final class Plugin implements BundlePluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [
            // As a string: the core bundle is a requirement of the project, not of this package.
            BundleConfig::create(ContaoRequestTokenBundle::class)->setLoadAfter(['Contao\CoreBundle\ContaoCoreBundle']),
        ];
    }
}
