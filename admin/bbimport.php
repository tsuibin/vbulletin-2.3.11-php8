<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

// Quick explanation:
/*
Import files must be named bbimport_xxx.php(3) where xxx is a shortname for the bb system
The files must begin like this:
-----------------------------------------
LINE 1: <?php
LINE 2: // Bulletin Board System Name
LINE 3: require ("../admin/bbimport.php");
-----------------------------------------
For example:
-----------------------------------------
<?php
// Infopop Ultimate Bulletin Board 5.x
require ("../admin/bbimport.php");
-----------------------------------------
The initial step of your import script must be
  if ($action=="start") { ...
*/

// ##############################################################################
// Jump to correct import script

if ($_POST['action']=="choosebb") {
  if ($_POST['importbb']=="membersarea") {
    header("Location: http://vbulletin.com/members/");
  } else {
    header("Location: ../importers/bbimport_$_POST[importbb].php?s=$_POST[s]&action=start");
  }
}


// ##############################################################################
// Connect to DB
chdir ("./../admin");
include ("./../admin/global.php");

// ##############################################################################
// Display list of available import scripts
if ($action=="") {
  cpheader("<title>vBulletin 2 Import Systems</title>");

  echo "<p>Please choose a bulletin board system from which to import from the list below.</p>\n";
  echo "<p>Once you have chosen a system, proceed through the import routine. <b>Do not</b> press your browser's 'back' button, or refresh any pages, as this can lead to items being imported twice, which would be bad...</p>\n";

  doformheader("../admin/bbimport","choosebb");
  maketableheader("vBulletin 2 Bulletin Board Import System");
  echo "<tr class='".getrowbg()."' valign='top'><td><p>Choose a bulletin board system to import from.</p><p><font size='1'>".makelinkcode("get more import scripts","http://vbulletin.com/members/",1)."</font></p></td>\n";
  echo "<td><select name='importbb' size='5'>\n";

  $handle=@opendir("../importers");
  while ($handle && ($file = readdir($handle))) {
    if (preg_match("/^bbimport_(.*)\./",$file,$regs)) {
      $gotimporters = 1;
      $importfile = fopen("../importers/$file","r");
      fseek($importfile,9);
      echo "\t<option value='$regs[1]'>".htmlspecialchars(str_replace("// ","",trim(fgets($importfile,255))))."</option>\n";
      flush();
    }
  }
  if ($handle) { closedir($handle); }

  if (!$gotimporters) {
    echo "<option value='membersarea'>You do not currently have any import scripts to use.</option>\n";
    echo "<option value='membersarea'>A full list of the currently available import scripts</option>\n";
    echo "<option value='membersarea'>can be found in the vBulletin Members' area.</option>\n";
  }


  echo "</select></td></tr>\n";
  doformfooter("Begin Import");

  echo "<p>It is highly recommended the you <b>back up your database</b> before proceeding with this process.</p>\n";

  cpfooter();
}


// ##############################################################################
// List existing vB users for association purposes
if ($action=="listvbusers") {
  cpheader("<title>Existing $bbtitle Users, with user id numbers.</title>");
  echo "<form><font size='1'><b>$bbtitle Members:<br><select name='vbuserid' size='30' onchange='this.form.vbid.value=this.options[this.selectedIndex].value' style='font-size:11px'>\n";
  $vbusers = $DB_site->query("SELECT userid,username,importuserid FROM user ORDER BY username");
  while ($vbuser = $DB_site->fetch_array($vbusers)) {
    echo "<option value='$vbuser[userid]'>$vbuser[username] &raquo; $vbuser[userid]\n";
  }
  echo "</select><br>User ID: <input type='text' name='vbid' size='12'></b></font></form>\n";
  cpfooter();
}

// ##############################################################################
// ##############################################################################
// ##################o>> I M P O R T  F U N C T I O N S <<o######################
// ##############################################################################
// ##############################################################################





// ##############################################################################
// adds temporary fields to the database for importing

function initdb() {
  global $DB_site;

  $DB_site->reporterror = 0;
  // Table to hold some OT stuff
  $DB_site->query("CREATE TABLE importtable (
                                importid int(11) NOT NULL auto_increment,
                                forumid smallint(6) DEFAULT '0' NOT NULL,
                                filename char(255) NOT NULL,
                                PRIMARY KEY (importid),
                                KEY forumid (forumid)
                                  )");
  $DB_site->query("ALTER TABLE user ADD importuserid BIGINT UNSIGNED not null");
  $DB_site->query("ALTER TABLE user ADD isnew INT (1) UNSIGNED not null");
  $DB_site->query("ALTER TABLE user ADD INDEX userimport (importuserid, isnew)");
  $DB_site->query("ALTER TABLE userfield ADD importuserid INT (10) UNSIGNED not null");
  $DB_site->query("ALTER TABLE userfield ADD isnew INT (1) UNSIGNED not null");
  $DB_site->query("ALTER TABLE usergroup ADD importusergroupid INT (10) UNSIGNED not null");
  $DB_site->query("ALTER TABLE style ADD importstyleid SMALLINT (5) UNSIGNED not null");
  $DB_site->query("ALTER TABLE replacementset ADD importreplacementsetid SMALLINT (5) UNSIGNED not null");
  $DB_site->query("ALTER TABLE templateset ADD importtemplatesetid SMALLINT (5) UNSIGNED not null");
  $DB_site->query("ALTER TABLE forum ADD importcategoryid SMALLINT (5) UNSIGNED not null");
  $DB_site->query("ALTER TABLE forum ADD importforumid SMALLINT (5) UNSIGNED not null");
  $DB_site->query("ALTER TABLE forum ADD isprivate INT (5) UNSIGNED not null");
  $DB_site->query("ALTER TABLE forumpermission ADD importforumpermissionid INT (10) UNSIGNED not null");
  $DB_site->query("ALTER TABLE moderator ADD importmoderatorid INT (10) not null");
  $DB_site->query("ALTER TABLE access ADD importaccessid INT (10) not null");
  $DB_site->query("ALTER TABLE thread ADD importthreadid INT (10) not null");
  $DB_site->query("ALTER TABLE subscribethread ADD importsubscribethreadid INT (10) not null");
  $DB_site->query("ALTER TABLE subscribeforum ADD importsubscribeforumid INT (10) not null");
  $DB_site->query("ALTER TABLE post ADD importpostid INT (10) not null");
  $DB_site->query("ALTER TABLE poll ADD importpollid CHAR(20) not null"); // support for w3t pollid which is a string
  $DB_site->query("ALTER TABLE privatemessage ADD importpmid INT (10) not null");
  $DB_site->query("ALTER TABLE attachment ADD importattachmentid INT (10) not null");
  // make usergroup for imported banned members
  $result = $DB_site->query("SELECT usergroupid FROM usergroup WHERE title='Imported Banned Users'");
  if ($DB_site->num_rows($result)==0) {
    $DB_site->query("INSERT INTO usergroup (title,usertitle,importusergroupid) VALUES ('Imported Banned Users','Banned',1)");
  }
  $DB_site->reporterror = 1;

  echo "<p>Database initialized for import.</p>\n";

}

