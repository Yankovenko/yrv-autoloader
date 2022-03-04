<?php
namespace YRV\Autoloader\Parser;

interface ContentAnalyzer
{
    public function extract(array &$tokens, $deleteExtracted=false): \Generator;
}

trait ComponentAnalyzerLibrary {

    protected static $namespaceAnalyzerStatic;
    protected static $classAnalyzerStatic;
    protected static $interfaceAnalyzerStatic;
    protected static $traitAnalyzerStatic;
    protected static $functionAnalyzerStatic;
    protected static $paramAnalyzerStatic;

    public function getNamespaceAnalyzer(): NamespaceAnalyzer
    {
        if (!static::$namespaceAnalyzerStatic) {
            static::$namespaceAnalyzerStatic = new NamespaceAnalyzer();
        }
        return static::$namespaceAnalyzerStatic;
    }

    public function getClassAnalyzer(): ClassAnalyzer
    {
        if (!static::$classAnalyzerStatic) {
            static::$classAnalyzerStatic = new ClassAnalyzer();
        }
        return static::$classAnalyzerStatic;
    }

    public function getInterfaceAnalyzer(): InterfaceAnalyzer
    {
        if (!static::$interfaceAnalyzerStatic) {
            static::$interfaceAnalyzerStatic = new InterfaceAnalyzer();
        }
        return static::$interfaceAnalyzerStatic;
    }

    public function getTraitAnalyzer(): TraitAnalyzer
    {
        if (!static::$traitAnalyzerStatic) {
            static::$traitAnalyzerStatic = new TraitAnalyzer();
        }
        return static::$traitAnalyzerStatic;
    }

    public function getFunctionAnalyzer(): FunctionAnalyzer
    {
        if (!static::$functionAnalyzerStatic) {
            static::$functionAnalyzerStatic = new FunctionAnalyzer();
        }
        return static::$functionAnalyzerStatic;
    }

    public function getParamAnalyzer(): ParamAnalyzer
    {
        if (!static::$paramAnalyzerStatic) {
            static::$paramAnalyzerStatic = new ParamAnalyzer();
        }
        return static::$paramAnalyzerStatic;
    }

