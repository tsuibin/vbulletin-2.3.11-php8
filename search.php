<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

$templatesused = "forumjumpbit,searchintro,redirect_search,forumdisplay_gotonew,error_searchnoresults,error_searchinvalidterm,searchresultbit,searchresultbit_threadonly,searchresults,searchresults_threadonly";

require("./global.php");

if (!$enablesearches) {
  eval("standarderror(\"".gettemplate("error_searchdisabled")."\");");
  exit;
}

$permissions=getpermissions();
if (!$permissions['canview'] or !$permissions['cansearch']) {
        show_nopermission();
}

if (!isset($action) or $action=="") {
  if (isset($searchid)) {
    $action="showresults";
  } else {
    $action="intro";
  }
}

// ###################### Start getallforumsql #######################
function getallforumsql() {
  $noforums = getallforumsql2();
  if ($noforums) {
    return " AND NOT (".$noforums.")";
  }
}

$firstdone = 0;
$curforumid = 0;

// ###################### Start getallforumsql2 #######################
function getallforumsql2($forumid=-1,$addbox=1,$prependchars="",$permission="") {
  global $DB_site,$optionselected,$usecategories,$jumpforumid,$jumpforumtitle,$curforumid;
  global $hideprivateforums,$defaultselected,$forumjump,$bbuserinfo,$selectedone,$session,$useforumjump,$enableaccess;
  static $iforumcache,$ipermcache,$iaccesscache,$usergroupdef,$noperms,$firstdone;

  if ( !is_array($permission) ) {
    $permission = getpermissions(0,-1,$bbuserinfo['usergroupid']);
    $usergroupdef = $permission;
  }

  if ( !isset($iforumcache) ) {
    $iforums=$DB_site->query('SELECT forumid,parentid,displayorder,title,active FROM forum ORDER BY parentid,displayorder,forumid');
    while ($iforum=$DB_site->fetch_array($iforums)) {
      $iforumcache["$iforum[parentid]"]["$iforum[displayorder]"]["$iforum[forumid]"] = $iforum;
    }
    unset($iforum);
    $DB_site->free_result($iforums);

    $iforumperms=$DB_site->query("SELECT forumid,canview,cansearch FROM forumpermission WHERE usergroupid='$bbuserinfo[usergroupid]'");
    while ($iforumperm=$DB_site->fetch_array($iforumperms)) {
      $ipermcache["$iforumperm[forumid]"] = $iforumperm;
    }
    unset($iforumperm);
    $DB_site->free_result($iforumperms);

    $noperms['canview']=0;
    $noperms['cansearch']=0;

    if ($bbuserinfo['userid']!=0 and $enableaccess==1) {
      $iaccessperms=$DB_site->query("SELECT forumid,accessmask FROM access WHERE userid='$bbuserinfo[userid]'");
      while ($iaccessperm=$DB_site->fetch_array($iaccessperms)) {
        $iaccesscache["$iaccessperm[forumid]"] = $iaccessperm;
      }
      unset($iaccessperm);
      $DB_site->free_result($iaccessperms);
    } else {
      $iaccesscache = '';
    }
  }

  if ( !is_array($iforumcache["$forumid"]) ) {
    return;
  }

  while ( list($key1,$val1)=each($iforumcache["$forumid"]) ) {
    while ( list($key2,$forum)=each($val1) ) {

      if ( is_array($iaccesscache["$forum[forumid]"]) ) {
        if ($iaccesscache["$forum[forumid]"]['accessmask']==1) {
          $forumperms = $usergroupdef;
        } else {
          $forumperms = $noperms;
        }
      } else if ( is_array($ipermcache["$forum[forumid]"]) ) {
        $forumperms = $ipermcache["$forum[forumid]"];
      } else {
        $forumperms = $permission;
      }

      if (!$forumperms['canview'] or !$forumperms['cansearch'] or !$forum['active']) {
        if (!$firstdone) {
          $firstdone=1;
        } else {
          $noforums.=" OR ";
        }
        $noforums .= "thread.forumid='$forum[forumid]'";
      }
      $noforums .= getallforumsql2($forum['forumid'],0,$prependchars."--",$forumperms);
    } // while forums
  }

  return $noforums;
}

