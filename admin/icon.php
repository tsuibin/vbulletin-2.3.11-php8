<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

require("./global.php");

adminlog(iif($iconid!=0,"icon id = $iconid",""));

cpheader();

if (isset($action)==0) {
  $action="modify";
}

// ###################### Start Upload #######################
if ($action=="upload") {

  echo "<p><b>Using this feature required your web server and PHP to have permission to write files to disk. If they do not have the neccessary permissions, it will fail.</b></p>";

  doformheader("icon","doupload",1);

  maketableheader("Upload new icon from your computer");
  makeuploadcode("Icon file","iconfile");
  makeinputcode("Alt text","title");
  makeinputcode("Path to save image to <BR>(no filename; no trailing slash; relative from main vB directory)","iconpath","images/icons");

  doformfooter("Upload Now");

}

// ###################### Start Do Upload #######################
if ($_POST['action']=="doupload") {

  if ($_FILES['iconfile']) {
    $iconfile = $_FILES['iconfile']['tmp_name'];
    $iconfile_name = $_FILES['iconfile']['name'];
  }
  copy("$iconfile","../$iconpath/$iconfile_name");

  $iconpath.="/$iconfile_name";

  echo "<p>Icon successfuly uploaded</p>";

  $_POST['action']="insert";
}

// ###################### Start Add #######################
if ($action=="add") {

  doformheader("icon","insert");
  maketableheader("Add a single icon");
  makeinputcode("Alt text","title");
  makeinputcode("Path to image","iconpath","images/icons");
  doformfooter("Add Icon");

  // Add multiple
  doformheader("icon","insertmultiple");
  maketableheader("Add multiple icons");
  makeinputcode("Path to images (relative to your forums directory)","iconpath","images/icons");
  makeinputcode("Number of icons to display per page?","iconsperpage",7);
  doformfooter("Add Icons");
}

// ###################### Start Insert #######################
if ($_POST['action']=="insert") {

  $DB_site->query("INSERT INTO icon (iconid,title,iconpath) VALUES (NULL,'".vb_escape($title)."','".vb_escape($iconpath)."')");

  $action="modify";

  echo "<p>Record added</p>";

}

// ###################### Start Multi Prompt  #######################
if ($action=="insertmultiple") {
?>
<script language="JavaScript">
<!--
function CheckAll()
{
	for (var i=0;i<document.form.elements.length;i++)
	{
		var e = document.form.elements[i];
		if ((e.name != 'allbox') && (e.type=='checkbox'))
		e.checked = document.form.allbox.checked;
	}
}
function CheckCheckAll()
{
	var TotalBoxes = 0;
	var TotalOn = 0;
	for (var i=0;i<document.form.elements.length;i++)
	{
		var e = document.form.elements[i];
		if ((e.name != 'allbox') && (e.type=='checkbox'))
		{
			TotalBoxes++;
		if (e.checked)
		{
			TotalOn++;
		}
		}
	}
	if (TotalBoxes==TotalOn)
	{document.form.allbox.checked=true;}
	else
	{document.form.allbox.checked=false;}
}
//-->
</script>
<?php

  if (intval($startpage)<1)
    $startpage = 1;
  if (intval($iconsperpage)<1)
    $iconsperpage = 10;
  $iconpath = preg_replace("/\/$/s","",$iconpath);
  if($dirhandle=@opendir('../'.$iconpath)) {
     chdir('../'.$iconpath);
     $ok_exit='no';

     doformheader("icon","domultiple",0,0,"form");
     echo "<input type='hidden' value='$iconpath' name='iconpath'><input type='hidden' value='$iconsperpage' name='iconsperpage'>";
     echo "<table align='center' border='0' border='0' cellspacing='1' cellpadding='5' class='tblborder'>";
     echo "<tr class='secondalt'><td align='center' colspan=3>Adding Multiple Message Icons from<br><b>$bburl/$iconpath</b></td></tr>";
     echo "<tr class='tblhead'><td align='center'><b><span class='tblhead'>Image</span></b></td><td align='center'><b><span class='tblhead'>Title</span></b></td>";
     echo "<td align='center'><input name='allbox' type='checkbox' value='Check All' onClick='CheckAll();'></td>";
     $icons=$DB_site->query("SELECT iconpath FROM icon");
     while ($icon=$DB_site->fetch_array($icons)) {
       $iconArray[$icon['iconpath']] = 'yes';
     }
     while ($filename=readdir($dirhandle)) {
       $iconname = $iconpath . '/' . $filename;
       if ($filename!='.' and $filename!='..' and @is_file($filename) and !$iconArray[$iconname] and (($filelen=strlen($filename))>=5)) {
           $fileext = strtolower(substr($filename,$filelen-4,$filelen-1));
           if ($fileext==".gif" or $fileext==".bmp" or $fileext==".jpg" or $fileext=="jpeg" or $fileext=="png") {
             $FileArray[count($FileArray)] = addslashes($filename);
           }
       }
     }
     $iconcount = count($FileArray);
     if ((($iconcount / $iconsperpage) - ((int) ($iconcount / $iconsperpage))) == 0)
       $numpages = $iconcount / $iconsperpage;
     else
       $numpages = (int) ($iconcount / $iconsperpage) + 1;
     if ($startpage == 1) {
       $starticon = 0;
       $endicon = $iconsperpage - 1;
     } else {
       $starticon = ($startpage - 1) * $iconsperpage;
       $endicon = ( $iconsperpage * $startpage ) - 1 ;
     }
     for ($x = 0; $x < $iconcount; $x++) {
       if ($x >= $starticon && $x <= $endicon) {
         echo "<tr class='".getrowbg()."'><td align='center' valign='bottom'>";
         echo "<img src='../$iconpath/$FileArray[$x]' border=0 align='center'>";
         echo "<td align='center'><input type='text' name='icontitle[$FileArray[$x]]' size=25></td>";
         echo "<td align='center'><input type='checkbox' name='iconuse[$FileArray[$x]]' value='yes'></td></tr>";
       }
     }
     if ($numpages > 1) {
       for ($x = 1; $x <= $numpages; $x++) {
         if ($x == $startpage)
            $pagelinks .= "<b> $x </b>";
         else
            $pagelinks .= " <a href=\"icon.php?startpage=$x&amp;iconsperpage=$iconsperpage&amp;action=insertmultiple&amp;iconpath=$iconpath\">$x</a> ";
       }
       if ($startpage != $numpages) {
         $nextstart = $startpage + 1;
         $nextpage = " <a href='icon.php?startpage=$nextstart&amp;iconsperpage=$iconsperpage&amp;action=insertmultiple&amp;iconpath=$iconpath'>></a>";
         $eicon = $endicon + 1;
       } else
         $eicon = $iconcount;
       if ($startpage!=1) {
         $prevstart = $startpage - 1;
         $prevpage = "<a href='icon.php?startpage=$prevstart&amp;iconsperpage=$iconsperpage&amp;action=insertmultiple&amp;iconpath=$iconpath'><</a> ";
       }
       $sicon = $starticon +  1;
       echo "<tr class='".getrowbg()."'><td align='center' colspan=4>Showing icons $sicon to $eicon of $iconcount<br><br>$prevpage $pagelinks $nextpage</td></tr>";
     }
     echo '</table>';
     echo "<input type='hidden' name='iconcount' value='$iconcount'>";
     doformfooter("Save");
     closedir($dirhandle);
  } else {
     echo "<p><b>$iconpath</b> could not be found";
  }
}