    protected function checkTockenIn(&$tokens, int $from = 0, $shift = 1, array $inWhere, array $skip = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]) {
        while(isset($tokens[($from += $shift)])) {

            if (in_array(
                is_array($tokens[$from]) ? $tokens[$from][0] : $tokens[$from],
                $inWhere,
                true
            )) {
                return true;
            }

            if (!in_array(
                is_array($tokens[$from]) ? $tokens[$from][0] : $tokens[$from],
                $skip,
                true
            )) {
                return false;
            }

        }
        return false;
    }

    protected function extractExtends(array $tokens): string
    {
        $extended = '';
        $extendsFound = false;

        foreach ($tokens as $token) {
            if ($token === '{' || is_array($token) && $token[0] === T_IMPLEMENTS) {
                break;
            }

            if (!$extendsFound && is_array($token) && $token[0] === T_EXTENDS) {
                $extendsFound = true;
                continue;
            }

            if (!$extendsFound || !is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_STRING, T_NS_SEPARATOR], true)) {
                $extended .= $token[1];
                continue;
            }
        }

        return $extended;
    }

    protected function extractConstants(array &$tokens, $delete=false)
    {
        $name = '';
        $constFound = false;
        $constants = [];

        foreach ($tokens as $pos => $token) {
            if (is_array($token) && $token[0] === T_CONST && !$this->checkTockenIn($tokens, $pos, -1, [T_USE])) {
                $constFound = true;
                if ($delete) {
                    unset($tokens[$pos]);
                }
                continue;
            }

            if (!$constFound) {
                continue;
            }

            if (in_array($token[0], [T_STRING, T_NS_SEPARATOR], true)) {
                $name .= $token[1];
                if ($delete) {
                    unset($tokens[$pos]);
                }
                continue;
            }

            if ($token === '=') {
                $constants[] = $name;
                $name = '';
            }
            if ($token === ';') {
                $constFound = false;
            }
            if ($delete) {
                unset($tokens[$pos]);
            }
        }

        foreach ($tokens as $pos => $token) {
            if (is_array($token) && $token[0] === T_STRING && $token[1] == 'define') {
                $constFound = true;
                $name = '';
                continue;
            }

            if (!$constFound) {
                continue;
            }

            if (!$name && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING], true)) {
                $name = $token[1];
                $name = '\\' . trim($name, '\'"');
                $constants[] = $name;
            }

            if ($token === ';') {
                $name = '';
                $constFound = false;
            }

        }

        return $constants;
    }

    protected function extractTraits(array $tokens)
    {
        $name = '';
        $useFound = false;
        $isRedeclare = false;
        $constants = [];

        foreach ($tokens as $pos => $token) {
            if (is_array($token) && $token[0] === T_USE && !$this->checkTockenIn($tokens, $pos, 1, ['('])) {
                $useFound = true;
                continue;
            }

            if (!$useFound) {
                continue;
            }

            if ($isRedeclare && $token !== '}') {
                continue;
            } elseif ($isRedeclare) {
                $isRedeclare = false;
                $useFound = false;
                continue;
            }

            if (in_array($token[0], [T_STRING, T_NS_SEPARATOR], true)) {
                $name .= $token[1];
                continue;
            }

            if ($token === '{') {
                $constants[] = $name;
                $name = '';
                $isRedeclare = true;
                continue;
            } elseif ($token === ',') {
                $constants[] = $name;
                $name = '';
            } elseif ($token === ';') {
                $constants[] = $name;
                $name = '';
                $useFound = false;
            }
        }

        return $constants;
    }

    protected function extractName(array $tokens)
    {
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }
        }

        throw new \Exception("Name is missing");
    }


    protected function extractProperties(array $tokens)
    {
        $properties = [];

        $isFunction = false;
        $nestedLevel = 0;

        $currentProperty = null;

        $isAssignment = false;
        $assignmentTokens = [];

        foreach ($tokens as $pos => $token) {
            if ($isFunction) {
                if ($nestedLevel === 0 && $token === ';') {
                    $isFunction = false;
                } elseif ($token === '{' || $token[0] === T_CURLY_OPEN) {
                    $nestedLevel++;
                } elseif ($token === '}') {
                    $nestedLevel--;
                    $isFunction = $nestedLevel !== 0;
                }
            } elseif (is_array($token) && $token[0] === T_FUNCTION) {
                $isFunction = true;
            }

            if ($isFunction) {
                continue;
            }

            if ($currentProperty === null && is_array($token) && in_array($token[0], [T_VARIABLE], true)) {
                $currentProperty = new PropertyComponent();
                $currentProperty->name = substr($token[1], 1);

                $sliceBegin = max($pos - 4, 0);
                $sliceLength = max($pos - $sliceBegin, 0);

                $previousTokens = array_slice($tokens, $sliceBegin, $sliceLength, true);
                $previousTokens = array_filter($previousTokens, 'is_array');

                array_walk($previousTokens, function ($token) use ($currentProperty) {
                    switch ($token[0]) {
                        case T_STATIC:
                            $currentProperty->isStatic = true;
                            break;
                        case T_PUBLIC:
                            $currentProperty->visibility = 'public';
                            break;
                        case T_PROTECTED:
                            $currentProperty->visibility = 'protected';
                            break;
                        case T_PRIVATE:
                            $currentProperty->visibility = 'private';
                            break;
                    }
                });

                continue;
            }

            if ($currentProperty === null) {
                continue;
            }

            if ($token === ';') {
                if ($isAssignment) {
                    ParamAnalyzer::assignDefaultValue($currentProperty, $assignmentTokens);
                }

                $properties[] = $currentProperty;
                $currentProperty = null;
                $isAssignment = false;
                $assignmentTokens = [];
            } elseif ($token === '=') {
                $isAssignment = true;
            } elseif ($isAssignment && (!is_array($token) || $token[0] !== T_WHITESPACE)) {
                $assignmentTokens[] = $token;
            }
        }

        return $properties;
    }

    protected function extractCalledFunctions(array $tokens)
    {
        $called = [];
        $name = '';

        $tokens = array_filter($tokens, function ($token) {
            return !is_array($token) || $token[0] !== T_WHITESPACE;
        });

        $tokens = array_values($tokens);

        $posStart = null;
        foreach ($tokens as $pos => $token) {
            if (is_array($token)
                && in_array($token[0], [T_STRING, T_NS_SEPARATOR], true)) {
                $name .= $token[1];
                $posStart = $posStart ?? $pos;
            } else {
                $name = '';
                $posStart = null;
                continue;
            }

            if (is_array($token)
                && $token[0] === T_STRING
                && isset($tokens[$pos + 1])
                && $this->checkTockenIn($tokens, $pos, 1, ['('])
                && !$this->checkTockenIn($tokens, $posStart, -1, [T_OBJECT_OPERATOR, T_NEW, T_PAAMAYIM_NEKUDOTAYIM])
            ) {
                $called[] = $name;
                $name = '';
                $posStart = null;
            }
        }

        return array_unique($called);
    }

    protected function extractUsedConstants(array $tokens)
    {
        $constants = [];
        $usedPos = [];
        $nameConstant = '';

        foreach ($tokens as $pos => $token) {

            if (is_array($token) && in_array($token[0], [T_STRING, T_NS_SEPARATOR])) {
                $nameConstant .= $token[1];
            } elseif (!$nameConstant) {
                continue;
            }
//
//                if (!$nameConstant && (!is_array($token) || !in_array($token[0], [T_STRING, T_NS_SEPARATOR]))) {
//                continue;
//            } elseif ($nameConstant) {
//                $nameConstant .= $token[0];
//            } else {
//                continue;
//            }

            if (!in_array($token[0], [T_STRING, T_NS_SEPARATOR])) {
                if ($this->checkTockenIn($tokens, $pos, -1,
                        ['.', ',', ':', '(', '*', '/', '-', '+', '%', '|', '&', '^', '!', '=', '[', '>', '<', '?',
                            T_BOOLEAN_AND, T_BOOLEAN_OR, T_COALESCE, T_SL, T_SPACESHIP, T_SR,
                            T_IS_NOT_EQUAL, T_IS_EQUAL, T_IS_GREATER_OR_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_SMALLER_OR_EQUAL,
                            //
                            T_CONCAT_EQUAL, T_DIV_EQUAL, T_AND_EQUAL, T_COALESCE_EQUAL, T_MINUS_EQUAL, T_MOD_EQUAL, T_MUL_EQUAL,
                            T_OR_EQUAL, T_POW_EQUAL, T_SL_EQUAL, T_SR_EQUAL, T_XOR_EQUAL
                        ],
                        [T_WHITESPACE, T_COMMENT, T_NS_SEPARATOR, T_STRING])
                    && $this->checkTockenIn($tokens, $pos-1, 1,
                        ['.', ',', ':', ')', '*', '/', '-', '+', '%', '|', '&', '^', '!', ']', '>', '<', '?', ';',
                            T_BOOLEAN_AND, T_BOOLEAN_OR, T_COALESCE, T_SL, T_SPACESHIP, T_SR,
                            T_IS_NOT_EQUAL, T_IS_EQUAL, T_IS_GREATER_OR_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_SMALLER_OR_EQUAL
                        ])
                ) {
                    $constants[] = $nameConstant;
                }
                $nameConstant = '';
            }
        }

        return array_unique($constants);
    }
}

