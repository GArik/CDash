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
$cdashpath = str_replace('\\', '/', dirname(dirname(__FILE__)));
set_include_path($cdashpath . PATH_SEPARATOR . get_include_path());

include_once('api.php');

class RevisionAPI extends WebAPI
{
  /**
   * Get revisions description
   * @param project project name
   * @param pid project id
   * @param revision list of revisions
   */
  private function DescribeRevisions()
    {
    include_once('../cdash/common.php');

    if(!isset($this->Parameters['revision']))
      {
      return array('status'=>false, 'message'=>'You must specify a revision parameter.');
      }
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

    $q = "SELECT DISTINCT p.name AS project,
                 uf.revision AS revision,
                 uf.author AS author,
                 uf.email AS author_email,
                 uf.committer AS committer,
                 uf.committeremail AS committer_email,
                 uf.checkindate AS checkindate,
                 uf.log AS log
          FROM build AS b, build2update AS b2u,
               project AS p, updatefile AS uf
          WHERE b.id = b2u.buildid AND b2u.updateid = uf.updateid AND b.projectid = p.id
                AND (uf.revision = '".str_replace(';', "' OR uf.revision = '", $this->Parameters['revision'])."')
                AND p.id = $projectid";
    $query = pdo_query($q);
    while($query_array = pdo_fetch_array($query))
      {
      $checkindate = strtotime($query_array['checkindate']);

      $revisions[] = array('project' => $query_array['project'],
                           'revision' => $query_array['revision'],
                           'author' => $query_array['author'],
                           'author_email' => $query_array['author_email'],
                           'committer' => $query_array['committer'],
                           'committer_email' => $query_array['committer_email'],
                           'year' => date("Y", $checkindate),
                           'month' => date("n", $checkindate),
                           'day' => date("j", $checkindate),
                           'time' => date("H:i:s", $checkindate),
                           'log' => $query_array['log']);
      }

    return array('status' => true,
                 'revisions' => $revisions);
    } // end function DescribeRevisions

  /** Run function */
  function Run()
    {
    if(!isset($this->Parameters['task']))
      {
      return array('status'=>false, 'message'=>'Task should be set: task=...');
      }
    switch($this->Parameters['task'])
      {
      case 'describe': return $this->DescribeRevisions();
      default: return array('status'=>false, 'message'=>'Unknown task: '.$this->Parameters['task']);
      }
    }
}

?>
