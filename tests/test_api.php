<?php
//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once(dirname(__FILE__).'/cdash_test_case.php');

require_once('cdash/common.php');
require_once('cdash/pdo.php');

class APITestCase extends KWWebTestCase
{
  function __construct()
    {
    parent::__construct();
    }

  function testAPI()
    {
    $projectList = $this->get($this->url."/api/?method=project&task=list");
    if(strpos($projectList, "InsightExample") === false)
      {
      $this->fail("'InsightExample' not found in list of projects");
      return 1;
      }

    $defects = $this->get($this->url."/api/?method=build&task=defects&project=EmailProjectExample");
    if(strpos($defects, "testfailed") === false)
      {
      $this->fail("Expected output not found when querying API for defects");
      return 1;
      }

    $checkinsdefects = $this->get($this->url."/api/?method=build&task=checkinsdefects&project=EmailProjectExample");
    if(strpos($checkinsdefects, '"testfailed":"3"') === false && strpos($checkinsdefects, '"testfailed":3') === false)
      {
      $this->fail("Expected output not found when querying API for checkinsdefects.");
      return 1;
      }

    $sitetestfailures = $this->get($this->url."/api/?method=build&task=sitetestfailures&project=EmailProjectExample&group=Nightly");
    if(strpos($sitetestfailures, "[]") === false)
      {
      $this->fail("Expected output not found when querying API for sitetestfailures");
      return 1;
      }

    $coveragedirectory = $this->get($this->url."/api/?method=coverage&task=directory&project=InsightExample");
    if(strpos($coveragedirectory, "[]") === false)
      {
      $this->fail("Expected output not found when querying API for coveragedirectory");
      return 1;
      }

    $userdefects = $this->get($this->url."/api/?method=user&task=defects&project=EmailProjectExample");
    if($userdefects != '{"user1kw":{"buildfixes":"6","buildfixesfiles":"1","testfixes":"2","testfixesfiles":"1"},"Test Author":{"testerrors":"1","testerrorsfiles":"1"}}')
      {
      $this->fail("Expected output not found when querying API for userdefects: $userdefects");
      return 1;
      }

    $buildid = $this->get($this->url."/api/getbuildid.php?project=EmailProjectExample&siteid=3&name=Win32-MSVC2009&stamp=20090223-0100-Nightly");
    if($buildid !== '<?xml version="1.0" encoding="UTF-8"?><buildid>4</buildid>')
      {
      $this->fail("Expected output not found when querying API for buildid: $buildid");
      return 1;
      }

    $userid = $this->get($this->url."/api/getuserid.php?author=simpleuser@localhost");
    if(!preg_match("/..xml version..1.0. encoding..UTF-8....userid.\d+..userid./", $userid))
      {
      $this->fail("Output does not match expected pattern when querying API for userid (test1): $userid");
      return 1;
      }

    $userid = $this->get($this->url."/api/getuserid.php?author=simpleuser&project=PublicDashboard");
    if(!preg_match("/..xml version..1.0. encoding..UTF-8....userid.\d+..userid./", $userid))
      {
      $this->fail("Output does not match expected pattern when querying API for userid (test2): $userid");
      return 1;
      }

    $userid = $this->get($this->url."/api/getuserid.php");
    if($userid !== '<?xml version="1.0" encoding="UTF-8"?><userid>error<no-author-param/></userid>')
      {
      $this->fail("Expected error output not found when querying API for userid (test3): $userid");
      return 1;
      }

    $userid = $this->get($this->url."/api/getuserid.php?author=");
    if($userid !== '<?xml version="1.0" encoding="UTF-8"?><userid>error<empty-author-param/></userid>')
      {
      $this->fail("Expected error output not found when querying API for userid (test4): $userid");
      return 1;
      }

    $userid = $this->get($this->url."/api/getuserid.php?author=blahblahblahblahblah");
    if($userid !== '<?xml version="1.0" encoding="UTF-8"?><userid>error<no-project-param/></userid>')
      {
      $this->fail("Expected error output not found when querying API for userid (test5): $userid");
      return 1;
      }

    $userid = $this->get($this->url."/api/getuserid.php?author=blahblahblahblahblah&project=");
    if($userid !== '<?xml version="1.0" encoding="UTF-8"?><userid>error<empty-project-param/></userid>')
      {
      $this->fail("Expected error output not found when querying API for userid (test6): $userid");
      return 1;
      }

    $userid = $this->get($this->url."/api/getuserid.php?author=blahblahblahblahblah&project=garbage-project-1234567890");
    if($userid !== '<?xml version="1.0" encoding="UTF-8"?><userid>error<no-such-project/></userid>')
      {
      $this->fail("Expected error output not found when querying API for userid (test7): $userid");
      return 1;
      }

    $userid = $this->get($this->url."/api/getuserid.php?author=blahblahblahblahblah&project=PublicDashboard");
    if($userid !== '<?xml version="1.0" encoding="UTF-8"?><userid>not found<no-such-user/></userid>')
      {
      $this->fail("Expected valid output not found when querying API for userid (test8): $userid");
      return 1;
      }

    include("cdash/version.php");
    $version = $this->get($this->url."/api/getversion.php");
    if($version !== $CDASH_VERSION)
      {
      $this->fail("Expected output not found when querying API for version: $version");
      return 1;
      }

    $hasfile = $this->get($this->url."/api/hasfile.php");
    if($hasfile !== "md5sum not specified")
      {
      $this->fail("No output found when querying API for hasfile: $hasfile");
      return 1;
      }

    $hasfile = $this->get($this->url."/api/hasfile.php?md5sums=1q2w3e4r5t");
    if(strpos($hasfile, "1q2w3e4r5t") === false)
      {
      $this->fail("Expected output not found when querying API for hasfile\n$hasfile\n");
      return 1;
      }

    $this->pass("Passed");
    }
}

?>
