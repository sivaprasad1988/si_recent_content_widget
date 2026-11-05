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
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Woit\SiRecentContentWidget\EventListener;

use TYPO3\CMS\Backend\Controller\Event\ModifyPageLayoutContentEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Page\PageRenderer;

/**
 * The widget's "Show page in backend" link ends in #element-tt_content-{uid}.
 * The page module doesn't scroll to that fragment by itself, so this loads a
 * small module into the page module that scrolls to, focuses and briefly
 * highlights the referenced content element (no-op without such a fragment).
 */
#[AsEventListener(identifier: 'si-recent-content-widget/focus-content-element')]
final readonly class FocusContentElementInPageModule
{
    public function __construct(
        private PageRenderer $pageRenderer,
    ) {}

    public function __invoke(ModifyPageLayoutContentEvent $event): void
    {
        $this->pageRenderer->loadJavaScriptModule('@woit/si-recent-content-widget/page-module-focus.js');
        $this->pageRenderer->addCssFile('EXT:si_recent_content_widget/Resources/Public/Css/page-module-focus.css');
    }
}
