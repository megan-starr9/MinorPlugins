<?php
/**
 * User Tagging
 * Jeremiah Johnson
 * http://jwjdev.com/
 */

if(!defined("IN_MYBB"))
{
    die("You Cannot Access This File Directly");
}

$plugins->add_hook("datahandler_post_insert_post", "user_taggingv2_datahandler_post_insert_post");
$plugins->add_hook("datahandler_post_insert_thread_post", "user_taggingv2_datahandler_post_insert_thread_post");
$plugins->add_hook("datahandler_post_update", "user_taggingv2_datahandler_post_update");

function user_taggingv2_info()
{
return array(
        "name"  => "User Tagging v2",
        "description"=> "Adds the ability to tag other users in posts. Also sends PM from tagging user to tagged user. (Modified by Meg!)",
        "website"        => "http://jwjdev.com/",
        "author"        => "Jeremiah Johnson",
        "authorsite"    => "http://jwjdev.com/",
        "version"        => "1.2.1",
        "guid"             => "498ebe70a8844739b14163bd42ac24eb",
        "compatibility" => "18*"
    );
}

function user_taggingv2_is_installed()
{
   global $db;

   $query = $db->simple_select("settinggroups", "name", "name='user_taggingv2'");

   $result = $db->fetch_array($query);

   if($result) {
	return 1;
   } else {
	return 0;
   }

}

function user_taggingv2_install()
{
   global $db;
   $setting_group = array(
		'gid'			=> 'NULL',
		'name'			=> 'user_taggingv2',
		'title'			=> 'User Tagging',
		'description'	=> 'Settings for User Tagging.',
		'disporder'		=> "1",
		'isdefault'		=> 'no',
	);

   $db->insert_query('settinggroups', $setting_group);
   $gid = $db->insert_id();

   $myplugin_setting = array(
		'name'			=> 'user_taggingv2_on',
		'title'			=> 'On/Off',
		'description'	=> 'Turn User Tagging On or Off',
		'optionscode'	=> 'yesno', //this will be a yes/no select box
		'value'			=> '1', //default value is yes, use 0 for no
		'disporder'		=> 1,
		'gid'			=> intval($gid),
	);

   $db->insert_query('settings', $myplugin_setting);

   $myplugin_setting = array(
		'name'			=> 'user_taggingv2_styling',
		'title'			=> 'Tag Styling',
		'description'	=> 'Styles to apply to the text for tags using MyCode.<br /><span style="font-weight:bold;">Example:</span> [b]{tag}[/b]<br /><span style="font-weight:bold;color:red;">MUST CONTAIN {tag} TO WORK</span>',
		'optionscode'	=> 'text',
		'value'			=> '{tag}',
		'disporder'		=> 2,
		'gid'			=> intval($gid),
	);

   $db->insert_query('settings', $myplugin_setting);

   $myplugin_setting = array(
		'name'			=> 'user_taggingv2_forums',
		'title'			=> 'Allowed Forums',
		'description'	=> 'A comma separated list of forum IDs where User Tagging should be enabled.<br />Leave blank for all forums.',
		'optionscode'	=> 'text',
		'value'			=> '',
		'disporder'		=> 3,
		'gid'			=> intval($gid),
	);

   $db->insert_query('settings', $myplugin_setting);

   $myplugin_setting = array(
		'name'			=> 'user_taggingv2_groups',
		'title'			=> 'Allowed Groups',
		'description'	=> 'A comma separated list of groups allowed to use User Tagging.<br />Leave blank for all groups.',
		'optionscode'	=> 'text',
		'value'			=> '',
		'disporder'		=> 4,
		'gid'			=> intval($gid),
	);

   $db->insert_query('settings', $myplugin_setting);

   $myplugin_setting = array(
		'name'			=> 'user_taggingv2_pm_on',
		'title'			=> 'PM Enabled',
		'description'	=> 'Enable or disable sending a PM to the tagged user.',
		'optionscode'	=> 'yesno', //this will be a yes/no select box
		'value'			=> '1', //default value is yes, use 0 for no
		'disporder'		=> 5,
		'gid'			=> intval($gid),
	);

   $db->insert_query('settings', $myplugin_setting);

   $myplugin_setting = array(
		'name'			=> 'user_taggingv2_subject',
		'title'			=> 'PM Subject',
		'description'	=> 'The subject line for the PM sent to the tagged user.',
		'optionscode'	=> 'text',
		'value'			=> 'I tagged you!',
		'disporder'		=> 6,
		'gid'			=> intval($gid),
	);

   $db->insert_query('settings', $myplugin_setting);

   $myplugin_setting = array(
		'name'			=> 'user_taggingv2_body',
		'title'			=> 'PM Body',
		'description'	=> 'The message body for the PM sent to the tagged user. To specify the thread they were tagged in, use {thread}.',
		'optionscode'	=> 'textarea',
		'value'			=> 'I tagged you here: {thread}',
		'disporder'		=> 7,
		'gid'			=> intval($gid),
	);

	$db->insert_query('settings', $myplugin_setting);

   rebuild_settings();
}

