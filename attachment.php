<?php
require_once __DIR__ . '/admin/php8compat.php';
error_reporting(7);

$noheader=1;

require("./global.php");

if ($postid) {
  $postid=verifyid("post",$postid);
} else {
  $attachmentid=verifyid("attachment",$attachmentid);
}

$getforuminfo=$DB_site->query_first("SELECT forumid".
                                     iif($postid,',attachmentid ','')."
                                     FROM thread,post
                                     WHERE post.threadid=thread.threadid ".
                                      iif($postid,"AND post.postid='$postid'","AND post.attachmentid='$attachmentid'")."
                                      ");

$permissions=getpermissions($getforuminfo['forumid']);
if (!$permissions['canview'] or !$permissions['cangetattachment']) {
  show_nopermission();
}

if ($postid) {
  $attachmentid=$getforuminfo['attachmentid'];
}

if (!$attachmentinfo=$DB_site->query_first("SELECT filename,filedata,dateline,visible
							FROM attachment
							WHERE attachmentid='$attachmentid'")){
  $idname='attachment';
  eval("standarderror(\"".gettemplate('error_invalidid')."\");");
  exit;
}

if ($attachmentinfo['visible'] == 0) {
	if (!ismoderator($getforuminfo['forumid'],"canmoderateattachments")) {
		$idname='attachment';
		eval("standarderror(\"".gettemplate('error_invalidid')."\");");
 		exit;
	}
}

updateuserforum($getforuminfo['forumid']);

if ($noshutdownfunc) {
  $DB_site->query("UPDATE attachment SET counter=counter+1 WHERE attachmentid='$attachmentid'");
} else {
  $shutdownqueries[]="UPDATE LOW_PRIORITY attachment SET counter=counter+1 WHERE attachmentid='$attachmentid'";
}

$extension=strtolower(substr(strrchr($attachmentinfo['filename'],"."),1));

header("Cache-control: max-age=31536000");
header("Expires: " . gmdate("D, d M Y H:i:s",time()+31536000) . " GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s",$attachmentinfo['dateline']) . " GMT");
if (in_array($extension, array('jpg', 'jpe', 'jpeg', 'gif', 'png')))
{
	header("Content-disposition: inline; filename=\"$attachmentinfo[filename]\"");
	header('Content-transfer-encoding: binary');
}
else
{
	// force files to be downloaded because of a possible XSS issue in IE
	header("Content-disposition: attachment; filename=\"$attachmentinfo[filename]\"");
}
header("Content-Length: ".strlen($attachmentinfo['filedata']));

if ($extension=='gif') {
  header('Content-type: image/gif');
} elseif ($extension=='jpg' or $extension=='jpeg') {
  header('Content-type: image/jpeg');
} elseif ($extension=='png') {
  header('Content-type: image/png');
} elseif ($extension=='pdf') {
  header('Content-type: application/pdf');
} else {
  header('Content-type: unknown/unknown');
}
echo $attachmentinfo['filedata'];

?>