<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

if (!$_POST['style'] && $_POST['action'] == "standard") {
  $noheader=1;
}

require("./global.php");

adminlog();

if (!$style && $action == "standard") { } else {
  cpheader();
}

// Start date dropdown function
function datelist($useDate=0,$prefix="") {
  $monthName = array(1=> "January",  "February",  "March", "April",  "May",  "June",  "July",  "August", "September",  "October",  "November",  "December");
  $temp = "<select name='".$prefix."month'>\n";
  for($currentMonth=1;$currentMonth<=12;$currentMonth++) {
    $temp .= "<option value='";
    $temp .= intval($currentMonth);
    $temp .= "'";
    if(intval(date( "m", $useDate))==$currentMonth) {
      $temp .= " selected";
    }
    $temp .= ">" . $monthName[$currentMonth] .  "\n";
  }
  $temp .= "</select>";

  $temp .= "<select name='".$prefix."day'>\n";
  for($currentDay=1;$currentDay <= 31;$currentDay++) {
    $temp .= "<option value='$currentDay'";
    if(intval(date( "d", $useDate))==$currentDay) {
      $temp .= " selected";
    }
    $temp .= ">$currentDay\n";
  }
  $temp .= "</select>";

  $temp .= "<select name='".$prefix."year'>\n";
  $startYear = date( "Y", $useDate);
  for($currentYear=$startYear-5;$currentYear<=$startYear+5;$currentYear++) {
    $temp .= "<option value=\"$currentYear\"";
    if(date( "Y", $useDate)==$currentYear) {
      $temp .= " selected";
    }
    $temp .= ">$currentYear\n";
  }
  $temp .= "</select>";

  return $temp;
}

if (isset($action)==0) {
  $action="index";
}

