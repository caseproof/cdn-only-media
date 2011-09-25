<?php
/**
 * WordPress S3 Filesystem.

Copyright (C) 2011 Caseproof, LLC

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>

*/

/**
 * WordPress Filesystem Class for direct PHP file and folder manipulation.
 *
 * @uses WP_Filesystem_Base Extends class
 */
class WP_Filesystem_S3 extends WP_Filesystem_Base
{
  var $errors = null;
  var $s3Obj = null;

  /**
   * constructor
   *
   * @param mixed $arg ingored argument
   */
  function __construct($arg)
  {
    $this->method = 'S3';
    $this->errors = new WP_Error();
  }

  /**
   * connect filesystem.
   *
   * @return bool Returns true on success or false on failure (always true for WP_Filesystem_Direct).
   */
  function connect()
  {
    // Create the s3Obj & connect to S3
    if($w3tcconfig = $this->_get_w3_config())
    {
      $engine = $w3tcconfig->get_string('cdn.engine');

      if($engine == 's3' or $engine == 'cf')
      {
        $this->s3Obj = new S3( $w3tcconfig->get_string( 'cdn.'.$engine.'.key' ),
                               $w3tcconfig->get_string( 'cdn.'.$engine.'.secret' )  ); 

        return ($this->s3Obj !== false);
      }
    }
  
    return false;
  }

  /**
   * Reads entire file into a string
   *
   * @param string $file Name of the file to read.
   * @return string|bool The function returns the read data or false on failure.
   */
  function get_contents($file)
  {
    // Pull down file from Amazon S3 and return it
    if($w3tcconfig = $this->_get_w3_config())
    {
      $engine = $w3tcconfig->get_string('cdn.engine');
  
      if($engine == 's3' or $engine == 'cf')
      {
        $bucket = $w3tcconfig->get_string("cdn.{$engine}.bucket");

        $uri = $this->_get_s3_uri($file);
  
        if( is_object($this->s3Obj) )
          return $this->s3Obj->getObject( $bucket, $uri );
      }
    }
  
    return false;
  }

  /**
   * Reads entire file into an array
   *
   * @param string $file Path to the file.
   * @return array|bool the file contents in an array or false on failure.
   */
  function get_contents_array($file)
  {
    // Pull down file from Amazon S3 and run file on it
    if($w3tcconfig = $this->_get_w3_config())
    {
      $engine = $w3tcconfig->get_string('cdn.engine');
  
      if($engine == 's3' or $engine == 'cf')
      {
        $bucket = $w3tcconfig->get_string("cdn.{$engine}.bucket");

        $uri = $this->_get_s3_uri($file);
  
        if(is_object($this->s3Obj))
        {
          $tmp_file = $this->cwd() . '/' . uniqid( 'S3_file_' );
          if($this->s3Obj->getObject( $bucket, $uri, $tmp_file ))
          {
            if( file_exists($tmp_file) )
            {
              $file_array = @file($tmp_file);
              @unlink($tmp_file);
              return $file_array;
            }
          }
        }
      }
    }
  
    return false;
  }

  /**
   * Write a string to a file
   *
   * @param string $file Remote path to the file where to write the data.
   * @param string $contents The data to write.
   * @param int $mode (optional) The file permissions as octal number, usually 0644.
   * @return bool False upon failure.
   */
  function put_contents($file, $contents, $mode = false )
  {
    // push this thing up to s3
    if($w3tcconfig = $this->_get_w3_config())
    {
      $engine = $w3tcconfig->get_string('cdn.engine');
  
      if($engine == 's3' or $engine == 'cf')
      {
        $bucket = $w3tcconfig->get_string('cdn.'.$engine.'.bucket');
        $uri = $this->_get_s3_uri( $file );
  
        if( is_object($this->s3Obj) )
        {
	  $result = $this->s3Obj->putObject( $contents, $bucket, $uri, $this->_get_s3_perms( $mode ), array(), $this->_get_mime_type($file) );
          
          // We are deleting any reference of the file -- remember this is CDN ONLY
          if(file_exists( $file ))
            @unlink( $file );
        }
      }
    }
  
    return $result;
  }

