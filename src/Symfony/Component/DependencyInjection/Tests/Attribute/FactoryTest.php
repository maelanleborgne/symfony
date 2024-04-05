<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Attribute\Factory;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;

class FactoryTest extends TestCase
{
    public function testNoArguments()
    {
        new Factory();
        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider provideInvalidArguments
     *
     * @param array<string, string> $factoryArguments
     * @param class-string<\Throwable> $expectedException
     */
    public function testExceptionOnConstruct(array $factoryArguments, string $expectedException)
    {
        $this->expectException($expectedException);
        new Factory(...$factoryArguments);
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: class-string<\Throwable>}>
     */
    public static function provideInvalidArguments(): array
    {
        return [
            'class_and_service' => [['class' => 'my_class', 'service' => 'my_service'], LogicException::class],
            'class_and_expression' => [['class' => 'my_class', 'expression' => 'my_expression'], LogicException::class],
            'service_and_expression' => [['service' => 'my_service', 'expression' => 'my_expression'], LogicException::class],
            'class_and_service_and_expression' => [['class' => 'my_class', 'service' => 'my_service', 'expression' => 'my_expression'], LogicException::class],
            'expression_and_method' => [['method' => 'static_method', 'expression' => 'my_expression'], LogicException::class],
        ];
    }

    public function testGetFactoryOnNonStaticMethod()
    {
        $class = $this->createMock(\ReflectionClass::class);
        $class->method('getName')->willReturn('MyClass');
        $target = $this->createMock(\ReflectionMethod::class);
        $target->method('isStatic')->willReturn(false);
        $target->method('getDeclaringClass')->willReturn($class);

        $factory = new Factory();
        $this->expectException(LogicException::class);
        $factory->getFactoryForTarget($target);
    }

    public function testGetFactoryOnMethodWithService()
    {
        $class = $this->createMock(\ReflectionClass::class);
        $class->method('getName')->willReturn('MyClass');
        $target = $this->createMock(\ReflectionMethod::class);
        $target->method('isStatic')->willReturn(true);
        $target->method('getDeclaringClass')->willReturn($class);

        $factory = new Factory(service: 'my_service');
        $this->expectException(LogicException::class);
        $factory->getFactoryForTarget($target);
    }

    public function testGetFactoryOnMethodWithExpression()
    {
        $class = $this->createMock(\ReflectionClass::class);
        $class->method('getName')->willReturn('MyClass');
        $target = $this->createMock(\ReflectionMethod::class);
        $target->method('isStatic')->willReturn(true);
        $target->method('getDeclaringClass')->willReturn($class);

        $factory = new Factory(expression: 'my_expression');
        $this->expectException(LogicException::class);
        $factory->getFactoryForTarget($target);
    }

    /**
     * @dataProvider provideGetFactoryForTarget
     */
    public function testGetArguments(array $arguments, array $expected)
    {
        $factory = new Factory(class: 'foo', arguments: $arguments);
        $this->assertEquals($expected, $factory->getArguments());
    }

    public static function provideGetFactoryForTarget()
    {
        return [
            'no_arguments' => [[], []],
            'string_argument' => [['arg1'], ['arg1']],
            'parameter_as_a_string' => [['%foo%'], [new Parameter('foo')]],
            'reference_as_a_string' => [['@foo'], [new Reference('foo')]],
            'typed_parameter' => [[new Parameter('foo')], [new Parameter('foo')]],
            'typed_reference' => [[new Reference('foo')], [new Reference('foo')]],
        ];
    }
}
