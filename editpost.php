<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

//$templatesused="redirect_threadclosed,forumrules,newpost_disablesmiliesoption,editpost,redirect_editthanks,redirect_deletethread,redirect_deletepost,redirect_nodelete,posticons,posticonbit,username_loggedin";
$templatesused="forumrules,newpost_disablesmiliesoption,editpost,posticons,posticonbit,username_loggedin,redirect_editthanks";
$templatesused.=",vbcode_smilies,vbcode_smiliebit,vbcode_smilies_getmore,vbcode_buttons,vbcode_sizebits,vbcode_fontbits,vbcode_colorbits";
require("./global.php");

// get decent textarea size for user's browser
$textareacols = gettextareawidth();

$action = trim($action);
if (!isset($action) or $action=="") {
  $action="editpost";
}

// verify postid
$postid = verifyid("post",$postid);

$postinfo=getpostinfo($postid);
if (!$postinfo['visible']) {
  $idname="post";
  eval("standarderror(\"".gettemplate("error_invalidid")."\");");
}

$threadinfo=getthreadinfo($postinfo['threadid']);
if ($wordwrap!=0) {
  $threadinfo['title']=dowordwrap($threadinfo['title']);
}

if (!$threadinfo['visible']) {
  $idname="thread";
  eval("standarderror(\"".gettemplate("error_invalidid")."\");");
}

// get permissions info
$getperms=getpermissions($threadinfo['forumid']);

updateuserforum($threadinfo['forumid']);

if ($action=="deletepost") {
  // is thread being deleted? if so check delete specific permissions
  if (!ismoderator($threadinfo['forumid'],"candeleteposts")) {
    if (!$threadinfo['open']) {
      eval("standardredirect(\"".gettemplate("redirect_threadclosed")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$postinfo[threadid]\");");
      exit;
    }
    if (!$getperms['candeletepost']) {
      show_nopermission();
    } else {
      if ($bbuserinfo['userid']!=$postinfo['userid']) {
	    // check user owns this post since they failed the Mod Delete permission check for this forum
        show_nopermission();
      }
    }
  }
} else {
  // otherwise, post is being edited
  if (!ismoderator($threadinfo['forumid'],"caneditposts")) { // check for moderator
    if (!$threadinfo['open']) {
      eval("standardredirect(\"".gettemplate("redirect_threadclosed")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadinfo[threadid]\");");
      exit;
    }
    if (!$getperms['caneditpost']) {
        show_nopermission();
    } else {
      if ($bbuserinfo['userid']!=$postinfo['userid']) {
        // check user owns this post
        show_nopermission();
      } else {
        // check for time limits
        if ($postinfo['dateline']<(time()-($edittimelimit*60)) and $edittimelimit!=0) {
          eval("standarderror(\"".gettemplate("error_edittimelimit")."\");");
          exit;
        }
      }
    }
  }
}

$foruminfo=getforuminfo($threadinfo['forumid']);

