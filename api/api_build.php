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

class BuildAPI extends WebAPI
{
  /** Return the list of builds
    * @param date list of the build dates
    * @param count limit of the build number
    * @param project list of the project names
    * @param pid list of project ids
    * @param site list of the site names
    * @param sid list of site ids
    * @param type list of the build types (nightly, experimental, continuous) */
  private function ListBuilds()
    {
    include_once('../cdash/common.php');

    // FIXME: Ensure that site name and type are correct
    $q = "SELECT build.id AS id, build.name AS name, site.name AS site,
                 project.name AS project, build.type AS type,
                 extract(YEAR FROM build.submittime) AS y,
                 extract(MONTH FROM build.submittime) AS m,
                 extract(DAY FROM build.submittime) AS d,
                 build.submittime::time AS t
          FROM build, site, project
          WHERE build.siteid = site.id AND build.projectid = project.id";

    if(isset($this->Parameters['type']))
      {
      $q .= " AND (type = '".str_replace(';', "' OR type = '", $this->Parameters['type'])."')";
      }

    if(isset($this->Parameters['project']))
      {
      /*$projects = explode(';', $this->Parameters['project']);
      foreach($projects as $p)
        {
        $projectid = get_project_id($p);
        if(!is_numeric($projectid) || $projectid <= 0)
          {
          return array('status'=>false, 'message'=>"Project '$p' not found.");
          }
        $ids[] = $projectid;
        }
      $q .= " AND (project.id = ".implode(" OR project.id = ", $ids).")";*/
      $q .= " AND (project.name = '".str_replace(';', "' OR project.name = '", $this->Parameters['project'])."')";
      }

    if(isset($this->Parameters['pid']))
      {
      $q .= " AND (project.id = ".str_replace(';', " OR project.id = ", $this->Parameters['pid']).")";
      }

    if(isset($this->Parameters['site']))
      {
      $q .= " AND (site.name = '".str_replace(';', "' OR site.name = '", $this->Parameters['site'])."')";
      }

    if(isset($this->Parameters['sid']))
      {
      $q .= " AND (site.id = ".str_replace(';', " OR site.id = ", $this->Parameters['sid']).")";
      }

    if(!isset($this->Parameters['date']) && !isset($this->Parameters['count']))
      {
      $q .= " ORDER BY submittime DESC LIMIT 10"; // return 10 last builds by default
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
            $q .= "('$dd[0]' <= submittime::date AND submittime::date <= '$dd[1]')";
            }
          else
            {
            $q .= "submittime::date = '$d'";
            }
          }
          $q .= ")";
        }
      if(isset($this->Parameters['count']))
        {
        $q .= " ORDER BY submittime DESC LIMIT ".$this->Parameters['count'];
        }
      }

    $builds = array();
    $query = pdo_query($q);
    while($query_array = pdo_fetch_array($query))
      {
      $build['id'] = $query_array['id'];
      $build['name'] = $query_array['name'];
      $build['site'] = $query_array['site'];
      $build['project'] = $query_array['project'];
      $build['type'] = $query_array['type'];
      $build['year'] = $query_array['y'];
      $build['month'] = $query_array['m'];
      $build['day'] = $query_array['d'];
      $build['time'] = $query_array['t'];
      $builds[] = $build;
      }
    return array('status'=>true, 'builds'=>$builds);
    } // end function ListBuilds

  /**
   * Get build description
   * @param bid the list of build ids
   */
  private function DescribeBuilds()
    {
    include_once('../cdash/common.php');

    if(!isset($this->Parameters['bid']))
      {
      return array('status'=>false, 'message'=>'You must specify the bid parameter.');
      }
    $ids = explode(';', $this->Parameters['bid']);
    /*$id = $this->Parameters['bid'];
    if(!is_numeric($id) || $id <= 0)
      {
      return array('status'=>false, 'message'=>'Incorrect id.');
      }*/

    $builds = array();
    $q = "SELECT bu.nfiles,
                 bu.starttime AS updatestarttime,
                 bu.endtime AS updateendtime,
                 c.starttime AS configurestarttime,
                 c.endtime AS configureendtime,
                 btt.time AS testsduration,
                 b.id, b.name, b.type,
                 b.starttime, b.endtime, b.submittime,
                 b.builderrors AS countbuilderrors,
                 b.buildwarnings AS countbuildwarnings,
                 b.testnotrun AS counttestsnotrun,
                 b.testfailed AS counttestsfailed,
                 b.testpassed AS counttestspassed,
                 cov.loctested, cov.locuntested,
                 s.name AS sitename,
                 p.name AS projectname
                 FROM site AS s, project AS p, build AS b
                   LEFT JOIN build2update AS b2u ON (b2u.buildid=b.id)
                   LEFT JOIN buildupdate AS bu ON (b2u.updateid=bu.id)
                   LEFT JOIN configure AS c ON (c.buildid=b.id)
                   LEFT JOIN buildtesttime AS btt ON (btt.buildid=b.id)
                   LEFT JOIN coveragesummary AS cov ON (cov.buildid=b.id)
                 WHERE s.id = b.siteid AND p.id = b.projectid
                   AND (b.id = ".str_replace(';', " OR b.id = ", $this->Parameters['bid']).")
                   ORDER BY id";
    $query = pdo_query($q);
    while($query_array = pdo_fetch_array($query))
      {
      $id = $query_array['id'];
      $name = $query_array['name'];
      $type = $query_array['type'];
      $site = $query_array['sitename'];
      $project = $query_array['projectname'];

      $n_upd_files = 0;
      if(!empty($query_array['nfiles']))
        {
        $n_upd_files = $query_array['nfiles'];
        }

      $submittime = strtotime($query_array['submittime']);
      $cfg_duration = round((strtotime($query_array['configureendtime'])-strtotime($query_array['configurestarttime']))/60,1);
      $build_duration = round((strtotime($query_array['endtime'])-strtotime($query_array['starttime']))/60,1);
      $test_duration = !empty($query_array['testsduration']) ? round($query_array['testsduration'], 1) : 0.0; // already in minutes

      $n_warnings = $query_array['countbuildwarnings'] > 0 ? $query_array['countbuildwarnings'] : 0;
      $n_errors = $query_array['countbuilderrors'] > 0 ? $query_array['countbuilderrors'] : 0;

      $n_test_pass = $query_array['counttestspassed'] > 0 ? $query_array['counttestspassed'] : 0;
      $n_test_fail = $query_array['counttestsfailed'] > 0 ? $query_array['counttestsfailed'] : 0;
      $n_test_not_run = $query_array['counttestsnotrun'] > 0 ? $query_array['counttestsnotrun'] : 0;

      $n_loc_covered = $query_array['loctested'] > 0 ? $query_array['loctested'] : 0;
      $n_loc_uncovered = $query_array['locuntested'] > 0 ? $query_array['locuntested'] : 0;

      $builds[] = array('id' => $id,
                        'name' => $name,
                        'site' => $site,
                        'project' => $project,
                        'type' => $type,
                        'year' => date("Y", $submittime),
                        'month' => date("n", $submittime),
                        'day' => date("j", $submittime),
                        'time' => date("H:i:s", $submittime),
                        'n_upd_files' => $n_upd_files,
                        'cfg_duration' => $cfg_duration,
                        'build_duration' => $build_duration,
                        'test_duration' => $test_duration,
                        'n_warnings' => $n_warnings,
                        'n_errors' => $n_errors,
                        'n_test_pass' => $n_test_pass,
                        'n_test_fail' => $n_test_fail,
                        'n_test_not_run' => $n_test_not_run,
                        'n_loc_covered' => $n_loc_covered,
                        'n_loc_uncovered' => $n_loc_uncovered);
      }
    return array('status' => true, 'builds' => $builds);
    } // end function DescribeBuilds

  /** Return the defects: builderrors, buildwarnings, testnotrun, testfailed. */
  private function ListDefects()
    {
    include_once('../cdash/common.php');
    include("../cdash/config.php");

    if(!isset($this->Parameters['project']))
      {
      echo "Project not set";
      return;
      }

    $projectid = get_project_id($this->Parameters['project']);
    if(!is_numeric($projectid) || $projectid <= 0)
      {
      echo "Project not found";
      return;
      }

    $builds = array();


    if($CDASH_DB_TYPE == "pgsql")
      {
      $query = pdo_query("SELECT EXTRACT(YEAR FROM starttime) AS y ,
                              EXTRACT(MONTH FROM starttime) AS m,
                              EXTRACT(DAY FROM starttime) AS d,
                  AVG(builderrors) AS builderrors,AVG(buildwarnings) AS buildwarnings,
                  AVG(testnotrun) AS testnotrun,AVG(testfailed) AS testfailed
                  FROM build WHERE projectid=".$projectid."
                  AND starttime<NOW()
                  GROUP BY y,m,d
                  ORDER BY y,m,d ASC LIMIT 1000"); // limit the request
      }
    else
      {
      $query = pdo_query("SELECT YEAR(starttime) AS y ,MONTH(starttime) AS m,DAY(starttime) AS d,
                  AVG(builderrors) AS builderrors,AVG(buildwarnings) AS buildwarnings,
                  AVG(testnotrun) AS testnotrun,AVG(testfailed) AS testfailed
                  FROM build WHERE projectid=".$projectid."
                  AND starttime<NOW()
                  GROUP BY YEAR(starttime),MONTH(starttime),DAY(starttime)
                  ORDER BY YEAR(starttime),MONTH(starttime),DAY(starttime) ASC LIMIT 1000"); // limit the request
      }

    echo pdo_error();

    while($query_array = pdo_fetch_array($query))
      {
      $build['month'] = $query_array['m'];
      $build['day'] = $query_array['d'];
      $build['year'] = $query_array['y'];
      $build['time'] = strtotime($query_array['y'].'-'.$query_array['m'].'-'.$query_array['d']);

      $build['builderrors'] = 0;
      if($query_array['builderrors']>=0)
        {
        $build['builderrors'] = $query_array['builderrors'];
        }
      $build['buildwarnings'] = 0;
      if($query_array['buildwarnings']>=0)
        {
        $build['buildwarnings'] = $query_array['buildwarnings'];
        }
      $build['testnotrun'] = 0;
      if($query_array['testnotrun']>=0)
        {
        $build['testnotrun'] = $query_array['testnotrun'];
        }
      $build['testfailed'] = 0;
      if($query_array['testfailed']>=0)
        {
        $build['testfailed'] = $query_array['testfailed'];
        }
      $builds[] = $build;
      }
    return $builds;
    } // end function ListDefects

  /** Return the defects: builderrors, buildwarnings, testnotrun, testfailed. */
  private function RevisionStatus()
    {
    include_once('../cdash/common.php');
    include("../cdash/config.php");

    if(!isset($this->Parameters['project']))
      {
      echo "Project not set";
      return;
      }

    if(!isset($this->Parameters['revision']))
      {
      echo "revision not set";
      return;
      }
 
    $revision = trim($this->Parameters['revision']);
    
    $projectid = get_project_id($this->Parameters['project']);
    if(!is_numeric($projectid) || $projectid <= 0)
      {
      echo "Project not found";
      return;
      }

    $builds = array();

    // Finds all the buildid 
    $query = pdo_query("SELECT b.name,b.id, b.starttime,b.endtime,b.submittime,b.builderrors,b.buildwarnings,b.testnotrun,b.testfailed,b.testpassed
                        FROM build AS b, buildupdate AS bu, build2update AS b2u WHERE
                        b2u.buildid=b.id AND b2u.updateid=bu.id AND 
                        bu.revision='".$revision."' AND b.projectid='".$projectid."'   "); // limit the request

    echo pdo_error();
      
    while($query_array = pdo_fetch_array($query))
      {
      $build['id'] = $query_array['id'];
      $build['name'] = $query_array['name'];
      $build['starttime'] = $query_array['starttime'];
      $build['endtime'] = $query_array['endtime'];
      $build['submittime'] = $query_array['submittime'];
      
      // Finds the osname
      $infoquery = pdo_query("SELECT osname FROM buildinformation WHERE buildid='".$build['id']."'");  
      if(pdo_num_rows($infoquery)>0)
        {
        $query_infoarray = pdo_fetch_array($infoquery);
        $build['os'] = $query_infoarray['osname'];
        }
      
      // Finds the configuration errors
      $configquery = pdo_query("SELECT count(*) AS c FROM configureerror WHERE buildid='".$build['id']."' AND type='0'");  
      $query_configarray = pdo_fetch_array($configquery);
      $build['configureerrors'] = $query_configarray['c'];
      
      $configquery = pdo_query("SELECT count(*) AS c FROM configureerror WHERE buildid='".$build['id']."' AND type='1'");  
      $query_configarray = pdo_fetch_array($configquery);
      $build['configurewarnings'] = $query_configarray['c'];
      
      $coveragequery = pdo_query("SELECT loctested,locuntested FROM coveragesummary WHERE buildid='".$build['id']."'");  
      if($coveragequery)
        {
        $coveragequery_configarray = pdo_fetch_array($coveragequery);
        $build['loctested'] = $coveragequery_configarray['loctested'];
        $build['locuntested'] = $coveragequery_configarray['locuntested'];
        }

      $build['builderrors'] = 0;
      if($query_array['builderrors']>=0)
        {
        $build['builderrors'] = $query_array['builderrors'];
        }
      $build['buildwarnings'] = 0;
      if($query_array['buildwarnings']>=0)
        {
        $build['buildwarnings'] = $query_array['buildwarnings'];
        }
      $build['testnotrun'] = 0;
      if($query_array['testnotrun']>=0)
        {
        $build['testnotrun'] = $query_array['testnotrun'];
        }
      $build['testpassed'] = 0;
      if($query_array['testpassed']>=0)
        {
        $build['testpassed'] = $query_array['testpassed'];
        }
      $build['testfailed'] = 0;
      if($query_array['testfailed']>=0)
        {
        $build['testfailed'] = $query_array['testfailed'];
        }
      $builds[] = $build;
      }
    return $builds;
    } // end function ListDefects
    
    
  /** Return the number of defects per number of checkins */
  private function ListCheckinsDefects()
    {
    include_once('../cdash/common.php');
    if(!isset($this->Parameters['project']))
      {
      echo "Project not set";
      return;
      }

    $projectid = get_project_id($this->Parameters['project']);
    if(!is_numeric($projectid) || $projectid <= 0)
      {
      echo "Project not found";
      return;
      }

    $builds = array();
    $query = pdo_query("SELECT nfiles, builderrors, buildwarnings, testnotrun, testfailed
                FROM build,buildupdate,build2update WHERE build.projectid=".$projectid."
                AND buildupdate.id=build2update.updateid
                AND build2update.buildid=build.id
                AND nfiles>0
                AND build.starttime<NOW()
                ORDER BY build.starttime DESC LIMIT 1000"); // limit the request
    echo pdo_error();

    while($query_array = pdo_fetch_array($query))
      {
      $build['nfiles'] = $query_array['nfiles'];
      $build['builderrors'] = 0;
      if($query_array['builderrors']>=0)
        {
        $build['builderrors'] = $query_array['builderrors'];
        }
      $build['buildwarnings'] = 0;
      if($query_array['buildwarnings']>=0)
        {
        $build['buildwarnings'] = $query_array['buildwarnings'];
        }
      $build['testnotrun'] = 0;
      if($query_array['testnotrun']>=0)
        {
        $build['testnotrun'] = $query_array['testnotrun'];
        }
      $build['testfailed'] = 0;
      if($query_array['testfailed']>=0)
        {
        $build['testfailed'] = $query_array['testfailed'];
        }
      $builds[] = $build;
      }
    return $builds;
    } // end function ListCheckinsDefects


  /** Return an array with two sub arrays:
   *  array1: id, buildname, os, bits, memory, frequency
   *  array2: array1_id, test_fullname */
  private function ListSiteTestFailure()
    {
    include("../cdash/config.php");
    include_once('../cdash/common.php');


    if(!isset($this->Parameters['project']))
      {
      echo "Project not set";
      return;
      }

    $projectid = get_project_id($this->Parameters['project']);
    if(!is_numeric($projectid) || $projectid <= 0)
      {
      echo "Project not found";
      return;
      }

    $group = 'Nightly';
    if(isset($this->Parameters['group']))
      {
      $group = pdo_real_escape_string($this->Parameters['group']);
      }

    // Get first all the unique builds for today's dashboard and group
    $query = pdo_query("SELECT nightlytime FROM project WHERE id=".qnum($projectid));
    $project_array = pdo_fetch_array($query);

    $date = date("Y-m-d");
    list ($previousdate, $currentstarttime, $nextdate) = get_dates($date,$project_array["nightlytime"]);
    $currentUTCTime =  date(FMT_DATETIME,$currentstarttime);

    // Get all the unique builds for the section of the dashboard
    if($CDASH_DB_TYPE == "pgsql")
      {
      $query = pdo_query("SELECT max(b.id) AS buildid,s.name || '-' || b.name AS fullname,s.name AS sitename,b.name,
               si.totalphysicalmemory,si.processorclockfrequency
               FROM build AS b, site AS s, siteinformation AS si, buildgroup AS bg, build2group AS b2g
               WHERE b.projectid=".$projectid." AND b.siteid=s.id AND si.siteid=s.id
               AND bg.name='".$group."' AND b.testfailed>0 AND b2g.buildid=b.id AND b2g.groupid=bg.id
               AND b.starttime>'$currentUTCTime' AND b.starttime<NOW() GROUP BY fullname,
               s.name,b.name,si.totalphysicalmemory,si.processorclockfrequency
               ORDER BY buildid");
      }
    else
      {
      $query = pdo_query("SELECT max(b.id) AS buildid,CONCAT(s.name,'-',b.name) AS fullname,s.name AS sitename,b.name,
               si.totalphysicalmemory,si.processorclockfrequency
               FROM build AS b, site AS s, siteinformation AS si, buildgroup AS bg, build2group AS b2g
               WHERE b.projectid=".$projectid." AND b.siteid=s.id AND si.siteid=s.id
               AND bg.name='".$group."' AND b.testfailed>0 AND b2g.buildid=b.id AND b2g.groupid=bg.id
               AND b.starttime>'$currentUTCTime' AND b.starttime<UTC_TIMESTAMP() GROUP BY fullname ORDER BY buildid");
      }
    $sites = array();
    $buildids = '';
    while($query_array = pdo_fetch_array($query))
      {
      if($buildids != '')
        {
        $buildids.=",";
        }
      $buildids .= $query_array['buildid'];
      $site = array();
      $site['name'] = $query_array['sitename'];
      $site['buildname'] = $query_array['name'];
      $site['cpu'] = $query_array['processorclockfrequency'];
      $site['memory'] = $query_array['totalphysicalmemory'];
      $sites[$query_array['buildid']] = $site;
      }

    if(empty($sites))
      {
      return $sites;
      }

    $query = pdo_query("SELECT bt.buildid AS buildid,t.name AS testname,t.id AS testid
              FROM build2test AS bt,test as t
              WHERE bt.buildid IN (".$buildids.") AND bt.testid=t.id AND bt.status='failed'");

    $tests = array();

    while($query_array = pdo_fetch_array($query))
      {
      $test = array();
      $test['id'] = $query_array['testid'];
      $test['name'] = $query_array['testname'];
      $sites[$query_array['buildid']]['tests'][] = $test;
      }

    return $sites;
    } // end function ListCheckinsDefects

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
    } // end function ScheduleBuild

  /** Run function */
  function Run()
    {
    if(!isset($this->Parameters['task']))
      {
      return array('status'=>false, 'message'=>'Task should be set: task=...');
      }
    switch($this->Parameters['task'])
      {
      case 'list': return $this->ListBuilds();
      case 'describe': return $this->DescribeBuilds();
      case 'defects': return $this->ListDefects();
      case 'revisionstatus': return $this->RevisionStatus();
      case 'checkinsdefects': return $this->ListCheckinsDefects();
      case 'sitetestfailures': return $this->ListSiteTestFailure();
      case 'schedule': return $this->ScheduleBuild();
      case 'schedulestatus': return $this->ScheduleStatus();
      default: return array('status'=>false, 'message'=>'Unknown task: '.$this->Parameters['task']);
      }
    }
}

?>