class FileAnalyzer implements ContentAnalyzer
{
    private NamespaceAnalyzer $namespaceAnalyzer;
    use ComponentAnalyzerLibrary;

    public function __construct()
    {
        $this->namespaceAnalyzer = $this->getNamespaceAnalyzer();
    }

    /**
     * @param string $path
     * @return NamespaceComponent[]|null
     */
    public function analyze(string $path): ?array
    {
        $contents = file_get_contents($path);
        $tokens = token_get_all($contents);

        if (empty($tokens)) {
            return null;
        }
        $tokens = array_map(function($token) {
            if (!is_array($token)) return $token;
            $token[3] = token_name ($token[0]);
            return $token;
        }, $tokens);

        $components = [];
        foreach ($this->extract($tokens) as $component) {
            $components[] = $component;
        }
        return $components;
    }

    public function extract(array &$tokens, $deleteExtracted=false): \Generator
    {
        yield from $this->namespaceAnalyzer->extract($tokens, $deleteExtracted);
    }
}

class NamespaceAnalyzer implements ContentAnalyzer
{
    private $classAnalyzer;
    private $functionAnalyzer;
    private $interfaceAnalyzer;
    private $traitAnalyzer;
    use ComponentAnalyzerLibrary;

    public function __construct()
    {
        $this->classAnalyzer = $this->getClassAnalyzer();
        $this->functionAnalyzer = $this->getFunctionAnalyzer();
        $this->interfaceAnalyzer = $this->getInterfaceAnalyzer();
        $this->traitAnalyzer = $this->getTraitAnalyzer();
    }

