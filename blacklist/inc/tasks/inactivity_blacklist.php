<?php
/**
 * FINALE VERSION mit Multi-Szenen-Prüfung und allen Feinschliffen
 */
function task_inactivity_blacklist($task)
{
    global $mybb, $db, $lang, $plugins;

    // --- MyAlerts Bootstrap-Block ---
    if (!function_exists('myalerts_info')) {
        if (file_exists(MYBB_ROOT . 'inc/plugins/myalerts.php')) { require_once MYBB_ROOT . 'inc/plugins/myalerts.php'; } 
        else { add_task_log($task, 'Konnte myalerts.php nicht finden.'); return; }
    }
    if (function_exists('myalerts_create_instances')) { myalerts_create_instances(); } 
    else { add_task_log($task, 'konnte myalerts_create_instances() nicht ausführen.'); return; }
    // --- Ende Bootstrap ---

    // Lade alle notwendigen Dateien und Einstellungen
    $lang->load('inactivity_blacklist');
    require_once MYBB_ROOT."inc/functions_user.php";
    $settings = $mybb->settings;

    // Lese Einstellungen aus der Datenbank
    $fids_str = $settings['inactivity_blacklist_ingame_fids'];
    $archive_fids_str = $settings['inactivity_blacklist_archive_fids']; // NEU: Archiv-Foren lesen
    $inactive_group_id = (int)$settings['inactivity_blacklist_inactive_group'];
    $applicant_group_id = (int)$settings['inactivity_blacklist_applicant_group'];
    $response_grace_period_days = (int)$settings['inactivity_blacklist_response_grace_period'];
    $post_days = (int)$settings['inactivity_blacklist_post_days'];
    $debug_mode = (int)$settings['inactivity_blacklist_debug_mode'];
    $excluded_uids_str = $settings['inactivity_blacklist_excluded_uids'];
    $post_fid = (int)$settings['inactivity_blacklist_post_fid'];
    $poster_uid = (int)$settings['inactivity_blacklist_poster_uid'];
    $admin_uids_str = $settings['inactivity_blacklist_admin_uids'];

    $excluded_uids = [];
    if (!empty($excluded_uids_str)) {
        $excluded_uids = array_map('intval', explode(',', $excluded_uids_str));
    }
    
    $admin_uids_notify = [];
    if (!empty($admin_uids_str)) {
        $admin_uids_notify = array_map('intval', explode(',', $admin_uids_str));
    }

	// Kritische Fehlerprüfung: Sind die wichtigsten Einstellungen gesetzt?
    if (empty($fids_str) || empty($inactive_group_id)) {
        add_task_log($task, "Fehler: Die Einstellung für 'Inplay-Foren IDs' oder 'Inaktiv-Benutzergruppe' im Admin-CP ist nicht gesetzt.");
        return;
    }

    // Initialisierung der Variablen
    $time_now = time();
    $grace_period_seconds = $grace_period_days * 86400;
    $debug_log = "";
    $moved_to_blacklist = [];
    $ready_for_deletion = [];

    // #################### STUFE 2: LÖSCHKANDIDATEN IDENTIFIZIEREN ####################
    if ($debug_mode) $debug_log .= "--- BEGINNE STUFE 2: PRÜFE LÖSCHKANDIDATEN ---\n";
    $deletion_query = $db->query("
		SELECT u.uid, u.username
		FROM ".TABLE_PREFIX."users u
		LEFT JOIN ".TABLE_PREFIX."inactivity_blacklist_log l ON (u.uid = l.uid) 
		WHERE (CONCAT(',', u.additionalgroups, ',') LIKE '%,{$inactive_group_id},%' OR u.usergroup = '{$inactive_group_id}') 
		AND l.dateline < ($time_now - $grace_period_seconds)"
	);
    while($user_to_delete = $db->fetch_array($deletion_query)) {
        $ready_for_deletion[] = $user_to_delete['username'];
        if ($debug_mode) $debug_log .= "==> BEREIT ZUR LÖSCHUNG: {$user_to_delete['username']} (UID: {$user_to_delete['uid']})\n";
    }

    // #################### STUFE 1: INAKTIVITÄTSKANDIDATEN FINDEN ####################
    if ($debug_mode) $debug_log .= "\n--- BEGINNE STUFE 1: SUCHE INAKTIVITÄTSKANDIDATEN ---\n";
    $time_post_limit = $time_now - ($post_days * 86400);
    $fids_in = "'". implode("','", explode(',', $fids_str)). "'";

    $where_conditions = [];
	$where_conditions[] = "NOT (CONCAT(',', u.additionalgroups, ',') LIKE '%,{$inactive_group_id},%' OR u.usergroup = '{$inactive_group_id}')";

	if ($applicant_group_id > 0) {
    $where_conditions[] = "u.usergroup != '{$applicant_group_id}'";
	}

	$user_query = $db->query("
		SELECT u.uid, u.username, u.usergroup, u.additionalgroups, u.lastactive, u.away, uf.fid7
		FROM ".TABLE_PREFIX."users u
		LEFT JOIN ".TABLE_PREFIX."userfields uf ON (u.uid = uf.ufid) 
		WHERE ".implode(' AND ', $where_conditions)."
	");

    while ($user = $db->fetch_array($user_query)) {
        if (!empty($excluded_uids) && in_array((int)$user['uid'], $excluded_uids, true)) { continue; }
        if ($user['away'] == 1) { if ($debug_mode) { $debug_log .= "-> ÜBERSPRUNGEN: User {$user['username']} ist als abwesend markiert.\n"; } continue; }
        if (isset($user['fid7']) && $user['fid7'] == 'Ja') { if ($debug_mode) { $debug_log .= "-> ÜBERSPRUNGEN: Charakter {$user['username']} ist 'Auf Eis' gelegt.\n"; } continue; }

        if ($debug_mode) $debug_log .= "--- Prüfe Benutzer: {$user['username']} (UID: {$user['uid']}) ---\n";
        
        $is_inactive = false; // Zurücksetzen für jeden User

        $posts_query = $db->simple_select("posts", "tid", "uid = '{$user['uid']}' AND fid IN ({$fids_in})", ['order_by' => 'dateline', 'order_dir' => 'DESC']);
        $user_threads = [];
        while($post = $db->fetch_array($posts_query)) {
            if (!isset($user_threads[$post['tid']])) {
                $user_threads[$post['tid']] = true;
            }
        }

        if (empty($user_threads)) {
            $last_any_post_query = $db->simple_select("posts", "dateline", "uid = '{$user['uid']}'", ['order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => 1]);
            $last_any_post_date = $db->fetch_field($last_any_post_query, "dateline");
            if (!$last_any_post_date || $last_any_post_date < $time_post_limit) {
                $is_inactive = true;
                if ($debug_mode) $debug_log .= "-> INAKTIV: Keine Inplay-Posts und auch sonst keine kürzlichen Posts.\n";
            } else {
                if ($debug_mode) $debug_log .= "-> AKTIV: Keine Inplay-Posts, aber ein anderer Post ist frisch genug.\n";
            }
        } else {
            $is_active_in_any_scene = false;
            foreach (array_keys($user_threads) as $tid) {
                $thread_info = $db->fetch_array($db->simple_select("threads", "lastposteruid, lastpost", "tid = '{$tid}'"));
                if (!$thread_info) continue;
                if ($thread_info['lastposteruid'] == $user['uid'] || $thread_info['lastpost'] > ($time_now - ($response_grace_period_days * 86400))) {
                    $is_active_in_any_scene = true;
                    if ($debug_mode) $debug_log .= "-> Szene {$tid}: AKTIV (letzter Poster oder innerhalb Gnadenfrist).\n";
                    break;
                }
            }
            if (!$is_active_in_any_scene) {
                $is_inactive = true;
                if ($debug_mode) $debug_log .= "-> INAKTIV: In allen Szenen am Zug und Frist abgelaufen.\n";
            }
        }
        
        if ($is_inactive) {
            join_usergroup($user['uid'], $inactive_group_id);
            $db->replace_query("inactivity_blacklist_log", ['uid' => (int)$user['uid'], 'dateline' => $time_now]);
            $moved_to_blacklist[] = $user['username'];
            if ($debug_mode) $debug_log .= "==> AKTION: Verschiebe {$user['username']} in Gruppe {$inactive_group_id}.\n";
            
            if (function_exists('myalerts_info')) {
                $alertManager = \MybbStuff_MyAlerts_AlertManager::getInstance();
                $reason_for_alert = "Automatische Versetzung wegen Inaktivität";

                $userAlertType = \MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('inactivity_blacklist_user');
                if ($userAlertType && $userAlertType->getEnabled()) {
                    $alert = new \MybbStuff_MyAlerts_Entity_Alert((int)$user['uid'], $userAlertType, (int)$user['uid']);
                    $alert->setExtraDetails(['reason' => $reason_for_alert]);
                    $alertManager->addAlert($alert);
                }

                $adminAlertType = \MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('inactivity_blacklist_admin');
                if ($adminAlertType && $adminAlertType->getEnabled() && !empty($admin_uids_notify)) {
                    foreach ($admin_uids_notify as $admin_uid) {
                        if ($admin_uid == $user['uid']) continue;
                        $adminAlert = new \MybbStuff_MyAlerts_Entity_Alert((int)$admin_uid, $adminAlertType, (int)$user['uid']);
                        $adminAlert->setExtraDetails(['reason' => $reason_for_alert, 'moved_username' => $user['username']]);
                        $alertManager->addAlert($adminAlert);
                    }
                }
            }
        }
    } // Ende der while-Schleife
    

    // FORUM-POST ERSTELLEN
    if (!empty($moved_to_blacklist)) {
        if ($post_fid > 0 && $poster_uid > 0) {
            require_once MYBB_ROOT."inc/datahandlers/post.php";
            $posthandler = new PostDataHandler("insert");
            $poster_info = $db->simple_select("users", "username", "uid='{$poster_uid}'", ["limit" => 1]);
            $poster_username = $db->fetch_field($poster_info, "username");
            $formatted_date = my_date("d.m.Y", $time_now);
            $post_title = str_replace('{1}', $formatted_date, $lang->inactivity_blacklist_post_title);
            $post_message = $lang->sprintf($lang->inactivity_blacklist_post_message, implode("\n[*] ", $moved_to_blacklist));
            $post_data = [
                "fid"       => $post_fid,
                "uid"       => $poster_uid,
                "username"  => $poster_username,
                "subject"   => $post_title,
                "message"   => $post_message,
                "dateline"  => $time_now,
                "ipaddress" => serialize(['ip' => '127.0.0.1']),
            ];
            $posthandler->set_data($post_data);
            if($posthandler->validate_thread()) {
                $posthandler->insert_thread();
                if($debug_mode) $debug_log .= "==> AKTION: Blacklist-Post im Forum {$post_fid} erstellt.\n";
            } else {
                if($debug_mode) $debug_log .= "==> FEHLER: Konnte Blacklist-Post nicht erstellen. Fehler: ".htmlspecialchars_uni(print_r($posthandler->get_friendly_errors(), true))."\n";
            }
        }
    }
    
    // LOG-NACHRICHT ERSTELLEN
    $log_message = $lang->sprintf($lang->inactivity_blacklist_task_log_success, count($moved_to_blacklist), count($ready_for_deletion));
    if (!empty($moved_to_blacklist)) {
        $log_message .= "\n". $lang->inactivity_blacklist_task_log_added. ' '. implode(', ', $moved_to_blacklist);
    }
    if (!empty($ready_for_deletion)) {
        $log_message .= "\n". $lang->inactivity_blacklist_task_log_removed. ' '. implode(', ', $ready_for_deletion);
    }
    if ($debug_mode && !empty($debug_log)) {
        $log_message .= "\n\n--- DEBUG-LOG ---\n". $debug_log;
    }
    add_task_log($task, $log_message);
}
?>