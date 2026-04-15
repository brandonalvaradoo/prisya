<?php

namespace Attributes;

interface IHandler
{
    public function handle(Invocation $invocation);
}