// ###################### Start Insert Multiple #######################
if ($_POST['action']=="domultiple") {
  if (is_array($iconuse)) {
    $count=0;
    while(list($key,$val)=each($iconuse)) {
      $filename = "$iconpath/" . stripslashes($key);
      $title = $icontitle[$key];
      $DB_site->query("INSERT INTO icon (title,iconpath) VALUES ('".vb_escape($title)."','".vb_escape($filename)."')");
      $count++;
    } //end while
    if ($count==$iconcount) { // We inserted all of our available Icons
      $action="modify";
    } else {
      echo "<script language=\"javascript\">window.location=\"icon.php?action=insertmultiple&iconpath=$iconpath&iconsperpage=$iconsperpage\";</script>";
      echo "<p><a href=\"icon.php?action=insertmultiple&amp;iconpath=$iconpath&amp;iconsperpage=$iconsperpage\">Click here to continue if you aren't forwarded</a></p>";
      exit;
    }
  } else {
    echo '<p>No Icons Selected<p>';
  }
}

// ###################### Start Edit #######################
if ($action=="edit") {

  $icon=$DB_site->query_first("SELECT title,iconpath FROM icon WHERE iconid=$iconid");

  doformheader("icon","doupdate");
  maketableheader("Edit Icon:</b> <i>$icon[title]</i>","",0);
  makehiddencode("iconid","$iconid");
  makehiddencode("startpage","$startpage");
  makehiddencode("perpage","$perpage");
  makeinputcode("Alt text","title",$icon['title']);
  makeinputcode("Path to image","iconpath",$icon['iconpath']);
  doformfooter("Save Changes");

}

// ###################### Start Update #######################
if ($_POST['action']=="doupdate") {

  $DB_site->query("UPDATE icon SET title='".vb_escape($title)."',iconpath='".vb_escape($iconpath)."' WHERE iconid='$iconid'");

  echo "<p>Record updated!</p>";

  $action="modify";

}
// ###################### Start Remove #######################

if ($action=="remove") {

	doformheader("icon","kill");
	makehiddencode("iconid",$iconid);
	makehiddencode("perpage",$perpage);
	makehiddencode("startpage",$startpage);
	maketableheader("Confirm deletion");
	makedescription("Are you sure you want to delete this icon?");
	doformfooter("Yes","",2,"No");

}

