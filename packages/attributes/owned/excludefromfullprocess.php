<?php
namespace Attributes;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ExcludeFromFullProcess implements IHandler
{
    public function handle(Invocation $invocation)
    {
        // Skip processing for this attribute
        return $invocation->Proceed();
    }
}