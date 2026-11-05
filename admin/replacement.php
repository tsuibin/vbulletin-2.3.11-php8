<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

require("./global.php");

adminlog(iif($replacementid!=0,"replacement id = $replacementid",iif($replacementsetid!=0,"replacementset id = $replacementsetid","")));

cpheader();

if (isset($action)==0) {
  $action="modify";
}

// ###################### Start Add #######################
if ($action=="add") {

  doformheader("replacement","insert");
  maketableheader("Add new replacement variable");

  if (isset($findword)) {
    $findword=urldecode($findword);
    $replacementinfo=$DB_site->query_first("SELECT replaceword FROM replacement WHERE replacementsetid=-1 AND findword='".vb_escape($findword)."'");
    $replaceword=$replacementinfo['replaceword'];
  }

  makechoosercode("Replacement set","replacementsetid","replacementset",iif(isset($replacementsetid),$replacementsetid,-1),iif($debug,"All - global to all replacement sets",""));
  makeinputcode("Code to find","findword",$findword,1,60);
  maketextareacode("Code to insert","replaceword",$replaceword,3,60);
  doformfooter("Save");

}

// ###################### Start Insert #######################
if ($_POST['action']=="insert") {

  if (!$preexists=$DB_site->query_first("SELECT replacementid FROM replacement WHERE findword='".vb_escape($findword)."' AND replacementsetid='$replacementsetid'")) {
    $DB_site->query("INSERT INTO replacement (replacementid,replacementsetid,findword,replaceword) VALUES (NULL,$replacementsetid,'".vb_escape($findword)."','".vb_escape($replaceword)."')");
  } else {
    $DB_site->query("UPDATE replacement SET replaceword='".vb_escape($replaceword)."' WHERE replacementsetid='$replacementsetid' AND findword='".vb_escape($findword)."'");
  }

  $action="modify";
  $expandset=$replacementsetid;

}

// ###################### Start Edit #######################
if ($action=="edit") {

  $found=$DB_site->query("SELECT replacementsetid,findword,replaceword FROM replacement WHERE replacementid=$replacementid");
  $replacement=$DB_site->fetch_array($found);

  doformheader("replacement","doupdate");
  maketableheader("Edit replacement variable");
  makehiddencode("replacementid",$replacementid);

  makechoosercode("Replacement set","replacementsetid","replacementset",$replacement['replacementsetid'],iif ($debug,"All - global to all replacement sets",""));
  makeinputcode("Code to find","findword",$replacement['findword'],1,60);
  maketextareacode("Code to insert","replaceword",$replacement['replaceword'],3,60);
  doformfooter("Save Changes");

}

// ###################### Start view #######################
if ($action=="view") {

  $found=$DB_site->query("SELECT replacementsetid,findword,replaceword FROM replacement WHERE replacementsetid=-1 AND findword='".urldecode($findword)."'");
  $replacement=$DB_site->fetch_array($found);

  echo "<p>Default replacement:</p>";

  echo "<p>".htmlspecialchars($replacement['findword'])." is replaced by ".htmlspecialchars($replacement['replaceword'])."</p>";

}

// ###################### Start Do Update #######################
if ($_POST['action']=="doupdate") {

  $DB_site->query("UPDATE replacement SET replacementsetid='$replacementsetid',findword='".vb_escape($findword)."',replaceword='".vb_escape($replaceword)."' WHERE replacementid='$replacementid'");
  echo "<p>Done</p>";
  $action="modify";
  $expandset=$replacementsetid;
}

// ###################### Start Remove #######################

if ($action=="remove") {

	doformheader("replacement","kill");
	makehiddencode("replacementid",$replacementid);
	maketableheader("Confirm deletion");
	makedescription("Are you sure you want to delete (revert) this replacement variable?");
	doformfooter("Yes","",2,"No");

}

// ###################### Start Kill #######################

