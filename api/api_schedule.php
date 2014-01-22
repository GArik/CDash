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

class ScheduleAPI extends WebAPI
{
  /** Schedule a build */
  private function ScheduleBuild()
    {
    include("../cdash/config.php");
    include_once('../cdash/common.php');
    include_once("../models/clientjobschedule.php");
    include_once("../models/clientos.php");
    include_once("../models/clientcmake.php");
    include_once("../models/clientcompiler.php");
    include_once("../models/clientlibrary.php");

    if(!isset($this->Parameters['token']))
      {
      return array('status'=>false, 'message'=>'You must specify a token parameter.');
      }

    $clientJobSchedule = new ClientJobSchedule();

    $status = array();
    $status['scheduled'] = 0;
    if(!isset($this->Parameters['project']))
      {
      return array('status'=>false, 'message'=>'You must specify a project parameter.');
      }

    $projectid = get_project_id($this->Parameters['project']);
    if(!is_numeric($projectid) || $projectid<=0)
      {
      return array('status'=>false, 'message'=>'Project not found.');
      }
    $clientJobSchedule->ProjectId = $projectid;

    // Perform the authentication (make sure user has project admin priviledges)
    if(!web_api_authenticate($projectid, $this->Parameters['token']))
      {
      return array('status'=>false, 'message'=>'Invalid API token.');
      }

    // We would need a user login/password at some point
    $clientJobSchedule->UserId = '1';
    if(isset($this->Parameters['userid']))
      {
      $clientJobSchedule->UserId = pdo_real_escape_string($this->Parameters['userid']);
      }

    // Experimental: 0
    // Nightly: 1
    // Continuous: 2
    $clientJobSchedule->Type = 0;
    if(isset($this->Parameters['type']))
      {
      $clientJobSchedule->Type = pdo_real_escape_string($this->Parameters['type']);
      }

    if(!isset($this->Parameters['repository']))
      {
      return array('status'=>false, 'message'=>'You must specify a repository parameter.');
      }

    $clientJobSchedule->Repository = pdo_real_escape_string($this->Parameters['repository']);

    if(isset($this->Parameters['module']))
      {
      $clientJobSchedule->Module = pdo_real_escape_string($this->Parameters['module']);
      }

    if(isset($this->Parameters['tag']))
      {
      $clientJobSchedule->Tag = pdo_real_escape_string($this->Parameters['tag']);
      }

    if(isset($this->Parameters['suffix']))
      {
      $clientJobSchedule->BuildNameSuffix = pdo_real_escape_string($this->Parameters['suffix']);
      }

    // Build Configuration
    // Debug: 0
    // Release: 1
    // RelWithDebInfo: 2
    // MinSizeRel: 3
    $clientJobSchedule->BuildConfiguration = 0;
    if(isset($this->Parameters['configuration']))
      {
      $clientJobSchedule->BuildConfiguration = pdo_real_escape_string($this->Parameters['configuration']);
      }

    $clientJobSchedule->StartDate = date("Y-m-d H:i:s");
    $clientJobSchedule->StartTime = date("Y-m-d H:i:s");
    $clientJobSchedule->EndDate = '1980-01-01 00:00:00';
    $clientJobSchedule->RepeatTime = 0; // No repeat
    $clientJobSchedule->Enable = 1;
    $clientJobSchedule->Save();

    // Remove everything and add them back in
    $clientJobSchedule->RemoveDependencies();

    // Set CMake
    if(isset($this->Parameters['cmakeversion']))
      {
      $cmakeversion = pdo_real_escape_string($this->Parameters['cmakeversion']);
      $ClientCMake  = new ClientCMake();
      $ClientCMake->Version = $cmakeversion;
      $cmakeid = $ClientCMake->GetIdFromVersion();
      if(!empty($cmakeid))
        {
        $clientJobSchedule->AddCMake($cmakeid);
        }
      }

    // Set the site id (for now only one)
    if(isset($this->Parameters['siteid']))
      {
      $siteid = pdo_real_escape_string($this->Parameters['siteid']);
      $clientJobSchedule->AddSite($siteid);
      }

    if(isset($this->Parameters['osname'])
       || isset($this->Parameters['osversion'])
       || isset($this->Parameters['osbits'])
       )
      {
      $ClientOS  = new ClientOS();
      $osname = '';
      $osversion = '';
      $osbits = '';
      if(isset($this->Parameters['osname'])) {$osname = $this->Parameters['osname'];}
      if(isset($this->Parameters['osversion'])) {$osversion = $this->Parameters['osversion'];}
      if(isset($this->Parameters['osbits'])) {$osbits = $this->Parameters['osbits'];}
      $osids = $ClientOS->GetOS($osname,$osversion,$osbits);

      foreach($osids as $osid)
        {
        $clientJobSchedule->AddOS($osid);
        }
      }

     if(isset($this->Parameters['compilername'])
       || isset($this->Parameters['compilerversion']))
       {
       $ClientCompiler  = new ClientCompiler();
       $compilername = '';
       $compilerversion = '';
       if(isset($this->Parameters['compilername'])) {$compilername = $this->Parameters['compilername'];}
       if(isset($this->Parameters['compilerversion'])) {$compilerversion = $this->Parameters['compilerversion'];}
       $compilerids = $ClientCompiler->GetCompiler($compilername,$compilerversion);
       foreach($compilerids as $compilerid)
         {
         $clientJobSchedule->AddCompiler($compilerid);
         }
       }

    if(isset($this->Parameters['libraryname'])
       || isset($this->Parameters['libraryversion']))
       {
       $ClientLibrary  = new ClientLibrary();
       $libraryname = '';
       $libraryversion = '';
       if(isset($this->Parameters['libraryname'])) {$libraryname = $this->Parameters['libraryname'];}
       if(isset($this->Parameters['libraryversion'])) {$libraryversion = $this->Parameters['libraryversion'];}
       $libraryids = $ClientLibrary->GetLibrary($libraryname,$libraryversion);
       foreach($libraryids as $libraryid)
         {
         $clientJobSchedule->AddLibrary($libraryid);
         }
       }

    $status['scheduleid'] = $clientJobSchedule->Id;
    $status['scheduled'] = 1;
    $status['status'] = true;
    return $status;
    } // end function ScheduleBuild

