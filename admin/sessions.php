<?php
require_once __DIR__ . '/php8compat.php';

error_reporting(7);

if (!function_exists('vbsetcookie'))
{
	exit;
}

if ($templateversion > '2.0.1') { // Use location!
  $location1 = ",location='" . vb_escape($scriptpath) . "'";
  $location2 = ",location";
  $location3 = ",'" . vb_escape($scriptpath) . "'";
} else {
  $location1 = '';
  $location2 = '';
  $location3 = '';
}
// ###################### Start sessions #######################
// get session info
unset($bbuserinfo);
unset($session);

// get first 50 chars
$HTTP_USER_AGENT=substr($HTTP_USER_AGENT,0,50);
$REMOTE_ADDR=substr($REMOTE_ADDR,0,50);

$createanonsession=0;

if ( is_array($_COOKIE) ) {
	$bbuserid = $_COOKIE['bbuserid'];
	$bbpassword = $_COOKIE['bbpassword'];
	$bbalthash = $_COOKIE['bbalthash'];
}

if (isset($styleid)) {
  vbsetcookie("bbstyleid",intval($styleid));
} elseif (isset($bbstyleid)) {
  $styleid = intval($bbstyleid);
}

$cookiehash = $sessionhash;

if ($s) {
  if (!preg_match('#^[a-f0-9]{32}$#', $s))
  {
    $s = '';
  }
  else
  {
    $sessionhash=$s;
  }
}

$badcookie = 0;
if ($s and $cookiehash and $s!=$cookiehash) {
  // s= and cookie 'sessionhash' exist, but they're not equal, so use the one in the URL.
  $badcookie=1;
}

if (isset($loginusername) and isset($loginpassword)) {
  if ($bbuserinfo=$DB_site->query_first("SELECT user.*,userfield.* FROM user LEFT JOIN userfield ON userfield.userid=user.userid WHERE user.username='".vb_escape(htmlspecialchars($loginusername))."'")) {
    if (md5($loginpassword)!=$bbuserinfo['password']) {
    		$useforumjump = 0;
		eval("standarderror(\"".gettemplate("error_wrongpassword")."\");");
    } else {
      $bbuserid=$bbuserinfo['userid'];
      $bbpassword=md5($loginpassword);

      if ($bbuserinfo['cookieuser']==1) {
        vbsetcookie("bbuserid",$bbuserid, true, true);
        vbsetcookie("bbpassword",$bbpassword, true, true);
      }

      $DB_site->query("DELETE FROM session WHERE sessionhash='".vb_escape($sessionhash)."'");
      unset($sessionhash);
      $bbuserinfo['realstyleid'] = $bbuserinfo['styleid'];
    }
  } else {
    $bbuserid = "";
    $bbpassword = "";
    // make anon session
    $createanonsession=1;
  }
}

if (isset($sessionhash)) {
  // session hash exists

  // validate it:
  if ($session=$DB_site->query_first("SELECT sessionhash,userid,host,useragent,styleid FROM session WHERE lastactivity>".($ourtimenow-$cookietimeout)." AND sessionhash='".vb_escape($sessionhash)."' AND (host='".vb_escape($REMOTE_ADDR)."' OR (althash='".vb_escape($bbalthash)."' AND althash<>'')) AND useragent='".vb_escape($HTTP_USER_AGENT)."'")) {
    // session hash exists

    if ( isset($styleid) ) {
      $styleup = ",styleid=".intval($styleid);
      $session['styleid'] = intval($styleid);
    } else {
      $styleup = "";
    }

    if ($noshutdownfunc) {
      $DB_site->query("UPDATE session SET lastactivity=$ourtimenow".$location1.$styleup." WHERE sessionhash='".vb_escape($sessionhash)."'");
    } else {
      $shutdownqueries[]="UPDATE session SET lastactivity=$ourtimenow".$location1.$styleup." WHERE sessionhash='".vb_escape($sessionhash)."'";
    }

  } else {
    // invalid session hash
    $createanonsession=1;
    unset($session);
  }
  unset($sessionhash);
}