    public function extract(array &$tokens, $deleteExtracted=false): \Generator
    {
        foreach ($this->extractNamespaces($tokens) as $component) {
            yield $component;
        }
    }

    private function extractNamespaces(array $tokens)
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
            $namespaceTokens = array_slice($tokens, $namespace->tokenStartPos, $namespace->tokenEndPos - $namespace->tokenStartPos+1, true);

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

    private function extractUsedNames(array $tokens)
    {
        $classes = [];
        $functions = [];
        $currentClass = '';
        $currentAlias = '';
        $currentShortName = '';
        $multiplePrefix = '';

        $isUse = false;
        $isMultiple = false;
        $isAlias = false;

        $isFunction = false;
        $isConst = false;

        foreach ($tokens as $pos => $token) {
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
                    $classes[$currentShortName] = $currentClass;
                } else {
                    $classes[$currentAlias] = $currentClass;
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
            } elseif ($token[0] === T_AS) {
                $isAlias = true;
            }
        }

        return $classes;
    }
}

class InterfaceAnalyzer implements ContentAnalyzer
{
    use ComponentAnalyzerLibrary;

    private $functionAnalyzer;

    public function __construct()
    {
        $this->functionAnalyzer = $this->getFunctionAnalyzer();
    }

    public function extract(array &$tokens, $deleteExtracted=false): \Generator
    {
        $currentInterfaceTokens = [];
        $isInterface = false;
        $nestedLevel = 0;

        $startedPos = -1;
        $endedPos = -1;

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
                    $isFinal = false;
                    $isAbstract = false;
                }
            }
        }
    }

    private function createInterface(array $tokens)
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

    private function extractMethods(array $tokens)
    {
        $methods = [];

        foreach ($this->functionAnalyzer->extract($tokens) as $component) {
            $methods[] = $component;
        }

        return $methods;
    }

}

class TraitAnalyzer implements ContentAnalyzer
{
    use ComponentAnalyzerLibrary;

    private $functionAnalyzer;

    public function __construct()
    {
        $this->functionAnalyzer = $this->getFunctionAnalyzer();
    }

    public function extract(array &$tokens, $deleteExtracted=false): \Generator
    {
        $currentTraitTokens = [];
        $isTrait = false;
        $nestedLevel = 0;

        $startedPos = -1;
        $endedPos = -1;

        foreach ($tokens as $pos => $token) {
            if (!isset($currentTraitTokens[0]) && is_array($token) && in_array($token[0], [T_TRAIT], true)) {
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
    }

    private function createTrait(array $tokens)
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

    private function extractMethods(array $tokens)
    {
        $methods = [];

        foreach ($this->functionAnalyzer->extract($tokens) as $component) {
            $methods[] = $component;
        }

        return $methods;
    }
}

class ClassAnalyzer implements ContentAnalyzer
{
    use ComponentAnalyzerLibrary;

    private $functionAnalyzer;

    public function __construct()
    {
        $this->functionAnalyzer = $this->getFunctionAnalyzer();
    }

    public function extract(array &$tokens, $deleteExtracted=false): \Generator
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
                if ($token[0] === T_CLASS && $this->checkTockenIn($tokens, $pos, -1, [T_DOUBLE_COLON, T_PAAMAYIM_NEKUDOTAYIM])) {
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
                if ($currInterface[0] !== '') {
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
            }
        }

        return $interfaces;
    }

    private function extractMethods(array &$tokens, $deleteExtracted=false)
    {
        $methods = [];

        foreach ($this->functionAnalyzer->extract($tokens, $deleteExtracted) as $component) {
            $methods[] = $component;
        }

        return $methods;
    }

}

class FunctionAnalyzer implements ContentAnalyzer
{
    use ComponentAnalyzerLibrary;

    private $paramAnalyzer;

    public function __construct()
    {
        $this->paramAnalyzer = $this->getParamAnalyzer();
    }

