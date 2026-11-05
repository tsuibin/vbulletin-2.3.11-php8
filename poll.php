<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

$templatesused = "forumrules,pollpreview,redirect_postthanks,new_disablesmiliesoption,pollnewbit,newpoll,polleditbit,editpoll,redirect_editthanks,pollresult,pollresults,redirect_pollvotethanks";

require("./global.php");

if (!isset($action) or $action=="") {
  $action="newpoll";
}

$pollpreview = '';
$multiplechecked = '';
$parseurlchecked = '';
$disablesmilieschecked = '';

// ############################### start post poll ###############################
if ($_POST['action']=="postpoll") {

  $threadid = verifyid("thread",$threadid);
  $threadinfo=getthreadinfo($threadid);
  $foruminfo=getforuminfo($threadinfo['forumid']);
  $visible=iif($foruminfo['moderatenew'],0,1);

  updateuserforum($threadinfo['forumid']);

  $thisthread=$DB_site->query("SELECT userid FROM post WHERE threadid='$threadinfo[threadid]' ORDER BY dateline");
  $count = $DB_site->num_rows($thisthread);
  $postuser = $DB_site->fetch_array($thisthread);
  if (($bbuserinfo['userid']!=$postuser['userid'] or $count>1) and !ismoderator($foruminfo['forumid'])) {
    show_nopermission();
  }

  // auto bypass queueing for admins/mods
  if (ismoderator($foruminfo['forumid'])) {
      $visible=1;
  }
  if ($threadinfo['pollid']!=0) {
    eval("standarderror(\"".gettemplate("error_pollalready")."\");");
    exit;
  }

  // decode check boxes
  $parseurl = iif($parseurl=="yes",1,0);
  $allowsmilie = iif($disablesmilies=="yes",0,1);
  $multiple = iif($multiple=="yes",1,0);
  $preview = iif($preview!="",1,0);
  $update = iif($updatenumber!="",1,0);

  $timeout=intval($timeout);

  if ($parseurl) {
    $message=parseurl($message);
  }
	$counter=0;
	while ($counter++<$polloptions){ // 0..Pollnum-1 we want, as arrays start with 0
		if ($parseurl) {
			//$options[$counter]=parseurl(htmlspecialchars($options[$counter]));
			$options[$counter]=parseurl($options[$counter]);
		}
	}

  if ($maxpolloptions>0 and $polloptions>$maxpolloptions) {
    $polloptions=$maxpolloptions;
  }

  // check question and if 2 options or more were given
  $counter=0;
  $optioncount=0;
  while ($counter++<$polloptions){ // 0..Pollnum-1 we want, as arrays start with 0
    if ($options[$counter]!="") {
      $optioncount++;
    }
  }

  if ($update) {
    $action="newpoll";
  } elseif ($preview) {
    $previewpost=1;

    $counter = 0;
    $pollpreviewbits = "";
    $previewquestion=bbcodeparse($question,$foruminfo['forumid'],$allowsmilie);
    while ($counter++<$polloptions){
      $pollpreviewbits .= "&nbsp;&nbsp; $counter. &nbsp; ".bbcodeparse($options[$counter],$foruminfo['forumid'],$allowsmilie). "<BR>";
    }

    eval("\$pollpreview = \"".gettemplate("pollpreview")."\";");

    $multiplechecked = iif($multiple,"checked","");
    $parseurlchecked = iif($parseurl,"checked","");
    $disablesmilieschecked = iif(!$allowsmilie,"checked","");

    $action="newpoll";
  } else {

    //$question = htmlspecialchars($question);

    if (trim($question)=="" or $optioncount < 2){
      eval("standarderror(\"".gettemplate("error_noquestionoption")."\");");
      exit;
    }

    $permissions=getpermissions($foruminfo['forumid']);
    if (!$permissions['canview'] or !$permissions['canpostnew'] or !$permissions['canpostpoll']) {
			// in case someone gets here without permission, we need to update the thread's status
         $DB_site->query("UPDATE thread SET visible='$visible' WHERE threadid=$threadinfo[threadid]");

			show_nopermission();
    }

    // check max images
    if ($maximages!=0) {
      $counter=0;
      $maximgtest = '';
      while ($counter++<$polloptions){ // 0..Pollnum-1 we want, as arrays start with 0
        $maximgtest.=$options[$counter];
      }
      $parsedmessage=bbcodeparse($maximgtest.$question,$forumid,$allowsmilie);
      if (countchar($parsedmessage,"<img")>$maximages) {
        eval("standarderror(\"".gettemplate("error_toomanyimages")."\");");
        exit;
      }
    }

    $question=censortext($question);
    $counter=0;
    while ($counter++<$polloptions){ // 0..Pollnum-1 we want, as arrays start with 0
      $options[$counter]=censortext($options[$counter]);
    }

    $optionsstring = "";  //lets create the option/votenumber string
    $votesstring="";
    $counter=0;
    while ($counter++<$polloptions) {
      if (trim($options[$counter])!=""){
        $options[$counter]=str_replace("|"," | ",$options[$counter]);
        $optionsstring .= "|||".$options[$counter]; //||| is delimter, 0 means no votes (as new poll)
        $votesstring .= "|||0";
      }
    }

    if (substr($optionsstring,0,3)=="|||") {
      $optionsstring=substr($optionsstring,3);
    }
    if (substr($votesstring,0,3)=="|||") {
      $votesstring=substr($votesstring,3);
    }

    $DB_site->query("INSERT INTO poll (pollid,question,dateline,options,votes,active,numberoptions,timeout,multiple) VALUES (NULL,'".vb_escape($question)."','".time()."','".vb_escape($optionsstring)."','".vb_escape($votesstring)."',1,$optioncount,'".vb_escape($timeout)."','$multiple')");

    $pollid=$DB_site->insert_id();
    //end create new poll


    // update thread
    $DB_site->query("UPDATE user SET lastpost=".time()." WHERE userid=$bbuserinfo[userid]");
    $DB_site->query("UPDATE thread SET visible='$visible',pollid='$pollid',lastpost=".time()." WHERE threadid=$threadinfo[threadid]");
    $DB_site->query("UPDATE forum SET replycount=replycount+1,threadcount=threadcount+1,lastpost='".time()."',lastposter='".vb_escape($bbuserinfo['username'])."' WHERE forumid IN ($foruminfo[parentlist])");

    // redirect
    if ($visible) {
      $goto="showthread.php?s=$session[sessionhash]&amp;threadid=$threadinfo[threadid]";
    } else {
      $goto="forumdisplay.php?s=$session[sessionhash]&amp;forumid=$threadinfo[forumid]";
    }

    eval("standardredirect(\"".gettemplate("redirect_postthanks")."\",\"$goto\");");

  }
}