if (!isset($session) or $session['userid']==0) {
  // no session hash exists; check cookies

  if (isset($bbuserid) and isset($bbpassword)) {
    // cookies exist
    $createanonsession=0;

    // validate username and password
    $bbuserinfo=getuserinfo($bbuserid);
    $bbuserinfo['realstyleid'] = $bbuserinfo['styleid'];

    if ($bbpassword==$bbuserinfo['password']) {
      // password valid
      /*if ($bbuserinfo[userid]==0 and $session=$DB_site->query_first("SELECT sessionhash,userid,host,useragent,styleid FROM session WHERE userid=$bbuserinfo[userid] AND host='".vb_escape($REMOTE_ADDR)."' AND useragent='".vb_escape($HTTP_USER_AGENT)."'")) {
        //session already exists

        if ( isset($styleid) ) {
          $styleup = ",styleid=".intval($styleid);
          $session['styleid'] = intval($styleid);
         } else {
          $styleup = "";
        }

        if ($noshutdownfunc) {
          $DB_site->query("UPDATE session SET lastactivity=$ourtimenow".$location1.$styleup." WHERE sessionhash='".vb_escape($session['sessionhash'])."'");
        } else {
          $shutdownqueries[]="UPDATE LOW_PRIORITY session SET lastactivity=$ourtimenow".$location1.$styleup." WHERE sessionhash='".vb_escape($session['sessionhash'])."'";
        }

        // set cookie
        if ($action!="login" and $action!="logout") {
          vbsetcookie("sessionhash",$session['sessionhash'],false,true);
        }

      } else { */
        // no session exists for this user. create a new one

        // don't keep any redundant sessions
        if ($session['userid']==0) {
          if ($noshutdownfunc) {
            $DB_site->query("DELETE FROM session WHERE sessionhash='".vb_escape($session['sessionhash'])."'");
          } else {
            $shutdownqueries[]="DELETE FROM session WHERE sessionhash='".vb_escape($session['sessionhash'])."'";
           }
        }

        //get rid of old session data
        unset($session);

        $session['sessionhash']=md5(uniqid(microtime()));
        $session['host']=$REMOTE_ADDR;
        $session['useragent']=$HTTP_USER_AGENT;
        $session['userid']=$bbuserinfo['userid'];
        if ( isset($styleid) ) {
          $session['styleid'] = intval($styleid);
        } else {
          $session['styleid'] = 0;
        }

        // check not too busy
        if ($sessionlimit>0) {
          $sessions=$DB_site->query_first("SELECT COUNT(*) AS sessions FROM session");
          if ($sessions['sessions']>$sessionlimit) {
            $servertoobusy=1;
          }
        }

        if (!$servertoobusy) {

          if ( isset($styleid) ) {
            $styleupf = ",styleid";
            $styleupv = ','.intval($styleid);
          } else {
            $styleupf = "";
            $styleupv = "";
          }

          $aolips = array(
						'64' => array('12' => array('96' => 1, '97' => 1, '101' => 1, '102' => 1, '103' => 1, '104' => 1, '105' => 1, '106' => 1, '107' => 1)),
						'152' => array('163' => array('188' => 1, '189' => 1, '194' => 1, '195' => 1, '197' => 1, '201' => 1, '204' => 1, '205' => 1, '206' => 1, '207' => 1, '213' => 1)),
						'195' => array('93' => array('32' => 1, '33' => 1, '34' => 1, '48' => 1, '49' => 1, '50' => 1, '64' => 1, '65' => 1, '66' => 1, '72' => 1, '73' => 1, '74' => 1, '75' => 1)),
						'198' => array('81' => array('4' => 1, '5' => 1, '6' => 1, '8' => 1, '9' => 1, '10' => 1, '16' => 1, '21' => 1, '23' => 1, '26' => 1)),
						'202' => array('67' => array('64' => 1)),
						'205' => array('188' => array('178' => 1, '192' => 1, '193' => 1, '195' => 1, '196' => 1, '197' => 1, '198' => 1, '199' => 1, '200' => 1, '201' => 1, '208' => 1, '209' => 1))
					);
					$ipoctet = explode('.', $REMOTE_ADDR);
					if (is_array($aolips[$ipoctet[0]])) {
						if ($aolips[ $ipoctet[0] ][ $ipoctet[1] ][ $ipoctet[2] ] == 1) {
							$althash = md5(uniqid($REMOTE_ADDR . microtime()));
							vbsetcookie('bbalthash', $althash, 0);
						} else {
							$althash = '';
						}
					} else {
						$althash = '';
					}

          $DB_site->query("INSERT INTO session (sessionhash,userid,host,useragent,lastactivity,althash".$location2.$styleupf.") VALUES ('".vb_escape($session['sessionhash'])."','$bbuserinfo[userid]','".vb_escape($session['host'])."','".vb_escape($session['useragent'])."','$ourtimenow','".vb_escape($althash)."'".$location3.$styleupv.")");
        }

        if ($action!="login" and $action!="logout") {
          vbsetcookie("sessionhash",$session['sessionhash'],false, true);
        }
      /*} */
    } else {
      // password invalid
      unset($bbuserinfo);

      $createanonsession=1;

      vbsetcookie("bbuserid","", true, true);
      vbsetcookie("bbpassword","", true, true);

    }
  } else {
    // if we have $session defined, we already know we have a valid session
    if (!isset($session)) {
      // no cookies. try to match on useragent and host data
      $sessions=$DB_site->query("SELECT sessionhash,userid,host,useragent,styleid FROM session WHERE userid=0 AND host='".vb_escape($REMOTE_ADDR)."' AND useragent='".vb_escape($HTTP_USER_AGENT)."'");
      if ($DB_site->num_rows($sessions)==1) {
        //there is one session. use that one!
        $session=$DB_site->fetch_array($sessions);

        if ( isset($styleid) ) {
          $styleup = ",styleid=".intval($styleid);
          $session['styleid'] = intval($styleid);
        } else {
          $styleup = "";
        }

        if ($noshutdownfunc) {
          $DB_site->query("UPDATE session SET lastactivity=$ourtimenow".$location1.$styleup." WHERE sessionhash='".vb_escape($session['sessionhash'])."'");
        } else {
          $shutdownqueries[]="UPDATE session SET lastactivity=$ourtimenow".$location1.$styleup." WHERE sessionhash='".vb_escape($session['sessionhash'])."'";
        }

        if ($action!="login" and $action!="logout") {
          vbsetcookie("sessionhash",$session['sessionhash'],false, true);
        }
      } else {
        // either no session, or more than one possibility. use anonymous user option
        $createanonsession=1;
      }
      unset($sessions);
    }
  }
}

