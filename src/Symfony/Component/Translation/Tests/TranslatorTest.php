<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Translator;

class TranslatorTest extends TestCase
{
    /**
     * @dataProvider      getInvalidLocalesTests
     * @expectedException \Symfony\Component\Translation\Exception\InvalidArgumentException
     */
    public function testConstructorInvalidLocale($locale)
    {
        new Translator($locale);
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testConstructorValidLocale($locale)
    {
        $translator = new Translator($locale);

        $this->assertEquals($locale, $translator->getLocale());
    }

    public function testConstructorWithoutLocale()
    {
        $translator = new Translator(null);

        $this->assertNull($translator->getLocale());
    }

    public function testSetGetLocale()
    {
        $translator = new Translator('en');

        $this->assertEquals('en', $translator->getLocale());

        $translator->setLocale('fr');
        $this->assertEquals('fr', $translator->getLocale());
    }

    /**
     * @dataProvider      getInvalidLocalesTests
     * @expectedException \Symfony\Component\Translation\Exception\InvalidArgumentException
     */
    public function testSetInvalidLocale($locale)
    {
        $translator = new Translator('fr');
        $translator->setLocale($locale);
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testSetValidLocale($locale)
    {
        $translator = new Translator($locale);
        $translator->setLocale($locale);

        $this->assertEquals($locale, $translator->getLocale());
    }

    public function testGetCatalogue()
    {
        $translator = new Translator('en');

        $this->assertEquals(new MessageCatalogue('en'), $translator->getCatalogue());

        $translator->setLocale('fr');
        $this->assertEquals(new MessageCatalogue('fr'), $translator->getCatalogue('fr'));
    }

    public function testGetCatalogueReturnsConsolidatedCatalogue()
    {
        /*
         * This will be useful once we refactor so that different domains will be loaded lazily (on-demand).
         * In that case, getCatalogue() will probably have to load all missing domains in order to return
         * one complete catalogue.
         */

        $locale = 'whatever';
        $translator = new Translator($locale);
        $translator->addLoader('loader-a', new ArrayLoader());
        $translator->addLoader('loader-b', new ArrayLoader());
        $translator->addResource('loader-a', ['foo' => 'foofoo'], $locale, 'domain-a');
        $translator->addResource('loader-b', ['bar' => 'foobar'], $locale, 'domain-b');

        /*
         * Test that we get a single catalogue comprising messages
         * from different loaders and different domains
         */
        $catalogue = $translator->getCatalogue($locale);
        $this->assertTrue($catalogue->defines('foo', 'domain-a'));
        $this->assertTrue($catalogue->defines('bar', 'domain-b'));
    }

    public function testSetFallbackLocales()
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foofoo'], 'en');
        $translator->addResource('array', ['bar' => 'foobar'], 'fr');

        // force catalogue loading
        $translator->trans('bar');

        $translator->setFallbackLocales(['fr']);
        $this->assertEquals('foobar', $translator->trans('bar'));
    }

    public function testSetFallbackLocalesMultiple()
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foo (en)'], 'en');
        $translator->addResource('array', ['bar' => 'bar (fr)'], 'fr');

        // force catalogue loading
        $translator->trans('bar');

        $translator->setFallbackLocales(['fr_FR', 'fr']);
        $this->assertEquals('bar (fr)', $translator->trans('bar'));
    }

    /**
     * @dataProvider      getInvalidLocalesTests
     * @expectedException \Symfony\Component\Translation\Exception\InvalidArgumentException
     */
    public function testSetFallbackInvalidLocales($locale)
    {
        $translator = new Translator('fr');
        $translator->setFallbackLocales(['fr', $locale]);
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testSetFallbackValidLocales($locale)
    {
        $translator = new Translator($locale);
        $translator->setFallbackLocales(['fr', $locale]);
        // no assertion. this method just asserts that no exception is thrown
        $this->addToAssertionCount(1);
    }

    public function testTransWithFallbackLocale()
    {
        $translator = new Translator('fr_FR');
        $translator->setFallbackLocales(['en']);

        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['bar' => 'foobar'], 'en');

        $this->assertEquals('foobar', $translator->trans('bar'));
    }

    /**
     * @dataProvider      getInvalidLocalesTests
     * @expectedException \Symfony\Component\Translation\Exception\InvalidArgumentException
     */
    public function testAddResourceInvalidLocales($locale)
    {
        $translator = new Translator('fr');
        $translator->addResource('array', ['foo' => 'foofoo'], $locale);
    }

    /**
     * @dataProvider getValidLocalesTests
     */
    public function testAddResourceValidLocales($locale)
    {
        $translator = new Translator('fr');
        $translator->addResource('array', ['foo' => 'foofoo'], $locale);
        // no assertion. this method just asserts that no exception is thrown
        $this->addToAssertionCount(1);
    }

