<?php

namespace App\ReflectionFactory;

use App\ReflectionFactory\Contract\IntermediateObject;
use App\ReflectionFactory\Exception\ArgumentParseException;
use Exception;

/**
 * Materializes argument expressions into actual PHP values.
 *
 * If an argument is 'new Class(...)', it delegates to Factory to build it recursively.
 */
class ArgumentMaterializer
{
    public function __construct(
        private Factory $factory
    ) {}

    /**
     * Converts an expression string into a PHP value.
     *
     * Supports: scalars, arrays [], null, true, false, new Class(...), constants.
     * Does NOT support variables like $foo.
     *
     * @param string $expr Single argument expression.
     * @param string $namespace Current namespace for resolving nested new().
     * @param array<string,string> $uses Current file uses.
     * @return mixed
     * @throws ArgumentParseException If expression is invalid.
     */
    public function materialize(string $expr, string $namespace, array &$uses): mixed
    {
        $expr = trim($expr);

        // null, true, false
        if ($expr === 'null') return null;
        if ($expr === 'true') return true;
        if ($expr === 'false') return false;

        // Numbers
        if (is_numeric($expr)) {
            return str_contains($expr, '.')? (float)$expr : (int)$expr;
        }

        // Strings
        if ((str_starts_with($expr, '"') && str_ends_with($expr, '"')) ||
            (str_starts_with($expr, "'") && str_ends_with($expr, "'"))) {
            return stripcslashes(substr($expr, 1, -1));
        }

        // Arrays []
        if (str_starts_with($expr, '[') && str_ends_with($expr, ']')) {
            return $this->parseArray(substr($expr, 1, -1), $namespace, $uses);
        }

        // new Class(...)
        if (str_starts_with($expr, 'new ')) {
            return $this->parseNew($expr, $namespace, $uses);
        }

        // Class constants: User::TYPE_ADMIN
        if (preg_match('/^([\\\\\w]+)::(\w+)$/', $expr, $m)) {
            $fqcn = (new NameResolver())->resolve($m[1], $namespace, $uses);
            return constant($fqcn. '::'. $m[2]);
        }

        throw new Exception("Unsupported expression: $expr");
    }

    private function parseArray(string $inner, string $namespace, array &$uses): array
    {
        if (trim($inner) === '') return [];
        $items = (new ArgumentParser())->split($inner);
        $result = [];
        foreach ($items as $item) {
            if (preg_match('/^(.+?)\s*=>\s*(.+)$/', $item, $m)) {
                $key = $this->materialize($m[1], $namespace, $uses);
                $val = $this->materialize($m[2], $namespace, $uses);
                $result[$key] = $val;
            } else {
                $result[] = $this->materialize($item, $namespace, $uses);
            }
        }
        return $result;
    }

    private function parseNew(string $expr, string $namespace, array &$uses): object
    {
        // new Class(args) or new Class
        if (!preg_match('/^new\s+([\\\\\w]+)(?:\((.*)\))?$/s', $expr, $m)) {
            throw new Exception("Invalid new expression: $expr");
        }

        $className = $m[1];
        $rawArgs = $m[2]?? '';

        $intermediate = new IntermediateObject($className, $namespace, $uses, $rawArgs);
        return $this->factory->make($intermediate);
    }
}