// ##############################################################################
// removes temporary fields from the database after importing

function cleandb($doclear=0) {
  global $DB_site;

  $DB_site->reporterror = 0;
  if ($doclear==1) {

    // clear users
    $DB_site->query("DELETE FROM user WHERE importuserid<>0 AND isnew=1");
    $DB_site->query("DELETE FROM userfield WHERE importuserid<>0 AND isnew=1");

    // clear styles
    $repls = $DB_site->query("SELECT replacementsetid FROM replacementset WHERE importreplacementsetid<>0");
    $DB_site->query("DELETE FROM style WHERE importstyleid<>0");
    if ($DB_site->num_rows($repls)) {
      while ($repl = $DB_site->fetch_array($repls)) {
        $DB_site->query("DELETE FROM replacement WHERE replacementsetid='$repl[replacementsetid]'");
      }
    }
    $DB_site->query("DELETE FROM replacementset WHERE importreplacementsetid<>0");

    // clear templates
    $tmplts = $DB_site->query("SELECT templatesetid FROM templateset WHERE importtemplatesetid<>0");
    while ($tmplt = $DB_site->fetch_array($tmplts)) {
      $DB_site->query("DELETE FROM template WHERE templatesetid='$tmplt[templatesetid]'");
    }
    $DB_site->query("DELETE FROM templateset WHERE importtemplatesetid<>0");

    $DB_site->query("UPDATE style SET replacementsetid=1,templatesetid=1 WHERE styleid=1");

    // clear forums & permissions
    $DB_site->query("DELETE FROM forum WHERE importforumid<>0 OR importcategoryid<>0");
    $DB_site->query("DELETE FROM forumpermission WHERE importforumpermissionid<>0");
    $DB_site->query("DELETE FROM forumpermission WHERE importforumpermissionid<>0");
    $DB_site->query("DELETE FROM subscribeforumid WHERE importsubscribeforumid<>0");

    // clear moderators
    $DB_site->query("DELETE FROM moderator WHERE importmoderatorid<>0");
    $DB_site->query("DELETE FROM access WHERE importaccessid<>0");

    // clear threads & posts
    $DB_site->query("DELETE FROM thread WHERE threadid > 1");
    $DB_site->query("DELETE FROM post WHERE postid > 1");
    $DB_site->query("DELETE FROM poll WHERE importpollid <> ''");
    $DB_site->query("DELETE FROM subscribethreadid WHERE importsubscribethreadid<>0");
	$DB_site->query("DELETE FROM attachment WHERE importattachmentid <> 0");


    // clear private messages
    $DB_site->query("DELETE FROM privatemessage WHERE importpmid<>0");

    // clear usergroups
    $DB_site->query("DELETE FROM usergroup WHERE importusergroupid<>0");

    // reset icons
    $icons = $DB_site->query("SELECT iconid FROM icon");
    while ($icon = $DB_site->fetch_array($icons)) {
      $DB_site->query("UPDATE icon SET iconpath=\"images/icons/icon$icon[iconid].gif\" WHERE iconid='$icon[iconid]' AND iconid < 14");
    }


    // reset smilies
    $smilies = $DB_site->query("SELECT smilieid,smiliepath FROM smilie");
    while ($smilie = $DB_site->fetch_array($smilies)) {
      $DB_site->query("UPDATE smilie SET smiliepath='images/smilies/".strrchr($smilie['smiliepath'],"/")."' WHERE smilieid='$smilie[smilieid]' AND smilieid < 12");
    }

  }

  // remove temporary fields / revert schema
  // Drop the OT Table
  $DB_site->query("DROP TABLE importtable");
  $DB_site->query("ALTER TABLE user DROP importuserid");
  $DB_site->query("ALTER TABLE user DROP isnew");
  $DB_site->query("ALTER TABLE userfield DROP importuserid");
  $DB_site->query("ALTER TABLE userfield DROP isnew");
  $DB_site->query("ALTER TABLE usergroup DROP importusergroupid");
  $DB_site->query("ALTER TABLE forum DROP importcategoryid");
  $DB_site->query("ALTER TABLE forum DROP importforumid");
  $DB_site->query("ALTER TABLE forum DROP isprivate");
  $DB_site->query("ALTER TABLE forumpermission DROP importforumpermissionid");
  $DB_site->query("ALTER TABLE style DROP importstyleid");
  $DB_site->query("ALTER TABLE replacementset DROP importreplacementsetid");
  $DB_site->query("ALTER TABLE templateset DROP importtemplatesetid");
  $DB_site->query("ALTER TABLE thread DROP importthreadid");
  $DB_site->query("ALTER TABLE subscribethread DROP importsubscribethreadid");
  $DB_site->query("ALTER TABLE subscribeforum DROP importsubscribeforumid");
  $DB_site->query("ALTER TABLE post DROP importpostid");
  $DB_site->query("ALTER TABLE poll DROP importpollid");
  $DB_site->query("ALTER TABLE moderator DROP importmoderatorid");
  $DB_site->query("ALTER TABLE access DROP importaccessid");
  $DB_site->query("ALTER TABLE privatemessage DROP importpmid");
  $DB_site->query("ALTER TABLE attachment DROP importattachmentid");
  $DB_site->reporterror = 1;

  //echo "Tables reset".makelinkcode("restart","../admin/bbimport.php?s=$session[sessionhash]");

}

// ##############################################################################
// julian date to m/d/y for php installs without jdtogregorian();
function jd2greg($julian) {
  $julian = $julian - 1721119;
  $c1 = 4 * $julian - 1;
  $year = floor($c1 / 146097);
  $julian = floor($c1 - 146097 * $year);
  $day = floor($julian / 4);
  $c2 = 4 * $day + 3;
  $julian = floor($c2 / 1461);
  $day = $c2 - 1461 * $julian;
  $day = floor(($day + 4) / 4);
  $c3 = 5 * $day - 3;
  $month = floor($c3 / 153);
  $day = $c3 - 153 * $month;
  $day = floor(($day + 5) / 5);
  $year = 100 * $year + $julian;

  if ($month < 10) {
    $month = $month + 3;
  }
  else {
    $month = $month - 9;
    $year = $year + 1;
  }
  //return mktime(0,0,0,$month,$day,$year);
  return "$month/$day/$year";
}

