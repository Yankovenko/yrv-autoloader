<?php

namespace YRV\Autoloader\Parser\Analyzers;

use Exception;
use YRV\Autoloader\Parser\Components\PropertyComponent;

trait ComponentAnalyzerLibrary
{

    protected static NamespaceAnalyzer $namespaceAnalyzerStatic;
    protected static ClassAnalyzer $classAnalyzerStatic;
    protected static EnumAnalyzer $enumAnalyzerStatic;
    protected static InterfaceAnalyzer $interfaceAnalyzerStatic;
    protected static TraitAnalyzer $traitAnalyzerStatic;
    protected static FunctionAnalyzer $functionAnalyzerStatic;
    protected static ParamAnalyzer $paramAnalyzerStatic;

    /**
     * @return NamespaceAnalyzer
     */
    public function getNamespaceAnalyzer(): NamespaceAnalyzer
    {
        if (!isset(static::$namespaceAnalyzerStatic)) {
            static::$namespaceAnalyzerStatic = new NamespaceAnalyzer();
        }
        return static::$namespaceAnalyzerStatic;
    }

    /**
     * @return ClassAnalyzer
     */
    public function getClassAnalyzer(): ClassAnalyzer
    {
        if (!isset(static::$classAnalyzerStatic)) {
            static::$classAnalyzerStatic = new ClassAnalyzer();
        }
        return static::$classAnalyzerStatic;
    }

    /**
     * @return EnumAnalyzer
     */
    public function getEnumAnalyzer(): EnumAnalyzer
    {
        if (!isset(static::$enumAnalyzerStatic)) {
            static::$enumAnalyzerStatic = new EnumAnalyzer();
        }
        return static::$enumAnalyzerStatic;
    }

    /**
     * @return InterfaceAnalyzer
     */
    public function getInterfaceAnalyzer(): InterfaceAnalyzer
    {
        if (!isset(static::$interfaceAnalyzerStatic)) {
            static::$interfaceAnalyzerStatic = new InterfaceAnalyzer();
        }
        return static::$interfaceAnalyzerStatic;
    }

    /**
     * @return TraitAnalyzer
     */
    public function getTraitAnalyzer(): TraitAnalyzer
    {
        if (!isset(static::$traitAnalyzerStatic)) {
            static::$traitAnalyzerStatic = new TraitAnalyzer();
        }
        return static::$traitAnalyzerStatic;
    }

    /**
     * @return FunctionAnalyzer
     */
    public function getFunctionAnalyzer(): FunctionAnalyzer
    {
        if (!isset(static::$functionAnalyzerStatic)) {
            static::$functionAnalyzerStatic = new FunctionAnalyzer();
        }
        return static::$functionAnalyzerStatic;
    }

    /**
     * @return ParamAnalyzer
     */
    public function getParamAnalyzer(): ParamAnalyzer
    {
        if (!isset(static::$paramAnalyzerStatic)) {
            static::$paramAnalyzerStatic = new ParamAnalyzer();
        }
        return static::$paramAnalyzerStatic;
    }

