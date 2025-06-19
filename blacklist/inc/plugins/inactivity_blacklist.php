<?php
/*
 * Inaktivitäts- & Blacklist-Plugin
 */
 
if(!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

// #################### HOOK-REGISTRIERUNGEN ####################
// ACP-Hooks
$plugins->add_hook("admin_config_menu", "inactivity_blacklist_admin_config_menu");
$plugins->add_hook("admin_config_action_handler", "inactivity_blacklist_admin_action_handler");
$plugins->add_hook("admin_config_permissions", "inactivity_blacklist_admin_permissions");
$plugins->add_hook("admin_load", "inactivity_blacklist_admin_load");

// MyAlerts Hooks
$plugins->add_hook('global_start', 'inactivity_blacklist_register_formatter_back_compat');
$plugins->add_hook('xmlhttp', 'inactivity_blacklist_register_formatter_back_compat', -2);
$plugins->add_hook('myalerts_register_client_alert_formatters', 'inactivity_blacklist_register_formatter');

// PLUGIN-INFORMATIONEN
function inactivity_blacklist_info() {
    global $lang;
    $lang->load('inactivity_blacklist', true);

    return [
        "name" => "Blacklist",
        "description" => "Blacklist Plugin zum überwachen der Aktivität",
        "website" => "https://shadow.or.at/index.php",
        "author" => "Dani",
        "authorsite" => "https://github.com/ShadowOfDestiny",
        "version" => "1.0",
        "guid" => "",
        "compatibility" => "18*"
    ];
}

// PLUGIN-INSTALLATION
function inactivity_blacklist_install()
{
    global $db, $lang;
    $lang->load('inactivity_blacklist', false, true);

    if (!$db->table_exists("inactivity_blacklist"))
    {
        $collation = $db->build_create_table_collation();
        $db->write_query("CREATE TABLE `" . TABLE_PREFIX . "inactivity_blacklist` (
            `bid` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `uid` INT(10) UNSIGNED NOT NULL,
            `username` VARCHAR(120) NOT NULL,
            `blacklisted_since` BIGINT(30) NOT NULL,
            `reason` VARCHAR(255) NOT NULL,
            PRIMARY KEY (`bid`),
            UNIQUE KEY `uid` (`uid`)
        ) ENGINE=MyISAM{$collation};");
        
        $db->write_query("
            CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "inactivity_blacklist_log` (
                `uid` int(10) unsigned NOT NULL,
                `dateline` bigint(30) NOT NULL,
                PRIMARY KEY (`uid`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
        ");
    }
	
// KORREKTUR: Zwei getrennte Alert-Typen registrieren
    if (function_exists('myalerts_info')) {
        $alertTypeManager = \MybbStuff_MyAlerts_AlertTypeManager::getInstance();
        if (!$alertTypeManager) { myalerts_create_instances(); $alertTypeManager = \MybbStuff_MyAlerts_AlertTypeManager::getInstance(); }

        if ($alertTypeManager) {
            if ($db->num_rows($db->simple_select('alert_types', 'id', "code = 'inactivity_blacklist_user'")) == 0) {
                $alertTypeUser = new \MybbStuff_MyAlerts_Entity_AlertType();
                $alertTypeUser->setCode('inactivity_blacklist_user')->setEnabled(true)->setCanBeUserDisabled(true)->setDefaultUserEnabled(true);
                $alertTypeManager->add($alertTypeUser);
            }
            if ($db->num_rows($db->simple_select('alert_types', 'id', "code = 'inactivity_blacklist_admin'")) == 0) {
                $alertTypeAdmin = new \MybbStuff_MyAlerts_Entity_AlertType();
                $alertTypeAdmin->setCode('inactivity_blacklist_admin')->setEnabled(true)->setCanBeUserDisabled(false)->setDefaultUserEnabled(true);
                $alertTypeManager->add($alertTypeAdmin);
            }
        }
    }

    $settinggroup = [
        'name'          => 'inactivity_blacklist',
        'title'         => $db->escape_string($lang->settinggroup_inactivity_blacklist),
        'description'   => $db->escape_string($lang->settinggroup_inactivity_blacklist_desc),
        'disporder'     => 50,
        'isdefault'     => 0
    ];
    $gid = $db->insert_query("settinggroups", $settinggroup);

    // 3. Einstellungen erstellen
    $settings = [
        'ingame_fids' 	  	=> [
            'title' 	  	=> $db->escape_string($lang->setting_inactivity_blacklist_ingame_fids),
            'description' 	=> $db->escape_string($lang->setting_inactivity_blacklist_ingame_fids_desc),
            'optionscode' 	=> 'forumselect', 
			'value' 	  	=> '', 
			'disporder'   	=> 1
        ],
        'archive_fids' 	  	=> [
            'title' 	  	=> $db->escape_string($lang->setting_inactivity_blacklist_archive_fids),
            'description' 	=> $db->escape_string($lang->setting_inactivity_blacklist_archive_fids_desc),
            'optionscode'	=> 'forumselect', 
			'value'		 	=> '', 
			'disporder'  	=> 2
        ],
        'post_days'  		=> [
            'title'         => $db->escape_string($lang->setting_inactivity_blacklist_post_days),
            'description'   => $db->escape_string($lang->setting_inactivity_blacklist_post_days_desc),
            'optionscode'   => 'numeric', 
			'value' => '90', 
			'disporder' => 3
        ],
        'login_days' 		=> [
            'title'         => $db->escape_string($lang->setting_inactivity_blacklist_login_days),
            'description'   => $db->escape_string($lang->setting_inactivity_blacklist_login_days_desc),
            'optionscode'   => 'numeric', 
			'value' 		=> '90', 
			'disporder' 	=> 4
        ],
        'excluded_uids' 	=> [
            'title'         => $db->escape_string($lang->setting_inactivity_blacklist_excluded_uids),
            'description'   => $db->escape_string($lang->setting_inactivity_blacklist_excluded_uids_desc),
            'optionscode'   => 'text', 
			'value' 		=> '1,8,35', 
			'disporder' 	=> 5
        ],
        'post_fid' 			=> [
            'title'         => $db->escape_string($lang->setting_inactivity_blacklist_post_fid),
            'description'   => $db->escape_string($lang->setting_inactivity_blacklist_post_fid_desc),
            'optionscode'   => 'forumselectsingle', 
			'value' 		=> '', 
			'disporder' 	=> 6
        ],
        'poster_uid' 		=> [
            'title'         => $db->escape_string($lang->setting_inactivity_blacklist_poster_uid),
            'description'   => $db->escape_string($lang->setting_inactivity_blacklist_poster_uid_desc),
            'optionscode'   => 'numeric', 
			'value'			=> '1', 
			'disporder' 	=> 7
        ],
        'admin_uids' => [
            'title'         => $db->escape_string($lang->setting_inactivity_blacklist_admin_uids),
            'description'   => $db->escape_string($lang->inactivity_blacklist_admin_uids_desc),
            'optionscode'   => 'text', 
			'value' 		=> '1', 
			'disporder'		=> 8
        ],
        'inactive_group' 	=> [
            'title'       	=> $db->escape_string($lang->setting_inactivity_blacklist_inactive_group),
            'description' 	=> $db->escape_string($lang->setting_inactivity_blacklist_inactive_group_desc),
            'optionscode' 	=> 'groupselectsingle',
			'value'		  	=> '',
            'disporder'   	=> 9
        ],
        'grace_period' 		=> [
            'title'       	=> $db->escape_string($lang->setting_inactivity_blacklist_grace_period),
            'description' 	=> $db->escape_string($lang->setting_inactivity_blacklist_grace_period_desc),
            'optionscode' 	=> 'numeric',
            'value'       	=> '14',
            'disporder'   	=> 10
        ],
        'check_partner' 	=> [
            'title'       	=> $db->escape_string($lang->setting_inactivity_blacklist_check_partner),
            'description' 	=> $db->escape_string($lang->setting_inactivity_blacklist_check_partner_desc),
            'optionscode' 	=> 'yesno',
            'value'       	=> '1', // Standardmäßig aktiviert
            'disporder'   	=> 11
        ],
        'debug_mode'    	=> [
			'title' 		=> $db->escape_string($lang->setting_inactivity_blacklist_debug_mode),     
			'description' 	=> $db->escape_string($lang->setting_inactivity_blacklist_debug_mode_desc),     
			'optionscode' 	=> 'yesno', 
			'value' 		=> '0', 
			'disporder' 	=> 12
		],
    ];
    
    foreach($settings as $name => $setting)
    {
        $setting['name'] = "inactivity_blacklist_{$name}";
        $setting['gid'] = $gid;
        $db->insert_query("settings", $setting);
    }
    rebuild_settings();
    
    // 4. ACP Templates erstellen (KORRIGIERTES HTML)
    $templates_to_add = [
        "inactivity_blacklist_main" => '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - Inaktivitäts-Blacklist</title>
{$headerinclude}
</head>
<body>
{$header}
{$admintabs}
<table width="100%" border="0" align="center">
<tr>
<td>
<div id="tab_overview" class="tab_content">
    {$overview_table}
</div>
<div id="tab_settings" class="tab_content">
    <p>Hier kannst du die Einstellungen direkt in der <a href="index.php?module=config-settings&action=change&search=inactivity_blacklist">Plugin-Konfiguration</a> bearbeiten.</p>
</div>
</td>
</tr>
</table>
{$footer}
</body>
</html>',
        "inactivity_blacklist_overview" => '<form action="index.php?module=config-inactivity_blacklist&action=remove" method="post">
<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="general" width="100%">
    <tr>
        <td class="thead" colspan="5"><strong>Übersicht der Blacklist</strong></td>
    </tr>
    <tr>
        <td class="tcat" width="5%"><span class="smalltext"><strong>Entfernen</strong></span></td>
        <td class="tcat"><span class="smalltext"><strong>Benutzername</strong></span></td>
        <td class="tcat" width="25%"><span class="smalltext"><strong>Auf der Liste seit</strong></span></td>
        <td class="tcat" width="40%"><span class="smalltext"><strong>Grund</strong></span></td>
    </tr>
    {$user_rows}
</table>
<br />
<div class="float_right">
    <input type="submit" class="button" name="submit" value="Ausgewählte entfernen" />
</div>
</form>',
        "inactivity_blacklist_overview_row" => '<tr>
    <td class="trow1" align="center"><input type="checkbox" name="remove_uids[]" value="{$user[\'uid\']}" class="checkbox" /></td>
    <td class="trow1"><a href="../member.php?action=profile&uid={$user[\'uid\']}" target="_blank">{$user[\'username\']}</a></td>
    <td class="trow1">{$blacklisted_date}</td>
    <td class="trow1">{$user[\'reason\']}</td>
</tr>',
        "inactivity_blacklist_overview_no_users" => '<tr>
    <td class="trow1" colspan="5" align="center">Die Blacklist ist aktuell leer.</td>
</tr>',
    ];

    foreach($templates_to_add as $name => $template)
    {
        $db->insert_query("templates", 
        ["title" => $db->escape_string($name), 
        "template" => $db->escape_string($template), 
        "sid" => "-1", 
        "version" => "18", 
        "dateline" => TIME_NOW]); 
    }
}

function inactivity_blacklist_is_installed() {
    global $db;
    return $db->table_exists("inactivity_blacklist");
}

function inactivity_blacklist_uninstall() {
    global $db;
	
	// KORRIGIERT: MyAlerts-Typ korrekt deregistrieren
    if (function_exists('myalerts_info')) {
        $alertTypeManager = \MybbStuff_MyAlerts_AlertTypeManager::getInstance();
        if (!$alertTypeManager) { myalerts_create_instances(); $alertTypeManager = \MybbStuff_MyAlerts_AlertTypeManager::getInstance(); }
        if ($alertTypeManager) {
            $alertTypeManager->deleteByCode('inactivity_blacklist_user');
            $alertTypeManager->deleteByCode('inactivity_blacklist_admin');
        }
    }
        
    if($db->table_exists("inactivity_blacklist")) {
        $db->drop_table("inactivity_blacklist");
        $db->query("DROP TABLE IF EXISTS `".TABLE_PREFIX."inactivity_blacklist_log`");
    }
    $db->delete_query("settings", "name LIKE 'inactivity_blacklist_%'");
    $db->delete_query("settinggroups", "name = 'inactivity_blacklist'");
    $db->delete_query("templates", "title LIKE 'inactivity_blacklist_%'");
    rebuild_settings();
}

function inactivity_blacklist_activate() {
    global $db, $plugins, $lang;
    $lang->load('inactivity_blacklist', false, true);
    require_once MYBB_ROOT."/inc/functions_task.php";
    $query = $db->simple_select("tasks", "tid", "file = 'inactivity_blacklist'", ["limit" => 1]);
    if($db->num_rows($query) == 0) {
        $new_task = [
            "title" => $db->escape_string($lang->inactivity_blacklist_task_name),
            "description" => $db->escape_string($lang->inactivity_blacklist_task_desc),
            "file" => "inactivity_blacklist",
            "minute" => "0", "hour" => "3", "day" => "*",
            "month" => "*", "weekday" => "*",
            "enabled" => 1, "logging" => 1
        ];
        $new_task['nextrun'] = fetch_next_run($new_task);
        $db->insert_query("tasks", $new_task);
    }
}

function inactivity_blacklist_deactivate() {
    global $db, $plugins, $lang;
    require_once MYBB_ROOT."/inc/functions_task.php";
    $db->update_query("tasks", ["enabled" => 0], "file = 'inactivity_blacklist'");
}

// #################### FINALE, KORRIGIERTE ADMIN-CP FUNKTIONEN ####################

// Hook: Fügt den Menüpunkt im Admin-CP unter "Konfiguration" hinzu
function inactivity_blacklist_admin_config_menu(&$sub_menu)
{
    global $lang;
    $lang->load('inactivity_blacklist', false, true);

    // Diese Struktur ist entscheidend. Es MUSS ein Array in einem Array sein.
    $sub_menu[] = [
        'id'    => 'inactivity_blacklist',
        'title' => $lang->inactivity_blacklist_menu,
        'link'  => 'index.php?module=config-inactivity_blacklist'
    ];
}

// Hook: Definiert, welche Aktionen unser Modul behandeln kann
function inactivity_blacklist_admin_action_handler(&$actions)
{
    $actions['inactivity_blacklist'] = ['active' => 'inactivity_blacklist', 'file' => 'inactivity_blacklist_admin_page'];
}

// Hook: Fügt Berechtigungen für unser Modul hinzu
function inactivity_blacklist_admin_permissions(&$admin_permissions)
{
    global $lang;
    $lang->load("inactivity_blacklist", true);
    $admin_permissions['inactivity_blacklist'] = $lang->inactivity_blacklist_menu;
}

// Hook: Wird bei jedem Laden einer Admin-Seite aufgerufen und steuert die Anzeige.
function inactivity_blacklist_admin_load()
{
    global $page, $mybb, $action_file, $run_module;

    if ($run_module == 'config' && ($action_file == 'inactivity_blacklist_admin_page' || $page->active_action == 'inactivity_blacklist')) {
        inactivity_blacklist_admin_page();
        die();
    }
}

// Baut die eigentliche Admin-Seite auf
function inactivity_blacklist_admin_page()
{
    global $page, $mybb, $db, $lang;
    $lang->load('inactivity_blacklist', true);

    $page->add_breadcrumb_item($lang->inactivity_blacklist_menu, "index.php?module=config-inactivity_blacklist");
    $page->output_header($lang->inactivity_blacklist_menu);

    // Tabs für "Übersicht" und "Einstellungen"
    $sub_tabs['overview'] = [
        'title' => $lang->inactivity_blacklist_overview,
        'link' => "index.php?module=config-inactivity_blacklist&action=overview",
        'description' => $lang->inactivity_blacklist_overview_desc
    ];
    $query = $db->simple_select("settinggroups", "gid", "name='inactivity_blacklist'");
    $gid = $db->fetch_field($query, "gid");
    $sub_tabs['settings'] = [
        'title' => $lang->inactivity_blacklist_settings,
        'link' => "index.php?module=config-settings&action=change&gid=". (int)$gid,
        'description' => $lang->inactivity_blacklist_settings_desc
    ];

    $current_action = $mybb->input['action'];
    if (!$current_action || $current_action == 'index') {
        $current_action = 'overview';
    }
    $page->output_nav_tabs($sub_tabs, $current_action);

    if ($current_action == 'overview') {
        $inactive_group_id = (int)$mybb->settings['inactivity_blacklist_inactive_group'];
        if ($inactive_group_id > 0) {
            $table = new Table;
            $table->construct_header($lang->username);
            $table->construct_header($lang->inactivity_blacklist_date_added, ["width" => "30%", "class" => "align_center"]);

            $query = $db->query("
                SELECT u.uid, u.username, l.dateline
                FROM ".TABLE_PREFIX."users u
                LEFT JOIN ".TABLE_PREFIX."inactivity_blacklist_log l ON u.uid = l.uid
                WHERE CONCAT(',', u.additionalgroups, ',') LIKE '%,{$inactive_group_id},%'
                   OR u.usergroup = '{$inactive_group_id}'
                ORDER BY l.dateline ASC
            ");

            if($db->num_rows($query) == 0) {
                $table->construct_cell($lang->inactivity_blacklist_no_users, ['colspan' => 2]);
                $table->construct_row();
            } else {
                while($user = $db->fetch_array($query)) {
                    $profile_link = build_profile_link($user['username'], $user['uid']);
                    $date_added = $user['dateline']? my_date('relative', $user['dateline']) : 'Unbekannt';
                    $table->construct_cell($profile_link);
                    $table->construct_cell($date_added, ["class" => "align_center"]);
                    $table->construct_row();
                }
            }
            $table->output($lang->inactivity_blacklist_overview);
        } else {
            $page->output_inline_error($lang->inactivity_blacklist_no_group_selected);
        }
    }
    $page->output_footer();
}

// #################### MyAlerts Formatierer-Funktionen (NUR EINMAL) ####################

function inactivity_blacklist_register_formatter_back_compat()
{
    if (function_exists('myalerts_info')) {
        $myalerts_info = myalerts_info();
        if (version_compare($myalerts_info['version'], '2.0.4', '<=')) {
            inactivity_blacklist_register_formatter();
        }
    }
}

function inactivity_blacklist_register_formatter()
{
    global $mybb, $lang;
    if (class_exists('MybbStuff_MyAlerts_Formatter_AbstractFormatter') && class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
        $formatterManager = \MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
        if (!$formatterManager) { myalerts_create_instances(); $formatterManager = \MybbStuff_MyAlerts_AlertFormatterManager::getInstance(); }
        if ($formatterManager) {
            // KORREKTUR: Wir registrieren den Formatierer explizit für JEDEN Alert-Typ einzeln.
            $formatterManager->registerFormatter( new InactivityBlacklistAlertFormatter($mybb, $lang, 'inactivity_blacklist_user') );
            $formatterManager->registerFormatter( new InactivityBlacklistAlertFormatter($mybb, $lang, 'inactivity_blacklist_admin') );
        }
    }
}

// #################### MyAlerts Formatierer-Klasse ####################
if (!class_exists('InactivityBlacklistAlertFormatter'))
{
    class InactivityBlacklistAlertFormatter extends \MybbStuff_MyAlerts_Formatter_AbstractFormatter
    {
        public function init() {
            $this->lang->load('inactivity_blacklist');
        }

        public function formatAlert(\MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
        {
            $this->init();
            $alertContent = $alert->getExtraDetails();
            $alertTypeCode = $alert->getType()->getCode();

            if ($alertTypeCode == 'inactivity_blacklist_admin') {
                $moved_user_name = htmlspecialchars_uni($alertContent['moved_username']);
                $moved_user_link = build_profile_link($moved_user_name, (int)$alert->getObjectId());
                $reason = htmlspecialchars_uni($alertContent['reason']);
                return $this->lang->sprintf($this->lang->inactivity_blacklist_alert_admin, $moved_user_link, $reason);
            } 
            elseif ($alertTypeCode == 'inactivity_blacklist_user') {
                $reason = htmlspecialchars_uni($alertContent['reason']);
                return $this->lang->sprintf($this->lang->inactivity_blacklist_alert_user, $reason);
            }
            return "";
        }

        public function buildShowLink(\MybbStuff_MyAlerts_Entity_Alert $alert) {
            global $mybb;
            return $mybb->settings['bburl'] . '/member.php?action=profile&uid=' . (int)$alert->getObjectId();
        }
    }
}
?>