// ##############################################################################
// probably won't get used eventually...

function importtemplateset($file) {
  global $DB_site;

  $templatedata=readfromfile("../importers/$file.templateset");
  eval($templatedata);
  //include("../importers/$file.templateset");

  return $newsetid;
}

// ##############################################################################
// takes an input, checks the value - if value is non existent, insert the default value

function checkdef(&$value,$default)  {
  //if ($value=="" and $value!="0") {
  if (!isset($value) or $value=='') {
    $value = $default;
  }
  return $value;
}

// ##############################################################################
// useful for parsing out data from UBB files
// matches q|xxxxxxx|, q~xxxxxxxxx~, and "xxxxxxxxx",

function ubbextractdata($string) {
  if (preg_match("/=> q[\|\~](.*)[\|\~]\,/",$string,$regs)) { return $regs[1]; }
  elseif (preg_match("/=> \"(.*)\"\,/",$string,$regs)) { return $regs[1]; }
}

// ##############################################################################
// defines str_pad in PHP3
if (!function_exists("str_pad")) {

  define("STR_PAD_LEFT", 2);
  define("STR_PAD_RIGHT", 1);
  define("STR_PAD_BOTH", 0);

  function str_pad($string, $length, $padchar=' ', $padtype=1){
    $cnt = $length - strlen($string);
    for ($i=0;$i<$cnt;$i++) {
      switch($padtype) {
        case 2:
          $i%2 ? $string = $padchar.$string : $string = $string.$padchar;
          break;
        case 1:
          $string = $string.$padchar;
          break;
        default:
          $string = $padchar.$string;
          break;
      }
    }
    return $string;
  }

}

// ##############################################################################
// pads and integer with leading zeroes - useful for UBB imports

function padvalue($integer,$stringlength) {
  return str_pad($integer,$stringlength,0,STR_PAD_LEFT);
}

// ##############################################################################
// returns unix time from UBB datestamp

function ubbdate2unix($datestring,$timestring) {

  //echo "<hr>";
  $datebits = explode("-",$datestring);
  $timebits = preg_match("/([0-9]*):([0-9]*) ([A-Z]*)/",$timestring,$regs);
  if ($regs[1]==12 and $regs[3]=="AM") {
    $regs[1] = 0;
  } elseif ($regs[3]=="PM" and $regs[1]!=12) {
    $regs[1] += 12;
  }
  return mktime($regs[1],$regs[2],0,$datebits[0],$datebits[1],$datebits[2]);

}

// ##############################################################################
// looks at UBB permissions string and returns vB usergroupid

function getubbperms($adminstring,$bannedgroupid) {
  global $user;

  $adminstring = str_replace("&","",trim($adminstring));

    if ($adminstring=="") {
            //echo "NO POSTING";
            return $bannedgroupid;
    }

      if (stristr($adminstring,"COPPA")) {
            //echo "COPPA";
        $user['coppauser']=1;
      } else {
        $user['coppauser']=0;
      }

      if (stristr($adminstring,"Admin")) {
            //echo "ADMIN";
        return 6;
      } else {
            //echo "REGISTERED";
        return 2;
      }

}

// ##############################################################################
// takes a yes/no input and returns binary
// now case insensitive

function option2bin($optionstring) {

  $optionstring = strtolower(trim($optionstring));

  if ($optionstring=="yes" || $optionstring=="is" || $optionstring=="on" || $optionstring=="true") {
    return 1;
  } elseif ($optionstring=="no" || $optionstring=="is not" || $optionstring=="off" || $$optionstring=="false") {
    return 0;
  } else {
    return $optionstring;
  }
}


// ##############################################################################
// translates common HTML into bbcode

