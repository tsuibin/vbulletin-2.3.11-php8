<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

// jump from forumjump
$goto = '';
switch($_GET['forumid']) {
	case 'home': $goto = 'index'; break;
	case 'search': $goto = 'search'; break;
	case 'pm': $goto = 'private'; break;
	case 'wol': $goto = 'online'; break;
	case 'cp': $goto = 'usercp'; break;
}
if ($goto != '') {
	if ($_GET['s']) {
		$sessionhash = $_GET['s'];
	} else {
		$sessionhash = '';
	}
	header("Location: $goto.php?s=$sessionhash");
	exit;
}
// end forumjump redirects

if ($_GET['action'] != "markread") {
	$templatesused='forumdisplay_threadslist_rateoption,forumdisplay_threadslist_threadrate,forumdisplaybit_threadrate,forumhome_lastpostby,forumdisplay_announcement,forumdisplay_moderator,forumdisplay_newthreadlink,forumdisplay_moderatedby,forumdisplay_forumbit_level1_post,forumdisplay_forumbit_level1_nopost,forumdisplay_forumbit_level2_post,forumdisplay_forumbit_level2_nopost,';
	$templatesused.='forumdisplay_loggedinuser,forumdisplay_loggedinusers,forumdisplay_adminoptions,forumdisplay_forumslist,forumdisplay_multipagenav_more,forumdisplay_multipagenav_pagenumber,forumdisplay_multipagenav,forumdisplay_gotonew,forumdisplaybit,forumdisplay_threadslist,forumdisplay_nothreads,forumrules,forumdisplay,forumdisplay_sortarrow,forumdisplay_announcementsonly,forumhome_moderator';
} else {
	$templatesused='standardredirect,redirect_markreadforum';
}
require('./global.php');

if ($action=="markread") {
	$forumid = intval($forumid);

	if ($bbuserinfo['cookieuser']) {
		set_bbarraycookie('forumview', $forumid, time());
	}

	$parentid = &$forumcache["$forumid"]['parentid'];
	if ($parentid == -1) {
		$url = "index.php?s=$session[sessionhash]";
	} else {
		$url = "forumdisplay.php?s=$session[sessionhash]&amp;forumid=$parentid";
	}

	$url = str_replace("\"", "", $url);
	eval("standardredirect(\"".gettemplate("redirect_markreadforum")."\",\"\$url\");");
	exit;
}

$forumid = intval($forumid);
$foruminfo = verifyid('forum',$forumid,1,1);

$getperms=getpermissions($forumid,-1,-1,$foruminfo['parentlist']);
if (!$getperms['canview']) {
  show_nopermission();
}

updateuserforum($forumid);

$forumdisplay['threadslist'] = "";

$bbcodeon=iif($foruminfo['allowbbcode'],$ontext,$offtext);
$imgcodeon=iif($foruminfo['allowimages'],$ontext,$offtext);
$htmlcodeon=iif($foruminfo['allowhtml'],$ontext,$offtext);
$smilieson=iif($foruminfo['allowsmilies'],$ontext,$offtext);

if (!isset($daysprune) or $daysprune==0 or $daysprune==-1) {
  if ($bbuserinfo['daysprune']>0) {
    $daysprune = $bbuserinfo['daysprune'];
  } else {
    $daysprune = iif($foruminfo['daysprune'], $foruminfo['daysprune'], 30);
  }
}

// draw nav bar
$navbar=makenavbar($forumid,"forum",0);

