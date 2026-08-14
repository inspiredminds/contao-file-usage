<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Tests\Provider;

use Contao\CoreBundle\Framework\ContaoFramework;
use InspiredMinds\ContaoFileUsage\Provider\FilesystemProvider;
use InspiredMinds\ContaoFileUsage\Result\FilesystemResult;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class FilesystemProviderTest extends TestCase
{
    private const UUID = '58ca4a90-2d30-11e4-8c21-0800200c9a66';

    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/contao-file-usage-'.bin2hex(random_bytes(8));

        (new Filesystem())->mkdir($this->projectDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    public function testFindsInsertTagsWithinTheConfiguredPaths(): void
    {
        $this->givenFile('templates/foo.html5', "<div>\n{{image::".self::UUID."?width=1}}\n</div>");

        $results = $this->getProvider(['templates'])->find();

        $this->assertTrue($results->hasResults());
        $this->assertCount(1, $results[self::UUID]);

        $result = iterator_to_array($results[self::UUID])[0];

        $this->assertInstanceOf(FilesystemResult::class, $result);
        $this->assertSame('templates/foo.html5', $result->getPath());
        $this->assertSame(2, $result->getLine());
    }

    public function testFindsReferencesWithWindowsLineEndings(): void
    {
        $this->givenFile('templates/foo.html5', "<div>\r\n{{file::".self::UUID."}}\r\n</div>");

        $result = iterator_to_array($this->getProvider(['templates'])->find()[self::UUID])[0];

        $this->assertSame(2, $result->getLine());
    }

    public function testFindsReferencesInTheLastLineWithoutTrailingNewline(): void
    {
        $this->givenFile('templates/foo.html5', "<div>\n{{file::".self::UUID.'}}');

        $result = iterator_to_array($this->getProvider(['templates'])->find()[self::UUID])[0];

        $this->assertSame(2, $result->getLine());
    }

    public function testHandlesEmptyFiles(): void
    {
        $this->givenFile('templates/foo.html5', '');

        $this->assertFalse($this->getProvider(['templates'])->find()->hasResults());
    }

    public function testIgnoresFilesNotMatchingTheIncludePatterns(): void
    {
        $this->givenFile('templates/foo.txt', '{{file::'.self::UUID.'}}');

        $this->assertFalse($this->getProvider(['templates'])->find()->hasResults());
    }

    public function testIgnoresFilesMatchingTheExcludePatterns(): void
    {
        $this->givenFile('templates/node_modules/foo.html5', '{{file::'.self::UUID.'}}');

        $this->assertFalse($this->getProvider(['templates'])->find()->hasResults());
    }

    public function testIgnoresPathsOutsideOfTheConfiguredOnes(): void
    {
        $this->givenFile('other/foo.html5', '{{file::'.self::UUID.'}}');

        $this->assertFalse($this->getProvider(['templates'])->find()->hasResults());
    }

    public function testAcceptsAbsolutePaths(): void
    {
        $this->givenFile('templates/foo.html5', '{{file::'.self::UUID.'}}');

        $this->assertTrue($this->getProvider([$this->projectDir.'/templates'])->find()->hasResults());
    }

    public function testSkipsPathsThatDoNotExist(): void
    {
        $this->givenFile('templates/foo.html5', '{{file::'.self::UUID.'}}');

        $this->assertTrue($this->getProvider(['templates', 'does/not/exist'])->find()->hasResults());
    }

    public function testReturnsNoResultsWithoutAnyExistingPath(): void
    {
        $this->assertFalse($this->getProvider(['does/not/exist'])->find()->hasResults());
    }

    /**
     * @param list<string> $paths
     */
    private function getProvider(array $paths): FilesystemProvider
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework
            ->expects($this->once())
            ->method('initialize')
        ;

        return new FilesystemProvider(
            $framework,
            $this->projectDir,
            'files',
            $paths,
            ['~\.(twig|html5|css|scss|js|php)$~i'],
            ['~/(node_modules|vendor)/~'],
        );
    }

    private function givenFile(string $path, string $contents): void
    {
        (new Filesystem())->dumpFile($this->projectDir.'/'.$path, $contents);
    }
}