function user_taggingv2_activate() {
}

function user_taggingv2_deactivate() {
}

function user_taggingv2_uninstall()
{
	global $db;
	$db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name IN ('user_taggingv2_on','user_taggingv2_subject','user_taggingv2_body','user_taggingv2_pm_on','user_taggingv2_styling','user_taggingv2_forums','user_taggingv2_groups')");
	$db->query("DELETE FROM ".TABLE_PREFIX."settinggroups WHERE name='user_taggingv2'");
	rebuild_settings();
}


function user_taggingv2_send_pm($subject, $msg, $toname, $fromid) {
   require_once MYBB_ROOT."inc/datahandlers/pm.php";
   global $db, $mybb, $lang; ;

   //if PMs are not enabled, exit
   if(!$mybb->settings['user_taggingv2_pm_on']) {
      return;
   }

   $pm_handler = new PMDataHandler();
   $pm_handler->admin_override = true;
   $pm = array(

   	   "subject" => $subject,

   	   "message" => $msg,

	   "fromid" => $fromid,

	   "options" => array(
"savecopy" => "0"),
	   );


   $pm['to'] = array($toname);
   $pm_handler->set_data($pm);

   if(!$pm_handler->validate_pm())
   {
      //bad pm. oops. lol
   } else {
      $pm_handler->insert_pm();
   }
}

function user_taggingv2_check_permissions() {
   global $mybb;
   $allowed = false;
   if(strlen(trim($mybb->settings['user_taggingv2_groups'])) > 0) { //if allowed groups is set
      $allowedGroups = explode(",", $mybb->settings['user_taggingv2_groups']);
      for($i = 0; $i < sizeof($allowedGroups); $i++) { //trim allowed groups
         $allowedGroups[$i] = trim($allowedGroups[$i]);
      }
	  $userGroup = $mybb->user['usergroup'];
	  if(in_array($userGroup, $allowedGroups)) { //check primary usergroups
	     $allowed = true;
	  } else { //check additional user groups
	     $addGroups = explode(",", $mybb->user['additionalgroups']);
		 foreach($addGroups as $checkGroup) {
		    if(in_array($checkGroup, $allowedGroups)) {
			   $allowed = true;
			}
		 }
      }
   } else {
      $allowed = true;
   }
   return $allowed;
}

function user_taggingv2_datahandler_post_insert_post(&$post) {
   global $mybb;
   //pull vars from object
   $msg = $post->post_insert_data['message'];
   $tid = $post->post_insert_data['tid'];
   $time = $post->post_insert_data['dateline'];

   //if they have tagging disabled, do nothing
   if(!$mybb->settings['user_taggingv2_on']) {
      return $msg;
   }

   //if user is not allowed to tag, or forum is disabled for tagging
   if(!user_taggingv2_check_permissions()) {
      return $msg;
   }

   //if PMs are enabled
   if($mybb->settings['user_taggingv2_pm_on']) {
      //build the pm from user settings
      $pmBody = $mybb->settings['user_taggingv2_body'];
      $pmBody = str_replace('{thread}', "[url=" . $mybb->settings["bburl"] . "/showthread.php?tid=" . $tid . "]" . $mybb->settings["bburl"] . "/showthread.php?tid=" . $tid . "[/url]", $pmBody);
   } else {
      $pmBody = "";
   }
   $msg = user_taggingv2_tag($time, $pmBody, $msg);

   $post->post_insert_data['message'] = $msg;
   return $post;
}

