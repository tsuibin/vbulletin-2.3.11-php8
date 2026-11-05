<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

require("./global.php");

adminlog(iif($forumpermissionid!=0,"forumpermission id = $forumpermissionid",""));

cpheader();

if (isset($action)==0) {
  $action="modify";
}

// ###################### Start edit #######################
if ($action=="edit") {

  doformheader("forumpermission","doupdate");

  if (isset($forumpermissionid)) {
    $forumpermission=$DB_site->query_first("SELECT * FROM forumpermission WHERE forumpermissionid=$forumpermissionid");
    makehiddencode("forumpermissionid","$forumpermissionid");

  } else {
    $forumpermission = getpermissions($forumid, 1, intval($usergroupid));
    makehiddencode("forumid",$forumid);
  }

  makehiddencode("usergroupid",$forumpermission['usergroupid']);

  echo "<tr class='tblhead'><td colspan=2><b><input type=\"radio\" name=\"useusergroup\" value=\"1\" ".iif (!isset($forumpermissionid),"checked","")."><span class='tblhead'>Use usergroup default</b> (Note: this will delete any previous custom settings)</span></td></tr>\n";

  makehrcode();

  echo "<tr class='tblhead'><td colspan=2><b><input type=\"radio\" name=\"useusergroup\" value=\"0\" ".iif(isset($forumpermissionid),"checked","")."><span class='tblhead'>Use custom settings:</span></b></td></tr>\n";

  makeyesnocode("Can view forum","canview",$forumpermission['canview']);

  makeyesnocode("Can search","cansearch",$forumpermission['cansearch']);
  makeyesnocode("Can use 'email to friend' feature","canemail",$forumpermission['canemail']);
  makeyesnocode("Can post new threads","canpostnew",$forumpermission['canpostnew']);

  makeyesnocode("Can post polls","canpostpoll",$forumpermission['canpostpoll']);
  makeyesnocode("Can vote on polls","canvote",$forumpermission['canvote']);
  makeyesnocode("Can post attachments","canpostattachment",$forumpermission['canpostattachment']);
  makeyesnocode("Can download attachments","cangetattachment",$forumpermission['cangetattachment']);

  makeyesnocode("Can move own threads to other forums","canmove",$forumpermission['canmove']);
  makeyesnocode("Can open / close own threads","canopenclose",$forumpermission['canopenclose']);
  makeyesnocode("Can delete own threads","candeletethread",$forumpermission['candeletethread']);

  makeyesnocode("Can reply to own threads","canreplyown",$forumpermission['canreplyown']);
  makeyesnocode("Can reply to other's threads","canreplyothers",$forumpermission['canreplyothers']);

  makeyesnocode("Can view others' threads","canviewothers",$forumpermission['canviewothers']);

  makeyesnocode("Can edit own posts","caneditpost",$forumpermission['caneditpost']);
  makeyesnocode("Can delete own posts","candeletepost",$forumpermission['candeletepost']);

  doformfooter("Save Changes");

}