function html2bb($htmlcode,$parsesmilies=1,$parseurls=0) {
  global $ubbnoncgiurl;

  // do smilies
  if ($parsesmilies==1) {
    $htmlcode=str_replace("<IMG SRC=\"smile.gif\" border=\"0\">",":)",$htmlcode);
    $htmlcode=str_replace("<IMG SRC=\"frown.gif\" border=\"0\">",":(",$htmlcode);
    $htmlcode=str_replace("<IMG SRC=\"redface.gif\" border=\"0\">",":o",$htmlcode);
    $htmlcode=str_replace("<IMG SRC=\"biggrin.gif\" border=\"0\">",":D",$htmlcode);
    $htmlcode=str_replace("<IMG SRC=\"wink.gif\" border=\"0\">",";)",$htmlcode);
    $htmlcode=str_replace("<IMG SRC=\"tongue.gif\" border=\"0\">",":p",$htmlcode);
    $htmlcode=str_replace("<IMG SRC=\"cool.gif\" border=\"0\">",":cool:",$htmlcode);
    $htmlcode=str_replace("<IMG SRC=\"rolleyes.gif\" border=\"0\">",":rolleyes:",$htmlcode);
    $htmlcode=str_replace("<IMG SRC=\"mad.gif\" border=\"0\">",":mad:",$htmlcode);
    $htmlcode=str_replace("<IMG SRC=\"eek.gif\" border=\"0\">",":eek:",$htmlcode);
    $htmlcode=str_replace("<IMG SRC=\"confused.gif\" border=\"0\">",":confused:",$htmlcode);
  }

  // bold and italics: easy peasy
  $htmlcode=str_replace("<b>","[b]",$htmlcode);
  $htmlcode=str_replace("</b>","[/b]",$htmlcode);

  $htmlcode=str_replace("<i>","[i]",$htmlcode);
  $htmlcode=str_replace("</i>","[/i]",$htmlcode);
  $htmlcode=str_replace("<B>","[b]",$htmlcode);
  $htmlcode=str_replace("</B>","[/b]",$htmlcode);
  $htmlcode=str_replace("<I>","[i]",$htmlcode);
  $htmlcode=str_replace("</I>","[/i]",$htmlcode);

  $htmlcode=eregi_replace("<img src=\"([^\"]*)\">","[img]\\1[/img]",$htmlcode);
  // Jerry catcht the 'alt=""' ones as well
  $htmlcode=eregi_replace("<img src=\"([^\"]*)\" alt=\"\" />","[img]\\1[/img]",$htmlcode);
  $htmlcode=eregi_replace("<img src=\"([^\"]*)\" alt=\" - \" />","[img]\\1[/img]",$htmlcode);
  $htmlcode=eregi_replace("<IMG SRC=\"([^\"]*)\" border=\"0\">","[img]\\1[/img]",$htmlcode);
  $htmlcode=eregi_replace("<IMG SRC=\"([^\"]*)\" >","[img]\\1[/img]",$htmlcode);

  // Jerry 2003-07-15 phpBB2.0.0
  $htmlcode=preg_replace("#<a href=\"([^\"]*)\">#iU","[url=\"\\1\"]\\1[/url]",$htmlcode);
  $htmlcode=preg_replace("#<a border=\"([^\"]*)\ src=\"([^\"]*)\">#iU","[url=\"\\1\"]\\1[/url]",$htmlcode);

  // Jerry 2003-06-19 Trying to catch a customer smiley.
  $htmlcode=eregi_replace("<a href=\"mailto:([^\"]*)\">([^<]*)</a>","[email]\\2[/email]",$htmlcode);
  $htmlcode=eregi_replace("<a href=\"([^\"]*)\" target=_blank>([^<]*)</a>","[url=\"\\1\"]\\2[/url]",$htmlcode);

  // Jerry 06-07-03 Getting the "ed target text
  $htmlcode=eregi_replace("<a href=\"([^\"]*)\" target=\"_blank\">([^<]*)</a>","[url=\"\\1\"]\\2[/url]",$htmlcode);
  $htmlcode=eregi_replace("<a target=\"_blank\" href=([^\"]*)>([^<]*)</a>","[url=\"\\1\"]\\2[/url]",$htmlcode);
  $htmlcode=eregi_replace("<a href=\"([^\"]*)\" target=\"_blank\">([^\"]*)</a>","[url=\"\\1\"]\\2[/url]", $htmlcode);
  $htmlcode=eregi_replace("<A HREF=\"([^\"]*)\" TARGET=_blank>([^\"]*)</A>","[url=\"\\1\"]\\2[/url]", $htmlcode);
  // do code tags
  $htmlcode=eregi_replace("<BLOCKQUOTE><font size=\"1\" face=\"([^\"]*)\">code:</font><HR><pre>","[code]",$htmlcode);
  $htmlcode=str_replace("</pre><HR></BLOCKQUOTE>","[/code]",$htmlcode);

  // do quotes
  $htmlcode=eregi_replace("<BLOCKQUOTE><font size=\"1\" face=\"([^\"]*)\">quote:</font><HR>","[quote]",$htmlcode);
  $htmlcode=str_replace("<HR></BLOCKQUOTE>","[/quote]",$htmlcode);
  $htmlcolde = eregi_replace("</p> <small> </small> <pre style=\"font-size:x-small; font-family: monospace;\"> </pre> <STRONG> </strong> <blockquote><font size=\"1\" face=\"([^\"]*)\">quote:</font><hr /><font size=\"2\" face=\"([^\"]*)\"> <hr /></blockquote>", "[quote]", $htmlcode);

  // do lists
  $htmlcode=eregi_replace("<ul type=square>","[list]",$htmlcode);
  $htmlcode=eregi_replace("<ul type=\"square\">","[list]",$htmlcode);
  $htmlcode=eregi_replace("</ul>","[/list]",$htmlcode);
  $htmlcode=eregi_replace("<ol type=1>","[list=1]",$htmlcode);
  $htmlcode=eregi_replace("<ol type=A>","[list=a]",$htmlcode);
  $htmlcode=eregi_replace("</ol>","[/list=a]",$htmlcode);
  // Jerry 07-07-2003 hard coding for 6.3.1
  $htmlcode=eregi_replace("<li><font size=\"2\" face=\"Verdana\">","[*]",$htmlcode);
  $htmlcode=eregi_replace("<li>","[*]",$htmlcode);

  $htmlcode=str_replace("<p>","\n\n",$htmlcode);
  $htmlcode=str_replace("<P>","\n\n",$htmlcode);
  $htmlcode=str_replace("<br>","\n",$htmlcode);
  $htmlcode=str_replace("<BR>","\n",$htmlcode);

	//new smilies for UBB 6.2.1:
	$htmlcode = str_replace("<br />", "\n", $htmlcode);
	$htmlcode = str_replace("<img src=\"biggrin.gif\" border=\"0\">", ":D", $htmlcode);
	$htmlcode = str_replace("<img src=\"eek.gif\" border=\"0\">", ":eek:", $htmlcode);
	$htmlcode = str_replace("<img src=\"frown.gif\" border=\"0\">", ":(", $htmlcode);
	$htmlcode = str_replace("<img src=\"redface.gif\" border=\"0\">", ":o", $htmlcode);
	$htmlcode = str_replace("<img src=\"rolleyes.gif\" border=\"0\">", ":rolleyes:", $htmlcode);
	$htmlcode = str_replace("<img src=\"smile.gif\" border=\"0\">", ":)", $htmlcode);
	$htmlcode = str_replace("<img src=\"tongue.gif\" border=\"0\">", ":p", $htmlcode);
	$htmlcode = str_replace("<img src=\"wink.gif\" border=\"0\">", ";)", $htmlcode);
	$htmlcode = str_replace("<img src=\"smile.gif\" border=\"0\">", ":)", $htmlcode);
	$htmlcode = str_replace("<img src=\"cool.gif\" border=\"0\">", ":cool:", $htmlcode);
	$htmlcode = str_replace("<img src=\"mad.gif\" border=\"0\">", ":mad:", $htmlcode);
	$htmlcode = str_replace("<img src=\"redface.gif\" border=\"0\">", ":o", $htmlcode);
	$htmlcode = str_replace("<img src=\"confused.gif\" border=\"0\"> ", ":confused:", $htmlcode);
	$htmlcode = str_replace("<img border=\"0\" title=\"\" alt=\"[Confused]\" src=\"confused.gif\" />", ":confused:", $htmlcode);
	$htmlcode = str_replace("<img border=\"0\" title=\"\" alt=\"[Big Grin]\" src=\"biggrin.gif\" />", ":D", $htmlcode);
	$htmlcode = str_replace("<img border=\"0\" title=\"\" alt=\"[Embarrassed]\" src=\"redface.gif\" />", ":o", $htmlcode);
	$htmlcode = str_replace("<img border=\"0\" title=\"\" alt=\"[Roll Eyes]\" src=\"rolleyes.gif\" />", ":rolleyes:", $htmlcode);
	$htmlcode = str_replace("<img border=\"0\" title=\"\" alt=\"[Eek]\" src=\"eek.gif\" />", ":eek:", $htmlcode);
	$htmlcode = str_replace("<img border=\"0\" title=\"\" alt=\"[Frown]\" src=\"frown.gif\" /> ", ":(", $htmlcode);
	$htmlcode = str_replace("<img border=\"0\" title=\"\" alt=\"[Mad]\" src=\"mad.gif\" />", ":mad:", $htmlcode);
	$htmlcode = str_replace("<img border=\"0\" title=\"\" alt=\"[Smile]\" src=\"smile.gif\" />", ":)", $htmlcode);
	$htmlcode = str_replace("<img border=\"0\" title=\"\" alt=\"[Cool]\" src=\"cool.gif\" />", ":cool:", $htmlcode);
	$htmlcode = str_replace("<img border=\"0\" title=\"\" alt=\"[Razz]\" src=\"tongue.gif\" />", ":p", $htmlcode);

	// Jerry 16-07-2003 have to binn this for phpbb2
	$htmlcode = str_replace("<!-- BBCode Start -->","", $htmlcode);
	$htmlcode = str_replace("<!-- BBCode End -->","", $htmlcode);
	$htmlcode = str_replace(":razz:",":mad:", $htmlcode);
	$htmlcode = str_replace(":roll:",":rolleyes:", $htmlcode);


  if ($parseurls) {
    $htmlcode=parseurl($htmlcode);
  }

  return $htmlcode;
}

