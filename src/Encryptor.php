<?php
namespace SS;
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 加密解密类
 * @author walkor<walkor@workerman.net>
 */
class Encryptor
{
    private static $encryptTable;
    private static $decryptTable;
    private static $cachedTables;

    public static function init()
    {
        if(!self::$encryptTable)
        {
            $ref = self::getTable();
            self::$encryptTable = $ref[0];
            self::$decryptTable = $ref[1];
        }
    }

    public static function decrypt($buffer)
    {
        return self::substitute(self::$decryptTable, $buffer);
    }

    private static function substitute($table, $buf)
    {
        $i = 0;
        $len = strlen($buf);
        while ($i < $len) {
            $buf[$i] = chr($table[ord($buf[$i])]);
            $i++;
        }
        return $buf;
    }

    private static function getTable()
    {
        if (isset(self::$cachedTables))
        {
            return self::$cachedTables;
        }
        $int32Max = pow(2, 32);
        $table = [];
        $decryptTable = [];
        $hash = md5('', true);
        $tmp = unpack('V2', $hash);
        $al = $tmp[1];
        $ah = $tmp[2];
        $i = 0;
        while ($i < 256) {
            $table[$i] = $i;
            $i++;
        }
        $i = 1;
        while ($i < 1024) {
            $table = self::merge_sort($table, function($x, $y)use($ah, $al, $i, $int32Max) {
                return (($ah % ($x + $i)) * $int32Max + $al) % ($x + $i) - (($ah % ($y + $i)) * $int32Max + $al) % ($y + $i);
            });
            $i++;
        }
        $table = array_values($table);
        $i = 0;
        while ($i < 256) {
            $decryptTable[$table[$i]] = $i;
            ++$i;
        }
        ksort($decryptTable);
        $decryptTable = array_values($decryptTable);
        $result = [$table, $decryptTable];
        self::$cachedTables = $result;
        return $result;
    }

    private static function merge_sort($array, $comparison)
    {
        if (count($array) < 2) {
            return $array;
        }
        $middle = ceil(count($array) / 2);
        return self::merge(
            self::merge_sort(self::slice($array, 0, $middle), $comparison),
            self::merge_sort(self::slice($array, $middle), $comparison),
            $comparison
        );
    }

    private static function slice($table, $start, $end = null)
    {
        $table = array_values($table);
        if($end)
        {
            return array_slice($table, $start, $end);
        }
        else
        {
            return array_slice($table, $start);
        }
    }

    private static function merge($left, $right, $comparison)
    {
        $result = array();
        while ((count($left) > 0) && (count($right) > 0)) {
            if(call_user_func($comparison, $left[0], $right[0]) <= 0){
                $result[] = array_shift($left);
            } else {
                $result[] = array_shift($right);
            }
        }
        while (count($left) > 0) {
            $result[] = array_shift($left);
        }
        while (count($right) > 0) {
            $result[] = array_shift($right);
        }
        return $result;
    }
}

