<?php
/**
 * PHPUnit
 *
 * Copyright (c) 2002-2007, Sebastian Bergmann <sb@sebastian-bergmann.de>.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 * 
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the name of Sebastian Bergmann nor the names of his
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   Testing
 * @package    PHPUnit
 * @author     Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @copyright  2002-2007 Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    SVN: $Id$
 * @link       http://www.phpunit.de/
 * @since      File available since Release 3.1.0
 */

require_once 'PHPUnit/Util/Filter.php';

PHPUnit_Util_Filter::addFileToFilter(__FILE__, 'PHPUNIT');

/**
 * Class helpers.
 *
 * @category   Testing
 * @package    PHPUnit
 * @author     Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @copyright  2002-2007 Sebastian Bergmann <sb@sebastian-bergmann.de>
 * @license    http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @version    Release: @package_version@
 * @link       http://www.phpunit.de/
 * @since      Class available since Release 3.1.0
 */
class PHPUnit_Util_Class
{
    protected static $buffer = array();
    protected static $fileClassMap = array();
    protected static $noc = array();

    /**
     * Starts the collection of loaded classes.
     *
     * @access public
     * @static
     */
    public static function collectStart()
    {
        self::$buffer = get_declared_classes();
    }

    /**
     * Stops the collection of loaded classes and
     * returns the names of the loaded classes.
     *
     * @return array
     * @access public
     * @static
     */
    public static function collectEnd()
    {
        return array_values(
          array_diff(get_declared_classes(), self::$buffer)
        );
    }

    /**
     * Stops the collection of loaded classes and
     * returns the names of the files that declare the loaded classes.
     *
     * @return array
     * @access public
     * @static
     */
    public static function collectEndAsFiles()
    {
        $result = self::collectEnd();
        $count  = count($result);

        for ($i = 0; $i < $count; $i++) {
            $class = new ReflectionClass($result[$i]);

            if ($class->isUserDefined()) {
                $file = $class->getFileName();

                if (file_exists($file)) {
                    $result[$i] = $file;
                } else {
                    unset($result[$i]);
                }
            }
        }

        return $result;
    }

    /**
     * Returns the names of the classes declared in a sourcefile.
     *
     * @param  string  $filename
     * @param  string  $commonPath
     * @param  boolean $clearCache
     * @return array
     * @access public
     * @static
     */
    public static function getClassesInFile($filename, $commonPath = '', $clearCache = FALSE)
    {
        if ($commonPath != '') {
            $filename = str_replace($commonPath, '', $filename);
        }

        if ($clearCache) {
            self::$fileClassMap = array();
        }

        if (empty(self::$fileClassMap)) {
            $classes = array_merge(get_declared_classes(), get_declared_interfaces());
            $count   = count($classes);
            
            for ($i = 0; $i < $count; $i++) {
                $class = new ReflectionClass($classes[$i]);

                if ($class->isUserDefined()) {
                    $file = $class->getFileName();

                    if ($commonPath != '') {
                        $file = str_replace($commonPath, '', $file);
                    }

                    if (!isset(self::$fileClassMap[$file])) {
                        self::$fileClassMap[$file] = array($class);
                    } else {
                        self::$fileClassMap[$file][] = $class;
                    }
                }
            }
        }

        return isset(self::$fileClassMap[$filename]) ? self::$fileClassMap[$filename] : array();
    }

    /**
     * Returns the class hierarchy for a given class.
     *
     * @param  string  $className
     * @return array
     * @access public
     * @static
     */
    public static function getHierarchy($className)
    {
        $classes = array($className);
        $done    = FALSE;

        while (!$done) {
            $class  = new ReflectionClass($classes[count($classes)-1]);
            $parent = $class->getParentClass();

            if ($parent !== FALSE) {
                $classes[] = $parent->getName();
            } else {
                $done = TRUE;
            }
        }

        return $classes;
    }

    /**
     * Returns the sourcecode of a user-defined class.
     *
     * @param  string  $className
     * @param  string  $methodName
     * @return string
     * @access public
     * @static
     */
    public static function getMethodSource($className, $methodName)
    {
        $method = new ReflectionMethod($className, $methodName);
        $file   = file($method->getFileName());
        $result = '';

        for ($line = $method->getStartLine() - 1; $line <= $method->getEndLine() - 1; $line++) {
            $result .= $file[$line];
        }

        return $result;
    }

    /**
     * Returns the Cyclomatic Complexity Number (CCN) for a method.
     * This is also known as the McCabe metric.
     *
     * Each method has a minimum value of 1 per default. For each of the
     * following PHP keywords/statements this value gets incremented by one:
     *
     *   - if
     *   - for
     *   - foreach
     *   - while
     *   - case
     *   - catch
     *   - AND, &&
     *   - OR, ||
     *
     * Note that 'else', 'default', and 'finally' don't increment the value
     * any further. On the other hand, a simple method with a 'switch'
     * statement and a huge block of 'case 'statements can have a surprisingly
     * high value (still it has the same value when converting a 'switch'
     * block to an equivalent sequence of 'if' statements).
     *
     * @param  string  $className
     * @param  string  $methodName
     * @return integer
     * @access public
     * @static
     * @since  Method available since Release 3.1.6
     */
    public static function getCCN($className, $methodName)
    {
        $source = self::getMethodSource($className, $methodName);
        $tokens = token_get_all('<?php' . $source . '?>');
        $ccn    = 1;

        foreach ($tokens as $i => $token) {
            if (is_string($token)) {
                continue;
            }

            list ($token, $value) = $token;

            switch ($token) {
                case T_IF:
                case T_FOR:
                case T_FOREACH:
                case T_WHILE:
                case T_CASE:
                case T_CATCH:
                case T_BOOLEAN_AND:
                case T_LOGICAL_AND:
                case T_BOOLEAN_OR:
                case T_LOGICAL_OR: {
                    $ccn++;
                }
                break;
            }
        }

        return $ccn;
    }