// ############################### start new poll ###############################
if ($action=="newpoll") {

  $threadid = verifyid("thread",$threadid);
  $threadinfo=getthreadinfo($threadid);
  if ($threadinfo['pollid']!=0) {
    eval("standarderror(\"".gettemplate("error_pollalready")."\");");
    exit;
  }
  updateuserforum($threadinfo['forumid']);
  $foruminfo=getforuminfo($threadinfo['forumid']);

  $thisthread=$DB_site->query("SELECT userid FROM post WHERE threadid='$threadinfo[threadid]' ORDER BY dateline");
  $count = $DB_site->num_rows($thisthread);
  $postuser = $DB_site->fetch_array($thisthread);
  if (($bbuserinfo['userid']!=$postuser['userid'] or $count>1) and !ismoderator($foruminfo['forumid'])) {
    show_nopermission();
  }

  // stop there being too many
  $polloptions = intval($polloptions);
  if ($maxpolloptions>0 and $polloptions>$maxpolloptions) {
    $polloptions=$maxpolloptions;
  }
  // stop there being too few
  if ($polloptions<=1) {
    $polloptions=2;
  }

  // check permissions
  $permissions=getpermissions($foruminfo['forumid']);
  if (!$permissions['canview'] or !$permissions['canpostnew'] or !$permissions['canpostpoll']) {
    // in case someone gets here without permission, we need to update the thread's status

    $visible=iif($foruminfo['moderatenew'],0,1);
    if (ismoderator($foruminfo['forumid'])) {
      $visible=1;
    }

    $DB_site->query("UPDATE thread SET visible='$visible' WHERE threadid=$threadinfo[threadid]");

    show_nopermission();
  }

  $bbcodeon=iif($foruminfo['allowbbcode'],$ontext,$offtext);
  $imgcodeon=iif($foruminfo['allowimages'],$ontext,$offtext);
  $htmlcodeon=iif($foruminfo['allowhtml'],$ontext,$offtext);
  $smilieson=iif($foruminfo['allowsmilies'],$ontext,$offtext);

  $timeout=intval($timeout);

  // draw nav bar
  $navbar=makenavbar($threadid,"thread",0);

  if (!isset($parseurl) OR $parseurl == 1) {
    $parseurlchecked="CHECKED";
  } else {
    $parseurlchecked = '';
  }

  $multiplechecked = iif($multiple, "CHECKED", "");

  if ($foruminfo['allowsmilies']) {
    eval("\$disablesmiliesoption = \"".gettemplate("new_disablesmiliesoption")."\";");
  } else {
    $disablesmiliesoption = '';
  }

  getforumrules($foruminfo, $permissions);

  $counter=0;
  $pollnewbits = '';
  while ($counter++<$polloptions){
    $option['number']=$counter;
    if (is_array($options)) {
      $option['question']=htmlspecialchars($options[$counter]);
    }
    eval("\$pollnewbits .= \"".gettemplate("pollnewbit")."\";");
  }

  $question = htmlspecialchars($question);

  eval("dooutput(\"".gettemplate("newpoll")."\");");

}

