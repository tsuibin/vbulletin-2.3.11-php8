<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

if ($_POST['action']) {
	$action = $_POST['action'];
} else if ($_GET['action']) {
	$action = $_GET['action'];
}

if ($action=="dodownload") {
  $noheader=1;
}

require("./global.php");

adminlog(iif($styleid!=0,"style id = $styleid",""));

// ###################### Start do download #######################
function escapepipe ($text) {
  return str_replace("|||","|| |",$text);
}

if ($action=="dodownload") {

	if (function_exists("set_time_limit")==1 and get_cfg_var("safe_mode")==0) {
		@set_time_limit(1200);
	}

  if ($styleid==-1) {
    $style['title']="Master vBulletin Style Set!!!master!!!";
    $style['replacementsetid']=-1;
    $style['templatesetid']=-1;
    $replacementset['title']="None";
    $templateset['title']="None";
  } else {
    $style=$DB_site->query_first("SELECT * FROM style WHERE styleid=$styleid");
		$replacementset=$DB_site->query_first("SELECT * FROM replacementset WHERE replacementsetid=$style[replacementsetid]");
		$templateset=$DB_site->query_first("SELECT * FROM templateset WHERE templatesetid=$style[templatesetid]");
  }

  $replacements=$DB_site->query("SELECT * FROM replacement WHERE replacementsetid=$style[replacementsetid]");
  $templates=$DB_site->query("SELECT * FROM template WHERE title<>'options' AND templatesetid=$style[templatesetid] ORDER BY title ASC");

  $code=escapepipe($templateversion)."|||";
  $code.=escapepipe($style['title'])."|||".escapepipe($replacementset['title'])."|||".escapepipe($templateset['title'])."|||";
  $code.=$DB_site->num_rows($replacements)."|||".$DB_site->num_rows($templates)."|||";

  while ($replacement=$DB_site->fetch_array($replacements)) {
    $code.=escapepipe($replacement['findword'])."|||".escapepipe($replacement['replaceword'])."|||";
  }
  while ($template=$DB_site->fetch_array($templates)) {
    $code.=escapepipe($template['title'])."|||".escapepipe($template['template'])."|||";
  }

  header("Content-disposition: filename=vbulletin.style");
  header("Content-Length: ".strlen($code));
  header("Content-type: unknown/unknown");
	header("Pragma: no-cache");
	header("Expires: 0");
  echo $code;
  exit;
}

cpheader();

if (!isset($action)) {
  $action="modify";
}

// ###################### Start Add #######################
if ($action=="add") {

  doformheader("style","insert");
  maketableheader("Add new style set");

  if ($replacementsetid==0) {
    $replacementsetid=-1;
  }
  if ($templatesetid==0) {
    $templatesetid=-1;
  }

  makeinputcode("Title","title");
  makechoosercode("Replacement Set","replacementsetid","replacementset",$replacementsetid,"Create new replacement set");
  makechoosercode("Template Set","templatesetid","templateset",$templatesetid,"Create new template set");
  makeyesnocode("User selectable?<br>(user can select this style in their profile?)","userselect",1);

  doformfooter("Save");
}

// ###################### Start Insert #######################
if ($_POST['action']=="insert") {
  if ($replacementsetid==-1) {
    $DB_site->query("INSERT INTO replacementset VALUES (NULL,'".vb_escape($title)."')");
    $replacementsetid=$DB_site->insert_id();
  }
  if ($templatesetid==-1) {
    $DB_site->query("INSERT INTO templateset VALUES (NULL,'".vb_escape($title)."')");
    $templatesetid=$DB_site->insert_id();
  }
  $DB_site->query("INSERT INTO style (styleid,title,replacementsetid,templatesetid,userselect) VALUES (NULL,'".vb_escape($title)."','$replacementsetid','$templatesetid','$userselect')");

  $action="modify";

  echo "<p>Record added</p>";

}

// ###################### Start Edit #######################
if ($action=="edit") {

  $style=$DB_site->query_first("SELECT styleid,title,replacementsetid,templatesetid,userselect FROM style WHERE styleid=$editstyleid");

  doformheader("style","doupdate");
  maketableheader("Edit Style Set:</b> <i>$style[title]</i>","",0);
  makehiddencode("editstyleid","$editstyleid");

  makeinputcode("Title","title",$style['title']);
  makechoosercode("Replacement Set","replacementsetid","replacementset",$style['replacementsetid']);
  makechoosercode("Template Set","templatesetid","templateset",$style['templatesetid']);
  makeyesnocode("User selectable?<br>(user can select this style in their profile?)","userselect",$style['userselect']);

  doformfooter("Save Changes");

}