if ($createanonsession) {
  if ($sessionlimit>0) {
    $sessions=$DB_site->query_first("SELECT COUNT(*) AS sessions FROM session");
    if ($session['sessions']>$sessionlimit) {
      $servertoobusy=1;
    }
  }
}

if ($createanonsession and !$servertoobusy) {
  // create dummy session and user info for an unregistered or not logged in user
  unset($createanonsession);

  if ($guestsession=$DB_site->query_first("SELECT sessionhash,styleid FROM session WHERE userid=0 AND host='".vb_escape($REMOTE_ADDR)."' AND useragent='".vb_escape($HTTP_USER_AGENT)."'")) {
    $session['sessionhash']=$guestsession['sessionhash'];
    $session['host']=$REMOTE_ADDR;
    $session['useragent']=$HTTP_USER_AGENT;
    $session['userid']=0;
    $session['lastactivity'] = $ourtimenow;

    if ( isset($styleid) ) {
      $styleup = ",styleid=".intval($styleid);
      $session['styleid'] = intval($styleid);
    } else {
      $styleup = "";
      $session['styleid'] = $guestsession['styleid'];
    }

    if ($noshutdownfunc) {
      $DB_site->query("UPDATE session SET lastactivity=$ourtimenow".$location1.$styleup." WHERE sessionhash='".vb_escape($session['sessionhash'])."'");
    } else {
      $shutdownqueries[]="UPDATE session SET lastactivity=$ourtimenow".$location1.$styleup." WHERE sessionhash='".vb_escape($session['sessionhash'])."'";
    }
  } else {
    $session['sessionhash']=md5(uniqid(microtime()));
    $session['host']=$REMOTE_ADDR;
    $session['useragent']=$HTTP_USER_AGENT;
    $session['userid']=0;
    $session['lastactivity'] = $ourtimenow;

    if ( isset($styleid) ) {
      $styleupf = ",styleid";
      $styleupv = ','.intval($styleid);
      $session['styleid'] = intval($styleid);
    } else {
      $styleupf = "";
      $styleupv = "";
      $session['styleid'] = 0;
    }

    $aolips = array(
    	'64' => array('12' => array('96' => 1, '97' => 1, '101' => 1, '102' => 1, '103' => 1, '104' => 1, '105' => 1, '106' => 1, '107' => 1)),
    	'152' => array('163' => array('188' => 1, '189' => 1, '194' => 1, '195' => 1, '197' => 1, '201' => 1, '204' => 1, '205' => 1, '206' => 1, '207' => 1, '213' => 1)),
    	'195' => array('93' => array('32' => 1, '33' => 1, '34' => 1, '48' => 1, '49' => 1, '50' => 1, '64' => 1, '65' => 1, '66' => 1, '72' => 1, '73' => 1, '74' => 1, '75' => 1)),
    	'198' => array('81' => array('4' => 1, '5' => 1, '6' => 1, '8' => 1, '9' => 1, '10' => 1, '16' => 1, '21' => 1, '23' => 1, '26' => 1)),
    	'202' => array('67' => array('64' => 1)),
    	'205' => array('188' => array('178' => 1, '192' => 1, '193' => 1, '195' => 1, '196' => 1, '197' => 1, '198' => 1, '199' => 1, '200' => 1, '201' => 1, '208' => 1, '209' => 1))
    );
    $ipoctet = explode('.', $REMOTE_ADDR);
    if (is_array($aolips[$ipoctet[0]])) {
    	if ($aolips[ $ipoctet[0] ][ $ipoctet[1] ][ $ipoctet[2] ] == 1) {
    		$althash = md5(uniqid($REMOTE_ADDR . microtime()));
    		vbsetcookie('bbalthash', $althash, 0);
    	} else {
    		$althash = '';
    	}
    } else {
    	$althash = '';
    }

    $DB_site->query("INSERT INTO session (sessionhash,userid,host,useragent,lastactivity,althash".$location2.$styleupf.") VALUES ('".vb_escape($session['sessionhash'])."',0,'".vb_escape($session['host'])."','".vb_escape($session['useragent'])."','$ourtimenow','".vb_escape($althash)."'".$location3.$styleupv.")");
  }

  if ($action!="login" and $action!="logout") {
    vbsetcookie("sessionhash",$session['sessionhash'],false,true);
  }
}

