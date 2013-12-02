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

class SiteAPI extends WebAPI
{
  /** Return the list of all client sites */
  function ListSites()
    {
    include_once('../cdash/common.php');
    $query = pdo_query("SELECT id, name FROM client_site ORDER BY name ASC");
    while($query_array = pdo_fetch_array($query))
      {
      $site['id'] = $query_array['id'];
      $site['name'] = $query_array['name'];
      $sites[] = $site;
      }
    return array('status'=>true, 'sites'=>$sites);
    } // end function ListSites

  /**
   * Get client site description
   * @param site the list of client sites
   * @param sid the list of client site ids
   */
  private function DescribeSites()
    {
    include_once('../cdash/common.php');
    include_once('../models/clientsite.php');
    include_once('../models/clientos.php');

    if(!isset($this->Parameters['site']) && !isset($this->Parameters['sid']))
      {
      return array('status'=>false, 'message'=>'You must specify a site or sid parameter.');
      }
    if(isset($this->Parameters['site']) && isset($this->Parameters['sid']))
      {
      return array('status'=>false, 'message'=>'Only one of the site and sid parameter can be specified.');
      }

    $ids = array();
    if(isset($this->Parameters['site']))
      {
      $sites = explode(';', $this->Parameters['site']);
      foreach($sites as $s)
        {
        $Site = new ClientSite();
        $siteid = $Site->GetId($s);
        if(!is_numeric($siteid) || $siteid <= 0)
          {
          return array('status'=>false, 'message'=>"Site '$s' not found.");
          }
        $ids[] = $siteid;
        }
      }
    else
      {
      $ids = explode(';', $this->Parameters['sid']);
      }

    $sites = array();
    foreach($ids as $id)
      {
      $Site = new ClientSite();
      $Site->Id = $id;

      $SiteOs = new ClientOS();
      $SiteOs->Id = $Site->GetOS();

      $sites[] = array('id' => $id,
                       'name' => $Site->GetName(),
                       'system_name' => $Site->GetSystemName(),
                       'os_name' => $SiteOs->GetName(),
                       'os_version' => $SiteOs->GetVersion(),
                       'bits' => $SiteOs->GetBits(),
                       'last_ping' => $Site->GetLastPing());
      }
    return array('status' => true,
                 'sites' => $sites);
    } // end function DescribeSites

   /**
   * Get client site description
   * @param site the list of client sites
   * @param sid the list of client site ids
   */
  private function ListTools()
    {
    include_once('../cdash/common.php');
    include_once('../models/clientsite.php');
    include_once('../models/clientcompiler.php');
    include_once('../models/clientlibrary.php');

    if(!isset($this->Parameters['site']) && !isset($this->Parameters['sid']))
      {
      return array('status'=>false, 'message'=>'You must specify a site or sid parameter.');
      }
    if(isset($this->Parameters['site']) && isset($this->Parameters['sid']))
      {
      return array('status'=>false, 'message'=>'Only one of the site and sid parameter can be specified.');
      }

    $ids = array();
    if(isset($this->Parameters['site']))
      {
      $sites = explode(';', $this->Parameters['site']);
      foreach($sites as $s)
        {
        $Site = new ClientSite();
        $siteid = $Site->GetId($s);
        if(!is_numeric($siteid) || $siteid <= 0)
          {
          return array('status'=>false, 'message'=>"Site '$s' not found.");
          }
        $ids[] = $siteid;
        }
      }
    else
      {
      $ids = explode(';', $this->Parameters['sid']);
      }

    $sites = array();
    foreach($ids as $id)
      {
      $Site = new ClientSite();
      $Site->Id = $id;

      // Get list of compilers
      $compilerids = $Site->GetCompilerIds();
      $compilerList = array();
      foreach($compilerids as $cid)
        {
        $cc = new ClientCompiler();
        $cc->Id = $cid;

        $compilerList[] = array('name'=>$cc->GetName(), 'version'=>$cc->GetVersion());
        }

      // Get list of libraries
      $libraryids = $Site->GetLibraryIds();
      $libraryList = array();
      foreach($libraryids as $lid)
        {
        $cl = new ClientLibrary();
        $cl->Id = $cid;

        $libraryList[] = array('name'=>$cl->GetName(), 'version'=>$cl->GetVersion());
        }

      // Get list of programs
      $programs = $Site->GetPrograms();
      $programList = array();
      foreach($programs as $p)
        {
        $programList[] = array('name'=>$p['name'], 'version'=>$p['version']);
        }
      $sites[] = array('id' => $id,
                       'name' => $Site->GetName(),
                       'compiler' => $compilerList,
                       'library' => $libraryList,
                       'program' => $programList);
      }
      return array('status' => true,
                   'sites' => $sites);
    } // end function ListTools

  /** Run function */
  function Run()
    {
    if(!isset($this->Parameters['task']))
      {
      return array('status'=>false, 'message'=>'Task should be set: task=...');
      }
    switch($this->Parameters['task'])
      {
      case 'list': return $this->ListSites();
      case 'describe': return $this->DescribeSites();
      case 'tools': return $this->ListTools();
      default: return array('status'=>false, 'message'=>'Unknown task: '.$this->Parameters['task']);
      }
    }
}

?>
