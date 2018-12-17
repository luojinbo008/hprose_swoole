<?php
/**
 * Created by PhpStorm.
 * User: luojinbo
 * Date: 2018/9/21
 * Time: 18:19
 */

namespace HproseSwoole;


class Loader
{
    /**
     * 命名空间的路径
     */
    protected static $namespaces;
    public static $_objects;

    /**
     * Loader constructor.
     */
    public function __construct()
    {
        self::$_objects = [
            'model'  => new \ArrayObject,
            'object' => new \ArrayObject
        ];
    }

    /**
     * 自动载入类
     * @param $class
     */
    public static function autoload($class)
    {
        $root = explode('\\', trim($class, '\\'), 2);
        if (count($root) > 1 and isset(self::$namespaces[$root[0]])) {
            include self::$namespaces[$root[0]] . '/'.str_replace('\\', '/', $root[1]) . '.php';
        }
    }

    /**
     * 设置根命名空间
     * @param $root
     * @param $path
     */
    public static function addNameSpace($root, $path)
    {
        self::$namespaces[$root] = $path;
    }
}