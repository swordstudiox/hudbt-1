<?php
require_once("include/benc.php");
require_once("include/bittorrent.php");
ini_set("upload_max_filesize",$max_torrent_size);
dbconn();
require_once(get_langfile_path());
require(get_langfile_path("",true));
loggedinorreturn();

if ($_REQUEST['format'] == 'json') {
  $format = 'json';
}
else {
  $format = 'html';
}

function bark($msg, $escape = true) {
	global $lang_takeupload;
	global $format;
	if ($format == 'json') {
	  echo json_encode(['success' => false, 'message' => $msg]);
	}
	else {
	  stderr($lang_takeupload['std_upload_failed'], $msg, $escape);
	}
	die;
}


if ($CURUSER["uploadpos"] == 'no') {
  bark($lang_takeupload['std_unauthorized_upload_freely']);
}

foreach(explode(":","descr:type:name") as $v) {
	if (!isset($_POST[$v]))
	bark($lang_takeupload['std_missing_form_data']);
}

if (get_user_class()>=$beanonymous_class && $_POST['uplver'] == 'yes') {
	$anonymous = "yes";
	$anon = "Anonymous";
}
else {
	$anonymous = "no";
	$anon = $CURUSER["username"];
}

$dl_url = trim($_POST['dl-url']);
if($dl_url && !filter_var($dl_url, FILTER_VALIDATE_URL)) {
  bark('无效的下载链接');
}

$url = parse_imdb_id($_POST['url']);
if (!$url) {
  $url = null;
}

$nfo = '';
if ($enablenfo_main=='yes'){
$nfofile = $_FILES['nfo'];
if ($nfofile['name'] != '') {

	if ($nfofile['size'] == 0)
	bark($lang_takeupload['std_zero_byte_nfo']);

	if ($nfofile['size'] > 65535)
	bark($lang_takeupload['std_nfo_too_big']);

	$nfofilename = $nfofile['tmp_name'];

	if (@!is_uploaded_file($nfofilename))
	bark($lang_takeupload['std_nfo_upload_failed']);
	$nfo = str_replace("\x0d\x0d\x0a", "\x0d\x0a", @file_get_contents($nfofilename));
}
}


$small_descr = add_space_between_words(unesc($_POST["small_descr"]));

$descr = unesc($_POST["descr"]);
if (!$descr) {
  bark($lang_takeupload['std_blank_description']);
}
require_once('HTML/BBCodePreparser.php');
$preparser = new BBCodePreparser($descr);
$descr = $preparser->getText();

$catid = (0 + $_POST["type"]);
$sourceid = (0 + $_POST["source_sel"]);
$mediumid = (0 + $_POST["medium_sel"]);
$codecid = (0 + $_POST["codec_sel"]);
$standardid = (0 + $_POST["standard_sel"]);
$processingid = (0 + $_POST["processing_sel"]);
$teamid = (0 + $_POST["team_sel"]);
$audiocodecid = (0 + $_POST["audiocodec_sel"]);

if (!is_valid_id($catid)) {
  bark($lang_takeupload['std_category_unselected']);
}
$torrent = unesc($_POST["name"]);

if (isset($_FILES["file"])) {
  /* if ($_FILES['file']['error'] == UPLOAD_ERR_NO_FILE) { */
  /*   if (!$dl_url) { */
  /*     bark('必须上传种子或填写下载链接'); */
  /*   } */
  /*   else { */
  /*     $id = get_single_value('torrents', 'id', 'WHERE dl_url=?', [$dl_url]); */
  /*     if ($id) { */
  /* 	bark('已经存在相同的种子了, <a href="//' . $BASEURL .'/details.php?id=' . $id . '">点击这里</a>', false); */
  /*     } */
  /*     $no_torrent = true; */
      
  /*     $filelist = []; */
  /*     $totallen = 0 + $_POST['filesize'] * 1024 * 1024; */
  /*     $fname = ''; */
  /*     $type = 'single'; */
  /*     $dname = ''; */
  /*     $infohash = null; */
  /*     $write_torrent = function($id) { */
  /*     }; */
  /*     $parse_time = 0; */
  /*   } */
  /* } */
  /* else */ {
    $no_torrent = false;
    $start = time();
    include('include/upload.php');
    $parse_time = time() - $start;
    $write_torrent = function($id) use ($torrent_dir) {
      global $dict;
      $fp = fopen("$torrent_dir/$id.torrent", "w");
      if ($fp) {
	fwrite($fp, benc($dict), strlen(benc($dict)));
	fclose($fp);
      }
    };
  }
}
/* else if (isset($_REQUEST['torrenthash'])) { */
/*   $infohash = $_REQUEST['torrenthash']; */
/*   $filename = $torrent_dir . '/cache/' . $infohash . '.torrent'; */
/*   if (!preg_match('/^[0-9a-f]+$/i', $infohash) || !file_exists($filename)) { */
/*     bark('无效的infohash'); */
/*   } */
/*   $write_torrent = function($id) use ($filename) { */
/*     rename($filename, $torrent_dir . '/' . $id . '.torrent'); */
/*   }; */
/* } */
else {
  bark($lang_takeupload['std_missing_form_data']);
}

