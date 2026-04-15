<?php
namespace Scry\PhpFile;

use Scry\PhpFile\Cursor\TokenCursor;
use Composer\Composer;

class ClassParser
{
    private string $name;
    private array $attributes = [];
    private array $properties = [];
    private array $methods = [];
    private array $resultMaps = [];

    private string|null $lastVisibility = null;
    private bool $lastStatic = false;
    private bool $lastConst = false;
    private string|null $lastType = null;

    private int $startPosition;
    private TokenCursor $cursor;


    public function __construct(int $startPosition, TokenCursor $cursor)
    {
        if($cursor->CurrentDetector() !== T_CLASS)
        {
            Composer::Throw("Cannot parse class. Expected start token of T_CLASS.");
        }

        $this->startPosition = $startPosition;
        $this->cursor = $cursor;

        $this->name = $this->cursor->ReadQualifiedName() ?? 'UnknownClass';
        $this->attributes = $this->cursor->DropAttributes();

        $this->resultMaps['attributes'] = $this->attributes;
        $this->resultMaps['properties'] = [];
        $this->resultMaps['methods'] = [];
    }

    public function BufferExport(array &$target) : void
    {
        // Move pointer to class body start, actual token will be '{'.
        $this->cursor->GoTo($this->startPosition);
        $this->cursor->SkipUntil('{');
        $startLevel = $this->cursor->BlockLevel();
        $lastTokenIsVisibility = false;

        while ($this->cursor->Advance() && $startLevel <= $this->cursor->BlockLevel())
        {
            $tokenDetector = $this->cursor->CurrentDetector();

            switch ($tokenDetector)
            {
                case T_PUBLIC:
                case T_PROTECTED:
                case T_PRIVATE:
                    $lastTokenIsVisibility = true;
                    $this->lastVisibility = PhpFile::MODIFIERS[$tokenDetector];
                    continue 2; // Skip resetting visibility after switch since we need it for properties and methods
                    break;

                case T_STATIC:
                    $this->lastStatic = true;
                    break;

                case T_CONST:
                    $this->lastConst = true;
                    break;

                case T_STRING:
                    // Break inmediatly if const is detected.
                    if($this->lastConst)
                    {
                        $this->ParseProperty();
                    }
                    break;

                case T_STRING:
                case '?':
                case T_NAME_FULLY_QUALIFIED:
                case T_ARRAY:
                case T_NS_SEPARATOR:
                    if($lastTokenIsVisibility) $this->lastType = $this->ParseType();
                    break;

                case T_VARIABLE:
                    if($this->lastVisibility !== null || $this->lastStatic || $this->lastType !== null || !empty($this->attributesBuffer))
                    {
                        $this->ParseProperty();
                    }
                    break;
                
                case T_FUNCTION:
                    $this->ParseFunction();
                    break;
            }
            
            $lastTokenIsVisibility = false;
        }

        $target[$this->name] = $this->resultMaps;
    }

/**
______     _            _         _____      _             __               
| ___ \   (_)          | |       |_   _|    | |           / _|              
| |_/ / __ ___   ____ _| |_ ___    | | _ __ | |_ ___ _ __| |_ __ _  ___ ___ 
|  __/ '__| \ \ / / _` | __/ _ \   | || '_ \| __/ _ \ '__|  _/ _` |/ __/ _ \
| |  | |  | |\ V / (_| | ||  __/  _| || | | | ||  __/ |  | || (_| | (_|  __/
\_|  |_|  |_| \_/ \__,_|\__\___|  \___/_| |_|\__\___|_|  |_| \__,_|\___\___|
 */

    private function SetPropertyResult(array $map)
    {
        $this->resultMaps['properties'] += $map;
    }

    private function SetFunctionResult(array $map)
    {
        $this->resultMaps['methods'] += $map;
    }

    private function ParseProperty()
    {
        // A property can be defined as: [attributes] [visibility] [type] $name [= default];
        // We start from $name and look backwards for [visibility] and [type], and forwards for [default].
        $name = ltrim($this->cursor->CurrentChars(), '$');
        $this->properties[] = $name;

        $results =
        [
            $name =>
            [
                'attributes' => $this->cursor->DropAttributes(),
                'visibility' => $this->lastVisibility ?? 'public',
                'type' => $this->lastType ?? 'mixed',
                'static' => $this->lastStatic,
                'const' => $this->lastConst,
                'default' => $this->ParseDefaultValue()
            ]
        ];

        $this->SetPropertyResult($results);
        
        $this->lastVisibility = null; // Reset after use
        $this->lastType = null; // Reset after use
        $this->lastStatic = false; // Reset after use
    }

    private function ParseFunction()
    {
        // Similar to ParseProperty but for methods. We can look for return type and parameters as well.
        $name = $this->cursor->ReadQualifiedName();
        $this->methods[] = $name;

        $results = [
            $name =>
            [
                'attributes' => $this->attributes = $this->cursor->DropAttributes(),
                'visibility' => $this->lastVisibility ?? 'public',
                'static' => $this->lastStatic
            ]
        ];


        // Look for return type (after parameters) before the method body starts
        $this->cursor->SkipUntil(':', '{');
        $first = $this->cursor->CurrentDetector();

        // If ':' was found FIRST
        if ($first === ':')
        {
            $this->cursor->Advance(); // Move past ':'
            $returnType = $this->ParseType();
        }

        $results[$name]['type'] = $returnType ?? 'mixed';

        $this->cursor->SkipUntil('{'); // Starts at '{' for correct block consumation.
        $this->cursor->ConsumeBlock();

        $this->SetFunctionResult($results);

        $this->lastVisibility = null; // Reset after use
        $this->lastStatic = false; // Reset after use
    }

    private function ParseType(): string|null
    {
        $this->cursor->Unadvance();
        $this->cursor->SkipUntil(T_STRING, '?', T_NAME_QUALIFIED, T_ARRAY, T_NAME_FULLY_QUALIFIED, T_NS_SEPARATOR, '{');
        $nextStop = $this->cursor->CurrentDetector();

        if($nextStop === '{' || $nextStop === null)
        {
            return 'mixed'; // No type declared, default to mixed
        }

        $type = $this->cursor->CollectUntil([T_VARIABLE, '{']);
        return $type;
    }


    private function ParseDefaultValue()
    {
        $this->cursor->SkipUntil('=', ';');
        $currentToken = $this->cursor->CurrentChars();

        if ($currentToken === '=')
        {
            $this->cursor->Advance(); // Move past '='
            $result = $this->cursor->CollectUntil(';');

            return $result;
        }

        return null;
    }
}
