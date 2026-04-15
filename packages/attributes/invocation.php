<?php

namespace Attributes;

use Composer\Composer;

include_once "attributes.php";
include_once "ihandler.php";

class Invocation
{
    private int $currentHandlerIndex = 0;
    private mixed $value;

    // Constructor properties
    private object $instance;
    private string $property;
    private array $handlers;
    private array $args = [];

    /**
     * Constructor for the Invocation class.
     *
     * @param object $instance Reference to the object instance being invoked
     * @param string $property The property name associated with this invocation
     * @param array $handlers Reference to an array of handler callbacks to be executed
     * @param mixed ...$args Variable number of additional arguments to use in `handler($invocation)` methods
     */
    public function __construct(
        object &$instance,
        string $property,
        array &$handlers = [],
        ...$args
    )
    {
        $this->instance = &$instance;
        $this->property = $property;
        $this->handlers = &$handlers;
        $this->args = $args;

        // Initialize the property value reference
        $getRef = function &() use ($property) {
            return $this->$property;
        };

        $getRef = $getRef->bindTo($instance, get_class($instance));
        $this->value =& $getRef();
    }

    /**
     * Proceeds through the handler chain and executes the next handler in sequence.
     * 
     * Each handler can modify the invocation state, including the property value,
     * and then call `Proceed()` to continue to the next handler.
     *
     * This method iterates through registered handlers, executing each one in order.
     * Each handler is passed the current invocation instance, allowing it to modify
     * the invocation state or property value. Once all handlers have been processed,
     * the final property value is retrieved and returned.
     *
     * @return mixed The result from the current handler, or the property value after
     *               all handlers have been processed.
     */
    public function Proceed() : mixed
    {
        if ($this->currentHandlerIndex < count($this->handlers))
        {
            $handler = $this->handlers[$this->currentHandlerIndex++];
            $handleResult = $handler->handle($this);

            return $handleResult;
        }

        // Finally returns the property value after processing all handlers.
        // This allows handlers to modify the value and have the modified value returned at the end of the chain.
        return $this->GetPropertyValue();
    }

    /*
 _____      _   _                
|  __ \    | | | |               
| |  \/ ___| |_| |_ ___ _ __ ___ 
| | __ / _ \ __| __/ _ \ '__/ __|
| |_\ \  __/ |_| ||  __/ |  \__ \
 \____/\___|\__|\__\___|_|  |___/
     */

    /**
     * Returns the instance of the class on which the property is being accessed.
     * 
     * @return object The instance of the class that this invocation is associated with.
    */
    public function GetInstance(): object
    {
        return $this->instance;
    }

    /**
     * Retrieves the name of the property associated with this invocation.
     *
     * @return string The name of the property that this invocation refers to.
     */
    public function GetProperty(): string
    {
        return $this->property;
    }

    /**
     * Retrieves all attributes associated with this instance.
     *
     * @return IHandler[] An array of IHandler interface instances representing
     *                    all attributes handled by IHandler on this property.
     */
    public function GetHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * Returns the number of handlers.
     *
     * @return int The count of handlers in the current instance.
     */
    public function GetHandlersCount(): int
    {
        return count($this->handlers);
    }

    /**
     * Retrieves the arguments associated with the invocation.
     *
     * @return array The array of arguments passed to the invocation.
     */
    public function GetArgs(): array
    {
        return $this->args;
    }

    /**
     * Retrieves all handlers of a specified class.
     *
     * This method returns an array of handlers that match the given handler class.
     * If the handlers have been previously classified and cached in the `$clasifiedHandlers` array,
     * it returns the cached handlers. Otherwise, it filters the `$handlers` array to find handlers
     * whose class matches the specified `$handlerClass`.
     *
     * @param string $handlerClass The fully qualified class name of the handler to retrieve.
     * @return array An array of handlers matching the specified class.
     */
    public function GetAttributesOfClass(string $attributeClass): array
    {
        return $this->clasifiedAttributtes[$attributeClass]["attributes"] ?? array_filter($this->handlers, function($attribute) use ($attributeClass) {
            return get_class($attribute) === $attributeClass;
        });
    }

    /*
 _____          _                                             _ _  __ _               
|_   _|        | |                                           | (_)/ _(_)              
  | | _ __  ___| |_ __ _ _ __   ___ ___   _ __ ___   ___   __| |_| |_ _  ___ _ __ ___ 
  | || '_ \/ __| __/ _` | '_ \ / __/ _ \ | '_ ` _ \ / _ \ / _` | |  _| |/ _ \ '__/ __|
 _| || | | \__ \ || (_| | | | | (_|  __/ | | | | | | (_) | (_| | | | | |  __/ |  \__ \
 \___/_| |_|___/\__\__,_|_| |_|\___\___| |_| |_| |_|\___/ \__,_|_|_| |_|\___|_|  |___/                                                                               
     */

    /**
     * Retrieves the property value stored in this invocation attribute instance.
     *
     * This method provides access to the underlying value that was assigned to this
     * attribute during instantiation or through subsequent modifications. The returned
     * value can be of any type, as indicated by the mixed return type hint.
     * 
     * The value will be returned if the property exists, even if it is private or protected.
     * This allows handlers to access and modify the value of the property regardless of its
     * visibility, as long as they have access to the Invocation instance.
     *
     * @return mixed The value stored in this invocation attribute. The specific type
     *               depends on the context in which this attribute was created and
     *               how it was initialized.
     */
    public function GetPropertyValue() : mixed
    {
        return $this->value;
    }

    /**
     * Modifies the property value stored in this invocation attribute instance.
     *
     * This method provides a way to update the underlying value that was assigned to this
     * attribute during instantiation. The new value can be of any type, as indicated by the
     * mixed parameter type hint.
     *
     * The value will be set regardless of the property's visibility (private or protected).
     * This allows handlers to access and modify the value of the property regardless of its
     * visibility, as long as they have access to the Invocation instance.
     *
     * @param mixed $newValue The new value to be stored in this invocation attribute.
     *                        The specific type can be any valid PHP type depending on
     *                        the context in which this attribute is being used.
     * @return void
     */
    public function ModifyPropertyValue(mixed $newValue) : void
    {
        $this->value = $newValue;
    }
    
    /**
     * Invokes an instance method on the object with the given arguments.
     *
     * @param string $methodName The name of the method to invoke
     * @param mixed ...$args Variable number of arguments to pass to the method
     * @return mixed The return value of the invoked method
     * @throws Exception If the method does not exist in the object's class
     */
    public function InvokeInstanceMethod(string $methodName, ...$args) : mixed
    {
        $reflectionClass = CachePerformance::GetReflectionClassForObject($this->instance);

        if ($reflectionClass->hasMethod($methodName))
        {
            $method = $reflectionClass->getMethod($methodName);
            return $method->invokeArgs($this->instance, $args);
        }

        return Composer::Throw("Method $methodName does not exist in class " . get_class($this->instance));
    }
}