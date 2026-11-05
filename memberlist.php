<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

$templatesused = "memberlist_letterselected,memberlist_letter,postbit_search,postbit_useremail,icq,aim,yahoo,postbit_homepage,postbit_sendpm,postbit_profile,memberlistbit,memberlist,memberlistsearch";

require("./global.php");

$letter = "#";
$linkletter = urlencode("#");

if ($ltr==$letter) {
	eval("\$letterbits = \"".gettemplate("memberlist_letterselected")."\";");
} else {
	eval("\$letterbits = \"".gettemplate("memberlist_letter")."\";");
}

for ($i=65;$i<91;$i++) {
	$letter = chr($i); $linkletter = $letter;
	if ($ltr==$letter) {
		eval("\$letterbits .= \"".gettemplate("memberlist_letterselected")."\";");
	} else {
		eval("\$letterbits .= \"".gettemplate("memberlist_letter")."\";");
	}
}

if (!$enablememberlist) {
  eval("standarderror(\"".gettemplate("error_nomemberlist")."\");");
  exit;
}

if (!isset($action) or $action=="") {
  $action="getall";
}

$permissions=getpermissions();
if (!$permissions['canview'] or !$permissions['canviewmembers']) {
  show_nopermission();
}

if ($action=="getall") {

  // get conditions
  $condition="1=1";
  if ($usememberlistadvsearch) {
    if ($ausername!="") {
      $condition.=" AND username LIKE '%".vb_escape(htmlspecialchars($ausername))."%' ";
    }
    if ($email!="") {
      $condition.=" AND email LIKE '%".vb_escape(htmlspecialchars($email))."%' ";
    }
    if ($homepage!="") {
      $condition.=" AND homepage LIKE '%".vb_escape(htmlspecialchars($homepage))."%' ";
    }
    if ($icq!="") {
      $condition.=" AND icq LIKE '%".vb_escape($icq)."%' ";
    }
    if ($aim!="") {
      $condition.=" AND aim LIKE '%".vb_escape(htmlspecialchars($aim))."%' ";
    }
    if ($yahoo!="") {
      $condition.=" AND yahoo LIKE '%".vb_escape(htmlspecialchars($yahoo))."%' ";
    }
    if ($joindateafter!="") {
      $condition.=" AND joindate>UNIX_TIMESTAMP('".vb_escape(strtolower($joindateafter))."')";
    }
    if ($joindatebefore!="") {
      $condition.=" AND joindate<UNIX_TIMESTAMP('".vb_escape(strtolower($joindatebefore))."')";
    }
    if ($lastpostafter!="") {
      $condition.=" AND lastpost>UNIX_TIMESTAMP('".vb_escape(strtolower($lastpostafter))."')";
    }
    if ($lastpostbefore!="") {
      $condition.=" AND lastpost<UNIX_TIMESTAMP('".vb_escape(strtolower($lastpostbefore))."')";
    }
    if ($postslower!="") {
      $condition.=" AND posts>'".intval($postslower)."'";
    }
    if ($postsupper!="") {
      $condition.=" AND posts<'".intval($postsupper)."'";
    }
  } else {
    $orderby="";
    $direction="";
  }

  if ($what=="topposters") {
    $orderby="posts";
    $direction="DESC";
  }
  if ($what=="datejoined") {
    $orderby="joindate";
    $direction="DESC";
  }
  if ($ltr!="") {
  	if ($ltr=="#") {
		$condition = "username NOT REGEXP(\"^[a-zA-Z]\")";
	} else {
	  $ltr = chr(intval(ord($ltr)));
		$condition = "username LIKE(\"".vb_escape($ltr)."%\")";
	}
  }

  if ($orderby=="" or ($orderby!="username" and $orderby!="posts" and $orderby!="joindate" and $orderby!="lastpost")) {
    $what = 'username';
    $orderby="username";
  }

  if ($direction!="DESC") {
    $direction = "ASC";
  }

  $memberlistbits = "";
  $counter=0;

  $userscount=$DB_site->query_first("SELECT COUNT(*) AS users
                                     FROM user,userfield
                                     WHERE $condition AND
                                           user.userid = userfield.userid
                                           ".iif($memberAllGood, " AND usergroupid NOT IN (1,3,4) ", "")."
                                           ");
  $totalusers=$userscount['users'];
  sanitize_pageresults($totalusers, $pagenumber, $perpage, 200, $memberlistperpage);

  $limitlower=($pagenumber-1)*$perpage+1;
  $limitupper=($pagenumber)*$perpage;

  if ($limitupper>$totalusers) {
    $limitupper=$totalusers;
    if ($limitlower>$totalusers) {
      $limitlower=$totalusers-$perpage;
    }
  }
  if ($limitlower<=0) {
    $limitlower=1;
  }

  $users=$DB_site->query("SELECT *
                          FROM user,userfield
                          WHERE $condition AND
                                user.userid = userfield.userid
                                ".iif($memberAllGood, " AND usergroupid NOT IN (1,3,4) ", "")."
                          ORDER BY $orderby $direction
                          LIMIT ".($limitlower-1).",$perpage");

  $counter=0;

  while ($userinfo=$DB_site->fetch_array($users) and $counter++<$perpage) {

    $post=$userinfo;

    $userinfo['datejoined']=vbdate($dateformat,$userinfo['joindate']);

		if ($userinfo['posts']) {
			eval("\$userinfo['search'] = \"".gettemplate("postbit_search")."\";");
		} else {
			$userinfo['search'] = "&nbsp;";
		}

    if ($userinfo['showemail'] and $displayemails) {
      eval("\$userinfo[useremail] = \"".gettemplate("postbit_useremail")."\";");
    } else {
      $userinfo['useremail']="&nbsp;";
    }
    if ($userinfo['icq']!="") {
      eval("\$userinfo[icqicon] = \"".gettemplate("icq")."\";");
    } else {
      $userinfo['icq']="&nbsp;";
    }
    if ($userinfo['aim']!="") {
      eval("\$userinfo[aimicon] = \"".gettemplate("aim")."\";");
    } else {
      $userinfo['aim']="&nbsp;";
    }
    if ($userinfo['yahoo']!="") {
      eval("\$userinfo[yahooicon] = \"".gettemplate("yahoo")."\";");
    } else {
      $userinfo['yahoo']="&nbsp;";
    }

    if ($userinfo['homepage']!="" and $userinfo['homepage']!="http://") {
      eval("\$userinfo[homepage] = \"".gettemplate("postbit_homepage")."\";");
    } else {
      $userinfo['homepage']="&nbsp;";
    }

    if ($userinfo['receivepm']) {
      eval("\$userinfo[pmlink] = \"".gettemplate("postbit_sendpm")."\";");
    } else {
      $userinfo['pmlink'] = "&nbsp;";
    }

    eval("\$userinfo[profile] = \"".gettemplate("postbit_profile")."\";");

    eval("\$memberlistbits .= \"".gettemplate("memberlistbit")."\";");

  }  // end while

  $what = htmlspecialchars($what);
  $pagenav = getpagenav($totalusers,"memberlist.php?s=$session[sessionhash]&amp;action=" . urlencode($action) . "&amp;what=" . urlencode($what) . "&amp;ltr=" . urlencode( $ltr ) . "&amp;perpage=" . urlencode($perpage) . "&amp;orderby=" . urlencode($orderby) . "&amp;ausername=" . urlencode($ausername) . "&amp;homepage=" . urlencode($homepage) . "&amp;icq=" . urlencode($icq) . "&amp;aim=" . urlencode($aim) . "&amp;yahoo=" . urlencode($yahoo) . "&amp;joindateafter=" . urlencode($joindateafter) . "&amp;joindatebefore=" . urlencode($joindatebefore) . "&amp;lastpostafter=" . urlencode($lastpostafter) . "&amp;lastpostbefore=" . urlencode($lastpostbefore) . "&amp;postslower=" . urlencode($postslower) . "&amp;postsupper=" . urlencode($postsupper) . "&amp;direction=" . urlencode($direction));

  eval("dooutput(\"".gettemplate("memberlist")."\");");
} #end if ($action=="getall")

if ($action=="search") {
  if (!$usememberlistadvsearch) {
    eval("standarderror(\"".gettemplate("error_nomemberlistsearch")."\");");
    exit;
  }

  eval("dooutput(\"".gettemplate("memberlistsearch")."\");");

} #end if ($action=="search")

?>