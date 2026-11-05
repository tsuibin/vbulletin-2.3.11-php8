<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

if ($_POST['action']) {
	$action = $_POST['action'];
} else if ($_GET['action']) {
	$action = $_GET['action'];
}

if (!isset($action) or $action=="") {
  $action="faq";
}

// ############################### start who posted ###############################
if ($action=="whoposted") {
	$templatesused = "whoposted,whopostedbit,error_invalidid";
	include("./global.php");
	
	$threadid = intval($threadid);
	$posters = '';
		
	$posts = $DB_site->query("
		SELECT COUNT(postid) AS posts,
		post.username AS postuser,user.userid,user.username
		FROM post LEFT JOIN user USING(userid)
		WHERE threadid=$threadid AND visible=1
		GROUP BY userid ORDER BY posts DESC
	");
	
	if ($DB_site->num_rows($posts)) {
		while ($post = $DB_site->fetch_array($posts)) {
			if (($counter++ % 2) != 0) {
				$backcolor="{firstaltcolor}";
				$bgclass="alt1";
			} else {
				$backcolor = "{secondaltcolor}";
				$bgclass="alt2";
			}
			if ($post['username']=="") {
				$post['username'] = $post['postuser'];
			}
			$totalposts += $post['posts'];
			eval("\$posters .= \"".gettemplate("whopostedbit")."\";");
		}
		$totalposts = number_format($totalposts);
		eval("dooutput(\"".gettemplate("whoposted")."\");");
	} else {
		$idname = "thread";
		eval("standarderror(\"".gettemplate("error_invalidid")."\");");
	}
}

// ############################### start show smilies ###############################
if ($action=="showsmilies") {
  $templatesused = "smiliebit,smilies";
  include("./global.php");
  $smiliebits="";

  $smilies=$DB_site->query("SELECT smilietext,title,smiliepath FROM smilie ORDER BY title");
  while ($smilie=$DB_site->fetch_array($smilies)) {
    eval("\$smiliebits .= \"".gettemplate("smiliebit")."\";");
  }

  eval("dooutput(\"".gettemplate("smilies")."\");");
}

// ############################### start show avatars ###############################
if ($action=="showavatars") {
  $templatesused = "avatarbit,avatar,avatars";
  include("./global.php");

  $minposts=0;
  $avatarbits = '';
  $avatarlist = '';

  $avatars=$DB_site->query("SELECT title,minimumposts,avatarpath FROM avatar ORDER BY minimumposts,title");
  if ($DB_site->num_rows($avatars)!=0) {
    while ($avatar=$DB_site->fetch_array($avatars)) {
      if ($avatar['minimumposts']!=$minposts) {
        eval("\$avatarbits .= \"".gettemplate("avatarbit")."\";");
        $avatarlist="";
      }
      $minposts=$avatar['minimumposts'];
      eval("\$avatarlist .= \"".gettemplate("avatar")."\";");
    }
    eval("\$avatarbits .= \"".gettemplate("avatarbit")."\";");
  }

  eval("dooutput(\"".gettemplate("avatars")."\");");
}

// ############################### start bbcode ###############################
if ($action=="bbcode") {
  $templatesused = "bbcode";
  include("./global.php");
  eval("dooutput(\"".gettemplate("bbcode")."\");");

}

// ############################### start faq ###############################
if ($action=="faq") {
  $page = intval($_GET['page']);
  if (!$page)
    $page = '';
  $templatesused = "faq$page";
  include("./global.php");
  eval("dooutput(\"".gettemplate("faq$page")."\");");

}

// ############################### Popup Smilies for vbCode ################
if ($action=="getsmilies") {
  $templatesused = "vbcode_popup_smiliesbits,vbcode_popup_smilies";
  include("./global.php");

  $smilies = $DB_site->query("SELECT smilietext AS text, smiliepath AS path, title FROM smilie");
  $popup_smiliesbits = '';

  $rightorleft = 'left';
  while ($smilie = $DB_site->fetch_array($smilies)) {
	if ($rightorleft == 'left') {
	  if (($i++ % 2) != 0)
	    $backcolor='{firstaltcolor}';
	  else $backcolor='{secondaltcolor}';
	    $popup_smiliesbits .= "";
	  eval ("\$popup_smiliesbits .= \"<tr>".gettemplate("vbcode_popup_smiliesbits")."\";");
	  $rightorleft = 'right';
	} else {
	  eval ("\$popup_smiliesbits .= \"".gettemplate("vbcode_popup_smiliesbits")."</tr>\";");
	  $rightorleft = 'left';
	}
  }
  if ($rightorleft=='right') {
    $popup_smiliesbits .= "<td bgcolor=\"$backcolor\">&nbsp;</td><td bgcolor=\"$backcolor\">&nbsp;</td></tr>";
  }
  eval("dooutput(\"".gettemplate("vbcode_popup_smilies")."\");");
}

?>