<?php
require_once __DIR__ . '/admin/php8compat.php';

error_reporting(7);

$templatesused = 'postbit_online,postbit_offline,postbit_sendpm,postbit_avatar,';
$templatesused .= 'showgroups,showgroups_group,showgroups_bit,showgroups_forumheader,showgroups_moderatedforums,showgroups_forumbit';
require ("./global.php");

// check permissions to view
$permissions = getpermissions();
if (!$permissions['canview'])
{
	show_nopermission();
}

// #############################################################################
// douserinfo function
function douserinfo()
{
	global $user, $sendpmlink, $onlinestatus, $showonline, $cookietimeout, $locationfieldid;

	$datecut = time() - $cookietimeout;
	$post['username'] = $user['username'];
	if ($user['field' . $locationfieldid] == '')
	{
		$user['location'] = "&nbsp;";
	}
	else
	{
		$user['location'] = $user['field'.$locationfieldid];
	}

	if ($user['receivepm'] == 0)
	{
		$sendpmlink = "&nbsp;";
	}
	else
	{
		$post['userid'] = $user['userid'];
		eval('$sendpmlink = "' . gettemplate('postbit_sendpm') . '";');
	}

	if ($user['lastactivity'] > $datecut and !$user['invisible'] and $user['lastactivity'] != $user['lastvisit'])
	{
		eval('$onlinestatus = "' . gettemplate('postbit_online') . '";');
	}
	else
	{
		eval('$onlinestatus = "' . gettemplate('postbit_offline') . '";');
	}

}

// #############################################################################
// initialize $moderatedforums and $forumheader
$forumheader = '';
$moderatedforums = '';

// get the fieldid of the location field
if ($field = $DB_site->query_first("SELECT profilefieldid FROM profilefield WHERE profilefieldid=2 OR title='Location'"))
{
	$locationfieldselect = "userfield.field$field[profilefieldid],";
	$locationfieldid = $field['profilefieldid'];
}
else
{
	$locationfieldselect = "";
	$locationfieldid = 0;
}

