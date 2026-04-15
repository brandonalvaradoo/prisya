<?php
namespace Attributes;

include_once "invocation.php";
include_once "ihandler.php";
include_once "cacheperformance.php";
include_once "owned/excludefromfullprocess.php";

use Attribute;
use Composer\Composer;

final class Attributes
{
    public static function ProcessProperty(object &$instance, string $property) : mixed
    {
        // Restriction 1. If the property does not exist, throw an error
        if (!property_exists($instance, $property))
        {
            Composer::Throw("Property `$property` does not exist on class `" . get_class($instance) . "`. Trying to proccess attributes on a non-existing property is not allowed.");
        }

        $handlers = self::GetHandlersForProperty($instance, $property);

        // Create an Invocation instance with the current context (instance, property, value and handlers)
        $invocation = new Invocation($instance, $property, $handlers);

        // Returns the modified property value after processing ALL the attributes (decorators) associated with the property
        $modifiedValue = $invocation->Proceed();

        return $modifiedValue;
    }

    public static function ProcessAttribute(object &$instance, string $property, string $attributeClass, ...$args) : mixed
    {
        // Restriction 1. If the property does not exist, throw an error
        if (!property_exists($instance, $property))
        {
            Composer::Throw("Property `$property` does not exist on class `" . get_class($instance) . "`. Trying to proccess attributes on a non-existing property is not allowed.");
        }

        $attributesWithClass = CachePerformance::GetReflectionAttributesForProperty($instance, $property, $attributeClass);
        
        // Restriction 2. If no attributes with the specified class are found, throw an error
        if (empty($attributesWithClass))
        {
            Composer::Throw("No attributes found with attribute class `$attributeClass` on property `$property`.");
        }

        // Restriction 3. If multiple attributes with the specified class are found, throw an error
        if (count($attributesWithClass) > 1)
        {
            Composer::Throw("Multiple attributes found with attribute class `$attributeClass` on property `$property`. Called method can only process one attribute at a time. Please specify an attribute class that is only used once on the property.");
        }

        // Create handlers array with unique Attribute item (Invocation must receive an array of handlers)
        $handler = self::CreateHandlersForAttributes($attributesWithClass, true);
        $invocation = new Invocation($instance, $property, $handler, ...$args);

        // Returns the modified property value after processing the specified attribute
        $modifiedValue = $invocation->Proceed();

        return $modifiedValue;
    }

    public static function ProccessAttributesOfClass(object &$instance, string $attributeClass, ...$args) : void
    {
        $propertiesWithAttribute = self::GetAllPropertiesWithAttribute($instance, $attributeClass);

        foreach ($propertiesWithAttribute as $property)
        {
            self::ProcessAttribute($instance, $property->getName(), $attributeClass, ...$args);
        }
    }

    /**
     * Creates handler instances from an array of attribute objects.
     *
     * Iterates through the provided attributes, instantiates each one, and collects
     * those that implement the IHandler interface. If an attribute fails to instantiate,
     * an exception is thrown with a descriptive error message.
     *
     * @param array $attributes An array of attribute objects to process
     * @return array An array of instantiated handlers that implement IHandler
     * @throws \Exception If an attribute fails to instantiate
     */
    public static function CreateHandlersForAttributes(array $attributes, bool $includeAll=false) : array
    {
        $handlers = [];

        foreach ($attributes as $attribute)
        {
            try
            {
                // Prevents detailed error if the attribute class does not exist
                $handlerInstance = $attribute->newInstance();
                $handlerAttributes = CachePerformance::GetReflectionAttributesForClass($handlerInstance, ExcludeFromFullProcess::class);

                // If the attribute class uses ExcludeFromFullProcess attribute, continue without adding it to the handlers array
                if (!$includeAll && !empty($handlerAttributes))
                {
                    continue;
                }

                if ($handlerInstance instanceof IHandler)
                {
                    $handlers[] = $handlerInstance;
                }
            }
            catch (\Throwable $e)
            {
                Composer::Throw("Failed to instantiate attribute " . $attribute->getName() . ": " . $e->getMessage());
            }
        }

        return $handlers;
    }


    public static function GetHandlersForProperty(object &$instance, string $property) : array
    {
        $attributes = CachePerformance::GetReflectionAttributesForProperty($instance, $property);
        $handlers = self::CreateHandlersForAttributes($attributes);
        return $handlers;
    }

    public static function GetAllPropertiesWithAttribute(object &$instance, string $attributeClass): array
    {
        $objectProperties = CachePerformance::GetReflectionPropertiesForObject($instance);
        $result = [];
    
        foreach ($objectProperties as $property)
        {
            $attributes = CachePerformance::GetReflectionAttributesForProperty($instance, $property->getName(), $attributeClass);
            if (!empty($attributes))
            {
                $result[] = $property;
            }
        }

        return $result;
    }

    /**
     * Determines if a specific property of an object has a given attribute.
     *
     * This method checks whether the specified property of the provided object instance
     * is decorated with the given attribute class. It utilizes a caching mechanism
     * to improve performance when retrieving reflection attributes.
     *
     * @param object $instance The object instance containing the property to check.
     * @param string $property The name of the property to inspect for the attribute.
     * @param string $attributeClass The fully qualified class name of the attribute to look for.
     * @return bool Returns true if the property has the specified attribute; otherwise, false.
     */
    public static function PropertyHasAttribute(object &$instance, string $property, string $attributeClass): bool
    {
        $attributes = CachePerformance::GetReflectionAttributesForProperty($instance, $property, $attributeClass);

        return !empty($attributes);
    }
}