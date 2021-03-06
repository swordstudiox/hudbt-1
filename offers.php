<?php
require_once("include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
require_once(get_langfile_path("",true));
loggedinorreturn();
parked();
if ($enableoffer == 'no')
permissiondenied();
function bark($msg) {
	global $lang_offers;
	stdhead($lang_offers['head_offer_error']);
	stdmsg($lang_offers['std_error'], $msg);
	stdfoot();
	exit;
}

if ($_GET["category"]){
  $categ = isset($_GET['category']) ? (int)$_GET['category'] : 0;
	if(!is_valid_id($categ))
	stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
}

if ($_GET["id"]){
	$id = 0 + htmlspecialchars($_GET["id"]);
	if (preg_match('/^[0-9]+$/', !$id))
	stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
}

//==== add offer
if ($_GET["add_offer"]){
	if (get_user_class() < $addoffer_class)
		permissiondenied();
	$add_offer = 0 + $_GET["add_offer"];
	if($add_offer != '1')
	stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);

	stdhead($lang_offers['head_offer']);

	print("<p>".$lang_offers['text_red_star_required']."</p>");

	print("<form id=\"compose\" action=\"?new_offer=1\" name=\"compose\" method=\"post\">".
	"<table width=940 border=0 cellspacing=0 cellpadding=5><tr><td class=colhead align=center colspan=2>".$lang_offers['text_offers_open_to_all']."</td></tr>\n");

	$s = "<select name=type>\n<option value=0>".$lang_offers['select_type_select']."</option>\n";
	$cats = genrelist($browsecatmode);
	foreach ($cats as $row)
	$s .= "<option value=".$row["id"].">" . htmlspecialchars($row["name"]) . "</option>\n";
	$s .= "</select>\n";
	print("<tr><td class=rowhead align=right><b>".$lang_offers['row_type']."<font color=red>*</font></b></td><td class=rowfollow align=left> $s</td></tr>".
	"<tr><td class=rowhead align=right><b>".$lang_offers['row_title']."<font color=red>*</font></b></td><td class=rowfollow align=left><input type=text name=name style=\"width: 650px;\" />".
	"</td></tr><tr><td class=rowhead align=right><b>".$lang_offers['row_post_or_photo']."</b></td><td class=rowfollow align=left>".
	"<input type=text name=picture style=\"width: 650px;\"><br />".$lang_offers['text_link_to_picture']."</td></tr>".
	"<tr><td class=rowhead align=right valign=top><b>".$lang_offers['row_description']."<b><font color=red>*</font></td><td class=rowfollow align=left>\n");
	textbbcode("compose","body");
	print("</td></tr><tr><td class=toolbox align=center colspan=2><input id=\"qr\" type=\"submit\" class=\"btn\" value=".$lang_offers['submit_add_offer']." ></td></tr></table></form>\n");
	stdfoot();
	die;
}
//=== end add offer

//=== take new offer
if ($_GET["new_offer"]){
	if (get_user_class() < $addoffer_class)
		permissiondenied();
	$new_offer = 0 + $_GET["new_offer"];
	if($new_offer != '1')
	stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);

	$userid = 0 + $CURUSER["id"];
	if (preg_match("/^[0-9]+$/", !$userid))
	stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);

	$name = $_POST["name"];
	if ($name == "")
	bark($lang_offers['std_must_enter_name']);

	$cat = (0 + $_POST["type"]);
	if (!is_valid_id($cat))
	bark($lang_offers['std_must_select_category']);

	$descrmain = unesc($_POST["body"]);
	if (!$descrmain)
	bark($lang_offers['std_must_enter_description']);

	if (!empty($_POST['picture'])){
		$picture = unesc($_POST["picture"]);
		if(!preg_match("/^http:\/\/[^\s'\"<>]+\.(jpg|gif|png)$/i", $picture))
		stderr($lang_offers['std_error'], $lang_offers['std_wrong_image_format']);
		$pic = "[img]".$picture."[/img]\n";
		$descr = $pic;
	}
	else {
	  $descr = '';
	}
	$descr .= $descrmain;

	$res = sql_query("SELECT name FROM offers WHERE name =".sqlesc($_POST['name'])) or sqlerr(__FILE__,__LINE__);
	$arr = _mysql_fetch_assoc($res);
	if (!$arr['name']){
		//===add karma //=== uncomment if you use the mod
		//sql_query("UPDATE LOW_PRIORITY users SET seedbonus = seedbonus+10.0 WHERE id = $CURUSER[id]") or sqlerr(__FILE__, __LINE__);
		//===end

		$ret = sql_query("INSERT INTO offers (userid, name, descr, category, added) VALUES (" .
		implode(",", array_map("sqlesc", array($CURUSER["id"], $name, $descr, 0 + $_POST["type"]))) .
		", '" . date("Y-m-d H:i:s") . "')");
		if (!$ret) {
			if (mysql_errno() == 1062)
			bark("!!!");
			bark("mysql puked: "._mysql_error());
		}
		$id = _mysql_insert_id();

		write_log("offer $name was added by ".$CURUSER['username'],'normal');

		header("Location: offers.php?id=$id&off_details=1");
		die;
		stdhead($lang_offers['head_success']);
	}
	else{
		stderr ($lang_offers['std_error'], $lang_offers['std_offer_exists']."<a class=altlink href=offers.php>".$lang_offers['text_view_all_offers']."</a>",false);
	}
	stdfoot();
	die;
}
//==end take new offer