// ############################### start edit post ###############################
if ($action=="editpost") {

  // draw nav bar
  $navbar=makenavbar($threadinfo['threadid'],"thread",1);

  $bbcodeon=iif($foruminfo['allowbbcode'],$ontext,$offtext);
  $imgcodeon=iif($foruminfo['allowimages'],$ontext,$offtext);
  $htmlcodeon=iif($foruminfo['allowhtml'],$ontext,$offtext);
  $smilieson=iif($foruminfo['allowsmilies'],$ontext,$offtext);

  $postinfo['message']=htmlspecialchars($postinfo['pagetext']);
  $postinfo['postdate']=vbdate($dateformat,$postinfo['dateline']);
  $postinfo['posttime']=vbdate($timeformat,$postinfo['dateline']);

  // find out if first post
  $getpost=$DB_site->query_first("SELECT postid FROM post WHERE threadid=$threadinfo[threadid] ORDER BY dateline LIMIT 1");
  if ($getpost['postid']==$postid) {
    $isfirst=1;
  } else {
    $isfirst=0;
  }
  if ($isfirst and $postinfo['title']=="" and $postinfo['dateline']+$editthreadtitlelimit*60>time()) {
    $postinfo['title']=$threadinfo['title'];
  }

  if ($postinfo['userid']!=0) {
    $userinfo=getuserinfo($postinfo['userid']);
    $postinfo['username']=$userinfo['username'];
  } else {
    $userinfo = array();
  }

  if ($foruminfo['allowicons']) {
    $posticons=chooseicons($postinfo['iconid']);
  }  else {
    $posticons="";
  }

  $parseurlchecked="CHECKED";

  $disablesmilieschecked=iif($postinfo['allowsmilie'],"","CHECKED");
  $signaturechecked=iif($postinfo['showsignature'],"CHECKED","");

  if ($checkid=$DB_site->query_first("SELECT subscribethreadid FROM subscribethread WHERE userid='$userinfo[userid]' AND threadid=$threadinfo[threadid]")) {
    $emailchecked="CHECKED";
  } else {
    $emailchecked="";
  }

  if (phpversion() < '4.0.3') {
    $enctype = 'enctype="multipart/form-data"';
  } else if (ini_get('file_uploads')) {
    $enctype = 'enctype="multipart/form-data"';
  } else {
    $enctype = '';
  }
  
  $editattachment = '';
  if ($postinfo['attachmentid']!=0 and (!$safeupload or function_exists("is_uploaded_file"))) {
    // show edit attachment options
    // keep, delete, new upload
    $attachmentinfo=$DB_site->query_first("SELECT filename FROM attachment WHERE attachmentid=$postinfo[attachmentid]");
    $postinfo['filename'] = htmlspecialchars($attachmentinfo['filename']);

    $maxattachsize_temp = getmaxattachsize();

    eval("\$editattachment = \"".gettemplate("editpost_attachment")."\";");
  }

  getforumrules($foruminfo,$getperms);

  $vbcode_smilies = '';
  $vbcode_buttons = '';
  if ($foruminfo['allowsmilies']) {
    if ($bbuserinfo['showvbcode'] && $allowvbcodebuttons)
      $vbcode_smilies = getclickysmilies();
    eval("\$disablesmiliesoption = \"".gettemplate("newpost_disablesmiliesoption")."\";");
  } else {
    $disablesmiliesoption="";
  }
  if ($bbuserinfo['showvbcode'] && $allowvbcodebuttons)
    $vbcode_buttons = getcodebuttons();

  eval("dooutput(\"".gettemplate("editpost")."\");");

}