// ############################### start poll edit ###############################
if ($action=="polledit") {

  //check if the poll is closed
  $pollid=verifyid("poll",$pollid);
  $pollinfo=$DB_site->query_first("SELECT * FROM poll WHERE pollid='$pollid'");

  $threadinfo=$DB_site->query_first("SELECT * FROM thread WHERE pollid='$pollid' AND open<>10");
  $threadcache[$threadinfo['threadid']]=$threadinfo;
  updateuserforum($threadinfo['forumid']);

  // check if user is allowed to do edit
  if (!ismoderator($threadinfo['forumid'],"caneditposts")) {
    show_nopermission();
  }

  if (!$threadinfo['visible']) {
    $idname="thread";
    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
    exit;
  }

  if (!$pollinfo['active']) {
     $pollinfo['closed']="checked";
  }

  $pollinfo['postdate']=vbdate($dateformat,$pollinfo['dateline']);
  $pollinfo['posttime']=vbdate($timeformat,$pollinfo['dateline']);

  $foruminfo=getforuminfo($threadinfo['threadid']);

  // draw nav bar
  $navbar=makenavbar($threadinfo['threadid'],"thread",1);

  $bbcodeon=iif($foruminfo['allowbbcode'],$ontext,$offtext);
  $imgcodeon=iif($foruminfo['allowimages'],$ontext,$offtext);
  $htmlcodeon=iif($foruminfo['allowhtml'],$ontext,$offtext);
  $smilieson=iif($foruminfo['allowsmilies'],$ontext,$offtext);

  $perms=getpermissions($threadinfo['forumid']);
  getforumrules($foruminfo,$perms);

  //get options
  $splitoptions=explode("|||", $pollinfo['options']);
  $splitvotes=explode("|||",$pollinfo['votes']);

  $counter=0;
  while ($counter++<$pollinfo['numberoptions']) {
    $pollinfo['numbervotes']+=$splitvotes[$counter-1];
  }

  $counter=0;
  $pollbits="";

  $pollinfo['question'] = htmlspecialchars(unhtmlspecialchars($pollinfo['question']));

  $option = array();
  while ($counter++<$pollinfo['numberoptions']) {
    $option['question'] = htmlspecialchars($splitoptions[$counter-1]);
    $option['votes'] = $splitvotes[$counter-1];  //get the vote count for the option
    $option['number'] = $counter;  //number of the option

    eval("\$pollbits .= \"".gettemplate("polleditbit")."\";");
  }


  eval("dooutput(\"".gettemplate("editpoll")."\");");
}

// ############################### start adding the edit to the db ###############################