//=== offer details
if ($_GET["off_details"]){

	$off_details = 0 + $_GET["off_details"];
	if($off_details != '1')
	stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);

	$id = 0+$_GET["id"];
	if(!$id)
		die();
		//stderr("Error", "I smell a rat!");
	
	$res = sql_query("SELECT * FROM offers WHERE id = $id") or sqlerr(__FILE__,__LINE__);
	$num = _mysql_fetch_array($res);

	if (!is_null($num['torrent_id'])) {
	  if (!$_REQUEST['noredir']) {
	    header('Location: details.php?id=' . $num['torrent_id'] , true, 301);
	  }
	  else {
	    $torrent = sql_query('SELECT id, name FROM torrents WHERE id = ?', [$num['torrent_id']])->fetch();
	  }
	}

	$s = $num["name"];

	stdhead($lang_offers['head_offer_detail_for']." \"".$s."\"");
	print("<h1 id=\"top\">".htmlspecialchars($s)."</h1>");

	print("<table width=\"940\" cellspacing=\"0\" cellpadding=\"5\">");
	$offertime = gettime($num['added'],true,false);
		$offertime = $lang_offers['text_at'].$offertime;
	tr($lang_offers['row_info'], $lang_offers['text_offered_by'].get_username($num['userid']).$offertime, 1);
	if ($num["allowed"] == "pending")
	$status="<span color=\"offer-pending\">".$lang_offers['text_pending']."</span>";
	elseif ($num["allowed"] == "allowed") {
	  if (is_null($num['torrent_id'])) {
	    $status="<span class=\"offer-allowed\">".$lang_offers['text_allowed']."</span>";
	  }
	  else {
	    $status="<span class=\"offer-uploaded\">已发布</span>";
	  }
	}
	elseif ($num["allowed"] == "frozen")
	$status='<span class="offer-frozen">冻结</span>';
	else
	$status="<span class=\"offer-denied\">".$lang_offers['text_denied']."</span>";
	tr($lang_offers['row_status'], $status, 1);
//=== if you want to have a pending thing for uploaders use this next bit
	/* if (get_user_class() >= $offermanage_class && $num["allowed"] == "pending") */
	/* tr($lang_offers['row_allow'], "<table><tr><td class=\"embedded\"><form method=\"post\" action=\"?allow_offer=1\"><input type=\"hidden\" value=\"".$id."\" name=\"offerid\" />". */
	/* "<input class=\"btn\" type=\"submit\" value=\"".$lang_offers['submit_allow']."\" />&nbsp;&nbsp;</form></td><td class=\"embedded\"><form method=\"post\" action=\"?id=".$id."&amp;finish_offer=1\">". */
	/* "<input type=\"hidden\" value=\"".$id."\" name=\"finish\" /><input class=\"btn\" type=\"submit\" value=\"".$lang_offers['submit_let_votes_decide']."\" /></form></td></tr></table>", 1); */

	$za = get_row_count('offervotes', "where vote='yeah' and offerid=$id");
	$protiv = get_row_count('offervotes', "where vote='against' and offerid=$id");
	//=== in the following section, there is a line to report comment... either remove the link or change it to work with your report script :)

	//if pending
	$voted = get_row_count('offervotes', "WHERE offerid=".sqlesc($id)." AND userid=".sqlesc($CURUSER["id"]));
	if ($num["allowed"] == "pending" && $CURUSER['id'] != $num['userid'] && !$voted){
		tr($lang_offers['row_vote'],'<form class="a" action="offers.php" method="post"><input type="submit" name="votebutton "value="'.$lang_offers['text_for'].'"/><input type="hidden" name="vote" value="yeah"/><input type="hidden" name="id" value="'.$id.'"/></form>'.
		(get_user_class() >= $againstoffer_class ?'<form class="a" action="offers.php" method="post"><input type="submit" name="votebutton "value="'.$lang_offers['text_against'].'"/><input type="hidden" name="vote" value="against"/><input type="hidden" name="id" value="'.$id.'"/>':'').'</form>',1);
		tr($lang_offers['row_vote_results'], 
	"<b>".$lang_offers['text_for'].":</b> $za  <b>".$lang_offers['text_against']."</b> $protiv &nbsp; &nbsp; <a href=\"?id=".$id."&amp;offer_vote=1\"><i>".$lang_offers['text_see_vote_detail']."</i></a>", 1);
	}
	//===upload torrent message
	if ($num["allowed"] == "allowed") {
	  if (is_null($num['torrent_id'])) {
	  if ($CURUSER["id"] != $num["userid"]) {
	    $msg = $lang_offers['text_voter_receives_pm_note'];
	  }
	  else {
	    $msg = $lang_offers['text_urge_upload_offer_note'];
	  }
	  }
	  else {
	    if (isset($torrent)) {
	      $msg = '<a href="details.php?id=' . $torrent['id'] . '">' . $torrent['name'] . '</a>';
	    }
	    else {
	      $msg = '不过，种子好像不见了:(';
	    }
	  }

	  tr($lang_offers['row_offer_allowed'],$msg, 1);
	}
	if ($CURUSER['id'] == $num['userid'] || get_user_class() >= $offermanage_class){
		$edit = "<a href=\"?id=".$id."&amp;edit_offer=1\"><img class=\"dt_edit\" src=\"pic/trans.gif\" alt=\"edit\" />&nbsp;<b><font class=\"small\">".$lang_offers['text_edit_offer'] . "</font></b></a>&nbsp;|&nbsp;";
		$delete = "<a href=\"?id=".$id."&amp;del_offer=1&amp;sure=0\"><img class=\"dt_delete\" src=\"pic/trans.gif\" alt=\"delete\" />&nbsp;<b><font class=\"small\">".$lang_offers['text_delete_offer']."</font></b></a>&nbsp;|&nbsp;";
	}
	else {
	  $delete = '';
	  $edit = '';
	}

	$freeze = '';
	if (get_user_class() >= $offermanage_class) {
	  if ($num['allowed'] == 'pending') {
	    $freeze = ' | <form class="a" action="offers.php?id=' . $id.'" method="post"><input class="a" type="submit" value="冻结"/><input type="hidden" name="freeze" value="1"/></form>';
	  }
	  else if ($num['allowed'] == 'frozen') {
	    $freeze = ' | <form class="a" action="offers.php?id=' . $id.'" method="post"><input class="a" type="submit" value="解冻"/><input type="hidden" name="freeze" value="0"/></form>';
	  }
	}
	
	$report = "<a href=\"report.php?reportofferid=".$id."\"><img class=\"dt_report\" src=\"pic/trans.gif\" alt=\"report\" />&nbsp;<b><font class=\"small\">".$lang_offers['report_offer']."</font></b></a>";
	tr($lang_offers['row_action'], $edit . $delete .$report . $freeze, 1);
	if ($num["descr"]){
		$off_bb = format_comment($num["descr"]);
		tr($lang_offers['row_description'], $off_bb, 1);
	}
	print("</table>");
	// -----------------COMMENT SECTION ---------------------//
	$commentbar = "<p align=\"center\"><a class=\"index\" href=\"comment.php?action=add&amp;pid=".$id."&amp;type=offer\">".$lang_offers['text_add_comment']."</a></p>\n";
	$subres = sql_query("SELECT COUNT(*) FROM comments WHERE offer = $id");
	$subrow = _mysql_fetch_array($subres);
	$count = $subrow[0];
	if (!$count) {
		print("<h1 id=\"startcomments\" align=\"center\">".$lang_offers['text_no_comments']."</h1>\n");
	}

	else {
	  list($pagertop, $pagerbottom, $limit, $next_page ,$offset) = pager(10, $count, "offers.php?id=$id&off_details=1&", array('lastpagedefault' => 1));

		$subres = sql_query("SELECT id, text, user, added, editedby, editnotseen,editdate FROM comments  WHERE offer = " . sqlesc($id) . " ORDER BY id $limit") or sqlerr(__FILE__, __LINE__);
		$allrows = array();
		while ($subrow = _mysql_fetch_array($subres))
		$allrows[] = $subrow;

		//end_frame();
		//print($commentbar);
		print($pagertop);

		commenttable($allrows,"offer",$id, false, $offset);		
		print($pagerbottom);
	}
	print('<div class="table td" id="forum-reply-post"><h2>'.$lang_offers['text_quick_comment']."</h2>".
"<form id=\"compose\" name=\"comment\" method=\"post\" action=\"comment.php?action=add&amp;type=offer\" onsubmit=\"return postvalid(this);\">".
"<input type=\"hidden\" name=\"pid\" value=\"".$id."\" /><br />");
	quickreply('comment', 'body',$lang_offers['submit_add_comment']);
	print("</form></div>");
	print($commentbar);
	stdfoot();
	die;
}
//=== end offer details
//=== allow offer by staff
if ($_GET["allow_offer"]) {

	if (get_user_class() < $offermanage_class)
	stderr($lang_offers['std_access_denied'], $lang_offers['std_mans_job']);

	$allow_offer = 0 + $_GET["allow_offer"];
	if($allow_offer != '1')
	stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);

	//=== to allow the offer  credit to S4NE for this next bit :)
	//if ($_POST["offerid"]){
	$offid = 0 + $_POST["offerid"];
	if(!is_valid_id($offid))
	stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);

	$res = sql_query("SELECT users.username, offers.userid, offers.name FROM offers inner join users on offers.userid = users.id where offers.id = $offid") or sqlerr(__FILE__,__LINE__);
	$arr = _mysql_fetch_assoc($res);
	if ($offeruptimeout_main){
		$timeouthour = floor($offeruptimeout_main/3600);
		$timeoutnote = $lang_offers_target[get_user_lang($arr["userid"])]['msg_you_must_upload_in'].$timeouthour.$lang_offers_target[get_user_lang($arr["userid"])]['msg_hours_otherwise'];
	}
	else $timeoutnote = "";
	$msg = "$CURUSER[username]".$lang_offers_target[get_user_lang($arr["userid"])]['msg_has_allowed']."[b][url=". get_protocol_prefix() . $BASEURL ."/offers.php?id=$offid&off_details=1]" . $arr['name'] . "[/url][/b]. ".$lang_offers_target[get_user_lang($arr["userid"])]['msg_find_offer_option'].$timeoutnote;

	$subject = $lang_offers_target[get_user_lang($arr["userid"])]['msg_your_offer_allowed'];
	$allowedtime = date("Y-m-d H:i:s");
	send_pm(0, $arr['userid'], $subject, $msg);
	sql_query ("UPDATE offers SET allowed = 'allowed', allowedtime = '".$allowedtime."' WHERE id = $offid") or sqlerr(__FILE__,__LINE__);

	write_log("$CURUSER[username] allowed offer $arr[name]",'normal');
	header("Refresh: 0; url=" . get_protocol_prefix() . "$BASEURL/offers.php");
}
//=== end allow the offer

