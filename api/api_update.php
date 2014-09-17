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

class UpdateAPI extends WebAPI
{
  /**
   * Get updates description
   * @param bid the list of build ids
   */
  private function DescribeUpdates()
    {
    include_once('../cdash/common.php');

    if(!isset($this->Parameters['bid']))
      {
      return array('status'=>false, 'message'=>'You must specify a bid parameter.');
      }

    $q = "SELECT b.id AS bid,
                 bu.id AS uid,
                 bu.type AS vcs_type,
                 bu.command AS command,
                 bu.revision AS revision,
                 bu.priorrevision AS prior_revision,
                 bu.nfiles
          FROM build AS b, buildupdate AS bu, build2update AS b2u
          WHERE b.id = b2u.buildid AND b2u.updateid = bu.id
                AND (b.id = ".str_replace(';', " OR b.id = ", $this->Parameters['bid']).")
          ORDER BY bid";
    $query = pdo_query($q);
    while($query_array = pdo_fetch_array($query))
      {
      $update['build_id'] = $query_array['bid'];
      $update['vcs_type'] = $query_array['vcs_type'];
      $update['command'] = $query_array['command'];
      $update['revision'] = $query_array['revision'];
      $update['prior_revision'] = $query_array['prior_revision'];
      $update['n_upd_files'] = $query_array['nfiles'];

      $uid = $query_array['uid'];
      $q = "SELECT filename, revision, priorrevision FROM updatefile WHERE updateid = $uid";
      $files = array();
      $file_query = pdo_query($q);
      while($file_array = pdo_fetch_array($file_query))
        {
        $file['filename'] = $file_array['filename'];
        $file['revision'] = $file_array['revision'];
        $file['prior_revision'] = $file_array['priorrevision'];
        $files[] = $file;
        }
      $update['files'] = $files;

      $updates[] = $update;
      }

    return array('status' => true,
                 'updates' => $updates);
    } // end function DescribeUpdates

  /** Run function */
  function Run()
    {
    if(!isset($this->Parameters['task']))
      {
      return array('status'=>false, 'message'=>'Task should be set: task=...');
      }
    switch($this->Parameters['task'])
      {
      case 'describe': return $this->DescribeUpdates();
      default: return array('status'=>false, 'message'=>'Unknown task: '.$this->Parameters['task']);
      }
    }
}

?>
