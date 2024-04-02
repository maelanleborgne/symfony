<?php

namespace Symfony\Component\DependencyInjection\Tests\Fixtures\Factory;

use Symfony\Component\DependencyInjection\Attribute\Factory;

class SelfEmptyOnMethod extends AbstractFactoryInstantiatedService
{
    #[\Override]
    #[Factory]
    public static function create($randomArgName = null, $foo = null): AbstractFactoryInstantiatedService
    {
        return parent::create($randomArgName, $foo);
    }
}
