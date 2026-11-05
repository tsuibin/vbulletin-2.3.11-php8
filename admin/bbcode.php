<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

require ("./global.php");

adminlog(iif($bbcodeid!=0,"bbcode id = $bbcodeid",""));

echo cpheader();

if (!isset($action)) {
  $action="modify";
}

$bbcodes=$DB_site->query("SELECT bbcodetag,bbcodereplacement,twoparams FROM bbcode");
$searcharray = array();
$replacementarray = array();
$doubleRegex = "/(\[)(%s)(=)(['\"]?)([^\"']*)(\\4])(.*)(\[\/%s\])/siU";
$singleRegex = "/(\[)(%s)(])(.*)(\[\/%s\])/siU";

while ($bbcode=$DB_site->fetch_array($bbcodes)) {
  if ($bbcode['twoparams']) {
    $regex=sprintf($doubleRegex, $bbcode['bbcodetag'], $bbcode['bbcodetag']);
  } else {
    $regex=sprintf($singleRegex, $bbcode['bbcodetag'], $bbcode['bbcodetag']);
  }
  $searcharray[] = $regex;
  $replacementarray[] = $bbcode['bbcodereplacement'];
}

if($action=="add") {
  doformheader("bbcode","insert");

  maketableheader("Add New vB Code");
  makeinputcode("vB Code tag","bbcodetag");
  maketextareacode("vB Code replacement","bbcodereplacement","",4,30);
  makeinputcode("vB Code example","bbcodeexample");
  maketextareacode("vB Code explanation","bbcodeexplanation","",10,50);
  makeyesnocode("Use {option} ?","twoparams",1);

  doformfooter("Save");

?>
<p><b>Explanations:</b></p>
<p><b>vB Code Tag:</b> This is the text for the vB code, which goes inside the square brackets.<br>
eg. you would use 'b' for [b] tags, 'url' (without quotes) for [url] tags.</p>
<p><b>vB Code Replacement:</b> This is the HTML code for the vB code replacement. Make sure that you include '{param}' (without the quotes) to insert the text between the opening and closing vB code tags, and '{option}' for the parameter within the vB code tag. You can only use {option} if 'Use Option' is set to yes.<br>
eg. you would use &lt;b>{param}&lt;/b> for [b] tags, &lt;a href="{option}">{param}</a> for [url=xxx] . You will always use '{param}', but you will only use '{option}' when "Use Option?" is "yes."</p>
<p><b>vB Code Example:</b> This is a sample piece of vB code to use as an example for this particular vB code.
eg. you would use [b]Bold[/b] for [b] tags, [url=http://www.vbulletin.com]vBulletin[/url] for [url] tags</p>
<p><b>vB Code explanation:</b> This is a piece of text to describe the vB code tag. This can include HTML tags if you wish.</p>
<p><b>Use Option:</b> Setting this option to yes will allow you to create a [tag=option][/tag] style tag, rather than just a [tag][/tag] style tag.</p>
<?php
}

if($_POST['action']=="insert") {
  if($twoparams) {
    $bbcodereplacement = str_replace("{param}","\\7",$bbcodereplacement);
    $bbcodereplacement = str_replace("{option}","\\5",$bbcodereplacement);
  }  else {
    $bbcodereplacement = str_replace("{param}","\\4",$bbcodereplacement);
  }

  $DB_site->query("INSERT INTO bbcode (bbcodeid,bbcodetag,bbcodereplacement,bbcodeexample,bbcodeexplanation,twoparams) VALUES (NULL,'".trim(vb_escape(preg_quote($bbcodetag)))."','".trim(vb_escape($bbcodereplacement))."','".trim(vb_escape($bbcodeexample))."','".trim(vb_escape($bbcodeexplanation))."', $twoparams)");

  $action="modify";

  echo "<p>Record added</p>";
}

if($action=="edit") {
  $bbcode=$DB_site->query_first("SELECT bbcodetag,bbcodereplacement,bbcodeexample,bbcodeexplanation,twoparams FROM bbcode WHERE bbcodeid=$bbcodeid");

  if($bbcode['twoparams']) {
    $bbcode['bbcodereplacement'] = str_replace("\\7","{param}",$bbcode['bbcodereplacement']);
    $bbcode['bbcodereplacement'] = str_replace("\\5","{option}",$bbcode['bbcodereplacement']);
  }  else {
    $bbcode['bbcodereplacement'] = str_replace("\\4","{param}",$bbcode['bbcodereplacement']);
  }

  doformheader("bbcode","doupdate");
  maketableheader("Edit vB Code:</b> [$bbcode[bbcodetag]]","",0);
  makehiddencode("bbcodeid","$bbcodeid");

  makeinputcode("vB Code tag","bbcodetag",stripslashes($bbcode['bbcodetag'])); //stripslashes to undo preg_quote
  maketextareacode("vB Code replacement","bbcodereplacement",$bbcode['bbcodereplacement'],4,30);
  makeinputcode("vB Code example","bbcodeexample",$bbcode['bbcodeexample']);
  maketextareacode("vB Code explanation","bbcodeexplanation",$bbcode['bbcodeexplanation'],10,50);
  makeyesnocode("Use {option} ?","twoparams",$bbcode['twoparams']);

  doformfooter("Save Changes");

?>
<p><b>Explanations:</b></p>
<p><b>vB Code Tag:</b> This is the text for the vB code, which goes inside the square brackets.<br>
eg. you would use 'b' for [b] tags, 'url' (without quotes) for [url] tags.</p>
<p><b>vB Code Replacement:</b> This is the HTML code for the vB code replacement. Make sure that you include '{param}' (without the quotes) to insert the text between the opening and closing vB code tags, and '{option}' for the parameter within the vB code tag. You can only use {option} if 'Use Parameter' is set to yes.<br>
eg. you would use &lt;b>{param}&lt;/b> for [b] tags, &lt;a href="{option}">{param}</a> for [url=xxx]</p>
<p><b>vB Code Example:</b> This is a sample piece of vB code to use as an example for this particular vB code.
eg. you would use [b]Bold[/b] for [b] tags, [url=http://www.vbulletin.com]vBulletin[/url] for [url] tags</p>
<p><b>vB Code explanation:</b> This is a piece of text to describe the vB code tag. This can include HTML tags if you wish.</p>
<p><b>Use Parameter:</b> Setting this option to yes will allow you to create a [tag=option][/tag] style tag, rather than just a [tag][/tag] style tag.</p>
<?php
}

if($_POST['action']=="doupdate") {
  if($twoparams) {
    $bbcodereplacement = str_replace("{param}","\\7",$bbcodereplacement);
    $bbcodereplacement = str_replace("{option}","\\5",$bbcodereplacement);
  }  else {
    $bbcodereplacement = str_replace("{param}","\\4",$bbcodereplacement);
  }

  $DB_site->query("UPDATE bbcode SET bbcodetag='".trim(vb_escape(preg_quote($bbcodetag)))."',bbcodereplacement='".trim(vb_escape($bbcodereplacement))."',bbcodeexample='".trim(vb_escape($bbcodeexample))."',bbcodeexplanation='".trim(vb_escape($bbcodeexplanation))."',twoparams='".trim(vb_escape($twoparams))."' WHERE bbcodeid=$bbcodeid");

  echo "<p>Record updated!</p>";

  $action="modify";
}

// ************** remove ******************

if ($action=="remove") {

	doformheader("bbcode","kill");
	makehiddencode("bbcodeid",$bbcodeid);
	maketableheader("Confirm deletion");
	makedescription("Are you sure you want to delete this bbcode?");
	doformfooter("Yes","",2,"No");

}

if($_POST['action']=="kill") {
  $DB_site->query("DELETE FROM bbcode WHERE bbcodeid=$bbcodeid");

  $action="modify";
}

if($action=="modify")  {
  $doubleRegex = "/(\[)(%s)(=)(['\"]?)([^\"']*)(\\4])(.*)(\[\/%s\])/siU";
  $singleRegex = "/(\[)(%s)(])(.*)(\[\/%s\])/siU";

  $bbcodes=$DB_site->query("SELECT bbcodeid,bbcodetag,bbcodereplacement,bbcodeexample,bbcodeexplanation,twoparams FROM bbcode");

  //echo "<ul>";
  doformheader("","");
  maketableheader("vBcode Definitions");

  while ($bbcode=$DB_site->fetch_array($bbcodes)) {
    if ($bbcode['twoparams']) {
      $regex=sprintf($doubleRegex, $bbcode['bbcodetag'], $bbcode['bbcodetag']);
    } else {
      $regex=sprintf($singleRegex, $bbcode['bbcodetag'], $bbcode['bbcodetag']);
    }

    $parsedCode = preg_replace($regex, $bbcode['bbcodereplacement'], $bbcode['bbcodeexample']);
	makelabelcode("<b>".htmlspecialchars($bbcode['bbcodeexample'])."</b> is replaced with ".$parsedCode." (" . htmlspecialchars($parsedCode).")",
		makelinkcode("edit","bbcode.php?s=$session[sessionhash]&amp;action=edit&amp;bbcodeid=$bbcode[bbcodeid]").
		makelinkcode("remove","bbcode.php?s=$session[sessionhash]&amp;action=remove&amp;bbcodeid=$bbcode[bbcodeid]")
	);
  }

  dotablefooter();

  doformheader("bbcode","test");
  maketableheader("Test your vB Codes");
  maketextareacode("Enter text with vB Codes:","text","",15,60);
  doformfooter("Test this text");

}

if($_POST['action']=="test") {
  $parsedText = nl2br(preg_replace($searcharray, $replacementarray, $text));

  doformheader("bbcode","test");

  maketableheader("Test your vB Codes");
  makelabelcode("This is how the text will appear after vB Code formatting:","<table border=0 cellspacing=1 cellpadding=4 width=100% class='tblborder'><tr class='secondalt'><td>".iif($parsedText!="",$parsedText,"<i>-- nothing to display! --</i>")."</td></tr></table>");
  maketextareacode("This is the text you entered","text",$text,15,60);

  doformfooter("Test this text");
  echo makelinkcode("Edit custom bbcodes","bbcode.php?s=$session[sessionhash]&amp;action=modify");

}

cpfooter();

?>
