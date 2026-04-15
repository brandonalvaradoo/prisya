<?php
namespace Database\Attributes;

use Attribute;
use Attributes\IHandler;
use Attributes\Invocation;
use Attributes\ExcludeFromFullProcess;

#[Attribute(Attribute::TARGET_PROPERTY)]
#[ExcludeFromFullProcess]
class Get implements IHandler
{
    public function __construct(protected string|null $overwriteGetMethod = null)
    {}

     /**
     * Handles the invocation of the Get attribute.
     *
     * This method is called when a property decorated with the Get attribute is accessed. It retrieves the current value of the property, applies a lowercase transformation if it's a string, and returns the result. If the value is not a string, it returns the original value without modification.
     *
     * @param Invocation $invocation The invocation context containing information about the property access.
     * @return mixed The transformed value of the property if it's a string, or the original value if it's not a string.
     */
    public function handle(Invocation $invocation)
    {
        // Get the current value of the property
        if($this->overwriteGetMethod !== null)
        {
            $methodResult = $invocation->InvokeInstanceMethod($this->overwriteGetMethod);
            return $methodResult;
        }

        return $invocation->Proceed();
    }
}