//=== allow offer by vote
/*
if ($_GET["finish_offer"]) {

	if (get_user_class() < $offermanage_class)
	stderr($lang_offers['std_access_denied'], $lang_offers['std_have_no_permission']);

	$finish_offer = 0 + $_GET["finish_offer"];
	if($finish_offer != '1')
	stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);

	$offid = 0 + $_POST["finish"];
	if(!is_valid_id($offid))
		stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);

	$res = sql_query("SELECT users.username, offers.userid, offers.name FROM offers inner join users on offers.userid = users.id where offers.id = $offid") or sqlerr(__FILE__,__LINE__);
	$arr = _mysql_fetch_assoc($res);

	$voteresyes = sql_query("SELECT COUNT(*) from offervotes where vote='yeah' and offerid=$offid");
	$arryes = _mysql_fetch_row($voteresyes);
	$yes = $arryes[0];
	$voteresno = sql_query("SELECT COUNT(*) from offervotes where vote='against' and offerid=$offid");
	$arrno = _mysql_fetch_row($voteresno);
	$no = $arrno[0];

	if($yes == '0' && $no == '0')
	stderr($lang_offers['std_sorry'], $lang_offers['std_no_votes_yet']."<a  href=offers.php>".$lang_offers['std_back_to_offer_detail']."</a>",false);
	$finishvotetime = date("Y-m-d H:i:s");
	if (($yes - $no)>=$minoffervotes){
		if ($offeruptimeout_main){
			$timeouthour = floor($offeruptimeout_main/3600);
			$timeoutnote = $lang_offers_target[get_user_lang($arr["userid"])]['msg_you_must_upload_in'].$timeouthour.$lang_offers_target[get_user_lang($arr["userid"])]['msg_hours_otherwise'];
		}
		else $timeoutnote = "";
		$msg = $lang_offers_target[get_user_lang($arr["userid"])]['msg_offer_voted_on']."[b][url=" . get_protocol_prefix() . $BASEURL."/offers.php?id=$offid&off_details=1]" . $arr['name'] . "[/url][/b].". $lang_offers_target[get_user_lang($arr["userid"])]['msg_find_offer_option'].$timeoutnote;
		sql_query ("UPDATE offers SET allowed = 'allowed',allowedtime ='".$finishvotetime."' WHERE id = $offid") or sqlerr(__FILE__,__LINE__);
	}
	else if(($no - $yes)>=$minoffervotes){
		$msg = $lang_offers_target[get_user_lang($arr["userid"])]['msg_offer_voted_off']."[b][url=". get_protocol_prefix() . $BASEURL."/offers.php?id=$offid&off_details=1]" . $arr['name'] . "[/url][/b].".$lang_offers_target[get_user_lang($arr["userid"])]['msg_offer_deleted'] ;
		sql_query ("UPDATE offers SET allowed = 'denied' WHERE id = $offid") or sqlerr(__FILE__,__LINE__);
	}

	$subject = $lang_offers_target[get_user_lang($arr['userid'])]['msg_your_offer'].$arr['name'].$lang_offers_target[get_user_lang($arr['userid'])]['msg_voted_on'];
	send_pm(0, $arr['userid'], $subject, $msg);
	write_log("$CURUSER[username] closed poll $arr[name]",'normal');

	header("Refresh: 0; url=" . get_protocol_prefix() . "$BASEURL/offers.php");
	die;
}
*/
//===end allow offer by vote

