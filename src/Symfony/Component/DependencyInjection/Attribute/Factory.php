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

use Symfony\Component\DependencyInjection\Argument\ArgumentInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Parameter;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\Expression;

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
        public string|Reference|Definition|null $service = null,
        public ?string $method = null,
        public string|Expression|null $expression = null,
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

    /**
     * Validate the attribute properties and transform them into a factory that can be used by Definition::setFactory.
     */
    public function getFactoryForTarget(\ReflectionClass|\ReflectionMethod $target): array|string|Reference
    {
        $targetIsMethod = $target instanceof \ReflectionMethod;
        $class = $targetIsMethod ? $target->getDeclaringClass() : $target;
        if ($targetIsMethod) {
            if (!$target->isStatic()) {
                throw new LogicException('The #[Factory] attribute can only be used on classes and static methods.');
            }
            if (null === $this->method) {
                $this->method = $target->getName();
            }
            if (null !== $this->service || null !== $this->expression) {
                throw new LogicException('The #[Factory] attribute cannot declare "$service" or "$expression" when used on a method.');
            }
        }

        // A method is set but no service nor class : resolve to "class::method"
        if (null !== $this->method && (null === $this->class && null === $this->service)) {
            $this->class = $class->getName();
        }
        // No method is set but a service is set : resolve to "service::__invoke"
        $method = null === $this->method && null !== $this->service ? '__invoke' : $this->method;
        unset($this->method);
        // Only one of expression, class or service can be set
        $this->assertOneOrNoneOf($this->class, $this->service, $this->expression);

        if (null !== ($this->expression)) {
            return match(true) {
                !class_exists(Expression::class) => throw new LogicException(sprintf('Using an expression as factory for %s requires the ExpressionLanguage component. Try running "composer require symfony/expression-language".', $class->getName())),
                is_string($this->expression) => str_starts_with($this->expression, '@=') ? $this->expression : '@='.$this->expression,
                $this->expression instanceof Expression => '@='.$this->expression,
                default => throw new LogicException(sprintf('The "expression" argument of attribute "#[Factory]" must be a string or an instance of Symfony\Component\ExpressionLanguage\Expression for %s.', $class->getName())),
            };
        } elseif (null !== $this->service) {
            if (is_string($this->service)) {
                return [new Reference(str_starts_with($this->service, '@') ? substr($this->service, 1) : $this->service), $method];
            } else {
                return [$this->service, $method];
            }
        } elseif (is_string($this->class)) {
            return [$this->class, $method];
        }

        throw new LogicException(sprintf('The "class" argument of attribute "#[Factory]" must be a string for %s.', $class->getName()));
    }

    /**
     * Return the factory arguments in format that can be used by Definition::setFactory.
     */
    public function getArguments(): array
    {
        return array_map(
            $this->resolveArgument(...),
            $this->arguments
        );
    }

    private function assertOneOrNoneOf(...$arguments): string
    {
        return \count(array_filter($arguments, fn ($argument) => null !== $argument)) <= 1;
    }

    private function resolveArgument(mixed $argument): Reference|Definition|Parameter|ArgumentInterface|string
    {
        if (is_string($argument)) {
            if(str_starts_with($argument, '@')) {
                return new Reference(substr($argument, 1));
            }
            if (str_starts_with($argument, '%') && str_ends_with($argument, '%')) {
                return new Parameter(substr($argument, 1, -1));
            }
        }
        return $argument;
    }
}
