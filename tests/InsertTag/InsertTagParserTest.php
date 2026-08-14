<?php

declare(strict_types=1);

/*
 * This file is part of the Contao File Usage extension.
 *
 * (c) INSPIRED MINDS
 *
 * @license LGPL-3.0-or-later
 */

namespace InspiredMinds\ContaoFileUsage\Tests\InsertTag;

use InspiredMinds\ContaoFileUsage\InsertTag\InsertTagParser;
use PHPUnit\Framework\TestCase;

class InsertTagParserTest extends TestCase
{
    private const UUID = '58ca4a90-2d30-11e4-8c21-0800200c9a66';

    /**
     * @dataProvider provideContentWithSingleReference
     */
    public function testExtractsTheUuidFromAnyInsertTag(string $content): void
    {
        $this->assertSame([self::UUID], InsertTagParser::extractUuids($content));
    }

    public static function provideContentWithSingleReference(): iterable
    {
        yield 'file without parameters' => ['{{file::'.self::UUID.'}}'];
        yield 'image with parameters' => ['{{image::'.self::UUID.'?width=200&height=150}}'];
        yield 'picture with template' => ['{{picture::'.self::UUID.'?size=1&template=picture_default}}'];
        yield 'svg with class' => ['{{svg::'.self::UUID.'?height=80&class=custom-icon}}'];
        yield 'custom tag with underscore' => ['{{foo_bar::'.self::UUID.'?height=80&class=custom-icon}}'];
        yield 'legacy pipe parameters' => ['{{file::'.self::UUID.'|urlattr}}'];
        yield 'additional colon segment' => ['{{svg::'.self::UUID.'::my-class}}'];
        yield 'surrounded by other content' => ['lorem {{image::'.self::UUID.'?width=1}} ipsum'];
        yield 'multi-line figure with bracket parameters' => [
            "{{figure::".self::UUID."?\n"
            ."size=1&\n"
            ."metadata[title]=Mein%20Bild&\n"
            ."enableLightbox=1&\n"
            ."options[attr][class]=main_figure&\n"
            ."template=image\n"
            .'}}',
        ];
    }

    public function testExtractsAllReferencesInOrder(): void
    {
        $content = '{{file::'.self::UUID.'}} some text '
            .'{{svg::a8824458-a08e-11e9-9d96-81cb79fa7a74?height=80}}';

        $this->assertSame(
            [self::UUID, 'a8824458-a08e-11e9-9d96-81cb79fa7a74'],
            InsertTagParser::extractUuids($content),
        );
    }

    /**
     * @dataProvider provideContentWithoutReference
     */
    public function testReturnsNoUuidForNonFileReferences(string $content): void
    {
        $this->assertSame([], InsertTagParser::extractUuids($content));
    }

    public static function provideContentWithoutReference(): iterable
    {
        yield 'empty string' => [''];
        yield 'plain text' => ['Just some text without any insert tag.'];
        yield 'numeric id parameter' => ['{{link_url::123}}'];
        yield 'non-uuid parameter' => ['{{insert_article::an-alias}}'];
        yield 'uppercase tag name' => ['{{FILE::'.self::UUID.'}}'];
        yield 'malformed uuid' => ['{{file::not-a-uuid}}'];
    }
}
