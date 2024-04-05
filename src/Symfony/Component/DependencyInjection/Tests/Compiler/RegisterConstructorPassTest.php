<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Constructor;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\RegisterConstructorPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\AutoconfigureFailedException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\TypedReference;

/**
 * @template TagList list<array{name: string, attributes: array<string, string>}>
 */
class RegisterConstructorPassTest extends TestCase
{
    /**
     * @dataProvider provideInvalidTags
     *
     * @param TagList $tags
     */
    public function testProcessInvalidDefinition(array $tags): void
    {
        $container = new ContainerBuilder();
        $definition = $container->register('foo', SelfFactoryClass::class);
        foreach ($tags as $tag) {
            $definition->addTag($tag['name'], $tag['attributes']);
        }
        $this->expectException(AutoconfigureFailedException::class);

        (new RegisterConstructorPass())->process($container);
    }

    /**
     * @return \Generator<string, list<TagList>, null, null>
     */
    public static function provideInvalidTags(): \Generator
    {
        yield 'with_multiple_tags' => [[['name' => 'container.from_constructor', 'attributes' => []], ['name' => 'container.from_constructor', 'attributes' => []]]];
        yield 'with_no_method' => [[['name' => 'container.from_constructor', 'attributes' => []]]];
        yield 'with_non_existant_method' => [[['name' => 'container.from_constructor', 'attributes' => ['method' => 'notARealMethod']]]];
        yield 'with_non_public_method' => [[['name' => 'container.from_constructor', 'attributes' => ['method' => 'protectedCreate']]]];
        yield 'with_non_static_method' => [[['name' => 'container.from_constructor', 'attributes' => ['method' => 'nonStaticCreate']]]];
    }

    public function testProcessUntaggedDefinition(): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', SelfFactoryClass::class);

        (new RegisterConstructorPass())->process($container);
        $definition = $container->getDefinition('foo');

        $this->assertArrayNotHasKey('factory', $definition->getChanges());
        $this->assertEquals(null, $definition->getFactory());
    }

    public function testProcessValidDefinition(): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', SelfFactoryClass::class)
            ->addTag('container.from_constructor', ['method' => 'create']);

        (new RegisterConstructorPass())->process($container);
        $definition = $container->getDefinition('foo');

        $this->assertArrayHasKey('factory', $definition->getChanges());
        $this->assertEquals([null, 'create'], $definition->getFactory());
    }

    public function testAttributeAutoconfiguration(): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', SelfFactoryClass::class)
            ->setPublic(true)
            ->setAutoconfigured(true);
        $container->registerAttributeForAutoconfiguration(Constructor::class, static function (ChildDefinition $definition, Constructor $attribute, \ReflectionMethod $reflector): void {
            if (!$reflector->isStatic()) {
                throw new LogicException(sprintf('Constructor attribute cannot be applied to non-static method "%s::%s".', $reflector->class, $reflector->name));
            }
            $definition->addTag('container.from_constructor', ['method' => $reflector->name]);
        });

        $container->compile();
        $definition = $container->getDefinition('foo');

        $this->assertArrayHasKey('container.from_constructor', $definition->getTags());
        $this->assertArrayHasKey('method', $definition->getTag('container.from_constructor')[0]);
        $this->assertEquals('create', $definition->getTag('container.from_constructor')[0]['method']);
        $this->assertArrayHasKey('factory', $definition->getChanges());
        $this->assertEquals([SelfFactoryClass::class, 'create'], $definition->getFactory());
    }

    public function testAutowireConstructorArguments(): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', SelfFactoryClass::class)
            ->setPublic(true)
            ->setAutowired(true)
            ->setAutoconfigured(true);
        $container->register(AnotherService::class)
            ->setPublic(true);
        $container->setParameter('bar', 'parameter_value');
        $container->registerAttributeForAutoconfiguration(Constructor::class, static function (ChildDefinition $definition, Constructor $attribute, \ReflectionMethod $reflector): void {
            if (!$reflector->isStatic()) {
                throw new LogicException(sprintf('Constructor attribute cannot be applied to non-static method "%s::%s".', $reflector->class, $reflector->name));
            }
            $definition->addTag('container.from_constructor', ['method' => $reflector->name]);
        });

        $container->compile();
        $definition = $container->getDefinition('foo');
        $argument1 = $definition->getArgument(0);
        $argument2 = $definition->getArgument(1);

        $this->assertInstanceOf(TypedReference::class, $argument1);
        $this->assertEquals(AnotherService::class, $argument1->getType());
        $this->assertEquals(AnotherService::class, (string)$argument1);
        $this->assertIsString($argument2);
        $this->assertEquals('parameter_value', $argument2);
    }
}

class SelfFactoryClass
{
    #[Constructor]
    public static function create(AnotherService $argument1, #[Autowire(param: 'bar')] string $argument2): self
    {
        return new self();
    }
    protected static function protectedCreate(): self
    {
        return new self();
    }
    public function nonStaticCreate(): self
    {
        return new self();
    }
}

class AnotherService
{

}