function user_taggingv2_datahandler_post_insert_thread_post(&$post) {
   global $mybb;
   //pull vars from object
   $msg = $post->post_insert_data['message'];
   $tid = $post->post_insert_data['tid'];
   $time = $post->post_insert_data['dateline'];

   //if they have tagging disabled, do nothing
   if(!$mybb->settings['user_taggingv2_on']) {
      return $msg;
   }

   //if user is not allowed to tag, or forum is disabled for tagging
   if(!user_taggingv2_check_permissions()) {
      return $msg;
   }

   //if PMs are enabled
   if($mybb->settings['user_taggingv2_pm_on']) {
      //build the pm from user settings
      $pmBody = $mybb->settings['user_taggingv2_body'];
      $pmBody = str_replace('{thread}', "[url=" . $mybb->settings["bburl"] . "/showthread.php?tid=" . $tid . "]" . $mybb->settings["bburl"] . "/showthread.php?tid=" . $tid . "[/url]", $pmBody);
   } else {
      $pmBody = "";
   }
   $msg = user_taggingv2_tag($time, $pmBody, $msg);

   $post->post_insert_data['message'] = $msg;
   return $post;
}

function user_taggingv2_datahandler_post_update(&$post) {
    global $mybb;
   //pull vars from object
   $msg = $post->post_update_data['message'];
   $tid = $post->data['tid'];
   $pid = $post->data['pid'];
   $time = $post->post_update_data['edittime'];

   //if they have tagging disabled, do nothing
   if(!$mybb->settings['user_taggingv2_on']) {
      return $msg;
   }

   //if user is not allowed to tag, or forum is disabled for tagging
   if(!user_taggingv2_check_permissions()) {
      return $msg;
   }

   //if PMs are enabled
   if($mybb->settings['user_taggingv2_pm_on']) {
      //build the pm from user settings
      $pmBody = $mybb->settings['user_taggingv2_body'];
      $pmBody = str_replace('{thread}', "[url=" . $mybb->settings["bburl"] . "/showthread.php?tid=" . $tid . "&pid=" . $pid . "#pid" . $pid . "]" . $mybb->settings["bburl"] . "/showthread.php?tid=" . $tid . "&pid=" . $pid . "#" . $pid . "[/url]", $pmBody);
   } else {
      $pmBody = "";
   }
   $msg = user_taggingv2_tag($pmBody, $pmBody, $msg);

   $post->post_update_data['message'] = $msg;
   return $post;
}

function user_taggingv2_tag($time, $pmBody, $msg) {
   global $db, $mybb;
   $maxlength = $mybb->settings['maxnamelength'];
   $tagged = array(); //array to hold tagged users, incase of multiple tagging

   // get rid of newlines
   $delimiter = ' ' . crypt($msg, $time) . ' ';
   $msg = str_replace("\\n", $delimiter, $msg);

   //build the pm from user settings
   $pmSubject = $mybb->settings['user_taggingv2_subject'];

   $tagPos = 0;
   while (($tagPos = strpos($msg, '@', $tagPos)) !== FALSE) {
     $inc = 1;

     // If the tag is within a quote box, we don't want to do anything with it...
     if(substr_count(strtolower(substr($msg, 0, $tagPos)), '[quote')
            != substr_count(strtolower(substr($msg, 0, $tagPos)), '[/quote]')) {
       $tagPos = $tagPos + $inc;
       continue;
     }

     //get the initial text
     if(strlen($msg) >= $tagPos + $maxlength) {
       $tagtext = substr($msg, $tagPos+1, $maxlength);
     } else {
       $tagtext = substr($msg, $tagPos+1);
     }

     $user = find_user($tagtext);
     //echo " TAG ---- ".$user['username']." ------ ET ";

     if(!empty($user['username'])) {
       $openstyle = '';
       $closestyle = '';
       if(stristr($mybb->settings["user_taggingv2_styling"], "{tag}") !== FALSE) {
          $styles = explode('{tag}', $mybb->settings["user_taggingv2_styling"]);
          $openstyle = $styles[0];
          if(sizeof($styles) > 1) {
            $closestyle = $styles[1];
          }
       }
       // We have our user that we are tagging :)
       // check if userid is already linked
       if(($tagPos-strlen($openstyle)-strlen($user['uid'])-5) < 0 || !preg_match('/uid=(.*?)\]@/i', substr($msg, $tagPos-strlen($openstyle)-strlen($user['uid'])-5, strlen($user['uid'])+6))) {
         $linkedname = '[url=' . $mybb->settings["bburl"] . '/member.php?action=profile&uid=' . $user['uid'] . ']@';
         $linkedname .= $openstyle.$user['username'].$closestyle.'[/url]';
         $msg = substr($msg, 0, $tagPos).$linkedname.substr($msg, $tagPos+strlen(normalize($user['username'])) +1);
         $inc = strlen($linkedname)-2;

         // Finally, send the pm for the tag!
         if(!in_array($user['uid'], $tagged)) //if first tag in post, send pm
         {
          array_push($tagged, $user['uid']);
          //send the pm
          user_taggingv2_send_pm($pmSubject, $pmBody, $user['username'], $mybb->user['uid']);
         }

       } else {
         // if we found a tag and it is linked, we can at least increment by it
         $inc = strlen($user['username'])-2;
       }
     }
     // Increment the search by the amount of text we just entered :)
     $tagPos = $tagPos + $inc;
   }

   // add back in newlines
   $msg = str_replace($delimiter, "\\n", $msg);
    return $msg;
}

