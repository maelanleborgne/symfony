<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\DependencyInjection\Attribute\Factory;

#[Factory(method: 'create')]
#[Autoconfigure(constructor: 'create')]
class AutoconfigureAttributeClass extends AbstractFactoryInstantiatedService
{
}
