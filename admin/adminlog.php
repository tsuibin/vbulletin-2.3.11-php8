<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

unset($canviewadminlog);
unset($canpruneadminlog);

require("./global.php");

cpheader("<title>Admin Log Viewer</title>");

if (isset($action)==0) {
  $action="choose";
}

$sqlconds = "";
if ($userid!="") {
	if ($sqlconds=="") {
		$sqlconds .= "WHERE adminlog.userid='$userid' ";
	} else {
		$sqlconds .= "AND adminlog.userid='$userid' ";
	}
}
if ($script!="") {
	if ($sqlconds=="") {
		$sqlconds .= "WHERE adminlog.script='$script' ";
	} else {
		$sqlconds .= "AND adminlog.script='$script' ";
	}
}

// ###################### Start resolveip #######################
if ($action=="resolveip") {
	
	$resolved = gethostbyaddr($ip);
	if ($resolved==$ip) {
		echo "<p>Sorry, I was unable to resolve a host address for IP $ip.</p>";
	} else {
		echo "<p>Host address for IP $ip is: <b>$resolved</b></p>";
	}
	echo makelinkcode("go back","javascript:history.back(1)");
	cpfooter();
	exit;
	
}

// ###################### Start view #######################
if ($action=="view" && checklogperms($canviewadminlog,1,"<p>Admin log viewing restricted.</p>")) {

	if ($perpage=="") {
		$perpage = 15;
	}
	
	$counter = $DB_site->query_first("SELECT COUNT(*) AS total FROM adminlog $sqlconds");
	$totalpages = ceil($counter['total']/$perpage);

	if ($page=="") {
		$page = 1;
	}
	$startat = ($page-1)*$perpage;
	
	switch($orderby) {	
		case "user": $order = "username ASC,adminlogid DESC"; break;		
		case "date": $order = "adminlogid DESC"; break;		
		case "script": $order = "script ASC,adminlogid DESC"; break;		
		default: $order = "adminlogid DESC"; break;	
	}
	
	$logs = $DB_site->query("
		SELECT adminlog.*,user.username
		FROM adminlog
		LEFT JOIN user USING(userid)
		$sqlconds
		ORDER BY $order
		LIMIT $startat,$perpage
	");
	
	if ($DB_site->num_rows($logs)) {
	
		if ($page!=1) {
			$prv = $page-1;
			$firstpage = "<input type='button' value='&laquo; first page' onclick=\"window.location='adminlog.php?s=$session[sessionhash]&amp;action=view&amp;script=$script&amp;userid=$userid&amp;perpage=$perpage&amp;orderby=$orderby&amp;page=1'\">";
			$prevpage = "<input type='button' value='&lt; prev page' onclick=\"window.location='adminlog.php?s=$session[sessionhash]&amp;action=view&amp;script=$script&amp;userid=$userid&amp;perpage=$perpage&amp;orderby=$orderby&amp;page=$prv'\">";
		}
		
		if ($page!=$totalpages) {
			$nxt = $page+1;
			$nextpage = "<input type='button' value='next page &gt;' onclick=\"window.location='adminlog.php?s=$session[sessionhash]&amp;action=view&amp;script=$script&amp;userid=$userid&amp;perpage=$perpage&amp;orderby=$orderby&amp;page=$nxt'\">";
			$lastpage = "<input type='button' value='last page &raquo;' onclick=\"window.location='adminlog.php?s=$session[sessionhash]&amp;action=view&amp;script=$script&amp;userid=$userid&amp;perpage=$perpage&amp;orderby=$orderby&amp;page=$totalpages'\">";
		}
	
		doformheader("adminlog","remove");		
		maketableheader("vBulletin Administrator's Control Panel Log Viewer (page $page/".number_format($totalpages).") | There are ".number_format($counter['total'])." total log entries.");
		restarttable("<center><br>use the navigation controls at the bottom of the page to change the page you are viewing</center>");
		
		echo "<tr class='tblhead'>
			<td><b><span class='tblhead'>Log id</span></b></td>
			<td><b><a href='adminlog.php?s=$session[sessionhash]&amp;action=view&amp;script=$script&amp;userid=$userid&amp;perpage=$perpage&amp;orderby=user&amp;page=$page' title='order results by username'><span class='tblhead'>Username</span></a></b></td>
			<td><b><a href='adminlog.php?s=$session[sessionhash]&amp;action=view&amp;script=$script&amp;userid=$userid&amp;perpage=$perpage&amp;orderby=date&amp;page=$page' title='order results by date'><span class='tblhead'><span class='tblhead'>Date</span></a></b></td>
			<td><b><a href='adminlog.php?s=$session[sessionhash]&amp;action=view&amp;script=$script&amp;userid=$userid&amp;perpage=$perpage&amp;orderby=script&amp;page=$page' title='order results by script accessed'><span class='tblhead'><span class='tblhead'>Script</span></a></b></td>
			<td><b><span class='tblhead'>Action</span></b></td>
			<td><b><span class='tblhead'>Extra info</span></b></td>
			<td><b><span class='tblhead'>IP Address</span></b></td>
		</tr>\n";
		
		while ($log = $DB_site->fetch_array($logs)) {
			echo "<tr class='".getrowbg()."'>\n";
			echo "\t<td>$log[adminlogid]</td>\n";
			echo "\t<td><a href='user.php?s=$session[sessionhash]&amp;action=edit&amp;userid=$log[userid]'><b>$log[username]</b></a></td>\n";
			echo "\t<td><font size='1'>".vbdate("H:i, jS M Y",$log['dateline'])."</font></td>\n";
			echo "\t<td>$log[script]&nbsp;</td>\n";
			echo "\t<td>$log[action]&nbsp;</td>\n";
			echo "\t<td>$log[extrainfo]&nbsp;</td>\n";
			echo "\t<td><font size='1'>";
			if ($log['ipaddress']!="") {
				echo "<a href='adminlog.php?s=$session[sessionhash]&amp;action=resolveip&amp;ip=$log[ipaddress]'>$log[ipaddress]</a></font></td>\n";
			} else {
				echo "&nbsp;</font></td>\n";
			}
			echo "</tr>\n";
		}
		
		echo "<tr id='submitrow'><td colspan='7' align='center'>$firstpage $prevpage &nbsp; $nextpage $lastpage</td></tr>\n";
		echo "</table></td></tr></table></form>";
		
		echo makelinkcode("restart","adminlog.php?s=$session[sessionhash]");
		
	} else {
		echo "<p>No records matched your query. Returning you to the start page.</p>";
		$action = "choose";
	}

}

// ###################### Start prune log #######################
if ($action=="prunelog" && checklogperms($canpruneadminlog,0,"<p>Admin log pruning permission restricted.</p>")) {

	$datecut = time() - (86400 * intval(trim($daysprune)));
	$query = "SELECT COUNT(*) AS total FROM adminlog WHERE dateline<$datecut";
	if ($script!="") {
		$query .= "\nAND script='$script'";
	}
	if ($userid!="") {
		$query .= "\nAND userid='$userid'";
	}
	//echo "<pre>$query</pre>";
	
	$logs = $DB_site->query_first($query);
	if ($logs['total']) {
		doformheader("adminlog","doprunelog");
		makehiddencode("datecut",$datecut);
		makehiddencode("script",$script);
		makehiddencode("userid",$userid);
		maketableheader("Confirm Log Prune");
		makedescription("Are you sure you want to prune these ".number_format($logs['total'])." log entries from the admin log?");
		doformfooter("Yes, delete them",0,0,"Oops, no!");
	} else {
		echo "<p><b><font size='1'>Error: No logs matched your search conditions.</font></b></p>";
		$action = "choose";
	}

}

// ###################### Start do prune log #######################
if ($_POST['action']=="doprunelog" && checklogperms($canpruneadminlog,0,"<p>Admin log pruning permission restricted.</p>")) {

	$query = "DELETE FROM adminlog WHERE dateline<$datecut";
	if ($script!="") {
		$query .= "\nAND script='$script'";
	}
	if ($userid!="") {
		$query .= "\nAND userid='$userid'";
	}
	//echo "<pre>$query</pre>";
	
	$DB_site->query($query);
	
	echo "<p><b>Admin Log Pruned.</b></p>\n";
	$action = "choose";
	
}

// ###################### Start modify #######################
if ($action=="choose") {

	adminlog();
	
	if (checklogperms($canviewadminlog,1,"<p>Admin log viewing restricted.</p>")) {
		$handle=opendir("./");
		while ($file = readdir($handle)) {
			if (preg_match("/php$/",$file)) {
				if ($file!="adminfunctions.php" &&
					$file!="functions.php" &&
					$file!="global.php" &&
					$file!="db_mysql.php" &&
					$file!="badwords.php" &&
					$file!="config.php") {
					$filelist .= "<option value='$file'>$file</option>\n";
				}
			}
		}
		closedir($handle);
		
		$users = $DB_site->query("
			SELECT DISTINCT adminlog.userid,user.username
			FROM adminlog
			LEFT JOIN user USING(userid)
			ORDER BY username
		");
		while ($user = $DB_site->fetch_array($users)) {
			$userlist .= "<option value='$user[userid]'>$user[username]</option>\n";
		}
		
		doformheader("adminlog","view");
		maketableheader("vBulletin Administrator's Control Panel Log Viewer");
		makelabelcode("Log entries to display per page","<select name='perpage'>
			<option value='5'>5</option>
			<option value='10'>10</option>
			<option value='15' selected>15</option>
			<option value='20'>20</option>
			<option value='25'>25</option>
			<option value='30'>30</option>
			<option value='40'>40</option>
			<option value='50'>50</option>
			<option value='100'>100</option>
			<option value='$counter[total]'>------------------------------</option>
		</select>");
		makelabelcode("Show only access to this script:","<select name='script'>
			<option value=''>------------- all -------------</option>
			$filelist</select>");
		makelabelcode("Show only logs generated by:","<select name='userid'>
			<option value=''>------------- all -------------</option>
			$userlist</select>");
		makelabelcode("Order log entries by ...","<select name='orderby'>
			<option value='date' selected>date</option>
			<option value='user'>user name</option>
			<option value='script'>script accessed</option>
			<option value=''>------------- all -------------</option>
		</select>");
		doformfooter("View Logs",0);
	}
	
	if (checklogperms($canpruneadminlog,0,"<p>Admin log pruning permission restricted.</p>")) {
		doformheader("adminlog","prunelog");
		maketableheader("Prune Admin Log");
		makelabelcode("Remove entries relating script:","<select name='script'>
			<option value=''>------------- all -------------</option>
			$filelist</select>");
		makelabelcode("Remove entries logged by user:","<select name='userid'>
			<option value=''>------------- all -------------</option>
			$userlist</select>");
		makeinputcode("Remove entries older than days:","daysprune",30);
		doformfooter("Prune Logs",0);
	}
	
}

// ###################### Start help #######################
if ($action=="help") {
?>
<p><b>Instructions for limiting view/prune access to the admin log.</b></p>

<hr>

<p>Access control to the admin log is <b>not</b> controlled through the control panel for security reasons.<br>
Access to the admin log is controlled with two variables, which you can insert in the admin/config.php file.</p>

<p>Both variables can take either a single number, or a list of numbers separated by commas, each representing userids which have access to the admin log.</p>

If you would like to limit access to the admin log to a few selected administrators, you may do so by opening <i>admin/config.php</i> in a text editor, and adding a pair of lines like these:</p>

<pre>	$canviewadminlog = "<?php echo $bbuserinfo['userid']; ?>,10";
	$canpruneadminlog = "<?php echo $bbuserinfo['userid']; ?>";</pre>

<p>This will restrict viewing access to this script to only you (userid <?php echo $bbuserinfo['userid']; ?>) and the member with userid 10. Access to prune entries from the log is restricted to you (userid <?php echo $bbuserinfo['userid']; ?>).

<p>If the $canviewadminlog line is ommitted from config.php, or if the value is blank (<i>$canviewadminlog = "";</i>), all administrators will have access to view and prune the admin log.<br>
Conversely, if the $canpruneadminlog line is ommitted, <i>nobody</i> will be granted pruning permission.</p>

<hr>

<form><b>Example config.php</b><br>
<textarea rows="20" cols="80" wrap="off">&lt;?php

/////////////////////////////////////////////////////////////
// Please note that if you get any errors when connecting, //
// that you will need to email your host as we cannot tell //
// you what your specific values are supposed to be        //
/////////////////////////////////////////////////////////////

// type of database running
// (only mysql is supported at the moment)
$dbservertype="mysql";

// hostname or ip of server
$servername="localhost";

// username and password to log onto db server
$dbusername="yourusername";
$dbpassword="yourpassword";

// name of database
$dbname="yourforum";

// technical email address - any error messages will be emailed here
$technicalemail = "yourtechemail@somewhere.com";

// use persistant connections to the database
// 0 = don't use
// 1 = use
$usepconnect = 0;

$canviewadminlog = "<?php echo $bbuserinfo['userid']; ?>";
$canpruneadminlog = "<?php echo $bbuserinfo['userid']; ?>";

?&gt;</textarea></form>
<hr>
<?php
}

echo "<p align='center'><font size='1'><a href='adminlog.php?s=$session[sessionhash]&amp;action=help'>Want to restrict access to this script?</a></font></p>";

cpfooter();

?>