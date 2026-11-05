<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

require("./global.php");

cpheader();

adminlog(iif($smilieid!=0,"smilie id = $smilieid",""));

if (isset($action)==0) {
  $action="modify";
}

// ###################### Start Upload #######################
if ($action=="upload") {
  echo "<p><b>Using this feature requires your web server and PHP to have permission to write files to disk in the directory you specify. If they do not have the neccessary permissions, it will fail.</b></p>";

  doformheader("smilie","doupload",1);

  maketableheader("Upload new smilie from your computer");
  makeuploadcode("Smilie file","smiliefile");
  makeinputcode("Name","title");
  makeinputcode("Text to replace<br>(note this IS case sensitive)","smilietext");
  makeinputcode("Path to save image to <BR>(no filename; no trailing slash; relative from main vB directory)","smiliepath", "images/smilies");

  doformfooter("Upload");

}

// ###################### Start DoUpload #######################
if ($_POST['action']=="doupload") {

  if ($_FILES['smiliefile']) {
    $smiliefile = $_FILES['smiliefile']['tmp_name'];
    $smiliefile_name = $_FILES['smiliefile']['name'];
  }
  copy("$smiliefile","../$smiliepath/$smiliefile_name");

  $smiliepath.="/$smiliefile_name";

  echo "<p>Smilie successfuly uploaded</p>";

  $_POST['action']="insert";
}

// ###################### Start Add #######################
if ($action=="add") {

  doformheader("smilie","insert");
  maketableheader("Add a single smilie");
  makeinputcode("Name","title");
  makeinputcode("Text to replace<br>(note this IS case sensitive)","smilietext");
  makeinputcode("Path to image<BR>(such as images/smilies/smilie.gif)","smiliepath");
  doformfooter("Add Smilie");

  // Add multiple
  doformheader("smilie","insertmultiple");
  maketableheader("Add multiple smilies");
  makeinputcode("Path to images (relative to your forums directory)","smiliepath","images/smilies");
  makeinputcode("Number of smilies to display per page?","iconsperpage",7);
  doformfooter("Add Smilies");
}

// ###################### Start Insert #######################
if ($_POST['action']=="insert") {

  $DB_site->query("INSERT INTO smilie (smilieid,title,smilietext,smiliepath) VALUES (NULL,'".vb_escape($title)."','".vb_escape($smilietext)."','".vb_escape($smiliepath)."')");

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
  $smiliepath = preg_replace("/\/$/s","",$smiliepath);
  if($dirhandle=@opendir('../'.$smiliepath)) {
     chdir('../'.$smiliepath);
     $ok_exit='no';

     doformheader("smilie","domultiple",0,0,"form");
     echo "<input type='hidden' value='$smiliepath' name='smiliepath'><input type='hidden' value='$iconsperpage' name='iconsperpage'>";
     echo "<table align='center' border='0' cellspacing='1' cellpadding='5' class='tblborder'>";
     echo "<tr class='secondalt'><td align='center' colspan='4'>Adding Multiple Smilies from<br><b>$bburl/$smiliepath</b></td></tr>";
     echo "<tr class='tblhead'><td align='center'><b><span class='tblhead'>Image</span></b></td><td align='center'><b><span class='tblhead'>Title</span></b></td><td align='center'><b><span class='tblhead'>Text to Replace</b><br><font size=1>(case sensitive)</font></span></td>";
     echo "<td align='center'><input name='allbox' type='checkbox' value='Check All' onClick='CheckAll();'></td>";
     $smilies=$DB_site->query("SELECT smiliepath FROM smilie");
     while ($smilie=$DB_site->fetch_array($smilies)) {
       $smilieArray[$smilie['smiliepath']] = 'yes';
     }
     while ($filename=readdir($dirhandle)) {
       $smiliename = $smiliepath . '/' . $filename;
       if ($filename!='.' and $filename!='..' and @is_file($filename) and !$smilieArray[$smiliename] and (($filelen=strlen($filename))>=5)) {
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
         echo "<img src='../$smiliepath/$FileArray[$x]' border=0 align='center'>";
         echo "<td align='center'><input type='text' name='smilietitle[$FileArray[$x]]' size=25></td>";
         echo "<td align='center'><input type='text' name='smilietext[$FileArray[$x]]' size=25></td>";
         echo "<td align='center'><input type='checkbox' name='smilieuse[$FileArray[$x]]' value='yes'></td></tr>";
       }
     }
     if ($numpages > 1) {
       for ($x = 1; $x <= $numpages; $x++) {
         if ($x == $startpage)
            $pagelinks .= "<b> $x </b>";
         else
            $pagelinks .= " <a href=\"smilie.php?startpage=$x&amp;iconsperpage=$iconsperpage&amp;action=insertmultiple&amp;smiliepath=$smiliepath\">$x</a> ";
       }
       if ($startpage != $numpages) {
         $nextstart = $startpage + 1;
         $nextpage = " <a href='smilie.php?startpage=$nextstart&amp;iconsperpage=$iconsperpage&amp;action=insertmultiple&amp;smiliepath=$smiliepath'>></a>";
         $eicon = $endicon + 1;
       } else
         $eicon = $iconcount;
       if ($startpage!=1) {
         $prevstart = $startpage - 1;
         $prevpage = "<a href='smilie.php?startpage=$prevstart&amp;iconsperpage=$iconsperpage&amp;action=insertmultiple&amp;smiliepath=$smiliepath'><</a> ";
       }
       $sicon = $starticon +  1;
       echo "<tr class='".getrowbg()."'><td align='center' colspan=4>Showing smilies $sicon to $eicon of $iconcount<br><br>$prevpage $pagelinks $nextpage</td></tr>";
     }
     echo '</table>';
     echo "<input type='hidden' name='iconcount' value='$iconcount'>";
     doformfooter("Save");
     closedir($dirhandle);
  } else {
     echo "<p><b>$smiliepath</b> could not be found";
  }
}

