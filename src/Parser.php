<?php

namespace YRV\Autoloader;

defined('T_NAME_QUALIFIED') || define('T_NAME_QUALIFIED', -4);
defined('T_NAME_FULLY_QUALIFIED') || define('T_NAME_FULLY_QUALIFIED', -5);
defined('T_FN') || define('T_FN', -6);

class Parser
{
    protected array $tokens = [];

    public function __construct()
    {
    }

    public function parseFile($file)
    {
        return $this->parseCode(file_get_contents($file));
    }
    public function parseCode($code)
    {
        $this->code = $code;
        $tokens = $this->getTokens();
        $this->parseStructure($tokens);
    }

    protected function getTokens($from = null, $to = null)
    {
        if (empty ($this->tokens)) {
            $this->tokinize($this->code);
        }
        if (!$from && !$to) {
            return $this->tokens;
        }
        $results = [];
        foreach ($tokens as $i => $token) {
            if ($from && $i< $from) {
                continue;
            }
            if ($to && $i> $to) {
                break;
            }
            $results[] = &$token;
        }
        return $results;
    }

    protected function tokinize($code)
    {
        $this->tokens = token_get_all($code);
        $this->tokens = array_map(function($token) {
            if (!is_array($token)) return $token;
            $token[3] = token_name ($token[0]);
            return $token;
        }, $this->tokens);
    }

