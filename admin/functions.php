<?php
require_once __DIR__ . '/php8compat.php';
require_once __DIR__ . '/lang.php';
error_reporting(7);

// start prep shutdown function
$noshutdownfunc = 0; // #CHANGE ME IF YOU CAN'T USE register_shutdown_function
$ourtimenow = time(); // Make this available to all files

// Defined constants used for user field.
// Hope to convert all user fields into one integer.
define ("SHOWSIGNATURES", 1);
define ("SHOWAVATARS", 2);
define ("SHOWIMAGES", 4);
define ("SHOWVBCODE", 8);

unset($templatecache);

function moo($str)
{
	return $str;
}

// ####################### Start sanitize_perpage #####################
function sanitize_perpage(&$perpage, $max, $default = 25)
{
	$perpage = intval($perpage);

	if ($perpage == 0)
	{
		$perpage = $default;
	}
	else if ($perpage < 1)
	{
		$perpage = 1;
	}
	else if ($perpage > $max)
	{
		$perpage = $max;
	}
}

// ####################### Start sanitize_pageresults #####################
function sanitize_pageresults($numresults, &$page, &$perpage, $maxperpage = 20, $defaultperpage = 20)
{
	$perpage = intval($perpage);
	if ($perpage < 1)
	{
		$perpage = $defaultperpage;
	}
	else if ($perpage > $maxperpage)
	{
		$perpage = $maxperpage;
	}

	$numpages = ceil($numresults / $perpage);

	if ($page < 1)
	{
		$page = 1;
	}
	else if ($page > $numpages)
	{
		$page = $numpages;
	}
}

// ####################### Start Regstring #####################
function regstring($length)
{
	$chars = '2346789ABCDEFGHJKLMNPRTWXYZ';
	// . 'abcdefghjkmnpqrstwxyz'; easier to read with all uppercase

	for ($x = 1; $x <= $length; $x++)
	{
		$number = rand(1, strlen($chars));
		$word .= substr($chars, $number - 1, 1);
 	}

 	return $word;
}

// ###################### Start vbmail #######################
// vBulletin wrapper for PHP's 'mail()' function
function vbmail($toemail, $subject, $message, $from = '', $headers = '', $username = '') {
	global $bbtitle, $webmasteremail;

	$toemail = trim($toemail);

	if ($toemail) {

		$subject = trim($subject);
		$message = preg_replace("/(\r\n|\r|\n)/s", "\r\n", trim($message));
		$from = trim($from);
		$username = trim($username);

		// work out the 'From' header
		if ($from == '') {
			$headers = "From: \"$bbtitle Mailer\" <$webmasteremail>\r\n" . $headers;
		} else {
			$headers = 'From: "' . iif($username, "$username @ $bbtitle", $from) . "\" <$from>\r\n" . $headers;
		}

		// actually send the email message
		mail($toemail, $subject, $message, trim($headers));

		return true;

	} else {
		return false;
	}
}

// ###################### Start getpagenav #######################
// template-based page splitting system from 3dfrontier.com :)
function getpagenav($results,$address) {
	global $perpage,$pagenumber,$pagenavpages;

	if ($results <= $perpage) {
		return "";
	}

	$totalpages = ceil($results/$perpage);

	if ($pagenumber>1) {
		$prevpage = $pagenumber-1;
		eval("\$prevlink = \"".gettemplate("pagenav_prevlink")."\";");
	}
	if ($pagenumber<$totalpages) {
		$nextpage = $pagenumber+1;
		eval("\$nextlink = \"".gettemplate("pagenav_nextlink")."\";");
	}
	while ($curpage++<$totalpages) {
		if ( ( $curpage <= $pagenumber-$pagenavpages || $curpage >= $pagenumber+$pagenavpages ) && $pagenavpages!=0 ) {
			if ($curpage==1) {
				eval("\$firstlink = \"".gettemplate("pagenav_firstlink")."\";");
			}
		    if ($curpage==$totalpages) {
				eval("\$lastlink = \"".gettemplate("pagenav_lastlink")."\";");
			}
		} else {
			if ($curpage==$pagenumber) {
				eval("\$pagenav .= \"".gettemplate("pagenav_curpage")."\";");
			} else {
				eval("\$pagenav .= \"".gettemplate("pagenav_pagelink")."\";");
			}
		}
	}
	eval("\$pagenav = \"".gettemplate("pagenav")."\";");
	return $pagenav;

}

// ###################### Start buildpostbit #######################
$firstnew = 0;
function getpostbit($post) {
// sorts through all the stuff to return the postbit template

	// user
	global $bbuserinfo,$session,$ignore,$cookietimeout;
	// showthread
	global $counter,$firstnew,$sigcache,$highlight,$postid,$forum;
	// global options
	global $showdeficon,$displayemails,$enablepms,$allowsignatures,$wordwrap,$dateformat,$timeformat,$logip,$replacewords,$postsperday,$avatarenabled,$registereddateformat,$viewattachedimages;

	$datecut = time() - $cookietimeout;

	if ($counter%2==0) {
		$post['backcolor']="{firstaltcolor}";
		$post['bgclass'] = "alt1";
	} else {
		$post['backcolor']="{secondaltcolor}";
		$post['bgclass'] = "alt2";
	}

	// find first new post
	if (isset($bbuserinfo['lastvisit'])) {
		if ($post['dateline']>$bbuserinfo['lastvisit'] and $firstnew==0) {
			$firstnew=1;
			$post['firstnewinsert']="<a name=\"newpost\"></a>";
		} else {
			$post['firstnewinsert']="";
		}
	}

	$post['postdate']=vbdate($dateformat,$post['dateline']);
	$post['posttime']=vbdate($timeformat,$post['dateline']);

	if ($wordwrap!=0) {
		$post['title']=dowordwrap($post['title']);
	}

	if ($post['attachmentid']!=0 and $post['attachmentvisible']) {
		$post['attachmentextension']=strtolower(getextension($post['filename']));
		$post['filename'] = censortext(htmlspecialchars($post['filename']));
		if ($post['attachmentextension']=="gif" or $post['attachmentextension']=="jpg" or $post['attachmentextension']=="jpeg" or $post['attachmentextension']=="jpe" or $post['attachmentextension']=="png") {
			if (($viewattachedimages) and ($bbuserinfo['userid']==0 or $bbuserinfo['showimages'])) {
				eval("\$post[attachment] = \"".gettemplate("postbit_attachmentimage")."\";");
			} else {
				eval("\$post[attachment] = \"".gettemplate("postbit_attachment")."\";");
			}
		} else {
			eval("\$post[attachment] = \"".gettemplate("postbit_attachment")."\";");
		}
	} else {
		$post['attachment']="";
	}

	if ($post['edituserid']!=0) {
		if ($post['edituserid']!=$post['userid']) {
			$edituser=getuserinfo($post['edituserid']);
		} else {
			$edituser = $post;
		}
		$post['edittime']=vbdate($timeformat,$post['editdate']);
		$post['editdate']=vbdate($dateformat,$post['editdate']);
		eval("\$post[editedby] = \"".gettemplate("postbit_editedby")."\";");
	} else {
		$post['editedby']="";
	}

	if ($post['dateline']>$bbuserinfo['lastvisit']) {
		$post['foldericon']="<img src=\"{imagesfolder}/posticonnew.gif\" border=\"0\" alt=\"New Post\">";
	} else {
		$post['foldericon']="<img src=\"{imagesfolder}/posticon.gif\" border=\"0\" alt=\"Old Post\">";
	}
	if (!$forum['allowicons'] or $post['iconid']==0) {
		if ($showdeficon) {
			$post['icon']='<img src="{imagesfolder}/icons/icon1.gif" border="0" alt="">';
		}
	} else {
		/*
		unset ($iconwidth);
		unset($iconheight);
		$imginfo=@getimagesize($post[iconpath]);
		if ($imginfo[2]==1 or $imginfo[2]==2) { // We have a .gif or .jpg
		$iconwidth = "width=\"$imginfo[0]\"";
		$iconheight = "height=\"$imginfo[1]\"";
		}
		*/
		$post['icon']="<img src=\"$post[iconpath]\" alt=\"$post[icontitle]\" border=\"0\">";
	}

	if ($post['userid']!=0) {
		unset($onlinestatus);
		if ($post['lastactivity'] > $datecut and !$post['invisible'] and $post['lastvisit'] != $post['lastactivity']) {
			eval("\$onlinestatus = \"".gettemplate("postbit_online")."\";");
		} else {
			eval("\$onlinestatus = \"".gettemplate("postbit_offline")."\";");
		}
		if ($post['avatarid']!=0) {
			$avatarurl=$post['avatarpath'];
		} else {
			if ($post['hascustomavatar'] and $avatarenabled) {
				$avatarurl="avatar.php?userid=$post[userid]&amp;dateline=$post[avatardateline]";
			} else {
				$avatarurl="";
			}
		}
		if ($avatarurl=="" or ($bbuserinfo['userid']>0 and !($bbuserinfo['showavatars']))) {
			$post['avatar']="";
		} else {
			eval("\$post[avatar] = \"".gettemplate("postbit_avatar")."\";");
		}
		if ($post['customtitle']==2) {
			$post['usertitle'] = htmlspecialchars($post['usertitle']);
		}
		$jointime = (time() - $post['joindate']) / 86400; // Days Joined
		if ($jointime < 1) { // User has been a member for less than one day.
			$postsperday = "$post[posts]";
		} else {
			$postsperday = sprintf("%.2f",($post['posts'] / $jointime));
		}

		$post['joindate']=vbdate($registereddateformat,$post['joindate']);

		if ($post['showemail'] and $displayemails) {
			eval("\$post[useremail] = \"".gettemplate("postbit_useremail")."\";");
		} else {
			$post['useremail']="";
		}
		$userinfo=$post;
		if ($post['icq']!="") {
			eval("\$post[icqicon] = \"".gettemplate("icq")."\";");
		} else {
			$post['icq']="";
		}
		if ($post['aim']!="") {
			$userinfo['aim'] = urlencode($userinfo['aim']);
			eval("\$post[aimicon] = \"".gettemplate("aim")."\";");
		} else {
			$post['aim']="";
		}
		if ($post['yahoo']!="") {
			eval("\$post[yahooicon] = \"".gettemplate("yahoo")."\";");
		} else {
			$post['yahoo']="";
		}

		if ($post['homepage']!="" and $post['homepage']!="http://") {
			eval("\$post[homepage] = \"".gettemplate("postbit_homepage")."\";");
		} else {
			$post['homepage']="";
		}

		if ($post['receivepm'] and $enablepms==1) {
			eval("\$post[pmlink] = \"".gettemplate("postbit_sendpm")."\";");
		} else {
			$post['pmlink'] = "";
		}

		eval("\$post[profile] = \"".gettemplate("postbit_profile")."\";");
		eval("\$post[search] = \"".gettemplate("postbit_search")."\";");
		eval("\$post[buddy] = \"".gettemplate("postbit_buddy")."\";");

		if ($post['showsignature'] and $allowsignatures and trim($post['signature'])!="" and ($bbuserinfo['userid']==0 or $bbuserinfo['showsignatures'])) {
			if (!isset($sigcache["$post[userid]"])) {
				$post['signature']=bbcodeparse($post['signature'],0,$allowsmilies);
				eval("\$post[signature] = \"".gettemplate("postbit_signature")."\";");
				$sigcache["$post[userid]"] = $post['signature'];
			} else {
				$post['signature'] = $sigcache["$post[userid]"];
			}
		} else {
			$post['signature'] = "";
		}
	} else {
		$postsperday=0;
		$post['username']=$post['postusername'];
		$post['usertitle']="Guest";
		$post['joindate']="Not Yet";
		$post['posts']="N/A";

		$post['avatar']="";
		$post['profile']="";
		$post['email']="";
		$post['useremail']="";
		$post['icqicon']="";
		$post['aimicon']="";
		$post['yahooicon']="";
		$post['homepage']="";
		$post['findposts']="";
		$post['signature']="";
		$onlinestatus="";
	}
	// do ip addresses
	if ($post['ip']!="") {
		if ($logip==2) {
			eval("\$post[iplogged] .= \"".gettemplate("postbit_ip_show")."\";");
		}
		if ($logip==1) {
			eval("\$post[iplogged] .= \"".gettemplate("postbit_ip_hidden")."\";");
		}
		if ($logip==0) {
			$post['iplogged']="";
		}
	} else {
		$post['iplogged']="";
	}

	$post['message']=bbcodeparse($post['pagetext'],$forum['forumid'],$post['allowsmilie']);

	//highlight words for search engine
	if (isset($highlight) && $highlight != '') {
		if ((isset($postid) and $postid==$post['postid']) or !isset($postid)) {
			reset($replacewords);
			while (list($key,$val)=each($replacewords)) {
				$post['message']=preg_replace('#(?<=[\s"\]>()]|^)(' . $val . ')(([\.,;-?!()]+)?([\s"<\[]|$))#siU', "<highlight>\\1</highlight>\\2", $post['message']);
			}
		}
	}
	// do posts from ignored users
	if (($ignore[$post['userid']] and $post['userid'] != 0)) {
		eval("\$retval = \"".gettemplate("postbit_ignore")."\";");
	} else {
		eval("\$retval = \"".gettemplate("postbit")."\";");
	}
	return $retval;
}

