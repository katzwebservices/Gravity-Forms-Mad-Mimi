<?php
/*
Plugin Name: Gravity Forms Mad Mimi Add-On (Stable)
Plugin URI: http://www.gravityforms.com
Description: Integrates Gravity Forms with Mad Mimi allowing form submissions to be automatically sent to your Mad Mimi account
Version: 2.0 Stable
Requires at least: 3.2
Author: Katz Web Services, Inc.
Author URI: http://www.katzwebservices.com

------------------------------------------------------------------------
Copyright 2013 Katz Web Services, Inc.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

add_action('init',  array('GFMadMimi', 'init'));
register_activation_hook( __FILE__, array("GFMadMimi", "add_permissions"));

class GFMadMimi {

	private static $name = "Gravity Forms Mad Mimi Add-On";
    private static $path = "gravity-forms-madmimi/madmimi.php";
    private static $url = "http://www.gravityforms.com";
    private static $slug = "gravity-forms-madmimi";
    private static $version = "2.0.0";
    private static $min_gravityforms_version = "1.7";

    //Plugin starting point. Will load appropriate files
    public static function init(){
		global $pagenow;

		//loading translations
        load_plugin_textdomain('gravity-forms-madmimi', FALSE, '/gravity-forms-madmimi/languages' );

		if($pagenow === 'plugins.php') {
			add_action("admin_notices", array('GFMadMimi', 'is_gravity_forms_installed'), 10);
            add_action('admin_notices', array('GFMadMimi', 'is_beta_enabled'));
		}

		if(self::is_gravity_forms_installed(false, false) === 0){
			add_action('after_plugin_row_' . self::$path, array('GFMadMimi', 'plugin_row') );
           return;
        }

        if($pagenow == 'plugins.php' || defined('RG_CURRENT_PAGE') && RG_CURRENT_PAGE == "plugins.php"){

        	add_action('after_plugin_row_' . self::$path, array('GFMadMimi', 'plugin_row') );

            add_filter('plugin_action_links', array('GFMadMimi', 'settings_link'), 10, 2 );

        }

        if(!self::is_gravityforms_supported()){
           return;
        }

        if(is_admin()){
            //loading translations
            load_plugin_textdomain('gravity-forms-madmimi', FALSE, '/gravity-forms-madmimi/languages' );

            add_filter("transient_update_plugins", array('GFMadMimi', 'check_update'));
            #add_filter("site_transient_update_plugins", array('GFMadMimi', 'check_update'));

            //creates a new Settings page on Gravity Forms' settings screen
            if(self::has_access("gravityforms_madmimi")){
                RGForms::add_settings_page("Mad Mimi", array("GFMadMimi", "settings_page"), self::get_base_url() . "/images/madmimi_wordpress_icon_32.png");
            }
        }

        //integrating with Members plugin
        if(function_exists('members_get_capabilities'))
            add_filter('members_get_capabilities', array("GFMadMimi", "members_get_capabilities"));

        //creates the subnav left menu
        add_filter("gform_addon_navigation", array('GFMadMimi', 'create_menu'));

        if(self::is_madmimi_page()){

            //enqueueing sack for AJAX requests
            wp_enqueue_script(array("sack"));

            //loading data lib
            require_once(self::get_base_path() . "/data.php");


            //loading Gravity Forms tooltips
            require_once(GFCommon::get_base_path() . "/tooltips.php");
            add_filter('gform_tooltips', array('GFMadMimi', 'tooltips'));

            //runs the setup when version changes
            self::setup();

         }
         else if(in_array(RG_CURRENT_PAGE, array("admin-ajax.php"))){

            //loading data class
            require_once(self::get_base_path() . "/data.php");

            add_action('wp_ajax_rg_update_feed_active', array('GFMadMimi', 'update_feed_active'));
            add_action('wp_ajax_gf_select_madmimi_form', array('GFMadMimi', 'select_madmimi_form'));

        }
        else{
             //handling post submission.
            add_action("gform_post_submission", array('GFMadMimi', 'export'), 10, 2);
        }
    }

    /**
     * Check whether the both the beta and the stable are enabled. If enabled, print an admin notice.
     */
    public static function is_beta_enabled() {
        if(function_exists('load_KWSGFMadMimiAddon')) {
            $message = __('Both the Beta and the Stable version of the Gravity Forms Mad Mimi Add-On plugins are active. This may cause problems. Please disable one of them.', 'gravity-forms-madmimi');
            $disable_beta = '<a class="button button-secondary" href="'.wp_nonce_url(admin_url('plugins.php?action=deactivate&plugin=gravity-forms-mad-mimi/madmimi.beta.php'), 'deactivate-plugin_gravity-forms-mad-mimi/madmimi.beta.php').'">'.__('Disable Beta Version', 'gravity-forms-madmimi').'</a>';
            $disable_stable = '<a class="button button-secondary" href="'.wp_nonce_url(admin_url('plugins.php?action=deactivate&plugin=gravity-forms-mad-mimi/madmimi.php'), 'deactivate-plugin_gravity-forms-mad-mimi/madmimi.php').'">'.__('Disable Stable Version', 'gravity-forms-madmimi').'</a>';
            echo '<div id="message" class="error">'.'<h3>'.__('Gravity Forms Mad Mimi', 'gravity-forms-madmimi').'</h3>'.wpautop( $message ).wpautop( $disable_beta.' '.$disable_stable ).'</div>';
        }
    }

    public static function is_gravity_forms_installed($asd = '', $echo = true) {
		global $pagenow, $page; $message = '';

		$installed = 0;
		$name = self::$name;
		if(!class_exists('RGForms')) {
			if(file_exists(WP_PLUGIN_DIR.'/gravityforms/gravityforms.php')) {
				$installed = 1;

				$message .= __(sprintf('%sGravity Forms is installed but not active. %sActivate Gravity Forms%s to use the %s plugin.%s', '<p>', '<strong><a href="'.wp_nonce_url(admin_url('plugins.php?action=activate&plugin=gravityforms/gravityforms.php'), 'activate-plugin_gravityforms/gravityforms.php').'">', '</a></strong>', $name,'</p>'), 'gravity-forms-madmimi');

			} else {
				$message .= <<<EOD
<p><a href="http://katz.si/gravityforms?con=banner" title="Gravity Forms Contact Form Plugin for WordPress"><img src="http://gravityforms.s3.amazonaws.com/banners/728x90.gif" alt="Gravity Forms Plugin for WordPress" width="728" height="90" style="border:none;" /></a></p>
		<h3><a href="http://katz.si/gravityforms" target="_blank">Gravity Forms</a> is required for the $name</h3>
		<p>You do not have the Gravity Forms plugin installed. <a href="http://katz.si/gravityforms">Get Gravity Forms</a> today.</p>
EOD;
			}

			if(!empty($message) && $echo) {
				echo '<div id="message" class="updated">'.$message.'</div>';
			}
		} else {
			return true;
		}
		return $installed;
	}

	public static function plugin_row(){
        if(!self::is_gravityforms_supported()){
            $message = sprintf(__("%sGravity Forms%s is required. %sPurchase it today!%s"), "<a href='http://katz.si/gravityforms'>", "</a>", "<a href='http://katz.si/gravityforms'>", "</a>");
            self::display_plugin_message($message, true);
        }
    }

    public static function display_plugin_message($message, $is_error = false){
    	$style = '';
        if($is_error)
            $style = 'style="background-color: #ffebe8;"';

        echo '</tr><tr class="plugin-update-tr"><td colspan="5" class="plugin-update"><div class="update-message" ' . $style . '>' . $message . '</div></td>';
    }

    public static function update_feed_active(){
        check_ajax_referer('rg_update_feed_active','rg_update_feed_active');
        $id = $_POST["feed_id"];
        $feed = GFMadMimiData::get_feed($id);
        GFMadMimiData::update_feed($id, $feed["form_id"], $_POST["is_active"], $feed["meta"]);
    }

    //--------------   Automatic upgrade ---------------------------------------------------

    function settings_link( $links, $file ) {
        static $this_plugin;
        if( ! $this_plugin ) $this_plugin = plugin_basename(__FILE__);
        if ( $file == $this_plugin ) {
            $settings_link = '<a href="' . admin_url( 'admin.php?page=gf_madmimi' ) . '" title="' . __('Select the Gravity Form you would like to integrate with Mad Mimi. Contacts generated by this form will be automatically added to your Mad Mimi account.', 'gravity-forms-madmimi') . '">' . __('Feeds', 'gravity-forms-madmimi') . '</a>';
            array_unshift( $links, $settings_link ); // before other links
            $settings_link = '<a href="' . admin_url( 'admin.php?page=gf_settings&addon=Mad+Mimi' ) . '" title="' . __('Configure your Mad Mimi settings.', 'gravity-forms-madmimi') . '">' . __('Settings', 'gravity-forms-madmimi') . '</a>';
            array_unshift( $links, $settings_link ); // before other links
        }
        return $links;
    }


    //Returns true if the current page is an Feed pages. Returns false if not
    private static function is_madmimi_page(){
    	global $plugin_page; $current_page = '';
        $madmimi_pages = array("gf_madmimi");

        if(isset($_GET['page'])) {
			$current_page = trim(strtolower($_GET["page"]));
		}

        return (in_array($plugin_page, $madmimi_pages) || in_array($current_page, $madmimi_pages));
    }


    //Creates or updates database tables. Will only run when version changes
    private static function setup(){

        if(get_option("gf_madmimi_version") != self::$version)
            GFMadMimiData::update_table();

        update_option("gf_madmimi_version", self::$version);
    }

    //Adds feed tooltips to the list of tooltips
    public static function tooltips($tooltips){
        $madmimi_tooltips = array(
            "madmimi_contact_list" => "<h6>" . __("Mad Mimi List", "gravity-forms-madmimi") . "</h6>" . __("Select the Mad Mimi list you would like to add your contacts to.", "gravity-forms-madmimi"),
            "madmimi_gravity_form" => "<h6>" . __("Gravity Form", "gravity-forms-madmimi") . "</h6>" . __("Select the Gravity Form you would like to integrate with Mad Mimi. Contacts generated by this form will be automatically added to your Mad Mimi account.", "gravity-forms-madmimi"),
            "madmimi_map_fields" => "<h6>" . __("Map Fields", "gravity-forms-madmimi") . "</h6>" . __("Associate your Mad Mimi merge variables to the appropriate Gravity Form fields by selecting.", "gravity-forms-madmimi"),
            "madmimi_optin_condition" => "<h6>" . __("Opt-In Condition", "gravity-forms-madmimi") . "</h6>" . __("When the opt-in condition is enabled, form submissions will only be exported to Mad Mimi when the condition is met. When disabled all form submissions will be exported.", "gravity-forms-madmimi"),

        );
        return array_merge($tooltips, $madmimi_tooltips);
    }

    //Creates Mad Mimi left nav menu under Forms
    public static function create_menu($menus){

        // Adding submenu if user has access
        $permission = self::has_access("gravityforms_madmimi");
        if(!empty($permission))
            $menus[] = array("name" => "gf_madmimi", "label" => __("Mad Mimi", "gravity-forms-madmimi"), "callback" =>  array("GFMadMimi", "madmimi_page"), "permission" => $permission);

        return $menus;
    }

    public static function settings_page(){

        if(isset($_POST["uninstall"])){
            check_admin_referer("uninstall", "gf_madmimi_uninstall");
            self::uninstall();

            ?>
            <div class="updated fade" style="padding:20px;"><?php _e(sprintf("Gravity Forms Mad Mimi Add-On has been successfully uninstalled. It can be re-activated from the %splugins page%s.", "<a href='plugins.php'>","</a>"), "gravity-forms-madmimi")?></div>
            <?php
            return;
        }
        else if(isset($_POST["gf_madmimi_submit"])){
            check_admin_referer("update", "gf_madmimi_update");
            $settings = array("email" => stripslashes($_POST["gf_madmimi_username"]), "api_key" => stripslashes($_POST["gf_madmimi_api_key"]));
            update_option("gf_madmimi_settings", $settings);
        }
        else{
            $settings = get_option("gf_madmimi_settings");
        }

        $api = self::get_api();
		$message = '';
        if(!empty($settings["email"]) && !empty($settings["api_key"]) && empty($api->lastError)){
            $message = sprintf(__("Valid username and API key. Now go %sconfigure form integration with Mad Mimi%s!", "gravity-forms-madmimi"), '<a href="'.admin_url('admin.php?page=gf_madmimi').'">', '</a>');
            $class = "updated valid_credentials";
        }
        else if(!empty($settings["email"]) || !empty($settings["api_key"])){
            $message = __("Invalid username and/or API key. Please try another combination. (Message from Mimi: &ldquo;".$api->lastError.'&rdquo;)', "gravity-forms-madmimi");
            $class = "error invalid_credentials";
        } else if (empty($settings["email"]) && empty($settings["api_key"])) {
			$message = sprintf(__('%s%sDon\'t have a Mad Mimi account? %sSign up now!%s%s %sMad Mimi is a lovely, simple email service that lets you create, send and track emails. Over 32,000 businesses use Mad Mimi to handle email the simple way.%s%s%sSign up for an account today%s (it\'s even free with up to 100 contacts!)%s%s'), '<h3>', '<img class="alignleft" width="124" height="150" alt="'.__('Meet Mad Mimi', 'gravity-forms-madmimi').'" style="margin-right:1em" src="'.GFMadMimi::get_base_url().'/images/madmimi-banner.gif" />', '<a href="http://katz.si/mm">', '</a>', '</h3>', '<p>', '</p>', '<h4>', '<a href="http://katz.si/mm">', '</a>', '</h4>', '<div class="clear"></div>');
			$class = 'updated notice';
        }

		if($message) {
	        ?>
	        <div id="message" class="<?php echo $class ?>"><?php echo wpautop($message); ?></div>
	        <?php
        }
        ?>
        <form method="post" action="">
            <?php wp_nonce_field("update", "gf_madmimi_update") ?>
            <h3><?php _e("Mad Mimi Account Information", "gravity-forms-madmimi") ?></h3>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="gf_madmimi_username"><?php _e("Mad Mimi Account Email Address", "gravity-forms-madmimi"); ?></label> </th>
                    <td><input type="text" id="gf_madmimi_username" name="gf_madmimi_username" size="30" value="<?php echo !empty($settings["email"]) ? esc_attr($settings["email"]) :  '' ; ?>"/></td>
                </tr>
                <tr>
                    <th scope="row"><label for="gf_madmimi_api_key"><?php _e("API Key", "gravity-forms-madmimi"); ?></label> </th>
                    <td><input type="password" id="gf_madmimi_api_key" name="gf_madmimi_api_key" size="40" value="<?php echo !empty($settings["api_key"]) ? esc_attr($settings["api_key"]) : ''; ?>"/></td>
                </tr>
                <tr>
                    <td colspan="2" ><input type="submit" name="gf_madmimi_submit" class="button-primary" value="<?php _e("Save Settings", "gravity-forms-madmimi") ?>" /></td>
                </tr>

            </table>
            <div>

            </div>
        </form>

        <form action="" method="post">
            <?php wp_nonce_field("uninstall", "gf_madmimi_uninstall") ?>
            <?php if(GFCommon::current_user_can_any("gravityforms_madmimi_uninstall")){ ?>
                <div class="hr-divider"></div>

                <h3><?php _e("Uninstall Mad Mimi Add-On", "gravity-forms-madmimi") ?></h3>
                <div class="delete-alert"><?php _e("Warning! This operation deletes ALL Mad Mimi Feeds.", "gravity-forms-madmimi") ?>
                    <?php
                    $uninstall_button = '<input type="submit" name="uninstall" value="' . __("Uninstall Mad Mimi Add-On", "gravity-forms-madmimi") . '" class="button" onclick="return confirm(\'' . __("Warning! ALL Mad Mimi Feeds will be deleted. This cannot be undone. \'OK\' to delete, \'Cancel\' to stop", "gravity-forms-madmimi") . '\');"/>';
                    echo apply_filters("gform_madmimi_uninstall_button", $uninstall_button);
                    ?>
                </div>
            <?php } ?>
        </form>
        <?php
    }

    public static function madmimi_page(){
        $view = isset($_GET["view"]) ? $_GET["view"] : '';
        if($view == "edit")
            self::edit_page($_GET["id"]);
        else
            self::list_page();
    }

    //Displays the mad mimi feeds list page
    private static function list_page(){
        if(!self::is_gravityforms_supported()){
            die(__(sprintf("The Mad Mimi Add-On requires Gravity Forms %s. Upgrade automatically on the %sPlugin page%s.", self::$min_gravityforms_version, "<a href='plugins.php'>", "</a>"), "gravity-forms-madmimi"));
        }

        if(isset($_POST["action"]) && $_POST["action"] == "delete"){
            check_admin_referer("list_action", "gf_madmimi_list");

            $id = absint($_POST["action_argument"]);
            GFMadMimiData::delete_feed($id);
            ?>
            <div class="updated fade" style="padding:6px"><?php _e("Feed deleted.", "gravity-forms-madmimi") ?></div>
            <?php
        }
        else if (!empty($_POST["bulk_action"])){
            check_admin_referer("list_action", "gf_madmimi_list");
            $selected_feeds = $_POST["feed"];
            if(is_array($selected_feeds)){
                foreach($selected_feeds as $feed_id)
                    GFMadMimiData::delete_feed($feed_id);
            }
            ?>
            <div class="updated fade" style="padding:6px"><?php _e("Feeds deleted.", "gravity-forms-madmimi") ?></div>
            <?php
        }

        ?>
        <div class="wrap">
            <img alt="<?php _e("Mad Mimi Feeds", "gravity-forms-madmimi") ?>" src="<?php echo self::get_base_url()?>/images/madmimi_wordpress_icon_32.png" style="float:left; margin:15px 7px 0 0;"/>
            <h2><?php _e("Mad Mimi Feeds", "gravity-forms-madmimi"); ?>
            <a class="button add-new-h2" href="admin.php?page=gf_madmimi&view=edit&id=0"><?php _e("Add New", "gravity-forms-madmimi") ?></a>
            </h2>

			<ul class="subsubsub">
	            <li><a href="<?php echo admin_url('admin.php?page=gf_settings&addon=Mad+Mimi'); ?>">Mad Mimi Settings</a> |</li>
	            <li><a href="<?php echo admin_url('admin.php?page=gf_madmimi'); ?>" class="current">Mad Mimi Feeds</a></li>
	        </ul>

            <form id="feed_form" method="post">
                <?php wp_nonce_field('list_action', 'gf_madmimi_list') ?>
                <input type="hidden" id="action" name="action"/>
                <input type="hidden" id="action_argument" name="action_argument"/>

                <div class="tablenav">
                    <div class="alignleft actions" style="padding:8px 0 7px; 0">
                        <label class="hidden" for="bulk_action"><?php _e("Bulk action", "gravity-forms-madmimi") ?></label>
                        <select name="bulk_action" id="bulk_action">
                            <option value=''> <?php _e("Bulk action", "gravity-forms-madmimi") ?> </option>
                            <option value='delete'><?php _e("Delete", "gravity-forms-madmimi") ?></option>
                        </select>
                        <?php
                        echo '<input type="submit" class="button" value="' . __("Apply", "gravity-forms-madmimi") . '" onclick="if( jQuery(\'#bulk_action\').val() == \'delete\' && !confirm(\'' . __("Delete selected feeds? ", "gravity-forms-madmimi") . __("\'Cancel\' to stop, \'OK\' to delete.", "gravity-forms-madmimi") .'\')) { return false; } return true;"/>';
                        ?>
                    </div>
                </div>
                <table class="widefat fixed" cellspacing="0">
                    <thead>
                        <tr>
                            <th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th>
                            <th scope="col" id="active" class="manage-column check-column"></th>
                            <th scope="col" class="manage-column"><?php _e("Form", "gravity-forms-madmimi") ?></th>
                            <th scope="col" class="manage-column"><?php _e("Mad Mimi List", "gravity-forms-madmimi") ?></th>
                        </tr>
                    </thead>

                    <tfoot>
                        <tr>
                            <th scope="col" id="cb" class="manage-column column-cb check-column" style=""><input type="checkbox" /></th>
                            <th scope="col" id="active" class="manage-column check-column"></th>
                            <th scope="col" class="manage-column"><?php _e("Form", "gravity-forms-madmimi") ?></th>
                            <th scope="col" class="manage-column"><?php _e("Mad Mimi List", "gravity-forms-madmimi") ?></th>
                        </tr>
                    </tfoot>

                    <tbody class="list:user user-list">
                        <?php

                        $settings = GFMadMimiData::get_feeds();
                        if(is_array($settings) && !empty($settings)){
                            foreach($settings as $setting){
                                ?>
                                <tr class='author-self status-inherit' valign="top">
                                    <th scope="row" class="check-column"><input type="checkbox" name="feed[]" value="<?php echo $setting["id"] ?>"/></th>
                                    <td><img src="<?php echo self::get_base_url() ?>/images/active<?php echo intval($setting["is_active"]) ?>.png" alt="<?php echo $setting["is_active"] ? __("Active", "gravity-forms-madmimi") : __("Inactive", "gravity-forms-madmimi");?>" title="<?php echo $setting["is_active"] ? __("Active", "gravity-forms-madmimi") : __("Inactive", "gravity-forms-madmimi");?>" onclick="ToggleActive(this, <?php echo $setting['id'] ?>); " /></td>
                                    <td class="column-title">
                                        <a href="admin.php?page=gf_madmimi&view=edit&id=<?php echo $setting["id"] ?>" title="<?php _e("Edit", "gravity-forms-madmimi") ?>"><?php echo $setting["form_title"] ?></a>
                                        <div class="row-actions">
                                            <span class="edit">
                                            <a title="Edit this setting" href="admin.php?page=gf_madmimi&view=edit&id=<?php echo $setting["id"] ?>" title="<?php _e("Edit", "gravity-forms-madmimi") ?>"><?php _e("Edit", "gravity-forms-madmimi") ?></a>
                                            |
                                            </span>

                                            <span class="edit">
                                            <a title="<?php _e("Delete", "gravity-forms-madmimi") ?>" href="javascript: if(confirm('<?php _e("Delete this feed? ", "gravity-forms-madmimi") ?> <?php _e("\'Cancel\' to stop, \'OK\' to delete.", "gravity-forms-madmimi") ?>')){ DeleteSetting(<?php echo $setting["id"] ?>);}"><?php _e("Delete", "gravity-forms-madmimi")?></a>

                                            </span>
                                        </div>
                                    </td>
                                    <td class="column-date"><?php echo $setting["meta"]["contact_list_name"] ?></td>
                                </tr>
                                <?php
                            }
                        }
                        else {
                        	$api = self::get_api();
	                        if(!empty($api) && empty($api->lastError)){
	                            ?>
	                            <tr>
	                                <td colspan="4" style="padding:20px;">
	                                    <?php _e(sprintf("You don't have any Mad Mimi feeds configured. Let's go %screate one%s!", '<a href="'.admin_url('admin.php?page=gf_madmimi&view=edit&id=0').'">', "</a>"), "gravity-forms-madmimi"); ?>
	                                </td>
	                            </tr>
	                            <?php
	                        }
	                        else{
	                            ?>
	                            <tr>
	                                <td colspan="4" style="padding:20px;">
	                                    <?php _e(sprintf("To get started, please configure your %sMad Mimi Settings%s.", '<a href="admin.php?page=gf_settings&addon=Mad+Mimi">', "</a>"), "gravity-forms-madmimi"); ?>
	                                </td>
	                            </tr>
	                            <?php
	                        }
	                    }
                        ?>
                    </tbody>
                </table>
            </form>
        </div>
        <script type="text/javascript">
            function DeleteSetting(id){
                jQuery("#action_argument").val(id);
                jQuery("#action").val("delete");
                jQuery("#feed_form")[0].submit();
            }
            function ToggleActive(img, feed_id){
                var is_active = img.src.indexOf("active1.png") >=0
                if(is_active){
                    img.src = img.src.replace("active1.png", "active0.png");
                    jQuery(img).attr('title','<?php _e("Inactive", "gravity-forms-madmimi") ?>').attr('alt', '<?php _e("Inactive", "gravity-forms-madmimi") ?>');
                }
                else{
                    img.src = img.src.replace("active0.png", "active1.png");
                    jQuery(img).attr('title','<?php _e("Active", "gravity-forms-madmimi") ?>').attr('alt', '<?php _e("Active", "gravity-forms-madmimi") ?>');
                }

                var mysack = new sack("<?php echo admin_url("admin-ajax.php")?>" );
                mysack.execute = 1;
                mysack.method = 'POST';
                mysack.setVar( "action", "rg_update_feed_active" );
                mysack.setVar( "rg_update_feed_active", "<?php echo wp_create_nonce("rg_update_feed_active") ?>" );
                mysack.setVar( "feed_id", feed_id );
                mysack.setVar( "is_active", is_active ? 0 : 1 );
                mysack.encVar( "cookie", document.cookie, false );
                mysack.onError = function() { alert('<?php _e("Ajax error while updating feed", "gravity-forms-madmimi" ) ?>' )};
                mysack.runAJAX();

                return true;
            }
        </script>
        <?php
    }

    private static function get_api(){
        if(!class_exists("MadMimi"))
            require_once(self::get_base_path()."/lib/MadMimi.class.php");

        if(!class_exists('MadMimi_SuperClass'))
            require_once(self::get_base_path()."/lib/MadMimiSuperClass.class.php");

        $settings = get_option("gf_madmimi_settings");

        //global madmimi settings
        $api = new MadMimi_SuperClass(trim($settings['email']), trim($settings['api_key']));

        $lists = $api->Lists(true);

		if(!empty($api->lastError)) {
			return $api;
		} elseif(isset($api->lastRequest)) {
			if(is_wp_error($api->lastRequest) || (isset($api->lastRequest['response']['code']) && $api->lastRequest['response']['code'] !== 200)) {
				$api->lastError = $api->lastRequest['body'];
				return $api;
			}
		}

        return $api;
    }

    private static function edit_page(){
        ?>
        <style>
            .madmimi_col_heading{padding-bottom:2px; border-bottom: 1px solid #ccc; font-weight:bold;}
            .madmimi_field_cell {padding: 6px 17px 0 0; margin-right:15px;}
            .gfield_required{color:red;}

            .feeds_validation_error{ background-color:#FFDFDF;}
            .feeds_validation_error td{ margin-top:4px; margin-bottom:6px; padding-top:6px; padding-bottom:6px; border-top:1px dotted #C89797; border-bottom:1px dotted #C89797}

            .left_header{float:left; width:200px;}
            .margin_vertical_10{margin: 10px 0;}
            #madmimi_doubleoptin_warning{padding-left: 5px; padding-bottom:4px; font-size: 10px;}
        </style>
        <script type="text/javascript">
            var form = Array();
        </script>
        <div class="wrap">
            <img alt="<?php _e("Mad Mimi", "gravity-forms-madmimi") ?>" style="margin: 15px 7px 0pt 0pt; float: left;" src="<?php echo self::get_base_url() ?>/images/madmimi_wordpress_icon_32.png"/>
            <h2><?php _e("Mad Mimi Feed", "gravity-forms-madmimi") ?></h2>

        <?php
        //getting MadMimi API
        $api = self::get_api();

		//ensures valid credentials were entered in the settings page
        if(!empty($api->lastError)){
            ?>
            <div class="error" id="message" style="margin-top:20px;"><?php echo wpautop(sprintf(__("We are unable to login to Mad Mimi with the provided username and API key. Please make sure they are valid in the %sSettings Page%s", "gravity-forms-madmimi"), "<a href='?page=gf_settings&addon=Mad+Mimi'>", "</a>")); ?></div>
            <?php
            return;
        }

        //getting setting id (0 when creating a new one)
        $id = !empty($_POST["madmimi_setting_id"]) ? $_POST["madmimi_setting_id"] : absint($_GET["id"]);
        $config = empty($id) ? array("meta" => array(), "is_active" => true) : GFMadMimiData::get_feed($id);


        //getting merge vars from selected list (if one was selected)
        $merge_vars = empty($config["meta"]["contact_list_id"]) ? array() : self::listMergeVars($config["meta"]["contact_list_id"]);

        //updating meta information
        if(isset($_POST["gf_madmimi_submit"])){

			list($list_id, $list_name) = explode("|:|", stripslashes($_POST["gf_madmimi_list"]));
            $config["meta"]["contact_list_id"] = $list_id;
            $config["meta"]["contact_list_name"] = $list_name;
            $config["form_id"] = absint($_POST["gf_madmimi_form"]);

            $is_valid = true;
            $merge_vars = self::listMergeVars($config["meta"]["contact_list_id"]);

            $field_map = array();
            foreach($merge_vars as $var){
                $field_name = "madmimi_map_field_" . $var["tag"];
                $mapped_field = isset($_POST[$field_name]) ? stripslashes($_POST[$field_name]) : '';
                if(!empty($mapped_field)){
                    $field_map[$var["tag"]] = $mapped_field;
                }
                else{
                    unset($field_map[$var["tag"]]);
                    if($var["req"] == "Y")
                    $is_valid = false;
                }
                unset($_POST["{$field_name}"]);
            }

            // Go through the items that were not in the field map;
            // the Custom Fields
            foreach($_POST as $k => $v) {
            	if(preg_match('/madmimi\_map\_field\_/', $k)) {
            		$tag = str_replace('madmimi_map_field_', '', $k);
            		$field_map[$tag] = stripslashes($_POST[$k]);
	           	}
            }

			$config["meta"]["field_map"] = $field_map;
            #$config["meta"]["double_optin"] = !empty($_POST["madmimi_double_optin"]) ? true : false;
            #$config["meta"]["welcome_email"] = !empty($_POST["madmimi_welcome_email"]) ? true : false;

            $config["meta"]["optin_enabled"] = !empty($_POST["madmimi_optin_enable"]) ? true : false;
            $config["meta"]["optin_field_id"] = $config["meta"]["optin_enabled"] ? isset($_POST["madmimi_optin_field_id"]) ? $_POST["madmimi_optin_field_id"] : '' : "";
            $config["meta"]["optin_operator"] = $config["meta"]["optin_enabled"] ? isset($_POST["madmimi_optin_operator"]) ? $_POST["madmimi_optin_operator"] : '' : "";
            $config["meta"]["optin_value"] = $config["meta"]["optin_enabled"] ? $_POST["madmimi_optin_value"] : "";



            if($is_valid){
                $id = GFMadMimiData::update_feed($id, $config["form_id"], $config["is_active"], $config["meta"]);
                ?>
                <div class="updated fade" style="padding:6px"><?php echo sprintf(__("Feed Updated. %sback to list%s", "gravity-forms-madmimi"), "<a href='?page=gf_madmimi'>", "</a>") ?></div>
                <input type="hidden" name="madmimi_setting_id" value="<?php echo $id ?>"/>
                <?php
            }
            else{
                ?>
                <div class="error" style="padding:6px"><?php echo __("Feed could not be updated. Please enter all required information below.", "gravity-forms-madmimi") ?></div>
                <?php
            }
        }
  //      r($field_map);
//		r($config);
		if(!function_exists('gform_tooltip')) {
			require_once(GFCommon::get_base_path() . "/tooltips.php");
		}

        ?>
        <form method="post" action="">
            <input type="hidden" name="madmimi_setting_id" value="<?php echo $id ?>"/>
            <div class="margin_vertical_10">
                <label for="gf_madmimi_list" class="left_header"><?php _e("Mad Mimi List", "gravity-forms-madmimi"); ?> <?php gform_tooltip("madmimi_contact_list") ?></label>
                <?php

                //getting all contact lists
                $lists = $api->Lists();
				$lists = self::convert_xml_to_array($lists);

                if (!$lists){
                    echo __("Could not load Mad Mimi contact lists. <br/>Error: ", "gravity-forms-madmimi");
                    echo isset($api->errorMessage) ? $api->errorMessage : '';
                }
                else{
                    ?>
                    <select id="gf_madmimi_list" name="gf_madmimi_list" onchange="SelectList(jQuery(this).val());">
                        <option value=""><?php _e("Select a Mad Mimi List", "gravity-forms-madmimi"); ?></option>
                    <?php
                    foreach ($lists['list'] as $list){
                        $selected = $list["id"] == $config["meta"]["contact_list_id"] ? "selected='selected'" : "";
                        ?>
                        <option value="<?php echo esc_html($list['id']) . "|:|" . esc_html($list['name']) ?>" <?php echo $selected ?>><?php echo esc_html($list['name']) ?></option>
                        <?php
                    }
                    ?>
                  </select>
                <?php
                }
                ?>
            </div>

            <div id="madmimi_form_container" valign="top" class="margin_vertical_10" <?php echo empty($config["meta"]["contact_list_id"]) ? "style='display:none;'" : "" ?>>
                <label for="gf_madmimi_form" class="left_header"><?php _e("Gravity Form", "gravity-forms-madmimi"); ?> <?php gform_tooltip("madmimi_gravity_form") ?></label>

                <select id="gf_madmimi_form" name="gf_madmimi_form" onchange="SelectForm(jQuery('#gf_madmimi_list').val(), jQuery(this).val());">
                <option value=""><?php _e("Select a form", "gravity-forms-madmimi"); ?> </option>
                <?php
                $forms = RGFormsModel::get_forms();
                foreach($forms as $form){
                    $selected = absint($form->id) == $config["form_id"] ? "selected='selected'" : "";
                    ?>
                    <option value="<?php echo absint($form->id) ?>"  <?php echo $selected ?>><?php echo esc_html($form->title) ?></option>
                    <?php
                }
                ?>
                </select>
                &nbsp;&nbsp;
                <img src="<?php echo GFMadMimi::get_base_url() ?>/images/loading.gif" id="madmimi_wait" style="display: none;"/>
            </div>
            <div id="madmimi_field_group" valign="top" <?php echo empty($config["meta"]["contact_list_id"]) || empty($config["form_id"]) ? "style='display:none;'" : "" ?>>
                <div id="madmimi_field_container" valign="top" class="margin_vertical_10" >
                    <label for="madmimi_fields" class="left_header"><?php _e("Map Fields", "gravity-forms-madmimi"); ?> <?php gform_tooltip("madmimi_map_fields") ?></label>

                    <div id="madmimi_field_list">
                    <?php
                    if(!empty($config["form_id"])){

                        //getting list of all Mad Mimi merge variables for the selected contact list
                        if(empty($merge_vars))
                            $merge_vars = self::listMergeVars($list_id);

                        //getting field map UI
                        echo self::get_field_mapping($config, $config["form_id"], $merge_vars);

                        //getting list of selection fields to be used by the optin
                        $form_meta = RGFormsModel::get_form_meta($config["form_id"]);
                        $selection_fields = GFCommon::get_selection_fields($form_meta, $config["meta"]["optin_field_id"]);
                    }
                    ?>
                    </div>
                </div>

                <div id="madmimi_optin_container" valign="top" class="margin_vertical_10">
                    <label for="madmimi_optin" class="left_header"><?php _e("Opt-In Condition", "gravity-forms-madmimi"); ?> <?php gform_tooltip("madmimi_optin_condition") ?></label>
                    <div id="madmimi_optin">
                        <table>
                            <tr>
                                <td>
                                    <input type="checkbox" id="madmimi_optin_enable" name="madmimi_optin_enable" value="1" onclick="if(this.checked){jQuery('#madmimi_optin_condition_field_container').show('slow');} else{jQuery('#madmimi_optin_condition_field_container').hide('slow');}" <?php echo !empty($config["meta"]["optin_enabled"]) ? "checked='checked'" : ""?>/>
                                    <label for="madmimi_optin_enable"><?php _e("Enable", "gravity-forms-madmimi"); ?></label>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div id="madmimi_optin_condition_field_container" <?php echo empty($config["meta"]["optin_enabled"]) ? "style='display:none'" : ""?>>
                                        <div id="madmimi_optin_condition_fields" <?php echo empty($selection_fields) ? "style='display:none'" : ""?>>
                                            <?php _e("Export to Mad Mimi if ", "gravity-forms-madmimi") ?>

                                            <select id="madmimi_optin_field_id" name="madmimi_optin_field_id" class='optin_select' onchange='jQuery("#madmimi_optin_value").html(GetFieldValues(jQuery(this).val(), "", 20));'><?php echo $selection_fields ?></select>
                                            <select id="madmimi_optin_operator" name="madmimi_optin_operator" />
                                                <option value="is" <?php echo (isset($config["meta"]["optin_operator"]) && $config["meta"]["optin_operator"] == "is") ? "selected='selected'" : "" ?>><?php _e("is", "gravity-forms-madmimi") ?></option>
                                                <option value="isnot" <?php echo (isset($config["meta"]["optin_operator"]) && $config["meta"]["optin_operator"] == "isnot") ? "selected='selected'" : "" ?>><?php _e("is not", "gravity-forms-madmimi") ?></option>
                                            </select>
                                            <select id="madmimi_optin_value" name="madmimi_optin_value" class='optin_select'>
                                            </select>

                                        </div>
                                        <div id="madmimi_optin_condition_message" <?php echo !empty($selection_fields) ? "style='display:none'" : ""?>>
                                            <?php _e("To create an Opt-In condition, your form must have a drop down, checkbox or multiple choice field.", "gravityform") ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <script type="text/javascript">
                        <?php
                        if(!empty($config["form_id"])){
                            ?>
                            //creating Javascript form object
                            form = <?php echo GFCommon::json_encode($form_meta)?> ;

                            //initializing drop downs
                            jQuery(document).ready(function(){
                                var selectedField = "<?php echo str_replace('"', '\"', $config["meta"]["optin_field_id"])?>";
                                var selectedValue = "<?php echo str_replace('"', '\"', $config["meta"]["optin_value"])?>";
                                SetOptin(selectedField, selectedValue);
                            });
                        <?php
                        }
                        ?>
                    </script>
                </div>

                <div id="madmimi_submit_container" class="margin_vertical_10">
                    <input type="submit" name="gf_madmimi_submit" value="<?php echo empty($id) ? __("Save Feed", "gravity-forms-madmimi") : __("Update Feed", "gravity-forms-madmimi"); ?>" class="button-primary"/>
                </div>
            </div>
        </form>
        </div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$("#madmimi_check_all").live("change click load", function(e) {
				if(e.type == "load") {
					if($(".madmimi_checkboxes input").attr("checked")) {
						$(this).attr("checked", true);
					};
					return;
				}

				if($().prop) {
					$(".madmimi_checkboxes input").prop("checked", $(this).is(":checked"));
				} else {
					$(".madmimi_checkboxes input").attr("checked", $(this).is(":checked"));
				}
			}).trigger('load');

			<?php if(isset($_REQUEST['id']) && $_REQUEST['id'] == '0') { ?>
			$('#madmimi_field_list').live('load', function() {
				$('.madmimi_field_cell select').each(function() {
					var $select = $(this);
					if($().prop) {
						var label = $.trim($('label[for='+$(this).prop('name')+']').text());
					} else {
						var label = $.trim($('label[for='+$(this).attr('name')+']').text());
					}
					label = label.replace(' *', '');

					if($select.val() === '') {
						$('option', $select).each(function() {
							if($(this).text() === label) {
								if($().prop) {
									$('option:contains('+label+')', $select).prop('selected', true);
								} else {
									$('option:contains('+label+')', $select).prop('selected', true);
								}
							}
						});
					}
				});
			});
			<?php } ?>
		});
		</script>

		<script type="text/javascript">

            function SelectList(listId){
                if(listId){
                    jQuery("#madmimi_form_container").slideDown();
                    jQuery("#gf_madmimi_form").val("");
                }
                else{
                    jQuery("#madmimi_form_container").slideUp();
                    EndSelectForm("");
                }
            }

            function SelectForm(listId, formId){
                if(!formId){
                    jQuery("#madmimi_field_group").slideUp();
                    return;
                }

                jQuery("#madmimi_wait").show();
                jQuery("#madmimi_field_group").slideUp();

                var mysack = new sack("<?php bloginfo( 'wpurl' ); ?>/wp-admin/admin-ajax.php" );
                mysack.execute = 1;
                mysack.method = 'POST';
                mysack.setVar( "action", "gf_select_madmimi_form" );
                mysack.setVar( "gf_select_madmimi_form", "<?php echo wp_create_nonce("gf_select_madmimi_form") ?>" );
                mysack.setVar( "list_id", listId);
                mysack.setVar( "form_id", formId);
                mysack.encVar( "cookie", document.cookie, false );
                mysack.onError = function() {jQuery("#madmimi_wait").hide(); alert('<?php _e("Ajax error while selecting a form", "gravity-forms-madmimi") ?>' )};
                mysack.runAJAX();
                return true;
            }

            function SetOptin(selectedField, selectedValue){

                //load form fields
                jQuery("#madmimi_optin_field_id").html(GetSelectableFields(selectedField, 20));
                var optinConditionField = jQuery("#madmimi_optin_field_id").val();

                if(optinConditionField){
                    jQuery("#madmimi_optin_condition_message").hide();
                    jQuery("#madmimi_optin_condition_fields").show();
                    jQuery("#madmimi_optin_value").html(GetFieldValues(optinConditionField, selectedValue, 20));
                }
                else{
                    jQuery("#madmimi_optin_condition_message").show();
                    jQuery("#madmimi_optin_condition_fields").hide();
                }
            }

            function EndSelectForm(fieldList, form_meta){
                //setting global form object
                form = form_meta;

                if(fieldList){

                    SetOptin("","");

                    jQuery("#madmimi_field_list").html(fieldList);
                    jQuery("#madmimi_field_group").slideDown();
					jQuery('#madmimi_field_list').trigger('load');
                }
                else{
                    jQuery("#madmimi_field_group").slideUp();
                    jQuery("#madmimi_field_list").html("");
                }
                jQuery("#madmimi_wait").hide();
            }

            function GetFieldValues(fieldId, selectedValue, labelMaxCharacters){
                if(!fieldId)
                    return "";

                var str = "";
                var field = GetFieldById(fieldId);
                if(!field || !field.choices)
                    return "";

                var isAnySelected = false;

                for(var i=0; i<field.choices.length; i++){
                    var fieldValue = field.choices[i].value ? field.choices[i].value : field.choices[i].text;
                    var isSelected = fieldValue == selectedValue;
                    var selected = isSelected ? "selected='selected'" : "";
                    if(isSelected)
                        isAnySelected = true;

                    str += "<option value='" + fieldValue.replace("'", "&#039;") + "' " + selected + ">" + TruncateMiddle(field.choices[i].text, labelMaxCharacters) + "</option>";
                }

                if(!isAnySelected && selectedValue){
                    str += "<option value='" + selectedValue.replace("'", "&#039;") + "' selected='selected'>" + TruncateMiddle(selectedValue, labelMaxCharacters) + "</option>";
                }

                return str;
            }

            function GetFieldById(fieldId){
                for(var i=0; i<form.fields.length; i++){
                    if(form.fields[i].id == fieldId)
                        return form.fields[i];
                }
                return null;
            }

            function TruncateMiddle(text, maxCharacters){
                if(text.length <= maxCharacters)
                    return text;
                var middle = parseInt(maxCharacters / 2);
                return text.substr(0, middle) + "..." + text.substr(text.length - middle, middle);
            }

            function GetSelectableFields(selectedFieldId, labelMaxCharacters){
                var str = "";
                var inputType;
                for(var i=0; i<form.fields.length; i++){
                    fieldLabel = form.fields[i].adminLabel ? form.fields[i].adminLabel : form.fields[i].label;
                    inputType = form.fields[i].inputType ? form.fields[i].inputType : form.fields[i].type;
                    if(inputType == "checkbox" || inputType == "radio" || inputType == "select"){
                        var selected = form.fields[i].id == selectedFieldId ? "selected='selected'" : "";
                        str += "<option value='" + form.fields[i].id + "' " + selected + ">" + TruncateMiddle(fieldLabel, labelMaxCharacters) + "</option>";
                    }
                }
                return str;
            }

        </script>

        <?php

    }

    public static function add_permissions(){
        global $wp_roles;
        $wp_roles->add_cap("administrator", "gravityforms_madmimi");
        $wp_roles->add_cap("administrator", "gravityforms_madmimi_uninstall");
    }

    //Target of Member plugin filter. Provides the plugin with Gravity Forms lists of capabilities
    public static function members_get_capabilities( $caps ) {
        return array_merge($caps, array("gravityforms_madmimi", "gravityforms_madmimi_uninstall"));
    }

    public static function disable_madmimi(){
        delete_option("gf_madmimi_settings");
    }

    public static function select_madmimi_form(){

        check_ajax_referer("gf_select_madmimi_form", "gf_select_madmimi_form");
        $form_id =  intval($_POST["form_id"]);
        list($list_id, $list_name) =  explode("|:|", $_POST["list_id"]);
        $setting_id =  0; //intval($_POST["madmimi_setting_id"]);

        $api = self::get_api();
        if(!empty($api->lastError))
            die("EndSelectForm();");

        //getting list of all MadMimi merge variables for the selected contact list
        $merge_vars = self::listMergeVars($list_id);

        //getting configuration
        $config = GFMadMimiData::get_feed($setting_id);

        //getting field map UI
        $str = self::get_field_mapping($config, $form_id, $merge_vars);

        //fields meta
        $form = RGFormsModel::get_form_meta($form_id);
        //$fields = $form["fields"];
        die("EndSelectForm('" . str_replace("'", "\'", $str) . "', " . GFCommon::json_encode($form) . ");");
    }

    private static function get_field_mapping($config, $form_id, $merge_vars){

        //getting list of all fields for the selected form
        $form_fields = self::get_form_fields($form_id);
        $form = RGFormsModel::get_form_meta($form_id);

       	$usedFields = $customFields = array();

		$str = '';

        $str .= "<table cellpadding='0' cellspacing='0'><tr><td class='madmimi_col_heading'>" . __("List Fields", "gravity-forms-madmimi") . "</td><td class='madmimi_col_heading'>" . __("Form Fields", "gravity-forms-madmimi") . "</td></tr>";
        foreach($merge_vars as $var){
            $selected_field = (isset($config["meta"]) && isset($config["meta"]["field_map"]) && isset($config["meta"]["field_map"][$var["tag"]])) ? $config["meta"]["field_map"][$var["tag"]] : '';
            $required = $var["req"] == "Y" ? "<span class='gfield_required'>*</span>" : "";
            $error_class = $var["req"] == "Y" && empty($selected_field) && !empty($_POST["gf_madmimi_submit"]) ? " feeds_validation_error" : "";
            $str .= "<tr class='$error_class'><td class='madmimi_field_cell'><label for='madmimi_map_field_".$var['tag']."'>" . $var["name"]  . " $required</label></td><td class='madmimi_field_cell'>" . self::get_mapped_field_list($var["tag"], $selected_field, $form_fields) . "</td></tr>";
            $usedFields[$var["tag"]] = $var["name"];
        }
        $str .= '<tr><td colspan="2"><h3>'.__('Also add the additional fields to Mad Mimi:', "gravity-forms-madmimi").'</h3><p>'.__('This information will be stored as custom fields in Mad Mimi.', "gravity-forms-madmimi")."</p><p class='howto alignright' style='margin:0'><label for='madmimi_check_all'>".__('Check / Uncheck All Fields', "gravity-forms-madmimi")." <input type='checkbox' id='madmimi_check_all' /></label></p></td></tr>";
        foreach($form_fields as $field) {
        	$field['tag'] = str_replace('-', '_', sanitize_title($field[1]));
        	$tag = self::getNewTag($field['tag'], $customFields);

        	$selected_field = (isset($config["meta"]) && isset($config["meta"]["field_map"]) && isset($config["meta"]["field_map"][$tag])) ? $config["meta"]["field_map"][$tag] : '';

        	if(!isset($usedFields[$tag]) && !in_array($field[1], $usedFields) && $tag != 'name_first' && $tag != 'name_last') {
        		$str .= "<tr class='$error_class'><td class='madmimi_field_cell'><label for='madmimi_map_field_{$tag}' title='Create a \"{$field[1]}\" custom field for signups'>" . $field[1]  . "</label></td><td class='madmimi_field_cell madmimi_checkboxes'>" . self::get_mapped_field_checkbox($tag, $selected_field, $field) . "</td></tr>";
	        	$customFields[$tag] = $field[1];
	        }
        }
        $str .= "</table>";

		return $str;
    }

    private function getNewTag($tag, $used = array()) {
		if(isset($used[$tag])) {
			$i = 1;
			while($i < 1000) {
				if(!isset($used[$tag.'_'.$i])) {
					return $tag.'_'.$i;
				}
				$i++;
			}
		}
		return $tag;
    }

	private function listMergeVars($blank) {

		return array(
			array('tag'=>'email', 'req' => true, 'name' => __("Email")),
			array('tag'=>'first_name', 	  'req' => false, 'name' => __("Name (First)")),
			array('tag'=>'last_name',	  'req' => false, 'name' => __("Name (Last)")),
			array('tag'=>'title', 	  'req' => false, 'name' => __("Title")),
			array('tag'=>'company',  'req' => false, 'name' => __("Company")),
			array('tag'=>'home_number',   'req' => false, 'name' => __("Phone")),
			array('tag'=>'work_number',	  'req' => false, 'name' => __("Work Phone")),
			array('tag'=>'address','req' => false, 'name' => __("Address (Street Address)")),
			array('tag'=>'address_2','req' => false, 'name' => __("Address (Address Line 2)")),
			array('tag'=>'city',	  'req' => false, 'name' => __("Address (City)")),
			array('tag'=>'state', 'req' => false, 'name' => __("Address (State / Province)")),
			array('tag'=>'country',  'req' => false, 'name' => __("Address (Country)")),
			array('tag'=>'zip',	  'req' => false, 'name' => __("Address (Zip / Postal Code)")),
		);
	}


    public static function get_form_fields($form_id){
        $form = RGFormsModel::get_form_meta($form_id);
        $fields = array();

        //Adding default fields
        array_push($form["fields"],array("id" => "date_created" , "label" => __("Entry Date", "gravity-forms-madmimi")));
        array_push($form["fields"],array("id" => "ip" , "label" => __("User IP", "gravity-forms-madmimi")));
        array_push($form["fields"],array("id" => "source_url" , "label" => __("Source Url", "gravity-forms-madmimi")));

        if(is_array($form["fields"])){
            foreach($form["fields"] as $field){
                if(isset($field["inputs"]) && is_array($field["inputs"]) && $field['type'] !== 'checkbox' && $field['type'] !== 'select'){

                    //If this is an address field, add full name to the list
                    if(RGFormsModel::get_input_type($field) == "address")
                        $fields[] =  array($field["id"], GFCommon::get_label($field) . " (" . __("Full" , "gravity-forms-madmimi") . ")");

                    foreach($field["inputs"] as $input)
                        $fields[] =  array($input["id"], GFCommon::get_label($field, $input["id"]));
                }
                else if(empty($field["displayOnly"])){
                    $fields[] =  array($field["id"], GFCommon::get_label($field));
                }
            }
        }
        return $fields;
    }

    private static function get_address($entry, $field_id){
        $street_value = str_replace("  ", " ", trim($entry[$field_id . ".1"]));
        $street2_value = str_replace("  ", " ", trim($entry[$field_id . ".2"]));
        $city_value = str_replace("  ", " ", trim($entry[$field_id . ".3"]));
        $state_value = str_replace("  ", " ", trim($entry[$field_id . ".4"]));
        $zip_value = trim($entry[$field_id . ".5"]);
        $country_value = GFCommon::get_country_code(trim($entry[$field_id . ".6"]));

        $address = $street_value;
        $address .= !empty($address) && !empty($street2_value) ? "  $street2_value" : $street2_value;
        $address .= !empty($address) && (!empty($city_value) || !empty($state_value)) ? "  $city_value" : $city_value;
        $address .= !empty($address) && !empty($city_value) && !empty($state_value) ? "  $state_value" : $state_value;
        $address .= !empty($address) && !empty($zip_value) ? "  $zip_value" : $zip_value;
        $address .= !empty($address) && !empty($country_value) ? "  $country_value" : $country_value;

        return $address;
    }

    public static function get_mapped_field_list($variable_name, $selected_field, $fields){
        $field_name = "madmimi_map_field_" . $variable_name;
        $str = "<select name='$field_name' id='$field_name'><option value=''>" . __("", "gravity-forms-madmimi") . "</option>";
        foreach($fields as $field){
            $field_id = $field[0];
            $field_label = $field[1];

            $selected = $field_id == $selected_field ? "selected='selected'" : "";
            $str .= "<option value='" . $field_id . "' ". $selected . ">" . $field_label . "</option>";
        }
        $str .= "</select>";
        return $str;
    }

    public static function get_mapped_field_checkbox($variable_name, $selected_field, $field){
        $field_name = "madmimi_map_field_" . $variable_name;
        $field_id = $field[0];
        $str =  "<input name='$field_name' id='$field_name' type='checkbox' value='$field_id'";
        $selected = $field_id == $selected_field ? " checked='checked'" : false;
        if($selected) {
        	$str .= $selected;
        }

        $str .= " />";
        return $str;
    }

    public static function export($entry, $form){
        //Login to MadMimi
        $api = self::get_api();
        if(!empty($api->lastError))
            return;

        //loading data class
        require_once(self::get_base_path() . "/data.php");

        //getting all active feeds
        $feeds = GFMadMimiData::get_feed_by_form($form["id"], true);
        foreach($feeds as $feed){
            //only export if user has opted in
            if(self::is_optin($form, $feed))
                self::export_feed($entry, $form, $feed, $api);
        }
    }

    public static function export_feed($entry, $form, $feed, $api){

        $email = $entry[$feed["meta"]["field_map"]["email"]];

        $merge_vars = array('');
        foreach($feed["meta"]["field_map"] as $var_tag => $field_id){

            $field = RGFormsModel::get_field($form, $field_id);

            if($var_tag == 'address_full') {
                $merge_vars[$var_tag] = self::get_address($entry, $field_id);
            } else if($var_tag  == 'country') {
                $merge_vars[$var_tag] = empty($entry[$field_id]) ? '' : GFCommon::get_country_code(trim($entry[$field_id]));
            } else if($var_tag != "email") {
                if(!empty($entry[$field_id])) {
                    $merge_vars[$var_tag] = $entry[$field_id];
                } else {
                    foreach($entry as $key => $value) {
                        if(floor($key) == floor($field_id) && !empty($value)) {
                            $merge_vars[$var_tag][] = $value;
                        }
                    }
                }
            }
        }

        if(apply_filters('gf_madmimi_add_source', true) && isset($form['title'])) {
            $merge_vars['source_form'] = $form['title'];
        }

        $retval = $api->listSubscribe(array('name' => $feed["meta"]["contact_list_name"], 'id' => $feed["meta"]["contact_list_id"]), $email, $merge_vars, "html", false, false, true, false );

    }

    public static function uninstall(){

        //loading data lib
        require_once(self::get_base_path() . "/data.php");

        if(!GFMadMimi::has_access("gravityforms_madmimi_uninstall"))
            die(__("You don't have adequate permission to uninstall Mad Mimi Add-On.", "gravity-forms-madmimi"));

        //droping all tables
        GFMadMimiData::drop_tables();

        //removing options
        delete_option("gf_madmimi_settings");
        delete_option("gf_madmimi_version");

        //Deactivating plugin
        $plugin = "gravity-forms-madmimi/madmimi.php";
        deactivate_plugins($plugin);
        update_option('recently_activated', array($plugin => time()) + (array)get_option('recently_activated'));
    }

    public static function is_optin($form, $settings){
        $config = $settings["meta"];
        $operator = $config["optin_operator"];

        $field = RGFormsModel::get_field($form, $config["optin_field_id"]);
        $field_value = RGFormsModel::get_field_value($field, array());
        $is_value_match = is_array($field_value) ? in_array($config["optin_value"], $field_value) : $field_value == $config["optin_value"];

        return  !$config["optin_enabled"] || empty($field) || ($operator == "is" && $is_value_match) || ($operator == "isnot" && !$is_value_match);
    }


    private static function is_gravityforms_installed(){
        return class_exists("RGForms");
    }

    private static function is_gravityforms_supported(){
        if(class_exists("GFCommon")){
            $is_correct_version = version_compare(GFCommon::$version, self::$min_gravityforms_version, ">=");
            return $is_correct_version;
        }
        else{
            return false;
        }
    }

  	private function simpleXMLToArray($xml,
                    $flattenValues=true,
                    $flattenAttributes = true,
                    $flattenChildren=true,
                    $valueKey='@value',
                    $attributesKey='@attributes',
                    $childrenKey='@children'){

        $return = array();
        if(!($xml instanceof SimpleXMLElement)){return $return;}
        $name = $xml->getName();
        $_value = trim((string)$xml);
        if(strlen($_value)==0){$_value = null;};

        if($_value!==null){
            if(!$flattenValues){$return[$valueKey] = $_value;}
            else{$return = $_value;}
        }

        $children = array();
        $first = true;
        foreach($xml->children() as $elementName => $child){
            $value = self::simpleXMLToArray($child, $flattenValues, $flattenAttributes, $flattenChildren, $valueKey, $attributesKey, $childrenKey);
            if(isset($children[$elementName])){
                if($first){
                    $temp = $children[$elementName];
                    unset($children[$elementName]);
                    $children[$elementName][] = $temp;
                    $first=false;
                }
                $children[$elementName][] = $value;
            }
            else{
                $children[$elementName] = $value;
            }
        }
        if(count($children)>0){
            if(!$flattenChildren){$return[$childrenKey] = $children;}
            else{$return = array_merge($return,$children);}
        }

        $attributes = array();
        foreach($xml->attributes() as $name=>$value){
            $attributes[$name] = trim($value);
        }
        if(count($attributes)>0){
            if(!$flattenAttributes){$return[$attributesKey] = $attributes;}
            else{$return = array_merge($return, $attributes);}
        }

        return $return;
    }

    private function convert_xml_to_object($response) {
  		$response = @simplexml_load_string($response);  // Added @ 1.2.2
		if(is_object($response)) {
		    return $response;
		} else {
		    return false;
		}
    }

    private function convert_xml_to_array($response) {
        $response = self::convert_xml_to_object($response);
        $response = self::simpleXMLToArray($response);
        if(is_array($response)) {
            // If there's a single list, convert it to an array response
            if(!isset($response['list'][0])) {
                $response['list'] = array($response['list']);
            }
            return $response;
		} else {
		    return false;
		}
    }

    protected static function has_access($required_permission){
        $has_members_plugin = function_exists('members_get_capabilities');
        $has_access = $has_members_plugin ? current_user_can($required_permission) : current_user_can("level_7");
        if($has_access)
            return $has_members_plugin ? $required_permission : "level_7";
        else
            return false;
    }

    //Returns the url of the plugin's root folder
    protected function get_base_url(){
        return plugins_url(null, __FILE__);
    }

    //Returns the physical path of the plugin's root folder
    public function get_base_path(){
        $folder = basename(dirname(__FILE__));
        return WP_PLUGIN_DIR . "/" . $folder;
    }

}
