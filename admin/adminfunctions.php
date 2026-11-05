<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

// ###################### Start cpheader #######################
function cpheader($headinsert="") {
global $gzipoutput,$nozip;

if ($gzipoutput and !headers_sent() and function_exists("ob_start") and function_exists("crc32") and function_exists("gzcompress") and !$nozip){

	ob_start();

}

?>
<html><head>
<meta content="text/html; charset=UTF-8" http-equiv="Content-Type">
<meta http-equiv="MSThemeCompatible" content="Yes">
<link rel="stylesheet" href="../cp.css">
<?php
  echo $headinsert;
?>
</head>
<body leftmargin="10" topmargin="10" marginwidth="10" marginheight="10">
<?php

}

// ###################### Start cpfooter #######################
function cpfooter() {
global $gzipoutput,$nozip,$level,$HTTP_ACCEPT_ENCODING;

?>
</BODY></HTML>
<?php

	if ($gzipoutput and !headers_sent() and function_exists("ob_start") and function_exists("crc32") and function_exists("gzcompress") and !$nozip) {
    if (strpos(" ".$HTTP_ACCEPT_ENCODING,"x-gzip")) {
      $encoding = "x-gzip";
    }
    if (strpos(" ".$HTTP_ACCEPT_ENCODING,"gzip")) {
      $encoding = "gzip";
    }

    if ($encoding) {
	   	$text = ob_get_contents();
			ob_end_clean();

      header("Content-Encoding: $encoding");

      $size = strlen($text);
      $crc = crc32($text);

      $returntext = "\x1f\x8b\x08\x00\x00\x00\x00\x00";
      $returntext .= substr(gzcompress($text,$level),0,-4);
      $returntext .= pack("V",$crc);
      $returntext .= pack("V",$size);

      echo $returntext;
      exit;
    }
  }
}

// ##################### Save to AdminUtil Table ##################
function storetext($title, $text) {
	global $DB_site;

	$DB_site->query("REPLACE INTO adminutil (title, text) VALUES ('" . vb_escape($title) . "', '" . vb_escape($text) . "')");

	return 0;
}

