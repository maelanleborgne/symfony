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
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @phpstan-type TagArguments array{class?: string, service?: string, method?: string, expression?: string, arguments?: array}
 */
class RegisterFactoryPassTest extends TestCase
{
    /**
     * @dataProvider provideInvalid
     *
     * @param TagArguments             $tagArguments
     * @param class-string<\Throwable> $exceptionClass
     */
    public function testInvalid(array $tagArguments, string $exceptionClass): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', FactoryInstantiatedService::class)
            ->addTag('container.from_factory', $tagArguments);

        $this->expectException($exceptionClass);

        (new RegisterFactoryPass())->process($container);
    }

    /**
     * @return list<array{0: TagArguments, 1: class-string<\Throwable>}>
     */
    public static function provideInvalid(): array
    {
        return [
            // No method, no arguments
            [[], LogicException::class],
            [['class' => FactoryInstantiatedService::class, 'service' => 'service_id'], LogicException::class],
            [['class' => FactoryInstantiatedService::class, 'expression' => 'expression'], LogicException::class],
            [['service' => 'service_id', 'expression' => 'expression'], LogicException::class],
            [['service' => 'service_id', 'class' => FactoryInstantiatedService::class], LogicException::class],
            [['service' => 'service_id', 'class' => FactoryInstantiatedService::class, 'expression' => 'expression'], LogicException::class],
            // No arguments
            [['method' => 'create', 'expression' => 'expression'], LogicException::class],
            [['method' => 'create', 'service' => 'service_id', 'expression' => 'expression'], LogicException::class],
            [['method' => 'create', 'class' => FactoryInstantiatedService::class, 'expression' => 'expression'], LogicException::class],
            [['method' => 'create', 'service' => 'service_id', 'class' => FactoryInstantiatedService::class], LogicException::class],
            [['method' => 'create', 'service' => 'service_id', 'class' => FactoryInstantiatedService::class, 'expression' => 'expression'], LogicException::class],
            // No method
            [['arguments' => ['arg1', 'arg2'], 'service' => 'service_id', 'expression' => 'expression'], LogicException::class],
            [['arguments' => ['arg1', 'arg2'], 'class' => FactoryInstantiatedService::class, 'expression' => 'expression'], LogicException::class],
            [['arguments' => ['arg1', 'arg2'], 'service' => 'service_id', 'class' => FactoryInstantiatedService::class], LogicException::class],
            [['arguments' => ['arg1', 'arg2'], 'service' => 'service_id', 'class' => FactoryInstantiatedService::class, 'expression' => 'expression'], LogicException::class],
            // With methods and arguments
            [['arguments' => ['arg1', 'arg2'], 'method' => 'create', 'service' => 'service_id', 'expression' => 'expression'], LogicException::class],
            [['arguments' => ['arg1', 'arg2'], 'method' => 'create', 'class' => FactoryInstantiatedService::class, 'expression' => 'expression'], LogicException::class],
            [['arguments' => ['arg1', 'arg2'], 'method' => 'create', 'service' => 'service_id', 'class' => FactoryInstantiatedService::class], LogicException::class],
            [['arguments' => ['arg1', 'arg2'], 'method' => 'create', 'service' => 'service_id', 'class' => FactoryInstantiatedService::class, 'expression' => 'expression'], LogicException::class],
        ];
    }

    /**
     * @dataProvider provideSelfFactory
     *
     * @param TagArguments                 $tagArguments
     * @param array{string, string} $expectedFactory
     */
    public function testSelfFactory(array $tagArguments, array $expectedFactory, array $expectedArguments, string $instanceValidationKey): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', FactoryInstantiatedService::class)
            ->setPublic(true)
            ->addTag('container.from_factory', $tagArguments);

        (new RegisterFactoryPass())->process($container);

        $definition = $container->getDefinition('foo');
        $this->assertEquals($expectedFactory, $definition->getFactory());
        $this->assertEquals($expectedArguments, $definition->getArguments());
        $container->compile();
        $instance = $container->get('foo');
        $this->assertEquals($instanceValidationKey, $instance->getInstanceValidationKey());
    }

    /**
     * @return list<array{0: TagArguments, 1: array{string, string}, 2: array, 3: string}>
     */
    public static function provideSelfFactory(): array
    {
        return [
            [['method' => 'create'], [FactoryInstantiatedService::class, 'create'], [], 'self_create'],
            [['method' => 'create', 'class' => FactoryInstantiatedService::class], [FactoryInstantiatedService::class, 'create'], [], 'self_create'],
            [['method' => 'create', 'class' => FactoryInstantiatedService::class, 'arguments' => [123456, '$foo' => 'bar']], [FactoryInstantiatedService::class, 'create'], [123456, '$foo' => 'bar'], 'self_create123456bar'],
        ];
    }

    /**
     * @dataProvider provideServiceFactory
     *
     * @param TagArguments                 $tagArguments
     * @param array{Reference, string} $expectedFactory
     */
    public function testServiceFactory(array $tagArguments, array $expectedFactory, array $expectedArguments, string $instanceValidationKey): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', FactoryInstantiatedService::class)
            ->setPublic(true)
            ->addTag('container.from_factory', $tagArguments);
        $container->register('factory_service_id', FactoryService::class);
        (new RegisterFactoryPass())->process($container);

        $definition = $container->getDefinition('foo');
        $this->assertEquals($expectedFactory, $definition->getFactory());
        $this->assertEquals($expectedArguments, $definition->getArguments());
        $container->compile();
        $instance = $container->get('foo');
        $this->assertEquals($instanceValidationKey, $instance->getInstanceValidationKey());
    }

    /**
     * @return list<array{0: TagArguments, 1: array{Reference, string}, 2: array, 3: string}>
     */
    public static function provideServiceFactory(): array
    {
        return [
            [['method' => 'create', 'service' => 'factory_service_id'], [new Reference('factory_service_id'), 'create'], [], 'service_create'],
            [['method' => 'create', 'service' => '@factory_service_id', 'arguments' => ['arg1', '$foo' => 123456]], [new Reference('factory_service_id'), 'create'], ['arg1', '$foo' => 123456], 'service_createarg1123456'],
            [['service' => 'factory_service_id', 'arguments' => ['arg1', '$foo' => 123456]], [new Reference('factory_service_id'), '__invoke'], ['arg1', '$foo' => 123456], 'service_invokearg1123456'],
            [['service' => '@factory_service_id', 'arguments' => ['arg1', '$foo' => 123456]], [new Reference('factory_service_id'), '__invoke'], ['arg1', '$foo' => 123456], 'service_invokearg1123456'],
        ];
    }

    /**
     * @dataProvider provideExpressionFactory
     *
     * @param TagArguments                 $tagArguments
     * @param string $expectedFactory
     */
    public function testExpressionFactory(array $tagArguments, string $expectedFactory, array $expectedArguments, string $instanceValidationKey): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', FactoryInstantiatedService::class)
            ->setPublic(true)
            ->addTag('container.from_factory', $tagArguments);
        $container->register('factory_service_id', FactoryService::class)
            ->setPublic(true);
        (new RegisterFactoryPass())->process($container);

        $definition = $container->getDefinition('foo');
        $this->assertEquals($expectedFactory, $definition->getFactory());
        $this->assertEquals($expectedArguments, $definition->getArguments());
        $container->compile();
        $instance = $container->get('foo');
        $this->assertEquals($instanceValidationKey, $instance->getInstanceValidationKey());
    }

    /**
     * @return list<array{0: TagArguments, 1: string, 2: array, 3: string}>
     */
    public static function provideExpressionFactory(): array
    {
        return [
            [
                [
                    'expression' => 'arg(0) + arg(1) > 10 ? service("factory_service_id").create(arg(0), arg(1)) : service("factory_service_id").__invoke(arg(0), arg(1))',
                    'arguments' => [5, 9],
                ],
                '@=arg(0) + arg(1) > 10 ? service("factory_service_id").create(arg(0), arg(1)) : service("factory_service_id").__invoke(arg(0), arg(1))',
                [5, 9],
                'service_create59',
            ],
            [
                [
                    'expression' => '@=arg(0) + arg(1) > 10 ? service("factory_service_id").create(arg(0), arg(1)) : service("factory_service_id").__invoke(arg(0), arg(1))',
                    'arguments' => [2, 4],
                ],
                '@=arg(0) + arg(1) > 10 ? service("factory_service_id").create(arg(0), arg(1)) : service("factory_service_id").__invoke(arg(0), arg(1))',
                [2, 4],
                'service_invoke24',
            ],
        ];
    }

    public function testParameterArgument(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', '/path/to/project');
        $container->register('foo', FactoryInstantiatedService::class)
            ->setPublic(true)
            ->addTag('container.from_factory', ['method' => 'create', 'arguments' => ['"%kernel.project_dir%"']]);

        (new RegisterFactoryPass())->process($container);
        $container->compile();
        $instance = $container->get('foo');
        $this->assertEquals('/path/to/project', $instance->args[0]);
    }

    public function testAbstractArgument(): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', FactoryInstantiatedService::class)
            ->setPublic(true)
            ->addTag('container.from_factory', ['method' => 'create', 'arguments' => ['!abstract "should be defined somehow"']]);
        $this->expectException(RuntimeException::class);

        (new RegisterFactoryPass())->process($container);
        $container->compile();
        $container->get('foo');
    }

    public function testIteratorArgument(): void
    {
        $container = new ContainerBuilder();
        $container->register('foo', FactoryInstantiatedService::class)
            ->setPublic(true)
            ->addTag('container.from_factory', ['method' => 'create', 'arguments' => ['!iterator ["value 1", "value 2"]']]);

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
        $container->register('foo', FactoryInstantiatedService::class)
            ->setPublic(true)
            ->addTag('container.from_factory', ['method' => 'create', 'arguments' => ['!service_closure "@other_service"']]);

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
        $container->register('foo', FactoryInstantiatedService::class)
            ->setPublic(true)
            ->addTag('container.from_factory', ['method' => 'create', 'arguments' => ['!service_locator ["@other_service"]']]);

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
        $container->register('foo', FactoryInstantiatedService::class)
            ->setPublic(true)
            ->addTag('container.from_factory', ['method' => 'create', 'arguments' => ['!tagged_iterator "custom_tag"']]);

        (new RegisterFactoryPass())->process($container);
        $container->compile();
        $instance = $container->get('foo');
        $iterator = $instance->args[0];
        $this->assertInstanceOf(RewindableGenerator::class, $iterator);
        $array = iterator_to_array($iterator);
        $this->assertInstanceOf(FactoryService::class, $array[0]);
    }

    //TODO: test bound arguments ?
}

final class FactoryInstantiatedService
{
    public ?string $factoryName = null;
    public array $args = [];

    public static function create($randomArgName = null, $foo = null): self
    {
        $instance = new self();
        $instance->factoryName = 'self_create';
        $instance->args = [$randomArgName, $foo];

        return $instance;
    }

    public function getInstanceValidationKey(): string
    {
        return $this->factoryName.implode('', $this->args);
    }
}

final class FactoryService
{
    public function __invoke($randomArgName = null, $foo = null): FactoryInstantiatedService
    {
        return $this->createService('service_invoke', [$randomArgName, $foo]);
    }

    public function create($randomArgName = null, $foo = null): FactoryInstantiatedService
    {
        return $this->createService('service_create', [$randomArgName, $foo]);
    }

    private function createService(string $factoryName, array $arguments): FactoryInstantiatedService
    {
        $instance = new FactoryInstantiatedService();
        $instance->factoryName = $factoryName;
        $instance->args = $arguments;

        return $instance;
    }
}
