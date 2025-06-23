<?php
/**
 * Deutsche Frontend-Sprachdatei für das Inaktivitäts-Blacklist Plugin
 */

// NEU: Texte für den Blacklist-Post im Forum
$l['inactivity_blacklist_post_title'] = "Inaktivitäts-Update von {1}";
$l['inactivity_blacklist_post_message'] = "Folgende Benutzer wurden aufgrund von Inaktivität ins Wartezimmer verschoben und haben nun zwei Wochen Zeit, sich zurückzumelden, bevor ihr Account zur Löschung vorgeschlagen wird:\n\n[list]\n[*]{1}[/list]";


// Für MyAlerts (falls genutzt und im Frontend angezeigt)
$l['myalerts_inactivity_blacklist_user'] = "Du wurdest aufgrund von Inaktivität ins Wartezimmer verschoben. Grund: %s";
$l['inactivity_blacklist_reason_user_moved'] = "Inaktivität festgestellt"; // Standard-Grund für den User Alert

$l['inactivity_blacklist_alert_user'] = 'Du wurdest aufgrund von Inaktivität auf die Blacklist gesetzt. Grund: {1}';
$l['inactivity_blacklist_alert_admin'] = '{1} wurde aufgrund von Inaktivität auf die Blacklist gesetzt. Grund: {2}';

// Deine bestehenden Sprachvariablen für den Task und die Einstellungen
$l['inactivity_blacklist_task_name'] = 'Inaktivitäts-Blacklist Prüfung';
$l['inactivity_blacklist_task_desc'] = 'Prüft auf inaktive Benutzer und setzt sie auf die Blacklist.';
$l['inactivity_blacklist_task_log_success'] = 'Inactivity Blacklist Task erfolgreich ausgeführt. {1} Benutzer auf die Blacklist gesetzt, {2} zur Löschung markiert.';
$l['inactivity_blacklist_task_log_added'] = 'Auf die Blacklist gesetzt:';
$l['inactivity_blacklist_task_log_removed'] = 'Zur Löschung markiert:';