<?php

namespace App\ReflectionFactory;

use App\ReflectionFactory\Exception\ClassResolutionException;
use Exception;
use InvalidArgumentException;

/**
 * Resolves a potentially short or aliased class name into a Fully Qualified Class Name.
 *
 * Uses the current namespace and imported 'use' statements to mimic PHP's resolution rules.
 */
class NameResolver
{
    /**
     * Resolves a class name to its FQCN.
     *
     * Rules:
     * 1. If name starts with \, it's already absolute.
     * 2. If first segment matches a use alias, replace it.
     * 3. If no \, prepend current namespace.
     * 4. If it contains \, but first segment is not aliased, prepend current namespace.
     *
     * @param string $className Class name as written in code.
     * @param string $namespace Current file namespace. '' for global.
     * @param array<string,string> $uses Map alias => FQCN.
     * @return string Fully qualified class name starting with \.
     * @throws ClassResolutionException If resolution is ambiguous.
     */
    public function resolve(string $className, string $namespace, array $uses): string
    {
        $className = trim($className);

        if ($className === '')
        {
            throw new InvalidArgumentException('Class name cannot be empty');
        }

        // 1. Already FQCN
        if ($className[0] === '\\') {
            return $className;
        }

        $segments = explode('\\', $className);
        $first = $segments[0];

        // 2. Check alias: use App\Models\User as UserModel;
        if (isset($uses[$first]))
        {
            $segments[0] = $uses[$first];
            return '\\'. implode('\\', $segments);
        }

        // 3. No namespace separators: relative to current namespace
        if (count($segments) === 1)
        {
            return $namespace === ''
               ? '\\'. $className
                : '\\'. $namespace. '\\'. $className;
        }

        // 4. Qualified but not aliased: prepend current namespace
        return $namespace === ''
           ? '\\'. $className
            : '\\'. $namespace. '\\'. $className;
    }
}