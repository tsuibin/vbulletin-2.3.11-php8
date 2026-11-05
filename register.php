<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

$templatesused = "signupadult,signupverify,modifyoptions_maxposts,register_birthday,modifyoptions_styleset,register_stylecell,register_customfields,registeradult,registercoppa,email_newuser,emailsubject_newuser,email_activateaccount,emailsubject_activateaccount,redirect_registerthanks,register_imagebit";

require("./global.php");

if ((!isset($action) or $action=="") and (!isset($a) or $a=="")) {
  $action="signup";
}

if ($url==$HTTP_REFERER) {
  $url=urlencode($url);
}

// ############################### start signup ###############################
if ($action=="signup") {

  if (!$allowregistration) {
    eval("standarderror(\"".gettemplate("error_noregister")."\");");
    exit;
  }

  if ($bbuserinfo['userid']!=0 and !$allowmultiregs) {
    $getuser=$DB_site->query_first("SELECT username FROM user WHERE userid='$bbuserinfo[userid]'");
    $username=$getuser['username'];

    eval("standarderror(\"".gettemplate("error_alreadyregistered")."\");");

    exit;
  }

  $coppadate=vbdate($dateformat,mktime(0,0,0,date("m"),date("d"),date("Y")-13));

  if (!$usecoppa) {
    $who="adult";
  }

  if ($who=="coppa") {
    eval("dooutput(\"".gettemplate("signupcoppa")."\");");
  } else {
    if ($who=="adult") {
      eval("dooutput(\"".gettemplate("signupadult")."\");");
    } else {
      eval("dooutput(\"".gettemplate("signupverify")."\");");
   }
 }
}