  /**
   * Gets the current working directory
   *
   * @return string|bool the current working directory on success, or false on failure.
   */
  function cwd()
  {
    // Get the bucket .. or /tmp dir?
    return '/tmp'; // We just work out of the tmp dir
  }

  /**
   * Change directory
   *
   * @param string $dir The new current directory.
   * @return bool Returns true on success or false on failure.
   */
  function chdir($dir)
  {
    // chdir with buckets?
    return true; // Don't worry about this now
  }

  /**
   * Changes file group
   *
   * @param string $file Path to the file.
   * @param mixed $group A group name or number.
   * @param bool $recursive (optional) If set True changes file group recursivly. Defaults to False.
   * @return bool Returns true on success or false on failure.
   */
  function chgrp($file, $group, $recursive = false)
  {
    // Change group permissions
    return true; // for now we don't care what group it's in
  }

  /**
   * Changes filesystem permissions
   *
   * @param string $file Path to the file.
   * @param int $mode (optional) The permissions as octal number, usually 0644 for files, 0755 for dirs.
   * @param bool $recursive (optional) If set True changes file group recursivly. Defaults to False.
   * @return bool Returns true on success or false on failure.
   */
  function chmod($file, $mode = false, $recursive = false)
  {
    // Change S3 permissions
    return true; // TODO: Make this actually work
  }

  /**
   * Changes file owner
   *
   * @param string $file Path to the file.
   * @param mixed $owner A user name or number.
   * @param bool $recursive (optional) If set True changes file owner recursivly. Defaults to False.
   * @return bool Returns true on success or false on failure.
   */
  function chown($file, $owner, $recursive = false)
  {
    // Change S3 owner
    return true; // for now we don't care who the owner is
  }

  /**
   * Gets file owner
   *
   * @param string $file Path to the file.
   * @return string Username of the user.
   */
  function owner($file)
  {
    // Get Owner?
    return "amazon"; // we don't care for now
  }

  /**
   * Gets file permissions
   *
   * FIXME does not handle errors in fileperms()
   *
   * @param string $file Path to the file.
   * @return string Mode of the file (last 4 digits).
   */
  function getchmod($file)
  {
    return $this->_get_file_perms(S3::ACL_PUBLIC_READ); 
  }

  function group($file)
  {
    return "amazon"; // we don't care for now
  }

  function copy($source, $destination, $overwrite = false)
  {
    // Copy the S3 file from one place to another

    // push this thing up to s3
    if($w3tcconfig = $this->_get_w3_config())
    {
      $engine = $w3tcconfig->get_string('cdn.engine');
  
      if($engine == 's3' or $engine == 'cf')
      {
        $bucket = $w3tcconfig->get_string('cdn.'.$engine.'.bucket');

        $src_uri  = $this->_get_s3_uri($source);
        $dest_uri = $this->_get_s3_uri($destination);
  
        if( is_object($this->s3Obj) )
	  $this->s3Obj->copyObject( $bucket, $src_uri, $bucket, $dest_uri, $this->_get_s3_perms($this->getchmod($src_uri)), array(), array( "Content-Type" => $this->_get_mime_type($dest_uri) ) );
      }
    }
  }

  function move($source, $destination, $overwrite = false)
  {
    // Move the S3 file from one place to another
    $this->copy($source, $destination, $overwrite);
    $this->delete($source);
  }

  function delete($file, $recursive = false)
  {
    // Delete an S3 file
    if($w3tcconfig = $this->_get_w3_config())
    {
      $engine = $w3tcconfig->get_string('cdn.engine');
  
      if($engine == 's3' or $engine == 'cf')
      {
        // Check to make sure the file exists before trying to delete it
        if(!$this->exists($file))
        {
	  $uploadpath = wp_upload_dir();
          $file = path_join($uploadpath['basedir'], $file );
          if(!$this->exists($file))
            return false;
        }

        $bucket = $w3tcconfig->get_string('cdn.'.$engine.'.bucket');
        $uri = $this->_get_s3_uri($file);
  
        return $this->s3Obj->deleteObject( $bucket, $uri );
      }
    }
  
    return false;
  }

