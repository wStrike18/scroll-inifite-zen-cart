<?php
include_once "message_stack.php";


function io_lock($file){
  global $bmzConf;
  // no locking if safemode hack
  if ($bmzConf['safemodehack']) return;

  $lockDir = $bmzConf['lockdir'] . '/' . md5($file);
  @ignore_user_abort(1);

  
  $timeStart = time();
  do {
    //waited longer than 3 seconds? -> stale lock
    if ((time() - $timeStart) > 3) break;
    $locked = @mkdir($lockDir);
  } while ($locked === false);
}

/**
 * Unlocks a file
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Tim Kroeger <tim@breakmyzencart.com>
 */
function io_unlock($file){
  global $bmzConf;

  // no locking if safemode hack
  if($bmzConf['safemodehack']) return;

  $lockDir = $bmzConf['lockdir'] . '/' . md5($file);
  @rmdir($lockDir);
  @ignore_user_abort(0);
}

/**
 * Returns the name of a cachefile from given data
 *
 * The needed directory is created by this function!
 *
 * @author Andreas Gohr <andi@splitbrain.org>
 * @author Tim Kroeger <tim@breakmyzencart.com>
 *
 * @param string $data  This data is used to create a unique md5 name
 * @param string $ext   This is appended to the filename if given
 * @return string       The filename of the cachefile
 */
function getCacheName($data, $ext='') {
  global $bmzConf;

  $md5  = md5($data);
  $file = $bmzConf['cachedir'] . '/' . $md5{0} . '/' . $md5.$ext;
  io_makeFileDir($file);

 $bmzConf['cachedir'];
  return $file;
}


function io_makeFileDir($file){
  global $messageStack;
  global $bmzConf;

  $dir = dirname($file);
  $dmask = $bmzConf['dmask'];
  umask($dmask);
  if(!is_dir($dir)){
    io_mkdir_p($dir) ;
  }
  umask($bmzConf['umask']); 
}

/**
 * Creates a directory hierachy.
 *
 * @link    http://www.php.net/manual/en/function.mkdir.php
 * @author  <saint@corenova.com>
 * @author  Andreas Gohr <andi@splitbrain.org>
 * @author  Tim Kroeger <tim@breakmyzencart.com>
 */
function io_mkdir_p($target){
  global $bmzConf;

  if (is_dir($target) || empty($target)) return 1; // best case check first
  if (@file_exists($target) && !is_dir($target)) return 0;
  //recursion
  if (io_mkdir_p(substr($target, 0, strrpos($target, '/')))){
    if($bmzConf['safemodehack']){
      $dir = preg_replace('/^' . preg_quote(realpath($bmzConf['ftp']['root']), '/') . '/', '', $target);
      return io_mkdir_ftp($dir);
    }else{
      return @mkdir($target, 0777); // crawl back up & create dir tree
    }
  }
  return 0;
}

/**
 * Creates a directory using FTP
 *
 * This is used when the safemode workaround is enabled
 *
 * @author <andi@splitbrain.org>
 */
function io_mkdir_ftp($dir){
  global $messageStack;
  global $bmzConf;

  if(!function_exists('ftp_connect')){
    $messageStack->add("FTP support not found - safemode workaround not usable", "error");
    return false;
  }
  
  $conn = @ftp_connect($bmzConf['ftp']['host'], $bmzConf['ftp']['port'], 10);
  if(!$conn){
    $messageStack->add("FTP connection failed", "error");
    return false;
  }

  if(!@ftp_login($conn, $bmzConf['ftp']['user'], $bmzConf['ftp']['pass'])){
    $messageStack->add("FTP login failed", "error");
    return false;
  }

  //create directory
  $ok = @ftp_mkdir($conn, $dir);
  //set permissions (using the directory umask)
  @ftp_site($conn, sprintf("CHMOD %04o %s", (0777 - $bmzConf['dmask']), $dir));

  @ftp_close($conn);
  return $ok;
}