// ###################### Start Update #######################
if ($_POST['action']=="doupdate") {

  $DB_site->query("UPDATE style SET title='".vb_escape($title)."',replacementsetid='$replacementsetid',templatesetid='$templatesetid',userselect='$userselect' WHERE styleid='$editstyleid'");

  echo "<p>Record updated!</p>";

  $action="modify";

}
// ###################### Start Remove #######################

if ($action=="remove") {

	doformheader("style","kill");
	makehiddencode("styleid",$styleid);
	maketableheader("Confirm deletion");
	makedescription("Are you sure you want to delete this style?");
	doformfooter("Yes",0);

}

// ###################### Start Kill #######################

if ($_POST['action']=="kill") {

  $DB_site->query("DELETE FROM style WHERE styleid=$styleid");

  echo "<p>Deleted!</p>";

  $action="modify";
}

// ###################### Start Modify #######################
if ($action=="modify") {

  doformheader("","");
  maketableheader("Style Sets");
  $styles=$DB_site->query("SELECT styleid,title FROM style ORDER BY title");
  while ($style=$DB_site->fetch_array($styles)) {

    echo "<tr class='".getrowbg()."'>\n<td><p>$style[title]</p></td><td><p>".
			makelinkcode("fonts/colors/etc","style.php?s=$session[sessionhash]&amp;action=styles&amp;dostyleid=$style[styleid]").
			makelinkcode("properties","style.php?s=$session[sessionhash]&amp;action=edit&amp;editstyleid=$style[styleid]",0,"edit style properties").
			makelinkcode("download","style.php?s=$session[sessionhash]&amp;action=dodownload&amp;styleid=$style[styleid]").
			iif($style['styleid']!=1,makelinkcode("remove","style.php?s=$session[sessionhash]&amp;action=remove&amp;styleid=$style[styleid]"),"").
			"</p></td></tr>\n";

  }

  echo "</table></td></tr></table>";

}

// ###################### Start download/upload #######################
if ($action=="download") {

  // get styleid
  doformheader("style","dodownload");
  maketableheader("Download one of your style sets");
  makechoosercode("Style","styleid","style",-1,iif($debug,"Global (base) style set",""));
  doformfooter("Download");

  doformheader("style","doupload",1);
  maketableheader("Upload existing style set:</b> (from your computer)<b>","",0);
  makeuploadcode("Style file: (upload from computer)","stylefile");
  makechoosercode("Overwrite existing style","oldstyleid","style",-1,"No, create a new style");
  makeyesnocode("Use style file even if it is for a different version of vBulletin","ignorestyle",0);
  echo "<tr class='secondalt'><td colspan=2><p>(if this method doesn't work, try the below method)</p></td></tr>";
  doformfooter("Upload File");

  doformheader("style","doupload");
  makehiddencode("localfile","1");
  maketableheader("Import existing style set:</b> (from local file)<b>","",0);
  makeinputcode("Style file: (local)","stylefile", "vbulletin.style");
  makechoosercode("Overwrite existing style","oldstyleid","style",-1,"No, create a new style");
  makeyesnocode("Use style file even if it is for a different version of vBulletin","ignorestyle",0);
  doformfooter("Import File");

}