  function exists($file)
  {
    if($w3tcconfig = $this->_get_w3_config())
    {
      $engine = $w3tcconfig->get_string('cdn.engine');

      if($engine == 's3' or $engine == 'cf')
      {
        $bucket = $w3tcconfig->get_string('cdn.'.$engine.'.bucket');
        $uri = $this->_get_s3_uri( $file );

        $s3File = @$this->s3Obj->getObject( $bucket, $uri );

        return ($s3File !== false);
      }
    }
  
    // File doesn't exist in S3
    return false;
  }

  function is_file($file)
  {
    return $this->exists($file);
  }

  function is_dir($path)
  {
    // S3 doesn't really recognize directories but will auto
    // create them -- so if the path isn't a file then we'll
    // say it could be a directory -- if it doesn't exist then
    // when a file is uploaded to it -- it will.
    return !$this->is_file($path);
  }

  function is_readable($file)
  {
    $chmod = $this->getchmod($file);
    return $this->_get_s3_perms( $chmod );
  }

  function is_writable($file)
  {
    // We'll assume that any file is writable by
    // the owner of the current bucket setup in W3TC
    return $this->is_file($file);
  }

  function atime($file)
  {
    if($this->exists($file))
    {
      $info = $this->_get_info($file);
      if($info and is_array($info))
        return $info['time'];
      else
        return time();
    }

    return false;
  }

  function mtime($file)
  {
    if($this->exists($file))
    {
      $info = $this->_get_info($file);
      if($info and is_array($info))
        return $info['time'];
      else
        return time();
    }

    return false;
  }

  function size($file)
  {
    if($this->exists($file))
    {
      $info = $this->_get_info($file);
      if($info and is_array($info))
        return $info['size'];
      else
        return 0;
    }

    return false;
  }

  /** I don't think we can update the timestamp without reuploading */
  function touch($file, $time = 0, $atime = 0)
  {
    return true;
  }

  /** This only returns true because by the default in the S3 class the directories
    * are automatically created for a file given a path. For instance, if
    * I do a put_contents on a file path of 'cool-folder/wordup/g/dontmess.txt'
    * and assuming that none of the directories in this path exists then
    * S3 will create a 'cool-folder', 'wordup' and 'g' folder automatically.
    * Therefore -- to stay backwards compatible with a standard filesystem
    * we just return true here to indicate that the directory exists (even
    * if it does not) because as soon as a file is placed in the non-existent
    * folder then it will be created.
    */
  function mkdir($path, $chmod = false, $chown = false, $chgrp = false)
  {
    return true;
  }

  function rmdir($path, $recursive = false)
  {
    return true;
  }

  function dirlist($path, $include_hidden = true, $recursive = false)
  {
    if($w3tcconfig = $this->_get_w3_config())
    {
      $engine = $w3tcconfig->get_string('cdn.engine');
  
      if($engine == 's3' or $engine == 'cf')
        $bucket = $w3tcconfig->get_string("cdn.{$engine}.bucket");
    }
    
    return $bucket; // TODO: List the dir at some point -- for now just return the bucket name
  }

  /** Gets the W3TC Config file if W3TC is active & installed
    * @version Blair Williams (Caseproof, LLC)
    * @return boolean W3_Config Object or false
    */
  function _get_w3_config()
  {
    $w3tcconfig = & w3_instance('W3_Config');
    return $w3tcconfig;
  }
  
  /** Pulls a file from S3 and saves it in the working directory. */
  function _get_file($file)
  {
    if($w3tcconfig = $this->_get_w3_config())
    {
      $engine = $w3tcconfig->get_string('cdn.engine');
  
      if($engine == 's3' or $engine == 'cf')
      {
        $bucket = $w3tcconfig->get_string("cdn.{$engine}.bucket");
        $uri = $this->_get_s3_uri( $file );
  
        if(is_object($this->s3Obj))
        {
          $tmp_file = $this->cwd() . '/' . basename($file);
          $s3File = $this->s3Obj->getObject( $bucket, $uri, $tmp_file );

          return $s3File;
        }
      }
    }
  
    return false;
  }