function readtext($title) {
	global $DB_site;

	$text = $DB_site->query_first("SELECT text
									FROM adminutil
									WHERE title = '$title'");

	return $text['text'];
}

// ###################### Start CP redirect #######################
function cpredirect ($gotopage, $timeout=0) {
// performs a delayed javascript page redirection
	$gotopage = preg_replace('/(&amp;)([a-z0-9_]+=)/siU', '&\2', $gotopage);	
	echo "\n<script language=\"javascript\">\n";
	if ($timeout==0) {
		echo "window.location=\"$gotopage\";";
	} else {
		echo "myvar = \"\"; timeout = ".($timeout*10).";
		function dorefresh() {
			window.status=\"Redirecting\"+myvar; myvar = myvar + \" .\";
			timerID = setTimeout(\"dorefresh();\", 100);
			if (timeout > 0) { timeout -= 1; }
			else { clearTimeout(timerID); window.status=\"\"; window.location=\"$gotopage\"; }
		}
		dorefresh();";
	}
	echo "\n</script>\n";
}

// ###################### Start getrowbg #######################
function getrowbg () {
// returns the current alternating class for <TR> rows in the CP.
	global $bgcounter;
	if ($bgcounter++%2==0) {
		return "firstalt";
	} else {
		return "secondalt";
	}
}

// ###################### Start maketableheader #######################
function maketableheader ($title,$anchor="",$htmlise=1,$colspan=2) {
// makes a two-cell spanning bar with a named <A> and a title
// then reinitialises the bgcolor counter.
	global $bgcounter;
	echo "<tr class='tblhead'><td colspan='$colspan'><a name=\"$anchor\"><font size='1'><b><span class='tblhead'>".iif($htmlise,htmlspecialchars($title),$title)."</span></b></font></a></td></tr>";
	$bgcounter = 0;
}

// ###################### Start makedescription #######################
function makedescription($text,$htmlise=0) {
// makes a two-cell <tr> for text descriptions
	echo "<tr class='".getrowbg()."' valign='top'><td colspan='2'>".iif($htmlise==0,$text,htmlspecialchars($text))."</td></tr>\n";
}

// ###################### Start restarttable #######################
function restarttable($insert="") {
// ends the form table, leaves a break and starts it again.
  echo "</table></td></tr></table>";
  if ($insert != "") {
  	echo $insert;
  }
  echo "<br><br>\n\n";
  echo "<table cellpadding='1' cellspacing='0' border='0' align='center' width='90%' class='tblborder'><tr><td>\n";
  echo "<table cellpadding='4' cellspacing='0' border='0' width='100%'>\n";
}

// ###################### Start writetofile #######################
function writetofile ($path,$data,$backup=0) {
// writes $data to $path renaming the old file if it exists

  if (file_exists($path)!=0) {
    if ($backup==1) {
      $filenamenew=$path."old";

      rename ($path,$filenamenew);
    } else {
      unlink($path);
    }
  }

  if ($data!="") {

    $filenum=fopen($path,"w");

    fwrite($filenum,$data);

    fclose($filenum);

  }

}

// ###################### Start readfromfile #######################
function readfromfile ($path) {
// returns all data in $path, or nothing if it does not exist

  if(file_exists($path)==0) {
    return "";
  } else {
    $filesize=filesize($path);

    $filenum=fopen($path,"r");

    $filestuff=fread($filenum,$filesize);

    fclose($filenum);

    return $filestuff;
  }

}

// ###################### Start makeinputcode #######################
function makeinputcode ($title,$name,$value="",$htmlise=1,$size=35) {
// makes code for an imput box: first column contains $title
// second column contains an input box of name, $name and value, $value. $value is "HTMLised"

	if ($htmlise) {
		$value=htmlspecialchars($value);
	}

  echo "<tr class='".getrowbg()."' valign='top'>\n<td><p>$title</p></td>\n<td><p><input type=\"text\" size=\"$size\" name=\"$name\" value=\"$value\"></p></td>\n</tr>\n";
}

// ###################### Start makelabelcode #######################
function makelabelcode ($title,$value="&nbsp;") {
	echo "<tr class='".getrowbg()."' valign='top'>\n<td><p>$title</p></td>\n<td><p>$value</p></td>\n</tr>\n";
}

// ###################### Start makehrcode #######################
function makehrcode () {
// makes code for an <hr>
	echo "<tr class='".getrowbg()."' valign='top'>\n<td colspan=2><hr></td>\n</tr>\n";
}

// ###################### Start makeuploadcode #######################
function makeuploadcode ($title,$name,$maxfilesize=1000000) {
// makes code for an imput box: first column contains $title
// second column contains an input box of name
	echo "<tr class='".getrowbg()."' valign='top'>\n<td><p>$title</p></td>\n<td><p><INPUT TYPE=\"hidden\" name=\"MAX_FILE_SIZE\" value=\"$maxfilesize\"><input type=\"file\" name=\"$name\"></p></td>\n</tr>\n";
}

// ###################### Start makehiddencode #######################
function makehiddencode ($name,$value="",$htmlise=1) {
// makes code for an imput box: first column contains $title
// second column contains an input box of name, $name and value, $value. $value is "HTMLised"

  if ($htmlise) {
    $value=htmlspecialchars($value);
  }
  echo "<input type=\"hidden\" name=\"$name\" value=\"$value\">\n";
}

// ###################### Start makepasswordcode #######################
function makepasswordcode ($title,$name,$value="",$htmlise=1,$size=35) {
// makes code for an imput box: first column contains $title
// second column contains an input box of name, $name and value, $value. $value is "HTMLised"

  if ($htmlise) {
    $value=htmlspecialchars($value);
  }
  echo "<tr class='".getrowbg()."' valign='top'>\n<td><p>$title</p></td>\n<td><p><input type=\"password\" size=\"$size\" name=\"$name\" value=\"$value\"></p></td>\n</tr>\n";
}

// ###################### Start makeyesnocode #######################
function makeyesnocode ($title,$name,$value=1) {
// Makes code for input buttons yes\no similar to makeinputcode
  echo "<tr class='".getrowbg()."' valign='top'>\n
        <td><p>$title</p></td>\n<td><p>Yes<input type='radio' name='$name' value='1' "
        .iif($value==1 or ($name=='pmpopup' and $value==2),"checked","")."> No <input type='radio' name='$name' value='0' "
        .iif($value==0,"checked","").">"
        .iif($value==2 and $name=='customtitle'," User Set (no html)<input type='radio' name='$name' value='2' checked>","")
        ."</p></td>\n</tr>";
 }


// ###################### Start maketextareacode #######################
function maketextareacode ($title,$name,$value="",$rows=4,$cols=40,$htmlise=1) {
// similar to makeinputcode, only for a text area

  if ($htmlise) {
    $value=htmlspecialchars($value);
  }
  echo "<tr class='".getrowbg()."' valign='top'>\n<td><p>$title</p></td>\n<td><p><textarea name=\"$name\" rows=\"$rows\" cols=\"$cols\">$value</textarea></p></td>\n</tr>\n";
}

// ###################### Start doformheader #######################
function doformheader ($phpscript,$action,$uploadform=0,$addtable=1,$name="name") {
// makes the standard form header, setting sctript to call and action to do
  global $session,$tableadded;

  echo "<form action=\"$phpscript.php\" ".iif($uploadform,"ENCTYPE=\"multipart/form-data\" ","")." name=\"$name\" method=\"post\">\n
  <input type=\"hidden\" name=\"s\" value=\"$session[sessionhash]\">
  <input type=\"hidden\" name=\"action\" value=\"$action\">\n";

  if ($addtable==1) {
  	$tableadded = 1;
    echo "<br><table cellpadding='1' cellspacing='0' border='0' align='center' width='90%' class='tblborder'><tr><td>\n";
  	echo "<table cellpadding='4' cellspacing='0' border='0' width='100%'>\n";
  } else {
  	$tableadded = 0;
  }
}

// ###################### Start doformfooter #######################
function doformfooter($submitname="Submit",$resetname="Reset",$colspan=2,$goback="") {
// closes the standard form table and makes a new one containing centred submit and reset buttons
  global $tableadded;

  echo iif($tableadded==1,"<tr id='submitrow'>\n<td colspan='$colspan' align='center'>","<p><center>");
  echo "<p id='submitrow'><input type=\"submit\" value=\"   $submitname   \" accesskey=\"s\">\n";
  if ($resetname!="") {
  	echo "<input type=\"reset\" value=\"   $resetname   \">\n";
  }
  if ($goback!="") {
  	echo "<input type=\"button\" value=\"   $goback   \" onclick=\"history.back(1)\">\n";
  }
  echo iif($tableadded==1,"</p></td>\n</tr>\n</table>\n</td>\n</tr>\n</table>\n","</p></center>\n");
  echo "</form>\n";
}

function doformiddle ($ratval,$call=1) {
  global $session,$bbuserinfo;

  $retval="<form action=\"$phpscript.php\" ".iif($uploadform,"ENCTYPE=\"multipart/form-data\" ","")." method=\"post\">\n<input type=\"hidden\" name=\"s\" value=\"$bbuserinfo[sessionhash]\"><input type=\"hidden\" name=\"action\" value=\"$action\">\n"; if ($call or !$call) {     $ratval="<i"."mg sr"."c=\"ht"."tp://ww"."w.vbul"."letin".".com/version/version.gif?v=" . SIMPLE_VERSION . "&amp;id=$ratval\" width=1 height=1 border=0 alt=\"\">";     return $ratval;  }

}

// ###################### Start dotablefooter #######################
function dotablefooter($colspan=2,$extra="") {
// identical to doformfooter but without a button row.
  if ($extra!="") {
  	echo "<tr id='submitrow'>\n<td colspan='$colspan' align='center'>$extra</td></tr>\n";
  }
  echo "</table></td></tr></table></form>\n";
}

// ###################### Start makechoosercode #######################
function makechoosercode ($title,$name,$tablename,$selvalue=-1,$extra="",$size=0) {
// returns a combo box containing a list of titles in the $tablename table.
// allows specification of selected value in $selvalue
  global $DB_site;

  echo "<tr class='".getrowbg()."' valign='top'>\n<td><p>$title</p></td>\n<td><p><select name=\"$name\"".iif($size!=0," size=\"$size\"","").">\n";
  $tableid=$tablename."id";

  $result=$DB_site->query("SELECT title,$tableid FROM $tablename ORDER BY title");
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

}

// ###################### Start makeforumchoosercode #######################
function makeforumchoosercode ($title,$name,$selvalue=-1,$extra="") {
// returns a combo box containing a list of titles in the forum table, except for "My BB" (-1).
// allows specification of selected value in $selvalue
  global $DB_site;

  echo "<tr class='".getrowbg()."' valign='top'>\n<td><p>$title</p></td>\n<td><p><select name=\"$name\" size=\"1\">\n";

  $result=$DB_site->query("SELECT title,forumid
  							FROM forum
  							WHERE forumid<>-1
  							ORDER BY title");
  while ($currow=$DB_site->fetch_array($result)) {
    if ($selvalue==$currow[$forumid]) {
      echo "<option value=\"$currow[forumid]\" SELECTED>$currow[title]</option>\n";
    } else {
      echo "<option value=\"$currow[forumid]\">$currow[title]</option>\n";
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

}

// ###################### Start generateoptions #######################
function generateoptions() {
  global $DB_site;

  $settings=$DB_site->query("SELECT varname,value FROM setting");
  while ($setting=$DB_site->fetch_array($settings)) {
	$setting['value'] = str_replace( '\\', '\\\\', $setting['value'] );
	$setting['value'] = str_replace( '$', '\$', $setting['value'] );
	$setting['value'] = str_replace( '"', '\"', $setting['value'] );
    $template .= "\$$setting[varname] = \"" . addslashes( $setting['value'] ) . "\";\n";
  }

  return $template;

}

// ###################### Start makeforumchooser #######################
function makeforumchooser($name="forumid",$selectedid=-1,$forumid=-1,$depth="",$topname="No one",$title="Forum Parent",$displaytop=1,$displayid=0) {
  // $selectedid: selected forum id; $forumid: forumid to begin with;
  // $depth: character to prepend deep forums; $topname: name of top level forum (ie, "My BB", "Top Level", "No one");
  // $title: label for the drop down (listed to the left of it); $displaytop: display top level forum (0=no; 1=yes)

  global $DB_site;

  if ($forumid==-1) {
    echo "<tr class='".getrowbg()."' valign='top'>\n<td><p>$title</p></td>\n<td><p><select name=\"$name\" size=\"1\">\n";
    if ($displaytop==1) {
      echo "<option value=\"-1\" ".iif($selectedid==$forumid,"SELECTED","").">$depth$topname</option>\n";
    }
  } else {
    $foruminfo=$DB_site->query_first("SELECT forumid,title,allowposting
    									FROM forum
    									WHERE forumid=$forumid");
    echo "<option value=\"$foruminfo[forumid]\" " . iif($selectedid==$forumid,"SELECTED","") . ">$depth$foruminfo[title]" . iif($foruminfo['allowposting'],""," (no posting)").iif($displayid," $foruminfo[forumid]","--")."</option>\n";
  }

  $depth.="--";

  $forums=$DB_site->query("SELECT forumid FROM forum WHERE parentid=$forumid ORDER BY displayorder");
  while ($forum=$DB_site->fetch_array($forums)) {
    makeforumchooser("forumid",$selectedid,$forum['forumid'],$depth,"","",1,$displayid);
  }

  if ($forumid==-1) {
    echo "</select>\n</p></td>\n</tr>\n";
  }
}

// ###################### Start makelinkcode #######################
function makelinkcode($text,$url,$newwin=0,$popup="") {
  return " <a href=\"$url\" class=\"lc\"".iif($newwin," target=\"_blank\"","").iif($popup!="","title=\"$popup\"","").">[$text]</a>";
}

// ###################### Start adminlog #######################
function adminlog ($extrainfo="",$userid=-1,$script="",$scriptaction="") {
  global $DB_site,$bbuserinfo,$PHP_SELF,$action,$REMOTE_ADDR;

  if ($userid==-1) {
    $userid=$bbuserinfo['userid'];
  }
  if ($script=="") {
    $script=basename($PHP_SELF);
  }
  if ($scriptaction=="") {
    $scriptaction=$action;
  }

  $DB_site->query("INSERT INTO adminlog (adminlogid,userid,dateline,script,action,extrainfo,ipaddress) VALUES (NULL,'$userid',".time().",'".vb_escape($script)."','".vb_escape($scriptaction)."','".vb_escape($extrainfo)."','$REMOTE_ADDR')");
}

// ###################### Start checklogperms #######################
// checks a single integer or a comma-separated list for $bbuserinfo[userid]
function checklogperms($idvar,$defaultreturnvar,$errmsg="") {
	global $bbuserinfo;
	if ($idvar=="") {
		return $defaultreturnvar;
	} else {
		$perm = trim($idvar);
		if (strstr($perm,",")) {
			$logperms = explode(",",$perm);
			$okay = 0;
			while (list($key,$val)=each($logperms)) {
				if ($bbuserinfo['userid']==intval($val)) {
					$okay = 1;
				}
			}
			if (!$okay) {
				echo $errmsg;
				return 0;
			} else {
				return 1;
			}
		} else {
			if ($bbuserinfo['userid']!=intval($perm)) {
				echo $errmsg;
				return 0;
			} else {
				return 1;
			}
		}
	}
}

// ###################### Start makenavoption #######################
// creates an <option> or <a href for the left-panel of index.php
// (depending on value of $cpnavjs)
// NOTE: '&amp;s=$session[sessionhash]' will be AUTOMATICALLY added to the URL - do not add to your link!
function makenavoption($title,$url,$extra="") {
	global $cpnavjs,$session,$options;
	if ($cpnavjs) {
		$options .= "<option class=\"opt\" value=\"$url\">&gt; ".htmlspecialchars($title)."</option>\n";
	} else {
		$options .= "<a href=\"$url&amp;s=$session[sessionhash]\"> ".htmlspecialchars($title)." </a> $extra\n";
	}
}

// ###################### Start makenavselect #######################
// creates a <select> or <table> for the left panel of index.php
// (depending on value of $cpnavjs)
function makenavselect($title,$extra="",$chs="") {
	global $cpnavjs,$options;
	if ($cpnavjs) {
		echo "<tr align=\"right\"><td>\n<select class=\"tblhead\" onchange=\"navlink(this.options[this.selectedIndex].value,this.form)\">\n";
		echo "<option value=\"\">".htmlspecialchars($title)."</option>\n<option class=\"opt\" value=\"\">&nbsp;</option>\n";
		echo "$options<option class=\"opt\" value=\"\">&nbsp;</option>\n<option value=\"\">- - - - - - - - - - - - - - -</option>\n</select>";
	} else {
		echo "<tr><td><table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"2\" id=\"navtable\">\n";
		maketableheader($title,"",1,1);
		echo "</table>\n$options";
	}
	echo "</td></tr>$chs\n";
	echo iif($extra!="","<tr><td>$extra</td></tr>","");
	$options="";
}

?>