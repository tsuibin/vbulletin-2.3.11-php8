<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

require("./global.php");

adminlog(iif($moderatorid!=0," moderator id = $moderatorid",iif($forumid!=0,"forum id = $forumid","")));
// ###################### Start makeparentlist #######################
function makeparentlist($forumid) {
  global $DB_site;

  $foruminfo=$DB_site->query_first("SELECT parentid FROM forum WHERE forumid=$forumid");

  $forumarray=$forumid;

  if ($foruminfo['parentid']!=0) {
    $forumarray.=",".getforumarray($foruminfo['parentid']);
  }

  if (substr($forumarray, -2)!="-1") {
    $forumarray.="-1";
  }

  return $forumarray;
}

// ###################### Start updateparentlists #######################
function updateparentlists() {
  global $DB_site;

  $forums=$DB_site->query("SELECT forumid FROM forum ORDER BY parentid");
  while($forum=$DB_site->fetch_array($forums)) {
    $parentlist = makeparentlist($forum['forumid']);
    $DB_site->query("UPDATE forum SET parentlist='".vb_escape($parentlist)."' WHERE forumid=$forum[forumid]");
  }
}

// ###################### Start function displayforums #######################
function displayforums($parentid=-1) {
  global $DB_site,$session;

  $forums=$DB_site->query("SELECT forumid,title,displayorder FROM forum WHERE parentid=$parentid ORDER BY displayorder");

  while ($forum=$DB_site->fetch_array($forums)) {

    echo "<li><b><a href=\"../forumdisplay.php?s=$session[sessionhash]&amp;forumid=$forum[forumid]\" target=\"_blank\">$forum[title]</a></b> ".iif($parentid!=0,"(Order: <input type=text name=\"order[$forum[forumid]]\" size=5 value=\"$forum[displayorder]\">) ".
			makelinkcode("edit","forum.php?s=$session[sessionhash]&amp;action=edit&amp;forumid=$forum[forumid]").
			makelinkcode("remove","forum.php?s=$session[sessionhash]&amp;action=remove&amp;forumid=$forum[forumid]"),"").
			makelinkcode("add moderator","forum.php?s=$session[sessionhash]&amp;action=addmoderator&amp;forumid=$forum[forumid]").
			makelinkcode("add sub-forum","forum.php?s=$session[sessionhash]&amp;action=add&amp;parentid=$forum[forumid]")."\n";

    $forummoderators=$DB_site->query("SELECT moderator.moderatorid,user.userid,user.username,user.usergroupid FROM moderator,user WHERE moderator.userid=user.userid AND moderator.forumid=$forum[forumid]");

    if ($DB_site->num_rows($forummoderators)) {
      echo "<ul>Moderators:<ul>\n";

      while ($moderator=$DB_site->fetch_array($forummoderators)) {
        echo "<li>$moderator[username] ".
						makelinkcode("edit","forum.php?s=$session[sessionhash]&amp;action=editmoderator&amp;moderatorid=$moderator[moderatorid]").
						makelinkcode("remove","forum.php?s=$session[sessionhash]&amp;action=removemoderator&amp;moderatorid=$moderator[moderatorid]").
						"</li>";
      }
      echo "</ul></ul>\n";
    }

    echo "<ul>\n";
    displayforums($forum['forumid']);
    echo "</ul>\n";

    echo "</li>\n";
  }
}

cpheader();

if (isset($action)==0) {
  $action="modify";
}