// ###################### Start getsearchforums #######################
function getsearchforums($forumid=-1,$addbox=1,$prependchars="",$permission="") {
  global $DB_site,$optionselected,$usecategories,$jumpforumid,$jumpforumtitle,$curforumid;
  global $hideprivateforums,$defaultselected,$forumjump,$bbuserinfo,$selectedone,$session,$useforumjump,$enableaccess;
  static $iforumcache,$ipermcache,$iaccesscache,$usergroupdef,$noperms;

  if ( !is_array($permission) ) {
    $permission = getpermissions(0,-1,$bbuserinfo['usergroupid']);
    $usergroupdef = $permission;
  }

  if ( !isset($iforumcache) ) {
    $iforums=$DB_site->query('SELECT forumid,parentid,displayorder,title FROM forum WHERE displayorder<>0 AND active=1 ORDER BY parentid,displayorder,forumid');
    while ($iforum=$DB_site->fetch_array($iforums)) {
      $iforumcache["$iforum[parentid]"]["$iforum[displayorder]"]["$iforum[forumid]"] = $iforum;
    }
    unset($iforum);
    $DB_site->free_result($iforums);

    $iforumperms=$DB_site->query("SELECT forumid,canview,cansearch FROM forumpermission WHERE usergroupid='$bbuserinfo[usergroupid]'");
    while ($iforumperm=$DB_site->fetch_array($iforumperms)) {
      $ipermcache["$iforumperm[forumid]"] = $iforumperm;
    }
    unset($iforumperm);
    $DB_site->free_result($iforumperms);

    $noperms['canview']=0;
    $noperms['cansearch']=0;

    if ($bbuserinfo['userid']!=0 and $enableaccess==1) {
      $iaccessperms=$DB_site->query("SELECT forumid,accessmask FROM access WHERE userid='$bbuserinfo[userid]'");
      while ($iaccessperm=$DB_site->fetch_array($iaccessperms)) {
        $iaccesscache["$iaccessperm[forumid]"] = $iaccessperm;
      }
      unset($iaccessperm);
      $DB_site->free_result($iaccessperms);
    } else {
      $iaccesscache = '';
    }
  }

  if ( !is_array($iforumcache["$forumid"]) ) {
    return;
  }

  while ( list($key1,$val1)=each($iforumcache["$forumid"]) ) {
    while ( list($key2,$forum)=each($val1) ) {

      if ( is_array($iaccesscache["$forum[forumid]"]) ) {
        if ($iaccesscache["$forum[forumid]"]['accessmask']==1) {
          $forumperms = $usergroupdef;
        } else {
          $forumperms = $noperms;
        }
      } else if ( is_array($ipermcache["$forum[forumid]"]) ) {
        $forumperms = $ipermcache["$forum[forumid]"];
      } else {
        $forumperms = $permission;
      }

      if ($forumperms['canview'] and $forumperms['cansearch']) {
        $jumpforumid=$forum['forumid'];
        $jumpforumtitle=$prependchars." $forum[title]";

        if ($curforumid==$jumpforumid) {
          $optionselected='selected';
          $selectedone=1;
        } else {
          $optionselected='';
        }
        eval("\$jumpforumbits .= \"".gettemplate('forumjumpbit')."\";");

        $jumpforumbits .= getsearchforums($jumpforumid,0,$prependchars."--",$forumperms);
      } // if can view
    } // while forums
  }

  return $jumpforumbits;
}

// Make first part of Search Nav Bar
$nav_url="search.php?s=$session[sessionhash]";
$nav_title="Search";
eval("\$navbits = \"".gettemplate('nav_linkon')."\";");

// ###################### Start intro #######################
if ($action=="intro") {

  $searchforumbits=getsearchforums();

  // Make Nav Bar
  eval("\$navbar = \"".gettemplate("navbar")."\";");

  eval("dooutput(\"".gettemplate("searchintro")."\");");
}

