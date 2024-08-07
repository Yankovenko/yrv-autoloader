<?php

namespace YRV\Autoloader\Parser\Analyzers;

use YRV\Autoloader\Parser\Components\ClassComponent;

class ClassAnalyzer implements ContentAnalyzer
{
    use ComponentAnalyzerLibrary;

    private FunctionAnalyzer $functionAnalyzer;

    public function __construct()
    {
        $this->functionAnalyzer = $this->getFunctionAnalyzer();
    }

    public function extract(array &$tokens, $deleteExtracted = false): \Generator
    {
        $currentClassTokens = [];
        $isClass = false;
        $isAbstract = false;
        $isFinal = false;
        $nestedLevel = 0;

        $startedPos = -1;
        $endedPos = -1;

        foreach ($tokens as $pos => $token) {
            if (!isset($currentClassTokens[0]) && is_array($token) && in_array($token[0], [T_ABSTRACT, T_FINAL, T_CLASS], true)) {
                if ($token[0] === T_CLASS && $this->checkTokenIn($tokens, [T_DOUBLE_COLON, T_PAAMAYIM_NEKUDOTAYIM], $pos, false)) {
                    continue;
                }
                $followingTokens = array_slice($tokens, $pos + 1, 4);
                $tokenTypes = array_column($followingTokens, 0);

                $isClass = $token[0] === T_CLASS || in_array(T_CLASS, $tokenTypes, true);

                if (!$isClass) {
                    continue;
                }

                $startedPos = $pos;
                $isAbstract = $token[0] === T_ABSTRACT || in_array(T_ABSTRACT, $tokenTypes, true);
                $isFinal = $token[0] === T_FINAL || in_array(T_FINAL, $tokenTypes, true);
            } elseif (!$isClass) {
                continue;
            }

            $currentClassTokens[] = $token;
            if ($deleteExtracted) {
                unset ($tokens[$pos]);
            }


            if ($token === '{' || $token[0] === T_CURLY_OPEN) {
                $nestedLevel++;
            } elseif ($token === '}') {
                $nestedLevel--;

                if ($nestedLevel === 0) {
                    $endedPos = $pos;
                    $classComponent = $this->createClass($currentClassTokens);
                    $classComponent->isAbstract = $isAbstract;
                    $classComponent->isFinal = $isFinal;

                    $classComponent->tokenStartPos = $startedPos;
                    $classComponent->tokenEndPos = $endedPos;

                    array_walk($classComponent->methods, function ($method) use ($startedPos) {
                        $method->tokenStartPos += $startedPos;
                        $method->tokenEndPos += $startedPos;
                    });

                    yield $classComponent;

                    $currentClassTokens = [];
                    $isClass = false;
                    $isFinal = false;
                    $isAbstract = false;
                }
            }
        }

        $tokens = array_values($tokens);
    }

    private function createClass(array $tokens): ClassComponent
    {
        $component = new ClassComponent();

        $component->name = $this->extractName($tokens);
        $component->interfaces = $this->extractInterfaces($tokens);
        $component->extends = $this->extractExtends($tokens);

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