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

namespace Woit\SiRecentContentWidget\Tests\Unit\Widgets;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\View\BackendViewFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Dashboard\Widgets\AdditionalCssInterface;
use TYPO3\CMS\Dashboard\Widgets\RequestAwareWidgetInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use Woit\SiRecentContentWidget\Widgets\RecentContentWidget;

final class RecentContentWidgetTest extends TestCase
{
    private const NOW = 1_800_000_000;
    private const DAY = 86400;

    private mixed $execTimeBackup = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->execTimeBackup = $GLOBALS['EXEC_TIME'] ?? null;
        $GLOBALS['EXEC_TIME'] = self::NOW;
    }

    protected function tearDown(): void
    {
        $GLOBALS['EXEC_TIME'] = $this->execTimeBackup;
        parent::tearDown();
    }

    #[Test]
    public function implementsWidgetInterfacesAvailableInTypo3V13AndV14(): void
    {
        $widget = $this->createWidget();

        self::assertInstanceOf(WidgetInterface::class, $widget);
        self::assertInstanceOf(RequestAwareWidgetInterface::class, $widget);
        self::assertInstanceOf(AdditionalCssInterface::class, $widget);
    }

    #[Test]
    public function optionsDefaultToFiveElementsAndRefreshButton(): void
    {
        self::assertSame(['limit' => 5, 'refreshAvailable' => true], $this->createWidget()->getOptions());
    }

    #[Test]
    public function optionsFromServiceConfigurationOverrideDefaults(): void
    {
        $widget = $this->createWidget(options: ['limit' => 12, 'refreshAvailable' => false]);

        self::assertSame(['limit' => 12, 'refreshAvailable' => false], $widget->getOptions());
    }

    #[Test]
    public function cssFilesPointToExistingStylesheet(): void
    {
        $cssFiles = $this->createWidget()->getCssFiles();

        self::assertSame(['EXT:si_recent_content_widget/Resources/Public/Css/recent-content-widget.css'], $cssFiles);
        self::assertFileExists(dirname(__DIR__, 3) . '/Resources/Public/Css/recent-content-widget.css');
    }

    #[Test]
    #[DataProvider('nonPositiveLimits')]
    public function nonPositiveLimitReturnsNothingWithoutQuerying(int $limit): void
    {
        $widget = $this->createTestableWidget(rows: [$this->row(1)]);

        self::assertSame([], $widget->callGetRecentContent($limit));
        self::assertSame([], $widget->batchCalls);
    }

    public static function nonPositiveLimits(): array
    {
        return ['zero' => [0], 'negative' => [-3]];
    }

    #[Test]
    public function skipsElementsOnPagesWithoutShowAccess(): void
    {
        $widget = $this->createTestableWidget(
            rows: [$this->row(1, pid: 10), $this->row(2, pid: 20), $this->row(3, pid: 10)],
            pageAccess: [10 => ['show' => false, 'editContent' => false], 20 => ['show' => true, 'editContent' => true]],
        );

        $uids = array_column($widget->callGetRecentContent(5), 'uid');

        self::assertSame([2], $uids);
    }

    #[Test]
    public function stopsAtLimitAndKeepsQueryOrder(): void
    {
        $widget = $this->createTestableWidget(rows: [$this->row(7), $this->row(3), $this->row(9), $this->row(1)]);

        $uids = array_column($widget->callGetRecentContent(2), 'uid');

        self::assertSame([7, 3], $uids);
        self::assertCount(1, $widget->batchCalls);
    }

    #[Test]
    public function loadsNextBatchWhenFullBatchHadTooFewAccessibleElements(): void
    {
        // First batch (100 rows) only contains inaccessible pages, the accessible
        // element is the first row of the second batch.
        $rows = [];
        for ($uid = 1; $uid <= 100; $uid++) {
            $rows[] = $this->row($uid, pid: 99);
        }
        $rows[] = $this->row(101, pid: 1);
        $widget = $this->createTestableWidget(
            rows: $rows,
            pageAccess: [99 => ['show' => false, 'editContent' => false], 1 => ['show' => true, 'editContent' => true]],
        );

        $uids = array_column($widget->callGetRecentContent(5), 'uid');

        self::assertSame([101], $uids);
        self::assertSame([[100, 0], [100, 100]], $widget->batchCalls);
    }

    #[Test]
    public function stopsQueryingWhenBatchIsNotFull(): void
    {
        $widget = $this->createTestableWidget(rows: [$this->row(1), $this->row(2)]);

        $widget->callGetRecentContent(5);

        self::assertSame([[100, 0]], $widget->batchCalls);
    }

    #[Test]
    #[DataProvider('badgeCases')]
    public function calculatesBadges(array $fields, array $expectedBadges): void
    {
        $widget = $this->createTestableWidget(rows: [$this->row(1, ...$fields)]);

        $element = $widget->callGetRecentContent(1)[0];

        self::assertSame($expectedBadges, $element['badges']);
    }

    public static function badgeCases(): array
    {
        $now = self::NOW;
        $day = self::DAY;

        return [
            'old visible element has no badge' => [
                ['crdate' => $now - 10 * $day],
                ['new' => false, 'visibleInFuture' => false, 'visibleInPast' => false],
            ],
            'created within two days is new' => [
                ['crdate' => $now - $day],
                ['new' => true, 'visibleInFuture' => false, 'visibleInPast' => false],
            ],
            'created exactly two days ago is still new' => [
                ['crdate' => $now - 2 * $day],
                ['new' => true, 'visibleInFuture' => false, 'visibleInPast' => false],
            ],
            'created more than two days ago is not new' => [
                ['crdate' => $now - 2 * $day - 1],
                ['new' => false, 'visibleInFuture' => false, 'visibleInPast' => false],
            ],
            'starttime in future' => [
                ['crdate' => $now - 10 * $day, 'starttime' => $now + 60],
                ['new' => false, 'visibleInFuture' => true, 'visibleInPast' => false],
            ],
            'endtime in past' => [
                ['crdate' => $now - 10 * $day, 'endtime' => $now - 60],
                ['new' => false, 'visibleInFuture' => false, 'visibleInPast' => true],
            ],
            'endtime zero means no end' => [
                ['crdate' => $now - 10 * $day, 'endtime' => 0],
                ['new' => false, 'visibleInFuture' => false, 'visibleInPast' => false],
            ],
            'hidden element gets no schedule badges' => [
                ['crdate' => $now - 10 * $day, 'hidden' => 1, 'starttime' => $now + 60, 'endtime' => $now - 60],
                ['new' => false, 'visibleInFuture' => false, 'visibleInPast' => false],
            ],
        ];
    }

    #[Test]
    #[DataProvider('editableCases')]
    public function editableOnlyWithTablePermissionPagePermissionAndRecordAccess(
        bool $tablesModify,
        bool $editContent,
        bool $recordAccess,
        bool $expected,
    ): void {
        $widget = $this->createTestableWidget(
            rows: [$this->row(1)],
            pageAccess: [1 => ['show' => true, 'editContent' => $editContent]],
            tablesModify: $tablesModify,
            recordAccess: $recordAccess,
        );

        self::assertSame($expected, $widget->callGetRecentContent(1)[0]['isEditable']);
    }

    public static function editableCases(): array
    {
        return [
            'all permissions' => [true, true, true, true],
            'no tables_modify for tt_content' => [false, true, true, false],
            'no content edit permission on page' => [true, false, true, false],
            'no record access (e.g. language or field restriction)' => [true, true, false, false],
        ];
    }

    #[Test]
    public function addsTypeLabelAndPageModuleUrlToEachElement(): void
    {
        $widget = $this->createTestableWidget(rows: [$this->row(4, pid: 8)]);

        $element = $widget->callGetRecentContent(1)[0];

        self::assertSame('Label for text', $element['typeLabel']);
        self::assertSame('/page/8#element-tt_content-4', $element['pageModuleUrl']);
    }

    #[Test]
    public function pageModuleUrlSelectsElementLanguageAndJumpsToElement(): void
    {
        $uriBuilder = $this->createMock(UriBuilder::class);
        $uriBuilder->expects(self::once())
            ->method('buildUriFromRoute')
            ->with('web_layout', ['id' => 42, 'languages' => [2]])
            ->willReturn(new Uri('/typo3/module/web/layout?id=42'));
        $widget = $this->createWidget(uriBuilder: $uriBuilder);

        $url = $this->callProtected($widget, 'getPageModuleUrl', ['uid' => 17, 'pid' => 42, 'sys_language_uid' => 2]);

        self::assertSame('/typo3/module/web/layout?id=42#element-tt_content-17', $url);
    }

    #[Test]
    public function pageModuleUrlForAllLanguagesElementForcesNoLanguage(): void
    {
        $uriBuilder = $this->createMock(UriBuilder::class);
        $uriBuilder->expects(self::once())
            ->method('buildUriFromRoute')
            ->with('web_layout', ['id' => 42])
            ->willReturn(new Uri('/typo3/module/web/layout?id=42'));
        $widget = $this->createWidget(uriBuilder: $uriBuilder);

        $url = $this->callProtected($widget, 'getPageModuleUrl', ['uid' => 17, 'pid' => 42, 'sys_language_uid' => -1]);

        self::assertSame('/typo3/module/web/layout?id=42#element-tt_content-17', $url);
    }

    private function createWidget(?UriBuilder $uriBuilder = null, array $options = []): RecentContentWidget
    {
        return new RecentContentWidget(
            $this->createBackendViewFactory(),
            $this->createStub(ConnectionPool::class),
            $uriBuilder ?? $this->createStub(UriBuilder::class),
            $this->createStub(WidgetConfigurationInterface::class),
            $options,
        );
    }

    /**
     * @param list<array<string, mixed>> $rows rows in query order (tstamp DESC)
     * @param array<int, array{show: bool, editContent: bool}> $pageAccess default: every page fully accessible
     */
    private function createTestableWidget(
        array $rows,
        array $pageAccess = [],
        bool $tablesModify = true,
        bool $recordAccess = true,
    ): TestableRecentContentWidget {
        $backendUser = $this->createMock(BackendUserAuthentication::class);
        $backendUser->method('check')->with('tables_modify', 'tt_content')->willReturn($tablesModify);
        $backendUser->method('recordEditAccessInternals')->willReturn($recordAccess);

        $widget = new TestableRecentContentWidget(
            $this->createBackendViewFactory(),
            $this->createStub(ConnectionPool::class),
            $this->createStub(UriBuilder::class),
            $this->createStub(WidgetConfigurationInterface::class),
        );
        $widget->rows = $rows;
        $widget->pageAccessMap = $pageAccess;
        $widget->backendUser = $backendUser;

        return $widget;
    }

    /**
     * BackendViewFactory is final and only used for rendering, which these
     * tests don't cover, so an uninitialised instance is enough.
     */
    private function createBackendViewFactory(): BackendViewFactory
    {
        return (new \ReflectionClass(BackendViewFactory::class))->newInstanceWithoutConstructor();
    }

    private function row(int $uid, int $pid = 1, mixed ...$fields): array
    {
        return array_merge([
            'uid' => $uid,
            'pid' => $pid,
            'CType' => 'text',
            'header' => 'Element ' . $uid,
            'sys_language_uid' => 0,
            'hidden' => 0,
            'starttime' => 0,
            'endtime' => 0,
            'crdate' => self::NOW - 30 * self::DAY,
            'tstamp' => self::NOW - self::DAY,
        ], $fields);
    }

    private function callProtected(object $object, string $method, mixed ...$arguments): mixed
    {
        return (new \ReflectionMethod($object, $method))->invoke($object, ...$arguments);
    }
}

/**
 * Replaces the database, page permission, TCA and routing lookups with
 * in-memory fixtures so the selection and badge logic can be unit tested.
 */
final class TestableRecentContentWidget extends RecentContentWidget
{
    /** @var list<array<string, mixed>> */
    public array $rows = [];

    /** @var array<int, array{show: bool, editContent: bool}> */
    public array $pageAccessMap = [];

    /** @var list<array{int, int}> */
    public array $batchCalls = [];

    public ?BackendUserAuthentication $backendUser = null;

    public function callGetRecentContent(int $limit): array
    {
        return $this->getRecentContent($limit);
    }

    protected function getRecentElementsBatch(int $limit, int $offset): array
    {
        $this->batchCalls[] = [$limit, $offset];

        return array_slice($this->rows, $offset, $limit);
    }

    protected function getPageAccess(int $pageId): array
    {
        return $this->pageAccessMap[$pageId] ?? ['show' => true, 'editContent' => true];
    }

    protected function getTypeLabel(array $row): string
    {
        return 'Label for ' . $row['CType'];
    }

    protected function getPageModuleUrl(array $row): string
    {
        return '/page/' . $row['pid'] . '#element-tt_content-' . $row['uid'];
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $this->backendUser;
    }
}
