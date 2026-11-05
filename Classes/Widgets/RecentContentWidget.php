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

namespace Woit\SiRecentContentWidget\Widgets;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Dashboard\Widgets\AdditionalCssInterface;
use TYPO3\CMS\Dashboard\Widgets\RequestAwareWidgetInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;

/**
 * Lists the most recently changed tt_content records the current backend user
 * may see (page show permission), with edit / page-module links and badges
 * for new, hidden and scheduled/expired elements.
 *
 * Implements WidgetInterface + RequestAwareWidgetInterface, which exist in
 * TYPO3 v13 and v14 (v14 core widgets use them too), so one class runs on both.
 *
 * Options:
 * - limit             int   number of content elements to show (default 5)
 * - refreshAvailable  bool  show the refresh button (default true)
 */
class RecentContentWidget implements WidgetInterface, RequestAwareWidgetInterface, AdditionalCssInterface
{
    private const PACKAGE_NAME = 'woit/si-recent-content-widget';
    private const BATCH_SIZE = 100;
    private const NEW_THRESHOLD_SECONDS = 60 * 60 * 24 * 2;

    /**
     * @var array{limit: int, refreshAvailable?: bool}
     */
    private array $options;

    /**
     * Per-request cache: page uid => ['show' => bool, 'editContent' => bool]
     *
     * @var array<int, array{show: bool, editContent: bool}>
     */
    private array $pageAccess = [];

    private ?ServerRequestInterface $request = null;

    public function __construct(
        private readonly BackendViewFactory $backendViewFactory,
        private readonly ConnectionPool $connectionPool,
        private readonly UriBuilder $uriBuilder,
        private readonly WidgetConfigurationInterface $configuration,
        array $options = [],
    ) {
        $this->options = array_merge(['limit' => 5, 'refreshAvailable' => true], $options);
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function renderWidgetContent(): string
    {
        $request = $this->request ?? $GLOBALS['TYPO3_REQUEST'];
        $view = $this->backendViewFactory->create($request, [self::PACKAGE_NAME]);
        $view->assignMultiple([
            'options' => $this->options,
            'configuration' => $this->configuration,
            'contentElements' => $this->getRecentContent((int)$this->options['limit']),
        ]);

        return $view->render('RecentContentWidget');
    }

    public function getCssFiles(): array
    {
        return [
            'EXT:si_recent_content_widget/Resources/Public/Css/recent-content-widget.css',
        ];
    }

    protected function getRecentElementsBatch(int $limit, int $offset): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_content');
        // Hidden / scheduled / fe_group restricted elements are wanted here (they get
        // badges), so only deleted records are filtered. Workspace versions are
        // excluded via t3ver_wsid = 0 (live records only).
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());

        return $queryBuilder
            ->select('*')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->gt('pid', 0),
                $queryBuilder->expr()->eq('t3ver_wsid', 0),
            )
            ->orderBy('tstamp', 'DESC')
            ->addOrderBy('uid', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    protected function getRecentContent(int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $backendUser = $this->getBackendUser();
        $canModifyContent = $backendUser->check('tables_modify', 'tt_content');
        $now = (int)$GLOBALS['EXEC_TIME'];

        $elements = [];
        $offset = 0;
        do {
            $results = $this->getRecentElementsBatch(self::BATCH_SIZE, $offset);
            foreach ($results as $row) {
                $access = $this->getPageAccess((int)$row['pid']);
                if (!$access['show']) {
                    continue;
                }

                $hidden = (int)$row['hidden'] === 1;
                $starttime = (int)$row['starttime'];
                $endtime = (int)$row['endtime'];

                $row['isEditable'] = $canModifyContent
                    && $access['editContent']
                    && $backendUser->recordEditAccessInternals('tt_content', $row);
                $row['badges'] = [
                    'new' => $now - (int)$row['crdate'] <= self::NEW_THRESHOLD_SECONDS,
                    'visibleInFuture' => !$hidden && $starttime > $now,
                    'visibleInPast' => !$hidden && $endtime > 0 && $endtime < $now,
                ];
                $row['typeLabel'] = $this->getTypeLabel($row);
                $row['pageModuleUrl'] = $this->getPageModuleUrl($row);

                $elements[] = $row;
                if (count($elements) >= $limit) {
                    break 2;
                }
            }
            $offset += self::BATCH_SIZE;
        } while (count($results) === self::BATCH_SIZE);

        return $elements;
    }

    /**
     * @return array{show: bool, editContent: bool}
     */
    protected function getPageAccess(int $pageId): array
    {
        if (!isset($this->pageAccess[$pageId])) {
            $backendUser = $this->getBackendUser();
            $page = BackendUtility::readPageAccess($pageId, $backendUser->getPagePermsClause(Permission::PAGE_SHOW));
            $this->pageAccess[$pageId] = [
                'show' => is_array($page) && $page !== [],
                'editContent' => is_array($page) && $page !== []
                    && ($backendUser->calcPerms($page) & Permission::CONTENT_EDIT) === Permission::CONTENT_EDIT,
            ];
        }

        return $this->pageAccess[$pageId];
    }

    /**
     * Page module URL that opens the element's page in the element's language and
     * jumps to it: the page module renders every element with
     * id="element-tt_content-{uid}" (EXT:backend PageLayout/Record partial), so the
     * fragment scrolls it into view. "All languages" (-1) elements are shown in
     * the default language column, so no language is forced for them.
     */
    protected function getPageModuleUrl(array $row): string
    {
        $parameters = ['id' => (int)$row['pid']];
        $languageId = (int)$row['sys_language_uid'];
        if ($languageId >= 0) {
            $parameters['languages'] = [$languageId];
        }

        return (string)$this->uriBuilder->buildUriFromRoute('web_layout', $parameters)
            . '#element-tt_content-' . (int)$row['uid'];
    }

    /**
     * Label of the record's CType from TCA (the new content element wizard is
     * TCA-driven since TYPO3 v13, so page TSconfig wizardItems no longer carry
     * the labels). Falls back to the raw CType if TCA doesn't know it.
     */
    protected function getTypeLabel(array $row): string
    {
        $cType = (string)($row['CType'] ?? '');
        $label = (string)BackendUtility::getLabelFromItemlist('tt_content', 'CType', $cType, $row);
        $label = $label !== '' ? $this->getLanguageService()->sL($label) : '';

        return $label !== '' ? $label : $cType;
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
