<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

$templatesused = "newpost_postpreview,error_nosubject,redirect_postthanks,email_moderator,emailsubject_moderator,newthread_postpoll,newpost_attachment,newpost_disablesmiliesoption,forumrules,newthread,posticons,posticonbit";
$templatesused.=",vbcode_smilies,vbcode_smiliebit,vbcode_smilies_getmore,vbcode_buttons,vbcode_sizebits,vbcode_fontbits,vbcode_colorbits";
require("./global.php");

// get decent textarea size for user's browser
$textareacols = gettextareawidth();

$action = trim($action);
if (!isset($action) or $action=="") {
  $action="newthread";
}

unset ($postpreview);
unset ($parseurlchecked);
unset ($emailchecked);
unset ($disablesmilieschecked);
unset ($signaturechecked);
unset ($previewchecked);

$forumid=verifyid("forum",$forumid);

$foruminfo=getforuminfo($forumid);
if ($foruminfo['allowposting']==0) {
  eval("standarderror(\"".gettemplate("error_forumclosed")."\");");
}

if ($foruminfo['allowposting']==0) {
  eval("standarderror(\"".gettemplate("error_forumnoreply")."\");");
  exit;
}

$permissions=getpermissions($forumid);
if (!$permissions['canview'] or !$permissions['canpostnew']) {
  show_nopermission();
}

updateuserforum($foruminfo['forumid']);

