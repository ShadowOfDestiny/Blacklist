<?php
/**
 * Deutsche Admin-Sprachdatei für das Inaktivitäts-Blacklist Plugin
 */

// Texte für die Admin-CP Übersichtsseite und Menü
$l['inactivity_blacklist_menu'] = "Inaktivitäts-Blacklist";
$l['inactivity_blacklist_overview'] = "Übersicht";
$l['inactivity_blacklist_overview_desc'] = "Zeigt alle Benutzer an, die sich aktuell im 'Wartezimmer' (Inaktiv-Gruppe) befinden.";
$l['inactivity_blacklist_settings'] = "Einstellungen";
$l['inactivity_blacklist_settings_desc'] = "Einstellungen für das Inaktivitäts-Plugin ändern.";
$l['inactivity_blacklist_date_added'] = "Im Wartezimmer seit";
$l['inactivity_blacklist_no_users'] = "Aktuell befinden sich keine Benutzer im Wartezimmer.";
$l['inactivity_blacklist_no_group_selected'] = "Bitte wähle zuerst in den Plugin-Einstellungen eine 'Inaktiv-Benutzergruppe' aus.";
$l['inactivity_blacklist_remove_col'] = "Entfernen";
$l['inactivity_blacklist_reason'] = "Grund"; // Aktuell noch nicht befüllt vom Task
$l['inactivity_blacklist_remove_selected'] = "Ausgewählte entfernen";
$l['inactivity_blacklist_users_removed_success'] = "Folgende Benutzer wurden aus dem Wartezimmer entfernt: {1}";
$l['inactivity_blacklist_no_users_removed'] = "Es wurden keine Benutzer aus dem Wartezimmer entfernt.";
$l['inactivity_blacklist_no_users_selected'] = "Bitte wähle Benutzer zum Entfernen aus.";

// Für die Settings-Gruppe (werden im ACP geladen)
$l['settinggroup_inactivity_blacklist'] = "Inaktivitäts-Blacklist Einstellungen";
$l['settinggroup_inactivity_blacklist_desc'] = "Einstellungen für das Inaktivitäts-Plugin.";

// Für die einzelnen Einstellungen (werden im ACP geladen)
$l['setting_inactivity_blacklist_ingame_fids'] = "Inplay-Foren IDs";
$l['setting_inactivity_blacklist_ingame_fids_desc'] = "Gib hier die IDs der Foren an, die als Inplay-Bereiche gelten sollen (durch Komma getrennt).";
$l['setting_inactivity_blacklist_archive_fids'] = "Archiv-Foren IDs";
$l['setting_inactivity_blacklist_archive_fids_desc'] = "Gib hier die IDs der Foren an, die als Archiv-Foren gelten sollen (durch Komma getrennt).";
$l['setting_inactivity_blacklist_post_days'] = "Inaktiv nach X Tagen (Post)";
$l['setting_inactivity_blacklist_post_days_desc'] = "Anzahl der Tage, nach denen ein fehlender Beitrag im Inplay zur Inaktivität führt.";
$l['setting_inactivity_blacklist_login_days'] = "Inaktiv nach X Tagen (Login)";
$l['setting_inactivity_blacklist_login_days_desc'] = "Anzahl der Tage, nach denen ein fehlender Login zur Inaktivität führt.";
$l['setting_inactivity_blacklist_excluded_uids'] = "Ausgenommene Benutzer IDs";
$l['setting_inactivity_blacklist_excluded_uids_desc'] = "Benutzer-IDs, die von der Prüfung immer ausgenommen werden (durch Komma getrennt).";
$l['setting_inactivity_blacklist_post_fid'] = "Forum ID für Blacklist-Post";
$l['setting_inactivity_blacklist_post_fid_desc'] = "Die ID des Forums, in dem die öffentliche Blacklist gepostet werden soll.";
$l['setting_inactivity_blacklist_poster_uid'] = "Benutzer ID für Blacklist-Post";
$l['setting_inactivity_blacklist_poster_uid_desc'] = "Die ID des Benutzers, der den öffentlichen Blacklist-Post erstellen soll (z.B. ein NPC-Account).";
$l['setting_inactivity_blacklist_admin_uids'] = "Admin UIDs"; // Dieser Name war in deinem Code, obwohl er nicht benutzt wird
$l['setting_inactivity_blacklist_admin_uids_desc'] = "UIDs von Admins, falls speziell ausgeschlossen."; // Beschreibung dazu
$l['setting_inactivity_blacklist_inactive_group'] = "Gruppe der inaktiven User";
$l['setting_inactivity_blacklist_inactive_group_desc'] = "In diese Gruppe werden inaktive User verschoben";
$l['setting_inactivity_blacklist_grace_period'] = "Löschung nach X Tagen.";
$l['setting_inactivity_blacklist_inactive_group'] = "Gruppe der inaktiven User";
$l['setting_inactivity_blacklist_inactive_group_desc'] = "In diese Gruppe werden inaktive User verschoben";
$l['setting_inactivity_blacklist_check_partner'] = "Partner-Check.";
$l['setting_inactivity_blacklist_check_partner_desc'] = "Einstellen, ob nach Postpartnern gesucht werden soll";
$l['setting_inactivity_blacklist_debug_mode'] = "Debug-Modus aktivieren?";
$l['setting_inactivity_blacklist_debug_mode_desc'] = "Wenn Ja, schreibt der Task sehr detaillierte Logs, um bei der Fehlersuche zu helfen.";

