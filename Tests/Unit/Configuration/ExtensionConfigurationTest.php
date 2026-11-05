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

namespace Woit\SiRecentContentWidget\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;
use Woit\SiRecentContentWidget\Widgets\RecentContentWidget;

/**
 * Guards the consistency of extension key, package name, namespace, file
 * references and labels (e.g. after renaming the extension).
 */
final class ExtensionConfigurationTest extends TestCase
{
    private const EXTENSION_KEY = 'si_recent_content_widget';
    private const PACKAGE_NAME = 'woit/si-recent-content-widget';

    private static function extensionPath(string $path = ''): string
    {
        return dirname(__DIR__, 3) . ($path !== '' ? '/' . $path : '');
    }

    private static function composerJson(): array
    {
        return json_decode((string)file_get_contents(self::extensionPath('composer.json')), true, flags: JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function composerJsonDefinesPackageNameExtensionKeyAndNamespace(): void
    {
        $composer = self::composerJson();

        self::assertSame(self::PACKAGE_NAME, $composer['name']);
        self::assertSame('typo3-cms-extension', $composer['type']);
        self::assertSame(self::EXTENSION_KEY, $composer['extra']['typo3/cms']['extension-key']);
        self::assertSame(['Woit\\SiRecentContentWidget\\' => 'Classes/'], $composer['autoload']['psr-4']);
    }

    #[Test]
    public function composerJsonCreditsMaintainerAndOriginalAuthor(): void
    {
        $roles = array_column(self::composerJson()['authors'], 'role', 'name');

        self::assertSame('Maintainer', $roles['Siva'] ?? null);
        self::assertSame('Original author', $roles['Epixskill'] ?? null);

        $epixskill = array_values(array_filter(self::composerJson()['authors'], static fn(array $author): bool => $author['name'] === 'Epixskill'))[0];
        self::assertArrayNotHasKey('email', $epixskill, 'Support requests go to the maintainer, not the original author');
    }

    #[Test]
    public function extEmconfVersionSupportsTypo3V13AndV14(): void
    {
        $_EXTKEY = self::EXTENSION_KEY;
        $EM_CONF = [];
        require self::extensionPath('ext_emconf.php');

        self::assertSame('13.4.0-14.99.99', $EM_CONF[$_EXTKEY]['constraints']['depends']['typo3']);
        self::assertSame('13.4.0-14.99.99', $EM_CONF[$_EXTKEY]['constraints']['depends']['dashboard']);
    }

    #[Test]
    public function widgetUsesComposerPackageNameForTemplates(): void
    {
        self::assertSame(
            self::PACKAGE_NAME,
            (new \ReflectionClassConstant(RecentContentWidget::class, 'PACKAGE_NAME'))->getValue(),
        );
    }

    #[Test]
    public function servicesYamlRegistersDashboardWidget(): void
    {
        $services = Yaml::parseFile(self::extensionPath('Configuration/Services.yaml'))['services'];
        $widget = $services['dashboard.widget.woit.si_recent_content_widget.recentContent'] ?? null;

        self::assertIsArray($widget);
        self::assertSame(RecentContentWidget::class, $widget['class']);
        self::assertTrue(class_exists($widget['class']));
        $tag = $widget['tags'][0];
        self::assertSame('dashboard.widget', $tag['name']);
        self::assertSame('recentContent', $tag['identifier']);
        self::assertSame('content', $tag['groupNames']);
    }

    #[Test]
    public function javaScriptImportMapPointsToExistingModule(): void
    {
        $config = require self::extensionPath('Configuration/JavaScriptModules.php');

        self::assertSame(
            ['@woit/si-recent-content-widget/' => 'EXT:si_recent_content_widget/Resources/Public/JavaScript/'],
            $config['imports'],
        );
        self::assertFileExists(self::extensionPath('Resources/Public/JavaScript/page-module-focus.js'));
    }

    #[Test]
    public function allExtensionFileReferencesExist(): void
    {
        $references = self::collectReferences('#EXT:' . self::EXTENSION_KEY . '/([A-Za-z0-9_./-]+\.[a-z]+)#');

        self::assertNotEmpty($references);
        foreach ($references as $reference => $usedIn) {
            self::assertFileExists(self::extensionPath($reference), 'Referenced in ' . $usedIn);
        }
    }

    #[Test]
    public function allReferencedLabelsExistInEnglishAndGermanFile(): void
    {
        $keys = self::collectReferences('#LLL:EXT:' . self::EXTENSION_KEY . '/Resources/Private/Language/locallang\.xlf:([A-Za-z0-9_.]+)#');
        $english = self::labelIds('Resources/Private/Language/locallang.xlf');
        $german = self::labelIds('Resources/Private/Language/de.locallang.xlf');

        self::assertNotEmpty($keys);
        foreach ($keys as $key => $usedIn) {
            self::assertContains($key, $english, sprintf('Label "%s" (used in %s) missing in locallang.xlf', $key, $usedIn));
            self::assertContains($key, $german, sprintf('Label "%s" (used in %s) missing in de.locallang.xlf', $key, $usedIn));
        }
    }

    #[Test]
    public function noReferenceToOldExtensionKeyOrVendorRemains(): void
    {
        $old = self::collectReferences('#(EXT:recent_content_widget|Epixskill\\\\RecentContentWidget|@epixskill/)#');

        self::assertSame([], $old);
    }

    /**
     * @return array<string, string> first capture group => file it was found in
     */
    private static function collectReferences(string $pattern): array
    {
        $found = [];
        foreach (['Classes', 'Configuration', 'Resources/Private'] as $directory) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::extensionPath($directory), \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (!in_array($file->getExtension(), ['php', 'yaml', 'html', 'xlf'], true)) {
                    continue;
                }
                preg_match_all($pattern, (string)file_get_contents($file->getPathname()), $matches);
                foreach ($matches[1] as $match) {
                    $found[$match] ??= substr($file->getPathname(), strlen(self::extensionPath()) + 1);
                }
            }
        }
        ksort($found);

        return $found;
    }

    /**
     * @return list<string>
     */
    private static function labelIds(string $file): array
    {
        $xml = simplexml_load_file(self::extensionPath($file));
        self::assertNotFalse($xml, $file . ' is not valid XML');

        return array_map('strval', $xml->xpath('//*[local-name()="trans-unit"]/@id'));
    }
}
