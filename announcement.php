<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

$templatesused="postbit_useremail,icq,aim,yahoo,postbit_homepage,announcebit,announcement";

require("./global.php");

$foruminfo = verifyid("forum",$forumid,1,1);

$curforumid = $foruminfo['forumid'];
makeforumjump();

$getperms=getpermissions($foruminfo['forumid']);
if (!$getperms['canview']) {
  show_nopermission();
}

$counter=0;

$datenow=time();
$datecut = $datenow - $cookietimeout;

unset($announcebits);

$forumlist=getforumlist($forumid,"announcement.forumid");
$announcements=$DB_site->query("
SELECT
announcementid,startdate,enddate,announcement.title,pagetext,user.*,userfield.*
".iif($avatarenabled,",avatar.avatarpath,NOT ISNULL(customavatar.avatardata) AS hascustomavatar,customavatar.dateline AS avatardateline","")."
FROM announcement
LEFT JOIN user ON user.userid=announcement.userid
LEFT JOIN userfield ON userfield.userid=announcement.userid
".iif ($avatarenabled,"LEFT JOIN avatar ON avatar.avatarid=user.avatarid LEFT JOIN customavatar ON customavatar.userid=announcement.userid","")."
WHERE startdate<='$datenow' AND enddate>='$datenow' AND $forumlist ORDER BY startdate DESC");
while ($post=$DB_site->fetch_array($announcements)) {


  $counter++;
  //$allowhtml = 1;
  //$announcebits .= getpostbit($post);

  if ($counter%2==0) {
    $backcolor="{firstaltcolor}";
  	$post['bgclass'] = "alt1";
  } else {
    $backcolor="{secondaltcolor}";
		$post['bgclass'] = "alt2";
  }

  $post['startdate']=vbdate($dateformat,$post['startdate']);
  $post['enddate']=vbdate($dateformat,$post['enddate']);

  if ($post['startdate']>$bbuserinfo['lastvisit']) {
    $post['posticon']="<img src=\"{imagesfolder}/posticonnew.gif\" border=\"0\" alt=\"\">";
  } else {
    $post['posticon']="<img src=\"{imagesfolder}/posticon.gif\" border=\"0\" alt=\"\">";
  }

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
  if ($avatarurl=="") {
  	 $post['avatar']="";
  } else {
  	 eval("\$post[avatar] = \"".gettemplate("postbit_avatar")."\";");
  }

  if ($post['customtitle']==2) {
    $post['usertitle'] = htmlspecialchars($post['usertitle']);
  }

  $post['joindate']=vbdate($registereddateformat,$post['joindate']);

  if ($post['showemail'] and $displayemails) {
  	 eval("\$post[useremail] = \"".gettemplate("postbit_useremail")."\";");
  } else {
  	 $post['useremail']="";
  }

  if ($post['icq']!="") {
    $userinfo['icq'] = $post['icq'];
  	 eval("\$post[icqicon] = \"".gettemplate("icq")."\";");
  } else {
  	 $post['icq']="";
  	 $post['icqicon']="";
  }

  if ($post['aim']!="") {
    $userinfo['aim'] = $post['aim'];
  	 eval("\$post[aimicon] = \"".gettemplate("aim")."\";");
  } else {
  	 $post['aim']="";
  	 $post['aimicon']="";
  }

  if ($post['yahoo']!="") {
    $userinfo['yahoo'] = $post['yahoo'];
  	 eval("\$post[yahooicon] = \"".gettemplate("yahoo")."\";");
  } else {
  	 $post['yahoo']="";
  	 $post['yahooicon']="";
  }

  if ($post['homepage']!="" and $post['homepage']!="http://") {
  	 eval("\$post[homepage] = \"".gettemplate("postbit_homepage")."\";");
  } else {
  	 $post['homepage']="";
  }

  if ($post['receivepm']) {
  	 eval("\$post[pmlink] = \"".gettemplate("postbit_sendpm")."\";");
  } else {
  	 $post['pmlink'] = "";
  }

  eval("\$post[profile] = \"".gettemplate("postbit_profile")."\";");

  $message = $post['pagetext'];
  $post['signature'] = bbcodeparse($post['signature'],0,$allowsmilies);

  eval("\$announcebits .= \"".gettemplate("announcebit")."\";");

}

$announcecount=$counter;

eval("dooutput(\"".gettemplate("announcement")."\");");

?>