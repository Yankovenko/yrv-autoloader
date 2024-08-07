<?php

namespace YRV\Autoloader\Parser\Analyzers;

use Generator;
use YRV\Autoloader\Parser\Components\ParamComponent;
use YRV\Autoloader\Parser\Components\VariableComponent;

class ParamAnalyzer implements ContentAnalyzer
{
    public function extract(array &$tokens, $deleteExtracted = false): Generator
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

        $tokens = array_values($tokens);
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

    public static function assignDefaultValue(VariableComponent $varComponent, array $assignmentTokens): void
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