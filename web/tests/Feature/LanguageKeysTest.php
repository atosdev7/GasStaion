<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

// Every language key written in the code must exist in resources/lang/en, so a moved or renamed key shows up here and not as raw
// text on a page. A key built at run time ('cards.status_' . $status) needs at least one key with that start.
class LanguageKeysTest extends TestCase
{
    private function flatten(array $lines, $prefix = '')
    {
        $keys = [];
        foreach ($lines as $key => $value) {
            $full = $prefix === '' ? $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $keys = array_merge($keys, $this->flatten($value, $full));
            } else {
                $keys[$full] = true;
            }
        }

        return $keys;
    }

    private function languageKeys()
    {
        $keys = [];
        foreach (glob(resource_path('lang/en/*.php')) as $file) {
            $keys = array_merge($keys, $this->flatten([basename($file, '.php') => require $file]));
        }

        return $keys;
    }

    private function codeFiles()
    {
        $files = [];
        foreach ([app_path(), resource_path('views')] as $dir) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && substr($file->getFilename(), -4) === '.php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    public function testEveryKeyUsedInTheCodeExists()
    {
        $keys = $this->languageKeys();
        $names = [];
        foreach ($keys as $key => $ignored) {
            $names[substr($key, 0, strpos($key, '.'))] = true;
        }

        $missing = [];
        foreach ($this->codeFiles() as $path) {
            $code = file_get_contents($path);
            if (!preg_match_all('/(?:__|trans|trans_choice|@lang)\(\s*[\'"]([a-z_]+)\.([A-Za-z0-9_.]+)[\'"]/', $code, $found, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($found as $match) {
                if (!isset($names[$match[1]])) {
                    continue; // not one of our language files
                }
                $key = $match[1] . '.' . $match[2];
                $exists = isset($keys[$key]);
                if (!$exists && substr($key, -1) === '_') {
                    foreach ($keys as $known => $ignored) {
                        if (strpos($known, $key) === 0) {
                            $exists = true;
                            break;
                        }
                    }
                }
                if (!$exists) {
                    $missing[] = $key . ' (' . basename($path) . ')';
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), 'language keys used in the code but not defined');
    }
}
