<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

if ($_GET['HTTP_POST_VARS']['action'] == $_POST['action']) {
  unset($_POST['action']);
}
$_POST['action'] = trim($_POST['action']);

if ($_POST['action']) {
	$action = $_POST['action'];
} else if ($_GET['action']) {
	$action = $_GET['action'];
}
if ($_GET['a']) {
	$a = $_GET['a'];
}

if ( isset($action) and $action=="login") {
  $noheader=1;
}

if ((!isset($action) or $action=="") and (!isset($a) or $a=="")) {
  $action="lostpw";
}

$templatesused = '';
$cpnav = array();
$cpmenu = array();

// ############################### start logout ###############################
if ($action=="logout") {
  $templatesused = 'error_cookieclear';
  include("./global.php");
  vbsetcookie("bbuserid","", true, true);
  vbsetcookie("bbpassword","", true, true);
  vbsetcookie("bbstyleid","",1);

  if ($bbuserinfo['userid'] > 0) {
    $DB_site->query("UPDATE user SET lastactivity='".(time()-$cookietimeout)."',lastvisit='".time()."' WHERE userid='$bbuserinfo[userid]'");
  }

  //$DB_site->query("UPDATE session SET userid=0 WHERE sessionhash='".vb_escape($session[sessionhash])."'");
  $DB_site->query("DELETE FROM session WHERE sessionhash='".vb_escape($session['dbsessionhash'])."'");

  if ($bbuserinfo['userid'] > 0) {
    // make sure any other of this user's sessions are deleted (incase they ended up with more than one)
    $DB_site->query("DELETE FROM session WHERE userid='$bbuserinfo[userid]'");
  }

  $session['sessionhash']=md5(uniqid(microtime()));
  $session['dbsessionhash']=$session['sessionhash'];
  $DB_site->query("INSERT INTO session (sessionhash,userid,host,useragent,lastactivity,styleid) VALUES ('".vb_escape($session['sessionhash'])."','0','".vb_escape($session['host'])."','".vb_escape($session['useragent'])."','".time()."','0')");
  vbsetcookie("sessionhash",$session['sessionhash'], false, true);

  eval("standarderror(\"".gettemplate("error_cookieclear")."\");");

}

// ############################### start login ###############################
if ($action=="login") {
  $templatesused = 'redirect_login,error_wrongpassword,error_wrongusername';
  include("./global.php");
  $userid = 0;
  if (isset($username)) {
    // get userid for given username
    if ($user=$DB_site->query_first("SELECT userid,username,password,cookieuser FROM user WHERE username='".vb_escape(htmlspecialchars($username))."'")) {
      if ($user['password']!=md5($password)) {  // check password
        eval("standarderror(\"".gettemplate("error_wrongpassword")."\");");
        exit;
      }
      $userid=$user['userid'];
    } else { // invalid username entered
      eval("standarderror(\"".gettemplate("error_wrongusername")."\");");
      exit;
    }

    if ($user['cookieuser']==1) {
      vbsetcookie("bbuserid",$user['userid'], true, true);
      vbsetcookie("bbpassword",$user['password'], true, true);
    }

    $DB_site->query("DELETE FROM session WHERE sessionhash='".vb_escape($session['dbsessionhash'])."'");

    $session['sessionhash']=md5(uniqid(microtime()));
    $session['dbsessionhash']=$session['sessionhash'];
    $DB_site->query("INSERT INTO session (sessionhash,userid,host,useragent,lastactivity,styleid) VALUES ('".vb_escape($session['sessionhash'])."','".intval($userid)."','".vb_escape($session['host'])."','".vb_escape($session['useragent'])."','".time()."','$session[styleid]')");
    vbsetcookie("sessionhash",$session['sessionhash'], false, true);
    $username = $user['username'];
  }

  $url=ereg_replace("sessionhash=[a-z0-9]{32}&","",$url);
  $url=ereg_replace("\\?sessionhash=[a-z0-9]{32}","",$url);
  $url=ereg_replace("s=[a-z0-9]{32}&","",$url);
  $url=ereg_replace("\\?s=[a-z0-9]{32}","",$url);

  if ($url!="" and $url!="index.php" and $url!=$HTTP_REFERER) {

    if (strpos($url,"?")>0) {
      $url.="&amp;s=$session[dbsessionhash]";
    } else {
      $url.="?s=$session[dbsessionhash]";
    }
    //header("Location: $url");

    $url = str_replace("\"", "", $url);
    eval("standardredirect(\"".gettemplate("redirect_login")."\",\"\$url\");");
  } else {
    $bbuserinfo=getuserinfo($userid);
    eval("standardredirect(\"".gettemplate("redirect_login")."\",\"index.php?s=$session[dbsessionhash]\");");
  }

}


// ############################### start mark all forums read ###############################
if ($action=="markread") {
  $templatesused = 'redirect_markread';
  include("./global.php");
  if ($bbuserinfo['userid']!=0 and $bbuserinfo['userid']!=-1) {
    $DB_site->query("UPDATE user SET lastactivity='".time()."',lastvisit='".time()."' WHERE userid='$bbuserinfo[userid]'");
  } else {
    vbsetcookie("bblastvisit",time());
  }
  eval("standardredirect(\"".gettemplate("redirect_markread")."\",\"index.php?s=$session[sessionhash]\");");
}

// ############################### start lost password ###############################
if ($action=="lostpw") {
  $templatesused = 'lostpw';
  include("./global.php");
  eval("dooutput(\"".gettemplate("lostpw")."\");");
}