// ###################### Start Insert Multiple #######################
if ($_POST['action']=="domultiple") {
  if (is_array($smilieuse)) {
    $count=0;
    while(list($key,$val)=each($smilieuse)) {
      $filename = "$smiliepath/" . stripslashes($key);
      $title = $smilietitle[$key];
      $text = $smilietext[$key];
      $DB_site->query("INSERT INTO smilie (title,smilietext,smiliepath) VALUES ('".vb_escape($title)."','".vb_escape($text)."','".vb_escape($filename)."')");
      $count++;
    } //end while
    if ($count==$iconcount) { // We inserted all of our available Smilies
      $action="modify";
    } else {
      echo "<script language=\"javascript\">window.location=\"smilie.php?action=insertmultiple&smiliepath=$smiliepath&iconsperpage=$iconsperpage\";</script>";
      echo "<p><a href=\"smilie.php?action=insertmultiple&amp;smiliepath=$smiliepath&amp;iconsperpage=$iconsperpage\">Click here to continue if you aren't forwarded</a></p>";
      exit;
    }
  } else {
    echo '<p>No Smilies Selected<p>';
  }
}

// ###################### Start Edit #######################
if ($action=="edit") {

  $smilie=$DB_site->query_first("SELECT title,smilietext,smiliepath FROM smilie WHERE smilieid=$smilieid");

  doformheader("smilie","doupdate");
  maketableheader("Edit Smilie $smilie[smilietext]");
  makehiddencode("smilieid","$smilieid");
  makehiddencode("startpage","$startpage");
  makehiddencode("perpage","$perpage");
  makeinputcode("Name","title",$smilie['title']);
  makeinputcode("Text to replace<br>(note this IS case sensitive)","smilietext",$smilie['smilietext']);
  makeinputcode("Path to image","smiliepath",$smilie['smiliepath']);

  doformfooter("Save Changes");
}

// ###################### Start Update #######################
if ($_POST['action']=="doupdate") {

  $DB_site->query("UPDATE smilie SET title='".vb_escape($title)."',smilietext='".vb_escape($smilietext)."',smiliepath='".vb_escape($smiliepath)."' WHERE smilieid='$smilieid'");

  echo "<p>Record updated!</p>";
  $action="modify";

}
// ###################### Start Remove #######################

if ($action=="remove") {

	doformheader("smilie","kill");
	makehiddencode("smilieid",$smilieid);
	makehiddencode("perpage",$perpage);
	makehiddencode("startpage",$startpage);
	maketableheader("Confirm deletion");
	makedescription("Are you sure you want to delete this smilie?");
	doformfooter("Yes","",2,"No");

}