// ###################### Start standard #######################
if ($action=="index") {

  doformheader("stats","standard");
  maketableheader("View Statistics");
  echo "<tr class='firstalt'>\n<td><p>Type of Statistics</p></td>\n<td><p><select name=\"type\" size=\"1\">\n";
  echo "<option value=\"user\">New Users</option>\n";
  echo "<option value=\"post\">Posts</option>\n";
  echo "<option value=\"thread\">Threads</option>\n";
  echo "</select>\n</p></td>\n</tr>\n";
  echo "<tr class='secondalt'>\n<td><p>Date From</p></td>\n<td><p>\n";
  echo datelist(time()-3600*24*90,"from");
  echo "\n</p></td>\n</tr>\n";
  echo "<tr class='firstalt'>\n<td><p>Date To</p></td>\n<td><p>\n";
  echo datelist(time()+3600*24,"to");
  echo "\n</p></td>\n</tr>\n";
  echo "<tr class='secondalt'>\n<td><p>Timeframe</p></td>\n<td><p><select name=\"timeframe\" size=\"1\">\n";
  echo "<option value=\"day\">Daily</option>\n";
  echo "<option value=\"week\">Weekly</option>\n";
  echo "<option value=\"month\">Monthly</option>\n";
  echo "</select>\n</p></td>\n</tr>\n";
  echo "<tr class='firstalt'>
		<td><p>Sorting</p></td>
		<td><p>Recent dates first <input type=\"radio\" checked name=\"order\" value=\"1\"> Recent dates last <input type=\"radio\" name=\"order\" value=\"0\"></p></td>
	</tr>
	<tr class='secondalt'>
		<td><p>Format</p></td>
		<td><p>In-line HTML <input type=\"radio\" checked name=\"style\" value=\"1\"> Word Document (RTF) <input type=\"radio\" name=\"style\" value=\"0\"></p></td>
	</tr>";
  //echo str_replace("Yes","Recent dates first",str_replace("No","Recent dates last",makeyesnocode ("Sorting","order",1)));
  //echo str_replace("Yes","In-line HTML",str_replace("No","Word Document (RTF)",makeyesnocode ("Format","style",1)));
//  makeinputcode("User Name","ausername");
  doformfooter("Display Stats");

} elseif ($_POST['action'] == "standard") {

  if ($type == "thread") {
    $table = "thread";
    $field = "dateline";
  } elseif ($type == "post") {
    $table = "post";
    $field = "dateline";
  } elseif ($type == "user") {
    $table = "user";
    $field = "joindate";
  }

  if ($timeframe == "day") {
    $sqlformat = "%w %U %m %Y";
    $phpformat = "F dS, Y";
  } elseif ($timeframe == "week") {
    $sqlformat = "%U %Y";
    $phpformat = "# (F Y)";
  } elseif ($timeframe == "month") {
    $sqlformat = "%m %Y";
    $phpformat = "F Y";
  }

  if ($order) {
    $sort = "DESC";
  } else {
    $sort = "ASC";
  }

  $to = mktime(12,0,0,$tomonth,$today,$toyear);
  $from = mktime(12,0,0,$frommonth,$fromday,$fromyear);

  $stats = $DB_site->query("SELECT COUNT(*), DATE_FORMAT(FROM_UNIXTIME($field),'$sqlformat') AS timeframe, MAX($field) FROM $table WHERE $field > '$from' AND $field < '$to' GROUP BY timeframe ORDER BY $field $sort");
  unset($totals);
  unset($dates);
  unset($sum);
  while ($stat = $DB_site->fetch_array($stats)) {
    $totals[] = $stat[0];
    if ($stat[0]>$max) {
      $max=$stat[0];
    }
    $dates[] = str_replace(" ","&nbsp;",str_replace("#","Week ".date("W",$stat[2]),date($phpformat,$stat[2])));
    $rtfdt[] = str_replace("#","Week ".date("W",$stat[2]),date($phpformat,$stat[2]));
    $sum += $stat[0];
  }

  if (count($totals)==0) {
    if (!$style && $action == "standard") {
      cpheader();
    }
    print "No results";
    cpfooter();
    exit;
  }

  $average = $sum / count($totals);
  if ($style) {
    print "<table width='100%' cellpadding='4' cellspacing='1' border='0'>";
    print "<tr><td width='100%' colspan='2'><b>Date</b></td>\n<td width='0'>#</td>\n</tr>\n";
  } else {
    header("Content-Type: application/octet-stream; name=stats".date("Y-m-d").".doc");
    header("Content-Disposition: attachment; filename=stats".date("Y-m-d").".doc");
    header("Pragma: no-cache");
    header("Expires: 0");
    print "{\\rtf1\\ansi\\ansicpg1252\\deff0\\deflang1033{\\fonttbl{\\f0\\fnil\\fcharset0 Times New Roman;}{\\f1\\fnil\\fcharset2 Symbol;}}
{\\colortbl ;\\red255\\green0\\blue0;\\red0\\green0\\blue255;}
\\viewkind4\\uc1\\pard\\f0\\fs40\\i $bbtitle\\i0  Statistics\\par
\\fs20\\par
This is text, explaining the \\b stats\\b0 .\\par
\\par
Numbers in \\b\\cf2 blue\\cf0\\b0  indicate time periods that performed above the average for the date range. Numbers in \\b\\cf1 red\\cf0\\b0  indicate periods that performed below the average for the date range.\\par
\\par
\\pard{\\pntext\\f1\\'B7\\tab}{\\*\\pn\\pnlvlblt\\pnf1\\pnindent0{\\pntxtb\\'B7}}\\fi-720\\li720 #\\tab\\tab\\tab\\tab\\tab \\b Date\\b0\\cf0\\par
";
  }

  for ($i=0;$i<count($totals);$i++) {
    if ($totals[$i] > $average) {
      $color = 3;
      $rtf = 2;
    } else {
      $color = 2;
      $rtf = 1;
    }
    $width = intval($totals[$i] / $max * 100 - 10) . "%";
    if ($style) {
      print "<tr><td width='0'>".$dates[$i]."</td>\n";
      print "<td width='100%' nowrap><img src='../images/polls/bar$color-l.gif'><img src='../images/polls/bar$color.gif' width='$width' height='10'><img src='../images/polls/bar$color-r.gif'></td>\n";
      print "<td width='0%' nowrap>".$totals[$i]."</td></tr>\n";
    } else {
      print "{\\pntext\\f1\\'B7\\tab}\\cf$rtf ".$totals[$i]."\\cf0\\tab\\tab\\tab\\tab\\tab ".$rtfdt[$i]."\\par
";
    }
  }
  if ($style) {
    print "</table>";
  } else {
    print "\\pard\\par
\\par
\\par
Bulletin board: \\b $bbtitle\\b0  <$bburl/>\\par
Home page: \\b $hometitle\\b0  <$homeurl>\\par
\\par
\\par
Prepared by: \\b $bbuserinfo[username]\\b0  <$bbuserinfo[email]>\\par
Generated by: \\b vBulletin\\b0  <www.vbulletin.com>\\par
\\par
\\par
Published on: \\b ".date("r")."\\b0\\par
}
";
  }
}
//date("D, d M Y H:iS +0200")

if (!$style && $action == "standard") { } else {
  cpfooter();
}
?>