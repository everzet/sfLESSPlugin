<?php
/*
 * This file is part of the sfLESSPlugin.
 * (c) 2010 Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Various utility functions
 *
 * @package    sfLESSPlugin
 * @subpackage lib
 * @author     Victor Berchet <victor@suumit.com>
 * @version    1.0.0
 */

class sfLESSUtils
{
  /**
   * Determine if a filesystem path is absolute.
   *
   * @param  path $path  A filesystem path.
   *
   * @return bool true, if the path is absolute, otherwise false.
   */
  public static function isPathAbsolute($path)
  {
    if ($path[0] == '/' || $path[0] == '\\' ||
        (strlen($path) > 3 && ctype_alpha($path[0]) &&
         $path[1] == ':' &&
         ($path[2] == '\\' || $path[2] == '/')
        )
       )
    {
      return true;
    }
    return false;
  }

  /**
   * Strip comments from less content
   * 
   * @param string $less LESS code
   * @return string LESS code without comments
   */
  public static function stripLessComments($less)
  {
    // strip /* */ style comments
    $less = preg_replace('#/\*.*?\*/#ms', '', $less);
    // stip // style comments
    $less = preg_replace('#//.*$#m', '', $less);
    return $less;
  }
}