    protected function parseStructure($tokens)
    {
        $classes = array();
        $functions = array();
        $constants = array();
        $structures = array();
        $namespace = '';

        $open = 0;
        $state = 'start';
        $lastState = '';
        $prefix = '';
        $name = '';
        $alias = '';
        $isFunc = $isConst = false;

        $startLine = $endLine = 0;
        $structType = $structName = '';
        $structIgnore = false;

        foreach ($tokens as $k => $token) {

            switch ($state) {
                case 'start':
                    switch ($token[0]) {
                        case T_NAMESPACE:
                            $state = 'namespace';
                            $structIgnore = true;
                            break;
                        case T_FN:
                        case T_FUNCTION:
                        case T_CLASS:
                        case T_INTERFACE:
                        case T_TRAIT:
                            $state = 'before_structure';
                            $startLine = $token[2];
                            $structType = $sType[$token[0]];
                            break;
                        case T_USE:
                            $state = 'use';
                            $prefix = $name = $alias = '';
                            $isFunc = $isConst = false;
                            break;
//                        case T_FN:
//                        case T_FUNCTION:
//                            $state = 'structure';
//                            $structIgnore = true;
//                            break;
                        case T_NEW:
                            $state = 'new';
                            break;
                        case T_OBJECT_OPERATOR:
                        case T_DOUBLE_COLON:
                            $state = 'invoke';
                            break;
                        case T_WHITESPACE:
                            break;
                        default:
                            $state = 'inline';
                            break;
                    }
                    break;
                case 'use':
                    switch ($token[0]) {
                        case T_FUNCTION:
                            $isFunc = true;
                            break;
                        case T_CONST:
                            $isConst = true;
                            break;
                        case T_NS_SEPARATOR:
                            $name .= $token[1];
                            break;
                        case T_STRING:
                            $name .= $token[1];
                            $alias = $token[1];
                            break;
                        case T_NAME_QUALIFIED:
                            $name .= $token[1];
                            $pieces = explode('\\', $token[1]);
                            $alias = end($pieces);
                            break;
                        case T_AS:
                            $lastState = 'use';
                            $state = 'alias';
                            break;
                        case '{':
                            $prefix = $name;
                            $name = $alias = '';
                            $state = 'use-group';
                            break;
                        case ',':
                        case ';':
                            if ($name === '' || $name[0] !== '\\') {
                                $name = '\\' . $name;
                            }

                            if ($alias !== '') {
                                if ($isFunc) {
                                    $functions[strtolower($alias)] = $name;
                                } elseif ($isConst) {
                                    $constants[$alias] = $name;
                                } else {
                                    $classes[strtolower($alias)] = $name;
                                }
                            }
                            $name = $alias = '';
                            $state = $token === ';' ? 'start' : 'use';
                            break;
                    }
                    break;
                case 'use-group':
                    switch ($token[0]) {
                        case T_NS_SEPARATOR:
                            $name .= $token[1];
                            break;
                        case T_NAME_QUALIFIED:
                            $name .= $token[1];
                            $pieces = explode('\\', $token[1]);
                            $alias = end($pieces);
                            break;
                        case T_STRING:
                            $name .= $token[1];
                            $alias = $token[1];
                            break;
                        case T_AS:
                            $lastState = 'use-group';
                            $state = 'alias';
                            break;
                        case ',':
                        case '}':

                            if ($prefix === '' || $prefix[0] !== '\\') {
                                $prefix = '\\' . $prefix;
                            }

                            if ($alias !== '') {
                                if ($isFunc) {
                                    $functions[strtolower($alias)] = $prefix . $name;
                                } elseif ($isConst) {
                                    $constants[$alias] = $prefix . $name;
                                } else {
                                    $classes[strtolower($alias)] = $prefix . $name;
                                }
                            }
                            $name = $alias = '';
                            $state = $token === '}' ? 'use' : 'use-group';
                            break;
                    }
                    break;
                case 'alias':
                    if ($token[0] === T_STRING) {
                        $alias = $token[1];
                        $state = $lastState;
                    }
                    break;
                case 'new':
                    switch ($token[0]) {
                        case T_WHITESPACE:
                        case T_COMMENT:
                        case T_DOC_COMMENT:
                            break 2;
                        case T_CLASS:
                            $state = 'structure';
//                            $structIgnore = true;
                            break;
                        default:
                            $state = 'start';
                    }
                    break;
                case 'invoke':
                    switch ($token[0]) {
                        case T_WHITESPACE:
                        case T_COMMENT:
                        case T_DOC_COMMENT:
                            break 2;
                        default:
                            $state = 'start';
                    }
                    break;
                case 'before_structure':
                    if ($token[0] == T_STRING) {
                        $structName = $token[1];
                        $state = 'structure';
                    }
                    break;
                case 'inline':
                    switch ($token[0]) {
                        case T_WHITESPACE:
                            break;
                        case T_FUNCTION:
                        case T_FN:
                        case T_CLASS:
                        case T_INTERFACE:
                        case T_TRAIT:
                            $state = 'before_structure';
                            $startLine = $token[2];
                            $structType = $sType[$token[0]];
                            break;
                    }
                case 'structure':
                    switch ($token[0]) {
                        case '{':
                        case T_CURLY_OPEN:
                        case T_DOLLAR_OPEN_CURLY_BRACES:
                            $open++;
                            break;
                        case '}':
                            if (--$open == 0) {
                                if(!$structIgnore){
                                    $structures[] = array(
                                        'type' => $structType,
                                        'name' => $structName,
                                        'start' => $startLine,
                                        'end' => $endLine,
                                    );
                                }
                                $structIgnore = false;
                                $state = 'start';
                            }
                            break;
                        default:
                            if (is_array($token)) {
                                $endLine = $token[2];
                            }
                    }
                    break;
                case 'namespace':
                    switch ($token[0]) {
                        case T_STRING:
                        case T_NS_SEPARATOR:
                            $name .= $token[1];
                            break;
                        case T_WHITESPACE:
                            break;
                        default:
                            $namespace = $name;
                            $state = 'start';
                    }
            }
        }

        $result['namespace'] = $namespace;
        $result['classes'] = $classes;
        $result['functions'] = $functions;
        $result['fFunctions'] = $fFunctions;
        $result['constants'] = $constants;
        $result['structures'] = $structures;

        return $result;
    }
}
