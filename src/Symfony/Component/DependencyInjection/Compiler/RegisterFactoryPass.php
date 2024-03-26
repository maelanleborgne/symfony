<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\ExpressionLanguage\Expression;

/**
 * Register factories for services tagged with "container.from_factory".
 *
 * @author Maelan Le Borgne <maelan.leborgne@gmail.com>
 */
final class RegisterFactoryPass implements CompilerPassInterface
{
    private static \Closure $registerFactoryConfigurationClosure;

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('container.from_factory') as $id => $arguments) {
            $tagArguments = $arguments[0];
            $definition = $container->getDefinition($id);
            $class = $container->getReflectionClass($definition->getClass(), false);
            if (null === $class) {
                return;
            }

            $factory = $this->resolveFactory($tagArguments, $class);

            if (!isset(self::$registerFactoryConfigurationClosure)) {
                /** @var YamlFileLoader $yamlLoader */
                $reflectionClass = new \ReflectionClass(YamlFileLoader::class);
                $yamlLoader = $reflectionClass->newInstanceWithoutConstructor();
                $parseDefinition = $reflectionClass->getMethod('parseDefinition');

                self::$registerFactoryConfigurationClosure = static function (ContainerBuilder $container, string $id, string $fileName, array|string $factory, array $arguments) use ($parseDefinition, $yamlLoader) {
                    $parseDefinition->invoke(
                        $yamlLoader,
                        $id,
                        [$container->getDefinition($id)] + ['factory' => $factory, 'arguments' => $arguments],
                        $fileName,
                        [],
                        false,
                        false
                    );
                };
            }

            (self::$registerFactoryConfigurationClosure)($container, $id, $class->getFileName(), $factory, $tagArguments['arguments'] ?? []);
        }
    }

    /**
     * Validate the tag arguments and transform them into a factory configuration that can be used by the YamlFileLoader.
     *
     * @param array{class?: string, service?: string, method?: string, expression?: string, arguments?: array} $tagArguments
     */
    private function resolveFactory(array $tagArguments, \ReflectionClass $class): array|string
    {
        $tagArguments = [
            'class' => $tagArguments['class'] ?? null,
            'service' => $tagArguments['service'] ?? null,
            'expression' => $tagArguments['expression'] ?? null,
            'method' => $tagArguments['method'] ?? null,
        ];
        // When using expression, no method can be set
        if (is_string($tagArguments['method']) && is_string($tagArguments['expression'])) {
            throw new LogicException('The "container.from_factory" tag cannot declare both "method" and "expression".');
        }
        // A method is set but no service nor class : resolve to "class::method"
        if (is_string($tagArguments['method']) && (!is_string($tagArguments['class']) && !is_string($tagArguments['service']))) {
            $tagArguments['class'] = $class->name;
        }
        // No method is set but a service is set : resolve to "service::__invoke"
        $method = !is_string($tagArguments['method']) && is_string($tagArguments['service']) ? '__invoke' : $tagArguments['method'];
        unset($tagArguments['method']);
        // Only one of expression, class or service can be set
        if (1 !== \count(\array_filter($tagArguments, static fn ($argument) => is_string($argument)))) {
            throw new LogicException('The "container.from_factory" tag must declare one of "expression", "class" or "service".');
        }

        if (is_string($tagArguments['expression'])) {
            if (!class_exists(Expression::class)) {
                throw new LogicException(sprintf('Using an expression as factory for %s requires the ExpressionLanguage component. Try running "composer require symfony/expression-language".', $class->name));
            }

            return str_starts_with($tagArguments['expression'], '@=') ? $tagArguments['expression'] : ('@=' . $tagArguments['expression']);
        } elseif (is_string($tagArguments['service'])) {
            $firstElement = str_starts_with($tagArguments['service'], '@') ? $tagArguments['service'] : ('@' . $tagArguments['service']);
        } elseif (is_string($tagArguments['class'])) {
            $firstElement = $tagArguments['class'];
        } else {
            throw new LogicException('The "container.from_factory" tag must declare a "service", "class" or "expression".');
        }

        return [$firstElement, $method];
    }
}
