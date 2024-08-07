<?php

namespace YRV\Autoloader\Parser\Analyzers;

interface ContentAnalyzer
{
    public function extract(array &$tokens, $deleteExtracted = false): \Generator;
}