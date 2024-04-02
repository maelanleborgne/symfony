<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

use Symfony\Component\DependencyInjection\Attribute\Factory;

#[Factory(class: self::class, method: 'create')]
class SelfWithClassAndMethod extends AbstractFactoryInstantiatedService
{
}
