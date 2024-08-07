<?php

namespace YRV\Autoloader\Parser\Analyzers;

use Exception;
use Generator;
use YRV\Autoloader\Parser\Components\FunctionComponent;

class FunctionAnalyzer implements ContentAnalyzer
{
    use ComponentAnalyzerLibrary;

    private ParamAnalyzer $paramAnalyzer;

    public function __construct()
    {
        $this->paramAnalyzer = $this->getParamAnalyzer();
    }

    public function extract(array &$tokens, $deleteExtracted = false): Generator
    {
        $isMethod = false;
        $isAbstract = false;
        $isStatic = false;
        $isFinal = false;
        $finished = false;
        $visibility = 'public';
        $nestedLevel = 0;
        $startedPos = null;

        $currFunctionTokens = [];

        foreach ($tokens as $pos => $token) {
            if (!$isMethod
                && is_array($token)
                && in_array($token[0], [T_FINAL, T_ABSTRACT, T_FUNCTION, T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_READONLY], true)
            ) {
                $followingTypes = [];

                foreach (array_slice($tokens, $pos + 1) as $followingToken) {
                    if (!is_array($followingToken)) {
                        break;
                    }

                    if (in_array($followingToken[0], [T_WHITESPACE, T_COMMENT], true)) {
                        continue;
                    }

                    if (!in_array($followingToken[0], [T_FINAL, T_ABSTRACT, T_FUNCTION, T_PUBLIC, T_PROTECTED, T_PRIVATE], true)) {
                        break;
                    }

                    $followingTypes[] = $followingToken[0];
                }
                if ($token[0] === T_FUNCTION && $this->checkTokenIn($tokens, [T_USE], $pos, false)) {
                    continue;
                }
                if ($token[0] === T_FUNCTION && $this->checkTokenIn($tokens, ['('], $pos)) {
                    continue;
                }

                $isMethod = $token[0] === T_FUNCTION || in_array(T_FUNCTION, $followingTypes, true);

                if (!$isMethod) {
                    continue;
                }

                $startedPos = $pos;
                $isAbstract = $token[0] === T_ABSTRACT || in_array(T_ABSTRACT, $followingTypes, true);
                $isStatic = $token[0] === T_STATIC || in_array(T_STATIC, $followingTypes, true);
                $isFinal = $token[0] === T_FINAL || in_array(T_FINAL, $followingTypes, true);

                $visibilityTokens = in_array($token[0], [T_PUBLIC, T_PRIVATE, T_PROTECTED], true)
                    ? [$token[0]]
                    : array_intersect([T_PUBLIC, T_PRIVATE, T_PROTECTED], $followingTypes);
                $visibilityTokens = array_values($visibilityTokens);

                if ($visibilityTokens !== []) {
                    switch ($visibilityTokens[0]) {
                        case T_PUBLIC:
                            $visibility = 'public';
                            break;
                        case T_PROTECTED:
                            $visibility = 'protected';
                            break;
                        case T_PRIVATE:
                            $visibility = 'private';
                            break;
                    }
                } else {
                    $visibility = 'public';
                }
            }

            if (!$isMethod) {
                continue;
            }

            $currFunctionTokens[] = $token;
            if ($deleteExtracted) {
                unset ($tokens[$pos]);
            }

            if ($token === '{' || $token[0] === T_CURLY_OPEN) {
                $nestedLevel++;
            } elseif ($token === '}') {
                $nestedLevel--;
                if (!$nestedLevel) {
                    $finished = true;
                }
            } elseif ($token === ';' && !$nestedLevel) {
                $finished = true;
            }

            if ($finished) {
                $functionComponent = $this->createFunction($currFunctionTokens);
                $functionComponent->isStatic = $isStatic;
                $functionComponent->isFinal = $isFinal;
                $functionComponent->isAbstract = $isAbstract;
                $functionComponent->visibility = $visibility;
                $functionComponent->tokenStartPos = $startedPos;
                $functionComponent->tokenEndPos = $pos;

                yield $functionComponent;

                $isMethod = false;
                $isAbstract = false;
                $isStatic = false;
                $isFinal = false;
                $visibility = 'public';
                $nestedLevel = 0;
                $startedPos = null;
                $finished = false;

                $currFunctionTokens = [];
            }
        }

        $tokens = array_values($tokens);
    }

    private function createFunction(array $tokens): FunctionComponent
    {
        $innerTokens = [];
        $headerTokens = $tokens;

        foreach ($tokens as $pos => $token) {
            if ($token === '{') {
                $innerTokens = array_slice($tokens, $pos + 1, count($tokens) - $pos - 2);
                $headerTokens = array_slice($tokens, 0, $pos - 1);
                break;
            }
        }

        $functionComponent = new FunctionComponent();

        try {
            $functionComponent->name = $this->extractName($headerTokens);
        } catch (Exception $e) {
            // TODO information
        }

        $functionComponent->params = $this->extractParams($headerTokens);
        $functionComponent->returnType = $this->extractReturnTypeHint($headerTokens);
        $functionComponent->callFunctions = $this->extractCalledFunctions($innerTokens);
        $functionComponent->instantiated = $this->extractInstantiatedClasses($innerTokens);
        $functionComponent->usedConstants = $this->extractUsedConstants($innerTokens);

        return $functionComponent;
    }

    private function extractParams(array $tokens): array
    {
        $params = [];

        foreach ($this->paramAnalyzer->extract($tokens) as $paramComponent) {
            $params[] = $paramComponent;
        }

        return $params;
    }

    private function extractInstantiatedClasses(array $tokens): array
    {
        $instantiated = [];

        $tokens = array_filter($tokens, function ($token) {
            return !is_array($token) || $token[0] !== T_WHITESPACE;
        });

        $tokens = array_values($tokens);

        foreach ($tokens as $pos => $token) {
            if (!is_array($token) || $token[0] !== T_STRING || !isset($tokens[$pos - 1]) || !is_array($tokens[$pos - 1])) {
                continue;
            }

            if ($tokens[$pos - 1][0] === T_NEW
                || ($tokens[$pos - 1][0] === T_NS_SEPARATOR
                    && isset($tokens[$pos - 2])
                    && is_array($tokens[$pos - 2])
                    && $tokens[$pos - 2][0] === T_NEW
                )
            ) {
                $className = $tokens[$pos - 1][0] === T_NS_SEPARATOR
                    ? $tokens[$pos - 1][1]
                    : '';

                for ($i = $pos; isset($tokens[$i]) && is_array($tokens[$i]) && in_array($tokens[$i][0], [T_NS_SEPARATOR, T_STRING], true); $i++) {
                    $className .= $tokens[$i][1];
                }

                $instantiated[] = $className;
            }
        }

        return array_unique($instantiated);
    }


    private function extractReturnTypeHint(array $tokens): ?string
    {
        $typehint = null;

        foreach ($tokens as $pos => $token) {
            if ($token !== ':') {
                continue;
            }

            $typehintTokens = array_slice($tokens, $pos);
            $typehint = array_reduce($typehintTokens, function ($acc, $token) {
                if ($token === '?') {
                    return '?';
                }

                if (!is_array($token) || !in_array($token[0], [T_NS_SEPARATOR, T_STRING])) {
                    return $acc;
                }


                return is_string($acc)
                    ? $acc . $token[1]
                    : $token[1];
            });

            return $typehint;
        }

        return $typehint;
    }
}