// ##############################################################################
// returns an array with $importedstyleid[$vbstyleid]

function getstyleids($pad=0) {
  global $DB_site;
  $styles = $DB_site->query("SELECT styleid,importstyleid FROM style");
  while ($style = $DB_site->fetch_array($styles)) {
    $impstyleid = iif($pad,$style['importstyleid'],intval($style['importstyleid']));
    $styleid[$impstyleid] = $style['styleid'];
  }
  return $styleid;
}

// ##############################################################################
// returns an array with $importeduserid[$vbuserid]

function getuserids() {
  global $DB_site;
  $users = $DB_site->query("SELECT userid,username,importuserid AS importuserid FROM user WHERE importuserid<>0");
  while ($user = $DB_site->fetch_array($users)) {
    $importuserid = $user['importuserid'];
    $userid[$importuserid] = $user['userid'];
  }
  return $userid;
}

// ##############################################################################
// returns an array with $importedcategoryid[$vbforumid]

function getcategoryids($pad=0) {
  global $DB_site;
  $forums = $DB_site->query("SELECT forumid,title,importcategoryid FROM forum WHERE importcategoryid<>0");
  while ($forum = $DB_site->fetch_array($forums)) {
    $impforumid = iif($pad,$forum['importcategoryid'],intval($forum['importcategoryid']));
    $categoryid[$impforumid] = $forum['forumid'];
  }
  return $categoryid;
}

// ##############################################################################
// returns an array with $importedforumid[$vbforumid]

function getforumids($pad=0) {
  global $DB_site;
  $forums = $DB_site->query("SELECT forumid,title,importforumid FROM forum WHERE importforumid<>0");
  while ($forum = $DB_site->fetch_array($forums)) {
    $impforumid = iif($pad,$forum['importforumid'],intval($forum['importforumid']));
    $forumid[$impforumid] = $forum['forumid'];
  }
  return $forumid;
}

// ##############################################################################
// returns an array with $importedpollid[$vbpollid]

function getpollids() {
  global $DB_site;
  $polls = $DB_site->query("SELECT pollid,importpollid FROM poll WHERE importpollid<>0");
  while ($poll = $DB_site->fetch_array($polls)) {
    $importpollid = $poll['importpollid'];
    $pollid[$importpollid] = $poll['pollid'];
  }
  return $pollid;
}

// ##############################################################################
// checks user details against admin options - DO NOT USE FOR BATCH IMPORTS!!!

function verifyuserdata($user) {
  global $DB_site,$session,$maxuserlength,$minuserlength,$banemail,$requireuniqueemail;

  if (strlen($user['username']) > $maxuserlength) {
    echo "<p>The username specified is too long ($usernamesize characters)</p>\n";
    return 0;

  } elseif (strlen($user['username']) < $minuserlength) {
    echo "<p>The username specified is too short ($usernamesize characters)</p>\n";
    return 0;

  } elseif (!eregi("^[a-z0-9\.\-\_]*[a-z0-9]\@([a-z0-9\-\_]*[a-z0-9]\.[a-z0-9\.\-\_]*[a-z0-9])$",$user['email'],$regs)) {
    echo "<p>This does not appear to be a valid email address!</p><pre><font color='darkred'>$user[email]</font></pre>\n";
    return 0;

  } elseif (stristr(" $banemail "," $user[email] ")) {
    echo "<p>This email address is on your banned email list!</p><pre><font color='darkred'>$user[email]</font></pre>";
    return 0;

  } elseif (stristr(" $banemail "," $regs[1] ")) {
    echo "<p>This email domain is on your banned email list!</p><pre><font color='darkred'>$regs[1]</font></pre>\n";
    return 0;

  } elseif ($checkuser=$DB_site->query_first("SELECT userid,username FROM user WHERE username='".vb_escape($user['username'])."' OR username='".vb_escape(eregi_replace("[^A-Za-z0-9]","",$user['username']))."'")) {
    echo "<p>This username is already in use.<br>".makelinkcode("check profile for $user[username]","../admin/user.php?s=$session[sessionhash]&amp;action=edit&amp;userid=$checkuser[userid]",1)."</p>\n";
    return 0;

  } elseif ($requireuniqueemail and $checkuser=$DB_site->query_first("SELECT username,userid,email FROM user WHERE email='".vb_escape($user['email'])."'")) {
    echo "<p>This email address is already registered as the email address for <a href='../admin/user.php?s=$session[sessionhash]&amp;action=edit&amp;userid=$checkuser[userid]' target='_blank'><u>$checkuser[username]</u></a>, and you have specified that you require each member to have a unique email address.</p>\n";
    return 0;

  } else {
    return 1;
  }

}
// *******************************************************




// ##############################################################################
// inserts user data into user and userfield tables

