<?php
require_once __DIR__ . '/admin/php8compat.php';

error_reporting(7);
$templatesused = 'whosonline,whosonlinebit,postbit_useremail,postbit_sendpm,postbit_profile,whosonline_ip,whosonline_pm,whosonline_email';
$templatesused .= ',whosonlinebit_ip,whosonlinebit_pm,whosonlinebit_email,whosonline_legend';

require('./global.php');

if (!$WOLenable) {
  eval("standarderror(\"".gettemplate("error_whosonlinedisabled")."\");");
}

$permissions = getpermissions(0,-1,$bbuserinfo['usergroupid']);
$usergroupdef = $permissions;

if (!$usergroupdef['canwhosonline']) {
  show_nopermission();
}

// initialize everything
$postids = '';
$threadids = '';
$forumids = '';
$eventids = '';
$onlinebits = '';
$mod = array();
$userinfo = array();
$guests = array();
$post = array();
$thread = array();
$forum = array();
$gotforum = array();
$event = array();

$datecut = $ourtimenow - $cookietimeout;

function show($userinfo) {
  global $session, $thread, $post, $forum, $event, $gotforum, $hideprivateforums, $bbuserinfo, $timeformat, $displayemails, $enablepms, $bbtitle, $usergroupdef, $numberguests;
  switch($userinfo['activity']) {
    case 'showthread':
      if (!$thread['title'][$userinfo['threadid']] || !$forum['canview'][$thread['forumid'][$userinfo['threadid']]] || (!$forum['canviewothers'][$thread['forumid'][$userinfo['threadid']]] && $thread['postuserid'][$userinfo['threadid']] != $bbuserinfo['userid'])) {
        $userinfo['where'] = "Viewing Thread";
      } else {
        $userinfo['where'] = "Viewing Thread <a href='showthread.php?s=$session[sessionhash]&amp;threadid=$userinfo[threadid]'>".$thread['title'][$userinfo['threadid']]."</a>";
      }
      break;
    case 'showpost':
      if (!$thread['title'][$post[$userinfo['postid']]] || !$forum['canview'][$thread['forumid'][$post[$userinfo['postid']]]] || (!$forum['canviewothers'][$thread['forumid'][$post[$userinfo['postid']]]] && $thread['postuserid'][$post[$userinfo['postid']]] != $bbuserinfo['userid'])) {
        $userinfo['where'] = "Viewing Thread";
      } else {
        $userinfo['where'] = "Viewing Thread <a href='showthread.php?s=$session[sessionhash]&amp;postid=$userinfo[postid]'>".$thread['title'][$post[$userinfo['postid']]]."</a>";
      }
      break;
    case 'lastpost':
      if (!$forum['forumid'][$userinfo['forumid']] || (!$forum['canview'][$userinfo['forumid']] && $hideprivateforums)) {
        $userinfo['where'] = "Viewing Thread";
      } else {
        $userinfo['where'] = "Viewing Last Post in <a href='showthread.php?s=$session[sessionhash]&amp;goto=lastpost&amp;forumid=$userinfo[forumid]'>".$forum['forumid'][$userinfo['forumid']]."</a>";
      }
      break;
    case 'forumdisplay':
      if (!$forum['forumid'][$userinfo['forumid']] || (!$forum['canview'][$userinfo['forumid']] && $hideprivateforums)) {
        $userinfo['where'] = "Viewing Forum";
      } else {
        $userinfo['where'] = "Viewing Forum <a href='forumdisplay.php?s=$session[sessionhash]&amp;forumid=$userinfo[forumid]'>".$forum['forumid'][$userinfo['forumid']]."</a>";
      }
      break;
    case 'newthread':
      if (!$forum['forumid'][$userinfo['forumid']] || (!$forum['canview'][$userinfo['forumid']] && $hideprivateforums)) {
        $userinfo['where'] = "Creating New Thread";
      } else {
        $userinfo['where'] = "Creating New Thread in Forum <a href='forumdisplay.php?s=$session[sessionhash]&amp;forumid=$userinfo[forumid]'>".$forum['forumid'][$userinfo['forumid']]."</a>";
      }
      break;
    case 'newreply':
      if (!$thread['title'][$userinfo['threadid']] || !$forum['canview'][$thread['forumid'][$userinfo['threadid']]] || (!$forum['canviewothers'][$thread['forumid'][$userinfo['threadid']]] && $thread['postuserid'][$userinfo['threadid']] != $bbuserinfo['userid'])) {
        $userinfo['where'] = "Replying to Thread";
      } else {
        $userinfo['where'] = "Replying to Thread <a href='showthread.php?s=$session[sessionhash]&amp;threadid=$userinfo[threadid]'>".$thread['title'][$userinfo['threadid']]."</a>";
      }
      break;
    case 'quote':
      if (!$thread['title'][$post[$userinfo['postid']]] || !$forum['canview'][$thread['forumid'][$post[$userinfo['postid']]]] || (!$forum['canviewothers'][$thread['forumid'][$post[$userinfo['postid']]]] && $thread['postuserid'][$post[$userinfo['postid']]] != $bbuserinfo['userid'])) {
        $userinfo['where'] = "Quoting Post";
      } else {
        $userinfo['where'] = "Quoting Post in Thread <a href='showthread.php?s=$session[sessionhash]&amp;threadid=".$post[$userinfo['postid']]."'>".$thread['title'][$post[$userinfo['postid']]]."</a>";
      }
      break;
    case 'attachment':
      if (!$thread['title'][$post[$userinfo['postid']]] || !$forum['canview'][$thread['forumid'][$post[$userinfo['postid']]]] || (!$forum['canviewothers'][$thread['forumid'][$post[$userinfo['postid']]]] && $thread['postuserid'][$post[$userinfo['postid']]] != $bbuserinfo['userid'])) {
        $userinfo['where'] = "Viewing Attachment";
      } else {
        $userinfo['where'] = "Viewing Attachment in Thread <a href='showthread.php?s=$session[sessionhash]&amp;postid=$userinfo[postid]'>".$thread['title'][$post[$userinfo['postid']]]."</a>";
      }
      break;
    case 'index':
      $userinfo['where'] = "$bbtitle <a href='index.php?s=$session[sessionhash]'>Main Index</a>";
      break;
    case 'online':
      $userinfo['where'] = "Viewing <a href='online.php?s=$session[sessionhash]'>Who's Online</a>";
      break;
    case 'searchnew':
      $userinfo['where'] = "Viewing <a href='search.php?s=$session[sessionhash]&amp;action=getnew'>New Topics</a>";
      break;
    case 'search':
      $userinfo['where'] = "Searching Forums";
      break;
    case 'mail':
      $userinfo['where'] = "Sending Email to another forum user";
      break;
    case 'lostpw':
      $userinfo['where'] = "Retrieving Password";
      break;
    case 'getinfo':
      $userinfo['where'] = "Viewing Profile of a Forum Member";
      break;
    case 'editprofile':
      $userinfo['where'] = "Editing Forum Profile";
      break;
    case 'editoptions':
      $userinfo['where'] = "Editing Forum Options";
      break;
    case 'editpassword':
      $userinfo['where'] = "Changing Forum Password";
      break;
    case 'editavatar':
      $userinfo['where'] = "Changing Avatar";
      break;
    case 'markread':
      $userinfo['where'] = "Marking All Forums as Read";
      break;
    case 'whoposted':
	  if (!$thread['title'][$userinfo['threadid']] || !$forum['canview'][$thread['forumid'][$userinfo['threadid']]] || (!$forum['canviewothers'][$thread['forumid'][$userinfo['threadid']]] && $thread['postuserid'][$userinfo['threadid']] != $bbuserinfo['userid'])) {
	    $userinfo['where'] = "Viewing Thread";
	  } else {
	    $userinfo['where'] = "Viewing Who Posted for <a href='showthread.php?s=$session[sessionhash]&amp;threadid=$userinfo[threadid]'>".$thread['title'][$userinfo['threadid']]."</a>";
	  }
      break;
    case 'showgroups':
      $userinfo['where'] = "Viewing <a href=\"showgroups.php?s=$session[sessionhash]\">Forum Leaders</a>";
      break;
    case 'showprivate':
      $userinfo['where'] = 'Reading a Private Message';
      break;
    case 'newprivate':
      $userinfo['where'] = 'Sending a Private Message';
      break;
    case 'otherprivate':
      $userinfo['where'] = "Using Private Messaging System";
      break;
    case 'ignore':
      $userinfo['where'] = "Editing Ignore List";
      break;
    case 'buddy':
      $userinfo['where'] = "Editing Buddy List";
      break;
    case 'addbuddy':
      $userinfo['where'] = "Adding Member to Buddy List";
      break;
    case 'addignore':
      $userinfo['where'] = "Adding Member to Ignore List";
      break;
    case 'subscription':
      $userinfo['where'] = "Viewing Subscribed Threads";
      break;
    case 'addsub':
      $userinfo['where'] = "Adding Forum Subscription";
      break;
    case 'remsubthread':
      $userinfo['where'] = "Removing Subscribed Threads";
      break;
    case 'remsubforum':
      $userinfo['where'] = "Removing Forum Subscription";
      break;
    case 'usercp':
      $userinfo['where'] = "Viewing <a href='usercp.php?s=$session[sessionhash]'>User Control Panel</a>";
      break;
    case 'memberlist':
      $userinfo['where'] = "Viewing <a href='memberlist.php?s=$session[sessionhash]'>Memberlist</a>";
      break;
    case 'postings':
      $userinfo['where'] = 'Moderating Duties';
      break;
    case 'register':
      $userinfo['where'] = "Registering...";
      break;
    case 'announcement':
      if (!$forum['forumid'][$userinfo['forumid']] || (!$forum['canview'][$userinfo['forumid']] && $hideprivateforums)) {
        $userinfo['where'] = "Viewing Announcement";
      } else {
        $userinfo['where'] = "Viewing Announcement in Forum <a href='forumdisplay.php?s=$session[sessionhash]&amp;forumid=$userinfo[forumid]'>".$forum['forumid'][$userinfo['forumid']]."</a>";
      }
      break;
    case 'pollresults':
      $userinfo['where'] = "Viewing the Voting Results of a Poll";
      break;
    case 'polledit':
      $userinfo['where'] = "Editing Poll";
      break;
    case 'newpoll':
      $userinfo['where'] = 'Creating a New Poll';
      break;
    case 'pollvote':
      $userinfo['where'] = 'Voting on a Poll';
      break;
    case 'showsmilies':
      $userinfo['where'] = "Viewing <a href='misc.php?s=$session[sessionhash]&amp;action=showsmilies'>Smilies</a>";
      break;
    case 'showavatars':
      $userinfo['where'] = "Viewing <a href='misc.php?s=$session[sessionhash]&amp;action=showavatars'>Avatars</a>";
      break;
    case 'bbcode':
      $userinfo['where'] = "Viewing <a href='misc.php?s=$session[sessionhash]&amp;action=bbcode'>vB code</a>";
      break;
    case 'faq':
      $userinfo['where'] = "Viewing <a href='misc.php?s=$session[sessionhash]&amp;action=faq'>FAQ</a>";
      break;
    case 'edit':
      $userinfo['where'] = "Editing Post";
      break;
    case 'sendto':
      $userinfo['where'] = "Sending a Thread to a Friend";
      break;
    case 'report':
      $userinfo['where'] = "Reporting a Post";
      break;
    case 'printthread':
      if (!$thread['title'][$userinfo['threadid']] || !$forum['canview'][$thread['forumid'][$userinfo['threadid']]] || (!$forum['canviewothers'][$thread['forumid'][$userinfo['threadid']]] && $thread['postuserid'][$userinfo['threadid']] != $bbuserinfo['userid'])) {
        $userinfo['where'] = "Viewing Printable Version of a Thread";
      } else {
        $userinfo['where'] = "Viewing Printable Version of Thread <a href='printthread.php?s=$session[sessionhash]&amp;threadid=$userinfo[threadid]'>".$thread['title'][$userinfo['threadid']]."</a>";
      }
      break;
    case 'addpublicevent':
      $userinfo['where'] = "Adding Public Event to the <a href='calendar.php?s=$session[sessionhash]'>Calendar</a>";
      break;
    case 'addprivateevent':
      $userinfo['where'] = "Adding Private Event to the <a href='calendar.php?s=$session[sessionhash]'>Calendar</a>";
      break;
    case 'getcalendarinfo':
      if (!$event[$userinfo['eventid']]) {
        $userinfo['where'] = "Viewing <a href='calendar.php?s=$session[sessionhash]'>Calendar</a>";
      } else {
        $userinfo['where'] = "Viewing Calendar event <a href='calendar.php?s=$session[sessionhash]&amp;action=getinfo&amp;eventid=$userinfo[eventid]'>".$event[$userinfo['eventid']]."</a>";
      }
      break;
    case 'getcalendarday':
      if (!$userinfo['calendarday']) {
        $userinfo['where'] = "Viewing <a href='calendar.php?s=$session[sessionhash]'>Calendar</a>";
      } else {
        $userinfo['where'] = "Viewing Calendar day <a href='calendar.php?s=$session[sessionhash]&amp;action=getday&amp;day=$userinfo[calendarday]'>$userinfo[calendarday]</a>";
      }
      break;
    case 'calendar':
      $userinfo['where'] = "Viewing <a href='calendar.php?s=$session[sessionhash]'>Calendar</a>";
      break;
    case 'chat':
      $userinfo['where'] = "Entered Chat";
      break;
    case 'gallery':
      $userinfo['where'] = "Viewing Picture <a href='gallery.php?s=$session[sessionhash]'>Gallery</a>";
      break;
    case 'spider':
      $userinfo['where'] = "Search Engine Spider";
      break;
    default:
      // Let's show the admin the location but put something false up for everyone else..
      if ($bbuserinfo['usergroupid'] == 6) {
         $userinfo['location'] = htmlspecialchars(stripslashes(replacesession($userinfo['location'])));
        $userinfo['where'] = "<b>Unknown Location:</b> <a href=\"$userinfo[location]\">$userinfo[location]</a>";
      } else {
        // We were unable to parse the location
        $userinfo['where'] = "$bbtitle <a href='index.php?s=$session[sessionhash]'>Main Index</a>";
      }
  }
  $userinfo['time'] = vbdate($timeformat,$userinfo['lastactivity']);
  $post['userid'] = $userinfo['userid'];
  $post['username'] = $userinfo['realname'];
  $backcolor = "{firstaltcolor}";
  $bgclass = "alt1";
  if ($enablepms) {
    $backcolor = "{secondaltcolor}";
	$bgclass = "alt2";
    if ($userinfo['receivepm']) {
      eval("\$userinfo[pmlink] = \"".gettemplate("postbit_sendpm")."\";");
    } else {
      $userinfo['pmlink'] = "&nbsp;";
    }
    eval("\$onlinebit_pm = \"".gettemplate("whosonlinebit_pm")."\";");
  }
  if ($displayemails) {
  	if ($backcolor == "{firstaltcolor}") {
    	  $backcolor = "{secondaltcolor}";
		  $bgclass = "alt2";
    } else {
    	  $backcolor = "{firstaltcolor}";
		  $bgclass = "alt1";
    }
    if ($userinfo['showemail']) {
    	  eval("\$userinfo['useremail'] = \"".gettemplate("postbit_useremail")."\";");
    } else {
    	  $userinfo['useremail'] = '&nbsp;';
    }
    eval("\$onlinebit_email = \"".gettemplate("whosonlinebit_email")."\";");
  }
  if ($usergroupdef['canwhosonlineip']) {
    if ($backcolor == "{firstaltcolor}") {
      $backcolor = "{secondaltcolor}";
	  $bgclass = "alt2";
    } else {
      $backcolor = "{firstaltcolor}";
	  $bgclass = "alt1";
    }
    eval("\$onlinebit_ip = \"".gettemplate("whosonlinebit_ip")."\";");
  }
  eval("\$onlinebits .= \"".gettemplate("whosonlinebit")."\";");
  return $onlinebits;
}