// ------------- start: check upload authority ------------------//
$allowtorrents = user_can_upload("torrents");
$allowspecial = user_can_upload("music");

$catmod = get_single_value("categories","mode","WHERE id=".sqlesc($catid));
$offerid = $_POST['offer'];
$is_offer=false;
if ($browsecatmode != $specialcatmode && $catmod == $specialcatmode){//upload to special section
	if (!$allowspecial)
		bark($lang_takeupload['std_unauthorized_upload_freely']);
}
elseif($catmod == $browsecatmode){//upload to torrents section
 	if ($offerid && $enableoffer == 'yes'){//it is a offer
	  $allowed_offer = sql_query("SELECT name FROM offers WHERE id= ? AND allowed='allowed' AND userid=? AND ISNULL(torrent_id)", [$offerid, $CURUSER["id"]]);
	  if ($allowed_offer->rowCount()) {
	    if ($_REQUEST['name'] != $allowed_offer->fetch()['name']) {
	      bark('候选名称必须一致!');
	    }
	    $is_offer = true;
	  }
	  else {
	    //user uploaded torrent that is not an allowed offer
	    bark($lang_takeupload['std_uploaded_not_offered']);
	  }	  
	}
	elseif (!$allowtorrents)
		bark($lang_takeupload['std_unauthorized_upload_freely']);
}
else //upload to unknown section
	die("Upload to unknown section.");
// ------------- end: check upload authority ------------------//

foreach ($filelist as $file) {
  $filename = $file[0];
  $tokens = explode('.', $filename);
  $extension = array_pop($tokens);
  if (strtolower($extension) == 'torrent') {
    bark($lang_takeupload['std_contains_torrent']);
  }
}

// Replace punctuation characters with spaces

//$torrent = str_replace("_", " ", $torrent);

$sp_until = '0000-00-00 00:00:00';
$sp_time_type = 0;
if ($largesize_torrent && $totallen > ($largesize_torrent * 1073741824)) //Large Torrent Promotion
{
	switch($largepro_torrent)
	{
		case 2: //Free
		{
			$sp_state = 2;
			break;
		}
		case 3: //2X
		{
			$sp_state = 3;
			break;
		}
		case 4: //2X Free
		{
			$sp_state = 4;
			break;
		}
		case 5: //Half Leech
		{
			$sp_state = 5;
			break;
		}
		case 6: //2X Half Leech
		{
			$sp_state = 6;
			break;
		}
		case 7: //30% Leech
		{
			$sp_state = 7;
			break;
		}
		default: //normal
		{
			$sp_state = 1;
			break;
		}
	}
}
elseif (!$no_torrent) { //ramdom torrent promotion
	$sp_id = mt_rand(1,100);
	if($sp_id <= ($probability = $randomtwoupfree_torrent)) //2X Free
		$sp_state = 4;
	elseif($sp_id <= ($probability += $randomtwoup_torrent)) //2X
		$sp_state = 3;
	elseif($sp_id <= ($probability += $randomfree_torrent)) //Free
		$sp_state = 2;
	elseif($sp_id <= ($probability += $randomhalfleech_torrent)) //Half Leech
		$sp_state = 5;
	elseif($sp_id <= ($probability += $randomtwouphalfdown_torrent)) //2X Half Leech
		$sp_state = 6;
	elseif($sp_id <= ($probability += $randomthirtypercentdown_torrent)) //30% Leech
		$sp_state = 7;
	else
		$sp_state = 1; //normal

	if ($sp_state != 1) {
	  if ($randomtimelimit_torrent != 0) {
	    $sp_until = date("Y-m-d H:i:s", TIMENOW + 7200);
	    $sp_time_type = 2;
	  }
	  else {
	    $sp_time_type = 1;
	  }
	}
}

