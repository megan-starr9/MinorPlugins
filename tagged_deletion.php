<?php
/*
 * This plugin is free to use
 * 
 * The following is amazing and was heavily referenced in the making:
 * Enhanced Account Switcher for MyBB 1.6 and 1.8
 * Copyright (c) 2012-2014 doylecc
 * http://mybbplugins.de.vu
 */

if(!defined("IN_MYBB"))
{
	die("You Cannot Access This File Directly. Please Make Sure IN_MYBB Is Defined.");
}

function tagged_deletion_info() {
	return array(
			"name"  => "Tagged PM Deletion",
			"description"=> "Allows user to delete all tag notification pms in inbox with the click of a single button.",
			"website"        => "https://github.com/megan-starr9/TaggedDeletion/wiki",
			"author"        => "Megan Lyle",
			"authorsite"    => "http://megstarr.com",
			"version"        => "1.0",
			"guid"             => "",
			"compatibility" => "18*"
	);
}

function tagged_deletion_activate() {
	global $db;

	// create settings group
	$settingarray = array(
			'name' => 'tagged_deletion',
			'title' => 'Tagged PM Deletion',
			'description' => 'Settings for deletion of tag notice pms.',
			'disporder' => 100,
			'isdefault' => 0
	);
	$gid = $db->insert_query("settinggroups", $settingarray);

	// add settings
	$setting1 = array(
			"sid" => NULL,
			"name" => "tagged_pm_subject",
			"title" => "Subject of Tag Notice PM",
			"description" => "Enter the subject of the tagged notice!",
			"optionscode" => "text",
			"value" => NULL,
			"disporder" => 1,
			"gid" => $gid
	);
	$db->insert_query("settings", $setting1);
	
	$templates = array();
	// add/edit templates
	$template1 = array(
			"tid" => NULL,
			"title" => "tagged_pm_delete_button",
			"template" => $db->escape_string('<a class="button delete_tagged_pms" href="javascript:void(0);">Delete All Tag Notification PMs</a>'),
			"sid" => "-1"
	);
	$db->insert_query("templates", $template1);
	
	// Apply any Template Edits
	//First undo
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets('private', '# {\$tagged_pm_delete_button}#', '');
	// Then apply
	find_replace_templatesets('private', '#{\$lang->selected_messages}#', '{\$lang->selected_messages} {\$tagged_pm_delete_button}');
}

function tagged_deletion_deactivate() {
	global $db, $mybb;

	// delete settings group
	$db->delete_query("settinggroups", "name = 'tagged_deletion'");

	// remove settings
	$db->delete_query("settings", "name = 'tagged_pm_subject'");

	rebuild_settings();

	// delete templates
	$db->delete_query('templates', "title = 'tagged_pm_delete_button'");

	// Revert any Template Edits
	require_once MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets('private', '# {\$tagged_pm_delete_button}#', '');
}

/*
 * Show the button on the pm page!
 */
$plugins->add_hook('private_start', 'show_tagged_delete_button');

function show_tagged_delete_button() {
	global $templates, $tagged_pm_delete_button,$footer;
	
	eval("\$tagged_pm_delete_button = \"".$templates->get('tagged_pm_delete_button')."\";");
	
	$script = '<script>
			$(".delete_tagged_pms").click(function() {
				var del = confirm("Are you sure you want to delete all tag notifications?");
				if(del) {
					$.ajax({
						url: "xmlhttp.php",
						data: {
							action : "delete_tagged_pms",
						},
						type: "post",
						dataType: "html",
						success: function(response){
							alert("Tag Notifications successfully deleted!");
							location.reload();
						},
						error: function(response) {
							alert("There was an error "+response.responseText);
						}
					});
				}
			});
			</script>';
	
	$footer .= $script;
}

/*
 * Perform the delete on submit!
 */
$plugins->add_hook('xmlhttp', 'delete_tagged_pms');

function delete_tagged_pms() {
	global $mybb, $db;
	
	if($mybb->input['action'] == 'delete_tagged_pms') {
		// NOTE: IF YOU HAVE A TAG PLUGIN AND IT SETS A SUBJECT, YOU CAN REPLACE THE SETTING WITH THAT! :D
		$db->update_query('privatemessages', array('folder' => 4),'toid = '.$mybb->user['uid'].' and subject = "'.$mybb->settings['tagged_pm_subject'].'"');
	}
}
?>