<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

$templatesused = "quotereply,newpost_postpreview,email_notify,emailsubject_notify,redirect_postthanks,email_moderator,emailsubject_moderator,threadreview,threadreviewbit,newpost_attachment,newpost_disablesmiliesoption,forumrules,newreply,posticons,posticonbit";
$templatesused.=",vbcode_smilies,vbcode_smiliebit,vbcode_smilies_getmore,vbcode_buttons,vbcode_sizebits,vbcode_fontbits,vbcode_colorbits";

require("./global.php");

// get decent textarea size for user's browser
$textareacols = gettextareawidth();

$action = trim($action);
if (!isset($action) or $action=="") {
  $action="newreply";
}

unset ($postpreview);
unset ($parseurlchecked);
unset ($emailchecked);
unset ($disablesmilieschecked);
unset ($signaturechecked);
unset ($previewchecked);
$rate = array();

// check for valid thread or post

if (isset($postid)) {
  $postid=verifyid("post",$postid,0);
  if ($postid!=0) {
    $postinfo=getpostinfo($postid);
    $threadid=$postinfo['threadid'];
    if ($postinfo['userid']==0) {
      $originalposter=$postinfo['username'];
    } else {
      $getusername=$DB_site->query_first("SELECT username FROM user WHERE userid='$postinfo[userid]'");
      $originalposter=$getusername['username'];
    }
    $originalposter = unhtmlspecialchars( $originalposter );

    if ($postinfo['title']!="") {
      $title="Re: ".unhtmlspecialchars($postinfo['title']);
    }
    $postdate=vbdate($dateformat,$postinfo['dateline']);
    $posttime=vbdate($timeformat,$postinfo['dateline']);
    $pagetext=$postinfo['pagetext'];
    $pagetext = trim(preg_replace("/(\[quote])(.*)(\[\/quote])/siU", "", $pagetext));
    eval("\$message = \"".gettemplate("quotereply",1,0)."\";");
  }
}

$threadid=verifyid("thread",$threadid);

$threadinfo=getthreadinfo($threadid);

if (!$threadinfo['visible']) {
  $idname="thread";
  eval("standarderror(\"".gettemplate("error_invalidid")."\");");
}
if (!$threadinfo['open']) {
  if (!ismoderator($threadinfo['forumid'],"canopenclose")) {
    eval("standardredirect(\"".gettemplate("redirect_threadclosed")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadid\");");
    exit;
  }
}

if ($threadinfo['open'] == 10) {
    //send them to their correct thread    
    header("Location: newreply.php?s=$session[sessionhash]&threadid=$threadinfo[pollid]");
    exit;
}

$permissions=getpermissions($threadinfo['forumid']);
if (($bbuserinfo['userid']!=$threadinfo['postuserid'] or $bbuserinfo['userid']==0) and (!$permissions['canviewothers'] or !$permissions['canreplyothers'])) {
  show_nopermission();
}
if (!$permissions['canview'] or (!$permissions['canreplyown'] and $bbuserinfo['userid']==$threadinfo['postuserid'])) {
  show_nopermission();
}

updateuserforum($threadinfo['forumid']);

