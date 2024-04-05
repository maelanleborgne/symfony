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
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\DependencyInjection\Compiler\RegisterFactoryPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\AutoconfigureFailedException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Factory as FactoryFixtures;
use Symfony\Component\DependencyInjection\Tests\Fixtures\Factory\FactoryService;

/**
 * @phpstan-type TagArguments array{class?: string, service?: string, method?: string, expression?: string, arguments?: array}
 */
class RegisterFactoryPassTest extends TestCase
{
    public function testMultipleAttributesOnSameClass(): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', FactoryFixtures\MultipleAttributeClass::class)
            ->addTag('container.from_factory_attribute');

        $this->expectException(LogicException::class);

        (new RegisterFactoryPass())->process($container);
    }

    public function testConflictWithAutoconfigure(): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', FactoryFixtures\AutoconfigureAttributeClass::class)
            ->addTag('container.from_factory_attribute');

        $this->expectException(AutoconfigureFailedException::class);

        (new RegisterFactoryPass())->process($container);
    }

    /**
     * @dataProvider provideSelfFactory
     *
     * @param class-string $class
     * @param array{string, string} $expectedFactory
     */
    public function testSelfFactory(string $class, array $expectedFactory, array $expectedArguments, string $instanceValidationKey): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', $class)
            ->setPublic(true)
            ->addTag('container.from_factory_attribute');

        $container->compile();
        $definition = $container->getDefinition('foo');
        $this->assertEquals($expectedFactory, $definition->getFactory());
        $this->assertEquals($expectedArguments, $definition->getArguments());
        $instance = $container->get('foo');
        $this->assertEquals($instanceValidationKey, $instance->getInstanceValidationKey());
    }

    /**
     * @return list<array{0: class-string, 1: array{string, string}, 2: array, 3: string}>
     */
    public static function provideSelfFactory(): array
    {
        return [
            [FactoryFixtures\SelfEmptyOnMethod::class, [FactoryFixtures\SelfEmptyOnMethod::class, 'create'], [], 'self_create'],
            [FactoryFixtures\SelfWithMethod::class, [FactoryFixtures\SelfWithMethod::class, 'create'], [], 'self_create'],
            [FactoryFixtures\SelfWithClassAndMethod::class, [FactoryFixtures\SelfWithClassAndMethod::class, 'create'], [], 'self_create'],
            [FactoryFixtures\SelfWithMethodAndArgs::class, [FactoryFixtures\SelfWithMethodAndArgs::class, 'create'], [123456, 'bar'], 'self_create123456bar'],
        ];
    }

    /**
     * @dataProvider provideServiceFactory
     *
     * @param class-string                   $class
     * @param array{Reference, string} $expectedFactory
     */
    public function testServiceFactory(string $class, array $expectedFactory, array $expectedArguments, string $instanceValidationKey): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', $class)
            ->setPublic(true)
            ->addTag('container.from_factory_attribute');
        $container->register('factory_service_id', FactoryService::class)
            ->setPublic(true);

        $container->compile();
        $definition = $container->getDefinition('foo');
        $this->assertEquals($expectedFactory, $definition->getFactory());
        $this->assertEquals($expectedArguments, $definition->getArguments());
        $instance = $container->get('foo');
        $this->assertEquals($instanceValidationKey, $instance->getInstanceValidationKey());
    }
    /**
     * @return list<array{0: class-string, 1: array{Reference, string}, 2: array, 3: string}>
     */
    public static function provideServiceFactory(): array
    {
        return [
            [FactoryFixtures\ServiceAndMethod::class, [new Reference('factory_service_id'), 'create'], ['targetClass' => FactoryFixtures\ServiceAndMethod::class], 'service_create'],
            [FactoryFixtures\ServiceWithAtPrefixAndMethodAndArguments::class, [new Reference('factory_service_id'), 'create'], ['arg1', 123456, FactoryFixtures\ServiceWithAtPrefixAndMethodAndArguments::class], 'service_createarg1123456'],
            [FactoryFixtures\ServiceNoMethodAndArguments::class, [new Reference('factory_service_id'), '__invoke'], ['arg1', 123456, FactoryFixtures\ServiceNoMethodAndArguments::class], 'service_invokearg1123456'],
            [FactoryFixtures\ServiceReferenceNoMethodAndArguments::class, [new Reference('factory_service_id'), '__invoke'], ['arg1', 123456, FactoryFixtures\ServiceReferenceNoMethodAndArguments::class], 'service_invokearg1123456'],
        ];
    }

    /**
     * @dataProvider provideExpressionFactory
     *
     * @param class-string $class
     */
    public function testExpressionFactory(string $class, string $expectedFactory, array $expectedArguments, string $instanceValidationKey): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', $class)
            ->setPublic(true)
            ->addTag('container.from_factory_attribute');
        $container->register('factory_service_id', FactoryService::class)
            ->setPublic(true);
        (new RegisterFactoryPass())->process($container);

        $container->compile();
        $definition = $container->getDefinition('foo');
        $this->assertEquals($expectedFactory, $definition->getFactory());
        $this->assertEquals($expectedArguments, $definition->getArguments());
        $instance = $container->get('foo');
        $this->assertEquals($instanceValidationKey, $instance->getInstanceValidationKey());
    }

    /**
     * @return list<array{0: class-string, 1: string, 2: array, 3: string}>
     */
    public static function provideExpressionFactory(): array
    {
        return [
            [
                FactoryFixtures\ExpressionAsString::class,
                '@=arg(0) + arg(1) > 10 ? service("factory_service_id").create(arg(0), arg(1), arg(2)) : service("factory_service_id").__invoke(arg(0), arg(1), arg(2))',
                [5, 9, FactoryFixtures\ExpressionAsString::class],
                'service_create59',
            ],
            [
                FactoryFixtures\ExpressionAsStringWithAtPrefix::class,
                '@=arg(0) + arg(1) > 10 ? service("factory_service_id").create(arg(0), arg(1), arg(2)) : service("factory_service_id").__invoke(arg(0), arg(1), arg(2))',
                [2, 4, FactoryFixtures\ExpressionAsStringWithAtPrefix::class],
                'service_invoke24',
            ],
            [
                FactoryFixtures\ExpressionAsTypedExpression::class,
                '@=arg(0) + arg(1) > 10 ? service("factory_service_id").create(arg(0), arg(1), arg(2)) : service("factory_service_id").__invoke(arg(0), arg(1), arg(2))',
                [3, 8, FactoryFixtures\ExpressionAsTypedExpression::class],
                'service_create38',
            ],
        ];
    }

    public function testParameterArgument(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/path/to/project');
        $container->register('foo', FactoryFixtures\WithParameterArgument::class)
            ->setPublic(true)
            ->addTag('container.from_factory_attribute');

        (new RegisterFactoryPass())->process($container);
        $container->compile();
        $instance = $container->get('foo');
        $this->assertEquals('/path/to/project', $instance->args[0]); // from type parameter
        $this->assertEquals('/path/to/project', $instance->args[1]); // from %kernel.project_dir%
    }

    public function testIteratorArgument(): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', FactoryFixtures\WithIteratorArgument::class)
            ->setPublic(true)
            ->addTag('container.from_factory_attribute');

        (new RegisterFactoryPass())->process($container);
        $container->compile();
        $instance = $container->get('foo');
        $iterator = $instance->args[0];
        $this->assertInstanceOf(RewindableGenerator::class, $iterator);
        $array = iterator_to_array($iterator);
        $this->assertEquals('value 1', $array[0]);
        $this->assertEquals('value 2', $array[1]);
    }

    public function testServiceClosureArgument(): void
    {
        $container = new ContainerBuilder();
        $container->register('other_service', FactoryService::class);
        $container->register('foo', FactoryFixtures\WithServiceClosureArgument::class)
            ->setPublic(true)
            ->addTag('container.from_factory_attribute');

        (new RegisterFactoryPass())->process($container);
        $container->compile();
        $instance = $container->get('foo');
        $serviceClosure = $instance->args[0];
        $this->assertInstanceOf(\Closure::class, $serviceClosure);
        $this->assertInstanceOf(FactoryService::class, $serviceClosure());
    }

    public function testServiceLocatorArgument(): void
    {
        $container = new ContainerBuilder();
        $container->register('other_service', FactoryService::class);
        $container->register('foo', FactoryFixtures\WithServiceLocatorArgument::class)
            ->setPublic(true)
            ->addTag('container.from_factory_attribute');

        (new RegisterFactoryPass())->process($container);
        $container->compile();
        $instance = $container->get('foo');
        $serviceLocator = $instance->args[0];
        $this->assertInstanceOf(ServiceLocator::class, $serviceLocator);
        $this->assertInstanceOf(FactoryService::class, $serviceLocator->get('other_service'));
    }

    public function testTaggedIteratorArgument(): void
    {
        $container = new ContainerBuilder();
        $container->register('other_service', FactoryService::class)
            ->addTag('custom_tag');
        $container->register('foo', FactoryFixtures\WithTaggedIteratorArgument::class)
            ->setPublic(true)
            ->addTag('container.from_factory_attribute');

        (new RegisterFactoryPass())->process($container);
        $container->compile();
        $instance = $container->get('foo');
        $iterator = $instance->args[0];
        $this->assertInstanceOf(RewindableGenerator::class, $iterator);
        $array = iterator_to_array($iterator);
        $this->assertInstanceOf(FactoryService::class, $array[0]);
    }
}
