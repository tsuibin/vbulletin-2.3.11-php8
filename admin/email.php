<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

require("./global.php");

adminlog();

cpheader();

if ($action=="") {
	$action = "start";
}

// *************************** Send a page of emails **********************
if ($_POST['action']=="dosendmail") {

  if (isset($perpage)==0 or $perpage=="") {
    $perpage=500;
  }
  if (isset($startat)==0 or $startat=="") {
    $startat=0;
  }

  if ($condition=="") {

    $condition="1=1";
    if ($ausername!="") {
      $condition.=" AND INSTR(LCASE(username),'".vb_escape(htmlspecialchars(strtolower($ausername)))."')>0";
    }
    if ($usergroupid!=-1 and $usergroupid!="") {
      $condition.=" AND usergroupid=$usergroupid";
    }
    if ($aemail!="") {
      $condition.=" AND INSTR(LCASE(email),'".vb_escape(htmlspecialchars(strtolower($aemail)))."')>0";
    }
    if ($parentemail!="") {
      $condition.=" AND INSTR(LCASE(parentemail),'".vb_escape(htmlspecialchars(strtolower($parentemail)))."')>0";
    }
    $coppauser=strtolower($coppauser);
    if ($coppauser=="yes") {
      $condition.=" AND coppauser=1";
    }
    if ($coppauser=="no") {
      $condition.=" AND coppauser=0";
    }
    if ($homepage!="") {
      $condition.=" AND INSTR(LCASE(homepage),'".vb_escape(htmlspecialchars(strtolower($homepage)))."')>0";
    }
    if ($icq!="") {
      $condition.=" AND INSTR(LCASE(icq),'".vb_escape(htmlspecialchars(strtolower($icq)))."')>0";
    }
    if ($aim!="") {
      $condition.=" AND INSTR(LCASE(aim),'".vb_escape(htmlspecialchars(strtolower($aim)))."')>0";
    }
    if ($yahoo!="") {
      $condition.=" AND INSTR(LCASE(yahoo),'".vb_escape(htmlspecialchars(strtolower($yahoo)))."')>0";
    }
    if ($signature!="") {
      $condition.=" AND INSTR(LCASE(signature),'".vb_escape(strtolower($signature))."')>0";
    }
    if ($usertitle!="") {
      $condition.=" AND INSTR(LCASE(usertitle),'".vb_escape(strtolower($usertitle))."')>0";
    }
    if ($joindateafter!="") {
      $condition.=" AND joindate>UNIX_TIMESTAMP('".vb_escape($joindateafter)."')";
    }
    if ($joindatebefore!="") {
      $condition.=" AND joindate<UNIX_TIMESTAMP('".vb_escape($joindatebefore)."')";
    }
    if ($birthdayafter)
	  $condition.=" AND birthday>'".vb_escape($joindateafter)."'";
		if ($birthdaybefore)
	  $condition.=" AND birthday<'".vb_escape($joindatebefore)."'";
    if ($lastvisitafter!="") {
      $condition.=" AND lastvisit>UNIX_TIMESTAMP('".vb_escape($lastvisitafter)."')";
    }
    if ($lastvisitbefore!="") {
      $condition.=" AND lastvisit<UNIX_TIMESTAMP('".vb_escape($lastvisitbefore)."')";
    }
    if ($lastpostafter!="") {
      $condition.=" AND lastpost>UNIX_TIMESTAMP('".vb_escape($lastpostafter)."')";
    }
    if ($lastpostbefore!="") {
      $condition.=" AND lastpost<UNIX_TIMESTAMP('".vb_escape($lastpostbefore)."')";
    }
    if ($postslower!="") {
      $condition.=" AND posts>'$postslower'";
    }
    if ($postsupper!="") {
      $condition.=" AND posts<'$postsupper'";
    }
}

	$counter = $DB_site->query_first("SELECT COUNT(*) AS total FROM user WHERE $condition AND adminemail=1");
	if ($counter['total']==0) {
		echo "<p><b>No users matched your search conditions.</b></p>\n";
		$action = "start";
	} else {
		$users=$DB_site->query("SELECT userid,usergroupid,username,email,joindate FROM user WHERE $condition AND adminemail=1 ORDER BY userid DESC LIMIT $startat,$perpage");
		if ($DB_site->num_rows($users)) {

			$page = $startat/$perpage+1;
			$totalpages = ceil($counter['total']/$perpage);

			echo "<p><b>Emailing users. Page $page/$totalpages (".number_format($counter['total'])." users total).</b></p>";

			if (strpos($message,"\$activateid")) {
				$hasactivateid=1;
			} else {
				$hasactivateid=0;
			}

			while ($user=$DB_site->fetch_array($users)) {

				echo "$user[userid] - $user[username] .... \n";
				flush();

				$userid=$user['userid'];
				$sendmessage=$message;
				$sendmessage=str_replace("\$email",$user['email'],$sendmessage);
				$sendmessage=str_replace("\$username",$user['username'],$sendmessage);

				$sendmessage=str_replace("\$userid",$user['userid'],$sendmessage);
				if ($hasactivateid) {
					if ($user['usergroupid']==3) { // if in correct usergroup
					  //check for existing one...if not generate new one

						$activate=$DB_site->query_first("SELECT activationid FROM useractivation WHERE userid='$user[userid]' AND type=0");

						mt_srand ((float) microtime() * 1000000);

						if ($activate['activationid']=="") {
							$activate['activationid'] = mt_rand(0,100000000);
							$DB_site->query("
								INSERT INTO useractivation
									(useractivationid, userid, dateline, activationid, type, usergroupid)
								VALUES
									(NULL, $userid, ".time().", '$activate[activationid]', 0, 2)
							");
						} else {
							$activate['activationid'] = mt_rand(0,100000000);
							$DB_site->query("UPDATE useractivation SET dateline='" . time() . "',activationid='$activate[activationid]' WHERE userid=$user[userid] AND type=0");
						}
					}

					$sendmessage=str_replace("\$activateid",$activate['activationid'],$sendmessage);
				}
				$sendmessage=str_replace("\$bburl",$bburl,$sendmessage);
				$sendmessage=str_replace("\$bbtitle",$bbtitle,$sendmessage);

				if (!$test) {
					echo "sending ... \n";
					vbmail($user['email'], $subject, $sendmessage, $from);
				} else {
					echo "testing ... \n";
				}

				echo "ok<br>\n";
				flush();

				$action = "donext";
			}
		} else {
		    echo "<p>All done!</p>";
		}
	}
}

// *************************** Link to next page of emails to send **********************
if ($action=="donext") {

	if ($page++==$totalpages) {
		echo "<p><b>All done!</b></p>";
	} else {
		$startat += $perpage;

		doformheader("email","dosendmail");
		makehiddencode("test",$test);
		makehiddencode("ausername",$ausername);
		makehiddencode("usergroupid",$usergroupid);
		makehiddencode("aemail",$aemail);
		makehiddencode("parentemail",$parentemail);
		makehiddencode("coppauser",$coppauser);
		makehiddencode("homepage",$homepage);
		makehiddencode("icq",$icq);
		makehiddencode("aim",$aim);
		makehiddencode("yahoo",$yahoo);
		makehiddencode("biography",$biography);
		makehiddencode("signature",$signature);
		makehiddencode("usertitle",$usertitle);
		makehiddencode("joindateafter",$joindateafter);
		makehiddencode("joindatebefore",$joindatebefore);
		makehiddencode("lastvisitafter",$lastvisitafter);
		makehiddencode("lastvisitbefore",$lastvisitbefore);
		makehiddencode("lastpostafter",$lastpostafter);
		makehiddencode("lastpostbefore",$lastpostbefore);
		makehiddencode("postslower",$postslower);
		makehiddencode("postsupper",$postsupper);
		makehiddencode("from",$from);
		makehiddencode("subject",$subject);
		makehiddencode("message",$message);
		makehiddencode("startat",$startat);
		makehiddencode("perpage",$perpage);
		makehiddencode("birthdayafter",$birthdayafter);
		makehiddencode("birthdaybefore",$birthdaybefore);
		doformfooter("Do Next Page (page $page)",0);
	}

}

// *************************** Form to build mailing list **********************
if ($action=="genlist") {

  echo "<p>Notes on 'Text to separate addresses by' field: This is a space by default, but you may wish to produce, CSV (in this case use a comma) or quoted CSV (use: \",\" in this case, including the quotes), so both of these are possible.";

  doformheader("email","makelist");

  maketableheader("Generate Mailing List");
  maketextareacode("Text to separate addresses by","septext"," ");

  maketableheader("Select users where:");

  makeinputcode("User Name contains","ausername");
  makechoosercode("and usergroup is","usergroupid","usergroup",-1,"Any");
  makeinputcode("and email contains","email");
  makeinputcode("and parent's email contains","parentemail");
  makeinputcode("and is coppa user (yes, no, blank for don't mind)","coppauser");
  makeinputcode("and homepage contains","homepage");
  makeinputcode("and ICQ Number contains","icq");
  makeinputcode("and AIM Handle contains","aim");
  makeinputcode("and Yahoo Messenger Handle contains","yahoo");
  makeinputcode("and Biography contains","biography");
  makeinputcode("and Signature contains","signature");
  makeinputcode("and User Title contains","usertitle");
  makeinputcode("and Join Date is after<br>(Format yyyy-mm-dd, leave blank for today)","joindateafter");
  makeinputcode("and Join Date is before<br>(Format yyyy-mm-dd, leave blank for today)","joindatebefore");
  makeinputcode("and Birthday is after<br>(Format yyyy-mm-dd)","birthdayafter");
  makeinputcode("and Birthday is before<br>(Format yyyy-mm-dd)","birthdaybefore");
  makeinputcode("and Last Visit is after<br>(Format yyyy-mm-dd hh:mm:ss, leave blank for today)","lastvisitafter");
  makeinputcode("and Last Visit is before<br>(Format yyyy-mm-dd hh:mm:ss, leave blank for today)","lastvisitbefore");
  makeinputcode("and Last Post is after<br>(Format yyyy-mm-dd hh:mm:ss, leave blank for today)","lastpostafter");
  makeinputcode("and Last Post is before<br>(Format yyyy-mm-dd hh:mm:ss, leave blank for today)","lastpostbefore");
  makeinputcode("and Number of Posts is greater than","postslower");
  makeinputcode("and Number of Posts is less than","postsupper");

  doformfooter("Generate List");
}

// *************************** Do make mailing list **********************
if ($_POST['action']=="makelist") {

  $condition="1=1";
  if ($ausername!="") {
    $condition.=" AND INSTR(LCASE(username),'".vb_escape(strtolower($ausername))."')>0";
  }
  if ($usergroupid!=-1 and $usergroupid!="") {
    $condition.=" AND usergroupid=$usergroupid";
  }
  if ($email!="") {
    $condition.=" AND INSTR(LCASE(email),'".vb_escape(strtolower($email))."')>0";
  }
  if ($parentemail!="") {
    $condition.=" AND INSTR(LCASE(parentemail),'".vb_escape(strtolower($parentemail))."')>0";
  }
  $coppauser=strtolower($coppauser);
  if ($coppauser=="yes") {
    $condition.=" AND coppauser=1";
  }
  if ($coppauser=="no") {
    $condition.=" AND coppauser=0";
  }
  if ($homepage!="") {
    $condition.=" AND INSTR(LCASE(homepage),'".vb_escape(strtolower($homepage))."')>0";
  }
  if ($icq!="") {
    $condition.=" AND INSTR(LCASE(icq),'".vb_escape(strtolower($icq))."')>0";
  }
  if ($aim!="") {
    $condition.=" AND INSTR(LCASE(aim),'".vb_escape(strtolower($aim))."')>0";
  }
  if ($yahoo!="") {
    $condition.=" AND INSTR(LCASE(yahoo),'".vb_escape(strtolower($yahoo))."')>0";
  }
  if ($biography!="") {
    $condition.=" AND INSTR(LCASE(biography),'".vb_escape(strtolower($biography))."')>0";
  }
  if ($signature!="") {
    $condition.=" AND INSTR(LCASE(signature),'".vb_escape(strtolower($signature))."')>0";
  }
  if ($usertitle!="") {
    $condition.=" AND INSTR(LCASE(usertitle),'".vb_escape(strtolower($usertitle))."')>0";
  }
  if ($joindateafter!="") {
    $condition.=" AND joindate>UNIX_TIMESTAMP('".vb_escape($joindateafter)."')";
  }
  if ($joindatebefore!="") {
    $condition.=" AND joindate<UNIX_TIMESTAMP('".vb_escape($joindatebefore)."')";
  }
  if ($birthdayafter) {
    $condition.=" AND birthday>'".vb_escape($joindateafter)."'";
  }
  if ($birthdaybefore) {
    $condition.=" AND birthday<'".vb_escape($joindatebefore)."'";
  }
  if ($lastvisitafter!="") {
    $condition.=" AND lastvisit>UNIX_TIMESTAMP('".vb_escape($lastvisitafter)."')";
  }
  if ($lastvisitbefore!="") {
    $condition.=" AND lastvisit<UNIX_TIMESTAMP('".vb_escape($lastvisitbefore)."')";
  }
  if ($lastpostafter!="") {
    $condition.=" AND lastpost>UNIX_TIMESTAMP('".vb_escape($lastpostafter)."')";
  }
  if ($lastpostbefore!="") {
    $condition.=" AND lastpost<UNIX_TIMESTAMP('".vb_escape($lastpostbefore)."')";
  }
  if ($postslower!="") {
    $condition.=" AND posts>$postslower";
  }
  if ($postsupper!="") {
    $condition.=" AND posts<$postsupper";
  }

  $users=$DB_site->query("SELECT email FROM user WHERE $condition AND adminemail=1");
  while ($user=$DB_site->fetch_array($users)) {

    echo $user['email'].$septext;

    flush();

  }

}

// *************************** Main email form **********************
if ($action=="start") {

  echo "<p><b>To generate a list of emails, click <a href='email.php?s=$session[sessionhash]&amp;action=genlist'>here</a>.</b></p>";

  doformheader("email","dosendmail");

  maketableheader("Email Board Members");
  makeyesnocode("Just a test? <font size='1'>(do not actually send emails)</font>","test",0);
  makeinputcode("Messages to send at once:","perpage","500");
  makeinputcode("From:","from",$webmasteremail);
  makeinputcode("Subject:","subject");
  maketextareacode("Message:<p><font size='1'>In the message, you may use \$userid, \$activateid, \$username and \$email.<br><br>This is the string to use if you want to email the activation link to a member:<br><input type='text' size='35' value='\$bburl/register.php?a=act&amp;u=\$userid&amp;i=\$activateid' readonly></font></p><p><font size='1'>Note: you can no longer send passwords using this form as they are encrypted in the database. Please link to the 'lost password' form instead.</font></p>","message","",10,50);

  maketableheader("Email to users where:");

  makeinputcode("User Name contains","ausername");
  makechoosercode("and usergroup is","usergroupid","usergroup",-1,"Any");
  makeinputcode("and email contains","aemail");
  makeinputcode("and parent's email contains","parentemail");
  makeinputcode("and is coppa user (yes, no, blank for don't mind)","coppauser");
  makeinputcode("and homepage contains","homepage");
  makeinputcode("and ICQ Number contains","icq");
  makeinputcode("and AIM Handle contains","aim");
  makeinputcode("and Yahoo Messenger Handle contains","yahoo");
  makeinputcode("and Signature contains","signature");
  makeinputcode("and User Title contains","usertitle");
  makeinputcode("and Join Date is after<br>(Format yyyy-mm-dd, leave blank for today)","joindateafter");
  makeinputcode("and Join Date is before<br>(Format yyyy-mm-dd, leave blank for today)","joindatebefore");
  makeinputcode("and Birthday is after<br>(Format yyyy-mm-dd)","birthdayafter");
  makeinputcode("and Birthday is before<br>(Format yyyy-mm-dd)","birthdaybefore");
  makeinputcode("and Last Visit is after<br>(Format yyyy-mm-dd hh:mm:ss, leave blank for today)","lastvisitafter");
  makeinputcode("and Last Visit is before<br>(Format yyyy-mm-dd hh:mm:ss, leave blank for today)","lastvisitbefore");
  makeinputcode("and Last Post is after<br>(Format yyyy-mm-dd hh:mm:ss, leave blank for today)","lastpostafter");
  makeinputcode("and Last Post is before<br>(Format yyyy-mm-dd hh:mm:ss, leave blank for today)","lastpostbefore");
  makeinputcode("and Number of Posts is greater than","postslower");
  makeinputcode("and Number of Posts is less than","postsupper");

  doformfooter("Send Email");
}

cpfooter();
?>