// ###################### Start generate searchid #######################
if ($action=="simplesearch") {

  if ($titleonly)
    $intitle=' AND intitle=1';
  else
    $intitle='';

  // get last search for this user and check floodcheck
  if ($prevsearch=$DB_site->query_first("SELECT searchid,dateline FROM search WHERE ".iif($bbuserinfo['userid']==0,"ipaddress='".vb_escape($ipaddress)."'","userid=$bbuserinfo[userid]")." ORDER BY dateline DESC LIMIT 1")) {
    if (time()-$prevsearch['dateline']<$searchfloodtime and $searchfloodtime!=0) { // and !ismoderator()) {
      eval("standarderror(\"".gettemplate("error_searchfloodcheck")."\");");
      exit;
    }
  }

  if ($searchuser!="" and strlen($searchuser)<=3 and $exactname!="yes") {
    eval("standarderror(\"".gettemplate("error_searchnametooshort")."\");");
  }

	$query=urldecode($query);
  $query=ereg_replace("[\n,]"," ",$query);
  $query=str_replace(". ", " ", $query);
  $query=preg_replace("/[\(\)\"':;\[\]!#{}_\-+\\\\]/s","",$query);
  $query=ereg_replace("( ){2,}", " ", $query);
  $query=trim($query);

  $showposts=iif($showposts,1,0);
  if ($query=="") {
    if (trim($searchuser)=="") {
      eval("standarderror(\"".gettemplate("error_searchspecifyterms")."\");");
      exit;
    }
    $wheresql = " 1=1 ";
  } else {
    include("./admin/badwords.php"); // get the stop word list

    $querywc=str_replace("%", "\\%", $query);
    $querywc=str_replace("_", "\\_", $querywc);
    if ($allowwildcards) {
      $querywc = str_replace("*","%",$querywc);
    }
    $querywc=preg_replace("/(%){1,}/s", '%', $querywc);

    $words=explode(" ",strtolower(vb_escape($querywc)));

    $firstword=1;
    $havewords=0;
    $badfirstterm=0;
    while (list($key,$val)=each($words)) {
      if ($val=="and") {
        $nextop="AND";
      } elseif ($val=="or") {
        $nextop="OR";
      } elseif ($val=="not") {
        $nextop="AND NOT";
      } else {
        if ($badwords["$val"]) {
          // this is a stop word, so strip don't process it as it will most likely
          // end up just screwing up the search
          continue;
        }
		if (strpos(" $val", '%') != false)
		{
			$wordlength = strlen(str_replace('%', '', $val)) + 1;
		}
		else
		{
			$wordlength = strlen($val);
		}
        if ($wordlength < $minsearchlength) {
          if ($firstword) {
            $badfirstterm = str_replace("%", "*", $val);
            continue;
          } else {
            if ($nextop=="AND") {
              $val = str_replace("%", "*", $val);
              eval("standarderror(\"".gettemplate("error_searchinvalidterm")."\");");
            } else {
              continue;
            }
          }
        }
        $sqlwords=$DB_site->query("SELECT wordid,title FROM word WHERE title LIKE '".vb_escape($val)."'");
        if ($DB_site->num_rows($sqlwords)) {
          while($thisword=$DB_site->fetch_array($sqlwords)) {
            $havewords=1;
            if ($firstword) {
              $tempwordid[] = $thisword['wordid'];
              $tempval = $val;
            } else {
              if ($nextop=="AND") {

                if ($nofirstword) {
                  if ($badfirstterm) {
                    $val = $badfirstterm;
                    eval("standarderror(\"".gettemplate("error_searchinvalidterm")."\");");
                  }
                  eval("standarderror(\"".gettemplate("error_searchnoresults")."\");");
                }
                $wordparts['AND']["$val"][] = $thisword['wordid'];
              } else {
                $wordparts["$nextop"][] = $thisword['wordid'];
              }

              if ( is_array($tempwordid) ) {

                while(list($key,$wordid)=each($tempwordid)) {
                  if ($nextop=="AND") {
                    if ($badfirstterm) {
                      $val = $badfirstterm;
                      eval("standarderror(\"".gettemplate("error_searchinvalidterm")."\");");
                    }
                    $wordparts['AND']["$tempval"][] = $wordid;
                  } else {
                    if ($badfirstterm) {
                      continue;
                    }
                    $wordparts["OR"][] = $wordid;
                  }
                }
                unset($tempwordid);
                unset($tempval);
              }
            }
          }
        } else {
          if ($firstword) {
            $nofirstword = 1;
          } else {
            if ($nextop=='AND') {
               eval("standarderror(\"".gettemplate("error_searchnoresults")."\");");
            }
            $nofirstword = 0;
          }
        }
        $DB_site->free_result($sqlwords);
        $firstword=0;
        $nextop="AND";
      }
    }

    if ( is_array($tempwordid) ) {
      if ($badfirstterm) {
        $val = $badfirstterm;
        eval("standarderror(\"".gettemplate("error_searchinvalidterm")."\");");
      }
      $wordparts["OR"] = $tempwordid;
    }

    if (!$havewords) {
      if ($badfirstterm) {
        $val = $badfirstterm;
        eval("standarderror(\"".gettemplate("error_searchinvalidterm")."\");");
      }
      eval("standarderror(\"".gettemplate("error_searchnoresults")."\");");
    }

   $andor = 'wordid IN (0';
    if ( is_array($wordparts["AND"]) ) {
      while ( list($key,$val)=each($wordparts["AND"]) ) {
        while( list($newkey,$newwordid)=each($val)) {
          $andor .= ",'$newwordid'";
          $andlist["$key"][] = "$newwordid";
        }
      }
    }

    if ( is_array($wordparts["OR"]) ) {
      while ( list($key,$val)=each($wordparts["OR"]) ) {
        $andor .= ",'$val'";
        $orlist[] = "$val";
      }
    }

    if ( is_array($wordparts["AND NOT"]) ) {
      while ( list($key,$val)=each($wordparts["AND NOT"]) ) {
        $andor .= ",'$val'";
        $notlist[] = "$val";
      }
    }
    $andor .= ')';

    unset($postlists);
    $goodpostlist = '';

    $posts=$DB_site->query("SELECT postid,wordid FROM searchindex WHERE $andor $intitle");
    while($post=$DB_site->fetch_array($posts)) {
      $postlists["$post[postid]"] .= " ,$post[wordid],";
    }

    if (!$postlists) {
      eval("standarderror(\"".gettemplate("error_searchnoresults")."\");");
    }
    while ( list($key,$val)=each($postlists) ) {
      $good = 1;
      if ( is_array($andlist) ) {
        reset($andlist);
        while ( list($keyw,$id)=each($andlist) ) {
          $thisgood=0;
          while( list($keynew,$newid)=each($id) ) {
            if ( strlen(strpos($val, ",$newid,")) ) {
              $thisgood = 1;
              break;
            }
          }
          if ($thisgood!=1) {
            $good = 0;
            break;
          }
        }
      }

      if ( is_array($orlist) AND $good!=1 ) {
        reset($orlist);
        while ( list($keyw,$id)=each($orlist) ) {
          if ( strlen(strpos($val, ",$id,")) ) {
            $good = 1;
            break;
          }
        }
      }

      if ( is_array($notlist) AND $good!=0 ) {
        reset($notlist);
        while ( list($keyw,$id)=each($notlist) ) {
          if ( strlen(strpos($val, ",$id,")) ) {
            $good = 0;
            break;
          }
        }
      }

      if ($good==1) {
        $goodpostlist .= "$key,";
      }
    }

    $wheresql = " 1=1 ";

    if (!$goodpostlist) {
      eval("standarderror(\"".gettemplate("error_searchnoresults")."\");");
    }
  }

  // parse username
  // get userids
  if ($searchuser!="") {
    if ($exactname=="yes") {
      $users=$DB_site->query("SELECT userid FROM user WHERE username='".vb_escape(htmlspecialchars($searchuser))."'");
    } else {
      $users=$DB_site->query("SELECT userid FROM user WHERE INSTR(LCASE(username),'".vb_escape(htmlspecialchars(strtolower($searchuser)))."')>0");
    }
    if (trim($query)=="" and $DB_site->num_rows($users)==0) {
      eval("standarderror(\"".gettemplate("error_searchspecifyterms")."\");");
      exit;
    }

    $userssql=" AND (1=0 ";
    while ($user=$DB_site->fetch_array($users)) {
      $userssql.=" OR post.userid='$user[userid]'";
    }
    $userssql.=")";

    if (!trim($query)) {
      $goodpostlist="";
      $posts=$DB_site->query("SELECT postid FROM post WHERE 1=1 $userssql");
      while($thispost=$DB_site->fetch_array($posts)) {
        $goodpostlist .= "$thispost[postid],";
      }

      if (!$goodpostlist) {
        eval("standarderror(\"".gettemplate("error_searchnoresults")."\");");
      }
    } else {
      $wheresql.=$userssql;
    }
  }

  // parse forumids
  if (isset($forumchoice)) {
    $forumchoice=verifyid("forum",$forumchoice,0);
  } else {
    $forumchoice=-1;
  }
  if ($forumchoice!=-1 and $forumchoice!=0) {

    $getperms=getpermissions($forumchoice);
    if (!$getperms['canview'] or !$getperms['cansearch']) {
      show_nopermission();
    }

    $forums=$DB_site->query("SELECT forum.forumid FROM forum LEFT JOIN forumpermission ON (forum.forumid=forumpermission.forumid AND forumpermission.usergroupid='$bbuserinfo[usergroupid]') WHERE INSTR(CONCAT(',',parentlist,','),',$forumchoice,')>0 AND (ISNULL(forumpermission.forumid) OR forumpermission.cansearch=1)");
    if ($DB_site->num_rows($forums)==1) {
      $wheresql.=" AND thread.forumid='$forumchoice' ";
    } else {
      $wheresql.=" AND thread.forumid IN ('$forumchoice'";
      while ($forum=$DB_site->fetch_array($forums)) {
        $wheresql.=",$forum[forumid]";
      }
      $wheresql.=") ";
    }
  } else {
    $wheresql.=getallforumsql();
  }

  // parse dates
  // before or after?
  if ($searchdate!=-1) {
    $datesql=" AND post.dateline";
    if ($beforeafter=="before") {
      $datesql.="<=";
    } else {
      $datesql.=">=";
    }
    $datesql.="'".(time()-$searchdate*3600*24)."'";

    $wheresql.=$datesql;

  }

  // insert query into db
  $DB_site->query("INSERT INTO search (searchid,query,postids,dateline,querystring,showposts,userid,ipaddress) VALUES (NULL,'".vb_escape($wheresql)."','".vb_escape($goodpostlist)."',".time().",'".vb_escape($query)."','".intval($showposts)."',$bbuserinfo[userid],'".vb_escape($ipaddress)."')");
  $searchid=$DB_site->insert_id();

  eval("standardredirect(\"".gettemplate("redirect_search")."\",\"search.php?s=$session[sessionhash]&amp;action=showresults&amp;searchid=$searchid&amp;sortby=".urlencode($sortby)."&amp;sortorder=".urlencode($sortorder)."\");");

}

