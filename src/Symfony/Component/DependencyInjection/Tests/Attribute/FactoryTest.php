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

class FactoryTest extends TestCase
{
    public function testNoArguments()
    {
        new Factory();
        $this->addToAssertionCount(1);
    }

    public function testMethodOnly()
    {
        new Factory(method: 'my_method');
        $this->assertEquals('my_method', (new Factory(method: 'my_method'))->method);
    }

    public function testClassAndService()
    {
        $this->expectException(LogicException::class);

        new Factory(class: 'my_class', service: 'my_service');
    }

    public function testClassAndExpression()
    {
        $this->expectException(LogicException::class);

        new Factory(class: 'my_class', expression: 'my_expression');
    }

    public function testServiceAndExpression()
    {
        $this->expectException(LogicException::class);

        new Factory(service: 'my_service', expression: 'my_expression');
    }

    public function testClassAndServiceAndExpression()
    {
        $this->expectException(LogicException::class);

        new Factory(class: 'my_class', service: 'my_service', expression: 'my_expression');
    }

    public function testExpressionAndMethod()
    {
        $this->expectException(LogicException::class);

        new Factory(method: 'static_method', expression: 'my_expression');
    }
}
