<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Attribute;

use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * An attribute to define the factory for a base type.
 *
 * @author Maelan Le Borgne <maelan.leborgne@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS|\Attribute::TARGET_METHOD)]
class Factory
{
    public function __construct(
        public ?string $class = null,
        public ?string $service = null,
        public ?string $method = null,
        public ?string $expression = null,
        public array $arguments = [],
    )
    {
        if (!$this->assertOneOrNoneOf($this->class, $this->service, $this->expression)) {
            throw new LogicException('The #[Factory] attribute must declare one or none of "$expression", "$class" or "$service".');
        }
        if (isset($this->method) && isset($this->expression)) {
            throw new LogicException('The #[Factory] attribute cannot declare both "$method" and "$expression".');
        }
    }

    private function assertOneOrNoneOf(...$arguments): string
    {
        return 1 >= \count(array_filter($arguments, fn ($argument) => null !== $argument));
    }
}
