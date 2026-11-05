<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

$templatesused="calendar_showbirthdays,calendar_birthday,calendar_publicevent,calendar_privateevent,calendar_daylink,calendarbit_today,calendarbit,calendarbit_bg,calendar_publiceventbutton,calendar,calendar_postedby,calendar_daybits,calendar_deleteevent,newpost_disablesmiliesoption,calendar_getday,forumrules,calendar_convert,calendar_enterevent,redirect_calendaraddevent,calendar_privateeventbutton,calendar_publiceventbutton,calendar_daynames_sunday,username_loggedin,calendar_hasbirthday,calendar_getbirthdays";
$templatesused.=",vbcode_smilies,vbcode_smiliebit,vbcode_smilies_getmore,vbcode_buttons,vbcode_sizebits,vbcode_fontbits,vbcode_colorbits";

require ("./global.php");

// get decent textarea size for user's browser
$textareacols = gettextareawidth();

$day = vb_escape($day);

if (!$calendarenabled) {
  eval("standarderror(\"".gettemplate("error_calendardisabled")."\");");
}

$calMonday = 1;

function calcodeparse($bbcode,$smilies=1) {
  global $calallowhtml, $calallowbbcode, $calallowsmilies;
  if ($calallowsmilies == 0)
  {   $smilies = 0;  }
  $bbcode=bbcodeparse2($bbcode,$calallowhtml,$calallowbbcode,$smilies,$calallowbbcode);
  return $bbcode;
}

// Make first part of Calendar Nav Bar
$nav_url="calendar.php?s=$session[sessionhash]";
$nav_title="Calendar";
eval("\$navbits = \"".gettemplate('nav_linkon')."\";");

$today_day = date("j",mktime(date("H"),date("i"),date("s"),date("m"),date("d"),date("Y"))+($bbuserinfo['timezoneoffset']-$timeoffset)*3600);
$today_month = date("n",mktime(date("H"),date("i"),date("s"),date("m"),date("d"),date("Y"))+($bbuserinfo['timezoneoffset']-$timeoffset)*3600);
$today_year = date("Y",mktime(date("H"),date("i"),date("s"),date("m"),date("d"),date("Y"))+($bbuserinfo['timezoneoffset']-$timeoffset)*3600);

$month1name = vbphrase("January");
$month2name = vbphrase("February");
$month3name = vbphrase("March");
$month4name = vbphrase("April");
$month5name = vbphrase("May");
$month6name = vbphrase("June");
$month7name = vbphrase("July");
$month8name = vbphrase("August");
$month9name = vbphrase("September");
$month10name = vbphrase("October");
$month11name = vbphrase("November");
$month12name = vbphrase("December");

if ($action == "" or isset($action)==0)
{
   $action = "display";
}