// ############################### start register ###############################
if ($action=="register") {

	if (!$allowregistration) {
		eval("standarderror(\"".gettemplate("error_noregister")."\");");
		exit;
	}

	if ($bbuserinfo['userid']!=0 and !$allowmultiregs) {
		$getuser=$DB_site->query_first("SELECT username FROM user WHERE userid='$bbuserinfo[userid]'");
		$username=$getuser['username'];

		eval("standarderror(\"".gettemplate("error_alreadyregistered")."\");");

		exit;
	}

	// set up starting values for alternating colors
	$req_bgcolor = iif($usecoppa and $who == 'coppa', 1, 2);
	$opt_bgcolor = 2;
	$prf_bgcolor = 1;

	// #############################################################################
	function altbgcolor(&$colorcounter) {
		global $bgcolor, $bgclass;
		if ($colorcounter == 1) {
			$colorcounter = 2;
			$bgcolor = '{secondaltcolor}';
			$bgclass = 'alt2';
		} else {
			$colorcounter = 1;
			$bgcolor = '{firstaltcolor}';
			$bgclass = 'alt1';
		}
	}
	// #############################################################################

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

	// Generate image string if Image Checking is enabled.
	if ($regimagecheck AND $gdversion) {

		$string = regstring(6);

		// Clean table out of entries > 1 hour
		$DB_site->query("DELETE FROM regimage WHERE dateline < " . ($ourtimenow - 3600));
		$imagehash = md5(uniqid(rand(), 1));

		// Gen hash and insert into database;
		$DB_site->query("INSERT INTO regimage (regimagehash, imagestamp, dateline) VALUES ('" . vb_escape($imagehash) . "', '" . vb_escape($string) . "',$ourtimenow)");

		altbgcolor($req_bgcolor);
		eval("\$imageregbit = \"".gettemplate("register_imagebit")."\";");

	}

	// Referrer
	if ($usereferrer and $bbuserinfo['userid']==0) {
		if ($bbreferrerid) {
			if ($referrername=$DB_site->query_first("SELECT username FROM user WHERE userid = '".vb_escape($bbreferrerid)."'")) {
				$referrername = $referrername['username'];
			}
		}
		altbgcolor($opt_bgcolor);
		eval("\$referrer = \"".gettemplate("register_referrer")."\";");
	} else {
		$referrer = '';
	}

	//Birthday
	if ($calbirthday == 1) {
		altbgcolor($opt_bgcolor);
		eval("\$birthday = \"".gettemplate("register_birthday")."\";");
	} else {
		$birthday = '';
	}

	// get extra profile fields
	$customfields_required = '';
	$customfields = '';
	$profilefields = $DB_site->query("
		SELECT * FROM profilefield
		WHERE editable = 1 ORDER BY displayorder
	");
	while ($profilefield=$DB_site->fetch_array($profilefields)) {
		$profilefieldname = "field$profilefield[profilefieldid]";
		if ($profilefield['required'] == 1) {
			altbgcolor($req_bgcolor);
			eval("\$customfields_required .= \"".gettemplate("register_customfields")."\";");
		} else { // Not Required
			altbgcolor($opt_bgcolor);
			eval("\$customfields .= \"".gettemplate("register_customfields")."\";");
		}
	}

	// User selectable style sets
	$stylesetlist = "";
	if ($allowchangestyles==1) {
		$stylesets=$DB_site->query("SELECT * FROM style WHERE userselect=1");
		while($thisset=$DB_site->fetch_array($stylesets)) {
			if ($bbuserinfo['styleid']==$thisset['styleid']) {
				$selected = "selected";
			} else {
				$selected = "";
			}
			$thisid = $thisset['styleid'];
			$thisstylename = $thisset['title'];

			eval ("\$stylesetlist .= \"".gettemplate("modifyoptions_styleset")."\";");
			altbgcolor($prf_bgcolor);
			eval ("\$stylecell = \"".gettemplate("register_stylecell")."\";");
		}
	} else {
		$stylecell = "";
	}

	// pre-defined avatar options
	$avatarbits = '';
	if ($avatarenabled) {
		$avatars = $DB_site->query("
			SELECT avatarid, title, avatarpath FROM avatar
			WHERE minimumposts = 0 ORDER BY title
		");
		if ($DB_site->num_rows($avatars) > 0) {
			while ($avatar = $DB_site->fetch_array($avatars)) {
				if (!$avatar['title']) {
					$avatar['title'] = $avatar['avatarpath'];
					if (strstr($avatar['title'], '/')) {
						$avatar['title'] = substr( strrchr($avatar['title'], '/') , 1);
					}
					$avatar['title'] = str_replace('_', ' ', $avatar['title']);
					$dotpos = strrpos($avatar['title'], '.');
					$avatar['title'] =  substr($avatar['title'], 0, $dotpos);
				}
				eval("\$avatarbits .= \"".gettemplate("register_avatarbit")."\";");
			}
			altbgcolor($prf_bgcolor);
			eval("\$avatarbit = \"".gettemplate("register_avatar")."\";");
		}
	} else {
		$avatarbit = '';
	}

	if (!$usecoppa) {
		$who="adult";
	}

	//MaxPosts by User
	$optionArray = explode(",", $usermaxposts);
	$maxpostsoptions = '';
	while (list($key, $val) = each($optionArray))
	{
		eval ("\$maxpostsoptions .= \"".gettemplate("modifyoptions_maxposts")."\";");
	}
	$postsdefaultselected = "selected";

	if ($who=="adult") {
		eval("dooutput(\"".gettemplate("registeradult")."\");");
	} else {
		eval("dooutput(\"".gettemplate("registercoppa")."\");");
	}
}

// ############################### start add member ###############################
if ($_POST['action']=="addmember") {
  $email = trim($email);
  if ($enablebanning and $banemail!="") {
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

  if (!$allowregistration) {
    eval("standarderror(\"".gettemplate("error_noregister")."\");");
    exit;
  }

  if ($bbuserinfo['userid']!=0 and !$allowmultiregs) {
    $getuser=$DB_site->query_first("SELECT username FROM user WHERE userid='$bbuserinfo[userid]'");
    $username=$getuser['username'];

    eval("standarderror(\"".gettemplate("error_alreadyregistered")."\");");

    exit;
  }

  $username = trim($username);
  $username = eregi_replace("( ){2,}", " ", $username);
  $emailconfirm = trim($emailconfirm);
  $password = trim($password);
  $passwordconfirm = trim($passwordconfirm);

  // do add user

  if ($checkuser=$DB_site->query_first("SELECT username FROM user WHERE username='".vb_escape(htmlspecialchars($username))."' OR username='".vb_escape(eregi_replace("[^A-Za-z0-9]","",$username))."'")) {
    eval("standarderror(\"".gettemplate("error_usernametaken")."\");");
    exit;
  }

  if (!preg_match('/^[-!#$%&\'*+\\.\/0-9=?A-Z^_`{|}~]+@([-0-9A-Z]+\.)+([0-9A-Z]){2,4}$/i', $email)) {
    eval("standarderror(\"".gettemplate("error_bademail")."\");");
  }

  if ($requireuniqueemail and $checkuser=$DB_site->query_first("SELECT username,email FROM user WHERE email='".vb_escape($email)."'")) {
    eval("standarderror(\"".gettemplate("error_emailtaken")."\");");
    exit;
  }

  if ($username=="" or $email=="" or $emailconfirm=="" or $password=="" or $passwordconfirm=="") {
    eval("standarderror(\"".gettemplate("error_fieldmissing")."\");");
    exit;
  }

  if ($password!=$passwordconfirm) {
    eval("standarderror(\"".gettemplate("error_passwordmismatch")."\");");
    exit;
  }

  if ($email!=$emailconfirm) {
    eval("standarderror(\"".gettemplate("error_emailmismatch")."\");");
    exit;
  }
  if (strlen($username)<$minuserlength) {
    eval("standarderror(\"".gettemplate("error_usernametooshort")."\");");
    exit;
  } elseif (strlen($username)>$maxuserlength) {
    eval("standarderror(\"".gettemplate("error_usernametoolong")."\");");
    exit;
  }

  // check max images
  if ($maximages!=0) {
    $parsedsig=bbcodeparse($signature,0,$allowsmilies);
    if (countchar($parsedsig,"<img")>$maximages) {
      eval("standarderror(\"".gettemplate("error_toomanyimages")."\");");
      exit;
    }
  }

  $testreferrerid['userid'] = 0;
  if ($usereferrer and $bbuserinfo['userid']==0) {
     if ($referrername) {
        if (!$testreferrerid=$DB_site->query_first("SELECT userid FROM user WHERE username = '".vb_escape($referrername)."'")) {
          eval("standarderror(\"".gettemplate("error_badreferrer")."\");");
          exit;
        }
     }
  }

  if ($regimagecheck AND $gdversion) {
  	$imagestamp = trim(str_replace(' ', '', $imagestamp));
  	$ih = $DB_site->query_first("SELECT imagestamp FROM regimage WHERE regimagehash = '" . vb_escape($imagehash) . "'");
  	if (!$imagestamp OR strtoupper($imagestamp) != $ih['imagestamp'])
  	{
  		eval("standarderror(\"".gettemplate("error_badimagecheck")."\");");
        exit;
  	}
  }

  if ($verifyemail) {
    $newusergroupid=3;
  } else {
    if ($moderatenewmembers or $coppauser) {
      $newusergroupid=4;
    } else {
      $newusergroupid=2;
    }
  }

  $adminemail=iif($allowmail=="yes",1,0);
  $showemail=iif($showemail=="yes",1,0);
  $invisible=iif($invisible=="yes",1,0);
  $cookieuser=iif($cookieuser=="yes",1,0);
  $nosessionhash=iif($nosessionhash=="yes",1,0);
  $emailnotification=iif($emailnotification=="yes",1,0);
  $options=iif($showsignatures=="yes",1,0);
  $options+=iif($showavatars=="yes",2,0);
  $options+=iif($showimages=="yes",4,0);
  $options+=iif($vbcode=="yes",8,0);
  $receivepm=iif($receivepm=="yes",1,0);
  $emailonpm=iif($emailonpm=="yes",1,0);
  $pmpopup=iif($pmpopup=="yes",1,0);

  $icq=intval($icq);
  if ($icq==0) {
    $icq="";
  }

  $usergroup=$DB_site->query_first("SELECT usertitle FROM usergroup WHERE usergroupid='$newusergroupid'");
  if ($usergroup['usertitle']=="") {
    $gettitle=$DB_site->query_first("SELECT title FROM usertitle WHERE minposts<=0 ORDER BY minposts DESC LIMIT 1");
    $usertitle=$gettitle['title'];
  } else {
    $usertitle=$usergroup['usertitle'];
  }

//  $timezoneoffset=intval($timezoneoffset); -- not needed

  // check that nothing illegal is in the username/biography/signature
  if ($username!=censortext($username)) {
    eval("standarderror(\"".gettemplate("error_censorfield")."\");");
    exit;
  }
  $signature=censortext($signature);

  if ($illegalusernames!="") {
    $usernames=explode(" ",$illegalusernames);

    while (list($key,$val)=each($usernames)) {
      if ($val!="") {
        if (strstr(strtolower($username),strtolower($val))!="") {
          eval("standarderror(\"".gettemplate("error_usernametaken")."\");");
          exit;
        }
      }
    }
  }
  // check extra profile fields
  $userfields="";
  $userfieldsnames="(userid";
  $profilefields=$DB_site->query("SELECT maxlength,profilefieldid,required,title
                                  FROM profilefield
                                  WHERE editable = 1
                                  ORDER BY displayorder");
  while ($profilefield=$DB_site->fetch_array($profilefields)) {
    $havefields = 1;
    $varname="field$profilefield[profilefieldid]";
    if ($profilefield['required'] and $$varname=="") {
      eval("standarderror(\"".gettemplate("error_requiredfieldmissing")."\");");
      exit;
    }
    $$varname=censortext($$varname);
    // ENTER ANY CUSTOM FIELD VALIDATION HERE!
    //Make sure user didn't try to bypass the maxchars for the fields
    $$varname=substr($$varname,0,$profilefield['maxlength']);
    $userfieldsnames.=",field$profilefield[profilefieldid]";
    $userfields.=",'".vb_escape(htmlspecialchars($$varname))."'";
  }
  $userfieldsnames.=')';

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

  // Birthday Stuff...
  if ($calbirthday == 1)  {
     if ( ($day == -1 and $month != -1) or ($day !=-1 and $month == -1) )   {
        eval("standarderror(\"".gettemplate("error_birthdayfield")."\");");
        exit;
     }
     if (($day == -1) and ($month==-1))  {
        $birthday = 0;
     } else {
        if (($year>1901) and ($year<date("Y")))
           $birthday = $year . "-" . $month . "-" . $day;
        else
           $birthday = "0000" . "-" . $month . "-" . $day;
        if ($showbirthdays) {
          $todayneggmt = date("n-j",time()+(-12-$timeoffset)*3600);
          $todayposgmt = date("n-j",time()+(12-$timeoffset)*3600);
          if ($todayneggmt == "$month-$day" or $todayposgmt == "$month-$day")
            getbirthdays();
        }
     }
  } else {
     $birthday = 0;
  }

  if ($allowchangestyles==1) {
    $newstylefield = "styleid,";
    $newstyleval = "'".intval($newstyleset)."',";
  } else {
    $newstylefield = "";
    $newstyleval = "";
  }

  $avatarid = 0;
  if ($avatarsel) { // Got an avatar, probably want to add an index on minimumposts!
    if ($avatarids = $DB_site->query_first("SELECT avatarid
                                           FROM avatar
                                           WHERE avatarpath = '".vb_escape($avatarsel)."' AND
                                           minimumposts = 0")) {
      $avatarid = $avatarids['avatarid'];
    }
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
  $DB_site->query("INSERT INTO user (userid,username,password,email,".$newstylefield."parentemail,coppauser,homepage,icq,aim,yahoo,signature,adminemail,showemail,invisible,usertitle,joindate,cookieuser,daysprune,lastvisit,lastactivity,usergroupid,timezoneoffset,emailnotification,receivepm,emailonpm,options,birthday,maxposts,startofweek,ipaddress,pmpopup,referrerid,nosessionhash,avatarid) VALUES (NULL,'".vb_escape(htmlspecialchars($username))."','".vb_escape(md5($password))."','".vb_escape(htmlspecialchars($email))."',".$newstyleval."'".vb_escape(htmlspecialchars($parentemail))."','$coppauser','".vb_escape(htmlspecialchars($homepage))."','".vb_escape(htmlspecialchars($icq))."','".vb_escape(htmlspecialchars($aim))."','".vb_escape(htmlspecialchars($yahoo))."','".vb_escape($signature)."','$adminemail','$showemail','$invisible','".vb_escape($usertitle)."','".time()."','$cookieuser','".vb_escape($prunedays)."','".time()."','".time()."','$newusergroupid','".vb_escape($timezoneoffset)."','$emailnotification','$receivepm','$emailonpm','$options','".vb_escape($birthday)."','".vb_escape($umaxposts)."','".vb_escape($startofweek)."','".vb_escape($ipaddress)."','$pmpopup','".vb_escape($testreferrerid['userid'])."','$nosessionhash','$avatarid')");
  $userid=$DB_site->insert_id();

  // insert custom user fields
  $DB_site->query("INSERT INTO userfield $userfieldsnames VALUES ($userid$userfields)");

  // initialise cookies
  if ($cookieuser==1) {
    vbsetcookie("bbuserid",$userid, true, true);
    vbsetcookie("bbpassword",md5($password), true, true);
  }
  $DB_site->query("UPDATE session SET userid=$userid WHERE sessionhash='".vb_escape($session['dbsessionhash'])."'");

  if ($newuseremail!="")
  {
    if ($birthday == 0) {
       $birthday = "N/A";
    } else {
       if (date("Y")>$year and $year>1901 && $year!='0000') {
		  $cformat = str_replace('Y', $year, $calformat1);
          $cformat = str_replace('y', substr($year, 2, 2), $cformat);
          $birthday = @date($cformat,mktime(0,0,0,$month,$day,1992));
       } else {
          $birthday = @date($calformat2,mktime(0,0,0,$month,$day,1992));
       }
       if ($birthday=="") {
         $birthday = "$month-$day-$year";
       }
    }

    $customfields = '';
    if ($havefields)
    {
       $DB_site->data_seek(0,$profilefields);
       while ($profilefield=$DB_site->fetch_array($profilefields))
       {
          $varname="field$profilefield[profilefieldid]";
          $cfield = $$varname;
          $customfields .= "$profilefield[title] : $cfield\n";
       }
    }

    eval("\$message = \"".gettemplate("email_newuser",1,0)."\";");
    eval("\$subject = \"".gettemplate("emailsubject_newuser",1,0)."\";");

    vbmail($newuseremail, $subject, $message);
  }

  // sort out emails and usergroups
  if ($verifyemail) {
		// make random number
		mt_srand ((float) microtime() * 1000000);
    $activateid=mt_rand(0,100000000);

    //save to DB
    $DB_site->query("
		INSERT INTO useractivation
			(useractivationid, userid, dateline, activationid, type, usergroupid)
		VALUES
			(NULL, $userid, ".time().", '$activateid', 0, " . iif($newusergroupid == 4, 4, 2) . ")
	");

    eval("\$message = \"".gettemplate("email_activateaccount",1,0)."\";");
    eval("\$subject = \"".gettemplate("emailsubject_activateaccount",1,0)."\";");

    vbmail($email, $subject, $message);

  }

  $username=htmlspecialchars($username);
  $url=urldecode($url);
  if ($coppauser) {
    $action="coppaform";
  } else {
    if ($verifyemail) {
      eval("standarderror(\"".gettemplate("error_registeremail")."\");");

    } else {
      if ($moderatenewmembers) {
        eval("standarderror(\"".gettemplate("error_moderateuser")."\");");
      } else {
        $url = str_replace("\"", "", $url);
        if (!$url) {
          $url = "index.php?s=$session[sessionhash]";
        }
        eval("standardredirect(\"".gettemplate("redirect_registerthanks")."\",\"".iif(strpos($url,"register")>0,"index.php?s=$session[sessionhash]",$url)."\");");
      }
    }
  }
}

// ############################### start activate form ###############################
if ($a=="ver") {
  // get username and password
  if ($bbuserinfo['userid']==0) {
    $bbuserinfo['username']="";
  }
  eval("dooutput(\"".gettemplate("activateform")."\");");
}

// ############################### start activate ###############################
if ($action=="activate") {
  if ($userinfo=$DB_site->query_first("SELECT userid FROM user WHERE username='".vb_escape(htmlspecialchars($username))."'")) {

		$u=$userinfo['userid'];
		$a="act";
		$i=$activateid;
  } else {
    eval("standarderror(\"".gettemplate("error_wrongusername")."\");");
  }
}
if ($a=="act") {
  // do activate account
  $u = intval($u);

  $userinfo=verifyid("user",$u,1,1);

  if ($userinfo['usergroupid']==3) {
		$user=$DB_site->query_first("SELECT activationid, usergroupid FROM useractivation WHERE userid='$userinfo[userid]' AND type=0");
		if ($i!=$user['activationid']) {
			// send email again
			eval("standarderror(\"".gettemplate("error_invalidactivateid")."\");");
			exit;
		}

		// delete activationid
		$DB_site->query("DELETE FROM useractivation WHERE userid='$userinfo[userid]' AND type=0");


		if ($userinfo['coppauser'] or ($moderatenewmembers and !$userinfo['posts'])) {
			// put user in moderated group
			if ($userinfo['customtitle']==0) {
				$usergroup=$DB_site->query_first("SELECT usertitle FROM usergroup WHERE usergroupid=4");
				if ($usergroup['usertitle']=="") {
					$gettitle=$DB_site->query_first("SELECT title FROM usertitle WHERE minposts<=$userinfo[posts] ORDER BY minposts DESC LIMIT 1");
					$usertitle=$gettitle['title'];
				} else {
					$usertitle=$usergroup['usertitle'];
				}
				$dotitle=", usertitle='".vb_escape($usertitle)."'";
			} else {
				$dotitle = '';
			}
			$username = $userinfo['username'];
			$DB_site->query("UPDATE user SET usergroupid=4$dotitle WHERE userid='$u'");
			eval("standarderror(\"".gettemplate("error_moderateuser")."\");");
		} else {
			// activate account
			if ($userinfo['customtitle']==0) {
				$usergroup=$DB_site->query_first("SELECT usertitle FROM usergroup WHERE usergroupid=$user[usergroupid]");
				if ($usergroup['usertitle']=="") {
					$gettitle=$DB_site->query_first("SELECT title FROM usertitle WHERE minposts<=$userinfo[posts] ORDER BY minposts DESC LIMIT 1");
					$usertitle=$gettitle['title'];
				} else {
					$usertitle=$usergroup['usertitle'];
				}
				$dotitle=", usertitle='".vb_escape($usertitle)."'";
			} else {
				$dotitle = '';
			}
			$DB_site->query("UPDATE user SET usergroupid=$user[usergroupid]$dotitle WHERE userid='$u'");
			$username=$userinfo['username'];
			eval("standarderror(\"".gettemplate("error_activatedthanks")."\");");
		}
  } else {
    if ($userinfo['usergroupid']==4) {
      // In Moderation Queue
      eval("standarderror(\"".gettemplate("error_activate_moderation")."\");");
      exit;
    } else {
      // Already activated
      eval("standarderror(\"".gettemplate("error_activate_wrongusergroup")."\");");
      exit;
    }
  }

}

// ############################### start request activation email ###############################
if ($action=="requestemail") {
  eval("dooutput(\"".gettemplate("activate_requestemail")."\");");
}

if ($_POST['action']=="emailcode") {
  $users=$DB_site->query("SELECT user.userid,user.usergroupid,username,email,password,activationid,dateline, useractivation.usergroupid AS pusergroupid FROM user LEFT JOIN useractivation ON (user.userid=useractivation.userid AND type=0) WHERE email='".vb_escape(htmlspecialchars($email))."'");

  if ($DB_site->num_rows($users)) {

    while ($user=$DB_site->fetch_array($users)) {
			if ($user['usergroupid']==3) { // only do it if the user is in the correct usergroup

				// make random number
				mt_srand ((float) microtime() * 1000000);
				$user['activationid']=mt_rand(0,100000000);
				if ($user['dateline']=="") {
					$DB_site->query("
						INSERT INTO useractivation
							(useractivationid, userid, dateline, activationid, type, usergroupid)
						VALUES
							(NULL, $user[userid], ".time().", '$user[activationid]', 0, " . iif($user['pusergroupid']=='', 2, $user['pusergroupid']) . ")
					");
				} else {
					$DB_site->query("UPDATE useractivation SET dateline='" . time() . "',activationid='$user[activationid]' WHERE userid=$user[userid] AND type=0");
				}

		                if($ourtimenow-$user['dateline'] <= 60) {
		                    eval("standarderror(\"".gettemplate("error_emailflood")."\");");
		                }

				$userid=$user['userid'];
				$username=$user['username'];
				$password=$user['password'];
				$activateid=$user['activationid'];

				eval("\$message = \"".gettemplate("email_activateaccount",1,0)."\";");
				eval("\$subject = \"".gettemplate("emailsubject_activateaccount",1,0)."\";");

				vbmail($user['email'], $subject, $message);
			}
		}
    		$url=urldecode($url);
		if ($url=="") {
			$url="index.php?s=$session[sessionhash]";
		}

    $url = str_replace("\"", "", $url);
    eval("standardredirect(\"".gettemplate("redirect_lostactivatecode")."\",\"\$url\");");
  } else {
    eval("standarderror(\"".gettemplate("error_invalidemail")."\");");
  }

}

// ############################### start coppa form ###############################
if ($action=="coppaform") {
  if ($bbuserinfo['userid']!=0) {

    $bbuserinfo['signature']=nl2br($bbuserinfo['signature']);

    if ($bbuserinfo['showemail']) {
      $bbuserinfo['showemail']="no";
    } else {
      $bbuserinfo['showemail']="yes";
    }

  } else {
    $bbuserinfo['username']="";
    $bbuserinfo['homepage']="http://";

  }

  eval("dooutput(\"".gettemplate("coppaform")."\");");

}

?>