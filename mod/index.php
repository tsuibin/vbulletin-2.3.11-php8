<?php
require_once __DIR__ . '/../admin/php8compat.php';
error_reporting(7);

require("./global.php");

if ($redirect!="") {

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
<title>vBulletin Moderator's Control Panel</title>

<frameset cols="175,*"  framespacing="0" border="0" frameborder="0" frameborder="no" border="0">

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
?>
<HTML><HEAD>
<link rel="stylesheet" href="../cp.css">
</HEAD>
<BODY leftmargin="10" topmargin="0" marginwidth="0" marginheight="0" id="navbody">
<TABLE border=0 width="100%" height="100%">
  <TBODY>
  <TR valign="middle">
    <TD width="100%" nowrap><a href="http://www.vbulletin.com" target="_blank">Moderators' Control Panel (Version <?php echo $versionnumber; ?>)</a></TD>
    <TD nowrap><B><a href="../index.php?s=<?php echo $session['sessionhash']; ?>"
      target="_blank">Go to your Forums Home Page</A></B> </TD>
  </TR></TBODY></TABLE></BODY></HTML>
<?php
}

if ($action=="home") {

cpheader("<script language='javascript'> function jumpto(url) { if (url != '') { window.open(url); } } </script>");

?>

<p><b>Welcome to the vBulletin Moderators' Control Panel</b><br>
<font size='1'>Software Developed by <a href="http://www.jelsoft.com/">Jelsoft Enterprises Limited</a></font></p>

<?php

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
<input type="hidden" name="action" value="findnames">
<input type="hidden" name="s" value="<?php echo $session['sessionhash']; ?>">
<tr class="firstalt">
	<td>Quick User Finder</td>
	<td><input type="text" name="findname" size="30"> <span id="submitrow"><input type="submit" value="Find Now"></span></td>
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
<script language="javascript">
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
makenavoption("Add","announcement.php?action=add","|");
makenavoption("Edit","announcement.php?action=modify");
makenavselect("Announcements");
// *************************************************
makenavoption("New Posts","moderate.php?action=posts","<br>");
makenavoption("New Attachments","moderate.php?action=attachments");
makenavselect("Moderation Lists");
// *************************************************
makenavoption("Ban","user.php?action=find","|");
makenavoption("View","user.php?action=find");
makenavselect("User Actions");
// *************************************************
makenavoption("Mass Move","thread.php?action=move","<br>");
makenavoption("Mass Prune","thread.php?action=prune");
makenavselect("Thread Control","<hr>");
// *************************************************
?>
</table>
</BODY></HTML>
<?php
}

?>