//=== edit offer

if ($_GET["edit_offer"]) {

	$edit_offer = 0 + $_GET["edit_offer"];
	if($edit_offer != '1')
	stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);

	$id = 0 + $_GET["id"];

	$res = sql_query("SELECT * FROM offers WHERE id = $id") or sqlerr(__FILE__, __LINE__);
	$num = _mysql_fetch_array($res);

	$timezone = $num["added"];

	$s = $num["name"];
	$id2 = $num["category"];

	if ($CURUSER["id"] != $num["userid"] && get_user_class() < $offermanage_class)
	stderr($lang_offers['std_error'], $lang_offers['std_cannot_edit_others_offer']);

	$body = htmlspecialchars(unesc($num["descr"]));
	$s2 = "<select name=\"category\">\n";

	$cats = genrelist($browsecatmode);

	foreach ($cats as $row)
	$s2 .= "<option value=\"" . $row["id"] . "\" ".($row['id'] == $id2 ? " selected=\"selected\"" : "").">" . htmlspecialchars($row["name"]) . "</option>\n";
	$s2 .= "</select>\n";

	stdhead($lang_offers['head_edit_offer'].": $s");
	$title = htmlspecialchars(trim($s));
	
	print("<form id=\"compose\" method=\"post\" name=\"compose\" action=\"?id=".$id."&amp;take_off_edit=1\">".
	"<table width=\"940\" cellspacing=\"0\" cellpadding=\"3\"><tr><td class=\"colhead\" align=\"center\" colspan=\"2\">".$lang_offers['text_edit_offer']."</td></tr>");
	tr($lang_offers['row_type']."<font color=\"red\">*</font>", $s2, 1);
	tr($lang_offers['row_title']."<font color=\"red\">*</font>", "<input type=\"text\" style=\"width: 650px\" name=\"name\" value=\"".$title."\" />", 1);
	tr($lang_offers['row_post_or_photo'], "<input type=\"text\" name=\"picture\" style=\"width: 650px\" value='' /><br />".$lang_offers['text_link_to_picture'], 1);
	print("<tr><td class=\"rowhead\" align=\"right\" valign=\"top\"><b>".$lang_offers['row_description']."<font color=\"red\">*</font></b></td><td class=\"rowfollow\" align=\"left\">");
	textbbcode("compose","body",$body,false);
	print("</td></tr>");
	print("<tr><td class=\"toolbox\" style=\"vertical-align: middle; padding-top: 10px; padding-bottom: 10px;\" align=\"center\" colspan=\"2\"><input id=\"qr\" type=\"submit\" value=\"".$lang_offers['submit_edit_offer']."\" class=\"btn\" /></td></tr></table></form><br />\n");
	stdfoot();
	die;
}
//=== end edit offer

//==== take offer edit
if ($_GET["take_off_edit"]){

	$take_off_edit = 0 + $_GET["take_off_edit"];
	if($take_off_edit != '1')
	stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);

	$id = 0 + $_GET["id"];

	$res = sql_query("SELECT userid FROM offers WHERE id = $id") or sqlerr(__FILE__, __LINE__);
	$num = _mysql_fetch_array($res);

	if ($CURUSER['id'] != $num['userid'] && get_user_class() < $offermanage_class)
	stderr($lang_offers['std_error'], $lang_offers['std_access_denied']);

	$name = $_POST["name"];

	if (!empty($_POST['picture'])){
		$picture = unesc($_POST["picture"]);
		if(!preg_match("/^http:\/\/[^\s'\"<>]+\.(jpg|gif|png)$/i", $picture))
		stderr($lang_offers['std_error'], $lang_offers['std_wrong_image_format']);
		$pic = "[img]".$picture."[/img]\n";
		$descr = $pic;
	}
	else {
	  $descr = '';
	}
	$descr .= unesc($_POST["body"]);
	if (!$name)
	bark($lang_offers['std_must_enter_name']);
	if (!$descr)
	bark($lang_offers['std_must_enter_description']);
	$cat = (0 + $_POST["category"]);
	if (!is_valid_id($cat))
	bark($lang_offers['std_must_select_category']);

	$name = sqlesc($name);
	$descr = sqlesc($descr);
	$cat = sqlesc($cat);

	sql_query("UPDATE offers SET category=$cat, name=$name, descr=$descr where id=".sqlesc($id));

	//header("Refresh: 0; url=offers.php?id=$id&off_details=1");
}
//======end take offer edit

//=== offer votes list
if ($_GET["offer_vote"]){

	$offer_vote = 0 + $_GET["offer_vote"];
	if($offer_vote != '1')
	stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);

	$offerid = 0 + htmlspecialchars($_GET['id']);

	$res2 = sql_query("SELECT COUNT(*) FROM offervotes WHERE offerid = ".sqlesc($offerid)) or sqlerr(__FILE__, __LINE__);
	$row = _mysql_fetch_array($res2);
	$count = $row[0];

	$offername = get_single_value("offers","name","WHERE id=".sqlesc($offerid));
	stdhead($lang_offers['head_offer_voters']." - \"".$offername."\"");

	print("<h1 align=center>".$lang_offers['text_vote_results_for']." <a  href=offers.php?id=$offerid&off_details=1><b>".htmlspecialchars($offername)."</b></a></h1>");

	$perpage = 25;
	list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, $_SERVER["PHP_SELF"] ."?id=".$offerid."&offer_vote=1&");
	$res = sql_query("SELECT * FROM offervotes WHERE offerid=".sqlesc($offerid)." ".$limit) or sqlerr(__FILE__, __LINE__);

	if (_mysql_num_rows($res) == 0)
	print("<p align=center><b>".$lang_offers['std_no_votes_yet']."</b></p>\n");
	else
	{
		echo $pagertop;
		print("<table border=1 cellspacing=0 cellpadding=5><tr><td class=colhead>".$lang_offers['col_user']."</td><td class=colhead align=left>".$lang_offers['col_vote']."</td>\n");

		while ($arr = _mysql_fetch_assoc($res))
		{
			if ($arr['vote'] == 'yeah')
				$vote = "<b><font color=green>".$lang_offers['text_for']."</font></b>";
			elseif ($arr['vote'] == 'against')
				$vote = "<b><font color=red>".$lang_offers['text_against']."</font></b>";
			else $vote = "unknown";

			print("<tr><td class=rowfollow>" . get_username($arr['userid']) . "</td><td class=rowfollow align=left >".$vote."</td></tr>\n");
		}
		print("</table>\n");
		echo $pagerbottom;
	}

	stdfoot();
	die;
}
//=== end offer votes list

