<?php
// Direktzugriff auf die Datei aus Sicherheitsgründen sperren
if(!defined("IN_MYBB")){
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// HOOKS
$plugins->add_hook('admin_config_settings_change', 'lexicon_settings_change');
$plugins->add_hook('admin_settings_print_peekers', 'lexicon_settings_peek');
$plugins->add_hook("admin_rpgstuff_action_handler", "lexicon_admin_rpgstuff_action_handler");
$plugins->add_hook("admin_rpgstuff_menu_updates", "lexicon_admin_rpgstuff_menu_updates");
$plugins->add_hook("admin_load", "lexicon_admin_manage");
$plugins->add_hook('admin_rpgstuff_update_stylesheet', 'lexicon_admin_update_stylesheet');
$plugins->add_hook('admin_rpgstuff_update_plugin', 'lexicon_admin_update_plugin');
$plugins->add_hook('global_intermediate', 'lexicon_global');
$plugins->add_hook('modcp_nav', 'lexicon_modcp_nav');
$plugins->add_hook("modcp_start", "lexicon_modcp");
$plugins->add_hook("fetch_wol_activity_end", "lexicon_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "lexicon_online_location");
if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
	$plugins->add_hook("global_start", "lexicon_myalert_alerts");
}
 
// Die Informationen, die im Pluginmanager angezeigt werden
function lexicon_info(){
	return array(
		"name"		=> "Boardinternes Lexikon",
		"description"	=> "Fügt dem Forum ein eigenes Lexikon hinzu. Dies kann auf der Seite /lexicon.php aufgerufen werden.",
		"website"	=> "hhttps://github.com/little-evil-genius/Lexikon",
		"author"	=> "little.evil.genius",
		"authorsite"	=> "https://storming-gates.de/member.php?action=profile&uid=1712",
		"version"	=> "1.2.4",
		"compatibility" => "18*"
	);
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin installiert wird (optional).
function lexicon_install(){
    
    global $db, $cache, $mybb;

    // RPG Stuff Modul muss vorhanden sein
    if (!file_exists(MYBB_ADMIN_DIR."/modules/rpgstuff/module_meta.php")) {
		flash_message("Das ACP Modul <a href=\"https://github.com/little-evil-genius/rpgstuff_modul\" target=\"_blank\">\"RPG Stuff\"</a> muss vorhanden sein!", 'error');
		admin_redirect('index.php?module=config-plugins');
	}

	// DATENBANKTABELLEN UND FELDER
    lexicon_database();

	// EINSTELLUNGEN HINZUFÜGEN
	$maxdisporder = $db->fetch_field($db->query("SELECT MAX(disporder) FROM ".TABLE_PREFIX."settinggroups"), "MAX(disporder)");
	$setting_group = array(
		'name'          => 'lexicon',
		'title'         => 'Boardinternes Lexikon',
		'description'   => 'Einstellungen für das Lexikon',
		'disporder'     => $maxdisporder+1,
		'isdefault'     => 0
	);	
	$db->insert_query("settinggroups", $setting_group); 
		
    lexicon_settings();
	rebuild_settings();

    // STYLESHEET HINZUFÜGEN
	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
    // Funktion
    $css = lexicon_stylesheet();
    $sid = $db->insert_query("themestylesheets", $css);
	$db->update_query("themestylesheets", array("cachefile" => "lexicon.css"), "sid = '".$sid."'", 1);

	$tids = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($tids)) {
		update_theme_stylesheet_list($theme['tid']);
	}  

	// TEMPLATES ERSTELLEN
	// Template Gruppe für jedes Design erstellen
    $templategroup = array(
        "prefix" => "lexicon",
        "title" => $db->escape_string("Lexikon"),
    );
    $db->insert_query("templategroups", $templategroup);
    lexicon_templates();

}
 
// Funktion zur Überprüfung des Installationsstatus; liefert true zurürck, wenn Plugin installiert, sonst false (optional).
function lexicon_is_installed(){
	global $db, $mybb;

    if ($db->table_exists("lexicon_categories")) {
        return true;
    }
    return false;
} 
 
// Diese Funktion wird aufgerufen, wenn das Plugin deinstalliert wird (optional).
function lexicon_uninstall(){
	global $db, $cache;

    //DATENBANKEN LÖSCHEN
    if($db->table_exists("lexicon_categories"))
    {
        $db->drop_table("lexicon_categories");
    }
    if($db->table_exists("lexicon_entries"))
    {
        $db->drop_table("lexicon_entries");
    }
    
    // EINSTELLUNGEN LÖSCHEN
    $db->delete_query('settings', "name LIKE 'lexicon%'");
    $db->delete_query('settinggroups', "name = 'lexicon'");

    rebuild_settings();

    // TEMPLATGRUPPE LÖSCHEN
    $db->delete_query("templategroups", "prefix = 'lexicon'");

    // TEMPLATES LÖSCHEN
    $db->delete_query("templates", "title LIKE '%lexicon%'");

	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    // STYLESHEET ENTFERNEN
	$db->delete_query("themestylesheets", "name = 'lexicon.css'");
	$query = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($query)) {
		update_theme_stylesheet_list($theme['tid']);
	}
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin aktiviert wird.
function lexicon_activate(){

	global $db, $cache;

    require MYBB_ROOT."/inc/adminfunctions_templates.php";

	// VARIABLEN EINFÜGEN
	find_replace_templatesets('header', '#'.preg_quote('{$bbclosedwarning}').'#', '{$lexikon_newentry} {$bbclosedwarning}');
	find_replace_templatesets("header", "#".preg_quote('{$menu_memberlist}')."#i", '{$menu_memberlist}{$menu_lexicon}');
	find_replace_templatesets('modcp_nav_users', '#'.preg_quote('{$nav_ipsearch}').'#', '{$nav_ipsearch} {$nav_lexicon}');
	
	// MyALERTS STUFF
	if(class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
		$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

		if (!$alertTypeManager) {
			$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
		}

        // Alert fürs Annehmen
		$alertType = new MybbStuff_MyAlerts_Entity_AlertType();
		$alertType->setCode('lexicon_accept'); // The codename for your alert type. Can be any unique string.
		$alertType->setEnabled(true);
		$alertType->setCanBeUserDisabled(true);

		$alertTypeManager->add($alertType);

        // Alert fürs Ablehnen
        $alertType = new MybbStuff_MyAlerts_Entity_AlertType();
		$alertType->setCode('lexicon_delete'); // The codename for your alert type. Can be any unique string.
		$alertType->setEnabled(true);
		$alertType->setCanBeUserDisabled(true);

		$alertTypeManager->add($alertType);
    }
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin deaktiviert wird.
function lexicon_deactivate(){

	global $db, $cache;

    require MYBB_ROOT."/inc/adminfunctions_templates.php";

    // VARIABLEN ENTFERNEN
	find_replace_templatesets("header", "#".preg_quote('{$lexikon_newentry}')."#i", '', 0);
	find_replace_templatesets("header", "#".preg_quote('{$menu_lexicon}')."#i", '', 0);
    find_replace_templatesets("modcp_nav_users", "#".preg_quote('{$nav_lexicon}')."#i", '', 0);

    // MyALERT STUFF
    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
		$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

		if (!$alertTypeManager) {
			$alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
		}

		$alertTypeManager->deleteByCode('lexicon_delete');
        $alertTypeManager->deleteByCode('lexicon_accept');
	}
}

#####################################
### THE BIG MAGIC - THE FUNCTIONS ###
#####################################

// ADMIN-CP PEEKER
function lexicon_settings_change(){
    
    global $db, $mybb, $lexicon_settings_peeker;

    $result = $db->simple_select('settinggroups', 'gid', "name='lexicon'", array("limit" => 1));
    $group = $db->fetch_array($result);
    $lexicon_settings_peeker = ($mybb->get_input('gid') == $group['gid']) && ($mybb->request_method != 'post');
}
function lexicon_settings_peek(&$peekers){

    global $mybb, $lexicon_settings_peeker;

	if ($lexicon_settings_peeker) {
        $peekers[] = 'new Peeker($(".setting_lexicon_user_accepted"), $("#row_setting_lexicon_user_edit, #row_setting_lexicon_user_delete"),/1/,true)';

		// Überprüfe ob MybbStuff_MyAlerts_AlertTypeManager existiert
        if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
            $peekers[] = 'new Peeker($(".setting_lexicon_user_alert"), $("#row_setting_lexicon_user_alert"),/1/,true)';
        } else {
            echo '<script type="text/javascript">
                $(document).ready(function() {
                    $("#row_setting_lexicon_user_alert").hide();
                });
            </script>';
        }
	}
}

// ADMIN BEREICH - KONFIGURATION //

// action handler fürs acp konfigurieren
function lexicon_admin_rpgstuff_action_handler(&$actions) {
	$actions['lexicon_transfer'] = array('active' => 'lexicon_transfer', 'file' => 'lexicon_transfer');
}

// im Menü einfügen [Übertragen]
function lexicon_admin_rpgstuff_menu_updates(&$sub_menu) {

	global $mybb, $lang, $db;

    if ($db->table_exists("wiki_categories") AND $db->table_exists("wiki_entries")) {
        $sub_menu[] = [
            "id" => "lexicon_transfer",
            "title" => "Wiki-Daten übertragen",
            "link" => "index.php?module=rpgstuff-lexicon_transfer"
        ];
    }
}

