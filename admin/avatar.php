<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

require("./global.php");

adminlog(iif($avatarid!=0,"avatar id = $avatarid",""));

cpheader();

if (isset($action)==0) {
  $action="modify";
}

// ###################### Start Upload #######################
if ($action=="upload") {

  echo "<p><b>Using this feature required your web server and PHP to have permission to write files to disk. If they do not have the neccessary permissions, it will fail.</b></p>";

  doformheader("avatar","doupload",1);

  maketableheader("Upload New Avatar");
  makeuploadcode("Avatar file","avatarfile");

  makeinputcode("Name (optional)","title");
  makeinputcode("Minimum posts to use this avatar","minimumposts",0);
  makeinputcode("Path to save image to <BR>(no filename; no trailing slash; relative from main vB directory)","avatarpath");

  doformfooter("Upload Now");

}

// ###################### Start DoUpload #######################
if ($_POST['action']=="doupload") {

  if ($_FILES['avatarfile']) {
    $avatarfile = $_FILES['avatarfile']['tmp_name'];
    $avatarfile_name = $_FILES['avatarfile']['name'];
  }
  copy("$avatarfile","../$avatarpath/$avatarfile_name");

  $avatarpath.="/$avatarfile_name";

  echo "<p>avatar successfuly uploaded</p>";

  $_POST['action']="insert";
}

// ###################### Start Add #######################
if ($action=="add") {

 // Add single
  doformheader("avatar","insert");
  maketableheader("Add a single avatar");
  makeinputcode("Name","title");
  makeinputcode("Minimum posts to use this avatar","minimumposts");
  makeinputcode("Path to image","avatarpath");
  doformfooter("Add Avatar");

  // Add multiple
  doformheader("avatar","insertmultiple");
  maketableheader("Add multiple avatars");
  makeinputcode("Path to images (relative to your forums directory)","avatarpath","images/avatars");
  makeinputcode("Number of avatars to display per page?","iconsperpage",6);
  doformfooter("Add Avatars");
}

// ###################### Start Insert #######################
if ($_POST['action']=="insert") {

  $DB_site->query("INSERT INTO avatar (avatarid,title,minimumposts,avatarpath) VALUES (NULL,'".vb_escape($title)."','$minimumposts','".vb_escape($avatarpath)."')");

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
  $avatarpath = preg_replace("/\/$/s","",$avatarpath);
  if($dirhandle=@opendir('../'.$avatarpath)) {
     chdir('../'.$avatarpath);
     $ok_exit='no';

     doformheader("avatar","domultiple",0,0,"form");
     echo "<input type='hidden' value='$avatarpath' name='avatarpath'><input type='hidden' value='$iconsperpage' name='iconsperpage'>";
     echo "<table align='center' border='0' cellspacing='1' cellpadding='5' class='tblborder'>";
     echo "<tr class='secondalt'><td align='center' colspan='4'>Adding Multiple Avatars from<br><b>$bburl/$avatarpath</b></td></tr>";
     echo "<tr class='tblhead'><td align='center'><span class='tblhead'><b>Image</b></span></td><td align='center'><span class='tblhead'><b>Title</b> <font size='1'>(optional)</font></span></td><td align='center'><span class='tblhead'><b>Minimum Posts</b></span></td>";
     echo "<td align='center'><input name='allbox' type='checkbox' value='Check All' onClick='CheckAll();'></td>";
     $avatars=$DB_site->query("SELECT avatarpath FROM avatar");
     while ($avatar=$DB_site->fetch_array($avatars)) {
       $avatarArray[$avatar['avatarpath']] = 'yes';
     }
     while ($filename=readdir($dirhandle)) {
       $avatarname = $avatarpath . '/' . $filename;
       if ($filename!='.' and $filename!='..' and @is_file($filename) and !$avatarArray[$avatarname] and (($filelen=strlen($filename))>=5)) {
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
         echo "<img src='../$avatarpath/$FileArray[$x]' border=0 align='center'>";
         echo "<td align='center'><input type='text' name='avatartitle[$FileArray[$x]]' size=25></td>";
         echo "<td align='center'><input type='text' name='avatarposts[$FileArray[$x]]' size=8 value=0></td>";
         echo "<td align='center'><input type='checkbox' name='avataruse[$FileArray[$x]]' value='yes'></td></tr>";
       }
     }
     if ($numpages > 1) {
       for ($x = 1; $x <= $numpages; $x++) {
         if ($x == $startpage)
            $pagelinks .= "<b> $x </b>";
         else
            $pagelinks .= " <a href=\"avatar.php?startpage=$x&amp;iconsperpage=$iconsperpage&amp;action=insertmultiple&amp;avatarpath=$avatarpath\">$x</a> ";
       }
       if ($startpage != $numpages) {
         $nextstart = $startpage + 1;
         $nextpage = " <a href='avatar.php?startpage=$nextstart&amp;iconsperpage=$iconsperpage&amp;action=insertmultiple&amp;avatarpath=$avatarpath'>></a>";
         $eicon = $endicon + 1;
       } else
         $eicon = $iconcount;
       if ($startpage!=1) {
         $prevstart = $startpage - 1;
         $prevpage = "<a href='avatar.php?startpage=$prevstart&amp;iconsperpage=$iconsperpage&amp;action=insertmultiple&amp;avatarpath=$avatarpath'><</a> ";
       }
       $sicon = $starticon +  1;
       echo "<tr class='".getrowbg()."'><td align='center' colspan=4>Showing avatars $sicon to $eicon of $iconcount<br><br>$prevpage $pagelinks $nextpage</td></tr>";
     }
     echo '</table>';
     echo "<input type='hidden' name='iconcount' value='$iconcount'>";
     doformfooter("Save");
     closedir($dirhandle);
  } else {
     echo "<p><b>$avatarpath</b> could not be found";
  }
}