// ###################### Start get new #######################
if ($action=="getnew") {
  // generate query
  // do it!
  if ($bbuserinfo['userid'] != 0 or ($bbuserinfo['lastvisit'] != 0 and $bbuserinfo['lastvisit'] != $ourtimenow)) {
    $forumsql=getallforumsql();

		if (isset($forumid)) {
			$forums=$DB_site->query("SELECT forumid FROM forum WHERE INSTR(CONCAT(',',parentlist,','),',".vb_escape($forumid).",')>0");
			$forumsql.=" AND forumid IN (0";
			while ($forum=$DB_site->fetch_array($forums)) {
				$forumsql.=",$forum[forumid]";
			}
			$forumsql.=") ";
		}


    // get date:
    $datesql=" AND thread.lastpost>=".$bbuserinfo['lastvisit'];

    $wheresql="1=1".$forumsql.$datesql;
    $wheresql.=" AND thread.open<>10";

    // insert query into db
    $DB_site->query("INSERT INTO search (searchid,query,dateline,querystring,showposts,userid,ipaddress) VALUES (NULL,'".vb_escape($wheresql)."',".time().",'".vb_escape($query)."',0,$bbuserinfo[userid],'".vb_escape($ipaddress)."')");
    $searchid=$DB_site->insert_id();

    eval("standardredirect(\"".gettemplate("redirect_search")."\",\"search.php?s=$session[sessionhash]&amp;action=showresults&amp;getnew=true&amp;searchid=$searchid\");");
  } else {
    $action="getdaily";
  }
}

