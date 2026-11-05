<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

require("./global.php");

adminlog(iif($announcementid!=0,"announcement id = $announcementid",""));

// ###################### Start function displayforums #######################
function displayforums($parentid=-1) {
  global $DB_site,$session;

  $forums=$DB_site->query("SELECT forumid,title,displayorder FROM forum WHERE parentid=$parentid ORDER BY displayorder");

  while ($forum=$DB_site->fetch_array($forums)) {

    echo "<li><b>$forum[title]</b> ".makelinkcode("add announcement","announcement.php?s=$session[sessionhash]&amp;action=add&amp;forumid=$forum[forumid]")."\n";

    $forumannouncements=$DB_site->query("SELECT * FROM announcement WHERE announcement.forumid=$forum[forumid]");

    if ($DB_site->num_rows($forumannouncements)) {
      echo "<ul>Announcements:<ul>\n";

      while ($announcement=$DB_site->fetch_array($forumannouncements)) {
        echo "<li>$announcement[title] ".
					makelinkcode("edit","announcement.php?s=$session[sessionhash]&amp;action=edit&amp;announcementid=$announcement[announcementid]").
					makelinkcode("remove","announcement.php?s=$session[sessionhash]&amp;action=remove&amp;announcementid=$announcement[announcementid]").
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

  doformheader("announcement","insert");

  maketableheader("Add Announcement");
  makeinputcode("Title","title");
  makeinputcode("Start Date<br>(Format: yyyy-mm-dd hh:mm:ss)<br>The date that the announcement will run from","startdate",date("Y-m-d",time()));
  makeinputcode("End Date<br>(Format: yyyy-mm-dd hh:mm:ss)<br>The date that the announcement will run until","enddate",date("Y-m-d",time()+24*60*60*31));

  maketextareacode("Announcement","pagetext","",10,50);

  makeyesnocode("Allow HTML code","annc_allowhtmlcode",0);
  makeyesnocode("Allow vB code","annc_allowvbcode",1);
  makeyesnocode("Allow smilies","annc_allowsmilies",1);

  makeforumchooser("parentid",$forumid,-1,'','All Forums','Visible In',1);

  doformfooter("Save");
}

// ###################### Start insert #######################
if ($_POST['action']=="insert") {

  if ($annc_allowvbcode) {
    $pagetext=bbcodeparse2($pagetext,$annc_allowhtmlcode,$annc_allowvbcode,$annc_allowsmilies,$annc_allowvbcode);
  } else {
    if (!$annc_allowhtmlcode) {
      $pagetext=str_replace("&lt;","&amp;lt;",$pagetext);
      $pagetext=str_replace("&gt;","&amp;gt;",$pagetext);
      $pagetext=str_replace("<","&lt;",$pagetext);
      $pagetext=str_replace(">","&gt;",$pagetext);
      $pagetext=nl2br($pagetext);
    }

    if ($annc_allowsmilies) {
      $smilies=$DB_site->query("SELECT smilietext,smiliepath FROM smilie");
      while ($smilie=$DB_site->fetch_array($smilies)) {
        if(trim($smilie['smilietext'])!="") {
          $pagetext=str_replace(trim($smilie['smilietext']),"<img src=\"$smilie[smiliepath]\" border=\"0\" alt=\"$smilie[smilietext]\">",$pagetext);
        }
      }
    }
  }

  if (!trim($title)) {
    $title = "Announcement";
  }

  $DB_site->query("INSERT INTO announcement(announcementid,title,userid,startdate,enddate,pagetext,forumid) VALUES (NULL,'".vb_escape($title)."','$bbuserinfo[userid]',UNIX_TIMESTAMP('".vb_escape($startdate)."'),UNIX_TIMESTAMP('".vb_escape($enddate)."'),'".vb_escape($pagetext)."','$parentid')");

  $action="modify";

  echo "<p>Record added</p>";

}

// ###################### Start edit #######################
if ($action=="edit") {

  $announcement=$DB_site->query_first("SELECT title,userid,FROM_UNIXTIME(startdate) AS startdate,FROM_UNIXTIME(enddate) AS enddate,pagetext,forumid FROM announcement WHERE announcementid=$announcementid");

  doformheader("announcement","doupdate");
  makehiddencode("announcementid","$announcementid");

  maketableheader("Edit Announcement:</b> <i>$announcement[title]</i><b>","",0);
  makeinputcode("Title","title",$announcement['title']);

  makeinputcode("Start Date<br>(Format: yyyy-mm-dd hh:mm:ss)<br>The date that the announcement will run from","startdate",$announcement['startdate']);
  makeinputcode("End Date<br>(Format: yyyy-mm-dd hh:mm:ss)<br>The date that the announcement will run until","enddate",$announcement['enddate']);

  maketextareacode("Announcement","pagetext",$announcement['pagetext'],10,50);

  makeyesnocode("Allow HTML code","annc_allowhtmlcode",1);
  makeyesnocode("Allow vB code","annc_allowvbcode",0);
  makeyesnocode("Allow smilies","annc_allowsmilies",0);

  makeforumchooser("parentid",$announcement['forumid'],-1,"","All forums","Forum");

  doformfooter("Save Changes");

}

// ###################### Start do update #######################
if ($_POST['action']=="doupdate") {

  $getannc=$DB_site->query_first("SELECT pagetext FROM announcement WHERE announcementid=$announcementid");
  if (strlen($getannc['pagetext'])!=strlen($pagetext)) {
    if ($annc_allowvbcode) {
      $pagetext=str_replace("\n<br>\n","\n",$pagetext);
      $pagetext=str_replace("\r\n<br>\r\n","\r\n",$pagetext);
      $pagetext=bbcodeparse2($pagetext,$annc_allowhtmlcode,$annc_allowvbcode,$annc_allowsmilies,$annc_allowvbcode);
    } else {
      if (!$annc_allowhtmlcode) {
        $pagetext=str_replace("&lt;","&amp;lt;",$pagetext);
        $pagetext=str_replace("&gt;","&amp;gt;",$pagetext);
        $pagetext=str_replace("<","&lt;",$pagetext);
        $pagetext=str_replace(">","&gt;",$pagetext);
        $pagetext=nl2br($pagetext);
      }

      if ($annc_allowsmilies) {
        $smilies=$DB_site->query("SELECT smilietext,smiliepath FROM smilie");
        while ($smilie=$DB_site->fetch_array($smilies)) {
          if(trim($smilie['smilietext'])!="") {
            $pagetext=str_replace(trim($smilie['smilietext']),"<img src=\"$smilie[smiliepath]\" border=\"0\" alt=\"$smilie[smilietext]\">",$pagetext);
          }
        }
      }
    }
  }

  $DB_site->query("UPDATE announcement SET title='".vb_escape($title)."', startdate=UNIX_TIMESTAMP('".vb_escape($startdate)."'),enddate=UNIX_TIMESTAMP('".vb_escape($enddate)."'),pagetext='".vb_escape($pagetext)."',forumid='$parentid' WHERE announcementid='$announcementid'");

  echo "<p>Record updated!</p>";

  $action="modify";

}

// ###################### Start Remove #######################

if ($action=="remove") {

	doformheader("announcement","kill");
	makehiddencode("announcementid",$announcementid);
	maketableheader("Confirm deletion");
	makedescription("Are you sure you want to delete this announcement?");
	doformfooter("Yes","",2,"No");

}

// ###################### Start Kill #######################

if ($_POST['action']=="kill") {

  $DB_site->query("DELETE FROM announcement WHERE announcementid=$announcementid");

  echo "<p>Deleted!</p>";

  $action="modify";
}

// ###################### Start modify #######################
if ($action=="modify") {

	$forumannouncements=$DB_site->query("SELECT title,FROM_UNIXTIME(startdate) AS startdate,FROM_UNIXTIME(enddate) AS enddate,announcementid FROM announcement WHERE announcement.forumid=-1");

	if ($DB_site->num_rows($forumannouncements)) {
		doformheader("","");
		maketableheader("Global Announcements","",1,4);
		echo "<tr class='".getrowbg()."' align='center'>
			<td align='left'><font size='1'><b>Title</b></font></td>
			<td><font size='1'><b>Start Date</b></font></td>
			<td><font size='1'><b>End Date</b></font></td>
			<td><font size='1'><b>Modify</b></font></td>
		</tr>\n";
		while ($announcement=$DB_site->fetch_array($forumannouncements)) {
			echo "<tr class='".getrowbg()."'><td width=\"100%\">$announcement[title]</td><td nowrap>$announcement[startdate]</td><td nowrap>$announcement[enddate]</td><td nowrap>".
				makelinkcode("edit","announcement.php?s=$session[sessionhash]&amp;action=edit&amp;announcementid=$announcement[announcementid]").
				makelinkcode("remove","announcement.php?s=$session[sessionhash]&amp;action=remove&amp;announcementid=$announcement[announcementid]").
				"</td></tr>\n";
		}
		echo "</table></td></tr></table></form>\n";
	}

  echo "<ul>\n";
  echo makelinkcode("add announcement to all forums","announcement.php?s=$session[sessionhash]&amp;action=add")."<br><br>";
  displayforums(-1);
  echo "</ul>\n";

}

cpfooter();
?>