function find_user($tagtext) {
  global $mybb, $db;
  $minlength = $mybb->settings['minnamelength'];
  $checkSize = strlen($tagtext);
  $prevSize = $checkSize;
  $usermatches = array();

  //We are treating this like a binary search tree!
  while($checkSize > $minlength) {
    // Get the string we are working with
    $username = substr($tagtext, 0, $checkSize);
    $query = $db->simple_select('users', 'uid,username', 'username LIKE "'.$username.'%"');
    if($query->num_rows > 0) {
      while($user = $query->fetch_assoc()) {
        // One last check to make sure it's actually a match!
        if(strcasecmp(normalize(substr($tagtext, 0, strlen($user['username']))), normalize($user['username'])) == 0) {
          $usermatches[] = $user;
        }
      }
      break;
    }
    $prevSize = $checkSize;
    $checkSize = floor($checkSize/2);
  }
  if(sizeof($usermatches) == 0) {
    //One last check at the minimum length, just in case
    $checkSize = $minlength;
    $username = substr($tagtext, 0, $checkSize);
    $query = $db->simple_select('users', 'uid,username', 'username LIKE "'.$username.'%"');
    if($query->num_rows > 0) {
      while($user = $query->fetch_assoc()) {
        // One last check to be sure it's actually a match!
        if(strcasecmp(normalize(substr($tagtext, 0, strlen($user['username']))), normalize($user['username'])) == 0) {
          $usermatches[] = $user;
        }
      }
    }
  }
  if(sizeof($usermatches) > 1) {
    // Sort usermatches longest to shortest
    usort($usermatches, compareLengths);
    // Now loop through, when we find the longest match that's it!
    for($i=0; $i < $usermatches.length; $i++) {
      $user = $usermatches[$i];
      if(strcasecmp(normalize(substr($tagtext, 0, strlen($user['username']))), normalize($user['username'])) == 0) {
        return $user;
      }
    }
  }
  return $usermatches[0];
}

function compareLengths($user1, $user2){
    return strlen($user2['username']) - strlen($user1['username']);
}

// Handle accented names @.@
function normalize ($string) {
    $table = array(
        'Š'=>'S', 'š'=>'s', 'Đ'=>'Dj', 'đ'=>'dj', 'Ž'=>'Z', 'ž'=>'z', 'Č'=>'C', 'č'=>'c', 'Ć'=>'C', 'ć'=>'c',
        'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
        'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O',
        'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U', 'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss',
        'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c', 'è'=>'e', 'é'=>'e',
        'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o',
        'ô'=>'o', 'õ'=>'o', 'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'ý'=>'y', 'þ'=>'b',
        'ÿ'=>'y', 'Ŕ'=>'R', 'ŕ'=>'r',
    );

    return strtr(trim($string), $table);
}

/*** FOR TESTING PURPOSES ***/
/*function logtxt($text) {
  $file = 'log.txt';
  $current = file_get_contents($file);
  $current .= $text.' EOL';
  file_put_contents($file, $current);
}*/

?>