    /**
     * Returns the Attribute Inheritance Factor (AIF) for a class.
     *
     * @param  string  $className
     * @return integer
     * @access public
     * @static
     * @since  Method available since Release 3.1.6
     */
    public static function getAIF($className)
    {
        $class = new ReflectionClass($className);

        $attributes          = 0;
        $inheritedAttributes = 0;

        foreach ($class->getProperties() as $attribute) {
            if ($attribute->getDeclaringClass()->getName() != $className) {
                $inheritedAttributes++;
            }

            $attributes++;
        }

        if ($attributes > 0) {
            return (100 * $inheritedAttributes) / $attributes;
        } else {
            return 0;
        }
    }

    /**
     * Returns the Attribute Hiding Factor (AHF) for a class.
     *
     * @param  string  $className
     * @return integer
     * @access public
     * @static
     * @since  Method available since Release 3.1.6
     */
    public static function getAHF($className)
    {
        $class = new ReflectionClass($className);

        $attributes       = 0;
        $hiddenAttributes = 0;

        foreach ($class->getProperties() as $attribute) {
            if (!$attribute->isPublic()) {
                $hiddenAttributes++;
            }

            $attributes++;
        }

        if ($attributes > 0) {
            return (100 * $hiddenAttributes) / $attributes;
        } else {
            return 0;
        }
    }

    /**
     * Returns the Depth of Inheritance Tree (DIT) for a class.
     *
     * @param  string  $className
     * @return integer
     * @access public
     * @static
     * @since  Method available since Release 3.1.6
     */
    public static function getDIT($className)
    {
        return count(self::getHierarchy($className));
    }

    /**
     * Returns the Method Inheritance Factor (MIF) for a class.
     *
     * @param  string  $className
     * @return float
     * @access public
     * @static
     * @since  Method available since Release 3.1.6
     */
    public static function getMIF($className)
    {
        $class = new ReflectionClass($className);

        $methods          = 0;
        $inheritedMethods = 0;

        foreach ($class->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() != $className) {
                $inheritedMethods++;
            }

            $methods++;
        }

        if ($methods > 0) {
            return (100 * $inheritedMethods) / $methods;
        } else {
            return 0;
        }
    }

    /**
     * Returns the Method Hiding Factor (MHF) for a class.
     *
     * @param  string  $className
     * @return float
     * @access public
     * @static
     * @since  Method available since Release 3.1.6
     */
    public static function getMHF($className)
    {
        $class = new ReflectionClass($className);

        $methods       = 0;
        $hiddenMethods = 0;

        foreach ($class->getMethods() as $method) {
            if (!$method->isPublic()) {
                $hiddenMethods++;
            }

            $methods++;
        }

        if ($methods > 0) {
            return (100 * $hiddenMethods) / $methods;
        } else {
            return 0;
        }
    }

    /**
     * Returns the Number of Children (NOC) for a class.
     *
     * @param  string  $className
     * @param  boolean $clearCache
     * @return integer
     * @access public
     * @static
     * @since  Method available since Release 3.1.6
     */
    public static function getNOC($className, $clearCache = FALSE)
    {
        if ($clearCache) {
            self::$noc = array();
        }

        if (empty(self::$noc)) {
            foreach (get_declared_classes() as $_className) {
                $class  = new ReflectionClass($_className);
                $parent = $class->getParentClass();

                if ($parent !== FALSE) {
                    $parentName = $parent->getName();

                    if (isset(self::$noc[$parentName])) {
                        self::$noc[$parentName]++;
                    } else {
                        self::$noc[$parentName] = 1;
                    }
                }
            }
        }

        if (isset(self::$noc[$className])) {
            return self::$noc[$className];
        } else {
            return 0;
        }
    }

    /**
     * Returns the Polymorphism Factor (PF) for a class.
     *
     * @param  string  $className
     * @return float
     * @access public
     * @static
     * @since  Method available since Release 3.1.6
     */
    public static function getPF($className)
    {
        $class       = new ReflectionClass($className);
        $parentClass = $class->getParentClass();

        if ($parentClass !== FALSE) {
            $overridableMethods = array();

            foreach ($parentClass->getMethods() as $method) {
                if (!$method->isPrivate() && !$method->isFinal() && !$method->isAbstract()) {
                    $overridableMethods[] = $method->getName();
                }
            }

            if (!empty($overridableMethods)) {
                $overriddenMethods = 0;

                foreach ($class->getMethods() as $method) {
                    if ($method->getDeclaringClass()->getName() == $className) {
                        $methodName = $method->getName();

                        if (in_array($methodName, $overridableMethods)) {
                            $overriddenMethods++;
                        }
                    }
                }

                return (100 * $overriddenMethods) / count($overridableMethods);
            }
        }

        return 0;
    }
}
?>