// ###################### Start do upload #######################
if ($_POST['action']=="doupload") {

  if (function_exists("set_time_limit")==1 and get_cfg_var("safe_mode")==0) {
    @set_time_limit(1200);
  }
	
  if (!$localfile) {
    if ($_FILES['stylefile']) {
      $stylefile = $_FILES['stylefile']['tmp_name'];
      $stylefile_name = $_FILES['stylefile']['name'];
    }

    if ($safeupload) {
      if (function_exists("is_uploaded_file")) {
        $path = "$tmppath/$stylefile_name";
        if (is_uploaded_file($stylefile)) {
          if (move_uploaded_file($stylefile, "$path")) {
            $stylefile = $path;
          }
        }
      }
    }
  }
  $styletext=readfromfile($stylefile);
  if (!$localfile) {
    @unlink($stylefile);
  }

  if (trim($styletext)=="") {
    echo "<p>Invalid style file!</p>";
    exit;
  }

  $stylebits=explode("|||",$styletext);

  list($devnul,$styleversion)=each($stylebits);
  if ($styleversion!=$templateversion and !$ignorestyle) {
    echo "<p>The version of the style file does not match your version of vBulletin. Please obtain an updated version of vBulletin and/or the style file.</p>";
    echo "<p>Your template version: $templateversion<br>\nStyle file template version: $styleversion</p>";
    echo "<p>To use this style regardless of version, select the correct option on the previous page.</p>";
    exit;
  }

  list($devnul,$style['title'])=each($stylebits);
  list($devnul,$replacementset['title'])=each($stylebits);
  list($devnul,$templateset['title'])=each($stylebits);

  // check to see if we are installing a master template set or just a custom style set
  if (substr($style['title'],-12)=="!!!master!!!") {
		// installing a master!!
		$style['styleid']=-1;
		$style['userselect']=0;
		$style['replacementsetid']=-1;
		$style['templatesetid']=-1;

		$DB_site->query("UPDATE replacement SET replacementsetid=-3 WHERE replacementsetid=-1");
		$DB_site->query("UPDATE template SET templatesetid=-3 WHERE title<>'options' AND templatesetid=-1");
  } else {
    // installing a custom style
		if ($oldstyleid==-1) {
		  // creating a new styleset
			$style['styleid']="NULL";
			$style['userselect']=1;
			$style['replacementsetid']="NULL";
			$style['templatesetid']="NULL";

			$replacementset['replacementsetid']="NULL";
			$templateset['templatesetid']="NULL";
		} else {
		  // replacing old style set
			if ($oldstyle=$DB_site->query_first("SELECT * FROM style WHERE styleid='$oldstyleid'")) {
			  $style['styleid']=$oldstyle['styleid'];
			  $style['userselect']=$oldstyle['userselect'];
			  $style['replacementsetid']=$oldstyle['replacementsetid'];
			  $style['templatesetid']=$oldstyle['replacementsetid'];

			  $DB_site->query("DELETE FROM style WHERE styleid=$oldstyle[styleid]");
			  $DB_site->query("DELETE FROM replacementset WHERE replacementsetid=$oldstyle[replacementsetid]");
			  $DB_site->query("DELETE FROM templateset WHERE templatesetid=$oldstyle[templatesetid]");
			  $DB_site->query("DELETE FROM replacement WHERE replacementsetid=$oldstyle[replacementsetid]");
			  $DB_site->query("DELETE FROM template WHERE title<>'options' AND templatesetid=$oldstyle[templatesetid]");
			} else {
			  $style['styleid']="NULL";
			  $style['userselect']=1;
			  $style['replacementsetid']="NULL";
			  $style['templatesetid']="NULL";

			  $replacementset['replacementsetid']="NULL";
			  $templateset['templatesetid']="NULL";
			}
		}
		$DB_site->query("INSERT INTO replacementset (replacementsetid,title) VALUES ($style[replacementsetid],'".vb_escape($replacementset['title'])."')");
		if ($style['replacementsetid']=="NULL") {
			$style['replacementsetid']=$DB_site->insert_id();
		}
		$DB_site->query("INSERT INTO templateset (templatesetid,title) VALUES ($style[templatesetid],'".vb_escape($templateset['title'])."')");
		if ($style['templatesetid']=="NULL") {
			$style['templatesetid']=$DB_site->insert_id();
		}
		$DB_site->query("INSERT INTO style (styleid,title,replacementsetid,templatesetid,userselect) VALUES ($style[styleid],'".vb_escape($style['title'])."',$style[replacementsetid],$style[templatesetid],$style[userselect])");
		if ($style['styleid']=="NULL") {
			$style['styleid']=$DB_site->insert_id();
		}
  }

  // get number of replacements and templates
  list($devnull,$numreplacements)=each($stylebits);
  list($devnull,$numtemplates)=each($stylebits);

  $counter=0;
  while ($counter++<$numreplacements) {
    list($devnull,$findword)=each($stylebits);
    list($devnull,$replaceword)=each($stylebits);
    if (trim($findword)!="") {
      $DB_site->query("INSERT INTO replacement (replacementsetid,findword,replaceword) VALUES ($style[replacementsetid],'".vb_escape($findword)."','".vb_escape($replaceword)."')");
    }
  }

  $counter=0;
  while ($counter++<$numtemplates) {
    list($devnull,$title)=each($stylebits);
    list($devnull,$template)=each($stylebits);
    if (trim($title)!="") {
      $DB_site->query("INSERT INTO template (templatesetid,title,template) VALUES ($style[templatesetid],'".vb_escape($title)."','".vb_escape($template)."')");
    }
  }

  if (substr($style['title'],-12)=="!!!master!!!") {
    // successfully imported, so remove old templates
		$DB_site->query("DELETE FROM replacement WHERE replacementsetid=-3");
		$DB_site->query("DELETE FROM template WHERE title<>'options' AND templatesetid=-3");
  }

  echo "<p>Style $style[title] imported correctly!</p>";
  echo "<P>Make sure that you upload any accompanying images too!</p>";
}

