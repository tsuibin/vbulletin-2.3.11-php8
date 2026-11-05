<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

require("./global.php");


if ($action=="") {

  adminlog();
}

if ($redirect!="") {

  $redirect=ereg_replace("sessionhash=[a-z0-9]{32}&","",$redirect);
  $redirect=ereg_replace("\\?sessionhash=[a-z0-9]{32}","",$redirect);
  $redirect=ereg_replace("s=[a-z0-9]{32}&","",$redirect);
  $redirect=ereg_replace("\\?s=[a-z0-9]{32}","",$redirect);

  if (strpos($redirect,"?")>0) {
    $redirect.="&amp;s=$session[dbsessionhash]";
  } else {
    $redirect.="?s=$session[dbsessionhash]";
  }

  cpheader("<meta http-equiv=\"Refresh\" content=\"0; URL=$redirect\">");
  echo "<p>Hang on a sec</p>";
  cpfooter();
  exit;
}

if (isset($action)==0) {
  $action="frames";
}

if ($action=="phpinfo") {
  phpinfo();
}

if ($action=="frames") {
?>
<html>
<head>
<title><?php echo $bbtitle?> Control Panel</title>

<frameset cols="180,*"  framespacing="0" border="0" frameborder="0" frameborder="no" border="0">

<frame src="index.php?s=<?php echo $session['sessionhash']; ?>&amp;action=nav&amp;cpnavjs=<?php echo $cpnavjs; ?>" name="nav" scrolling="AUTO" NORESIZE frameborder="0" marginwidth="0" marginheight="0" border="no">

<frameset rows="20,*"  framespacing="0" border="0" frameborder="0" frameborder="no" border="0">
<frame src="index.php?s=<?php echo $session['sessionhash']; ?>&amp;action=head" name="head" scrolling="NO" NORESIZE frameborder="0" marginwidth="10" marginheight="0" border="no" >

<frame src="<?php

if ($loc!="") {
  echo $loc;
} else {
  echo "index.php?s=$session[sessionhash]&amp;action=home";
}

?>" name="main" scrolling="AUTO" NORESIZE frameborder="0" marginwidth="10" marginheight="10" border="no">

</frameset>
</frameset>
</head>
</html><?php
}

if ($action=="head") {
	$fp = @fsockopen('version.vbulletin.com', 80, $errno, $errstr, 3);
	$headjs = '';
	if ($fp)
	{
		fclose($fp);
		$headjs = '<script type="text/javascript" src="http://version.vbulletin.com/version.js?v=' . SIMPLE_VERSION . '&amp;id=VBFBF97DF0"></script>';
	}
?>
<html><head><link rel="stylesheet" href="../cp.css">
<?php echo $headjs; ?>
</head>
<body leftmargin="10" topmargin="0" marginwidth="0" marginheight="0" id="navbody">
<script type="text/javascript">
	<!--
	if (typeof(vb_version) == "undefined")
	{
		vb_version = 'N/A';
	}
	// -->
	</script>
<table border="0" width="100%" height="100%">
<tr valign="middle">
	<td><a href="http://www.vbulletin.com/" target="_blank">Control Panel (Version <?php echo $versionnumber.doformiddle("VBFBF97DF0"); ?>)</a></td>
	<td align="center"><a href="http://vbulletin.com/members/" target="_blank">Latest version of vBulletin available is <script language="javascript" type="text/javascript">document.write(vb_version);</script>.</a></td>
	<td align="right"><b><a href="../index.php?s=" target="_blank">Go to your Forums Home Page</a></b></TD>
</tr>
</table>
</body></html>
<?php
}