// #############################################################################
// query forums to build the iforumcache
$iforums = $DB_site->query("
	SELECT forumid,parentid,displayorder,title
	FROM forum
	WHERE displayorder<>0
	AND active=1
	ORDER BY parentid,displayorder,forumid
");
while ($iforum=$DB_site->fetch_array($iforums))
{
  $iforumcache["$iforum[parentid]"]["$iforum[displayorder]"]["$iforum[forumid]"] = $iforum;
}
unset($iforum);
$DB_site->free_result($iforums);

// #############################################################################
// query forumpermissions
$iforumperms = $DB_site->query("
	SELECT forumid, canview
	FROM forumpermission
	WHERE usergroupid = $bbuserinfo[usergroupid]
");
while ($iforumperm = $DB_site->fetch_array($iforumperms)) {
  $ipermcache["$iforumperm[forumid]"] = $iforumperm;
}
unset($iforumperm);
$DB_site->free_result($iforumperms);

$noperms['canview'] = 0;
$noperms['canviewothers'] = 0;
$accesscache = array();

// #############################################################################
// query access masks
if ($bbuserinfo['userid'] and $enableaccess)
{
	//Access table perms
	$accessperms = $DB_site->query("
		SELECT forumid, accessmask
		FROM access
		WHERE userid = $bbuserinfo[userid]
	");
	while ($accessperm=$DB_site->fetch_array($accessperms))
	{
		$accesscache["$accessperm[forumid]"] = $accessperm;
	}
	$DB_site->free_result($accessperms);
	unset($accessperm);
	
	// usergroup defaults
	$usergroupdef['canview'] = $permissions['canview'];
	$usergroupdef['canpostnew'] = $permissions['canpostnew'];
}

// #############################################################################
// get administrators & super moderators
$users = $DB_site->query("
	SELECT
	$locationfieldselect usergroup.title, user.username, user.userid, user.invisible, user.receivepm,
	user.usergroupid, user.lastactivity, user.lastvisit
	FROM usergroup
	LEFT JOIN user ON (usergroup.usergroupid = user.usergroupid)
	LEFT JOIN userfield ON (userfield.userid = user.userid)
	WHERE usergroup.showgroup = 1
");

$admininfo = '';
$modinfo = '';
unset($groupinfo);
$groupbits = '';
while ($user = $DB_site->fetch_array($users))
{
	if (($smodcount++ % 2) == 0)
	{
		$backcolor = "{secondaltcolor}";
	}
	else
	{
		$backcolor = "{firstaltcolor}";
	}
	if (!$user['username'])
	{
		continue;
	}
	douserinfo();
	$usergroupid = $user['usergroupid'];
	if ($usergroupid == 6)
	{
		// Admins
		eval('$admininfo .= "' . gettemplate('showgroups_bit') . '";');
		$admingrouptitle = $user['title'];
	}
	else if ($usergroupid == 5)
	{
		// Super Mods
		eval('$modinfo .= "' . gettemplate('showgroups_bit') . '";');
		$modgrouptitle=$user['title'];
	}
	else
	{
		// (other group)
		if (!$grouptitle[$usergroupid])
		{
			$grouptitle[$usergroupid] = $user['title'];
		}
		eval('$groupinfo[$usergroupid] .= "' . gettemplate('showgroups_bit') . '";');
	}
}

if ($admininfo) {
	if ($admingrouptitle == '') {
	  $groupname = 'Administrators';
	} else {
		$groupname = $admingrouptitle;
	}
	if (substr($groupname,-1)!="s") {
		$groupname .= "s";
	}

	$groupmembers = $admininfo;
	eval("\$groupbits .= \"".gettemplate("showgroups_group")."\";");
	unset($groupmembers);
}

if ($modinfo) {
	if ($modgrouptitle=='') {
	  $groupname = 'Super Moderators';
	} else {
		$groupname = $modgrouptitle;
	}
	if (substr($groupname,-1)!="s") {
		$groupname.="s";
	}

	$groupmembers = $modinfo;
	eval("\$groupbits .= \"".gettemplate("showgroups_group")."\";");
	unset($groupmembers);
}

// build all the group tables
if (is_array($groupinfo))
{
	while(list($key, $val) = each($groupinfo))
	{
		$groupname = $grouptitle["$key"];
		if (substr($groupname, -1) != 's')
		{
			$groupname .= 's';
		}
		$groupmembers = $val;
		eval('$groupbits .= "' . gettemplate('showgroups_group') . '";');
		unset($groupmembers);
	}
}

// #############################################################################
// get moderators
$users = $DB_site->query("
	SELECT
		$locationfieldselect forum.forumid, forum.title AS forumtitle,
		user.username, user.userid, user.invisible, user.receivepm, user.lastactivity, user.lastvisit
		FROM moderator
		LEFT JOIN user ON (user.userid = moderator.userid)
		LEFT JOIN forum ON (forum.forumid = moderator.forumid)
		LEFT JOIN userfield ON (userfield.userid = user.userid)
	WHERE forum.active = 1
	ORDER BY user.username ASC, forum.displayorder ASC
");

unset ($modforums);

// only show moderators if there are any...
if ($DB_site->num_rows($users))
{	
	$groupmembers = '';
	$groupname = 'Moderators';
	eval('$forumheader = "' . gettemplate('showgroups_forumheader') . '";');
	
	while ($user = $DB_site->fetch_array($users))
	{
		// Permissions
		if ( $enableaccess and is_array($accesscache["$user[forumid]"]) )
		{
			if ($accesscache["$user[forumid]"]['accessmask'] == 1)
			{
				$forumperms = $usergroupdef;
			}
			else
			{
				$forumperms = $noperms;
			}
		}
		else if ( is_array($ipermcache["$user[forumid]"]) )
		{
			$forumperms = $ipermcache["$user[forumid]"];
		}
		else
		{
			$forumperms = $permissions;
		}
		
		if (!$hideprivateforums)
		{
			$forumperms['canview'] = 1;
		}
		
		if (!$forumperms['canview'] or !$user['forumid'])
		{
			continue;
		}
		
		$userid = $user['userid'];
		$moderator["$userid"] = $user;
		
		if ($modforums["$userid"] == '')
		{
			eval('$modforums["$userid"] .= "' . gettemplate('showgroups_forumbit') . '";');
		}
		else
		{
			$modforums["$userid"] .= '<br>';
			eval('$modforums["$userid"] .= "' . gettemplate('showgroups_forumbit') . '";');
		}
	}
	unset($user);
		
	$moderatorbits = '';
	if (is_array($moderator))
	{
		while(list($key, $user) = each($moderator))
		{
			if (($modcount++ % 2) == 0)
			{
				$backcolor = "{secondaltcolor}";
			}
			else
			{
				$backcolor = "{firstaltcolor}";
			}
			$forumbits = $modforums["$user[userid]"];
			eval('$moderatedforums = "' . gettemplate('showgroups_moderatedforums') . '";');
			douserinfo();
			eval('$groupmembers .= "' . gettemplate('showgroups_bit') . '";');
		}
	}
	
	eval('$groupbits .= "' . gettemplate('showgroups_group') . '";');

}

// *******************************************************

makeforumjump();
eval('dooutput("' . gettemplate('showgroups') . '");');
?>