  function _get_mime_type($file)
  {
    preg_replace('#\.([^\.]*?)$#', $file, $matches);
    $ext = $matches[1];

    if( isset($ext) and $ext and !empty($ext) )
    {
      $mime_array = array(
        "ez" => "application/andrew-inset",
        "hqx" => "application/mac-binhex40",
        "cpt" => "application/mac-compactpro",
        "doc" => "application/msword",
        "bin" => "application/octet-stream",
        "dms" => "application/octet-stream",
        "lha" => "application/octet-stream",
        "lzh" => "application/octet-stream",
        "exe" => "application/octet-stream",
        "class" => "application/octet-stream",
        "so" => "application/octet-stream",
        "dll" => "application/octet-stream",
        "oda" => "application/oda",
        "pdf" => "application/pdf",
        "ai" => "application/postscript",
        "eps" => "application/postscript",
        "ps" => "application/postscript",
        "smi" => "application/smil",
        "smil" => "application/smil",
        "wbxml" => "application/vnd.wap.wbxml",
        "wmlc" => "application/vnd.wap.wmlc",
        "wmlsc" => "application/vnd.wap.wmlscriptc",
        "bcpio" => "application/x-bcpio",
        "vcd" => "application/x-cdlink",
        "pgn" => "application/x-chess-pgn",
        "cpio" => "application/x-cpio",
        "csh" => "application/x-csh",
        "dcr" => "application/x-director",
        "dir" => "application/x-director",
        "dxr" => "application/x-director",
        "dvi" => "application/x-dvi",
        "spl" => "application/x-futuresplash",
        "gtar" => "application/x-gtar",
        "hdf" => "application/x-hdf",
        "js" => "application/x-javascript",
        "skp" => "application/x-koan",
        "skd" => "application/x-koan",
        "skt" => "application/x-koan",
        "skm" => "application/x-koan",
        "latex" => "application/x-latex",
        "nc" => "application/x-netcdf",
        "cdf" => "application/x-netcdf",
        "sh" => "application/x-sh",
        "shar" => "application/x-shar",
        "swf" => "application/x-shockwave-flash",
        "sit" => "application/x-stuffit",
        "sv4cpio" => "application/x-sv4cpio",
        "sv4crc" => "application/x-sv4crc",
        "tar" => "application/x-tar",
        "tcl" => "application/x-tcl",
        "tex" => "application/x-tex",
        "texinfo" => "application/x-texinfo",
        "texi" => "application/x-texinfo",
        "t" => "application/x-troff",
        "tr" => "application/x-troff",
        "roff" => "application/x-troff",
        "man" => "application/x-troff-man",
        "me" => "application/x-troff-me",
        "ms" => "application/x-troff-ms",
        "ustar" => "application/x-ustar",
        "src" => "application/x-wais-source",
        "xhtml" => "application/xhtml+xml",
        "xht" => "application/xhtml+xml",
        "zip" => "application/zip",
        "au" => "audio/basic",
        "snd" => "audio/basic",
        "mid" => "audio/midi",
        "midi" => "audio/midi",
        "kar" => "audio/midi",
        "mpga" => "audio/mpeg",
        "mp2" => "audio/mpeg",
        "mp3" => "audio/mpeg",
        "aif" => "audio/x-aiff",
        "aiff" => "audio/x-aiff",
        "aifc" => "audio/x-aiff",
        "m3u" => "audio/x-mpegurl",
        "ram" => "audio/x-pn-realaudio",
        "rm" => "audio/x-pn-realaudio",
        "rpm" => "audio/x-pn-realaudio-plugin",
        "ra" => "audio/x-realaudio",
        "wav" => "audio/x-wav",
        "pdb" => "chemical/x-pdb",
        "xyz" => "chemical/x-xyz",
        "bmp" => "image/bmp",
        "gif" => "image/gif",
        "ief" => "image/ief",
        "jpeg" => "image/jpeg",
        "jpg" => "image/jpeg",
        "jpe" => "image/jpeg",
        "png" => "image/png",
        "tiff" => "image/tiff",
        "tif" => "image/tif",
        "djvu" => "image/vnd.djvu",
        "djv" => "image/vnd.djvu",
        "wbmp" => "image/vnd.wap.wbmp",
        "ras" => "image/x-cmu-raster",
        "pnm" => "image/x-portable-anymap",
        "pbm" => "image/x-portable-bitmap",
        "pgm" => "image/x-portable-graymap",
        "ppm" => "image/x-portable-pixmap",
        "rgb" => "image/x-rgb",
        "xbm" => "image/x-xbitmap",
        "xpm" => "image/x-xpixmap",
        "xwd" => "image/x-windowdump",
        "igs" => "model/iges",
        "iges" => "model/iges",
        "msh" => "model/mesh",
        "mesh" => "model/mesh",
        "silo" => "model/mesh",
        "wrl" => "model/vrml",
        "vrml" => "model/vrml",
        "css" => "text/css",
        "html" => "text/html",
        "htm" => "text/html",
        "asc" => "text/plain",
        "txt" => "text/plain",
        "rtx" => "text/richtext",
        "rtf" => "text/rtf",
        "sgml" => "text/sgml",
        "sgm" => "text/sgml",
        "tsv" => "text/tab-seperated-values",
        "wml" => "text/vnd.wap.wml",
        "wmls" => "text/vnd.wap.wmlscript",
        "etx" => "text/x-setext",
        "xml" => "text/xml",
        "xsl" => "text/xml",
        "mpeg" => "video/mpeg",
        "mpg" => "video/mpeg",
        "mpe" => "video/mpeg",
        "qt" => "video/quicktime",
        "mov" => "video/quicktime",
        "mxu" => "video/vnd.mpegurl",
        "avi" => "video/x-msvideo",
        "movie" => "video/x-sgi-movie",
        "ice" => "x-conference-xcooltalk"
      );

      if(isset($mime_array[$ext]) and $mime_array[$ext] and !empty($mime_array[$ext]))
        return $mime_array[$ext];
    }

    return false;
  }