// ###################### Start Insert Multiple #######################
if ($_POST['action']=="domultiple") {
  if (is_array($avataruse)) {
    $count=0;
    while(list($key,$val)=each($avataruse)) {
      $filename = "$avatarpath/" . stripslashes($key);
      $posts = $avatarposts[$key];
      $title = $avatartitle[$key];
      $DB_site->query("INSERT INTO avatar (title,minimumposts,avatarpath) VALUES ('".vb_escape($title)."','$posts','".vb_escape($filename)."')");
      $count++;
    } //end while
    if ($count==$iconcount) { // We inserted all of our available avatars
      $action="modify";
    } else {
      echo "<script language=\"javascript\">window.location=\"avatar.php?action=insertmultiple&avatarpath=$avatarpath&iconsperpage=$iconsperpage\";</script>";
      echo "<p><a href=\"avatar.php?action=insertmultiple&amp;avatarpath=$avatarpath&amp;iconsperpage=$iconsperpage\">Click here to continue if you aren't forwarded</a></p>";
      exit;
    }
  } else {
    echo '<p>No Avatars Selected<p>';
  }
}

// ###################### Start Edit #######################
if ($action=="edit") {

  $avatar=$DB_site->query_first("SELECT title,minimumposts,avatarpath FROM avatar WHERE avatarid=$avatarid");

  doformheader("avatar","doupdate");
  maketableheader("Edit Avatar:</b> <i>$avatar[title]</i>","",0);
  makehiddencode("avatarid","$avatarid");
  makehiddencode("startpage","$startpage");
  makehiddencode("perpage","$perpage");
  makeinputcode("Name","title",$avatar['title']);
  makeinputcode("Minimum posts to use this avatar","minimumposts",$avatar['minimumposts']);
  makeinputcode("Path to image","avatarpath",$avatar['avatarpath']);

  doformfooter("Save Changes");

}

// ###################### Start Update #######################
if ($_POST['action']=="doupdate") {

  $DB_site->query("UPDATE avatar SET title='".vb_escape($title)."',minimumposts='$minimumposts',avatarpath='".vb_escape($avatarpath)."' WHERE avatarid='$avatarid'");

  echo "<p>Record updated!</p>";

  $action="modify";

}

// ###################### Start Remove #######################

if ($action=="remove") {

	doformheader("avatar","kill");
	makehiddencode("avatarid",$avatarid);
	makehiddencode("perpage",$perpage);
	makehiddencode("startpage",$startpage);
	maketableheader("Confirm deletion");
	makedescription("Are you sure you want to delete this avatar?");
	doformfooter("Yes","",2,"No");

}

// ###################### Start Kill #######################

if ($_POST['action']=="kill") {

  $DB_site->query("DELETE FROM avatar WHERE avatarid=$avatarid");
  echo "<p>Deleted!</p>";
  flush();
  echo "<p>Removing Avatar from any users who may be using it</p>";
  $DB_site->query("UPDATE user SET avatarid=0 WHERE avatarid=$avatarid");
  $action="modify";
}