// die Seite zum Übertragen
function lexicon_admin_manage() {

	global $mybb, $db, $lang, $page, $run_module, $action_file, $cache;

    if ($page->active_action != 'lexicon_transfer') {
		return false;
	}

    if ($run_module == 'rpgstuff' && $action_file == 'lexicon_transfer') {

        // Add to page navigation
        $page->add_breadcrumb_item("Wiki-Daten übertragen", "index.php?module=rpgstuff-lexicon_transfer");

		if ($mybb->get_input('action') == "" || !$mybb->get_input('action')) {

            $page->output_header("Wiki-Daten ins Lexikon übertragen");
    
            if ($mybb->request_method == 'post') {

				if (lexicon_columnExists("wiki_categories", "sort")) {
					// Code, falls die Spalte 'sort' existiert
					$db->query("INSERT INTO ".TABLE_PREFIX."lexicon_categories (cid, categoryname, sort) SELECT cid, category, sort FROM ".TABLE_PREFIX."wiki_categories");
				} else {
					// Code, falls die Spalte 'sort' nicht existiert
					$db->query("INSERT INTO ".TABLE_PREFIX."lexicon_categories (cid, categoryname, sort) SELECT cid, category, 0 AS sort FROM ".TABLE_PREFIX."wiki_categories");
				}

				if (lexicon_columnExists("wiki_entries", "sort")) {
					// Code, falls die Spalte 'sort' existiert
					$db->query("INSERT INTO ".TABLE_PREFIX."lexicon_entries (eid, cid, linktitle, link, externallink, title, entrytext, sort, parentlist, uid, accepted) SELECT wid, cid, linktitle, link, '', title, wikitext, sort, '0', uid, accepted FROM ".TABLE_PREFIX."wiki_entries");
				} else {
					// Code, falls die Spalte 'sort' nicht existiert
					$db->query("INSERT INTO ".TABLE_PREFIX."lexicon_entries (eid, cid, linktitle, link, externallink, title, entrytext, sort, parentlist, uid, accepted) SELECT wid, cid, linktitle, link, '', title, wikitext, 0, '0', uid, accepted FROM ".TABLE_PREFIX."wiki_entries");
				}
		    
				// Log admin action                   
				log_admin_action("Wiki-Daten übertragen");
        
				flash_message("Alle Kategorien und die entsprechenden Einträge wurden erfolgreich übertragen. Du kannst nun das Wiki-Plugin entfernen.", 'success');
				admin_redirect("index.php?module=rpgstuff-lexicon_transfer");
			}
			
			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}
    
            $form = new Form("index.php?module=rpgstuff-lexicon_transfer", "post", "", 1);
            $form_container = new FormContainer("Wiki-Daten ins Lexikon übertragen");
            echo $form->generate_hidden_field("my_post_key", $mybb->post_code);
  
            $lexicon_categories = $db->fetch_field($db->query("SELECT cid FROM ".TABLE_PREFIX."lexicon_categories"), "cid");

            if ($lexicon_categories == 0) {

				$form_container->output_row(
					"Datein importieren von Ales Wiki Plugin in das Lexikon",
					"Mit einem einfachen Klick können alle Kategorien und die entsprechenden Einträge vom Wiki-Plugin von Ales in das Lexikon übertragen werden."
				);

                $form_container->end();
                $buttons[] = $form->generate_submit_button("Daten übertragen");
                $form->output_submit_wrapper($buttons);
            } else {
                $form_container->output_cell("Es bestehen schon Kategorien und Einträge, weswegen keine Übertragung möglich ist.", array('style' => 'text-align: center;'));
                $form_container->construct_row();

                $form_container->end();
            }

            $form->end();
            $page->output_footer();
            exit;
		}
	}
}