if ($session['userid']==0) {
  $bbuserinfo = $session;
  $bbuserinfo['userid'] = 0;
  $bbuserinfo['username']=iif ($username=="","Unregistered",htmlspecialchars($username));
  $bbuserinfo['password']="";
  $bbuserinfo['daysprune']=-1;

  if (isset($bblastvisit)) {
    $bbuserinfo['lastvisit']=intval($bblastvisit);
    if (!isset($bblastactivity)) {
      $bbuserinfo['lastactivity'] = $ourtimenow;
    } else {
      $bbuserinfo['lastactivity']=intval($bblastactivity);
    }

    // see if user has been here recently
    if ($ourtimenow - $bbuserinfo['lastactivity'] > $cookietimeout) {
      vbsetcookie("bblastvisit",$bbuserinfo['lastactivity']);
      $bbuserinfo['lastvisit']=$bbuserinfo['lastactivity'];
    }
  } else {
    $bbuserinfo['lastvisit'] = $ourtimenow;
    vbsetcookie("bblastvisit" , $ourtimenow);
  }

  $bbuserinfo['cookieuser']=0;
  $bbuserinfo['nosessionhash']=0;
  $bbuserinfo['usergroupid']=1;
  $bbuserinfo['timezoneoffset']=0;
  $bbuserinfo['showsignatures']=1;
  $bbuserinfo['showavatars']=1;
  $bbuserinfo['showimages']=1;
  $bbuserinfo['showvbcode']=0; // Let's leave this off since guests can't choose to disable it.
} else {
  // get pertinient user info
  if (!isset($bbuserinfo) or $bbuserinfo['userid']!=$session['userid']) {
    $bbuserinfo=getuserinfo($session['userid']);
    $bbuserinfo['realstyleid'] = $bbuserinfo['styleid'];
  }
  if ($session['lastactivity']!=0) {
    // use session last activity rather than bbuserinfo, in case it has not been updated in user table
    $bbuserinfo['lastactivity']=$session['lastactivity'];
  }

  // We only use this from forumdisplay.php! Otherwise we set it to 0 which means we aren't in a forum!
  // Other files that want to update this will modify the doshutdownqueries[] as needed.
  if ($ourtimenow - $bbuserinfo['lastactivity'] > $cookietimeout) {
    if (!isset($bypass)) {
      if ($noshutdownfunc) {
        $DB_site->query("UPDATE user SET lastvisit=lastactivity,lastactivity=$ourtimenow".iif($showforumusers,",inforum='0' ","")." WHERE userid='$bbuserinfo[userid]'");
      } else {
        $shutdownqueries[99]="UPDATE user SET lastvisit=lastactivity,lastactivity=$ourtimenow".iif($showforumusers,",inforum='0'","")." WHERE userid='$bbuserinfo[userid]'";
      }
    }
    $bbuserinfo['lastvisit'] = $bbuserinfo['lastactivity'];
  } else {
    if (!isset($bypass)) {
      if ($noshutdownfunc) {
        $DB_site->query("UPDATE user SET lastactivity=$ourtimenow".iif($showforumusers,",inforum='0' ","")." WHERE userid='$bbuserinfo[userid]'");
      } else {
        $shutdownqueries[99]="UPDATE user SET lastactivity=$ourtimenow".iif($showforumusers,",inforum='0' ","")." WHERE userid='$bbuserinfo[userid]'";
        // This update will be done in the doshutdownfunction automatically, but the old method was doing screwy things!!
      }
    }
    $bbuserinfo['lastvisit'] = $bbuserinfo['lastvisit'];
  }
}

