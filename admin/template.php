<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

//suppress gzipping
$nozip=1;

function dotemplatejavascript() {

	$buttonextra="
<SCRIPT LANGUAGE=\"JavaScript\">
function displayHTML() {
var inf = document.name.template.value;
win = window.open(\", \", 'popup', 'toolbar = no, status = no, scrollbars=yes');
win.document.write(\"\" + inf + \"\");
}
function HighlightAll() {
	var tempval=eval(\"document.name.template\")
	tempval.focus()
	tempval.select()
	if (document.all){
	therange=tempval.createTextRange()
	therange.execCommand(\"Copy\")
	window.status=\"Contents highlighted and copied to clipboard!\"
	setTimeout(\"window.status=''\",1800)
	}
}
var NS4 = (document.layers);    // Which browser?
var IE4 = (document.all);
var win = window;    // window to search.
var n   = 0;

function findInPage(str) {
  var txt, i, found;
  if (str == '')
    return false;
  if (NS4) {
    if (!win.find(str))
      while(win.find(str, false, true))
        n++;
    else
      n++;
    if (n == 0)
      alert('Not found.');
  }

  if (IE4) {
    txt = win.document.body.createTextRange();
    for (i = 0; i <= n && (found = txt.findText(str)) != false; i++) {
      txt.moveStart('character', 1);
      txt.moveEnd('textedit');
    }
    if (found) {
      txt.moveStart('character', -1);
      txt.findText(str);
      txt.select();
      txt.scrollIntoView();
      n++;
    } else {
      if (n > 0) {
        n = 0;
        findInPage(str);
      }
      else
        alert('Not found.');
    }
  }
  return false;
}
</script>
<input name='string' type='text' accesskey='t' size=20 onChange='n=0;'>
<input type='button' value='Find' accesskey='f' onClick='javascript:findInPage(document.name.string.value)'>&nbsp;&nbsp;&nbsp;
<input type='button' value='Preview' accesskey='p' onclick='javascript:displayHTML()'>
<input type='button' value='Copy' accesskey='c' onclick='javascript:HighlightAll()'>";

	makelabelcode("",$buttonextra);
}

if ($_POST['action']) {
	$action = $_POST['action'];
} else if ($_GET['action']) {
	$action = $_GET['action'];
}

if ($action=="customize") $action="add";

if (isset($action) and $action=="generate") {
  $noheader=1;
}

require("./global.php");

adminlog(iif($templateid!=0,"template id = $templateid",iif($templatesetid!=0,"templateset id = $templatesetid","")));

// ###################### Start generate file #######################
if ($_POST['action']=="generate") {
  if (!isset($templatesetid)) {
    $templatesetid=-1;
  }

  header("Content-type: unknown/unknown");
  header("Content-disposition: filename=template.php");

  if ($title!="") {
    $titleinsert=" AND title='".vb_escape($title)."'";
  }

  $templates=$DB_site->query("SELECT * FROM template WHERE title<>'options' AND templatesetid='$templatesetid' $titleinsert ORDER BY title");

//  echo "<? php\n\n";
//  echo "require(\"global.php\");\n";
//  echo "echo \"<html><body><p>Adding templates</p>\";\n";

  if ($newset==0) {
    echo "\$DB_site->query(\"DELETE FROM template WHERE title<>'options' AND templatesetid='$templatesetid'\");\n";
  } else {
    echo "\$DB_site->query(\"INSERT INTO templateset (templatesetid,title) VALUES (NULL,'".vb_escape($newsetname)."')\");\n\$newsetid=\$DB_site->insert_id();\n";
  }
  while ($template=$DB_site->fetch_array($templates)) {
    $temp=addslashes($template['template']);
    $temp=str_replace("\$","\\\$",$temp);
    $temp=str_replace("\r","",$temp);
//    $temp=str_replace("'","'",$temp);
    if ($newset==0) {
      echo "\$DB_site->query(\"INSERT INTO template (templateid,templatesetid,title,template) VALUES (NULL,'$templatesetid','".vb_escape($template['title'])."','$temp')\");\n";
    } else {
      echo "\$DB_site->query(\"INSERT INTO template (templateid,templatesetid,title,template) VALUES (NULL,'\$newsetid','".vb_escape($template['title'])."','$temp')\");\n";
    }
  }

//  echo "echo \"<p>Templates added correctly</p>\";\n? >\n</body></html>";

//  echo "\n? >\n";

  exit;
}

cpheader();

if (isset($action)==0) {
  $action="modify";
}

// ###################### Start add #######################
if ($action=="add") {

  doformheader("template","insert");
  maketableheader("Add new template");

  if (isset($title)) {
    $title=urldecode($title);
    $templateinfo=$DB_site->query_first("SELECT template FROM template WHERE templatesetid=-1 AND title='".vb_escape($title)."'");
    $template=$templateinfo['template'];
  }

  makeinputcode("Template name","title",$title);
  makechoosercode("Template set","templatesetid","templateset",iif(isset($templatesetid),$templatesetid,-1),iif($debug,"All - global to all template sets",""));
  maketextareacode("Template<br><br><font size='1'>".iif(isset($title),makelinkcode("view default template","template.php?s=$session[sessionhash]&amp;action=view&amp;title=$title",1)."</font>",""),"template",$template,25,80);

	dotemplatejavascript();

  makehiddencode("group", "$group");
  doformfooter("Save");

}

// ###################### Start insert #######################
if ($_POST['action']=="insert") {

  if (trim($title) != "") {
    if (!$preexists=$DB_site->query_first("SELECT templateid FROM template WHERE title='".vb_escape($title)."' AND templatesetid='$templatesetid'")) {
      $result = $DB_site->query("INSERT INTO template (templateid,templatesetid,title,template) VALUES (NULL,'$templatesetid','".vb_escape("$title")."','".vb_escape("$template")."')");
  	  $templateid = $DB_site->insert_id($result);
    } else {
      $DB_site->query("UPDATE template SET template='".vb_escape($template)."' WHERE templatesetid='$templatesetid' AND title='".vb_escape($title)."'");
    }
    echo "<p>Record added successfully!</p>";
    $action="modify";
    $expandset=$templatesetid;
  } else {
    echo "<p>You forgot a title for this template!</p>";
  }

}

// ###################### Start edit #######################
if ($action=="edit") {
  $templates=$DB_site->query("SELECT templateid,templatesetid,title,template FROM template WHERE templateid=$templateid");
  $template=$DB_site->fetch_array($templates);

  doformheader("template","doupdate");
  maketableheader("Edit template");
  makehiddencode("templateid",$template['templateid']);
  makehiddencode('orig_templatesetid', $template['templatesetid']);

  makeinputcode("Template name","title",$template['title']);
  makechoosercode("Template set","templatesetid","templateset",$template['templatesetid'],iif($debug,"All - global to all template sets",""));
  maketextareacode("Template<br><br><font size='1'>".makelinkcode("view default template","template.php?s=$session[sessionhash]&amp;action=view&amp;title=$template[title]",1)."</font>","template",$template['template'],25,80);

	dotemplatejavascript();

  makehiddencode("group", "$group");
  doformfooter("Save Changes");

}

// ###################### Start view #######################
if ($action=="view") {
  $templates=$DB_site->query("SELECT templateid,templatesetid,title,template FROM template WHERE templatesetid=-1 AND title='".urldecode($title)."'");
  $template=$DB_site->fetch_array($templates);

  doformheader("","");
  maketableheader("View Default template");
  maketextareacode($template['title'],"",$template['template'],20,80);
  echo "</table>\n</td></tr></table></form>";

}

// ###################### Start do update #######################
if ($_POST['action'] == "doupdate") {

	$templateid = intval($templateid);
	$templatesetid = intval($templatesetid);
	$orig_templatesetid = intval($orig_templatesetid);
	$title = vb_escape(trim($title));
	$template = vb_escape($template);

	// if we are changing templateset id...
	if ($templatesetid != $orig_templatesetid)
	{
		// if destination template set already has a customized version of this template, update THAT version
		if ($check = $DB_site->query_first("SELECT templateid FROM template WHERE title = '$title' AND templatesetid = $templatesetid"))
		{
			$templateid = $check['templateid'];
			$DB_site->query("
				UPDATE template SET
					templatesetid = $templatesetid,
					title = '$title',
					template= '$template'
				WHERE templateid = $templateid
			");
		}
		// if destination template set does NOT already have a customized version, insert a new one
		else
		{
			$DB_site->query("
				INSERT INTO template
					(templateid, templatesetid, title, template)
				VALUES
					(NULL, $templatesetid, '$title', '$template')
			");
			$templateid = $DB_site->insert_id();
		}
	}
	// we are not changing template sets, so just update the version we edited.
	else
	{	
		$DB_site->query("
			UPDATE template SET
				templatesetid = $templatesetid,
				title = '$title',
				template= '$template'
			WHERE templateid = $templateid
		");	
	}
	
	echo "<p>Done!</p>";
	$action = "modify";
	$expandset = $templatesetid;
}

// ###################### Start Remove #######################

if ($action=="remove") {

	doformheader("template","kill");
	makehiddencode("templateid",$templateid);
	makehiddencode("group",$group);
	maketableheader("Confirm deletion");
	makedescription("Are you sure you want to delete (revert) this template?");
	doformfooter("Yes","",2,"No");

}

// ###################### Start Kill #######################

if ($_POST['action']=="kill") {

  $t=$DB_site->query_first("SELECT templatesetid FROM template WHERE templateid=$templateid");
  $DB_site->query("DELETE FROM template WHERE templateid=$templateid");

  echo "<p>Done!</p>";
  $action="modify";
  $expandset=$t['templatesetid'];
}

// ###################### Start add templateset #######################
if ($action=="addset") {

  doformheader("template","insertset");
  maketableheader("Add new template set");
  makehiddencode("group", "$group");

  makeinputcode("Title","title");

  doformfooter("Save");
}

// ###################### Start insert templateset #######################
if ($_POST['action']=="insertset") {

  $DB_site->query("INSERT INTO templateset (templatesetid,title) VALUES (NULL,'".vb_escape($title)."')");
  $tset = $DB_site->insert_id();

  $action="modify";
  $expandset=$tset;

  echo "<p>Record added</p>";

}

// ###################### Start edit templateset #######################
if ($action=="editset") {

  $templateset=$DB_site->query_first("SELECT title FROM templateset WHERE templatesetid=$templatesetid");

  doformheader("template","doupdateset");
  maketableheader("Edit template set");
  makehiddencode("templatesetid","$templatesetid");
  makehiddencode("group", "$group");

  makeinputcode("Title","title",$templateset['title']);

  doformfooter("Save Changes");

}

// ###################### Start do update templateset #######################
if ($_POST['action']=="doupdateset") {

  $DB_site->query("UPDATE templateset SET title='".vb_escape($title)."' WHERE templatesetid=$templatesetid");

  echo "<p>Record updated!</p>";

  $action="modify";
  $expandset=$templatesetid;

}
// ###################### Start Remove templateset #######################

if ($action=="removeset") {

	doformheader("template","killset");
	makehiddencode("templatesetid",$templatesetid);
	maketableheader("Confirm deletion");
	makedescription("Are you sure you want to delete this template set? Doing so will also delete all templates in this set!");
	doformfooter("Yes","",2,"No");

}

// ###################### Start Kill templateset #######################

if ($_POST['action']=="killset") {

  $DB_site->query("DELETE FROM templateset WHERE templatesetid=$templatesetid");
  $DB_site->query("DELETE FROM template WHERE templatesetid=$templatesetid");
  if (!$min=$DB_site->query_first("SELECT MIN(templatesetid) AS min FROM templateset")) {
    $min['min']=1;
  }
  $DB_site->query("UPDATE style SET templatesetid=$min[min] WHERE templatesetid=$templatesetid");

  echo "<p>Deleted!</p>";

  $action="modify";
}

// ###################### Start Modify #######################
if ($action=="modify") {
//include "ktemplate.php";

  unset($only);
  $only['calendar'] = 'Calendar';
  $only['emailsubject'] = 'Email Subject';
  $only['email'] = 'Email';
  $only['error'] = 'Error Message';
  $only['faq'] = 'FAQ';
  $only['forumdisplay'] = 'Forum Display';
  $only['forumhome'] = 'Forum Home Page';
  $only['getinfo'] = 'User Info Display';
  $only['memberlist'] = 'Member List';
  $only['modify'] = 'User Option';
  $only['new'] = 'New Posting';
  $only['pagenav'] = 'Page Navigation';
  $only['poll'] = 'Polling';
  $only['postbit'] = 'Postbit';
  $only['priv'] = 'Private Messaging';
  $only['redirect'] = 'Redirection Message';
  $only['register'] = 'Registration';
  $only['search'] = 'Search';
  $only['showthread'] = 'Show Thread';
  $only['subscribe'] = 'Subscribed Thread';
  $only['threads'] = 'Thread Management';
  $only['usercp'] = 'User Control Panel';
  $only['vbcode'] = 'vB Code';
  $only['whosonline'] = 'Who\'s Online';
  $only['showgroup'] = 'Show Groups';

  echo "<p>Templates with <span class=\"gc\">this color</span> names are using the original templates that are included by default with vBulletin and may only be added in order to create a custom template.<br>\nTemplates with <span class=\"cc\">this color</span> names are custom templates you have edited and may be re-edited or reverted back to their original version.</p>

  <p>To edit an <span class=\"gc\">original template</span>, click the \"change original\" link. To edit any other template, click the \"edit\" link. To delete a template, click the \"revert to original\" link</p>

  <p>Please note that not all related templates may be contained within a group, as grouping is only done alphabetically. If you can't find a template, try the template search utility or turn on \"Add template name in comments\" in your options!</p>";

  echo "<ul>";

  if (!$expandset) {
    $expandset=-1;
  }

  if ($debug) {
		if (isset($searchstring)) {
			$sqlinsert=" AND INSTR(template,'".vb_escape($searchstring)."')>0 ";
		}
		// display global templates
		echo "<li><b>Global templates</b> ".makelinkcode("add custom template","template.php?s=$session[sessionhash]&amp;action=add&amp;templatesetid=-1").
		makelinkcode("show all","template.php?s=$session[sessionhash]&amp;action=modify&amp;expandset=-1&amp;group=all").
      makelinkcode("collapse groups","template.php?s=$session[sessionhash]&amp;action=modify&amp;expandset=-1").
      "<ul>\n";

		$templates=$DB_site->query("SELECT templateid,title FROM template WHERE templatesetid=-1 $sqlinsert AND title<>'options' ORDER BY title");
		while ($template=$DB_site->fetch_array($templates)) {

		  $under = 0;
        reset($only);
        while(list($text,$display)=each($only)) {
          if (strpos(" $template[title]", $text)==1) {
            $under = $display;
            $shortname = $text;
            break;
          }
        }

        if (!$searchstring or ($searchstring and ($template['globalcontain']>0 or $template['localcontain']>0))) {
          if ($under) {
            if ($lastunder!=$under and $lastunder and $shrink) {
              echo "</ul></li>\n";
              $shrink = 0;
            }

            $lastunder = $under;
            if (!$shrink) {
              echo "<li><a name=\"".urlencode($shortname)."\"><b></a>$under Templates <a href=\"template.php?s=$session[sessionhash]&amp;action=modify&amp;expandset=$templateset[templatesetid]&amp;group=".urlencode($shortname)."#".urlencode($shortname)."\">[expand]</a></b>\n<ul>\n";
            }
            $shrink = 1;
            if ($group!=$shortname and $group!="all") {
              continue;
            }
          } else {
            if ($shrink) {
              echo "</ul></li>\n";
            }
            $shrink = 0;
          }
        }

			echo "<li>$template[title]".
				makelinkcode("edit","template.php?s=$session[sessionhash]&amp;action=edit&amp;templateid=$template[templateid]&amp;group=$group").
				makelinkcode("remove","template.php?s=$session[sessionhash]&amp;action=remove&amp;templateid=$template[templateid]&amp;group=$group").
				"</li>\n";

		}
		echo "</ul></li>\n";
  }

	if (isset($searchstring)) {
		$sqlinsert=" AND INSTR(t1.template,'".vb_escape($searchstring)."')>0 ";
		$expandset=0;
	}
  // do the rest of the templates
  $templatesets=$DB_site->query("SELECT templatesetid,title FROM templateset");
  while ($templateset=$DB_site->fetch_array($templatesets)) {
    $donecustom=0;
    $donedefault=0;

    echo "<li><b>$templateset[title]</b>".
      makelinkcode("edit","template.php?s=$session[sessionhash]&amp;action=editset&amp;templatesetid=$templateset[templatesetid]&amp;group=$group").
      makelinkcode("remove","template.php?s=$session[sessionhash]&amp;action=removeset&amp;templatesetid=$templateset[templatesetid]").
      makelinkcode("add template","template.php?s=$session[sessionhash]&amp;action=add&amp;templatesetid=$templateset[templatesetid]&amp;group=$group").
      makelinkcode("show all","template.php?s=$session[sessionhash]&amp;action=modify&amp;expandset=$templateset[templatesetid]&amp;group=all").
      makelinkcode("collapse groups","template.php?s=$session[sessionhash]&amp;action=modify&amp;expandset=$templateset[templatesetid]").
      "<ul>\n";

    if ($expandset and $expandset!=$templateset['templatesetid']) {
      echo "<li><b>".
      makelinkcode("expand list","template.php?s=$session[sessionhash]&amp;action=modify&amp;expandset=$templateset[templatesetid]").
      "</b></li>\n";
      echo "</ul></li>\n";
      continue;
    }

    $templates=$DB_site->query("SELECT t1.* FROM template AS t1 LEFT JOIN template AS t2 ON (t1.title=t2.title AND t2.templatesetid=-1) WHERE t1.templatesetid=$templateset[templatesetid] $sqlinsert AND t1.title<>'options' AND ISNULL(t2.templateid) ORDER BY t1.title");
    while ($template=$DB_site->fetch_array($templates)) {
      if (!$donecustom) {
        $donecustom=1;
        echo "<b>Custom templates</b>";
      }

			echo "<li><span class='cc'>$template[title]</span>".
				makelinkcode("edit","template.php?s=$session[sessionhash]&amp;action=edit&amp;templateid=$template[templateid]&amp;group=$group").
				makelinkcode("remove","template.php?s=$session[sessionhash]&amp;action=remove&amp;templateid=$template[templateid]&amp;group=$group").
				"</li>\n";

    }

    $templates=$DB_site->query("SELECT t1.title AS title,t2.templateid, NOT ISNULL(t2.templateid) AS found" . iif(isset($searchstring), ",INSTR(t1.template,'".vb_escape($searchstring)."') AS globalcontain,INSTR(t2.template,'".vb_escape($searchstring)."') AS localcontain","")."
    							FROM template AS t1
    							LEFT JOIN template AS t2 ON (t1.title=t2.title AND t2.templatesetid=$templateset[templatesetid])
    							WHERE t1.templatesetid=-1 AND t1.title<>'options'
    							ORDER BY t1.title");

    while ($template=$DB_site->fetch_array($templates)) {

      if (!$donedefault and $donecustom) {
        $donedefault=1;
        echo "<br><b>Default templates</b>";
      }

      $under = 0;
      reset($only);
      while(list($text,$display)=each($only)) {
        if (strpos(" $template[title]", $text)==1) {
          $under = $display;
          $shortname = $text;
          break;
        }
      }

      if (!$searchstring or ($searchstring and ($template['globalcontain']>0 or $template['localcontain']>0))) {
        if ($under) {
          if ($lastunder!=$under and $lastunder and $shrink) {
            echo "</ul></li>\n";
            $shrink = 0;
          }

          $lastunder = $under;
          if (!$shrink) {
            echo "<li><a name=\"".urlencode($shortname)."\"><b></a>$under Templates <a href=\"template.php?s=$session[sessionhash]&amp;action=modify&amp;expandset=$templateset[templatesetid]&amp;group=".urlencode($shortname)."#".urlencode($shortname)."\">[expand]</a></b>\n<ul>\n";
          }
          $shrink = 1;
          if ($group!=$shortname and $group!="all") {
            continue;
          }
        } else {
          if ($shrink) {
            echo "</ul></li>\n";
          }
          $shrink = 0;
        }
      }

      if ($template['found']) {
        if (!$searchstring or ($searchstring and $template['localcontain']>0)) {
          echo "<li><span class='cc'>$template[title]</span>".
          makelinkcode("edit","template.php?s=$session[sessionhash]&amp;action=edit&amp;templateid=$template[templateid]&amp;group=$group").
          makelinkcode("revert to original","template.php?s=$session[sessionhash]&amp;action=remove&amp;templateid=$template[templateid]&amp;group=$group").
					makelinkcode("view original","template.php?s=$session[sessionhash]&amp;action=view&amp;title=".urlencode($template['title']),1).
          "</li>\n";
        }
      } else {
        if (!$searchstring or ($searchstring and $template['globalcontain']>0)) {
          echo "<li><span class='gc'>$template[title]</span>".
					makelinkcode("change original","template.php?s=$session[sessionhash]&amp;action=add&amp;templatesetid=$templateset[templatesetid]&amp;title=".urlencode($template['title'])."&amp;group=$group").
					"</li>";
		  }
      }

    }

    if ($shrink) {
      echo "</ul></li>\n";
    }
    $shrink = 0;

    echo "</ul></li>\n";

  }

  echo "</ul><p>That's all folks</p>";
/**/
}

// ###################### Start search #######################
if ($action=="search") {

?>
<script language="javascript">
function confirmaction(theform) {
	if ((theform.searchstring.value=="") || (theform.replacestring.value=="")) {
		theform.searchstring.select();
		return confirm("WARNING:\n\nYou have chosen to perform a FIND/REPLACE operation on all your templates,\nbut at least one of the fields is empty.\n\nAre you SURE?");
	} else {
		return true;
	}
}
</script>
<?php

  doformheader("template","modify");
  maketableheader("Search for string:");

  makeinputcode("Search string","searchstring");
  makehiddencode("group", "all");
  doformfooter("Find String");

  doformheader("template","replace",0,1,"srform\" onSubmit=\"return confirmaction(this)");
  maketableheader("Search and replace ALL templates. Be careful!");

  makeinputcode("Search string","searchstring");
  makeinputcode("Replace string","replacestring");
  doformfooter("Find/Replace");

}

// ###################### Start dosearch #######################
if ($_POST['action']=="replace") {
  if (!$searchstring) {
    echo "Need to enter a search string to do a search & replace";
    exit;
  }

  echo "<p>Doing search for '".htmlspecialchars($searchstring)."' and replacing with '".htmlspecialchars($replacestring)."'.</p>\n";

  $templates=$DB_site->query("SELECT templateid,template,title FROM template WHERE templatesetid<>-1");
  while ($template=$DB_site->fetch_array($templates)) {

    $newtemplate=str_replace($searchstring,$replacestring,$template['template']);
    if ($newtemplate!=$template['template']) {
      $DB_site->query("UPDATE template SET template='".vb_escape($newtemplate)."' WHERE templateid=$template[templateid]");

      echo "<p>Updated template $template[title], id $template[templateid]".
        makelinkcode("edit","template.php?s=$session[sessionhash]&amp;action=edit&amp;templateid=$template[templateid]").
        "</p>\n";

    }

  }

  echo "<p>All done!</p>";

}

// ###################### Start function getprop #######################
function getprop ($text,$prop) {

  $prop.="=\"";

  $text=strtolower($text);
  $prop=strtolower($prop);

  if (strpos($text,$prop)>0) {
    $firstbit=substr($text,strpos($text,$prop)+strlen($prop));
    $retval=substr($firstbit,0,strpos($firstbit,"\""));
  } else {
    $retval="";
  }

  return $retval;

}

// ###################### Start img tags #######################
if ($action=="imgtags") {

  echo "<p>Doing this will parse ALL templates for &lt;img&gt; tags, and insert in any it can the neccessary width and height tags. This will work with any tags where the file name is actually specified, either as a relative path, or as a URL.</p>";
  echo "<p>We recommend that you back up your templates before going ahead and doing it.</p>";

  echo "<p><a href=\"template.php?s=$session[sessionhash]&amp;action=doimgtags\">Click here to do it!</a>";
}

// ###################### Start do img tags #######################
if ($action=="doimgtags") {

  $templates=$DB_site->query("SELECT templateid,title,template FROM template ORDER BY title");
  while ($template=$DB_site->fetch_array($templates)) {


    if (@strpos($template['template'],"<img")) {
      $bits=explode("<img",$template['template']);

      list($key,$val)=each($bits);
      $temp=$val;

      while (list($key,$val)=each($bits)) {
        $aftertag=substr(strstr($val,">"),1);
        $tag=" ".substr($val,0,strlen($val)-strlen($aftertag));

        $source=getprop($tag,"src");
        if (!strpos($source,"://")) {
          // protocol not specified
          // relative file path given
          $source="../".$source;
        }

        if ($imginfo=@getimagesize($source) and !strpos($tag,"vb_noparse_vb")) {
          $newwidth=$imginfo[0];
          $newheight=$imginfo[1];

          $tag=eregi_replace("width=\"[0-9]*\"","",$tag);
          $tag=eregi_replace("width=[0-9]*","",$tag);
          $tag=eregi_replace("height=\"[0-9]*\"","",$tag);
          $tag=eregi_replace("height=[0-9]*","",$tag);

          $dimensions=" width=\"$newwidth\" height=\"$newheight\" ";

        } else {
          $dimensions="";
        }

        // remove double spaces
        while (strpos($tag,"  ") or substr($tag,0,2)=="  ") {
          $tag=str_replace("  "," ",$tag);
        }

        $temp.="<img$dimensions$tag$aftertag";
      }
    } else {
      $temp=$template['template'];
    }
    if ($temp!=$template['template']) {
      echo "<p>Done $template[title]</p>\n";
      $DB_site->query("UPDATE template SET template='".vb_escape($temp)."' WHERE templateid=$template[templateid]");
    }
  }
}

// ###################### Start downloadset #######################
if ($action=="downloadset") {
  doformheader("template","generate");
  maketableheader("Download template set");

  makechoosercode("Template set","templatesetid","templateset",-1,"Global template set");
  makeyesnocode("Create new set from downloaded templates?<br>(Selecting no will download them into<BR>the same set they came from)","newset",0);
  makeinputcode("New set's name:<br>(If creating a new set)", "newsetname");
  doformfooter("Download");
}

// ###################### Start uploadset #######################
if ($action=="uploadset") {
  doformheader("template","douploadset",1);
  maketableheader("Upload template set");

  makeuploadcode("Upload template data","upload");
  doformfooter("Upload");
}

// ###################### Start douploadset #######################
if ($_POST['action']=="douploadset") {
  $templatedata=readfromfile($upload);

  eval($templatedata);

  echo "<P>Done</p>";
}
cpfooter();
?>