// ###################### Start add #######################
if ($action=="add") {

  doformheader("forum","insert");

  maketableheader("Add New Forum");
  makedescription('<div align="center"><hr width="75%"><b>Please note that child forums will not inherit any of this forum\'s settings.</b><br><hr width="75%"></div>');

  makeinputcode("Title","title");
  maketextareacode("Description<br>You may use HTML","description");

  makeinputcode("Display Order<br>0=do not display","displayorder",1);
  makeinputcode("Default view age<br>'Select threads from last x days'<br>Recommended values: 1, 2, 5, 10, 20, 30, 45, 60, 75, 100, 365, 1000 (ie all)","daysprune","30");

  makeforumchooser("parentid",$parentid,-1,"","No one");

  maketableheader("Moderation Options");

  makeinputcode("Emails addresses to notify when there is a new post<br>(Separate each with a SPACE)","newpostemail");
  makeinputcode("Emails addresses to notify when there is a new thread<br>(Separate each with a SPACE)","newthreademail");

  makeyesnocode("Moderator Queue<br>(Require moderator validation before new posts are displayed)","moderatenew",0);
  makeyesnocode("Attachment Queue<br>(Require moderator validation before new attachment are displayed)","moderateattach",0);

  maketableheader("Style Options");

  makechoosercode("Custom style set for this forum","styleset","style",1);
  makeyesnocode("Override users custom styles<BR>(will force this forums specified colors)","styleoverride",0);

  maketableheader("Posting Options");

  makeyesnocode("Private forum<br>(Invisible to all except moderators and admins; user access masks must be on!)","private",0);
  makeyesnocode("Act as forum?<br>(Will act as a category if no)","cancontainthreads",1);
  makeyesnocode("Is active?<br>(Will not appear if not)","isactive",1);
  makeyesnocode("Open for new posts?<br>(Will have lock if not, but still act as forum)","allowposting",1);

  maketableheader("Enable/Disable Features");

  makeyesnocode("Allow HTML Code in posts","aallowhtmlcode",0);
  makeyesnocode("Allow BB Code in posts","aallowbbcode",1);
  makeyesnocode("Allow BB IMG Code in posts","aallowimgcode",0);
  makeyesnocode("Allow Smilies in posts","aallowsmilies",1);
  makeyesnocode("Allow Icons for posts","aallowicons",1);
  makeyesnocode("Allow thread ratings in this forum?","allowratings",1);
  makeyesnocode("Count posts made in this forum towards user post counts?","countposts",1);

  doformfooter("Save Forum");
}