// ###################### Start gettextareawidth #######################
function gettextareawidth() {
	// attempts to fix idiotic Nutscrape textarea width problems
	global $HTTP_USER_AGENT;

	if (eregi("MSIE",$HTTP_USER_AGENT)) { // browser is IE
		return "{textareacols_IE}";

	} elseif (eregi("Mozilla/5.0",$HTTP_USER_AGENT)) { // browser is NS 6
		return "{textareacols_NS6}";

	} elseif (eregi("Mozilla/4.",$HTTP_USER_AGENT)) { // browser is NS4
		return "{textareacols_NS4}";

	} else { // unknown browser - stick in a sensible value
		return 60;

	}

}

// ###################### Start getforuminfo #######################
function getforuminfo (&$forumid) {
  global $DB_site;
  global $forumcache;

  $forumid = intval($forumid);
  if (!isset($forumcache[$forumid])) {
    $forumcache[$forumid]=$DB_site->query_first("SELECT * FROM forum WHERE forumid='$forumid'");
  }

  return $forumcache[$forumid];
}

// ###################### Start getthreadinfo #######################
function getthreadinfo (&$threadid) {
  global $DB_site;
  global $threadcache;

  $threadid = intval($threadid);
  if (!isset($threadcache[$threadid])) {
    $threadcache[$threadid]=$DB_site->query_first("SELECT * FROM thread WHERE threadid='$threadid'");
  }

  return $threadcache[$threadid];
}

// ###################### Start getpostinfo #######################
function getpostinfo (&$postid) {
  global $DB_site;
  global $postcache;

  $postid = intval($postid);
  if (!isset($postcache[$postid])) {
    $postcache[$postid]=$DB_site->query_first("SELECT * FROM post WHERE postid='$postid'");
  }

  return $postcache[$postid];
}

// ###################### Start getuserinfo #######################
function getuserinfo (&$userid) {
  global $DB_site;
  static $usercache;

  $userid = intval($userid);
  if (!isset($usercache[$userid])) {
    $usercache["$userid"]=$DB_site->query_first("SELECT user.*,userfield.* FROM user LEFT JOIN userfield ON userfield.userid=user.userid WHERE user.userid='$userid'");

    $usercache["$userid"]['showsignatures'] = iif(($usercache["$userid"]['options'])&SHOWSIGNATURES,1,0);
    $usercache["$userid"]['showavatars'] = iif(($usercache["$userid"]['options'])&SHOWAVATARS,1,0);
    $usercache["$userid"]['showimages'] = iif(($usercache["$userid"]['options'])&SHOWIMAGES,1,0);
    $usercache["$userid"]['showvbcode'] = iif(($usercache["$userid"]['options'])&SHOWVBCODE,1,0);
  }

  return $usercache[$userid];
}

// ###################### Start getforumarray #######################
function getforumarray($forumid) {
  global $DB_site,$forumcache;

  static $forumarraycache;

  if (isset($forumarraycache["$forumid"])) {
    return $forumarraycache["$forumid"];
  } else if (isset($forumcache["$forumid"])) {
    return $forumcache["$forumid"]['parentlist'];
  } else {
    $foruminfo=$DB_site->query_first("SELECT parentlist FROM forum WHERE forumid='$forumid'");
    $forumarraycache["$forumid"]=$foruminfo['parentlist'];
    return $foruminfo['parentlist'];
  }
}

// ###################### Start getforumlist #######################
function getforumlist($forumid,$field="forumid",$joiner="OR",$parentlist="") {
  global $DB_site;

  if (!$parentlist) {
    $parentlist=getforumarray($forumid);
  }

  return "($field='".implode("' $joiner $field='", explode(",",$parentlist))."')";

}

// ###################### Start makenavbar #######################
$altnavbar = 0;
function makenavbar($id,$idtype="forum",$highlightlast=1) {
	global $header,$footer,$headinclude,$toplinks,$forumjump,$timezone,$bbtitle,
		$hometitle,$bburl,$homeurl,$copyrighttext,$privacyurl,$contactuslink,
		$webmasteremail,$technicalemail,$faxnumber,$address,$companyname,$titleimage,
		$replyimage,$newthreadimage,$closedthreadimage,$lastvisitdate,$timenow,$navbits,
		$templateversion,$session,$altnavbar;

	$navbits=makenav($id,$idtype,$highlightlast);

	if ($altnavbar) {
		$navbits = explode(gettemplate("nav_joiner"),$navbits);
		while (list($key,$val)=each($navbits)) {
			$altnavbits .= "<br>$altnavprefix<img src=\"{imagesfolder}/cascade/casendline.gif\"><img src=\"{imagesfolder}/cascade/casicon.gif\">&nbsp;&nbsp;$val\n";
			$altnavprefix .= "<img src=\"{imagesfolder}/cascade/casvertline.gif\">";
		}
		eval("\$navbar = \"".gettemplate("navbaralt")."\";");
	} else {
		eval("\$navbar = \"".gettemplate("navbar")."\";");
	}

	return $navbar;
}

function makenav($id,$idtype="forum",$highlightlast=1) {
  global $DB_site,$nav_url,$nav_title,$session,$threadcache;
  $code = "";
  if ($id!=-1) {
    if ($idtype=="thread") {
      if ( !isset($threadcache["$id"]) ) {
        $getforumid=$DB_site->query_first("SELECT forumid FROM thread WHERE threadid=$id");
      } else {
        $getforumid['forumid'] = $threadcache["$id"]['forumid'];
      }
      $code=makenav($getforumid['forumid'],"forum",1);

      if ($highlightlast) {
        $templatename="nav_linkon";
      } else {
        $templatename="nav_linkoff";
      }

      if (strlen($code)>0) {
        $code.=gettemplate("nav_joiner",0);
      }

      $threadinfo=getthreadinfo($id);
      $nav_url="showthread.php?s=$session[sessionhash]&amp;threadid=$id";
      $nav_title=$threadinfo['title'];

      eval("\$code .= \"".gettemplate("$templatename")."\";");
    } else {
      $foruminfo=getforuminfo($id);
      if ($foruminfo['parentid']!=-1) {
        $code=makenav($foruminfo['parentid'],$idtype,1);
      }

      if (strlen($code)>0) {
        $code.=gettemplate("nav_joiner",0);
      }

      $nav_url="forumdisplay.php?s=$session[sessionhash]&amp;forumid=$id";
      $nav_title=$foruminfo['title'];

      if ($highlightlast) {
        eval("\$code .= \"".gettemplate('nav_linkon')."\";");
      } else {
        eval("\$code .= \"".gettemplate('nav_linkoff')."\";");
      }

    }
  }
  return $code;

}

// ###################### Start dooutput #######################
function dooutput($vartext,$sendheader=1) {

  global $pagestarttime,$query_count,$showqueries,$querytime,$DB_site;

  if ($showqueries) {
    $pageendtime=microtime();

    $starttime=explode(" ",$pagestarttime);
    $endtime=explode(" ",$pageendtime);

    $totaltime=$endtime[0]-$starttime[0]+$endtime[1]-$starttime[1];

    $vartext.="<!-- Page generated in $totaltime seconds with $query_count queries -->";
  }

  if (!$showqueries) {
    echo dovars($vartext,$sendheader);
    flush();
  } else {
    $output=dovars($vartext,$sendheader);
    echo "\n<b>Page generated in $totaltime seconds with $query_count queries,\nspending $querytime doing MySQL queries and ".($totaltime-$querytime)." doing PHP things.";
    if (function_exists('memory_get_usage'))
    {
    	echo "\n\nMemory Used: " . number_format((memory_get_usage() / 1024)) . " KB\n";
    }
    echo "</b></pre>";
    flush();
  }
}

