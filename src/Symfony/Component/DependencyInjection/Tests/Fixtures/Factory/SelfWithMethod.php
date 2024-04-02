<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

use Symfony\Component\DependencyInjection\Attribute\Factory;

#[Factory(method: 'create')]
class SelfWithMethod extends AbstractFactoryInstantiatedService
{
}
