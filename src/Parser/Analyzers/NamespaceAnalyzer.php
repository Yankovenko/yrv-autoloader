<?php

namespace YRV\Autoloader\Parser\Analyzers;

use Generator;
use YRV\Autoloader\Parser\Components\NamespaceComponent;

class NamespaceAnalyzer implements ContentAnalyzer
{
    private ClassAnalyzer $classAnalyzer;
    private FunctionAnalyzer $functionAnalyzer;
    private InterfaceAnalyzer $interfaceAnalyzer;
    private TraitAnalyzer $traitAnalyzer;
    use ComponentAnalyzerLibrary;

    public function __construct()
    {
        $this->classAnalyzer = $this->getClassAnalyzer();
        $this->functionAnalyzer = $this->getFunctionAnalyzer();
        $this->interfaceAnalyzer = $this->getInterfaceAnalyzer();
        $this->traitAnalyzer = $this->getTraitAnalyzer();
    }

    public function extract(array &$tokens, $deleteExtracted = false): Generator
    {
        foreach ($this->extractNamespaces($tokens) as $component) {
            yield $component;
        }
    }

    /**
     * @param array $tokens
     * @return NamespaceComponent[]
     */
    private function extractNamespaces(array $tokens): array
    {
        $namespaces = [];

        $isNamespace = false;
        $isNested = false;
        $nestedLevel = 0;
        $currentNamespace = '';
        $startedPos = null;

        foreach ($tokens as $pos => $token) {
            if (!$isNamespace && !$isNested) {
                if (is_array($token) && $token[0] === T_NAMESPACE) {
                    $isNamespace = true;
                    $startedPos = $pos;
                }
            } elseif ($isNamespace && is_array($token) && in_array($token[0], [T_NS_SEPARATOR, T_STRING], true)) {
                $currentNamespace .= $token[1];
                $startedPos = $pos+1;
            } elseif ($isNamespace && is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $currentNamespace .= $token[1];
                $startedPos = $pos+1;
            } elseif ($isNamespace && $token === ';') {
                $namespaceComponent = new NamespaceComponent();
                $namespaceComponent->name = $currentNamespace;
                $namespaceComponent->tokenStartPos = $startedPos;
                $namespaces[] = $namespaceComponent;

                $isNamespace = false;
                $startedPos = null;
                $currentNamespace = '';
                $isNested = false;
                $nestedLevel = 0;
            } elseif (($isNamespace || $isNested) && ($token === '{' || $token[0] === T_CURLY_OPEN)) {
                $startedPos = $pos+1;
                $isNamespace = false;
                $isNested = true;
                $nestedLevel++;
            } elseif ($isNested && $token === '}') {
                $nestedLevel--;

                if ($nestedLevel === 0) {
                    $namespaceComponent = new NamespaceComponent();
                    $namespaceComponent->name = $currentNamespace;
                    $namespaceComponent->tokenStartPos = $startedPos;
                    $namespaceComponent->tokenEndPos = $pos;
                    $namespaces[] = $namespaceComponent;

                    $startedPos = null;
                    $currentNamespace = '';
                    $isNested = false;
                }
            }
        }

        if (isset($namespaces[0]) && !isset($namespaces[1]) && $namespaces[0]->tokenEndPos === null) {
            $namespaces[0]->tokenEndPos = $pos;
        } elseif (isset($namespaces[1])) {
            for ($i = count($namespaces) - 1; isset($namespaces[$i]); $i--) {
                if ($namespaces[$i]->tokenEndPos !== null) {
                    continue;
                }

                $namespaces[$i]->tokenEndPos = !isset($namespaces[$i + 1])
                    ? $pos
                    : $namespaces[$i + 1]->tokenStartPos - 1;
            }
        } elseif (!isset($namespaces[0])) {
            $namespaceComponent = new NamespaceComponent();
            $namespaceComponent->name = '';
            $namespaceComponent->tokenStartPos = 0;
            $namespaceComponent->tokenEndPos = $pos;

            $namespaces[] = $namespaceComponent;
        }

        $namespaces = array_map(function (NamespaceComponent $namespace) use ($tokens) {
            $namespaceTokens = array_slice($tokens, $namespace->tokenStartPos, $namespace->tokenEndPos - $namespace->tokenStartPos + 1, true);


            foreach ($this->classAnalyzer->extract($namespaceTokens, true) as $classComponent) {
                $namespace->classes[] = $classComponent;
            }

            foreach ($this->traitAnalyzer->extract($namespaceTokens, true) as $traitComponent) {
                $namespace->traits[] = $traitComponent;
            }

            foreach ($this->interfaceAnalyzer->extract($namespaceTokens, true) as $interfaceComponent) {
                $namespace->interfaces[] = $interfaceComponent;
            }

            $namespace->uses = $this->extractUsedNames($namespaceTokens);

            foreach ($this->functionAnalyzer->extract($namespaceTokens, true) as $functionComponent) {
                $namespace->functions[] = $functionComponent;
                $namespace->usedConstants = array_merge($namespace->usedConstants, $functionComponent->usedConstants);
                $namespace->callFunctions = array_merge($namespace->callFunctions, $functionComponent->callFunctions);
            }

            $namespace->constants = $this->extractConstants($namespaceTokens, true);
            $namespace->usedConstants = array_merge($namespace->usedConstants, $this->extractUsedConstants($namespaceTokens));
            $namespace->callFunctions = array_merge($namespace->callFunctions, $this->extractCalledFunctions($namespaceTokens));


            return $namespace;
        }, $namespaces);

        return $namespaces;
    }

