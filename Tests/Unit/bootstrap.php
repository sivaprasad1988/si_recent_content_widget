<?php

declare(strict_types=1);

/*
 * This file is part of the si_recent_content_widget extension for TYPO3 CMS.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

/*
 * Finds the Composer autoloader of either a standalone checkout of the
 * extension (vendor/ next to it) or of the TYPO3 project it is installed in,
 * and registers the extension and test namespaces.
 */
$candidates = [
    __DIR__ . '/../../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];
foreach ($candidates as $candidate) {
    if (is_file($candidate)) {
        $classLoader = require $candidate;
        $classLoader->addPsr4('Woit\\SiRecentContentWidget\\Tests\\', __DIR__ . '/..');
        $classLoader->addPsr4('Woit\\SiRecentContentWidget\\', __DIR__ . '/../../Classes');
        return;
    }
}

throw new \RuntimeException('Composer autoloader not found. Run "composer install" first.');
