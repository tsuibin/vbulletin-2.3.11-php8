<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

require("./global.php");

adminlog(iif($usergroupid!=0,"usergroup id = $usergroupid",""));

cpheader();

if (isset($action)==0) {
  $action="modify";
}

// ###################### Start add #######################
if ($action=="add") {

  doformheader("usergroup","insert");
  maketableheader("Add new usergroup");

  makeinputcode("Title","title");
  makeinputcode("User Status<br>Use this to override the default 'ladder' of user status titles","usertitle");
  makeyesnocode("Viewable on <a href=\"../showgroups.php?s=$session[sessionhash]\" target=\"_blank\">Show Groups</a>?","showgroup",1);

  maketableheader("Viewing Permissions");
  makeyesnocode("Can view board","canview",1);
  makeyesnocode("Can view members info (including other's profiles and members list)","canviewmembers",1);
  makeyesnocode("Can view others' threads","canviewothers",1);
  makeyesnocode("Can download attachments","cangetattachment",1);

  maketableheader("Miscellaneous Permissions");
  makeyesnocode("Can search","cansearch",1);
  makeyesnocode("Can use 'email to friend' feature","canemail",0);
  makeyesnocode("Can modify profile","canmodifyprofile",1);

  maketableheader("Posting Permissions");
  makeyesnocode("Can post new threads","canpostnew",0);
  makeyesnocode("Can reply to own threads","canreplyown",0);
  makeyesnocode("Can reply to other's threads","canreplyothers",0);
  makeyesnocode("Can Rate threads if thread rating is enabled in the forum?","canthreadrate",1);
  makeyesnocode("Can post attachments","canpostattachment",1);

  maketableheader("Post/Thread Editing Permissions");
  makeyesnocode("Can edit own posts","caneditpost",0);
  makeyesnocode("Can delete own posts","candeletepost",0);
  makeyesnocode("Can move own threads to other forums","canmove",0);
  makeyesnocode("Can open / close own threads","canopenclose",0);
  makeyesnocode("Can delete own threads by deleting the first post","candeletethread",0);

  maketableheader("Poll Permissions");
  makeyesnocode("Can post polls","canpostpoll",1);
  makeyesnocode("Can vote on polls","canvote",1);

  maketableheader("Private Messaging Permissions");
  makeyesnocode("Can use Private Messaging","canusepm",1);
  makeyesnocode("Can Use Message Tracking?","cantrackpm",0);
  makeyesnocode("Can Deny Private Message Read Receipt Request?","candenypmreceipts",0);
  makeinputcode("Maximum Buddies to Send PMs at a time<br><font size=1>Do not set this too high for performance reasons (set to 0 to disable)</font>","maxbuddypm",0);
  makeinputcode("Maximum PMs to Forward at a time<br><font size=1>Do not set this too high for performance reasons (set to 0 to disable)</font>","maxforwardpm",0);

  maketableheader("Calendar Permissions");
  makeyesnocode("Can Post Public events on Calendar","canpublicevent",0);
  makeyesnocode("Can Edit other's Public events","canpublicedit",0);

  maketableheader("Who's Online Permissions");
  makeyesnocode("Can View Who's Online","canwhosonline",1);
  makeyesnocode("Can View IP Addresses on Who's Online","canwhosonlineip",0);

  maketableheader("Adminstrative Permissions");
  makeyesnocode("Is Super Moderator","ismoderator",0);
  makeyesnocode("Can access control panel<br><font size='1'>Be careful! Allowing CP access will allow a usergroup to change their own permissions!</font>","cancontrolpanel",0);

  doformfooter("Save");
}