function what($userinfo) {
       global $bbuserinfo, $threadids, $postids, $forumids, $eventids, $PHP_SELF;

  $loc = $userinfo['location'];
  $loc=preg_replace("/\?s=[a-z0-9]{32}(&)?/","?",$loc);
  if ($loc==$userinfo['location']) {
    $loc=preg_replace("/\?s=(&)?/","?",$loc);
  }
  if ($loc==$userinfo['location']) {
    $loc=preg_replace("/&amp;s=[a-z0-9]{32}/","",$loc);
  }
  if ($loc==$userinfo['location']) {
    $loc=preg_replace("/&amp;s=/","",$loc);
  }

  if ($userinfo['invisible']) {
    $userinfo['hidden'] = '*';
    if ($bbuserinfo['usergroupid'] == 6) {
      $userinfo['invisible'] = 0;
    }
  }

  $filename = strtok($loc, '?');
  $pos = strrpos ($filename, '/');
  if (!is_string($pos) || $pos) {
    $filename = substr($filename, $pos+1);
  }

  $token1 = strtok('&');
  $token2 = strtok('&');
  $token3 = strtok('&');

// ################################################## Showthread
  switch($filename) {
  case 'showthread.php':
    if (strstr($token1,'threadid')) {
      $blowup = explode('=', $token1);
      $threadid = intval($blowup[1]);
      $threadids .= ",$threadid";
      $userinfo['activity'] = 'showthread';
      $userinfo['threadid'] = $threadid;
    } else if (strstr($token1,'postid')) {
      $blowup = explode('=', $token1);
      $blowupmore = explode('#', $blowup[1]);
      $postid = intval($blowupmore[0]);
      $postids .= ",$postid";
      $userinfo['activity'] = 'showpost';
      $userinfo['postid'] = $postid;
    } else if (strstr($token2,'threadid')) {
      $blowup = explode('=', $token2);
      $threadid = intval($blowup[1]);
      $threadids .= ",$threadid";
      $userinfo['activity'] = 'showthread';
      $userinfo['threadid'] = $threadid;
    } else if ($token1 == 'action=showpost') {
      $blowup = explode('=', $token2);
	  $postid = intval($blowup[1]);
	  $postids .= ",$postid";
	  $userinfo['postid'] = $postid;
      $userinfo['activity'] = 'showpost';
    } else if ($token1 == 'goto=lastpost') {
      $blowup = explode('=', $token2);
      $userinfo['activity'] = 'lastpost';
      $userinfo['forumid'] = intval($blowup[1]);
      $forumids .= ",$userinfo[forumid]";
    }
    break;
  case 'forumdisplay.php':
    $blowup = explode('=', $token1);
    $forumid = intval($blowup[1]);
    $forumids .= ",$forumid";
    $userinfo['activity'] = 'forumdisplay';
    $userinfo['forumid'] = $forumid;
    break;
  case 'attachment.php':
    if (strstr($token1,'postid')) {
      $blowup = explode('=', $token1);
      $postid = intval($blowup[1]);
      $postids .= ",$postid";
    } else if (strstr($token2,'postid')) {
      $blowup = explode('=', $token2);
      $postid = intval($blowup[1]);
      $postids .= ",$postid";
    }
    $userinfo['activity'] = 'attachment';
    $userinfo['postid'] = $postid;
    break;
  case '/':
  case '':
  case 'index.php':
    $userinfo['activity'] = 'index';
    break;
  case 'online.php':
    $userinfo['activity'] = 'online';
    break;
  case 'search.php':
    if ($token1 == 'action=showresults' && $token2 == 'getnew=true') {
      $userinfo['activity'] = 'searchnew';
    } else {
      $blowup = explode('=', $token2);
      $userinfo['activity'] = 'search';
      $userinfo['searchid'] = intval($blowup[1]);
    }
    break;
  case 'newreply.php':
    $blowup = explode('=', $token2);
    if ($blowup[0]=='threadid') {
      $threadid = intval($blowup[1]);
      $threadids .= ",$threadid";
      $userinfo['threadid'] = $threadid;
      $userinfo['activity'] = 'newreply';
    } elseif ($blowup[0]=='postid') {
      $postid = intval($blowup[1]);
      $postids .= ",$postid";
      $userinfo['postid'] = $postid;
      $userinfo['activity'] = 'quote';
    } else {
      $userinfo['activity'] = 'newreply';
    }
    break;
  case 'newthread.php':
    $userinfo['activity'] = 'newthread';
    $blowup = explode('=', $token2);
    $forumid = intval($blowup[1]);
    $forumids .= ",$forumid";
    $userinfo['forumid'] = $forumid;
    break;
  case 'member.php':
    if ($token1 == 'action=mailform') {
      $userinfo['activity'] = "mail";
    } else if ($token1 == 'action=editprofile') {
      $userinfo['activity'] = 'editprofile';
    } else if ($token1 == 'action=editoptions') {
      $userinfo['activity'] = 'editoptions';
    } else if ($token1 == 'action=editpassword' OR $token1 == 'action=updatepassword' OR $token1 == 'action=emailpassword' OR $token1 == 'action=resetpassword') {
      $userinfo['activity'] = 'editpassword';
    } else if ($token1 == 'action=editavatar') {
      $userinfo['activity'] = 'editavatar';
    } else if ($token1 == 'action=getinfo') {
      $userinfo['activity'] = 'getinfo';
    } else if ($token1 == 'action=lostpw') {
      $userinfo['activity'] = "lostpw";
    } else if ($token1 == 'action=markread') {
      $userinfo['activity'] = "markread";
    } else {
      // Well the user must have just posted a form from member.php and we don't know where they really are so we will say they
      // are in the usercp..
      $userinfo['activity'] = "usercp";
    }
    break;
  case 'showgroups.php':
    $userinfo['activity'] = 'showgroups';
    break;
  case 'editpost.php':
    $userinfo['activity'] = 'edit';
    break;
  case 'private.php':
  case 'private2.php':
    if ($token1 == 'action=show') {
      $userinfo['activity'] = 'showprivate';
    } else if ($token1 == 'action=newmessage') {
      $userinfo['activity'] = 'newprivate';
    } else {
      $userinfo['activity'] = 'otherprivate';
    }
    break;
  case 'member2.php':
    if ($token1 == 'action=viewlist' && $token2 == 'userlist=ignore') {
      $userinfo['activity'] = 'ignore';
    } else if ($token1 == 'action=viewlist' && $token2 == 'userlist=buddy') {
      $userinfo['activity'] = 'buddy';
    } else if ($token1 == 'action=addlist' && $token2 == 'userlist=ignore') {
      $userinfo['activity'] = 'addignore';
    } else if ($token1 == 'action=addlist' && $token2 == 'userlist=buddy') {
      $userinfo['activity'] = 'addbuddy';
    } else if ($token1 == 'action=viewsubscription') {
      $userinfo['activity'] = 'subscription';
    } else if ($token1 == 'action=addsubscription') {
      $userinfo['activity'] = 'addsub';
    } else if ($token1 == 'action=removesubscription' || $token1 == 'action=usub') {
      if ($token2 == 'type=allthread' || strstr($token2, 'threadid')) {
        $userinfo['activity'] = 'remsubthread';
      } else {
        $userinfo['activity'] = 'remsubforum';
      }
    } else {
      $userinfo['activity'] = 'usercp'; // where are they?
    }
    break;
  case 'misc.php':
    if ($token1 == 'action=showsmilies' || $token1 == 'action=getsmilies') {
      $userinfo['activity'] = 'showsmilies';
    } else if ($token1 == 'action=showavatars') {
      $userinfo['activity'] = 'showavatars';
    } else if ($token1 == 'action=bbcode') {
      $userinfo['activity'] = 'bbcode';
    } else if ($token1 == 'action=faq') {
      $userinfo['activity'] = 'faq';
    } else if ($token1 == 'action=whoposted') {
      $userinfo['activity'] = 'whoposted';
      $blowup = explode('=', $token2);
	  $threadid = intval($blowup[1]);
      $threadids .= ",$threadid";
      $userinfo['threadid'] = $threadid;
    } else {
      $userinfo['activity'] = 'index'; // where are they?
    }
    break;
  case 'poll.php':
    if ($token1 == 'action=showresults') {
      $userinfo['activity'] = 'pollresults';
    } else if ($token1 == 'action=polledit') {
      $userinfo['activity'] = 'polledit';
    } else if ($token1 == 'action=newpoll') {
      $userinfo['activity'] = 'newpoll';
    } else if ($token1 == 'action=pollvote') {
      $userinfo['activity'] = 'pollvote';
    } else {
      $blowup = explode('=', $token1);
      if ($blowup[0] == 'threadid') {
        $userinfo['activity'] = 'newpoll';
      }
    }
    break;
  case 'postings.php':
    $userinfo['activity'] = 'postings';
    break;
  case 'memberlist.php':
    $userinfo['activity'] = 'memberlist';
    break;
  case 'regimage.php':
  case 'register.php':
    $userinfo['activity'] = 'register';
    break;
  case 'usercp.php':
    $userinfo['activity'] = 'usercp';
    break;
  case 'calendar.php':
    if ($token1 == 'action=add') {
      if ($token2 == 'type=public') {
        $userinfo['activity'] = 'addpublicevent';
      } elseif ($token2 == 'type=private') {
        $userinfo['activity'] = 'addprivateevent';
      }
    } elseif ($token1 == 'action=getinfo') {
      $blowup = explode('=', $token2);
      $eventid = intval($blowup[1]);
      $eventids .= ",$eventid";
      $userinfo['eventid'] = $eventid;
      $userinfo['activity'] = 'getcalendarinfo';
    } elseif ($token1 == 'action=getday') {
      $blowup = explode('=', $token2);
      $userinfo['activity'] = 'getcalendarday';
      $userinfo['calendarday'] = $blowup[1];
    } else {
      $userinfo['activity'] = 'calendar';
    }
    break;
  case 'announcement.php':
    $userinfo['activity'] = 'announcement';
    $blowup = explode('=', $token1);
    $forumid = intval($blowup[1]);
    $userinfo['forumid'] = $forumid;
    $forumids .= ",$forumid";
    break;
  case 'report.php':
    $userinfo['activity'] = 'report';
    break;
  case 'sendtofriend.php':
    $userinfo['activity'] = 'sendto';
    break;
  case 'printthread.php':
    $userinfo['activity'] = 'printthread';
    $blowup = explode('=', $token1);
    $threadid = intval($blowup[1]);
    $threadids .= ",$threadid";
    $userinfo['threadid'] = $threadid;
    break;
  case 'chat.php':
    $userinfo['activity'] = 'chat';
    break;
  case 'gallery.php':
    $userinfo['activity'] = 'gallery';
    break;
  case '/robots.txt':
    $userinfo['activity'] = 'spider';
    break;
  default:
    $userinfo['activity'] = 'unknown';
  }

  return $userinfo;
}

  $allusers= $DB_site->query("SELECT user.username, session.location, session.lastactivity, user.userid, user.usergroupid, user.invisible, session.host, user.showemail, user.receivepm
                              FROM session
                              ". iif($WOLguests, " LEFT JOIN user USING (userid) ", ",user") ."
                              WHERE session.lastactivity > $datecut
                              ". iif(!$WOLguests, " AND session.userid = user.userid", "") ."
                              ORDER BY user.username
                              ");

  $moderators = $DB_site->query("SELECT DISTINCT userid FROM moderator");
  while ($mods = $DB_site->fetch_array($moderators)) {
    $mod[$mods['userid']] = 1;
  }

  $count = 0;
  while ($user = $DB_site->fetch_array($allusers)) {
    if ($user['userid']) { // Reg'd Member
      $key = $user['userid'];
      if (($userinfo["$key"]['lastactivity'] < $user['lastactivity']) or !$userinfo["$key"]['lastactivity']) {
        $userinfo["$key"]['realname'] = $user['username'];
        if ($user['usergroupid'] == 6 and $highlightadmin) {
          $userinfo["$key"]['username'] = "<b><i>$user[username]</i></b>";
        } else if (($mod["$key"] or $user['usergroupid'] == 5)and $highlightadmin) {
          $userinfo["$key"]['username'] = "<b>$user[username]</b>";
        } else {
	      $userinfo["$key"]['username'] = $user['username'];
	    }
        $userinfo[$key]['location'] = $user['location'];
        $userinfo[$key]['lastactivity'] = $user['lastactivity'];
        $userinfo[$key]['invisible'] = $user['invisible'];
        if ($WOLresolve && $usergroupdef['canwhosonlineip']) {
          $userinfo[$key]['ipaddress'] = @gethostbyaddr($user['host']);
        } else {
          $userinfo[$key]['ipaddress'] = $user['host'];
        }
        $userinfo[$key]['userid'] = $user['userid'];
        $userinfo[$key]['showemail'] = $user['showemail'];
        $userinfo[$key]['receivepm'] = $user['receivepm'];
      }
    } else { // Guest
      $guests[$count]['location'] = $user['location'];
      $guests[$count]['invisible'] = 0;
      $guests[$count]['username'] = "Guest";
      if ($WOLresolve && $usergroupdef['canwhosonlineip']) {
        $guests[$count]['ipaddress'] = @gethostbyaddr($user['host']);
      } else {
        $guests[$count]['ipaddress'] = $user['host'];
      }
      $guests[$count]['lastactivity'] = $user['lastactivity'];
      $count++;
    }
  }

  if ($guests) {
    while ( list($key,$val)=each($guests) ) {
      $guests[$key] = what($val);
    }
    reset($guests);
  }
  if ($userinfo) {
    while ( list($key,$val)=each($userinfo) ) {
      $userinfo[$key] = what($val);
    }
    reset($userinfo);
  }

  $iforums=$DB_site->query('SELECT forumid,parentid,displayorder,title FROM forum WHERE displayorder<>0 AND active=1 ORDER BY parentid,displayorder,forumid');
  while ($iforum=$DB_site->fetch_array($iforums)) {
    $iforumcache["$iforum[parentid]"]["$iforum[displayorder]"]["$iforum[forumid]"] = $iforum;
  }
  unset($iforum);
  $DB_site->free_result($iforums);

  $iforumperms=$DB_site->query("SELECT forumid,canview,canviewothers FROM forumpermission WHERE usergroupid='$bbuserinfo[usergroupid]'");
  while ($iforumperm=$DB_site->fetch_array($iforumperms)) {
    $ipermcache["$iforumperm[forumid]"] = $iforumperm;
  }
  unset($iforumperm);
  $DB_site->free_result($iforumperms);

  $noperms['canview'] = 0;
  $noperms['canviewothers'] = 0;

  if ($bbuserinfo['userid']!=0 and $enableaccess==1) {
    $iaccessperms=$DB_site->query("SELECT forumid,accessmask FROM access WHERE userid='$bbuserinfo[userid]'");
    while ($iaccessperm=$DB_site->fetch_array($iaccessperms)) {
      $iaccesscache["$iaccessperm[forumid]"] = $iaccessperm;
    }
    unset($iaccessperm);
    $DB_site->free_result($iaccessperms);
  } else {
    $iaccesscache = array();
  }

  // Get title information for threads...
  // Make sure user has permission to see results otherwise show something else..
  if ($postids) {
    $postidquery = $DB_site->query("SELECT threadid, postid
                                    FROM post
                                    WHERE postid IN (0$postids)");
    while ($postidqueryr = $DB_site->fetch_array($postidquery)) {
      $threadids .= ",$postidqueryr[threadid]";
      $post[$postidqueryr['postid']] = $postidqueryr['threadid'];
    }
  }
  if ($threadids) {
    $threadresults = $DB_site->query("SELECT title, threadid, forumid, postuserid
                                      FROM thread
                                      WHERE threadid IN (0$threadids)");
    while ($threadresult = $DB_site->fetch_array($threadresults)) {
      $gotforum['forumid'][$threadresult['forumid']] = 1;
      $thread['title'][$threadresult['threadid']] = $threadresult['title'];
      $thread['forumid'][$threadresult['threadid']] = $threadresult['forumid'];
      $thread['postuserid'][$threadresult['threadid']] = $threadresult['postuserid'];
    }
  }
  if ($forumids) {
    // Get forum information for forums...
    // Make sure user has permission to see results otherwise show something else..
    $forumresults = $DB_site->query("SELECT forumid, title
                                     FROM forum
                                     WHERE forumid IN (0$forumids)");
    while ($forumresult = $DB_site->fetch_array($forumresults)) {
      $forum['forumid'][$forumresult['forumid']] = $forumresult['title'];
      $gotforum['forumid'][$forumresult['forumid']] = 1;
    }
  }
  if ($eventids) {
    $eventresults = $DB_site->query("SELECT eventid, subject
                                     FROM calendar_events
                                     WHERE eventid IN (0$eventids) AND public = 1");
    while ($eventresult = $DB_site->fetch_array($eventresults)) {
      $event[$eventresult['eventid']] = htmlspecialchars($eventresult['subject']);
    }
  }
  if ($gotforum) {
    while ( list($key,$val)=each($gotforum['forumid']) ) {
      if ( $enableaccess and is_array($iaccesscache["$key"]) ) {
        if ($iaccesscache["$key"]['accessmask']==1) {
          $forumperms = $usergroupdef;
        } else {
          $forumperms = $noperms;
        }
      } else if ( is_array($ipermcache["$key"]) ) {
        $forumperms = $ipermcache["$key"];
      } else {
        $forumperms = $permissions;
      }
      if ($forumperms['canview']) {
        $forum['canview'][$key] = 1;
      }
      if ($forumperms['canviewothers']) {
        $forum['canviewothers'][$key] = 1;
      }
    }
  }
  $numbervisible = 0;
  if ($userinfo) {
    while ( list($key,$val)=each($userinfo) ) {
      if (!$val['invisible']) {
       $onlinebits .= show($val);
       $numbervisible++;
      }
    }
  }
  $numberguests = 0;
  if ($guests) {
    while ( list($key,$val)=each($guests) ) {
      $numberguests++;
      $onlinebits .= show($val);
    }
  }
  $totalonline = $numbervisible + $numberguests;
  $onlinecolspan = 3;
  if ($usergroupdef['canwhosonlineip']) {
    $onlinecolspan++;
    eval("\$online_ip = \"".gettemplate("whosonline_ip")."\";");
  } else {
    $online_ip = '';
  }
  if ($displayemails) {
    $onlinecolspan++;
    eval("\$online_email = \"".gettemplate("whosonline_email")."\";");
  } else {
    $online_email = '';
  }
  if ($enablepms) {
    $onlinecolspan++;
    eval("\$online_pm = \"".gettemplate("whosonline_pm")."\";");
  } else {
    $online_pm = '';
  }
  $currenttime = vbdate($timeformat,$ourtimenow);
  if ($WOLrefresh) {
    $metarefresh = "<META HTTP-EQUIV=\"refresh\" CONTENT=\"$WOLrefresh; URL=online.php?s=$session[sessionhash]\"> ";
  } else {
    $metarefresh = '';
  }
  if ($highlightadmin) {
  	eval("\$legendtable = \"".gettemplate("whosonline_legend")."\";");
  } else {
    $legendtable = '';
  }
  $frmjmpsel['wol'] = " selected class=\"fjsel\"";
  makeforumjump();
  eval("dooutput(\"".gettemplate("whosonline")."\");");

?>