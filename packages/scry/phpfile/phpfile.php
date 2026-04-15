<?php
namespace Scry\PhpFile;
use Scry\PhpFile\Cursor\Cursor;
use Scry\PhpFile\Cursor\TokenCursor;
use Composer\Composer;
use Scry\PhpFile\Cursor\Events\AdvanceEventHandler;
use Scry\PhpFile\Cursor\Events\Collectors\AttributeCollector;
use Scry\PhpFile\Cursor\Events\Collectors\StepCollector;

include_once 'classparser.php';
include_once 'cursor/cursor.php';


class PhpFile implements TokenCursor
{
    private Cursor $cursor;

    private string $file;
    private string $content;
    private string|null $namespace;
    private array $using;
    private array $openFiles;
    private array $classes;

    private array $tokens;
    private int $tokensCount;

    // Collectors
    private AttributeCollector $attributesCollector;
    private StepCollector $stepCollector;
    private AdvanceEventHandler $stepCollectorHandler;

    private const CLOSE_SYMBOLS = [')', '}', ']', '>'];
    private const COMMENTS_OPEN = ['//', '/*', '# '];
    private const COMMENTS_CLOSE = ["\n", '*/', "\n"];
    private const OPEN_FILES_TARGET_TYPE =
    [
        'include',
        'include_once',
        'require',
        'require_once'
    ];
    public const MODIFIERS =
    [
        T_PRIVATE => 'private',
        T_PROTECTED => 'protected',
        T_PUBLIC => 'public',
        T_CONST => 'const',
        T_STATIC => 'static',
        T_ABSTRACT => 'abstract',
        T_FINAL => 'final',
    ];
    
    /**
     * Constructor for a Token by Token File Scan Thread
     * 
     * @property $fileLocation The file location of the .php file will be scanned.
     * @throws Exception Throws an error if the $fileLocation is not of .php file extension.
     */
    public function __construct(string $fileLocation)
    {
        $this->file = $fileLocation;

        if(!str_ends_with($fileLocation, '.php'))
        {
            Composer::Throw("Cannot create this File Scan Thread because the expected file '$fileLocation' is not a .php file.");
        }

        $this->content = file_get_contents($fileLocation);
        $this->using = [];
        $this->openFiles = [];
        $this->classes = [];
    }

    public function Tokenize()
    {
        $this->tokens = token_get_all($this->content);
        $this->tokensCount = count($this->tokens);
        $this->cursor = new Cursor($this->tokensCount - 1, $this);

        // Colectors
        $this->stepCollector = new StepCollector();
        $this->attributesCollector = new AttributeCollector();

        // Add Step Listener
        $this->stepCollectorHandler = $this->cursor->onAdvance->Listen($this->stepCollector);

        $this->ReadNamespace();
    }

/**
 * TokenCursor Implementation
 */
    public function CollectAttributes(): void
    {
        $newListener = $this->cursor->onAdvance->Listen($this->attributesCollector);
        $this->stepCollectorHandler->PauseUntil($newListener);
    }

    public function DropModifiers() : array
    {
        return [];
    }

    public function DropAttributes() : array
    {
        $result = $this->attributesCollector->Commit();
        return $result;
    }

    public function Position() : int|bool
    {
        return $this->cursor->GetPosition();
    }

    public function Advance() : int|bool
    {
        do
        {
            if(!$this->cursor->MoveForward(1))
            {
                return false; // FINAL catch
            }

            $current = $this->CurrentDetector();
        }
        while($current === T_WHITESPACE);

        return true;
    }

    public function Unadvance(): int|bool
    {
        // This method doesn't modify the furthest advance on cursor
        return $this->cursor->MoveBackward(1); // False at 0
    }

    public function Current() : array|string|null
    {
        return $this->tokens[$this->Position()] ?? null;
    }

    public function CurrentDetector() : int|string|null
    {
        $token = $this->Current();
        return $token[0] ?? $token;
    }

    public function CurrentChars() : string|null
    {
        $token = $this->Current();
        return $token[1] ?? $token;
    }

    public function SeekDetector(int $position) : int|string|null
    {
        $t = $this->tokens[$position] ?? null;
        return $t[0] ?? $t;
    }

    public function SeekChars(int $position) : string|null
    {
        $t = $this->tokens[$position] ?? null;
        return $t[1] ?? $t;
    }


    public function GoTo(int $position) : bool
    {
        return $this->cursor->Seek($position);
    }

