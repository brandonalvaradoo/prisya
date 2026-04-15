<?php

namespace Database\Events;

class Event
{
    private array $listeners = [];

    function __construct()
    {
    }

    public function Listen(callable $listener, int $priority = 0) : void
    {
        $this->listeners[] = $listener;
    }

    public function RemoveListener(callable $listener) : bool
    {
        $index = array_search($listener, $this->listeners);
        
        if($index !== false)
        {
            unset($this->listeners[$index]);
            return true;
        }

        return false;
    }

    public function Trigger(...$args) : void
    {
        foreach($this->listeners as $listener)
        {
            $listener(...$args);
        }
    }
}