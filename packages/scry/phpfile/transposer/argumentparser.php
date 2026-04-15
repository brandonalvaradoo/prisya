<?php

namespace App\ReflectionFactory;

use App\ReflectionFactory\Exception\ArgumentParseException;
use Exception;

/**
 * Parses a raw PHP argument string into discrete argument expressions.
 *
 * Handles nesting: new Foo(new Bar(1), 'x') becomes 2 args.
 * Does not execute code, only splits safely respecting quotes and parens.
 */
class ArgumentParser
{
    /**
     * Splits raw arguments string into individual argument expressions.
     *
     * Example: "10, new User($id), 'hello, world'" => ["10", "new User($id)", "'hello, world'"]
     *
     * @param string $raw Raw arguments like "1, 'a', new Foo()"
     * @return string[] Array of argument expressions.
     * @throws ArgumentParseException On unbalanced brackets/quotes.
     */
    public function split(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $args = [];
        $current = '';
        $depth = 0; // () [] {}
        $inString = null; // ' or "
        $escaped = false;

        for ($i = 0, $len = strlen($raw); $i < $len; $i++) {
            $char = $raw[$i];

            if ($escaped) {
                $current.= $char;
                $escaped = false;
                continue;
            }

            if ($char === '\\') {
                $escaped = true;
                $current.= $char;
                continue;
            }

            if ($inString!== null) {
                $current.= $char;
                if ($char === $inString) {
                    $inString = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'") {
                $inString = $char;
                $current.= $char;
                continue;
            }

            if ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
            } elseif ($char === ')' || $char === ']' || $char === '}') {
                $depth--;
                if ($depth < 0) {
                    throw new Exception("Unbalanced brackets at position $i");
                }
            }

            if ($char === ',' && $depth === 0) {
                $args[] = trim($current);
                $current = '';
                continue;
            }

            $current.= $char;
        }

        if ($depth!== 0) {
            throw new Exception('Unbalanced brackets in arguments');
        }
        if ($inString!== null) {
            throw new Exception('Unterminated string in arguments');
        }

        if ($current!== '') {
            $args[] = trim($current);
        }

        return $args;
    }
}