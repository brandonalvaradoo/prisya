<?php
namespace Scry\PhpFile\Cursor\Events\Collectors;
use Scry\PhpFile\Cursor\Events\Seeker;
use Scry\PhpFile\Cursor\TokenCursor;
use Scry\PhpFile\Cursor\Events\AdvanceEventHandler;
use Scry\PhpFile\PhpFile;

class StepCollector implements Seeker
{
    private int $blockLevel = 0;
    private array $modifiersBuffer = [];


    public function Handle(TokenCursor &$handligTokenCursor, AdvanceEventHandler $handler) : void
    {
        $detector = $handligTokenCursor->CurrentDetector();
        switch($detector)
        {
            case '{':
                $this->blockLevel++;
                break;
            case '}':
                $this->blockLevel--;
                break;
            case T_PRIVATE:
            case T_PROTECTED:
            case T_PUBLIC:
            case T_CONST:
            case T_STATIC:
            case T_ABSTRACT:
            case T_FINAL:
                $this->modifiersBuffer[] = PhpFile::MODIFIERS[$detector];
                break;
            case T_ATTRIBUTE:
                $handligTokenCursor->CollectAttributes();
                break;
        }

    }

    public function GetBlockLevel() : int
    {
        return $this->blockLevel;
    }

    public function CommitModifiers() : array
    {

    }
}