//=== offer votes
if ($_POST["vote"]) {
	$offerid = 0 + htmlspecialchars($_POST["id"]);
	$vote = htmlspecialchars($_POST["vote"]);
	if ($vote == 'against' && get_user_class() < $againstoffer_class)
		stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
	if ($vote =='yeah' || $vote =='against')
	{
		$userid = 0+$CURUSER["id"];
		$voted = get_row_count('offervotes', "WHERE offerid=".sqlesc($offerid)." AND userid=".sqlesc($userid));
		if (get_single_value('offers', 'allowed', 'WHERE id='.$offerid) != 'pending') {
		  stderr('不能给不在候选状态的投票', $lang_offers['std_cannot_vote_youself']);
		}

		$offer_userid = get_single_value("offers", "userid", "WHERE id=".sqlesc($offerid));
		if ($offer_userid == $CURUSER['id'])
		{
			stderr($lang_offers['std_error'], $lang_offers['std_cannot_vote_youself']);
		}
		elseif ($voted)
		{
			stderr($lang_offers['std_already_voted'],$lang_offers['std_already_voted_note']."<a  href=offers.php>".$lang_offers['std_back_to_offer_detail'] ,false);
		}
		else
		{
			sql_query("UPDATE offers SET $vote = $vote + 1 WHERE id=".sqlesc($offerid)) or sqlerr(__FILE__,__LINE__);

			$res = sql_query("SELECT users.username, offers.userid, offers.name FROM offers LEFT JOIN users ON offers.userid = users.id WHERE offers.id = ".sqlesc($offerid)) or sqlerr(__FILE__,__LINE__);
			$arr = _mysql_fetch_assoc($res);

			$rs = sql_query("SELECT yeah, against, allowed FROM offers WHERE id=".sqlesc($offerid)) or sqlerr(__FILE__,__LINE__);
			$ya_arr = _mysql_fetch_assoc($rs);
			$yeah = $ya_arr["yeah"];
			$against = $ya_arr["against"];
			$finishtime = date("Y-m-d H:i:s");
			//allowed and send offer voted on message
			if(($yeah-$against)>=$minoffervotes && $ya_arr['allowed'] != "allowed")
			{
				if ($offeruptimeout_main){
					$timeouthour = floor($offeruptimeout_main/3600);
					$timeoutnote = $lang_offers_target[get_user_lang($arr["userid"])]['msg_you_must_upload_in'].$timeouthour.$lang_offers_target[get_user_lang($arr["userid"])]['msg_hours_otherwise'];
				}
				else $timeoutnote = "";
				sql_query("UPDATE offers SET allowed='allowed', allowedtime=".sqlesc($finishtime)." WHERE id=".sqlesc($offerid)) or sqlerr(__FILE__,__LINE__);
				$msg = $lang_offers_target[get_user_lang($arr['userid'])]['msg_offer_voted_on']."[b][url=". get_protocol_prefix() . $BASEURL."/offers.php?id=$offerid&off_details=1]" . $arr['name'] . "[/url][/b].". $lang_offers_target[get_user_lang($arr['userid'])]['msg_find_offer_option'].$timeoutnote;
				$subject = $lang_offers_target[get_user_lang($arr['userid'])]['msg_your_offer_allowed'];
				send_pm(0, $arr['userid'], $subject, $msg);
				write_log("System allowed offer $arr[name]",'normal');
			}
			//denied and send offer voted off message
			if(($against-$yeah)>=$minoffervotes && $ya_arr['allowed'] != "denied")
			{
				sql_query("UPDATE offers SET allowed='denied' WHERE id=".sqlesc($offerid)) or sqlerr(__FILE__,__LINE__);
				$msg = $lang_offers_target[get_user_lang($arr['userid'])]['msg_offer_voted_off']."[b][url=" . get_protocol_prefix() . $BASEURL."/offers.php?id=$offid&off_details=1]" . $arr['name'] . "[/url][/b].".$lang_offers_target[get_user_lang($arr['userid'])]['msg_offer_deleted'] ;
				$subject = $lang_offers_target[get_user_lang($arr['userid'])]['msg_offer_deleted'];
				send_pm(0, $arr['userid'], $subject, $msg);
				write_log("System denied offer $arr[name]",'normal');
			}


			sql_query("INSERT INTO offervotes (offerid, userid, vote) VALUES($offerid, $userid, ".sqlesc($vote).")") or sqlerr(__FILE__,__LINE__);
			KPS("+",$offervote_bonus,$CURUSER["id"]);
			Header("Location:offers.php?off_details=1&id=" . $offerid);
			die;
		}
	}
	else
	stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
}
//=== end offer votes