    public function BlockLevel(): int
    {
        return $this->stepCollector->GetBlockLevel();
    }

    public function ConsumeBlock() : int
    {
        // For start, pointer must be already at '{' or passed it
        $start = $this->BlockLevel();

        // Advance until Advance() is true and CURRENT block level's inside $start
        while($this->Advance() && $start <= $this->BlockLevel());
        
        return $this->Position();
    }

    public function SkipUntil(int|string $token, int|string ...$expected) : bool
    {
        $expected[] = $token;
        $currentTokenDetector = null;
        $advance = true;
        $currentTokenDetector = $this->CurrentDetector();

        while(!in_array($currentTokenDetector, $expected) && $advance)
        {
            $advance = $this->Advance(); // Pre-increment, don't check self call
            $currentTokenDetector = $this->CurrentDetector();
        }

        return $advance;
    }

    public function CollectUntil(array|string $stopTokens, array $collectRuleset = [T_WHITESPACE], bool $blacklist = true) : string
    {
        $buffer = '';
        $stopTokens = (array)$stopTokens;

        if(!empty($collectRuleset))
        {
            $currentTokenDetector = $this->CurrentDetector();

            do
            {
                $inRuleset = in_array($currentTokenDetector, $collectRuleset);

                // In Ruleset && Not Blacklist OR Not In Ruleset && Blacklist
                if((!$blacklist && $inRuleset) || ($blacklist && !$inRuleset))
                {
                    $buffer .= $this->CurrentChars();
                }

                $advance = $this->Advance(); // Post-increment, first collect call origin
                $currentTokenDetector = $this->CurrentDetector();
            }
            while(!in_array($currentTokenDetector, $stopTokens) && $advance);
        }

        // When Ruleset is empty, skip every comparasion
        else
        {
            do
            {
                $currentTokenDetector = $this->CurrentDetector();
                $buffer .= $this->CurrentChars();
            }
            while($this->Advance() && !in_array($currentTokenDetector, $stopTokens)); // Post-increment
        }

        return $buffer;
    }

    public function ReadQualifiedName() : string
    {
        return $this->CollectUntil([';', '{', '}', ',', '(', ')', T_VARIABLE, T_EXTENDS, T_IMPLEMENTS], [T_STRING, '\\', T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], false);
    }

/**
 * PHP TOKENIZABLE FUNCTIONS
 */

    private function ReadNamespace() : string|null
    {
        $this->namespace = null;
        
        // Guide by NAMESPACE rule definition. Namespace must be the very first statement definition.
        // Only pass if token type is T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, OR T_DECLARE

        while($this->Advance())
        {
            $tokenDetector = $this->CurrentDetector(); // T_... or STRING

            switch($tokenDetector)
            {
                case T_WHITESPACE:
                case T_OPEN_TAG:
                case T_COMMENT:
                case T_DOC_COMMENT:
                    continue 2;
                case T_DECLARE:
                    while($this->Advance() && $this->CurrentDetector() != ';');
                    continue 2;
            }

            break;
        }

        $tokenDetector = $this->CurrentDetector();
        
        if($tokenDetector === T_NAMESPACE)
        {
            $this->namespace = $this->ReadQualifiedName();
        }

        return $this->namespace;
    }

    public function NextClass() : int|null
    {
        $nextClass = $this->SkipUntil(T_CLASS);

        if($nextClass)
        {
            $classThread = new ClassParser($this->Position(), $this);
            $classThread->BufferExport($this->classes);
            
            /*[
                "extends" => [],
                "implements" => [],
                "traits" => [],
                "properties" => [],
                "methods" => []
            ];*/
        }

        return $nextClass;
    }

    public function HasAttributes() : bool
    {
        $next = function(int $startPosition, int $direction=1)
        {
            $i = $startPosition;
            while($this->content[$i+$direction] === ' ');
        };

        $attributeFound = strpos($this->content, '#');
        $nextCharacter = $next($attributeFound, 1);

        if($nextCharacter === '[')
        {
            return true;
        }

        return false;
    }

    public function BufferExport(array &$target) : void
    {
        $target[$this->file] =
        [
            'namespace' => $this->namespace,                        
            'using' => $this->using,
            'opens' => $this->openFiles,
            'classes' => $this->classes
        ];
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

    public function GetNamespace() : string|null
    {
        return $this->namespace;
    }
}
