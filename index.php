<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

$templatesused='forumhome_birthdaybit,error_nopermission,forumhome_pmloggedin,forumhome_welcometext,forumhome_logoutcode,forumhome_newposts,forumhome_todayposts,forumhome_logincode,forumhome_loggedinuser,forumhome_loggedinusers,forumhome_lastpostby,forumhome_moderator,forumhome_forumbit_level1_nopost,forumhome_forumbit_level1_post,forumhome_forumbit_level2_nopost,forumhome_forumbit_level2_post,forumhome,forumhome_unregmessage';
$loadbirthdays=1;
$loadmaxusers=1;

require('./global.php');

$permissions=getpermissions();
if (!$permissions['canview']) {
	show_nopermission();
}

//check usergroup of user to see if they can use PMs
//$permissions=getpermissions($forumid);
if ($enablepms==1 and $permissions['canusepm'] and $bbuserinfo['receivepm']) {
  $ignoreusers="";
  if (trim($bbuserinfo['ignorelist'])!="") {
    $ignoreusers='AND fromuserid<>'.implode(' AND fromuserid<>',explode(' ', trim($bbuserinfo['ignorelist'])));
  }

  $allpm=$DB_site->query_first("SELECT COUNT(*) AS messages FROM privatemessage WHERE userid=$bbuserinfo[userid] $ignoreusers");
  $newpm=$DB_site->query_first("SELECT COUNT(*) AS messages FROM privatemessage WHERE userid=$bbuserinfo[userid] AND dateline>$bbuserinfo[lastvisit] AND folderid=0 $ignoreusers");
  $unreadpm=$DB_site->query_first("SELECT COUNT(*) AS messages FROM privatemessage WHERE userid=$bbuserinfo[userid] AND messageread=0 AND folderid=0 $ignoreusers");

  if ($newpm['messages']==0) {
    $lightbulb='off';
  } else {
    $lightbulb='on';
  }
  eval("\$pminfo = \"".gettemplate('forumhome_pmloggedin')."\";");

} else {
  $pminfo='';
}

$numbersmembers=$DB_site->query_first('SELECT COUNT(*) AS users,MAX(userid) AS max FROM user');
$numbermembers=number_format($numbersmembers['users']);

// get total posts
$countposts=$DB_site->query_first('SELECT COUNT(*) AS posts FROM post');
$totalposts=number_format($countposts['posts']);

$countthreads=$DB_site->query_first('SELECT COUNT(*) AS threads FROM thread');
$totalthreads=number_format($countthreads['threads']);

// get newest member
$getnewestusers=$DB_site->query_first("SELECT userid,username FROM user WHERE userid=$numbersmembers[max]");
$newusername=$getnewestusers['username'];
$newuserid=$getnewestusers['userid'];

// if user is know, then welcome
if ($bbuserinfo['userid']!=0) {
  $username=$bbuserinfo['username'];
  eval("\$welcometext = \"".gettemplate('forumhome_welcometext')."\";");
  eval("\$logincode = \"".gettemplate('forumhome_logoutcode')."\";");
  eval("\$newposts = \"".gettemplate('forumhome_newposts')."\";");

} else {
  $welcometext = "";
  eval("\$newposts = \"".gettemplate('forumhome_todayposts')."\";");
  eval("\$logincode = \"".gettemplate('forumhome_logincode')."\";");
}

$birthdaybits="";
if ($showbirthdays) {

  $birthdays = gettemplate('birthdays',0,0);
  $btoday = explode('|||',$birthdays);
  $today = vbdate("Y-m-d",time());
  if (($today != $btoday[0] and $today != $btoday[1]) or empty($birthdays))  { // Need to update!
    if (empty($birthdays)) {
		$DB_site->query("INSERT INTO template (templateid, templatesetid, title, template) VALUES (NULL, '-2', 'birthdays', '')");
	}
    getbirthdays();
    $birthdays = $DB_site->query_first("SELECT template FROM template WHERE title='birthdays' and templatesetid = -2");
    $birthdays = $birthdays['template'];
    $btoday = explode('|||',$birthdays);
  }

  if ($today == $btoday[0]) {
    $birthdays = $btoday[2];
  } elseif ($today == $btoday[1]) {
    $birthdays = $btoday[3];
  }

  if ($birthdays) {
    eval("\$birthdaybits = \"".gettemplate("forumhome_birthdaybit")."\";");
  }
}

//Forum info
$forums=$DB_site->query('SELECT * FROM forum WHERE displayorder<>0 AND active=1 ORDER BY parentid,displayorder');
while ($forum=$DB_site->fetch_array($forums)) {
    $iforumcache["$forum[parentid]"]["$forum[displayorder]"]["$forum[forumid]"] = $forum;
}
$DB_site->free_result($forums);
unset($forum);