// ###################### Start stylelist #######################
if ($action=="stylelist") {
  $styles=$DB_site->query("SELECT * FROM style");
  echo "<ul>\n";
  while($style=$DB_site->fetch_array($styles)) {
    echo "<li> $style[title] ". makelinkcode("edit","style.php?s=$session[sessionhash]&amp;action=styles&amp;dostyleid=$style[styleid]")."</li>";
  }
  echo "</ul>\n";
}

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

// ###################### Start dostyles #######################
if ($_POST['action']=="dostyles") {

  $styleinfo=$DB_site->query_first("SELECT templatesetid,replacementsetid FROM style WHERE styleid='$dostyleid'");

  // template stuff
  while(list($key,$val)=each($temp)) {
    if ($val!=$old[$key]) {
      if ($set["$key"] == '-1') {
        if (!$preexists=$DB_site->query_first("SELECT templateid FROM template WHERE title='".vb_escape($key)."' AND templatesetid='$styleinfo[templatesetid]'")) {
          $DB_site->query("INSERT INTO template (templateid,title,template,templatesetid) VALUES (NULL,'".vb_escape($key)."','".vb_escape($val)."','$styleinfo[templatesetid]')");
        } else {
          $DB_site->query("UPDATE template SET template='".vb_escape($val)."' WHERE title='".vb_escape($key)."' AND templatesetid='$styleinfo[templatesetid]'");
        }
      } else {
        if ($val=="") {
          $DB_site->query("DELETE FROM template WHERE title='".vb_escape($key)."' AND templatesetid='$styleinfo[templatesetid]'");
        } else {
          $DB_site->query("UPDATE template SET template='".vb_escape($val)."' WHERE title='".vb_escape($key)."' AND templatesetid='$styleinfo[templatesetid]'");
        }
      }
    }
  }

  // replacement stuff
  while(list($key,$val)=each($replace)) {
    if ($val!=$old[$key]) {
      if ($set["$key"]=='-1') {
        if (!$preexists=$DB_site->query_first("SELECT replacementid FROM replacement WHERE findword='{".vb_escape($key)."}' AND replacementsetid='$styleinfo[replacementsetid]'")) {
          $DB_site->query("INSERT INTO replacement (replacementid,replacementsetid,findword,replaceword) VALUES (NULL,'$styleinfo[replacementsetid]','{".vb_escape("$key")."}','".vb_escape($val)."')");
        } else {
          $DB_site->query("UPDATE replacement SET replaceword='".vb_escape($val)."' WHERE findword='{".vb_escape("$key")."}' AND replacementsetid='$styleinfo[replacementsetid]'");
        }
      } else {
        if ($val=="") {
          $DB_site->query("DELETE FROM replacement WHERE findword='{".vb_escape("$key")."}' AND replacementsetid='$styleinfo[replacementsetid]'");
        } else {
          $DB_site->query("UPDATE replacement SET replaceword='".vb_escape($val)."' WHERE findword='{".vb_escape("$key")."}' AND replacementsetid='$styleinfo[replacementsetid]'");
        }
      }
    }
  }

  // body tag stuff
  $body = $bodytag;
  if ($body!=$old['body']) {
    if ($set['body']=='-1') {
      if (!$preexists=$DB_site->query_first("SELECT replacementid FROM replacement WHERE findword='<body>' AND replacementsetid='$styleinfo[replacementsetid]'")) {
        $DB_site->query("INSERT INTO replacement (replacementid,replacementsetid,findword,replaceword) VALUES (NULL,'$styleinfo[replacementsetid]','<body>','".vb_escape(iif($body=="","<body>",$body))."')");
      } else {
        $DB_site->query("UPDATE replacement SET replaceword='".vb_escape($bodytag)."' WHERE findword='<body>' AND replacementsetid='$styleinfo[replacementsetid]'");
      }
    } else {
      if ($body=="") {
        $DB_site->query("DELETE FROM replacement WHERE findword='<body>' AND replacementsetid='$styleinfo[replacementsetid]'");
      } else {
        $DB_site->query("UPDATE replacement SET replaceword='".vb_escape($bodytag)."' WHERE findword='<body>' AND replacementsetid='$styleinfo[replacementsetid]'");
      }
    }
  }

function generatefont($fontface,$fontsize,$fontcolor,$fontclass,$findword) {
global $DB_site,$styleinfo,$old,$set;

	if ($findword == "largefont") { $fontstart = "<b><font"; }
	else { $fontstart = "<font"; }

	if ($fontface != "") { $newfont .= " face=\"$fontface\""; }
	if ($fontsize != "") { $newfont .= " size=\"$fontsize\""; }
	if ($fontcolor != "") { $newfont .= " color=\"$fontcolor\""; }
	if ($fontclass != "") { $newfont .= " class=\"$fontclass\""; }

	eval("\$oldfontface = \$old['".$findword."face'];");
	eval("\$oldfontsize = \$old['".$findword."size'];");
	eval("\$oldfontcolor = \$old['".$findword."color'];");
	eval("\$oldfontclass = \$old['".$findword."class'];");

	if ($oldfontface != "") { $oldfont .= " face=\"$oldfontface\""; }
	if ($oldfontsize != "") { $oldfont .= " size=\"$oldfontsize\""; }
	if ($oldfontcolor != "") { $oldfont .= " color=\"$oldfontcolor\""; }
	if ($oldfontclass != "") { $oldfont .= " class=\"$oldfontclass\""; }

	// debugging viewer:
	// makeinputcode("$findword new:","bla",$newfont,1,60);
	// makeinputcode("$findword old:","bla",$oldfont,1,60);

	if ($newfont != $oldfont) {
		if ($set[$findword] == '-1') {
			if (!$prexists = $DB_site->query_first("SELECT replacementid FROM replacement WHERE findword='<$findword' AND replacementsetid='$styleinfo[replacementsetid]'"))
			{ //echo "<li>$findword <b>case 1</b> (add custom)\n";
				$DB_site->query("INSERT INTO replacement (replacementid,replacementsetid,findword,replaceword) VALUES (NULL,'$styleinfo[replacementsetid]','<$findword','".vb_escape($fontstart.$newfont)."')");
			} else { //echo "<li>$findword <b>case 2</b> (update custom)\n";
				$DB_site->query("UPDATE replacement SET replaceword='".vb_escape($fontstart.$newfont)."' WHERE findword='<$findword' AND replacementsetid='$styleinfo[replacementsetid]'");
			}
		} else {
			if ($newfont == "") { //echo "<li>$findword <b>case 3</b> (delete custom)\n";
				$DB_site->query("DELETE FROM replacement WHERE findword='<$findword' AND replacementsetid='$styleinfo[replacementsetid]'");
			} else { //echo "<li>$findword <b>case 4</b> (update custom)\n";
				$DB_site->query("UPDATE replacement SET replaceword='".vb_escape($fontstart.$newfont)."' WHERE findword='<$findword' AND replacementsetid='$styleinfo[replacementsetid]'");
			}
		}
	}
}

generatefont($largefontface,$largefontsize,$largefontcolor,$largefontclass,"largefont");
generatefont($normalfontface,$normalfontsize,$normalfontcolor,$normalfontclass,"normalfont");
generatefont($smallfontface,$smallfontsize,$smallfontcolor,$smallfontclass,"smallfont");
generatefont($highlightface,$highlightsize,$highlightcolor,$highlightclass,"highlight");

  echo "<p>Styles updated</p>";

  $action="styles";

}