if ($_POST['action']=="updatepoll") {


  //check if the poll is closed
  $pollid=verifyid("poll",$pollid);
  $pollinfo=$DB_site->query_first("SELECT * FROM poll WHERE pollid='$pollid'");

  $threadinfo=$DB_site->query_first("SELECT * FROM thread WHERE pollid='$pollid'");
  $threadcache[$threadinfo['threadid']]=$threadinfo;
  updateuserforum($threadinfo['forumid']);

  // check if user is allowed to do edit
  if (!ismoderator($threadinfo['forumid'],"caneditposts")) {
    show_nopermission();
  }

  if (!$threadinfo['visible']) {
    $idname="thread";
    eval("standarderror(\"".gettemplate("error_invalidid")."\");");
    exit;
  }

  //check if there are 2 options or more after edit
  $optioncount=0;
  $votescount=0;
  $votesstring="";
  $optionsstring="";
  $counter=0;
  while ($counter++<$pollinfo['numberoptions']+2) {
    if (trim($options[$counter])!=""){
      $options[$counter]=str_replace("|"," | ",$options[$counter]);
      $optionsstring .= "|||".unhtmlspecialchars($options[$counter]); //||| is delimter, 0 means no votes (as new poll)
	  // sanity check for votes count
	  $votesbit = intval($pollvotes[$counter]);
	  if ($votesbit < 0) {
	    $votesbit = 0;
	  }
      $votesstring .= "|||".$votesbit;
      $optioncount++;
    }
  }

  if (substr($optionsstring,0,3)=="|||") {
    $optionsstring=substr($optionsstring,3);
  }
  if (substr($votesstring,0,3)=="|||") {
    $votesstring=substr($votesstring,3);
  }

  if (trim($pollquestion)=="" or $optioncount < 2){
    eval("standarderror(\"".gettemplate("error_noquestionoption")."\");");
    exit;
  }

  if ($closepoll == "yes"){
      $pollactive=0;
  } else {
    $pollactive=1;
  }

  $DB_site->query("UPDATE poll SET numberoptions='$optioncount',question='".vb_escape($pollquestion)."',votes='".vb_escape($votesstring)."',options='".vb_escape($optionsstring)."',active='$pollactive',timeout='".vb_escape($timeout)."' WHERE pollid=$pollid");
  eval("standardredirect(\"".gettemplate("redirect_editthanks")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadinfo[threadid]\");");
}

// ############################### start show results without vote ###############################
if ($action=="showresults") {

  $pollid=verifyid("poll",$pollid);
  $pollinfo=$DB_site->query_first("SELECT * FROM poll WHERE pollid='$pollid'");

  $threadinfo=$DB_site->query_first("SELECT * FROM thread WHERE pollid='$pollid'");
  $threadcache[$threadinfo['threadid']]=$threadinfo;
  updateuserforum($threadinfo['forumid']);

  $foruminfo=getforuminfo($threadinfo['forumid']);

  // check permissions
  $permissions=getpermissions($foruminfo['forumid']);
  if (!$permissions['canview']) {
    show_nopermission();
  }

  // draw nav bar
  $navbar=makenavbar($threadinfo['threadid'],"thread",1);

  $pollinfo['question']=bbcodeparse($pollinfo['question'],$foruminfo['forumid'],1);

  $splitoptions=explode("|||", $pollinfo['options']);
  $splitvotes=explode("|||",$pollinfo['votes']);

  $counter=0;
  while ($counter++<$pollinfo['numberoptions']) {
    $pollinfo['numbervotes']+=$splitvotes[$counter-1];
  }

  $counter=0;
  $pollbits="";

  $option = array();
  while ($counter++<$pollinfo['numberoptions']) {
    $option['question'] = bbcodeparse($splitoptions[$counter-1],$foruminfo['forumid'],1);
    $option['votes'] = $splitvotes[$counter-1];  //get the vote count for the option
    $option['number'] = $counter;  //number of the option

    if ($option['votes'] == 0){
      $option['percent']=0;
    } else{
      $option['percent'] = number_format($option['votes']/$pollinfo['numbervotes']*100,2);
    }

    $option['graphicnumber']=$option['number']%6 + 1;
    $option['barnumber'] = round($option['percent'])*2;

    eval("\$pollbits .= \"".gettemplate("pollresult")."\";");
  }

  eval("\$poll = \"".gettemplate("pollresults")."\";");

  eval("dooutput(\"".gettemplate("pollresults")."\");");
}