// ###################### Start get daily #######################
if ($action=="getdaily") {
  // get allowable forums:
  $forumsql=getallforumsql();

	if (isset($forumid)) {
		$forums=$DB_site->query("SELECT forumid FROM forum WHERE INSTR(CONCAT(',',parentlist,','),',".vb_escape($forumid).",')>0");
		$forumsql.=" AND forumid IN (0";
		while ($forum=$DB_site->fetch_array($forums)) {
			$forumsql.=",$forum[forumid]";
		}
		$forumsql.=") ";
	}

  // get date:
  $days = intval($days);
  if ($days < 1) {
    $days = 1;
  }
  $datesql=" AND thread.lastpost>=".(time() - (24 * 60 *60 * $days));

  $wheresql="1=1".$forumsql.$datesql;
  $wheresql.=" AND thread.open<>10";

  // insert query into db
  $DB_site->query("INSERT INTO search (searchid,query,dateline,querystring,showposts,userid,ipaddress) VALUES (NULL,'".vb_escape($wheresql)."',".time().",'".vb_escape($query)."',0,$bbuserinfo[userid],'".vb_escape($ipaddress)."')");
  $searchid=$DB_site->insert_id();

  eval("standardredirect(\"".gettemplate("redirect_search")."\",\"search.php?s=$session[sessionhash]&amp;action=showresults&amp;getnew=true&amp;searchid=$searchid\");");
}

// ###################### Start posts by user x #######################
if ($action=="finduser") {
  // get allowable forums:
  $forumsql=getallforumsql();

	if (empty($userid))
	{
		$userinfo = $DB_site->query_first("SELECT userid FROM user WHERE username='" . vb_escape(htmlspecialchars($username)) . "'");
		$userid = intval($userinfo['userid']);
	}

  // get user:
  $usersql=" AND post.userid='$userid'";

  $wheresql="1=1".$forumsql.$usersql;

  // insert query into db
  $DB_site->query("INSERT INTO search (searchid,query,dateline,querystring,showposts,userid,ipaddress) VALUES (NULL,'".vb_escape($wheresql)."',".time().",'".vb_escape($query)."',1,$bbuserinfo[userid],'".vb_escape($ipaddress)."')");
  $searchid=$DB_site->insert_id();

  eval("standardredirect(\"".gettemplate("redirect_search")."\",\"search.php?s=$session[sessionhash]&amp;action=showresults&amp;searchid=$searchid\");");
}


