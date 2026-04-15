<?php
namespace Scry\PhpFile\Cursor;

interface TokenCursor
{
    public function CollectAttributes() : void;
    public function DropModifiers() : array;
    public function DropAttributes() : array;
    public function Current() : array|string|null;
    public function CurrentDetector() : int|string|null;
    public function CurrentChars() : string|null;
    public function Position() : int|bool;
    public function Advance() : int|bool;
    public function Unadvance() : int|bool;
    public function SeekDetector(int $position) : int|string|null;
    public function SeekChars(int $position) : string|null;
    public function GoTo(int $position) : bool;
    public function ConsumeBlock() : int;
    public function BlockLevel() : int;
    public function SkipUntil(int|string $token, int|string ...$expected) : bool;
    public function CollectUntil(array|string $stopTokens, array $collectRuleset = [T_WHITESPACE], bool $blacklist = true) : string;
    public function ReadQualifiedName() : string;
}