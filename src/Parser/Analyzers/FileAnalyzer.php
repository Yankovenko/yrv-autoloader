<?php

namespace YRV\Autoloader\Parser\Analyzers;

use Generator;
use YRV\Autoloader\Parser\Components\NamespaceComponent;

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
        array_walk($tokens, function (&$token) {
            if (!is_array($token))
                return null;
            $token[3] = token_name($token[0]);
            return null;
        });

        $components = [];
        foreach ($this->extract($tokens) as $component) {
            $components[] = $component;
        }
        return $components;
    }

    public function extract(array &$tokens, $deleteExtracted = false): Generator
    {
        yield from $this->namespaceAnalyzer->extract($tokens, $deleteExtracted);
    }
}