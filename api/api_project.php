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

class ProjectAPI extends WebAPI
{
  /** Return the list of all public projects */
  private function ListProjects()
    {
    include_once('../cdash/common.php');

    $projects = array();
    $query = pdo_query("SELECT id,name FROM project WHERE public=1 ORDER BY name ASC");
    while($query_array = pdo_fetch_array($query))
      {
      $project['id'] = $query_array['id'];
      $project['name'] = $query_array['name'];
      $projects[] = $project;
      }
    return array('status'=>true, 'projects'=>$projects);
    } // end function ListProjects

  /**
   * Get projects description
   * @param project the list of project names
   * @param pid the list of project ids
   */
  private function DescribeProjects()
    {
    include_once('../cdash/common.php');
    include_once('../models/project.php');

    if(!isset($this->Parameters['project']) && !isset($this->Parameters['pid']))
      {
      return array('status'=>false, 'message'=>'You must specify a project or pid parameter.');
      }
    if(isset($this->Parameters['project']) && isset($this->Parameters['pid']))
      {
      return array('status'=>false, 'message'=>'Only one of the project and pid parameter can be specified.');
      }

    $ids = array();
    if(isset($this->Parameters['project']))
      {
      $projects = explode(';', $this->Parameters['project']);
      foreach($projects as $p)
        {
        $projectid = get_project_id($p);
        if(!is_numeric($projectid) || $projectid <= 0)
          {
          return array('status'=>false, 'message'=>"Project '$p' not found.");
          }
        $ids[] = $projectid;
        }
      }
    else
      {
      $ids = explode(';', $this->Parameters['pid']);
      }

    $projects = array();
    foreach($ids as $id)
      {
      $Project = new Project();
      $Project->Id = $id;
      $Project->Fill();

      $projects[] = array('id' => $id,
                          'name' => $Project->Name,
                          'description' => $Project->Description,
                          'home_url' => $Project->HomeUrl,
                          'vcs_url' => $Project->CvsUrl,
                          'bug_url' => $Project->BugTrackerUrl,
                          'doc_url' => $Project->DocumentationUrl);
      }
    return array('status' => true, 'projects' => $projects);
    } // end function DescribeProjects

  /**
   * Authenticate to the web API as a project admin
   * @param project the name of the project
   * @param pid the project id
   * @param key the web API key for that project
   */
  function Authenticate()
    {
    include_once('../cdash/common.php');
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
    if(!isset($this->Parameters['key']) || $this->Parameters['key'] == '')
      {
      return array('status'=>false, 'message'=>"You must specify a key parameter.");
      }

    $key = $this->Parameters['key'];
    $query = pdo_query("SELECT webapikey FROM project WHERE id=$projectid");
    if(pdo_num_rows($query) == 0)
      {
      return array('status'=>false, 'message'=>"Invalid project id.");
      }
    $row = pdo_fetch_array($query);
    $realKey = $row['webapikey'];

    if($key != $realKey)
      {
      return array('status'=>false, 'message'=>"Incorrect API key passed.");
      }
    $token = create_web_api_token($projectid);
    return array('status'=>true, 'token'=>$token);
    }

  /**
   * List all files for a given project
   * @param project the name of the project
   * @param key the web API key for that project
   * @param [match] regular expression that files must match
   * @param [mostrecent] include this if you only want the most recent match
   */
  function ListFiles()
    {
    include_once('../cdash/common.php');
    include_once('../models/project.php');

    global $CDASH_DOWNLOAD_RELATIVE_URL;

    if(!isset($this->Parameters['project']))
      {
      return array('status'=>false, 'message'=>'You must specify a project parameter.');
      }
    $projectid = get_project_id($this->Parameters['project']);
    if(!is_numeric($projectid) || $projectid <= 0)
      {
      return array('status'=>false, 'message'=>'Project not found.');
      }

    $Project = new Project();
    $Project->Id = $projectid;
    $files = $Project->GetUploadedFilesOrUrls();

    if($files === false)
      {
      return array('status'=>false, 'message'=>'Error in Project::GetUploadedFilesOrUrls');
      }
    $filteredList = array();
    foreach($files as $file)
      {
      if($file['isurl'])
        {
        continue; // skip if filename is a URL
        }
      if(isset($this->Parameters['match']) && !preg_match('/'.$this->Parameters['match'].'/', $file['filename']))
        {
        continue; //skip if it doesn't match regex
        }
      $filteredList[] = array_merge($file, array('url'=>$CDASH_DOWNLOAD_RELATIVE_URL.'/'.$file['sha1sum'].'/'.$file['filename']));

      if(isset($this->Parameters['mostrecent']))
        {
        break; //user requested only the most recent file
        }
      }

    return array('status'=>true, 'files'=>$filteredList);
    }

  /** Run function */
  function Run()
    {
    if(!isset($this->Parameters['task']))
      {
      return array('status'=>false, 'message'=>'Task should be set: task=...');
      }
    switch($this->Parameters['task'])
      {
      case 'list': return $this->ListProjects();
      case 'describe': return $this->DescribeProjects();
      case 'login': return $this->Authenticate();
      case 'files': return $this->ListFiles();
      default: return array('status'=>false, 'message'=>'Unknown task: '.$this->Parameters['task']);
      }
    }
}

?>