// ###################### Start Kill #######################

if ($_POST['action']=="kill") {

  $DB_site->query("DELETE FROM icon WHERE iconid=$iconid");
  $DB_site->query("UPDATE thread SET iconid=0 WHERE iconid='$iconid'");
  $DB_site->query("UPDATE post SET iconid=0 WHERE iconid='$iconid'");
  echo "<p>Deleted! & thread/posts with that icon have been updated.</p>";

  $action="modify";
}

// ###################### Start Modify #######################
if ($action=="modify") {
  if (intval($startpage)<1)
    $startpage = 1;
  if (intval($perpage)<1)
    $perpage = 20;
  $iconcount=$DB_site->query_first("SELECT count(*) AS count FROM icon");
  $totalicons = $iconcount['count'];
  if (($startpage-1)*$perpage > $totalicons) {
     if ((($totalicons / $perpage) - ((int) ($totalicons / $perpage))) == 0)
       $startpage = $totalicons / $perpage;
     else
       $startpage = (int) ($totalicons / $perpage) + 1;
  }
  $limitlower=($startpage-1)*$perpage+1;
  $limitupper=($startpage)*$perpage;
    if ($limitupper>$totalicons) {
      $limitupper=$totalicons;
      if ($limitlower>$totalicons) {
        $limitlower=$totalicons-$perpage;
      }
    }
    if ($limitlower<=0) {
      $limitlower=1;
  }

  $icons=$DB_site->query("SELECT iconid,title,iconpath FROM icon ORDER BY title LIMIT ".($limitlower-1).",$perpage");

  doformheader("icon","modify");
  maketableheader("Current Message Icons","",1,5);
  $count=0;
  while ($icon=$DB_site->fetch_array($icons)) {
    if ($count==0)
      echo "<tr class='".getrowbg()."'>";
    echo "<td valign='bottom' nowrap align='center'>$icon[title]<br><br>";
    echo "<img src=\"".iif(substr($icon['iconpath'],0,7)!="http://" and substr($icon['iconpath'],0,1)!="/","../","").str_replace("{imagesfolder}","images",$icon['iconpath'])."\"><br><br>";
 	echo makelinkcode("edit","icon.php?s=$session[sessionhash]&amp;action=edit&amp;iconid=$icon[iconid]&amp;perpage=$perpage&amp;startpage=$startpage");
	echo makelinkcode("remove","icon.php?s=$session[sessionhash]&amp;action=remove&amp;iconid=$icon[iconid]&amp;perpage=$perpage&amp;startpage=$startpage");
    echo '</td>';
    $count++;
    if ($count==5) {
      echo '</tr>';
      $count = 0;
    }
  }
  if ($count!=0) {
    while ($count !=5) {
      echo '<td>&nbsp;</td>';
      $count++;
    }
    echo '</tr>';
  }
  if ((($totalicons / $perpage) - ((int) ($totalicons / $perpage))) == 0)
    $numpages = $totalicons / $perpage;
  else
    $numpages = (int) ($totalicons / $perpage) + 1;
  if ($startpage == 1) {
    $starticon = 0;
    $endicon = $perpage - 1;
  } else {
    $starticon = ($startpage - 1) * $perpage;
    $endicon = ( $perpage * $startpage ) - 1 ;
  }
  if ($numpages > 1) {
    for ($x = 1; $x <= $numpages; $x++) {
      if ($x == $startpage)
        $pagelinks .= "<b> <font size=+1>$x</font> </b>";
      else
        $pagelinks .= " <a href=\"icon.php?startpage=$x&amp;perpage=$perpage&amp;action=modify\">$x</a> ";
    }
  }
  if ($startpage != $numpages) {
    $nextstart = $startpage + 1;
    $nextpage = " <a href='icon.php?startpage=$nextstart&amp;perpage=$perpage&amp;action=modify'>&gt;</a>";
    $eicon = $endicon + 1;
  } else
    $eicon = $totalicons;
  if ($startpage!=1) {
    $prevstart = $startpage - 1;
    $prevpage = "<a href='icon.php?startpage=$prevstart&amp;perpage=$perpage&amp;action=modify'>&lt;</a> ";
  }
  $sicon = $starticon +  1;
  echo "<tr class='".getrowbg()."'><td align=\"center\" colspan=5><table border=0><tr><td width=\"100%\" valign=\"top\" align=\"center\">";
  echo "<font size='1'><b>Showing message icons $sicon to $eicon of $totalicons</b></font></td><td nowrap>";
  echo "<font size='1'><b>Perpage:</b></font> <input type=\"text\" name=\"perpage\" value=\"$perpage\" size=5> <input type=\"submit\" value=\"GO\">";
  echo "</td></tr></table><font size='1'>$prevpage $pagelinks $nextpage</font></td></tr>";
  echo "</table></td></tr></table></form>";
}


cpfooter();
?>
