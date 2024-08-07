<?php

namespace YRV\Autoloader\Parser\Analyzers;

use Generator;
use YRV\Autoloader\Parser\Components\InterfaceComponent;

class InterfaceAnalyzer implements ContentAnalyzer
{
    use ComponentAnalyzerLibrary;

    private FunctionAnalyzer $functionAnalyzer;

    public function __construct()
    {
        $this->functionAnalyzer = $this->getFunctionAnalyzer();
    }

    public function extract(array &$tokens, $deleteExtracted = false): Generator
    {
        $currentInterfaceTokens = [];
        $isInterface = false;
        $nestedLevel = 0;

        $startedPos = -1;

        foreach ($tokens as $pos => $token) {
            if (!isset($currentInterfaceTokens[0]) && is_array($token) && in_array($token[0], [T_INTERFACE], true)) {
                $followingTokens = array_slice($tokens, $pos + 1, 4);
                $tokenTypes = array_column($followingTokens, 0);

                $isInterface = $token[0] === T_INTERFACE || in_array(T_INTERFACE, $tokenTypes, true);

                if (!$isInterface) {
                    continue;
                }

                $startedPos = $pos;

            } elseif (!$isInterface) {
                continue;
            }

            $currentInterfaceTokens[] = $token;
            if ($deleteExtracted) {
                unset ($tokens[$pos]);
            }

            if ($token === '{' || $token[0] === T_CURLY_OPEN) {
                $nestedLevel++;
            } elseif ($token === '}') {
                $nestedLevel--;

                if ($nestedLevel === 0) {
                    $endedPos = $pos;
                    $interfaceComponent = $this->createInterface($currentInterfaceTokens);

                    $interfaceComponent->tokenStartPos = $startedPos;
                    $interfaceComponent->tokenEndPos = $endedPos;

                    array_walk($interfaceComponent->methods, function ($method) use ($startedPos) {
                        $method->tokenStartPos += $startedPos;
                        $method->tokenEndPos += $startedPos;
                    });

                    yield $interfaceComponent;

                    $currentInterfaceTokens = [];
                    $isInterface = false;
                }
            }
        }
        $tokens = array_values($tokens);

    }

    private function createInterface(array $tokens): InterfaceComponent
    {
        $component = new InterfaceComponent();

        $component->name = $this->extractName($tokens);
        $component->extends = $this->extractExtends($tokens);

        $innerTokens = $tokens;

        foreach ($tokens as $pos => $token) {
            if ($token === '{') {
                $innerTokens = array_slice($tokens, $pos + 1, count($tokens) - $pos - 2);
                break;
            }
        }
        foreach ($this->extractMethods($innerTokens) as $method) {
            $method->tokenStartPos += $pos + 1;
            $method->tokenEndPos += $pos + 1;
            $component->methods[] = $method;
        }

        $component->properties = $this->extractProperties($innerTokens);
        $component->constants = $this->extractConstants($innerTokens, true);

        return $component;
    }

    private function extractMethods(array $tokens): array
    {
        $methods = [];

        foreach ($this->functionAnalyzer->extract($tokens) as $component) {
            $methods[] = $component;
        }

        return $methods;
    }

}