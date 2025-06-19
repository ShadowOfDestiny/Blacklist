<?php
/**
 * FINALE VERSION mit KORREKTER PARTNER-LOGIK - 17.06.2025
 */
function task_inactivity_blacklist($task)
{
    global $mybb, $db, $lang;
	
	// --- MyAlerts Bootstrap-Block (KORRIGIERT) ---
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
    $inactive_group_id = (int)$settings['inactivity_blacklist_inactive_group'];
    $grace_period_days = (int)$settings['inactivity_blacklist_grace_period'];
    $post_days = (int)$settings['inactivity_blacklist_post_days'];
    $login_days = (int)$settings['inactivity_blacklist_login_days'];
    $debug_mode = (int)$settings['inactivity_blacklist_debug_mode'];
    $check_partner = (int)$settings['inactivity_blacklist_check_partner'];
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

    // Initialisierung der Variablen (KORRIGIERT)
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
          AND l.dateline < ($time_now - $grace_period_seconds)
    ");
    while($user_to_delete = $db->fetch_array($deletion_query)) {
        $ready_for_deletion[] = $user_to_delete['username']; // KORRIGIERT
        if ($debug_mode) $debug_log .= "==> BEREIT ZUR LÖSCHUNG: {$user_to_delete['username']} (UID: {$user_to_delete['uid']}) - Manuelle Löschung durch Admin erforderlich.\n";
    }

    // #################### STUFE 1: INAKTIVITÄTSKANDIDATEN FINDEN ####################
    if ($debug_mode) $debug_log .= "\n--- BEGINNE STUFE 1: SUCHE INAKTIVITÄTSKANDIDATEN ---\n";
    $time_post_limit = $time_now - ($post_days * 86400);
    $time_login_limit = $time_now - ($login_days * 86400);
    $fids_in = "'". implode("','", explode(',', $fids_str)). "'";

    $user_query = $db->query("
    SELECT u.uid, u.username, u.usergroup, u.additionalgroups, u.lastactive, u.away, uf.fid7
    FROM ".TABLE_PREFIX."users u
    LEFT JOIN ".TABLE_PREFIX."userfields uf ON (u.uid = uf.ufid)
    WHERE NOT (CONCAT(',', u.additionalgroups, ',') LIKE '%,{$inactive_group_id},%' OR u.usergroup = '{$inactive_group_id}')
	");

    while ($user = $db->fetch_array($user_query)) {
        if (!empty($excluded_uids) && in_array((int)$user['uid'], $excluded_uids, true)) {
            continue;
        }
		
		// ########## NEUER BLOCK START ##########
        // Prüfung, ob der Benutzer als abwesend markiert ist.
        if ($user['away'] == 1) {
            if ($debug_mode) {
                $debug_log .= "-> ÜBERSPRUNGEN: User {$user['username']} ist als abwesend markiert.\n";
            }
            continue; // Nächsten Benutzer prüfen
        }
        // ########## NEUER BLOCK ENDE ##########
		
		// ########## NEUER BLOCK START ##########
        // Prüfung auf Profilfeld "Auf Eis" (fid7)
        if (isset($user['fid7']) && $user['fid7'] == 'Ja') {
            if ($debug_mode) {
                $debug_log .= "-> ÜBERSPRUNGEN: Charakter {$user['username']} ist 'Auf Eis' gelegt.\n";
            }
            continue; // Nächsten Benutzer prüfen
        }
        // ########## NEUER BLOCK ENDE ##########

        if ($debug_mode) $debug_log .= "--- Prüfe Benutzer: {$user['username']} (UID: {$user['uid']}) ---\n";

        $is_inactive = false;

        // Finde den letzten Post des Users in den Inplay-Foren
        $post_query = $db->simple_select("posts", "tid, dateline", "uid = '{$user['uid']}' AND fid IN ({$fids_in})", array('order_by' => 'dateline', 'order_dir' => 'DESC', 'limit' => 1));
        $my_last_post = $db->fetch_array($post_query);

        if (!$my_last_post) {
            // User hat keine Posts in Inplay-Foren, prüfe nur Login
            if ($user['lastactive'] < $time_login_limit) {
                $is_inactive = true;
                if ($debug_mode) $debug_log .= "-> Kriterium 'Login-Inaktivität' erfüllt (keine Inplay-Posts vorhanden).\n";
            }
        } else {
            // User hat Posts, wir prüfen die letzte Szene.
            $thread_query = $db->simple_select("threads", "lastposteruid", "tid = '{$my_last_post['tid']}'");
            $last_poster_in_thread_uid = (int)$db->fetch_field($thread_query, "lastposteruid");

            // FALL 2: Der User hat als LETZTES in der Szene gepostet.
            if ($last_poster_in_thread_uid == $user['uid']) {
                // Der User wartet auf eine Antwort. Er ist automatisch AKTIV und wird übersprungen.
                $is_inactive = false;
                if ($debug_mode) $debug_log .= "-> AKTIV: Ist der letzte Poster in der Szene und wartet auf Antwort.\n";
            }
            // FALL 3: Jemand anderes hat nach dem User gepostet.
            else {
                // Jetzt prüfen wir, ob der LETZTE EIGENE POST dieses Users zu alt ist.
                // Es ist egal, wer dran ist. Es zählt nur, wann dieser User zuletzt aktiv war.
                if ($my_last_post['dateline'] < $time_post_limit) {
                    $is_inactive = true;
                    if ($debug_mode) $debug_log .= "-> INAKTIV: Der letzte eigene Post des Users ist älter als die Frist von {$post_days} Tagen.\n";
                } else {
                    // Der eigene Post ist noch frisch, also ist der User aktiv, auch wenn er wartet.
                    $is_inactive = false;
                    if ($debug_mode) $debug_log .= "-> AKTIV: Der letzte eigene Post ist innerhalb der Frist.\n";
                }
            }
        }

        // ########## NEUE, FAIRE LOGIK ENDE ##########

        if ($is_inactive) {
            join_usergroup($user['uid'], $inactive_group_id);
            $db->replace_query("inactivity_blacklist_log", ['uid' => (int)$user['uid'], 'dateline' => $time_now]);
            $moved_to_blacklist[] = $user['username']; // KORRIGIERT
            if ($debug_mode) $debug_log .= "==> AKTION: Verschiebe {$user['username']} in Gruppe {$inactive_group_id} (Wartezimmer).\n";
			
			// #################### MyAlerts-BLOCK nach dem Vorbild von inplayscenes ####################
            
            $reason_for_alert = "Automatische Versetzung wegen Inaktivität";

            // A) Benachrichtigung für den betroffenen Benutzer
            if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
                $alertTypeManager = \MybbStuff_MyAlerts_AlertTypeManager::getInstance();
                $userAlertType = $alertTypeManager->getByCode('inactivity_blacklist_user');

                if ($userAlertType && $userAlertType->getEnabled()) {
                    try {
                        $alert = new \MybbStuff_MyAlerts_Entity_Alert((int)$user['uid'], $userAlertType, (int)$user['uid']);
                        $alert->setExtraDetails(['reason' => $reason_for_alert]);
                        \MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
                    } catch (\Exception $e) {}
                }
            }

            // B) Benachrichtigungen für alle Admins
            if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
                if (!empty($admin_uids_notify)) {
                    $alertTypeManager = \MybbStuff_MyAlerts_AlertTypeManager::getInstance();
                    $adminAlertType = $alertTypeManager->getByCode('inactivity_blacklist_admin');

                    if ($adminAlertType && $adminAlertType->getEnabled()) {
                        foreach ($admin_uids_notify as $admin_uid) {
                            if ($admin_uid == $user['uid']) continue;
                            try {
                                $adminAlert = new \MybbStuff_MyAlerts_Entity_Alert((int)$admin_uid, $adminAlertType, (int)$user['uid']);
                                $adminAlert->setExtraDetails([
                                    'reason' => $reason_for_alert,
                                    'moved_username' => $user['username']
                                ]);
                                \MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($adminAlert);
                            } catch (\Exception $e) {}
                        }
                    }
                }
            }
            // #################### ENDE: MyAlerts-BLOCK ####################
        }
    }
        
    

    // #################### FORUM-POST ERSTELLEN ####################
	
    if (!empty($moved_to_blacklist)) 
	{
        if ($post_fid > 0 && $poster_uid > 0) 
		{
            require_once MYBB_ROOT."inc/datahandlers/post.php";
            $posthandler = new PostDataHandler("insert");

            $poster_info = $db->simple_select("users", "username", "uid='{$poster_uid}'", ["limit" => 1]);
            $poster_username = $db->fetch_field($poster_info, "username");

            $post_title = $lang->sprintf($lang->inactivity_blacklist_post_title, my_date($mybb->settings['dateformat']));
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
    // #################### LOG-NACHRICHT ERSTELLEN ####################
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