<?php
/**
 * Paulyg/Autoloader - A slightly different PSR-0 and PSR-4 compatable class autoloader.
 *
 * Copyright 2012-2013 Paul Garvin <paul@paulgarvin.net>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS
 * IN THE SOFTWARE.
 */
namespace Paulyg;
/**
 * This autoloader class supports PSR-0 rules, PEAR type class mappings, and
 * the newly adopted PSR-4 class mapping rules.
 *
 * The inner workings of the autoloader are different from most of the other
 * implementations out there as well. Each time you add a namespace or class
 * prefix with the addPsr0() or addPsr4() methods it creates a Closure and
 * registers that with the PHP's SPL autoloader stack.
 *
 * The addPsr0() or addPsr4() functions only accept a string as the $path
 * argument whereas other implementations allow an array of paths here.
 * But you can call addPsr0() and addPsr4() multiple times with the same
 * $prefix. Each call will register a different Closure with PHP. You can
 * affect the order directories are looked in by prepending to the stack
 * instead of the deault of appending.
 *
 * The PHP engine will be responsible for looping through all the Closures
 * registered on the stack vs. the way most implementations work, foreaching
 * through an array of paths in userland code. Allowing the PHP engine to do
 * iteration should be faster. Also since each add* call registers with the
 * SPL autoload stack automatically there is no need to call a separate
 * register() function as is the case with most other framework autoloaders.
 *
 * @author Paul Garvin <paul@paulgarvin.net>
 * @copyright Copyright 2012-2013 Paul Garvin.
 * @license MIT License
 */
class Autoloader
{
    protected static $autoloaders = array();
    public static function addPsr0($prefix, $path, $prepend = false)
    {
        $key = self::getHashKey($prefix, $path);
        $prefix = ltrim($prefix, '\\');
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $autoloader = function($fqcn) use ($prefix, $path) {
            if (strpos($fqcn, $prefix) !== 0) {
                return;
            }
            $relpath = Autoloader::class2Path($fqcn);
            $file = $path . DIRECTORY_SEPARATOR . $relpath;
            if (is_file($file)) {
                require $file;
            }
        };
        self::$autoloaders[$key] = $autoloader;
        spl_autoload_register($autoloader, true, $prepend);
    }
    public static function addPsr4($prefix, $path, $prepend = false)
    {
        $key = self::getHashKey($prefix, $path);
        $prefix = ltrim($prefix, '\\');
        $path = rtrim($path, DIRECTORY_SEPARATOR);
        $autoloader = function($fqcn) use ($prefix, $path) {
            if (strpos($fqcn, $prefix) !== 0) {
                return;
            }
            $trimmed = substr($fqcn, strlen($prefix));
            $relpath = Autoloader::class2Path($trimmed);
            $file = $path . DIRECTORY_SEPARATOR . $relpath;
            if (is_file($file)) {
                require $file;
            }
        };
        self::$autoloaders[$key] = $autoloader;
        spl_autoload_register($autoloader, true, $prepend);
    }
    public static function remove($prefix, $path)
    {
        $key = self::getHashKey($prefix, $path);
        if (!isset(self::$autoloaders[$key])) {
            return;
        }
        $autoloader = self::$autoloaders[$key];
        spl_autoload_unregister($autoloader);
        unset(self::$autoloaders[$key]);
    }
    public static function class2Path($fqcn)
    {
        $lastNsPos = strrpos($fqcn, '\\');
        if ($lastNsPos !== false) {
            $ns = substr($fqcn, 0, $lastNsPos);
            $ns = str_replace('\\', DIRECTORY_SEPARATOR, $ns);
            $cls = substr($fqcn, $lastNsPos + 1);
            $cls = str_replace('_', DIRECTORY_SEPARATOR, $cls);
            $normalized = $ns . DIRECTORY_SEPARATOR . $cls;
        } else {
            $normalized = str_replace('_', DIRECTORY_SEPARATOR, $fqcn);
        }
        return $normalized . '.php';
    }
    protected static function getHashKey($prefix, $path)
    {
        return crc32($prefix . '-' . $path);
    }
}