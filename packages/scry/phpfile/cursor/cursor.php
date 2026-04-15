<?php

namespace Scry\PhpFile\Cursor;

use Countable;
use Scry\PhpFile\Cursor\TokenCursor;
use Scry\PhpFile\Cursor\Events\AdvanceEvent;
use Scry\PhpFile\Cursor\Events\Seeker;

include_once 'events/advanceevent.php';
include_once 'events/collectors/attributecollector.php';
include_once 'events/collectors/stepcollector.php';
include_once 'tokencursor.php';

/**
 * Represents a validated cursor that tracks position within bounds.
 *
 * The cursor cannot move below 0 or above maxPosition. It also tracks a highWaterMark,
 * which represents the furthest position ever reached. The highWaterMark only increases
 * when the cursor moves forward past it. Moving backward does not affect the highWaterMark.
 */
class Cursor implements Countable
{
    /**
     * @var int Current cursor position. Always between 0 and maxPosition.
     */
    private int $position = 0;

    /**
     * @var int The furthest position ever reached by this cursor.
     * This value never decreases. It only increases when position exceeds it.
     */
    private int $furthestAdvance = 0;

    /**
     * @var AdvanceEvent Internal event dispatcher for cursor movements.
     */
    public readonly AdvanceEvent $onAdvance;

    /**
     * @param int $maxPosition Maximum allowed position. Must be >= 0.
     * @throws \InvalidArgumentException If maxPosition is negative.
     */
    public function __construct(
        private readonly int $maxPosition,
        private TokenCursor &$handlingTokenCursor
    )
    {
        if ($maxPosition < 0)
        {
            throw new \InvalidArgumentException('maxPosition cannot be negative');
        }

        $this->onAdvance = new AdvanceEvent($handlingTokenCursor);
    }

/**
    *
  ________        __    __                       
 /  _____/  _____/  |__/  |_  ___________  ______
/   \  ____/ __ \   __\   __\/ __ \_  __ \/  ___/
\    \_\  \  ___/|  |  |  | \  ___/|  | \/\___ \ 
 \______  /\___  >__|  |__|  \___  >__|  /____  >
        \/     \/                \/           \/ 
     */

    /**
     * Gets the current cursor position.
     *
     * @return int Current position between 0 and maxPosition.
     */
    public function GetPosition(): int
    {
        return $this->position;
    }

    /**
     * Gets the high water mark - the furthest position ever reached.
     *
     * This value never decreases, even if the cursor moves backward.
     * It represents completed work/progress.
     *
     * @return int The highest position this cursor has reached.
     */
    public function GetHighWaterMark(): int
    {
        return $this->furthestAdvance;
    }

    /**
     * Gets the maximum allowed position for this cursor.
     *
     * @return int The maxPosition set in constructor.
     */
    public function GetMaxPosition(): int
    {
        return $this->maxPosition;
    }

    /**
     * Moves the cursor forward by the specified offset.
     *
     * If the new position exceeds highWaterMark, the highWaterMark advances
     * and an 'advance' event is dispatched to all subscribers.
     *
     * @param int $offset Number of positions to move forward. Must be >= 0.
     * @return bool TRUE if new position is inside [0, maxPosition], FALSE otherwise.
     * @throws \InvalidArgumentException If offset is negative.
     */
    public function MoveForward(int $offset) : bool
    {
        if ($offset < 0)
        {
            throw new \InvalidArgumentException('Offset must be non-negative. Use moveBackward() to go back.');
            return false;
        }

        $newPosition = $this->position + $offset;

        if ($newPosition > $this->maxPosition)
        {
            return false;
        }

        $oldPosition = $this->position;
        $this->position = $newPosition;

        // High water mark logic: only advances if we pass it
        if ($this->position > $this->furthestAdvance)
        {
            $oldMark = $this->furthestAdvance;
            $this->furthestAdvance = $this->position;
            
            $this->onAdvance->Trigger();
        }

        return true;
    }

    /**
     * Moves the cursor backward by the specified offset.
     *
     * The highWaterMark is NOT affected by backward movement. Work is not undone.
     * No 'advance' event is dispatched when moving backward.
     *
     * @param int $offset Number of positions to move backward. Must be >= 0.
     * @return bool TRUE if new position is inside [0, maxPosition], FALSE otherwise.
     * @throws \InvalidArgumentException If offset is negative.
     */
    public function MoveBackward(int $offset) : bool
    {
        if ($offset < 0)
        {
            throw new \InvalidArgumentException('Offset must be non-negative.');
            return false;
        }

        $newPosition = $this->position - $offset;

        if ($newPosition < 0)
        {
            return false;
        }

        $this->position = $newPosition;
        // furtherAdvance intentionally unchanged

        return true;
    }

    /**
     * Sets the cursor to an absolute position.
     *
     * Behaves like moveForward/moveBackward depending on direction.
     * May trigger 'advance' event if moving forward past highWaterMark.
     *
     * @param int $position Target position between 0 and maxPosition.
     * @return bool TRUE if new position is inside [0, maxPosition], FALSE otherwise.
     */
    public function Seek(int $position) : bool
    {
        if ($position < 0 || $position > $this->maxPosition)
        {
            return false;
        }

        if ($position >= $this->position)
        {
            $this->moveForward($position - $this->position);
        }
        else
        {
            $this->moveBackward($this->position - $position);
        }

        return true;
    }

    /**
     * Subscribes a listener to cursor advance events.
     *
     * The callback is invoked only when the cursor moves forward past the
     * current highWaterMark. Backward movement does not trigger events.
     *
     * @param Seeker $listener Object of Seeker to handle the event.
     * @return void
     */
    public function OnAdvance(Seeker $listener): void
    {
        $this->onAdvance->Listen($listener);
    }

    /**
     * Checks if the cursor is currently at its high water mark.
     *
     * @return bool True if position === furthestAdvance.
     */
    public function IsSynchronized(): bool
    {
        return $this->position === $this->furthestAdvance;
    }

    public function count(): int
    {
        return $this->maxPosition;
    }
}