// ############################### start post reply ###############################
if ($_POST['action']=="postreply") {

  // check for subject and message
  $message=trim($message);
  if ($message=="") {
    eval("standarderror(\"".gettemplate("error_nosubject")."\");");
    exit;
  }

  // decode check boxes
  $parseurl=iif(trim($parseurl)=="yes",1,0);
  $email=iif(trim($email)=="yes",1,0);
  $allowsmilie=iif(trim($disablesmilies)=="yes",0,1);
  $signature=iif(trim($signature)=="yes",1,0);
  $preview=iif(trim($preview)!="",1,0);

  if ($wordwrap!=0) {
    $threadinfo['title']=dowordwrap($threadinfo['title']);
  }

  $foruminfo=getforuminfo($threadinfo['forumid']);
  $forumid=$foruminfo['forumid'];

  if ($foruminfo['allowposting']==0) {
    eval("standarderror(\"".gettemplate("error_forumclosed")."\");");
  }

  $visible=!$foruminfo['moderatenew'];

  // auto bypass queueing for admins/mods
  if (ismoderator($foruminfo['forumid'])) {
    $visible=1;
  }

  if ($parseurl) {
    $message=parseurl($message);
  }
  // remove sessionhash from urls:
  $message = stripsession($message);

  if (strlen($message)>$postmaxchars and $postmaxchars!=0) {
    eval("standarderror(\"".gettemplate("error_toolong")."\");");
  }

  if ($preview) {
    $previewpost=1;
    $previewmessage=bbcodeparse($message,$threadinfo['forumid'],$allowsmilie);

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

    if ($rating) {
      $rate["$rating"] = " selected";
    }

    $action="newreply";
  } else {
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

    if ($enablefloodcheck) {
      if ($bbuserinfo['userid']!=0 and time()-$bbuserinfo['lastpost']<=$floodchecktime and !ismoderator($foruminfo['forumid'])) {
        eval("standarderror(\"".gettemplate("error_floodcheck")."\");");
        exit;
      }
    }

    // check max images
    if ($maximages!=0) {
      $parsedmessage=bbcodeparse($message,$forumid,$allowsmilie);
      if (countchar($parsedmessage,"<img")>$maximages) {
        eval("standarderror(\"".gettemplate("error_toomanyimages")."\");");
        exit;
      }
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

    if (!isset($iconid) or $iconid=="") {
      $iconid=0;
    }
    $iconid = intval($iconid);

    $permissions=getpermissions($threadinfo['forumid']);
    if (($bbuserinfo['userid']!=$threadinfo['postuserid'] or $bbuserinfo['userid']==0) and (!$permissions['canviewothers'] or !$permissions['canreplyothers'])) {
      show_nopermission();
    }
    if (!$permissions['canview'] or (!$permissions['canreplyown'] and $bbuserinfo['userid']==$threadinfo['postuserid'])) {
      show_nopermission();
    }

    /*if ($bbuserinfo[userid]==0) {
      $postusername=$bbuserinfo[username];
    } else {
      $postusername="";
    }*/

    $title=censortext($title);
    $message=censortext($message);

    // sort attachement
    if (is_array($_FILES)) {
    	$attachment = $_FILES['attachment']['tmp_name'];
    	$attachment_name = $_FILES['attachment']['name'];
    	$attachment_size = $_FILES['attachment']['size'];
    }
    if ($permissions['canpostattachment'] and trim($attachment)!="none" and trim($attachment)!="" and trim($attachment_name)!="") {
      $attachmentid=acceptupload($foruminfo['moderateattach']);
    } else {
      $attachmentid=0;
    }

    if ($email&&$bbuserinfo['userid']!=0) {
      if (!$checkid=$DB_site->query_first("SELECT subscribethreadid FROM subscribethread WHERE userid=$bbuserinfo[userid] AND threadid=$threadid")) {
        $DB_site->query("INSERT INTO subscribethread (subscribethreadid,userid,threadid) VALUES (NULL,$bbuserinfo[userid],$threadid)");
      }
    }
    // see if there has been a post identical to this in the last 5 mins.  If so, update that one, as user has probably done a double post
    $datecut=time()-300;
    if ($prevpost=$DB_site->query_first("SELECT attachmentid,postid,visible FROM post WHERE threadid='$threadid' AND username='".vb_escape($postusername)."' AND userid='$bbuserinfo[userid]' AND title='".vb_escape(htmlspecialchars($title))."' AND dateline>$datecut AND pagetext='".vb_escape($message)."'")) {
      $postid=$prevpost['postid'];

      if ($prevpost['attachmentid']!=0) {
        $DB_site->query("DELETE FROM attachment WHERE attachmentid = '$prevpost[attachmentid]'");
        if ($attachmentid==0) {
          $DB_site->query("UPDATE thread SET attach = attach - 1 WHERE threadid = '$threadid'");
        }
      }
      $DB_site->query("UPDATE post SET title='".vb_escape(htmlspecialchars($title))."',pagetext='".vb_escape($message)."',allowsmilie='$allowsmilie',showsignature='$signature',iconid='$iconid',attachmentid='$attachmentid' WHERE postid='$postid'");

      // redirect
      if ($prevpost['visible']) {
        $goto="showthread.php?s=$session[sessionhash]&amp;postid=$postid#post$postid";
      } else {
        $goto="forumdisplay.php?s=$session[sessionhash]&amp;forumid=$forumid";
      }
      eval("standardredirect(\"".gettemplate("redirect_postthanks")."\",\"$goto\");");

    } else {
      if ($visible) {
        sendnotification ($threadinfo['threadid'], $bbuserinfo['userid'], 0);
      }

      if ($attachmentid and !$foruminfo['moderateattach']) {
        $DB_site->query("UPDATE thread SET attach = attach + 1 WHERE threadid = '$threadid'");
      }
      $DB_site->query("INSERT INTO post (postid,threadid,title,username,userid,dateline,attachmentid,pagetext,allowsmilie,showsignature,ipaddress,iconid,visible) VALUES (NULL,'$threadid','".vb_escape(htmlspecialchars($title))."','".vb_escape($postusername)."','$bbuserinfo[userid]','".time()."','$attachmentid','".vb_escape($message)."','$allowsmilie','$signature','$ipaddress','$iconid','$visible')");
      $postid=$DB_site->insert_id();

      indexpost($postid,0);

      if ($visible) {
        if ($threadinfo['replycount']%10==0) {
          $replies=$DB_site->query_first("SELECT COUNT(*)-1 AS replies FROM post WHERE threadid='$threadid'");
          $DB_site->query("UPDATE thread SET lastpost='".time()."',replycount='$replies[replies]',lastposter='".vb_escape($postusername)."' WHERE threadid='$threadid'");
        } else {
          $DB_site->query("UPDATE thread SET lastpost='".time()."',replycount=replycount+1,lastposter='".vb_escape($postusername)."' WHERE threadid='$threadid'");
        }
      }

			if ($rating > 0 and $rating < 6 and $foruminfo['allowratings'] == 1) {
				if ($permissions['canthreadrate']) {
					$vote = intval($rating);
					if ($ratingsel = $DB_site->query_first("SELECT vote, threadrateid
															FROM threadrate
															WHERE userid='$bbuserinfo[userid]'
															AND threadid = '$threadid'")) {
						if ($votechange) {
							if ($vote != $ratingsel['vote']) {
								$voteupdate = $vote - $ratingsel['vote'];
								$DB_site->query("UPDATE threadrate SET vote='$vote'
												WHERE threadrateid=$ratingsel[threadrateid]");
								$DB_site->query("UPDATE thread SET votetotal=votetotal+$voteupdate
												WHERE threadid='$threadid'");
							}
						}
					} else {
						$DB_site->query("INSERT INTO threadrate (threadid,userid,vote)
											VALUES ('$threadid','$bbuserinfo[userid]','$vote')");
						$DB_site->query("UPDATE thread SET votetotal=votetotal+$vote,votenum=votenum+1
												WHERE threadid='$threadid'");
					}
				}
			}

      // update forum stuff
      if ($visible==1) {
        $DB_site->query("UPDATE forum SET replycount=replycount+1,lastpost='".time()."',lastposter='".vb_escape($postusername)."' WHERE forumid IN ($foruminfo[parentlist])");
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
        $moderators=$DB_site->query_first("SELECT newpostemail FROM forum WHERE forumid='$threadinfo[forumid]'");

        $mods=$DB_site->query("SELECT DISTINCT user.email FROM moderator LEFT JOIN user USING (userid) WHERE moderator.forumid IN ($foruminfo[parentlist]) AND moderator.newpostemail=1");
        while ($mod=$DB_site->fetch_array($mods)) {
          $moderators['newpostemail'].=' '.$mod['email'];
        }
        $moderators['newpostemail']=trim($moderators['newpostemail']);

        if ($moderators['newpostemail']!="") {
          $threadinfo['title'] = unhtmlspecialchars($threadinfo['title']);
          $bbuserinfo['username'] = unhtmlspecialchars($bbuserinfo['username']); //for emails
          $mods=explode(" ",$moderators['newpostemail']);
          while (list($key,$val)=each($mods)) {
            if (trim($val)!="") {
              eval("\$emailmsg = \"".gettemplate("email_moderator",1,0)."\";");
              eval("\$emailsubject = \"".gettemplate("emailsubject_moderator",1,0)."\";");
			  vbmail($val, $emailsubject, $emailmsg);
            }
          }
          $threadinfo['title'] = htmlspecialchars($threadinfo['title']);
          $bbuserinfo['username'] = htmlspecialchars($bbuserinfo['username']); //back to norm
        }
      }

      // redirect
      if ($visible) {
        $goto="showthread.php?s=$session[sessionhash]&amp;postid=$postid#post$postid";
      } else {
        $goto="forumdisplay.php?s=$session[sessionhash]&amp;forumid=$threadinfo[forumid]";
      }
      eval("standardredirect(\"".gettemplate("redirect_postthanks")."\",\"$goto\");");
    }
  }
}

// ############################### start new reply ###############################
if ($action=="newreply") {

  if ($wordwrap!=0) {
    $threadinfo['title']=dowordwrap($threadinfo['title']);
  }

  $message = htmlspecialchars($message); // Without this, a </textarea> in the message breaks the form on preview
  $title = htmlspecialchars($title);

  $foruminfo=getforuminfo($threadinfo['forumid']);

	$threadratingoption = '';
	if ($foruminfo['allowratings']==1 and $permissions['canthreadrate']==1) {
		if ($rating=$DB_site->query_first("SELECT vote, threadrateid FROM threadrate
										WHERE userid = $bbuserinfo[userid] AND threadid = '$threadid'")) {
			if ($votechange) {
				$rate[$rating['vote']] = " selected";
				eval("\$threadratingoption = \"".gettemplate("newreply_ratethread")."\";");
			} else {
				$threadratingoption = "";
			}
		} else {
			eval("\$threadratingoption = \"".gettemplate("newreply_ratethread")."\";");
		}
	}

  if ($foruminfo['allowposting']==0) {
    eval("standarderror(\"".gettemplate("error_forumclosed")."\");");
  }

  $bbcodeon=iif($foruminfo['allowbbcode'],$ontext,$offtext);
  $imgcodeon=iif($foruminfo['allowimages'],$ontext,$offtext);
  $htmlcodeon=iif($foruminfo['allowhtml'],$ontext,$offtext);
  $smilieson=iif($foruminfo['allowsmilies'],$ontext,$offtext);

  // draw nav bar
  $navbar=makenavbar($threadid,"thread",1);

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

  if (($bbuserinfo['maxposts'] != -1) and ($bbuserinfo['maxposts'] != 0)) {
    $maxposts = $bbuserinfo['maxposts'];
  }
  $posts=$DB_site->query("
        SELECT IF(post.userid=0,post.username,user.username) AS username,
        post.pagetext,post.allowsmilie,post.userid FROM post
        LEFT JOIN user ON user.userid=post.userid
        WHERE post.visible=1 AND post.threadid='$threadid'
        ORDER BY dateline DESC LIMIT " . ($maxposts+1)); // return +1 so that check later will still work

  $threadreviewbits = '';
  while ($post=$DB_site->fetch_array($posts)) {
    if ($postcounter++ < $maxposts) {
			if ($postcounter%2 == 0) {
				$backcolor = "{firstaltcolor}";
				$post['bgclass'] = "alt1";
			} else {
				$backcolor = "{secondaltcolor}";
				$post['bgclass'] = "alt2";
			}
			$username=$post['username'];
			if ($ignore[$post['userid']]) {
				$reviewmessage = $ignoreduser;
			} else {
				$reviewmessage = bbcodeparse($post['pagetext'],$threadinfo['forumid'],$post['allowsmilie']);
			}
			eval("\$threadreviewbits .= \"".gettemplate("threadreviewbit")."\";");
		} else {
			break;
		}
  }
  if ($DB_site->num_rows($posts)>$maxposts) {
    eval("\$threadreviewbits .= \"".gettemplate("threadreview")."\";");
  }

  if ($bbuserinfo['userid']!=0 and !$previewpost) {
    if ($bbuserinfo['signature']!="") {
      $signaturechecked="CHECKED";
    }
    if ($bbuserinfo['emailnotification']!=0) {
      $emailchecked="checked";
    }
  }

  if ($foruminfo['allowicons']) {
    $posticons=chooseicons($iconid);
  }  else {
    $posticons="";
  }

  if (!isset($parseurl)) {
    $parseurlchecked="CHECKED";
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
    if ($bbuserinfo['showvbcode'] && $allowvbcodebuttons) {
      $vbcode_smilies = getclickysmilies();
    }
    eval("\$disablesmiliesoption = \"".gettemplate("newpost_disablesmiliesoption")."\";");
  } else {
    $disablesmiliesoption="";
  }
  if ($bbuserinfo['showvbcode'] && $allowvbcodebuttons) {
    $vbcode_buttons = getcodebuttons();
  }

  getforumrules($foruminfo,$permissions);

  eval("dooutput(\"".gettemplate("newreply")."\");");

}

?>