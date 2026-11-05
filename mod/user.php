<?php
require_once __DIR__ . '/../admin/php8compat.php';
error_reporting(7);

require("./global.php");

// ###################### Start makestylecode #######################
function makestylecode ($title,$name,$selvalue=-1,$extra="") {
// returns a combo box containing a list of titles in the $tablename table.
// allows specification of selected value in $selvalue
  global $DB_site,$bgcounter;
  $tablename="style";

  echo "<tr class='".iif($bgcounter++%2==0,"firstalt","secondalt")."'>\n<td><p>$title</p></td>\n<td><p><select name=\"$name\" size=\"1\">\n";
  $tableid=$tablename."id";

  $result=$DB_site->query("SELECT title,$tableid FROM $tablename WHERE userselect=1 ORDER BY title");
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

// ###################### Start find #######################
if ($action=="find") {
  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND (canbanusers=1 OR canviewprofile=1)")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  doformheader("user","findnames");
  maketableheader("Find Users");
  makeinputcode("Enter username to find: ","findname","");
  doformfooter("Search");
}

// ###################### Start findnames #######################
if ($_POST['action']=="findnames") {
  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND (canbanusers=1 OR canviewprofile=1)")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  $users=$DB_site->query("SELECT userid,username FROM user WHERE username LIKE '%".vb_escape(htmlspecialchars($findname))."%' ORDER BY username");
  echo "<table>";
  if ($DB_site->num_rows($users)>0) {
    echo "<tr><td nowrap><p><b>Users Found:</b></p></td>".iif($perms['ismoderator'] or $ismod['canbanusers']==1, "<td nowrap><p>&nbsp;</p></td>", "").iif($perms['ismoderator'] or $ismod['canviewprofile']==1, "<td nowrap><p>&nbsp;</p></td>", "")."</tr>\n";
    while ($user=$DB_site->fetch_array($users)) {
      echo "<tr><td nowrap><p>$user[username]</p></td>".iif($perms['ismoderator'] or $ismod['canbanusers']==1, "<td nowrap><a href=\"user.php?action=ban&amp;userid=$user[userid]\"><p>[ban]</p></a></td>", "").iif($perms['ismoderator'] or $ismod['canviewprofile']==1, "<td nowrap><a href=\"user.php?action=viewuser&amp;userid=$user[userid]\"><p>[view user]</p></a></td>", "")."</tr>\n";
    }
  } else {
    echo "<td><p>No users found</p></td>";
  }
  echo "</table>";
}

// ###################### Start ban #######################
if ($action=="ban") {
  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canbanusers=1")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  $banuser=$DB_site->query_first("SELECT username,userid,usergroupid FROM user WHERE userid =  " . intval( $userid ) );
  $ismod=$DB_site->query_first("SELECT moderatorid FROM moderator WHERE userid =  " . intval( $userid ) );
  if ($banuser['usergroupid']!=2 or $ismod) {
    echo "<p>You may not ban someone who is not a normal registered user!</p>";
  } else {
  	doformheader("user","doban");
	makehiddencode("userid",$banuser['userid']);
	maketableheader("Confirm ban");
	makedescription("Are you sure you wish to ban $banuser[username]?");
	doformfooter("Yes","",2,"No");
  }
}

// ###################### Start doban #######################
if ($_POST['action']=="doban") {
  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canbanusers=1")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  $banuser=$DB_site->query_first("SELECT username,userid,usergroupid, customtitle FROM user WHERE userid =  " . intval( $userid ) );
  $ismod=$DB_site->query_first("SELECT moderatorid FROM moderator WHERE userid =  " . intval( $userid ) );
  if ($banuser['usergroupid']!=2 or $ismod) {
    echo "<p>You may not ban someone who is not a normal registered user!</p>";
  }

  $bangroup=$DB_site->query_first("SELECT usergroupid,usertitle FROM usergroup WHERE title='Banned by Moderators'");
  if (!$bangroup) {
    $DB_site->query("INSERT INTO usergroup
                       (usergroupid, title, usertitle, cancontrolpanel, canmodifyprofile, canviewmembers,
                        canview, cansearch, canemail, canpostnew, canmove, canopenclose, candeletethread, canreplyown,
                        canreplyothers, canviewothers, caneditpost, candeletepost, canusepm, canpostpoll, canvote,
                        canpostattachment, ismoderator, canpublicevent, canpublicedit, canthreadrate, cantrackpm,
                        candenypmreceipts)
                     VALUES
                        (NULL, 'Banned by Moderators', 'Banned', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0', '0')");
    $bangroup['usergroupid'] = $DB_site->insert_id();
    $bangroup['usertitle'] = 'Banned';
  }

  if ($bangroup['usergroupid']>6) {
    $DB_site->query("UPDATE user SET usergroupid=$bangroup[usergroupid], customtitle = 0 , usertitle='".vb_escape($bangroup['usertitle'])."' WHERE userid=$banuser[userid]");
    echo "<p>$banuser[username] has been banned!</p>";
  } else {
    echo "<p>An unknown error occured while attempting to ban $banuser[username] ($banuser[userid]). The group the user was supposed to be moved to was \"$bangroup[usergroupid]\"</p>";
  }
}

// ###################### Start viewuser #######################
if ($action=="viewuser") {
  $perms=getpermissions();
  if (!$perms['ismoderator'] and !$ismod=$DB_site->query_first("SELECT * FROM moderator WHERE userid=$bbuserinfo[userid] AND canviewprofile=1")) {
    echo "<p>You do not have permission to do this!</p>";
    exit;
  }

  $userid=verifyid("user",$userid,0);

  $user=$DB_site->query_first("SELECT userid,usergroupid,username,password,email,parentemail,coppauser,homepage,icq,aim,yahoo,signature,adminemail,showemail,styleid,invisible,usertitle,customtitle,FROM_UNIXTIME(joindate) AS joindate,cookieuser,daysprune,FROM_UNIXTIME(lastvisit) AS lastvisit,FROM_UNIXTIME(lastactivity) AS lastactivity,FROM_UNIXTIME(lastpost) AS lastpost,posts,timezoneoffset,emailnotification,emailonpm,receivepm,ipaddress FROM user WHERE userid=$userid");

  doformheader("user","");
  maketableheader("Profile for user - $user[username]");
	  makechoosercode("User Group","usergroupid","usergroup",$user['usergroupid']);
	  makeinputcode("User Name","ausername",$user['username'],0);
	  makeinputcode("Email Address","email",$user['email'],0);
	  makeinputcode("User Title","usertitle",$user['usertitle']);
	  makeyesnocode("Use Custom Title<br><font size='1'>(This forces the title that you put in the field above to be used)</font>","customtitle",$user['customtitle']);
	  makestylecode("Style set","styleid",$user['styleid']);
	  makeinputcode("Home Page","homepage",$user['homepage'],0);
	  maketextareacode("Signature","signature",$user['signature'],5);
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
	  makeinputcode("Join Date<br>(Format yyyy-mm-dd, leave blank for today)","joindate",$user['joindate']);
	  makeyesnocode("Remember Username and password","cookieuser",$user['cookieuser']);
	  makeinputcode("Default view age<br>'Select threads from last x days'<br>Recommended values: 1, 2, 5, 10, 20, 30, 45, 60, 75, 100, 365, 1000 (ie all). -1 gives default forum selection","daysprune",$user['daysprune']);
	  makeinputcode("Last Visit<br>(Format yyyy-mm-dd hh:mm:ss, leave blank for today)","lastvisit",$user['lastvisit']);
	  makeinputcode("Last Activity<br>(Format yyyy-mm-dd hh:mm:ss, leave blank for today)","lastactivity",$user['lastactivity']);
	  makeinputcode("Last Post<br>(Format yyyy-mm-dd hh:mm:ss, leave blank for today)","lastpost",$user['lastpost']);
	  makeinputcode("Time Zone Offset (hours)","timezoneoffset",$user['timezoneoffset']);
	  makeyesnocode("Use email notification by default","emailnotification",$user['emailnotification']);
	  makeinputcode("IP Address","aipaddress",$user['ipaddress']);
  maketableheader("Custom Profile Fields");
  $userfield=$DB_site->query_first("SELECT * FROM userfield WHERE userid=$userid");

  $profilefields=$DB_site->query("SELECT profilefieldid,title FROM profilefield");
  while ($profilefield=$DB_site->fetch_array($profilefields)) {
  $varname="field$profilefield[profilefieldid]";
  makeinputcode($profilefield['title'],"field".$profilefield['profilefieldid'],$userfield[$varname]);
  }

  echo "</table></td></tr></table></form>\n";
}

cpfooter();

?>