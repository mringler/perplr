<?php

namespace Propel\Tests;

/**
 * Parent class for tests that compare code builder output against file content.
 *
 * Call tests/bin/rebuild-reference-files to update content of files.
 */
class CompareGeneratedCodeTestCase extends TestCase
{
    /**
     * Summary of generateCodeFileContent
     * @param mixed $obj
     * @param array<string> $methods
     *
     * @return string
     */
    public function generateCodeFileContentScript($obj, array $methods): string
    {
        $content = $obj::class . "\n";
        foreach ($methods as $method) {
            $script = '';
            $this->callMethod($obj, $method, [&$script]);
            $content .= $this->buildCodeFileContent("{$method}()", $script);
        }

        return $content;
    }

    public function buildCodeFileContent(string $header, string $code): string
    {
        return "\n\n$header:\n$code";
    }
}