// ############################### start post thread ###############################
if ($_POST['action']=="postthread") {

  // decode check boxes
  $parseurl=iif(trim($parseurl)=="yes",1,0);
  $email=iif(trim($email)=="yes",1,0);
  $allowsmilie=iif(trim($disablesmilies)=="yes",0,1);
  $signature=iif(trim($signature)=="yes",1,0);
  $preview=iif(trim($preview)!="",1,0);
  $postpoll=iif(trim($postpoll)=="yes",1,0);

  $visible=iif($foruminfo['moderatenew'],0,1);

  // auto bypass queueing for admins/mods
  if (ismoderator($foruminfo['forumid'])) {
    $visible=1;
  }

  $message=trim($message);
  if ($parseurl) {
    $message=parseurl($message);
  }
  // remove sessionhash from urls:
  $message = stripsession($message);

  if (strlen($message)>$postmaxchars and $postmaxchars!=0) {
    eval("standarderror(\"".gettemplate("error_toolong")."\");");
  }

  if ($preview) {
    // preview? yes:
    $previewpost=1;
    $previewmessage=bbcodeparse($message,$foruminfo['forumid'],$allowsmilie);

    $toomanyimages = '';
    if ($maximages!=0) {
      if (countchar($previewmessage,"<img")>$maximages) {
        //too many images
        eval("\$toomanyimages = \"".gettemplate("error_previewtoomanyimages")."\";");
      }
    }

    if ($signature) {
      $post['signature'] = bbcodeparse($bbuserinfo['signature'],0,$allowsmilie);
      eval("\$post[signature] = \"".gettemplate("postbit_signature")."\";");
      $previewmessage.=$post['signature'];
    }

    eval("\$postpreview=\"".gettemplate("newpost_postpreview")."\";");

    $parseurlchecked=iif($parseurl,"checked","");
    $emailchecked=iif($email,"checked","");
    $disablesmilieschecked=iif(!$allowsmilie,"checked","");
    $signaturechecked=iif($signature,"checked","");
    $previewchecked=0;

    $action="newthread";
  } else {
    // check for subject and message
    if (trim($subject)=="" or trim($message)=="") {
      eval("standarderror(\"".gettemplate("error_nosubject")."\");");
      exit;
    }

    $username = $_POST['username'];
    $password = $_POST['password'];

    if ($bbuserinfo['userid'] == 0) {
      $password = $_POST['password'];
      $username = trim($_POST['username']);
      $username = eregi_replace("( ){2,}", " ", $username);

      if (empty($username)) {
        eval("standarderror(\"".gettemplate("error_nousername")."\");");
      } else if (strlen($username) < $minuserlength) {
        eval("standarderror(\"".gettemplate("error_usernametooshort")."\");");
      } else if (strlen($username) > $maxuserlength) {
        eval("standarderror(\"".gettemplate("error_usernametoolong")."\");");
      } else if ($username != censortext($username)) {
        eval("standarderror(\"".gettemplate("error_usernametaken")."\");");
      } else if ($illegalusernames != '') {
        $usernames = preg_split('/( )+/', trim(strtolower($illegalusernames)), -1, PREG_SPLIT_NO_EMPTY);
        $tempusername = strtolower($username);
        while ( list( , $val ) = each( $usernames ) ) {
          if (strstr($tempusername, $val) != '') {
            eval("standarderror(\"".gettemplate("error_usernametaken")."\");");
          }
        }
      }

      if ($userinfo=$DB_site->query_first("
      		SELECT user.*,userfield.*
      		FROM user,userfield
      		WHERE 	(username='".vb_escape(htmlspecialchars($username))."' OR
      				username='".vb_escape(eregi_replace("[^A-Za-z0-9]","",$username))."') AND
      				user.userid=userfield.userid")) {
        if (!$password) {
          eval("standarderror(\"".gettemplate("error_usernametaken")."\");");
        } elseif (md5($password)!=$userinfo['password']) {
          eval("standarderror(\"".gettemplate("error_wrongpassword")."\");");
        } else {
          $bbuserinfo = $userinfo;
          $postusername = $bbuserinfo['username'];

          if ($user['cookieuser']==1) {
            vbsetcookie("bbuserid",$user['userid'], true, true);
            vbsetcookie("bbpassword",$user['password'], true, true);
          }
          $DB_site->query("UPDATE session SET userid='$bbuserinfo[userid]' WHERE sessionhash='".vb_escape($session['dbsessionhash'])."'");
        }
      } else {
        $postusername = htmlspecialchars($username);
      }
    } else {
      $postusername = $bbuserinfo['username'];
    }

    // not previewing:
    if ($enablefloodcheck) {
      if ($bbuserinfo['userid']!=0 and time()-$bbuserinfo['lastpost']<=$floodchecktime and !ismoderator($foruminfo['forumid'])) {
        // check whether admin
        eval("standarderror(\"".gettemplate("error_floodcheck")."\");");
        exit;
      }
    }

    if (!isset($iconid) or $iconid=="") {
      $iconid=0;
    }
    $iconid = intval($iconid);

    $permissions=getpermissions($forumid);
    if (!$permissions['canview'] or !$permissions['canpostnew']) {
			show_nopermission();
    }

    if ($logip==1 or $logip==2) {
      if ($temp = $_SERVER['REMOTE_ADDR']) {
        $ipaddress = $temp;
      } else if ($temp = $REMOTE_ADDR) {
        $ipaddress = $temp;
      } else {
        $ipaddress = $HTTP_HOST;
      }
    } else {
      $ipaddress="";
    }

    // check max images
    if ($maximages!=0) {
      $parsedmessage=bbcodeparse($message,$foruminfo['forumid'],$allowsmilie);
      if (countchar($parsedmessage,"<img")>$maximages) {
        eval("standarderror(\"".gettemplate("error_toomanyimages")."\");");
        exit;
      }
    }

    $subject=censortext($subject);
    $message=censortext($message);

    // remove all caps subjects
    if ($stopshouting and $subject==strtoupper($subject)) {
      $subject=ucwords(strtolower($subject));
    }

    // sort attachement
    if (is_array($_FILES)) {
    	$attachment = $_FILES['attachment']['tmp_name'];
    	$attachment_name = $_FILES['attachment']['name'];
    	$attachment_size = $_FILES['attachment']['size'];
    }
    if ($permissions['canpostattachment'] and trim($attachment)!="none" and trim($attachment)!="" and trim($attachment_name)!="") {
      $attachmentid=acceptupload($foruminfo['moderateattach']);
      if (!$foruminfo['moderateattach']) {
        $attachcount = 1;
      } else {
        $attachcount = 0;
      }
    } else {
      $attachmentid=0;
      $attachcount=0;
    }

    // see if there has been a post identical to this in the last 5 mins.
    // If so, update that one, as user has probably done a double post
    $datecut=time()-300;

    if ($prevpost=$DB_site->query_first("SELECT threadid,visible FROM thread WHERE forumid='$forumid' AND postuserid='$bbuserinfo[userid]' AND title='".vb_escape(htmlspecialchars($subject))."' AND dateline>$datecut AND replycount=0")) {
      $threadid=$prevpost['threadid'];

      // subscribe to thread
      if ($email) {
        if (!$checkid=$DB_site->query_first("SELECT subscribethreadid FROM subscribethread WHERE userid=$bbuserinfo[userid] AND threadid=$threadid")) {
          $DB_site->query("INSERT INTO subscribethread (subscribethreadid,userid,threadid) VALUES (NULL,$bbuserinfo[userid],$threadid)");
        }
      }

      $DB_site->query("UPDATE thread SET title='".vb_escape(htmlspecialchars($subject))."',iconid='$iconid' WHERE threadid='$threadid'");
      $posts=$DB_site->query_first("SELECT MIN(post.postid) AS minpost,attachmentid FROM post,thread WHERE thread.threadid=post.threadid AND thread.threadid='$threadid' GROUP BY thread.threadid");
      if ($posts['attachmentid']!=0) {
	          $DB_site->query("DELETE FROM attachment WHERE attachmentid = '$posts[attachmentid]'");
	    if ($attachmentid==0) {
		        $DB_site->query("UPDATE thread SET attach = attach - 1 WHERE threadid = '$threadid'");
        }
      }
      $DB_site->query("UPDATE post SET pagetext='".vb_escape($message)."',allowsmilie='$allowsmilie',showsignature='$signature',iconid='$iconid',attachmentid='$attachmentid' WHERE postid='$posts[minpost]'");

      // redirect
      if ($prevpost['visible']) {
        $goto="showthread.php?s=$session[sessionhash]&amp;threadid=$threadid";
      } else {
        $goto="forumdisplay.php?s=$session[sessionhash]&amp;forumid=$forumid";
      }
      eval("standardredirect(\"".gettemplate("redirect_postthanks")."\",\"$goto\");");

    } else {
      //create new thread
      if ($postpoll) {
         $visible = 0;
      }
      $DB_site->query("INSERT INTO thread (threadid,title,lastpost,forumid,open,replycount,postusername,postuserid,lastposter,dateline,iconid,visible,attach) VALUES (NULL,'".vb_escape(htmlspecialchars($subject))."','".time()."','$forumid','1','0','".vb_escape($postusername)."','$bbuserinfo[userid]','".vb_escape($postusername)."','".time()."','$iconid','$visible','$attachcount')");
      $threadid=$DB_site->insert_id();

      // subscribe to thread
      if ($email and $bbuserinfo['userid']!=0) {
        if (!$checkid=$DB_site->query_first("SELECT subscribethreadid FROM subscribethread WHERE userid=$bbuserinfo[userid] AND threadid=$threadid")) {
          $DB_site->query("INSERT INTO subscribethread (subscribethreadid,userid,threadid) VALUES (NULL,$bbuserinfo[userid],$threadid)");
        }
      }

      /*if ($bbuserinfo[userid]==0) {
        $postusername=$bbuserinfo[username];
      } else {
        $postusername="";
      }*/
      // create first post
      $DB_site->query("INSERT INTO post (postid,threadid,title,username,userid,dateline,attachmentid,pagetext,allowsmilie,showsignature,ipaddress,iconid,visible) VALUES (NULL,'$threadid','".vb_escape(htmlspecialchars($subject))."','".vb_escape($postusername)."','$bbuserinfo[userid]','".time()."','$attachmentid','".vb_escape($message)."','$allowsmilie','$signature','$ipaddress','$iconid','1')");
      $postid=$DB_site->insert_id();

      indexpost($postid,1);

      // update forum stuff
      if ($visible==1) {
        $DB_site->query("UPDATE forum SET replycount=replycount+1,threadcount=threadcount+1,lastpost='".time()."',lastposter='".vb_escape($postusername)."' WHERE forumid IN ($foruminfo[parentlist])");
      }

      // update user stuff
      $dotitle="";
      if ($bbuserinfo['userid']!=0)
      {
        if ($bbuserinfo['customtitle']==0 && $foruminfo['countposts'])
        {
          $usergroup=$DB_site->query_first("SELECT usertitle FROM usergroup WHERE usergroupid='$bbuserinfo[usergroupid]'");
          if ($usergroup['usertitle']=="") {
            $gettitle=$DB_site->query_first("SELECT title FROM usertitle WHERE minposts<=$bbuserinfo[posts]".iif ($foruminfo['countposts'],"+1","")." ORDER BY minposts DESC LIMIT 1");
            $usertitle=$gettitle['title'];
          } else {
            $usertitle=$usergroup['usertitle'];
          }
          $dotitle="usertitle='".vb_escape($usertitle)."',";
        }

          $DB_site->query("UPDATE user SET
          ".iif ($foruminfo['countposts'],"posts=posts+1,","")."
          $dotitle"."lastpost='".time()."' WHERE userid='$bbuserinfo[userid]'");
      }

      // send email to moderators
      if ($enableemail) {
        $moderators=$DB_site->query_first("SELECT CONCAT(newthreademail,' ',newpostemail) AS newthreademail FROM forum WHERE forumid='$forumid'");

        $modtable=$DB_site->query("SELECT DISTINCT user.email FROM moderator,user WHERE moderator.userid=user.userid AND forumid IN ($foruminfo[parentlist]) AND (newthreademail=1 OR newpostemail=1)");
        while($thismod=$DB_site->fetch_array($modtable)) {
          $moderators['newthreademail'].=" $thismod[email]";
        }

        if ($moderators['newthreademail']!="") {
          $forumtitle = $foruminfo['title'];
          $threadinfo['title'] = $subject;
          $bbuserinfo['username'] = unhtmlspecialchars($bbuserinfo['username']);
          $mods = explode(" ",trim($moderators['newthreademail']));
          while (list($key,$val)=each($mods)) {
            if (trim($val)!="") {
              eval("\$emailmsg = \"".gettemplate("email_moderator",1,0)."\";");
              eval("\$emailsubject = \"".gettemplate("emailsubject_moderator",1,0)."\";");
			  vbmail($val, $emailsubject, $emailmsg);
            }
          }
          $bbuserinfo['username'] = htmlspecialchars($bbuserinfo['username']);
        }
      }

      // redirect
      if ($postpoll) {
        $goto="poll.php?s=$session[sessionhash]&amp;threadid=$threadid&amp;polloptions=".intval($polloptions);
      } elseif ($visible) {
        $goto="showthread.php?s=$session[sessionhash]&amp;threadid=$threadid";
      } else {
        $goto="forumdisplay.php?s=$session[sessionhash]&amp;forumid=$forumid";
      }
      eval("standardredirect(\"".gettemplate("redirect_postthanks")."\",\"$goto\");");
    }
  }
}

// ############################### start new thread ###############################
if ($action=="newthread") {

//  $foruminfo=getforuminfo($forumid);
  $message = htmlspecialchars($message); // Without this, a </textarea> in the message breaks the form on preview
  $subject = htmlspecialchars($subject);
  $bbcodeon=iif($foruminfo['allowbbcode'],$ontext,$offtext);
  $imgcodeon=iif($foruminfo['allowimages'],$ontext,$offtext);
  $htmlcodeon=iif($foruminfo['allowhtml'],$ontext,$offtext);
  $smilieson=iif($foruminfo['allowsmilies'],$ontext,$offtext);

  // draw nav bar
  $navbar=makenavbar($forumid,"forum",1);

  if ($foruminfo['allowicons']) {
    $posticons=chooseicons($iconid);
  }  else {
    $posticons="";
  }

  if (!isset($parseurl)) {
    $parseurlchecked="CHECKED";
  }

  if ($postpoll) {
    $postpollchecked="CHECKED";
  } else {
		$postpollchecked = '';
	}

  if (!isset($polloptions)) {
    $polloptions=4;
  } else {
    $polloptions = intval($polloptions);
  }

  // check to see if signature required
  if ($bbuserinfo['userid']!=0 and !$previewpost) {
    if ($bbuserinfo['signature']!="") {
      $signaturechecked="CHECKED";
    } else {
			$signaturechecked='';
		}
    if ($bbuserinfo['emailnotification']) {
      $emailchecked="checked";
    } else {
			$emailchecked='';
		}
  }

  if ($permissions['canpostpoll']) {
    eval("\$postpolloption = \"".gettemplate("newthread_postpoll")."\";");
  } else {
    $postpolloption = '';
  }

  $maxattachsize_temp = getmaxattachsize();

  if (phpversion() < '4.0.3') {
    $enctype = 'enctype="multipart/form-data"';
  } else if (ini_get('file_uploads')) {
    $enctype = 'enctype="multipart/form-data"';
  } else {
    $enctype = '';
  }

  if ($permissions['canpostattachment'] and (!$safeupload or function_exists("is_uploaded_file"))) {
    eval("\$attachmentoption = \"".gettemplate("newpost_attachment")."\";");
  } else {
    $attachmentoption="";
  }

  $vbcode_smilies = '';
  $vbcode_buttons = '';
  if ($foruminfo['allowsmilies']) {
    if ($bbuserinfo['showvbcode'] && $allowvbcodebuttons)
      $vbcode_smilies = getclickysmilies();
    eval("\$disablesmiliesoption = \"".gettemplate("newpost_disablesmiliesoption")."\";");
  } else {
    $disablesmiliesoption="";
  }
  if ($bbuserinfo['showvbcode'] && $allowvbcodebuttons)
    $vbcode_buttons = getcodebuttons();

  getforumrules($foruminfo,$permissions);

  eval("dooutput(\"".gettemplate("newthread")."\");");

}

?>