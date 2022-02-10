<?php
/*
 * Основной принцип работы:
 *
 * Сканируем все файлы composer.json подключенных библиотек
 * Формируем карту соответствий классов и файлов автозагрузки
 * Сканируем все файлы прямых инклудов на наличие объявленных фунцкий
 * Сканируем все файлы .php на наличие вызова этих функций
 * (только внутри библиотеки и в корневом проекте)
 * Если функция встречается в файле класса автозагрузки, то добавляем файл
 * с функцией к автозагрузке, вместе с классом.
 * Если функция встречается в других php файлах, то добавляем файл с функцией
 * к принудительной автозагрузке
 *
 */
namespace YRV\Autoloader;

defined('T_NAME_QUALIFIED') || define('T_NAME_QUALIFIED', -4);
defined('T_NAME_FULLY_QUALIFIED') || define('T_NAME_FULLY_QUALIFIED', -5);
defined('T_FN') || define('T_FN', -6);

class Dumper
{
    protected string $baseDir;
    protected string $cacheDir;
    
    public function __construct(?string $baseDir = null, ?string $cacheDir = null)
    {
        $this->baseDir = $baseDir ? $baseDir : __DIR__ . '/../../../../';
        $this->cacheDir = $cacheDir ? $cacheDir : './../cache/';
    }
    
    public function dump(?string $dir = null)
    {
        $dir = $dir ?? $this->baseDir;
        $this->scan($dir);
    }
    
    protected function scan(string $dir)
    {
        $directory = new \RecursiveDirectoryIterator($dir);
        $iterator = new \RecursiveIteratorIterator($directory);
        $files = array();
        $data = [];
        foreach ($iterator as $info) {
            if ($info->getFilename() == 'composer.json') {
                $data = array_merge_recursive($data, $this->parseComposerJson($info));
            }
        }
        
        if (isset($data['f'])) {
            $this->parseIncludedFiles($data['f']);
        }
        print_r ($data);
    }

    protected function parseComposerJson(\SplFileInfo $file): array
    {
        $body = \file_get_contents($file->getPathname());
        if ($body === false) {
            echo "Error loading file [{$file->getFilename()}]\n";
            return [];
        }

        $json = \json_decode($body, true);

        $data = [];
        $base = $file->getPath();

        $map1 = ['a' => 'autoload', 'd' => 'autoload-dev'];
        $map2 = ['f' => 'files', 'm' => 'classmap', 'p4' => 'psr-4', 'p0' => 'psr-0', 'x' => 'exclude-from-classmap'];

        foreach ($map1 as $k1 => $v1) {
            foreach ($map2 as $k2 => $v2) {
                if (isset($json[$v1][$v2]) && !empty($json[$v1][$v2])) {
                    $data[$k1][$k2][] = [
                        'b' => $base,
                        'd' => $json[$v1][$v2]
                    ];


//                    $data[$k1][$k2] = array_map(function($f) use ($base) {
//                        return [
//                            'b' => $base,
//                            'd' => $f
//                        ];
//                    }, $json[$v1][$v2]);
                }
            }
        }
        
        return $data;
    }
    
    protected function parseIncludedFiles($data)
    {
        foreach ($data as $subData) {
            foreach ($subData['d'] as $file) {
                $filepath = $subData['b'] . '/' . $file;
                $this->parseFile($file);
            }
        }
    }
    
    public function parseFile($file)
    {
        $content = file_get_contents($file);
        $tokens = token_get_all($content);
        $tokens = array_map(function($token) {
            if (!is_array($token)) return $token;
            $token[3] = token_name ($token[0]);
            return $token;
        }, $tokens);
        print_r ($tokens);
//        $tokens = $this->getTokens();
        $sType = [
            T_FN => 'fn',
            T_FUNCTION => 'function',
            T_CLASS => 'class',
            T_INTERFACE => 'interface',
            T_TRAIT => 'trait'
        ];

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

    private function parseNameQualified($token)
    {
        $pieces = explode('\\', $token);

        $id_start = array_shift($pieces);

        $id_start_ci = strtolower($id_start);

        $id_name = '\\' . implode('\\', $pieces);

        return [$id_start, $id_start_ci, $id_name];
    }


    
}