// Stylesheet zum Master Style hinzufügen
function lexicon_admin_update_stylesheet(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_stylesheet_updates');

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    // HINZUFÜGEN
    if ($mybb->input['action'] == 'add_master' AND $mybb->get_input('plugin') == "lexicon") {

        $css = lexicon_stylesheet();
        
        $sid = $db->insert_query("themestylesheets", $css);
        $db->update_query("themestylesheets", array("cachefile" => "lexicon.css"), "sid = '".$sid."'", 1);
    
        $tids = $db->simple_select("themes", "tid");
        while($theme = $db->fetch_array($tids)) {
            update_theme_stylesheet_list($theme['tid']);
        } 

        flash_message($lang->stylesheets_flash, "success");
        admin_redirect("index.php?module=rpgstuff-stylesheet_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Boardinternes Lexikon")."</b>", array('width' => '70%'));

    // Ob im Master Style vorhanden
    $master_check = $db->fetch_field($db->query("SELECT tid FROM ".TABLE_PREFIX."themestylesheets 
    WHERE name = 'lexicon.css' 
    AND tid = 1
    "), "tid");
    
    if (!empty($master_check)) {
        $masterstyle = true;
    } else {
        $masterstyle = false;
    }

    if (!empty($masterstyle)) {
        $table->construct_cell($lang->stylesheets_masterstyle, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-stylesheet_updates&action=add_master&plugin=lexicon\">".$lang->stylesheets_add."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// Plugin Update
function lexicon_admin_update_plugin(&$table) {

    global $db, $mybb, $lang;
	
    $lang->load('rpgstuff_plugin_updates');

    // UPDATE
    if ($mybb->input['action'] == 'add_update' AND $mybb->get_input('plugin') == "lexicon") {

        // Einstellungen überprüfen => Type = update
        lexicon_settings('update');
        rebuild_settings();

        // Templates 
        lexicon_templates('update');

        // Stylesheet
        $update_data = lexicon_stylesheet_update();
        $update_stylesheet = $update_data['stylesheet'];
        $update_string = $update_data['update_string'];
        if (!empty($update_string)) {

            // Ob im Master Style die Überprüfung vorhanden ist
            $masterstylesheet = $db->fetch_field($db->query("SELECT stylesheet FROM ".TABLE_PREFIX."themestylesheets WHERE tid = 1 AND name = 'lexicon.css'"), "stylesheet");
            $masterstylesheet = (string)($masterstylesheet ?? '');
            $update_string = (string)($update_string ?? '');
            $pos = strpos($masterstylesheet, $update_string);
            if ($pos === false) { // nicht vorhanden 
            
                $theme_query = $db->simple_select('themes', 'tid, name');
                while ($theme = $db->fetch_array($theme_query)) {
        
                    $stylesheet_query = $db->simple_select("themestylesheets", "*", "name='".$db->escape_string('lexicon.css')."' AND tid = ".$theme['tid']);
                    $stylesheet = $db->fetch_array($stylesheet_query);
        
                    if ($stylesheet) {

                        require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
        
                        $sid = $stylesheet['sid'];
            
                        $updated_stylesheet = array(
                            "cachefile" => $db->escape_string($stylesheet['name']),
                            "stylesheet" => $db->escape_string($stylesheet['stylesheet']."\n\n".$update_stylesheet),
                            "lastmodified" => TIME_NOW
                        );
            
                        $db->update_query("themestylesheets", $updated_stylesheet, "sid='".$sid."'");
            
                        if(!cache_stylesheet($theme['tid'], $stylesheet['name'], $updated_stylesheet['stylesheet'])) {
                            $db->update_query("themestylesheets", array('cachefile' => "css.php?stylesheet=".$sid), "sid='".$sid."'", 1);
                        }
            
                        update_theme_stylesheet_list($theme['tid']);
                    }
                }
            } 
        }

        // Datenbanktabellen & Felder
        lexicon_database();

        // Collation prüfen und korrigieren
        $charset = 'utf8mb4';
        $collation = 'utf8mb4_unicode_ci';

        $collation_string = $db->build_create_table_collation();
        if (preg_match('/CHARACTER SET ([^\s]+)\s+COLLATE ([^\s]+)/i', $collation_string, $matches)) {
            $charset = $matches[1];
            $collation = $matches[2];
        }

        $databaseTables = [
            "lexicon_categories",
            "lexicon_entries"
        ];

        foreach ($databaseTables as $databaseTable) {
            if ($db->table_exists($databaseTable)) {
                $table = TABLE_PREFIX.$databaseTable;

                $query = $db->query("SHOW TABLE STATUS LIKE '".$db->escape_string($table)."'");
                $table_status = $db->fetch_array($query);
                $actual_collation = strtolower($table_status['Collation'] ?? '');

                $actual_collation = str_replace('utf8mb3', 'utf8', $actual_collation);
                $expected_collation = str_replace('utf8mb3', 'utf8', $collation);

                if (!empty($collation) && $actual_collation !== $expected_collation) {
                    $db->query("ALTER TABLE {$table} CONVERT TO CHARACTER SET {$charset} COLLATE {$collation}");
                }
            }
        }

        flash_message($lang->plugins_flash, "success");
        admin_redirect("index.php?module=rpgstuff-plugin_updates");
    }

    // Zelle mit dem Namen des Themes
    $table->construct_cell("<b>".htmlspecialchars_uni("Boardinternes Lexikon")."</b>", array('width' => '70%'));

    // Überprüfen, ob Update erledigt
    $update_check = lexicon_is_updated();

    if (!empty($update_check)) {
        $table->construct_cell($lang->plugins_actual, array('class' => 'align_center'));
    } else {
        $table->construct_cell("<a href=\"index.php?module=rpgstuff-plugin_updates&action=add_update&plugin=lexicon\">".$lang->plugins_update."</a>", array('class' => 'align_center'));
    }
    
    $table->construct_row();
}

// TEAMHINWEIS
function lexicon_global() {

    global $db, $cache, $mybb, $templates, $lang, $lexikon_newentry, $menu_lexicon, $newentry_notice;
	
	// SPRACHDATEI
	$lang->load('lexicon');

	// EINSTELLUNGEN
	$user_accepted_setting = $mybb->settings['lexicon_user_accepted'];

	// MENU LINK
	eval("\$menu_lexicon = \"".$templates->get("lexicon_header_link")."\";");

	// TEAMHINWEIS => User einträge müssen überprüft werden
	if ($user_accepted_setting == 1) {
		$countentries = $db->num_rows($db->query("SELECT eid FROM ".TABLE_PREFIX."lexicon_entries WHERE accepted = '0'"));
		if ($mybb->usergroup['canmodcp'] == "1" && $countentries > 0) {
			if ($countentries == "1") {   
				$newentry_notice = $lang->sprintf($lang->lexicon_header_banner, 'liegt', 'ein', 'neuer', 'Eintrag');
			} elseif ($countentries > "1") {
				$newentry_notice = $lang->sprintf($lang->lexicon_header_banner, 'liegen', $countentries, 'neue', 'Einträge');
			}
			eval("\$lexikon_newentry = \"".$templates->get("lexicon_header_banner")."\";");
		} else {
			$lexikon_newentry = "";
		}
	} else {
		$lexikon_newentry = "";
	}
}

// MOD-CP - NAVIGATION
function lexicon_modcp_nav() {

    global $db, $mybb, $templates, $theme, $header, $headerinclude, $footer, $lang, $modcp_nav, $nav_lexicon;

	// SPRACHDATEI
	$lang->load('lexicon');

	// EINSTELLUNGEN ZIEHEN
	$user_accepted_setting = $mybb->settings['lexicon_user_accepted'];
    
	if ($user_accepted_setting == 1) {
		eval("\$nav_lexicon = \"".$templates->get ("lexicon_modcp_nav")."\";");
	}  else {
		$nav_lexicon = "";
	}
}

// MOD-CP - SEITE
function lexicon_modcp() {
   
    global $mybb, $templates, $lang, $theme, $header, $headerinclude, $footer, $db, $page, $modcp_nav, $text_options, $modcp_control_bit, $codebuttons;

    // DAMIT DIE PN SACHE FUNKTIONIERT
    require_once MYBB_ROOT."inc/datahandlers/pm.php";
    $pmhandler = new PMDataHandler();

	// EINSTELLUNGEN ZIEHEN
	$alertsystem = $mybb->settings['lexicon_user_alert'];
	$user_accepted_setting = $mybb->settings['lexicon_user_accepted'];
	$lexicon_sort_entry_setting = $mybb->settings['lexicon_sort_entry'];
	$lexicon_sub_setting = $mybb->settings['lexicon_sub'];

	// SPRACHDATEI
	$lang->load('lexicon');

	// PARSER - HTML und CO erlauben
	require_once MYBB_ROOT."inc/class_parser.php";;
	$parser = new postParser;
	$text_options = array(
		"allow_html" => 1,
		"allow_mycode" => 1,
		"allow_smilies" => 1,
		"allow_imgcode" => 1,
		"filter_badwords" => 0,
		"nl2br" => 1,
		"allow_videocode" => 0
	);

	// Freischalten/Ablehnen
    if($mybb->get_input('action') == 'lexicon') {

		if ($user_accepted_setting != 1) {
			redirect('modcp.php', $lang->lexicon_redirect_modcp);
		}

        // Add a breadcrumb
        add_breadcrumb($lang->nav_modcp, "modcp.php");
        add_breadcrumb($lang->lexicon_modcp, "modcp.php?action=lexicon");

		$modcp_query = $db->query("SELECT * FROM ".TABLE_PREFIX."lexicon_entries
		WHERE accepted = '0'
        ORDER BY linktitle ASC
        ");

		$modcp_control_none = $lang->lexicon_modcp_control_none;
        while($modcp = $db->fetch_array($modcp_query)) {
			$modcp_control_none = "";
   
			// Leer laufen lassen  
			$eid = "";
			$cid = "";
			$linktitle = "";
			$link = "";
			$title = "";
			$entrytext = "";
			$externallink = "";
			$cat = "";
	
			// Mit Infos füllen   
			$eid = $modcp['eid'];
			$cid = $modcp['cid'];
			$linktitle = $modcp['linktitle'];
			$externallink = $modcp['externallink'];

			$cat = $db->fetch_field($db->simple_select("lexicon_categories", "categoryname", "cid = '".$cid."'"), "categoryname");

			if($externallink != "") {
				$title = $linktitle;
				$link = $externallink;
				$entrytext = $lang->sprintf($lang->lexicon_modcp_externallink, $externallink);
			} else {
				$title = $modcp['title'];
				$link = "lexicon.php?page=".$modcp['link'];
				$entrytext = $parser->parse_message($modcp['entrytext'], $text_options);
			}
   
            // User der das eingesendet hat
            $modcp['uid'] = htmlspecialchars_uni($modcp['uid']);
            $user = get_user($modcp['uid']);
            $user['username'] = htmlspecialchars_uni($user['username']);
            $createdby = build_profile_link($user['username'], $modcp['uid']);

			// Unterbeitrag
			if ($mybb->settings['lexicon_sub'] == 1 AND $modcp['parentlist'] != 0) {
				$parentname = $db->fetch_field($db->simple_select("lexicon_entries", "linktitle", "eid = '".$modcp['parentlist']."'"), "linktitle");
				$path = $cat."&nbsp;»&nbsp;".$parentname."&nbsp;»&nbsp;<b>".$linktitle."</b>";
			} else {
				$path = $cat."&nbsp;»&nbsp;<b>".$linktitle."</b>";
			}
   
            eval("\$modcp_control_bit .= \"".$templates->get("lexicon_modcp_bit")."\";");
        }

        $team_uid = $mybb->user['uid'];

		//Der Eintrag wurde vom Team abgelehnt
        if($delete = $mybb->get_input('delete')){

			$titel = $db->fetch_field($db->simple_select("lexicon_entries", "linktitle", "eid = '".$delete."'"), "linktitle");
			$sendby = $db->fetch_field($db->simple_select("lexicon_entries", "uid", "eid = '".$delete."'"), "uid");
			$sendbyName = get_user($sendby)['username'];
			$externallink = $db->fetch_field($db->simple_select("lexicon_entries", "externallink", "eid = '".$accept."'"), "externallink");
			
			// Link
			if (!empty($externallink)) {
				$pm_message = $lang->sprintf($lang->lexicon_pm_message_delete_link, $sendbyName, $titel, $externallink);
			}
			// eintrag
			else {
				$pm_message = $lang->sprintf($lang->lexicon_pm_message_delete, $sendbyName, $titel);
			}

			// MyALERTS STUFF
			if(class_exists('MybbStuff_MyAlerts_AlertTypeManager') AND $alertsystem = 0) {
				$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('lexicon_delete');
				if ($alertType != NULL && $alertType->getEnabled()) {
					$alert = new MybbStuff_MyAlerts_Entity_Alert((int)$sendby, $alertType, (int)$team_uid);
					$alert->setExtraDetails([
						'username' => $mybb->user['username'],
						'from' => $mybb->user['uid'],
						'titel' => $titel,
					]);
					MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
				}
			} 
			// PN STUFF
			else {
				$pm_change = array(
					"subject" => $lang->lexicon_pm_subject_delete,
					"message" => $pm_message,
					"fromid" => $team_uid,
					"toid" => $sendby,
					"icon" => "",
					"do" => "",
					"pmid" => "",
				);
		
				$pm_change['options'] = array(
					'signature' => '0',
					'savecopy' => '0',
					'disablesmilies' => '0',
					'readreceipt' => '0',
				);
				// $pmhandler->admin_override = true;
				$pmhandler->set_data($pm_change);
				if (!$pmhandler->validate_pm())
					return false;
				else {
					$pmhandler->insert_pm();
				}
			}

			$db->delete_query("lexicon_entries", "eid = '$delete'");
			redirect("modcp.php?action=lexicon", $lang->lexicon_redirect_modcp_delete);
		}
        
		//Der Eintag wurde vom Team angenommen        
		if($accept = $mybb->get_input('accept')){

			$titel = $db->fetch_field($db->simple_select("lexicon_entries", "linktitle", "eid = '".$accept."'"), "linktitle");
			$link = $db->fetch_field($db->simple_select("lexicon_entries", "link", "eid = '".$accept."'"), "link");
			$sendby = $db->fetch_field($db->simple_select("lexicon_entries", "uid", "eid = '".$accept."'"), "uid");
			$sendbyName = get_user($sendby)['username'];
			$externallink = $db->fetch_field($db->simple_select("lexicon_entries", "externallink", "eid = '".$accept."'"), "externallink");
			
			// Link
			if (!empty($externallink)) {
				$pm_message = $lang->sprintf($lang->lexicon_pm_message_accept_link, $sendbyName, $titel, $externallink);
			}
			// eintrag
			else {
				$pm_message = $lang->sprintf($lang->lexicon_pm_message_accept, $sendbyName, $titel, $link);
			}

			// MyALERTS STUFF
			if(class_exists('MybbStuff_MyAlerts_AlertTypeManager') AND $alertsystem = 0) {
				$alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('lexicon_accept');
				if ($alertType != NULL && $alertType->getEnabled()) {
					$alert = new MybbStuff_MyAlerts_Entity_Alert((int)$sendby, $alertType, (int)$team_uid);
					$alert->setExtraDetails([
						'username' => $mybb->user['username'],
						'from' => $mybb->user['uid'],
						'titel' => $titel,
						'link' => $link,
					]);
					MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
				}
			}
			// PN STUFF
			else {
				$pm_change = array(
					"pmid" => "",
					"uid" => $team_uid,
					"toid" => $sendby,
					"fromid" => $team_uid,
					"subject" => $lang->lexicon_pm_subject_accept,
					"icon" => "",
					"message" => $pm_message,
					"do" => "",
					"ipaddress" => "",
				);
		
				$pm_change['options'] = array(
					'signature' => '0',
					'savecopy' => '0',
					'disablesmilies' => '0',
					'readreceipt' => '0',
					"pmid" => "",
				);
				// $pmhandler->admin_override = true;
				$pmhandler->set_data($pm_change);
				if (!$pmhandler->validate_pm())
					return false;
				else {
					$pmhandler->insert_pm();
				}
			}

			$db->query("UPDATE ".TABLE_PREFIX."lexicon_entries SET accepted = 1 WHERE eid = '".$accept."'");
			redirect("modcp.php?action=lexicon", $lang->lexicon_redirect_modcp_accept);
		}
 
        // TEMPLATE FÜR DIE SEITE
        eval("\$page = \"".$templates->get("lexicon_modcp")."\";");
        output_page($page);
        die();
    }

	// Bearbeiten
	if($mybb->get_input('action') == "lexicon_entryedit") {

		if ($user_accepted_setting != 1) {
			redirect('modcp.php', $lang->lexicon_redirect_modcp);
		}

		$eid = $mybb->input['eid'];

        // Add a breadcrumb
        add_breadcrumb($lang->nav_modcp, "modcp.php");
        add_breadcrumb($lang->lexicon_modcp, "modcp.php?action=lexicon");
		add_breadcrumb($lang->lexicon_nav_edit_entry, "modcp.php?action=lexicon_entryedit&eid=".$eid);

		// Eintrag auslesen
		$entry_query = $db->query("SELECT * FROM ".TABLE_PREFIX."lexicon_entries
		WHERE eid = '".$eid."'
		");

		while($edit = $db->fetch_array($entry_query)){

			// Leer laufen lassen
			$cid = "";
			$linktitle = "";
			$link = "";
			$title = "";
			$entrytext = "";
			$sort = "";
			$externallink = "";

			// Mit Infos füllen
			$cid = $edit['cid'];
			$linktitle = $edit['linktitle'];
			$link = $edit['link'];
			$title = $edit['title'];
			$entrytext = $edit['entrytext'];
			$sort = $edit['sort'];
			$externallink = $edit['externallink'];
 
			// KATEGORIEN DROPBOX GENERIEREN
			$categories_query = $db->query("SELECT * FROM ".TABLE_PREFIX."lexicon_categories ORDER by categoryname ASC");
			$cat_select = "";
			while($category = $db->fetch_array($categories_query)) {
    
				// die bisherige Kategorie als ausgewählt anzeigen lassen
				if($category['cid'] == $cid) {
					$checked_cat = "selected";
				} else {
					$checked_cat = "";
				}
    
				$cat_select .= "<option value=\"{$category['cid']}\" {$checked_cat}>{$category['categoryname']}</option>";    
			}

			if($lexicon_sub_setting == 1) { 
        
				$entries_query = $db->query("SELECT * FROM ".TABLE_PREFIX."lexicon_entries  
				WHERE accepted = '1'
				AND parentlist = '0'
				ORDER by linktitle ASC
				");
   
				$entries_select = "";    
				while($entry = $db->fetch_array($entries_query)) {

					// die bisherige Kategorie als ausgewählt anzeigen lassen
					if($entry['eid'] == $edit['parentlist']) {
						$checked_sub = "selected";
					} else {
						$checked_sub = "";   
					}

					$entries_select .= "<option value=\"{$entry['eid']}\" {$checked_sub}>{$entry['linktitle']}</option>";   
				}
   
				eval("\$sub_option = \"".$templates->get("lexicon_add_subentry")."\";");

			} else {
				$sub_option = "";
			}
		}


		if($lexicon_sort_entry_setting == 1) { 
			eval("\$sort_option = \"".$templates->get("lexicon_add_sort")."\";");
		} else {
			$sort_option = "";
		}

		$codebuttons = build_mycode_inserter("entrytext");
		if(function_exists('markitup_run_build')) {
			markitup_run_build('entrytext');
		};
    
		eval("\$page = \"".$templates->get("lexicon_modcp_edit")."\";");
		output_page($page);
		die();
	}

	// Bearbeiten - speichern
	if($mybb->get_input('action') == "do_lexicon_entryedit") {

		$eid = $mybb->input['eid'];
 
		$edit_entry = [
			"cid" => $db->escape_string($mybb->get_input('category')),
			"linktitle" => $db->escape_string($mybb->get_input('linktitle')),
			"link" => $db->escape_string($mybb->get_input('link')),
			"externallink" => $db->escape_string($mybb->get_input('externallink')),
			"title" => $db->escape_string($mybb->get_input('title')),
			"sort" => $db->escape_string($mybb->get_input('sort')),
			"parentlist" => $db->escape_string($mybb->get_input('parentlist')),
			"entrytext" => $db->escape_string($mybb->get_input('entrytext')),
		];

		$db->update_query("lexicon_entries", $edit_entry, "eid = '".$eid."'");
 
		redirect("modcp.php?action=lexicon", $lang->lexicon_redirect_edit_entry);  
	}
	
}

// ONLINE LOCATION
function lexicon_online_activity($user_activity) {

    global $parameters, $user, $db, $side_name;

    $split_loc = explode(".php", $user_activity['location']);
    if(isset($user['location']) && $split_loc[0] == $user['location']) { 
        $filename = '';
    } else {
        $filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
    }

	// Unterseite
    if (!empty($split_loc[1])) {

        // Name der Unterseite
        $split_value = explode("=", $split_loc[1]);
        // Parameter
        $value = $split_value[1];

        // Name des Actions
        $split_parameter = explode("?", $split_value[0]);
        // Action
        $parameter = $split_parameter[1];

		// action Seiten 
		if ($parameter == 'action') {

			// INHALTSVERZEICHNIS
			// lexicon.php?action=contents
			if($value == "contents"){
				$side_name = "contents";
			}
			
			// KATEGORIE HINZUFÜGEN
			// lexicon.php?action=add_category
			if($value == "add_category"){
				$side_name = "add_category";
			}
			
			// EINTRAG HINZUFÜGEN
			// lexicon.php?action=add_entry
			if($value == "add_entry"){
				$side_name = "add_entry";
			}

		}

		// edit Seiten 
		if ($parameter == 'edit') {

			$value_split = explode("&", $value);
			$edit_value = $value_split[0];
			//IDs
			$value_id = $split_value[2];
			
			// KATEGORIE BEARBEITEN
			// lexicon.php?edit=category&cid=XXX
			if($edit_value == "category"){
				$side_name = "edit_category=".$value_id;
			}
			
			// EINTRAG BEARBEITEN
			// lexicon.php?edit=entry&eid=XXX
			if($edit_value == "entry"){
				$side_name = "edit_entry=".$value_id;
			}
			
			// EXTERNEN LINK BEARBEITEN
			// lexicon.php?edit=externallink&eid=XXX
			if($edit_value == "externallink"){
				$side_name = "edit_externallink=".$value_id;
			}

		}

		// page Seiten
		if ($parameter == 'page') {
			$side_name = "page=".$value;
		}

		// search Seiten
		// lexicon.php?search=results&keyword=XXX
		if ($parameter == 'search') {

			$value_split = explode("&", $value);
			// Keyword
			$kayword = $split_value[2];

			$side_name = "search=".$kayword;
		}
	} 
	// HAUPTSEITE
    else {
        $side_name = "main";
    }

	switch ($filename) {
        case 'lexicon':
            $user_activity['activity'] = "lexicon_".$side_name;
        break;
    }
      
	return $user_activity;
}

function lexicon_online_location($plugin_array) {
    global $db, $lang;

    $lang->load("lexicon");

	// Seitennamen
	$split_name = explode("=", $plugin_array['user_activity']['activity']);
	$sidename = $split_name[0];

	// HAUPTSEITE
	if($sidename == "lexicon_main") {
        $plugin_array['location_name'] = $lang->lexicon_online_location_main;
    }

	// INHALTSVERZEICHNIS
	if($sidename == "lexicon_contents") {
		$plugin_array['location_name'] = $lang->lexicon_online_location_contents;
	}

	// KATEGORIE HINZUFÜGEN
	if($sidename == "lexicon_add_category") {
		$plugin_array['location_name'] = $lang->lexicon_online_location_add_category;
	}

	// EINTRAG HINZUFÜGEN
	if($sidename == "lexicon_add_entry") {
		$plugin_array['location_name'] = $lang->lexicon_online_location_add_entry;
	}

	// KATEGORIE BEARBEITEN
	if($sidename == "lexicon_edit_category") {
		$cid = $split_name[1];
		$categoryname = $db->fetch_field($db->simple_select("lexicon_categories", "categoryname", "cid = '".$cid."'"), "categoryname");
		$plugin_array['location_name'] = $lang->sprintf($lang->lexicon_online_location_edit_category, $categoryname);
	}

	// EINTRAG BEARBEITEN
	if($sidename == "lexicon_edit_entry") {
		$eid = $split_name[1];
		$linktitle = $db->fetch_field($db->simple_select("lexicon_entries", "linktitle", "eid = '".$eid."'"), "linktitle");
		$plugin_array['location_name'] = $lang->sprintf($lang->lexicon_online_location_edit_entry, $linktitle);
	}

	// EXTERNEN LINK BEARBEITEN
	if($sidename == "lexicon_edit_externallink") {
		$eid = $split_name[1];
		$linktitle = $db->fetch_field($db->simple_select("lexicon_entries", "linktitle", "eid = '".$eid."'"), "linktitle");
		$plugin_array['location_name'] = $lang->sprintf($lang->lexicon_online_location_edit_externallink, $linktitle);
	}

	// DIE EINTRÄGE
	if($sidename == "lexicon_page") {
		$link = $split_name[1];
		$linktitle = $db->fetch_field($db->simple_select("lexicon_entries", "linktitle", "link = '".$link."'"), "linktitle");
		$plugin_array['location_name'] = $lang->sprintf($lang->lexicon_online_location_page, $link, $linktitle);
	}

	// DIE EINTRÄGE
	if($sidename == "lexicon_search") {
		$keyword = $split_name[1];
		$plugin_array['location_name'] = $lang->sprintf($lang->lexicon_online_location_search, $keyword);
	}

	return $plugin_array;
}

// MyALERTS STUFF
function lexicon_myalert_alerts() {

	global $mybb, $lang;
	$lang->load('lexicon');

    // ABLEHNEN
    /**
	 * Alert formatter for my custom alert type.
	 */
	class MybbStuff_MyAlerts_Formatter_lexiconDeleteFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
	{
	    /**
	     * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
	     *
	     * @return string The formatted alert string.
	     */
	    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
	    {
			global $db;
			$alertContent = $alert->getExtraDetails();
            $userid = $db->fetch_field($db->simple_select("users", "uid", "username = '{$alertContent['username']}'"), "uid");
            $user = get_user($userid);
            $alertContent['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
	        return $this->lang->sprintf(
	            $this->lang->lexicon_delete,
                $alertContent['username'],
                $alertContent['from'],
                $alertContent['titel']       
	        );
	    }

	    /**
	     * Init function called before running formatAlert(). Used to load language files and initialize other required
	     * resources.
	     *
	     * @return void
	     */
	    public function init()
	    {
	        if (!$this->lang->lexicon) {
	            $this->lang->load('lexicon');
	        }
	    }

	    /**
	     * Build a link to an alert's content so that the system can redirect to it.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
	     *
	     * @return string The built alert, preferably an absolute link.
	     */
	    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
	    {
            $alertContent = $alert->getExtraDetails();
            return $this->mybb->settings['bburl'] . '/lexicon.php';
	    }
	}

	if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
		$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

		if (!$formatterManager) {
			$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
		}

		$formatterManager->registerFormatter(
				new MybbStuff_MyAlerts_Formatter_lexiconDeleteFormatter($mybb, $lang, 'lexicon_delete')
		);
    }

	// ANNEHMEN
	/**
	* Alert formatter for my custom alert type.
	*/
	class MybbStuff_MyAlerts_Formatter_lexiconAcceptetFormatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
	{
	    /**
	     * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
	     *
	     * @return string The formatted alert string.
	     */
	    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
	    {
			global $db;
			$alertContent = $alert->getExtraDetails();
            $userid = $db->fetch_field($db->simple_select("users", "uid", "username = '{$alertContent['username']}'"), "uid");
            $user = get_user($userid);
            $alertContent['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
	        return $this->lang->sprintf(
	            $this->lang->lexicon_accept,
                $alertContent['username'],
                $alertContent['from'],
                $alertContent['titel'],
                $alertContent['link']        
	        );
	    }

	    /**
	     * Init function called before running formatAlert(). Used to load language files and initialize other required
	     * resources.
	     *
	     * @return void
	     */
	    public function init()
	    {
	        if (!$this->lang->lexicon) {
	            $this->lang->load('lexicon');
	        }
	    }

	    /**
	     * Build a link to an alert's content so that the system can redirect to it.
	     *
	     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
	     *
	     * @return string The built alert, preferably an absolute link.
	     */
	    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
	    {
            $alertContent = $alert->getExtraDetails();
            return $this->mybb->settings['bburl'] . '/lexicon.php?page='.$alertContent['link'];
	    }
	}

	if (class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
		$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

		if (!$formatterManager) {
			$formatterManager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
		}

		$formatterManager->registerFormatter(
				new MybbStuff_MyAlerts_Formatter_lexiconAcceptetFormatter($mybb, $lang, 'lexicon_accept')
		);
    }


}

// DATENBANKTABELLEN
function lexicon_database() {

    global $db;
    
    // DATENBANKEN ERSTELLEN
    // Kategorien
    if (!$db->table_exists("lexicon_categories")) {
        $db->query("CREATE TABLE ".TABLE_PREFIX."lexicon_categories(
			`cid` int(10) NOT NULL AUTO_INCREMENT,
			`categoryname` varchar(500) CHARACTER SET utf8 NOT NULL,
			`sort` INT(10) DEFAULT '0' NOT NULL,
			PRIMARY KEY(`cid`),
			KEY `cid` (`cid`)
			)
			ENGINE=InnoDB ".$db->build_create_table_collation().";
		");
    }
    // Einträge
    if (!$db->table_exists("lexicon_entries")) {
        $db->query("CREATE TABLE ".TABLE_PREFIX."lexicon_entries(
			`eid` int(10) NOT NULL auto_increment, 
			`cid` int(11) NOT NULL,  
			`linktitle` varchar(255) CHARACTER SET utf8 NOT NULL,  
			`link` varchar(255) CHARACTER SET utf8 NOT NULL,  
			`externallink` varchar(500) CHARACTER SET utf8 NOT NULL,  
			`title` varchar(255) CHARACTER SET utf8 NOT NULL,
			`entrytext` longtext CHARACTER SET utf8 NOT NULL,
			`sort` INT(10) DEFAULT '0' NOT NULL,
			`parentlist` varchar(255) CHARACTER SET utf8 DEFAULT '0' NOT NULL,
			`uid` int(10) NOT NULL,
			`accepted` int(10) DEFAULT '0' NOT NULL,
			PRIMARY KEY(`eid`),
			KEY `eid` (`eid`)
			)
			ENGINE=InnoDB ".$db->build_create_table_collation().";
		");
    }
}

// EINSTELLUNGEN
function lexicon_settings($type = 'install') {

    global $db; 

    $setting_array = array(
		'lexicon_groups_cat' => array(
			'title' => 'Gruppen für Kategorien',
			'description' => 'Welche Gruppen haben die Möglichkeit neue Kategorien für das Lexikon hinzufügen?',
			'optionscode' => 'groupselect',
			'value' => 4, // Default
			'disporder' => 1
		),
		'lexicon_groups_entry' => array(
			'title' => 'Gruppen für Einträge',
			'description' => 'Welche Gruppen haben die Möglichkeit neue Einträge für das Lexikon hinzufügen?',
			'optionscode' => 'groupselect',
			'value' => 4, // Default
			'disporder' => 2
		),
		'lexicon_user_accepted' => array(
			'title' => 'Überprüfung von Einträgen',
			'description' => 'Sollen eingereichte Einträge von Usern vorher überprüft werden und manuell vom Team freigeschaltet werden?',
			'optionscode' => 'yesno',
			'value' => 0, // Default
			'disporder' => 3
		),
		'lexicon_user_edit' => array(
			'title' => 'Bearbeitung von Einträgen',
			'description' => 'Dürfen User ihre eingereichten Einträge selbstständig bearbeiten? Eine weitere Überprüfung vom oder Benachrichtigung ans Team wird es nicht geben.',
			'optionscode' => 'yesno',
			'value' => 0, // Default
			'disporder' => 4
		),
		'lexicon_user_delete' => array(
			'title' => 'Löschen von Einträgen',
			'description' => 'Dürfen User ihre eingereichten Einträge selbstständig löschen?',
			'optionscode' => 'yesno',
			'value' => 0, // Default
			'disporder' => 5
		),
		'lexicon_user_alert' => array(
			'title' => 'Benachrichtigungsystem',
			'description' => 'Wie sollen User darüber in Kenntnis gesetzt werden, dass ihr eingereichter Lexikoneintrag angenommen bzw. abgelehnt wurde?',
			'optionscode' => 'select\n0=MyAlerts\n1=Private Nachricht',
			'value' => 0, // Default
			'disporder' => 6
		),
		'lexicon_sort_cat' => array(
			'title' => 'Sortierung der Kategorien',
			'description' => 'Sollen die Kategorien im Menü alphabetisch nach ihren Namen sortiert werden, oder nach einer manuellen Sortierung?'			,
			'optionscode' => 'select\n0=Kategorienamen\n1=manuelle Sortierung',
			'value' => 0, // Default
			'disporder' => 7
		),
		'lexicon_sort_entry' => array(
			'title' => 'Sortierung der Einträge',
			'description' => 'Sollen die Einträge im Menü alphabetisch nach ihren Linktitel sortiert werden oder nach einer manuellen Sortierung?',
			'optionscode' => 'select\n0=Linktitel\n1=manuelle Sortierung',
			'value' => 0, // Default
			'disporder' => 8
		),
		'lexicon_contents' => array(
			'title' => 'Inhaltsverzeichnis',
			'description' => 'Soll ein großes Inhaltsverzeichnis erstellt werden? Die Beiträge werden alphabetisch kategorisiert.',
			'optionscode' => 'yesno',
			'value' => 1, // Default
			'disporder' => 9
		),
		'lexicon_sub' => array(
			'title' => 'Untereinträge',
			'description' => 'Können Einträge auch noch Untereinträge bekommen?',
			'optionscode' => 'yesno',
			'value' => 1, // Default
			'disporder' => 10
		),
	);

    $gid = $db->fetch_field($db->write_query("SELECT gid FROM ".TABLE_PREFIX."settinggroups WHERE name = 'lexicon' LIMIT 1;"), "gid");

    if ($type == 'install') {
        foreach ($setting_array as $name => $setting) {
          $setting['name'] = $name;
          $setting['gid'] = $gid;
          $db->insert_query('settings', $setting);
        }  
    }

    if ($type == 'update') {

        // Einzeln durchgehen 
        foreach ($setting_array as $name => $setting) {
            $setting['name'] = $name;
            $check = $db->write_query("SELECT name FROM ".TABLE_PREFIX."settings WHERE name = '".$name."'"); // Überprüfen, ob sie vorhanden ist
            $check = $db->num_rows($check);
            $setting['gid'] = $gid;
            if ($check == 0) { // nicht vorhanden, hinzufügen
              $db->insert_query('settings', $setting);
            } else { // vorhanden, auf Änderungen überprüfen
                
                $current_setting = $db->fetch_array($db->write_query("SELECT title, description, optionscode, disporder FROM ".TABLE_PREFIX."settings 
                WHERE name = '".$db->escape_string($name)."'
                "));
            
                $update_needed = false;
                $update_data = array();
            
                if ($current_setting['title'] != $setting['title']) {
                    $update_data['title'] = $setting['title'];
                    $update_needed = true;
                }
                if ($current_setting['description'] != $setting['description']) {
                    $update_data['description'] = $setting['description'];
                    $update_needed = true;
                }
                if ($current_setting['optionscode'] != $setting['optionscode']) {
                    $update_data['optionscode'] = $setting['optionscode'];
                    $update_needed = true;
                }
                if ($current_setting['disporder'] != $setting['disporder']) {
                    $update_data['disporder'] = $setting['disporder'];
                    $update_needed = true;
                }
            
                if ($update_needed) {
                    $db->update_query('settings', $update_data, "name = '".$db->escape_string($name)."'");
                }
            }
        }  
    }

    rebuild_settings();
}

// TEMPLATES
function lexicon_templates($mode = '') {

    global $db;

    $templates[] = array(
        'title'		=> 'lexicon_add_category',
        'template'	=> $db->escape_string('<html>
		  <head>
			<title>{$mybb->settings[\'bbname\']} - {$lang->lexicon_nav_add_category}</title>
			{$headerinclude}
		 </head>
		 <body>
			{$header}
			<table width="100%" cellspacing="5" cellpadding="0">
				<tr>
					<td valign="top">
						<div id="lexicon">
							{$menu}
							<div class="lexicon-entry">
								<div class="entry-headline">{$lang->lexicon_nav_add_category}</div>
								<div class="entry">
								
									<form  action="lexicon.php?action=do_category" method="post">
										<table width="100%">
											<tbody>	
												<tr>
													<td class="trow1">
														<strong>{$lang->lexicon_add_categoryname_titel}</strong>
														<div class="smalltext">{$lang->lexicon_add_categoryname_desc}</div>
													</td>
													<td class="trow1">
														<input type="text" name="categoryname" id="categoryname" placeholder="Name" class="textbox" required>
													</td>		
												</tr>
																		
												{$sort_option}
					
												<tr>
													<td colspan="2" align="center">
														<input type="submit" name="do_category" value="{$lang->lexicon_nav_add_category}" class="button" />
													</td>
												</tr>	
											</tbody>
										</table>	
									</form>
									
								</div>
							</div>
						</div>
					</td>
				</tr>
			</table>
			{$footer}
		 </body>	
	 </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'lexicon_add_entry',
        'template'	=> $db->escape_string('<html>
		<head>
			<title>{$mybb->settings[\'bbname\']} - {$lang->lexicon_nav_add_entry}</title>
			{$headerinclude}
		</head>
		<body>
			{$header}
			<table width="100%" cellspacing="5" cellpadding="0">
				<tr>
					<td valign="top">
						<div id="lexicon">
							{$menu}
							<div class="lexicon-entry">
								<div class="entry-headline">{$lang->lexicon_nav_add_entry}</div>
								<div class="entry">
									<form action="lexicon.php?action=do_entry" method="post">
										<table width="100%">
											<tbody>			
												<tr>
													<td class="trow1">
														<strong>{$lang->lexicon_add_category_titel}</strong>
														<div class="smalltext">{$lang->lexicon_add_category_desc}</div>
													</td>				
													<td class="trow1">
														<select name="category" required>
															<option value="">Kategorie wählen</option>
															{$cat_select}
														</select> 
													</td>
												</tr>
												
												{$sub_option}
												
												<tr>
													<td class="trow1">
														<strong>{$lang->lexicon_add_linktitle_titel}</strong>
														<div class="smalltext">{$lang->lexicon_add_linktitle_desc}</div>
													</td>
													<td class="trow1">
														<input type="text" name="linktitle" id="linktitle" placeholder="Linktitel" class="textbox" required> 
													</td>
												</tr>
												
												<tr>
													<td class="trow1">
														<strong>{$lang->lexicon_add_link_titel}</strong>
														<div class="smalltext">{$lang->lexicon_add_link_desc}</div>
													</td>
													<td class="trow1">
														<input type="text" name="link" id="link" placeholder="bildung, usa, relations" class="textbox">
													</td>
												</tr>
												
												<tr>
													<td class="trow1">
														<strong>{$lang->lexicon_add_externallink_titel}</strong>
														<div class="smalltext">{$lang->lexicon_add_externallink_desc}</div>
													</td>
													<td class="trow1">
														<input type="text" name="externallink" id="externallink" placeholder="misc.php?action=xxx" class="textbox">
													</td>
												</tr>
												
												<tr>
													<td class="trow1">
														<strong>{$lang->lexicon_add_title_titel}</strong>
														<div class="smalltext">{$lang->lexicon_add_title_desc}</div>
													</td>
													<td class="trow1">
														<input type="text" name="title" id="title" placeholder="Titel des Artikels" class="textbox">
													</td>
												</tr>
												
												{$sort_option}
												
												<tr>
													<td class="trow1" colspan="2">
														<strong>{$lang->lexicon_add_entrytext}</strong>
													</td>
												</tr>
												<tr>
													<td class="trow1" colspan="2">
														<textarea class="textarea" name="entrytext" id="entrytext" rows="6" cols="30" style="width: 95%"></textarea>{$codebuttons}
													</td>
												</tr>
												
												<tr>
													<td colspan="2" align="center">
														<input type="submit" name="do_entry" value="{$lang->lexicon_nav_add_entry}" class="button" />
													</td>
												</tr>	
											</tbody>
										</table>
									</form>
								</div>
							</div>
						</div>
					</td>
				</tr>
			</table>
			{$footer}
		</body>
	 </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
    
	$templates[] = array(
        'title'		=> 'lexicon_add_sort',
        'template'	=> $db->escape_string('<tr>
		<td class="trow1">
			<strong>{$lang->lexicon_sort_titel}</strong>
			<div class="smalltext">{$lang->lexicon_sort_desc}</div>
		</td>
		<td class="trow1">
			<input type="number" name="sort" id="sort" class="textbox" value="{$sort}">
		</td>
	 </tr>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
    
	$templates[] = array(
        'title'		=> 'lexicon_add_subentry',
        'template'	=> $db->escape_string('<tr>
		<td class="trow1">
			<strong>{$lang->lexicon_sub_titel}</strong>
			<div class="smalltext">{$lang->lexicon_sub_desc}</div>
		</td>				
		<td class="trow1">
			<select name="parentlist" required>
				<option value="0">Kein Untereintrag</option>
				{$entries_select}
			</select> 
		</td>
	 </tr>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
    
	$templates[] = array(
        'title'		=> 'lexicon_contents',
        'template'	=> $db->escape_string('<html>
		<head>
			<title>{$mybb->settings[\'bbname\']} - {$lang->lexicon_contents}</title>
			{$headerinclude}
		</head>
		<body>
			{$header}
			<table width="100%" cellspacing="5" cellpadding="0">
				<tr>
					<td valign="top">
						<div id="lexicon">
							{$menu}
							<div class="lexicon-entry">
								<div class="entry-headline">{$lang->lexicon_contents}</div>
								<div class="entry content">{$lang->lexicon_contents_desc}</div>
								<div class="content-bit">
									{$contents_bit}
								</div>
							</div>
						</div>
					</td>
				</tr>
			</table>
			{$footer}
		</body>
	 </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
    
    $templates[] = array(
        'title'		=> 'lexicon_contents_bit',
        'template'	=> $db->escape_string('<div class="content-letter">
		<h2>{$buchstabe}</h2>
		{$entries}
	 </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );   

	$templates[] = array(
        'title'		=> 'lexicon_contents_entries',
        'template'	=> $db->escape_string('<div class="content-item">● <a href="{$fulllink}">{$linktitle}</a> <span class="content-item-cat">({$categoryname})</span></div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

	$templates[] = array(
        'title'		=> 'lexicon_edit_category',
        'template'	=> $db->escape_string('<html>
		<head>
			<title>{$mybb->settings[\'bbname\']} - {$lang->lexicon_nav_edit_category}</title>
			{$headerinclude}
		</head>
		<body>
			{$header}
			<table width="100%" cellspacing="5" cellpadding="0">
				<tr>
					<td valign="top">
						<div id="lexicon">
							{$menu}
							<div class="lexicon-entry">
								<div class="entry-headline">{$lang->lexicon_nav_edit_category}</div>
								<div class="entry">
									
									<form  action="lexicon.php?edit=do_category&cid={$cid}" method="post">
										<table width="100%">
											<tbody>			
												<tr>
													<td class="trow1">
														<strong>{$lang->lexicon_add_categoryname_titel}</strong>
														<div class="smalltext">{$lang->lexicon_add_categoryname_desc}</div>
													</td>				
													<td class="trow1">
														<input type="text" name="categoryname" id="categoryname" value="{$categoryname}" class="textbox" required>
													</td>
												</tr>
												
												{$sort_option}
												
												<tr>
													<td colspan="2" align="center">
														<input type="submit" name="do_category" value="{$lang->lexicon_nav_edit_category}" class="button" />
													</td>
												</tr>	
											</tbody>
										</table>
									</form>
									
								</div>
							</div>
						</div>
					</td>
				</tr>
			</table>
			{$footer}
		</body>
	 </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
    
    $templates[] = array(
        'title'		=> 'lexicon_edit_entry',
        'template'	=> $db->escape_string('<html>
		<head>
			<title>{$mybb->settings[\'bbname\']} - {$lang->lexicon_nav_edit_entry}</title>
			{$headerinclude}
	    </head>
		<body>
			{$header}
			<table width="100%" cellspacing="5" cellpadding="0">
				<tr>
					<td valign="top">
						<div id="lexicon">
							{$menu}
							<div class="lexicon-entry">
								<div class="entry-headline">{$lang->lexicon_nav_edit_entry}</div>
								<div class="entry">
									<form  action="lexicon.php?edit=do_entry&eid={$eid}" method="post">
										<table width="100%">
											<tbody>			
												<tr>
													<td class="trow1">
														<strong>{$lang->lexicon_add_category_titel}</strong>
														<div class="smalltext">{$lang->lexicon_add_category_desc}</div>
													</td>				
													<td class="trow1">
														<select name="category" required>
															{$cat_select}
														</select> 
													</td>
												</tr>
												
												{$sub_option}
	
												<tr>
													<td class="trow1">
														<strong>{$lang->lexicon_add_linktitle_titel}</strong>
														<div class="smalltext">{$lang->lexicon_add_linktitle_desc}</div>
													</td>
													<td class="trow1">
														<input type="text" name="linktitle" id="linktitle" value="{$linktitle}" class="textbox" required> 
													</td>
												</tr>
												
												<tr>
													<td class="trow1">
														<strong>{$lang->lexicon_add_link_titel}</strong>
														<div class="smalltext">{$lang->lexicon_add_link_desc}</div>
													</td>
													<td class="trow1">
														<input type="text" name="link" id="link" value="{$link}" class="textbox" required>
													</td>
												</tr>
	
												<tr>
													<td class="trow1">
														<strong>{$lang->lexicon_add_title_titel}</strong>
														<div class="smalltext">{$lang->lexicon_add_title_desc}</div>
													</td>
													<td class="trow1">
														<input type="text" name="title" id="title" value="{$title}" class="textbox" required>
													</td>
												</tr>

												{$sort_option}
				
												<tr>
													<td class="trow1" colspan="2">
														<strong>{$lang->lexicon_add_entrytext}</strong>
													</td>
												</tr>
												<tr>
													<td class="trow1" colspan="2">
														<textarea class="textarea" name="entrytext" id="entrytext" rows="6" cols="30" style="width: 95%">{$entrytext}</textarea>{$codebuttons}
													</td>
												</tr>
				
												<tr>
													<td colspan="2" align="center">
														<input type="submit" name="do_entry" value="{$lang->lexicon_nav_edit_entry}" class="button" />
													</td>
												</tr>	
											</tbody>
										</table>
									</form>
								</div>
							</div>
						</div>
					</td>
				</tr>
			</table>
			{$footer}
		</body>
	 </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
    
    $templates[] = array(
        'title'		=> 'lexicon_edit_externallink',
        'template'	=> $db->escape_string('<html>
		<head>
			<title>{$mybb->settings[\'bbname\']} - {$lang->lexicon_nav_edit_externallink}</title>
			{$headerinclude}
		</head>
		<body>
			{$header}
			<table width="100%" cellspacing="5" cellpadding="0">
				<tr>
					<td valign="top">
						<div id="lexicon">
							{$menu}
							<div class="lexicon-entry">
								<div class="entry-headline">{$lang->lexicon_nav_edit_externallink}</div>
								<div class="entry">
									<form  action="lexicon.php?edit=do_externallink&eid={$eid}" method="post">
										<table width="100%">
											<tbody>			
												<tr>
													<td class="trow1">
														<strong>{$lang->lexicon_add_category_titel}</strong>
														<div class="smalltext">{$lang->lexicon_add_category_desc}</div>
													</td>				
													<td class="trow1">
														<select name="category" required>
															{$cat_select}
														</select> 
													</td>
												</tr>
													
													{$sub_option}
													
													<tr>
														<td class="trow1">
															<strong>{$lang->lexicon_add_linktitle_titel}</strong>
															<div class="smalltext">{$lang->lexicon_add_linktitle_desc}</div>
														</td>
														<td class="trow1">
															<input type="text" name="linktitle" id="linktitle" value="{$linktitle}" class="textbox" required> 
														</td>
													</tr>
													
													<tr>
														<td class="trow1">
															<strong>{$lang->lexicon_add_externallink_titel}</strong>
															<div class="smalltext">{$lang->lexicon_add_externallink_desc}</div>
														</td>
														<td class="trow1">
															<input type="text" name="externallink" id="externallink" value="{$externallink}" class="textbox" required>
														</td>
													</tr>
	
												{$sort_option}
	
												<tr>
													<td colspan="2" align="center">
														<input type="submit" name="do_externallink" value="{$lang->lexicon_nav_edit_externallink}" class="button" />
													</td>
												</tr>	
											</tbody>
										</table>
									</form>
								</div>
							</div>
						</div>
					</td>
				</tr>
			</table>
			{$footer}
		</body>
	 </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
    
    $templates[] = array(
        'title'		=> 'lexicon_entry',
        'template'	=> $db->escape_string('<html>
		<head>
			<title>{$mybb->settings[\'bbname\']} - {$linktitle}</title>
			{$headerinclude}</head>
		<body>
			{$header}
			<table width="100%" cellspacing="5" cellpadding="0">
				<tr>
					<td valign="top">
						<div id="lexicon">
							{$menu}
							<div class="lexicon-entry">
								<div class="entry-headline">{$title}</div>
								{$option_buttons_entry}
								<div class="entry">{$entrytext}</div>
							</div>
						</div>
					</td>
				</tr>
			</table>
			{$footer}
		</body>
	 </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
    
    $templates[] = array(
        'title'		=> 'lexicon_mainpage',
        'template'	=> $db->escape_string('<html>
		<head>
			<title>{$mybb->settings[\'bbname\']} - {$lang->lexicon_nav_main}</title>
			{$headerinclude}</head>
		<body>
			{$header}
			<table width="100%" cellspacing="5" cellpadding="0">
				<tr>
					<td valign="top">
						<div id="lexicon">
							{$menu}
							<div class="lexicon-entry">
								<div class="entry-headline">{$lang->lexicon_nav_main}</div>
								<div class="entry">{$lang->lexicon_main_desc}</div>
							</div>
						</div>
					</td>
				</tr>
			</table>
			{$footer}
		</body>
	 </html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
    
    $templates[] = array(
        'title'		=> 'lexicon_menu',
        'template'	=> $db->escape_string('<div id="navigation">
		<div class="navigation-headline">
			<a href="lexicon.php">{$lang->lexicon_nav_main}</a>
		</div>  
		<div class="navigation-search">
			<form action="lexicon.php" method="get">
				<input type="hidden" name="search" value="results">
				<input type="text" class="textbox" name="keyword" id="keyword" placeholder="Suchbegriff eingeben" value="">
				<button type="submit">Suchen</button>
			</form>
		</div>
		{$menu_contents} 
		{$add_cat}
		{$add_entry}
		{$menu_cat}    
	 </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );  

    $templates[] = array(
        'title'		=> 'lexicon_menu_add_cat',
        'template'	=> $db->escape_string('<div class="navigation-item">
		<a href="lexicon.php?action=add_category">{$lang->lexicon_nav_add_category}</a>	
	 </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );

    $templates[] = array(
        'title'		=> 'lexicon_menu_add_entry',
        'template'	=> $db->escape_string('<div class="navigation-item">
		<a href="lexicon.php?action=add_entry">{$lang->lexicon_nav_add_entry}</a>	
	 </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
    
    $templates[] = array(
        'title'		=> 'lexicon_menu_cat',
        'template'	=> $db->escape_string('<div class="navigation-headline">
		{$category} 
		{$option_buttons_cat}
	 </div>
	 {$entries}'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
    
    $templates[] = array(
        'title'		=> 'lexicon_menu_entries',
        'template'	=> $db->escape_string('<div class="navigation-item">
		<a href="{$fulllink}">{$linktitle}</a> 
		{$option_menu_externallink}
	 </div>	
	 {$subentries}'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
    
    $templates[] = array(
        'title'		=> 'lexicon_menu_externallink_option',
        'template'	=> $db->escape_string('<div class="navigation-externallink-option">
		<a href="lexicon.php?edit=externallink&eid={$eid}">E</a> 
	   <a href="lexicon.php?delete_externallink={$eid}" onClick="return confirm(\'{$lang->lexicon_externallink_delet_notice}\');">X</a>
     </div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );  

    $templates[] = array(
        'title'		=> 'lexicon_menu_subentries',
        'template'	=> $db->escape_string('<div class="navigation-subitem">
		<i>»&nbsp;</i> 
		<a href="{$subfulllink}">{$sublinktitle}</a>
		{$option_menu_externallink}
		</div>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
    
	$templates[] = array(
        'title'		=> 'lexicon_modcp',
        'template'	=> $db->escape_string('<html>
		<head>
			<title>{$mybb->settings[\'bbname\']} - {$lang->lexicon_modcp}</title>
			{$headerinclude}
		</head>
		<body>
			{$header}
			<table width="100%" border="0" align="center">
				<tr>
					{$modcp_nav}
					<td valign="top">
						<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
							<tr>
								<td class="thead"><strong>{$lang->lexicon_modcp}</strong></td>
							</tr>
							<tr>
								<td class="trow1">
									{$modcp_control_none}
									{$modcp_control_bit}
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
			{$footer}
		</body>
    	</html>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
    
    $templates[] = array(
        'title'		=> 'lexicon_modcp_bit',
        'template'	=> $db->escape_string('<table width="100%">
		<tr>
			<td class="tcat" align="center" colspan="2">
				<strong>{$title}</strong>
			</td>
		</tr>
		<tr>
			<td class="smalltext" rowspan="2" width="20%">
				<strong>{$lang->lexicon_modcp_linktitel}</strong> {$linktitle}<br>
				<strong>{$lang->lexicon_modcp_link}</strong> {$link}<br>
				<b>{$lang->lexicon_modcp_sendby}</b> {$createdby}
			</td>
			<td class="thead smalltext" align="center">
				{$path}
			</td>
		</tr>
		<tr>
			<td>
				<div style="max-height: 100px; overflow: auto;text-align:justify;padding-right:10px;">
					{$entrytext}
				</div>
			</td>
		</tr>
		<tr>
			<td colspan="2" align="center">
				<a href="modcp.php?action=lexicon&accept={$eid}" class="button">{$lang->lexicon_modcp_accept_button}</a>
				<a href="modcp.php?action=lexicon_entryedit&eid={$eid}" class="button">{$lang->lexicon_modcp_edit_button}</a>
				<a href="modcp.php?action=lexicon&delete={$eid}" class="button">{$lang->lexicon_modcp_delete_button}</a> 
			</td>
		</tr>
		</table>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
    
    $templates[] = array(
        'title'		=> 'lexicon_modcp_nav',
        'template'	=> $db->escape_string('<tr>
		<td class="trow1 smalltext"><a href="modcp.php?action=lexicon" class="modcp_nav_item modcp_nav_modqueue">{$lang->lexicon_modcp_nav}</td>	
		</tr>'),
        'sid'		=> '-2',
        'version'	=> '',
        'dateline'	=> TIME_NOW
    );
    
    $templates[] = array(
        'title'		=> 'lexicon_header_link',
        'template'	=> $db->escape_string('<li><a href="{$mybb->settings[\'bburl\']}/lexicon.php" class="help">{$lang->lexicon_nav_main}</a></li>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    
    $templates[] = array(
        'title'		=> 'lexicon_header_banner',
        'template'	=> $db->escape_string('<div class="red_alert">{$newentry_notice}</div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    
    $templates[] = array(
        'title'		=> 'lexicon_entry_option',
        'template'	=> $db->escape_string('<div class="entry-subline">{$edit_button}  {$delete_button}</div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    
    $templates[] = array(
        'title'		=> 'lexicon_menu_cat_option',
        'template'	=> $db->escape_string('<a href="lexicon.php?edit=category&cid={$cid}">E</a> 
		<a href="lexicon.php?delete_category={$cid}" onClick="return confirm(\'{$lang->lexicon_cat_delet_notice}\');">X</a>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    
    $templates[] = array(
        'title'		=> 'lexicon_search_results',
        'template'	=> $db->escape_string('<html>
		<head>
			<title>{$mybb->settings[\'bbname\']} - {$lexicon_nav_search}</title>
			{$headerinclude}</head>
		<body>
			{$header}
			<table width="100%" cellspacing="5" cellpadding="0">
				<tr>
					<td valign="top">
						<div id="lexicon">
							{$menu}
							<div class="lexicon-entry">
								<div class="entry-headline">{$lexicon_nav_search}</div>
								<div class="entry">{$results_none}{$results_bit}</div>
							</div>
						</div>
					</td>
				</tr>
			</table>
			{$footer}
		</body>
	 </html>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    
	$templates[] = array(
		'title'		=> 'lexicon_search_results_bit',
        'template'	=> $db->escape_string('<div class="lexicon_search_results">
		<div class="lexicon_search_results_headline"><strong><a href="{$fulllink}">{$title}</a></strong> » {$categoryname}</div>
		<div class="lexicon_search_results_previw">
			{$previw_entry}
		</div>
	 </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    
    $templates[] = array(
        'title'		=> 'lexicon_modcp_edit',
        'template'	=> $db->escape_string('<html>
		<head>
			<title>{$mybb->settings[\'bbname\']} - {$lang->lexicon_nav_edit_entry}</title>
			{$headerinclude}
		</head>
		<body>
			{$header}
			<table width="100%" border="0" align="center">
				<tr>
					{$modcp_nav}
					<td valign="top">
						<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
							<tr>
								<td class="thead"><strong>{$lang->lexicon_nav_edit_entry}</strong></td>
							</tr>
							<tr>
								<td class="trow1">
										<form action="modcp.php?action=do_lexicon_entryedit&eid={$eid}" method="post">
											<table width="100%">
												<tbody>			
													<tr>
														<td class="trow1">
															<strong>{$lang->lexicon_add_category_titel}</strong>
															<div class="smalltext">{$lang->lexicon_add_category_desc}</div>
														</td>				
														<td class="trow1">
															<select name="category" required>
																{$cat_select}
															</select> 
														</td>
													</tr>
													
													{$sub_option}
		
													<tr>
														<td class="trow1">
															<strong>{$lang->lexicon_add_linktitle_titel}</strong>
															<div class="smalltext">{$lang->lexicon_add_linktitle_desc}</div>
														</td>
														<td class="trow1">
															<input type="text" name="linktitle" id="linktitle" value="{$linktitle}" class="textbox" required> 
														</td>
													</tr>
													
													<tr>
														<td class="trow1">
															<strong>{$lang->lexicon_add_link_titel}</strong>
															<div class="smalltext">{$lang->lexicon_add_link_desc}</div>
														</td>
														<td class="trow1">
															<input type="text" name="link" id="link" value="{$link}" class="textbox" required>
														</td>
													</tr>
		
													<tr>
														<td class="trow1">
															<strong>{$lang->lexicon_add_title_titel}</strong>
															<div class="smalltext">{$lang->lexicon_add_title_desc}</div>
														</td>
														<td class="trow1">
															<input type="text" name="title" id="title" value="{$title}" class="textbox" required>
														</td>
													</tr>
	
													{$sort_option}
					
													<tr>
														<td class="trow1" colspan="2">
															<strong>{$lang->lexicon_add_entrytext}</strong>
														</td>
													</tr>
													<tr>
														<td class="trow1" colspan="2">
															<textarea class="textarea" name="entrytext" id="entrytext" rows="6" cols="30" style="width: 95%">{$entrytext}</textarea>{$codebuttons}
														</td>
													</tr>
					
													<tr>
														<td colspan="2" align="center">
															<input type="submit" name="do_lexicon_entryedit" value="{$lang->lexicon_nav_edit_entry}" class="button" />
														</td>
													</tr>	
												</tbody>
											</table>
										</form>
								</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
			{$footer}
		</body>
    	</html>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );

    if ($mode == "update") {

        foreach ($templates as $template) {
            $query = $db->simple_select("templates", "tid, template", "title = '".$template['title']."' AND sid = '-2'");
            $existing_template = $db->fetch_array($query);

            if($existing_template) {
                if ($existing_template['template'] !== $template['template']) {
                    $db->update_query("templates", array(
                        'template' => $template['template'],
                        'dateline' => TIME_NOW
                    ), "tid = '".$existing_template['tid']."'");
                }
            }
            
            else {
                $db->insert_query("templates", $template);
            }
        }

    } else {
        foreach ($templates as $template) {
            $check = $db->num_rows($db->simple_select("templates", "title", "title = '".$template['title']."'"));
            if ($check == 0) {
                $db->insert_query("templates", $template);
            }
        }
    }
}

// STYLESHEET MASTER
function lexicon_stylesheet() {

    global $db;
    
    $css = array(
		'name' => 'lexicon.css',
		'tid' => 1,
		'attachedto' => '',
		"stylesheet" =>	'#lexicon {
			width: 100%;
			display: flex;
			gap: 20px;
			justify-content: space-between;
			align-items: flex-start;    
		}
		
		#lexicon #navigation {
			width: 20%;
			display: flex;
			flex-direction: column;
			align-items: flex-start;
			background: #fff;
			border: 1px solid #ccc;
			padding: 1px;
			-moz-border-radius: 7px;
			-webkit-border-radius: 7px;
			border-radius: 7px;   
		}
		
		#lexicon #navigation .navigation-headline {
			min-height: 50px;
			width: 100%;
			display: flex;
			justify-content: center;
			align-items: center;
			font-weight: bold;
			text-transform: uppercase;
			text-align: center;
			padding: 0 5px;
			box-sizing: border-box;
			background: #0066a2 url(../../../images/thead.png) top left repeat-x;
			color: #ffffff;
		}
		
		#lexicon #navigation .navigation-headline:first-child {
			-moz-border-radius-topleft: 6px;
			-moz-border-radius-topright: 6px;
			-webkit-border-top-left-radius: 6px;
			-webkit-border-top-right-radius: 6px;
			border-top-left-radius: 6px;
			border-top-right-radius: 6px; 
		}
		
		#lexicon #navigation .navigation-headline:first-child a:link,
		#lexicon #navigation .navigation-headline:first-child a:visited,
		#lexicon #navigation .navigation-headline:first-child a:active,
		#lexicon #navigation .navigation-headline:first-child a:hover {
			margin-left: 0;
		}
		
		#lexicon #navigation .navigation-headline a:link,
		#lexicon #navigation .navigation-headline a:visited,
		#lexicon #navigation .navigation-headline a:active,
		#lexicon #navigation .navigation-headline a:hover {
			color: #ffffff;
			margin-left: 5px;
		}
		
		#lexicon #navigation .navigation-item {
			min-height: 25px;
			width: 100%;
			margin: 0 auto;
			padding: 5px 20px;
			display: flex;
			align-items: center;
			box-sizing: border-box;
			border-bottom: 1px solid #ddd;
			background: #f5f5f5;
		}
		
		#lexicon #navigation .navigation-item:last-child {
			-moz-border-radius-bottomright: 6px;
			-webkit-border-bottom-right-radius: 6px;
			border-bottom-right-radius: 6px;
			-moz-border-radius-bottomleft: 6px;
			-webkit-border-bottom-left-radius: 6px;
			border-bottom-left-radius: 6px;
		}
		
		#lexicon #navigation .navigation-subitem {
			min-height: 25px;
			width: 100%;
			margin: 0 auto;
			padding: 0 20px 0px 20px;
			display: flex;
			align-items: center;
			box-sizing: border-box;
			border-bottom: 1px solid #ddd;
			background: #f5f5f5;
		}
		
		#lexicon #navigation .navigation-subitem i {
			font-size: 11px;
			padding-top: 1px;
		}
		
		#lexicon #navigation .navigation-externallink-option {
			width: 100%;
			text-align: right;
		}
		
		#lexicon #navigation .navigation-search {
			width: 100%;
			margin: 0 auto;
			padding: 10px 0;
			display: flex;
			align-items: center;
			box-sizing: border-box;
			border-bottom: 1px solid #ddd;
			background: #f5f5f5;
			justify-content: center;
		}
		
		#lexicon #navigation .navigation-search input.textbox {
			width: 68%;
		}
		
		#lexicon .lexicon-entry {
			width: 80%;
			box-sizing: border-box;
			background: #fff;
			border: 1px solid #ccc;
			padding: 1px;
			-moz-border-radius: 7px;
			-webkit-border-radius: 7px;
			border-radius: 7px;    
		}
		
		#lexicon .lexicon-entry .entry-headline {
			height: 50px;
			width: 100%;
			font-size: 30px;
			display: flex;
			justify-content: center;
			align-items: center;
			font-weight: bold;
			text-transform: uppercase;
			background: #0066a2 url(../../../images/thead.png) top left repeat-x;
			color: #ffffff;
			-moz-border-radius-topleft: 6px;
			-moz-border-radius-topright: 6px;
			-webkit-border-top-left-radius: 6px;
			-webkit-border-top-right-radius: 6px;
			border-top-left-radius: 6px;
			border-top-right-radius: 6px; 
		}
		
		
		#lexicon .lexicon-entry .entry-subline {
			text-align: right;
			padding-right: 10px;
			padding-top: 5px;
			background: #f5f5f5;
		}
		
		#lexicon .lexicon-entry .entry {
			background: #f5f5f5;
			padding: 20px 40px;
			text-align: justify;
			line-height: 180%;   
			-moz-border-radius-bottomright: 6px;
			-webkit-border-bottom-right-radius: 6px;
			border-bottom-right-radius: 6px;
			-moz-border-radius-bottomleft: 6px;
			-webkit-border-bottom-left-radius: 6px;
			border-bottom-left-radius: 6px; 
		}
		
		#lexicon .lexicon-entry .entry.content {
			-moz-border-radius-bottomright: 0;
			-webkit-border-bottom-right-radius: 0;
			border-bottom-right-radius: 0;
			-moz-border-radius-bottomleft: 0;
			-webkit-border-bottom-left-radius: 0;
			border-bottom-left-radius: 0;
		}
		
		#lexicon .lexicon-entry .content-bit {
			padding: 0 40px 40px 40px;
			display: flex;
			flex-wrap: wrap;
			justify-content: space-between;
			gap: 20px;
			background:#f5f5f5;
			-moz-border-radius-bottomright: 6px;
			-webkit-border-bottom-right-radius: 6px;
			border-bottom-right-radius: 6px;
			-moz-border-radius-bottomleft: 6px;
			-webkit-border-bottom-left-radius: 6px;
			border-bottom-left-radius: 6px; 
		}
		
		#lexicon .lexicon-entry .content-bit .content-letter {
			width: 45%;     
		}
		
		#lexicon .lexicon-entry .content-bit .content-letter .content-item {
			margin-bottom: 5px;    
		}
		
		#lexicon .lexicon-entry .content-bit .content-letter .content-item .content-item-cat {
			font-size:0.7em;
		}
		
		#lexicon .lexicon-entry .lexicon_search_results {
			margin-bottom: 10px;
		}',
		'cachefile' => $db->escape_string(str_replace('/', '', 'lexicon.css')),
		'lastmodified' => time()
	);

    return $css;
}

// STYLESHEET UPDATE
function lexicon_stylesheet_update() {

    // Update-Stylesheet
    // wird an bestehende Stylesheets immer ganz am ende hinzugefügt
    $update = '';

    // Definiere den  Überprüfung-String (muss spezifisch für die Überprüfung sein)
    $update_string = '';

    return array(
        'stylesheet' => $update,
        'update_string' => $update_string
    );
}

// UPDATE CHECK
function lexicon_is_updated(){

    global $db;

    $charset = 'utf8';
    $collation = 'utf8_general_ci';

    $collation_string = $db->build_create_table_collation();
    if (preg_match('/CHARACTER SET ([^\s]+)\s+COLLATE ([^\s]+)/i', $collation_string, $matches)) {
        $charset = strtolower($matches[1]);
        $collation = strtolower($matches[2]);
    }

    $databaseTables = [
		"lexicon_categories",
		"lexicon_entries"
	];

    foreach ($databaseTables as $table_name) {
        if (!$db->table_exists($table_name)) {
            return false;
        }

        $full_table_name = TABLE_PREFIX . $table_name;

        $query = $db->query("
            SELECT TABLE_COLLATION 
            FROM information_schema.TABLES 
            WHERE LOWER(TABLE_SCHEMA) = LOWER(DATABASE()) 
              AND TABLE_NAME = '".$db->escape_string($full_table_name)."'
        ");
        $result = $db->fetch_array($query);
        $actual_collation = strtolower(trim($result['TABLE_COLLATION'] ?? ''));
        
        $actual_collation = str_replace('utf8mb3', 'utf8', $actual_collation);
        $expected_collation = str_replace('utf8mb3', 'utf8', $collation);

        if ($actual_collation !== $expected_collation) {
            return false;
        }
    }

    return true;
}

function lexicon_columnExists($table, $column) {
    global $db;

    $query = $db->query("SHOW COLUMNS FROM ".TABLE_PREFIX."".$table." LIKE '".$column."'");
	
    return $db->num_rows($query) > 0;
}