if ($_POST['action']=="kill") {

  $r=$DB_site->query_first("SELECT replacementsetid FROM replacement WHERE replacementid=$replacementid");
  $DB_site->query("DELETE FROM replacement WHERE replacementid=$replacementid");

  echo "<p>Done!</p>";

  $action="modify";
  $expandset=$r['replacementsetid'];
}

// ###################### Start add replacementset #######################
if ($action=="addset") {

  doformheader("replacement","insertset");
  maketableheader("Add new replacement set");

  makeinputcode("Title","title","",1,60);

  doformfooter("Save");
}

// ###################### Start insert replacementset #######################
if ($_POST['action']=="insertset") {

  $DB_site->query("INSERT INTO replacementset (replacementsetid,title) VALUES (NULL,'".vb_escape($title)."')");
  $expandset=$DB_site->insert_id();

  $action="modify";

  echo "<p>Record added</p>";

}

// ###################### Start edit replacementset #######################
if ($action=="editset") {

  $replacementset=$DB_site->query_first("SELECT title FROM replacementset WHERE replacementsetid=$replacementsetid");

  doformheader("replacement","doupdateset");
  maketableheader("Edit replacement set");
  makehiddencode("replacementsetid","$replacementsetid");

  makeinputcode("Title","title",$replacementset['title'],1,60);

  doformfooter("Save Changes");

}

// ###################### Start do update replacementset #######################
if ($_POST['action']=="doupdateset") {

  $DB_site->query("UPDATE replacementset SET title='".vb_escape($title)."' WHERE replacementsetid='$replacementsetid'");

  echo "<p>Record updated!</p>";

  $action="modify";
  $expandset=$replacementsetid;

}
// ###################### Start Remove replacementset #######################

if ($action=="removeset") {

	doformheader("replacement","killset");
	makehiddencode("replacementsetid",$replacementsetid);
	maketableheader("Confirm deletion");
	makedescription("Are you sure you want to delete this replacement set? Doing so will also delete (revert) all replacement variables in this set!");
	doformfooter("Yes","",2,"No");

}

// ###################### Start Kill replacementset #######################

if ($_POST['action']=="killset") {

  $DB_site->query("DELETE FROM replacementset WHERE replacementsetid=$replacementsetid");
  $DB_site->query("DELETE FROM replacement WHERE replacementsetid=$replacementsetid");
  if (!$min=$DB_site->query_first("SELECT MIN(replacementsetid) AS min FROM replacementset")) {
    $min['min']=1;
  }
  $DB_site->query("UPDATE style SET replacementsetid=$min[min] WHERE replacementsetid=$replacementsetid");

  echo "<p>Deleted!</p>";

  $action="modify";
}