//=== delete offer
if ($_GET["del_offer"]){
	$del_offer = 0 + $_GET["del_offer"];
	if($del_offer != '1')
	stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);

	$offer = 0 + $_GET["id"];

	$userid = 0 + $CURUSER["id"];
	if (!is_valid_id($userid))
	stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);

	$res = sql_query("SELECT * FROM offers WHERE id = $offer") or sqlerr(__FILE__, __LINE__);
	$num = _mysql_fetch_array($res);

	$name = $num["name"];

	if ($userid != $num["userid"] && get_user_class() < $offermanage_class)
	stderr($lang_offers['std_error'], $lang_offers['std_cannot_delete_others_offer']);

	if (isset($_GET["sure"])) {
		$sure = $_GET["sure"];
		if($sure == '0' || $sure == '1')
		$sure = 0 + $_GET["sure"];
		else
		stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
	}
	else {
	  $sure = 0;
	}


	if ($sure == 0)
	stderr($lang_offers['std_delete_offer'], $lang_offers['std_delete_offer_note']."<br /><form method=post action=offers.php?id=$offer&del_offer=1&sure=1>".$lang_offers['text_reason_is']."<input type=text style=\"width: 200px\" name=reason><input type=submit value=\"".$lang_offers['submit_confirm']."\"></form>",false);
	elseif ($sure == 1)
	{
	    checkHTTPMethod('post');

		$reason = $_REQUEST["reason"];
		sql_query("DELETE FROM offers WHERE id=$offer");
		sql_query("DELETE FROM offervotes WHERE offerid=$offer");
		sql_query("DELETE FROM comments WHERE offer=$offer");

		//===add karma	//=== use this if you use the karma mod
		//sql_query("UPDATE LOW_PRIORITY users SET seedbonus = seedbonus-10.0 WHERE id = $num[userid]") or sqlerr(__FILE__, __LINE__);
		//===end

		if ($CURUSER["id"] != $num["userid"])
		{
			$subject = ($lang_offers_target[get_user_lang($num["userid"])]['msg_offer_deleted']);
			$msg = ($lang_offers_target[get_user_lang($num["userid"])]['msg_your_offer'].$num['name'].$lang_offers_target[get_user_lang($num["userid"])]['msg_was_deleted_by']. "[user=".$CURUSER['id']."]".$lang_offers_target[get_user_lang($num["userid"])]['msg_blank'].($reason != "" ? $lang_offers_target[get_user_lang($num["userid"])]['msg_reason_is'].$reason : ""));
			send_pm(0, $num['userid'], $subject, $msg);
		}
		write_log("Offer: $offer ($num[name]) was deleted by $CURUSER[username]".($reason != "" ? " (".$reason.")" : ""),'normal');
		header("Refresh: 0; url=offers.php");
		die;
	}
	else
	stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
}
//== end  delete offer

if (isset($_REQUEST['freeze'])) {
  checkHTTPMethod('post');
  $offerid = $_REQUEST['id'];
  $res = sql_query("SELECT allowed, name FROM offers WHERE id = $offerid") or sqlerr(__FILE__,__LINE__);
  $num = _mysql_fetch_array($res);
  if (get_user_class() >= $offermanage_class && ($num['allowed'] == 'frozen' || $num['allowed'] = 'pending')) {
    if ($_REQUEST['freeze']) {
      $allowed = 'frozen';
      $log = 'frozen';
    }
    else {
      $allowed = 'pending';
      $log = 'unfrozen';
    }
    sql_query("UPDATE offers SET allowed='$allowed' WHERE id=".sqlesc($id)) or sqlerr(__FILE__,__LINE__);
    write_log("Offer: $offerid ($num[name]) was $log by $CURUSER[username]",'normal');
    header("Refresh: 0; url=offers.php?id=$id&off_details=1");
    die;
  }
}

//=== prolly not needed, but what the hell... basically stopping the page getting screwed up
if ($_GET["sort"])
{
	$sort = $_GET["sort"];
	if($sort == 'cat' || $sort == 'name' || $sort == 'added' || $sort == 'comments' || $sort == 'yeah' || $sort == 'against' || $sort == 'v_res')
	$sort = $_GET["sort"];
	else
	stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
}
else {
  $sort = '';
}
//=== end of prolly not needed, but what the hell :P

$categ = 0 + $_GET["category"];

if ($_GET["offerorid"]){
	$offerorid = 0 + htmlspecialchars($_GET["offerorid"]);
	if (preg_match("/^[0-9]+$/", !$offerorid))
	stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
}

$search = ($_GET["search"]);

if ($search) {
	$search = " AND offers.name like ".sqlesc("%$search%");
} else {
	$search = "";
}


$cat_order_type = "desc";
$name_order_type = "desc";
$added_order_type = "desc";
$comments_order_type = "desc";
$v_res_order_type = "desc";

/*
if ($cat_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $cat_order_type = "asc"; } // for torrent name
if ($name_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $name_order_type = "desc"; }
if ($added_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $added_order_type = "desc"; }
if ($comments_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $comments_order_type = "desc"; }
if ($v_res_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $v_res_order_type = "desc"; }
*/

if ($sort == "cat")
{
	if ($_GET['type'] == "desc")
		$cat_order_type = "asc";
	$sort = " ORDER BY category ". $cat_order_type;
}
else if ($sort == "name")
{
	if ($_GET['type'] == "desc")
		$name_order_type = "asc";
	$sort = " ORDER BY name ". $name_order_type;
}
else if ($sort == "added")
{
	if ($_GET['type'] == "desc")
		$added_order_type = "asc";
	$sort = " ORDER BY added " . $added_order_type;
}
else if ($sort == "comments")
{
	if ($_GET['type'] == "desc")
		$comments_order_type = "asc";
	$sort = " ORDER BY comments " . $comments_order_type;
}
else if ($sort == "v_res")
{
	if ($_GET['type'] == "desc")
		$v_res_order_type = "asc";
	// Avoid error: Numeric value out of range
	sql_query('SET sql_mode="NO_UNSIGNED_SUBTRACTION"'); 
	$sort = " ORDER BY (yeah - against) " . $v_res_order_type;
}




if (isset($offerorid))
{
	if (($categ <> NULL) && ($categ <> 0))
	$categ = "WHERE offers.category = " . $categ . " AND offers.userid = " . $offerorid;
	else
	$categ = "WHERE offers.userid = " . $offerorid;
}

else if ($categ == 0)
$categ = '';
else
$categ = "WHERE offers.category = " . $categ;

if (isset($_REQUEST['published'])) {
  $published = '';
}
else {
  $published = 'AND ISNULL(offers.torrent_id)';
}

$res = sql_query("SELECT count(offers.id) FROM offers inner join categories on offers.category = categories.id inner join users on offers.userid = users.id $published $categ $search") or sqlerr(__FILE__, __LINE__);
$row = _mysql_fetch_array($res);
$count = $row[0];
$perpage = 25;

list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, $_SERVER["PHP_SELF"] ."?category=" . $_GET["category"] . "&sort=" . $_GET["sort"] . (isset($_REQUEST['published'])? "&published&" : '&') );

