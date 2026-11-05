<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

require("./global.php");

adminlog(iif($userid!=0,"user id = $userid",""));

// ###################### Start makestylecode #######################
function makestylecode ($title,$name,$selvalue=-1,$extra="") {
// returns a combo box containing a list of titles in the $tablename table.
// allows specification of selected value in $selvalue
  global $DB_site,$bgcounter;
  $tablename="style";

  echo "<tr class='".getrowbg()."'>\n<td><p>$title</p></td>\n<td><p><select name=\"$name\" size=\"1\">\n";
  $tableid=$tablename."id";

  $result=$DB_site->query("SELECT title,$tableid FROM $tablename WHERE userselect=1 ORDER BY title");
  if ($tablename == 'style')
  {
    echo "<option value=\"0\">Use forum default</option>\n";
  }
  while ($currow=$DB_site->fetch_array($result)) {

    if ($selvalue==$currow[$tableid]) {
      echo "<option value=\"$currow[$tableid]\" SELECTED>$currow[title]</option>\n";
    } else {
      echo "<option value=\"$currow[$tableid]\">$currow[title]</option>\n";
    }
  } // for

  if ($extra!="") {
    if ($selvalue==-1) {
      echo "<option value=\"-1\" SELECTED>$extra</option>\n";
    } else {
      echo "<option value=\"-1\">$extra</option>\n";
    }
  }

  echo "</select>\n</p></td>\n</tr>\n";

  return 1;

}

cpheader();

if (isset($action)==0) {
  $action="modify";
}

// ###################### Start add #######################
if ($action=="add") {

  echo "Add New User";

  doformheader("user","insert");
  maketableheader("User Profile");
	  makechoosercode("User Group","usergroupid","usergroup",2);
	  makeinputcode("User Name","ausername");
	  makeinputcode("Password","apassword");
	  makeinputcode("Email Address","email");
	  makeinputcode("User Title","usertitle");
	  makeyesnocode("Use Custom Title<br><font size='1'>(This forces the title that you put in the field above to be used)</font>","customtitle",0);
	  makestylecode("Style set","userstyleid",$user['styleid']);
	  makeinputcode("Home Page","homepage","http://www.");
	  makeinputcode("Birthday<br>(Format yyyy-mm-dd)","birthday");
	  maketextareacode("Signature","signature","",8,45);
	  makeinputcode("ICQ Number","icq");
	  makeinputcode("AIM Handle","aim");
	  makeinputcode("Yahoo Messenger Handle","yahoo");
	  makeyesnocode("COPPA user","coppauser",0);
	  makeinputcode("Parent Email Address","parentemail");
  maketableheader("Options");
	  makeyesnocode("Receive mailings from admins","adminemail",1);
	  makeyesnocode("Show email address","showemail",1);
	  makeyesnocode("Invisible on 'Online users' list","invisible",0);
	  makeyesnocode("Receive PMs","receivepm",1);
	  makeyesnocode("Email on PM","emailonpm",0);
	  makeyesnocode("PM Popup","pmpopup",0);
	  makeyesnocode("Show Signatures","showsignatures",1);
	  makeyesnocode("Show Avatars","showavatars",1);
	  makeyesnocode("Show Images","showimages",1);
	  makeyesnocode("Show VBcode","showvbcode",1);
	  makeyesnocode("Use email notification by default","emailnotification",1);
	  makeyesnocode("Remember Username and password","cookieuser",1);
	  makeyesnocode("Browse boards with cookies","nosessionhash",1);
	  makeinputcode("Join Date<br>(Format yyyy-mm-dd, leave blank for today)","joindate");
	  makeinputcode("Default view age<br>'Select threads from last x days'<br>Recommended values: 1, 2, 5, 10, 20, 30, 45, 60, 75, 100, 365, 1000 (ie all). -1 gives default forum selection","daysprune","-1");
	  makeinputcode("Last Visit<br>(Format yyyy-mm-dd hh:mm:ss, leave blank for today)","lastvisit");
	  makeinputcode("Last Activity<br>(Format yyyy-mm-dd hh:mm:ss, leave blank for today)","lastactivity");
	  makeinputcode("Last Post<br>(Format yyyy-mm-dd hh:mm:ss, leave blank for today)","lastpost");
	  makeinputcode("Number of Posts","posts","0");
	  makeinputcode("Time Zone Offset (hours)","timezoneoffset","0");
	  makeinputcode("IP Address","aipaddress",$ipaddress);
  maketableheader("Custom Profile Fields");
  $profilefields=$DB_site->query("SELECT profilefieldid,title FROM profilefield ORDER BY displayorder");
  while ($profilefield=$DB_site->fetch_array($profilefields)) {
    makeinputcode($profilefield['title'],"field".$profilefield['profilefieldid']);
  }

  doformfooter("Save");
}