// ############################### start vote on poll ###############################
if ($_POST['action'] == 'pollvote' OR ($action == 'pollvote' AND $s == $session['dbsessionhash'])) {

  $pollid=verifyid("poll",$pollid);
  $pollinfo=$DB_site->query_first("SELECT * FROM poll WHERE pollid='$pollid'");

  $threadinfo=$DB_site->query_first("SELECT * FROM thread WHERE pollid='$pollid' AND open<>10");
  $threadcache[$threadinfo['threadid']]=$threadinfo;
  updateuserforum($threadinfo['forumid']);

  // other permissions?
  $permissions=getpermissions($threadinfo['forumid']);
  if (!$permissions['canview'] or !$permissions['canvote']) {
    show_nopermission();
  }

  //check if poll is closed
  if (!$pollinfo['active'] or !$threadinfo['open'] or ($pollinfo['dateline']+($pollinfo['timeout']*86400)<time() and $pollinfo['timeout']!=0)){ //poll closed
     eval("standarderror(\"".gettemplate("error_pollclosed")."\");");
     exit;
  }

  //check if an option was selected
  if ($optionnumber) {
    if ($bbuserinfo['userid']==0) {
      if (get_bbarraycookie('pollvoted', $pollid)) {
				//the user has voted before
				eval("standarderror(\"".gettemplate("error_useralreadyvote")."\");");
				exit;
      } else {
        set_bbarraycookie('pollvoted', $pollid, 1, 1);
      }
    } elseif ($bbuserinfo['userid']!=0 and $uservoteinfo=$DB_site->query_first("SELECT userid FROM pollvote WHERE userid=$bbuserinfo[userid] AND pollid='$pollid'")){
      //the user has voted before
      eval("standarderror(\"".gettemplate("error_useralreadyvote")."\");");
      exit;
    }

    //Error checking complete, lets get the options
    if ($pollinfo['multiple']) {
      $insertsql = "";
	   while (list($val, $vote)=each($optionnumber)) {
	     if ($vote == "yes") {
	       if ($insertsql) {
	         $insertsql .= ",";
	       }
	       $insertsql .= "('$pollid','$ourtimenow','$val','$bbuserinfo[userid]')";
        }
      }
      if ($insertsql) {
        $DB_site->query("INSERT INTO pollvote (pollid,votedate,voteoption,userid) VALUES $insertsql");
      }
    } else {
      $DB_site->query("INSERT INTO pollvote (pollid,votedate,voteoption,userid) VALUES ('$pollid','$ourtimenow','$optionnumber','$bbuserinfo[userid]')");
    }

    $splitvotes=explode("|||",$pollinfo['votes']);
    if ($pollinfo['multiple']) {
      reset($optionnumber);
      while (list($val, $vote)=each($optionnumber)) {
        if ($vote == 'yes') {
          $splitvotes[$val-1]++;
        }
      }
    } else {
      $splitvotes[$optionnumber-1]++;
    }

    $counter=0;
    $votesstring = '';
    while ($counter<$pollinfo['numberoptions']) {
      $votesstring.="|||".intval($splitvotes[$counter]);
      $counter++;
    }
    if (substr($votesstring,0,3)=="|||") {
      $votesstring=substr($votesstring,3);
    }

    $DB_site->query("UPDATE poll
                     SET votes='".vb_escape($votesstring)."',
                     voters = voters + 1
                     WHERE pollid='$pollid'");

    //make last reply date == last vote date
    if ($updatelastpost){ //option selected in CP
      $DB_site->query("UPDATE thread SET lastpost=$ourtimenow WHERE threadid=$threadinfo[threadid]");
	  // don't do this any more - see http://www.vbulletin.com/forum/showthread.php?t=77713
      //$foruminfo=getforuminfo($threadinfo['forumid']);
      //$DB_site->query("UPDATE forum SET lastpost=$ourtimenow WHERE forumid IN ($foruminfo[parentlist])");
    }

    // redirect
    eval("standardredirect(\"".gettemplate("redirect_pollvotethanks")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadinfo[threadid]\");");
  } else {
    eval("standarderror(\"".gettemplate("error_nopolloptionselected")."\");");
  }
}

?>