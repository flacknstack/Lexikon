# Internal Forum Lexicon

This plugin adds a built-in lexicon to the forum. The lexicon provides a convenient way to compile a comprehensive and user-friendly reference guide for forum-specific information in one central location. Specific user groups, defined in the Admin Control Panel (ACP), can create entries for the lexicon and categorize them. Custom categories can also be created by selected groups. Submitted entries are immediately approved by the team. For users without access to the Mod Control Panel, the ACP allows you to choose whether their submissions should be reviewed beforehand. The user will then be notified of acceptance or rejection via private message (PM) or MyAlert. If the MyAlerts plugin is not installed, private messages will be sent accordingly. With MyAlerts installed, the team can configure in the settings whether to send an alert or a PM to the user. Similarly, you can configure whether users can edit and/or delete their own submitted entries.

The encyclopedia can include a table of contents, or rather a glossary, if desired. This is an alphabetical overview of all entries. Categories and entries can be sorted either by the displayed title in the menu or by manual sorting. Some entries are too extensive to be included in an existing entry, which is why it's also possible to mark entries as sub-entries. You can add not only standard entries to the encyclopedia but also extend the menu with external links. For example, if the board has a page where users can calculate their characters' graduation year, such links can be added to the menu without having to search within the entries themselves.

# Prerequisite

- The ACP module <a href="https://github.com/little-evil-genius/rpgstuff_modul" target="_blank">RPG Stuff</a> <b>must</b> be installed.

The <a href="https://doylecc.altervista.org/bb/downloads.php?dlid=26&cat=2" target="_blank">Accountswitcher</a> from doylecc <b>must</b> be installed.



``` # Database Changes
Added table:

- PREFIX_lexicon_categories
- PREFIX_lexicon_entries

# Settings
- Category groups
- Entry groups
- Entry verification
- Entry editing
- Entry deletion
- Notification system
- Category sorting
- Entry sorting
- Table of contents
- Sub-entries

# New template (not global!)
- lexicon_add_category
- lexicon_add_entry
- lexicon_add_sort
- lexicon_add_subentry
- lexicon_contents
- lexicon_contents_bit
- lexicon_contents_entries
- lexicon_edit_category
- lexicon_edit_entry
- lexicon_edit_externallink
- lexicon_entry
- lexicon_entry_option
- lexicon_header_banner
- lexicon_header_link
- lexicon_mainpage
- lexicon_menu
- lexicon_menu_add_cat
- lexicon_menu_add_entry
- lexicon_menu_cat
- lexicon_menu_cat_option
- lexicon_menu_entries
- lexicon_menu_externallink_option
- lexicon_menu_subentries
- lexicon_modcp
- lexicon_modcp_bit
- lexicon_modcp_edit
- lexicon_modcp_nav
- lexicon_search_results
- lexicon_search_results_bit

# New variable
- header: {$lexikon_newentry} and {$menu_lexicon}
- modcp_nav_users: {$nav_lexicon}

# New CSS - lexicon.css
It will automatically be added to any existing one and a new design added. You should save it – even in the default settings. Otherwise, it might be removed during a MyBB update.

Is the stylesheet missing from the master style after a MyBB upgrade? In the ACP module "RPG Extensions," you'll find the menu item "Check Stylesheets" and can add the stylesheet from any installed plugins.

#lexicon {
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
border-top-right-radius: 6px;d a:link,
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
}</blockquote>

# Importing Data from Ales Wiki Plugin:

If you have previously used Ales' Wiki plugin, this plugin offers an easy way to transfer existing categories and entries.

To perform the transfer, proceed as follows:<br>
<br>
1. <b>Navigate to the Transfer Page:</b><br>
In the ACP, you will find the menu item "Transfer Wiki Data" in the "RPG Extensions" module. Click on this item to open the transfer page.<br>
<br>
2. <b>Start the Transfer Process:</b><br>
Simply click on the "Transfer Data" button, and the transfer process will begin. You will receive a confirmation once the transfer is complete. Always report any problems in the SG support thread!<br>
<br>
3. <b>Uninstall the Wiki Plugin:</b><br>After the transfer has been successfully completed (check it beforehand), you can uninstall the wiki.-Uninstall the plugin safely, as all data has now been transferred to the new plugin.

Important note:

The transfer of in-play scenes must be completed before creating new scenes!



<b> ... # Demo
<img src="https://stormborn.at/plugins/lexikon_mainpage2.png">
<img src="https://stormborn.at/plugins/lexikon_ contents directory2.png">
<img src="https://stormborn.at/plugins/lexikon_entry2.png">
<img src="https://stormborn.at/plugins/lexikon_catadd2.png">
<img src="https://stormborn.at/plugins/lexikon_entryadd2.png">
<img src="https://stormborn.at/plugins/lexikon_search2.png">
<img src="https://stormborn.at/plugins/lexikon_modcp2.png">
}

#lexicon #navigation .navigation-headline:first-chil