// PHP 8: user.timezoneoffset is a VARCHAR and can be an empty or otherwise
// non-numeric string. PHP 8 throws TypeError when such a value reaches abs()
// or arithmetic (vbdate(), calendar, timediff), and also changed '' != 0 to
// true. Normalise it once here, after every path has set $bbuserinfo.
if (is_array($bbuserinfo)) {
  $bbuserinfo['timezoneoffset'] = (float) ($bbuserinfo['timezoneoffset'] ?? 0);
} else {
  $bbuserinfo = array('timezoneoffset' => 0.0);
}

// get formatted date/time
if ($bbuserinfo['lastvisit']) {
  $bbuserinfo['lastvisitdate'] = vbdate($dateformat." ".$timeformat,$bbuserinfo['lastvisit']);
} else {
  $bbuserinfo['lastvisitdate'] = vbphrase('Never');
}

$bbuserinfo['lastactivity']=intval($bbuserinfo['lastactivity']);

if ($session['styleid']!=0) {
  $bbuserinfo['styleid'] = $session['styleid'];
}

// set bbuserid cookies
if (!isset($bbuserid) and $bbuserinfo['cookieuser'] and $action!="logout") {
  vbsetcookie("bbuserid",$bbuserinfo['userid'], true, true);
  vbsetcookie("bbpassword",$bbuserinfo['password'], true, true);
}

//tidy sessions - once every 100 pages shown (ish!)
// if you are showing lots/fewer pages you might want to increase/decrease the second argument of rand (the 100)
if ($noshutdownfunc) {
	mt_srand ((float) microtime() * 1000000);
	if (mt_rand(1,100)=='50') {
	  $DB_site->query('DELETE FROM session WHERE lastactivity<'.($ourtimenow - $cookietimeout));
	}
} else {
  //  $shutdownqueries[]='DELETE FROM session WHERE lastactivity<'.($ourtimenow - $cookietimeout);
  // this will be done automatically in doshutdown
}
// END SESSIONS ******************************************************************************

$session['dbsessionhash'] = $s = $session['sessionhash'];

if ($badcookie==1) { // send the session has through the URL if the cookie is bad
  $bbuserinfo['nosessionhash']=0;
}

if ($bbuserinfo['nosessionhash']==1) {
  $session['sessionhash'] = '';
}

?>