// ###################### Start Modify #######################
if ($action=="modify") {
  if (intval($startpage)<1)
    $startpage = 1;
  if (intval($perpage)<1)
    $perpage = 25;
  $avatarcount=$DB_site->query_first("SELECT count(*) AS count FROM avatar");
  $totalavatars = $avatarcount['count'];
  if (($startpage-1)*$perpage > $totalavatars) {
     if ((($totalavatars / $perpage) - ((int) ($totalavatars / $perpage))) == 0)
       $startpage = $totalavatars / $perpage;
     else
       $startpage = (int) ($totalavatars / $perpage) + 1;
  }
  $limitlower=($startpage-1)*$perpage+1;
  $limitupper=($startpage)*$perpage;
    if ($limitupper>$totalavatars) {
      $limitupper=$totalavatars;
      if ($limitlower>$totalavatars) {
        $limitlower=$totalavatars-$perpage;
      }
    }
    if ($limitlower<=0) {
      $limitlower=1;
  }

  $avatars=$DB_site->query("SELECT minimumposts,avatarid,title,avatarpath FROM avatar ORDER BY minimumposts LIMIT ".($limitlower-1).",$perpage");

  doformheader("avatar","modify");
  maketableheader("Current Avatars","",1,5);

  $count=0;
  while ($avatar=$DB_site->fetch_array($avatars)) {

    if ($minposts!=$avatar['minimumposts']) {
       $minposts = $avatar['minimumposts'];
       while ($count!=5 and $count!=0) {
         echo '<td>&nbsp;</td>';
         $count++;
       }
       echo "<tr class='".getrowbg()."'><td align='center' colspan=5><b><font size='1'>-- $minposts Posts --</font></b></td></tr>";
       $count = 0;
    }
    if ($count==0)
      echo "<tr class='".getrowbg()."'>";
    echo "<td valign='bottom' nowrap align='center'><img src=\"".iif(substr($avatar['avatarpath'],0,7)!="http://" and substr($avatar['avatarpath'],0,1)!="/","../","")."$avatar[avatarpath]\"><br><b>$avatar[title]</b><br>\n";
    echo makelinkcode("edit","avatar.php?s=$session[sessionhash]&amp;action=edit&amp;avatarid=$avatar[avatarid]&amp;perpage=$perpage&amp;startpage=$startpage")."\n";
    echo makelinkcode("remove","avatar.php?s=$session[sessionhash]&amp;action=remove&amp;avatarid=$avatar[avatarid]&amp;perpage=$perpage&amp;startpage=$startpage")."\n";
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
  if ((($totalavatars / $perpage) - ((int) ($totalavatars / $perpage))) == 0)
    $numpages = $totalavatars / $perpage;
  else
    $numpages = (int) ($totalavatars / $perpage) + 1;
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
        $pagelinks .= " <a href=\"avatar.php?startpage=$x&amp;perpage=$perpage&amp;action=modify\">$x</a> ";
    }
  }
  if ($startpage != $numpages) {
    $nextstart = $startpage + 1;
    $nextpage = " <a href='avatar.php?startpage=$nextstart&amp;perpage=$perpage&amp;action=modify'>&gt;</a>";
    $eicon = $endicon + 1;
  } else
    $eicon = $totalavatars;
  if ($startpage!=1) {
    $prevstart = $startpage - 1;
    $prevpage = "<a href='avatar.php?startpage=$prevstart&amp;perpage=$perpage&amp;action=modify'>&lt;</a> ";
  }
  $sicon = $starticon +  1;
  echo "<tr class='".getrowbg()."'><td align=\"center\" colspan=5><table border=0><tr><td width=\"100%\" valign=\"top\" align=\"center\">";
  echo "<font size='1'><b>Showing avatars $sicon to $eicon of $totalavatars</b></font></td><td nowrap>";
  echo "<font size='1'><b>Perpage:</b></font> <input type=\"text\" name=\"perpage\" value=\"$perpage\" size=5> <input type=\"submit\" value=\"GO\">";
  echo "</td></tr></table><font size='1'><b>$prevpage $pagelinks $nextpage</b></font></td></tr>";
  echo '</table></td></tr></table></form>';

}
cpfooter();
?>