if ($altname_main == 'yes'){
$cnname_part = unesc(trim($_POST["cnname"]));
$size_part = str_replace(" ", "", mksize($totallen));
$date_part = date("m.d.y");
$category_part = get_single_value("categories","name","WHERE id = ".sqlesc($catid));
$torrent = "【".$date_part."】".($_POST["name"] ? "[".$_POST["name"]."]" : "").($cnname_part ? "[".$cnname_part."]" : "");
}

// some ugly code of automatically promoting torrents based on some rules
if ($prorules_torrent == 'yes'){
foreach ($promotionrules_torrent as $rule)
{
	if (!array_key_exists('catid', $rule) || in_array($catid, $rule['catid']))
		if (!array_key_exists('sourceid', $rule) || in_array($sourceid, $rule['sourceid']))
			if (!array_key_exists('mediumid', $rule) || in_array($mediumid, $rule['mediumid']))
				if (!array_key_exists('codecid', $rule) || in_array($codecid, $rule['codecid']))
					if (!array_key_exists('standardid', $rule) || in_array($standardid, $rule['standardid']))
						if (!array_key_exists('processingid', $rule) || in_array($processingid, $rule['processingid']))
							if (!array_key_exists('teamid', $rule) || in_array($teamid, $rule['teamid']))
								if (!array_key_exists('audiocodecid', $rule) || in_array($audiocodecid, $rule['audiocodecid']))
									if (!array_key_exists('pattern', $rule) || preg_match($rule['pattern'], $torrent))
										if (is_numeric($rule['promotion'])){
											$sp_state = $rule['promotion'];
											break;
										}
}
}

$torrent = add_space_between_words($torrent);

if ($no_torrent) {
  $startseed = 'yes';
}
else {
  $startseed = 'no';
}

sql_query("INSERT INTO torrents (filename, owner, visible, anonymous, name, size, numfiles, type, url, small_descr, descr, ori_descr, category, source, medium, codec, audiocodec, standard, processing, team, save_as, sp_state, promotion_time_type, promotion_until, added, last_action, nfo, info_hash, dl_url, startseed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, ?, ?, ?)", [$fname, $CURUSER["id"], 'yes', $anonymous, $torrent, $totallen, count($filelist), $type, $url, $small_descr, $descr, $descr, $catid, $sourceid, $mediumid, $codecid, $audiocodecid, $standardid, $processingid, $teamid, $dname, $sp_state, $sp_time_type, $sp_until, $nfo, $infohash, $dl_url, $startseed]);

$id = _mysql_insert_id();

$Cache->cache_value('torrent_max_id', $id, 86400);

sql_query("DELETE FROM files WHERE torrent = ?", [$id]);

if (!empty($filelist)) {
  $sql = "INSERT INTO files (torrent, filename, size) VALUES (?,?,?)";
  $first = true;
  $args = [];
  foreach ($filelist as $file) {
    if ($first) {
      $first = false;
    }
    else {
      $sql .= ',(?,?,?)';
    }
    array_push($args, $id, $file[0], $file[1]);
  }

  sql_query($sql, $args);
}

//===add karma
KPS("+",$uploadtorrent_bonus,$CURUSER["id"]);
//===end

//Tcategory
App::uses('Torrent', 'Model');
$Torrent = new Torrent;
$Torrent->id = $id;
if ($Torrent->exists()) {
  $data = $_REQUEST['data'];
  $d = ['Torrent' => ['id' => $id],
	'Tcategory' => ['Tcategory' => $data['Tcategory']['Tcategory']]];
  if (!$Torrent->save($d)) {
    bark('Cannot save tcategories');
  }
}

//End of tcategory


$location = "//$BASEURL/details.php?id=".htmlspecialchars($id);
if (!$no_torrent) {
  $location .= "&uploaded=1";
}

if ($parse_time > 3) { // Slow, large torrent
  $time = ceil($parse_time / 5) * 5;
#  header('HTTP/1.1 202 Accepted');
  header('Refresh: ' . $time . '; url=' . $location, true, 202);
  stdhead('上传');
  stdmsg('蝴蝶娘正在努力处理中', '种子太大了嘛~~~~(>_<)~~~~ ' . $time . '秒后自动定向到<a href="' . $location . '">详情页面</a>');
  stdfoot();

  $write_torrent($id);

}
else {
  $write_torrent($id);

  if ($format == 'json') {
    echo json_ecnode(['success' => true, 'uri' => $location]);
  }
  else {
    header("Location: $location");
  }
}


