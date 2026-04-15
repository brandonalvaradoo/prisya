<?php
namespace Attributes;

class CachePerformance
{
    private static array $CachedReflectionClasses = [];
    private static array $CachedReflectionAttributes = [];
    private static array $CachedReflectionProperties = [];

    /**
     * Retrieves a cached ReflectionClass instance for the given object.
     *
     * This method uses the object's hash as a cache key to store and retrieve
     * ReflectionClass instances, improving performance by avoiding redundant
     * reflection instantiations for the same object.
     *
     * @param object $objectToCreateReflectionClassFor The object to create or retrieve a ReflectionClass for.
     * @return \ReflectionClass The cached or newly created ReflectionClass instance for the given object.
     */
    public static function GetReflectionClassForObject(object &$objectToCreateReflectionClassFor) : \ReflectionClass
    {
        // 1. Get object hash to use as a key for caching
        $objectHash = spl_object_hash($objectToCreateReflectionClassFor);

        // 2. Check if the ReflectionClass for this object is already cached, if not create it and cache it
        self::$CachedReflectionClasses[$objectHash] = self::$CachedReflectionClasses[$objectHash] ?? new \ReflectionClass($objectToCreateReflectionClassFor);
        
        return self::$CachedReflectionClasses[$objectHash];
    }

    /**
     * Retrieves and caches the reflection attributes for a given object.
     *
     * This method generates a unique hash for the provided object and uses it as a key
     * to cache the reflection attributes. If the attributes for the object have already
     * been cached, it returns them directly from the cache. Otherwise, it creates the
     * reflection attributes, stores them in the cache, and then returns them.
     *
     * @param object $objectToCreateReflectionAttributesFor The object for which to retrieve reflection attributes.
     * @return array The array of reflection attributes for the given object.
     */
    public static function GetReflectionAttributesForObject(object &$objectToCreateReflectionAttributesFor) : array
    {
        // 1. Get object hash to use as a key for caching
        $objectHash = spl_object_hash($objectToCreateReflectionAttributesFor);

        // 2. Check if the ReflectionAttributes for this object is already cached, if not create it and cache it
        self::$CachedReflectionAttributes[$objectHash] = self::$CachedReflectionAttributes[$objectHash] ?? self::GetReflectionClassForObject($objectToCreateReflectionAttributesFor)->getAttributes();

        return self::$CachedReflectionAttributes[$objectHash];
    }

    /**
     * Retrieves and caches the reflection properties for a given object.
     *
     * This method generates a unique hash for the provided object and uses it as a key
     * to cache the reflection properties. If the properties for the object have already
     * been cached, it returns them directly from the cache. Otherwise, it creates the
     * reflection properties, stores them in the cache, and then returns them.
     *
     * @param object $objectToCreateReflectionPropertiesFor The object for which to retrieve reflection properties.
     * @return array The array of reflection properties for the given object.
     */
    public static function GetReflectionPropertiesForObject(object &$objectToCreateReflectionPropertiesFor) : array
    {
        // 1. Get object hash to use as a key for caching
        $objectHash = spl_object_hash($objectToCreateReflectionPropertiesFor);

        // 2. Check if the ReflectionProperties for this object is already cached, if not create it and cache it
        self::$CachedReflectionProperties[$objectHash] = self::$CachedReflectionProperties[$objectHash] ?? self::GetReflectionClassForObject($objectToCreateReflectionPropertiesFor)->getProperties();

        return self::$CachedReflectionProperties[$objectHash];
    }

    public static function GetReflectionPropertyForObject(object &$objectToCreateReflectionPropertyFor, string $property) : \ReflectionProperty
    {
        // 1. Get object hash to use as a key for caching
        $objectHash = spl_object_hash($objectToCreateReflectionPropertyFor);

        // 2. Check if the ReflectionProperty for this object and property is already cached, if not create it and cache it
        self::$CachedReflectionProperties[$objectHash][$property] = self::$CachedReflectionProperties[$objectHash][$property] ?? self::GetReflectionClassForObject($objectToCreateReflectionPropertyFor)->getProperty($property);

        return self::$CachedReflectionProperties[$objectHash][$property];
    }

    /**
     * Retrieves and caches reflection attributes for a specific property of an object.
     *
     * This method fetches reflection attributes for a given object property and optional attribute class.
     * Results are cached using the object's hash to improve performance on subsequent calls.
     *
     * @param object $objectToCreateReflectionAttributeFor The object instance to retrieve reflection attributes from.
     * @param string $property The name of the property to get reflection attributes for.
     * @param string|null $attributeClass Optional attribute class name to filter results. If null, all attributes are retrieved.
     *
     * @return ReflectionAttribute[]|null An array of ReflectionAttribute objects for the specified property,
     *                                           or null if no attributes are found or the property does not exist.
     */
    public static function GetReflectionAttributesForProperty(object &$objectToCreateReflectionAttributeFor, string $property, ?string $attributeClass = null) : ?array
    {
        // 1. Get object hash to use as a key for caching
        $objectHash = spl_object_hash($objectToCreateReflectionAttributeFor);

        // 2. Check if the ReflectionAttribute for this object, property and attribute class is already cached, if not create it and cache it
        self::$CachedReflectionAttributes[$objectHash][$property][$attributeClass] = self::$CachedReflectionAttributes[$objectHash][$property][$attributeClass] ?? self::GetReflectionPropertyForObject($objectToCreateReflectionAttributeFor, $property)->getAttributes($attributeClass);

        return self::$CachedReflectionAttributes[$objectHash][$property][$attributeClass];
    }

    public static function GetReflectionAttributesForClass(object &$objectToCreateReflectionAttributeFor, string $attributeClass = null) : ?array
    {
        // 1. Get object hash to use as a key for caching
        $objectHash = spl_object_hash($objectToCreateReflectionAttributeFor);

        // 2. Check if the ReflectionAttribute for this object and attribute class is already cached, if not create it and cache it
        self::$CachedReflectionAttributes[$objectHash]["class"][$attributeClass] = self::$CachedReflectionAttributes[$objectHash]["class"][$attributeClass] ?? self::GetReflectionClassForObject($objectToCreateReflectionAttributeFor)->getAttributes($attributeClass);

        return self::$CachedReflectionAttributes[$objectHash]["class"][$attributeClass];
    }
}