<?php
namespace Scry\PhpFile\Cursor\Events;

use Countable;
use Scry\PhpFile\Cursor\TokenCursor;

include_once "advanceeventhandler.php";
include_once "seeker.php";

class AdvanceEvent implements Countable
{
    /**
     * @var AdvanceEventHandler[] Array of the Event Handlers containing all the suscribed listeners.
     * @see Seeker
     * */
    private array $listeners;
    
    private TokenCursor $handlingTokenCursor;
    private int $currentListener;
    private int $activeListeners;

    public function __construct(TokenCursor &$handlingTokenCursor)
    {
        $this->listeners = [];
        $this->handlingTokenCursor = &$handlingTokenCursor;
        $this->currentListener = 0;
        $this->activeListeners = 0;
    }

    public function AddListener(AdvanceEventHandler &$eventHandler) : int
    {
        $this->listeners[$eventHandler->GetListenerIndex()] = $eventHandler;
        
        $this->currentListener++;
        $this->activeListeners++;

        return $this->currentListener - 1;
    }

    public function RemoveListener(AdvanceEventHandler $eventHandler) : void
    {
        for($i = 0; $i < $this->currentListener; $i++)
        {
            if(isset($this->listeners[$i]) && $this->listeners[$i] === $eventHandler)
            {
                unset($this->listeners[$i]);
                $this->activeListeners--;
            }
        }
    }

    public function Listen(Seeker $listener) : AdvanceEventHandler
    {
        $this->listeners[$this->currentListener] = new AdvanceEventHandler($this, $this->currentListener, $listener);
        
        $this->currentListener++;
        $this->activeListeners++;

        return $this->listeners[$this->currentListener - 1];
    }

    public function Unlisten(int $listenerIndex)
    {
        unset($this->listeners[$listenerIndex]);
        $this->activeListeners--;
    }

    public function RemoveAllListeners()
    {
        for($i = 0; $i < $this->currentListener; $i++)
        {
            unset($this->listeners[$i]);
        }

        $this->activeListeners;
    }

    public function PauseAll()
    {
        for($i = 0; $i < $this->currentListener; $i++)
        {
            if(isset($this->listeners[$i]))
            {
                $this->listeners[$i]->Pause();
            }
        }
    }

    public function ResumeAll()
    {
        for($i = 0; $i < $this->currentListener; $i++)
        {
            if(isset($this->listeners[$i]))
            {
                $this->listeners[$i]->Resume();
            }
        }
    }

    public function Trigger() : void
    {
        for($i = 0; $i < $this->currentListener; $i++)
        {
            if(isset($this->listeners[$i]))
            {
                $this->listeners[$i]?->Call();
            }
        }
    }

    public function &GetHandlingTokenCursor() : TokenCursor
    {
        return $this->handlingTokenCursor;
    }

    public function AllActiveListeners() : int
    {
        return $this->activeListeners;
    }

    public function count(): int
    {
        return $this->activeListeners;
    }
}