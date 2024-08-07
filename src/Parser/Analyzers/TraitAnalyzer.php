<?php

namespace YRV\Autoloader\Parser\Analyzers;


use Generator;
use YRV\Autoloader\Parser\Components\TraitComponent;

class TraitAnalyzer implements ContentAnalyzer
{
    use ComponentAnalyzerLibrary;

    private FunctionAnalyzer $functionAnalyzer;

    public function __construct()
    {
        $this->functionAnalyzer = $this->getFunctionAnalyzer();
    }

    public function extract(array &$tokens, $deleteExtracted = false): Generator
    {
        $currentTraitTokens = [];
        $isTrait = false;
        $nestedLevel = 0;

        $startedPos = -1;

        foreach ($tokens as $pos => $token) {
            if (!isset($currentTraitTokens[0]) && is_array($token) && $token[0] === T_TRAIT) {
                $isTrait = true;
                $startedPos = $pos;

            } elseif (!$isTrait) {
                continue;
            }

            $currentTraitTokens[] = $token;
            if ($deleteExtracted) {
                unset ($tokens[$pos]);
            }

            if ($token === '{' || $token[0] === T_CURLY_OPEN) {
                $nestedLevel++;
            } elseif ($token === '}') {
                $nestedLevel--;

                if ($nestedLevel === 0) {
                    $endedPos = $pos;
                    $traitComponent = $this->createTrait($currentTraitTokens);

                    $traitComponent->tokenStartPos = $startedPos;
                    $traitComponent->tokenEndPos = $endedPos;

                    array_walk($traitComponent->methods, function ($method) use ($startedPos) {
                        $method->tokenStartPos += $startedPos;
                        $method->tokenEndPos += $startedPos;
                    });

                    yield $traitComponent;

                    $currentTraitTokens = [];
                    $isTrait = false;
                }
            }
        }

        $tokens = array_values($tokens);
    }

    private function createTrait(array $tokens): TraitComponent
    {
        $component = new TraitComponent();

        $component->name = $this->extractName($tokens);
        $component->traits = $this->extractTraits($tokens);

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
        $component->constants = $this->extractConstants($tokens, true);

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