//Forum perms
$forumperms=$DB_site->query("SELECT forumid,canview,canpostnew FROM forumpermission WHERE usergroupid='$bbuserinfo[usergroupid]'");
while ($forumperm=$DB_site->fetch_array($forumperms)) {
  $ipermcache["$forumperm[forumid]"] = $forumperm;
}
$DB_site->free_result($forumperms);
unset($forumperm);

$accesscache = array();
if ($bbuserinfo['userid']!=0 AND $enableaccess) {
  //Access table perms
  $accessperms=$DB_site->query("SELECT forumid,accessmask FROM access WHERE userid='$bbuserinfo[userid]'");
  while ($accessperm=$DB_site->fetch_array($accessperms)) {
    $accesscache["$accessperm[forumid]"] = $accessperm;
  }
  $DB_site->free_result($accessperms);
  unset($accessperm);

  // usergroup defaults
  $usergroupdef['canview'] = $permissions['canview'];
  $usergroupdef['canpostnew'] = $permissions['canpostnew'];

  // array for accessmask=0
  $noperms['canview'] = 0;
  $noperms['canpostnew'] = 0;
}

$imodcache = array();
$mod = array();
$forummoderators=$DB_site->query('SELECT user.userid,user.username,moderator.forumid
                                  FROM moderator
                                  LEFT JOIN user
                                    ON (moderator.userid=user.userid)
                                  ORDER BY user.username');
while ($moderator=$DB_site->fetch_array($forummoderators)) {
  $imodcache["$moderator[forumid]"][] = $moderator;
  $mod["$moderator[userid]"] = 1;
}
$DB_site->free_result($forummoderators);
unset($moderator);

$activeusers = "";
$loggedinusers = "";
if ($displayloggedin) {
  $datecut=time()-$cookietimeout;

  $loggedins=$DB_site->query_first("SELECT COUNT(*) AS sessions FROM session WHERE userid=0 AND lastactivity>$datecut");
  $numberguest=$loggedins['sessions'];

  $numbervisible=0;
  $numberregistered=0;

  $loggedins=$DB_site->query("SELECT DISTINCT session.userid,username,invisible,usergroupid
                              FROM session
                              LEFT JOIN user ON (user.userid=session.userid)
                              WHERE session.userid>0 AND session.lastactivity>$datecut
                              ORDER BY invisible ASC, username ASC");
  if ($loggedin=$DB_site->fetch_array($loggedins)) {
    $numberregistered++;
    if ($loggedin['invisible']==0 or $bbuserinfo['usergroupid']==6) {
      $numbervisible++;
      $userid = $loggedin['userid'];
      if ($loggedin['invisible'] == 1) { // Invisible User but show to Admin
        $invisibleuser = '*';
      } else {
        $invisibleuser = '';
      }
      if ($loggedin['usergroupid'] == 6 and $highlightadmin) {
      	$username = "<b><i>$loggedin[username]</i></b>";
      } else if (($mod["$userid"] or $loggedin['usergroupid'] == 5) and $highlightadmin) {
      	$username = "<b>$loggedin[username]</b>";
      } else {
				$username = $loggedin['username'];
			}
      eval("\$activeusers = \"".gettemplate('forumhome_loggedinuser')."\";");
    }

    while ($loggedin=$DB_site->fetch_array($loggedins)) {
      $numberregistered++;
      $invisibleuser = '';
      if ($loggedin['invisible']==1 and $bbuserinfo['usergroupid']!=6) {
        continue;
      }
      $numbervisible++;
      $userid=$loggedin['userid'];
      if ($loggedin['invisible'] == 1) { // Invisible User but show to Admin
        $invisibleuser = '*';
      }
      if ($loggedin['usergroupid'] == 6 and $highlightadmin) {
	    $username = "<b><i>$loggedin[username]</i></b>";
			} else if (($mod["$userid"] or $loggedin['usergroupid'] == 5) and $highlightadmin) {
				$username = "<b>$loggedin[username]</b>";
			} else {
				$username = $loggedin['username'];
			}
      eval("\$activeusers .= \", ".gettemplate('forumhome_loggedinuser')."\";");
    }
  }
  $DB_site->free_result($loggedins);

  $totalonline=$numberregistered+$numberguest;
  $numberinvisible=$numberregistered-$numbervisible;

  $maxusers = explode(' ', trim(gettemplate('maxloggedin', 0, 0)) );
  $maxusers[0] = intval($maxusers[0]);
  if (($maxusers[0] <= $totalonline AND $maxusers[0] > 0) OR sizeof($maxusers) == 1) {
    $time = time();
    $maxloggedin = intval($totalonline) . ' ' . $time;
    $DB_site->query("UPDATE template SET template='$maxloggedin' WHERE title='maxloggedin'");
    $maxusers[0] = $totalonline;
    $maxusers[1] = $time;
  }
  $recordusers = $maxusers[0];
  $recorddate = vbdate($dateformat,$maxusers[1]);
  $recordtime = vbdate($timeformat,$maxusers[1]);
  eval("\$loggedinusers = \"".gettemplate('forumhome_loggedinusers')."\";");
}

// Start makeforumbit
function makeforumbit($forumid,$depth=1,$permissions='') {
  global $DB_site,$bbuserinfo,$iforumcache,$ipermcache,$imodcache,$session,$accesscache,$usergroupdef,$noperms;
  global $showlocks,$hideprivateforums,$showforumdescription,$forumhomedepth,$dateformat,$timeformat,$enableaccess;

  if ( !isset($iforumcache["$forumid"]) ) {
    return;
  }

  $forumbits = '';

  while ( list($key1,$val1)=each($iforumcache["$forumid"]) ) {
    while ( list($key2,$forum)=each($val1) ) {

      // Permissions
      if ( $enableaccess and is_array($accesscache["$forum[forumid]"]) ) {
        if ($accesscache["$forum[forumid]"]['accessmask']==1) {
          $forumperms = $usergroupdef;
        } else {
          $forumperms = $noperms;
        }
      } else if ( is_array($ipermcache["$forum[forumid]"]) ) {
        $forumperms = $ipermcache["$forum[forumid]"];
      } else {
        $forumperms = $permissions;
      }

      if (!$hideprivateforums) {
        $forumperms['canview']=1;
      }

      if (!$forumperms['canview']) {
        continue;
      } else {
        $forumshown=1;

        // do light bulb
        if ($bbuserinfo['lastvisitdate']=='Never') {
          $forum['onoff']='on';
        } else {
					if (($fview = get_bbarraycookie('forumview', $forum['forumid'])) > $bbuserinfo['lastvisit']) {
						$userlastvisit=$fview;
					} else {
						$userlastvisit=$bbuserinfo['lastvisit'];
					}
          if ($userlastvisit<$forum['lastpost']) {
            $forum['onoff']='on';
          } else {
            $forum['onoff']='off';
          }
        }

        if ((!$forumperms['canpostnew'] or $forum['allowposting']==0) and $showlocks) {
          $forum['onoff'].='lock';
        }

        // prepare template vars
        if (!$showforumdescription) {
          $forum['description']='';
        }

        // dates
        if ($forum['lastpost']>0) {
          $forum['lastpostdate']=vbdate($dateformat,$forum['lastpost']);
          $forum['lastposttime']=vbdate($timeformat,$forum['lastpost']);
          eval("\$forum['lastpostinfo'] = \"".gettemplate('forumhome_lastpostby')."\";");
        } else {
          $forum['lastpostinfo']='Never';
        }

        $listexploded=explode(",", $forum['parentlist']);
        while ( list($mkey1,$mval1)=each($listexploded) ) {
          if ( !isset($imodcache["$mval1"]) ) {
            continue;
          }
          reset($imodcache["$mval1"]);
          while ( list($mkey2,$moderator)=each($imodcache["$mval1"]) ) {
            if ( !isset($forum['moderators']) ) {
              eval("\$forum['moderators'] = \"".gettemplate('forumhome_moderator')."\";");
            } else {
              eval("\$forum['moderators'] .= \", ".gettemplate('forumhome_moderator')."\";");
            }
          }
        }

        if ( !isset($forum['moderators']) ) {
          $forum['moderators'] = '&nbsp;';
        }

        if ($forum['cancontainthreads']==1) {
          $tempext = '_post';
        } else {
          $tempext = '_nopost';
        }

        eval("\$forumbits .= \"".gettemplate("forumhome_forumbit_level$depth$tempext")."\";");

        if ($depth<$forumhomedepth) {
          $forumbits.=makeforumbit($forum['forumid'],$depth+1,$forumperms);
        }
      } // END if can view
    } // END while ( list($key2,$forum)=each($val1) ) {
  } // END while ( list($key1,$val1)=each($iforumcache["$forumid"]) ) {

  unset($iforumcache["$forumid"]);
  return $forumbits;
}

if (!isset($forumid) or $forumid==0 or $forumid=='') {
  $forumid=-1;
} else {
  // need to get permissions for this specific forum
  $permissions=getpermissions(intval($forumid));
}
$forumbits=makeforumbit(intval($forumid), 1, $permissions);

$unregwelcomemessage='';
if ($bbuserinfo['userid']==0) {
  eval("\$unregwelcomemessage = \"".gettemplate('forumhome_unregmessage')."\";");
}

eval("dooutput(\"".gettemplate('forumhome')."\");");

?>