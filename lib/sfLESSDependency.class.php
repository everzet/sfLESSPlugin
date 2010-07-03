<?php

/*
 * This file is part of the sfLESSPlugin.
 * (c) 2010 Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfLESSDependency checks for less dependencies
 *
 * @package    sfLESSPlugin
 * @subpackage lib
 * @author     Victor Berchet <victor@suumit.com>
 * @version    1.0.0
 */
class sfLESSDependency
{
  /**
   * Base path
   */
  protected $path;

  public function __construct($path)
  {
    if (!sfLESSUtils::isPathAbsolute($path) || !is_dir($path))
    {
      throw new InvalidArgumentException("An existing absolute folder must be provided");
    }
    else
    {
      $this->path = preg_replace('/\/$/', '', $path);
    }
  }

  /**
   * Compute the dependencies of the file
   *
   * @param file $lessFile A less file
   * @param array $deps An array of pre-existing dependencies
   * @return array The updated array of dependencies
   */
  public function computeDependencies($lessFile, array $deps)
  {
    if (!sfLESSUtils::isPathAbsolute($lessFile))
    {
      $lessFile = realpath($this->path . '/' . $lessFile);
    }

    if (is_file($lessFile))
    {
      $less = file_get_contents($lessFile);
      if (preg_match_all("/\s*@import\s+(['\"])(.*?)\\1\s*;/", $less, $files))
      {
        foreach ($files[2] as $file)
        {
          // Append the .less extension when omitted
          if (!preg_match('/\.(le?|c)ss$/', $file))
          {
            $file .= '.less';
          }
          // Compute the canonical path
          if (sfLESSUtils::isPathAbsolute($file))
          {
            $file = realpath($this->path . $file);
          }
          else
          {
            $file = realpath(dirname($lessFile) . '/' . $file);
          }
          if ($file !== false && !in_array($file, $deps))
          {
            $deps[] = $file;
            // Recursively add dependencies
            $deps = array_merge($deps, $this->computeDependencies($file, $deps));
          }
        }
      }
      return $deps;
    }
    else
    {
      return array();
    }
  }
}