// get moderators
$forummoderators=$DB_site->query('SELECT user.userid,user.username,moderator.forumid
                                    FROM moderator
                                    LEFT JOIN user
                                      ON (moderator.userid=user.userid)
                                    ORDER BY user.username');
unset($imodcache);
while ($moderator=$DB_site->fetch_array($forummoderators)) {
  $imodcache["$moderator[forumid]"][] = $moderator;
}
$DB_site->free_result($forummoderators);

unset($moderatorslist);

$listexploded=explode(',', $foruminfo['parentlist']);
while ( list($mkey1,$mval1)=each($listexploded) ) {
  if ( !isset($imodcache["$mval1"]) ) {
    continue;
  }
  reset($imodcache["$mval1"]);
  while ( list($mkey2,$moderator)=each($imodcache["$mval1"]) ) {
    if ( !isset($moderatorslist) ) {
      eval("\$moderatorslist = \"".gettemplate('forumdisplay_moderator')."\";");
    } else {
      eval("\$moderatorslist .= \", ".gettemplate('forumdisplay_moderator')."\";");
    }
  }
}

if ( isset($moderatorslist) ) {
  eval("\$moderatedby = \"".gettemplate('forumdisplay_moderatedby')."\";");
} else {
  $moderatedby='';
}


// display sub forums
$permissions = $getperms;
//Forum info
$forums=$DB_site->query('SELECT * FROM forum WHERE displayorder<>0 AND active=1 ORDER BY parentid,displayorder,forumid');
unset($iforumcache);
while ($forum=$DB_site->fetch_array($forums)) {
	$iforumcache["$forum[parentid]"]["$forum[displayorder]"]["$forum[forumid]"] = $forum;
}
$DB_site->free_result($forums);
unset($forum);


unset($ipermcache);
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

//GENERATE forumjump:
$curforumid = $forumid;
makeforumjump();

// Start makeforumbit
$forumshown = 0;
function makeforumbit($forumid,$depth=1,$permissions='') {
  global $DB_site,$bbuserinfo,$iforumcache,$ipermcache,$imodcache,$session,$accesscache,$usergroupdef,$noperms;
  global $showlocks,$hideprivateforums,$showforumdescription,$forumdisplaydepth,$dateformat,$timeformat,$forumshown,$enableaccess;

  if ( empty($iforumcache["$forumid"]) or !is_array($iforumcache["$forumid"]) ) {
    return;
  }

  $forumbits = '';

  reset($iforumcache["$forumid"]);
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

        unset($forum['moderators']);
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

        eval("\$forumbits .= \"".gettemplate("forumdisplay_forumbit_level$depth$tempext")."\";");

        if ($depth<$forumdisplaydepth) {
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
}
$forumbits=makeforumbit($forumid, 1, $permissions);

if ($forumshown==1) {
  eval("\$forumdisplay[forumslist] = \"".gettemplate('forumdisplay_forumslist')."\";");
} else {
  $forumdisplay['forumslist']='';
}
unset($imodcache);

$newthreadlink = "";

/////////////////////////////////
if ($foruminfo['cancontainthreads']==1) {
/////////////////////////////////

// check to see if forum has cookie set for last reading time...ie forum marked as read
if (($fview = get_bbarraycookie('forumview', $foruminfo['forumid'])) >$bbuserinfo['lastvisit']) {
	$bbuserinfo['lastvisit']=$fview;
}

if ($foruminfo['allowposting']==1) {
  eval("\$newthreadlink = \"".gettemplate('forumdisplay_newthreadlink')."\";");
}

// display threads
$limitothers="";
if (!$getperms['canviewothers']) {
  $limitothers="AND postuserid='$bbuserinfo[userid]' AND '$bbuserinfo[userid]'<>0";
}

// look at thread limiting options
$datecut = '';
$stickyids = '';
$stickycount = 0;
if ($daysprune != 1000) {
  $checkdate = time() - ($daysprune * 86400);
  $datecut = 'AND lastpost >= ' . $checkdate;
}

// get number of sticky threads for the first page
// on the first page there will be the sticky threads PLUS the $perpage other normal threads
// not quite a bug, but a deliberate feature!

// complete form fields on page
unset($daysprunesel);
$daysprunesel[$daysprune]='selected';

// look at sorting options:
if (!isset($sortorder)) {
  $sortorder = "";
}
if (!isset($sortfield)) {
  $sortfield = "";
}

if ($sortorder!='asc') {
  $sortorder = 'desc';
  $sqlsortorder='DESC';
  $order['desc']='selected';
} else {
  $sqlsortorder='';
  $order['asc']='selected';
}
switch ($sortfield) {
  case 'title':
  case 'lastpost':
  case 'replycount':
  case 'views':
  case 'postusername':
  case 'voteavg':
    break;

  default:
    $sortfield='lastpost';
}
if ($sortfield=="voteavg" and !$foruminfo['allowratings']) {
  $sortfield="lastpost";
}

$sort = array();
$sort[$sortfield]='selected';

$threadscount=$DB_site->query_first("SELECT COUNT(*) AS threads
						FROM thread
						WHERE thread.forumid = $foruminfo[forumid]
							AND thread.sticky=0
							AND thread.visible=1
						$datecut
						$limitothers");
$totalthreads = $threadscount['threads']; // + $stickycount;

sanitize_pageresults($totalthreads, $pagenumber, $perpage, 200, $maxthreads);

if ($pagenumber == 1) {
	$datecut .= ' AND sticky=0';
	$stickies = $DB_site->query("SELECT threadid,lastpost
						FROM thread
						WHERE forumid = $foruminfo[forumid]
							AND visible=1
							AND sticky=1
						$limitothers");
	while($thissticky=$DB_site->fetch_array($stickies)) {
		$stickycount++;
		$stickyids .= ",$thissticky[threadid]";
	}
}

$limitlower = ($pagenumber - 1) * $perpage + 1;
$limitupper = ($pagenumber) * $perpage;

if ($limitupper > $totalthreads) {
  $limitupper = $totalthreads;
  if ($limitlower > $totalthreads) {
    $limitlower = $totalthreads - $perpage;
  }
}
if ($limitlower <= 0) {
  $limitlower = 1;
}

$sel_limitlower = $limitlower;
if ($pagenumber != 1) {
	//$sel_limitlower -= $stickycount;
}

$dotuserid='';
$dotjoin='';
$votequery='';
if ($showdots and $bbuserinfo['userid'] >= 1) {
  $dotuserid = 'DISTINCT post.userid,';
  $dotjoin = "LEFT JOIN post ON (thread.threadid = post.threadid AND post.userid = '$bbuserinfo[userid]' AND post.visible = 1)";
}
if ($foruminfo['allowratings']) {
	$showvotes = intval($showvotes);
   $votequery = "IF(votenum>=$showvotes,votenum,0) AS votenum,
                 IF(votenum>=$showvotes AND votenum > 0,votetotal/votenum,0) AS voteavg,";
}

// get announcements
$datenow=time();
$forumlist=getforumlist($forumid,'forumid');
$doneannouncements = 0;
if ($announcement=$DB_site->query_first("SELECT announcementid,startdate,title,user.username,user.userid,user.usertitle,user.customtitle
                                         FROM announcement
                                         LEFT JOIN user
                                           ON user.userid=announcement.userid
                                         WHERE startdate<=$datenow AND enddate>=$datenow
                                           AND $forumlist
                                         ORDER BY startdate DESC
                                         LIMIT 1")) {
    if ($foruminfo['allowratings']) {
      $thread['rating']='clear.gif';
      eval("\$threadrating = \"".gettemplate('forumdisplaybit_threadrate')."\";");
      $backcolor = '{secondaltcolor}';
		  $bgclass = "alt2";
    } else {
			unset($threadrating);
      $backcolor = '{firstaltcolor}';
		  $bgclass = "alt1";
    }

     if ($announcement['customtitle']==2) {
       $announcement['usertitle'] = htmlspecialchars($announcement['usertitle']);
		 }
 	   $announcement['postdate']=vbdate($dateformat,$announcement['startdate']);

  if ($announcement['startdate']>$bbuserinfo['lastvisit']) {
  	$announcement['icon']='newannounce.gif';
  } else {
  	$announcement['icon']='announce.gif';
  }
  eval("\$announcement = \"".gettemplate('forumdisplay_announcement')."\";");
  $doneannouncements = 1;
}

$getthreadids=$DB_site->query("
	SELECT
	".iif($sortfield=="voteavg",$votequery,"")."
		thread.threadid
	FROM thread
	WHERE thread.forumid = $foruminfo[forumid]
		AND thread.sticky=0
		AND thread.visible=1
	$datecut
	$limitothers
  	ORDER BY sticky DESC, $sortfield $sqlsortorder
  	LIMIT ".($sel_limitlower-1).",$perpage");

$threadids='thread.threadid IN (0';
while ($thread=$DB_site->fetch_array($getthreadids)) {
  $threadids .= "," . $thread['threadid'];
}
if ($stickyids != '') {
	$threadids .= $stickyids;
}
$threadids.=')';

$threads=$DB_site->query("
SELECT $dotuserid $votequery ".iif($foruminfo['allowicons'],'icon.title as icontitle,icon.iconpath,','')."
	thread.threadid,thread.title,lastpost, forumid,pollid,open,replycount,postusername,postuserid,
	lastposter,thread.dateline,views,thread.iconid,notes,thread.visible,sticky,votetotal,attach
	FROM thread
	".iif($foruminfo['allowicons'],'LEFT JOIN icon ON (icon.iconid = thread.iconid)','')."
	$dotjoin
	WHERE $threadids
	ORDER BY sticky DESC, $sortfield $sqlsortorder
	");

unset($forumdisplaybits);
$pagenav = "";
if ($totalthreads>0 || $stickyids != '') {
  // check to see if there are any threads to display. If there are, do so, otherwise, show message

  $umaxposts = explode(',', $usermaxposts . ",$maxposts");
  $newmaxposts = max($umaxposts);
  if ($bbuserinfo['maxposts']!=-1 and $bbuserinfo['maxposts']!=0 and $bbuserinfo['maxposts'] <= $newmaxposts)	{
	$pperpage = $bbuserinfo['maxposts'];
  } else {
	$pperpage = $maxposts;
  }

  $counter=0;
  while ($thread=$DB_site->fetch_array($threads)) { // and $counter++<$perpage) {
    $thread['movedprefix'] = '';
    $thread['typeprefix'] = '';
    $paperclip = '';
    if ($thread['open']==10) {
      // thread has been moved!
      $thread['threadid']=$thread['pollid'];
      $thread['replycount']="-";
      $thread['views']="-";
      $thread['icon']="&nbsp;";
      if ($bbuserinfo['lastvisitdate']=='Never') {
        $thread['newoldhot']='newmovedfolder';
      } elseif ($thread['lastpost']>$bbuserinfo['lastvisit']) {
        $thread['newoldhot']='newmovedfolder';
      } else {
        $thread['newoldhot']='movedfolder';
      }
      $thread['pagenav']='';

      $thread['movedprefix']=$movedthreadprefix;
      if ($wordwrap!=0) {
        $thread['title']=dowordwrap($thread['title']);
      }

      $thread['lastreplydate']=vbdate($dateformat,$thread['lastpost']);
      $thread['lastreplytime']=vbdate($timeformat,$thread['lastpost']);

      $thread['gotonew']='';
      $thread['rating']='clear.gif';
      $thread['votenum'] = '0';
    } else {

      if ($foruminfo['allowratings']) {
				if ($thread['votenum'] >= $showvotes) {
					$rating = intval(round($thread['voteavg']));
					$thread['rating'] = $rating . 'stars.gif';
				} else {
					$thread['rating'] = 'clear.gif';
				}
      } else {
				$thread['rating']='clear.gif';
				$thread['votenum']='0';
      }
      if ($thread['pollid']!=0) {
         $thread['typeprefix']=$pollthreadprefix;
      }
      if ($thread['sticky'] == 1) {
         $thread['typeprefix']=$stickythreadprefix.$thread['typeprefix'];
      }

      if (($bbuserinfo['maxposts'] != -1) and ($bbuserinfo['maxposts'] != 0)) {
				$maxposts = $bbuserinfo['maxposts'];
			}
      if (($thread['replycount']+1)>$maxposts and $linktopages) {

        $totalpages=($thread['replycount']+1)/$maxposts;
        if ($totalpages!=intval($totalpages)) {
          $totalpages=intval($totalpages)+1;
        }

        $acurpage=0;
        $pagenumbers='';
        while ($acurpage++<$totalpages) {
          if ($acurpage==$maxmultipage) {
            eval("\$pagenumbers .= \"".gettemplate('forumdisplay_multipagenav_more')."\";");
            break;
          } else {
            eval("\$pagenumbers .= \"".gettemplate('forumdisplay_multipagenav_pagenumber')."\";");
          }
        }
        eval("\$thread[pagenav] = \"".gettemplate('forumdisplay_multipagenav')."\";");
      } else {
        $thread['pagenav']='';
      }

      if (!$foruminfo['allowicons'] or $thread['iconid']==0) {
        if ($showdeficon) {
			    $thread['icon']='<img src="{imagesfolder}/icons/icon1.gif"  border="0" alt="">';
				} else {
						$thread['icon']="&nbsp;";
        }
      } else {
         $thread['icon']="<img src=\"$thread[iconpath]\" alt=\"$thread[icontitle]\" width=\"15\" height=\"15\" border=\"0\">";
      }

      if ($foruminfo['allowicons'] and $thread['pollid']!=0) {
        $thread['icon']='<img src="{imagesfolder}/poll.gif" alt="Poll" width="15" height="15" border="0">';
      }

      if ($thread['attach']>0) {
        $paperclip="<img src=\"{imagesfolder}/paperclip.gif\" alt=\"$thread[attach] Attachment(s)\" border=\"0\" align=\"absmiddle\">";
      }

      if ($wordwrap!=0) {
        $thread['title']=dowordwrap($thread['title']);
      }

      $thread['lastreplydate']=vbdate($dateformat,$thread['lastpost']);
      $thread['lastreplytime']=vbdate($timeformat,$thread['lastpost']);

      $thread['gotonew']='';

      $thread['newoldhot']='folder';
      if (!$thread['open']) {
        $thread['newoldhot']='lock'.$thread['newoldhot'];
      }
      if ($usehotthreads and ( ($thread['replycount']>=$hotnumberposts and $hotnumberposts>0) or ($thread['views']>=$hotnumberviews and $hotnumberviews>0) ) ) {
        $thread['newoldhot']='hot'.$thread['newoldhot'];
      }
      if ($bbuserinfo['lastvisitdate']=='Never') {
        $thread['newoldhot']='new'.$thread['newoldhot'];
      } elseif ($thread['lastpost']>$bbuserinfo['lastvisit']) {
        if (get_bbarraycookie('threadview', $thread['threadid']) < $thread['lastpost']) {
          $thread['newoldhot']='new'.$thread['newoldhot'];
        }
        eval("\$thread[gotonew] = \"".gettemplate('forumdisplay_gotonew')."\";");
      }
    }
    if ($thread['postuserid']) {
      $thread['postedby'] = "<a href=\"member.php?s=$session[sessionhash]&amp;action=getinfo&amp;userid=$thread[postuserid]\">$thread[postusername]</a>";
    } else {
      $thread['postedby'] = $thread['postusername'];
    }
    if ($showdots and $bbuserinfo['userid'] >= 1 and $bbuserinfo['userid'] == $thread['userid']) {
       $thread['newoldhot'] = 'dot_' . $thread['newoldhot'];
    }
    if ($foruminfo['allowratings']) {
      eval("\$threadrating = \"".gettemplate('forumdisplaybit_threadrate')."\";");
      $backcolor = '{secondaltcolor}';
		  $bgclass = "alt2";
    } else {
			$threadrating='';
	    $backcolor = '{firstaltcolor}';
			$bgclass = "alt1";
    }
    eval("\$forumdisplaybits .= \"".gettemplate('forumdisplaybit')."\";");

  }
  $DB_site->free_result($threads);

  $pagenav = getpagenav($totalthreads,"forumdisplay.php?s=$session[sessionhash]&amp;forumid=$forumid&amp;daysprune=$daysprune&amp;sortorder=$sortorder&amp;sortfield=$sortfield&amp;perpage=$perpage");

  // prepare sort things for column header row:
  $sorturl="forumdisplay.php?s=$session[sessionhash]&amp;forumid=$forumid&amp;daysprune=$daysprune&amp;pagenumber=$pagenumber&amp;perpage=$perpage";
  $oppositesort=iif($sortorder=='asc','desc','asc');
	unset ($sortarrow);

  eval("\$sortarrow[$sortfield] = \"".gettemplate('forumdisplay_sortarrow')."\";");

}

// check to see if there are threads OR announcements (in a non-category forum only)
if ($totalthreads>0 or $doneannouncements==1 or $stickyids != '') {

  if ($foruminfo['allowratings']) {
    eval("\$threadrating = \"".gettemplate('forumdisplay_threadslist_threadrate')."\";");
    eval("\$threadrateoption = \"".gettemplate('forumdisplay_threadslist_rateoption')."\";");
  } else {
    $threadrating = '';
    $threadrateoption = '';
  }

  if ($totalthreads>0 || $stickyids != '') {
    // there are threads
    eval("\$forumdisplay[threadslist] = \"".gettemplate('forumdisplay_threadslist')."\";");
  } else {
    // just announcements
  	 eval("\$forumdisplay[threadslist] = \"".gettemplate('forumdisplay_announcementsonly')."\";");
  }

} else {
  // there are no threads or announcements to show
  eval("\$forumdisplay[threadslist] = \"".gettemplate('forumdisplay_nothreads')."\";");
}

/////////////////////////////////
}
/////////////////////////////////

getforumrules($foruminfo,$getperms);

if (ismoderator($forumid)) {
	eval("\$adminoptions = \"".gettemplate("forumdisplay_adminoptions")."\";");
} else {
	$adminoptions = "&nbsp;";
}

// Get users browsing this forum
$onlineusers = '';
if ($showforumusers) {
	$datecut = $ourtimenow - $cookietimeout;
	$browsers = '';
	$comma = '';
	$forumusers = $DB_site->query("SELECT username, invisible, userid
						FROM user
						WHERE  inforum = $foruminfo[forumid]
							AND lastactivity > $datecut
							AND lastvisit <> lastactivity");
	while ($forumuser = $DB_site->fetch_array($forumusers)) {
		if ((!$forumuser['invisible'] or $bbuserinfo['usergroupid'] == 6) and $bbuserinfo['userid'] != $forumuser['userid']) {
			$userid = $forumuser['userid'];
			$username = $forumuser['username'];
			if ($forumuser['invisible'] == 1) { // Invisible User but show to Admin
				$invisibleuser = '*';
			} else {
				$invisibleuser = '';
     			}
			eval("\$browsers .= \"".$comma.gettemplate('forumdisplay_loggedinuser')."\";");
			$comma = ', ';
		}
	}
	// Don't ask the DB for the user that is viewing the page as they wouldn't be here if they weren't! DOH!
	// This way our query up above can hit the inforum index so don't change unless you know what you are doing.
	if ((!$bbuserinfo['invisible'] or $bbuserinfo['usergroupid'] == 6) and $bbuserinfo['userid'] <> 0) {
		$userid = $bbuserinfo['userid'];
		$username = $bbuserinfo['username'];
		if ($bbuserinfo['invisible'] == 1) { // Admin is invisible but show himself to himself, get it!
			$invisibleuser = '*';
		} else {
			$invisibleuser = '';
		}
		eval("\$browsers .= \"".$comma.gettemplate('forumdisplay_loggedinuser')."\";");
	}
	if ($browsers) {
		if (!$moderatedby) {
			$onlineusers = "<br>";
		}
		eval("\$onlineusers .= \"".gettemplate('forumdisplay_loggedinusers')."\";");
	}
}


eval("dooutput(\"".gettemplate('forumdisplay')."\");");


?>