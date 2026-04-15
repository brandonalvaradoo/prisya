<?php

namespace App\ReflectionFactory\Contract;

/**
 * Represents an object that has been described but not yet instantiated.
 * Holds all data needed to build the final object later.
 */
class IntermediateObject
{
    /**
     * @param string $className Expected class name. May be short or FQCN.
     * @param string $namespace Current file namespace. Empty for global.
     * @param array<string,string> $uses Map of alias => FQCN from 'use' statements. Passed by reference.
     * @param string $rawArguments Raw argument string. Example: "10, new User($id), 'admin'"
     */
    public function __construct(
        public readonly string $className,
        public readonly string $namespace,
        public readonly array &$uses,
        public readonly string $rawArguments
    ) {}
}