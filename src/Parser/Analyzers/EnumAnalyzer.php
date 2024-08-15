<?php

namespace YRV\Autoloader\Parser\Analyzers;

use YRV\Autoloader\Parser\Components\EnumComponent;

class EnumAnalyzer implements ContentAnalyzer
{
    use ComponentAnalyzerLibrary;

    private FunctionAnalyzer $functionAnalyzer;

    public function __construct()
    {
        $this->functionAnalyzer = $this->getFunctionAnalyzer();
    }

    public function extract(array &$tokens, $deleteExtracted = false): \Generator
    {
        $currentEnumTokens = [];
        $isEnum = false;
        $nestedLevel = 0;

        $startedPos = -1;

        foreach ($tokens as $pos => $token) {
            if (!isset($currentEnumTokens[0]) && is_array($token) && $token[0] === T_ENUM) {
                $isEnum = true;
                $startedPos = $pos;

            } elseif (!$isEnum) {
                continue;
            }

            $currentEnumTokens[] = $token;
            if ($deleteExtracted) {
                unset ($tokens[$pos]);
            }

            if ($token === '{' || $token[0] === T_CURLY_OPEN) {
                $nestedLevel++;
            } elseif ($token === '}') {
                $nestedLevel--;

                if ($nestedLevel === 0) {
                    $endedPos = $pos;
                    $traitComponent = $this->createEnum($currentEnumTokens);

                    $traitComponent->tokenStartPos = $startedPos;
                    $traitComponent->tokenEndPos = $endedPos;

                    array_walk($traitComponent->methods, function ($method) use ($startedPos) {
                        $method->tokenStartPos += $startedPos;
                        $method->tokenEndPos += $startedPos;
                    });

                    yield $traitComponent;

                    $currentEnumTokens = [];
                    $isTrait = false;
                }
            }
        }

        $tokens = array_values($tokens);
    }

    /**
     * @throws \Exception
     */
    private function createEnum(array $tokens): EnumComponent
    {
        $component = new EnumComponent();

        $component->name = $this->extractName($tokens);
        $component->interfaces = $this->extractInterfaces($tokens);

        $innerTokens = $tokens;

        foreach ($tokens as $pos => $token) {
            if ($token === '{') {
                $innerTokens = array_slice($tokens, $pos + 1, count($tokens) - $pos - 2);
                break;
            }
        }

        $component->properties = $this->extractProperties($innerTokens);
        foreach ($this->extractMethods($innerTokens, true) as $method) {
            $method->tokenStartPos += $pos + 1;
            $method->tokenEndPos += $pos + 1;
            $component->methods[] = $method;
        }
        $component->traits = $this->extractTraits($tokens);
        $component->constants = $this->extractConstants($tokens, true);

        return $component;
    }

    private function extractInterfaces(array $tokens): array
    {
        $interfaces = [];
        $currInterface = '';

        $interfaceFound = false;

        foreach ($tokens as $token) {
            if ($interfaceFound && $token === '{') {
                if ($currInterface && $currInterface[0] !== '') {
                    $interfaces[] = $currInterface;
                }

                break;
            }

            if ($interfaceFound && $token === ',' && $currInterface !== '') {
                $interfaces[] = $currInterface;
                $currInterface = '';
                continue;
            }

            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_IMPLEMENTS) {
                $interfaceFound = true;
            } elseif ($interfaceFound && in_array($token[0], [T_STRING, T_NS_SEPARATOR], true)) {
                $currInterface .= $token[1];
            } elseif ($interfaceFound && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_RELATIVE, T_NAME_FULLY_QUALIFIED], true)) {
                $currInterface .= $token[1];
            }
        }

        return $interfaces;
    }

    private function extractMethods(array &$tokens, $deleteExtracted = false): array
    {
        $methods = [];

        foreach ($this->functionAnalyzer->extract($tokens, $deleteExtracted) as $component) {
            $methods[] = $component;
        }

        return $methods;
    }

}