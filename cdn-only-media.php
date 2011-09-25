<?php
/*
Plugin Name: CDN Only Media
Plugin URI: http://www.blairwilliams.com/cdn-only
Description: Used in conjunction with W3 Total Cache's Amazon S3 & Cloudfront Configuration and any other plugins that utilize WordPress' native file handling API plugins.
Version: 0.0.01
Author: Caseproof, LLC
Author URI: http://blairwilliams.com

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

/** Currently works with W3 Total Cache's Amazon S3 & Cloudfront
  * Configuration to bypass the local filesystem for all media files.
  */
class CDNOnlyMedia
{
  /** Constructor initializes hooks for this class. */
  function __construct()
  {
    add_filter( 'wp_get_attachment_url', array('CDNOnlyMedia','modify_url'), 9, 2 );

    if( self::w3tc_installed() )
    {
      require_once( WP_PLUGIN_DIR . '/w3-total-cache/lib/W3/Config.php' );
      require_once( WP_PLUGIN_DIR . '/w3-total-cache/lib/S3.php' );

      add_filter('filesystem_method',      array('CDNOnlyMedia','get_filesystem_method'), 10, 2);
      add_filter('filesystem_method_file', array('CDNOnlyMedia','get_filesystem_method_file'), 10, 2);
    }
  }

  /** Modifies the Attachment URL to use the CDN Endpoint.
    * @param $url Original Attachment URL
    * @param $postID ID of the attachment
    * @return string The new URL
    */
  public static function modify_url($url, $postID=0)
  {
    $home_url = get_option('home');
    $s3_url   = self::get_domain();

    preg_match("#(/wp-content.*$)#", $url, $matches);
    $url_path = $matches[1];

    if($home_url != $s3_url)
      return self::get_domain() . $url_path;
  
    return $url;
  }

  /** Gets the replacement domain name
    * @custom Blair Williams (Caseproof, LLC)
    * @version Blair Williams (Caseproof, LLC)
    */
  public static function get_domain()
  {
    if($w3tcconfig = self::get_w3_config())
    {
      $engine = $w3tcconfig->get_string('cdn.engine');
  
      if($engine == 's3' or $engine == 'cf')
      {
        $cnames = $w3tcconfig->get_array("cdn.{$engine}.cname");
        if(isset($cnames[0]))
          return 'http://' . $cnames[0];
        else
          return 'https://' . $w3tcconfig->get_string("cdn.{$engine}.bucket") . '.s3.amazonaws.com';
      }
    }
  
    return get_option('home');
  }

  /** Replace domain name
    * @param $url Url to modify
    * @custom Blair Williams (Caseproof, LLC)
    * @version Blair Williams (Caseproof, LLC)
    */
  public static function replace_domain($url)
  {
    $home_url = get_option('home');
    $s3_url   = self::get_domain();

    if($home_url != $s3_url)
      return preg_replace('#^' . preg_quote($home_url, '#') . '#', $s3_url, $url);

    return $url;
  }

  function w3tc_installed()
  {
    if(!function_exists('is_plugin_active'))
      require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
  
    return is_plugin_active('w3-total-cache/w3-total-cache.php');
  }

  /** Check to see if we're using Amazon S3
    * @return true or false
    */
  function using_s3()
  {
    $w3tcconfig = self::get_w3_config();
    $engine = $w3tcconfig->get_string('cdn.engine');
    return ($engine == 's3' or $engine == 'cf');
  }

  /** Gets the W3TC Config file if W3TC is active & installed
    * @version Blair Williams (Caseproof, LLC)
    * @return boolean W3_Config Object or false
    */
  function get_w3_config()
  {
    $w3tcconfig = & w3_instance('W3_Config');
    return $w3tcconfig;
  }

  function get_filesystem_method( $method, $args )
  {
    if(self::using_s3())
      return 's3';

    return $method;
  }

  function get_filesystem_method_file( $file, $method )
  {
    if($method == 's3')
      return WP_PLUGIN_DIR . "/cdn-only-media/class-wp-filesystem-s3.php";
    
    return $file;
  }

}

// Fire this thing up
new CDNOnlyMedia();
?>