    private function extractUsedNames(array &$tokens): array
    {
        $names = [];
        $currentClass = '';
        $currentAlias = '';
        $currentShortName = '';
        $multiplePrefix = '';

        $isUse = false;
        $isMultiple = false;
        $isAlias = false;

        $isFunction = false;
        $isConst = false;

        foreach ($tokens as $id => $token) {
            if (!$isUse) {
                if (!$isFunction && is_array($token) && $token[0] === T_FUNCTION) {
                    $isFunction = true;
                    continue;
                }

                if (!$isConst && is_array($token) && $token[0] === T_CONST) {
                    $isConst = true;
                    continue;
                }

                if ($isFunction && $token === ';') {
                    $isFunction = false;
                    $isConst = false;
                    continue;
                }

                if (!$isFunction && is_array($token) && $token[0] === T_USE) {
                    $isUse = true;
                    unset($tokens[$id]);
                    continue;
                }
            }

            if ($isUse && in_array($token, [';', '}', ','])) {
                if ($isMultiple) {
                    $currentClass = $multiplePrefix . $currentClass;
                    if ($token === '}') {
                        $isMultiple = false;
                        $multiplePrefix = '';
                    }
                }

                if (!$isAlias) {
                    $names[$currentShortName] = $currentClass;
                } else {
                    $names[$currentAlias] = $currentClass;
                }

                if (in_array($token, [';', '}'])) {
                    $isUse = false;
                }
                $isAlias = false;
                $currentClass = '';
                $currentAlias = '';
            } elseif ($isUse && $token === '{') {
                $isMultiple = true;
                $multiplePrefix = $currentClass;
                $currentClass = '';
            }

            if (!$isUse || !is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_NS_SEPARATOR, T_STRING], true)) {
                if ($isAlias) {
                    $currentAlias .= $token[1];
                } else {
                    $currentClass .= $token[1];
                    $currentShortName = $token[1];
                }
            } elseif (in_array($token[0], [T_NAME_QUALIFIED, T_NAME_RELATIVE, T_NAME_FULLY_QUALIFIED], true)) {
                if ($isAlias) {
                    $currentAlias .= $token[1];
                } else {
                    $currentClass .= $token[1];
                    $currentShortName = preg_replace('!^.*\\\!', '', $token[1]);
                }
            } elseif ($token[0] === T_AS) {
                $isAlias = true;
            }
            unset($tokens[$id]);
        }
        $tokens = array_values($tokens);

        return $names;
    }
}