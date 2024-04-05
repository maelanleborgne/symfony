<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

use Symfony\Component\DependencyInjection\Attribute\Factory;
use Symfony\Component\DependencyInjection\Parameter;

#[Factory(method: 'create', arguments: [new Parameter('kernel.project_dir'), '%kernel.project_dir%'])]
class WithParameterArgument extends AbstractFactoryInstantiatedService
{
}