// ###################### Start dovars #######################
function dovars($newtext, $sendheader = 1)
{
	// parses replacement vars

	global $PHP_SELF, $DB_site, $replacementsetid, $gzipoutput, $gziplevel, $newpmmsg;
	static $vars;

	if (connection_status())
	{
		exit;
	}

	if (!is_array($vars))
	{
		// build an array of $vars containing find/replace values
		$vars = array();
		$replacements = $DB_site->query("
			SELECT findword, replaceword, replacementsetid
			FROM replacement
			WHERE replacementsetid IN(-1, '" . intval($replacementsetid) . "')
			ORDER BY replacementsetid, replacementid DESC
		");
		while ($replacement = $DB_site->fetch_array($replacements))
		{
			if ($replacement['findword'] != '')
			{
				$vars["$replacement[findword]"] = $replacement['replaceword'];
			}
		}
		unset($replacement);
		$DB_site->free_result($replacements);
	}

	if (PHP_VERSION < '4.0.5' or 1)
	{
		// do each replacement in turn for PHP < 4.0.5
		reset($vars);
		while(list($find, $replace) = each($vars))
		{
			$newtext = str_replace($find, $replace, $newtext);
		}
	}
	else
	{
		// do all replacements in one go (PHP >= 4.0.5 only)
		$newtext = str_replace(array_keys($vars), $vars, $newtext);
	}

	if ($newpmmsg)
	{
		if (substr($PHP_SELF,-strlen('private.php')) == 'private.php')
		{
			// do nothing
		}
		else
		{
			$newtext = preg_replace("/<body/i", "<body onload=\"Javascript:confirm_newpm()\"", $newtext);
		}
	}

	if ($gzipoutput and !headers_sent())
	{
		$newtext = gzipoutput($newtext, $gziplevel);
	}

	if ($sendheader)
	{
		@header("Content-Length: " . strlen($newtext));
	}

	return $newtext;
}

// ###################### Start standarderror( #######################
function standarderror($error="",$headinsert="") {
  // print standard error page
  global $header,$footer,$headinclude,$headnewpm,$toplinks,$forumjump,$timezone,$bbtitle,$hometitle,$bburl,$homeurl,$copyrighttext,$privacyurl,$contactuslink,$webmasteremail,$technicalemail,$faxnumber,$address,$companyname,$titleimage,$replyimage,$newthreadimage,$closedthreadimage,$lastvisitdate,$timenow,$session,$logincode;

  makeforumjump();

  $title=$bbtitle;

  $pagetitle = "$title";
  $errormessage = $error;

  eval("dooutput(\"".gettemplate("standarderror")."\");");
  exit;

}

// ###################### Start standardredirect( #######################
function standardredirect($message="",$url="") {
  // print standard redirect page

  global $header,$footer,$headinclude,$headnewpm,$toplinks,$forumjump,$timezone,$bbtitle,$hometitle,$bburl,$homeurl,$copyrighttext,$privacyurl,$contactuslink,$webmasteremail,$technicalemail,$faxnumber,$address,$companyname,$titleimage,$replyimage,$newthreadimage,$closedthreadimage,$lastvisitdate,$timenow,$session;

  $title=$bbtitle;

  $pagetitle = "$title";
  $errormessage = $message;

  $url = str_replace("\"", "", $url);
  $url = str_replace(chr(0), '', $url);

  $url = preg_replace(
    array('/&#0*59;?/', '/&#x0*3B;?/i', '#;#'),
    '%3B',
    $url
  );
  $url = preg_replace('#&amp%3B#i', '&amp;', $url);

  if (!(preg_match('#https?://#i', $url) OR $url[0] == '/' OR preg_match('#^[^:*?"<>%|]+(\?.*)?$#i', $url)))
  {
    $url = $bburl . '/' . $url;
  }

  eval("dooutput(\"".gettemplate("standardredirect")."\");");
  exit;

}

// ###################### Start iif #######################
function iif ($expression,$returntrue,$returnfalse) {

  if ($expression==0) {
    return $returnfalse;
  } else {
    return $returntrue;
  }

}

// ###################### Start dowordwraptext #######################
function dowordwrap($text) {
  global $wordwrap;

  if ($wordwrap!=0 and $text!="") {
    return preg_replace("/([^\n\r ?&\.\/<>\"\\-]{".$wordwrap."})/i"," \\1\n",$text);
  }

}

// ###################### Start bbcodeparse #######################
function bbcodeparse($bbcode,$forumid=0,$allowsmilie=1) {

  global $allowhtml,$allowbbcode,$allowbbimagecode,$allowsmilies;

  if ($forumid == 0)
  {
    $dohtml=$allowhtml;
    $dobbcode=$allowbbcode;
    $dobbimagecode=$allowbbimagecode;
    $dosmilies=$allowsmilies;
  }
  else
  {
    $forum=getforuminfo($forumid);
    $dohtml=$forum['allowhtml'];
    $dobbimagecode=$forum['allowimages'];
    $dosmilies=$forum['allowsmilies'];

    if ($allowsmilie!=1) {
      $dosmilies = $allowsmilie;
    }

    $dobbcode=$forum['allowbbcode'];
  }

  return bbcodeparse2($bbcode,$dohtml,$dobbimagecode,$dosmilies,$dobbcode);
}

$regexcreated = 0;
$searcharray = array();
$replacearray = array();
$phpversionnum = phpversion();
function bbcodeparse2($bbcode,$dohtml,$dobbimagecode,$dosmilies,$dobbcode)
{ // parses text for vB code, smilies and censoring

  global $DB_site,$wordwrap,$allowdynimg, $bbuserinfo;

  static $smilies,$bbcodes;
  global $regexcreated,$searcharray,$replacearray,$phpversionnum,$vb_bbcode_callbacks;

  if($wordwrap!=0) {
    $bbcode=dowordwrap($bbcode);
  }

  if(!$dohtml)  { // kill any rogue html code
    // $bbcode=str_replace("&","&amp;",$bbcode);
    $bbcode = preg_replace('/&(?![a-z0-9#]+;)/i', '&amp;', $bbcode);
    $bbcode=str_replace("&lt;","&amp;lt;",$bbcode);
    $bbcode=str_replace("&gt;","&amp;gt;",$bbcode);
    $bbcode=str_replace("<","&lt;",$bbcode);
    $bbcode=str_replace(">","&gt;",$bbcode);
  }
  $bbcode=nl2br($bbcode);

  //smilies
  if($dosmilies) {
    $bbcode=str_replace("&gt;)", "&gt; )", $bbcode);
    $bbcode=str_replace("&lt;)", "&lt; )", $bbcode);
    if(!isset($smilies)) {
      $smilies=$DB_site->query("SELECT smilietext,smiliepath FROM smilie");
    } else {
      $DB_site->data_seek(0,$smilies);
    }

    while ($smilie=$DB_site->fetch_array($smilies)) {
      if(trim($smilie['smilietext'])!="") {
        $bbcode=str_replace(trim($smilie['smilietext']),"<img src=\"$smilie[smiliepath]\" border=\"0\" alt=\"\">",$bbcode);
      }
    }
  }

  if($dobbcode and strpos($bbcode,"]")) {
    if (!$regexcreated) {
      $regexcreated = 1;

      if (floor((float) $phpversionnum) < 4) {
        $searcharray = array(
          "/(\[)(list)(=)(['\"]?)([^\"']*)(\\4])(.*)(\[\/list)(((=)(\\4)([^\"']*)(\\4]))|(\]))/siU",
          "/(\[)(list)(])(.*)(\[\/list\])/siU",
          "/(\[\*\])/siU",
          "/(\[)(url)(=)(['\"]?)(www\.)([^\"']*)(\\4)(.*)(\[\/url\])/siU",
          "/(\[)(url)(=)(['\"]?)([^\"']*)(\\4])(.*)(\[\/url\])/siU",
          "/(\[)(url)(])(www\.)([^\"]*)(\[\/url\])/siU",
          "/(\[)(url)(])([^\"]*)(\[\/url\])/siU",
          "/(\[)(php)(])(\r\n)*(.*)(\[\/php\])/siU",
          "/(\[)(code)(])(\r\n)*(.*)(\[\/code\])/siU",
          "/javascript:/si",
          "/about:/si",
          "/vbscript:/si",
		  "/([^\w])on(\w+)=/si"
        );

        $replacearray = array(
          "<ol type=\"\\5\">\\7</ol>",
          "<ul>\\4</ul>",
          "<li>",
          "<a href=\"http://www.\\6\" target=\"_blank\">\\8</a>",
          "<a href=\"\\5\" target=\"_blank\">\\7</a>",
          "<a href=\"http://www.\\5\" target=\"_blank\">\\5</a>",
          "<a href=\"\\4\" target=\"_blank\">\\4</a>",
          "</normalfont><blockquote><code><smallfont>code:</smallfont><hr>\\5<hr></code></blockquote><normalfont>",
          "</normalfont><blockquote><pre><smallfont>code:</smallfont><hr>\\5<hr></pre></blockquote><normalfont>",
          "java script:",
          "about :",
          "vbscript :",
		  "\\1&#111;&#110;\\2="
        );
        $vb_bbcode_callbacks = array();
      } else {
        $searcharray = array(
          "/(\[)(list)(=)(['\"]?)(A|a|I|i|1)(\\4])(.*)(\[\/list)(((=)(\\4)([^\"']*)(\\4]))|(\]))/siU",
          "/(\[)(list)(])(.*)(\[\/list\])/siU",
          "/(\[)(url)(=)(['\"]?)([^\"'`]*)(\\4])(.*)(\[\/url\])/siU",
          "/(\[)(url)(])([^\"`]*)(\[\/url\])/siU",
          "/(\[)(code)(])(\r\n)*(.*)(\[\/code\])/siU",
          "/(\[)(php)(])(\r\n)*(.*)(\[\/php\])/siU",
          "/javascript:/si",
          "/about:/si",
          "/vbscript:/si",
		  "/([^\w])on(\w+)=/si"
        );

        $replacearray = array(
          "createlists('\\7', '\\5')",
          "createlists('\\4')",
          "checkurl('\\5', '\\7')",
          "checkurl('\\4')",
          "stripbrsfromcode('\\5')",
          "phphighlite('\\5')",
          "java script:",
          "about :",
          "vbscript :",
		  "\\1&#111;&#110;\\2="
        );
        // PHP 7 removed the /e modifier. Reproduce the six expression
        // replacements with explicit callbacks instead of eval-ing code.
        $vb_bbcode_callbacks = array(
          0 => function ($m) { return createlists($m[7] ?? '', $m[5] ?? ''); },
          1 => function ($m) { return createlists($m[4] ?? ''); },
          2 => function ($m) { return checkurl($m[5] ?? '', $m[7] ?? ''); },
          3 => function ($m) { return checkurl($m[4] ?? ''); },
          4 => function ($m) { return stripbrsfromcode($m[5] ?? ''); },
          5 => function ($m) { return phphighlite($m[5] ?? ''); },
        );
      }  // end version check

      $doubleRegex = "/(\[)(%s)(=)(['\"`]?)([^\"'`]*)(\\4])(.*)(\[\/%s\])/siU";
      $singleRegex = "/(\[)(%s)(])(.*)(\[\/%s\])/siU";

      $bbcodes=$DB_site->query("SELECT bbcodetag,bbcodereplacement,twoparams FROM bbcode");

      while($bbregex=$DB_site->fetch_array($bbcodes)) {
        if ($bbregex['twoparams']) {
          $regex=sprintf($doubleRegex, $bbregex['bbcodetag'], $bbregex['bbcodetag']);
        } else {
          $regex=sprintf($singleRegex, $bbregex['bbcodetag'], $bbregex['bbcodetag']);
        }
        $searcharray[] = $regex;
        $replacearray[] = $bbregex['bbcodereplacement'];
        // and get nested ones:
        $searcharray[] = $regex;
        $replacearray[] = $bbregex['bbcodereplacement'];
        $searcharray[] = $regex;
        $replacearray[] = $bbregex['bbcodereplacement'];
      }

    }

    while (preg_match('#\[email\](.*["[].*)\[/email\]#siU', $bbcode))
    {
      $bbcode_start = $bbcode;
      $bbcode = preg_replace('#\[email\](.*["[].*)\[/email\]#siU', '$1', $bbcode);
      if ($bbcode == $bbcode_start)
      {
      	break;
      }
    }
    unset($bbcode_start);

    if ($phpversionnum<"4.0.5") {
      $bbcode=str_replace("'", "\'", $bbcode);
    }
    // Apply patterns in order; entries that used /e go through callbacks.
    foreach ($searcharray as $vb_index => $vb_pattern) {
      if (isset($vb_bbcode_callbacks[$vb_index])) {
        $bbcode = preg_replace_callback($vb_pattern, $vb_bbcode_callbacks[$vb_index], $bbcode);
      } else {
        $bbcode = preg_replace($vb_pattern, $replacearray[$vb_index], $bbcode);
      }
    }

    if($dobbimagecode and ($bbuserinfo['userid']==0 or $bbuserinfo['showimages'])) {
      // do [img]xxx[/img]
      $bbcode = preg_replace("/\[img\](\r\n|\r|\n)*((http|https):\/\/([^<>\(\)\"".iif($allowdynimg,"","!\?\&")."]+)|[a-z0-9\/\\\._\- ]+)\[\/img\]/siU", "<img src=\"\\2\" border=\"0\" alt=\"\">", $bbcode);
    }
    $bbcode = preg_replace("/\[img\](\r\n|\r|\n)*((http|https):\/\/([^<>\*\(\)\"]+)|[a-z0-9\/\\\._\- ]+)\[\/img\]/siU", "<a href=\"\\2\" target=\"_blank\">\\2</a>", $bbcode);

    $bbcode=str_replace("\\'", "'", $bbcode);

    if (floor((float) $phpversionnum) < 4) {
      // get rid of stray <br> tags in the code - upsets older browsers (IE 4.72 reported problems)
      $codebits=explode("</normalfont><pre><smallfont>code:</smallfont></normalfont><hr><blockquote>",$bbcode);
      list($key,$bbcode)=each($codebits);

      while (list($key,$val)=each($codebits)) {
        $sbbits=explode("</blockquote><hr></pre><normalfont>",$val);
        $newbits=str_replace("<br>", "", $sbbits[0])."</blockquote><hr></pre><normalfont>".$sbbits[1];
        $bbcode.="</normalfont><pre><smallfont>code:</smallfont></normalfont><hr><blockquote>".$newbits;
      }
    }
  }

	$bbcode=str_replace("{", "&#123;", $bbcode); // stop people posting replacements in their posts

	$bbcode = preg_replace('/&#0*58;/', '&#58;<b></b>', $bbcode);
	//$bbcode = str_replace(':', '&#58;<b></b>', $bbcode);

  return censortext($bbcode);

}

// ###################### Start phphighlite #######################
function phphighlite($code) {
  //PHP 4 only

  if (floor((float) phpversion())<4) {
    $buffer=$code;
  } else {
		$code = str_replace("<br>", "", $code);
		$code = str_replace("<br />", "", $code);
		$code = str_replace("&gt;", ">", $code);
		$code = str_replace("&lt;", "<", $code);

		$code = str_replace("&amp;", "&", $code);
		$code = str_replace('$', '\$', $code);
		$code = str_replace('\n', '\\\\n', $code);
		$code = str_replace('\r', '\\\\r', $code);
		$code = str_replace('\t', '\\\\t', $code);

		$code = stripslashes($code);

		if (!strpos($code,"<?") and substr($code,0,2)!="<?") {
			$code="<?\n".trim($code)."\n?>";
			$addedtags=1;
		}
		ob_start();
		$oldlevel=error_reporting(0);
		highlight_string($code);
		error_reporting($oldlevel);
		$buffer = ob_get_contents();
		ob_end_clean();
		if ($addedtags) {
		  $openingpos = strpos($buffer,'&lt;?');
		  $closingpos = strrpos($buffer, '?');
		  $buffer=substr($buffer, 0, $openingpos).substr($buffer, $openingpos+5, $closingpos-($openingpos+5)).substr($buffer, $closingpos+5);
		}
		$buffer = str_replace("&quot;", "\"", $buffer);
  }

  return "</normalfont><blockquote><code><smallfont>PHP:</smallfont><hr>$buffer<hr></code></blockquote><normalfont>";
}

// ###################### Start stripbrsfromcode #######################
function stripbrsfromcode($foundcode) {
  $foundcode = str_replace("\\\"","\"",$foundcode);
  return "</normalfont><blockquote><pre><smallfont>code:</smallfont><hr>" . str_replace("<br>", "", str_replace("<br />", "", $foundcode) ) . "<hr></pre></blockquote><normalfont>";
}

// ###################### Start createlists #######################
function createlists($foundlist, $type="") {
  $type = iif(empty($type), $type,  " type=\"$type\"");
  $foundlist = str_replace("\\\"","\"",$foundlist);
  if ($type) {
    return "<ol$type>" . str_replace("[*]","<li>", $foundlist) . "</ol>";
  } else {
    return "<ul>" . str_replace("[*]","<li>", $foundlist) . "</ul>";
  }
}

// ###################### Start checkurl #######################
function checkurl($url, $hyperlink="") {
  $righturl = $url;
  if(!preg_match("!^[a-z]+://!si", $url)) {
    $righturl = "http://$righturl";
  }
  $righturl = str_replace('[', '&#91;', $righturl);
  // remove threat of users including javascript in url
  /*$righturl = preg_replace("/javascript:/si", "java script:", $righturl);
  $righturl = preg_replace("/about:/si", "about :", $righturl);*/
  $hyperlink = iif(trim($hyperlink)=="" or $hyperlink==$url, iif(strlen($url)>55,substr($url,0,35)."...".substr($url,-15),$url) ,$hyperlink);
  return "<a href=\"$righturl\" target=\"_blank\">".str_replace('\"', '"', $hyperlink)."</a>";
}

// ###################### Start parseurl #######################
unset($urlSearchArray);
unset($urlReplaceArray);
unset($emailSearchArray);
unset($emailReplaceArray);
function parseurl($messagetext)
{ // the auto parser - adds [url] tags around neccessary things

  global $urlSearchArray, $urlReplaceArray, $emailSearchArray, $emailReplaceArray;

  if (!isset($urlSearchArray)) {
    $urlSearchArray = array(
      "/([^]_a-z0-9-=\"'\/])((https?|ftp|gopher|news|telnet):\/\/|www\.)([^ \r\n\(\)\^\$!`\"'\|\[\]\{\}<>]*)(?![^[]*\[\/url\])/si",
      "/^((https?|ftp|gopher|news|telnet):\/\/|www\.)([^ \r\n\(\)\^\$!`\"'\|\[\]\{\}<>]*)/si"
    );

    $urlReplaceArray = array(
      "\\1[url]\\2\\4[/url]",
      "[url]\\1\\3[/url]"
    );

    $emailSearchArray = array(
      "/([ \n\r\t])([_a-zA-Z0-9-]+(\.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,4}))/si",
      "/^([_a-zA-Z0-9-]+(\.[_a-zA-Z0-9-]+)*@[a-zA-Z0-9-]+(\.[a-zA-Z0-9-]+)*(\.[a-zA-Z]{2,4}))/si"
    );

    $emailReplaceArray = array(
      "\\1[email]\\2[/email]",
      "[email]\\0[/email]"
    );
  }

  $text = preg_replace($urlSearchArray, $urlReplaceArray, $messagetext);
  if (strpos($text, "@")) {
    $text = preg_replace($emailSearchArray, $emailReplaceArray, $text);
  }

  return $text;

}

// ###################### Start repeatchar #######################
function repeatchar($char,$times) {
  // returns the contents of $char repeatd $times times
  $counter=0;

  while ($counter++<$times) {
    $retstring.=$char;
  }

  return $retstring;
}

// ###################### Start censortext #######################
unset($censorword);
function censortext($text) {
  global $enablecensor,$censorwords,$censorword,$censorchar;
  if ($enablecensor==1 and $censorwords!="") {
    if (!isset($censorword)) {
      $censorwords = preg_quote($censorwords);
      $censorwords = str_replace('/', '\\/', $censorwords);
      $censorword=explode(" ",$censorwords);
    } else {
      reset($censorword);
    }

    while (list($key,$val)=each($censorword)) {
      if ($val!="") {
        if (substr($val,0,2)=="\\{") {
          $val=substr($val,2,-2);

          $text=trim(preg_replace("/([^A-Za-z])".$val."(?=[^A-Za-z])/si","\\1".repeatchar($censorchar,strlen($val))," $text "));
        } else {
          $text=trim(preg_replace("/$val/si",repeatchar($censorchar,strlen($val))," $text "));
        }
      }
    }
  }

  // PHP 4 / windows-1252 era: these replaced the bare bytes 0xAD (soft hyphen)
  // and 0xA0 (NBSP). Under UTF-8 those characters are the 2-byte sequences
  // C2 AD / C2 A0; replacing the bare byte corrupts any multibyte character
  // whose last byte happens to be 0xAD or 0xA0 (e.g. U+4E2D = E4 B8 AD),
  // yielding invalid UTF-8 that htmlspecialchars() turns into U+FFFD and MySQL
  // into '?'. Match the complete UTF-8 sequences instead.
  $text = str_replace("\xC2\xAD", '_', $text);
  $text = str_replace("\xC2\xA0", '_', $text);

  return $text;
}

// ###################### Start customcensortext #######################
function customcensortext($text) {
  global $ctCensorWords,$censorchar;

  if (!isset($ctcensorword)) {
    $ctCensorWords = preg_quote($ctCensorWords);
    $ctCensorWords = str_replace('/', '\\/', $ctCensorWords);
    $ctcensorword = explode(' ', $ctCensorWords);
  }

  while (list($key, $val) = each($ctcensorword)) {
    if ($val != '') {
      if (substr($val, 0, 1) == '{') {
        $val = substr($val, 1, -1);
        $text = trim(preg_replace("/([^A-Za-z])" . $val . "(?=[^A-Za-z])/si", "\\1" . repeatchar($censorchar, strlen($val))," $text "));
      } else {
        $text = trim(preg_replace("/$val/si", repeatchar($censorchar, strlen($val)), " $text "));
      }
    }
  }
  return $text;
}

// ###################### Start cachetemplate #######################
function cachetemplates($templateslist) {
  // $templateslist: comma delimited list
  global $templatecache,$DB_site,$templatesetid,$loadmaxusers,$loadbirthdays;

  // add in sql info
  //$templateslist=str_replace(",","' OR title='",$templateslist);
  $templateslist=str_replace(',', "','", vb_escape($templateslist));

  // run query
  $temps=$DB_site->query("SELECT template,title
                          FROM template
                          WHERE (title IN ('$templateslist')
                            AND (templatesetid=-1 OR templatesetid=" . intval($templatesetid) . "))
                          ".iif ($loadmaxusers,"OR (title = 'maxloggedin')","")."
                          ".iif ($loadbirthdays,"OR (title = 'birthdays')","")."
                          ORDER BY templatesetid");

  // cache templates
  while ($temp=$DB_site->fetch_array($temps)) {
    $templatecache["$temp[title]"]=$temp['template'];
  }
  unset($temp);
  $DB_site->free_result($temps);

}

// ###################### Start gettemplate #######################
function gettemplate($templatename,$escape=1,$gethtmlcomments=1) {
  // gets a template from the db or from the local cache
  global $templatecache,$DB_site,$templatesetid,$addtemplatename;

  if (isset($templatecache[$templatename])) {
    $template=$templatecache[$templatename];
  } else {
    $gettemp=$DB_site->query_first("SELECT template FROM template WHERE title='".vb_escape($templatename)."' AND (templatesetid=-1 OR templatesetid=" . intval($templatesetid). ") ORDER BY templatesetid DESC LIMIT 1");
    $template=$gettemp['template'];
    $templatecache[$templatename]=$template;
 }

  // Translate UI text on the template source (before escaping/eval), so user
  // content interpolated later can never be rewritten by the language layer.
  $template = vblang_template($template);

  if ($escape==1) {
    $template=addslashes($template);
    $template=str_replace("\\'","'",$template);
  }
  if ($gethtmlcomments and $addtemplatename) {
    return "<!-- BEGIN TEMPLATE: $templatename -->\n$template\n<!-- END TEMPLATE: $templatename -->";
  }

  return $template;
}

// ###################### Start verifyid #######################
function verifyid($idname, &$id, $alert=1, $selall=0) {
  // verifies an id number and returns a correct one if it can be found
  // returns 0 if none found
  global $DB_site,$webmasteremail,$session,$threadcache,$forumcache;

  $id=intval($id);

  if (!isset($id) or $id==0 or $id=="") {
    if ($alert) {  // show alert?
      eval("standarderror(\"".gettemplate('error_noid')."\");");
      exit;
    }
  } else {
    if ($selall==1) {
      $selid = '*';
    } else {
      $selid = $idname.'id';
    }
    if ($idname=='thread' and $threadcache["$id"]) {
      if ($selall!=1) {
        return $threadcache["$id"]["$selid"];
      } else {
        return $threadcache["$id"];
      }
    } else if ($idname=='forum' and isset($forumcache["$id"]) and $forumcache["$id"]['forumid']==$id) {
      if ($selall!=1) {
        return $forumcache["$id"]['forumid'];
      } else {
        return $forumcache["$id"];
      }
    } else if (!$check=$DB_site->query_first("SELECT $selid FROM $idname WHERE $idname"."id=$id")) {
      if ($alert) { // show alert?
        eval("standarderror(\"".gettemplate('error_invalidid')."\");");
        exit;
      }
    } else {
      if ($selall!=1) {
        return $check["$selid"];
      } else {
        if ($idname=='thread') {
          $threadcache["$check[threadid]"] = $check;
        } else if ($idname=='forum') {
          $forumcache["$check[forumid]"] = $check;
        }
        return $check;
      } //if ($selall!=1) {
    } //if ($idname=="thread" and isset($threadcache["$id"])) {
  } //if (!isset($id) or $id==0 or $id=="") {

}

// ###################### Start checkipban #######################
function checkipban() {
  // checkes to see if the current ip address is banned
  global $enablebanning,$banip,$webmasteremail,$session, $_SERVER;

  $banip = trim($banip);
  if ($enablebanning==1 and $banip!="") {
    $ipaddress=$_SERVER['REMOTE_ADDR'];

    $addresses=explode(" ", preg_replace("/[[:space:]]+/", " ", $banip) );
    while (list($key,$val)=each($addresses)) {
      if (strstr(" ".$ipaddress," ".trim($val))!="") {
        eval("standarderror(\"".gettemplate("error_banip")."\");");
      }
    }
  }
}

// ###################### Start chooseicons #######################
function chooseicons($seliconid=0) {
  // retursn the icons chooser for posting new messages
  global $DB_site,$session;

/*
  if ($seliconid==0) {
    $seliconid=1;
  }
*/

  $icons=$DB_site->query("SELECT iconid,iconpath,title FROM icon ORDER BY iconid");

  $counter=0;

  while ($icon=$DB_site->fetch_array($icons)) {

    if ($counter%7==0 and $counter!=0) {
      $posticonbits.="<br>\n";
    }

    $counter++;

    $iconid=$icon['iconid'];
    $iconpath=$icon['iconpath'];
    if ($seliconid==$iconid) {
      $iconchecked="CHECKED";
    } else {
      $iconchecked="";
    }
    $alttext=$icon['title'];

    eval("\$posticonbits.= \"".gettemplate("posticonbit")."\";");

  }

  if ($seliconid==0) {
    $iconchecked="CHECKED";
  } else {
    $iconchecked="";
  }

  eval("\$posticons.= \"".gettemplate("posticons")."\";");

  return $posticons;

}

// ###################### Start getpermissions #######################
function getpermissions($forumid=0,$userid=-1,$usergroupid=-1,$parentlist="") {
  // gets permissions, depending on given userid and forumid
  global $DB_site, $usercache, $bbuserinfo, $enableaccess;

  static $permscache, $usergroupcache;

  $userid=intval($userid);
  if ($userid==-1) {
    $userid=$bbuserinfo['userid'];
    $usergroupid=$bbuserinfo['usergroupid'];
  }

  if ($usergroupid==-1 or $usergroupid==0) {
    if ($userid==0) {
      $usergroupid=1;
    } else {
      if (isset($usercache["$userid"])) {
        $usergroupid=$usercache["$userid"]['usergroupid'];
      } else {
        $getuser=$DB_site->query_first("SELECT usergroupid FROM user WHERE userid=$userid");
        $usergroupid=$getuser['usergroupid'];
      }
    }
  }

  if (!isset($permscache["$usergroupid"]["$forumid"])) {
    if (!$forumid) {
      if (!isset($usergroupcache["$usergroupid"])) {
        $usergroupcache["$usergroupid"] = $DB_site->query_first("SELECT * FROM usergroup WHERE usergroupid=$usergroupid");
        return $usergroupcache["$usergroupid"];
      } else {
        return $usergroupcache["$usergroupid"];
      }
    } else {
      if (!$parentlist) {
        $parentlist=getforumarray($forumid);
      }
      $forums=getforumlist($forumid,"forumid","OR",$parentlist);
      if ($enableaccess==1 AND $access=$DB_site->query_first("SELECT *,INSTR(',$parentlist,', CONCAT(',', forumid, ',') ) AS ordercontrol FROM access WHERE userid=$userid AND $forums ORDER BY ordercontrol LIMIT 1")) {
        if ($access['accessmask']==1) {
          if (!isset($usergroupcache["$usergroupid"])) {
            $getperms=$DB_site->query_first("SELECT * FROM usergroup WHERE usergroupid=$usergroupid");
            $usergroupcache["$usergroupid"] = $getperms;
           } else {
            $getperms = $usergroupcache["$usergroupid"];
          }
        } else {
          if (!isset($usergroupcache["$usergroupid"])) {
            $getperms2=$DB_site->query_first("SELECT * FROM usergroup WHERE usergroupid=$usergroupid");
            $usergroupcache["$usergroupid"] = $getperms2;
          } else {
            $getperms2 = $usergroupcache["$usergroupid"];
          }
          while ( list($gpkey,$gpval)=each($getperms2) ) {
            $getperms["$gpkey"] = 0;
          }
        }
      } else if (!$getperms=$DB_site->query_first("SELECT *,INSTR(',$parentlist,', CONCAT(',', forumid, ',') ) AS ordercontrol FROM forumpermission WHERE usergroupid=$usergroupid AND $forums ORDER BY ordercontrol LIMIT 1")) {
        if (!isset($usergroupcache["$usergroupid"])) {
          $getperms=$DB_site->query_first("SELECT * FROM usergroup WHERE usergroupid=$usergroupid");
          $usergroupcache["$usergroupid"] = $getperms;
        } else {
          $getperms = $usergroupcache["$usergroupid"];
        }
      }
    }
    $permscache["$usergroupid"]["$forumid"]=$getperms;
  } else {
    return $permscache["$usergroupid"]["$forumid"];
  }

  return $getperms;

}

// ###################### Start wordsonly #######################
function wordsonly ($text) {
  //for the searching - strips out unneccessary bits
  $text=strtolower($text);

  $text=ereg_replace("<pre>[^<]*</pre>"," ",$text);
  $text=ereg_replace("<[^>]*>"," ",$text); // remove HTML <> tags
  $text=ereg_replace("&[^;];"," ",$text); // remove HTML special chars
//  $text=ereg_replace("[^ 0-9a-z]"," ",$text); // keep only letters and numbers

  $counter=0;

  $words=explode(" ",$text);
  while (list($key,$val)=each($words)) {
    if (strlen($val)>2 and $val!="the" and $val!="this" and $val!="and" and $val!="but" and $val!="was" and $val!="that" and $val!="with" and $val!="its" and $val!="you" and $val!="they" and $val!="what" and $val!="why" and $val!="for" and $val!="are" and $val!="our" and $val!="then" and $val!="there" and $val!="which") {
      $counter++;
      $wordarray[]=$val;
    }
  }

  if ($counter>0) {
    return implode(" ", $wordarray);
  }
}

// ###################### Start indexpost #######################
$firstpst = array();
unset($badwords);
function indexpost($postid,$firstpost=-1) {
  global $DB_site,$bbadminon,$minsearchlength,$maxsearchlength;
  global $firstpst,$badwords,$incp;

  if (!is_array($badwords)) {
    if ($incp) {
      include("./badwords.php");
    } else {
      include("./admin/badwords.php");
    }
  }

  $post=$DB_site->query_first("SELECT postid,threadid,title,pagetext FROM post WHERE postid='$postid'");

  // What is the point of this?//
  if ($firstpst[$post['threadid']]==$postid) {
    $firstpost=1;
  } elseif (isset($firstpst[$post['threadid']])) {
    $firstpost=0;
  }
  ///////////////////////////////

  if ($firstpost==-1) {
    $getfirstpost=$DB_site->query_first("SELECT postid FROM post WHERE threadid=$post[threadid] ORDER BY dateline LIMIT 1");
    if ($getfirstpost['postid']==$postid) {
      $firstpost=1;
    } else {
      $firstpost=0;
    }
  }

  if ($firstpost) {
    $threadinfo=$DB_site->query_first("SELECT title FROM thread WHERE threadid=$post[threadid]");
    //$post[title].=" ".$threadinfo[title];

    // What is the point of this?///////
    $firstpst[$post['threadid']]=$postid;
    ////////////////////////////////////

    $titlewords=$threadinfo['title'];
    $titlewords=ereg_replace("[\n\t\r,]"," ",$titlewords);
	 $titlewords=preg_replace("/(\.+)($| |\n|\t)/s", " ", $titlewords);
	 $titlewords=str_replace("[", " [", $titlewords);
	 $titlewords=str_replace("]", "] ", $titlewords);
	 $titlewords=preg_replace("/[\(\)\"':;\[\]?!#{}_\-+\\\\]/s","",$titlewords);
	 $titlewords=strtolower(trim(str_replace("  "," ",$titlewords)));
	 $titlewordarray=explode(" ",$titlewords);
	 while (list($key,$val)=each($titlewordarray)) {
	   $titlearray[$val]=1;
	 }
  }

  $allwords=$post['title']." ".$post['pagetext'];
  $allwords=preg_replace("/[\n\t\r,]/s"," ",$allwords);
  $allwords=preg_replace("/(\.+)($| |\n|\t)/s", " ", $allwords);
  $allwords=str_replace("[", " [", $allwords);
  $allwords=str_replace("]", "] ", $allwords);
  $allwords=preg_replace("/[\(\)\"':;\[\]?!#{}_\-+\\\\]/s","",$allwords);
  $allwords=strtolower(trim(str_replace("  "," ",$allwords)));
  if ($titlewords)
    $allwords.=" ".$titlewords;
  $wordarray=explode(" ",$allwords);

  $getwordidsql="title IN ('".str_replace(" ","','",$allwords)."')";
  $words=$DB_site->query("SELECT wordid,title FROM word WHERE $getwordidsql");

  while ($word=$DB_site->fetch_array($words)) {
    $wordcache[$word['title']]=$word['wordid'];
  }
  $DB_site->free_result($words);

  $insertsql="";
  $newwords="";
  while (list($key,$val)=each($wordarray)) {
    if ($badwords[$val]==1 or strlen($val)<$minsearchlength or strlen($val)>$maxsearchlength) {
      unset($wordarray[$key]);
      continue;
    }

    if ($val!=""  and !$worddone[$val]) {
      $worddone[$val]=1; // Ok we have added this word
      if (isset($wordcache[$val])) { // Does this word already exist in the word table?
        if (isset($titlearray[$val]))
          $intitle=1;
        else
          $intitle=0;
        $insertsql.=",($wordcache[$val],$postid,$intitle)"; // yes so just add a searchindex entry for this post/word
      } else {
        if (isset($titlearray[$val]))
          $newtitlewords.=$val." ";
        else
          $newwords.=$val." "; // No so add it to the word table
      }
    }
  }

  if ($insertsql!="") {
    $insertsql=substr($insertsql,1);
    $DB_site->query("REPLACE INTO searchindex (wordid,postid,intitle) VALUES $insertsql");
  }

  if ($newwords) {
    $newwords=trim($newwords);
    $insertwords="(NULL,'".str_replace(" ","'),(NULL,'",vb_escape($newwords))."')";
    $DB_site->query("INSERT IGNORE INTO word (wordid,title) VALUES $insertwords");
    $selectwords="title IN('".str_replace(" ","','",vb_escape($newwords))."')";
    $DB_site->query("INSERT IGNORE INTO searchindex (wordid,postid) SELECT DISTINCT wordid,$postid FROM word WHERE $selectwords");
   }

   if ($newtitlewords) {
     $newtitlewords=trim($newtitlewords);
     $insertwords="(NULL,'".str_replace(" ","'),(NULL,'",vb_escape($newtitlewords))."')";
     $DB_site->query("INSERT IGNORE INTO word (wordid,title) VALUES $insertwords");
     $selectwords="title IN('".str_replace(" ","','",vb_escape($newtitlewords))."')";
     $DB_site->query("REPLACE INTO searchindex (wordid,postid,intitle) SELECT DISTINCT wordid,$postid,1 FROM word WHERE $selectwords");
   }
}

// ###################### Start unindexpost #######################
function unindexpost($postid,$title="",$pagetext="") {
	global $DB_site;

	// get the data
	if ($pagetext=="") {
		$post=$DB_site->query_first("SELECT postid,threadid,title,pagetext FROM post WHERE postid='$postid'");
	} else {
		$post['postid']=$postid;
		$post['title']=$title;
		$post['pagetext']=$pagetext;
	}

	// get word ids from table
	$allwords=$post['title']." ".$post['pagetext'];
	$allwords=preg_replace("/[\n\t\r,]/s"," ",$allwords);
	$allwords=preg_replace("/(\.+)($| |\n|\t)/s", " ", $allwords);
	$allwords=str_replace("[", " [", $allwords);
	$allwords=str_replace("]", "] ", $allwords);
	$allwords=preg_replace("/[\(\)\"':;\[\]?!#{}_\-+\\\\]/s","",$allwords);
	$allwords=strtolower(trim(str_replace("  "," ",$allwords)));
	if ($titlewords)
		$allwords.=" ".$titlewords;
	$wordarray=explode(" ",$allwords);

	$getwordidsql="title IN ('".str_replace(" ","','",$allwords)."')";
	$words=$DB_site->query("SELECT wordid,title FROM word WHERE $getwordidsql");

	$wordids="";
	while ($word=$DB_site->fetch_array($words)) {
		$wordids .= ',' . $word['wordid'];
	}

	// delete em!
	$DB_site->query("DELETE FROM searchindex WHERE wordid IN (0$wordids) AND postid=$post[postid]");

}

// ###################### Start makeforumjump #######################
$frmjmpsel = array();
function makeforumjump($forumid=-1,$addbox=1,$prependchars="",$permission="") {
  global $DB_site,$optionselected,$usecategories,$jumpforumid,$jumpforumtitle,$jumpforumbits,$curforumid, $daysprune;
  global $hideprivateforums,$defaultselected,$forumjump,$bbuserinfo,$selectedone,$session,$useforumjump,$enableaccess;
  global $frmjmpsel; // allows context sensitivity for non-forum areas
  global $iforumcache,$ipermcache,$iaccesscache,$usergroupdef,$noperms,$gobutton;
//////////////
if ($useforumjump) {
//////////////
  if ( !is_array($permission) ) {
    $permission = getpermissions(0,-1,$bbuserinfo['usergroupid']);
    $usergroupdef['canview'] = $permission['canview'];
  }

  if ( !isset($iforumcache) ) {
    $iforums=$DB_site->query('SELECT forumid,parentid,displayorder,title FROM forum WHERE displayorder<>0 AND active=1 ORDER BY parentid,displayorder,forumid');
    while ($iforum=$DB_site->fetch_array($iforums)) {
      $iforumcache["$iforum[parentid]"]["$iforum[displayorder]"]["$iforum[forumid]"] = $iforum;
    }
    unset($iforum);
    $DB_site->free_result($iforums);

    $iforumperms=$DB_site->query("SELECT forumid,canview FROM forumpermission WHERE usergroupid='$bbuserinfo[usergroupid]'");
    while ($iforumperm=$DB_site->fetch_array($iforumperms)) {
      $ipermcache["$iforumperm[forumid]"] = $iforumperm;
    }
    unset($iforumperm);
    $DB_site->free_result($iforumperms);

    $noperms['canview']=0;
  }

  if (!isset($iaccesscache)) {
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


  if ( empty($iforumcache["$forumid"]) or !is_array($iforumcache["$forumid"]) ) {
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

      if (!$hideprivateforums) {
        $forumperms['canview']=1;
      }
      if ($forumperms['canview']) {
        $jumpforumid=$forum['forumid'];
        $jumpforumtitle=$prependchars." $forum[title]";

		$fjclass = "fjdpth".strlen($prependchars)/2;

        if ($curforumid==$jumpforumid) {
          $optionselected='selected';
		  $fjclass = "fjsel";
          $selectedone=1;
        } else {
          $optionselected='';
        }
        eval("\$jumpforumbits .= \"".gettemplate('forumjumpbit')."\";");

        makeforumjump($jumpforumid,0,$prependchars."--",$forumperms);
      } // if can view
    } // while forums
  }

  if ($addbox) {
    if ($selectedone!=1) {
      $defaultselected='selected';
    }

    if (!is_array($frmjmpsel)) {
      $frmjmpsel = array();
    }
    if (empty($daysprune)) {
      $daysprune = "";
    } else {
      $daysprune = intval($daysprune);
    }
    eval("\$forumjump = \"".gettemplate('forumjump')."\";");
  }
/////////////
 }
/////////////
}

// ###################### Start countchar #######################
function countchar($string,$char) {
  //counts number of times $char occus in $string

  $charpos=strstr($string,$char);
  if ($charpos!="") {
    return countchar(substr($charpos,strlen($char)),$char)+1;
  } else {
    return 0;
  }
}

// ###################### Start gzipoutput #######################
function gzipoutput($text,$level=1){
  global $HTTP_ACCEPT_ENCODING,$nozip;

  $returntext=$text;

  if (function_exists("crc32") and function_exists("gzcompress") and !$nozip){
    if (strpos(" ".$HTTP_ACCEPT_ENCODING,"x-gzip")) {
      $encoding = "x-gzip";
    }
    if (strpos(" ".$HTTP_ACCEPT_ENCODING,"gzip")) {
      $encoding = "gzip";
    }

    if ($encoding) {
      header("Content-Encoding: $encoding");

      $size = strlen($text);
      $crc = crc32($text);

      $returntext = "\x1f\x8b\x08\x00\x00\x00\x00\x00";
      $returntext .= substr(gzcompress($text,$level),0,-4);
      $returntext .= pack("V",$crc);
      $returntext .= pack("V",$size);
    }
  }
  return $returntext;
}

// ###################### Start vbsetcookie #######################
function vbsetcookie($name,$value="",$permanent=1, $httponly = false) {
  global $cookiepath,$cookiedomain, $SERVER_PORT;

  if ($permanent) {
    $expire=time() + 60*60*24*365;
  } else {
    $expire = 0;
  }

  if ($SERVER_PORT == "443") {
    // we're using SSL
    $secure = 1;
  } else {
    $secure = 0;
  }

  if (defined('USE_COOKIE_WORKAROUND')) {
    // It's been reported that there's a bug in PHP 4.2.0/4.2.1 with Apache 2 causing setcookie() to not work correctly.
    // This is the workaround. If you need to use this code, please add:
    //      define('USE_COOKIE_WORKAROUND', 1);
    // to your config.php.

    if (!$value) {
      // need to do this so IE deletes the cookie correctly
      $expire = time() - 31536001;
      $value = 'deleted';
    }
    $cookieheader = "Set-Cookie: $name=".urlencode($value);
    if ($expire) {
    	$cookieheader .= '; expires='.gmdate('D, d-M-Y H:i:s', $expire).' GMT';
    }
    if ($cookiepath) {
      $cookieheader .= "; path=$cookiepath";
    }
    if ($cookiedomain) {
      $cookieheader .= "; domain=$cookiedomain";
    }
    if ($secure) {
      $cookieheader .= '; secure';
    }
	if ($httponly) {
	  $cookieheader .= '; HttpOnly';
	}
    header($cookieheader, false); // force multiple headers of same type
  } else {
    setcookie($name, $value, $expire, $cookiepath, $cookiedomain, $secure);
  }

}

// ###################### Start show_nopermission #######################
function show_nopermission() {
  global $bbtitle,$logincode,$url,$scriptpath,$bbuserinfo,$session;

  // generate 'logged in as:' box or username and pwd box
  if (!$logincode) {
    $logincode=makelogincode();
  }

  if ($bbuserinfo['userid']==0) {
    eval("standarderror(\"".gettemplate("error_nopermission_loggedout")."\");");
  } else {
    eval("standarderror(\"".gettemplate("error_nopermission_loggedin")."\");");
  }
  exit;
}

// ###################### Start vbdate #######################
function vbdate($format,$timestamp) {
  global $bbuserinfo,$timeoffset;

  // Both values come from the database as strings; cast before arithmetic so
  // PHP 8 never sees a non-numeric string in a numeric operation.
  $vb_tz = (float) (is_array($bbuserinfo) ? ($bbuserinfo['timezoneoffset'] ?? 0) : 0);
  $vb_board_offset = (float) ($timeoffset ?? 0);

  return vblang_date(date($format,$timestamp+($vb_tz-$vb_board_offset)*3600));

}

// ##################### Save birthdays into template ###################
function getbirthdays() {
  global $timeoffset, $DB_site;

  $btoday = date("Y-m-d",time()+(-12-$timeoffset)*3600) . '|||';
  $btoday .= date("Y-m-d",time()+(12-$timeoffset)*3600) . '|||';

  $todayneggmt = date("m-d",time()+(-12-$timeoffset)*3600);
  $todayposgmt = date("m-d",time()+(12-$timeoffset)*3600);

  $bdays = $DB_site->query("SELECT username,userid,birthday
                            FROM user
                            WHERE birthday LIKE '%-$todayneggmt'
                              OR birthday LIKE '%-$todayposgmt'");
  $today = date("Y");
  while ($birthday=$DB_site->fetch_array($bdays)) {
    $bd_user = $birthday['username'];
    $bd_userid = $birthday['userid'];
    $day = explode('-',$birthday['birthday']);
    if ($today > $day[0] && $day[0]!='0000') {
  	  $age='('.($today-$day[0]).')';
    } else {
  	  unset($age);
  	}
  	if ($todayneggmt == "$day[1]-$day[2]") {
  	  eval("\$day1 .= \"$comma1 ".gettemplate('calendar_showbirthdays',1,0)."\";");
  	  $comma1 = ',';
    } else {
      eval("\$day2 .= \"$comma2 ".gettemplate('calendar_showbirthdays',1,0)."\";");
      $comma2 = ',';
    }
  }
  $btoday .= $day1 . '|||' . $day2;
  $DB_site->query("UPDATE template
                   SET template='".vb_escape($btoday)."'
                   WHERE templatesetid=-2
                    AND title='birthdays'");
}

// ###################### Start getavatarurl #######################
function getavatarurl($userid) {
  global $DB_site,$session;

  if ($avatarinfo=$DB_site->query_first("SELECT user.avatarid,avatarpath,NOT ISNULL(avatardata) AS hascustom,customavatar.dateline
                                         FROM user
                                         LEFT JOIN avatar ON avatar.avatarid=user.avatarid
                                         LEFT JOIN customavatar ON customavatar.userid=user.userid
                                         WHERE user.userid='$userid'")) {
    if ($avatarinfo['avatarpath']!="") {
      return $avatarinfo['avatarpath'];
    } else if ($avatarinfo['hascustom']) {
      return "avatar.php?userid=$userid&amp;dateline=$avatarinfo[dateline]";
    } else {
      return '';
    }
  }
}

// ###################### Start getextension #######################
function getextension($filename) {
  return substr(strrchr($filename,"."),1);
}

// ###################### Start acceptupload #######################
function acceptupload($moderate=0) {
  global $DB_site,$attachment,$attachment_size,$attachment_name;
  global $attachextensions,$maxattachsize,$bbuserinfo,$maxattachwidth, $maxattachheight;
  global $safeupload,$tmppath, $allowduplicates;

	$extension_map = array(
		'gif' => '1',
		'jpg' => '2',
		'jpe' => '2',
		'jpeg'=> '2',
		'png' => '3',
		'swf' => '4',
	);

  $attachment_name = strtolower($attachment_name);
  $extension = strtolower(getextension($attachment_name));

  if (strpos("  $attachextensions  "," $extension ")==0) {
    // invalid extension
    eval("standarderror(\"".gettemplate("error_attachbadtype")."\");");
    exit;
  }

  if (is_uploaded_file($attachment)) {
    if ($safeupload) {
      $path = "$tmppath/vba".substr(uniqid(microtime()),-8);
      move_uploaded_file($attachment, "$path");
      $attachment = $path;
    }

    $filesize=filesize($attachment);
    if ($maxattachsize!=0 and $filesize>$maxattachsize) {
      // too big!
      @unlink($attachment);
      eval("standarderror(\"".gettemplate("error_attachtoobig")."\");");
      exit;
    }
    if ($filesize!=$attachment_size) {
      // security error
      @unlink($attachment);
      eval("standarderror(\"".gettemplate("error_attacherror")."\");");
      exit;
    }
    if (strstr($attachment,"..")!="") {
      //security error
      @unlink($attachment);
      eval("standarderror(\"".gettemplate("error_attacherror")."\");");
      exit;
    }
    if ($extension=="gif" or $extension=="jpg" or $extension=="jpeg" or $extension=="jpe" or $extension=="png" or $extension=="swf") { // Picture file
    	$validfile = false;
      // Verify that file is playing nice
      $fp = fopen($attachment, 'rb');
      if ($fp)
      {
         $imageheader = fread($fp, 200);
         fclose($fp);
         if (!preg_match('#<html|<head|<body|<script#si', $imageheader))
         {
            $validfile = true;
         }
      }
      if ($validfile AND $imginfo=@getimagesize($attachment)) {
        if (($maxattachwidth>0 and $imginfo[0]>$maxattachwidth) or ($maxattachheight>0 and $imginfo[1]>$maxattachheight)) {
          @unlink($attachment);
          eval("standarderror(\"".gettemplate("error_attachbaddimensions")."\");");
        }
        if (!$imginfo[2] OR $extension_map["$extension"] != $imginfo[2]) {
	       @unlink($attachment);
	       eval("standarderror(\"".gettemplate("error_avatarnotimage")."\");");
        }
      } else {
          @unlink($attachment);
          eval("standarderror(\"".gettemplate("error_avatarnotimage")."\");");
      }
    }

    // read file
    $filenum = fopen($attachment,"rb");
    $filestuff = fread($filenum,$filesize);
    fclose($filenum);
    @unlink($attachment);
    $visible = iif($moderate,0,1);
    // add to db
    if (!$allowduplicates) {
      if ($result=$DB_site->query_first("SELECT attachmentid
                                         FROM attachment
                                         WHERE userid = '$bbuserinfo[userid]'
                                           AND filedata = '".vb_escape($filestuff)."'")) {
         $threadresult=$DB_site->query_first("SELECT post.threadid as threadid,thread.title as title FROM post
                                             LEFT JOIN thread ON (thread.threadid = post.threadid)
                                             WHERE post.attachmentid=$result[attachmentid]");
        $threadresult['title'] = htmlspecialchars($threadresult['title']);
        eval("standarderror(\"".gettemplate("error_attachexists")."\");");
        exit;
      }
    }
    $DB_site->query("INSERT INTO attachment (attachmentid,userid,dateline,filename,filedata,visible) VALUES (NULL,$bbuserinfo[userid],".time().",'".vb_escape($attachment_name)."','".vb_escape($filestuff)."','$visible')");
    $attachmentid=$DB_site->insert_id();
  }
  return $attachmentid;
}


// ###################### Start getmodpermissions #######################
function getmodpermissions($forumid,$userid=-1) {
  // gets permissions, depending on given userid and forumid
  global $DB_site,$bbuserinfo;

  static $permscache;

  if ($userid==-1) {
    $userid=$bbuserinfo['userid'];
  }

  if (!isset($permscache[$forumid][$userid])) {

    $forumlist=getforumlist($forumid,"forumid");
    if ($forumlist!="") {
      $forumlist="AND ".$forumlist;
    }

    $getperms=$DB_site->query_first("SELECT * FROM moderator WHERE userid='$userid' $forumlist");

    $permscache[$forumid][$userid]=$getperms;

  } else {
    $getperms=$permscache[$forumid][$userid];
  }

  return $getperms;

}

// ###################### Start getmodpermissions #######################
function ismoderator($forumid=0,$action="",$userid=-1) {
  global $bbuserinfo, $DB_site;

  if ($userid==-1) {
    $userid=$bbuserinfo['userid'];
  }
  $supmod=getpermissions(0,$userid);
  if ($supmod['ismoderator']) {
    return 1;
  } else {
    if ($forumid==0) {
      if ($ismod=$DB_site->query_first("SELECT moderatorid FROM moderator WHERE userid='$userid' LIMIT 1"))
      {
        return 1;
      } else {
        return 0;
      }
    } else {
      if ($getmodperms=getmodpermissions($forumid,$userid) and $action=="") {
        return 1;
      } else {
        if ($getmodperms[$action]) {
          return 1;
        } else {
          return 0;
        }  // if has perms for this action
      }// if is mod for forum and no action set
    } // if forumid=0
  } // if is super moderator
}

// ###################### Start update forum count #######################
function updateforumcount($forumid) {
  global $DB_site;

  $forumid = intval($forumid);
  if (!$forumid) {
    return;
  }

  $threadcount=0;
  $replycount=0;

  $lastpost=0;
  $lastposter='';

  $forumslist='';
  $getchildforums=$DB_site->query("SELECT forumid,threadcount,replycount,parentlist,parentid,lastpost,lastposter FROM forum WHERE INSTR(CONCAT(',',parentlist,','),',$forumid,')>0");
  while ($getchildforum=$DB_site->fetch_array($getchildforums)) {
    if ($getchildforum['forumid']==$forumid) {
      $parentlist=$getchildforum['parentlist'];
    } else {
      if ($getchildforum['parentid']==$forumid) {
        $threadcount+=$getchildforum['threadcount'];
        $replycount+=$getchildforum['replycount'];
      }
      if ($getchildforum['lastpost']>$lastpost) {
        $lastpost=$getchildforum['lastpost'];
        $lastposter=$getchildforum['lastposter'];
      }
    }
    $forumslist.=",$getchildforum[forumid]";
  }
  $DB_site->free_result($getchildforums);

  // update forum counts
  if ($numpost=$DB_site->query_first("SELECT COUNT(*) AS threads,SUM(replycount) AS replies FROM thread WHERE forumid=$forumid AND visible=1 AND open<>10")) {
    $numberthreads=$numpost['threads']+$threadcount;
    $numberposts=$numpost['threads']+$numpost['replies']+$replycount;
  } else {
    $numberthreads=0;
    $numberposts=0;
  }

  $lastposts=$DB_site->query_first("SELECT MAX(lastpost) AS lastpost FROM thread WHERE forumid=$forumid AND visible=1 AND open<>10");
  if ($lastposts['lastpost']>$lastpost) {
	$lastposts=$DB_site->query_first("
			SELECT lastpost,lastposter
			FROM thread
			WHERE forumid = $forumid AND lastpost = '$lastposts[lastpost]'");
	$lastpost=$lastposts['lastpost'];
	$lastposter=$lastposts['lastposter'];
  }

  $lastpostquery=",lastpost='$lastpost',lastposter='".vb_escape($lastposter)."'";

  $DB_site->query("UPDATE forum SET replycount='$numberposts',threadcount='$numberthreads' $lastpostquery WHERE forumid='$forumid'");

  // this if check is incase someone's parent list gets messed up
  if ($parentlist) {
    $parents=explode(",",$parentlist);
    list($key,$val)=each($parents);
    list($key,$val)=each($parents);
    if ($val!=-1) {
      updateforumcount($val);
    }
  }
}

// ###################### Start update thread count #######################
function updatethreadcount($threadid) {
  global $DB_site,$threadcache;

    $replies=$DB_site->query_first("SELECT COUNT(*)-1 AS replies, SUM(attachment.visible) AS attachsum
                                    FROM post
                                    LEFT JOIN attachment ON attachment.attachmentid=post.attachmentid
                                    WHERE threadid='$threadid'");

    $lastposts=$DB_site->query_first("SELECT user.username,post.username AS postuser,post.dateline
                                      FROM post
                                      LEFT JOIN user ON user.userid=post.userid
                                      WHERE post.threadid='$threadid' AND visible>0
                                      ORDER BY dateline DESC
                                      LIMIT 1");
    $lastposter=iif($lastposts['username']=="",$lastposts['postuser'],$lastposts['username']);
    $lastposttime=$lastposts['dateline'];

    $firstposts=$DB_site->query_first("SELECT post.userid,user.username,post.username AS postuser,post.dateline
                                       FROM post
                                       LEFT JOIN user ON user.userid=post.userid
                                       WHERE post.threadid='$threadid' AND visible>0
                                       ORDER BY dateline
                                       LIMIT 1");
    $firstposter=iif($firstposts['username']=="",$firstposts['postuser'],$firstposts['username']);
    $firstposterid=$firstposts['userid'];
    $firstposttime=$firstposts['dateline'];

    $DB_site->query("UPDATE thread SET postusername='".vb_escape($firstposter)."',postuserid='$firstposterid',lastpost='$lastposttime',dateline='$firstposttime',replycount='$replies[replies]',attach='$replies[attachsum]', lastposter='".vb_escape($lastposter)."' WHERE threadid='$threadid'");

}

// ###################### Start delete thread #######################
function deletethread($threadid,$countposts=1) {
  global $DB_site;

  // decrement users post counts
  if ($threadinfo=getthreadinfo($threadid)) {
    $postids="";
    $attachmentids="";

    $posts=$DB_site->query("SELECT userid,attachmentid,postid FROM post WHERE threadid='$threadid'");
    while ($post=$DB_site->fetch_array($posts)) {
      if ($countposts) {
        if (!isset($userpostcount["$post[userid]"])) {
          $userpostcount["$post[userid]"] = -1;
        } else {
          $userpostcount["$post[userid]"]--;
        }
      }
      $postids.=$post['postid'].",";
      if ($post['attachmentid'] != 0) {
		  $attachmentids .= $post['attachmentid'].",";
      }
      unindexpost($post['postid']);
    }

    if ($attachmentids != '' ) {
      // make sure you don't remove attachments that are already in use!
      $checkattachments=$DB_site->query("SELECT DISTINCT attachmentid FROM post WHERE attachmentid IN ($attachmentids"."0) AND threadid<>'$threadid'");
      $omitattachmentids="";
      while ($omitattach=$DB_site->fetch_array($checkattachments)) {
        $omitattachmentids.=$omitattach['attachmentid'].",";
      }
      $DB_site->query("DELETE FROM attachment WHERE attachmentid IN ($attachmentids"."0) AND attachmentid NOT IN ($omitattachmentids"."0)");
    }

    if (is_array($userpostcount)) {
      while(list($postuserid,$subtract)=each($userpostcount)) {
        $DB_site->query("UPDATE user SET posts=posts$subtract WHERE userid='$postuserid'");
      }
    }

    if ($postids!="") {
      $DB_site->query("DELETE FROM post WHERE postid IN ($postids"."0)");
    }
    if ($threadinfo['pollid']!=0) {
      $DB_site->query("DELETE FROM poll WHERE pollid='$threadinfo[pollid]'");
      $DB_site->query("DELETE FROM pollvote WHERE pollid='$threadinfo[pollid]'");
    }
    $DB_site->query("DELETE FROM thread WHERE threadid='$threadid'");
    $DB_site->query("DELETE FROM thread WHERE open=10 AND pollid='$threadid'"); // delete redirects
    $DB_site->query("DELETE FROM threadrate WHERE threadid='$threadid'");
    $DB_site->query("DELETE FROM subscribethread WHERE threadid='$threadid'");
  }
}

// ###################### Start delete post #######################
function deletepost($postid,$countposts=1,$threadid=0) {
  global $DB_site;

  // decrement user post count
  if ($postinfo=getpostinfo($postid)) {
    if ($countposts) {
      $DB_site->query("UPDATE user SET posts=posts-1 WHERE userid='$postinfo[userid]'");
    }
    if ($postinfo['attachmentid']) {
			// make sure you don't remove attachments still in use
			$otherattachs=$DB_site->query("SELECT attachmentid FROM post WHERE attachmentid=$postinfo[attachmentid] AND threadid<>'$postinfo[threadid]'");
			if ($DB_site->num_rows($otherattachs)==0) {
				$DB_site->query("DELETE FROM attachment WHERE attachmentid=$postinfo[attachmentid]");
        $DB_site->query("UPDATE thread SET attach = attach - 1 WHERE threadid = '$threadid'");
			}
    }

    $DB_site->query("DELETE FROM post WHERE postid='$postid'");
  }
}

// ###################### Start make login code #######################
function makelogincode() {
  global $DB_site,$bbuserinfo,$session;

  if ($bbuserinfo['userid']==0) {
    eval("\$logincode = \"".gettemplate("username_loggedout")."\";");
  } else {
    eval("\$logincode = \"".gettemplate("username_loggedin")."\";");
  }

  return $logincode;
}

// ###################### Start un htmlspecialchars #######################
// UTF-8 aware chr() for numeric HTML entities. PHP 4's chr() returned a
// single byte, which cannot represent e.g. &#20013; (U+4E2D).
function vb_chr($codepoint) {
  $codepoint = (int) $codepoint;
  if ($codepoint < 0 or $codepoint > 0x10FFFF) {
    return '';
  }
  if (function_exists('mb_chr')) {
    return mb_chr($codepoint, 'UTF-8');
  }
  if ($codepoint < 0x80) {
    return chr($codepoint);
  }
  if ($codepoint < 0x800) {
    return chr(0xC0 | ($codepoint >> 6)) . chr(0x80 | ($codepoint & 0x3F));
  }
  if ($codepoint < 0x10000) {
    return chr(0xE0 | ($codepoint >> 12)) . chr(0x80 | (($codepoint >> 6) & 0x3F)) . chr(0x80 | ($codepoint & 0x3F));
  }
  return chr(0xF0 | ($codepoint >> 18)) . chr(0x80 | (($codepoint >> 12) & 0x3F)) . chr(0x80 | (($codepoint >> 6) & 0x3F)) . chr(0x80 | ($codepoint & 0x3F));
}

function unhtmlspecialchars($chars) {
  if (floor((float) phpversion())<4) {
    //php3
    $temp=addslashes($chars);
    $temp=preg_replace("/(&#)([0-9]*)(;)/siU","\".chr(intval('\\2')).\"",$temp);
    eval ("\$temp=\"$temp\";");
    $chars=stripslashes($temp);
  } else {
    //php4
    $chars=preg_replace_callback('/(&#)([0-9]*)(;)/iU', function ($m) {
      return vb_chr($m[2]);
    }, $chars);
  }

  $chars=str_replace("&gt;",">",$chars);
  $chars=str_replace("&lt;","<",$chars);
  $chars=str_replace("&quot;","\"",$chars);
  $chars=str_replace("&amp;","&",$chars);
  return $chars;
}

// ###################### Get Code Buttons #######################
function getcodebuttons () {
	global $vbcodemode,$vbcode_smilies;

	// set $vbcodemode to an integer, even if cookie is not set
	$vbcodemode = number_format($vbcodemode);
	// set mode based on cookie set by javascript
	$modechecked[$vbcodemode] = "checked";

	// get contents of the <select> font menus
	eval ("\$vbcode_sizebits = \"".gettemplate("vbcode_sizebits")."\";");
	eval ("\$vbcode_fontbits = \"".gettemplate("vbcode_fontbits")."\";");
	eval ("\$vbcode_colorbits = \"".gettemplate("vbcode_colorbits")."\";");

	eval ("\$vbcode_buttons = \"".gettemplate("vbcode_buttons")."\";");
	return $vbcode_buttons;
}

// ###################### Get Clicky Smilies #######################
function getclickysmilies () {
	global $DB_site,$session,$smcolumns,$smtotal;

	if ($smtotal > 0) {

		$smilies = $DB_site->query("SELECT title, smilietext, smiliepath FROM smilie");
		$numSmilies = $DB_site->num_rows($smilies);
		$totalSmilies = $numSmilies;

		if ($smtotal >= $numSmilies)
		  $smtotal = $numSmilies;
		elseif ($smtotal < $numSmilies) {
		  $numSmilies = $smtotal;
		  eval ("\$vbcode_smilies_getmore = \"".gettemplate("vbcode_smilies_getmore")."\";");
		}

		while (($smilie = $DB_site->fetch_array($smilies)) && ($i < $smtotal)) {
		  $smilie['smilietext'] = str_replace('"', '&quot;', $smilie['smilietext']);
		  $smilie['smilietext'] = str_replace("\\", "\\\\", $smilie['smilietext']);
		  $smilie['smilietext'] = str_replace("'", "\\'", $smilie['smilietext']);
		  eval ("\$smilieArray[\"".$i++."\"] = \"".gettemplate("vbcode_smiliebit")."\";");
		}

		// prevent division by zero errors
		if ($smcolumns==0) {
		  return;
		}

		$tableRows = ceil($numSmilies/$smcolumns);

		for ($i=0; $i<$tableRows; $i++) {
		  $smiliebits .= "<tr align='center'>\n";
		  for ($j=0; $j<$smcolumns; $j++) {
		    $smiliebits .= "<td>".$smilieArray[$count++]."&nbsp;</td>\n";
		  }
		  $smiliebits .= "</tr>\n";
		}

		eval ("\$vbcode_smilies = \"".gettemplate("vbcode_smilies")."\";");
		return $vbcode_smilies;

	} else {
		return "";
	}
}

// ###################### Start getforumrules #######################
$forumrules = '';
function getforumrules($foruminfo,$permissions) {
  // array of foruminfo and permissions for this forum
  global $offtext,$ontext,$forumrules,$session;

  $bbcodeon=iif($foruminfo['allowbbcode'],$ontext,$offtext);
  $imgcodeon=iif($foruminfo['allowimages'],$ontext,$offtext);
  $htmlcodeon=iif($foruminfo['allowhtml'],$ontext,$offtext);
  $smilieson=iif($foruminfo['allowsmilies'],$ontext,$offtext);

  $notword = 'not';
  $rules['postnew']=iif($permissions['canpostnew'],'',$notword);
  $rules['postreply']=iif($permissions['canreplyown'] or $permissions['canreplyothers'],'',$notword);
  $rules['edit']=iif($permissions['caneditpost'],'',$notword);
  $rules['attachment']=iif($permissions['canpostattachment'] and ($permissions['canpostnew'] or $permissions['canreplyown'] or $permissions['canreplyothers']),'',$notword);

  eval("\$forumrules = \"".gettemplate('forumrules')."\";");
}

// ###################### Start getmaxattachsize ###############
function getmaxattachsize() {
  if (function_exists("ini_get")) {
    $temp = ini_get("upload_max_filesize");
  } else {
    $temp = get_cfg_var("upload_max_filesize");
  }
  if ($temp) {
    if (ereg("[^0-9]", $temp)) {
      // max attach size is defined in megabytes which doesn't work in the MAX_FILE_SIZE field
      return (intval($temp) * 1048576);
    } else {
      return $temp;
    }
  } else {
    return "10000000";
  }
}

// ###################### Start replacesession ###############
function replacesession($url) {
  // replace the sessionhash in $url with the current one
  global $session;

  $url = vb_escape($url);
  $url=ereg_replace("s=[a-z0-9]{32}&","",$url);
  $url=ereg_replace("\\?s=[a-z0-9]{32}","",$url);
  $url=str_replace("s=", "", $url);

  if (strpos($url,"?")>0) {
    $url .= "&amp;s=$session[dbsessionhash]";
  } else {
    $url .= "?s=$session[dbsessionhash]";
  }


  return $url;
}

// ###################### Start stripsession ###############
function stripsession($text) {

	return preg_replace('/(s|sessionhash)=[a-z0-9]{32}(&|&amp;){0,1}/', '', $text);
}

// ####################### add these functions if they aren't defined #######
if (!function_exists("is_uploaded_file")) {
  function is_uploaded_file($filename) {
    if (function_exists('ini_get')) {
      $utdval = ini_get('upload_tmp_dir');
    } else {
      $utdval = get_cfg_var('upload_tmp_dir');
    }

    if (!$tmp_file = str_replace('\\', '/', $utdval)) {
        $tmp_file = dirname(str_replace('\\', '/', tempnam('', '')));
    }
    $tmp_file .= '/' . basename($filename);

    // remove any trailing slashes and standardize directory seperator to /
    return (ereg_replace('/+', '/', $tmp_file) == str_replace('\\', '/', $filename));
  }
}

if (!function_exists('move_uploaded_file')) {
  function move_uploaded_file($filename, $destination) {
     if (is_uploaded_file($filename))  {
       if (copy($filename,$destination)) {
         return true;
       } else {
         return false;
       }
     } else {
       return false;
     }
   }
}

// ########### add the mysql_escape_string() function if it doesn't exist ######
if (!function_exists("mysql_escape_string"))  {
  function mysql_escape_string($string) {
    $string = str_replace("\\", "\\\\", $string);
    $string = str_replace("\0", '\0', $string);
    $string = str_replace("\n", '\n', $string);
    $string = str_replace("\r", '\r', $string);
    $string = str_replace("'", '\\\'', $string);
    $string = str_replace("\"", '\"', $string);
    $string = str_replace("\032", "\\Z", $string);

    return $string;
  }
}

// ############## Update the Forum that the user is in ###########
function updateuserforum($forumid) {

	global $showforumusers, $bbuserinfo, $bypass, $cookietimeout, $shutdownqueries, $noshutdownfunc, $DB_site, $ourtimenow;

	if ($showforumusers AND $bbuserinfo['userid'] != 0 AND !isset($bypass)) {
		// This overwrites the shutdown query in sessions.php so we can update the $forumid and not have to run
		// two queries
		if ($ourtimenow - $bbuserinfo['lastactivity'] > $cookietimeout) {
		      if ($noshutdownfunc) {
		        $DB_site->query("UPDATE user SET inforum='$forumid' WHERE userid='$bbuserinfo[userid]'");
		      } else {
		        $shutdownqueries[99]="UPDATE user SET lastvisit=lastactivity,lastactivity=$ourtimenow,inforum='$forumid' WHERE userid='$bbuserinfo[userid]'";
		      }
		} else {
			if ($noshutdownfunc) {
				$DB_site->query("UPDATE user SET inforum='$forumid' WHERE userid='$bbuserinfo[userid]'");
			} else {
			    $shutdownqueries[99]="UPDATE user SET lastactivity=$ourtimenow,inforum='$forumid' WHERE userid='$bbuserinfo[userid]'";
			}
		}
	}
}

// ############## Send out email notification ############
function sendnotification ($threadid, $userid, $postid) {
  // $threadid = threadid to send from;
  // $userid = userid of who made the post
  // $postid = only sent if post is moderated -- used to get username correctly

  global $DB_site, $enableemail, $message, $bbtitle, $webmasteremail, $bburl, $postusername, $bbuserinfo;

  if (!$enableemail) {
    return;
  }

  $threadinfo = getthreadinfo($threadid);
  $foruminfo = getforuminfo($threadinfo['forumid']);

  // get last reply time
  if ($postid) {
    $dateline=$DB_site->query_first("SELECT dateline
                                     FROM post
                                     WHERE postid='$postid'");
    $lastposttime=$DB_site->query_first("SELECT dateline
                                         FROM post
                                         WHERE threadid = '$threadid'
                                               AND dateline < $dateline[dateline]
                                               AND visible = 1
                                         ORDER BY dateline DESC
                                         LIMIT 1");
  } else {
    $lastposttime=$DB_site->query_first("SELECT dateline
                                         FROM post
                                         WHERE threadid='$threadid'
                                         ORDER BY dateline DESC
                                         LIMIT 1");
  }

  // MySQL 8 / MariaDB: JOIN binds tighter than the comma operator, so
  // "FROM a,b,c LEFT JOIN d ON (...a.col...)" is parsed as "a,b,(c LEFT JOIN d)"
  // and a.col is unknown in ON. Use explicit JOINs instead.
  $useremails=$DB_site->query("SELECT user.*, style.templatesetid
                               FROM subscribethread
                               JOIN user ON subscribethread.userid=user.userid
                               JOIN usergroup ON usergroup.usergroupid=user.usergroupid
                               LEFT JOIN style ON (IF(user.styleid=0, 1, user.styleid)=style.styleid)
                               WHERE subscribethread.threadid='$threadid'
                                 AND user.userid<>'$userid'
                                 AND user.usergroupid<>'3'
                                 AND usergroup.canview = 1
                                 AND user.lastactivity>'$lastposttime[dateline]'");
  $threadinfo['title']=unhtmlspecialchars($threadinfo['title']);

  $temp = $bbuserinfo['username'];
  if ($postid) {
    $postinfo = getpostinfo($postid);
    $bbuserinfo['username'] = unhtmlspecialchars($postinfo['username']);
  } else {
    if (!$bbuserinfo['userid']) {
      $bbuserinfo['username'] = unhtmlspecialchars($postusername);
    } else {
      $bbuserinfo['username'] = unhtmlspecialchars($bbuserinfo['username']);
    }
  }

  $getmail = $DB_site->query("SELECT template, templatesetid, title FROM template WHERE title IN ('email_notify', 'emailsubject_notify') ORDER BY templatesetid DESC");
  while ($fgetmail = $DB_site->fetch_array($getmail)) {
    $mailcache["$fgetmail[templatesetid]"][$fgetmail['title']] = str_replace("\\'","'", vb_escape($fgetmail['template']));
  }

  while ($touser=$DB_site->fetch_array($useremails)) {
    $touser['username']=unhtmlspecialchars($touser['username']);

    // check templatesetid is loaded into cache, else use -1
    if (!isset($mailcache["$touser[templatesetid]"]['email_notify']))
    {
      $emailmsg = $mailcache["-1"]['email_notify'];
    }
    else
    {
      $emailmsg = $mailcache["$touser[templatesetid]"]['email_notify'];
    }

    if (!isset($mailcache["$touser[templatesetid]"]['emailsubject_notify']))
    {
      $emailsubject = $mailcache["-1"]['emailsubject_notify'];
    }
    else
    {
      $emailsubject = $mailcache["$touser[templatesetid]"]['emailsubject_notify'];
    }

    eval("\$emailmsg = \"$emailmsg\";");
    eval("\$emailsubject = \"$emailsubject\";");

    vbmail($touser['email'], $emailsubject, $emailmsg);
  }
  $bbuserinfo['username'] = $temp;
}

// ###################### Start get_bbthreadcookie #######################
$bbcookiecache = array();
function get_bbarraycookie($cookiename, $id) {
	// gets the value for a array stored in a cookie
	global $_COOKIE;
	global $bbcookiecache;

	if (!isset($bbcookiecache[$cookiename])) {
		$bbcookiecache[$cookiename] = @unserialize($_COOKIE["bb$cookiename"]);
	}
	return intval($bbcookiecache[$cookiename][$id]);
}

// ###################### Start set_bbthreadcookie #######################
function set_bbarraycookie($cookiename, $id, $value, $permanent = 0) {
	// sets the value for a array and sets the cookie
	global $_COOKIE;
	global $bbcookiecache;

	if (!isset($bbcookiecache[$cookiename])) {
		$bbcookiecache[$cookiename] = @unserialize($_COOKIE["bb$cookiename"]);
	}

	$bbcookiecache[$cookiename][$id] = $value;

	vbsetcookie("bb$cookiename", serialize($bbcookiecache[$cookiename]), $permanent);

}


// ###################### Start doshutdown #######################
$shutdownqueries=array();
function doshutdown() {
  global $shutdownqueries,$DB_site;

  if (is_array($shutdownqueries))
  {
    while (list($devnul,$query)=each($shutdownqueries))
    {
      $DB_site->query($query);
    }
  }

  global $cookietimeout,$bypass,$bbuserinfo,$session;

  if ($bypass and $bbuserinfo['userid'])
  {
    // if the user has sent bypass=1 through the url (to prevent updating of last activity/visit time), reset the session
    // so the below function doesn't do update their info anyway
    $userinfo=$DB_site->query_first("SELECT lastactivity FROM user WHERE userid='$bbuserinfo[userid]'");
    $DB_site->query("UPDATE session SET lastactivity='$userinfo[lastactivity]' WHERE sessionhash='".vb_escape($session['dbsessionhash'])."'");
  }

  // update user table from session table in bulk
  mt_srand ((float) microtime() * 1000000);
  if (mt_rand(1,100)=='50')
  {
    $oldsessions = $DB_site->query("SELECT userid,lastactivity FROM session WHERE userid <> 0 AND lastactivity<'" . (time() - $cookietimeout) . "'");
    while ($oldsession = $DB_site->fetch_array($oldsessions))
    {
      $DB_site->query("UPDATE user SET lastactivity=$oldsession[lastactivity] WHERE userid=$oldsession[userid] AND lastactivity<$oldsession[lastactivity]");
    }
	$DB_site->query('DELETE FROM session WHERE lastactivity<'.(time()-$cookietimeout));

	//searches expire after a week:
	$DB_site->query("DELETE FROM search WHERE dateline<".(time()-(7*24*60*60)));
  }
}

if (!$noshutdownfunc) {
  register_shutdown_function("doshutdown");
}

define('SIMPLE_VERSION', preg_replace('#[^\d]#s', '', $templateversion));

?>