    public function extract(array &$tokens, $deleteExtracted=false): \Generator
    {
        $isMethod = false;
        $isAbstract = false;
        $isStatic = false;
        $isFinal = false;
        $isAnonym = false;
        $finished = false;
        $visibility = 'public';
        $nestedLevel = 0;
        $startedPos;

        $currFunctionTokens = [];

        foreach ($tokens as $pos => $token) {
            if (!$isMethod
                && is_array($token)
                && in_array($token[0], [T_FINAL, T_ABSTRACT, T_FUNCTION, T_PUBLIC, T_PROTECTED, T_PRIVATE], true)
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
                if ($token[0] === T_FUNCTION && $this->checkTockenIn($tokens, $pos, -1, [T_USE])) {
                    continue;
                }
                if ($token[0] === T_FUNCTION && $this->checkTockenIn($tokens, $pos, 1, ['('])) {
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
                $isAnonym = false;
                $visibility = 'public';
                $nestedLevel = 0;
                $startedPos = null;
                $finished = false;

                $currFunctionTokens = [];
            }
        }
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
        } catch (\Exception $e) {
            //var_dump($e->getTrace());
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



    private function extractReturnTypeHint(array $tokens)
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

class ParamAnalyzer implements ContentAnalyzer
{
    public function extract(array &$tokens, $deleteExtracted=false): \Generator
    {
        $inParam = false;
        $isAssignment = false;
        $nestedListLevel = 0;

        $currParamTokens = [];

        foreach ($tokens as $token) {
            if (!$inParam && $token === '(') {
                $inParam = true;
                continue;
            } elseif ($inParam && $nestedListLevel === 0 && $token === ')') {
                if (!empty($currParamTokens)) {
                    yield $this->createParam($currParamTokens);
                }

                break;
            }

            if (!$inParam) {
                continue;
            }

            if ($token === ',' && $nestedListLevel === 0) {
                yield $this->createParam($currParamTokens);
                $currParamTokens = [];
                $isAssignment = false;
                $nestedListLevel = 0;
            } else {
                if ($token === '=') {
                    $isAssignment = true;
                } elseif ($isAssignment && in_array($token, ['[', '('])) {
                    $nestedListLevel++;
                } elseif ($isAssignment && in_array($token, [']', ')'])) {
                    $nestedListLevel--;
                }

                $currParamTokens[] = $token;
            }
        }
    }

    private function createParam(array $tokens): ParamComponent
    {
        $paramComponent = new ParamComponent();

        $isAssignment = false;
        $assignmentTokens = [];

        foreach ($tokens as $token) {
            if ($token === '=') {
                $isAssignment = true;
                continue;
            }

            if ($isAssignment) {
                if (!is_array($token) || $token[0] !== T_WHITESPACE) {
                    $assignmentTokens[] = $token;
                }
                continue;
            }

            if (!is_array($token)) {
                continue;
            }

            switch ($token[0]) {
                case T_NS_SEPARATOR:
                case T_STRING:
                    $paramComponent->type .= $token[1];
                    break;
                case T_ARRAY:
                    $paramComponent->type = 'array';
                    break;
                case T_VARIABLE:
                    $paramComponent->name = $token[1];
                    break;
                case T_ELLIPSIS:
                    $paramComponent->isVariadic = true;
                    break;
            }
        }

        static::assignDefaultValue($paramComponent, $assignmentTokens);

        return $paramComponent;
    }

    public static function assignDefaultValue(VariableComponent $varComponent, array $assignmentTokens)
    {
        switch (count($assignmentTokens)) {
            case 0:
                break;
            case 1 and is_array($assignmentTokens[0]):
                $varComponent->hasDefaultValue = true;
                switch ($assignmentTokens[0][1]) {
                    case 'array':
                        $varComponent->defaultValue = [];
                        break;
                    case 'null':
                        $varComponent->defaultValue = null;
                        break;
                    default:
                        $varComponent->defaultValue = $assignmentTokens[0][1];
                        break;
                }
                break;
            default:
                $defaultValue = array_map(function ($token) {
                    if ($token === '(') {
                        return '[';
                    } elseif ($token === ')') {
                        return ']';
                    } elseif (!is_array($token)) {
                        return $token;
                    }

                    if ($token[0] !== T_ARRAY) {
                        return $token[1];
                    }
                }, $assignmentTokens);

                $defaultValue = array_reduce($defaultValue, function ($current, $value) {
                    return $current . $value;
                });

                $varComponent->hasDefaultValue = true;
                $varComponent->defaultValue = $defaultValue;
                break;
        }
    }
}