// NEU: Texte für den Blacklist-Post im Forum
$l['inactivity_blacklist_post_title'] = "Inaktivitäts-Update vom {1}";
$l['inactivity_blacklist_post_message'] = "Folgende Benutzer wurden aufgrund von Inaktivität ins Wartezimmer verschoben und haben nun zwei Wochen Zeit, sich zurückzumelden, bevor ihr Account zur Löschung vorgeschlagen wird:\n\n[list]\n[*]{1}[/list]";

// MyAlerts (falls genutzt)
$l['myalerts_inactivity_blacklist_admin'] = "Neue Benutzer im Wartezimmer: {1}";
// NEU: Text für den Alert an den Benutzer
$l['myalerts_inactivity_blacklist_user'] = "Du bist auf der Blacklist und hast zwei Wochen Zeit, um wieder aktiv am Forengeschehen teilzunehmen.";

$l['inactivity_blacklist_alert_user'] = 'Du wurdest aufgrund von Inaktivität auf die Blacklist gesetzt. Grund: %s';
$l['inactivity_blacklist_alert_admin'] = 'Der Benutzer %s wurde aufgrund von Inaktivität auf die Blacklist gesetzt. Grund: %s';

// Deine bestehenden Sprachvariablen für den Task und die Einstellungen
$l['inactivity_blacklist_task_name'] = 'Inaktivitäts-Blacklist Prüfung';
$l['inactivity_blacklist_task_desc'] = 'Prüft auf inaktive Benutzer und setzt sie auf die Blacklist.';
$l['inactivity_blacklist_task_log_success'] = 'Inactivity Blacklist Task erfolgreich ausgeführt. {1} Benutzer auf die Blacklist gesetzt, {2} zur Löschung markiert.';
$l['inactivity_blacklist_task_log_added'] = 'Auf die Blacklist gesetzt:';
$l['inactivity_blacklist_task_log_removed'] = 'Zur Löschung markiert:';

$l['task_inactivity_blacklist_log'] = 'Inactivity Blacklist Task erfolgreich ausgeführt. {1} Benutzer benachrichtigt.';
$l['task_inactivity_blacklist_log_no_users'] = 'Inactivity Blacklist Task ausgeführt. Keine inaktiven Benutzer zum Benachrichtigen gefunden.';

// Fügen Sie hier auch Ihre bestehenden Sprachvariablen für das ACP hinzu
$l['inactivity_blacklist_task_name'] = 'Inaktivitäts-Prüfung';
$l['inactivity_blacklist_task_desc'] = 'Prüft auf inaktive Benutzer und setzt sie auf die Blacklist.';

?>