// ############################### start update post ###############################
if ($_POST['action']=="updatepost") {

  // check for message
  if ($message=="") {
    eval("standarderror(\"".gettemplate("error_nosubject")."\");");
    exit;
  }

  // decode check boxes
  $parseurl=iif($parseurl=="yes",1,0);
  $email=iif($email=="yes",1,0);
  $allowsmilie=iif($disablesmilies=="yes",0,1);
  $signature=iif($signature=="yes",1,0);

  $editedbysql="";
  if ($showeditedby and $postinfo['dateline']<(time()-($noeditedbytime*60)) and !($getperms['ismoderator'] and !$showeditedbyadmin)) {
    $editedbysql=",edituserid='$bbuserinfo[userid]',editdate='".time()."'";
  }

  // check max images
  if ($maximages!=0) {
    $parsedmessage=bbcodeparse($message,$threadinfo['forumid'],$allowsmilie);
    if (countchar($parsedmessage,"<img")>$maximages) {
      eval("standarderror(\"".gettemplate("error_toomanyimages")."\");");
      exit;
    }
  }

  if (!isset($iconid) or $iconid=="") {
    $iconid=0;
  }
  $iconid=intval($iconid);

  if ($parseurl) {
    $message=parseurl($message);
  }

  $attachmentsql="";
  if ($postinfo['attachmentid']!=0) {
    // check attachment status
    // keep, delete, update
    if (trim($attachmentaction)=="delete") {
      $attachmentsql=",attachmentid=0";
      $DB_site->query("DELETE FROM attachment WHERE attachmentid=$postinfo[attachmentid]");
      updatethreadcount($threadinfo['threadid']);
    }
    if (trim($attachmentaction)=="new") {
			// sort attachement
			if (is_array($_FILES)) {
    			$attachment = $_FILES['attachment']['tmp_name'];
    			$attachment_name = $_FILES['attachment']['name'];
    			$attachment_size = $_FILES['attachment']['size'];
    		}
			if (trim($attachment)!="none" and trim($attachment)!="" and trim($attachment_name)!="") {
		  	  $attachmentid=acceptupload($foruminfo['moderateattach']);
              $attachmentsql=",attachmentid='$attachmentid'";
              $DB_site->query("DELETE FROM attachment WHERE attachmentid=$postinfo[attachmentid]");
			}
    }
  }

  // remove sessionhash from urls:
  $message = stripsession($message);

  $title=censortext($title);
  $message=censortext($message);

  // remove all caps subjects
  if ($stopshouting and $subject==strtoupper($subject)) {
    $subject=ucwords(strtolower($subject));
  }

  if (strlen($message)>$postmaxchars and $postmaxchars!=0) {
    eval("standarderror(\"".gettemplate("error_toolong")."\");");
  }

  // find out if first post
  $getpost=$DB_site->query_first("SELECT postid FROM post WHERE threadid=$threadinfo[threadid] ORDER BY dateline LIMIT 1");
  if ($getpost['postid']==$postid) {
    $isfirst=1;
  } else {
    $isfirst=0;
  }
  if ($isfirst and $title!="" and $postinfo['dateline']+$editthreadtitlelimit*60>time()) {
    $DB_site->query("UPDATE thread SET title='".vb_escape(htmlspecialchars($title))."', iconid=".intval($iconid)." WHERE threadid=$threadinfo[threadid]");
    //$title="";
  }

  if ($email) {
    if (!$checkid=$DB_site->query_first("SELECT subscribethreadid FROM subscribethread WHERE userid=$postinfo[userid] AND threadid=$threadinfo[threadid]")) {
      $DB_site->query("INSERT INTO subscribethread (subscribethreadid,userid,threadid) VALUES (NULL,$postinfo[userid],$threadinfo[threadid])");
    } // else : already subscribed, so no need to do that again
  } else {
    if ($checkid=$DB_site->query_first("SELECT subscribethreadid FROM subscribethread WHERE userid=$postinfo[userid] AND threadid=$threadinfo[threadid]")) {
      $DB_site->query("DELETE FROM subscribethread WHERE userid=$postinfo[userid] AND threadid=$threadinfo[threadid]");
    } // else : already unsubscribed, so no need to unsubscribe
  }

  unindexpost($postid);
  $DB_site->query("UPDATE post SET title='".vb_escape(htmlspecialchars($title))."',pagetext='".vb_escape($message)."',allowsmilie='$allowsmilie',showsignature='$signature',iconid='$iconid'$editedbysql$attachmentsql WHERE postid='$postid'");
  indexpost($postid);

  eval("standardredirect(\"".gettemplate("redirect_editthanks")."\",\"showthread.php?s=$session[sessionhash]&amp;postid=$postid#post$postid\");");
}

if ($action=="deletepost") {
  if ($deletepost=="yes") {
    //get first post in thread
    $getfirst=$DB_site->query_first("SELECT postid,dateline FROM post WHERE threadid='$threadid' ORDER BY dateline LIMIT 1");
    if ($getfirst['postid']==$postid) {
      // delete thread
      if ($getperms['candeletethread']) {
        deletethread($threadinfo['threadid'],$foruminfo['countposts']);
        updateforumcount($threadinfo['forumid']);
        eval("standardredirect(\"".gettemplate("redirect_deletethread")."\",\"forumdisplay.php?s=$session[sessionhash]&amp;forumid=$threadinfo[forumid]\");");
      } else {
        show_nopermission();
      }
    } else {
      //delete just this post
      $foruminfo=getforuminfo($threadinfo['forumid']);
      deletepost($postid,$foruminfo['countposts'],$threadinfo['threadid']);

      updatethreadcount($threadinfo['threadid']);
      updateforumcount($threadinfo['forumid']);

     eval("standardredirect(\"".gettemplate("redirect_deletepost")."\",\"showthread.php?s=$session[sessionhash]&amp;threadid=$threadinfo[threadid]\");");
    }
  } else {
    eval("standardredirect(\"".gettemplate("redirect_nodelete")."\",\"showthread.php?s=$session[sessionhash]&amp;postid=$postid#post$postid\");");
  }
}
?>