// ###################### Start insert #######################
if ($_POST['action']=="insert") {

  $DB_site->query("INSERT INTO usergroup (usergroupid,title,usertitle,cancontrolpanel,canmodifyprofile,canviewmembers,canview,showgroup,cansearch,canemail,canpostnew,canmove,canopenclose,candeletethread,canreplyown,canreplyothers,canviewothers,caneditpost,candeletepost,canusepm,canpostpoll,canvote,canpostattachment,ismoderator,canpublicevent,canpublicedit,canthreadrate,cantrackpm,candenypmreceipts,maxbuddypm,maxforwardpm,canwhosonline,canwhosonlineip,cangetattachment)
	VALUES (NULL,'".vb_escape($title)."','".vb_escape($usertitle)."',$cancontrolpanel,$canmodifyprofile,$canviewmembers,$canview,$showgroup,$cansearch,$canemail,$canpostnew,$canmove,$canopenclose,$candeletethread,$canreplyown,$canreplyothers,$canviewothers,$caneditpost,$candeletepost,$canusepm,$canpostpoll,$canvote,$canpostattachment,$ismoderator,$canpublicevent,$canpublicedit,$canthreadrate,$cantrackpm,$candenypmreceipts,$maxbuddypm,$maxforwardpm,$canwhosonline,$canwhosonlineip,$cangetattachment)");

  $action="modify";

  echo "<p>Record added</p>";

}

// ###################### Start edit #######################
if ($action=="edit") {

  $usergroup=$DB_site->query_first("SELECT * FROM usergroup WHERE usergroupid=$usergroupid");

  doformheader("usergroup","doupdate");
  makehiddencode("usergroupid","$usergroupid");
  maketableheader("Edit usergroup: </b>$usergroup[title]<b>","",0);

  makeinputcode("Title","title",$usergroup['title']);
  makeinputcode("User Status<br>Use this to override the default 'ladder' of user status titles","usertitle",$usergroup['usertitle']);
  if (($usergroupid != 1 and $usergroupid != 2 and $usergroupid != 3 and $usergroupid != 4 and $usergroupid != 7) or $usergroup['showgroup'] == 1) {
  	makeyesnocode("Viewable on <a href=\"../showgroups.php?s=$session[sessionhash]\" target=\"_blank\">Show Groups</a>?","showgroup",$usergroup['showgroup']);
  } else {
		makehiddencode("showgroup",0);
	}

  maketableheader("Viewing Permissions");
  makeyesnocode("Can view board","canview",$usergroup['canview']);
  makeyesnocode("Can view members info (including other's profiles and members list)","canviewmembers",$usergroup['canviewmembers']);
  makeyesnocode("Can view others' threads","canviewothers",$usergroup['canviewothers']);
  makeyesnocode("Can download attachments","cangetattachment",$usergroup['cangetattachment']);

  maketableheader("Miscellaneous Permissions");
  makeyesnocode("Can search","cansearch",$usergroup['cansearch']);
  makeyesnocode("Can use 'email to friend' feature","canemail",$usergroup['canemail']);
  makeyesnocode("Can modify profile","canmodifyprofile",$usergroup['canmodifyprofile']);

  maketableheader("Posting Permissions");
  makeyesnocode("Can post new threads","canpostnew",$usergroup['canpostnew']);
  makeyesnocode("Can reply to own threads","canreplyown",$usergroup['canreplyown']);
  makeyesnocode("Can reply to other's threads","canreplyothers",$usergroup['canreplyothers']);
  makeyesnocode("Can Rate threads if thread rating is enabled in the forum?","canthreadrate",$usergroup['canthreadrate']);
  makeyesnocode("Can post attachments","canpostattachment",$usergroup['canpostattachment']);

  maketableheader("Post/Thread Editing Permissions");
  makeyesnocode("Can edit own posts","caneditpost",$usergroup['caneditpost']);
  makeyesnocode("Can delete own posts","candeletepost",$usergroup['candeletepost']);
  makeyesnocode("Can move own threads to other forums","canmove",$usergroup['canmove']);
  makeyesnocode("Can open / close own threads","canopenclose",$usergroup['canopenclose']);
  makeyesnocode("Can delete own threads by deleting the first post","candeletethread",$usergroup['candeletethread']);

  maketableheader("Poll Permissions");
  makeyesnocode("Can post polls","canpostpoll",$usergroup['canpostpoll']);
  makeyesnocode("Can vote on polls","canvote",$usergroup['canvote']);

  maketableheader("Private Messaging Permissions");
  makeyesnocode("Can use Private Messaging","canusepm",$usergroup['canusepm']);
  makeyesnocode("Can Use Message Tracking?","cantrackpm",$usergroup['cantrackpm']);
  makeyesnocode("Can Deny Private Message Read Receipt Request?","candenypmreceipts",$usergroup['candenypmreceipts']);
  makeinputcode("Maximum Buddies to Send PMs at a time<br><font size=1>Do not set this too high for performance reasons (set to 0 to disable)</font>","maxbuddypm",$usergroup['maxbuddypm']);
  makeinputcode("Maximum PMs to Forward at a time<br><font size=1>Do not set this too high for performance reasons (set to 0 to disable)</font>","maxforwardpm",$usergroup['maxforwardpm']);

  maketableheader("Calendar Permissions");
  makeyesnocode("Can post Public events on Calendar","canpublicevent",$usergroup['canpublicevent']);
  makeyesnocode("Can edit other's Public events on Calendar","canpublicedit",$usergroup['canpublicedit']);

  maketableheader("Who's Online Permissions");
  makeyesnocode("Can View Who's Online","canwhosonline",$usergroup['canwhosonline']);
  makeyesnocode("Can View IP Addresses on Who's Online","canwhosonlineip",$usergroup['canwhosonlineip']);

  maketableheader("Adminstrative Permissions");
  makeyesnocode("Is Super Moderator","ismoderator",$usergroup['ismoderator']);
  makeyesnocode("Can access control panel","cancontrolpanel",$usergroup['cancontrolpanel']);

  doformfooter("Save Changes");

}

// ###################### Start do update #######################
if ($_POST['action']=="doupdate") {

  // check that not removing last admin group
  $checkadmin=$DB_site->query_first("SELECT COUNT(*) AS usergroups FROM usergroup WHERE cancontrolpanel=1 AND usergroupid<>$usergroupid");
  if ($checkadmin['usergroups']==0 and $cancontrolpanel==0) {
    echo "<p>You are about to remove the last group with control panel access. This would lock you out of the control panel - you cannot proceed.</body></html>";
    exit;
  }
  $DB_site->query("UPDATE usergroup SET title='".vb_escape($title)."',usertitle='".vb_escape($usertitle)."',cancontrolpanel=$cancontrolpanel,canmodifyprofile=$canmodifyprofile,canviewmembers=$canviewmembers,canview=$canview,showgroup=".intval($showgroup).",cansearch=$cansearch,canemail=$canemail,canpostnew=$canpostnew,canmove=$canmove,canopenclose=$canopenclose,candeletethread=$candeletethread,canreplyown=$canreplyown,canreplyothers=$canreplyothers,canviewothers=$canviewothers,caneditpost=$caneditpost,candeletepost=$candeletepost,canusepm=$canusepm,canpostpoll=$canpostpoll,canvote=$canvote,canpostattachment=$canpostattachment,ismoderator=$ismoderator,canpublicedit=$canpublicedit,canpublicevent=$canpublicevent,canthreadrate=$canthreadrate,cantrackpm=$cantrackpm,candenypmreceipts=$candenypmreceipts,maxbuddypm=$maxbuddypm,maxforwardpm=$maxforwardpm,canwhosonline=$canwhosonline,canwhosonlineip=$canwhosonlineip,cangetattachment=$cangetattachment WHERE usergroupid=$usergroupid");

  if ($usertitle!="") {
    $DB_site->query("UPDATE user SET usertitle='".vb_escape($usertitle)."' WHERE usergroupid=$usergroupid AND customtitle=0");
  }

  echo "<p>Record updated!</p>";

  $action="modify";

}

// ###################### Start Remove #######################

if ($action=="remove") {

	doformheader("usergroup","kill");
	makehiddencode("usergroupid",$usergroupid);
	maketableheader("Confirm deletion");
	makedescription("Are you sure you want to delete this usergroup? All users will revert to the 'registered' group");
	doformfooter("Yes","",2,"No");

}

// ###################### Start Kill #######################
if ($_POST['action']=="kill") {

  $DB_site->query("DELETE FROM usergroup WHERE usergroupid=$usergroupid");
  $DB_site->query("DELETE FROM forumpermission WHERE usergroupid=$usergroupid");
  $DB_site->query("UPDATE user SET usergroupid=2 WHERE usergroupid=$usergroupid");
  echo "<p>Record deleted sucessfully - all existing members of this group have been returned to the 'registered' group</p>";

  $action="modify";
}

// ###################### Start modify #######################
if ($action=="modify") {

  $usergroups=$DB_site->query("SELECT usergroup.usergroupid, usergroup.title, COUNT(user.userid) as count
                               FROM usergroup
                               LEFT JOIN user ON (user.usergroupid = usergroup.usergroupid)
                               GROUP BY usergroup.usergroupid
                               ORDER BY usergroup.title");

  echo "<table align='center' border='0' cellpadding='0' cellspacing='0' class='tblborder'><tr><td>";
  echo "<table border=0 cellspacing=1 cellpadding=4><tr class='tblhead'><td align=\"center\" colspan=4><b><span class='tblhead'>Usergroups</span></b></td></tr>\n";
  echo "<tr class='".getrowbg()."'><td nowrap align=\"center\" width=\"100%\"><font size='1'><b>Usergroup</b></font></td><td align=\"center\" nowrap><font size='1'><b># of Users</b></font></td><td align=\"center\" nowrap><font size='1'><b>Edit</b></font></td><td nowrap align=\"center\"><font size='1'><b>List Users</b></font></td></tr>\n";

  while ($usergroup=$DB_site->fetch_array($usergroups)) {

    echo "<tr class='".getrowbg()."'><td width=\"100%\">$usergroup[title]</td><td nowrap>$usergroup[count]</td><td nowrap>".
         makelinkcode("edit","usergroup.php?s=$session[sessionhash]&amp;action=edit&amp;usergroupid=$usergroup[usergroupid]").
 		 iif($usergroup['usergroupid']>6,makelinkcode("remove","usergroup.php?s=$session[sessionhash]&amp;action=remove&amp;usergroupid=$usergroup[usergroupid]"),"")."</td><td nowrap>".
		 makelinkcode("list all users","user.php?s=$session[sessionhash]&amp;action=find&amp;usergroupid=$usergroup[usergroupid]&amp;limitnumber=300").
         "</td></tr>\n";
  }
  echo "</table></td></tr></table>\n";
}

cpfooter();
?>