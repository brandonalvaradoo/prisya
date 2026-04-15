<?php
namespace Scry\PhpFile\Cursor\Events\Collectors;
use Scry\PhpFile\Cursor\Events\Seeker;
use Scry\PhpFile\Cursor\TokenCursor;
use Scry\PhpFile\Cursor\Events\AdvanceEventHandler;

/**
 * Collects PHP 8 attributes from a token stream using a state machine.
 *
 * Parses structures like #[Route('/home'), Cache(60)] and exports them as:
 * [
 * 'Route' => ["'/home'"],
 * 'Cache' => ['60']
 * ]
 *
 * This class assumes the first token handled is '#['. It unlistens itself
 * when the closing ']' of the attribute group is consumed.
 *
 * Complexity: O(n) where n is the number of tokens inside #[...].
 * Memory: O(k) where k is total length of attribute names + args.
 */
class AttributeCollector implements Seeker
{
    /** @var array<string, list<string>> Collected attributes. Name => raw arg strings. */
    private array $attributes = [];

    /** @var int Tracks nested () depth. 0 = top level of attribute list. */
    private int $parenDepth = 0;

    /** @var int Tracks nested [] depth. Starts at 1 after #[. 0 = done. */
    private int $bracketDepth = 1;

    /** @var string[] Buffers characters for the current attribute name. */
    private array $nameBuffer = [];

    /** @var string[] Buffers characters for the current argument. */
    private array $argBuffer = [];

    /** @var string|null Current attribute name being processed. Null if none. */
    private?string $currentAttribute = null;

    /** @var array<string, list<string>> Export target. */
    private array $buffer = [];

    /**
     * Handles a single token from the TokenCursor.
     *
     * This is a state machine. It tracks [] and () depth to know if we are
     * at the attribute list level, inside an attribute, or inside an argument.
     *
     * @param TokenCursor &$cursor Active cursor interface. Provides CurrentDetector() and CurrentChars().
     * @param AdvanceEventHandler $handler Used to Unlisten() when parsing is complete.
     * @return void
     */
    public function Handle(TokenCursor &$cursor, AdvanceEventHandler $handler): void
    {
        $type = $cursor->currentDetector();
        $chars = $cursor->currentChars();

        // Fast path: if we are inside an argument, just buffer everything.
        if ($this->parenDepth >= 1)
        {
            $this->bufferChar($chars, $type);
        }

        switch ($type)
        {
            case T_STRING:
            case T_NAME_QUALIFIED:
            case T_NAME_FULLY_QUALIFIED:
            case T_DOUBLE_COLON:
                // Only buffer name if we are not inside () yet.
                if ($this->parenDepth === 0)
                {
                    $this->nameBuffer[] = $chars;
                }
                break;

            case '[':
                $this->bracketDepth++;
                break;

            case ']':
                $this->bracketDepth--;
                if ($this->bracketDepth === 0)
                {
                    $this->Finalize();
                    $handler->End(); // End and continue
                }
                break;

            case '(':
                $this->parenDepth++;
                // When we open first (, the name is complete.
                if ($this->parenDepth === 1)
                {
                    $this->CommitName();
                }
                break;

            case ')':
                $this->parenDepth--;
                // When we close last ), the argument is complete.
                if ($this->parenDepth === 0)
                {
                    $this->CommitArgument();
                }
                break;

            case ',':
                if ($this->parenDepth === 0)
                {
                    // Top level comma: #[A, B] -> finish A
                    $this->CommitName();
                }
                elseif ($this->parenDepth === 1)
                {
                    // Arg level comma: #[A(1, 2)] -> finish arg 1
                    $this->CommitArgument();
                }
                break;
        }
    }

    /**
     * Buffers chars into argBuffer when inside parentheses.
     * Uses array instead of string.= to avoid O(n^2) reallocations.
     */
    private function BufferChar(string $chars, string|int $type): void
    {
        // Skip the opening ( itself, we only want inner content.
        if ($type === '(' && $this->parenDepth === 1)
        {
            return;
        }
        // Skip ) and, because they are delimiters handled in switch.
        if ($type === ')')
        {
            return;
        }
        $this->argBuffer[] = $chars;
    }

    /**
     * Commits nameBuffer into currentAttribute and creates entry in attributes.
     * Safe to call multiple times. No-op if buffer empty.
     */
    private function CommitName(): void
    {
        if ($this->nameBuffer === [])
        {
            return;
        }

        $name = implode('', $this->nameBuffer);
        $this->nameBuffer = [];

        if ($name === '') {
            return;
        }

        $this->currentAttribute = $name;
        if (!isset($this->attributes[$name]))
        {
            $this->attributes[$name] = [];
        }
    }

    /**
     * Commits argBuffer into the current attribute's argument list.
     * No-op if no current attribute or buffer empty.
     */
    private function CommitArgument(): void
    {
        if ($this->currentAttribute === null || $this->argBuffer === []) {
            $this->argBuffer = [];
            return;
        }

        $arg = implode('', $this->argBuffer);
        $this->argBuffer = [];

        $arg = trim($arg);
        if ($arg!== '') {
            $this->attributes[$this->currentAttribute][] = $arg;
        }
    }

    /**
     * Finalizes parsing. Commits any pending name and merges results to export.
     * Called when bracketDepth reaches 0.
     */
    private function Finalize()
    {
        $this->CommitName(); // Handle last attribute like #[Single] or #[A, B]
        $this->CommitArgument(); // Handle last arg if ended with )

        // Merge results. Use += to preserve existing keys in export.
        $this->buffer += $this->attributes;
        $this->attributes = [];
        $this->bracketDepth = 1;
        $this->parenDepth = 0;
    }

    public function Commit() : array
    {
        $result = $this->buffer;
        $this->buffer = [];
        return $result;
    }
}