// ############################### start email password ###############################
if ($_POST['action']=="emailpassword") {
  $templatesused = 'redirect_lostpw,error_invalidemail';
  include("./global.php");

  $users=$DB_site->query("SELECT user.userid,username,email,usergroupid FROM user WHERE email='".vb_escape(htmlspecialchars($email))."'");

  if ($DB_site->num_rows($users)) {

    while ($user=$DB_site->fetch_array($users)) {

	  $check = $DB_site->query_first("SELECT * FROM useractivation WHERE userid=$user[userid] and type=1");
	  if($ourtimenow-$check['dateline'] <= 60) {
        eval("standarderror(\"".gettemplate("error_emailflood")."\");");
      }

      $username=unhtmlspecialchars($user['username']);

			// delete old activation id
			$DB_site->query("DELETE FROM useractivation WHERE userid='$user[userid]' AND type=1");

			// make random number
			mt_srand ((float) microtime() * 1000000);
			$user['activationid']=mt_rand(0,100000000);

			//save to DB
			$DB_site->query("
				INSERT INTO useractivation
					(useractivationid, userid, dateline, activationid, type, usergroupid)
				VALUES
					(NULL, $user[userid], ".time().", '$user[activationid]', 1, $user[usergroupid])
			");


      eval("\$message = \"".gettemplate("email_lostpw",1,0)."\";");
      eval("\$subject = \"".gettemplate("emailsubject_lostpw",1,0)."\";");

      vbmail($user['email'], $subject, $message);

    }
		if ($url=="") {
			$url="index.php?s=$session[sessionhash]";
		}

    $url = str_replace("\"", "", $url);
    eval("standardredirect(\"".gettemplate("redirect_lostpw")."\",\"\$url\");");
  } else {
    eval("standarderror(\"".gettemplate("error_invalidemail")."\");");
  }
}

// ############################### start reset password ###############################
if ($action=="resetpassword") {
	$a="pwd";
}

if ($a=="pwd") {
  $templatesused = 'error_resetexpired,error_resetbadid,email_resetpw,emailsubject_resetpw,error_resetpw';
  include("./global.php");

  if (!isset($userid)) {
		$userid=$u;
  }
  if (!isset($activationid)) {
		$activationid=$i;
	}

  $userinfo=verifyid("user",$userid,1,1);

  $user=$DB_site->query_first("SELECT activationid,dateline FROM useractivation WHERE type=1 AND userid='".vb_escape($userinfo['userid'])."'");

  if ($user['dateline']<(time()-24*60*60)) {  // is it older than 24 hours?
    eval("standarderror(\"".gettemplate("error_resetexpired")."\");");
	}

  if ($user['activationid']!=$activationid) { //wrong act id
    eval("standarderror(\"".gettemplate("error_resetbadid")."\");");
	}


	// delete old activation id
	$DB_site->query("DELETE FROM useractivation WHERE userid='$userinfo[userid]' AND type=1");

	// make random number
	mt_srand ((float) microtime() * 1000000);
	$newpassword=mt_rand(0,100000000);

  $DB_site->query("UPDATE user SET password='".vb_escape(md5($newpassword))."' WHERE userid=$userinfo[userid]");

	eval("\$message = \"".gettemplate("email_resetpw",1,0)."\";");
	eval("\$subject = \"".gettemplate("emailsubject_resetpw",1,0)."\";");

	vbmail($userinfo['email'], $subject, $message);

  eval("standarderror(\"".gettemplate("error_resetpw")."\");");

}

// ############################### start modify profile ###############################
if ($action=="editprofile") {
  $templatesused = 'register_birthday,modifyprofile_customtext,register_customfields,usercpnav,modifyprofile';
  include("./global.php");
  // do modify profile form

  if ($bbuserinfo['userid']==0 or $permissions['canmodifyprofile']==0) {
    show_nopermission();
  }

  if ($bbuserinfo['coppauser']!=0) {
    $parentemail=$bbuserinfo['parentemail'];

    eval("\$coppatext = \"".gettemplate("modifycoppa")."\";");
    eval("\$parentemail = \"".gettemplate("modifyparentemail")."\";");
  } else {
		$coppatext = '';
		$parentemail = '';
	}

  if ($calbirthday == 1)  {
     if ($bgcolor=="{firstaltcolor}") {
        $bgcolor="{secondaltcolor}";
     } else {
        $bgcolor="{firstaltcolor}";
     }
     // Set birthday fields right here!
     if ($bbuserinfo['birthday'] == '0000-00-00') {
        $daydefaultselected = "selected";
        $monthdefaultselected = "selected";
     } else {
        $birthday = explode("-",$bbuserinfo['birthday']);
        $dayname = "day".$birthday[2]."selected";
        $$dayname = "selected";
        $monthname = "month".$birthday[1]."selected";
        $$monthname = "selected";
        if (date("Y")>$birthday[0] && $birthday[0]!='0000')
           $year = $birthday[0];
     }
     eval("\$birthday = \"".gettemplate("register_birthday")."\";");
  } else {
		$birthday = '';
	}
	$customtext = '';
  if ($ctEnable == 1) { // Custom Titles are ON
    $ctShowTitle = 0;
    if (ismoderator() and $ctAdmin == 1) { // Allow mods to use titles no matter what
       $ctShowTitle = 1;
    } else {
       if ($ctEitherOr==0)  {  // Allow titles if Posts are ok OR JoinDate is ok
          if ( ($bbuserinfo['posts'] >= $ctPosts) or ( ($bbuserinfo['joindate'] <= (time()-($ctDays*86400))) or ($ctDays==0)) ) {
             $ctShowTitle = 1;
          }
       } else { // Allow titles if Posts AND Joindate is ok
          if ( ($bbuserinfo['posts'] >= $ctPosts) and ( ($bbuserinfo['joindate'] <= (time()-($ctDays*86400))) or ($ctDays==0)) )  {
             $ctShowTitle = 1;
          }
       }
    }
    if ($ctShowTitle == 1) {
      if ($bgcolor=="{firstaltcolor}") {
        $bgcolor="{secondaltcolor}";
      } else {
        $bgcolor="{firstaltcolor}";
      }
      if ($bbuserinfo['customtitle']==2)
        $bbuserinfo['usertitle'] = htmlspecialchars($bbuserinfo['usertitle']);
      eval("\$customtext = \"".gettemplate("modifyprofile_customtext")."\";");
    }
  }
  // get extra profile fields
  $profilefields=$DB_site->query("SELECT *
                                  FROM profilefield
                                  WHERE editable = 1
                                  ORDER BY displayorder");
  $customfields_required = '';
  $customfields = '';
  while ($profilefield=$DB_site->fetch_array($profilefields)) {
    $profilefieldname="field$profilefield[profilefieldid]";
    if ($profilefield['required'] == 1) {
      if ($bgcolor1=="{firstaltcolor}") {
        $bgcolor1="{secondaltcolor}";
		$bgclass1="alt2";
      } else {
        $bgcolor1="{firstaltcolor}";
		$bgclass1="alt2";
      }
      $temp = $bgcolor;
	  $tempclass = $bgclass;
      $bgcolor = $bgcolor1;
	  $bgclass = $bgclass1;
      eval("\$customfields_required .= \"".gettemplate("register_customfields")."\";");
      $bgcolor = $temp;
	  $bgclass = $tempclass;
    } else { // Not Required
      if ($bgcolor=="{firstaltcolor}") {
	    $bgcolor="{secondaltcolor}";
		$bgclass="alt2";
	  } else {
	    $bgcolor="{firstaltcolor}";
		$bgclass="alt1";
	  }
      eval("\$customfields .= \"".gettemplate("register_customfields")."\";");
    }
  }
  if ($allowhtml) {
    $htmlonoff=$ontext;
  } else {
    $htmlonoff=$offtext;
  }
  if ($allowbbcode) {
    $bbcodeonoff=$ontext;
  } else {
    $bbcodeonoff=$offtext;
  }
  if ($allowbbimagecode) {
    $imgcodeonoff=$ontext;
  } else {
    $imgcodeonoff=$offtext;
  }
  if ($allowsmilies) {
    $smiliesonoff=$ontext;
  } else {
    $smiliesonoff=$offtext;
  }

  $signature=htmlspecialchars($bbuserinfo['signature']);

  // draw cp nav bar
  $cpnav[1]="{secondaltcolor}";
  $cpnav[2]="{firstaltcolor}";
	$cpmenu[2]="class=\"fjsel\" selected";
  $cpnav[3]="{secondaltcolor}";
  $cpnav[4]="{secondaltcolor}";
  $cpnav[5]="{secondaltcolor}";
  $cpnav[6]="{secondaltcolor}";
  $cpnav[7]="{secondaltcolor}";
  eval("\$cpnav = \"".gettemplate("usercpnav")."\";");

  eval("dooutput(\"".gettemplate("modifyprofile")."\");");
}

// ############################### start update profile ###############################
if ($_POST['action']=="updateprofile") {
  $templatesused = 'redirect_updatethanks,error_fieldmissing,error_emailmismatch,error_emailtaken,error_fieldmissing,error_requiredfieldmissing,error_birthdayfield';
  include("./global.php");

  if ($bbuserinfo['userid']==0 or $permissions['canmodifyprofile']==0) {
    show_nopermission();
  }

  $email = trim($email);
  if ($enablebanning and $banemail!="") {
  	$banemail = preg_replace("/[[:space:]]+/"," ",$banemail);

   if (!$allowkeepbannedemail or $bbuserinfo['email']!=$email) {
     if (stristr(" ".$banemail." "," ".$email." ")!="") {
        eval("standarderror(\"".gettemplate("error_banemail")."\");");
        exit;
      }
      if ($emaildomain=substr(strstr($email,"@"),1)) {
        if (stristr(" ".$banemail." "," ".$emaildomain." ")!="") {
          eval("standarderror(\"".gettemplate("error_banemail")."\");");
          exit;
        }
      }
    }
  }

  if ($requireuniqueemail and $bbuserinfo['email']!=$email and $checkuser=$DB_site->query_first("SELECT userid,username,email FROM user WHERE email='".vb_escape($email)."' AND userid<>'$bbuserinfo[userid]'")) {
    if ($checkuser['userid']!=$bbuserinfo['userid']) {
      eval("standarderror(\"".gettemplate("error_emailtaken")."\");");
      exit;
    }
  }

  if ($email=="" or $emailconfirm=="") { //  or $password=="" or $passwordconfirm==""
    eval("standarderror(\"".gettemplate("error_fieldmissing")."\");");
    exit;
  }

  if ($email!=$emailconfirm) {
    eval("standarderror(\"".gettemplate("error_emailmismatch")."\");");
    exit;
  }

  // check valid email address
  if (!preg_match("/^(.+)@[a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+$/si", $email)) {
    eval("standarderror(\"".gettemplate("error_bademail")."\");");
  }

  $icq=intval($icq);
  if ($icq==0) {
    $icq="";
  }

  // check coppa things
  $coppauser = intval($coppauser);
  if ($coppauser) {
    if ($parentemail=="") {
      eval("standarderror(\"".gettemplate("error_fieldmissing")."\");");
      exit;
    }

    if ($enableemail) {
      eval("\$message = \"".gettemplate("email_parentcoppa",1,0)."\";");
      eval("\$subject = \"".gettemplate("emailsubject_parentcoppa",1,0)."\";");

	  vbmail($parentemail, $subject, $message);
    }
  } else {
    $parentemail="";
    $coppauser=0;
  }

  // check max images
  if ($maximages!=0) {
    $parsedsig=bbcodeparse($signature,0,$allowsmilies);
    if (countchar($parsedsig,"<img")>$maximages) {
      eval("standarderror(\"".gettemplate("error_toomanyimages")."\");");
      exit;
    }
  }
  // check that nothing illegal is in the signature
  $signature=censortext($signature);

  // check extra profile fields
  $userfields="";
  $profilefields=$DB_site->query("SELECT profilefieldid,required,title,size,maxlength
                                  FROM profilefield
                                  WHERE editable = 1");
  while ($profilefield=$DB_site->fetch_array($profilefields))
  {
    $varname="field$profilefield[profilefieldid]";
    if ($profilefield['required'] and $$varname=="")
    {
      eval("standarderror(\"".gettemplate("error_requiredfieldmissing")."\");");
      exit;
    }
    $$varname=censortext($$varname);
    // ENTER ANY CUSTOM FIELD VALIDATION HERE!
    //Make sure user didn't try to bypass the maxchars for the fields
    $$varname=substr($$varname,0,$profilefield['maxlength']);
    $userfields.=",$varname='".vb_escape(htmlspecialchars($$varname))."'";
  }

  if ($customtext) {
    $customtext = trim($customtext);
  }
  // Custom User Title Code!

  if ($resettitle) {
    $group=$DB_site->query_first("SELECT usertitle FROM usergroup WHERE usergroupid=$bbuserinfo[usergroupid]");
    if ($group['usertitle']=="") {
      $gettitle=$DB_site->query_first("SELECT title FROM usertitle WHERE minposts<=$bbuserinfo[posts] ORDER BY minposts DESC LIMIT 1");
      $usertitle=$gettitle['title'];
    } else {
      $usertitle=$group['usertitle'];
    }
    $bbuserinfo['usertitle'] = $usertitle;
    $bbuserinfo['customtitle'] = 0;
    unset($customtext);
  }

  if ($ctEnable == 1 and $customtext) {// Custom Titles are ON, Make sure user can actually use them and isn't trying to manipulate them through forms
     $ctShowTitle = 0;
     if (ismoderator() and $ctAdmin == 1) {// Allow mods to use titles no matter what
        $ctShowTitle = 1;
     } else {
        if ($ctEitherOr==0)  {// Allow titles if Posts are ok OR JoinDate is ok
           if ( ($bbuserinfo['posts'] >= $ctPosts) or ( ($bbuserinfo['joindate'] <= (time()-($ctDays*86400))) or ($ctDays==0)) ) {
              $ctShowTitle = 1;
           }
        } else {// Allow titles if Posts AND Joindate is ok
           if ( ($bbuserinfo['posts'] >= $ctPosts) and ( ($bbuserinfo['joindate'] <= (time()-($ctDays*86400))) or ($ctDays==0)) ) {
              $ctShowTitle = 1;
           }
        }
     }
     if ($ctShowTitle == 1) {
        if ($bbuserinfo['usergroupid']!=6) {
          $customtitle = 2;  // 2 signifies that the user set the title and we need to htmlspecialchars when ever displaying the title.
        } else {
          $customtitle = 1;
        }
        $customtext = substr($customtext, 0, $ctMaxChars);
        $customtext = censortext($customtext);
        if (!ismoderator() or (ismoderator() and $ctCensorMod==0)) {
          $customtext = customcensortext($customtext);
        }
      } else {
         $customtitle = $bbuserinfo['customtitle'];
         $customtext = $bbuserinfo['usertitle'];
      }
  } else {
     $customtitle = $bbuserinfo['customtitle'];
     $customtext = $bbuserinfo['usertitle'];
  }
  // Birthday Stuff...
  if ($calbirthday == 1) {
     if ( ($day == -1 and $month != -1) or ($day !=-1 and $month == -1) ) {
        eval("standarderror(\"".gettemplate("error_birthdayfield")."\");");
        exit;
     }
     if (($day == -1) and ($month==-1)) {
        $birthday = 0;
     } else {
        if (($year>1901) and ($year<date("Y")))
           $birthday = $year . "-" . $month . "-" . $day;
        else
           $birthday = "0000" . "-" . $month . "-" . $day;
     }
  } else {
     $birthday = 0;
  }
  if ($verifyemail and $email!=$bbuserinfo['email'] and ($bbuserinfo['usergroupid']!=5 and $bbuserinfo['usergroupid']!=6 and $bbuserinfo['usergroupid']!=7)) {
    $newemailaddress=1;
    if ($bbuserinfo['usergroupid']==3) {
       $checkoldactivation = $DB_site->query_first("SELECT usergroupid FROM useractivation WHERE userid='$bbuserinfo[userid]' and type=0");
       if (!$checkoldactivation) {
         $pusergroupid = 2;
       } else {
         $pusergroupid = $checkoldactivation['usergroupid'];
       }
    } else {
       $pusergroupid = $bbuserinfo['usergroupid'];
    }
    // delete old activation id
    $DB_site->query("DELETE FROM useractivation WHERE userid='$bbuserinfo[userid]' AND type=0");

		// make random number
		mt_srand ((float) microtime() * 1000000);
    $activateid=mt_rand(0,100000000);

    //save to DB
    $DB_site->query("
		INSERT INTO useractivation
			(useractivationid, userid, dateline, activationid, type, usergroupid)
		VALUES
			(NULL, $bbuserinfo[userid], ".time().", '$activateid', 0, $pusergroupid)
	");

    $username=unhtmlspecialchars($bbuserinfo['username']);
    $userid=$bbuserinfo['userid'];

    eval("\$message = \"".gettemplate("email_activateaccount_change",1,0)."\";");
    eval("\$subject = \"".gettemplate("emailsubject_activateaccount_change",1,0)."\";");

    vbmail($email, $subject, $message);

    $bbuserinfo['usergroupid'] = 3;
  } else {
    $newemailaddress=0;
  }

  $homepage = trim($homepage);
  if ($homepage) {
    if (preg_match('#^www\.#si', $homepage)) {
      $homepage = "http://$homepage";
    } else if (!preg_match('#^[a-z0-9]+://#si', $homepage)) {
      // homepage doesn't match the http://-style format in the beginning -- possible attempted exploit
      $homepage = '';
    }
  }

  $DB_site->query("UPDATE user SET birthday='".vb_escape($birthday)."',signature='".vb_escape($signature)."',customtitle='".intval($customtitle)."',usertitle='".vb_escape($customtext)."',email='".vb_escape(htmlspecialchars($email))."',parentemail='".vb_escape(htmlspecialchars($parentemail))."',coppauser='$coppauser',homepage='".vb_escape(htmlspecialchars($homepage))."',icq='".vb_escape(htmlspecialchars($icq))."',aim='".vb_escape(htmlspecialchars($aim))."',yahoo='".vb_escape(htmlspecialchars($yahoo))."',usergroupid='$bbuserinfo[usergroupid]' WHERE userid='$bbuserinfo[userid]'");
  if ($showbirthdays)
    getbirthdays();
  // insert custom user fields
  $DB_site->query("UPDATE userfield SET userid=$bbuserinfo[userid]$userfields WHERE userid=$bbuserinfo[userid]");

  if ($newemailaddress) {
    eval("standardredirect(\"".gettemplate("redirect_updatethanks_newemail")."\",\"usercp.php?s=$session[sessionhash]\");");
  } else {
    eval("standardredirect(\"".gettemplate("redirect_updatethanks")."\",\"usercp.php?s=$session[sessionhash]\");");
  }

}

// ############################### start modify options ###############################
if ($action=="editoptions") {
  $templatesused = 'modifyoptions_maxposts,modifyoptions_styleset,modifyoptions_stylecell,usercpnav,modifyoptions';
  include("./global.php");
  // do modify profile form

  if ($bbuserinfo['userid']==0 or $permissions['canmodifyprofile']==0) {
    show_nopermission();
  }

  if ($bbuserinfo['adminemail']) {
    $allowmailchecked="checked";
    $allowmailnotchecked="";
  } else {
    $allowmailchecked="";
    $allowmailnotchecked="checked";
  }

  if ($bbuserinfo['emailnotification']) {
    $emailnotificationchecked="checked";
    $emailnotificationnotchecked="";
  } else {
    $emailnotificationchecked="";
    $emailnotificationnotchecked="checked";
  }

  if ($bbuserinfo['showsignatures']) {
    $showsignatureschecked="checked";
    $showsignaturesnotchecked="";
  } else {
    $showsignatureschecked="";
    $showsignaturesnotchecked="checked";
  }

  if ($bbuserinfo['showavatars']) {
      $showavatarschecked="checked";
      $showavatarsnotchecked="";
    } else {
      $showavatarschecked="";
      $showavatarsnotchecked="checked";
  }

  if ($bbuserinfo['showimages']) {
      $showimageschecked="checked";
      $showimagesnotchecked="";
    } else {
      $showimageschecked="";
      $showimagesnotchecked="checked";
  }

  if ($bbuserinfo['showvbcode']) {
      $vbcodechecked="checked";
      $vbcodenotchecked="";
    } else {
      $vbcodechecked="";
      $vbcodenotchecked="checked";
  }

  if ($bbuserinfo['showemail']) {
    $showemailchecked="checked";
    $showemailnotchecked="";
  } else {
    $showemailchecked="";
    $showemailnotchecked="checked";
  }

  if ($bbuserinfo['invisible']) {
    $invisiblechecked="checked";
    $invisiblenotchecked="";
  } else {
    $invisiblechecked="";
    $invisiblenotchecked="checked";
  }

  if ($bbuserinfo['cookieuser']) {
    $cookieuserchecked="checked";
    $cookieusernotchecked="";
  } else {
    $cookieuserchecked="";
    $cookieusernotchecked="checked";
  }

  if ($bbuserinfo['nosessionhash']) {
    $nosessionhashchecked="checked";
    $nosessionhashnotchecked="";
  } else {
    $nosessionhashchecked="";
    $nosessionhashnotchecked="checked";
  }

  if ($bbuserinfo['receivepm']) {
    $receivepmchecked="checked";
    $receivepmnotchecked="";
  } else {
    $receivepmchecked="";
    $receivepmnotchecked="checked";
  }

  if ($bbuserinfo['emailonpm']) {
    $emailonpmchecked="checked";
    $emailonpmnotchecked="";
  } else {
    $emailonpmchecked="";
    $emailonpmnotchecked="checked";
  }

  if ($bbuserinfo['pmpopup']) {
    $pmpopupchecked="checked";
    $pmpopupnotchecked="";
  } else {
    $pmpopupchecked="";
    $pmpopupnotchecked="checked";
  }

  $days1selected = '';
	$days2selected = '';
	$days5selected = '';
	$days10selected = '';
	$days20selected = '';
	$days30selected = '';
	$days45selected = '';
	$days60selected = '';
	$days75selected = '';
	$days100selected = '';
	$days365selected = '';
	$days1000selected = '';
	if ($bbuserinfo['daysprune']==-1) {
    $daysdefaultselected="selected";
  } else {
		$daysdefaultselected = '';

    $dname="days".$bbuserinfo['daysprune']."selected";
    $$dname="selected";
  }

	unset ($timezonesel);
  if ($bbuserinfo['timezoneoffset']<0) {
    $timezonesel["n".(-$bbuserinfo['timezoneoffset']*10)]="selected";
  } else {
    $timezonesel[$bbuserinfo['timezoneoffset']*10]="selected";
  }

	$day1selected = '';
	$day2selected = '';
	$day3selected = '';
	$day4selected = '';
	$day5selected = '';
	$day6selected = '';
	$day7selected = '';

  if ($bbuserinfo['startofweek']>0) {
     $dname="day".$bbuserinfo['startofweek']."selected";
     $$dname = "selected";
  } else {
     $day1selected = "selected";
  }

  $bbuserinfo['avatarurl']=getavatarurl($bbuserinfo['userid']);
  if ($bbuserinfo['avatarurl']=="") {
    $bbuserinfo['avatarurl']="{imagesfolder}/clear.gif";
  }

  //MaxPosts by User
  $maxpostsoptions = '';
  $optionArray = explode(",", $usermaxposts);
  $foundmatch = 0;
  while (list($key, $val) = each($optionArray))
  {
     if ($val == $bbuserinfo['maxposts'])
     {   $selected = "selected";
         $foundmatch = 1;
     }
     else
     {   $selected = "";   }
     eval ("\$maxpostsoptions .= \"".gettemplate("modifyoptions_maxposts")."\";");
  }
  if ($foundmatch == 0)
  {
     $postsdefaultselected = "selected";
  } else {
		$postsdefaultselected = '';
	}

  $stylesetlist = "";
  if ($allowchangestyles==1) {
    $stylesets=$DB_site->query("SELECT * FROM style WHERE userselect=1 ORDER BY title");
    if ( !isset($bbuserinfo['realstyleid']) ) {
      $bbuserinfo['realstyleid'] = $bbuserinfo['styleid'];
    }
    while($thisset=$DB_site->fetch_array($stylesets)) {
      if ($bbuserinfo['realstyleid']==$thisset['styleid']) {
        $selected = "selected";
      } else {
        $selected = "";
      }
      $thisid = $thisset['styleid'];
      $thisstylename = $thisset['title'];
      eval ("\$stylesetlist .= \"".gettemplate("modifyoptions_styleset")."\";");
      eval ("\$stylecell = \"".gettemplate("modifyoptions_stylecell")."\";");
    }
  } else {
    $stylecell = "";
  }

  // draw cp nav bar
  $cpnav[1]="{secondaltcolor}";
  $cpnav[2]="{secondaltcolor}";
  $cpnav[3]="{firstaltcolor}";
	$cpmenu[3]="class=\"fjsel\" selected";
  $cpnav[4]="{secondaltcolor}";
  $cpnav[5]="{secondaltcolor}";
  $cpnav[6]="{secondaltcolor}";
  $cpnav[7]="{secondaltcolor}";
  eval("\$cpnav = \"".gettemplate("usercpnav")."\";");

  eval("dooutput(\"".gettemplate("modifyoptions")."\");");
}

// ############################### start update options ###############################
if ($_POST['action']=="updateoptions") {
  $templatesused = 'redirect_updatethanks';
  include("./global.php");

  if ($bbuserinfo['userid']==0 or $permissions['canmodifyprofile']==0) {
    show_nopermission();
  }

  $adminemail=iif($allowmail=="yes",1,0);
  $emailnotification=iif($emailnotification=="yes",1,0);
  $options=iif($showsignatures=="yes",1,0);
  $options+=iif($showavatars=="yes",2,0);
  $options+=iif($showimages=="yes",4,0);
  $options+=iif($vbcode=="yes",8,0);
  $showemail=iif($showemail=="yes",1,0);
  $invisible=iif($invisible=="yes",1,0);
  $cookieuser=iif($cookieuser=="yes",1,0);
  $nosessionhash=iif($nosessionhash=="yes",1,0);
  $receivepm=iif($receivepm=="yes",1,0);
  $emailonpm=iif($emailonpm=="yes",1,0);
  $pmpopup=iif($pmpopup=="yes",1,0);

  if ($allowchangestyles==1) {
    $updatestyles = "styleid='".vb_escape($newstyleset)."',";
    if ($newstyleset!=$bbuserinfo['styleid']) {
      $DB_site->query("UPDATE session SET styleid=".intval($newstyleset)." WHERE sessionhash='".vb_escape($session['dbsessionhash'])."'");
    }
    vbsetcookie("bbstyleid","",1);
  } else {
    $updatestyles = "";
  }

  //delete cookies if cookie user is off
  if ($cookieuser==0) {
    vbsetcookie("bbuserid","", true, true);
    vbsetcookie("bbpassword","", true, true);
  }

  $DB_site->query("UPDATE user
                   SET ".$updatestyles."adminemail='$adminemail',
                      showemail='$showemail',invisible='$invisible',cookieuser='$cookieuser',
                      maxposts='".vb_escape($umaxposts)."',daysprune='".vb_escape($prunedays)."',
                      timezoneoffset='".vb_escape($timezoneoffset)."',emailnotification='$emailnotification',
                      startofweek='".vb_escape($startofweek)."',options='$options',receivepm='$receivepm',
                      emailonpm='$emailonpm',pmpopup='$pmpopup',usergroupid='$bbuserinfo[usergroupid]',
                      nosessionhash='$nosessionhash'
                   WHERE userid='$bbuserinfo[userid]'");

  if ($modifyavatar!="") {
    $goto="member.php?s=$session[sessionhash]&amp;action=editavatar";
  } else {
    $goto="usercp.php?s=$session[sessionhash]";
  }
  eval("standardredirect(\"".gettemplate("redirect_updatethanks")."\",\"$goto\");");

}

// ############################### start modify password ###############################
if ($action=="editpassword") {
  $templatesused = 'usercpnav,modifypassword';
  include("./global.php");
  // do modify profile form

  if ($bbuserinfo['userid']==0) {
    show_nopermission();
  }

  // draw cp nav bar
  $cpnav[1]="{secondaltcolor}";
  $cpnav[2]="{secondaltcolor}";
  $cpnav[3]="{secondaltcolor}";
  $cpnav[4]="{firstaltcolor}";
	$cpmenu[4]="class=\"fjsel\" selected";
  $cpnav[5]="{secondaltcolor}";
  $cpnav[6]="{secondaltcolor}";
  $cpnav[7]="{secondaltcolor}";
  eval("\$cpnav = \"".gettemplate("usercpnav")."\";");

  eval("dooutput(\"".gettemplate("modifypassword")."\");");
}

// ############################### start update password ###############################
if ($_POST['action']=="updatepassword") {
  $templatesused = 'error_wrongpassword,error_passwordmismatch,redirect_updatethanks';
  include("./global.php");

  if ($bbuserinfo['userid']==0) {
    show_nopermission();
  }

  $newpassword = trim($newpassword);
  $newpasswordconfirm = trim($newpasswordconfirm);

  // validate old password and stop them being empty
  if (md5($currentpassword)!=$bbuserinfo['password'] or empty($newpassword)) {
    eval("standarderror(\"".gettemplate("error_wrongpassword")."\");");
    exit;
  }

  if ($newpassword!=$newpasswordconfirm) {
    eval("standarderror(\"".gettemplate("error_passwordmismatch")."\");");
    exit;
  }

  $DB_site->query("UPDATE user SET password='".vb_escape(md5($newpassword))."',usergroupid='$bbuserinfo[usergroupid]' WHERE userid='$bbuserinfo[userid]'");

  eval("standardredirect(\"".gettemplate("redirect_updatethanks")."\",\"usercp.php?s=$session[sessionhash]\");");

}

// ############################### start modify avatar ###############################
if ($action=="editavatar") {
  $templatesused = "modifyavatarbit,modifyavatar_customwebsite,modifyavatar_customupload,modifyavatar_custom,usercpnav,modifyavatar";
  include("./global.php");
  // do modify profile form
  if (!$avatarenabled) {
    eval("standarderror(\"".gettemplate("error_avatardisabled")."\");");
    exit;
  }

  if ($bbuserinfo['userid']==0) {
    show_nopermission();
  }

	unset($avatarchecked);

  $avatarchecked[$bbuserinfo['avatarid']]="checked";

  $avatarlist="";
  $nouseavatarchecked="";
  if (!$avatarinfo=$DB_site->query_first("SELECT * FROM customavatar WHERE userid=$bbuserinfo[userid]")) {
    // no custom avatar exists
    if ($bbuserinfo['avatarid']==0) {
      // must have no avatar selected
      $nouseavatarchecked="checked";
      $avatarchecked[0]="";
    }
  }

  if (intval($numavatarshigh)==0)
    $numavatarshigh=5;
  if (intval($numavatarswide)==0)
    $numavatarswide=5;
  $perpage = $numavatarshigh * $numavatarswide;

  $avatarcount = $DB_site->query_first("SELECT COUNT(*) AS count
                                  FROM avatar
                                  WHERE minimumposts<='$bbuserinfo[posts]'");
  $totalavatars = $avatarcount['count'];
  sanitize_pageresults($totalavatars, $pagenumber, $perpage, $perpage, $perpage);

  $limitlower=($pagenumber-1)*$perpage+1;
  $limitupper=($pagenumber)*$perpage;

  if ($limitupper>$totalavatars) {
    $limitupper=$totalavatars;
    if ($limitlower>$totalavatars) {
      $limitlower=$totalavatars-$perpage;
    }
  }
  if ($limitlower<=0) {
    $limitlower=1;
  }

  $avatars=$DB_site->query("SELECT *
                            FROM avatar
                            WHERE minimumposts<='$bbuserinfo[posts]'
                            ORDER BY title
                            LIMIT ".($limitlower-1).",$perpage");

  $avatarcount = 0;
  while ($avatar=$DB_site->fetch_array($avatars)) {
    $avatarid=$avatar['avatarid'];
    if ($avatarcount==0)
      $avatarlist .= '<tr>';
    eval("\$avatarlist .= \"".gettemplate("modifyavatarbit")."\";");
    $avatarcount++;
    if ($avatarcount==$numavatarswide) {
      $avatarlist .= '</tr>';
      $avatarcount = 0;
    }
  }
  if ($avatarcount!=0) {
    while ($avatarcount < $numavatarswide) {
      $avatarlist .= '<td bgcolor="{firstaltcolor}">&nbsp;</td>';
      $avatarcount++;
    }
    $avatarlist .= '</tr>';
  }

  $pagenav = getpagenav($totalavatars,"member.php?s=$session[sessionhash]&amp;action=editavatar&amp;perpage=$perpage");


	if (phpversion() < '4.0.3') {
     $enctype = 'enctype="multipart/form-data"';
   } else if (ini_get('file_uploads')) {
     $enctype = 'enctype="multipart/form-data"';
   } else {
     $enctype = '';
   }

	$customupload = '';
	$customavatar = '';
	$customwebsite = '';

  if (($avatarallowupload or $avatarallowwebsite) and $bbuserinfo['posts'] >= $avatarcustomposts) {
    $bbuserinfo['avatarurl']=getavatarurl($bbuserinfo['userid']);
    if ($bbuserinfo['avatarurl']=="" or $bbuserinfo['avatarid']!=0) {
      $bbuserinfo['avatarurl']="{imagesfolder}/clear.gif";
    }
    $backcolor = "{secondaltcolor}";
    $bgclass = "alt2";
    if ($avatarallowwebsite) {
      eval("\$customwebsite = \"".gettemplate("modifyavatar_customwebsite")."\";");
      $backcolor = "{firstaltcolor}";
      $bgclass = "alt1";
    }

    if ($avatarallowupload and (!$safeupload or function_exists("is_uploaded_file"))) {
      $maxattachsize_temp = getmaxattachsize();
      eval("\$customupload = \"".gettemplate("modifyavatar_customupload")."\";");
    }

    eval("\$customavatar = \"".gettemplate("modifyavatar_custom")."\";");
    // dynamically determine whether or not to put in enctype
  }


  // draw cp nav bar
  $cpnav[1]="{secondaltcolor}";
  $cpnav[2]="{secondaltcolor}";
  $cpnav[3]="{firstaltcolor}";
  $cpnav[4]="{secondaltcolor}";
  $cpnav[5]="{secondaltcolor}";
  $cpnav[6]="{secondaltcolor}";
  $cpnav[7]="{secondaltcolor}";
	$cpmenu[9]="class=\"fjsel\" selected";
  eval("\$cpnav = \"".gettemplate("usercpnav")."\";");

  eval("dooutput(\"".gettemplate("modifyavatar")."\");");
}

// ############################### start update avatar ###############################
if ($_POST['action']=="updateavatar") {
  $templatesused = "error_avatarmoreposts,error_avatarbadurl,error_avataruploaderror,error_avatarbaddimensions,error_avatarnotimage,error_avatartoobig,error_avatarmoreposts,redirect_updatethanks";
  include("./global.php");

  if ($bbuserinfo['userid']==0) {
    show_nopermission();
  }

  $useavatar=iif($avatarid==-1,0,1);

  if ($useavatar) {
    if ($avatarid==0 and (($avatarallowupload or $avatarallowwebsite) and $bbuserinfo['posts'] >= $avatarcustomposts)) {
      // using custom avatar
      $filename="";

      if ($bbuserinfo['posts']<$avatarcustomposts) {
        eval("standarderror(\"".gettemplate("error_avatarmoreposts")."\");");
      }

      // check for new uploaded file or for new url
      $avatarurl=trim($avatarurl);
      if ($avatarurl!="" and $avatarurl!="http://www." and $avatarallowwebsite and $bbuserinfo['posts'] >= $avatarcustomposts) {
        // get file from url

        if (!preg_match('#^(https?|ftp)://#i', $avatarurl)) {
          eval("standarderror(\"".gettemplate("error_avatarbadurl")."\");");;
        }

        $filenum=@fopen($avatarurl,"rb");
        if ($filenum == 0) {
          eval("standarderror(\"".gettemplate("error_avatarbadurl")."\");");
        }

        $contents="";
        while (!@feof($filenum)) {
          $contents.=@fread($filenum,1024); //filesize($filename));
        }
        @fclose($filenum);

        $avatarfile_name = "vba".substr(uniqid(microtime()),-8);
        if ($safeupload) {
          $filename="$tmppath/$avatarfile_name";
          $filenum=@fopen($filename,"wb");
          @fwrite($filenum,$contents);
          @fclose($filenum);
        } else {
          // write in temp dir
          $filename=tempnam(get_cfg_var("upload_tmp_dir"),"vbavatar");
          $filenum=@fopen($filename,"wb");
          @fwrite($filenum,$contents);
          @fclose($filenum);
        }
      } elseif ($avatarallowupload and $bbuserinfo['posts'] >= $avatarcustomposts) {
        // check file exists on server
        if (is_array($_FILES)) {
    	    $avatarfile = $_FILES['avatarfile']['tmp_name'];
    	    $avatarfile_name = $_FILES['avatarfile']['name'];
    	    $avatarfile_size = $_FILES['avatarfile']['size'];
        }
        if (!is_uploaded_file($avatarfile)) {
          eval("standarderror(\"".gettemplate("error_avataruploaderror")."\");");
        }
        if ($safeupload) {
	      $path = "$tmppath/vba".substr(uniqid(microtime()),-8);
	      move_uploaded_file($avatarfile, "$path");
          $avatarfile = $path;
        }
        if (file_exists($avatarfile)) {
          if (filesize($avatarfile)!=$avatarfile_size) {
            @unlink($avatarfile);
            eval("standarderror(\"".gettemplate("error_avataruploaderror")."\");");
          }
          $filename=$avatarfile;
        } else {
          // bad upload
          $avatarid=0;
          $filename="";
        }
      }

		$extension_map = array(
			'gif' => '1',
			'jpg' => '2',
			'jpeg'=> '2',
			'jpe' => '2',
			'png' => '3',
		);
		$extension = strtolower(getextension($avatarfile_name));

      if ($filename!="") {
        // check valid image
        // Verify that file is playing nice
        $validfile = false;
	     $fp = fopen($filename, 'rb');
        if ($fp)
        {
           $imageheader = fread($fp, 200);
           fclose($fp);
           if (!preg_match('#<html|<head|<body|<script#si', $imageheader))
           {
              $validfile = true;
           }
        }
        if ($validfile AND $imginfo=@getimagesize($filename)) {
          if ($imginfo[0]>$avatarmaxdimension or $imginfo[1]>$avatarmaxdimension) {
            @unlink($filename);
            eval("standarderror(\"".gettemplate("error_avatarbaddimensions")."\");");
          }
          if (($imginfo[2] != 1 and $imginfo[2] != 2 and $imginfo[2] != 3) OR $extension_map["$extension"] != $imginfo[2]) {
            @unlink($filename);
            eval("standarderror(\"".gettemplate("error_avatarnotimage")."\");");
          }
        } else {
          @unlink($filename);
          eval("standarderror(\"".gettemplate("error_avatarnotimage")."\");");
          exit;
        }

        // read file
        $filesize=@filesize($filename);
        if ($filesize>$avatarmaxsize) {
          @unlink($filename);
          eval("standarderror(\"".gettemplate("error_avatartoobig")."\");");
        }

        $filenum=@fopen($filename,"rb");
        $filestuff=@fread($filenum,$filesize);
        @fclose($filenum);

        @unlink($filename);

        if ($avexists=$DB_site->query_first("SELECT userid FROM customavatar WHERE userid=$bbuserinfo[userid]")) {
          $DB_site->query("UPDATE customavatar SET filename='".vb_escape($avatarfile_name)."',dateline='".time()."',avatardata='".vb_escape($filestuff)."' WHERE userid=$bbuserinfo[userid]");
        } else {
          $DB_site->query("INSERT INTO customavatar (userid,avatardata,dateline,filename) VALUES ($bbuserinfo[userid],'".vb_escape($filestuff)."','".time()."','".vb_escape($avatarfile_name)."')");
        }
      }
    } else {
      $avatarid=verifyid("avatar",$avatarid);
      $avatarinfo=$DB_site->query_first("SELECT minimumposts FROM avatar WHERE avatarid=$avatarid");
      if ($avatarinfo['minimumposts']>$bbuserinfo['posts']) {
        eval("standarderror(\"".gettemplate("error_avatarmoreposts")."\");");
        // not enough posts error
        exit;
      }
      $DB_site->query("DELETE FROM customavatar WHERE userid=$bbuserinfo[userid]");
    }
  } else {
    $avatarid=0;
    $DB_site->query("DELETE FROM customavatar WHERE userid=$bbuserinfo[userid]");
  }

  $DB_site->query("UPDATE user SET avatarid='".vb_escape($avatarid)."',usergroupid='$bbuserinfo[usergroupid]' WHERE userid='$bbuserinfo[userid]'");

  eval("standardredirect(\"".gettemplate("redirect_updatethanks")."\",\"usercp.php?s=$session[sessionhash]\");");

}

// ############################### start get info ###############################
if ($action=="getinfo") {
  $templatesused = "getinfo_sendpm,aol,icq,yahoo,getinfo_birthday,getinfo_customfields,getinfo";
  include("./global.php");

  $permissions=getpermissions();
  if (!$permissions['canview'] or !$permissions['canviewmembers']) {
		show_nopermission();
  }

  if ($find=="firstposter" and isset($threadid)) {
    $threadid=verifyid("thread",$threadid);
    $getuserid=$DB_site->query_first("SELECT postuserid FROM thread WHERE threadid='$threadid' AND visible=1");
    $userid=$getuserid['postuserid'];
  }
  if ($find=="lastposter" and isset($threadid)) {
    $threadid=verifyid("thread",$threadid);
    $getuserid=$DB_site->query_first("SELECT userid FROM post WHERE threadid='$threadid' AND visible=1 ORDER BY dateline DESC LIMIT 1");
    $userid=$getuserid['userid'];
  }
  if ($find=="lastposter" and isset($forumid)) {
    $foruminfo=verifyid("forum",$forumid,1,1);
    $forumid = $foruminfo['forumid'];

    // prevent a small backdoor where anyone could see who the last poster in ANY forum was
    $forumperms=getpermissions($forumid);
    if (!$forumperms['canview']) {
		show_nopermission();
    }

    $forumslist = "";
    $getchildforums=$DB_site->query("SELECT forumid,parentlist FROM forum WHERE INSTR(CONCAT(',',parentlist,','),',$forumid,')>0");
    while ($getchildforum=$DB_site->fetch_array($getchildforums)) {
      $forumslist.=",$getchildforum[forumid]";
    }

    $thread=$DB_site->query_first("SELECT threadid FROM thread WHERE forumid IN (0$forumslist) AND visible=1 AND (sticky=1 OR sticky=0) AND lastpost>='".($foruminfo['lastpost']-30)."' AND open<>10 ORDER BY lastpost DESC LIMIT 1");
    $getuserid=$DB_site->query_first("SELECT userid FROM post WHERE threadid='$thread[threadid]' AND visible=1 ORDER BY dateline DESC LIMIT 1");
    $userid=$getuserid['userid'];
  }
  if ($find=="moderator" and isset($moderatorid)) {
    $moderatorid=verifyid("moderator",$moderatorid);
    $getuserid=$DB_site->query_first("SELECT userid FROM moderator WHERE moderatorid='$moderatorid'");
    $userid=$getuserid['userid'];
  }

  if ($userid=="" and $username!="") {
    $username=urldecode($username);
    $user=$DB_site->query_first("SELECT userid FROM user WHERE username='".vb_escape(htmlspecialchars($username))."'");
    $userid=$user['userid'];
  }

  if ($userid==0) {
    eval("standarderror(\"".gettemplate("error_unregistereduser")."\");");
  }

  $userid = verifyid("user",$userid);

  // display user info

  $userinfo=getuserinfo($userid);

  if ($userinfo['usergroupid'] == 4 && $permissions['cancontrolpanel'] != 1)
  {
    show_nopermission();
  }

  $usergroupperms = getpermissions(0, $userinfo['userid'], $userinfo['usergroupid']);

  if ($userinfo['customtitle']==2)
    $userinfo['usertitle'] = htmlspecialchars($userinfo['usertitle']);
  $userinfo['datejoined']=vbdate($dateformat,$userinfo['joindate']);

  $jointime = (time() - $userinfo['joindate']) / 86400; // Days Joined
  if ($jointime < 1) { // User has been a member for less than one day.
    $postsperday = "$userinfo[posts]";
  } else {
    $postsperday = sprintf("%.2f",($userinfo['posts'] / $jointime));
  }

  if ($userinfo['homepage']!="http://" and $userinfo['homepage']!="") {
    $userinfo['homepage']=$userinfo['homepage'];
  } else {
    $userinfo['homepage']="";
  }

  if ($userinfo['receivepm'] and $usergroupperms['canusepm']) {
    eval("\$userinfo[sendpm] = \"".gettemplate("getinfo_sendpm")."\";");
  } else {
    $userinfo['sendpm'] = "";
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

  $userinfo['avatarurl']=getavatarurl($userinfo['userid']);
  if ($userinfo['avatarurl']=="") {
    $userinfo['avatarurl']="{imagesfolder}/clear.gif";
  }

  // get last post
  $totalposts=$userinfo['posts'];
  if ($userinfo['posts']!=0 and $userinfo['lastpost']!=0) {
    $lastpostdate=vbdate($dateformat,$userinfo['lastpost']);
    $lastposttime=vbdate($timeformat,$userinfo['lastpost']);

    $getlastposts=$DB_site->query("SELECT thread.title,thread.threadid,thread.forumid,postid,post.dateline FROM post,thread WHERE thread.threadid=post.threadid AND thread.visible = 1 AND post.userid='$userid' ORDER BY post.dateline DESC LIMIT 20");

    while ($getlastpost=$DB_site->fetch_array($getlastposts)) {

      $getperms=getpermissions($getlastpost['forumid']);
      if ($getperms['canview']) {
        $lastposttitle=$getlastpost['title'];
        $lastposturl="showthread.php?s=$session[sessionhash]&amp;postid=$getlastpost[postid]#post$getlastpost[postid]";
				$lastpostdate=vbdate($dateformat,$getlastpost['dateline']);
				$lastposttime=vbdate($timeformat,$getlastpost['dateline']);
        break;
      }
    }
  } else {
    $lastpostdate="Never";
    $lastposttime = '';
  }
  if ($calbirthday == 1) {
     if ($backcolor=="{firstaltcolor}") {
        $backcolor="{secondaltcolor}";
				$bgclass = "alt2";
     } else {
        $backcolor="{firstaltcolor}";
			$bgclass = "alt1";
		 }
     // Set birthday fields right here!
     if ($userinfo['birthday'] == '0000-00-00') {
        $birthday = "N/A";
     } else {
        $bday = explode("-",$userinfo['birthday']);
        if (date("Y")>$bday[0] and $bday[0]>1901 && $bday[0]!='0000') {
          $cformat = str_replace('Y', $bday['0'], $calformat1);
          $cformat = str_replace('y', substr($bday['0'], 2, 2), $cformat);
          $birthday = @date($cformat,mktime(0,0,0,$bday[1],$bday[2],1992));
        } else {
          // lets send a valid year as some PHP3 don't like year to be 0
          // $calformat2 should not contania year identifier so the year doesn't matter
          $birthday = @date($calformat2,mktime(0,0,0,$bday[1],$bday[2],1992));
        }
        if ($birthday=="") {
          $birthday="$bday[1]-$bday[2]-$bday[0]";
        }
     }
     eval("\$birthday = \"".gettemplate("getinfo_birthday")."\";");
  } else {
		$birthday = '';
	}


  // Get referrals
  if ($usereferrer) {
    if ($backcolor=="{firstaltcolor}") {
			$backcolor="{secondaltcolor}";
			$bgclass = "alt2";
		} else {
				$backcolor="{firstaltcolor}";
			$bgclass = "alt1";
		}
    $refcount = $DB_site->query_first("SELECT count(*) AS count
                                       FROM user
                                       WHERE referrerid = '$userinfo[userid]'");
    $referrals = $refcount['count'];
    eval("\$referrals = \"".gettemplate("getinfo_referrals")."\";");
  } else {
		$referrals = '';
	}

  // get extra profile fields
  $customfields = '';
  $profilefields=$DB_site->query("SELECT profilefieldid,required,title
                                  FROM profilefield
                                  WHERE hidden=0
                                  ORDER BY displayorder");
  while ($profilefield=$DB_site->fetch_array($profilefields)) {
    if ($backcolor=="{firstaltcolor}") {
      $backcolor="{secondaltcolor}";
			$bgclass = "alt2";
    } else {
      $backcolor="{firstaltcolor}";
		  $bgclass = "alt1";
    }

    $profilefieldname="field$profilefield[profilefieldid]";
    $profilefield['value']=$userinfo[$profilefieldname];
    eval("\$customfields .= \"".gettemplate("getinfo_customfields")."\";");

  }

  $userinfo['signature'] = bbcodeparse($userinfo['signature'],0,0);

  eval("dooutput(\"".gettemplate("getinfo")."\");");

}

// ############################### start aim message ###############################
if ($action=="aimmessage") {
  $templatesused = 'aimmessage';
  include("./global.php");
  $aim = urlencode(htmlspecialchars($aim));
  eval("dooutput(\"".gettemplate("aimmessage")."\");");

}

// ############################### start mail member ###############################
if ($action=="mailform") {
  $templatesused = 'error_emaildisabled,mailform,error_showemail,error_usernoemail';
  include("./global.php");
  $userid=verifyid("user",$userid);

  if (!$bbuserinfo['userid'] or $bbuserinfo['usergroupid']==3) {
    //don't let people awaiting email confirmation use it either as their email may be fake
    show_nopermission();
  }

  $userinfo=$DB_site->query_first("SELECT username,email,showemail FROM user WHERE userid='$userid'");
  if ($userinfo['showemail']) {
    $destusername=$userinfo['username'];
    if ($secureemail) {
			if (!$enableemail) {
				eval("standarderror(\"".gettemplate("error_emaildisabled")."\");");
				exit;
			}

      eval("dooutput(\"".gettemplate("mailform")."\");");
    } else {
      if ($displayemails) {
				$destusername=$userinfo['username'];
				$email=$userinfo['email'];

				eval("standarderror(\"".gettemplate("error_showemail")."\");");
      } else {
				eval("standarderror(\"".gettemplate("error_usernoemail")."\");");
				exit;
      }
    }
  } else {
    eval("standarderror(\"".gettemplate("error_usernoemail")."\");");
    exit;
  }
}

// ############################### start mail member ###############################
if ($_POST['action']=="emailmessage") {
  $templatesused = 'error_emaildisabled,error_usernoemail,error_nomessage,email_usermessage,redirect_sentemail';
  include("./global.php");

  if (!$bbuserinfo['userid'] or $bbuserinfo['usergroupid']==3) {
    show_nopermission();
  }

  if (!$enableemail) {
    eval("standarderror(\"".gettemplate("error_emaildisabled")."\");");
    exit;
  }

  $destuserid=verifyid("user",$userid);
  $destuserinfo=$DB_site->query_first("SELECT username,email,showemail FROM user WHERE userid='$destuserid'");

  if (!$destuserinfo['showemail']) {
    eval("standarderror(\"".gettemplate("error_usernoemail")."\");");
    exit;
  }

  $message = trim($message);
  $message = stripsession($message);
  if (!$message) {
    eval("standarderror(\"".gettemplate("error_nomessage")."\");");
  }

  eval("\$sendmessage = \"".gettemplate("email_usermessage",1,0)."\";");

  $subject = preg_replace('/[\n\t\r,]/s', ' ', $subject);

  vbmail($destuserinfo['email'], $subject, $sendmessage, $bbuserinfo['email'], '', $bbuserinfo['username']);

  // parse this next line with eval:
  $sendtoname=$destuserinfo['username'];
  eval("standardredirect(\"".gettemplate("redirect_sentemail")."\",\"$_POST[url]\");");
}

?>