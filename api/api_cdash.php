<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

     This software is distributed WITHOUT ANY WARRANTY; without even
     the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
     PURPOSE.  See the above copyright notices for more information.

=========================================================================*/

// To be able to access files in this CDash installation regardless
// of getcwd() value:
//
$cdashpath = str_replace('\\', '/', dirname(dirname(__FILE__)));
set_include_path($cdashpath . PATH_SEPARATOR . get_include_path());

include_once('api.php');

class CDashAPI extends WebAPI
{
  /** Return version of CDash */
  private function ShowVersion()
    {
    require_once("../cdash/version.php");

    return array('status'=>true, 'version'=>$CDASH_VERSION);
    } // end function ShowVersion

  private function ShowSettings()
    {
    include("../cdash/config.php");

    return array('status'=>true,
                 'email_admin'=>$CDASH_EMAILADMIN,
                 'active_project_days'=>$CDASH_ACTIVE_PROJECT_DAYS,
                 'manage_clients'=>$CDASH_MANAGE_CLIENTS,
                 'large_text_limit'=>$CDASH_LARGE_TEXT_LIMIT);
    } // end function ShowSettings

  /** Run function */
  function Run()
    {
    if(!isset($this->Parameters['task']))
      {
      return array('status'=>false, 'message'=>'Task should be set: task=...');
      }
    switch($this->Parameters['task'])
      {
      case 'version': return $this->ShowVersion();
      case 'settings': return $this->ShowSettings();
      default: return array('status'=>false, 'message'=>'Unknown task: '.$this->Parameters['task']);
      }
    }
}

?>
