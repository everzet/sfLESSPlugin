<?php

/*
 * This file is part of the sfLESSPlugin.
 * (c) 2010 Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * sfLESS is helper class to provide LESS compiling in symfony projects.
 *
 * @package    sfLESSPlugin
 * @subpackage lib
 * @author     Konstantin Kudryashov <ever.zet@gmail.com>
 * @version    1.0.0
 */
class sfLESS
{
  /**
   * Array of LESS styles
   *
   * @var array
   */
  protected static $results = array();

  /**
   * Errors of compiler
   *
   * @var array
   */
  protected static $errors  = array();

  /**
   * Current LESS file to be parsed. This var used to help output errors in callCompiler()
   *
   * @var string
   */
  protected $currentFile;

  /**
   * LESS configuration manager
   *
   * @var LESSConfig
   */
  protected $config;

  /**
   * Constructor
   *
   * @param   LESSConfig  $config   configuration manager
   */
  public function __construct(LESSConfig $config)
  {
    $this->config = $config;
  }

  /**
   * Returns configuration manager
   *
   * @return  LESSConfig  configurator instance
   */
  public function getConfig()
  {
    return $this->config;
  }

  /**
   * Returns array of compiled styles info
   *
   * @return  array
   */
  public static function getCompileResults()
  {
    return self::$results;
  }

  /**
   * Returns array of compiled styles errors
   *
   * @return  array
   */
  public static function getCompileErrors()
  {
    return self::$errors;
  }

  /**
   * Returns all CSS files under the CSS directory
   *
   * @return  array   an array of CSS files
   */
  public function findCssFiles()
  {
    return sfFinder::type('file')
      ->exec(array('sfLESSUtils', 'isCssLessCompiled'))
      ->name('*.css')
      ->in($this->config->getCssPaths());
  }

  /**
   * Returns all LESS files under the LESS directories
   *
   * @return  array   an array of LESS files
   */
  public function findLessFiles()
  {
    return sfFinder::type('file')
      ->name('*.less')
      ->discard('_*')
      ->follow_link()
      ->in($this->config->getLessPaths());
  }

  /**
   * Returns CSS file path by its LESS alternative
   *
   * @param   string  $lessFile LESS file path
   * 
   * @return  string            CSS file path
   */
  public function getCssPathOfLess($lessFile)
  {
    $file = preg_replace('/\.less$/', '.css', $lessFile);
    $file = preg_replace(sprintf('/^%s/', preg_quote($this->config->getLessPaths(), '/')), $this->config->getCssPaths(), $file);
    return $file;
  }

  /**
   * Compiles LESS file to CSS
   *
   * @param   string  $lessFile a LESS file
   * 
   * @return  boolean           true if succesfully compiled & false in other way
   */
  public function compile($lessFile)
  {
    // Creates timer
    $timer = new sfTimer;

    // Gets CSS file path
    $cssFile = $this->getCssPathOfLess($lessFile);

    // Checks if path exists & create if not
    if (!is_dir(dirname($cssFile)))
    {
      mkdir(dirname($cssFile), 0777, true);
      // PHP workaround to fix nested folders
      chmod(dirname($cssFile), 0777);
    }

    // Is file compiled
    $isCompiled = false;

    // If we check dates - recompile only really old CSS
    if ($this->config->isCheckDates())
    {
      try
      {
        $d = new sfLESSDependency(sfConfig::get('sf_web_dir'),
          sfConfig::get('app_sf_less_plugin_check_dependencies', false));
        if (!is_file($cssFile) || $d->getMtime($lessFile) > filemtime($cssFile))
        {
          $isCompiled = $this->callCompiler($lessFile, $cssFile);
        }
      }
      catch (Exception $e)
      {
        $isCompiled = false;
      }
    }
    else
    {
      $isCompiled = $this->callCompiler($lessFile, $cssFile);
    }

    // Adds debug info to debug array
    self::$results[] = array(
      'lessFile'   => $lessFile,
      'cssFile'    => $cssFile,
      'compTime'   => $timer->getElapsedTime(),
      'isCompiled' => $isCompiled
    );

    return $isCompiled;
  }

  /**
   * Calls current LESS compiler for single file
   *
   * @param   string  $lessFile a LESS file
   * @param   string  $cssFile  a CSS file
   * 
   * @return  boolean           true if succesfully compiled & false in other way
   */
  public function callCompiler($lessFile, $cssFile)
  {
    // Setting current file. We will output this var if compiler throws error
    $this->currentFile = $lessFile;

    // Do not try to change the permission of an existing file which we might not own
    $setPermission = !is_file($cssFile);

    // Call compiler
    $buffer = $this->callLesscCompiler($lessFile, $cssFile);

    // Checks if compiler returns false
    if (false === $buffer)
    {
      return $buffer;
    }

    // Compress CSS if we use compression
    if ($this->config->isUseCompression())
    {
      $buffer = sfLESSUtils::getCompressedCss($buffer);
    }

    // Add compiler header to CSS & writes it to file
    file_put_contents($cssFile, sfLESSUtils::getCssHeader() . "\n\n" . $buffer);

    if ($setPermission)
    {
      // Set permissions for fresh files only
      chmod($cssFile, 0666);
    }

    // Setting current file to null
    $this->currentFile = null;

    return true;
  }

  /**
   * Calls lessc compiler for LESS file
   *
   * @param   string  $lessFile a LESS file
   * @param   string  $cssFile  a CSS file
   * 
   * @return  string            output
   */
  public function callLesscCompiler($lessFile, $cssFile)
  {
    // Compile with lessc
    $fs = new sfFilesystem;
    $command = sprintf('lessc "%s" "%s"', $lessFile, $cssFile);

    if ('1.3.0' <= SYMFONY_VERSION)
    {
      try
      {
        $fs->execute($command, null, array($this, 'throwCompilerError'));
      }
      catch (RuntimeException $e)
      {
        return false;
      }
    }
    else
    {
      $fs->sh($command);
    }

    return file_get_contents($cssFile);
  }

  /**
   * Returns true if compiler can throw RuntimeException
   *
   * @return boolean
   */
  public function canThrowExceptions()
  {
    return (('prod' !== sfConfig::get('sf_environment') || !sfConfig::get('sf_app')) &&
        !(sfConfig::get('sf_web_debug') && sfConfig::get('app_sf_less_plugin_toolbar', true))
    );
  }

  /**
   * Throws formatted compiler error
   *
   * @param   string  $line error line
   * 
   * @return  boolean
   */
  public function throwCompilerError($line)
  {
    // Generate error description
    $errorDescription = sprintf("LESS parser error in \"%s\":\n\n%s", $this->currentFile, $line);

    // Adds error description to list of errors
    self::$errors[$this->currentFile] = $errorDescription;

    // Throw exception if allowed & log error otherwise
    if ($this->canThrowExceptions())
    {
      throw new sfException($errorDescription);
    }
    else
    {
      sfContext::getInstance()->getLogger()->err($errorDescription);
    }

    return false;
  }
}