// ###################### Start do update #######################
if ($_POST['action']=="doupdate") {

  if ($useusergroup) {
    // use usergroup defaults. delete forumpermission if it exists
    if (isset($forumpermissionid)) {
      $DB_site->query("DELETE FROM forumpermission WHERE forumpermissionid=$forumpermissionid");
    }
  } else {

    if (isset($forumid)) {
      $DB_site->query("INSERT INTO forumpermission
                         (forumpermissionid,usergroupid,forumid,canview,cansearch,canemail,canpostnew,canmove,canopenclose,candeletethread,canreplyown,canreplyothers,canviewothers,caneditpost,candeletepost,canpostpoll,canvote,canpostattachment,cangetattachment)
                       VALUES
                          (NULL,$usergroupid,$forumid,$canview,$cansearch,$canemail,$canpostnew,$canmove,$canopenclose,$candeletethread,$canreplyown,$canreplyothers,$canviewothers,$caneditpost,$candeletepost,$canpostpoll,$canvote,$canpostattachment,$cangetattachment)");
    } else {
      $DB_site->query("UPDATE forumpermission SET usergroupid=$usergroupid,canview=$canview,cansearch=$cansearch,canemail=$canemail,canpostnew=$canpostnew,canmove=$canmove,canopenclose=$canopenclose,candeletethread=$candeletethread,canreplyown=$canreplyown,canreplyothers=$canreplyothers,canviewothers=$canviewothers,caneditpost=$caneditpost,candeletepost=$candeletepost,canpostpoll=$canpostpoll,canvote=$canvote,canpostattachment=$canpostattachment,cangetattachment=$cangetattachment WHERE forumpermissionid=$forumpermissionid");
    }
  }
  echo "<p>Record updated!</p>";

  $action="modify";

}

// ###################### Start function displayforums #######################
function displayforums($parentid=-1) {
  global $DB_site,$session;

  $forums=$DB_site->query("SELECT forumid,title,displayorder FROM forum WHERE parentid=$parentid ORDER BY displayorder");

  while ($forum=$DB_site->fetch_array($forums)) {

    echo "<li><b>$forum[title]</b>";

    $forummoderators=$DB_site->query("SELECT moderator.moderatorid,user.userid,user.username,user.usergroupid FROM moderator,user WHERE moderator.userid=user.userid AND moderator.forumid=$forum[forumid]");

    if ($DB_site->num_rows($forummoderators)) {
      echo " - Moderators:\n";

      $first = 1;
      while ($moderator=$DB_site->fetch_array($forummoderators)) {
        if (!$first) {
          echo ", ";
        } else {
          $first = 0;
        }
        echo "<a href=\"forum.php?s=$session[sessionhash]&amp;action=editmoderator&amp;moderatorid=$moderator[moderatorid]\">$moderator[username]</a>";
      }
      echo "\n";
    }

    echo "<ul>\n";
    $usergroups=$DB_site->query("SELECT usergroupid,title FROM usergroup ORDER BY title");
    while ($usergroup=$DB_site->fetch_array($usergroups)) {
      if ($forumpermission=$DB_site->query_first("SELECT forumpermissionid FROM forumpermission WHERE usergroupid=$usergroup[usergroupid] AND forumid=$forum[forumid]")) {
        echo "<li><font color=\"red\">$usergroup[title]</font> ".
					makelinkcode("edit","forumpermission.php?s=$session[sessionhash]&amp;action=edit&amp;forumpermissionid=$forumpermission[forumpermissionid]").
					"</li>\n";
      } else {
        $forumslist=getforumlist($forum['forumid'],"forumid");
        if ($checkfp=$DB_site->query_first("SELECT forumpermissionid FROM forumpermission WHERE $forumslist AND usergroupid=$usergroup[usergroupid]")) {
          echo "<li><font color=\"blue\">$usergroup[title]</font> ".
						makelinkcode("edit","forumpermission.php?s=$session[sessionhash]&amp;action=edit&amp;forumid=$forum[forumid]&amp;usergroupid=$usergroup[usergroupid]").
						"</li>\n";
        } else {
          echo "<li>$usergroup[title] ".
						makelinkcode("edit","forumpermission.php?s=$session[sessionhash]&amp;action=edit&amp;forumid=$forum[forumid]&amp;usergroupid=$usergroup[usergroupid]").
						"</li>\n";
        }
      }
    }
    displayforums($forum['forumid']);
    echo "</ul>\n";

    echo "</li>\n";
  }
}

// ###################### Start modify #######################
if ($action=="modify") {

?>
<p><b>Key:</b><br>
Black: use usergroup defaults<br>
<font color="red">Red: using custom permissions for this forum</font><br>
<font color="blue">Blue: using custom permissions inherited from parent forum</font></p>
<?php

  echo "<ul>\n";
  displayforums(-1);
  echo "</ul>\n";

  echo "<p>That's all folks!</p>";

}

cpfooter();
?>