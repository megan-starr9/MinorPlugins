<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Make sure we can't access this file directly from the browser.
if(!defined('IN_MYBB')) {
	die('This file cannot be accessed directly.');
}

function displaygroupfix_info() {
  return array(
   'name'			=> 'Display Group Fix',
   'description'	=> 'Once user is accepted into a group, it automatically makes that group the user\'s display.',
   'website'		=> 'http://megstarr.com/wolf/displaygroupfix.txt',
   'author'		=> 'Megan Lyle',
   'authorsite'	=> 'http://megstarr.com',
   'version'		=> '1.0',
   'compatibility'	=> '18*'
  );
}

function displaygroupfix_activate() {
  global $db;

  // Create Settings
	$dispgroupfix_group = array(
			'gid'    => 'NULL',
			'name'  => 'displaygroupfix',
			'title'      => 'Display Group Fix',
			'description'    => 'Settings For Display Group Fix',
			'disporder'    => "1",
			'isdefault'  => "0",
	);

	$db->insert_query('settinggroups', $dispgroupfix_group);
	$gid = $db->insert_id();

  $dispgroupfix_settings[0] = array(
					'sid'            => 'NULL',
					'name'        => 'displaygroupfix_exemptgroups',
					'title'            => 'Groups to Ignore',
					'description'    => 'Groups to not automatically set as display when joined (comma delineate).',
					'optionscode'    => 'text',
					'value'        => '',
					'disporder'        => 1,
					'gid'            => intval($gid),
			);
      foreach($dispgroupfix_settings as $setting) {
    		$db->insert_query('settings', $setting);
    	}
    	rebuild_settings();
}

function displaygroupfix_deactivate() {
  global $db;

  // Delete settings
	$db->query("DELETE FROM ".TABLE_PREFIX."settings WHERE name LIKE 'displaygroupfix_%'");
	$db->query("DELETE FROM ".TABLE_PREFIX."settinggroups WHERE name='displaygroupfix'");
	rebuild_settings();
}

//Accepting single user
$plugins->add_hook('managegroup_do_add_end', 'approve');
function approve() {
  global $user, $gid;
  set_display_group($user['uid'], $gid);
}

//Accepting multiple requests
$plugins->add_hook('managegroup_do_joinrequests_end', 'add_requests');
function add_requests() {
  global $mybb, $gid;
  foreach($mybb->get_input('request', MyBB::INPUT_ARRAY) as $uid => $what) {
    if($what == "accept") {
      set_display_group($uid, $gid);
    }
  }
}

//Admin accepting
$plugins->add_hook('admin_user_groups_approve_join_request_commit', 'admin_approve');
function admin_approve() {
  global $request;
  set_display_group($request['uid'], $request['gid']);
}

//Admin accepting many requests
$plugins->add_hook('admin_user_groups_join_requests_commit', 'admin_add_requests');
function admin_add_requests() {
  global $mybb, $group;
  if(isset($mybb->input['approve']) && is_array($mybb->input['users'])) {
			foreach($mybb->input['users'] as $uid) {
        set_display_group($uid, $group['gid']);
      }
    }
}

function set_display_group($uid, $gid) {
  global $mybb, $db;

  $groupstoignore = explode(',', $mybb->settings['displaygroupfix_exemptgroups']);
  if(!in_array($gid, $groupstoignore)) {
    echo ("RUN");
    $db->update_query("users", array('displaygroup' => $gid), "uid='".(int)$uid."'");
  }
}
