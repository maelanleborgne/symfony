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
use Symfony\Component\DependencyInjection\Exception\AutoconfigureFailedException;

/**
 * Sets the constructor of services tagged with 'container.from_constructor'
 *
 * @author Maelan Le Borgne <maelan.leborgne@gmail.com>
 */
final class RegisterConstructorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->findTaggedServiceIds('container.from_constructor') as $id => $tags) {
            if (1 !== count($tags) || !key_exists('method', $tags[0])) {
                throw new AutoconfigureFailedException($id, sprintf('Multiple tags "container.from_constructor" attached to service %s. The #[Constructor] attribute can only be applied to one method.', $id));
            }
            $definition = $container->getDefinition($id);
            $methodName = $tags[0]['method'];

            if (null === $reflectionClass = $container->getReflectionClass($definition->getClass(), false)) {
                return;
            }
            try {
                $reflectionMethod = $reflectionClass->getMethod($methodName);
            } catch (\ReflectionException) {
                throw new AutoconfigureFailedException($id, sprintf('Cannot use non-existant method "%s:%s" as a constructor for service %s.', $reflectionClass->getName(), $methodName, $id));
            }
            if (!$reflectionMethod->isPublic()) {
                throw new AutoconfigureFailedException($id, sprintf('Cannot use non-public method "%s:%s" as a constructor for service %s.', $reflectionClass->getName(), $methodName, $id));
            }
            if (!$reflectionMethod->isStatic()) {
                throw new AutoconfigureFailedException($id, sprintf('Cannot use non-static method "%s:%s" as a constructor for service %s.', $reflectionClass->getName(), $methodName, $id));
            }

            $definition->setFactory([null, $methodName]);
        }
    }
}