// ###################### Start Modify #######################
if ($action=="modify") {

  if (!$expandset) {
    $expandset=-1;
  }

  echo "<p>Replacements with <span class=\"gc\">this color</span> names are using the original replacements and may only be added in order to create a custom replacement.<br>\nReplacements with <span class=\"cc\">this color</span> names are custom replacements, and may be edited or reverted back to the default.</p>

  <p>To edit an original replacement, click the \"change original\" link. To edit any other replacement, click the \"edit\" link. To delete a replacement, click the \"revert to original\" link</p>";

  echo "<ul>";

  if ($debug) {
		// display global replacements
		echo "<li><b>Global replacements</b> <a href='replacement.php?s=$session[sessionhash]&amp;action=add&amp;replacementsetid=-1'>[add replacement]</a><ul>\n";

		$replacements=$DB_site->query("SELECT replacementid,findword,replaceword FROM replacement WHERE replacementsetid=-1 ORDER BY findword");
		while ($replacement=$DB_site->fetch_array($replacements)) {

			echo "<li>".htmlspecialchars($replacement['findword'])." is replaced by ".htmlspecialchars($replacement['replaceword'])." <a href='replacement.php?s=$session[sessionhash]&amp;action=edit&amp;replacementid=$replacement[replacementid]'>[edit]</a> <a href='replacement.php?s=$session[sessionhash]&amp;action=remove&amp;replacementid=$replacement[replacementid]'>[remove]</a></li>\n";

		}
		echo "</ul></li>\n";
  }

  // do the rest of the replacements
  $replacementsets=$DB_site->query("SELECT replacementsetid,title FROM replacementset");
  while ($replacementset=$DB_site->fetch_array($replacementsets)) {
    $donecustom=0;
    $donedefault=0;
    echo "<li><b>$replacementset[title]</b> <a href='replacement.php?s=$session[sessionhash]&amp;action=editset&amp;replacementsetid=$replacementset[replacementsetid]'>[edit]</a> <a href='replacement.php?s=$session[sessionhash]&amp;action=removeset&amp;replacementsetid=$replacementset[replacementsetid]'>[remove]</a> <a href='replacement.php?s=$session[sessionhash]&amp;action=add&amp;replacementsetid=$replacementset[replacementsetid]'>[add custom replacement]</a><ul>\n";

   if ($expandset and $expandset!=$replacementset['replacementsetid']) {
      echo "<li><b>".
      makelinkcode("expand list","replacement.php?s=$session[sessionhash]&amp;action=modify&amp;expandset=$replacementset[replacementsetid]").
      "</b></li>";
      echo "</ul></li>\n";
      continue;
    }


    $replacements=$DB_site->query("SELECT r1.* FROM replacement AS r1 LEFT JOIN replacement AS r2 ON (r1.findword=r2.findword AND r2.replacementsetid=-1) WHERE r1.replacementsetid=$replacementset[replacementsetid] AND ISNULL(r2.replacementsetid) ORDER BY findword");
    while ($replacement=$DB_site->fetch_array($replacements)) {
      if (!$donecustom) {
        $donecustom=1;
        echo "<b>Custom replacements</b>";
      }

      echo "<li><span class=\"cc\">".htmlspecialchars($replacement['findword'])." is replaced by ".htmlspecialchars($replacement['replaceword'])."</span>".
				makelinkcode("edit","replacement.php?s=$session[sessionhash]&amp;action=edit&amp;replacementid=$replacement[replacementid]").
				makelinkcode("remove","replacement.php?s=$session[sessionhash]&amp;action=remove&amp;replacementid=$replacement[replacementid]").
				"</li>\n";
    }

    $replacements=$DB_site->query("SELECT r1.findword AS masterfind,r2.*,NOT ISNULL(r2.replacementid) AS found FROM replacement AS r1 LEFT JOIN replacement AS r2 ON (r1.findword=r2.findword AND r2.replacementsetid=$replacementset[replacementsetid]) WHERE r1.replacementsetid=-1 ORDER BY masterfind");
    while ($replacement=$DB_site->fetch_array($replacements)) {
      if (!$donedefault and $donecustom) {
        $donedefault=1;
        echo "<br><b>Default replacements</b>";
      }

      if ($replacement['found']) {
        echo "<li><span class=\"cc\">".htmlspecialchars($replacement['findword'])." is replaced by ".htmlspecialchars($replacement['replaceword'])."</span>".
					makelinkcode("edit","replacement.php?s=$session[sessionhash]&amp;action=edit&amp;replacementid=$replacement[replacementid]").
					makelinkcode("revert to original","replacement.php?s=$session[sessionhash]&amp;action=remove&amp;replacementid=$replacement[replacementid]").
					makelinkcode("view original","replacement.php?s=$session[sessionhash]&amp;action=view&amp;findword=".urlencode($replacement['masterfind'])).
					"</li>\n";
      } else {
        echo "<li><span class=\"gc\">".htmlspecialchars($replacement['masterfind']).
					makelinkcode("change original","replacement.php?s=$session[sessionhash]&amp;action=add&amp;replacementsetid=$replacementset[replacementsetid]&amp;findword=".urlencode($replacement['masterfind'])).
					"</span></li>";
      }

    }
    echo "</ul></li>\n";

  }

  echo "</ul><p>That's all folks</p>";

}
cpfooter();
?>