if ($action == "display") // Show Calendar
{
  $calendarbits = '';

  if (empty($year)) {
    $year= vbdate("Y", $ourtimenow);
  }
  if (empty($month)) {
    $month= vbdate("n", $ourtimenow);
  }
  $month = intval($month);
  $year = intval($year);

  if ($month >= 1 and $month <= 9)
  {  $doublemonth = "0" . (string) $month;   }
  else
  {  $doublemonth = $month;  }

  $prevyear = $year - 1;
  $nextyear = $year + 1;

  //  Set some variables
  $numdays=1;
  $day=1;
  $off=1;

  $NumMonth =date("n");
  $MonthNum=date("n");
  $NameMonth=${'month'.$MonthNum.'name'};
  $NumYear  =date("Y");

  $yearbits = '';
  for ($gyear = 2000; $gyear < ($NumYear + 3); $gyear++)
  { // generate select menu since 2000 and for the next 3
    $yearbits .= "\t\t<option value=\"$gyear\">$gyear</option>\n";
  }
  unset($gyear);

  if ($calbirthday == 1) {
     // Load the birthdays for this month:
     $birthday=$DB_site->query("SELECT birthday,username,userid from user where birthday like '%-$doublemonth-%'");
  }
  // Load the events for the month!
  $events=$DB_site->query("SELECT eventid, subject, eventdate, public
                   FROM calendar_events
                   WHERE eventdate
                   LIKE '$year-$doublemonth-%' AND ((userid = '$bbuserinfo[userid]' AND public = 0) OR (public = 1))");
  //  Figure out how many days are in this month
  while (checkdate($month,$numdays,$year))
  {
    $numdays++;
  }

  //  Figure out what month it is, convert the number to words, and then create
  //  a table with days of the week headers

  if ($month == 1)
  {
    $prevmonth = "$month12name $prevyear";
    $monthtext = "$month1name $year";
    $nextmonth = "$month2name  $year";
  }
  elseif ($month == 12)
  {
    $prevmonth = "$month11name  $year";
    $monthtext = "$month12name  $year";
    $nextmonth = "$month1name  $nextyear";
  }
  else
  {
     // This is at least php 3.0.15 Compatible
     $prevm = "month" . ($month - 1) . "name";
     $prevmonth = $$prevm. " $year";
     $montht = "month" . $month . "name";
     $monthtext = $$montht . " $year";
     $nextm = "month" . ($month + 1) . "name";
     $nextmonth = $$nextm . " $year";
  }

  // Make Nav Bar
  $navbits .= gettemplate("nav_joiner",0);
  $nav_url="";
  $nav_title="$monthtext";
  eval("\$navbits .= \"".gettemplate('nav_linkoff')."\";");
  eval("\$navbar = \"".gettemplate("navbar")."\";");

  /*  Start the table data and make sure the number of days does not exceed
     the total for the month  - $numdays will always be one more than the total
     number of days in the momth  */

  if ($bbuserinfo['startofweek'] > 7 or $bbuserinfo['startofweek'] < 1)
  {   $bbuserinfo['startofweek'] = $calStart;   }
  while ($day<$numdays)
  {
    $userbdays = '';
    $hasbday = 0;
    $haveevents = 0;
    if ($calbirthday == 1 and $DB_site->num_rows($birthday)>0) {
			unset($userday);
			unset($age);
			$bdaycount=0;
			$DB_site->data_seek(0,$birthday);
			// Need to move this to a regular array
			while ($birthdays=$DB_site->fetch_array($birthday)) {
				$userday = explode("-",$birthdays['birthday']);
				if ($day == $userday[2]) {
					$bdaycount++;
					$hasbday = 1;
					$bd_user = $birthdays['username'];
					$bd_userid = $birthdays['userid'];
					if ($year > $userday[0] && $userday[0]!='0000') {
						$age = '('.($year-$userday[0]).')';
					} else {
						unset($age);
					}
					if ($calshowbirthdays) {
						eval ("\$userbdays .= \"".gettemplate("calendar_birthday")."\";");
					}
				}
			}
			if ($hasbday==1 and !$calshowbirthdays) {
				eval ("\$userbdays = \"".gettemplate("calendar_hasbirthday")."\";");
      }
    }
    if ($DB_site->num_rows($events)>0)
    {
      $userevents = '';
      $gotone = 0;
      $DB_site->data_seek(0,$events);
      $haveevents = 0;
      // Need to move this to a regular array
      while ($event=$DB_site->fetch_array($events))
      {
        $eventday = explode("-",$event['eventdate']);
        if ($eventday[2] == $day)
        {
          $haveevents = 1;
          $eventsubject =  htmlspecialchars($event['subject']);
          if ($caltitlelength != 0 and isset($caltitlelength)!=0)
          {
             if (strlen($eventsubject) > $caltitlelength)
             {
                $eventsubject = substr($eventsubject,0,$caltitlelength) . "...";
             }
          }
          $eventid = $event['eventid'];
          if ($event['public'] == 1) // Public Event
          {
            eval ("\$userevents .= \"".gettemplate("calendar_publicevent")."\";");
          }
          else  // Private Event
          {
            eval ("\$userevents .= \"".gettemplate("calendar_privateevent")."\";");
          }
        }
      }
    }
    if ($haveevents == 1) {
       eval ("\$daylink = \"".gettemplate("calendar_daylink")."\";");
    }
    else
    {
       $daylink = $day;
    }

        // What day is the 1st of the month on?
    if ($day == 1 and date('l', mktime(0,0,0,$month,$day,$year)) == 'Sunday')    {
       if ($bbuserinfo['startofweek'] == 1) {
          $off = 1;
       } else if ($bbuserinfo['startofweek'] == 2) {
          $off= 7;
          $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
       } else if ($bbuserinfo['startofweek'] == 3) {
          $off = 6;
          $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
       } else if ($bbuserinfo['startofweek'] == 4) {
          $off = 5;
          $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
       } else if ($bbuserinfo['startofweek'] == 5) {
          $off = 4;
          $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
       } else if ($bbuserinfo['startofweek'] == 6) {
          $off = 3;
          $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td>";
       } else if ($bbuserinfo['startofweek'] == 7) {
          $off = 2;
          $calendarbits .= "<td>&nbsp;</td>";
       }
    } elseif ($day == 1 and date('l', mktime(0,0,0,$month,$day,$year)) == 'Monday') {
      if ($bbuserinfo['startofweek'] == 1) {
       $off= 2;
       $calendarbits .= "<td>&nbsp;</td>";
      } else if ($bbuserinfo['startofweek'] == 2)  {
         $off = 1;
      } else if ($bbuserinfo['startofweek'] == 3)  {
          $off= 7;
          $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
      } else if ($bbuserinfo['startofweek'] == 4)  {
          $off = 6;
          $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
      } else if ($bbuserinfo['startofweek'] == 5)  {
          $off = 5;
          $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
      } else if ($bbuserinfo['startofweek'] == 6)  {
          $off = 4;
          $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
      } else if ($bbuserinfo['startofweek'] == 7)  {
          $off = 3;
          $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td>";
      }
    } elseif ($day == 1 and date('l', mktime(0,0,0,$month,$day,$year)) == 'Tuesday') {
      if ($bbuserinfo['startofweek'] == 1) {
          $off= 3;
          $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td>";
      } else if ($bbuserinfo['startofweek'] == 2)  {
          $off= 2;
          $calendarbits .= "<td>&nbsp;</td>";
      } else if ($bbuserinfo['startofweek'] == 3)  {
          $off = 1;
      } else if ($bbuserinfo['startofweek'] == 4)  {
          $off = 7;
          $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
      } else if ($bbuserinfo['startofweek'] == 5)  {
          $off = 6;
          $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
      } else if ($bbuserinfo['startofweek'] == 6)  {
          $off = 5;
          $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
      } else if ($bbuserinfo['startofweek'] == 7)  {
          $off = 4;
          $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
      }
    } elseif ($day == 1 and date('l', mktime(0,0,0,$month,$day,$year)) == 'Wednesday') {
        if ($bbuserinfo['startofweek'] == 1)  {
         $off= 4;
         $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 2) {
           $off= 3;
           $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 3) {
           $off = 2;
           $calendarbits .= "<td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 4) {
           $off = 1;
        } else if ($bbuserinfo['startofweek'] == 5) {
           $off = 7;
           $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 6) {
           $off = 6;
           $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 7) {
           $off = 5;
           $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
        }
    } elseif ($day == 1 and date('l', mktime(0,0,0,$month,$day,$year)) == 'Thursday')  {
        if ($bbuserinfo['startofweek'] == 1) {
         $off= 5;
         $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 2) {
           $off = 4;
           $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 3) {
           $off= 3;
           $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 4) {
           $off = 2;
           $calendarbits .= "<td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 5) {
           $off = 1;
        } else if ($bbuserinfo['startofweek'] == 6) {
           $off = 7;
           $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 7) {
           $off = 6;
           $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
        }
    } elseif ($day == 1 and date('l', mktime(0,0,0,$month,$day,$year)) == 'Friday') {
        if ($bbuserinfo['startofweek'] == 1) {
       $off= 6;
       $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 2) {
           $off = 5;
           $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 3) {
           $off = 4;
           $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 4) {
       $off= 3;
       $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 5) {
           $off = 2;
           $calendarbits .= "<td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 6) {
           $off = 1;
        } else if ($bbuserinfo['startofweek'] == 7) {
           $off = 7;
           $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
        }
    } elseif ($day == 1 and date('l', mktime(0,0,0,$month,$day,$year)) == 'Saturday')  {
        if ($bbuserinfo['startofweek'] == 1) {
         $off= 7;
         $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 2) {
           $off = 6;
           $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 3) {
           $off = 5;
           $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 4) {
           $off = 4;
           $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 5) {
           $off= 3;
       $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td>";
    } else if ($bbuserinfo['startofweek'] == 6) {
       $off = 2;
           $calendarbits .= "<td>&nbsp;</td>";
        } else if ($bbuserinfo['startofweek'] == 7) {
           $off = 1;
        }
    }
    if (($today_day == $day) and ($today_month==$month) and ($today_year==$year))
    {
      eval ("\$calendarbits .= \"".gettemplate("calendarbit_today")."\";");
    }
    else
    {
      eval ("\$calendarbits .= \"".gettemplate("calendarbit")."\";");
    }

    /*  Increment the day and the cells to go before the end of the row and end the
       row when appropriate */
    $day++;
    $off++;
    if (($off>7)||($day==$numdays))
    {
      if ($day != $numdays)
      {
          eval ("\$calendarbits .= \"".gettemplate("calendarbit_bg")."\";");
        $off=1;
      }
      else  // $day == $numdays
      {
        if ($off == 7)
        {  $calendarbits .= "<td>&nbsp;</td>"; }
        else if ($off == 6)
        {  $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td>"; }
        else if ($off == 5)
        {  $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>"; }
        else if ($off == 4)
        {  $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>"; }
        else if ($off == 3)
        {  $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>"; }
        else if ($off == 2)
        {  $calendarbits .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>"; }
      }
    }
  }

  $pm = $month - 1;
  $py = $year - 1;
  if ($month == 1)
  {
    $premonth = "<a href='calendar.php?s=$session[sessionhash]&amp;month=12&amp;year=$py'>$prevmonth</a>";
  }
  else
  {
    $premonth = "<a href='calendar.php?s=$session[sessionhash]&amp;month=$pm&amp;year=$year'>$prevmonth</a>";
  }

  $nm = $month + 1;
  $ny = $year + 1;
  if ($month == 12)
  {
    $nextmonth = "<a href='calendar.php?s=$session[sessionhash]&amp;month=1&amp;year=$ny'>$nextmonth</a>";
  }
  else
  {
    $nextmonth = "<a href='calendar.php?s=$session[sessionhash]&amp;month=$nm&amp;year=$year'>$nextmonth</a>";
  }

    // check permissions
    $permissions=getpermissions();
    if ($permissions['canpublicevent']) {
    eval ("\$addpub = \"".gettemplate("calendar_publiceventbutton")."\";");
  }
  else
  {
    $addpub = "";
  }
  eval ("\$addpriv = \"".gettemplate("calendar_privateeventbutton")."\";");
    if ($bbuserinfo['startofweek'] == 1) {
       eval ("\$calendar_daynames = \"".gettemplate("calendar_daynames_sunday")."\";");
    } else if ($bbuserinfo['startofweek'] == 2) {
       eval ("\$calendar_daynames = \"".gettemplate("calendar_daynames_monday")."\";");
    } else if ($bbuserinfo['startofweek'] == 3) {
       eval ("\$calendar_daynames = \"".gettemplate("calendar_daynames_tuesday")."\";");
    } else if ($bbuserinfo['startofweek'] == 4) {
       eval ("\$calendar_daynames = \"".gettemplate("calendar_daynames_wednesday")."\";");
    } else if ($bbuserinfo['startofweek'] == 5) {
       eval ("\$calendar_daynames = \"".gettemplate("calendar_daynames_thursday")."\";");
    } else if ($bbuserinfo['startofweek'] == 6) {
       eval ("\$calendar_daynames = \"".gettemplate("calendar_daynames_friday")."\";");
    } else if ($bbuserinfo['startofweek'] == 7) {
       eval ("\$calendar_daynames = \"".gettemplate("calendar_daynames_saturday")."\";");
    } else {
      $calendar_daynames = '';
    }

  eval("dooutput(\"".gettemplate("calendar")."\");");
}
else // Show Input/Edit/Display Event forums
{
   if ($action == "getinfo")
   {
      $eventid = intval($eventid);
      if($info = $DB_site->query_first("SELECT eventdate,allowsmilies,username,event,subject,calendar_events.userid,public
                                        FROM calendar_events, user
                                        WHERE eventid = $eventid AND calendar_events.userid = user.userid"))
      {

				if ($info['public'] == 0) { // This is a private event, check userid
					if ($info['userid'] != $bbuserinfo['userid']) {
						eval("standarderror(\"".gettemplate("error_calendarhack")."\");");
						exit;
					}
				}
				$subject = htmlspecialchars($info['subject']);
				if ($wordwrap!=0)
				{  $subject=dowordwrap($subject);  }
				$eventinfo = $info['event'];
				$eventinfo=calcodeparse($eventinfo,$info['allowsmilies']);
				if ($eventinfo == "")
				{   $eventinfo = "&nbsp;";  }
				if ($info['public'] == 1)
				{
					$user = $info['username'];
					eval ("\$postedby = \"".gettemplate("calendar_postedby")."\";");
				} else {
					$postedby = '';
				}

				$eventdate = explode("-",$info['eventdate']);
				$date = date($dateformat,mktime(0,0,0,$eventdate[1],$eventdate[2],$eventdate[0]));
				// Make Rest of Nav Bar
				$navbits .= gettemplate("nav_joiner",0);
				$nav_url ="";
				$nav_title="View Event";
				eval("\$navbits .= \"".gettemplate('nav_linkoff')."\";");
				eval("\$navbar = \"".gettemplate("navbar")."\";");
				eval("\$caldaybits = \"".gettemplate("calendar_daybits")."\";");
				eval("dooutput(\"".gettemplate("calendar_getday")."\");");
			}
		}
		else if ($action == "getday")
		{
			$getdate = explode("-",$day);
			$year = $getdate[0];
			$month = $getdate[1];
			$day = $getdate[2];
			if ($day >= 1 and $day <= 9) {
				 $day = "0" . (string)$day;
			}
			if ($month >= 1 and $month <= 9) {
				 $month = "0" . (string)$month;
			}
			if ($calbirthday == 1) {  // Load the birthdays for today
				$comma = '';
				$birthday=$DB_site->query("SELECT birthday,username,userid from user where birthday like '%-$month-$day'");
				$userbdays = '';
				while ($birthdays=$DB_site->fetch_array($birthday)) {
					$userday = explode("-",$birthdays['birthday']);
					$bd_user = $birthdays['username'];
					$bd_userid = $birthdays['userid'];
					if ($year > $userday[0] && $userday[0]!='0000') {
						$age = '('.($year-$userday[0]).')';
					} else {
						unset($age);
					}
					$haveevents = 1;
					eval ("\$userbdays .= \"$comma ".gettemplate("calendar_showbirthdays")."\";");
					$comma = ',';
				}
			}
			$events=$DB_site->query("SELECT eventdate,calendar_events.userid,user.username,eventid,
																			subject,public,event,allowsmilies
										 FROM calendar_events, user
										 WHERE eventdate =
										 '$year-$month-$day' AND ((calendar_events.userid = '$bbuserinfo[userid]' AND public = 0) OR (public = 1))
														 AND user.userid = calendar_events.userid");
			$caldaybits = '';
			while ($info = $DB_site->fetch_array($events))
			{
				$subject = htmlspecialchars($info['subject']);
				if ($wordwrap!=0)
				{  $subject=dowordwrap($subject);  }
				$eventdate = explode("-",$info['eventdate']);
				$date = date($dateformat,mktime(0,0,0,$eventdate[1],$eventdate[2],$eventdate[0]));
				$eventinfo = $info['event'];
				$eventinfo=calcodeparse($eventinfo,$info['allowsmilies']);
				if ($eventinfo == "")
				{   $eventinfo = "&nbsp;";  }
				if ($info['public'] == 1)
				{
					$user = $info['username'];
					eval ("\$postedby = \"".gettemplate("calendar_postedby")."\";");
					$type = "Public";
				}
				else
				{
					unset($postedby);
					$type = "Private";
				}
				$eventid = $info['eventid'];
				eval ("\$caldaybits .= \"".gettemplate("calendar_daybits")."\";");
			}

			// Make Rest of Nav Bar
			$navbits .= gettemplate("nav_joiner",0);
			$nav_url ="";
			$nav_title=date($dateformat,mktime(0,0,0,$month,$day,$year));
			eval("\$navbits .= \"".gettemplate('nav_linkoff')."\";");
			eval("\$navbar = \"".gettemplate("navbar")."\";");
			if ($userbdays) {
				eval("\$birthdays = \"".gettemplate("calendar_getbirthdays")."\";");
			} else {
			  $birthdays = '';
			}
			eval("dooutput(\"".gettemplate("calendar_getday")."\");");
		}
		else if ($action == "edit")
		{
      $eventid = intval($eventid);
      $eventinfo = $DB_site->query_first("SELECT allowsmilies,public,userid,eventdate,event,subject FROM calendar_events WHERE eventid = $eventid");
      if ($eventinfo['userid'] != $bbuserinfo['userid'])
      {
         $permissions=getpermissions();
         if ($permissions['canpublicedit']!=1) {
				    show_nopermission();
         }
      }
      $parseurlchecked = iif(isset($parseurl)==0, 'CHECKED', '');
      $disablesmilieschecked=iif($eventinfo['allowsmilies']==1,"","CHECKED");
      if ($calallowsmilies==1) {
				eval("\$disablesmiliesoption = \"".gettemplate("newpost_disablesmiliesoption")."\";");
			} else {
				$disablesmiliesoption = '';
			}
      eval ("\$deleteevent = \"".gettemplate("calendar_deleteevent")."\";");
      $bbcodeon=iif($calallowbbcode==1,$ontext,$offtext);
      $imgcodeon=iif($calbbimagecode==1,$ontext,$offtext);
      $htmlcodeon=iif($calallowhtml==1,$ontext,$offtext);
      $smilieson=iif($calallowsmilies==1,$ontext,$offtext);
      $permissions=getpermissions();

      $calrules['allowbbcode'] = $calallowbbcode;
      $calrules['allowimages'] = $calbbimagecode;
      $calrules['allowhtml'] = $calallowhtml;
      $calrules['allowsmilies'] = $calallowsmilies;

      getforumrules($calrules,$permissions);
      $eventdate = explode("-",$eventinfo['eventdate']);
      $subject = htmlspecialchars($eventinfo['subject']);
      $message = htmlspecialchars($eventinfo['event']);
      $dayname = "day".(int)$eventdate[2]."selected";
      $$dayname = "selected";
      $monthname = "month".(int)$eventdate[1]."selected";
      $$monthname = "selected";

      $yearbits = '';
      for ($gyear = 2000; $gyear < ($today_year + 3); $gyear++)
      { // generate select menu since 2000 and for the next 3
        $yearbits .= "\t\t<option value=\"$gyear\" " . iif($eventdate[0] == $gyear, 'selected', '') . ">$gyear</option>\n";
      }
      unset($gyear);

      if ($permissions['canpublicevent']) {
        eval ("\$convert = \"".gettemplate("calendar_convert")."\";");
      } else {
        $convert = '';
      }
       // Make Rest of Nav Bar
   	  $navbits .= gettemplate("nav_joiner",0);
      $nav_url ="";
   	  if ($eventinfo['public'] == 1) {
	     	$nav_title = "Edit Public Event";
	    } else {
	      $nav_title = "Edit Private Event";
      }
  	  eval("\$navbits .= \"".gettemplate('nav_linkoff')."\";");
      eval("\$navbar = \"".gettemplate("navbar")."\";");
      if ($bbuserinfo['showvbcode'] && $allowvbcodebuttons) {
        $vbcode_smilies = getclickysmilies();
        $vbcode_buttons = getcodebuttons();
      } else {
        $vbcode_smilies = '';
        $vbcode_buttons = '';
      }
      eval("dooutput(\"".gettemplate("calendar_enterevent")."\");");
   }
   else if ($action == "add")
   {
      $parseurlchecked = iif(isset($parseurl)==0, 'CHECKED', '');
      if ($calallowsmilies==1)
      {
				eval("\$disablesmiliesoption = \"".gettemplate("newpost_disablesmiliesoption")."\";");
			} else {
				$disablesmiliesoption = '';
			}

      $bbcodeon=iif($calallowbbcode==1,$ontext,$offtext);
      $imgcodeon=iif($calbbimagecode==1,$ontext,$offtext);
      $htmlcodeon=iif($calallowhtml==1,$ontext,$offtext);
      $smilieson=iif($calallowsmilies==1,$ontext,$offtext);

      $permissions=getpermissions();

      $calrules['allowbbcode'] = $calallowbbcode;
      $calrules['allowimages'] = $calbbimagecode;
      $calrules['allowhtml'] = $calallowhtml;
      $calrules['allowsmilies'] = $calallowsmilies;

      getforumrules($calrules,$permissions);

      $dayname = "day".$today_day."selected";
      $$dayname = "selected";
      $monthname = "month".$today_month."selected";
      $$monthname = "selected";

      $yearbits = '';
      for ($gyear = $today_year - 2; $gyear < ($today_year + 3); $gyear++)
      { // generate select menu since 2000 and for the next 3
        $yearbits .= "\t\t<option value=\"$gyear\" " . iif($today_year == $gyear, 'selected', '') . ">$gyear</option>\n";
      }
      unset($gyear);

      if ($bbuserinfo['userid'] == 0)
      {
         show_nopermission();
      }
      $eventid = 0;
      // Make Rest of Nav Bar
      $navbits .= gettemplate("nav_joiner",0);
      $nav_url ="";
      if ($type=="public") {
   	    $nav_title = "Add Public Event";
   	  } else {
   	    $type = "Private";
   	    $nav_title = "Add Private Event";
      }
      eval("\$navbits .= \"".gettemplate('nav_linkoff')."\";");
      eval("\$navbar = \"".gettemplate("navbar")."\";");
      if ($bbuserinfo['showvbcode']) {
        $vbcode_smilies = getclickysmilies();
        $vbcode_buttons = getcodebuttons();
      } else {
        $vbcode_smilies = '';
        $vbcode_buttons = '';
      }
      eval("dooutput(\"".gettemplate("calendar_enterevent")."\");");
   }
   else if ($_POST['action'] == "update")
   {
      $eventid = intval($eventid);
      if ($bbuserinfo['userid'] == 0) {
        show_nopermission();
      }
      if (trim($subject) == "" or isset($subject)==0)
      {
         eval("standarderror(\"".gettemplate("error_calendarfieldmissing")."\");");
         exit;
      }
      else
      {
         if (strlen($message)>$postmaxchars and $postmaxchars!=0) {
		   		  eval("standarderror(\"".gettemplate("error_toolong")."\");");
         }
         $parseurl=iif($parseurl=="yes",1,0);
         $allowsmilies=iif($disablesmilies=="yes",0,1);
         if (!checkdate($month,$day,$year))
         {
					 eval("standarderror(\"".gettemplate("error_calendarbaddate")."\");");
					 exit;
         }
         if ($parseurl==1)
         { $message=parseurl($message);  }
         $date = intval($year) . "-" . intval($month) . "-" . intval($day);
         if ($maximages!=0)
         {
					 $parsedmessage=calcodeparse($message);
					 if (countchar($parsedmessage,"<img")>$maximages)
					 {
							eval("standarderror(\"".gettemplate("error_toomanyimages")."\");");
							exit;
					 }
         }
         $subject=censortext($subject);
         $message=censortext($message);
         $subject = vb_escape($subject);
         $message = vb_escape($message);
         if ($eventid == 0) // No Eventid == Insert Event
         {
            // Insert new field and redirect user to calendar
            if ($type == "public")
            {
               $public = 1;
		           $permissions=getpermissions();
               if (!$permissions['canpublicevent']) {
                  eval("standarderror(\"".gettemplate("error_calendarhack")."\");");
                  exit;
               }
            }
            else
            {
               $public = 0;
            }
            if ($query=$DB_site->query_first("SELECT eventid FROM calendar_events
                                              WHERE userid = '$bbuserinfo[userid]' AND eventdate = '$date' AND event = '$message' AND subject = '$subject'
                                                                       AND public = $public"))
            {
               eval("standarderror(\"".gettemplate("error_calendareventexists")."\");");
               exit;
            }
            else
            {
               $DB_site->query("INSERT INTO calendar_events (userid, event, eventdate, public, subject, allowsmilies)
                                VALUES ('$bbuserinfo[userid]', '$message', '$date', $public, '$subject', $allowsmilies)");
               $eventid = $DB_site->insert_id();
               eval("standardredirect(\"".gettemplate("redirect_calendaraddevent")."\",\"calendar.php?s=$session[sessionhash]&amp;action=getinfo&amp;eventid=$eventid\");");
            }
         }
         else  // Update event if it is truly ours
         {
            $eventinfo = $DB_site->query_first("SELECT userid,public FROM calendar_events WHERE eventid = $eventid");
            $public = $eventinfo['public'];
						if ($eventinfo['userid'] != $bbuserinfo['userid'])
						{
							 $permissions=getpermissions();
							 if (!$permissions['canpublicedit']) {
									show_nopermission();
							 }
						}
           if ($deletepost=="yes")
          {
          $DB_site->query("DELETE FROM calendar_events WHERE eventid = $eventid");
          eval("standardredirect(\"".gettemplate("redirect_calendardeleteevent")."\",\"calendar.php?s=$session[sessionhash]\");");
          }
          else
          {
           if ($convert == "yes")
           {
              $permissions=getpermissions();
                      if ($permissions['canpublicevent']) {
               if ($public == 1)
                 {   $public = 0;  }
               else
               {   $public = 1;  }
            }
            else
            {
               eval("standarderror(\"".gettemplate("error_calendarhack")."\");");
               exit;
            }
           }
          // Update field and redirect user to Calendar
          // We dont need to check for form manipulation because we dont change the public/private status in this query.
          $DB_site->query("UPDATE calendar_events SET event = '$message',
                           eventdate = '$date',
                           subject = '$subject',
                         allowsmilies = $allowsmilies,
                         public = $public
                         WHERE eventid = $eventid");
          eval("standardredirect(\"".gettemplate("redirect_calendarupdateevent")."\",\"calendar.php?s=$session[sessionhash]&amp;action=getinfo&amp;eventid=".intval($eventid)."\");");
           }
        }
   }
}
}
?>