// ###################### Start insert #######################
if ($_POST['action']=="insert") {

	if (!isset($ausername) or $ausername == '') {
		echo "<p>You did not give this user an username</p>";
		exit;
	}

	if ($exists=$DB_site->query_first("SELECT userid FROM user WHERE username='".vb_escape(htmlspecialchars($ausername))."'")) {
		echo "There is already a ".makelinkcode('user',"user.php?action=edit&amp;userid=$exists[userid]")." named <b>".htmlspecialchars($ausername)."</b>";
		exit;
	}

	if (!isset($apassword) or $apassword == '') {
		echo "You did not give this user a password";
		exit;
	}

  if ($joindate=="") {
    $joindate=time();
  } else {
    $joindate="UNIX_TIMESTAMP('".vb_escape($joindate)."')";
  }
  if ($lastvisit=="") {
    $lastvisit=time();
  } else {
    $lastvisit="UNIX_TIMESTAMP('".vb_escape($lastvisit)."')";
  }
  if ($lastactivity=="") {
    $lastactivity=time();
  } else {
    $lastactivity="UNIX_TIMESTAMP('".vb_escape($lastactivity)."')";
  }
  if ($lastpost=="") {
    $lastpost=time();
  } else {
    $lastpost="UNIX_TIMESTAMP('".vb_escape($lastpost)."')";
  }
  if ($biography=="Location:\r\nOccupation:\r\nInterests:\r\n") {
    $biography="";
  }
  if ($customtitle==0) {
    $usergroup=$DB_site->query_first("SELECT usertitle FROM usergroup WHERE usergroupid='$usergroupid'");
    if ($usergroup['usertitle']=="") {
      $gettitle=$DB_site->query_first("SELECT title FROM usertitle WHERE minposts<=0 ORDER BY minposts DESC LIMIT 1");
      $usertitle=$gettitle['title'];
    } else {
      $usertitle=$usergroup['usertitle'];
    }
  }

/* remove because it did not like 0000 as year-J $temp = explode("-", $birthday);
  if ((!checkdate($temp[1],$temp[2],$temp[0]))||(!ereg("^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$",$birthday)))
    $birthday = 0; */

  $options=iif($showsignatures==1,SHOWSIGNATURES,0);
  $options+=iif($showavatars==1,SHOWAVATARS,0);
  $options+=iif($showimages==1,SHOWIMAGES,0);
  $options+=iif($showvbcode==1,SHOWVBCODE,0);

  $DB_site->query("INSERT INTO user (userid,usergroupid,username,password,email,styleid,parentemail,coppauser,homepage,icq,aim,yahoo,signature,adminemail,showemail,invisible,usertitle,customtitle,joindate,cookieuser,nosessionhash,daysprune,lastvisit,lastactivity,lastpost,posts,timezoneoffset,emailnotification,receivepm,emailonpm,ipaddress,pmpopup,options,birthday) VALUES (NULL,'$usergroupid','".vb_escape(htmlspecialchars($ausername))."','".vb_escape(md5($apassword))."','".vb_escape(htmlspecialchars($email))."','$userstyleid','".vb_escape(htmlspecialchars($parentemail))."','$coppauser','".vb_escape(htmlspecialchars($homepage))."','".vb_escape(htmlspecialchars($icq))."','".vb_escape(htmlspecialchars($aim))."','".vb_escape(htmlspecialchars($yahoo))."','".vb_escape($signature)."','$adminemail','$showemail','$invisible','".vb_escape($usertitle)."','$customtitle',$joindate,'$cookieuser','$nosessionhash','$daysprune',$lastvisit,$lastactivity,$lastpost,'$posts','$timezoneoffset','$emailnotification','$receivepm','$emailonpm','".vb_escape($aipaddress)."','$pmpopup','$options','$birthday')");
  $userid=$DB_site->insert_id();

  $userfields="";
  $userfieldsnames="(userid";
  $profilefields=$DB_site->query("SELECT maxlength,profilefieldid,title
                                  FROM profilefield
                                  ORDER BY displayorder");
  while ($profilefield=$DB_site->fetch_array($profilefields)) {
    $varname="field$profilefield[profilefieldid]";
    $userfieldsnames.=",field$profilefield[profilefieldid]";
    $userfields.=",'".vb_escape(htmlspecialchars($$varname))."'";
  }
  $userfieldsnames.=')';

  $DB_site->query("INSERT INTO userfield $userfieldsnames VALUES ($userid$userfields)");

  $action="modify";

  echo "<p>Record added</p>";

}
// ###################### Start email password #######################

if ($action=="emailpassword") {

	doformheader("../member","emailpassword");
	makehiddencode("email","$email");
	maketableheader("Email password reminder to user");
	makedescription("Click the button below to send the password to $email");
	doformfooter("Email Password",0);

}

// ###################### Start edit #######################
if ($action=="edit") {

  $userid = intval($userid);

  $user=$DB_site->query_first("SELECT user.*,FROM_UNIXTIME(joindate) AS joindate,FROM_UNIXTIME(lastvisit) AS lastvisit,FROM_UNIXTIME(lastactivity) AS lastactivity,FROM_UNIXTIME(lastpost) AS lastpost,
                               avatar.avatarpath,NOT ISNULL(customavatar.avatardata) AS hascustomavatar
                               FROM user
                               LEFT JOIN avatar ON avatar.avatarid=user.avatarid
                               LEFT JOIN customavatar ON customavatar.userid=user.userid
                               WHERE user.userid=$userid");

  if ($user['coppauser']==1) {
    echo "<P><b>THIS IS A COPPA USER. DO NOT CHANGE TO USERGROUP TO REGISTERED USER UNLESS YOU HAVE RECEIVED PARENTAL CONSENT</b></p>";
  }

  if ($user['usergroupid']==3) {
    doformheader("../register","emailcode",0,0);
    makehiddencode("email","$user[email]");
  	doformfooter("Email Activation Codes",0);
  }

  $user['showsignatures'] = iif($user['options']&SHOWSIGNATURES,1,0);
  $user['showavatars'] = iif($user['options']&SHOWAVATARS,1,0);
  $user['showimages'] = iif($user['options']&SHOWIMAGES,1,0);
  $user['showvbcode'] = iif($user['options']&SHOWVBCODE,1,0);

  doformheader("user","doupdate");
  makehiddencode("userid","$userid");

  maketableheader("Useful Links");
  makedescription("<table width='90%' border='0' align='center'><tr valign='top'><td>
  <li>".makelinkcode("Send email to $user[username]","mailto:$user[email]")."</li>
  <li>".makelinkcode("Send a private message to $user[username]","../private.php?s=$session[sessionhash]&amp;action=newmessage&amp;userid=$user[userid]",1)."</li>
  <li>".makelinkcode("Find posts by $user[username]","../search.php?s=$session[sessionhash]&amp;action=finduser&amp;userid=$user[userid]",1)."</li>
  <li>".makelinkcode("View the profile for $user[username]","../member.php?s=$session[sessionhash]&amp;action=getinfo&amp;userid=$userid",1)."</li>
  </td><td>
  <li>".makelinkcode("Edit forum access for $user[username]","user.php?s=$session[sessionhash]&amp;action=editaccess&amp;userid=$user[userid]")."</li>
  <li>".makelinkcode("View IPs for $user[username]","user.php?s=$session[sessionhash]&amp;action=doips&amp;username=".urlencode($user['username']))."</li>
  <li>".makelinkcode("Email this user their password","user.php?s=$session[sessionhash]&amp;action=emailpassword&amp;email=$user[email]")."</li>
  <li>".makelinkcode("Remove User","user.php?s=$session[sessionhash]&amp;action=remove&amp;userid=$user[userid]")."</li>
  </td></tr></table>");
  restarttable();

  maketableheader("Edit User - $user[username] (userid: $user[userid])");
	  makechoosercode("User Group","usergroupid","usergroup",$user['usergroupid']);
	  makeinputcode("User Name","ausername",$user['username'],0);
	  makeinputcode("Password<br>Leave blank unless you want to change it","apassword");
	  makeinputcode("Email Address","email",$user['email'],0);
	  makeinputcode("User Title","usertitle",$user['usertitle']);
	  makeyesnocode("Use Custom Title<br><font size='1'>(This forces the title that you put in the field above to be used)</font>","customtitle",$user['customtitle']);
	  makestylecode("Style set","userstyleid",$user['styleid']);
	  makeinputcode("Home Page","homepage",$user['homepage'],0);
	  makeinputcode("Birthday<br>(Format yyyy-mm-dd)","birthday",$user['birthday'],0);
	  maketextareacode("Signature","signature",$user['signature'],8,45);
	  makeinputcode("ICQ Number","icq",$user['icq'],0);
	  makeinputcode("AIM Handle","aim",$user['aim'],0);
	  makeinputcode("Yahoo Messenger Handle","yahoo",$user['yahoo'],0);
	  makeyesnocode("COPPA user","coppauser",$user['coppauser']);
	  makeinputcode("Parent Email Address","parentemail",$user['parentemail'],0);
	  makeinputcode("Number of Posts","posts",$user['posts']);
  maketableheader("Options");
	  makeyesnocode("Receive mailings from admins","adminemail",$user['adminemail']);
	  makeyesnocode("Show email","showemail",$user['showemail']);
	  makeyesnocode("Invisible on 'Online users' list","invisible",$user['invisible']);
	  makeyesnocode("Receive PMs","receivepm",$user['receivepm']);
	  makeyesnocode("Email on PM","emailonpm",$user['emailonpm']);
	  makeyesnocode("PM Popup","pmpopup",$user['pmpopup']);
	  makeyesnocode("Show Signatures","showsignatures",$user['showsignatures']);
	  makeyesnocode("Show Avatars","showavatars",$user['showavatars']);
	  makeyesnocode("Show Images","showimages",$user['showimages']);
	  makeyesnocode("Show VBcode","showvbcode",$user['showvbcode']);
	  makeyesnocode("Use email notification by default","emailnotification",$user['emailnotification']);
	  makeyesnocode("Remember Username and password","cookieuser",$user['cookieuser']);
	  makeyesnocode("Browse boards with cookies","nosessionhash",$user['nosessionhash']);
	  makeinputcode("Join Date<br>(Format yyyy-mm-dd, leave blank for today)","joindate",$user['joindate']);
	  makeinputcode("Default view age<br>'Select threads from last x days'<br>Recommended values: 1, 2, 5, 10, 20, 30, 45, 60, 75, 100, 365, 1000 (ie all). -1 gives default forum selection","daysprune",$user['daysprune']);
	  makeinputcode("Last Visit<br>(Format yyyy-mm-dd hh:mm:ss, leave blank for today)","lastvisit",$user['lastvisit']);
	  makeinputcode("Last Activity<br>(Format yyyy-mm-dd hh:mm:ss, leave blank for today)","lastactivity",$user['lastactivity']);
	  makeinputcode("Last Post<br>(Format yyyy-mm-dd hh:mm:ss, leave blank for today)","lastpost",$user['lastpost']);
	  makeinputcode("Time Zone Offset (hours)","timezoneoffset",$user['timezoneoffset']);
	  makeinputcode("IP Address","aipaddress",$user['ipaddress']);
  maketableheader("User Avatar");
	  if ($user['avatarid']!=0) {
	     $avatarurl= "../" . $user['avatarpath'];
	  } else {
	    if ($user['hascustomavatar']) {
	      $avatarurl="../avatar.php?s=$session[sessionhash]&amp;userid=$user[userid]";
	    } else {
	      $avatarurl="";
	    }
	  }
	  echo "<tr class='".getrowbg()."'><td>Avatar</td><td nowrap>";
	  if ($avatarurl!="") {
	    echo "<img src=\"$avatarurl\">&nbsp;&nbsp;&nbsp;";
	  }
	  echo "<input type=\"submit\" name=\"modifyavatar\" value=\"Change Avatar\">";
	  echo "<input type=\"hidden\" name=\"userid\" value=\"$user[userid]\">";
	  echo "</td></tr>";

  maketableheader("Custom Profile Fields");
  $userfield=$DB_site->query_first("SELECT * FROM userfield WHERE userid=$userid");

  $profilefields=$DB_site->query("SELECT profilefieldid,title FROM profilefield ORDER BY displayorder");
  while ($profilefield=$DB_site->fetch_array($profilefields)) {
    $varname="field$profilefield[profilefieldid]";
    makeinputcode($profilefield['title'],"field".$profilefield['profilefieldid'],$userfield[$varname], false);
  }

  doformfooter("Save Changes");

}

// ###################### Start do update #######################
if ($_POST['action']=="doupdate") {

	if (!isset($ausername) or $ausername == '') {
		echo "<p>You did not give this user an username</p>";
		exit;
	}

	if ($exists=$DB_site->query_first("SELECT userid
					FROM user
					WHERE username='".vb_escape(htmlspecialchars($ausername))."'
						AND userid <> $userid")) {
		echo "There is already a ".makelinkcode('user',"user.php?action=edit&amp;userid=$exists[userid]")." named <b>".htmlspecialchars($ausername)."</b>";
		exit;
	}

  // check that not removing last admin
  $countadmin=$DB_site->query_first("SELECT COUNT(*) AS users FROM user,usergroup WHERE user.usergroupid=usergroup.usergroupid AND usergroup.cancontrolpanel=1 AND user.userid<>$userid");
  $getperms=$DB_site->query_first("SELECT cancontrolpanel FROM usergroup WHERE usergroupid=$usergroupid");
  if ($countadmin['users']==0 and $getperms['cancontrolpanel']!=1) {
    echo "<p>You are about to edit the last user with control panel access so that they do not have control panel access. This would lock you out of the control panel, so you cannot proceed.</p></body></html>";
    exit;
  }

  if ($joindate=="") {
    $joindate=time();
  } else {
    $joindate="UNIX_TIMESTAMP('".vb_escape($joindate)."')";
  }
  if ($lastvisit=="") {
    $lastvisit=time();
  } else {
    $lastvisit="UNIX_TIMESTAMP('".vb_escape($lastvisit)."')";
  }
  if ($lastactivity=="") {
    $lastactivity=time();
  } else {
    $lastactivity="UNIX_TIMESTAMP('".vb_escape($lastactivity)."')";
  }
  if ($lastpost=="") {
    $lastpost=time();
  } else {
    $lastpost="UNIX_TIMESTAMP('".vb_escape($lastpost)."')";
  }

  if ($posts=="") {
    $posts=0;
  }

/* removed reason see action=insert  $temp = explode("-", $birthday);
  if ((!checkdate($temp[1],$temp[2],$temp[0]))||(!ereg("^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$",$birthday)))
    $birthday = 0; */

	$pwinclude="";
  if ($apassword!="") {
    $pwdinclude=",password='".vb_escape(md5($apassword))."'";
  }

  if ($customtitle==0) {
    $usergroup=$DB_site->query_first("SELECT usertitle FROM usergroup WHERE usergroupid=$usergroupid");
    if ($usergroup['usertitle']=="") {
      $gettitle=$DB_site->query_first("SELECT title FROM usertitle WHERE minposts<=$posts+1 ORDER BY minposts DESC LIMIT 1");
      $usertitle=$gettitle['title'];
    } else {
      $usertitle=$usergroup['usertitle'];
    }
  }

  $options=iif($showsignatures==1,SHOWSIGNATURES,0);
  $options+=iif($showavatars==1,SHOWAVATARS,0);
  $options+=iif($showimages==1,SHOWIMAGES,0);
  $options+=iif($showvbcode==1,SHOWVBCODE,0);

  $DB_site->query("UPDATE user SET birthday='$birthday',options='$options',usergroupid='$usergroupid',username='".vb_escape(htmlspecialchars($ausername))."'$pwdinclude,email='".vb_escape(htmlspecialchars($email))."',styleid='$userstyleid',parentemail='".vb_escape(htmlspecialchars($parentemail))."',coppauser=$coppauser,homepage='".vb_escape(htmlspecialchars($homepage))."',icq='".vb_escape(htmlspecialchars($icq))."',aim='".vb_escape(htmlspecialchars($aim))."',yahoo='".vb_escape(htmlspecialchars($yahoo))."',signature='".vb_escape($signature)."',adminemail=$adminemail,showemail=$showemail,invisible=$invisible,usertitle='".vb_escape($usertitle)."',customtitle=$customtitle,joindate=$joindate,cookieuser=$cookieuser,nosessionhash=$nosessionhash,daysprune='$daysprune',lastvisit=$lastvisit,lastactivity=$lastactivity,lastpost=$lastpost,posts='$posts',timezoneoffset='$timezoneoffset',emailnotification=$emailnotification,receivepm='$receivepm',emailonpm='$emailonpm',ipaddress='".vb_escape($aipaddress)."',pmpopup=IF(pmpopup=2 AND $pmpopup=1,pmpopup,'$pmpopup') WHERE userid=$userid");

  $profilefields=$DB_site->query("SELECT profilefieldid,title FROM profilefield ORDER BY displayorder");
  while ($profilefield=$DB_site->fetch_array($profilefields)) {
    $varname="field$profilefield[profilefieldid]";
    $sql.=",field$profilefield[profilefieldid]='".vb_escape(htmlspecialchars($$varname))."'";
  }
  $DB_site->query("UPDATE userfield SET userid=$userid$sql WHERE userid=$userid");

  echo "<p>Record updated!</p>";

  if ($modifyavatar) {
    $action = "avatar";
  } else {
    $action = "modify";
  }
}

// ###################### Start Edit Access #######################

if ($action=="editaccess") {
  $user=$DB_site->query_first("SELECT username FROM user WHERE userid='$userid'");

  $accesslist=$DB_site->query("SELECT * FROM access WHERE userid='$userid'");
  while ($access=$DB_site->fetch_array($accesslist)) {
    $accessarray["$access[forumid]"] = $access;
  }

  doformheader("user","updateaccess");
  makehiddencode("userid","$userid");

  maketableheader("User Forum Access for <i>$user[username]</i>","",0);
  echo "<tr class='firstalt'><td colspan=2><p>Here you may edit forum access on a user-by-user basis.
  <BR>Selecting \"yes\" will allow this user access to the forum. Selecting \"no\" will deny this user access to the forum. Any changes made to this user's account will override the default permission settings in their usergroup.

  <br><br>Selecting \"default\" will revert this user to the default permissions settings for their usergroup.

  <BR><br>(Please note that the permission inheritance system still works here)

  <br><br>(Ensure that you have access masks enabled before attempting to use these!)</p></td></tr>\n";

  maketableheader("Forum List");

  $forumlist=$DB_site->query("SELECT * FROM forum");
  while($forum=$DB_site->fetch_array($forumlist)) {
    echo "<tr class='secondalt'><td nowrap><P>$forum[title]</p></td><td width=100%><p>";
    if ( is_array($accessarray["$forum[forumid]"]) ) {
      if ($accessarray["$forum[forumid]"]['accessmask']==0) {
        $sel = 0;
      } else if ($accessarray["$forum[forumid]"]['accessmask']==1) {
        $sel = 1;
      } else {
        $sel = -1;
      }
    } else {
      $sel = -1;
    }
    echo "<input type=\"radio\" name=\"accessupdate[".$forum['forumid']."]\" value=\"1\"".iif($sel==1,"checked","")."> Yes <input type=\"radio\" name=\"accessupdate[".$forum['forumid']."]\" value=\"0\"".iif($sel==0,"checked","")."> No <input type=\"radio\" name=\"accessupdate[".$forum['forumid']."]\" value=\"-1\" ".iif($sel==-1,"checked","")."> Default <input type=\"hidden\" name=\"oldcache[".$forum['forumid']."]\" value=\"$sel\">";
    echo "</p></td></tr>\n";
  }

  doformfooter("Save Changes");
}

// ###################### Start Update Access #######################

if ($_POST['action']=="updateaccess") {

  while ( list($forumid,$val)=each($accessupdate) ) {
    if ($oldcache["$forumid"]==$val) {
      continue;
    }

    if ($oldcache["$forumid"]=="-1") {
      $DB_site->query("INSERT IGNORE INTO access (userid,forumid,accessmask) VALUES ('$userid','$forumid','$val')");
    } else if ($oldcache["$forumid"]!="-1" and $val=="-1") {
      $DB_site->query("DELETE FROM access WHERE userid='$userid' AND forumid='$forumid'");
    } else {
      $DB_site->query("UPDATE access SET accessmask='$val' WHERE userid='$userid' AND forumid='$forumid'");
    }
  }

  echo "<p>User access updated successfully</p>";

  $action="modify";
}

// ###################### Start Remove #######################

if ($action=="remove") {

	doformheader("user","kill");
	makehiddencode("userid",$userid);
	maketableheader("Confirm deletion");
	makedescription("Are you sure you want to delete this user? All the posts made by this user will be set to guest");
	doformfooter("Yes","",2,"No");

	// RE: http://www.vbulletin.com/forum/showthread.php?s=&amp;threadid=16287
	echo "<p>If you want to prune all the user's messages first, please click <a href=\"thread.php?s=$session[sessionhash]&amp;action=pruneuser&amp;forumid=-1&amp;userid=$userid&amp;confirm=1\">here</a>.</p>";

}


// ###################### Start Kill #######################

if ($_POST['action']=="kill") {

  $user=$DB_site->query_first("SELECT username FROM user WHERE userid='$userid'");
  $DB_site->query("UPDATE post SET username='".vb_escape($user['username'])."',userid=0 WHERE userid='$userid'");
  $DB_site->query("DELETE FROM user WHERE userid='$userid'");
  $DB_site->query("DELETE FROM userfield WHERE userid='$userid'");
  $DB_site->query("DELETE FROM access WHERE userid='$userid'");
  $DB_site->query("DELETE FROM calendar_events WHERE userid='$userid'");
  $DB_site->query("DELETE FROM customavatar WHERE userid='$userid'");
  $DB_site->query("DELETE FROM moderator WHERE userid='$userid'");
  $DB_site->query("DELETE FROM privatemessage WHERE userid='$userid'");
  $DB_site->query("DELETE FROM subscribeforum WHERE userid='$userid'");
  $DB_site->query("DELETE FROM subscribethread WHERE userid='$userid'");
  $DB_site->query("DELETE FROM session WHERE userid='$userid'");
  $DB_site->query("DELETE FROM useractivation WHERE userid='$userid'");
  $DB_site->query("UPDATE user SET referrerid = 0 WHERE userid = '$userid'");

  echo "<p>User deleted successfully</p>";

  $action="modify";
}

// ###################### Start modify Avatar ################
if ($action=="avatar") {
    $bbuserinfo=getuserinfo($userid);
    $avatarchecked[$bbuserinfo['avatarid']]="checked";
    $nouseavatarchecked="";
    if (!$avatarinfo=$DB_site->query_first("SELECT * FROM customavatar WHERE userid=$userid")) {
      // no custom avatar exists
      if ($bbuserinfo['avatarid']==0) {
        // must have no avatar selected
        $nouseavatarchecked="checked";
        $avatarchecked[0]="";
      }
    }
    if (intval($startpage)<1)
	    $startpage = 1;
	  if (intval($perpage)<1)
    $perpage = 25;
    $avatarcount = $DB_site->query_first("SELECT COUNT(*) AS count
	                                      FROM avatar");
    $totalavatars = $avatarcount['count'];
    if (($startpage-1)*$perpage > $totalavatars) {
	     if ((($totalavatars / $perpage) - ((int) ($totalavatars / $perpage))) == 0)
	       $startpage = $totalavatars / $perpage;
	     else
	       $startpage = (int) ($totalavatars / $perpage) + 1;
	  }
	  $limitlower=($startpage-1)*$perpage+1;
	  $limitupper=($startpage)*$perpage;
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
                              ORDER BY title
                              LIMIT ".($limitlower-1).",$perpage");
    $avatarcount = 0;
    echo "<form action=\"user.php\" method=\"post\">Perpage:<input type=\"text\" name=\"perpage\" value=\"$perpage\" size=5> <input type=\"submit\" value=\"GO\">";
	echo "<input type=\"hidden\" name=\"userid\" value=\"$userid\"><input type=\"hidden\" value=\"avatar\" name=\"action\"></form>";
    echo "<FORM ENCTYPE=\"multipart/form-data\" ACTION=\"user.php\" METHOD=\"POST\"><input type=\"hidden\" name=\"sessionhash\" value=\"$session[sessionhash]\">";
    echo "<table bgcolor=\"#51485F\" border=0 cellspacing=0 cellpadding=0><tr><td><table border=0 cellpadding=6 cellspacing=1>";
    echo "<tr bgcolor=\"#51485F\"><td colspan=5 align=\"center\"><font color=\"#BCB6CD\"><b>Current Avatars</b></font></td></tr>";
	while ($avatar=$DB_site->fetch_array($avatars)) {
	  $avatarid=$avatar['avatarid'];
	  $avatar['avatarpath'] = iif(substr($avatar['avatarpath'],0,7)!="http://" and substr($avatar['avatarpath'],0,1)!="/","../","").$avatar['avatarpath'];
	  if ($avatarcount==0)
	     echo "<tr bgcolor=\"#DDDDDD\">";
	  echo "<td bgcolor=\"#DDDDDD\" valign=\"bottom\" align=\"center\"><input type=\"radio\" name=\"avatarid\" value=\"$avatar[avatarid]\" $avatarchecked[$avatarid]>";
	  echo "<img src=\"$avatar[avatarpath]\"><br>$avatar[title]</td>";
	  $avatarcount++;
	  if ($avatarcount == 5) {
	    echo '</tr>';
	    $avatarcount = 0;
	  }
	}
	if ($avatarcount!=0) {
	    while ($avatarcount !=5) {
	      echo '<td bgcolor="#DDDDDD">&nbsp;</td>';
	      $avatarcount++;
	    }
	    echo '</tr>';
    }
	if ((($totalavatars / $perpage) - ((int) ($totalavatars / $perpage))) == 0)
	  $numpages = $totalavatars / $perpage;
	else
	  $numpages = (int) ($totalavatars / $perpage) + 1;
	if ($startpage == 1) {
	  $starticon = 0;
	  $endicon = $perpage - 1;
	} else {
	  $starticon = ($startpage - 1) * $perpage;
	  $endicon = ( $perpage * $startpage ) - 1 ;
	}
	if ($numpages > 1) {
	  for ($x = 1; $x <= $numpages; $x++) {
	    if ($x == $startpage)
	      $pagelinks .= "<b> <font size=+1 color=\"#BCB6CD\">$x</font> </b>";
	    else
	      $pagelinks .= " <a href=\"user.php?startpage=$x&amp;perpage=$perpage&amp;action=avatar&amp;userid=$userid\"><font color=\"#BCB6CD\">$x</font></a> ";
	  }
	}
	if ($startpage != $numpages) {
	  $nextstart = $startpage + 1;
	  $nextpage = " <a href='user.php?startpage=$nextstart&amp;perpage=$perpage&amp;action=avatar&amp;userid=$userid'><font color=\"#BCB6CD\">></font></a>";
	  $eicon = $endicon + 1;
	} else
	  $eicon = $totalavatars;
	if ($startpage!=1) {
	  $prevstart = $startpage - 1;
	  $prevpage = "<a href='user.php?startpage=$prevstart&amp;perpage=$perpage&amp;action=avatar&amp;userid=$userid'><font color=\"#BCB6CD\"><</font></a> ";
	}
	$sicon = $starticon +  1;
	echo "<tr bgcolor=\"#51485F\"><td align=\"center\" colspan=5><font color=\"#BCB6CD\">";
	echo "Showing Avatars icons $sicon to $eicon of $totalavatars</font><br>";
    echo "<font color=\"#BCB6CD\">$prevpage $pagelinks $nextpage</font></td></tr>";
 	echo '</table></td></tr></table>';

    $bbuserinfo['avatarurl']=getavatarurl($bbuserinfo['userid']);
    if ($bbuserinfo['avatarurl']=="" or $bbuserinfo['avatarid']!=0) {
      $bbuserinfo['avatarurl']="../images/clear.gif";
    } else {
      $bbuserinfo['avatarurl'] = "../" . $bbuserinfo['avatarurl'];
    }
    echo "<table border=0 cellspacing=0 cellpadding=0><tr><td>";
	echo "<b>Keep avatar for $bbuserinfo[username]?</b>&nbsp;";
    echo "<INPUT TYPE=\"RADIO\" NAME=\"avatarid\" VALUE=\"-1\" $nouseavatarchecked> no<br><br>";
    echo "<hr><b>Add custom avatar for $bbuserinfo[username]?</b>&nbsp;<INPUT TYPE=\"RADIO\" NAME=\"avatarid\" VALUE=\"0\" $avatarchecked[0]> yes<br>";
    echo "<img src=\"$bbuserinfo[avatarurl]\"><br>";
    echo "<br><B>You can enter an URL of the avatar:</B><br>Note: this will be stored locally on the server";
    echo "<br><INPUT TYPE=\"text\" NAME=\"avatarurl\" value=\"http://www.\"><br>";
    echo "<br><B>Or you can upload an avatar from your computer:</B>";
	echo "<br><INPUT TYPE=\"file\" NAME=\"avatarfile\">";
	echo "<br><br><INPUT TYPE=\"HIDDEN\" NAME=\"action\" VALUE=\"updateavatar\"><INPUT TYPE=\"HIDDEN\" NAME=\"userid\" VALUE=\"$userid\">";
	echo "<INPUT TYPE=\"SUBMIT\" NAME=\"Submit\" VALUE=\"Submit Modifications\">";
	echo "</td></tr></table></FORM>";
}

// ###################### Start Update Avatar ################
if ($_POST['action']=="updateavatar") {

  $bbuserinfo=getuserinfo($userid);
  $useavatar=iif($avatarid==-1,0,1);
  if ($_FILES['avatarfile']) {
    $avatarfile = $_FILES['avatarfile']['tmp_name'];
    $avatarfile_name = $_FILES['avatarfile']['name'];
    $avatarfile_size = $_FILES['avatarfile']['size'];
  }
  if ($useavatar) {
    if ($avatarid==0) {
      // using custom avatar
      $filename="";
      // check for new uploaded file or for new url
      $avatarurl=trim($avatarurl);
      if ($avatarurl!="" and $avatarurl!="http://www.") {
        // get file from url
        $filenum=@fopen($avatarurl,"rb");
        if ($filenum!=0) {
          $contents="";
          while (!@feof($filenum)) {
            $contents.=@fread($filenum,1024); //filesize($filename));
          }
          @fclose($filenum);

          $avatarfile_name = "vba".substr(time(),-4);
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
        } else {
          // invalid address error
          eval("standarderror(\"".gettemplate("error_avatarbadurl")."\");");
          exit;
        }
      } else {
        if ($safeupload) {
          $filename="";
	       $path = "$tmppath/$avatarfile_name";
          if (function_exists("is_uploaded_file") and is_uploaded_file($avatarfile) and move_uploaded_file($avatarfile, "$path")) {
            if (file_exists($path)) {
              if (filesize($path)!=$avatarfile_size) {
                // security error
                eval("standarderror(\"".gettemplate("error_avataruploaderror")."\");");
              } ####### END if (filesize($path)!=$avatarfile_size)

              $filename=$path;
            } else {
              // bad upload
              $avatarid=0;
              $filename="";
            } ####### END if (file_exists($path))
          } ####### END if (function_exists("is_uploaded_file") and is_uploaded_file($avatarfile) [...]
        } else {
          if (file_exists($avatarfile)) {
            if (filesize($avatarfile)!=$avatarfile_size) {
              eval("standarderror(\"".gettemplate("error_avataruploaderror")."\");");
              // security error
              exit;
            }
            $filename=$avatarfile;
          } else {
            // bad upload
            $avatarid=0;
            $filename="";
          }
        } ####### END if ($safeupload)
      }
      if ($filename!="") {
        // check valid image

        $validfile = false;
        // Verify that file is playing nice
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
          if ($imginfo[2]!=1 and $imginfo[2]!=2) {
            eval("standarderror(\"".gettemplate("error_avatarnotmimage")."\");");
            // not gif or jpg
            exit;
          }
        }

        // read file
        $filesize=@filesize($filename);

        $filenum=@fopen($filename,"rb");
        $filestuff=@fread($filenum,$filesize);
        @fclose($filenum);

        @unlink($filename);

        if ($avexists=$DB_site->query_first("SELECT userid FROM customavatar WHERE userid=$bbuserinfo[userid]")) {
		  $DB_site->query("UPDATE customavatar SET dateline='".time()."',avatardata='".vb_escape($filestuff)."' WHERE userid=$bbuserinfo[userid]");
		} else {
		  $DB_site->query("INSERT INTO customavatar (userid,avatardata,dateline) VALUES ($bbuserinfo[userid],'".vb_escape($filestuff)."','".time()."')");
        }
      }
    } else {
      //$avatarid=verifyid("avatar",$avatarid);
      //$avatarinfo=$DB_site->query_first("SELECT minimumposts FROM avatar WHERE avatarid=$avatarid");
      //if ($avatarinfo[minimumposts]>$bbuserinfo[posts]) {
      //  eval("standarderror(\"".gettemplate("error_avatarmoreposts")."\");");
        // not enough posts error
      //  exit;
      //}
      $DB_site->query("DELETE FROM customavatar WHERE userid=$bbuserinfo[userid]");
    }
  } else {
    $avatarid=0;
    $DB_site->query("DELETE FROM customavatar WHERE userid=$bbuserinfo[userid]");
  }

  $DB_site->query("UPDATE user SET avatarid='".vb_escape($avatarid)."',usergroupid='$bbuserinfo[usergroupid]' WHERE userid='$bbuserinfo[userid]'");
  echo "<p>Avatar Updated!</p>";
  $action = "modify";
}
// ###################### Start modify #######################
if ($action=="modify") {

  echo "<p><b>Quick Search:</b></p>";
  echo "<ul>\n";
  echo "<li><a href=\"user.php?s=$session[sessionhash]&amp;action=find\">List all users</a></li>\n";
  echo "<li><a href=\"user.php?s=$session[sessionhash]&amp;action=find&amp;orderby=posts&amp;direction=DESC&amp;limitnumber=30\">List top posters</a></li>\n";
  echo "<li><a href=\"user.php?s=$session[sessionhash]&amp;action=find&amp;lastvisitafter=".(time()-86400)."&amp;orderby=lastvisit&amp;direction=DESC\">List visitors in the last 24 hours</a></li>\n";
  echo "<li><a href=\"user.php?s=$session[sessionhash]&amp;action=find&amp;orderby=joindate&amp;direction=DESC&amp;limitnumber=30\">List most recent registrations</a></li>\n";
  echo "<li><a href=\"user.php?s=$session[sessionhash]&amp;action=moderate\">List users awaiting moderation and COPPA users awaiting moderation</a></li>\n";
  echo "<li><a href=\"user.php?s=$session[sessionhash]&amp;action=find&amp;coppauser=yes\">List all COPPA users</a></li>\n";
  echo "</ul>";

  echo "<p><b>Advanced Search:</b></p>";

  doformheader("user","find");
  maketableheader("Find users where:</b> (If you leave a field blank it will be ignored)","",0);
  makeinputcode("User Name contains","ausername");
  makeinputcode("and password is equal to","apassword");
  makechoosercode("and usergroup is","usergroupid","usergroup",-1,"Any");
  makeinputcode("and email contains","email");
  makeinputcode("and parent's email contains","parentemail");
  makeinputcode("and is coppa user (yes, no, blank for don't mind)","coppauser");
  makeinputcode("and homepage contains","homepage");
  makeinputcode("and ICQ Number contains","icq");
  makeinputcode("and AIM Handle contains","aim");
  makeinputcode("and Yahoo Messenger Handle contains","yahoo");
  makeinputcode("and Signature contains","signature");
  makeinputcode("and User Title contains","usertitle");
  makeinputcode("and Join Date is after<br>(Format yyyy-mm-dd)","joindateafter");
  makeinputcode("and Join Date is before<br>(Format yyyy-mm-dd)","joindatebefore");
  makeinputcode("and Last Visit is after<br>(Format yyyy-mm-dd hh:mm:ss)","lastvisitafter");
  makeinputcode("and Last Visit is before<br>(Format yyyy-mm-dd hh:mm:ss)","lastvisitbefore");
  makeinputcode("and Last Post is after<br>(Format yyyy-mm-dd hh:mm:ss)","lastpostafter");
  makeinputcode("and Last Post is before<br>(Format yyyy-mm-dd hh:mm:ss)","lastpostbefore");
  makeinputcode("and Birthday is after<br>(Format yyyy-mm-dd)","birthdayafter");
  makeinputcode("and Birthday is before<br>(Format yyyy-mm-dd)","birthdaybefore");
  makeinputcode("and Number of Posts is greater than","postslower");
  makeinputcode("and Number of Posts is less than","postsupper");
  makeinputcode("and IP Address contains","aipaddress");

  $profilefields=$DB_site->query("SELECT profilefieldid,title FROM profilefield ORDER BY displayorder");
  while ($profilefield=$DB_site->fetch_array($profilefields)) {
    makeinputcode("and $profilefield[title] contains","field".$profilefield['profilefieldid']);
  }

  maketableheader("Sorting & Count Options");

  ?>
  <tr class='<?php echo getrowbg(); ?>'><td><p>Order by</p></td><td><p>
  <select name="orderby">
  <option selected>username</option>
  <option>email</option>
  <option>joindate</option>
  <option>lastvisit</option>
  <option>lastpost</option>
  <option>posts</option>
  <option>birthday</option>
  </select>
  <select name="direction">
  <option value="">in ascending order</option>
  <option value="DESC">in descending order</option>
  </select>
  </p></td></tr>
  <?php

  makeinputcode("Show this many entries at most:","limitnumber","300");
  makeinputcode("starting at row:","limitstart","1");

  maketableheader("Display Options");

  makeyesnocode("Display username","displayusername",1);
  makeyesnocode("Display options","displayoptions",1);
  makeyesnocode("Display user group","displayusergroup",0);
  makeyesnocode("Display email address","displayemail",1);
  makeyesnocode("Display parent's email","displayparentemail",0);
  makeyesnocode("Display whether a coppa user","displaycoppauser",0);
  makeyesnocode("Display homepage url","displayhomepage",0);
  makeyesnocode("Display ICQ UIN","displayicq",0);
  makeyesnocode("Display AIM ID","displayaim",0);
  makeyesnocode("Display Yahoo ID","displayyahoo",0);
  makeyesnocode("Display signature","displaysignature",0);
  makeyesnocode("Display usertitle","displayusertitle",0);
  makeyesnocode("Display joindate","displayjoindate",1);
  makeyesnocode("Display lastvisit","displaylastvisit",1);
  makeyesnocode("Display lastpost","displaylastpost",0);
  makeyesnocode("Display posts","displayposts",1);
  makeyesnocode("Display IP Address","displayipaddress",0);
  makeyesnocode("Display Birthday","displaybirthday",0);

  $profilefields=$DB_site->query("SELECT profilefieldid,title FROM profilefield ORDER BY displayorder");
  while ($profilefield=$DB_site->fetch_array($profilefields)) {
    makeyesnocode("Display $profilefield[title]","display".$profilefield['profilefieldid'],0);
  }

  doformfooter("Find");

}

// ###################### Start find #######################
if ($action=="find") {

  if ($displayusername == 0 and $displayoptions == 0 and $displayusergroup == 0 and $displayemail == 0 and $displayparentemail == 0 and $displaycoppauser == 0 and $displayhomepage == 0 and $displayicq == 0 and $displayaim == 0 and $displayyahoo == 0 and $displaybiography == 0 and $displaysignature == 0 and $displayusertitle == 0 and $displayjoindate == 0 and$displaylastvisit == 0 and $displaylastpost == 0 and $displayposts == 0) {
    $displayusername=1;
    $displayoptions=1;
    $displayemail=1;
    $displayjoindate=1;
    $displaylastvisit=1;
    $displayposts=1;
  }

  $condition="1=1";
  if ($ausername!="") {
    $condition.=" AND INSTR(LCASE(username),'".vb_escape(strtolower(htmlspecialchars($ausername)))."')>0";
  }
  if ($usergroupid!=-1 and $usergroupid!="") {
    $condition.=" AND usergroupid=$usergroupid";
  }
  if ($email!="") {
    $condition.=" AND INSTR(LCASE(email),'".vb_escape(strtolower($email))."')>0";
  }
  if ($apassword!="") {
    $condition.=" AND password='".vb_escape(md5($apassword))."'";
  }
  if ($parentemail!="") {
    $condition.=" AND INSTR(LCASE(parentemail),'".vb_escape(strtolower($parentemail))."')>0";
  }
  $coppauser=strtolower($coppauser);
  if ($coppauser=="yes") {
    $condition.=" AND coppauser=1";
  }
  if ($coppauser=="no") {
    $condition.=" AND coppauser=0";
  }
  if ($homepage!="") {
    $condition.=" AND INSTR(LCASE(homepage),'".vb_escape(strtolower($homepage))."')>0";
  }
  if ($icq!="") {
    $condition.=" AND INSTR(LCASE(icq),'".vb_escape(strtolower($icq))."')>0";
  }
  if ($aim!="") {
    $condition.=" AND INSTR(LCASE(aim),'".vb_escape(strtolower($aim))."')>0";
  }
  if ($yahoo!="") {
    $condition.=" AND INSTR(LCASE(yahoo),'".vb_escape(strtolower($yahoo))."')>0";
  }
  if ($signature!="") {
    $condition.=" AND INSTR(LCASE(signature),'".vb_escape(strtolower($signature))."')>0";
  }
  if ($usertitle!="") {
    $condition.=" AND INSTR(LCASE(usertitle),'".vb_escape(strtolower($usertitle))."')>0";
  }
  if ($joindateafter!="") {
    $condition.=" AND joindate>UNIX_TIMESTAMP('".vb_escape($joindateafter)."')";
  }
  if ($joindatebefore!="") {
    $condition.=" AND joindate<UNIX_TIMESTAMP('".vb_escape($joindatebefore)."')";
  }
  if ($birthdayafter)
      $condition.=" AND birthday>'".vb_escape($birthdayafter)."'";
  if ($birthdaybefore)
      $condition.=" AND birthday< '".vb_escape($birthdaybefore)."'";
  if ($lastvisitafter!="") {
    if (strval($lastvisitafter)==strval(intval($lastvisitafter))) {
      $condition.=" AND lastvisit>'".vb_escape($lastvisitafter)."'";
    } else {
      $condition.=" AND lastvisit>UNIX_TIMESTAMP('".vb_escape($lastvisitafter)."')";
    }
  }
  if ($lastvisitbefore!="") {
    $condition.=" AND lastvisit<UNIX_TIMESTAMP('".vb_escape($lastvisitbefore)."')";
  }
  if ($lastpostafter!="") {
    $condition.=" AND lastpost>UNIX_TIMESTAMP('".vb_escape($lastpostafter)."')";
  }
  if ($lastpostbefore!="") {
    $condition.=" AND lastpost<UNIX_TIMESTAMP('".vb_escape($lastpostbefore)."')";
  }
  if ($postslower!="") {
    $condition.=" AND posts>'".intval($postslower)."'";
  }
  if ($postsupper!="") {
    $condition.=" AND posts<'".intval($postsupper)."'";
  }
  if ($aipaddress!="") {
    $condition.=" AND INSTR(LCASE(ipaddress),'".vb_escape(strtolower($aipaddress))."')>0";
  }

  if ($orderby=="") {
    $orderby="username";
  }
  if ($limitstart=="") {
    $limitstart=0;
  } else {
    $limitstart--;
  }
  if ($limitnumber=="") {
    $limitnumber=99999999;
  }
  $profilefields=$DB_site->query("SELECT profilefieldid,title FROM profilefield ORDER BY displayorder");
  while ($profilefield=$DB_site->fetch_array($profilefields)) {
    $varname="field$profilefield[profilefieldid]";
    if ($$varname) {
      $condition.=" AND INSTR(LCASE(userfield.field$profilefield[profilefieldid]),'".vb_escape(strtolower($$varname))."')>0";
    }
  }

  $users=$DB_site->query("SELECT user.userid,username,usergroupid,birthday,email,parentemail,coppauser,homepage,icq,aim,yahoo,signature,usertitle,FROM_UNIXTIME(joindate) AS joindate,FROM_UNIXTIME(lastvisit) AS lastvisit,FROM_UNIXTIME(lastpost) AS lastpost,posts,ipaddress,userfield.* FROM user,userfield WHERE $condition AND userfield.userid=user.userid ORDER BY $orderby $direction LIMIT $limitstart,$limitnumber");

  $countusers=$DB_site->query_first("SELECT COUNT(*) AS users FROM user,userfield WHERE $condition AND userfield.userid=user.userid");

  if ($countusers['users']==1) {
		//show a user if there is just one found
		$user=$DB_site->fetch_array($users);
		echo "<p>Only one user found matching criteria! Redirecting to that user's page...</p>";
    cpredirect("user.php?s=$session[sessionhash]&amp;action=edit&amp;userid=$user[userid]");
		exit;
	} else if ($countusers['users']==0) {
		// no users found!
		echo "<p>No users found matching those criteria.</p>";
	} else {

		$limitfinish=$limitstart+$limitnumber;


		echo "<p>Showing records ".($limitstart+1)." to ".iif($limitfinish>$countusers['users'],$countusers['users'],$limitfinish)." of $countusers[users]. Click username to view forum profile.</p>";
		//echo "<table border=1>";
		doformheader("","");

		echo "<tr class='tblhead'>";

		if ($displayusername==1) {
			echo "<td><p><b><span class='tblhead'>Name</span></b></p></td>";
		}
		if ($displayoptions==1) {
			echo "<td><p><b><span class='tblhead'>Options</span></b></p></td>";
		}
		if ($displayusergroup==1) {
			echo "<td><p><b><span class='tblhead'>User Group</span></b></p></td>";
		}
		if ($displayemail==1) {
			echo "<td><p><b><span class='tblhead'>Email</span></b></p></td>";
		}
		if ($displayparentemail==1) {
			echo "<td><p><b><span class='tblhead'>Parent's Email address</span></b></p></td>";
		}
		if ($displaycoppauser==1) {
			echo "<td><p><b><span class='tblhead'>Coppa User?</span></b></p></td>";
		}
		if ($displayhomepage==1) {
			echo "<td><p><b><span class='tblhead'>Homepage URL</span></b></p></td>";
		}
		if ($displayicq==1) {
			echo "<td><p><b><span class='tblhead'>ICQ UIN</span></b></p></td>";
		}
		if ($displayaim==1) {
			echo "<td><p><b><span class='tblhead'>AIM ID</span></b></p></td>";
		}
		if ($displayyahoo==1) {
			echo "<td><p><b><span class='tblhead'>Yahoo ID</span></b></p></td>";
		}
		if ($displaysignature==1) {
			echo "<td><p><b><span class='tblhead'>Signature</span></b></p></td>";
		}
		if ($displayusertitle==1) {
			echo "<td><p><b><span class='tblhead'>User Title</span></b></p></td>";
		}
		if ($displayjoindate==1) {
			echo "<td><p><b><span class='tblhead'>Join Date</span></b></p></td>";
		}
		if ($displaylastvisit==1) {
			echo "<td><p><b><span class='tblhead'>Last Visit</span></b></p></td>";
		}
		if ($displaylastpost==1) {
			echo "<td><p><b><span class='tblhead'>Last Post</span></b></p></td>";
		}
		if ($displayposts==1) {
			echo "<td><p><b><span class='tblhead'>Posts</span></b></p></td>";
		}
		if ($displayipaddress==1) {
			echo "<td><p><b><span class='tblhead'>IP Address</span></b></p></td>";
		}
		if ($displaybirthday==1) {
			echo "<td><p><b><span class='tblhead'>Birthday</span></b></p></td>";
		}
		$profilefields=$DB_site->query("SELECT profilefieldid,title FROM profilefield ORDER BY displayorder");
		while ($profilefield=$DB_site->fetch_array($profilefields)) {
			$varname="display$profilefield[profilefieldid]";
			if ($$varname) {
				echo "<td><p><b><span class='tblhead'>$profilefield[title]</span></b></p></td>";
			}
		}

		echo "</tr>\n";

		while ($user=$DB_site->fetch_array($users)) {

			echo "<tr class='".getrowbg()."'>";

			if ($displayusername==1) {
				echo "<td><p><a href='../member.php?s=$session[sessionhash]&amp;action=getinfo&amp;userid=$user[userid]' target='_blank'>$user[username]</a>&nbsp;</p></td>";
			}
			if ($displayoptions==1) {
				echo "<td><p>".
					makelinkcode("edit","user.php?s=$session[sessionhash]&amp;action=edit&amp;userid=$user[userid]").
					makelinkcode("email password","user.php?s=$session[sessionhash]&amp;action=emailpassword&amp;email=$user[email]").
					makelinkcode("remove","user.php?s=$session[sessionhash]&amp;action=remove&amp;userid=$user[userid]").
					makelinkcode("edit access masks","user.php?s=$session[sessionhash]&amp;action=editaccess&amp;userid=$user[userid]")."
					</p></td>";
			}
			if ($displayusergroup==1) {
				$getusergroup=$DB_site->query_first("SELECT title FROM usergroup WHERE usergroupid=$user[usergroupid]");
				echo "<td><p>$getusergroup[title]&nbsp;</p></td>";
			}
			if ($displayemail==1) {
				echo "<td><p><a href='mailto:$user[email]'>$user[email]</a>&nbsp;</p></td>";
			}
			if ($displayparentemail==1) {
				echo "<td><p><a href='mailto:$user[parentemail]'>$user[parentemail]</a>&nbsp;</p></td>";
			}
			if ($displaycoppauser==1) {
				echo "<td><p>".iif($user['coppauser']==1,"Yes","No")."</p></td>";
			}
			if ($displayhomepage==1) {
				if ($user['homepage']=="") {
					$user['homepage']="&nbsp;";
				}
				echo "<td><p><a href='$user[homepage]' target=_new>$user[homepage]</a>&nbsp;</p></td>";
			}
			if ($displayicq==1) {
				if ($user['icq']=="") {
					$user['icq']="&nbsp;";
				}
				echo "<td><p>$user[icq]</p></td>";
			}
			if ($displayaim==1) {
				if ($user['aim']=="") {
					$user['aim']="&nbsp;";
				}
				echo "<td><p>$user[aim]</p></td>";
			}
			if ($displayyahoo==1) {
				if ($user['yahoo']=="") {
					$user['yahoo']="&nbsp;";
				}
				echo "<td><p>$user[yahoo]</p></td>";
			}
			if ($displaysignature==1) {
				if ($user['signature']=="") {
					$user['signature']="&nbsp;";
				}
				echo "<td><p>".nl2br($user['signature'])."</p></td>";
			}
			if ($displayusertitle==1) {
				if ($user['usertitle']=="") {
					$user['usertitle']="&nbsp;";
				}
				echo "<td><p>$user[usertitle]</p></td>";
			}
			if ($displayjoindate==1) {
				echo "<td><p>$user[joindate]</p></td>";
			}
			if ($displaylastvisit==1) {
				echo "<td><p>$user[lastvisit]</p></td>";
			}
			if ($displaylastpost==1) {
				echo "<td><p>$user[lastpost]</p></td>";
			}
			if ($displayposts==1) {
				echo "<td><p>$user[posts]</p></td>";
			}
			if ($displayipaddress==1) {
				echo "<td><p>".iif($user['ipaddress']!="","$user[ipaddress] (".gethostbyaddr($user['ipaddress']).")","&nbsp;")."</p></td>";
			}
			if ($displaybirthday==1)
				echo "<td><p>$user[birthday]</p></td>";
			$profilefields=$DB_site->query("SELECT profilefieldid,title FROM profilefield ORDER BY displayorder");
			while ($profilefield=$DB_site->fetch_array($profilefields)) {
				$varname="display$profilefield[profilefieldid]";
				if ($$varname) {
					$varname="field$profilefield[profilefieldid]";
					echo "<td><p>$user[$varname]&nbsp;</p></td>";
				}
			}

			echo "</tr>\n";

		}
		echo "</table></td></tr></table></form>";

		if ($limitnumber!=99999999 AND $limitfinish<$countusers['users']) {
			doformheader("user","find");
			makehiddencode("ausername",$ausername);
			makehiddencode("apassword",$apassword);
			makehiddencode("usergroupid",$usergroupid);
			makehiddencode("email",$email);
			makehiddencode("parentemail",$parentemail);
			makehiddencode("coppauser",$coppauser);
			makehiddencode("homepage",$homepage);
			makehiddencode("icq",$icq);
			makehiddencode("aim",$aim);
			makehiddencode("yahoo",$yahoo);
			makehiddencode("signature",$signature);
			makehiddencode("usertitle",$usertitle);
			makehiddencode("joindateafter",$joindateafter);
			makehiddencode("joindatebefore",$joindatebefore);
			makehiddencode("lastvisitafter",$lastvisitafter);
			makehiddencode("lastvisitbefore",$lastvisitbefore);
			makehiddencode("lastpostafter",$lastpostafter);
			makehiddencode("lastpostbefore",$lastpostbefore);
			makehiddencode("postslower",$postslower);
			makehiddencode("postsupper",$postsupper);
			makehiddencode("aipaddress",$aipaddress);
			makehiddencode("orderby",$orderby);
			makehiddencode("direction",$direction);
			makehiddencode("limitstart",$limitstart+$limitnumber+1);
			makehiddencode("limitnumber",$limitnumber);
			makehiddencode("displayusername",$displayusername);
			makehiddencode("displayoptions",$displayoptions);
			makehiddencode("displayusergroup",$displayusergroup);
			makehiddencode("displayemail",$displayemail);
			makehiddencode("displayparentemail",$displayparentemail);
			makehiddencode("displaycoppauser",$displaycoppauser);
			makehiddencode("displayhomepage",$displayhomepage);
			makehiddencode("displayicq",$displayicq);
			makehiddencode("displayaim",$displayaim);
			makehiddencode("displayyahoo",$displayyahoo);
			makehiddencode("displaybiography",$displaybiography);
			makehiddencode("displaysignature",$displaysignature);
			makehiddencode("displayusertitle",$displayusertitle);
			makehiddencode("displayjoindate",$displayjoindate);
			makehiddencode("displaylastvisit",$displaylastvisit);
			makehiddencode("displaylastpost",$displaylastpost);
			makehiddencode("displayposts",$displayposts);
			makehiddencode("displayipaddress",$displayipaddress);
			makehiddencode("displaybirthday",$displaybithday);

			$profilefields=$DB_site->query("SELECT profilefieldid,title FROM profilefield ORDER BY displayorder");
			while ($profilefield=$DB_site->fetch_array($profilefields)) {
				$varname="display$profilefield[profilefieldid]";
				makehiddencode($varname,$$varname);
				$varname="field$profilefield[profilefieldid]";
				makehiddencode($varname,$$varname);
			}

			echo "<input type=submit value=\"Show Next Page\">";
		}
	}
}

// ###################### Start moderate + coppa #######################
if ($action=="moderate") {

  /*
  // delete coppa entries older than 30 days
  $datecut=time()-(30*60*60*24);
  $DB_site->query("DELETE FROM user WHERE joindate<$datecut AND usergroupid=4");
  */

  $users=$DB_site->query("SELECT userid,username,email FROM user WHERE usergroupid=4 ORDER BY username");

  if ($DB_site->num_rows($users)==0) {
    echo "<p>None awaiting validation</p>";
  } else {
    doformheader("user","domoderate");

    echo "<tr class='tblhead'><td><p><b><span class='tblhead'>Validate?</span></b></p></td><td><p><b><span class='tblhead'>Name</span></b></p></td><td><p><b><span class='tblhead'>Email</span></b></p></td><td><p><b><span class='tblhead'>Options</span></b></p></td></tr>\n";
    while ($user=$DB_site->fetch_array($users)) {

      echo "<tr class='".getrowbg()."'><td><p> Yes<input type=\"radio\" checked name=\"validate[$user[userid]]\" value=\"1\"> No <input type=\"radio\" name=\"validate[$user[userid]]\" value=\"0\"></p></td>";
      echo "<td><p>$user[username]</p></td><td><p><a href=\"mailto:$user[email]\">$user[email]</a></p></td><td><p><a href=\"user.php?s=$session[sessionhash]&amp;action=edit&amp;userid=$user[userid]\" target=_blank>View profile</a></p></td></tr>\n";

    }

	maketableheader("Send this email to validated users:","",1,4);
    echo "<tr class='".getrowbg()."'><td>Subject:</td><td colspan='3'><input type=text name=subject size=50 value=\"Your account at $bbtitle has been activated\"></td></tr>\n";

    $gettemp=$DB_site->query_first("SELECT template FROM template WHERE title='email_validated' AND (templatesetid=-1 OR templatesetid=1) ORDER BY templatesetid DESC"); //kludgy!!
    $template=$gettemp['template'];

    echo "<tr class='".getrowbg()."'><td>Body:</td><td colspan='3'><textarea rows=10 cols=50 name=email>".htmlspecialchars($template)."</textarea></td></tr>\n";

    doformfooter("Process Users","Reset",4);
  }
}

// ###################### Start do moderate and coppa #######################
if ($_POST['action']=="domoderate") {

  while (list($key,$val)=each($validate)) {
    if ($val==1) {

      $user=$DB_site->query_first("SELECT username,email FROM user WHERE userid=$key");
      $username=unhtmlspecialchars($user['username']);

      eval("\$message = \"".ereg_replace("\"","\\\"",$email)."\";");
      eval("\$subject = \"".ereg_replace("\"","\\\"",$subject)."\";");

      vbmail($user['email'], $subject, $message);

      $DB_site->query("UPDATE user SET usergroupid=2 WHERE userid=$key");
    }
  }

  echo "<p>Accounts validated and users notified</p>";

}

// ############################# doipaddress() #########################
function doipaddress($ipaddress,$prevuserid,$depth=1) {
  global $DB_site,$session;

  echo "<ul>";

  $depth--;

  $users=$DB_site->query("SELECT DISTINCT user.userid,user.username FROM post,user WHERE user.userid=post.userid AND post.ipaddress LIKE '$ipaddress%' AND user.userid<>$prevuserid ORDER BY user.username");
  while ($user=$DB_site->fetch_array($users)) {

    echo "<li>$user[username]".
			makelinkcode("edit","user.php?s=$session[sessionhash]&amp;action=edit&amp;userid=$user[userid]").
			makelinkcode("find posts by user","../search.php?s=$session[sessionhash]&amp;action=finduser&amp;userid=$user[userid]").
			makelinkcode("find more ips for user","user.php?s=$session[sessionhash]&amp;action=doips&amp;userid=$user[userid]").
			"</li>\n";

    if ($depth>0) {
      douserid($user['userid'],$ipaddress,$depth);
    }
  }

  echo "</ul>";
}

// ############################# douserid() #########################
function douserid($userid,$previpaddress,$depth=2) {
  global $DB_site,$session;

  $depth--;

  $getusername=$DB_site->query_first("SELECT username FROM user WHERE userid=$userid");
  echo "<ul>";

  $ips=$DB_site->query("SELECT DISTINCT ipaddress FROM post WHERE userid=$userid AND ipaddress<>'' AND ipaddress<>'$previpaddress' ORDER BY ipaddress");
  while ($ip=$DB_site->fetch_array($ips)) {

    echo "<li>$ip[ipaddress] (".gethostbyaddr($ip['ipaddress']).") ".makelinkcode("find more users for this ip","user.php?s=$session[sessionhash]&amp;action=doips&amp;ipaddress=$ip[ipaddress]")."</li>\n";

    if ($depth>0 and $ip['ipaddress']!=$previpaddress) {
      doipaddress($ip['ipaddress'],$userid,$depth);
    }

  }

  echo "</ul>";

}

// ############################# start do ips #########################
if ($action=="doips") {
  @set_time_limit(1200);

  if (!isset($depth) or $depth==0 or $depth=="") {
    $depth=1;
  }
  if (!isset($_POST['depth']) and $depth>2) {
    $depth=1;
  }

  if ($username!="") {
    $getuserid=$DB_site->query_first("SELECT userid FROM user WHERE username='".vb_escape($username)."'");
    $userid=$getuserid['userid'];
  }

  if ($ipaddress!="") {
    doipaddress($ipaddress,0,$depth);
  }

  if ($userid!="") {
    douserid($userid,0,$depth);
  }

  doformheader("user","doips");
  maketableheader("Begin New Search");
  makeinputcode("IP Address:<br>(Or partial address)","ipaddress");
  makeinputcode("User name","username");
  makeinputcode("Depth to search","depth",$depth);
  doformfooter("Find");

}

if ($action=="referrers") {
  echo "<p>Please input the dates that you would like the report run for or leave them blank for a report covering everything</p>";
  doformheader("user","showreferrers");
  maketableheader("User Referrals");
  makeinputcode("Start Date<br>(Format: yyyy-mm-dd hh:mm:ss)","startdate",date("Y-m-d",time()-24*60*60*31));
  makeinputcode("End Date<br>(Format: yyyy-mm-dd hh:mm:ss)","enddate",date("Y-m-d",time()));

  doformfooter("Display");
}

if ($_POST['action']=="showreferrers") {

  if ($startdate and $enddate) {
     $datequery =  "AND users.joindate>=UNIX_TIMESTAMP('".vb_escape($startdate)."') ";
     $datequery .= "AND users.joindate<=UNIX_TIMESTAMP('".vb_escape($enddate)."')";
  }

  $users = $DB_site->query("SELECT COUNT(*) AS count, user.username, user.userid FROM user AS users
                   LEFT JOIN user ON (users.referrerid = user.userid)
                   WHERE users.referrerid <> 0
                   $datequery
                   GROUP BY users.referrerid
                   ORDER BY count DESC");
  if ($DB_site->num_rows($users)==0) {
      echo "<p>No Referrals for that time period</p>";
  } else {
    echo "<p><b>User Referrals</b>";
    if ($datequery) {
       echo " - from $startdate to $enddate";
    } else {
       echo " - covering all time";
    }
    echo "</p><p>Click the user's name to see information on the people that they have referred</p>";
    echo "<table border=2 cellspacing=0 cellpadding=4>";
    echo "<tr><td align=\"center\"><b>Username</b></td><td align=\"center\"><b>Referral Count</b></td></tr>";
    while ($user=$DB_site->fetch_array($users)) {
       echo "<tr><td align=\"center\"><a href=\"user.php?s=$session[sessionhash]&amp;action=showreferrals&amp;referrerid=$user[userid]&amp;startdate=$startdate&amp;enddate=$enddate\">$user[username]</a>";
       echo "</td><td align=\"center\">$user[count]</td></tr>";
    }
    echo "</table>";
  }
}

if ($action=='showreferrals') {
   if ($startdate and $enddate) {
     $datequery =  "AND joindate>=UNIX_TIMESTAMP('".vb_escape($startdate)."') ";
     $datequery .= "AND joindate<=UNIX_TIMESTAMP('".vb_escape($enddate)."')";
   }
   $username=$DB_site->query_first("SELECT username FROM user WHERE userid = '$referrerid'");
   $users = $DB_site->query("SELECT username, posts, userid, joindate, lastvisit, email
                             FROM user
                             WHERE referrerid = '$referrerid'
                             $datequery
                             ORDER BY joindate");
   echo "<p><b>Referrals for <a href=\"user.php?s=$session[sessionhash]&amp;action=edit&amp;userid=$referrerid\">$username[username]</a></b>";
   if ($datequery) {
     echo " - from $startdate to $enddate";
   } else {
     echo " - covering all time";
   }
   echo "</p><table border=2 cellspacing=0 cellpadding=4>";
   echo "<tr><td align=\"center\"><b>Username</b></td><td align=\"center\"><b>Posts</b></td><td align=\"center\"><b>Email</b></td><td align=\"center\"><b>Joindate</b></td><td align=\"center\"><b>Lastvisit</b></td></tr>";
   while ($user=$DB_site->fetch_array($users)) {
     $user['joindate'] = vbdate($dateformat,$user['joindate']) . " " . vbdate($timeformat,$user['joindate']);
     $user['lastvisit'] = vbdate($dateformat,$user['lastvisit']) . " " . vbdate($timeformat,$user['lastvisit']);
     $profile = "<a href=\"user.php?s=$session[sessionhash]&amp;action=edit&amp;userid=$user[userid]\">$user[username]</a>";
     echo "<tr><td align=\"center\">$profile</td><td align=\"center\">$user[posts]</td>";
     echo "<td align=\"center\">$user[email]</td><td align=\"center\">$user[joindate]</td>";
     echo "<td align=\"center\">$user[lastvisit]</td></tr>";
   }
    echo "</table>";
}

// ############################# start kill pms #########################
if ($action=="killpms") {

	doformheader("user","dokillpms");
	makehiddencode("userid",$userid);
	makehiddencode("username",$username);
	maketableheader("Delete User's Private Messages");
	makedescription("Are you <b>sure</b> you want to delete all private messages belonging to $username?");
	doformfooter("Yes, delete them",0,2,"Oops, no!");

}

// ############################# start kill pms #########################
if ($_POST['action']=="dokillpms") {

	$DB_site->query("DELETE FROM privatemessage WHERE userid='$userid'");
	echo "<p>Private messages belonging to $username deleted</p>\n";
	$action = "pmstats";

}

// ############################# start PM stats #########################
if ($action=="pmstats") {

	$pms = $DB_site->query("
		SELECT COUNT(*) AS total, userid
		FROM privatemessage
		GROUP BY userid
		ORDER BY total DESC
	");

	echo "<p>This page allows you to see the number of members who have a particular number of stored private messages.</p>\n";

	doformheader("user","viewpmstats");
	maketableheader("Stored Private Message Statistics","",0,3);

	echo "<tr class='".getrowbg()."' align='center'>
		<td><font size='1'><b>Number of messages</b></font></td>
		<td><font size='1'><b>Number of users</b></font></td>
		<td><font size='1'><b>Controls</b></font></td>
	</tr>\n";

	$groups=array();
	while ($pm = $DB_site->fetch_array($pms)) {
		$groups[$pm['total']]['total']++;
		$groups[$pm['total']]['ids'] .= "$pm[userid] ";
	}
	while (list($key,$val)=each($groups)) {
		$val['ids'] = str_replace(" ", ",", trim($val['ids']));
		echo "<tr class='".getrowbg()."' align='center'>
			<td>$key".iif($pmquota,"/$pmquota","")."</td>
			<td>$val[total]</td>
			<td>".makelinkcode("list users with $key messages","user.php?s=$session[sessionhash]&amp;action=pmuserstats&amp;pms=$key&amp;ids=$val[ids]")."</td>
		</tr>\n";
	}

	dotablefooter();

}

// ############################# start PM stats #########################
if ($action=="pmuserstats") {

	$users = $DB_site->query("SELECT * FROM user WHERE userid IN($ids)");

	doformheader("user","");

	maketableheader("Users with $pms private messages stored","",1,3);
	echo "<tr class='".getrowbg()."'>
		<td><font size='1'><b>Name</b></font></td>
		<td><font size='1'><b>Last Visit</b></font></td>
		<td><font size='1'><b>Options</b></font></td>
	</tr>\n";

	while($user = $DB_site->fetch_array($users)) {
		echo "<tr class='".getrowbg()."'>
		<td><a href=\"../member.php?s=$session[sessionhash]&amp;action=getinfo&amp;userid=$user[userid]\" target=\"_blank\">$user[username]</a></td>
		<td>".vbdate("H:i d-M-Y",$user['lastvisit'])."</td>
		<td>".
		makelinkcode("edit user","user.php?s=$session[sessionhash]&amp;action=edit&amp;userid=$user[userid]").
		makelinkcode("email","mailto:$user[email]").
		makelinkcode("send pm","../private.php?s=$session[sessionhash]&amp;action=newmessage&amp;userid=$user[userid]",1).
		makelinkcode("delete pms","user.php?s=$session[sessionhash]&amp;action=killpms&amp;userid=$user[userid]&amp;ids=$ids&amp;username=$user[username]")."
		</td>
		</tr>\n";
	}

	dotablefooter();

}

// ############################# do prune users (step 2) #########################
if ($action=="prune_updateposts" && $s==$session['dbsessionhash']) {

	$userids = readtext('ids');

	$users = $DB_site->query("SELECT userid,username FROM user WHERE userid IN($userids) LIMIT $startat,50");
	if ($DB_site->num_rows($users)) {
		while ($user = $DB_site->fetch_array($users)) {
			echo "<p>Updating threads and posts for user: <i>$user[username]</i> ....\n";
			flush();
			$DB_site->query("UPDATE thread SET postuserid=0, postusername='".vb_escape($user['username'])."' WHERE postuserid=$user[userid]");
			$DB_site->query("UPDATE post SET userid=0, username='".vb_escape($user['username'])."' WHERE userid=$user[userid]");
			echo "<b>done</b>.</p>\n";
			flush();
		}
		$startat += 50;
		cpredirect("user.php?s=$session[dbsessionhash]&amp;action=prune_updateposts&amp;startat=$startat",0);
		exit;
	} else {
		echo "<p>Deleting users...</p>";
		$DB_site->query("DELETE FROM userfield WHERE userid IN ($userids)");
		$DB_site->query("DELETE FROM user WHERE userid IN ($userids)");
		echo "<p><b>Threads and posts updated. User pruning complete.</b></p>";
		cpredirect("user.php?s=$session[sessionhash]&amp;action=prune",1);
		exit;
	}

}

// ############################# do prune/move users (step 1) #########################
if ($_POST['action']=="dopruneusers") {

	if (is_array($userid)) {
		unset($userids);
		while (list($key,$val)=each($userid)) {
			if ($val==1 && $key!=$bbuserinfo['userid']) {
				$userids .= "$key ";
			}
		}
		$userids = str_replace(" ",",",trim($userids));

		if ($dowhat=="delete") {
				echo "<p>Deleting forum subscriptions ....\n";
				flush();
			$DB_site->query("DELETE FROM subscribeforum WHERE userid IN($userids)");
				echo "okay.</p><p>Deleting thread subscriptions ....\n";
				flush();
			$DB_site->query("DELETE FROM subscribethread WHERE userid IN($userids)");
				echo "okay.</p><p>Deleting calendar events ....\n";
				flush();
			$DB_site->query("DELETE FROM calendar_events WHERE userid IN($userids)");
				echo "okay.</p><p>Deleting custom avatars ....\n";
				flush();
			$DB_site->query("DELETE FROM customavatar WHERE userid IN($userids)");
				echo "okay.</p><p>Deleting forum access ....\n";
				flush();
			$DB_site->query("DELETE FROM access WHERE userid IN($userids)");
				echo "okay.</p><p>Deleting moderators ....\n";
				flush();
			$DB_site->query("DELETE FROM moderator WHERE userid IN($userids)");
				echo "okay.</p><p>Deleting private messages ....\n";
				flush();
			$DB_site->query("DELETE FROM privatemessage WHERE userid IN($userids)");
				echo "okay.</p><p>Deleting any activations ....\n";
				flush();
			$DB_site->query("DELETE FROM useractivation WHERE userid IN($userids)");
				echo "okay.</p><p>Deleting user sessions ....\n";
				flush();
			$DB_site->query("DELETE FROM session WHERE userid IN($userids)");
				echo "okay.</p><p>Now proceding to update threads &amp; posts ....</p>\n";
				flush();

			storetext('ids',$userids);
			cpredirect("user.php?s=$session[dbsessionhash]&amp;action=prune_updateposts&amp;startat=0",1);
			exit;

		} elseif ($dowhat=="move") {
			$group = $DB_site->query_first("SELECT title FROM usergroup WHERE usergroupid='".intval($movegroup)."'");
			echo "<p>Moving users to usergroup: <i>$group[title]</i> ....\n";
			flush();
			$DB_site->query("UPDATE user SET usergroupid='".intval($movegroup)."' WHERE userid IN($userids)");
			echo "okay.</p><p><b>Users moved to <i>$group[title]</i> usergroup.</b></p>";
			cpredirect("user.php?s=$session[sessionhash]&amp;action=prune",1);
		} else {
			echo "<p><b>Error: no action specified! Please choose delete or move</b></p>";
			$action = "pruneusers";
		}

		if (is_array($query)) {
			while (list(,$val)=each($query)) {
				echo "<pre>$val</pre>\n";
			}
		}

	} else {
		echo "<p><b>Error: You did not select any users to prune/move!</b></p>";
		$action = "pruneusers";
	}

}

// ############################# start list users for pruning #########################
if ($action=="pruneusers") {

	$usergroupid = intval($usergroupid);
	$daysprune = intval($daysprune);
	$minposts = intval($minposts);
	unset($sqlconds);

	if ($usergroupid!=-1) {
		$sqlconds = "WHERE user.usergroupid = $usergroupid ";
	}
	if ($daysprune) {
		$sqlconds .= iif($sqlconds=="","WHERE","AND")." lastvisit < ".(time() - $daysprune*86400)." ";
	}
	if ($joindate) {
		$sqlconds .= iif($sqlconds=="","WHERE","AND")." joindate < UNIX_TIMESTAMP('$joindate') ";
	}
	if ($minposts) {
		$sqlconds .= iif($sqlconds=="","WHERE","AND")." posts < $minposts ";
	}

	if ($sqlconds!="") {

		$query = "SELECT DISTINCT user.userid,username,email,posts,lastvisit,joindate,user.usergroupid,moderator.moderatorid,usergroup.title
		FROM user
		LEFT JOIN moderator ON(moderator.userid=user.userid)
		LEFT JOIN usergroup ON(usergroup.usergroupid=user.usergroupid)
		$sqlconds GROUP BY user.userid ORDER BY $order";
		//echo "<pre>$query</pre>";
		$users = $DB_site->query($query);

		if ($numusers = $DB_site->num_rows($users)) {
			?>
			<script language="javascript">
			function CheckAll() {
				for (var i=0;i<document.form.elements.length;i++) {
					var e = document.form.elements[i];
					if ((e.name != 'allbox') && (e.type=='checkbox')) {
						e.checked = document.form.allbox.checked;
					}
				}
			}
			function oops() {
				alert("You may not delete/move this user - The user is either:\n\n1) An Administrator\n2) A Super-Moderator\n3) A Moderator\n4) You!");
			}
			</script>
			<?php

			$groups = $DB_site->query("
				SELECT usergroupid,title FROM usergroup
				WHERE usergroupid NOT IN(1,3,4,5,6)
				ORDER BY title");
			while ($group = $DB_site->fetch_array($groups)) {
				$groupslist .= "<option value='$group[usergroupid]'>$group[title]</option>";
			}

			doformheader("user","dopruneusers",0,1,"form");
				makehiddencode("usergroupid",$usergroupid);
				makehiddencode("daysprune",$daysprune);
				makehiddencode("minposts",$minposts);
				makehiddencode("joindate",$joindate);
				makehiddencode("order",$order);
			maketableheader("Found $numusers users matching your search conditions","",1,7);
			echo "<tr class='".getrowbg()."' align='center'>
				<td><font size='1'><b>ID</b></font></td>
				<td><font size='1'><b>Username</b></font></td>
				<td><font size='1'><b>Email</b></font></td>
				<td><font size='1'><b>Posts</b></font></td>
				<td><font size='1'><b>Last Visit</b></font></td>
				<td><font size='1'><b>Join Date</b></font></td>
				<td><input type='checkbox' name='allbox' onclick='CheckAll()' title='select/deselect all' checked></td>
			</tr>\n";
			while ($user = $DB_site->fetch_array($users)) {
				echo "<tr class='".getrowbg()."'>
					<td>$user[userid]</td>
					<td><a href='user.php?s=$session[sessionhash]&amp;action=edit&amp;userid=$user[userid]' target='_blank'>$user[username]</a><br><font size='1'>$user[title]".iif($user['moderatorid'],", Moderator","")."</font></td>
					<td><a href='mailto:$user[email]'>$user[email]</a></td>
					<td>".number_format($user['posts'])."</td>
					<td>".vbdate($dateformat,$user['lastvisit'])."</td>\n
					<td>".vbdate($dateformat,$user['joindate'])."</td>\n";
					if ($user['userid']==$bbuserinfo['userid'] || $user['usergroupid']==6 || $user['usergroupid']==5 || $user['moderatorid']!=0) {
						echo "<td><input type='button' value=' ! ' onclick='oops()' style='font-weight:bold'></td></tr>\n";
					} else {
						echo "<td><input type='checkbox' name='userid[$user[userid]]' value='1' checked></td></tr>\n";
					}
			}
			echo "<tr class='".getrowbg()."'>
				<td colspan='7' align='center'><font size='1'><b>Do what with selected users?
					<input type='radio' name='dowhat' value='delete'>Delete
					<input type='radio' name='dowhat' value='move'>Move to usergroup:
					<select name='movegroup'>$groupslist</select>
				</b></font></td>
			</tr>\n";
			doformfooter("Delete/move all selected users","Select all listed users",7);
			echo "<p>Note: clicking the 'delete' button will remove the selected users completely.
			This action is <b>not</b> un-doable... please be sure!</p>
			<p>Users marked with the <b>[ ! ]</b> button are not editable using this system.</p>";
		} else {
			echo "<p><b>Error: No users matched your search conditions.</b></p>";
			$action = "prune";
		}
	} else {
		echo "<p><b>Error: Please enter some search conditions.</b></p>";
		$action = "prune";
	}
}

// ############################# start prune users #########################
if ($action=="prune") {

	echo "<p>This system allows you to mass-move users to a different usergroup,  or prune away inactive members.</p>
	<p>Use the form below to select users matching your search conditions.
	You will then be given the option to individually move or prune away the matched users.</p>";

	doformheader("user","pruneusers");
	maketableheader("User Moving/Pruning System - Find users where...");
	makechoosercode("Usergroup is","usergroupid","usergroup",iif($usergroupid,$usergroupid,-1),"Any");
	makeinputcode("Has not logged on for <i>x</i> days","daysprune",iif($daysprune,$daysprune,365));
	makeinputcode("Registered before date: (yyyy-mm-dd format)","joindate",$joindate);
	makeinputcode("Has less than <i>x</i> posts:","minposts",iif($minposts,$minposts,""));
	makelabelcode("Order results by:","<select name='order'>
		<option value='username ASC'>User Name</option>
		<option value='email ASC'>Email Address</option>
		<option value='usergroup.title ASC'>Usergroup</option>
		<option value='posts DESC'>Post Count</option>
		<option value='lastvisit ASC'>Last Visit</option>
		<option value='joindate DESC'>Join Date</option>
	</select>");
	doformfooter("Find Users");

}

cpfooter();
?>