//stderr("", $sort);
if($sort == "")
$sort =  "ORDER BY added desc ";

$res = sql_query("SELECT offers.id, offers.userid, offers.name, offers.added, offers.allowedtime, offers.comments, offers.yeah, offers.against, offers.torrent_id, offers.category as cat_id, offers.allowed, categories.name as cat FROM offers INNER JOIN categories ON offers.category = categories.id $published $categ $search $sort $limit") or sqlerr(__FILE__,__LINE__);
$num = _mysql_num_rows($res);

stdhead($lang_offers['head_offers']);

print('<h1>' . $lang_offers['text_offers_section'] . '</h1>');
print('<div class="table td" style="margin-bottom:2em;padding:10px;">');
if (get_user_class() >= $addoffer_class) {
  print("<h2 class=\"center\"><a href=\"?add_offer=1\">".$lang_offers['text_add_offer']."</a></h2>");
}
print("<h2>".$lang_offers['text_rules']."</h2>\n");
print("<div align=\"left\"><ul>");
$rule_args = array([], 
		   array(get_user_class_name($upload_class, false, true, true), get_user_class_name($addoffer_class, false, true, true)),
		   array($minoffervotes),[],[],[],[]
		   );


if ($offervotetimeout_main) {
  $rule_args[3] = array($offervotetimeout_main / 3600);
}
if ($offeruptimeout_main) {
  $rule_args[4] = array($offeruptimeout_main / 3600);
}

foreach ($rule_args as $k => $v) {
  array_unshift($v, $lang_offers['text_rules_p'][$k]);
  echo '<li>', call_user_func_array('sprintf', $v), '</li>';
}

print("</ul></div>");
print("<div class=\"center\"><form method=\"get\" action=\"?\">".$lang_offers['text_search_offers']."&nbsp;&nbsp;<input type=\"search\" id=\"specialboxg\" name=\"search\" />&nbsp;&nbsp;");
$cats = genrelist($browsecatmode);
$catdropdown = "";
foreach ($cats as $cat) {
	$catdropdown .= "<option value=\"" . $cat["id"] . "\"";
	$catdropdown .= ">" . htmlspecialchars($cat["name"]) . "</option>\n";
}
print("<select name=\"category\"><option value=\"0\">".$lang_offers['select_show_all']."</option>".$catdropdown."</select>&nbsp;&nbsp;<input type=\"submit\" class=\"btn\" value=\"".$lang_offers['submit_search']."\" /></form><a href=\"?published=1\">显示已发布的</a></div>");

print('</div>');

$last_offer = strtotime($CURUSER['last_offer']);
if (!$num)
	stdmsg($lang_offers['text_nothing_found'],$lang_offers['text_nothing_found']);