// ###################### Start insert #######################
if ($_POST['action']=="insert") {

  $parentlist=makeparentlist($parentid);
  $DB_site->query("INSERT INTO forum
                      (forumid,styleid,title,description,active,displayorder,parentid,
                       parentlist,allowposting,cancontainthreads,daysprune,newpostemail,newthreademail,
                       moderatenew,allowhtml,allowbbcode,allowimages,allowsmilies,allowicons,
                       styleoverride,allowratings,countposts,moderateattach)
                   VALUES
                      (NULL,'$styleset','".vb_escape($title)."','".vb_escape($description)."','$isactive','$displayorder','$parentid',
                       '','$allowposting','$cancontainthreads','$daysprune','".vb_escape($newpostemail)."','".vb_escape($newthreademail)."',
                       '$moderatenew','$aallowhtmlcode','$aallowbbcode','$aallowimgcode','$aallowsmilies','$aallowicons',
                       '$styleoverride','$allowratings','$countposts','$moderateattach')");
  $forumid=$DB_site->insert_id();
  $DB_site->query("UPDATE forum SET parentlist='".vb_escape("$forumid,$parentlist")."' WHERE forumid=$forumid");

  if ($private==1) {
    $groups=$DB_site->query("SELECT usergroupid FROM usergroup WHERE usergroupid<5 OR usergroupid>6");
    while ($group=$DB_site->fetch_array($groups)) {
			$DB_site->query("INSERT INTO forumpermission  (forumpermissionid,forumid,usergroupid,canview,cansearch,canemail,canpostnew,canmove,canopenclose,candeletethread,canreplyown,canreplyothers,canviewothers,caneditpost,candeletepost,canpostattachment,canpostpoll,canvote,cangetattachment) VALUES (NULL,'$forumid','$group[usergroupid]',0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0)");
    }

    $mods=$DB_site->query("SELECT DISTINCT moderator.userid FROM moderator,user WHERE moderator.userid=user.userid AND user.usergroupid<>6 AND user.usergroupid<>5");
    if ($DB_site->num_rows($mods)) {
      while ($mod=$DB_site->fetch_array($mods)) {
        $accessto[] = $mod['userid'];
      }
      while ( list($key,$userid)=each($accessto) ) {
        $DB_site->query("INSERT INTO access (userid,forumid,accessmask) VALUES ('$userid','$forumid',1)");
      }
    }
  }

  $action="modify";

  echo "<p>Record added</p>";

}

// ###################### Start edit #######################
if ($action=="edit") {

  $forum=$DB_site->query_first("SELECT * FROM forum WHERE forumid=$forumid");

  doformheader("forum","doupdate");
  makehiddencode("forumid","$forumid");

  maketableheader("Edit Forum:</b> <i>$forum[title]</i>","",0);
  makedescription('<div align="center"><hr width="75%"><b>Please note that child forums will not inherit any of this forum\'s settings.</b><br><hr width="75%"></div>');

  makeinputcode("Title","title",$forum['title']);
  maketextareacode("Description<br>You may use HTML","description",$forum['description']);

  makeinputcode("Display Order<br>0=do not display","displayorder",$forum['displayorder']);
  makeinputcode("Default view age<br>'Select threads from last x days'<br>Recommended values: 1, 2, 5, 10, 20, 30, 45, 60, 75, 100, 365, 1000 (ie all)","daysprune",$forum['daysprune']);

  if ($forumid!=-1) {
    makeforumchooser("parentid",$forum['parentid']);
  } else {
    makehiddencode("parentid","0");
  }

  maketableheader("Moderation Options");

  makeinputcode("Emails addresses to notify when there is a new post<br>(Separate each with a SPACE)","newpostemail",$forum['newpostemail']);
  makeinputcode("Emails addresses to notify when there is a new thread<br>(Separate each with a SPACE)","newthreademail",$forum['newthreademail']);

  makeyesnocode("Moderator Queue<br>(Require moderator validation before new posts are displayed)","moderatenew",$forum['moderatenew']);
  makeyesnocode("Attachment Queue<br>(Require moderator validation before new attachment are displayed)","moderateattach",$forum['moderateattach']);

  $perms=getpermissions($forumid,0,2);
  if ($perms['canview']==1) {
    $private=0;
  } else {
    $private=1;
  }

  maketableheader("Style Options");

  makechoosercode("Custom style set for this forum","styleset","style",$forum['styleid']);
  makeyesnocode("Override users custom styles<BR>(will force this forum's specified colors)","styleoverride",$forum['styleoverride']);

  maketableheader("Posting Options");

  makeyesnocode("Private forum<br>(Invisible to all except moderators and admins; user access masks must be on!)","private",$private);
  makeyesnocode("Act as forum?<br>(Will act as a category if no)","cancontainthreads",$forum['cancontainthreads']);
  makeyesnocode("Is active?<br>(Will not appear if not)","isactive",$forum['active']);
  makeyesnocode("Open for new posts?<br>(Will have lock if not, but still act as forum)","allowposting",$forum['allowposting']);

  maketableheader("Enable/Disable Features");

  makeyesnocode("Allow HTML Code in posts","aallowhtmlcode",$forum['allowhtml']);
  makeyesnocode("Allow BB Code in posts","aallowbbcode",$forum['allowbbcode']);
  makeyesnocode("Allow BB IMG Code in posts","aallowimgcode",$forum['allowimages']);
  makeyesnocode("Allow Smilies in posts","aallowsmilies",$forum['allowsmilies']);
  makeyesnocode("Allow Icons for posts","aallowicons",$forum['allowicons']);
  makeyesnocode("Allow thread ratings in this forum?","allowratings",$forum['allowratings']);
  makeyesnocode("Count posts made in this forum towards user post counts?","countposts",$forum['countposts']);

  doformfooter("Save Changes");

}

// ###################### Start update #######################
if ($_POST['action']=="doupdate") {

  // SANITY CHECK (prevent invalid nesting)
  if ($parentid==$forumid) {
  	echo "<b>ERROR:</b> You can't parent a forum to itself!<br><br>".makelinkcode("Go back","javascript:history.back(1)");
	exit;
  }
  $foruminfo = $DB_site->query_first("SELECT forumid,title,parentlist FROM forum WHERE forumid='$parentid'");
  $parents = explode(",", $foruminfo['parentlist']);
  while (list(,$val) = each($parents)) {
	if ($val==$forumid) {
	  echo "<b>ERROR:</b> You can't parent a forum to one of its own children!<br><br>".makelinkcode("Go back","javascript:history.back(1)");
	  exit;
	}
  }
  // end Sanity check

  $parentlist="'$forumid,".makeparentlist($parentid)."'";
  $DB_site->query("UPDATE forum
                   SET
                     styleid='$styleset', title='".vb_escape($title)."', description='".vb_escape($description)."',
                     active='$isactive', displayorder='$displayorder', parentid='$parentid', parentlist=$parentlist,
                     allowposting='$allowposting', cancontainthreads='$cancontainthreads', daysprune='$daysprune',
                     newpostemail='".vb_escape($newpostemail)."', newthreademail='".vb_escape($newthreademail)."',
                     moderatenew='$moderatenew', allowhtml='$aallowhtmlcode', allowbbcode='$aallowbbcode',
                     allowimages='$aallowimgcode', allowsmilies='$aallowsmilies', allowicons='$aallowicons',
                     styleoverride='$styleoverride', allowratings='$allowratings', countposts='$countposts',
                     moderateattach='$moderateattach'
                   WHERE forumid='$forumid'");

  $perms=getpermissions($forumid,0,2);
  if ($perms['canview']==1) {
    $oldprivate=0;
  } else {
    $oldprivate=1;
  }
  if ($oldprivate!=$private) {
    if ($private==1) {
      $groups=$DB_site->query("SELECT usergroupid FROM usergroup WHERE usergroupid<>5 AND usergroupid<>6");
      while ($group=$DB_site->fetch_array($groups)) {
				$DB_site->query("INSERT INTO forumpermission  (forumpermissionid,forumid,usergroupid,canview,cansearch,canemail,canpostnew,canmove,canopenclose,candeletethread,canreplyown,canreplyothers,canviewothers,caneditpost,candeletepost,canpostattachment,canpostpoll,canvote,cangetattachment) VALUES (NULL,'$forumid','$group[usergroupid]',0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0)");
      }

      $mods=$DB_site->query("SELECT DISTINCT moderator.userid FROM moderator,user WHERE moderator.userid=user.userid AND user.usergroupid<>6 AND user.usergroupid<>5");
      if ($DB_site->num_rows($mods)) {
        while ($mod=$DB_site->fetch_array($mods)) {
          $accessto[] = $mod['userid'];
        }
      }

      if ( isset($accessto) ) {
        $whereclause = "userid=".implode(" OR userid=", $accessto);
        $accesslist=$DB_site->query("SELECT * FROM access WHERE ($whereclause) AND forumid=$forumid");
        while ($thisaccess=$DB_site->fetch_array($accesslist)) {
          $access["$thisaccess[userid]"] = $thisaccess['accessmask'];
        }
        reset($accessto);
        while ( list($key,$userid)=each($accessto) ) {
          if ( isset($access["$userid"]) ) {
            if ($access["$userid"]!=1) {
              $DB_site->query("UPDATE access SET accessmask=1 WHERE userid='$userid' AND forumid='$forumid'");
            }
          } else {
            $DB_site->query("INSERT INTO access (userid,forumid,accessmask) VALUES ('$userid','$forumid',1)");
          }
        }
      }
    } else {
      $mods=$DB_site->query("SELECT DISTINCT moderator.userid FROM moderator,user WHERE moderator.userid=user.userid AND user.usergroupid<>6 AND user.usergroupid<>5");
      while ($mod=$DB_site->fetch_array($mods)) {
        $accessto[] = $mod['userid'];
      }

      if ( isset($accessto) ) {
        $whereclause = "userid=".implode(" OR userid=", $accessto);
        $accesslist=$DB_site->query("DELETE FROM access WHERE ($whereclause) AND forumid=$forumid");
      }

      $DB_site->query("DELETE FROM forumpermission WHERE forumid=$forumid AND ((usergroupid>=1 AND usergroupid<=4) OR usergroupid>6)");
    }
  }

  updateparentlists();

  echo "<p>Record updated!</p>";

  $action="modify";

}
// ###################### Start Remove #######################

if ($action=="remove") {

	doformheader("forum","kill");
	makehiddencode("forumid",$forumid);
	maketableheader("Confirm deletion");
	makedescription("Are you sure you want to delete this forum?<br>It will remove all posts and subforums too!");
	doformfooter("Yes","",2,"No");

}

// ###################### Start Kill #######################

if ($_POST['action']=="kill") {

  $forums=$DB_site->query("SELECT forumid FROM forum WHERE INSTR(CONCAT(',',parentlist,','), ',$forumid,')>0");
  $forumlist = "0";
  while($thisforum=$DB_site->fetch_array($forums)) {
    $forumlist .= ",$thisforum[forumid]";
  }

  $DB_site->query("DELETE FROM forum WHERE forumid IN ($forumlist)");

  $threads=$DB_site->query("SELECT threadid FROM thread WHERE forumid IN ($forumlist)");

  while ($thread=$DB_site->fetch_array($threads)) {
    deletethread($thread['threadid']);
  }

  $DB_site->query("DELETE FROM forumpermission WHERE forumid IN ($forumlist)");
  $DB_site->query("DELETE FROM access WHERE forumid IN ($forumlist)");
  $DB_site->query("DELETE FROM moderator WHERE forumid IN ($forumlist)");

  $DB_site->query("DELETE FROM announcement WHERE forumid IN ($forumlist)");
  $DB_site->query("DELETE FROM subscribeforum WHERE forumid IN ($forumlist)");


  echo "<p>Forum deleted</p>\n";

  $action="modify";

}

// ###################### Start do order #######################
if ($_POST['action']=="doorder") {

  while (list($key,$val)=each($order)) {

    $DB_site->query("UPDATE forum SET displayorder='$val' WHERE forumid='$key'");

  }

  echo "<p>Order updated!</p>";
  $action="modify";

}

// ###################### Start add moderator #######################
if ($action=="addmoderator") {

  doformheader("forum","insertmoderator");

  maketableheader("Add Moderator");
  makeforumchooser("parentid",$forumid,-1,"","","Forum",0);
  makeinputcode("User name","ausername");
  makeyesnocode("Receive email when there is a new post","newpostemail",0);
  makeyesnocode("Receive email when there is a new thread","newthreademail",0);
  maketableheader("Moderator Permissions");
  makeyesnocode("Can edit posts","caneditposts",1);
  makeyesnocode("Can delete posts","candeleteposts",1);
  makeyesnocode("Can view IPs","canviewips",1);
  makeyesnocode("Can manage threads (move,copy,split,etc)","canmanagethreads",1);
  makeyesnocode("Can open and close threads","canopenclose",1);
  makeyesnocode("Can edit thread information (subject,icon,notes,etc)","caneditthreads",1);
  makeyesnocode("Can moderate new posts","canmoderateposts",1);
  makeyesnocode("Can moderate new attachments","canmoderateattachments",1);
  makeyesnocode("Can modify styles (not implemented yet)","canmodifystyles",0);
  makeyesnocode("Can ban users from board","canbanusers",0);
  makeyesnocode("Can can view whole user profile (but not edit)","canviewprofile",0);
  makeyesnocode("Can post announcements","canannounce",1);
  makeyesnocode("Can mass move threads","canmassmove",0);
  makeyesnocode("Can mass prune threads","canmassprune",0);

  doformfooter("Save Moderator");

}

// ###################### Start insert moderator #######################
if ($_POST['action']=="insertmoderator") {

  //get userid
  if ($getuserid=$DB_site->query_first("SELECT userid FROM user WHERE username='".vb_escape(htmlspecialchars($ausername))."'")) {
    $userid=$getuserid['userid'];

    $DB_site->query("INSERT INTO moderator (moderatorid,forumid,userid,newpostemail,newthreademail,caneditposts,candeleteposts,canviewips,canmanagethreads,canopenclose,caneditthreads,caneditstyles,canbanusers,canviewprofile,canannounce,canmassmove,canmassprune,canmoderateposts,canmoderateattachments) VALUES (NULL,'$parentid','$userid','$newpostemail','$newthreademail','$caneditposts','$candeleteposts','$canviewips','$canmanagethreads','$canopenclose','$caneditthreads','$caneditstyles','$canbanusers','$canviewprofile','$canannounce','$canmassmove','$canmassprune','$canmoderateposts','$canmoderateattachments')");

    // if the user is in the registered users usergroup , move them to the 'moderators' usergroup, if it exists
    if ($modug=$DB_site->query_first("SELECT usergroupid FROM usergroup WHERE title='Moderators'")) {
      $DB_site->query("UPDATE user SET usergroupid=$modug[usergroupid] WHERE userid='$userid' AND usergroupid=2");
    }

    $action="modify";

    echo "<p>Moderator added. If you have any private forums that this moderator should be able to access, you'll have to manually give him/her this.</p>";

  } else {
    echo "<p>Unable to find username!</p>";
  }

}

// ###################### Start edit moderator #######################
if ($action=="editmoderator") {

  $moderator=$DB_site->query_first("SELECT forumid,userid,newpostemail,newthreademail,caneditposts,candeleteposts,canviewips,canmanagethreads,canopenclose,caneditthreads,caneditstyles,canbanusers,canviewprofile,canannounce,canmassmove,canmassprune,canmoderateposts,canmoderateattachments FROM moderator WHERE moderatorid=$moderatorid");

  doformheader("forum","doupdatemoderator");
  makehiddencode("moderatorid","$moderatorid");

  maketableheader("Edit Moderator");
  makeforumchooser("parentid",$moderator['forumid'], -1, "", "All forums", "Forum Moderated (and children)", 0);

  //get username
  $getusername=$DB_site->query_first("SELECT username FROM user WHERE userid=$moderator[userid]");
  makeinputcode("User name","ausername",$getusername['username']);

  makeyesnocode("Receive email when there is a new post","newpostemail",$moderator['newpostemail']);
  makeyesnocode("Receive email when there is a new thread","newthreademail",$moderator['newthreademail']);
  maketableheader("Moderator Permissions");
  makeyesnocode("Can edit posts","caneditposts",$moderator['caneditposts']);
  makeyesnocode("Can delete posts","candeleteposts",$moderator['candeleteposts']);
  makeyesnocode("Can view IPs","canviewips",$moderator['canviewips']);
  makeyesnocode("Can manage threads (move,copy,split,etc)","canmanagethreads",$moderator['canmanagethreads']);
  makeyesnocode("Can open and close threads","canopenclose",$moderator['canopenclose']);
  makeyesnocode("Can edit thread information (subject,icon,notes,etc)","caneditthreads",$moderator['caneditthreads']);
  makeyesnocode("Can moderate new posts","canmoderateposts",$moderator['canmoderateposts']);
  makeyesnocode("Can moderate new attachments","canmoderateattachments",$moderator['canmoderateattachments']);
  makeyesnocode("Can modify styles (not implemented yet)","canmodifystyles",$moderator['canmodifystyles']);
  makeyesnocode("Can ban users from board","canbanusers",$moderator['canbanusers']);
  makeyesnocode("Can can view whole user profile (but not edit)","canviewprofile",$moderator['canviewprofile']);
  makeyesnocode("Can post announcements","canannounce",$moderator['canannounce']);
  makeyesnocode("Can mass move threads","canmassmove",$moderator['canmassmove']);
  makeyesnocode("Can mass prune threads","canmassprune",$moderator['canmassprune']);

  doformfooter("Save Changes");

}

// ###################### Start do update moderator #######################
if ($_POST['action']=="doupdatemoderator") {

  //get userid
  if ($getuserid=$DB_site->query_first("SELECT userid FROM user WHERE username='".vb_escape($ausername)."'")) {
    $userid=$getuserid['userid'];

    $DB_site->query("UPDATE moderator SET forumid='$parentid',userid='$userid',newpostemail='$newpostemail',newthreademail='$newthreademail',caneditposts='$caneditposts',candeleteposts='$candeleteposts',canviewips='$canviewips',canmanagethreads='$canmanagethreads',canopenclose='$canopenclose',caneditthreads='$caneditthreads',caneditstyles='$caneditstyles',canbanusers='$canbanusers',canviewprofile='$canviewprofile',canannounce='$canannounce',canmassmove='$canmassmove',canmassprune='$canmassprune',canmoderateposts='$canmoderateposts',canmoderateattachments='$canmoderateattachments' WHERE moderatorid='$moderatorid'");

    echo "<p>Record updated!</p>";

    $action="modify";

  } else {
    echo "<p>Unable to find username!</p>";
  }

}
// ###################### Start Remove moderator #######################

if ($action=="removemoderator") {

	doformheader("forum","killmoderator");
	makehiddencode("moderatorid",$moderatorid);
	maketableheader("Confirm deletion");
	makedescription("Are you sure you want to delete this moderator?");
	doformfooter("Yes","",2,"No");

}

// ###################### Start Kill moderator #######################

if ($_POST['action']=="killmoderator") {
  $getuserid=$DB_site->query_first("SELECT user.userid,usergroupid FROM moderator LEFT JOIN user USING (userid) WHERE moderatorid=$moderatorid");
  if (!$getuserid) {
    echo "It appears this user is no longer a moderator. The most likely reason for this to have happened is that you accidently clicked the remove buton twice. ";
  } else {

    $DB_site->query("DELETE FROM moderator WHERE moderatorid=$moderatorid");

  	  // if the user is in the moderators usergroup and they are not modding more forums, then move them to registered users usergroup
  	  if ($modug=$DB_site->query_first("SELECT usergroupid FROM usergroup WHERE title='Moderators' AND usergroupid=$getuserid[usergroupid]") and !$moreforums=$DB_site->query_first("SELECT userid FROM moderator WHERE userid=$getuserid[userid]")) {
  		 $DB_site->query("UPDATE user SET usergroupid=2 WHERE userid='$getuserid[userid]'");
  	  }

    echo "<p>Moderator removed. If you had any private forums, you may want to make sure the moderator doesn't have access to them anymore!</p>";
    $action="modify";
  }
}

// ###################### Start modify #######################
if ($action=="modify") {

  echo "<p>If you change the orders, please be sure to submit the form using the buttons at the bottom of the page</p>";

  doformheader("forum","doorder");
  maketableheader("Modify Forums");

  echo "<tr class='firstalt'><td>\n<ul>\n";

  // code to show moderators set for all forums
	$forummoderators=$DB_site->query("SELECT moderator.moderatorid,user.userid,user.username,user.usergroupid FROM moderator,user WHERE moderator.userid=user.userid AND moderator.forumid=-1");

	if ($DB_site->num_rows($forummoderators)) {
		echo "<p>Global Moderators:<ul>\n";

		while ($moderator=$DB_site->fetch_array($forummoderators)) {
			echo "<li>$moderator[username] ".
					makelinkcode("edit","forum.php?s=$session[sessionhash]&amp;action=editmoderator&amp;moderatorid=$moderator[moderatorid]").
					makelinkcode("remove","forum.php?s=$session[sessionhash]&amp;action=removemoderator&amp;moderatorid=$moderator[moderatorid]").
					"</li>";
		}
		echo "</ul></p>\n";
	}

  displayforums(-1);
  echo "</ul>\n</td></tr>\n";

  doformfooter("Update order");
}

cpfooter();
?>