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

namespace Woit\SiRecentContentWidget\Tests\Unit\EventListener;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Page\PageRenderer;
use Woit\SiRecentContentWidget\EventListener\FocusContentElementInPageModule;

final class FocusContentElementInPageModuleTest extends TestCase
{
    #[Test]
    public function loadsFocusModuleAndStylesheetIntoPageModule(): void
    {
        $pageRenderer = $this->createMock(PageRenderer::class);
        $pageRenderer->expects(self::once())
            ->method('loadJavaScriptModule')
            ->with('@woit/si-recent-content-widget/page-module-focus.js');
        $pageRenderer->expects(self::once())
            ->method('addCssFile')
            ->with('EXT:si_recent_content_widget/Resources/Public/Css/page-module-focus.css');

        // The listener doesn't read the event, so an uninitialised (final) event is enough.
        $event = (new \ReflectionClass(ModifyPageLayoutContentEvent::class))->newInstanceWithoutConstructor();

        (new FocusContentElementInPageModule($pageRenderer))($event);
    }

    #[Test]
    public function referencedAssetsExist(): void
    {
        $extensionPath = dirname(__DIR__, 3);

        self::assertFileExists($extensionPath . '/Resources/Public/JavaScript/page-module-focus.js');
        self::assertFileExists($extensionPath . '/Resources/Public/Css/page-module-focus.css');
    }

    #[Test]
    public function isRegisteredAsEventListenerWithPrefixedIdentifier(): void
    {
        $attributes = (new \ReflectionClass(FocusContentElementInPageModule::class))
            ->getAttributes(AsEventListener::class);

        self::assertCount(1, $attributes);
        self::assertSame(
            'si-recent-content-widget/focus-content-element',
            $attributes[0]->getArguments()['identifier'] ?? null,
        );
    }
}