write_log("Torrent $id ($torrent) was uploaded by $anon");

//===notify people who voted on offer thanks CoLdFuSiOn :)
if ($is_offer) {
	$res = sql_query("SELECT `userid` FROM `offervotes` WHERE `userid` != " . $CURUSER["id"] . " AND `offerid` = ". sqlesc($offerid)." AND `vote` = 'yeah'") or sqlerr(__FILE__, __LINE__);

	while($row = _mysql_fetch_assoc($res)) 
	{
		$pn_msg = $lang_takeupload_target[get_user_lang($row["userid"])]['msg_offer_you_voted'].$torrent.$lang_takeupload_target[get_user_lang($row["userid"])]['msg_was_uploaded_by']. $CURUSER["username"] .$lang_takeupload_target[get_user_lang($row["userid"])]['msg_you_can_download'] ."[url=" . get_protocol_prefix() . "$BASEURL/details.php?id=$id&hit=1]".$lang_takeupload_target[get_user_lang($row["userid"])]['msg_here']."[/url]";
		
		$subject = $lang_takeupload_target[get_user_lang($row["userid"])]['msg_offer'].$torrent.$lang_takeupload_target[get_user_lang($row["userid"])]['msg_was_just_uploaded'];
		send_pm(0, $row['userid'], $subject, $pn_msg);
	}

	sql_query('UPDATE offers SET torrent_id = ? WHERE id = ?', [$id, $offerid]);
}
//=== end notify people who voted on offer

/* Email notifs */
if ($emailnotify_smtp=='yes' && $smtptype != 'none') {
$cat = get_single_value("categories","name","WHERE id=".sqlesc($catid));
$res = sql_query("SELECT id, email, lang FROM users WHERE enabled='yes' AND parked='no' AND status='confirmed' AND notifs LIKE '%[cat$catid]%' AND notifs LIKE '%[email]%' ORDER BY lang ASC") or sqlerr(__FILE__, __LINE__);

$uploader = $anon;

$size = mksize($totallen);

$description = format_comment($descr);

//dirty code, change later

$langfolder_array = array("en", "chs", "cht", "ko", "ja");
$body_arr = array("en" => "", "chs" => "", "cht" => "", "ko" => "", "ja" => "");
$i = 0;
foreach($body_arr as $body)
{
$body_arr[$langfolder_array[$i]] = <<<EOD
{$lang_takeupload_target[$langfolder_array[$i]]['mail_hi']}

{$lang_takeupload_target[$langfolder_array[$i]]['mail_new_torrent']}

{$lang_takeupload_target[$langfolder_array[$i]]['mail_torrent_name']}$torrent
{$lang_takeupload_target[$langfolder_array[$i]]['mail_torrent_size']}$size
{$lang_takeupload_target[$langfolder_array[$i]]['mail_torrent_category']}$cat
{$lang_takeupload_target[$langfolder_array[$i]]['mail_torrent_uppedby']}$uploader

{$lang_takeupload_target[$langfolder_array[$i]]['mail_torrent_description']}
-------------------------------------------------------------------------------------------------------------------------
$description
-------------------------------------------------------------------------------------------------------------------------

{$lang_takeupload_target[$langfolder_array[$i]]['mail_torrent']}<b><a href="javascript:void(null)" onclick="window.open('http://$BASEURL/details.php?id=$id&hit=1')">{$lang_takeupload_target[$langfolder_array[$i]]['mail_here']}</a></b><br />
http://$BASEURL/details.php?id=$id&hit=1

------{$lang_takeupload_target[$langfolder_array[$i]]['mail_yours']}
{$lang_takeupload_target[$langfolder_array[$i]]['mail_team']}
EOD;

$body_arr[$langfolder_array[$i]] = str_replace("<br />","<br />",nl2br($body_arr[$langfolder_array[$i]]));
	$i++;
}

while($arr = _mysql_fetch_array($res)) {
  $current_lang = $arr["lang"];
  $to = $arr["email"];

  sent_mail($to,$SITENAME,$SITEEMAIL,change_email_encode(validlang($current_lang),$lang_takeupload_target[validlang($current_lang)]['mail_title'].$torrent),change_email_encode(validlang($current_lang),$body_arr[validlang($current_lang)]),"torrent upload",false,false,'',get_email_encode(validlang($current_lang)), "eYou");
}
}