    public function testAddResourceAfterTrans()
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());

        $translator->setFallbackLocales(['en']);

        $translator->addResource('array', ['foo' => 'foofoo'], 'en');
        $this->assertEquals('foofoo', $translator->trans('foo'));

        $translator->addResource('array', ['bar' => 'foobar'], 'en');
        $this->assertEquals('foobar', $translator->trans('bar'));
    }

    /**
     * @dataProvider      getTransFileTests
     * @expectedException \Symfony\Component\Translation\Exception\NotFoundResourceException
     */
    public function testTransWithoutFallbackLocaleFile($format, $loader)
    {
        $loaderClass = 'Symfony\\Component\\Translation\\Loader\\'.$loader;
        $translator = new Translator('en');
        $translator->addLoader($format, new $loaderClass());
        $translator->addResource($format, __DIR__.'/fixtures/non-existing', 'en');
        $translator->addResource($format, __DIR__.'/fixtures/resources.'.$format, 'en');

        // force catalogue loading
        $translator->trans('foo');
    }

    /**
     * @dataProvider getTransFileTests
     */
    public function testTransWithFallbackLocaleFile($format, $loader)
    {
        $loaderClass = 'Symfony\\Component\\Translation\\Loader\\'.$loader;
        $translator = new Translator('en_GB');
        $translator->addLoader($format, new $loaderClass());
        $translator->addResource($format, __DIR__.'/fixtures/non-existing', 'en_GB');
        $translator->addResource($format, __DIR__.'/fixtures/resources.'.$format, 'en', 'resources');

        $this->assertEquals('bar', $translator->trans('foo', [], 'resources'));
    }

    public function testTransWithIcuFallbackLocale()
    {
        $translator = new Translator('en_GB');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foofoo'], 'en_GB');
        $translator->addResource('array', ['bar' => 'foobar'], 'en_001');
        $translator->addResource('array', ['baz' => 'foobaz'], 'en');
        $this->assertSame('foofoo', $translator->trans('foo'));
        $this->assertSame('foobar', $translator->trans('bar'));
        $this->assertSame('foobaz', $translator->trans('baz'));
    }

    public function testTransWithIcuVariantFallbackLocale()
    {
        $translator = new Translator('en_GB_scouse');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foofoo'], 'en_GB_scouse');
        $translator->addResource('array', ['bar' => 'foobar'], 'en_GB');
        $translator->addResource('array', ['baz' => 'foobaz'], 'en_001');
        $translator->addResource('array', ['qux' => 'fooqux'], 'en');
        $this->assertSame('foofoo', $translator->trans('foo'));
        $this->assertSame('foobar', $translator->trans('bar'));
        $this->assertSame('foobaz', $translator->trans('baz'));
        $this->assertSame('fooqux', $translator->trans('qux'));
    }

    public function testTransWithIcuRootFallbackLocale()
    {
        $translator = new Translator('az_Cyrl');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foofoo'], 'az_Cyrl');
        $translator->addResource('array', ['bar' => 'foobar'], 'az');
        $this->assertSame('foofoo', $translator->trans('foo'));
        $this->assertSame('bar', $translator->trans('bar'));
    }

    public function testTransWithFallbackLocaleBis()
    {
        $translator = new Translator('en_US');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foofoo'], 'en_US');
        $translator->addResource('array', ['bar' => 'foobar'], 'en');
        $this->assertEquals('foobar', $translator->trans('bar'));
    }

    public function testTransWithFallbackLocaleTer()
    {
        $translator = new Translator('fr_FR');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foo (en_US)'], 'en_US');
        $translator->addResource('array', ['bar' => 'bar (en)'], 'en');

        $translator->setFallbackLocales(['en_US', 'en']);

        $this->assertEquals('foo (en_US)', $translator->trans('foo'));
        $this->assertEquals('bar (en)', $translator->trans('bar'));
    }

    public function testTransNonExistentWithFallback()
    {
        $translator = new Translator('fr');
        $translator->setFallbackLocales(['en']);
        $translator->addLoader('array', new ArrayLoader());
        $this->assertEquals('non-existent', $translator->trans('non-existent'));
    }

    /**
     * @expectedException \Symfony\Component\Translation\Exception\RuntimeException
     */
    public function testWhenAResourceHasNoRegisteredLoader()
    {
        $translator = new Translator('en');
        $translator->addResource('array', ['foo' => 'foofoo'], 'en');

        $translator->trans('foo');
    }

    public function testNestedFallbackCatalogueWhenUsingMultipleLocales()
    {
        $translator = new Translator('fr');
        $translator->setFallbackLocales(['ru', 'en']);

        $translator->getCatalogue('fr');

        $this->assertNotNull($translator->getCatalogue('ru')->getFallbackCatalogue());
    }

    public function testFallbackCatalogueResources()
    {
        $translator = new Translator('en_GB');
        $translator->addLoader('yml', new \Symfony\Component\Translation\Loader\YamlFileLoader());
        $translator->addResource('yml', __DIR__.'/fixtures/empty.yml', 'en_GB');
        $translator->addResource('yml', __DIR__.'/fixtures/resources.yml', 'en');

        // force catalogue loading
        $this->assertEquals('bar', $translator->trans('foo', []));

        $resources = $translator->getCatalogue('en')->getResources();
        $this->assertCount(1, $resources);
        $this->assertContains(__DIR__.\DIRECTORY_SEPARATOR.'fixtures'.\DIRECTORY_SEPARATOR.'resources.yml', $resources);

        $resources = $translator->getCatalogue('en_GB')->getResources();
        $this->assertCount(2, $resources);
        $this->assertContains(__DIR__.\DIRECTORY_SEPARATOR.'fixtures'.\DIRECTORY_SEPARATOR.'empty.yml', $resources);
        $this->assertContains(__DIR__.\DIRECTORY_SEPARATOR.'fixtures'.\DIRECTORY_SEPARATOR.'resources.yml', $resources);
    }

    /**
     * @dataProvider getTransTests
     */
    public function testTrans($expected, $id, $translation, $parameters, $locale, $domain)
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [(string) $id => $translation], $locale, $domain);

        $this->assertEquals($expected, $translator->trans($id, $parameters, $domain, $locale));
    }

    /**
     * @dataProvider      getInvalidLocalesTests
     * @expectedException \Symfony\Component\Translation\Exception\InvalidArgumentException
     */
    public function testTransInvalidLocale($locale)
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['foo' => 'foofoo'], 'en');

        $translator->trans('foo', [], '', $locale);
    }

    /**
     * @dataProvider      getValidLocalesTests
     */
    public function testTransValidLocale($locale)
    {
        $translator = new Translator($locale);
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['test' => 'OK'], $locale);

        $this->assertEquals('OK', $translator->trans('test'));
        $this->assertEquals('OK', $translator->trans('test', [], null, $locale));
    }

    /**
     * @dataProvider getFlattenedTransTests
     */
    public function testFlattenedTrans($expected, $messages, $id)
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', $messages, 'fr', '');

        $this->assertEquals($expected, $translator->trans($id, [], '', 'fr'));
    }

    public function getTransFileTests()
    {
        return [
            ['csv', 'CsvFileLoader'],
            ['ini', 'IniFileLoader'],
            ['mo', 'MoFileLoader'],
            ['po', 'PoFileLoader'],
            ['php', 'PhpFileLoader'],
            ['ts', 'QtFileLoader'],
            ['xlf', 'XliffFileLoader'],
            ['yml', 'YamlFileLoader'],
            ['json', 'JsonFileLoader'],
        ];
    }

    public function getTransTests()
    {
        return [
            ['Symfony est super !', 'Symfony is great!', 'Symfony est super !', [], 'fr', ''],
            ['Symfony est awesome !', 'Symfony is %what%!', 'Symfony est %what% !', ['%what%' => 'awesome'], 'fr', ''],
            ['Symfony est super !', new StringClass('Symfony is great!'), 'Symfony est super !', [], 'fr', ''],
        ];
    }

    public function getFlattenedTransTests()
    {
        $messages = [
            'symfony' => [
                'is' => [
                    'great' => 'Symfony est super!',
                ],
            ],
            'foo' => [
                'bar' => [
                    'baz' => 'Foo Bar Baz',
                ],
                'baz' => 'Foo Baz',
            ],
        ];

        return [
            ['Symfony est super!', $messages, 'symfony.is.great'],
            ['Foo Bar Baz', $messages, 'foo.bar.baz'],
            ['Foo Baz', $messages, 'foo.baz'],
        ];
    }

    public function getInvalidLocalesTests()
    {
        return [
            ['fr FR'],
            ['français'],
            ['fr+en'],
            ['utf#8'],
            ['fr&en'],
            ['fr~FR'],
            [' fr'],
            ['fr '],
            ['fr*'],
            ['fr/FR'],
            ['fr\\FR'],
        ];
    }

    public function getValidLocalesTests()
    {
        return [
            [''],
            [null],
            ['fr'],
            ['francais'],
            ['FR'],
            ['frFR'],
            ['fr-FR'],
            ['fr_FR'],
            ['fr.FR'],
            ['fr-FR.UTF8'],
            ['sr@latin'],
        ];
    }

    /**
     * @requires extension intl
     */
    public function testIntlFormattedDomain()
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());

        $translator->addResource('array', ['some_message' => 'Hello %name%'], 'en');
        $this->assertSame('Hello Bob', $translator->trans('some_message', ['%name%' => 'Bob']));

        $translator->addResource('array', ['some_message' => 'Hi {name}'], 'en', 'messages+intl-icu');
        $this->assertSame('Hi Bob', $translator->trans('some_message', ['%name%' => 'Bob']));
    }
}

class StringClass
{
    protected $str;

    public function __construct($str)
    {
        $this->str = $str;
    }

    public function __toString()
    {
        return $this->str;
    }
}