   /** Return the status of a scheduled build */
   private function ScheduleStatus()
    {
    include("../cdash/config.php");
    include_once('../cdash/common.php');
    include_once("../models/clientjobschedule.php");
    include_once("../models/clientos.php");
    include_once("../models/clientcmake.php");
    include_once("../models/clientcompiler.php");
    include_once("../models/clientlibrary.php");

    $status = array();
    $status['scheduled'] = 0;
    if(!isset($this->Parameters['project']))
      {
      echo "Project name should be set";
      return;
      }

    $projectid = get_project_id($this->Parameters['project']);
    if(!is_numeric($projectid) || $projectid<=0)
      {
      echo "Project not found";
      return;
      }

    $scheduleid = $this->Parameters['scheduleid'];
    if(!is_numeric($scheduleid) || $scheduleid<=0)
      {
      echo "ScheduleId not set";
      return;
      }

    $clientJobSchedule = new ClientJobSchedule();
    $clientJobSchedule->Id = $scheduleid;
    $clientJobSchedule->ProjectId = $projectid;

    $status['status'] = $clientJobSchedule->GetStatus();
    switch($status['status'])
      {
      case -1: $status['statusstring'] = "not found"; break;
      case 0: $status['statusstring'] = "scheduled"; break;
      case 2: $status['statusstring'] = "running"; break;
      case 3: $status['statusstring'] = "finished"; break;
      case 4: $status['statusstring'] = "aborted"; break;
      case 5: $status['statusstring'] = "failed"; break;
      }

    $status['scheduleid'] = $clientJobSchedule->Id;
    $status['builds'] = $clientJobSchedule->GetAssociatedBuilds();
    $status['scheduled'] = 0;
    if($status['status']>0)
      {
      $status['scheduled'] = 1;
      }
    return $status;
    } // end function ScheduleStatus

  /** Run function */
  function Run()
    {
    if(!isset($this->Parameters['task']))
      {
      return array('status'=>false, 'message'=>'Task should be set: task=...');
      }
    switch($this->Parameters['task'])
      {
      case 'schedule': return $this->ScheduleBuild();
      case 'schedulestatus': return $this->ScheduleStatus();
      default: return array('status'=>false, 'message'=>'Unknown task: '.$this->Parameters['task']);
      }
    }
}

?>
