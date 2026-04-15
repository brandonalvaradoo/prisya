<?php

namespace Attributes;

class Friend
{
    private object $friendClass;
    // Get property methdod
    private mixed $getProperty;
    private mixed $executeMethod;

    public function __construct(object &$friendClass)
    {
        $this->friendClass =& $friendClass;

        $getProperty = function &(string $property) {
             return $this->$property;
        };

        $executeMethod = function (string $method, ...$args) {
            return $this->$method(...$args);
        };

        $this->getProperty = $getProperty->bindTo($friendClass, get_class($friendClass));
        $this->executeMethod = $executeMethod->bindTo($friendClass, get_class($friendClass));
    }

    public function &GetFriendClass() : object
    {
        return $this->friendClass;
    }

    public function &get(string $property)
    {
        return ($this->getProperty)($property);
    }

    public function set(string $property, mixed $value) : void
    {
        $propertyRef =& ($this->getProperty)($property);
        $propertyRef = $value;
    }

    public function execute(string $method, ...$args) : mixed
    {
        return ($this->executeMethod)($method, ...$args);
    }
}