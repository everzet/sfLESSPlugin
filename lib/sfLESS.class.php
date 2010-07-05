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
   **/
  protected static $results = array();

  /**
   * Errors of compiler
   *
   * @var array
   **/
  protected static $errors  = array();

  /**
   * Do we need to check dates before compile
   *
   * @var boolean
   */
  protected $checkDates     = true;

  /**
   * Do we need compression for CSS files
   *
   * @var boolean
   */
  protected $useCompression = false;

  /**
   * Current LESS file to be parsed. This var used to help output errors in callCompiler()
   *
   * @var string
   */
  protected $currentFile;

  /**
   * Constructor
   *
   * @param   boolean $checkDates     Do we need to check dates before compile
   * @param   boolean $useCompression Do we need compression for CSS files
   */
  public function __construct($checkDates = true, $useCompression = false)
  {
    $this->setIsCheckDates($checkDates);
    $this->setIsUseCompression($useCompression);
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
   * Returns debug info of the current state
   *
   * @return  array state
   */
  public function getDebugInfo()
  {
    return array(
      'dates'       => var_export($this->isCheckDates(), true),
      'compress'    => var_export($this->isUseCompression(), true),
      'less'        => $this->getLessPaths(),
      'css'         => $this->getCssPaths()
    );
  }

  /**
   * Returns path with changed directory separators to unix-style (\ => /)
   *
   * @param   string  $path basic path
   * 
   * @return  string        unix-style path
   */
  public static function getSepFixedPath($path)
  {
    return str_replace(DIRECTORY_SEPARATOR, '/', $path);
  }

  /**
   * Returns relative path from the project root dir
   *
   * @param   string  $fullPath full path to file
   * 
   * @return  string            relative path from the project root
   */
  public static function getProjectRelativePath($fullPath)
  {
    return str_replace(
      self::getSepFixedPath(sfConfig::get('sf_root_dir')) . '/',
      '',
      self::getSepFixedPath($fullPath)
    );
  }

  /**
   * Do we need to check dates before compile
   *
   * @return  boolean
   */
  public function isCheckDates()
  {
    return sfConfig::get('app_sf_less_plugin_check_dates', $this->checkDates);
  }

  /**
   * Set need of check dates before compile
   *
   * @param   boolean $checkDates Do we need to check dates before compile
   */
  public function setIsCheckDates($checkDates)
  {
    $this->checkDates = $checkDates;
  }

  /**
   * Do we need compression for CSS files
   *
   * @return  boolean
   */
  public function isUseCompression()
  {
    return sfConfig::get('app_sf_less_plugin_use_compression', $this->useCompression);
  }

  /**
   * Set need of compression for CSS files
   *
   * @param   boolean $useCompression Do we need compression for CSS files
   */
  public function setIsUseCompression($useCompression)
  {
    $this->useCompression = $useCompression;
  }

  /**
   * Returns paths to CSS files
   *
   * @return  string  a path to CSS files directory
   */
  static public function getCssPaths()
  {  
    return self::getSepFixedPath(sfConfig::get('sf_web_dir')) . '/css/';
  }

  /**
   * Returns all CSS files under the CSS directory
   *
   * @return  array   an array of CSS files
   */
  static public function findCssFiles()
  {
    return sfFinder::type('file')
      ->exec(array('sfLESS', 'isCssLessCompiled'))
      ->name('*.css')
      ->in(self::getCssPaths());
  }

  /**
   * Returns header text for CSS files
   *
   * @return  string  a header text for CSS files
   */
  static protected function getCssHeader()
  {
    return '/* This CSS is autocompiled by LESS parser. Don\'t edit it manually. */';
  }

  /**
   * Checks if CSS file was compiled from LESS
   *
   * @param   string  $dir    a path to file
   * @param   string  $entry  a filename
   * 
   * @return  boolean
   */
  static public function isCssLessCompiled($dir, $entry)
  {
    $file = $dir . '/' . $entry;
    $fp = fopen( $file, 'r' );
    $line = stream_get_line($fp, 1024, "\n");
    fclose($fp);

    return (0 === strcmp($line, self::getCssHeader()));
  }

  /**
   * Returns paths to LESS files
   *
   * @return  string  a path to LESS files directories
   */
  static public function getLessPaths()
  {
    return self::getSepFixedPath(sfConfig::get('sf_web_dir')) . '/less/';
  }

  /**
   * Returns all LESS files under the LESS directories
   *
   * @return  array   an array of LESS files
   */
  static public function findLessFiles()
  {
    return sfFinder::type('file')
      ->name('*.less')
      ->discard('_*')
      ->follow_link()
      ->in(self::getLessPaths());
  }

  /**
   * Returns CSS file path by its LESS alternative
   *
   * @param   string  $lessFile LESS file path
   * 
   * @return  string            CSS file path
   */
  static public function getCssPathOfLess($lessFile)
  {
    return str_replace(
      array(self::getLessPaths(), '.less'),
      array(self::getCssPaths(), '.css'),
      $lessFile
    );
  }

  /**
   * Update the response by fixing less stylesheet path and adding the less js engine when required
   *
   * @param   sfWebResponse $response The response that will be sent back to the browser
   * @param   boolean       $useJs    Wether the less stylesheets should be processed by the js on the client side
   */
  static public function findAndFixContentLinks(sfWebResponse $response, $useJs)
  {
    $hasLess  = false;

    foreach ($response->getStylesheets() as $file => $options)
    {
      if ('.less' === substr($file, -5) && (!isset($options['rel']) || 'stylesheet/less' !== $options['rel']))
      {
        $response->removeStylesheet($file);
        if ($useJs)
        {
          $response->addStylesheet('/less/' . $file, '', array_merge($options, array('rel' => 'stylesheet/less')));
        $hasLess = true;
        }
        else
        {
          $response->addStylesheet('/css/' . substr($file, 0, -5) . '.css', '', $options);
        }
      }
    }

    if ($hasLess)
    {
      if (sfConfig::get('symfony.asset.javascripts_included', false))
      {
        throw new LogicException("The stylesheets must be included before the javascript in your layout");
      }
      else
      {
        $response->addJavascript(
          sfConfig::get('app_sf_less_plugin_js_lib', '/sfLESSPlugin/js/less-1.0.31.min.js')
        );
      }
    }
  }

  /**
   * Listens to the routing.load_configuration event. Finds & compiles LESS files to CSS
   *
   * @param   sfEvent $event  an sfEvent instance
   */
  static public function findAndCompile(sfEvent $event)
  {
    // Start compilation timer for debug info
    $timer = sfTimerManager::getTimer('Less compilation');

    // Create new helper object & compile LESS stylesheets with it
    $lessHelper = new self;
    foreach (self::findLessFiles() as $lessFile)
    {
      $lessHelper->compile($lessFile);
    }

    // Stop timer
    $timer->addTime();
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
    $cssFile = self::getCssPathOfLess($lessFile);

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
    if ($this->isCheckDates())
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
   * Compress CSS by removing whitespaces, tabs, newlines, etc.
   *
   * @param   string  $css  CSS to be compressed
   * 
   * @return  string        compressed CSS
   */
  static public function getCompressedCss($css)
  {
    return str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $css);
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
    if ($this->isUseCompression())
    {
      $buffer = self::getCompressedCss($buffer);
    }

    // Add compiler header to CSS & writes it to file
    file_put_contents($cssFile, self::getCssHeader() . "\n\n" . $buffer);

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