function importuser($user, $md5="1") {
  global $DB_site,$session,$pause;

  checkdef($user['emailnotification'],1);

if ($md5 == "1") {
	$user['password'] = md5($user['password']);
}
  $result = $DB_site->query("INSERT IGNORE INTO user
    (userid,usergroupid,username,password,
    email,styleid,parentemail,coppauser,
    homepage,icq,aim,yahoo,
    signature,adminemail,showemail,invisible,
    usertitle,customtitle,joindate,cookieuser,
    daysprune,lastvisit,lastactivity,lastpost,
    posts,timezoneoffset,emailnotification,buddylist,
    ignorelist,pmfolders,receivepm,emailonpm,
    pmpopup,avatarid,options,birthday,
    maxposts,startofweek,ipaddress,referrerid,nosessionhash,
    importuserid,isnew)
    VALUES
    ('',
    '".checkdef($user['usergroupid'],2)."',
    '".vb_escape(htmlspecialchars(checkdef($user['username'],"unknown user")))."',
    '".vb_escape(checkdef($user['password'],"password"))."',
    '".vb_escape(htmlspecialchars(checkdef($user['email'],"noone@localhost")))."',
    '$user[styleid]',
    '".vb_escape(htmlspecialchars($user['parentemail']))."',
    '$user[coppauser]',
    '".vb_escape(htmlspecialchars($user['homepage']))."',
    '".vb_escape(htmlspecialchars($user['icq']))."',
    '".vb_escape(htmlspecialchars($user['aim']))."',
    '".vb_escape(htmlspecialchars($user['yahoo']))."',
    '".vb_escape($user['signature'])."',
    '".checkdef($user['adminemail'],1)."',
    '".checkdef($user['showemail'],1)."',
    '".checkdef($user['invisible'],0)."',
    '".vb_escape($user['usertitle'])."',
    '$user[customtitle]',
    '".vb_escape($user['joindate'])."',
    '".checkdef($user['cookieuser'],1)."',
    '".intval($user['daysprune'])."',
    '$user[lastvisit]',
    '$user[lastactivity]',
    '$user[lastpost]',
    '".intval($user['posts'])."',
    '".checkdef($user['timezoneoffset'],0)."',
    '".intval($user['emailnotification'])."',
    '$user[buddylist]',
    '$user[ignorelist]',
    '$user[pmfolders]',
    '".intval(checkdef($user['receivepm'],1))."',
    '".intval($user['emailnotification'])."',
    '".intval($user['emailnotification'])."',
    '$user[avatarid]',
    '15',
    '$user[birthday]',
    '".checkdef($user['maxposts'],-1)."',
    '".checkdef($user['startofweek'],1)."',
    '$user[ipaddress]',
    '$user[referrerid]',
    '".checkdef($user['nosessionhash'],1)."',
    '$user[importuserid]',1
    )");

  $userid = $DB_site->insert_id($result);

  if ($userid) {
      $DB_site->query("INSERT INTO userfield
      (userid,field1,field2,field3,field4,importuserid,isnew)
      VALUES
      ('$userid',
      '".vb_escape(htmlspecialchars($user['biography']))."',
      '".vb_escape(htmlspecialchars($user['location']))."',
      '".vb_escape(htmlspecialchars($user['interests']))."',
      '".vb_escape(htmlspecialchars($user['occupation']))."',
      '$user[importuserid]',1
      )");
  }

  echo "user <i>".htmlspecialchars($user['username'])."</i> imported.".iif($pause,makelinkcode("edit","../admin/user.php?s=$session[sessionhash]&amp;action=edit&amp;userid=$userid",1),"")."</p>\n\n";
  flush();

  return $userid;

}

// ##############################################################################
// imports custom avatar specified by an URL - returns inserted id
function importcustomavatar($userid,$avatarurl) {
  global $DB_site;

  $filenum=@fopen($avatarurl,"rb");
  if ($filenum!=0) {
    $contents="";
    while (!@feof($filenum)) {
      $contents.=@fread($filenum,1024); //filesize($filename));
    }
    @fclose($filenum);

    $urlbits=parse_url($avatarurl);
    $pathbits=pathinfo($urlbits['path']);

    $DB_site->query("INSERT INTO customavatar VALUES ($userid,'".vb_escape($contents)."',".time().",'".vb_escape($pathbits['basename'])."')");

    return $DB_site->insert_id();
  }

}

// ##############################################################################
// imports ban lists

function importbanlists($iplist,$emaillist) {
  global $DB_site,$session;

  $DB_site->query("UPDATE setting SET value=1 WHERE varname='enablebanning'");
  echo "<p>Banning features enabled ....\n";
  flush();

  if ($iplist!="") {
    $setting = $DB_site->query_first("SELECT value FROM setting WHERE varname='banip'");
    $DB_site->query("UPDATE setting SET value='".trim("$setting[value] $iplist")."' WHERE varname='banip'");
    echo "IP ban list imported sucessfully ....\n";
    flush();
  }

  if ($emaillist!="") {
    $setting = $DB_site->query_first("SELECT value FROM setting WHERE varname='banemail'");
    $DB_site->query("UPDATE setting SET value='".trim("$setting[value] $emaillist")."' WHERE varname='banemail'");
    echo "Email ban list imported sucessfully ....\n";
    flush();
  }

  echo "done.</p>\n";
  echo "<p><b>IMPORTANT:</b> In order to activate any IP or Email ban lists imported, you must open
    <b>".makelinkcode("this page","../admin/options.php?s=$session[sessionhash]&amp;action=options#settinggroup17",1)."</b>
    and click the 'Save Changes' button at the bottom of the page.
    <a href=\"../admin/options.php?s=$session[sessionhash]&amp;action=options#settinggroup17\" target=\"_blank\">Do this now</a>
    (the page will open in a new window).</p>\n\n";
  flush();

}

// ##############################################################################
// inserts top level no post forums (ie category!)

function importcategory($category) {
  global $DB_site,$session;

  if (trim($category['title'])=="") {
    $category['active']=0;
  }

  $result = $DB_site->query("INSERT INTO forum
    (forumid,styleid,title,description,active,
    displayorder,parentid,countposts,styleoverride,importcategoryid)
    VALUES (
    '',
    '$category[styleid]',
    '".vb_escape(checkdef($category['title'],"imported category"))."',
    '".vb_escape($category['description'])."',
    '".checkdef($category['active'],1)."',
    '".intval(checkdef($category['displayorder'],1))."',
    '-1',
    '1',
    '$category[styleoverride]',
    '".intval($category['importcategoryid'])."'
    )");

  $categoryid = $DB_site->insert_id($result);
  $DB_site->query("UPDATE forum SET parentlist='$categoryid,-1' WHERE forumid='$categoryid'");

  echo "imported sucessfully.".makelinkcode("edit","../admin/forum.php?s=$session[sessionhash]&amp;action=edit&amp;forumid=$categoryid",1)."</p>\n\n";
  flush();

  return $categoryid;

}

// ##############################################################################
// inserts forums and forumpermissions

function importforum($forum) {
  global $DB_site,$session;

  if (!$forum['active']) {
    echo "(inactive)\n";
    flush();
  }

  $result = $DB_site->query("INSERT INTO forum (
  forumid,styleid,styleoverride,title,
  description,active,displayorder,allowposting,
  cancontainthreads,daysprune,allowbbcode,allowimages,
  allowhtml,allowsmilies,allowicons,parentid,
  allowratings,countposts,importforumid,importcategoryid,isprivate,moderatenew,moderateattach)
  VALUES ('',
  '$forum[styleid]',
  '".checkdef($forum['styleoverride'],0)."',
  '".vb_escape(checkdef($forum['title'],"imported forum"))."',
  '".vb_escape($forum['description'])."',
  '".checkdef($forum['active'],1)."',
  '".checkdef($forum['displayorder'],1)."',
  '".checkdef($forum['allowposting'],1)."',
  '".checkdef($forum['cancontainthreads'],1)."',
  '".checkdef($forum['daysprune'],30)."',
  '".checkdef($forum['allowbbcode'],1)."',
  '".checkdef($forum['allowimages'],1)."',
  '".checkdef($forum['allowhtml'],0)."',
  '".checkdef($forum['allowsmilies'],1)."',
  '".checkdef($forum['allowicons'],1)."',
  '".checkdef($forum['parentid'],-1)."',
  '".checkdef($forum['allowratings'],1)."',
  '".checkdef($forum['countposts'],1)."',
  '$forum[importforumid]',
  '$forum[importcategoryid]',
  '$forum[private]',
  '".checkdef($forum['moderatenew'],0)."',
  '".checkdef($forum['moderateattach'],0)."'
  )");

  $forumid = $DB_site->insert_id($result);

  $DB_site->query("UPDATE forum SET parentlist='$forumid,".iif($forum['parentid']!=-1,"$forum[parentid],","")."-1' WHERE forumid='$forumid'");

  if ($forum['private']==1) {

    echo "(private forum)\n";
    flush();

    $groups=$DB_site->query("SELECT usergroupid FROM usergroup WHERE usergroupid<5 OR usergroupid>6");
    while ($group=$DB_site->fetch_array($groups)) {
      $DB_site->query("INSERT INTO forumpermission (
        forumpermissionid,forumid,usergroupid,canview,
        cansearch,canemail,canpostnew,canmove,
        canopenclose,candeletethread,canreplyown,canreplyothers,
        canviewothers,caneditpost,candeletepost,
        canpostattachment,canpostpoll,canvote,importforumpermissionid)
        VALUES (
        '',
        '$forumid',
        '$group[usergroupid]',
        0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1)");
    }

  }

  echo "imported sucessfully.".makelinkcode("edit","../admin/forum.php?s=$session[sessionhash]&amp;action=edit&amp;forumid=$forumid",1)."</p>\n\n";
  flush();

  return $forumid;

}

// ##############################################################################
// inserts moderator

function importmoderator($mod) {
  global $DB_site,$session;

  $result = $DB_site->query("INSERT IGNORE INTO moderator
    (moderatorid,userid,forumid,newthreademail,
    newpostemail,caneditposts,candeleteposts,canviewips,
    canmanagethreads,canopenclose,caneditthreads,caneditstyles,
    canbanusers,canviewprofile,canannounce,canmassmove,
    canmassprune,canmoderateposts,canmoderateattachments,importmoderatorid)
    VALUES
    ('',
    '$mod[userid]',
    '$mod[forumid]',
    '".checkdef($mod['newthreademail'],0)."',
    '".checkdef($mod['newpostemail'],0)."',
    '".checkdef($mod['caneditposts'],1)."',
    '".checkdef($mod['candeleteposts'],1)."',
    '".checkdef($mod['canviewips'],1)."',
    '".checkdef($mod['canmanagethreads'],1)."',
    '".checkdef($mod['canopenclose'],1)."',
    '".checkdef($mod['caneditthreads'],1)."',
    '".checkdef($mod['caneditstyles'],0)."',
    '".checkdef($mod['canbanusers'],0)."',
    '".checkdef($mod['canviewprofile'],1)."',
    '".checkdef($mod['canannounce'],1)."',
    '".checkdef($mod['canmassmove'],0)."',
    '".checkdef($mod['canmassprune'],0)."',
    '".checkdef($mod['canmoderateposts'],1)."',
    '".checkdef($mod['canmoderateattachments'],1)."',
    '$mod[userid]'
    )");

  $moderatorid = $DB_site->insert_id($result);

  echo "imported sucessfully.".makelinkcode("edit","../admin/forum.php?s=$session[sessionhash]&amp;action=editmoderator&amp;moderatorid=$moderatorid",1)."</p>\n\n";
  flush();

}

// ##############################################################################
// adds access for moderators to private forums

function domoderatoraccess() {
  global $DB_site;

  $mods=$DB_site->query("SELECT DISTINCT moderator.userid FROM moderator,user WHERE moderator.userid=user.userid AND user.usergroupid<>6 AND user.usergroupid<>5");
  if ($DB_site->num_rows($mods)) {
    echo "<p>Updating access permissions for moderators to private forums</p>\n";
    flush();
    while ($mod=$DB_site->fetch_array($mods)) {
      $accessto[] = $mod['userid'];
    }
    $forums = $DB_site->query("SELECT forumid,title FROM forum WHERE isprivate=1");
    if ($DB_site->num_rows($forums)) {
      while ($forum = $DB_site->fetch_array($forums)) {
        echo "<p>Updating <i>$forum[title]</i> ....\n";
        flush();
        while ( list($key,$userid)=each($accessto) ) {
          $DB_site->query("INSERT IGNORE INTO access (userid,forumid,accessmask,importaccessid) VALUES ('$userid','$forum[forumid]',1,'$userid')");
        }
        echo "done</p>\n\n";
        reset($accessto);
        flush();
      }
    }
  }
}

// ##############################################################################
// inserts a new thread and returns the inserted threadid

function importthread($thread) {
  global $DB_site,$session,$pause;

  $result = $DB_site->query("INSERT INTO thread
    (threadid,title,forumid,open,
    postusername,postuserid,dateline,iconid,pollid,visible,sticky,views,importthreadid)
    VALUES (
    '',
    '".vb_escape($thread['title'])."',
    '$thread[forumid]',
    '".checkdef($thread['open'],1)."',
    '".vb_escape($thread['postusername'])."',
    '$thread[postuserid]',
    '$thread[dateline]',
    '$thread[iconid]',
    '".checkdef($thread['pollid'],0)."',
    '".checkdef($thread['visible'],1)."',
    '".checkdef($thread['sticky'],0)."',
    '".checkdef($thread['views'],0)."',
    '".checkdef($thread['importthreadid'],-1)."'
    )");

  $threadid = $DB_site->insert_id($result);

  echo "<i>".htmlspecialchars($thread['title'])."</i> inserted sucessfully".iif($pause,makelinkcode("edit","../postings.php?s=$session[sessionhash]&amp;action=editthread&amp;threadid=$threadid",1),"")."</p>\n\n";
  flush();

  return $threadid;

}

// ##############################################################################
// inserts a new thread subscription and returns the inserted id

function importsubscription($threadid,$userid) {
  global $DB_site;

  $result = $DB_site->query("INSERT INTO subscribethread
    (subscribethreadid,userid,threadid,emailupdate,importsubscribethreadid)
    VALUES (
    '','$userid','$threadid',1,'$threadid'
    )");

  //echo "done.</p>";
  flush();

  $subscribeid = $DB_site->insert_id($result);

  return $subscribeid;

}

// ##############################################################################
// inserts a new thread subscription and returns the inserted id

function importforumsubscription($forumid,$userid) {
  global $DB_site;

  $result = $DB_site->query("INSERT INTO subscribeforum
    (subscribeforumid,userid,forumid,emailupdate,importsubscribeforumid)
    VALUES (
    '','$userid','$forumid',1,'$forumid'
    )");

  //echo "done.</p>";
  flush();

  $subscribeid = $DB_site->insert_id($result);

  return $subscribeid;

}

// ##############################################################################
// inserts a new post and returns the inserted postid

function importpost($post, $showeditlink = true) {
  global $DB_site,$session;

  $result = $DB_site->query("INSERT INTO post
    (postid,threadid,username,userid,
    title,dateline,pagetext,allowsmilie,
    showsignature,ipaddress,iconid,visible,edituserid,editdate,importpostid)
    VALUES (
    '',
    '$post[threadid]',
    '".vb_escape($post['username'])."',
    '$post[userid]',
    '".vb_escape($post['title'])."',
    '$post[dateline]',
    '".vb_escape($post['pagetext'])."',
    '".checkdef($post['allowsmilie'],1)."',
    '".checkdef($post['showsignature'],1)."',
    '$post[ipaddress]',
    '$post[iconid]',
    '".checkdef($post['visible'],1)."',
    '".checkdef($post['edituserid'],0)."',
    '".checkdef($post['editdate'],0)."',
    '".checkdef($thread['importpostid'],-1)."'
    )");

  $postid = $DB_site->insert_id($result);


  if ($showeditlink)
  {
  	echo "imported successfully.".makelinkcode("edit","../editpost.php?s=$session[sessionhash]&action=editpost&postid=$postid",1)."</p>\n\n";
  }

  //echo "imported successfully.".makelinkcode("edit","../editpost.php?s=$session[sessionhash]&amp;action=editpost&amp;postid=$postid",1)."</p>\n\n";

  flush();

  return $postid;
}

// ##############################################################################
// adds buddy and ignore lists to user profile

function importbuddyignore($user) {
  global $DB_site;

  $sql="";

  if ($user['buddylist'] != "") {
    $sql = "buddylist=CONCAT(buddylist,' $user[buddylist]')";
  }
  if ($user['ignorelist'] != "") {
    if ($sql != "") {
      $sql .= ", ";
    }
    $sql .= "ignorelist=CONCAT(ignorelist,' $user[ignorelist]')";
  }

  if ($sql != "") {
    $DB_site->query("UPDATE user SET $sql WHERE userid='$user[userid]'");
  }

  echo "inserted sucessfully</p>\n\n";
  flush();

}

// ##############################################################################
// inserts a new privatemessage and returns the inserted privatemessageid

function importpm($pm) {
  global $DB_site;

  $result = $DB_site->query("INSERT INTO privatemessage
    (privatemessageid,folderid,userid,touserid,
    fromuserid,title,message,dateline,
    showsignature,iconid,messageread,readtime,
    receipt,deleteprompt,multiplerecipients,importpmid)
    VALUES (
    '',
    '".checkdef($pm['folderid'],0)."',
    '$pm[userid]',
    '$pm[touserid]',
    '$pm[fromuserid]',
    '".vb_escape(checkdef($pm['title'],"private message"))."',
    '".vb_escape($pm['message'])."',
    '".checkdef($pm['dateline'],time())."',
    '".checkdef($pm['showsignature'],1)."',
    '$pm[iconid]',
    '$pm[messageread]',
    '$pm[readtime]',
    '$pm[receipt]',
    '$pm[deleteprompt]',
    '$pm[multiplerecipients]',
    '".checkdef($pm['importpmid'],1)."'
    )");

  echo "imported sucessfully.</p>\n\n";
  flush();

  $privatemessageid = $DB_site->insert_id($result);

  return $privatemessageid;

}


// ######################### importattachment #######################################
// inserts a new attachment and returns the new attachmentid

function importattachment($attachment) {
	global $DB_site;

	$result = $DB_site->query("INSERT INTO attachment (attachmentid,userid,dateline,filename,filedata,visible) VALUES (

	'',
	'$attachment[userid]',
	'$attachment[dateline]',
	'".vb_escape($attachment['filename'])."',
	'".vb_escape($attachment['filedata'])."',
	'$attachment[visible]'
	)");

	//also need to update attachmentid in the post table

	$attachmentid = $DB_site->insert_id($result);

	$DB_site->query("UPDATE post SET attachmentid='$attachmentid' WHERE postid='$attachment[postid]'");

	return $attachmentid;
}

// ###################### importpoll ##############################################
// inserts a new poll and returns the new pollid

function importpoll($poll) {
	global $DB_site;

	$result = $DB_site->query("INSERT INTO poll (pollid,question,dateline,options,votes,active,numberoptions,timeout,multiple,voters,importpollid) VALUES (

	'',
	'".vb_escape($poll['question'])."',
	'$poll[dateline]',
	'".vb_escape($poll['options'])."',
	'".vb_escape($poll['votes'])."',
	'$poll[active]',
	'$poll[numberoptions]',
	'$poll[timeout]',
	'$poll[multiple]',
	'$poll[voters]',
	'$poll[importpollid]'
	)");

	$pollid = $DB_site->insert_id($result);
	return $pollid;
}

// ##############################################################################
// Reset imported data - this is not linked to in the scripts, but can be used to
// empty all imported data... it only works if cleandb() has not yet been called,
// and all imported data has the importXXXid field<>0 for imported records.
if ($action=="reset") {
  cpheader("<title>Reset imported data!</title>");
  doformheader("../admin/bbimport","doreset");
  maketableheader("Confirm reset");
  makedescription("Are you SURE you want to abort the import and reset all data?");
  doformfooter("Yes","",2,"No");
  cpfooter();
}
if ($_POST['action']=="doreset") {
  cleandb(1);
  cpheader();
  echo "<h2 align='center'><b>&raquo; all imported data removed &laquo;</b></h2>\n";
  //$action = "go";
}


?>