else
{
	$catid = $_GET['category'];
	print("<table class=\"torrents\" cellspacing=\"0\" cellpadding=\"5\" width=\"100%\">");
	print("<tr><td class=\"colhead\" style=\"padding: 0px\"><a href=\"?category=" . $catid . "&amp;sort=cat&amp;type=".$cat_order_type."\">".$lang_offers['col_type']."</a></td>".
"<td class=\"colhead\" width=\"100%\"><a href=\"?category=" . $catid . "&amp;sort=name&amp;type=".$name_order_type."\">".$lang_offers['col_title']."</a></td>".
"<td colspan=\"1\" class=\"colhead\"><a href=\"?category=" . $catid . "&amp;sort=v_res&amp;type=".$v_res_order_type."\">".$lang_offers['col_vote_results']."</a></td>".
"<td class=\"colhead\"><a href=\"?category=" . $catid . "&amp;sort=comments&amp;type=".$comments_order_type."\"><img class=\"comments\" src=\"pic/trans.gif\" alt=\"comments\" title=\"".$lang_offers['title_comment']."\" />".$lang_offers['col_comment']."</a></td>".
"<td class=\"colhead\"><a href=\"?category=" . $catid . "&amp;sort=added&amp;type=".$added_order_type."\"><img class=\"time\" src=\"pic/trans.gif\" alt=\"time\" title=\"".$lang_offers['title_time_added']."\" /></a></td>");
if ($offervotetimeout_main > 0 && $offeruptimeout_main > 0)
	print("<td class=\"colhead\">".$lang_offers['col_timeout']."</td>");
print("<td class=\"colhead\">".$lang_offers['col_offered_by']."</td>".
(get_user_class() >= $offermanage_class ? "<td class=\"colhead\">".$lang_offers['col_act']."</td>" : "")."</tr>\n");
$lastcom_tooltip = [];
	for ($i = 0; $i < $num; ++$i)
	{
	$arr = _mysql_fetch_assoc($res);


	$addedby = get_username($arr['userid']);
	$comms = $arr['comments'];
	if ($comms == 0)
		$comment = "<a href=\"comment.php?action=add&amp;pid=".$arr['id']."&amp;type=offer\" title=\"".$lang_offers['title_add_comments']."\">0</a>";
	else
	{
		if (!$lastcom = $Cache->get_value('offer_'.$arr['id'].'_last_comment_content')){
			$res2 = sql_query("SELECT user, added, text FROM comments WHERE offer = $arr[id] ORDER BY added DESC LIMIT 1");
			$lastcom = _mysql_fetch_array($res2);
			$Cache->cache_value('offer_'.$arr['id'].'_last_comment_content', $lastcom, 1855);
		}
		$timestamp = strtotime($lastcom["added"]);
		$hasnewcom = ($lastcom['user'] != $CURUSER['id'] && $timestamp >= $last_offer);

			if ($lastcom)
			{
				$title = "";
					$lastcomtime = $lang_offers['text_at_time'].$lastcom['added'];
					$counter = $i;
					$lastcom_tooltip[$counter]['id'] = "lastcom_" . $counter;
					$lastcom_tooltip[$counter]['content'] = ($hasnewcom ? "<b>(<font class='new'>".$lang_offers['text_new']."</font>)</b> " : "").$lang_offers['text_last_commented_by'].get_username($lastcom['user']) . $lastcomtime."<br />". format_comment(mb_substr($lastcom['text'],0,100,"UTF-8") . (mb_strlen($lastcom['text'],"UTF-8") > 100 ? " ......" : "" ),true,false,false,true,600,false,false);
					$onmouseover = "onmouseover=\"domTT_activate(this, event, 'content', document.getElementById('" . $lastcom_tooltip[$counter]['id'] . "'), 'trail', false, 'delay', 500,'lifetime',3000,'fade','both','styleClass','niceTitle','fadeMax', 87,'maxWidth', 400);\"";
			}	
		else
		{
			$title = " title=\"".($hasnewcom ? $lang_offers['title_has_new_comment'] : $lang_offers['title_no_new_comment'])."\"";
			$onmouseover = "";
		}
		$comment = "<b><a".$title." href=\"?id=".$arr['id']."&amp;off_details=1#startcomments\" ".$onmouseover.">".($hasnewcom ? "<font class='new'>" : ""). $comms .($hasnewcom ? "</font>" : "")."</a></b>";
	}

	//==== if you want allow deny for offers use this next bit
	if ($arr["allowed"] == 'allowed') {
	  if (is_null($arr['torrent_id'])) {
	    $allowed = "&nbsp;[<span class=\"offer-allowed\">".$lang_offers['text_allowed']."</span>]";
	  }
	  else {
	    $allowed = "&nbsp;[<span class=\"offer-uploaded\">已发布</span>]";
	  }
	}
	elseif ($arr["allowed"] == 'denied')
	  $allowed = "&nbsp;[<span class=\"offer-denied\">".$lang_offers['text_denied']."</span>]</b>";
	elseif ($arr["allowed"] == 'frozen')
	  $allowed = "&nbsp;[<span class=\"offer-frozen\">冻结</span>]</b>";
	else
	  $allowed = "&nbsp;[<span class=\"offer-pending\">".$lang_offers['text_pending']."</span>]";
	//===end

	if ($arr["yeah"] == 0)
	$zvote = $arr['yeah'];
	else
	$zvote = "<b><a href=\"?id=".$arr['id']."&amp;offer_vote=1\">".$arr['yeah']."</a></b>";
	if ($arr["against"] == 0)
	$pvote = "$arr[against]";
	else
	$pvote = "<b><a href=\"?id=".$arr['id']."&amp;offer_vote=1\">".$arr['against']."</a></b>";

	if ($arr["yeah"] == 0 && $arr["against"] == 0)
	{
		$v_res = "0";
	}
	else
	{

		$v_res = "<b><a href=\"?id=".$arr['id']."&amp;offer_vote=1\" title=\"".$lang_offers['title_show_vote_details']."\"><font color=\"green\">" .$arr['yeah']."</font> - <font color=\"red\">".$arr['against']."</font> = ".($arr['yeah'] - $arr['against']). "</a></b>";
	}
	$addtime = gettime($arr['added'],false,true);
	$dispname = $arr['name'];
	$count_dispname=mb_strlen($arr['name'],"UTF-8");
	$max_length_of_offer_name = 70;
	if($count_dispname > $max_length_of_offer_name)
		$dispname=mb_substr($dispname, 0, $max_length_of_offer_name-2,"UTF-8") . "..";
	print("<tr><td class=\"rowfollow\" style=\"padding: 0px\"><a href=\"?category=".$arr['cat_id']."\">".return_category_image($arr['cat_id'], "")."</a></td><td style='text-align: left'><a href=\"?id=".$arr['id']."&amp;off_details=1".((is_null($arr['torrent_id']))? '':'&amp;noredir=1')."\" title=\"".htmlspecialchars($arr['name'])."\"><b>".htmlspecialchars($dispname)."</b></a>".(strtotime($arr["added"]) >= $last_offer ? "<b> (<font class='new'>".$lang_offers['text_new']."</font>)</b>" : "").$allowed.((get_user_class() >= $offermanage_class && $arr["allowed"] == 'pending') ? "<br /><form method=\"post\" action=\"?allow_offer=1\"><input type=\"hidden\" value=\"".$arr['id']."\" name=\"offerid\" />"."<input class=\"btn\" type=\"submit\" value=\"".$lang_offers['submit_allow']."\" />&nbsp;</form>" : "")."</td><td class=\"rowfollow nowrap\" style='padding: 5px' align=\"center\">".$v_res."</td>" /*. "<td class=\"rowfollow nowrap\" ".(get_user_class() < $againstoffer_class ? " colspan=\"2\" " : "")." style='padding: 5px'><a href=\"?id=".$arr[id]."&amp;vote=yeah\" title=\"".$lang_offers['title_i_want_this']."\"><font color=\"green\"><b>".$lang_offers['text_yep']."</b></font></a></td>".(get_user_class() >= $againstoffer_class ? "<td class=\"rowfollow nowrap\" align=\"center\"><a href=\"?id=".$arr[id]."&amp;vote=against\" title=\"".$lang_offers['title_do_not_want_it']."\"><font color=\"red\"><b>".$lang_offers['text_nah']."</b></font></a></td>" : ""*/);

	print("<td class=\"rowfollow\">".$comment."</td><td class=\"rowfollow nowrap\">" . $addtime. "</td>");
	if ($offervotetimeout_main > 0 && $offeruptimeout_main > 0){
		if ($arr["allowed"] == 'allowed'){
			$futuretime = strtotime($arr['allowedtime']) + $offeruptimeout_main;
			$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, true, true, false, true);
		}
		elseif ($arr["allowed"] == 'pending')
		{
			$futuretime = strtotime($arr['added']) + $offervotetimeout_main;
			$timeout = gettime(date("Y-m-d H:i:s",$futuretime), false, true, true, false, true);
		}
		else {
			$timeout = "N/A";
		}

		print("<td class=\"rowfollow nowrap\">".$timeout."</td>");
	}
	print("<td class=\"rowfollow\">".$addedby."</td>".(get_user_class() >= $offermanage_class ? "<td class=\"rowfollow\"><a href=\"?id=".$arr['id']."&amp;del_offer=1\"><img class=\"staff_delete\" src=\"pic/trans.gif\" alt=\"D\" title=\"".$lang_offers['title_delete']."\" /></a><br /><a href=\"?id=".$arr['id']."&amp;edit_offer=1\"><img class=\"staff_edit\" src=\"pic/trans.gif\" alt=\"E\" title=\"".$lang_offers['title_edit']."\" /></a></td>" : "")."</tr>");
	}
	print("</table>\n");
	echo $pagerbottom;
create_tooltip_container($lastcom_tooltip, 400);
}
end_main_frame();
$USERUPDATESET[] = "last_offer = ".sqlesc(date("Y-m-d H:i:s"));
stdfoot();