if ($action=="home") {

cpheader("<script language=\"javascript\" type=\"text/javascript\"> function jumpto(url) { if (url != '') { window.open(url); } } </script>");
?>
<p><b>Welcome to the vBulletin Administrators' Control Panel</b><br>
<font size='1'>Software Developed by <a href="http://www.jelsoft.com/">Jelsoft Enterprises Limited</a></font></p>

<?php
$fp = @fsockopen('version.vbulletin.com', 80, $errno, $errstr, 3);
if ($fp)
{
	fclose($fp);
?>

<script language="javascript" type="text/javascript" src="http://version.vbulletin.com/versioncheck.js"></script>
<script language="javascript" type="text/javascript" src="http://version.vbulletin.com/version.js?v=<?php echo SIMPLE_VERSION; ?>&amp;id=VBFBF97DF0"></script>
<script language="Javascript" type="text/javascript">
<!--
if (typeof(vb_version) != "undefined" && isNewerVersion("<?php echo $templateversion; ?>", vb_version)) {
	document.write("<div align=\"center\" class=\"tblhead\" style=\"padding:4px\"><b><a href=\"http://vbulletin.com/forum/showthread.php?postid="+vb_announcementid+"\"><span class=\"tblhead\"><font size=\"2\">There is a newer version of vBulletin than the version you are running!</font></span></a></b>");
	document.write("<br><a href=\"http://vbulletin.com/members/\"><span class=\"tblhead\">Download vBulletin version "+vb_version+" from the Members' Area</span></a>.</div>");
}
//-->
</script>

<?php
}
?>

<p><font size='1'>From here, you can control all aspects of your vBulletin forums.
Please select what you need from the links down the left hand side of this page.</font><p>

<?php

if ($moderatenewmembers==1 or $usecoppa==1) {
  $waiting=$DB_site->query_first("SELECT COUNT(*) AS users FROM user WHERE usergroupid=4");
  if ($waiting['users']==0) {
    echo "<font size='1'>There are currently $waiting[users] user(s) awaiting <a href=\"user.php?s=$session[sessionhash]&amp;action=moderate\">moderation</a>.</font>";
  } else {
    echo "<b><a href=\"user.php?s=$session[sessionhash]&amp;action=moderate\">There are currently $waiting[users] user(s) awaiting moderation</a>.</b>";
  }
}

doformheader("user","find");
maketableheader("Useful Admin Stuff");

if ($stats = @exec("uptime")) {
	$datecut=time()-$cookietimeout;
	$guestsarry = $DB_site->query_first("SELECT COUNT(host) AS sessions FROM session WHERE userid=0 AND lastactivity>$datecut");
	$membersarry = $DB_site->query("SELECT DISTINCT userid FROM session WHERE userid<>0 AND lastactivity>$datecut");

	$guests = number_format($guestsarry['sessions']);
	$members = number_format($DB_site->num_rows($membersarry));

	$onlineusers = number_format($guests + $members) . " users online ($members members &amp; $guests guests).";

	preg_match("/averages?: ([0-9\.]+),[\s]+([0-9\.]+),[\s]+([0-9\.]+)/",$stats,$regs);
	echo "<tr class=\"secondalt\"><td>Server Load Averages</td><td><b>$regs[1], $regs[2], $regs[3]</b> $onlineusers</td></tr>\n";
}
?>
<form action="user.php" method="get">
<input type="hidden" name="action" value="find">
<input type="hidden" name="s" value="<?php echo $session['sessionhash']; ?>">
<tr class="firstalt">
	<td>Quick User Finder</td>
	<td><input type="text" name="ausername" size="30"> <span id="submitrow"><input type="submit" value="Find Now"></span></td>
</tr>
</form>
<form action="http://www.php.net/manual-lookup.php" method="get" target="_blank">
<tr class="secondalt">
	<td>PHP Function Lookup</td>
	<td><input type="text" name="function" size="30"> <span id="submitrow"><input type="submit" value="Find Now"></span></td>
</tr>
</form>
<form action="http://www.mysql.com/doc/manual.php" method="get" target="_blank">
<input type="hidden" name="depth" value="2">
<tr class="firstalt">
	<td>MySQL Language Lookup</td>
	<td><input type="text" name="search_query" size="30"> <span id="submitrow"><input type="submit" value="Find Now"></span></td>
</tr>
<tr class="secondalt">
	<td>Useful Links</td>
	<td><select onchange="jumpto(this.options[this.selectedIndex].value)">
		<option>&raquo; Useful Links &laquo;</option>
		<option value="http://www.vbulletin.com/">vBulletin Home Page</option>
		<option value="http://www.vbulletin.com/members/">vBulletin Members' Area</option>
		<option value="http://www.vbulletin.com/forum/">vBulletin Support Forums</option>
		<option value="http://www.vbulletin.com/manual/">vBulletin Online Manual</option>
		<option value="http://www.php.net/">PHP Home Page</option>
		<option value="http://www.php.net/manual/">PHP Online Manual</option>
		<option value="http://www.mysql.com/">MySQL Home Page</option>
		<option value="http://www.mysql.com/documentation/">MySQL Documentation</option>
	</select></td>
</tr>
</form>
</table>
</td></tr></table>
</form>
<?php

// vBulletin Credits
doformheader("","");
maketableheader("vBulletin Developers & Contributors");
makelabelcode("<b>Product Manager:</b>","<a href=\"http://www.vbulletin.com/forum/member.php?s=&amp;action=getinfo&amp;userid=1\" target=\"_blank\">John Percival</a>");
makelabelcode("<b>Business Development:</b>","<a href=\"http://www.vbulletin.com/forum/member.php?s=&amp;action=getinfo&amp;userid=2\" target=\"_blank\">James Limm</a>");
makelabelcode("<b>Developers:</b>","
	<a href=\"http://www.vbulletin.com/forum/member.php?s=&amp;action=getinfo&amp;userid=37\" target=\"_blank\">Mike 'Ed' Sullivan</a>,
	<a href=\"http://www.vbulletin.com/forum/member.php?s=&amp;action=getinfo&amp;userid=224\" target=\"_blank\">Freddie Bingham</a>,
	<a href=\"http://www.vbulletin.com/forum/member.php?s=&amp;action=getinfo&amp;userid=1034\" target=\"_blank\">Kier Darby</a>,
	<a href=\"http://www.vbulletin.com/forum/member.php?s=&amp;action=getinfo&amp;userid=40\" target=\"_blank\">Chris 'Stallion' Lambert</a>,
	<a href=\"http://www.vbulletin.com/forum/member.php?s=&amp;action=getinfo&amp;userid=146\" target=\"_blank\">Jim Frasch</a>
");
makelabelcode("<b>Polls:</b>","
	<a href=\"http://www.vbulletin.com/forum/member.php?s=&amp;action=getinfo&amp;userid=65\" target=\"_blank\">Doron Rosenberg</a>");
makelabelcode("<b>Other Addons:</b>","
	<a href=\"http://www.vbulletin.com/forum/member.php?s=&amp;action=getinfo&amp;userid=2751\" target=\"_blank\">Kevin 'Tubedogg' S.</a>,
	<a href=\"http://www.vbulletin.com/forum/member.php?s=&amp;action=getinfo&amp;userid=5755\" target=\"_blank\">Chen 'FireFly' Avinadav</a>
");
makelabelcode("<b>Graphics:</b>","
	<a href=\"http://www.vbulletin.com/forum/member.php?s=&amp;action=getinfo&amp;userid=1034\" target=\"_blank\">Kier</a>,
	<a href=\"http://www.vbulletin.com/forum/member.php?s=&amp;action=getinfo&amp;userid=19\" target=\"_blank\">Menno</a>,
	<a href=\"http://www.vbulletin.com/forum/member.php?s=&amp;action=getinfo&amp;userid=5137\" target=\"_blank\">Robin 'Isoeph' Morrison</a>
");
makelabelcode("<b>Graphics</b> (logo):","<a href=\"mailto:peter@nrg.be\">Peter Van den Wyngaert</a> ( <a href=\"http://www.nrg.be\" target=\"_blank\">NRG.BE</a> )");
echo "</table></td></tr></table></form>\n";
// end credits

?>

<p align="center"><font size='1'>&copy;2000 - <?php echo date('Y'); ?> Jelsoft Enterprises Ltd.<br>
<script language="javascript" type="text/javascript">
<!--
if (typeof(vb_version) != "undefined")
{
	if (isNewerVersion("<?php echo $templateversion; ?>", vb_version))
	{
		document.write("Latest version of vBulletin available: <a href=\"http://vbulletin.com/forum/showthread.php?postid="+vb_announcementid+"\">"+vb_version+"</a>; Your version: <?php echo $templateversion; ?>.");
	}
	else
	{
		document.write("You are running the latest version of vBulletin (<a href=\"http://vbulletin.com/forum/showthread.php?postid="+vb_announcementid+"\">"+vb_version+"</a>)");
	}
}
//-->
</script>
</font></p>
</body>
</html>

<?php
}

if ($action=="nav") {
?>
<html><head>
<!--<meta http-equiv="MSThemeCompatible" content="No">-->
<link rel="stylesheet" href="../cp.css">
<style type="text/css">.opt {COLOR: #3F3849; BACKGROUND-COLOR:#FFFFFF; FONT-SIZE:11px}</style>
<script language="javascript" type="text/javascript">
function navlink(u,f) {
f.reset();
if (u!="") { parent.frames.main.location=u+"&amp;s=<?php echo $session['sessionhash']; ?>"; }
}
</script>
<base target="main">
</head>
<body leftmargin="0" topmargin="0" marginwidth="0" marginheight="0" id="navbody">
<form><a href="http://www.vbulletin.com" target="_blank"><img src="./cp_logo.gif" width="160" height="49"<?php $df=doformiddle("VBFBF97DF0"); ?> border="0"></a>

<center><a href="index.php?s=<?php echo $session['sessionhash']; ?>&amp;action=home"> Control Panel Home </a></center>
<table width="100%" border="0" cellspacing="0" cellpadding="<?php echo iif($cpnavjs,2,5); ?>" id="navtable">

<tr><td><hr></td></tr>
<?php
// *************************************************
makenavoption("vBulletin Options","options.php?t=0","<br>");
if($debug==1) {
	makenavoption("Edit Settings","setting.php?action=modify","<br>");
	makenavoption("Add Setting","setting.php?action=add","<br>");
	makenavoption("Add Setting Group","setting.php?action=addgroup");
}
makenavselect("Options","<hr>");
// *************************************************
makenavoption("Add","announcement.php?action=add","|");
makenavoption("Modify","announcement.php?action=modify");
makenavselect("Announcements");
// ***
makenavoption("Add","forum.php?action=add","|");
makenavoption("Modify","forum.php?action=modify","<br>");
makenavoption("Permissions","forumpermission.php?action=modify");
makenavselect("Forums & Moderators");
// ***
makenavoption("Mass Prune","thread.php?action=prune","|");
makenavoption("Mass Move","thread.php?action=move","<br />");
makenavoption("Unsubscribe Thread","thread.php?action=unsubscribe","<br />");
makenavoption("Strip Poll","thread.php?action=killpoll","|");
makenavoption("Who Voted?","thread.php?action=votes");
makenavselect("Threads & Posts");
// ***
makenavoption("New Posts","../mod/moderate.php?action=posts","<br>");
makenavoption("New Attachments","../mod/moderate.php?action=attachments");
makenavselect("Moderation","<hr>");
// *************************************************
makenavoption("Add","user.php?action=add","|");
makenavoption("Find","user.php?action=modify","|");
makenavoption("Move/Prune","user.php?action=prune","<br>");
makenavoption("PM Statistics","user.php?action=pmstats","<br>");
makenavoption("IP Addresses","user.php?action=doips","<br>");
makenavoption("Referrals","user.php?action=referrers","<br>");
makenavoption("Email Users","email.php?action=start","<br>");
makenavoption("Build Mailing List","email.php?action=genlist");
makenavselect("Users");
// ***
makenavoption("Add","usertitle.php?action=add","|");
makenavoption("Modify","usertitle.php?action=modify");
makenavselect("User Titles");
// ***
makenavoption("Add","profilefield.php?action=add","|");
makenavoption("Modify","profilefield.php?action=modify");
makenavselect("User Profile Fields");
// ***
makenavoption("Add","usergroup.php?action=add","|");
makenavoption("Modify","usergroup.php?action=modify","<br>");
makenavoption("Forum Permissions","forumpermission.php?action=modify");
makenavselect("User Groups","<hr>");
// *************************************************
makenavoption("Add","avatar.php?action=add","|");
makenavoption("Modify","avatar.php?action=modify","|");
makenavoption("Upload","avatar.php?action=upload");
makenavselect("Avatars");
// ***
makenavoption("Add","icon.php?action=add","|");
makenavoption("Modify","icon.php?action=modify","|");
makenavoption("Upload","icon.php?action=upload");
makenavselect("Post Icons");
// ***
makenavoption("Add","smilie.php?action=add","|");
makenavoption("Modify","smilie.php?action=modify","|");
makenavoption("Upload","smilie.php?action=upload");
makenavselect("Smilies");
// ***
makenavoption("Add","bbcode.php?action=add","|");
makenavoption("Modify","bbcode.php?action=modify");
makenavselect("Custom vB Codes","<hr>");
// *************************************************
makenavoption("Back-up Database","backup.php?action=choose","<br>");
makenavoption("BB Import Systems","bbimport.php?action=","<br>");
makenavoption("Update Counters","misc.php?action=chooser");
makenavselect("Import & Maintenance");
// ***
makenavoption("Statistics","stats.php?action=index","<br>");
makenavoption("Admin Log","adminlog.php?action=choose");
makenavselect("Statistics & Logs","<hr>",$df);
// *************************************************
makenavoption("Add Style","style.php?action=add","|");
makenavoption("Modify","style.php?action=modify","<br>");
makenavoption("Download/Upload","style.php?action=download");
makenavselect("Styles");
// ***
makenavoption("Add","replacement.php?action=add","|");
makenavoption("Modify","replacement.php?action=modify","|");
makenavoption("Add Set","replacement.php?action=addset");
makenavselect("Replacements");
// ***
makenavoption("Add","template.php?action=add","|");
makenavoption("Modify","template.php?action=modify","|");
makenavoption("Search","template.php?action=search","<br>");
makenavoption("Add Template Set","template.php?action=addset");
if ($debug==1) {
	makenavoption("Download Set","template.php?action=downloadset","<br>");
	makenavoption("Upload Set","template.php?action=uploadset","<br>");
	makenavoption("Do <img> Tags","template.php?action=imgtags");
}
makenavselect("Templates","<hr>");
// *************************************************
?>
</table>
</form></BODY></HTML>
<?php
}

?>