// ###################### Start Kill #######################

if ($_POST['action']=="kill") {

  $DB_site->query("DELETE FROM smilie WHERE smilieid=$smilieid");

  echo "<p>Deleted!</p>";

  $action="modify";
}

// ###################### Start Modify #######################
if ($action=="modify") {
  if (intval($startpage)<1)
    $startpage = 1;
  if (intval($perpage)<1)
    $perpage = 15;
  $smiliecount=$DB_site->query_first("SELECT count(*) AS count FROM smilie");
  $totalsmilies = $smiliecount['count'];
  if (($startpage-1)*$perpage > $totalsmilies) {
     if ((($totalsmilies / $perpage) - ((int) ($totalsmilies / $perpage))) == 0)
       $startpage = $totalsmilies / $perpage;
     else
       $startpage = (int) ($totalsmilies / $perpage) + 1;
  }
  $limitlower=($startpage-1)*$perpage+1;
  $limitupper=($startpage)*$perpage;
    if ($limitupper>$totalsmilies) {
      $limitupper=$totalsmilies;
      if ($limitlower>$totalsmilies) {
        $limitlower=$totalsmilies-$perpage;
      }
    }
    if ($limitlower<=0) {
      $limitlower=1;
  }

  $smilies=$DB_site->query("SELECT smilietext,smilieid,title,smiliepath FROM smilie ORDER BY title LIMIT ".($limitlower-1).",$perpage");

  doformheader("smilie","modify");
  maketableheader("Current Smilies","",1,5);
  $count=0;
  while ($smilie=$DB_site->fetch_array($smilies)) {
    if ($count==0)
      echo "<tr class='".getrowbg()."'>";
    echo "<td valign='bottom' nowrap align='center'>$smilie[title]<br><br>";
    echo "<img src=\"".iif(substr($smilie['smiliepath'],0,7)!="http://" and substr($smilie['smiliepath'],0,1)!="/","../","").str_replace("{imagesfolder}","images",$smilie['smiliepath'])."\">";
    echo "&nbsp;&nbsp;<b>$smilie[smilietext]</b><br><br>\n";
 	echo makelinkcode("edit","smilie.php?s=$session[sessionhash]&amp;action=edit&amp;smilieid=$smilie[smilieid]&amp;perpage=$perpage&amp;startpage=$startpage");
	echo makelinkcode("remove","smilie.php?s=$session[sessionhash]&amp;action=remove&amp;smilieid=$smilie[smilieid]&amp;perpage=$perpage&amp;startpage=$startpage");
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
  if ((($totalsmilies / $perpage) - ((int) ($totalsmilies / $perpage))) == 0)
    $numpages = $totalsmilies / $perpage;
  else
    $numpages = (int) ($totalsmilies / $perpage) + 1;
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
        $pagelinks .= " <a href=\"smilie.php?startpage=$x&amp;perpage=$perpage&amp;action=modify\">$x</a> ";
    }
  }
  if ($startpage != $numpages) {
    $nextstart = $startpage + 1;
    $nextpage = " <a href='smilie.php?startpage=$nextstart&amp;perpage=$perpage&amp;action=modify'>&gt;</a>";
    $eicon = $endicon + 1;
  } else
    $eicon = $totalsmilies;
  if ($startpage!=1) {
    $prevstart = $startpage - 1;
    $prevpage = "<a href='smilie.php?startpage=$prevstart&amp;perpage=$perpage&amp;action=modify'>&lt;</a> ";
  }
  $sicon = $starticon +  1;
  echo "<tr class='".getrowbg()."'><td align=\"center\" colspan=5><table border=0><tr><td width=\"100%\" valign=\"top\" align=\"center\">";
  echo "<font size='1'><b>Showing smilies $sicon to $eicon of $totalsmilies</b></font></td><td nowrap><form action=\"smilie.php\" method=\"post\">";
  echo "<font size='1'><b>Perpage:</b></font> <input type=\"text\" name=\"perpage\" value=\"$perpage\" size=5> <input type=\"submit\" value=\"GO\">";
  echo "</td></tr></table><font size='1'>$prevpage $pagelinks $nextpage</font></td></tr>";
  echo "</table></td></tr></table></form>";
}

cpfooter();
?>