// ###################### Start display results #######################
if ($action=="showresults") {
  // do query
  // display results!
  $search=verifyid("search",$searchid,1,1);
  if ($search['userid'] != $bbuserinfo['userid']) {
		$searchuserinfo = getuserinfo( $search['userid'] );
		if ( $searchuserinfo['usergroupid'] != $bbuserinfo['usergroupid'] or $enableaccess ) {
//    	show_nopermission();
    	header("Location: search.php?action=simplesearch&showposts=$search[showposts]&query=" . urlencode( $search['querystring'] ) . "&sortby=$sortby&sortorder=$sortorder&searchdate=-1");
		}

  }

  if ($search['querystring']!="") {
    $search['querystring']=urlencode($search['querystring']);
    $highlightwords="&amp;highlight=$search[querystring]";
  } else {
    $highlightwords="";
  }

  $newurl = "&amp;action=showresults&amp;searchid=$searchid&amp;sortby=" . htmlspecialchars($sortby) . "&amp;sortorder=" . htmlspecialchars($sortorder);

  // parse search order
  switch ($sortby) {
    case "replies":
      $orderbysql="thread.replycount";
      break;

    case "views":
      $orderbysql="thread.views";
      break;

    case "poster":
      $orderbysql="usrname";
      break;

    case "forum":
      $orderbysql="forum.title";
      break;

    case "title":
      $orderbysql="posttext";
      break;

    case "lastpost":

    default:
      $orderbysql="post.dateline";

  }

  if ($sortorder!="ascending") {
    $orderbysql.=" DESC";
  }

  $orderbysql.=",post.dateline DESC";

  if ($search['showposts']==0) {

    // parse search order
    switch ($sortby) {
      case "replies":
        $orderbysql="thread.replycount";
        break;

      case "views":
        $orderbysql="thread.views";
        break;

      case "poster":
        $orderbysql="thread.postusername";
        break;

      case "forum":
        $orderbysql="forum.title";
        break;

      case "title":
        $orderbysql="thread.title";
        break;

      case "lastpost":

      default:
        $orderbysql="thread.lastpost";

    }

    if ($sortorder!="ascending") {
      $orderbysql.=" DESC";
    }

    $orderbysql.=",thread.lastpost DESC";

    if ($showdots and $bbuserinfo['userid'] >= 1) {
           $dotuserid = 'DISTINCT post.userid,';
           $dotjoin = "LEFT JOIN post ON (thread.threadid = post.threadid AND post.userid = '$bbuserinfo[userid]' AND post.visible = 1)";
    } else {
      $dotuserid = '';
      $dotjoin = '';
    }

    if (!$search['postids']) {
      $getnum=$DB_site->query_first("SELECT COUNT(*) AS threads FROM thread
      ".iif(strpos($search['query'],"searchindex")>0,",searchindex","")."
          WHERE thread.visible=1 AND $search[query]");
          $countmatches=$getnum['threads'];
    } else {
      $getnum=$DB_site->query("SELECT DISTINCT thread.threadid FROM thread
      ".iif(strpos($search['query'],"searchindex")>0,",searchindex","").
      ",post
      WHERE thread.threadid=post.threadid AND thread.visible=1 AND $search[query]
      AND (post.postid IN ($search[postids]0))");
      $countmatches=$DB_site->num_rows($getnum);
    }

	sanitize_pageresults($countmatches, $pagenumber, $perpage, $searchperpage, $searchperpage);
	$newurl.="&amp;perpage=$perpage";

        $limitlower=($pagenumber-1)*$perpage+1;
        $limitupper=($pagenumber)*$perpage;

        if ($limitupper>$countmatches) {
          $limitupper=$countmatches;
          if ($limitlower>$countmatches) {
            $limitlower=$countmatches-$perpage;
          }
        }
        if ($limitlower<=0) {
          $limitlower=1;
        }

    $getthreadids=$DB_site->query("
    SELECT
    ".iif($search['postids']," DISTINCT ","")."
      thread.threadid
    FROM
      thread
      ".iif(strpos($search['query'],"searchindex")>0,",searchindex","").iif($search['postids'],",post","").
      iif(strpos(" $orderbysql","forum.") or strpos($search['query'],"forum.")," LEFT JOIN forum ON thread.forumid=forum.forumid","")."

    WHERE
      thread.visible=1 AND $search[query]
      ".iif($search['postids']," AND thread.threadid=post.threadid AND (post.postid IN ($search[postids]0))","")."
                ORDER BY
      $orderbysql
    LIMIT ".($limitlower-1).", $perpage
    ");

                $threadids="thread.threadid IN (0";
                while ($thread=$DB_site->fetch_array($getthreadids)) {
                        $threadids.=",".$thread['threadid'];
                }
                $threadids.=")";


    $sql="
    SELECT $dotuserid $distinct
      thread.threadid,thread.threadid AS postid,thread.title AS threadtitle,thread.iconid AS threadiconid,thread.replycount,
      thread.views,thread.pollid,thread.open,thread.lastpost AS postdateline,thread.lastpost,thread.lastposter,
      forum.forumid,forum.title AS forumtitle,forum.allowicons,attach,
      thread.postusername AS usrname,
      user.userid AS postuserid,
      threadicon.iconpath AS threadiconpath,threadicon.title AS threadicontitle
    FROM
      thread,forum".iif(strpos($search['query'],"searchindex")>0,",searchindex","")."
    LEFT JOIN user ON user.username=thread.postusername
    LEFT JOIN icon AS threadicon ON thread.iconid=threadicon.iconid
    $dotjoin
    WHERE
      thread.forumid=forum.forumid AND $threadids
    ORDER BY
      $orderbysql";

    $searchtemplatebit = "searchresultbit_threadonly";
  } else { // Show Posts
    unset($ignore);
    $ignorelist = explode(' ', $bbuserinfo['ignorelist']);
	while ( list($key, $val)=each($ignorelist) ) {
	    $ignore[$val] = 1;
    }
    if ($ignore) {
      eval("\$ignoreduser = \"".gettemplate("threadreviewbit_ignore")."\";");
    } else {
      $ignoreduser = '';
    }

    if ($search['postids']) {
      $newpostsql=" post.postid IN ($search[postids]0) AND ";
    } else {
      $newpostsql = '';
    }

    $getnum=$DB_site->query_first("SELECT COUNT(*) AS posts FROM post
    ".
    iif(strlen(strpos($search['query'],"thread."))," LEFT JOIN thread ON thread.threadid=post.threadid ","").
    " WHERE post.visible=1 AND $newpostsql $search[query]");
    $countmatches=$getnum['posts'];

    sanitize_pageresults($countmatches, $pagenumber, $perpage, $searchperpage, $searchperpage);

    if ($countmatches==0) {
      eval("standarderror(\"".gettemplate("error_searchnoresults")."\");");
      exit;
    }

                $limitlower=($pagenumber-1)*$perpage+1;
                $limitupper=($pagenumber)*$perpage;

                if ($limitupper>$countmatches) {
                        $limitupper=$countmatches;
                        if ($limitlower>$countmatches) {
                                $limitlower=$countmatches-$perpage;
                        }
                }
                if ($limitlower<=0) {
                        $limitlower=1;
                }

    $getpostids=$DB_site->query("
        SELECT
          post.postid, thread.visible".
          iif(strlen(strpos(" $orderbysql","posttext")),",IF(post.title='',LEFT(post.pagetext,50),post.title) AS posttext","").
          iif(strlen(strpos(" $orderbysql","usrname")),",IF(post.userid=0,post.username,user.username) AS usrname","").
          "
        FROM
          post".
          iif(strlen(strpos($search['query'],"searchindex")),",searchindex","").
          " LEFT JOIN thread ON thread.threadid=post.threadid".
          iif(strlen(strpos(" $orderbysql","forum.")) or strlen(strpos($search['query'],"forum."))," LEFT JOIN forum ON thread.forumid=forum.forumid ","").
          iif(strlen(strpos(" $orderbysql","usrname"))," LEFT JOIN user ON (post.userid=user.userid) ","").
          "
        WHERE
          post.visible=1 AND thread.visible=1 AND $newpostsql $search[query]
                    ORDER BY
          $orderbysql
        LIMIT ".($limitlower-1).", $perpage
        ");
                $postids="post.postid IN (0";
                while ($post=$DB_site->fetch_array($getpostids)) {
                        $postids.=",".$post['postid'];
                }
                $postids.=")";

    $sql="
    SELECT
      post.postid,post.title AS posttitle,post.dateline AS postdateline,post.userid AS postuserid,post.iconid AS posticonid,LEFT(post.pagetext,250) AS pagetext,
      thread.threadid,thread.title AS threadtitle,thread.iconid AS threadiconid,thread.replycount,thread.views,thread.pollid,thread.open,thread.lastpost,
      forum.forumid,forum.title AS forumtitle,forum.allowicons,user.username,
      IF(post.title='',LEFT(post.pagetext,50),post.title) AS posttext,
      IF(post.userid=0,post.username,user.username) AS usrname,
      posticon.iconpath AS posticonpath,posticon.title AS posticontitle,
      threadicon.iconpath AS threadiconpath,threadicon.title AS threadicontitle
    FROM
      post".iif(strpos($search['query'],"searchindex")>0,",searchindex","").",thread
    LEFT JOIN forum ON forum.forumid=thread.forumid
    LEFT JOIN user ON user.userid=post.userid
    LEFT JOIN icon AS threadicon ON thread.iconid=threadicon.iconid
    LEFT JOIN icon AS posticon ON post.iconid=posticon.iconid
    WHERE
      $postids AND thread.threadid=post.threadid
    ORDER BY
      $orderbysql";

    $searchtemplatebit = "searchresultbit";
  }

  $searchresults=$DB_site->query($sql);

  if ($countmatches==0) {

    eval("standarderror(\"".gettemplate("error_searchnoresults")."\");");
    exit;

  } else {

    $counter=0;
    $postdone = array();
    $searchresultbits = '';
    while ($searchresult=$DB_site->fetch_array($searchresults) and $counter++<$perpage) {
      if ($postdone[$searchresult['postid']]) {
        $counter--;
        continue;
      }
      $postdone[$searchresult['postid']]=1;
      $searchresult['postdate']=vbdate($dateformat,$searchresult['postdateline']);
      $searchresult['posttime']=vbdate($timeformat,$searchresult['postdateline']);

      if ($ignore[$searchresult['postuserid']]) {
        $searchresult['pagetext'] = $ignoreduser;
	    $searchresult['posttitle'] = $ignoreduser;
      } else {
        // get first 30 chars of post title
        if (trim($searchresult['posttitle'])=="") {
          $searchresult['posttitle']=substr($searchresult['pagetext'],0,50);
          if (strlen($searchresult['posttitle'])>50) {
            $spacepos=strpos($searchresult['posttitle']." "," ",50);
            if ($spacepos!=0) {
              $searchresult['posttitle']=substr($searchresult['posttitle'],0,$spacepos)."...";
            }
          }
        }
        if ($wordwrap!=0) {
          $searchresult['posttitle']=dowordwrap($searchresult['posttitle']);
        }
        $searchresult['posttitle']=str_replace("<","&lt;",$searchresult['posttitle']);

        // get first 100 chars of page text
        if (strlen($searchresult['pagetext'])>200) {
          $spacepos=strpos($searchresult['pagetext']." "," ",200);
          if ($spacepos!=0) {
            $searchresult['pagetext']=censortext(substr($searchresult['pagetext'],0,$spacepos))."...";
          }
        }

        $searchresult['pagetext']=nl2br(str_replace('<', '&lt;', $searchresult['pagetext']));

        if ($wordwrap!=0) {
          $searchresult['pagetext']=dowordwrap($searchresult['pagetext']);
        }
      }

      if ($wordwrap!=0) {
        $searchresult['threadtitle']=dowordwrap($searchresult['threadtitle']);
      }

      if ($searchresult['attach']>0) {
        $paperclip="<img src=\"{imagesfolder}/paperclip.gif\" alt=\"$searchresult[attach] Attachment(s)\" border=\"0\">";
      } else {
        unset($paperclip);
      }

      // do post icons
      if ($searchresult['postdateline']>$bbuserinfo['lastvisit']) {
        $searchresult['foldericon']="<img src=\"{imagesfolder}/posticonnew.gif\" border=\"0\" alt=\"\">";
      } else {
        $searchresult['foldericon']="<img src=\"{imagesfolder}/posticon.gif\" border=\"0\" alt=\"\">";
      }
      if (!$searchresult['allowicons'] or $searchresult['posticonid']==0) {
        $searchresult['posticon']="&nbsp;";
      } else {
        $searchresult['posticon']="<img src=\"$searchresult[posticonpath]\" alt=\"$searchresult[posticontitle]\" width=\"15\" height=\"15\" border=\"0\">";
      }

      // do thread icons
      if (!$searchresult['allowicons'] or $searchresult['threadiconid']==0) {
        if ($showdeficon) {
                  $searchresult['threadicon']='<img src="{imagesfolder}/icons/icon1.gif" border="0" alt="">';
                } else {
          $searchresult['threadicon']="&nbsp;";
        }
      } else {
        $searchresult['threadicon']="<img src=\"$searchresult[threadiconpath]\" alt=\"$searchresult[threadicontitle]\" width=\"15\" height=\"15\" border=\"0\">";
      }

      unset($searchresult['typeprefix']);
      if ($searchresult['pollid']!=0) {
        $searchresult['typeprefix'] = $pollthreadprefix;
        if ($searchresult['allowicons']) {
          $searchresult['threadicon']="<img src=\"{imagesfolder}/poll.gif\" alt=\"Poll\" width=\"15\" height=\"15\" border=\"0\">";
        }
      }

      $searchresult['gotonew']="";

      $searchresult['newoldhot']="folder";
      if (!$searchresult['open'])
      {
        $searchresult['newoldhot']="lock".$searchresult['newoldhot'];
      }
      if (($searchresult['replycount']>=$hotnumberposts or $searchresult['views']>=$hotnumberviews) and $usehotthreads) {
        $searchresult['newoldhot']="hot".$searchresult['newoldhot'];
      }
      if ($bbuserinfo['lastvisitdate']=="Never")
      {
        $searchresult['newoldhot']="new".$searchresult['newoldhot'];
      }
      elseif ($searchresult['lastpost']>$bbuserinfo['lastvisit'])
      {
         if (get_bbarraycookie('threadview', $searchresult['threadid']) < $searchresult['lastpost'])
                 {
                    $searchresult['newoldhot']="new".$searchresult['newoldhot'];
         }
         unset($thread); // without this, the following assignment doesn't work on my forum! -freddie
         $thread['threadid']=$searchresult['threadid'];
         eval("\$searchresult[gotonew] = \"".gettemplate("forumdisplay_gotonew")."\";");
      }
      if ($showdots and $bbuserinfo['userid'] >= 1 and $bbuserinfo['userid'] == $searchresult['userid']) {
                 $searchresult['newoldhot'] = "dot_" . $searchresult['newoldhot'];
      }

	  $frmjmpsel['search'] = " selected class=\"fjsel\"";
	  makeforumjump();
      eval ("\$searchresultbits .= \"".gettemplate("$searchtemplatebit")."\";");
    }

    // draw page nav

	$pagenav = getpagenav($countmatches,"search.php?s=$session[sessionhash]".$newurl);

  }
  if ($search['showposts']==1) {
    $navbits .= gettemplate("nav_joiner",0);
    $nav_url="";
    $nav_title="Post Results";
    eval("\$navbits .= \"".gettemplate('nav_linkoff')."\";");
    eval("\$navbar = \"".gettemplate("navbar")."\";");
    eval("dooutput(\"".gettemplate("searchresults")."\");");
  } else {
    // Make Nav Bar
    $nav_title="Thread Results";
    $navbits .= gettemplate("nav_joiner",0);
        $nav_url="";
        eval("\$navbits .= \"".gettemplate('nav_linkoff')."\";");
    eval("\$navbar = \"".gettemplate("navbar")."\";");
    eval("dooutput(\"".gettemplate("searchresults_threadonly")."\");");
  }
}

?>