  /** Takes in a filesystem URI and converts it to an S3 URI. */
  function _get_s3_uri($file)
  {
    preg_match("#(wp-content.*$)#", $file, $matches);
    if( isset($matches[1]) and $matches[1] and !empty($matches[1]) )
      return $matches[1];
    else
      return $file;
  }

  /** Translates a unix filesystem mode to the amazon mode */
  function _get_s3_perms($mode = false)
  {
    if($mode and !($mode % 100))
      $amazon_perms = S3::ACL_PRIVATE;
    else if($mode and !($mode % 10))
      $amazon_perms = S3::ACL_PUBLIC_READ;
    else if($mode and ($mode % 10))
      $amazon_perms = S3::ACL_PUBLIC_READ_WRITE;
    else // Default to public readable
      $amazon_perms = S3::ACL_PUBLIC_READ;

    return $amazon_perms;
  }

  /** Translates an amazon mode into a unix filesystem mode */
  function _get_file_perms($amazon_perms)
  {
    if($amazon_perms == S3::ACL_PRIVATE)
      return 600;
    else if($amazon_perms == S3::ACL_PUBLIC_READ)
      return 644;
    else // S3::ACL_PUBLIC_READ_WRITE
      return 666;
  }

  /** Get Info from Amazon about file */
  function _get_info($remote_file)
  {
    if($w3tcconfig = $this->_get_w3_config())
    {
      $engine = $w3tcconfig->get_string('cdn.engine');
  
      if($engine == 's3' or $engine == 'cf')
      {
        $bucket = $w3tcconfig->get_string("cdn.{$engine}.bucket");

        if( is_object($this->s3Obj) )
           return $this->s3Obj->getObjectInfo($bucket, $remote_file);
      }

    }

    return false;
  }
}
?>
