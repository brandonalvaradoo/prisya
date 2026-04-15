<?php

namespace App\ReflectionFactory;

use App\ReflectionFactory\Contract\IntermediateObject;
use App\ReflectionFactory\Exception\FactoryException;
use Exception;

/**
 * Main factory that builds objects from IntermediateObject descriptions.
 *
 * Recursively resolves class names, parses arguments, and instantiates final objects.
 */
class Factory
{
    private NameResolver $nameResolver;
    private ArgumentParser $argumentParser;
    private ArgumentMaterializer $materializer;

    public function __construct()
    {
        $this->nameResolver = new NameResolver();
        $this->argumentParser = new ArgumentParser();
        $this->materializer = new ArgumentMaterializer($this);
    }

    /**
     * Builds the final PHP object from an intermediate description.
     *
     * @param IntermediateObject $io Description of object to build.
     * @return object Instance of the resolved class.
     * @throws FactoryException If class not found or constructor fails.
     */
    public function make(IntermediateObject $io): object
    {
        // 1. Resolve FQCN
        $fqcn = $this->nameResolver->resolve($io->className, $io->namespace, $io->uses);

        if (!class_exists($fqcn))
        {
            throw new Exception("Class $fqcn not found");
        }

        // 2. Parse arguments
        $argExprs = $this->argumentParser->split($io->rawArguments);

        // 3. Materialize arguments
        $args = [];
        foreach ($argExprs as $expr)
        {
            $args[] = $this->materializer->materialize($expr, $io->namespace, $io->uses);
        }

        // 4. Instantiate
        try
        {
            return new $fqcn(...$args);
        }
        catch (\Throwable $e)
        {
            throw new Exception("Failed to instantiate $fqcn: ". $e->getMessage(), 0, $e);
        }
    }
}