// ###################### Start styles #######################
if ($action=="styles") {

	?>
	<script language="JavaScript">
	<!--
	function updatecolor(preview,newvalue,findword) {
	  preview.style.backgroundColor = newvalue;
	}
	//-->
	</script>
	<?php

	// ********* start style editor functions *********

	function makebodytag() {
	global $styleinfo,$DB_site,$body;
		$body=$DB_site->query_first("SELECT replaceword,replacementsetid FROM replacement WHERE findword='<body>' AND (replacementsetid=-1 OR replacementsetid='$styleinfo[replacementsetid]') ORDER BY replacementsetid DESC");
		makestyleinput("Body Tag","bodytag",$body);
		makehiddencode("old[body]",$body['replaceword']);
		makehiddencode("set[body]",$body['replacementsetid']);
	}
	function maketexteditor($findword,$printname,$extra="",$rows=10,$cols=80) {
	global $styleinfo,$DB_site,$bgcounter;
		$textitem=$DB_site->query_first("SELECT template,templatesetid FROM template WHERE title='$findword' AND (templatesetid=-1 OR templatesetid='$styleinfo[templatesetid]') ORDER BY templatesetid DESC");
		echo "<tr class=\"".getrowbg()."\">\n";
		echo "<td><p>$printname</p><p><font size='1'>$extra</font></p></td>\n";
		echo "<td align='right'><textarea name=\"temp[$findword]\" rows=\"$rows\" cols=\"$cols\" wrap=\"default\" class=\""
			.iif($textitem['templatesetid']==-1,"gc","cc")."\">"
			.htmlspecialchars($textitem['template'])."</textarea><br><font size='1'>".makelinkcode("view default template","template.php?s=$session[sessionhash]&amp;action=view&amp;title=$findword",1)."</font></td>\n</tr>\n\n";
		makehiddencode("old[$findword]",$textitem['template']);
		makehiddencode("set[$findword]",$textitem['templatesetid']);
	}
	function makestyleinput($title,$name,$itemarray,$iscolor=0,$extra="") {
	global $bgcounter;
		$idvalue = ereg_replace("[\<\>\{\}]","",$itemarray['findword']);
		echo "<tr valign='top' class=\"".getrowbg()."\">\n";
		echo "<td width='50%'><p>$title".iif($extra!="","<br><font size='1'>$extra</font>","")."</p></td>\n";
		echo "<td width='50%' nowrap><input type=\"text\" name=\"$name\" class=\"".iif($itemarray['replacementsetid']==-1,"gc","cc")."\" value=\"".htmlspecialchars($itemarray['replaceword'])."\" size=\"";
		if ($iscolor) {
			echo "20\" onchange=\"updatecolor(this.form.preview$idvalue,this.value,'$idvalue')\" id=\"$idvalue\">\n";
			echo "<input type=\"button\" disabled value=\"              \" id=\"preview$idvalue\" style=\"background-color:$itemarray[replaceword]\">";
		} else {
			echo "40\">";
		}
		echo "</td></tr>\n\n";
	}

	function makestyleeditor($findword,$printname,$iscolor=0,$extra="") {
	global $styleinfo,$DB_site;
		$tofind = "{".$findword."}";
		$styleitem = $DB_site->query_first("SELECT findword,replaceword,replacementsetid FROM replacement WHERE findword='$tofind' AND (replacementsetid=-1 OR replacementsetid='$styleinfo[replacementsetid]') ORDER BY replacementsetid DESC");
		makestyleinput($printname,"replace[$findword]",$styleitem,$iscolor,$extra);
		makehiddencode("old[$findword]",$styleitem['replaceword']);
		makehiddencode("set[$findword]",$styleitem['replacementsetid']);
	}
	function makefontinput($title,$name,$value,$set) {
	global $bgcounter;
		echo "<tr class=\"".getrowbg()."\">\n<td width='50%'><p>$title</p></td>\n";
		echo "<td width='50%'><input type=\"text\" name=\"$name\" size=\"40\" class=\""
			.iif($set==-1,"gc","cc")."\" value=\"".htmlspecialchars($value)."\"></td>\n</tr>\n";
	}
	function makefonteditor($font,$findword,$printname) {
	global $styleinfo,$DB_site,$globalcolor,$customcolor;
			if ($fontclass=$DB_site->query_first("
			SELECT replaceword,replacementsetid FROM replacement
			WHERE findword='$findword' AND (replacementsetid=-1 OR replacementsetid='$styleinfo[replacementsetid]')
			ORDER BY replacementsetid DESC")) {
			maketableheader($printname.iif($fontclass['replacementsetid']!=-1,"</b> (customized)<b>",""),$font,0);
			makefontinput("$printname: face",$font."face",getprop($fontclass['replaceword'],"face"),$fontclass['replacementsetid'])
				.makehiddencode("old[".$font."face]",getprop($fontclass['replaceword'],"face"));
			makefontinput("$printname: size",$font."size",getprop($fontclass['replaceword'],"size"),$fontclass['replacementsetid'])
				.makehiddencode("old[".$font."size]",getprop($fontclass['replaceword'],"size"));
			makefontinput("$printname: color",$font."color",getprop($fontclass['replaceword'],"color"),$fontclass['replacementsetid'])
				.makehiddencode("old[".$font."color]",getprop($fontclass['replaceword'],"color"));
			makefontinput("$printname: class",$font."class",getprop($fontclass['replaceword'],"class"),$fontclass['replacementsetid'])
				.makehiddencode("old[".$font."class]",getprop($fontclass['replaceword'],"class"));
			makehiddencode("set[$font]",$fontclass['replacementsetid']);
		}
	}

	// ********* end style editor functions *********

	$styleinfo=$DB_site->query_first("SELECT templatesetid,replacementsetid,title FROM style WHERE styleid='$dostyleid'");

	echo "<p>&quot;<b>$styleinfo[title]</b>&quot;"
		.makelinkcode("edit style properties","style.php?s=$session[sessionhash]&amp;action=edit&amp;editstyleid=$dostyleid")
		.makelinkcode("edit replacements","replacement.php?s=$session[sessionhash]&amp;action=modify&amp;expandset=$styleinfo[replacementsetid]")
		.makelinkcode("edit templates","template.php?s=$session[sessionhash]&amp;action=modify&amp;expandset=$styleinfo[templatesetid]")
		."</p>\n";

	echo "<p>Make sure you keep this page refreshed and up to date after making changes! Don't use a cached version!</p>\n";
	echo "<p><font size='1'>This area of the control panel provides a more friendly interface to the templates and replacements sections. If you find that you cannot do something through this area, go directly to the templates and replacements sections. Empty a field to use the default.\n";
	echo "<br>If your browser supports it (IE4, NS6), values that are using the default values will be shown in <span class='gc'>this color</span>, while customized values will be shown in <span class='cc'>this color</span>.</font></p>";

	// make links to bookmark anchors
	echo "<table border='0'><tr valign='top'><td><b>Jump to:</b></td><td>"
		.makelinkcode("templates","#templates") .makelinkcode("page layout","#pagelayout")
		.makelinkcode("tables","#tables") .makelinkcode("main colors","#maincolors")
		."<br>"
		.makelinkcode("calendar colors","#calendarcolors") .makelinkcode("image paths","#imagepaths")
		.makelinkcode("fonts","#fonts") .makelinkcode("textareas","#textareas")
		."</td></tr></table>\n";

	doformheader("style","dostyles",0,1,"styleform");
	makehiddencode("dostyleid", $dostyleid);
	// *** templates ************************************************
	maketableheader("Templates","templates");
	maketexteditor("phpinclude","PHP parsed code:","(Don't print/echo out directly!)");
	maketexteditor("headinclude","Head Insert:","Code that is placed in &lt;head&gt; &lt;/head&gt; tags<br><br>".makelinkcode("view scrollbar color help","http://msdn.microsoft.com/workshop/samples/author/dhtml/refs/scrollbarcolor.htm",1));
	maketexteditor("header","Header:","Code that is placed just after the &lt;body&gt; tag");
	maketexteditor("footer","Footer:","Code that is placed just before the &lt;/body&gt; tag");
	/*
	maketexteditor("forumhome","Forum Home");
	maketexteditor("forumdisplay","Forum Display");
	maketexteditor("showthread","Show Thread");
	maketexteditor("postbit","PostBit");
	*/
	restarttable();
	// *** page layout ************************************************
	maketableheader("Page Layout","pagelayout");
	makestyleeditor("htmldoctype","HTML Doctype");
	makebodytag();
	restarttable();

	// *** tables ************************************************
	maketableheader("Tables","tables");
	makestyleeditor("tablewidth","Main Table Width:<br><font size='1'>This is the width applied to the main table stated in the <b>header</b> template and ended in the footer. All page content is inside this table.</font>");
	makestyleeditor("contenttablewidth","Content Table Width:<br><font size='1'>This is the width of all the tables <b>inside</b> the main table. Use a value in pixels or a percentage.</font>");
	makestyleeditor("tableouterborderwidth","Outer Borders Width:<br><font size='1'>Set to 0 to use no outer borders</font>");
	makestyleeditor("tableinnerborderwidth","Inner Borders Width:<br><font size='1'>Set to 0 to use no inner borders</font>");
	makestyleeditor("tableouterextra","Outer Table Extra:<br><font size='1'>Use this to specify extra attributes for the outer tables - example: <i>class=\"myclass\"</i></font>");
	makestyleeditor("tableinnerextra","Inner Table Extra:<br><font size='1'>Use this to specify extra attributes for the inner tables - example: <i>style=\"letter-spacing:0.1em\"</i></font>");
	makestyleeditor("tableinvisibleextra","Invisible Table Extra:<br><font size='1'>Use this to specify extra attributes for the invisble <i>holder</i> tables.</font>");
	restarttable();

	// *** main colors ************************************************
	maketableheader("Main Colors","maincolors");
	makestyleeditor("pagebgcolor","Page Background Color",1);
	makestyleeditor("pagetextcolor","Page Text Color",1,"(This color is <b>not used</b> in the default templates, but it can be useful for achieving specific effects with customized templates.)");
	makestyleeditor("tablebordercolor","Table Border Color:",1);
	makestyleeditor("categorybackcolor","Category Strip Background Color",1);
	makestyleeditor("categoryfontcolor","Category Strip Font Color",1);
	makestyleeditor("tableheadbgcolor","Table Heading Background Color",1);
	makestyleeditor("tableheadtextcolor","Table Heading Text Color",1);
	makestyleeditor("firstaltcolor","First Alternating Table Background Color",1,"(These two 'alternating' colors are used alternately in long lists so that one row can easily be told from the next.)");
	makestyleeditor("secondaltcolor","Second Alternating Table Background Color",1);
	makestyleeditor("linkcolor","Link Color",1);
	makestyleeditor("hovercolor","Hover Color for Links",1);
	makestyleeditor("timecolor","Time Color",1,"(This color is used for the time field on the main forum summary page and the forum pages)");
	restarttable();

	// *** calendar colors ************************************************
	maketableheader("Calendar Colors","calendarcolors");
	makestyleeditor("calbgcolor","Background Color",1);
	makestyleeditor("caltodaycolor","Today Background Color",1);
	makestyleeditor("caldaycolor","Calendar Date Font Color",1);
	makestyleeditor("calbirthdaycolor","Birthday Font Color",1);
	makestyleeditor("calprivatecolor","Private Event Font Color",1);
	makestyleeditor("calpubliccolor","Public Event Font Color",1);
	restarttable();

	// *** image paths ************************************************
	maketableheader("Image Paths","imagepaths");
	makestyleeditor("imagesfolder","Image folder path");
	makestyleeditor("titleimage","Title image path");
	makestyleeditor("replyimage","Reply image path");
	makestyleeditor("newthreadimage","New thread image path");
	makestyleeditor("closedthreadimage","Closed thread image path");
	restarttable();

	// *** fonts ************************************************
	makefonteditor("largefont","<largefont","<a name='fonts'>Large Font</a>");
	restarttable();
	makefonteditor("normalfont","<normalfont","Main Font");
	restarttable();
	makefonteditor("smallfont","<smallfont","Small Font");
	restarttable();
	makefonteditor("highlight","<highlight","Highlighted Font");
	restarttable();

	// *** textareas ************************************************
	maketableheader("Textarea Widths","textareas");
	echo "<tr class='".getrowbg()."'><td colspan='2'><p><font size='1'>";
	echo "Due to differences in the way that different browsers interpret the <i>cols=\"xx\"</i> attribute for a &lt;textarea&gt; form element, vBulletin will use a different width value for the three main browsers in order to produce a similarly-sized textarea box in each browser. You can set the values used here. These values are used in message-posting templates, such as <i>newthread, newreply</i> and <i>priv_sendprivmsg</i> amongst others.";
	echo "</font></p></td></tr>\n";
	makestyleeditor("textareacols_IE","Internet Explorer 4+");
	makestyleeditor("textareacols_NS4","Netscape Navigator 4.x");
	makestyleeditor("textareacols_NS6","Netscape Navigator 6+ / Mozilla");
	restarttable();

	// ***************************************************
	doformfooter("Save Changes");
}

cpfooter();
?>

