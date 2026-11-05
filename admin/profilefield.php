<?php
require_once __DIR__ . '/php8compat.php';
error_reporting(7);

require("./global.php");

cpheader();

adminlog(iif($profilefieldid!=0,"profilefield id = $profilefieldid",""));

if (isset($action)==0) {
  $action="modify";
}

// ###################### Start add #######################
if ($action=="add") {

  doformheader("profilefield","insert");
  maketableheader("Add new user profile field");

  makeinputcode("Title<br> - maximum 50","title");
  makeinputcode("Description<br>- indicate here for the user whether field is required/hidden<br> - maximum 250","description");
  makeinputcode("Maximum Input<br>- how many characters may a user enter into this field?<br> - maximum 250","maxlength","100");
  makeinputcode("Field Length<br>- how many characters long shall the input field appear on the form?<br> - maximum 250","size","25");
  makeinputcode("Display Order<br>- Sets order of fields when displayed at registration or getinfo","displayorder");
  makeyesnocode("Field required?<br>- Require field to be filled during registration.<br>- Does not apply if you select Yes to Hidden below","required",0);
  makeyesnocode("Field hidden on Profile?<br>- Field only viewable by admins and mods<br>- User will still be able to change it","hidden",0);
  makeyesnocode("Field Editable by the user?<br>- If no, field is only viewable by admins and mods<br>- If no, user cannot edit or view this field","editable",1);

  doformfooter("Save");
}

// ###################### Start insert #######################
if ($_POST['action']=="insert") {

  $DB_site->query("INSERT INTO profilefield (profilefieldid,title,description,required,hidden,maxlength,size,displayorder,editable) VALUES (NULL,'".vb_escape($title)."','".vb_escape($description)."',$required,$hidden,'$maxlength','$size','$displayorder', '$editable')");
  $profilefieldid=$DB_site->insert_id();

  $DB_site->query("ALTER TABLE userfield ADD field$profilefieldid CHAR(250) NOT NULL");
  // Uncomment this line and the one id "Do Update" to change the field size to match what the user specifies as "maxlength"
  //$DB_site->query("ALTER TABLE userfield ADD field$profilefieldid CHAR($maxlength) NOT NULL");
  $DB_site->query("OPTIMIZE TABLE userfield");

  $action="modify";

  echo "<p>Record added</p>";

}

// ###################### Start edit #######################
if ($action=="edit") {

  $profilefield=$DB_site->query_first("SELECT title,description,required,hidden,maxlength,size,displayorder,editable
                                       FROM profilefield
                                       WHERE profilefieldid=$profilefieldid");

  doformheader("profilefield","doupdate");
  maketableheader("Edit user profile field:</b> <i>$profilefield[title]</i>","",0);
  makehiddencode("profilefieldid","$profilefieldid");

  makeinputcode("Title<br> - maximum 50","title",$profilefield['title']);
  makeinputcode("Description<br>- indicate here for the user whether field is required/hidden<br> - maximum 250","description",$profilefield['description']);
  makeinputcode("Maximum Input<br>- how many characters may a user enter into this field?<br> - maximum 250","maxlength",$profilefield['maxlength']);
  makeinputcode("Field Length<br>- how many characters long shall the input field appear on the form?<br> - maximum 250","size",$profilefield['size']);
  makeinputcode("Display Order<br>- Sets order of fields when displayed at registration or getinfo<br> - Starts at 1","displayorder",$profilefield['displayorder']);
  makeyesnocode("Field required?<br>- Require field to be filled during registration.<br>- Does not apply if you select Yes to Hidden below","required",$profilefield['required']);
  makeyesnocode("Field hidden on Profile?<br>- Field only viewable by admins and mods<br>- User will still be able to change it","hidden",$profilefield['hidden']);
  makeyesnocode("Field Editable by the user?<br>- If no, field is only viewable by admins and mods<br>- If no, user cannot edit or view this field","editable",$profilefield['editable']);

  doformfooter("Save Changes");

}

// ###################### Start do update #######################
if ($_POST['action']=="doupdate") {
  $profilefield=$DB_site->query_first("SELECT title FROM profilefield WHERE profilefieldid=$profilefieldid");

  $DB_site->query("UPDATE profilefield SET title='".vb_escape($title)."',description='".vb_escape($description)."',required=$required,hidden=$hidden,maxlength='$maxlength',size='$size',displayorder='$displayorder',editable='$editable' WHERE profilefieldid=$profilefieldid");
  //$DB_site->query("ALTER TABLE userfield CHANGE field$profilefieldid field$profilefieldid CHAR ($maxlength) NOT NULL");

  echo "<p>Record updated!</p>";

  $action="modify";

}
// ###################### Start Remove #######################

if ($action=="remove") {

	doformheader("profilefield","kill");
	makehiddencode("profilefieldid",$profilefieldid);
	maketableheader("Confirm deletion");
	makedescription("Are you sure you want to delete the selected record? This WILL lose data!");
	doformfooter("Yes","",2,"No");

}

// ###################### Start Kill #######################

if ($_POST['action']=="kill") {

  $DB_site->query("DELETE FROM profilefield WHERE profilefieldid=$profilefieldid");
  $DB_site->query("ALTER TABLE userfield DROP field$profilefieldid");
  $DB_site->query("OPTIMIZE TABLE userfield");

  $action="modify";
}

// ###################### Start modify #######################
if ($action=="modify") {

  $profilefields=$DB_site->query("SELECT IF(required=1,'Yes','No') as required,
                                         IF(hidden=1, 'Yes', 'No') as hidden,
                                         IF(editable=1, 'Yes', 'No') as editable,
                                         maxlength, size, title, profilefieldid
                                  FROM profilefield
                                  ORDER BY displayorder");


  if ($DB_site->num_rows($profilefields)) {
    doformheader("","");
	maketableheader("Custom Profile Fields","",1,7);
	echo "<tr class='".getrowbg()."' align='center'>
	  	  <td align='left'><font size='1'><b>Title</b></font></td>
		  <td><font size='1'><b>Required</b></font></td>
		  <td><font size='1'><b>Hidden</b></font></td>
		  <td><font size='1'><b>Editable</b></font></td>
		  <td><font size='1'><b>Maxlength</b></font></td>
		  <td><font size='1'><b>Size</b></font></td>
		  <td><font size='1'><b>Modify</b></font></td>
		  </tr>\n";
	while ($profilefield=$DB_site->fetch_array($profilefields)) {
  	  echo "<tr class='".getrowbg()."'>
  	        <td width=\"100%\">$profilefield[title]</td>
  	        <td nowrap>$profilefield[required]</td>
  	        <td nowrap>$profilefield[hidden]</td>
  	        <td nowrap>$profilefield[editable]</td>
  	        <td nowrap>$profilefield[maxlength]</td>
  	        <td nowrap>$profilefield[size]</td><td nowrap>".
	        makelinkcode("edit","profilefield.php?s=$session[sessionhash]&amp;action=edit&amp;profilefieldid=$profilefield[profilefieldid]").
			makelinkcode("remove","profilefield.php?s=$session[sessionhash]&amp;action=remove&amp;profilefieldid=$profilefield[profilefieldid]").
		   "</td></tr>\n";
	}
	echo "</table></td></tr></table></form>\n";
  }

}

cpfooter();
?>
