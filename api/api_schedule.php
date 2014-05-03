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
  /** Get the user id from the user name */
  private function get_user_id($username)
  {
    require_once("cdash/common.php");
    require_once("cdash/pdo.php");

    $username = pdo_real_escape_string($username);
    $userid = pdo_get_field_value("SELECT id FROM ".qid("user")." WHERE email='$username'", 'id', '-1');
    return $userid;
  }

  /** Schedule a build
    * @param token the token obtained from the project API's login method. Pass as a POST parameter for security
    * @param project name of the project
    * @param pid id of the project
    * @param user user scheduling the build (default is administrator)
    * @param userid id of the user scheduling the build
    * @param type experimental, nighty, continuous (Experimental=0,Nightly=1,Continuous=2)
    * @param repository name of the repository
    * @param branch name of the repository branch
    * @param tag name of the repository tag
    * @param suffix suffix for the build
    * @param configuration configuration type for compilation (Debug=0,Release=1,RelWithDebInfo=2,MinSizeRel=3)
    * @param cmakeversion version of CMake to use
    * @param site specify that the build should run on one of the specified sites
    * @param sid specify that the build should run on one of the specified sites
    * @param osname name of the OS to run the build
    * @param osversion version of the OS to run the build
    * @param osbits number of bits of the target OS
    * @param compiler list of allowed target compilers (e.g. 'clang-3.2;clang-3.3;gcc')
    * @param library list of required libraries (e.g. 'qt-4.8;qca')
    * @param program list of program names (e.g. 'python')
    */
  private function ScheduleBuild()
    {
    include("../cdash/config.php");
    include_once('../cdash/common.php');
    include_once("../models/clientjobschedule.php");
    include_once("../models/clientos.php");
    include_once('../models/clientsite.php');
    include_once("../models/clientcmake.php");
    include_once("../models/clientcompiler.php");
    include_once("../models/clientlibrary.php");
    include_once("../models/project.php");

    if(!isset($this->Parameters['token']))
      {
      return array('status'=>false, 'message'=>'You must specify a token parameter.');
      }

    $clientJobSchedule = new ClientJobSchedule();
    $project = new Project();

    if(!isset($this->Parameters['project']) && !isset($this->Parameters['pid']))
      {
      return array('status'=>false, 'message'=>'You must specify a project or pid parameter.');
      }
    if(isset($this->Parameters['project']) && isset($this->Parameters['pid']))
      {
      return array('status'=>false, 'message'=>'Only one of the project and pid parameter can be specified.');
      }

    if(isset($this->Parameters['project']))
      {
      $projectid = get_project_id($this->Parameters['project']);
      }
    else
      {
      $projectid = $this->Parameters['pid'];
      }
    if(!is_numeric($projectid) || $projectid <= 0)
      {
      return array('status'=>false, 'message'=>'Project not found.');
      }
    $clientJobSchedule->ProjectId = $projectid;
    $project->Id = $projectid;

    // Perform the authentication (make sure user has project admin priviledges)
    if(!web_api_authenticate($projectid, $this->Parameters['token']))
      {
      return array('status'=>false, 'message'=>'Invalid API token.');
      }

    // We would need a user login/password at some point
    if(isset($this->Parameters['user']) && isset($this->Parameters['userid']))
      {
      return array('status'=>false, 'message'=>'Only one of the user and userid parameter can be specified.');
      }

    $userid = 1;
    if(isset($this->Parameters['user']))
      {
      $userid = $this->get_user_id($this->Parameters['user']);
      }
    if(isset($this->Parameters['userid']))
      {
      $userid = $this->Parameters['userid'];
      }
    if(!is_numeric($userid) || $userid <= 0)
      {
      return array('status'=>false, 'message'=>'User not found.');
      }

    $clientJobSchedule->UserId = $userid;

    // Experimental: 0
    // Nightly: 1
    // Continuous: 2
    $clientJobSchedule->Type = 0;
    if(isset($this->Parameters['type']))
      {
      $clientJobSchedule->Type = pdo_real_escape_string($this->Parameters['type']);
      }

    if(isset($this->Parameters['repository']))
      {
      $clientJobSchedule->Repository = pdo_real_escape_string($this->Parameters['repository']);
      }
    else
      {
      $repositories = $project->GetRepositories();
      if(count($repositories) < 1)
        {
        return array('status'=>false, 'message'=>'You must specify a repository parameter.');
        }
      $clientJobSchedule->Repository = $repositories[0]['url'];
      }

    if(isset($this->Parameters['branch']))
      {
      $clientJobSchedule->Module = pdo_real_escape_string($this->Parameters['branch']);
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

    // Set the site ids
    if(isset($this->Parameters['site']) && isset($this->Parameters['sid']))
      {
      return array('status'=>false, 'message'=>'Only one of the site and sid parameter can be specified.');
      }

    if(isset($this->Parameters['site']) || isset($this->Parameters['sid']))
      {
      $siteids = array();
      if(isset($this->Parameters['site']))
        {
        $Site = new ClientSite();
        $sites = explode(';', $this->Parameters['site']);
        foreach($sites as $s)
          {
          $siteid = $Site->GetId($s);
          if(!is_numeric($siteid) || $siteid <= 0)
            {
            return array('status'=>false, 'message'=>"Site '$s' not found.");
            }
          $siteids[] = $siteid;
          }
        }
      else
        {
        $siteids = explode(';', $this->Parameters['sid']);
        }

      foreach($siteids as $siteid)
        {
        $clientJobSchedule->AddSite($siteid);
        }
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

     if(isset($this->Parameters['compiler']))
       {
       $compilers = explode(';', $this->Parameters['compiler']);
       $ClientCompiler = new ClientCompiler();
       foreach($compilers as $compiler)
         {
         $c = explode('-', $compiler);
         $compilername = $c[0];
         $compilerversion = count($c) > 1 ? $c[1] : ''; // FIXME: what if count($c) > 2
         $compilerids = $ClientCompiler->GetCompiler($compilername,$compilerversion);
         foreach($compilerids as $compilerid)
           {
           $clientJobSchedule->AddCompiler($compilerid);
           }
         }
       }

    if(isset($this->Parameters['library']))
       {
       $libraries = explode(';', $this->Parameters['library']);
       $ClientLibrary = new ClientLibrary();
       foreach($libraries as $library)
         {
         $l = explode('-', $library);
         $libraryname = $l[0];
         $libraryversion = count($l) > 1 ? $l[1] : ''; // FIXME
         $libraryids = $ClientLibrary->GetLibrary($libraryname,$libraryversion);
         foreach($libraryids as $libraryid)
           {
           $clientJobSchedule->AddLibrary($libraryid);
           }
         }
       }

    // TODO: Handle $this->Parameters['program']

    return array('status'=>true, 'id'=>$clientJobSchedule->Id);
    } // end function ScheduleBuild

  /** Return the list of schedules
    * @param date list of the build dates
    * @param count limit of the build number
    * @param project list of the project names
    * @param pid list of project ids
    * @param site list of the site names
    * @param sid list of site ids
    * @param type list of the build types (nightly, experimental, continuous)
    * @param status list of schedule statuses (scheduled, running, finished, aborted, failed)
    */
  private function ListSchedules()
    {
    include_once('../cdash/common.php');
    include_once('../models/constants.php');

    $q = "SELECT js.lastrun, js.id, j.status, js.startdate, p.name
          FROM client_jobschedule AS js
            LEFT JOIN client_job AS j ON (j.scheduleid=js.id)
            LEFT JOIN project AS p ON (p.id=js.projectid)
            LEFT JOIN client_site AS s ON (s.id=j.siteid)
          WHERE TRUE ";

    if(isset($this->Parameters['project']))
      {
      $q .= " AND (p.name = '".str_replace(';', "' OR p.name = '", $this->Parameters['project'])."')";
      }

    if(isset($this->Parameters['pid']))
      {
      $q .= " AND (p.id = ".str_replace(';', " OR p.id = ", $this->Parameters['pid']).")";
      }

    if(isset($this->Parameters['site']))
      {
      $q .= " AND (s.name = '".str_replace(';', "' OR s.name = '", $this->Parameters['site'])."')";
      }

    if(isset($this->Parameters['sid']))
      {
      $q .= " AND (s.id = ".str_replace(';', " OR s.id = ", $this->Parameters['sid']).")";
      }

    if(isset($this->Parameters['type']))
      {
      $q .= " AND (";
      $first = true;
      $buildtypes = explode(';', $this->Parameters['type']);
      foreach($buildtypes AS $t)
        {
        switch($t)
          {
          case "Experimental": $buildtype = CDASH_JOB_EXPERIMENTAL; break;
          case "Nightly": $buildtype = CDASH_JOB_NIGHTLY; break;
          case "Continuous": $buildtype = CDASH_JOB_CONTINUOUS; break;
          default: return array('status'=>false, 'message'=>"Unknown build type: '$t'.");
          }
        if($first === false) $q .= " OR ";
        $q .= "js.type = '$buildtype'";
        $first = false;
        }
      $q .= ")";
      }

    if(isset($this->Parameters['status']))
      {
      $s = $this->Parameters['status'];
      switch($s)
        {
        case "scheduled": $status = CDASH_JOB_SCHEDULED; break;
        case "running": $status = CDASH_JOB_RUNNING; break;
        case "finished": $status = CDASH_JOB_FINISHED; break;
        case "aborted": $status = CDASH_JOB_ABORTED; break;
        case "failed": $status = CDASH_JOB_FAILED; break;
        default: return array('status'=>false, 'message'=>"Unknown schedule status: '$s'.");
        }
      $q .= " AND (j.status = $status";
      if($s == "scheduled")
        {
        $q .= " OR j.status IS NULL";
        }
      $q .= ")";
      }

    if(!isset($this->Parameters['date']) && !isset($this->Parameters['count']))
      {
      $q .= " ORDER BY js.startdate DESC LIMIT 10"; // return 10 last schedules by default
      }
    else
      {
      if(isset($this->Parameters['date']))
        {
        $q .= " AND (";
        $dates = explode(';', $this->Parameters['date']);
        for($i = 0; $i < count($dates); $i++)
          {
          $d = $dates[$i];
          if($i > 0)
            {
            $q .= " OR ";
            }
          if(strpos($d, ',') !== false)
            {
            $dd = explode(',', $d);
            if(count($dd) != 2)
              {
              return array('status'=>false, 'message'=>"Incorrect date interval specified: $d");
              }
            $q .= "('$dd[0]' <= js.startdate::date AND js.startdate::date <= '$dd[1]')";
            }
          else
            {
            $q .= "js.startdate::date = '$d'";
            }
          }
          $q .= ")";
        }
      $q .= " ORDER BY js.startdate DESC";
      if(isset($this->Parameters['count']))
        {
        $q .= " LIMIT ".$this->Parameters['count'];
        }
      }

    $schedules = array();
    $query = pdo_query($q);
    while($query_array = pdo_fetch_array($query))
      {
      $schedule['id'] = $query_array['id'];
      $schedule['project'] = $query_array['name'];
      $schedule['status'] =  "scheduled";
      switch($query_array['status'])
        {
        case CDASH_JOB_SCHEDULED: $schedule['status'] = "scheduled"; break;
        case CDASH_JOB_RUNNING: $schedule['status'] = "running"; break;
        case CDASH_JOB_FINISHED: $schedule['status'] = "finished"; break;
        case CDASH_JOB_ABORTED: $schedule['status'] = "aborted"; break;
        case CDASH_JOB_FAILED: $schedule['status'] = "failed"; break;
        }
      $schedules[] = $schedule;
      }
    return array('status'=>true, 'schedules'=>$schedules);
    } // end function ListSchedules

   /** Get information about specified schedules
    * @param schid the list of schedule ids
    */
   private function DescribeSchedules()
    {
    include_once('../cdash/common.php');
    include_once('../models/clientjob.php');
    include_once('../models/clientjobschedule.php');
    include_once('../models/project.php');

    if(!isset($this->Parameters['schid']))
      {
      return array('status'=>false, 'message'=>'You must specify a schedule id.');
      }
    $ids = explode(';', $this->Parameters['schid']);

    $schedules = array();
    foreach($ids as $scheduleid)
      {
      $clientJobSchedule = new ClientJobSchedule();
      $clientJobSchedule->Id = $scheduleid;

      $project = new Project();
      $project->Id = $clientJobSchedule->GetProjectId();
      $clientJobSchedule->ProjectId = $project->Id;

      $status = 'scheduled';
      switch($clientJobSchedule->GetStatus())
        {
        case CDASH_JOB_SCHEDULED: $status = "scheduled"; break;
        case CDASH_JOB_RUNNING: $status = "running"; break;
        case CDASH_JOB_FINISHED: $status = "finished"; break;
        case CDASH_JOB_ABORTED: $status = "aborted"; break;
        case CDASH_JOB_FAILED: $status = "failed"; break;
        }

      $clientJob = new ClientJob();
      $clientJob->Id = $clientJobSchedule->GetLastJobId();

      $startdate = $clientJob->GetStartDate();
      $startdate_array = date_parse($startdate);
      $enddate = $clientJob->GetEndDate();
      $enddate_array = date_parse($enddate);

      $schedules[] = array('id' => $scheduleid,
                           'status' => $status,
                           'project' => $project->GetName(),
                           'site' => $clientJobSchedule->GetSite(),
                           'year' => "".$startdate_array['year']."",
                           'month' => "".$startdate_array['month']."",
                           'day' => "".$startdate_array['day']."",
                           'time' => $startdate_array['hour'].':'.$startdate_array['minute'].':'.$startdate_array['second'],
                           'duration' => date_diff(date_create($startdate), date_create($enddate))->format("%h:%I:%S"),
                           'build_id' => $clientJobSchedule->GetAssociatedBuilds());
      }
    return array('status' => true, 'schedule' => $schedules);
    } // end function DescribeSchedules

  /** Run function */
  function Run()
    {
    if(!isset($this->Parameters['task']))
      {
      return array('status'=>false, 'message'=>'Task should be set: task=...');
      }
    switch($this->Parameters['task'])
      {
      case 'add': return $this->ScheduleBuild();
      case 'list': return $this->ListSchedules();
      case 'describe': return $this->DescribeSchedules();
      default: return array('status'=>false, 'message'=>'Unknown task: '.$this->Parameters['task']);
      }
    }
}

?>
