<?php
namespace Scry\PhpFile\Cursor\Events;

class AdvanceEventHandler
{
    private AdvanceEvent $eventParent;
    private readonly int $listenerIndex;
    private Seeker $callable;
    private bool $paused;
    private array $resumeOnEnd;

    public function __construct(AdvanceEvent &$eventParent, int $listenerIndex, Seeker &$callable)
    {
        $this->eventParent = &$eventParent;
        $this->listenerIndex = $listenerIndex;
        $this->callable = &$callable;
        $this->paused = false;
    }

    public function Pause() : void
    {
        $this->paused = true;
        $this->eventParent->RemoveListener($this);
    }

    public function Resume() : void
    {
        $this->paused = false;
        $this->eventParent->AddListener($this);
    }

    public function ResumeAtEnd(self &$handler) : void
    {
        $this->resumeOnEnd[] = $handler;
    }

    public function PauseUntil(self $endTrigger) : void
    {
        $endTrigger->resumeOnEnd[] = $this;
        $this->Pause();
    }

    public function IsPaused() : bool
    {
        return $this->paused;
    }

    public function Call() : void
    {
        if(!$this->paused)
        {
            $this->callable->Handle($this->eventParent->GetHandlingTokenCursor(), $this);
        }
    }

    public function Unlisten()
    {
        $this->eventParent->RemoveListener($this);
    }

    public function End() : void
    {
        $this->Unlisten();
        foreach($this->resumeOnEnd as $continueHandler)
        {
            $continueHandler->Resume();
        }

        $this->resumeOnEnd = [];
    }

    public function GetEventParent()
    {
        return $this->GetEventParent();
    }

    public function GetListenerIndex()
    {
        return $this->listenerIndex;
    }
}