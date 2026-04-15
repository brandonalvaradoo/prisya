<?php
namespace Database\Attributes;

use Attribute;
use Attributes\IHandler;
use Attributes\Invocation;
use Attributes\ExcludeFromFullProcess;

#[Attribute(Attribute::TARGET_PROPERTY)]
#[ExcludeFromFullProcess]
class Set implements IHandler
{
    public function __construct(protected string|null $overwriteSetMethod = null)
    {}

    
    public function handle(Invocation $invocation)
    {
        $args = $invocation->GetArgs();

        if($this->overwriteSetMethod !== null)
        {
            $invocation->InvokeInstanceMethod($this->overwriteSetMethod, ...$args);
            return $invocation->Proceed();
        }

        // Default behavior: set the property value to the provided value(s)
        $invocation->ModifyPropertyValue($args[0]);

        return null; // No chain of handlers after setting the value, so we return null
    }
}