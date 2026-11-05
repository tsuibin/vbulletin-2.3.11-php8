<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

require("./global.php");

adminlog(iif($usertitleid!=0,"usertitle id = $usertitleid",""));

cpheader();

if (isset($action)==0) {
  $action="modify";
}

// ###################### Start add #######################
if ($action=="add") {

  doformheader("usertitle","insert");

  maketableheader("Add New User Title");
  makeinputcode("Title","title");
  makeinputcode("Minimum posts required","minposts");

  doformfooter("Save");
}

// ###################### Start insert #######################
if ($_POST['action']=="insert") {

  $DB_site->query("INSERT INTO usertitle (usertitleid,title,minposts) VALUES (NULL,'".vb_escape($title)."','$minposts')");

  $action="modify";

  echo "<p>Record added</p>";
  echo "<p>It is recommended that you <a href=\"misc.php?s=$session[sessionhash]\">update user titles</a> when you are done.</p>";

}

// ###################### Start edit #######################
if ($action=="edit") {

  $usertitle=$DB_site->query_first("SELECT title,minposts FROM usertitle WHERE usertitleid=$usertitleid");

  doformheader("usertitle","doupdate");
  makehiddencode("usertitleid","$usertitleid");

  maketableheader("Edit User Title:</b> <i>$usertitle[title]</i>","",0);
  makeinputcode("Title","title",$usertitle['title']);
  makeinputcode("Minimum posts required","minposts",$usertitle['minposts']);

  doformfooter("Save Changes");

}

// ###################### Start do update #######################
if ($_POST['action']=="doupdate") {

  $DB_site->query("UPDATE usertitle SET title='".vb_escape($title)."',minposts='$minposts' WHERE usertitleid=$usertitleid");

  echo "<p>Record updated!</p>";

  echo "<p>It is recommended that you <a href=\"misc.php?s=$session[sessionhash]\">update user titles</a> when you are done.</p>";

  $action="modify";

}
// ###################### Start Remove #######################

if ($action=="remove") {

	doformheader("usertitle","kill");
	makehiddencode("usertitleid",$usertitleid);
	maketableheader("Confirm deletion");
	makedescription("Are you sure you want to delete this user title?");
	doformfooter("Yes","",2,"No");

}

// ###################### Start Kill #######################

if ($_POST['action']=="kill") {

  $DB_site->query("DELETE FROM usertitle WHERE usertitleid=$usertitleid");

  echo "<p>It is recommended that you <a href=\"misc.php?s=$session[sessionhash]\">update user titles</a> when you are done.</p>";

  $action="modify";
}

// ###################### Start modify #######################
if ($action=="modify") {

  $usertitles=$DB_site->query("SELECT usertitleid,title,minposts FROM usertitle ORDER BY minposts");

  echo "<ul>";

  while ($usertitle=$DB_site->fetch_array($usertitles)) {

    echo "<li>$usertitle[title] (Minimum Posts: $usertitle[minposts])".
			makelinkcode("edit","usertitle.php?s=$session[sessionhash]&amp;action=edit&amp;usertitleid=$usertitle[usertitleid]").
			makelinkcode("remove","usertitle.php?s=$session[sessionhash]&amp;action=remove&amp;usertitleid=$usertitle[usertitleid]").
			"</li>\n";

  }

  echo "</ul><p>That's all folks</p>";

}

cpfooter();
?>