    /**
     * @param $tokens
     * @param array $inWhere
     * @param int $from
     * @param bool $directionForward
     * @param array $skip
     * @return bool
     */
    protected function checkTokenIn(
        &$tokens,
        array $inWhere,
        int $from = 0,
        bool $directionForward = true,
        array $skip = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT]
    ): bool {

        while (isset($tokens[($from += $directionForward ? 1 : -1)])) {

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

    /**
     * @param array $tokens
     * @return string
     */
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

            if (in_array($token[0], [T_STRING, T_NS_SEPARATOR, T_NAME_RELATIVE, T_NAME_FULLY_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $extended .= $token[1];
            }
        }

        return $extended;
    }

    /**
     * @param array $tokens
     * @param $delete
     * @return array
     */
    protected function extractConstants(array &$tokens, $delete = false): array
    {
        $name = '';
        $constFound = false;
        $constants = [];

        foreach ($tokens as $pos => $token) {
            if (is_array($token)
                && in_array($token[0], [T_CONST], true)
                && !$this->checkTokenIn($tokens, [T_USE], $pos, false)
            ) {
                $constFound = true;
                if ($delete) {
                    unset($tokens[$pos]);
                }
                continue;
            }

            if (!$constFound) {
                continue;
            }

            if (in_array($token[0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_RELATIVE, T_NAME_FULLY_QUALIFIED], true)) {
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

        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_STRING && $token[1] == 'define') {
                $constFound = true;
                $name = '';
                continue;
            }

            if (!$constFound) {
                continue;
            }

            if (!$name && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $name = $token[1];
                $name = '\\' . trim($name, '\'"');
                $constants[] = $name;
            }

            if ($token === ';') {
                $name = '';
                $constFound = false;
            }

        }

        $tokens = array_values($tokens);

        return $constants;
    }

    /**
     * @param array $tokens
     * @return array
     */
    protected function extractTraits(array $tokens): array
    {
        $name = '';
        $useFound = false;
        $isRedeclare = false;
        $traits = [];

        foreach ($tokens as $pos => $token) {
            if (is_array($token) && $token[0] === T_USE && !$this->checkTokenIn($tokens, ['('], $pos)) {
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

            if (in_array($token[0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_RELATIVE, T_NAME_FULLY_QUALIFIED], true)) {
                $name .= $token[1];
                continue;
            }

            if ($token === '{') {
                $traits[] = $name;
                $name = '';
                $isRedeclare = true;
            } elseif ($token === ',') {
                $traits[] = $name;
                $name = '';
            } elseif ($token === ';') {
                $traits[] = $name;
                $name = '';
                $useFound = false;
            }
        }

        return $traits;
    }

    /**
     * @throws Exception
     */
    protected function extractName(array $tokens): string
    {
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }
        }

        throw new Exception("Name is missing");
    }

    /**
     * @param array $tokens
     * @return PropertyComponent[]
     */
    protected function extractProperties(array $tokens): array
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

            if ($currentProperty === null && is_array($token) && $token[0] === T_VARIABLE) {
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

    protected function extractCalledFunctions(array $tokens): array
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
                && in_array($token[0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_RELATIVE, T_NAME_FULLY_QUALIFIED], true)
            ) {
                $name .= $token[1];
                $posStart = $posStart ?? $pos;
            } else {
                $name = '';
                $posStart = null;
                continue;
            }

            if ($token[0] === T_STRING
                && isset($tokens[$pos + 1])
                && $this->checkTokenIn($tokens, ['('], $pos)
                && !$this->checkTokenIn($tokens, [T_OBJECT_OPERATOR, T_NEW, T_PAAMAYIM_NEKUDOTAYIM], $posStart, false)
            ) {
                $called[] = $name;
                $name = '';
                $posStart = null;
            }
        }

        return array_unique($called);
    }

    protected function extractUsedConstants(array $tokens): array
    {
        $constants = [];
        $nameConstant = '';

        foreach ($tokens as $pos => $token) {
            if (is_array($token) && in_array($token[0], [T_STRING, T_NS_SEPARATOR])) {
                $nameConstant .= $token[1];
            } elseif (is_array($token) && in_array($token[0], [T_NAME_QUALIFIED, T_NAME_RELATIVE, T_NAME_FULLY_QUALIFIED])) {
                $nameConstant .= $token[1];
            } elseif (!$nameConstant) {
                continue;
            }

            if (!in_array($token[0], [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_RELATIVE, T_NAME_FULLY_QUALIFIED])) {
                if ($this->checkTokenIn($tokens,
                        ['.', ',', ':', '(', '*', '/', '-', '+', '%', '|', '&', '^', '!', '=', '[', '>', '<', '?',
                            T_BOOLEAN_AND, T_BOOLEAN_OR, T_COALESCE, T_SL, T_SPACESHIP, T_SR,
                            T_IS_NOT_EQUAL, T_IS_EQUAL, T_IS_GREATER_OR_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_SMALLER_OR_EQUAL,
                            //
                            T_CONCAT_EQUAL, T_DIV_EQUAL, T_AND_EQUAL, T_COALESCE_EQUAL, T_MINUS_EQUAL, T_MOD_EQUAL, T_MUL_EQUAL,
                            T_OR_EQUAL, T_POW_EQUAL, T_SL_EQUAL, T_SR_EQUAL, T_XOR_EQUAL
                        ], $pos, false,
                        [T_WHITESPACE, T_COMMENT, T_NS_SEPARATOR, T_STRING, T_NAME_QUALIFIED, T_NAME_RELATIVE, T_NAME_FULLY_QUALIFIED])
                    && $this->checkTokenIn($tokens,
                        ['.', ',', ':', ')', '*', '/', '-', '+', '%', '|', '&', '^', '!', ']', '>', '<', '?', ';',
                            T_BOOLEAN_AND, T_BOOLEAN_OR, T_COALESCE, T_SL, T_SPACESHIP, T_SR,
                            T_IS_NOT_EQUAL, T_IS_EQUAL, T_IS_GREATER_OR_EQUAL, T_IS_IDENTICAL, T_IS_NOT_EQUAL, T_IS_SMALLER_OR_EQUAL
                        ], $pos - 1)
                ) {
                    $constants[] = $nameConstant;
                }
                $nameConstant = '';
            }
        }

        return array_unique($constants);
    }
}