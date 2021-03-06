<?php
/*
 * Plugin Name: WishList Member&trade;
 * Plugin URI: http://member.wishlistproducts.com/
 * Description: <strong>WishList Member&trade;</strong> is the most comprehensive membership plugin for WordPress users. It allows you to create multiple membership levels, protect desired content and much more.   For more WordPress tools please visit the <a href="http://wishlistproducts.com/blog" target="_blank">WishList Products Blog</a>. Requires at least WordPress 3.0 and PHP 5.2
 * Author: WishList Products
 * Version: 2.80.2119
 * Author URI: http://customers.wishlistproducts.com/support
 * License: GPLv2
 * Text Domain: wishlist-member
 * SVN: 2119
 */

if (isset($_GET['wlmdebug'])) {
	@setcookie('wlmdebug', $_COOKIE['wlmdebug'] = (int) $_GET['wlmdebug']);
}
if (!empty($_COOKIE['wlmdebug'])) {
	define('WLMERRORREPORTING', $_COOKIE['wlmdebug'] + 0);
} else {
	@setcookie('wlmdebug', '');
	/*
	 * From now on we want to display error messages that needs to be fixed
	 * For now, we include WARNINGS but we want those taken care of as well
	 * in the future.  And perhaps even NOTICES as well.  But for now, we just
	 * stick with the very important ERRORS.
	 */
	$error_reporting = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;
	if ((int) phpversion() >= 5) {
		$error_reporting = $error_reporting | E_RECOVERABLE_ERROR;
	}
	if (defined('WP_DEBUG') && WP_DEBUG) {
		$error_reporting = $error_reporting | E_WARNING; //|E_STRICT;
		if (defined('E_DEPRECATED')) {
			$error_reporting = $error_reporting | E_DEPRECATED;
		}
		$_GET['wlmdebug'] = $error_reporting;
	}
	define('WLMERRORREPORTING', $error_reporting);
}

error_reporting(WLMERRORREPORTING);
require_once(dirname(__FILE__) . '/core/Functions.php');
require_once(dirname(__FILE__) . '/core/WishListMemberCache.php');
require_once(dirname(__FILE__) . '/core/WishlistAPIQueue.php');
require_once(dirname(__FILE__) . '/core/Class.php');
require_once(dirname(__FILE__) . '/core/WLMDB.php');
require_once(dirname(__FILE__) . '/core/PluginMethods.php');
require_once(dirname(__FILE__) . '/core/Api.php');
//require_once(dirname(__FILE__).'/core/API2.php');
require_once(dirname(__FILE__) . '/core/ShortCodes.php');
require_once(dirname(__FILE__) . '/core/AnotherWidget.php');
require_once(dirname(__FILE__) . '/core/User.php');
require_once(dirname(__FILE__) . '/core/Level.php');
require_once(dirname(__FILE__) . '/core/WishListAcl.php');
require_once(dirname(__FILE__) . '/core/WishlistDebug.php');
require_once(dirname(__FILE__) . '/core/api-helper/functions.php');
require_once(dirname(__FILE__) . '/core/TinyMCEPlugin.php');
require_once(dirname(__FILE__) . '/core/WishListXhr.php');

// -----------------------------------------
// Our plugin class
if (!class_exists('WishListMember')) {

	class WishListMember extends WishListMemberPluginMethods {

		var $extensions;
		var $wp_upload_path = '';
		var $wp_upload_path_relative = '';
		var $access_control = null;

		// -----------------------------------------
		// Constructor call
		function WishListMember() {
			$x = func_get_args();
			$this->GMT = get_option('gmt_offset') * 3600;

			$this->Constructor(__FILE__, $x[0], $x[1], $x[2], $x[3]);

			$extensions = glob($this->pluginDir . '/extensions/*.php');
			foreach ((array) $extensions AS $k => $ex) {
				if (basename($ex) == 'api.php') {
					unset($extensions[$k]);
				}
			}
			sort($extensions);
			$this->extensions = $extensions;

			// constant
			define('WLMDEFAULTEMAILPERHOUR', '100');
			define('WLMMEMORYALLOCATION', '128M');
			define('WLMUNSUBKEY', 'ffa4017f6494a6637ca2636031d29eb7');
			define('WLMREGCOOKIESECRET', 'z4tyh(*&^%tghjgyu#$RFGYUnbh9654rtyhg89ingt54');
			//make sure the value is set. if not, direct level reg won't work
			$wlmregcookietimeout = $this->GetOption('reg_cookie_timeout') ? $this->GetOption('reg_cookie_timeout') : 600;
			define('WLMREGCOOKIETIMEOUT', $wlmregcookietimeout);
			define('WLMREGISTERURL', get_bloginfo('url') . '/index.php?/register');

			define('DUPLICATEPOST_TIMEOUT', 3600); // we block duplicate POSTS for one hour
			define('WLM_BACKUP_PATH', WP_CONTENT_DIR . '/wishlist-backup/wishlist-member/');

			if (substr($this->Version, -11) == '2119' && !defined('WLMEMBER_EXPERIMENTAL')) {
				define('WLMEMBER_EXPERIMENTAL', 1);
			}

			// the WP upload path;
			$folder = str_replace(ABSPATH, '', get_option('upload_path'));
			if ($folder == ''
			)
				$folder = 'wp-content/uploads';
			$this->wp_upload_path_relative = $folder;
			$this->wp_upload_path = ABSPATH . $folder;

			// we want to make sure that we have the necessary default data for the current version
			$cver = $this->GetOption('CurrentVersion');

//                if($cver && $cver!=$this->Version){
			if ($cver != $this->Version) {
				//$this->Backup_Generate();
				$this->SaveOption('CurrentVersion', $this->Version);
				$this->Activate();
			}

			$pd = basename($this->pluginDir) . '/lang';
			load_plugin_textdomain('wishlist-member', PLUGINDIR . '/' . $pd, $pd);
		}

		// -----------------------------------------
		// Plugin activation
		function Activate() {
			global $wpdb;

			$this->CoreActivate();

			/* This is where you place code that runs on plugin activation */

			/* load all initial values */
			require_once($this->pluginDir . '/core/InitialValues.php');
			foreach ($WishListMemberInitialData AS $key => $value) {
				$this->AddOption($key, $value);
			}
			include_once($this->pluginDir . '/core/OldValues.php');
			foreach($WishListMemberOldInitialValues AS $key => $values) {
				foreach((array) $values AS $value) {
					if(strtolower(preg_replace('/\s/', '', $this->GetOption($key))) == strtolower(preg_replace('/\s/', '', $value))) {
						$this->SaveOption($key, $WishListMemberInitialData[$key]);
					}
				}
			}

			$apikey = $this->GetOption('genericsecret');
			if (empty($apikey)) {
				$apikey = md5(microtime());
			}

			$this->AddOption('WLMAPIKey', $apikey);

			/* set email sender information */
			$user = $this->Get_UserData(1);
			$name = trim($user->first_name . ' ' . $user->last_name);
			if (!$name) {
				$name = $user->display_name;
			}
			if (!$name) {
				$name = $user->user_nicename;
			}
			if (!$name) {
				$name = $user->user_login;
			}

			$this->AddOption('email_sender_name', $name);
			$this->AddOption('email_sender_address', $user->user_email);
			$this->AddOption('newmembernotice_email_recipient', $user->user_email);

			/* add file protection htaccess */
			$this->FileProtectHtaccess(!($this->GetOption('file_protection') == 1));

			/* create WishList Member DB Tables */
			$this->CreateWLMDBTables();

			$wpm_levels = $this->GetOption('wpm_levels');
			/* membership levels cleanup */
			foreach ($wpm_levels AS $key => $level) {
				/* add slugs to membership levels that don't have slugs */
				if (empty($level['slug'])) {
					$level['slug'] = $this->SanitizeString($level['name']);
				}
				/* sequential upgrade settings - turn off sequential upgrade for levels that have no upgrade to specified or have 0-day moves */
				if (empty($level['upgradeTo']) OR empty($level['upgradeMethod']) OR ($level['upgradeMethod'] == 'MOVE' && !((int) $level['upgradeAfter']))) {
					$level['upgradeMethod'] = '0';
					$level['upgradeTo'] = '0';
					$level['upgradeAfter'] = '0';
				}
				$wpm_levels[$key] = $level;
			}
			$this->SaveOption('wpm_levels', $wpm_levels);

			/* Sync Membership Content */
			$this->SyncContent();

			/* migrate old cydec (qpp) stuff to new cydec. qpp is now a separate deal */
			if ($this->GetOption('cydec_migrated') != 1) {
				if ($this->AddOption('cydecthankyou', $this->GetOption('qppthankyou'))) {
					$this->DeleteOption('qppthankyou');
				}

				if ($this->AddOption('cydecsecret', $this->GetOption('qppsecret'))) {
					$this->DeleteOption('qppsecret');
				}

				if ($this->GetOption('lastcartviewed') == 'qpp') {
					$this->SaveOption('lastcartviewed', 'cydec');
				}

				$wpdb->query("UPDATE `{$this->Tables->userlevel_options}` SET `option_value`=REPLACE(`option_value`,'QPP','CYDEC') WHERE `option_name`='transaction_id' AND `option_value` LIKE 'QPP\_%'");

				$this->SaveOption('cydec_migrated', 1);
			}

			$this->RemoveCronHooks();
			if (!empty($GLOBALS['wp_rewrite'])) {
				$GLOBALS['wp_rewrite']->flush_rules();
			}

			#fix for the 7month activation
			#set to automatically re-activate after 7 days
			$Month = 60 * 60 * 24 * 30;
			$checkafter = 60 * 60 * 24 * 7;
			$this->SaveOption('LicenseLastCheck', $WPWLTime - $Month + ($checkafter));

			/*
			 * we clear xxxssapxxx% entries in the database
			 * removed in WLM 2.8 to prevent security issues
			 */
			$wpdb->query("DELETE FROM `{$this->Tables->options}` WHERE `option_name` LIKE 'xxxssapxxx%'");
		}

		// -----------------------------------------
		// Plugin Deactivation
		function Deactivate() {
			//$this->Backup_Generate();
			// we delete magic page
			wp_delete_post($this->MagicPage(false), true);
			// remove file protection htaccess
			$this->FileProtectHtaccess(true);
			// remove the cron schedule. Glen Barnhardt 4/16/2010
			$this->RemoveCronHooks();
		}

		function HelpImproveNotification() {
			if ( ! is_admin() ) { return; }

			if ( isset( $_GET["helpimprove"] ) ) {
				if ( $_GET["helpimprove"] == 1 ) {
					$info_to_send = array(
					  "send_wlmversion"=>"on",
					  "send_phpversion"=>"on",
					  "send_apachemod"=>"on",
					  "send_webserver"=>"on",
					  "send_language"=>"on",
					  "send_apiused"=>"on",
					  "send_payment"=>"on",
					  "send_autoresponder"=>"on",
					  "send_webinar"=>"on",
					  "send_nlevels"=>"on",
					  "send_nmembers"=>"on",
					  "send_sequential"=>"on",
					  "send_customreg"=>"on"
					);
					$this->SaveOption('WLMSiteTracking',maybe_serialize($info_to_send));
					$this->SaveOption('show_helpimprove', 1);
					echo "<div class='updated fade'>" . __('<p>Thank You for helping us improve our product.</p>', 'wishlist-member') . "</div>";
				} else {
					$this->SaveOption('show_helpimprove', 1);
				}
			}

			$show_helpimprove = $this->GetOption('show_helpimprove');

			if ( $show_helpimprove === false ) {
					$yes = add_query_arg( 'helpimprove', '1' );
					$no = add_query_arg( 'helpimprove', '0' );
				echo <<<sc
					<div class='update-nag'>
						Do you want help improve WishList Member by sending anonymous usage statistics to our servers? &nbsp;
                    	<a href="{$yes}">Yes</a>&nbsp;|&nbsp;<a href="{$no}">No</a>
					</div>
sc;
			}
		}

		function WizardNotification() {
			if (!is_admin()) {
				return true;
			}
			$ran = $this->GetOption('wizard_ran');
			$levels = $this->GetOption('wpm_levels');
			//show dialog if after installation
			$show_dialog = (!$ran && count($levels) <= 0) ? true : false;
			//show dialog only if were in the wishlist-member dialog
			$show_dialog = ($show_dialog && wlm_arrval($_GET, 'page') == $this->MenuID) ? true : false;
			//show dialog except inside the wizard
			$show_dialog = ($show_dialog && wlm_arrval($_GET, 'mode') != 'wizard' && wlm_arrval($_GET, 'mode2') != 'logs') ? true : false;

			if ($show_dialog) {
				$q = "page=" . $this->MenuID . '&wl=settings&mode=wizard';
				echo <<<sc
                    <div id="wizardnotification" style="display:none; text-align: center">
                        <p style="text-align:center">We noticed this is the first time you are using WishList Member....</p>
                        <p style="text-align:center">Would you like to use the Setup Wizard?</p>
                        <p style="text-align:center"><a href="admin.php?$q" class="button-secondary">yes</a>&nbsp;&nbsp;&nbsp;
                            <a href="" id="nowizard" class="button-secondary">no</a>
                        </p>
                        <form id="wizardbypass" method="post">
                            <input type="hidden" name="wizardbypass" value="1"/>
                            <input type="hidden" name="WishListMemberAction" value="WizardSetup">
                        </form>
                    </div>
                    <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        imgLoader = new Image();// preload image
                        imgLoader.src = tb_pathToImage;
                        tb_show("", "?TB_inline=true&height=160&width=300&inlineId=wizardnotification", false);
                        $('#nowizard').live('click', function(ev) {
                            ev.preventDefault();
                            $('#wizardbypass').submit();
                        });

                    });
                    </script>
sc;
			}
		}

		function WizardSetup() {
			$current_levels = $this->GetOption('wpm_levels');
			if (wlm_arrval($_POST, 'wizardbypass') == 1) {
				$this->SaveOption('wizard_ran', 1);
				return;
			}

			$option_names = array(
				'wpm_levels',
				'default_protect',
				'only_show_content_for_level'
			);

			// initialize options
			$post = array();
			$post['default_protect'] = $_POST['default_protect'];
			$post['only_show_content_for_level'] = $_POST['default_protect'];

			$post['wpm_levels'] = $this->GetOption('wpm_levels');
			foreach ($_POST['membership_levels'] as $i => $val) {

				if (!empty($current_levels[$i])) {
					continue;
				}

				$level_id = time() + $i;
				$post['wpm_levels'][$level_id]['name'] = $val;
				$post['wpm_levels'][$level_id]['url'] = $this->MakeRegURL();
				$post['wpm_levels'][$level_id]['noexpire'] = 1;
				$post['wpm_levels'][$level_id]['loginredirect'] = '---';
				$post['wpm_levels'][$level_id]['afterregredirect'] = '---';
			}


			$pages = array(
				'non_members_error_page_internal' => $_POST['non_members_error_page_internal'],
				'membership_cancelled_internal' => $_POST['membership_cancelled_internal'],
				'wrong_level_error_page_internal' => $_POST['wrong_level_error_page_internal'],
				//
				'after_login_internal' => $_POST['after_login_internal'],
				'after_registration_internal' => $_POST['after_registration_internal']
			);
			//find out which pages are set to autocreate
			foreach ($pages as $i => &$item) {
				if (isset($_POST['autocreate_' . $i])) {
					$page_data = array();
					$page_data['post_type'] = 'page';
					$page_data['post_status'] = 'publish';
					$page_data['comment_status'] = 'closed';
					$id = $this->CreatePostFromTemplate($i, $page_data);
					$item = $id;
				}
			}
			//protect the after_login_internal page
			$this->Protect($pages['after_login_internal'], 'Y');
			//just to be safe
			unset($item);

			//merge the error pages settings with the options list
			$post = array_merge($post, $pages);
			$option_names = array_merge(array_keys($post), array_keys($pages));
			$post['WLOptions'] = implode(',', $option_names);

			$p = $_POST;
			$_POST = $post;
			$this->SaveOptions();
			$this->SaveOption('wizard_ran', 1);

			//add this user to the membership levels that he created
			global $current_user;
			$this->SetMembershipLevels($current_user->ID, array_keys($post['wpm_levels']));
			$_POST = $p;
			wp_redirect("admin.php?page=" . $this->MenuID . '&wl=settings&mode=wizard&saved=1');
		}

		function RemoveCronHooks() {
			$hooks = array(
				'wishlistmember_check_scheduled_cancelations',
				'wishlistmember_check_level_cancelations',
				'wishlistmember_email_queue',
				'wishlistmember_api_queue',
				'wishlistmember_sequential_upgrade',
				'wishlistmember_registration_notification',
				'wishlistmember_expring_members_notification',
				'wishlistmember_run_scheduled_user_levels'
			);
			$scheds = get_option('cron');
			foreach ($scheds AS $sched) {
				if (is_array($sched)) {
					foreach (array_keys($sched) AS $hook) {
						if (substr($hook, 0, 15) == 'wishlistmember_') {
							$hooks[] = $hook;
						}
					}
				}
			}
			$hooks = array_unique($hooks);

			foreach ($hooks AS $hook) {
				wp_clear_scheduled_hook($hook);
			}
		}

		// -----------------------------------------
		// Admin Head
		function AdminHead() {
			if (!(current_user_can('edit_post') || current_user_can('edit_posts') )) {
				echo "<style type=\"text/css\">\n\n/* WishList Member */\ndivul#dashmenu{ display:none; }\n#wphead{ border-top-width:2px; }\n#menu-dashboard,#screen-meta a.show-settings{display:none;}\n</style>\n";
			}
		}

		function ErrorHandler($errno, $errmsg, $errfile, $errline) {
			static $errcodes;

			if (!isset($errcodes)) {
				$errcodes = array(
					E_ERROR => 'Fatal run-time error',
					E_WARNING => 'Run-time warning',
					E_PARSE => 'Compile-time parse error',
					E_NOTICE => 'Run-time notice',
					E_CORE_ERROR => 'Fatal initial startup error',
					E_CORE_WARNING => 'Initial startup warning',
					E_COMPILE_ERROR => 'Fatal compile-time error',
					E_COMPILE_WARNING => 'Compile-time warnings',
					E_USER_ERROR => 'User-generated error',
					E_USER_WARNING => 'User-generated warning',
					E_USER_NOTICE => 'User-generated notice',
					E_STRICT => 'E_STRICT error',
					E_RECOVERABLE_ERROR => 'Catchable fatal error',
					E_DEPRECATED => 'E_DEPRECATED error',
					E_USER_DEPRECATED => 'E_USER_DEPRECATED error'
				);
			}

			if (substr($errfile, 0, strlen($this->pluginDir)) == $this->pluginDir) {
				echo '<br />WishList Member Debug. [This is a notification for developers who are working in WordPress debug mode.]';
				if (wlm_arrval($_GET, 'wlmdebug')) {
					$code = $errcodes[$errno];
					echo "<br />{$code}<br />$errmsg<br />Location: $errfile line number $errline<br />";
				}
			}
			return false;
		}

		// -----------------------------------------
		// Init Hook
		function UnsubJavaScript() {
			echo '<script type="text/javascript">alert("';
			_e('You have been unsubscribed from our mailing list.', 'wishlist-member');
			echo '");</script>';
		}

		// -----------------------------------------
		// Init Hook
		function Init() {
			//check for access levels
			//do not allow wlm to run it's own access_protection
			//let's control it via another plugin. That is much cleane
			global $wpdb;
			set_error_handler(array(&$this, 'ErrorHandler'), WLMERRORREPORTING);

			$this->MigrateLevelData();

			// migrate data pertaining to each content's membership level
			// this prepares us for user level content
			$this->MigrateContentLevelData();


			/*
			 * Handle request for anonymous data
			 */
			if (isset($_POST['wlm_anon'])) {
				if ($this->ValidateRequestForAnonData($_POST['wlm_anon_time'], $_POST['wlm_anon_hash'])) {
					echo maybe_serialize($this->ReturnAnonymousData());
				}
				exit;
			}

			/*
			 * Short Codes
			 */
			$this->wlmshortcode = new WishListMemberShortCode;

			/*
			 * Generate Transient Hash Session
			 * and Javascript Code
			 */
			if (isset($_GET['wlm_th'])) {
				list($field, $name) = explode(':', $_GET['wlm_th']);
				header("Content-type:text/javascript");
				$ckname = md5('wlm_transient_hash');
				$hash = md5($_SERVER['REMOTE_ADDR'] . microtime());
				setcookie("{$ckname}[{$hash}]", $hash, 0, '/');
				echo "<!-- \n\n";
				if ($field == 'field' && !empty($name)) {
					echo 'document.write("<input type=\'hidden\' name=\'' . $name . '\' value=\'' . $hash . '\' />");';
				} else {
					echo 'var wlm_cookie_hash="' . $hash . '";';
				}
				echo "\n\n// -->";
				exit;
			}
			/*
			 * End Transient Hash Code
			 */

			$wpm_levels = (array) $this->GetOption('wpm_levels');

			// load $this->attachments with list of attachments including resized versions
			/*
			 * WP Cron Hooks
			 */
			// Send Queued Email
			if (!wp_next_scheduled('wishlistmember_email_queue')) {
				wp_schedule_event(time(), 'hourly', 'wishlistmember_email_queue');
			}
			//process api queue
			if (!wp_next_scheduled('wishlistmember_api_queue')) {
				wp_schedule_event(time(), 'hourly', 'wishlistmember_api_queue');
			}
			// Sequential Upgrade
			if (!wp_next_scheduled('wishlistmember_sequential_upgrade')) {
				wp_schedule_event(time(), 'daily', 'wishlistmember_sequential_upgrade');
			}

			$unsubscribe_expired = $this->GetOption('unsubscribe_expired_members') ? $this->GetOption('unsubscribe_expired_members') : 0;
			if ($unsubscribe_expired) {
				// Unsubscribe Expired Members
				if (!wp_next_scheduled('wishlistmember_unsubscribe_expired')) {
					wp_schedule_event(time(), 'daily', 'wishlistmember_unsubscribe_expired');
				}
			}

			// Schedule the cron to run the cancelling of memberships. Glen Barnhardt 4-16-2010
			if (!wp_next_scheduled('wishlistmember_check_scheduled_cancelations')) {
				// schedule the event for 3AM each day.
				wp_schedule_event(mktime(3, 0, 0, date('n'), date('j'), date('Y')), 'daily', 'wishlistmember_check_scheduled_cancelations');
			}

			// Schedule the cron to run the cancelling of waiting level cancellations. Glen Barnhardt 10-27-2010
			if (!wp_next_scheduled('wishlistmember_check_level_cancelations')) {
				// schedule the event for 3AM each day.
				wp_schedule_event(mktime(3, 0, 0, date('n'), date('j'), date('Y')), 'daily', 'wishlistmember_check_level_cancelations');
			}

			// Schedule the cron to run the notification of members with incomplete registration. Fel Jun 10-27-2010
			if (!wp_next_scheduled('wishlistmember_registration_notification')) {
				// schedule the event hourly.
				wp_schedule_event(time(), 'hourly', 'wishlistmember_registration_notification');
			}

			// Schedule the cron to run the notification for expiring members. Peter 02-20-2013
			if (!wp_next_scheduled('wishlistmember_expring_members_notification')) {
				// schedule the event daily.
				wp_schedule_event(time(), 'daily', 'wishlistmember_expring_members_notification');
			}

			// Schedule the cron to run User Level modifications
			if (!wp_next_scheduled('wishlistmember_run_scheduled_user_levels')) {
				// schedule the event daily.
				wp_schedule_event(time(), 'daily', 'wishlistmember_run_scheduled_user_levels');
			}

			if (wlm_arrval($_GET, 'wlmfile')) {
				$this->FileProtectLoadAttachments();
				$this->FileProtect(wlm_arrval($_GET, 'wlmfile'));
			}
			if (wlm_arrval($_GET, 'wlmfolder')) {
				if ($this->GetOption('folder_protection') == 1) {
					$this->FolderProtect(wlm_arrval($_GET, 'wlmfolder'));
				}
			}

			$wpm_current_user = wp_get_current_user();

			if ((isset($_GET['wlmfolderinfo']) ) && ( $wpm_current_user->caps['administrator'] )) {

				//echo "<link rel='stylesheet' type='text/css' href='". get_bloginfo('wpurl'). "/wp-admin/css/colors-fresh.css'    />";
				echo "<link rel='stylesheet' type='text/css' href='" . get_bloginfo('wpurl') . "/wp-admin/css/wp-admin.css'    />";

				/* $files=glob($_GET['wlmfolderinfo']."/*.*");
				  foreach ($files as $file) {
				  echo "$file<br>";
				  }
				 */

				// security check. we dont want display list of all files on the  server right? we make it limited only to folder protection folder even for admin
				$needle = $this->GetOption('rootOfFolders');
				// echo "<br>needle->".$needle;
				$haystack = $_GET['wlmfolderinfo'];
				// echo "<br>haystack->".$haystack;
				$pos = strpos($haystack, $needle);

				if ($pos === false) {


					// echo "<br> string needle NOT found in haystack";
					die();
				} else {

					//echo "<br>string needle found in haystack";
				}



				if ($handle = opendir(wlm_arrval($_GET, 'wlmfolderinfo'))) {
					?>
					<div style="padding-top:5px;padding-left:20px;">
						<table>
							<tr>
								<th> URL</th>
							</tr>
							<?php
							while (false !== ($file = readdir($handle))) {
								// do something with the file
								// note that '.' and '..' is returned even
								if (!( ($file == '.') || ($file == '..') || ($file == '.htaccess'))) {
									?>
									<tr>

										<td> <?php echo $_GET['wlmfolderLinkinfo']; ?>/<?php echo $file ?> </td>

									</tr>

									<?php
								}
							}
							?>
						</table>
					</div>
					<?php
					closedir($handle);
				}


				die();
			}


			if (wlm_arrval($_GET, 'clearRecentPosts')) {
				if (is_admin()) {
					$this->DeleteOption('RecentPosts');
				}
			}

			// email confirmation
			if (wlm_arrval($_GET, 'wlmconfirm')) {
				list($uid, $hash) = explode('/', $_GET['wlmconfirm'], 2);
				$user = new WishListMemberUser($uid, true);
				$levelID = $user->ConfirmByHash($hash);
				if ($levelID) {
					// send welcome email
					$userinfo = $user->UserInfo->data;

					//get first name and last name using get_user_meta as $userinfo only got the display name
					$usermeta = get_user_meta($userinfo->ID, $key, $single);

					$macros = array(
						'firstname' => $usermeta['first_name'][0],
						'lastname' => $usermeta['last_name'][0],
						'email' => $userinfo->user_email,
						'username' => $userinfo->user_login,

						'memberlevel' => trim($wpm_levels[$levelID]['name'])
					);

					$this->WPMAutoLogin($uid);
					$_POST['log'] = $userinfo->user_login;
					$this->Login();
				}
			}

			/* we just save the original post and get data just in case we need them later */
			$this->OrigPost = $_POST;
			$this->OrigGet = $_GET;
			/* remove unsecure information */

			unset($this->OrigPost['password']);
			unset($this->OrigGet['password']);
			unset($this->OrigPost['password1']);
			unset($this->OrigGet['password1']);
			unset($this->OrigPost['password2']);
			unset($this->OrigGet['password2']);

			/* load extensions */
			foreach ((array) $this->extensions AS $extension) {
				include_once($extension);
				$this->RegisterExtension($WLMExtension['Name'], $WLMExtension['URL'], $WLMExtension['Version'], $WLMExtension['Description'], $WLMExtension['Author'], $WLMExtension['AuthorURL'], $WLMExtension['File']);
			}

			/* check for REST API Call */
			if (isset($_GET['WLMAPI'])) {
				list($func, $key, $params) = explode('/', $_GET['WLMAPI'], 3);
				$params = explode('/', $params);
				foreach ((array) $params AS $k => $v) { // find arrays.  arrays are specified by separating values with commas
					if (strpos($v, ',') !== false) {
						$params[$k] = explode(',', $v);
					}
				}
				echo WLMAPI::__remoteProcess($func, $key, $params);

				//record API used
				$api_used = $this->GetOption("WLMAPIUsed");
				$date = date("Y-m-d");
				if ($api_used) {
					$api_used = (array) maybe_unserialize($api_used);
					if (isset($api_used["api1"]) && $api_used["api1"]["date"] == $date) {
						$request = (int) $api_used["api1"]["request"];
						$api_used["api1"]["request"] = $request + 1;
					} else {
						$arr = array("request" => 1, "date" => $date);
						$api_used["api1"] = $arr;
					}
				} else {
					$arr = array("request" => 1, "date" => $date);
					$api_used["api1"] = $arr;
				}
				$this->SaveOption('WLMAPIUsed', maybe_serialize((array) $api_used));

				exit;
			}

			if (strpos($_SERVER['REQUEST_URI'], '/wlmapi/2.0/') !== false) {
				if (file_exists($this->pluginDir . '/core/API2.php')) {
					require_once('core/API2.php');
					preg_match('/\/wlmapi\/2\.0\/(xml|json|php)?\//i', $_SERVER['REQUEST_URI'], $return_type);
					$return_type = $return_type[1];
					$wlmapi = new WLMAPI2('EXTERNAL');
					switch ($wlmapi->return_type) {
						case 'XML':
							header('Content-type: text/xml');
							break;
						case 'JSON':
							header('Content-type: application/json');
							break;
						default:
							header('Content-type: text/plain');
							break;
					}
					echo $wlmapi->result;

					//record API used
					$api_used = $this->GetOption("WLMAPIUsed");
					$date = date("Y-m-d");
					if ($api_used) {
						$api_used = (array) maybe_unserialize($api_used);
						if (isset($api_used["api2"]) && $api_used["api2"]["date"] == $date) {
							$request = (int) $api_used["api2"]["request"];
							$api_used["api2"]["request"] = $request + 1;
						} else {
							$arr = array("request" => 1, "date" => $date);
							$api_used["api2"] = $arr;
						}
					} else {
						$arr = array("request" => 1, "date" => $date);
						$api_used["api2"] = $arr;
					}
					$this->SaveOption('WLMAPIUsed', maybe_serialize((array) $api_used));

					exit;
				}
			}

			if (!defined('WLMCANSPAM')) {
				define('WLMCANSPAM', sprintf(__("If you no longer wish to receive communication from us:\n%1\$s=%2\$s\n\nTo update your contact information:\n%3\$s", 'wishlist-member'), get_bloginfo("url") . '/?wlmunsub', '%s', get_bloginfo('wpurl') . '/wp-admin/profile.php'));
			}

			$this->Permalink = (bool) get_option('permalink_structure'); // we get permalink status

			if (wlm_arrval($_POST, 'cookiehash'))
				@setcookie('wishlist_reg_cookie', $_COOKIE['wishlist_reg_cookie'] = stripslashes(wlm_arrval($_POST, 'cookiehash')), 0, '/');

			if (wlm_arrval($_GET, 'wlmunsub')) {
				list($uid, $key) = explode('/', $_GET['wlmunsub']);
				$mykey = substr(md5($uid . WLMUNSUBKEY), 0, 10);
				$user = $this->Get_UserData($uid);
				if ($user->ID && $mykey == $key) {
					$this->Update_UserMeta($user->ID, 'wlm_unsubscribe', 1);
					if ($this->GetOption('unsub_notification') == 1) {
						$macros = array(
							'firstname' => $user->user_firstname,
							'lastname' => $user->user_lastname,
							'email' => $user->user_email,
							'username' => $user->user_login,
						);
						$recipient_email = trim($this->GetOption('unsubscribe_notice_email_recipient')) == '' ? get_bloginfo('admin_email') : $this->GetOption('unsubscribe_notice_email_recipient');
						$this->SendMail(trim($recipient_email), $this->GetOption('unsubscribe_notice_email_subject'), $this->GetOption('unsubscribe_notice_email_message'), $macros);
					}
					$url = $this->UnsubscribeURL();
					if ($url) {
						header('Location:' . $url);
						exit;
					} else {
						add_action('wp_head', array(&$this, 'UnsubJavaScript'));
					}
				}
			}
			if (wlm_arrval($_GET, 'loginlimit')) {
				$GLOBALS['error'] = $this->GetOption('login_limit_error');
			}

			// process registration URL...
			$scuri = $this->RegistrationURL();

			if (wlm_arrval($_GET, 'wpm_download_sample_csv') == 1)
				$this->SampleImportCSV();

			if ($scuri) {
				// strip out trailing .php
				$scuri = preg_replace('/\.php$/', '', $scuri);

				// match the URL with an SC Method
				$scuris = array_keys((array) $this->SCIntegrationURIs);
				foreach ((array) $scuris AS $x) {
					if ($this->GetOption($x) == $scuri) {
						$scuri = $x;
						break;
					}
				}

				// get the method name to call for the shoppingcart
				if (isset($this->SCIntegrationURIs[$scuri])) {
					$scmethod = $this->SCIntegrationURIs[$scuri];
					$_POST['WishListMemberAction'] = 'WPMRegister';
				} else {
					// not a valid SC Integration URI - we terminate.
					$this->CartIntegrationTerminate();
					// not a valid SC Integration URI - we redirect to homepage
					/*
					  header("Location: ".get_bloginfo('url'));
					  exit;
					 */
				}
			}



			switch (wlm_arrval($_POST, 'WishListMemberAction')) {
				case 'SaveCustomRegForm':
					$this->SaveCustomRegForm();
					break;
				case 'CloneCustomRegForm':
					$this->CloneCustomRegForm(wlm_arrval($_POST, 'form_id'));
					break;
				case 'DeleteCustomRegForm':
					$this->DeleteCustomRegForm(wlm_arrval($_POST, 'form_id'));
					break;
				case 'SaveMembershipLevels':
					$this->SaveMembershipLevels();
					break;
				case 'SaveMembershipContent':
					$this->SaveMembershipContent();
					break;
				case 'SaveMembershipContentPayPerPost':
					$this->SaveMembershipContentPayPerPost();
					break;
				case 'SaveMembershipFiles':
					$this->SaveMembershipFiles();
					break;
				case 'SaveMembershipFolders':
					$this->SaveMembershipFolders();
					break;
				case 'EasyFolderProtection':
					$this->EasyFolderProtection();
					break;
				case 'FolderProtectionParentFolder':
					$this->FolderProtectionParentFolder();
					break;
				case 'SaveMembersData':
					$this->SaveMembersData();
					break;
				case 'MoveMembership':
					$this->MoveMembership();
					break;
				case 'ImportMembers':
					require_once($this->pluginDir . '/core/UserSearch.php');
					$this->ImportMembers();
					break;
				case 'ExportMembers':
					require_once($this->pluginDir . '/core/UserSearch.php');
					$this->ExportMembers();
					break;
				case 'ExportSettingsToFile':
					$this->ExportSettingsToFile();
					break;
				/* start - backup stuff */

				case 'BackupSettings':
					$this->Backup_Generate();
					break;
				case 'RestoreSettings';
					$this->Backup_Restore($_POST['SettingsName'], false);
					break;
				case 'ImportSettings';
					$this->Backup_Import(wlm_arrval($_POST, 'backup_first') == 1);
					break;
				case 'ExportSettings':
					$this->Backup_Download(wlm_arrval($_POST, 'SettingsName'));
					break;
				case 'DeleteSettings':
					$this->Backup_Delete(wlm_arrval($_POST, 'SettingsName'));
					break;
				case 'ResetSettings':
					$this->ResetSettings();
					break;
				case 'WizardSetup':
					$this->WizardSetup();
					break;
				/* end - backup stuff */
				case 'SaveSequential':
					$this->SaveSequential();
					break;
				case 'WPMRegister':
					// Added by Admin
					if (true === wlm_admin_in_admin()) {
						$wpm_errmsg = '';
						$registered = $this->WPMRegister($_POST, $wpm_errmsg);
						if ($registered) {
							$_POST = array('msg' => __('<b>New Member Added.</b>', 'wishlist-member'));
						} else {
							$_POST['err'] = $wpm_errmsg;
						}
					} elseif ($_POST) {
						$docart = true;
						/*
						 * this is an attempt to prevent duplicate shopping cart registration posts
						 * from being processed it will definitely have its side effects but let's
						 * give it a try and see if people will complain
						 */

						if ($this->GetOption('PreventDuplicatePosts')) {
							if ($scmethod) {
								$now = time();
								$recentposts = (array) $this->GetOption('RecentPosts');
								/*
								 * we now compute posthash from both $_GET and $_POST and not
								 * just from $_POST because some integrations don't send $_POST
								 * data but $_GET.
								 */
								//$posthash=md5(serialize($_POST));
								$posthash = md5(serialize($_GET) . serialize($_POST));

								asort($recentposts);
								foreach ((array) array_keys((array) $recentposts) AS $k) {
									if ($recentposts[$k] < $now
									)
										unset($recentposts[$k]);
								}
								if ($recentposts[$posthash]) {
									$docart = false;
									$url = $this->DuplicatePostURL();
									if ($url == $wpm_request_url) {
										$url = get_bloginfo('url');
									}
									header("Location: {$url}");
									exit;
								} else {
									$recentposts[$posthash] = $now + DUPLICATEPOST_TIMEOUT;
								}
								$this->SaveOption('RecentPosts', $recentposts);
							}
						}
						if ($docart) {
							// we save original $_POST to see if it will change
							$op = serialize($_POST);
							if (!class_exists($scmethod['class'])) {
								include_once($this->pluginDir . '/lib/' . $scmethod['file']);
							}
							$this->RegisterClass($scmethod['class']);
							call_user_func(array(&$this, $scmethod['method']));

							//record shopping cart used
							$shoppingcart_used = $this->GetOption("WLMShoppinCartUsed");
							$date = date("Y-m-d H:i:s");
							if ($shoppingcart_used) {
								$shoppingcart_used = (array) maybe_unserialize($shoppingcart_used);
								$shoppingcart_used[$scmethod['method']] = $date;
							} else {
								$shoppingcart_used[$scmethod['method']] = $date;
							}
							$this->SaveOption('WLMShoppinCartUsed', maybe_serialize((array) $shoppingcart_used));
							/*
							  // $_POST didn't changed - nothing happened, we redirect to homepage. This avoids 404 to be returned for the SC URIs
							  if(serialize($_POST)==$op){
							  header("Location: ".get_bloginfo('url'));
							  exit;
							  }
							 */
						}
						$this->CartIntegrationTerminate();
					}
					break;
				case 'EmailBroadcast':
					// email broadcast
					$this->EmailBroadcast();
					break;
				case 'DoMarketPlaceActions':
					// marketplace actions
					$this->DoMarketPlaceActions();
			}

			// check that each level has a reg URL specified
			$changed = false;
			foreach ((array) array_keys((array) $wpm_levels) AS $k) {
				if (!$wpm_levels[$k]['url']) {
					$wpm_levels[$k]['url'] = $this->PassGen(6);
					$changed = true;
				}
			}
			if ($changed
			)
				$this->SaveOption('wpm_levels', $wpm_levels);

			// no levels configured
			if (!count($wpm_levels)) {
				add_action('admin_notices', array(&$this, 'ErrNoLevels'));
			}

			// check if all levels have expirations specified
			$unspecifiedexpiration = array();
			foreach ((array) $wpm_levels AS $level) {
				if (!wlm_arrval($level, 'expire') && !wlm_arrval($level, 'noexpire') && wlm_arrval($level, 'name')) {
					$unspecifiedexpiration[] = $level['name'];
				}
			}
			if (count($unspecifiedexpiration)) {
				$GLOBALS['unspecifiedexpiration'] = $unspecifiedexpiration;
				add_action('admin_notices', array(&$this, 'ErrNoExpire'));
			}


			// widget setup
			if (function_exists('wp_register_sidebar_widget')) {
				wp_register_sidebar_widget('wishlist-member', 'WishList Member', array(&$this, 'Widget'), null);
				wp_register_widget_control('wishlist-member', 'WishList Member', array(&$this, 'WidgetAdmin'));
			}

			$wpm_current_user = wp_get_current_user();
			// No profile editing for members
			if ($wpm_current_user->ID && basename(dirname($_SERVER['PHP_SELF'])) == 'wp-admin' && basename($_SERVER['PHP_SELF']) == 'profile.php' && !$this->GetOption('members_can_update_info') && !$wpm_current_user->caps['administrator'] && !$this->GetOption('members_can_update_info') && !current_user_can('level_8')) {
				header('Location:' . get_bloginfo('url'));
				exit;
			}



			// Do not allow access to Dashboard for non-admins
			if ($wpm_current_user->ID && basename(dirname($_SERVER['PHP_SELF'])) . '/' . basename($_SERVER['PHP_SELF']) == 'wp-admin/index.php' && !(current_user_can('edit_post') || current_user_can('edit_posts')) && !current_user_can('level_8')) {
				header('Location:profile.php');
				exit;
			}

			if ($wpm_current_user->ID)
				$this->DoSequential($wpm_current_user->ID);

			// spawn cron job if requested
			if (wlm_arrval($_GET, 'wlmcron') == 1) {
				spawn_cron();
				exit;
			}
			// send notification by force without waiting for the cron
			if (wlm_arrval($_GET, 'regnotification') == 1) {
				$this->NotifyRegistration();
				exit;
			}
			if (wlm_arrval($_GET, 'wlmprocessapiqueues') > 0) {
				$tries = wlm_arrval($_GET, 'wlmapitries');
				$tries = $tries ? $tries:5;
				$this->ProcessApiQueue(wlm_arrval($_GET, 'wlmprocessapiqueues'), $tries);
				exit;
			}

			// temporary fix for wpm_useraddress
			$this->FixUserAddress(1);

			//get term_ids for OnlyShowContentForLevel
			$this->taxonomyIds = array();

			$this->taxonomies = get_taxonomies(array('_builtin' => false), 'names');
			array_unshift($this->taxonomies, 'category');
			foreach ($this->taxonomies AS $taxonomy) {
				add_action($taxonomy . '_edit_form_fields', array(&$this, 'CategoryForm'));
				add_action($taxonomy . '_add_form_fields', array(&$this, 'CategoryForm'));
				add_action('create_' . $taxonomy, array(&$this, 'SaveCategory'));
				add_action('edit_' . $taxonomy, array(&$this, 'SaveCategory'));

				$this->taxonomyIds = array_merge($this->taxonomyIds, get_terms($taxonomy, array('fields' => 'ids', 'get' => 'all')));
			}
			// Cateogry Protection
			//error_reporting($error_reporting);
		}

		// Permanent Fix to recent comments
		// to enhance performance on large sites
		function RecentComments($comments = null, $obj = null) {
			if (is_active_widget(false, false, 'recent-comments', true) === false) {
				return $comments;
			}
			if (empty($comments)) {
				return $comments;
			}
			if (current_user_can('moderate_comments')) {
				return $comments;
			}

			global $current_user;
			$levels = $this->GetMembershipLevels($current_user->ID);
			remove_filter('the_comments', array(&$this, 'RecentComments'), 10, 2);

			// we only limit the number if no post_id is specified
			if (!$obj->query_vars['post_id']) {

				$limit = $obj->query_vars['number'];
				$obj->query_vars['number'] = 30;
			}

			$all_comments = $obj->query($obj->query_vars);

			if (!empty($current_user->ID)) {
				$comments = $this->GetMembershipContent('comments', $levels);
			} else {
				$protected_comments = $this->GetMembershipContent('comments');
				$protect = $this->ProtectedIds();
				$comments = array();
				foreach ($protect AS $pc) {
					$comments = array_merge($comments, (array) $pc);
				}
			}

			$the_comments = array();
			foreach ($all_comments as $c) {
				if (!in_array($c->comment_post_ID, $comments)) {
					$the_comments[] = $c;
				}
				// we only check limit if limit is set
				if (!empty($limit) && count($the_comments) >= $limit) {
					break;
				}
			}


			add_filter('the_comments', array(&$this, 'RecentComments'), 10, 2);
			return $the_comments;
		}

		function ErrNoLevels() {
			$wl = 'membershiplevels';
			if (wlm_arrval($_GET, 'wl') != $wl) {
				$addlevelurl = $this->GetMenu($wl);
				echo '<div class="error fade"><p>';
				printf(__("<strong>WishList Member Notice:</strong> No Membership Levels added yet. <a href='admin.php%1\$s'>Click here</a> to add a new membership level now.", 'wishlist-member'), $addlevelurl->URL);
				echo '</p></div>';
			}
		}

		function ErrNoExpire() {
			$wl = 'membershiplevels';
			$addlevelurl = $this->GetMenu($wl);
			$ue = $GLOBALS['unspecifiedexpiration'];
			$s = ' ';
			if (count($ue) > 1) {
				$ue[count($ue) - 1] = 'and ' . $ue[count($ue) - 1];
				$s = 's ';
			}
			$ue = str_replace(', and', ' and', '<b>' . implode(', ', $ue) . '</b>');
			echo '<div class="error fade"><p>';
			printf(__("<strong>WishList Member Notice:</strong> No expiration specified for membership level%1\$s%2\$s. <a href='admin.php%3\$s'>Click here</a> to correct this error.</strong>", 'wishlist-member'), $s, $ue, $addlevelurl->URL);
			echo '</p></div>';
		}

		function PreparePostPageOptions() {
			global $WishListMemberInstance;
			$post_types = array('post', 'page', 'attachment') + get_post_types(array('_builtin' => false));
			foreach ($post_types AS $post_type) {
				if ($post_type == 'attachment')
					add_meta_box('wlm_attachment_metabox', __('WishList Member', 'wishlist-member'), array(&$WishListMemberInstance, 'AttachmentOptions'), $post_type);
				else
					add_meta_box('wlm_postpage_metabox', __('WishList Member', 'wishlist-member'), array(&$WishListMemberInstance, 'PostPageOptions'), $post_type);
			}
		}

		// -----------------------------------------
		// Post / Page Options Hook
		function PostPageOptions() {
			global $post;

			if ($post->post_type == 'page') {
				$allindex = 'allpages';
				$ContentType = 'pages';
			} elseif ($post->post_type == 'post') {
				$allindex = 'allposts';
				$ContentType = 'posts';
			} else {
				$ContentType = $post->post_type;
				$allindex = 'all' . $post->post_type;
			}
			$wpm_levels = $this->GetOption('wpm_levels');
			$wpm_access = array_flip($this->GetContentLevels($ContentType, $post->ID));
			if (!$post->ID) {
				$wpm_protect = (bool) $this->GetOption('default_protect');
				$wlm_payperpost = (bool) $this->GetOption('default_ppp');
				$wlm_payperpost_free = false;
			} else {
				$wpm_protect = $this->Protect($post->ID);
				$wlm_payperpost = $this->PayPerPost($post->ID);
				$wlm_payperpost_free = $this->Free_PayPerPost($post->ID);
			}

			//Fix by Andy. If post is  fully new, we follow defualt protection by force.
			if ($post->post_status == 'auto-draft') {
				$wpm_protect = (bool) $this->GetOption('default_protect');
			}

			//Fix by Andy. If post is new but saved, we follow   user selected option to protect.
			if ($post->post_status == 'draft') {
				$wpm_protect = $this->Protect($post->ID) == 'Y';
			}
			//End fix

			include($this->pluginDir . '/admin/post_page_options.php');
		}

		// -----------------------------------------
		// Save Post / Page Hook
		function SavePostPage() {

			switch (wlm_arrval($_POST, 'post_type')) {
				case 'page':
					$ContentType = 'pages';
					break;
				case 'post':
					$ContentType = 'posts';
					break;
				default:
					$ContentType = $_POST['post_type'];
			}

			if (wlm_arrval($_POST, 'wpm_protect')) {
				// content protection
				$this->Protect($_POST['post_ID'], $_POST['wpm_protect']);
				$this->PayPerPost($_POST['post_ID'], $_POST['wlm_payperpost']);
				$this->Free_PayPerPost($_POST['post_ID'], $_POST['wlm_payperpost_free']);

				// content levels
				$this->SetContentLevels($ContentType, $_POST['post_ID'], $_POST['wpm_access'] ? array_keys((array) $_POST['wpm_access']) : array());

				// user post
				$user_post = (array) $_POST['user_post_access'];
				$remove_user_post = (array) $_POST['remove_user_post_access'];
				$user_post = array_diff($user_post, $remove_user_post);
				$this->AddPostUsers($ContentType, $_POST['post_ID'], $user_post);
				$this->RemovePostUsers($ContentType, $_POST['post_ID'], $remove_user_post);

				//specific system pages
				$option_names = array(
					"non_members_error_page_internal" => "non_members_error_page_internal_" . $_POST['post_ID'],
					"non_members_error_page" => "non_members_error_page_" . $_POST['post_ID'],
					"wrong_level_error_page_internal" => "wrong_level_error_page_internal_" . $_POST['post_ID'],
					"wrong_level_error_page" => "wrong_level_error_page_" . $_POST['post_ID'],
					"membership_cancelled_internal" => "membership_cancelled_internal_" . $_POST['post_ID'],
					"membership_cancelled" => "membership_cancelled_" . $_POST['post_ID'],
					"membership_expired_internal" => "membership_expired_internal_" . $_POST['post_ID'],
					"membership_expired" => "membership_expired_" . $_POST['post_ID'],
					"membership_forapproval_internal" => "membership_forapproval_internal_" . $_POST['post_ID'],
					"membership_forapproval" => "membership_forapproval_" . $_POST['post_ID'],
					"membership_forconfirmation_internal" => "membership_forconfirmation_internal_" . $_POST['post_ID'],
					"membership_forconfirmation" => "membership_forconfirmation_" . $_POST['post_ID'],
				);

				// saving of specific system pages optimized by mike lopez
				foreach (array_keys($option_names) AS $index) {
					if (substr($index, -9) == '_internal') {
						continue;

					}
					$index_internal = $index . '_internal';
					$value = trim($_POST[$option_names[$index]]);
					$value_internal = (int) $_POST[$option_names[$index_internal]];
					if (empty($value_internal) && empty($value)) {
						$this->DeleteOption($option_names[$index]);
						$this->DeleteOption($option_names[$index_internal]);
					} elseif ($value_internal > 0) {
						$this->DeleteOption($option_names[$index]);
						$this->SaveOption($option_names[$index_internal], $value_internal);
					} else {
						$this->SaveOption($option_names[$index], $value);
						$this->SaveOption($option_names[$index_internal], $value_internal);
					}

				}
			}

			// By Andy: Commnet protection wil be off for new post
			if (wlm_arrval($_POST, '_wp_http_referer') == '/wp-admin/post-new.php') {
				$oldlevels = $this->GetContentLevels('comments', $id);
				$levels = array_unique(array_merge($oldlevels, $_POST['wpm_access'] ? array_keys((array) $_POST['wpm_access']) : array()));
				$this->SetContentLevels('comments', $_POST['post_ID'], $levels);
			}
		}

		//attachment's options hook
		function AttachmentOptions() {
			global $post;
			$wpm_protect = $this->GetFileProtect($post->ID, 'Protection');
			$wpm_levels = $this->GetOption('wpm_levels');

			include($this->pluginDir . '/admin/attachment_options.php');
		}

		//save attachment (edit) hook
		function Update_Attachment_Protection() {
			global $post;

			//SAVE FILE PROTECTION (Y/N)
			if (wlm_arrval($_POST, 'wpm_protect') == 'Y')
				$this->SetFileProtection($post->ID, 'Protection', true);
			else
				$this->SetFileProtection($post->ID, 'Protection', false);

			//SAVE MEMBERSHIP LEVEL ACCESS
			//Put all membership levels in an array
			$wpmlevelids = array();
			$wpm_levels = $this->GetOption('wpm_levels');
			foreach ($wpm_levels as $id => $level)
				$wpmlevelids[] = $id;

			//Put all checked levels in an array
			$protectlevelids = array();
			foreach ($_POST['wpm_access'] as $id => $postID)
				$protectlevelids[] = $id;

			//protect
			$protect = array_intersect($wpmlevelids, $protectlevelids);
			foreach ((array) $protect AS $level)
				$this->SetFileProtection($post->ID, $level, true);

			// unprotect
			$unprotect = array_diff($wpmlevelids, $protectlevelids);
			foreach ((array) $unprotect AS $level)
				$this->SetFileProtection($post->ID, $level, false);
		}

		// -----------------------------------------
		// Delete user Hook
		function DeleteUser($id) {
			$levels = $this->GetMembershipLevels($id);
			$usr = $this->Get_UserData($id);
			if ($usr->ID) {
				foreach ((array) $levels AS $level) {
					$this->ARUnsubscribe($usr->first_name, $usr->last_name, $usr->user_email, $level);
				}
			}
		}

		function DeletedUser() {
			if($this->NODELETED_USER_HOOK) return;
			$this->SyncMembership(true);
		}

		// -----------------------------------------
		// Update profile Hook
		function ProfileUpdate() {
			if (!isset($_POST['wlm_updating_profile'])) {
				return;
			}
			$wpm_current_user = wp_get_current_user();
			if ($wpm_current_user->ID) {
				if (wlm_arrval($_POST, 'wlm_unsubscribe')) {
					$this->Delete_UserMeta($_POST['user_id'], 'wlm_unsubscribe');
				} else {
					$this->Update_UserMeta($_POST['user_id'], 'wlm_unsubscribe', 1);
				}
			}
			if ($wpm_current_user->caps['administrator']) {
				if (wlm_arrval($_POST, 'wlm_reset_limit_counter')) {
					$this->Delete_UserMeta($_POST['user_id'], 'wpm_login_counter');
				}
				if (wlm_arrval($_POST, 'wpm_delete_member')) {
					if (wlm_arrval($_POST, 'user_id') > 1) {
						wp_delete_user(wlm_arrval($_POST, 'user_id'));
					}
					$msg = __('<b>User DELETED.</b>', 'wishlist-member');
					$this->DeleteUser(wlm_arrval($_POST, 'user_id'));
				} elseif (wlm_arrval($_POST, 'wpm_send_reset_email')) {
					$msg = __('<b>Reset Password Link Sent to User.</b>', 'wishlist-member');
					$this->RetrievePassword($_POST['user_login'], true);
				} else {
					$this->SetMembershipLevels($_POST['user_id'], $_POST['wpm_levels']);
					// txn ids & timestamps
					foreach ((array) $_POST['wpm_levels'] AS $k) {
						if (preg_match('#.+[-/,:]#', $_POST['lvltime'][$k])) {
							$gmt = get_option('gmt_offset');
							if ($gmt >= 0) {
								$gmt = '+' . $gmt;
							}
							$gmt = ' ' . $gmt . ' GMT';
						} else {
							$gmt = '';
						}
						$this->SetMembershipLevelTxnID($_POST['user_id'], $k, $_POST['txnid'][$k]);
						$this->UserLevelTimestamp($_POST['user_id'], $k, strtotime($_POST['lvltime'][$k] . $gmt),true);
					}
					$this->Update_UserMeta($_POST['user_id'], 'wpm_login_limit', $_POST['wpm_login_limit']);
					$msg = __('Member Profile Updated.', 'wishlist-member');
				}
			}
			// address
			foreach ((array) $_POST['wpm_useraddress'] AS $k => $v) {
				$_POST['wpm_useraddress'][$k] = stripslashes($v);
			}
			$this->Update_UserMeta($_POST['user_id'], 'wpm_useraddress', $_POST['wpm_useraddress']);

			// custom fields
			$custom_fields = explode(',', $_POST['wlm_custom_fields_profile']);
			if (!empty($custom_fields)) {
				foreach ($custom_fields AS $field) {
					$this->Update_UserMeta($_POST['user_id'], 'custom_' . $field, $_POST[$field]);
				}
			}

			// custom hidden fields
			$custom_fields = explode(',', $_POST['wlm_custom_fields_profile_hidden']);
			if (!empty($custom_fields)) {
				foreach ($custom_fields AS $field) {
					$this->Update_UserMeta($_POST['user_id'], 'custom_' . $field, $_POST[$field]);
				}
			}

			// password hint
			if ($this->GetOption('password_hinting')) {
				$this->Update_UserMeta($_POST['user_id'], 'wlm_password_hint', trim($_POST['passwordhint']));
			}

			if (in_array($_REQUEST['wp_http_referer'], array('wlm', 'http://wlm'))) {
				$link = $this->GetMenu('members');
				header("Location:admin.php" . $link->URL . '&msg=' . urlencode($msg));
				exit;
			}
		}

		// -----------------------------------------
		// Login Hook
		function Login() {
			$user = $this->Get_UserData(0, $_POST['log']);

			// we want run seq upgrade once at login time to make sure user will be assigned to all levels.
			$sequential_individual_call_name = 'wlm_is_doing_sequential_for_' . $user->ID;
			delete_transient( $sequential_individual_call_name );

			if ($this->LoginCounter($user)) {
				// save IP
				$this->Update_UserMeta($user->ID, 'wpm_login_ip', $_SERVER['REMOTE_ADDR']);
				$this->Update_UserMeta($user->ID, 'wpm_login_date', time());

				//admin wants to go to wp-admin?
				//wordpress always sets the redirect_to to admin url when it's empty
				if (substr($_POST['redirect_to'], 0, strlen(admin_url())) == admin_url()) {
					if ($user->caps['administrator']) {
						/*
						  header('Location:'.$_POST['redirect_to']);
						  exit();
						 */
						// instead of redirecting ourselves, we just let WP handle redirects for admins
						return;
					}
					// now let's force a wishlist-member redirect
					$_POST['redirect_to'] = 'wishlistmember';
				}

				if (!empty($_POST['wlm_redirect_to'])) {
					if (wlm_arrval($_POST, 'wlm_redirect_to') == 'wishlistmember') {
						$_POST['redirect_to'] = 'wishlistmember';
					} else {
						header('Location:' . $_POST['wlm_redirect_to']);
						exit;
					}
				}

				if (wlm_arrval($_POST, 'redirect_to') == 'wishlistmember' || !$user->caps['administrator']) {

					// if redirect_to is not wishlistmember, then we let WP handle things for us
					if(wlm_arrval($_POST, 'redirect_to') != 'wishlistmember' && !$this->GetOption('enable_login_redirect_override')) {
						return;
					}
					// get levels
					$levels = (array) array_flip($this->GetMembershipLevels($user->ID));

					// fetch all levels
					$wpm_levels = $this->GetOption('wpm_levels');

					// inject pay per post settings
					$this->InjectPPPSettings($wpm_levels, 'U-' . $user->ID);

					// no levels? redirect to homepage
					if (!count($levels))
						header("Location:" . get_bloginfo('url'));

					// sort levels by level order and subscription timestamp
					$ts = $this->UserLevelTimestamps($user->ID);
					foreach ((array) array_keys((array) $levels) AS $level) {

						if (empty($wpm_levels[$level]['levelOrder'])) {
							$levelOrder = sprintf("%04d", 0); // This make 0 digit like  string 0000!
						} else {
							$levelOrder = sprintf("%04d", $wpm_levels[$level]['levelOrder']);
						}
						$levels[$level] = $levelOrder . ',' . $ts[$level] . ',' . $level;
					}

					asort($levels);

					// remove user level and make it the first entry to assure that it is the last option
					$ulevel = array('U-' . $user->ID => $levels['U-' . $user->ID]);
					unset($levels['U-' . $user->ID]);
					$levels = $ulevel + $levels;

					// fetch the last level in the array
					$levels = array_keys((array) $levels);
					$level = array_pop($levels);
					$url = $wpm_levels[$level]['loginredirect'];

					// now let's get that after login page
					if ($url == '---') {
						// Get default after login page
						$url = $this->GetOption('after_login_internal');
						$url = $url ? get_permalink($url) : trim($this->GetOption('after_login'));
					} elseif ($url == '') {
						// per level login reg is homepage
						$url = get_bloginfo('url');
					} else {
						// get permalink of per level after login page
						$url = get_permalink($url);
					}

					// if no after login url specified then set it to homepage
					if (!$url) $url = get_bloginfo('url');

					// redirect
					header("Location:" . $url);
					exit;
				}
			}
		}

		// -----------------------------------------
		// Logout Hook
		function Logout() {
			global $current_user;
			/* we no longer reduce the counter on log-out to avoid abusers from
			 * gaining sequential access using the same login info by logging in
			 * then logging out sequentially
			 */
			// remove current IP from the login counter list
			// $counter=(array)$this->Get_UserMeta($current_user->ID,'wpm_login_counter');
			// unset($counter[$_SERVER['REMOTE_ADDR']]);
			// $this->Update_UserMeta($current_user->ID,'wpm_login_counter',$counter);

			if ($this->NoLogoutRedirect !== true) { // we only do the logout redirect if this is not TRUE
				// added by Andy start
				// get levels
				$levels = array_flip($this->GetMembershipLevels($current_user->ID));

				// now let's get that after logout page
				//
				// no levels? redirect to homepage
				if (!count($levels)) {
					$url = site_url('wp-login.php', 'login');
					header("Location:" . $url);
					exit;
				} else {
					$url = '---'; // Todo,  if we want add logout redirect to each level
				}

				if ($url == '---') {
					// Get default after logout page
					$url = $this->GetOption('after_logout_internal');
					$url = $url ? get_permalink($url) : trim($this->GetOption('after_logout'));
				} elseif ($url == '') {
					// per level logout reg is homepage
					$url = get_bloginfo('url');
				} else {
					// get permalink of per level after logout page
					$url = get_permalink($url);
				}

				// if no after logout url specified then set it to homepage
				if (!$url
				)
					$url = get_bloginfo('url');

				//redirect
				header("Location:" . $url);
				exit;
				// added by Andy end
			}
		}

		// -----------------------------------------
		// Reset Password Hook
		function RetrievePassword($user_login, $internal = false) {
			global $wpdb;

			global $wp_version;

			$wpm_levels = (array) $this->GetOption('wpm_levels');


			if ($wp_version >= 3.7) {

				// Generate something random for a password reset key.
				$key = wp_generate_password( 20, false );

				/**
				 * Fires when a password reset key is generated.
				 *
				 * @since 2.5.0
				 *
				 * @param string $user_login The username for the user.
				 * @param string $key        The generated password reset key.
				 */
				do_action( 'retrieve_password_key', $user_login, $key );

				// Now insert the key, hashed, into the DB.
				if ( empty( $wp_hasher ) ) {
					require_once ABSPATH . 'wp-includes/class-phpass.php';
					$wp_hasher = new PasswordHash( 8, true );
				}
				$hashed = $wp_hasher->HashPassword( $key );
				$wpdb->update( $wpdb->users, array( 'user_activation_key' => $hashed ), array( 'user_login' => $user_login ) );

			} else {
				/* create our own reset key */
				/* start of code copied from wp-login.php */
				$key = $wpdb->get_var($wpdb->prepare("SELECT user_activation_key FROM $wpdb->users WHERE user_login = %s", $user_login));
				if (empty($key)) {
					// Generate something random for a key...
					$key = wp_generate_password(20, false);
					do_action('retrieve_password_key', $user_login, $key);
					// Now insert the new md5 key into the db
					$wpdb->query($wpdb->prepare("UPDATE $wpdb->users SET user_activation_key = %s WHERE user_login = %s", $key, $user_login));
				}
				/* end of copied code */
			}

			$user = $this->Get_UserData(0, $user_login);
			$macros = array();
			$macros['firstname'] = $user->first_name;
			$macros['lastname'] = $user->last_name;
			$macros['email'] = $user->user_email;
			$macros['memberlevel'] = $this->GetMembershipLevels($user->ID, true);
			$macros['username'] = $user->user_login;
			$macros['reseturl'] = wp_login_url() . '?action=rp&key=' . $key . '&login=' . rawurlencode($user->user_login);
			$this->SendMail($user->user_email, $this->GetOption('lostinfo_email_subject'), $this->GetOption('lostinfo_email_message'), $macros);

			//If not requested by ADMIN in the Member's profile page then do redirect
			if (!$internal) {
				header("Location:" . wp_login_url() . '?checkemail=confirm');
				exit;
			}
		}

		// -----------------------------------------
		// Footer Hook
		function Footer() {
			if ($this->GetOption('show_linkback')) {
				$aff = $this->GetOption('affiliate_id');
				$url = $aff ? 'http://member.wishlistproducts.com/wlp.php?af=' . $aff : 'http://member.wishlistproducts.com/';
				echo '<div align="center">' . sprintf(__('Powered by WishList Member - <a href="%1$s" target="_blank" title="Membership Software">Membership Software</a>', 'wishlist-member'), $url) . '</div>';
			}
		}

		// -----------------------------------------
		// Exclude certain pages from the list
		function ExcludePages($pages, $noerror = false) {
			$x = array_unique(array_merge($pages, array($this->MagicPage(false))));
			if (!$noerror) {
				$x[] = $this->GetOption('non_members_error_page_internal');
				$x[] = $this->GetOption('wrong_level_error_page_internal');
				$x[] = $this->GetOption('after_registration_internal');
				$x[] = $this->GetOption('membership_cancelled_internal');
				$x[] = $this->GetOption('membership_expired_internal');
				$x[] = $this->GetOption('membership_forapproval_internal');
				$x[] = $this->GetOption('membership_forconfirmation_internal');
				$x[] = $this->GetOption('unsubscribe_internal');
				// $x[]=$this->GetOption('after_login_internal');
				$x[] = $this->GetOption('after_logout_internal');

				//get the specific pages
				$y = $this->GetSpecificSystemPagesID();

				$x = array_merge($x, $y);


				if ($this->GetOption('exclude_pages')) {
					$wpm_levels = (array) $this->GetOption('wpm_levels');
					// exclude after reg pages for each level
					foreach ((array) $wpm_levels AS $level) {
						if (is_numeric($level['afterregredirect']))
							$x[] = $level['afterregredirect'];
					}
					/*
					  // exclude after login pages for each level
					  foreach((array)$wpm_levels AS $level){
					  if(is_numeric($level['loginredirect']))
					  $x[]=$level['loginredirect'];
					  }
					 */
				}
			}
			return array_unique($x);
		}

		// -----------------------------------------
		// Registration: Handle 404
		function The404($content) {
			// check if 404 is a category page request
			$cat = $GLOBALS['wp_query']->query_vars['cat'];
			if ($cat) {
				// if it's a category, check if the category has posts in it...
				$cat = get_category($cat);
				if ($cat && $cat->count) {
					// if the category has posts in it then chances are we are just hiding content
					// so we get the proper redirect URL...
					$redirect = is_user_logged_in() ? $this->WrongLevelURL() : $this->NonMembersURL();
					// and redirect
					header("Location:" . $redirect);
					exit;
				}
			}
			return $content;
		}

		// -----------------------------------------
		// Registration Page Handling
		function RegistrationPage($content) {
			$posts = $content;
			if (is_page() && count($posts)) {
				$post = &$posts[0];
				if ($post->ID == $this->MagicPage(false)) {
					$reg = $_GET['reg'];
					$payperpost = $this->IsPPPLevel($reg);
					$fallback = $this->IsFallbackURL($reg);
					$forapproval = $this->IsForApprovalRegistration($reg);
					if ($fallback && array_key_exists('email', $_POST)) {
						$user = $this->Get_UserData(0, 'temp_' . md5($_POST['email']));
						if (!$user) {
							$GLOBALS['wlm_fallback_error'] = 1;
						} else {
							$redirect = $this->GetContinueRegistrationURL(wlm_arrval($_POST, 'email'));
							header('Location:' . $redirect);
							exit;
						}
					}
					$wpm_levels = $this->GetOption('wpm_levels');
					if ((!$wpm_levels[$reg] && !$payperpost && !$fallback && !$forapproval) || !$this->RegistrationCookie(false, $hash, $reg)) {
						header("Location:" . get_bloginfo('url'));
						exit;
					}
					$this->RegistrationCookie(null, $hash, $reg);
					$post->post_content = $this->RegContent();
					if ($payperpost) {
						$post->post_title = sprintf(__('%1$s Pay Per Post Registration', 'wishlist-member'), $payperpost->post_title);
					} elseif ($forapproval) {
						if(strrpos($forapproval["level"], "payperpost") !== false){
							$post->post_title = sprintf(__('%1$s Pay Per Post Registration', 'wishlist-member'), $forapproval['level_settings']['name']);
						}else{
							$post->post_title = sprintf(__('%1$s Registration', 'wishlist-member'), $forapproval['level_settings']['name']);
						}
					} elseif ($fallback) {
						$post->post_title = sprintf(__('Enter Your Email to Continue', 'wishlist-member'), $wpm_levels[$reg]['name']);
						$post->post_content = $this->RegFallbackContent();
					} else {
						$post->post_title = sprintf(__('%1$s Registration', 'wishlist-member'), $wpm_levels[$reg]['name']);
					}
				}
			}

			unset($post); // <- very important so the loop below does not overwrite the value of the first entry in $posts

			$hasreg = false;
			foreach ($posts AS $post) {
				if (preg_match('/\[(wlm_|wlm)*register_.+]/i', $post->post_content)) {
					$hasreg = true;
					break;
				}
			}

			if ($hasreg) {
				$this->force_registrationform_scripts_and_styles = true;
			}

			return $posts;
		}

		// -----------------------------------------
		// The Heart of It All
		function Process($template) {
			global $wp_query;

			if (!$wp_query->post->ID) {
				if (is_tag()) {
					// get the tag if there appears to be no post and it's a tag page
					$xxx = get_tag($wp_query->query_vars['tag_id']);
				} else {

					//Check if the the category is really empty or not then redirect the user to error pages
					// if it's a category, check if the category has posts in it...
					if (is_category()) {
						$cat = $GLOBALS['wp_query']->query_vars['cat'];
						if ($cat) {
							$args = array('category' => $cat);
							$cat_posts = get_posts($args); //get the posts for the category
							if (empty($cat_posts)) { //if its empty, lets check if the category is really empty or WishList Member hides it
								$cat = get_category($cat);
								if ($cat->count > 0) { //if theres a post in this cat, redirect to non member page
									$redirect = is_user_logged_in() ? $this->WrongLevelURL() : $this->NonMembersURL();
									header("Location:" . $redirect);
									exit;
								}
							}
						}
					}
					// return $template if there's no post
					return $template;
				}

				if ($xxx->count) {
					// we really have at least a post in this tag but it's being hidden
					// so we redirect to the correct error page
					$redirect = is_user_logged_in() ? $this->WrongLevelURL() : $this->NonMembersURL();
					// and redirect
					header("Location:" . $redirect);
					exit;
				}
				// return $template if there's no post and it's not a tag page
				return $template;
			}

			// just return the template if it's a tag page
			if (is_tag())
				return $template;
			// get current user
			$wpm_current_user = wp_get_current_user();
			// give everything is user is admin
			if ($wpm_current_user->caps['administrator']
			)
				return $template;

			// Construct Full Request URL
			$wpm_request_url = $this->RequestURL();

			// get all levels
			$wpm_levels = (array) $this->GetOption('wpm_levels');

			// check if the requested URL is a special URL
			$specialurl = false;
			$regurl = get_bloginfo('url') . '/register/';
			foreach ((array) $wpm_levels AS $wpml)
				$specialurl = $specialurl | (bool) ($regurl . $wpml['url'] == $wpm_request_url);
			if ($specialurl
			)
				return $template;

			// process attachments
			if (is_attachment()) {
				$aid = $wp_query->query_vars['attachment_id'];
				if (!$aid && $wp_query->post->post_type == 'attachment') {
					$aid = $wp_query->post->ID;
				}
				$attachment = get_post($aid);
				// no parent post? return template as-is
				if (!$attachment->post_parent)
					return $template;

				// we clone the protection information from the parent
				$type = get_post_type($attachment->post_parent) == 'page' ? 'pages' : 'posts';
				$this->CloneProtection($attachment->post_parent, $aid, $type, 'posts');
			}
			// process pages and posts
			if (is_page() OR is_single()) {
				/* page/post becomes protected if a more tag is located and wpm_protect_after_more==1 */
				if ($this->GetOption('protect_after_more') && strpos($wp_query->post->post_content, '<!--more-->') !== false) {
					$protectmore = true;
				} else {
					$protectmore = false;
				}

				// is page or post protected?
				$protect = $protectmore || $this->Protect($wp_query->post->ID);

				/*
				  // post is protected if category is protected
				  $cats=wp_get_post_categories($wp_query->post->ID);
				  $protectcat=false;
				  foreach((array)$cats AS $cat) $protectcat=$protectcat|$this->CatProtected($cat);
				 */

				// page / post not protected so give them all
				if (!$protect)
					return $template;

				// if this is a userpost/payperpost then return if user has access to it
				$is_userpost = in_array($wp_query->post->ID, $this->GetMembershipContent($wp_query->post->post_type, 'U-' . $wpm_current_user->ID));
				if ($is_userpost)
					return $template;

				// page / post is excluded (special page) so give all
				if (in_array($wp_query->post->ID, $this->ExcludePages(array())))
					return $template;
			}

			// process categories
			if (is_category() || is_tax()) {
				if (is_category()) {
					$cat_ID = get_query_var('cat');
				} else {
					$cat_ID = get_term_by('slug', get_query_var('term'), get_query_var('taxonomy'));
					$cat_ID = $cat_ID->term_id;
				}
				// go ahead for non-protected categories
				if (!$this->CatProtected($cat_ID))
					return $template;
			}

			// retrieve page to display for non-members
			$nonmemberredirect = $this->NonMembersURL();

			if (!$wpm_current_user->ID) {
				$redirect = $nonmemberredirect;
				// redirect non-members
				if ($redirect != $wpm_request_url) {
					header('Location:' . $redirect);
					exit;
				}
			} else {
				// user is a member
				// check if any of the current user's membership levels have expired
				$activeLevels = $thelevels = (array) $this->GetMembershipLevels($wpm_current_user->ID, null, null, null, true);
				$timestamps = $this->UserLevelTimestamps($wpm_current_user->ID);
				$time = time();

				$expiredLevels = $unconfirmedLevels = $forAprovalLevels = $cancelledLevels = array();

				foreach ((array) $activeLevels AS $key => $thelevelid) {
					if ($this->LevelExpired($thelevelid, $wpm_current_user->ID)) {
						unset($activeLevels[$key]);
						$expiredLevels[] = $thelevelid;
					}
				}
				// no more levels left for this member? if so, redirect
				if (!count($activeLevels)) {
					$redirect = $this->ExpiredURL($nonmemberredirect);
					if ($redirect != $wpm_request_url) {
						header('Location:' . $redirect);
						exit;
					}
				}

				// check if any of the levels are for confirmation
				foreach ((array) $activeLevels AS $key => $thelevelid) {
					if ($this->LevelUnConfirmed($thelevelid, $wpm_current_user->ID)) {
						unset($activeLevels[$key]);
						$unconfirmedLevels[] = $thelevelid;
					}
				}
				// no more levels left for this member? if so, redirect to for confirmation page
				if (!count($activeLevels)) {
					$redirect = $this->ForConfirmationURL();
					if ($redirect != $wpm_request_url) {
						header('Location:' . $redirect);
						exit;
					}
				}

				// check if any of the levels are for approval
				foreach ((array) $activeLevels AS $key => $thelevelid) {
					if ($this->LevelForApproval($thelevelid, $wpm_current_user->ID)) {
						unset($activeLevels[$key]);
						$forAprovalLevels[] = $thelevelid;
					}
				}
				// no more levels left for this member? if so, redirect to for approval page
				if (!count($activeLevels)) {
					$redirect = $this->ForApprovalURL();
					if ($redirect != $wpm_request_url) {
						header('Location:' . $redirect);
						exit;
					}
				}

				// check if any of the levels are cancelled
				foreach ((array) $activeLevels AS $key => $thelevelid) {
					if ($this->LevelCancelled($thelevelid, $wpm_current_user->ID)) {
						unset($activeLevels[$key]);
						$cancelledLevels[] = $thelevelid;
					}
				}
				// no more levels left for this member? if so, redirect to cancelled page
				if (!count($activeLevels)) {
					$redirect = $this->CancelledURL();
					if ($redirect != $wpm_request_url) {
						header('Location:' . $redirect);
						exit;
					}
				}

				// check viewing status for each level (if all is selected for any)
				$canviewpage = $canviewcategory = $canviewpost = $canviewcomment = false;
				foreach ((array) $thelevels AS $thelevelid) {
					if (in_array($thelevelid, $activeLevels)) {
						$thelevel = $wpm_levels[$thelevelid];
						$canviewpage = $canviewpage | isset($thelevel['allpages']);
						$canviewcategory = $canviewcategory | isset($thelevel['allcategories']);
						$canviewpost = $canviewpost | isset($thelevel['allposts']);
						$canviewcomment = $canviewcomment | isset($thelevel['allcomments']);
					}
				}

				// check individual viewing status for each content type (post, page, category, comment)
				$wronglevel = $cancelled = $unconfirmed = $expired = $forapproval = false;

				if (!$canviewcomment && is_single()) {
					$access = array_intersect((array) $this->GetContentLevels('comments', $wp_query->post->ID), $activeLevels);

					if (empty($access)) {
						//check if it's a custom post type
						$protected_types = $this->GetOption('protected_custom_post_types');
						$post_type = get_post_type($wp_query->post);
						if (in_array($post_type, (array) $protected_types)) {
							$access = array_intersect((array) $this->GetContentLevels($post_type, $wp_query->post->ID), $activeLevels);
						} else {
							add_filter('comments_template', array(&$this, 'NoComments'));
						}
					}
				}

				if (!$canviewpage && is_page()) {
					$access = array_intersect((array) $this->GetContentLevels('pages', $wp_query->post->ID), $activeLevels);
					if (!empty($access)) {
						return $template;
					}
					$wronglevel = true;
				} elseif ((!$canviewcategory && is_category()) OR is_tax()) {
					$access = array_intersect((array) $this->GetContentLevels('categories', $cat_ID), $activeLevels);
					if (!empty($access)) {
						return $template;
					}
					$wronglevel = true;
				} elseif (!$canviewpost && is_single()) {
					$access = array_intersect((array) $this->GetContentLevels('posts', $wp_query->post->ID), $activeLevels);
					if (!empty($access)) {
						return $template;
					}
					$wronglevel = true;
				}



				if ($wronglevel) {
					if (!empty($expiredLevels)) {
						$redirect = $this->ExpiredURL();
					} elseif (!empty($unconfirmedLevels)) {
						$redirect = $this->ForConfirmationURL();
					} elseif (!empty($forAprovalLevels)) {
						$redirect = $this->ForApprovalURL();
					} elseif (!empty($cancelledLevels)) {
						$redirect = $this->CancelledURL();
					} else {
						$redirect = $this->WrongLevelURL();
					}

					if ($redirect != $wpm_request_url) {
						header('Location:' . $redirect);
						exit;
					}
				}
			}
			return $template;
		}

		// -----------------------------------------
		// Process Private Tags
		function TheContent($content) {
			global $current_user, $wp_query;
			$wpm_levels = (array) $this->GetOption('wpm_levels');

			/* process private tags */
			$content = $this->PrivateTags($content, $regtags);

			/* process merge codes */

			// in-page registration form
			foreach ((array) $regtags AS $level => $regtag) {
				// render the the reg form only when were supposed to
				if (preg_match('/\[' . $regtag . '\]/i', $content)) {
					$content = preg_replace('/\[' . $regtag . '\]/i', $this->RegContent($level, true), $content);
				}
			}

			if (is_feed()) {
				$uid = $this->VerifyFeedKey(wlm_arrval($_GET, 'wpmfeedkey'));
				if (!$uid) {
					$pid = $wp_query->post->ID;
					if ($this->Protect($pid)) {
						$excerpt_length = apply_filters('excerpt_length', 55);
						$excerpt_more = '';

						$content = strip_tags($content);
						$content = preg_split('/[\s]/', $content);

						if (count($content) > $excerpt_length) {
							list($content) = array_chunk($content, $excerpt_length);
							$excerpt_more = apply_filters('excerpt_more', ' [...]');
						}

						$content = implode(' ', $content) . $excerpt_more;
					}
				}
			}

			return $content;
		}

		// -----------------------------------------
		// Auto insert more tag
		function TheMore($posts) {
			if (is_page() || is_single()) {
				return $posts;
			}

			$isfeed = is_feed();
			$authenticatedfeed = false;
			if ($isfeed && isset($_GET['wpmfeedkey'])) {
				$authenticatedfeed = $this->VerifyFeedKey(wlm_arrval($_GET, 'wpmfeedkey'));
			}

			$autoinsert = $this->GetOption('auto_insert_more');
			$protectaftermore = $this->GetOption('protect_after_more');
			$insertat = $this->GetOption('auto_insert_more_at') + 0;
			if ($insertat < 1) {
				$insertat = 50;
			}
			for ($i = 0; $i < count($posts); $i++) {
				$content = trim($posts[$i]->post_content);
				$morefound = stristr($content, '<!--more-->');
				if ($morefound === false && $autoinsert) {
					$content = preg_split('/([\s<>\[\]])/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
					$tag = false;
					$wordcnt = 0;
					for ($ii = 0; $ii < count($content); $ii++) {
						$char = trim($content[$ii]);
						if ($tag === false && trim($content[$ii + 1]) != '') {
							if ($char == '<' || $char == '[') {
								$tag = $char == '<' ? '>' : ']';
							}
						} elseif ($char == $tag) {
							$tag = false;
						}
						if (!$tag && $char != '>' && $char != ']' && $char != '') {
							$wordcnt++;
						}
						if ($wordcnt >= $insertat) {
							$content[$ii].=' <!--more--> ';
							break;
						}
					}
					$content = implode('', $content);
				}
				if ($morefound || $autoinsert) {
					// if it's not an authenticated feed then we only return content before the "more" tag
					if ($isfeed && $protectaftermore && !$authenticatedfeed) {
						$content = preg_split('/<!--more-->/i', $content);
						$content = force_balance_tags($content[0]);
					}
				}
				$posts[$i]->post_content = $content;
			}
			return $posts;
		}

		// -----------------------------------------
		// Feed Links
		function FeedLink($link, $key = null) {
			if (is_null($key)) {
				$key = $this->FeedKey();
			}
			if ($key) {
				$param = 'wpmfeedkey=' . $key;
				if (!strpos($link, '?')) {
					$param = '?' . $param;
				} else {
					$param = '&' . $param;
				}
				$link.=$param;
			}
			return $link;
		}

		// -----------------------------------------
		// We want all mails sent by WordPress to have our configured sender's name and address
		// Overridden by the AR
		function MailFrom($c) {
			if ($this->SendingMail !== true
			)
				return $c; // we don't change anything if mail's not being sent by WishList Member
			if (is_array($this->ARSender)) {
				$x = $this->ARSender['email'];
			} else {
				$x = $this->GetOption('email_sender_address');
			}
			if (!$x
			)
				$x = $c;
			return $x;
		}

		function MailFromName($c) {
			if ($this->SendingMail !== true
			)
				return $c; // we don't change anything if mail's not being sent by WishList Member
			if (is_array($this->ARSender)) {
				$x = $this->ARSender['name'];
			} else {
				$x = $this->GetOption('email_sender_name');
			}
			if (!$x
			)
				$x = $c;
			return $x;
		}

		// -----------------------------------------
		// Widget
		function Widget($args, $return = false) {
			extract($args);
			$wpm_levels = $this->GetOption('wpm_levels');
			$wpm_current_user = wp_get_current_user();
			if ($this->GetOption('widget_nologinbox') != 1 || $wpm_current_user->ID) {
				if (!wlm_arrval($_GET, 'reg')) {

					$output = '';
					if (!$return) {
						echo $before_widget . $before_title;
						if ($wpm_current_user->ID) {
							if (isset($args["title"]))
								echo $args["title"];
							else
								echo $this->GetOption('widget_title');
						}else {
							if (isset($args["title2"]))
								echo $args["title2"];
							else
								echo $this->GetOption('widget_title2');
						}
						echo $after_title;
						echo "<div id='wlmember_loginwidget'>";
					}
					if ($wpm_current_user->ID) {
						$name = $wpm_current_user->first_name;
						if (!$name
						)
							$name = $wpm_current_user->user_nicename;
						if (!$name
						)
							$name = $wpm_current_user->user_login;
						$output.='<p>' . trim(sprintf(__('Welcome %1$s', 'wishlist-member'), $name)) . ',</p>';
						$levels = $this->GetMembershipLevels($wpm_current_user->ID, null, null, null, true);
						$inactivelevels = $this->GetMemberInactiveLevels($wpm_current_user->ID);
						sort($levels); // <- we sort the levels
						if (!$this->GetOption('widget_hidelevels')) {
							$clevels = count($levels);

							if ($clevels) {
								//	$output.=__("&raquo; Level", "&raquo; Levels", $clevels, 'wishlist-member');
								if ($clevels == 1) {
									$output.=__("&raquo; Level", 'wishlist-member');
								} else {
									$output.=__("&raquo; Levels", 'wishlist-member');
								}

								$output.=': ';
								if ($clevels > 1)
									$output.='<br /><div id="" style="margin-left:1em">';
								$morelevels = false;
								$maxmorelevels = $return ? 1000000000 : 2;
								for ($i = 0; $i < $clevels; $i++) {
									if ($i > $maxmorelevels && !$morelevels) {
										$output.='<div id="wlm_morelevels" style="display:none">';
										$morelevels = true;
									}
									if ($clevels > 1
									)
										$output.='&middot; ';
									$strike = '';
									if (in_array($levels[$i], $inactivelevels)) {
										$output.='<strike>';
										$strike = '</strike>';
									}
									$output.=$wpm_levels[$levels[$i]]['name'];
									$output.=$strike;
									$output.='<br />';
								}
								if ($morelevels) {
									$output.='</div>';
									$output.='&middot; <label style="cursor:pointer;" onclick="wlmml=document.getElementById(\'wlm_morelevels\');wlmml.style.display=wlmml.style.display==\'none\'?\'block\':\'none\';this.innerHTML=wlmml.style.display==\'none\'?\'' . __('More levels', 'wishlist-member') . ' <small>&nabla;</small>\':\'' . __('Less levels', 'wishlist-member') . ' <small>&Delta;</small>\';this.blur()">' . __('More levels', 'wishlist-member') . ' <small>&nabla;</small></label>';
								}
								if ($clevels > 1)
									$output.='</div>';
							}
						}

						if ($this->GetOption('members_can_update_info')) {
							$output.='&raquo; <a href="' . get_bloginfo('wpurl') . '/wp-admin/profile.php">' . __('Membership Details', 'wishlist-member') . '</a><br />';
						}
						if ($this->GetOption('widget_hiderss') != 1) {
							$output.='&raquo; <a href="' . get_bloginfo('rss2_url') . '">' . __('RSS Feed', 'wishlist-member') . '</a><br />';
						}
						if (function_exists('wp_logout_url')) {
							$logout = wp_logout_url(get_bloginfo('url'));
						} else {
							$logout = wp_nonce_url(site_url('wp-login.php?action=logout&redirect_to=' . urlencode(get_bloginfo('url')), 'login'), 'log-out');
						}
						$output.='&raquo; <a href="' . $logout . '">' . __('Logout', 'wishlist-member') . '</a><br />';
						if ($return)
							return $output;
						echo $output;
					}else {
						$register = $this->GetOption('non_members_error_page_internal');
						$register = $register ? get_permalink($register) : $this->GetOption('non_members_error_page');
						$widget_fieldwidth = (int) $this->GetOption('widget_fieldwidth');
						$login_url = esc_url(site_url( 'wp-login.php', 'login_post' ));
						if (!$widget_fieldwidth
						)
							$widget_fieldwidth = 15;

						echo '<form method="post" action="' . $login_url . '"><p>' . __('You are not currently logged in.', 'wishlist-member') . '</p>';
						echo '<label>' . __('Username', 'wishlist-member') . ':<br /><input class="wlmember_loginwidget_input_username"  type="text" name="log" size="' . $widget_fieldwidth . '" /></label><br />';
						echo '<label>' . __('Password', 'wishlist-member') . ':<br /><input class="wlmember_loginwidget_input_password" type="password" name="pwd" size="' . $widget_fieldwidth . '" /></label><br />';
						echo '<label><input  class="wlmember_loginwidget_input_checkrememberme" type="checkbox" name="rememberme" value="forever" /> ' . __('Remember Me', 'wishlist-member') . '</label><br />';
						echo '<input class="wlmember_loginwidget_input_submit" type="submit" name="wp-submit" value="' . __('Login', 'wishlist-member') . '" /><br /><br />';
						if ($this->GetOption('widget_hideregister') != 1) {
							echo '&raquo; <a href="' . $register . '">' . __('Register', 'wishlist-member') . '</a><br />';
						}
						echo '&raquo; <a href="' . wp_login_url() . '?action=lostpassword">' . __('Lost your Password?', 'wishlist-member') . '</a>';
						$redirect = !empty($_GET['wlfrom']) ? esc_attr(stripslashes($_GET['wlfrom'])) : 'wishlistmember';
						echo '<input type="hidden" name="wlm_redirect_to" value="' . $redirect . '" /></form>';
					}
					if (!$return) {
						echo "</div>";
						echo $after_widget;
					}
				}
			}
		}

		// -----------------------------------------
		// Widget Admin
		function WidgetAdmin() {
			$title = $this->GetOption('widget_title');
			$title2 = $this->GetOption('widget_title2');
			$rsschecked = $this->GetOption('widget_hiderss') ? ' checked="checked" ' : '';
			$registerchecked = $this->GetOption('widget_hideregister') ? ' checked="checked" ' : '';
			$nologinboxchecked = $this->GetOption('widget_nologinbox') ? ' checked="checked" ' : '';
			$hidelevelschecked = $this->GetOption('widget_hidelevels') ? ' checked="checked" ' : '';
			$widget_fieldwidth = (int) $this->GetOption('widget_fieldwidth');
			if (!$widget_fieldwidth
			)
				$widget_fieldwidth = 15;

			echo '<p><label for="wpm-widget-title">' . __('Title when logged in', 'wishlist-member') . ': <input type="text" value="' . $title . '" name="wpm_widget_title" id="wpm-widget-title" class="widefat" /></label></p>';
			echo '<p><label for="wpm-widget-title2">' . __('Title when logged out', 'wishlist-member') . ': <input type="text" value="' . $title2 . '" name="wpm_widget_title2" id="wpm-widget-title2" class="widefat" /></label></p>';
			echo '<p><b>' . __('Advanced Options', 'wishlist-member') . '</b></p>';
			echo '<p><label for="wpm-widget-hiderss"><input type="checkbox" value="1" name="wpm_widget_hiderss" id="wpm-widget-hiderss"' . $rsschecked . ' /> ' . __('Hide RSS Link', 'wishlist-member') . '</label></p>';
			echo '<p><label for="wpm-widget-hideregister"><input type="checkbox" value="1" name="wpm_widget_hideregister" id="wpm-widget-hideregister"' . $registerchecked . ' /> ' . __('Hide Register Link', 'wishlist-member') . '</label></p>';
			echo '<p><label for="wpm-widget-nologinbox"><input type="checkbox" value="1" name="wpm_widget_nologinbox" id="wpm-widget-nologinbox"' . $nologinboxchecked . ' /> ' . __('Only display if member is logged in', 'wishlist-member') . '</label></p>';
			echo '<p><label for="wpm-widget-hidelevels"><input type="checkbox" value="1" name="wpm_widget_hidelevels" id="wpm-widget-hidelevels"' . $hidelevelschecked . ' /> ' . __('Hide membership levels', 'wishlist-member') . '</label></p>';
			echo '<p><label for="wpm-widget-fieldwidth">' . __('Width of Login Fields', 'wishlist-member') . '<br /><input type="text" value="' . $widget_fieldwidth . '" name="wpm_widget_fieldwidth" id="wpm-widget-fieldwidth" size="4" /></label></p>';
			if (isset($_POST['wpm_widget_title'])) {
				if (!trim(wlm_arrval($_POST, 'wpm_widget_title'))
				)
					$_POST['wpm_widget_title'] = __('Membership Detail', 'wishlist-member');
				if (!trim(wlm_arrval($_POST, 'wpm_widget_title2'))
				)
					$_POST['wpm_widget_title2'] = __('Login Status', 'wishlist-member');
				$this->SaveOption('widget_title', $_POST['wpm_widget_title']);
				$this->SaveOption('widget_title2', $_POST['wpm_widget_title2']);
				$this->SaveOption('widget_hiderss', $_POST['wpm_widget_hiderss']);
				$this->SaveOption('widget_hideregister', $_POST['wpm_widget_hideregister']);
				$this->SaveOption('widget_nologinbox', $_POST['wpm_widget_nologinbox']);
				$this->SaveOption('widget_hidelevels', $_POST['wpm_widget_hidelevels']);
				$this->SaveOption('widget_fieldwidth', (int) $_POST['wpm_widget_fieldwidth']);
			}
		}

		// -----------------------------------------
		// Hide's Prev/Next Links as per Configuration
		function OnlyShowPrevNextLinksForLevel($where) {
			global $wpdb;
			if (is_admin()) {
				return $where;
			}
			if (!$this->GetOption('only_show_content_for_level')) {
				return $where;
			}

			$id = $GLOBALS['current_user']->ID;

			if ($id) {
				if (!$GLOBALS['current_user']->caps['administrator'] || is_feed()) {
					$wpm_levels = $this->GetOption('wpm_levels');
					$levels = $this->GetMembershipLevels($id, false, true);

					// get all protected posts
					$protected = $this->ProtectedIds();

					$enabled_types = (array) $this->GetOption('protected_custom_post_types');
					$enabled_types[] = 'post';
					$enabled_types = "'" . implode("','", $enabled_types) . "'";
					$all = $wpdb->get_col("SELECT `ID` FROM `{$wpdb->posts}` WHERE `post_type` IN ({$enabled_types})");
					$unp = array_diff($all, $protected);
					$ids = array_merge((array) $ids, (array) $unp);
					$allpages = $allposts = false;

					// retrieve post ids
					if ($allposts) {
						$ids = array_merge($ids, $wpdb->get_col("SELECT `ID` FROM `{$wpdb->posts}` WHERE `post_type`='post' AND `post_status` IN ('publish','private')"));
					} else {
						foreach ((array) $levels AS $level)
							$ids = array_merge($ids, $x = $this->GetMembershipContent('posts', $level));
					}

					//retrieve custom post types id
					foreach ((array) $this->GetOption('protected_custom_post_types') as $custom_type) {
						foreach ($levels AS $level) {
							$ids = array_merge($ids, $x = $this->GetMembershipContent($custom_type, $level));
						}
					}

					$protected = array_diff($all, $ids);
				}
			} else {
				$protected = $this->ProtectedIds();
			}

			$protected[] = 0;
			$protected = implode(',', $protected);
			$where.=" AND p.ID NOT IN ({$protected})";
			return $where;
		}

		// -----------------------------------------
		// Hide's Content as per Configuration
		function OnlyShowContentForLevel($content) {
			global $wpdb;

			// if we're trying to view post or page content then just return the content to be processed by our the_content page.  this avoids 404 pages to be displayed on hidden pages.
			if ((is_single() && ($content->query['name'] || $content->query['p'])) || (is_page() && ($content->query['pagename'] || $content->query['page_id'])))
				return;

			$is_search = is_search();
			if ($is_search && !$this->GetOption('hide_from_search'))
				return;

			if (!is_feed() && !$this->GetOption('only_show_content_for_level'))
				return;


			$exclude_ids = $is_search ? $this->ExcludePages(array()) : array();
			// $include_ids = array();

			if (!is_admin()) {
				$id = $GLOBALS['current_user']->ID;
				if (is_feed() && isset($_GET['wpmfeedkey'])) {
					$wpmfeedkey = $_GET['wpmfeedkey'];
					$id = $this->VerifyFeedKey($wpmfeedkey);
				}
				if ($id) {
					if (!$GLOBALS['current_user']->caps['administrator'] || is_feed()) {
						$wpm_levels = $this->GetOption('wpm_levels');
						$levels = $this->GetMembershipLevels($id, false, true);

						// get all protected pages
						$protected = $this->ProtectedIds();
						$enabled_types = (array) $this->GetOption('protected_custom_post_types');
						$enabled_types[] = 'post';
						$enabled_types[] = 'page';
						$enabled_types[] = 'attachment';
						$enabled_types = "'" . implode("','", $enabled_types) . "'";
						$all = $wpdb->get_col("SELECT `ID` FROM `{$wpdb->posts}` WHERE `post_type` IN ({$enabled_types})");
						$unp = array_diff($all, $protected);
						$ids = array_merge((array) $ids, (array) $unp);

						// do we have all posts/pages enabled for any of the member's levels?
						$allpages = $allposts = false;
						foreach ((array) $levels AS $level) {
							$allposts = $allposts | isset($wpm_levels[$level]['allposts']);
							$allpages = $allpages | isset($wpm_levels[$level]['allpages']);
						}

						// retrieve page ids
						if ($allpages) {
							$ids = array_merge($ids, $wpdb->get_col("SELECT `ID` FROM `{$wpdb->posts}` WHERE `post_type`='page' AND `post_status` IN ('publish','private')"));
						} else {
							foreach ((array) $levels AS $level)
								$ids = array_merge($ids, $x = $this->GetMembershipContent('pages', $level));
						}

						// retrieve post ids
						if ($allposts) {
							$ids = array_merge($ids, $wpdb->get_col("SELECT `ID` FROM `{$wpdb->posts}` WHERE `post_type`='post' AND `post_status` IN ('publish','private')"));
						} else {
							foreach ((array) $levels AS $level)
								$ids = array_merge($ids, $x = $this->GetMembershipContent('posts', $level));
						}

						//retrieve custom post types id
						foreach ((array) $this->GetOption('protected_custom_post_types') as $custom_type) {
							foreach ($levels AS $level) {
								$ids = array_merge($ids, $x = $this->GetMembershipContent($custom_type, $level));
							}
						}

						$no_access_ids = array_diff($all, $ids);
						$exclude_ids = array_merge($exclude_ids, $no_access_ids);
					}
				} else {
					// public (not logged in)
					if (!is_feed() OR (is_feed() && $this->GetOption('rss_hide_protected'))) {
						$exclude_ids = $this->ProtectedIds();
					}
				}
			}
			if (count($exclude_ids)) {
				$exclude_ids = array_unique(array_merge($exclude_ids, (array) $content->query_vars['post__not_in']));
				$content->query_vars['post__not_in'] = $exclude_ids;
			}
			/*
			 * **** this is no longer needed ****
			  if (count($include_ids)) {
			  $include_ids = array_unique(array_merge($include_ids, (array) $content->query_vars['post__in']));
			  $content->query_vars['post__in'] = $include_ids;
			  }
			 */
		}

		function OnlyListPagesForLevel($pages) {
			if ($this->GetOption('only_show_content_for_level') && !wlm_arrval($GLOBALS['current_user']->caps, 'administrator')) {
				if ($GLOBALS['current_user']->ID) {
					$wpm_levels = $this->GetOption('wpm_levels');
					$levels = $this->GetMembershipLevels($GLOBALS['current_user']->ID, false, true);
					// is the user a member of a level that can view all pages?
					$allpages = false;
					foreach ((array) $levels AS $level) {
						$allpages = $allpages | isset($wpm_levels[$level]['allpages']);
					}
					if ($allpages
					)
						return $pages;

					// retrieve pages that the user can't view
					$protect = $this->ProtectedIds();
					$xpages = $this->GetMembershipContent('pages');
					$allowed = array();
					foreach ((array) $levels AS $level) {
						$allowed = array_merge((array) $allowed, (array) $xpages[$level]);
					}
					$allowed = array_merge((array) $allowed, (array) $this->GetMembershipContent('pages', 'U-' . $GLOBALS['current_user']->ID));
					$pages = array_merge($pages, array_diff($protect, $allowed));
				} else {
					$pages = array_merge($pages, $this->ProtectedIds());
				}

				$pages = array_unique($pages);

				//filter so that we are only excluding pages.
				//adding a lot of ID's in excludes greatly affects performance
				global $wpdb;
				$real_pages = $wpdb->get_col("SELECT ID FROM $wpdb->posts WHERE `post_type`='page'");
				$pages = array_intersect($pages, $real_pages);

				$k = array_search('', $pages);
				if ($k !== false
				)
					unset($pages[$k]);
			}
			return $pages;
		}

		function OnlyListCatsForLevel($cats) {
			global $current_user;

			if ($this->GetOption('only_show_content_for_level') && !wlm_arrval($GLOBALS['current_user']->caps, 'administrator')) {
				/* if (is_category() && !defined('ONLYLISTCATS')) {
				  define('ONLYLISTCATS', 1);
				  if ($this->Permalink)
				  return $cats; // we only return full cats on first run if permalinks are set
				  } */
				//I remove this part of code to show only cats accessible by the user on the cat widget. This code allows users to see
				//cats after visiting other pages, it only prevents them from seeing it on the hompage when permalink is on.

				$wpm_levels = $this->GetOption('wpm_levels');
				$levels = $this->GetMembershipLevels($current_user->ID, false, true);

				$notallowed = $this->taxonomyIds;
				$allowed = $this->GetMembershipContent('categories', $levels);

				foreach ((array) $notallowed AS $i => $cat) {
					if (in_array($cat, $allowed) || !$this->CatProtected($cat)) {
						unset($notallowed[$i]);
					}
				}

				if (count($notallowed)) {
					$notallowed[] = 0; // wp 2.8 fix?
					$notallowed = implode(',', $notallowed);
					$cats.=" AND t.term_id NOT IN ({$notallowed}) ";
				}
			}
			return $cats;
		}

		// -----------------------------------------
		// Category Protection Form
		function CategoryForm($tag) {
			$add = empty($tag->term_id);
			$tax = get_taxonomy($add ? $tag : $tag->taxonomy);
			$tax_label = $tax->labels->singular_name;
			if (!$tax_label) {
				$tax_label = $tax->labels->name;
			}

			$checked = $tag->term_id ? (int) $this->CatProtected($tag->term_id) : (int) $this->GetOption('default_protect');

			$chkyes = $checked ? 'checked="checked"' : '';
			$chkno = $checked ? '' : 'checked="checked"';

			$lbl = sprintf(__('Protect this %s?', 'wishlist-member'), $tax_label);
			$yes = __('Yes', 'wishlist-member');
			$no = __('No', 'wishlist-member');
			if ($add) {
				echo <<<STRING
				<div class="form-field">
					<label>{$lbl}</label>
					<label style="display:inline"><input style="width:auto" type="radio" name="wlmember_protect_category" {$chkyes} value="yes" /> {$yes}</label> &nbsp; <label style="display:inline"><input style="width:auto" type="radio" name="wlmember_protect_category" {$chkno} value="no" /> {$no}</label>
				</div>
STRING;
			} else {
				echo <<<STRING
				<tr class="form-field">
					<th scope="row">{$lbl}</th>
					<td><label style="display:inline"><input style="width:auto" type="radio" name="wlmember_protect_category" {$chkyes} value="yes" /> {$yes}</label> &nbsp; <label style="display:inline"><input style="width:auto" type="radio" name="wlmember_protect_category" {$chkno} value="no" /> {$no}</label></td>
				</tr>
STRING;
			}
		}

		// -----------------------------------------
		// Save Category
		function SaveCategory($id) {
			global $wpdb;
			$id = abs($id);
			switch (wlm_arrval($_POST, 'wlmember_protect_category')) {
				case 'yes':
					$this->CatProtected($id, 'Y');
					break;
				case 'no':
					$this->CatProtected($id, 'N');
					break;
			}
		}

		// -----------------------------------------
		// Edit Profile Page
		function ProfilePage() {
			global $current_user;

			$profileuser = $this->Get_UserData($GLOBALS['profileuser']->ID);
			$mlevels = $this->GetMembershipLevels($profileuser->ID);

			if ($current_user->caps['administrator']) {
				$custom_fields_form = $this->GetCustomRegFields();
			} else {
				$custom_fields_form = $this->GetCustomRegFields($mlevels);
			}

			$custom_fields = implode('', $custom_fields_form);
			$custom_fields = str_replace(array('<td class="label">', '</td><td'), array('<th scope="row">', '</th><td'), $custom_fields);

			// if password hinting is enabled, display the password hint for the member
			if ($this->GetOption('password_hinting')) {
				$custom_fields .= '<tr class="li_fld field_text required">
										<th scope="row">Password Hint:</th>
										<td class="fld_div">
											<input class="fld" type="text" name="passwordhint" size="20" value="' . $profileuser->wlm_password_hint . '">
											<div class="desc"></div>
										</td>
									</tr>';
			}

			$postdata = $user_custom_fields = $this->GetUserCustomFields($profileuser->ID);
			$postdata = array_intersect_key($postdata, $custom_fields_form);

			$user_custom_fields = array_diff_key($user_custom_fields, $custom_fields_form);
			$hastos = isset($user_custom_fields['terms_of_service']);

			if ($current_user->caps['administrator'] && $user_custom_fields) {
				foreach ($user_custom_fields AS $custom_name => $custom_value) {
					if ($custom_name != 'terms_of_service') {
						$custom_fields.='<tr><th scope="row"><span style="color:gray">' . $custom_name . '</span></th><td>';
						$custom_fields.='<input type="text" name="' . $custom_name . '" value="' . htmlentities(stripslashes(implode(' ', (array) $custom_value)), ENT_QUOTES) . '" />';
						$custom_fields.='</td></tr>';
					}
				}
			}
			if ($hastos) {
				$custom_fields.='<tr><th scope="row">' . __('Terms of Service', 'wishlist-member') . '</th><td>';
				if ($user_custom_fields['terms_of_service']) {
					$custom_fields.='Accepted';
				} else {
					$custom_fields.='&nbsp;';
				}
				$custom_fields.='</td></tr>';
			}

			$custom_fields_heading = $custom_fields ? __('<h3>Additional Registration Information</h3>', 'wishlist-member') : '';
			$custom_fields = $custom_fields ? $custom_fields_heading . '<table class="form-table wpm_regform_table WishListMemberCustomFields">' . $custom_fields . '</table>' : '';
			if ($custom_fields) {
				$custom_fields.='<input type="hidden" name="wlm_custom_fields_profile" value="' . implode(',', array_keys($custom_fields_form)) . '" />';
				if ($current_user->caps['administrator'] && $user_custom_fields) {
					$custom_fields.='<input type="hidden" name="wlm_custom_fields_profile_hidden" value="' . implode(',', array_keys($user_custom_fields)) . '" />';
				}



				$postdata = json_encode(array_diff((array) $postdata, array('')));
				echo <<<STRING
<script type="text/javascript">
var wlm_regform_values = eval({$postdata});
</script>
<script type="text/javascript" src="{$this->pluginURL}/js/regform_prefill.js"></script>
STRING;
			}

			$mailcheck = $profileuser->wlm_unsubscribe == 1 ? '' : 'checked="true"';
			$txt01 = __('Mailing List Subscription', 'wishlist-member');
			$txt02 = __('Subscribe to Mailing List', 'wishlist-member');
			$mailinglist = <<<STRING
            <tr valign="top">
                <th scope="row">{$txt01}</th>
                <td><label><input type="checkbox" name="wlm_unsubscribe" value="1" {$mailcheck} /> {$txt02}</label></td>
            </tr>
STRING;
			$txt01 = __('WishList Member Feed URL', 'wishlist-member');
			$wlm_feed_url = <<<STRING
            <tr valign="top">
                <th scope="row">{$txt01}</th>
				<td><a href="{$profileuser->wlm_feed_url}">{$profileuser->wlm_feed_url}</a></td>
            </tr>
STRING;
			// retrieve address
			$wpm_useraddress = $profileuser->wpm_useraddress;
			$countries = '<select name="wpm_useraddress[country]">';
			foreach ((array) $this->Countries() AS $country) {
				$selected = $country == $profileuser->wpm_useraddress['country'] ? ' selected="true" ' : '';
				$countries.='<option' . $selected . '>' . $country . '</option>';
			}

			$txtaddress = __('Address', 'wishlist-member');
			$txtcompany = __('Company', 'wishlist-member');
			$txtcity = __('City', 'wishlist-member');
			$txtstate = __('State', 'wishlist-member');
			$txtzip = __('Zip', 'wishlist-member');
			$txtcountry = __('Country', 'wishlist-member');
			$addresssection = <<<STRING
                   <h3>{$txtaddress}</h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">{$txtcompany}</th>
						<td><input type="text" name="wpm_useraddress[company]" value="{$wpm_useraddress[company]}" size="30" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">{$txtaddress}</th>
						<td><input type="text" name="wpm_useraddress[address1]" value="{$wpm_useraddress[address1]}" size="40" /><br /><input type="text" name="wpm_useraddress[address2]" value="{$wpm_useraddress[address2]}" size="40" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">{$txtcity}</th>
						<td><input type="text" name="wpm_useraddress[city]" value="{$wpm_useraddress[city]}" size="30" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">{$txtstate}</th>
						<td><input type="text" name="wpm_useraddress[state]" value="{$wpm_useraddress[state]}" size="30" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">{$txtzip}</th>
						<td><input type="text" name="wpm_useraddress[zip]" value="{$wpm_useraddress[zip]}" size="10" /></td>
					</tr>
					<tr valign="top">
						<th scope="row">{$txtcountry}</th>
						<td>{$countries}</td>
					</tr>
				</table>
STRING;

			if ($current_user->caps['administrator']) {
				$wpm_levels = $this->GetOption('wpm_levels');
				$options = array();
				foreach ((array) $wpm_levels AS $id => $level) {
					$checked = in_array($id, $mlevels) ? 'checked="true"' : '';
					if ($checked) {
						$txnid = '<input type="text" name="txnid[' . $id . ']" value="' . $this->GetMembershipLevelsTxnID($profileuser->ID, $id) . '" size="20" style="text-align:center" />';
						$lvltime = '<input type="text" name="lvltime[' . $id . ']" value="' . gmdate('F d, Y h:i:sa', $this->UserLevelTimestamp($profileuser->ID, $id) + $this->GMT) . '" size="25" style="text-align:center" />';
					} else {
						$txnid = '';
						$lvltime = '';
					}
					$options[] = '<tr><td style="padding:0;margin:0"><label><input type="checkbox" name="wpm_levels[]" value="' . $id . '" ' . $checked . ' /> ' . $strike . $level['name'] . $strike2 . '</label></td><td style="padding:0 5px;margin:0">' . $txnid . '</td><td style="padding:0 5px;margin:0">' . $lvltime . '</td></tr>';
				}
				$options = '<table cellpadding="0" cellspacing="0"><tr><td style="padding:0;margin:0;font-size:1em">' . __('Level', 'wishlist-member') . '</td><td style="padding:0 5px;margin:0;font-size:1em;text-align:center">' . __('Transaction ID', 'wishlist-member') . '</td><td style="padding:0 5px;margin:0;font-size:1em;text-align:center">' . __('Date Added to Level', 'wishlist-member') . '</td></tr>' . implode('', $options) . '</table>';

				$registered = date('F d, Y h:ia', $this->UserRegistered($profileuser));
				$regip = $profileuser->wpm_registration_ip;
				$loginip = $profileuser->wpm_login_ip;

				//fix issue when no login record shows date in 1970/1969
				if (($profileuser->wpm_login_date + $this->GMT) > 0) {
					$lastlogin = gmdate("F d, Y h:ia", $profileuser->wpm_login_date + $this->GMT); //+$gmt);
				} else {
					$lastlogin = "No login record yet.";
				}

				$blacklisturl = $this->GetMenu('members');
				$blacklisturl = $blacklisturl->URL . '&mode=blacklist';
				$eblacklisturl = $blacklisturl . '&eappend=' . $profileuser->user_email;
				$blacklisturl = $blacklisturl . '&append=';

				if (!$profile_user->caps['administrator']) {
					$txt01 = __('Login Limit', 'wishlist-member');
					$txt01b = __('IPs Logged in Today', 'wishlist-member');
					$txt02 = __('Special Values:', 'wishlist-member');
					$txt03 = __('<b>0</b> or Blank: Use default settings', 'wishlist-member');
					$txt04 = __('<b>-1</b>: No limit for this user', 'wishlist-member');
					$loginlimit = <<<STRING
					<tr valign="top">
						<th scope="row">{$txt01}</th>
						<td>
							<input type="text" name="wpm_login_limit" value="{$profileuser->wpm_login_limit}" size="3" style="width:50px" /> IPs per day<br />
							{$txt02}<br />
								&raquo; {$txt03}<br />
								&raquo; {$txt04}
						</td>
					</tr>
STRING;
					$current_loggedin = (array) $profileuser->wpm_login_counter;
					$today = date('Ymd');
					foreach ((array) $current_loggedin AS $k => $v) {
						if ($v != $today
						)
							unset($current_loggedin[$k]);
					}
					if (count($current_loggedin)) {
						$reset_limit_counter = __('Reset Limit Counter', 'wishlist-member');
						$reset_limit_counter2 = '<div><label><input type="checkbox" name="wlm_reset_limit_counter" value="1" /> ' . $reset_limit_counter . '</label></div>';
						$current_loggedin = implode('<br />', array_keys((array) $current_loggedin));
					} else {
						$current_loggedin = __('This user has not yet logged in for the day', 'wishlist-member');
					}
					$current_loggedin = <<<STRING
					<tr valign="top">
						<th scope="row">{$txt01b}</th>
						<td>
							{$current_loggedin}
							{$reset_limit_counter2}
						</td>
STRING;
				}

				$delete = '';
				if ($current_user->ID != $profileuser->ID && $profileuser->ID > 1) {
					$txt01 = __('Update Member Profile', 'wishlist-member');
					$txt02 = __('Delete This Member', 'wishlist-member');
					$txt03 = __('Warning!\\n\\nAre you sure you want to delete this user?', 'wishlist-member');
					$txt04 = __('Last Warning!\\n\\nAre you really sure that you want to delete this user?\\nNote that this action cannot be undone.', 'wishlist-member');
					$txt05 = __('Send Reset Password Link to User', 'wishlist-member');
					$delete = <<<STRING
					<tr valign="top">
						<th scope="row"></th>
						<td>
							<input type="hidden" name="user_login" value="{$profileuser->user_login}">
							<input class="button-primary" type="submit" value="{$txt01}" />
							<input class="button-secondary" type="submit" name="wpm_send_reset_email" value="{$txt05}" />
							&nbsp;&nbsp;
							<input class="button-secondary" type="submit" name="wpm_delete_member" value="{$txt02}" onclick="if(confirm('{$txt03}') && confirm('{$txt04}')){this.form.pass1.value='';return true;}else{return false;}" />
						</td>
					</tr>
STRING;
				}



				$txt01 = __('Membership Level', 'wishlist-member');
				$txt02 = __('Registered', 'wishlist-member');
				$txt03 = __('Email', 'wishlist-member');
				$txt04 = __('add to blacklist', 'wishlist-member');
				$txt05 = __('Date', 'wishlist-member');
				$txt06 = __('Last Login', 'wishlist-member');

				$wpmstuff = <<<STRING
				<h3>WishList Member</h3>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">{$txt01}</th>
						<td>{$options}</td>
					</tr>
                    {$mailinglist}
                    {$wlm_feed_url}
					<tr valign="top">
						<th scope="row">{$txt02}</th>
						<td>{$txt03}: {$profileuser->user_email} &nbsp; <a href="admin.php{$eblacklisturl}">{$txt04} &raquo;</a><br />{$txt05}: {$registered}<br />IP: {$regip} &nbsp; <a href="admin.php{$blacklisturl}{$regip}">{$txt04} &raquo;</a></td>
					</tr>
					<tr valign="top">
						<th scope="row">{$txt06}</th>
						<td>{$txt05}: {$lastlogin}<br />IP: {$loginip} &nbsp; <a href="admin.php{$blacklisturl}{$loginip}">{$txt04} &raquo;</a></td>
					</tr>
					{$loginlimit}
					{$current_loggedin}
					{$delete}
				</table>

				{$addresssection}

				{$custom_fields}

STRING;
			} else {
				$wpmstuff = "<table class='form-table'>{$mailinglist}{$wlm_feed_url}</table>{$addresssection}{$custom_fields}";
			}
			echo <<<STRING
<div id="WishListMemberUserProfile">
{$wpmstuff}
	<input type="hidden" name="wlm_updating_profile" value="1" />
</div>
STRING;

			$nodeIndex = $current_user->caps['administrator'] ? 0 : 3;
			echo <<<STRING
				<script type="text/javascript">
					function MoveWLMember(){
						try{
							var x=document.getElementById('WishListMemberUserProfile');
							var p=x.parentNode;
							var s=p.getElementsByTagName('h3');
							p.insertBefore(x,s[{$nodeIndex}]);
						}catch(e){}
					}
					MoveWLMember();
				</script>
STRING;
		}

		// -----------------------------------------
		// So that we can choose to return either a 404 or a 200 when viewing registration pages...
		function RewriteRules($rules = null) {
			$rules['register/(.+?)'] = 'index.php';
			return $rules;
		}

		// -----------------------------------------
		// Don't show comments...
		function NoComments() {
			return ($this->pluginDir . '/comments.php');
		}

		// -----------------------------------------
		// WP Head Hook
		function WPHead() {
			global $post;
			echo "<!-- Running WishList Member v{$this->Version} -->\n";

			if ($post->ID == $wpmpage = $this->MagicPage(false)) {
				echo '<META NAME="ROBOTS" CONTENT="NOINDEX, NOFOLLOW" />';
				echo "\n";
				echo '<META NAME="GOOGLEBOT" CONTENT="NOARCHIVE"/ >';
				echo "\n";
				echo '<META HTTP-EQUIV="PRAGMA" CONTENT="NO-CACHE"/ >';
				echo "\n";
				echo '<META HTTP-EQUIV="CACHE-CONTROL" CONTENT="NO-CACHE"/ >';
				echo "\n";
				echo '<META HTTP-EQUIV="EXPIRES" CONTENT="Mon, 02 Aug 1999 01:02:03 GMT">';
				echo "\n";
			}

			echo "<style type='text/css'>\n" . $this->GetOption('sidebar_widget_css') . "\n\n\n" . $this->GetOption('login_mergecode_css') . "\n</style>";
		}

		function ProcessApiQueue($limit = 10,$tries=5) {
			//process mailchimp api queue
			if(class_exists("WLM_AUTORESPONDER_MAILCHIMP_INIT")){
				$WLM_AUTORESPONDER_MAILCHIMP_INIT = new WLM_AUTORESPONDER_MAILCHIMP_INIT;
				if (isset($WLM_AUTORESPONDER_MAILCHIMP_INIT) && method_exists($WLM_AUTORESPONDER_MAILCHIMP_INIT, 'mcProcessQueue')) {
					$WLM_AUTORESPONDER_MAILCHIMP_INIT->mcProcessQueue($limit,$tries);
				}
			}
			//process infusionsoft sc api queue
			if(class_exists("WLM_INTEGRATION_INFUSIONSOFT_INIT")){
				$WLM_INTEGRATION_INFUSIONSOFT_INIT = new WLM_INTEGRATION_INFUSIONSOFT_INIT;
				if (isset($WLM_INTEGRATION_INFUSIONSOFT_INIT) && method_exists($WLM_INTEGRATION_INFUSIONSOFT_INIT, 'ifscProcessQueue')) {
					$WLM_INTEGRATION_INFUSIONSOFT_INIT->ifscProcessQueue($limit,$tries);
				}
			}
			//process infusionsoft ar api queue
			if(class_exists("WLM_AUTORESPONDER_INFUSIONSOFT_INIT")){
				$WLM_AUTORESPONDER_INFUSIONSOFT_INIT = new WLM_AUTORESPONDER_INFUSIONSOFT_INIT;
				if (isset($WLM_AUTORESPONDER_INFUSIONSOFT_INIT) && method_exists($WLM_AUTORESPONDER_INFUSIONSOFT_INIT, 'ifarProcessQueue')) {
					$WLM_AUTORESPONDER_INFUSIONSOFT_INIT->ifarProcessQueue($limit,$tries);
				}
			}
		}

		/**
		 * Send Queued Mail. Called via WP Cron
		 * @global object $wpdb WordPress Database Object
		 */
		function SendQueuedMail($limit = null) {
			global $wpdb;
			$mlimit = $this->GetOption('email_memory_allocation');
			$mlimit = ($mlimit == "" ? "128M" : $mlimit);
			ini_set('memory_limit', $mlimit); // request for more memory
			set_time_limit(3600); // 1 hour max execution because this script will be called again by wp-cron in an hour
			// is $limit specified? if so, use it. if not, read from email_per_hour setting
			if (is_int($limit)) {
				$limit+=0;
			} else {
				$limit = $this->GetOption('email_per_hour') + 0;
			}
			// no limit yet? let's set it to the default setting
			if ($limit < 1)
				$limit = WLMDEFAULTEMAILPERHOUR;

			// retrieve queued mails
			$mails = $wpdb->get_results("SELECT `option_name`,`option_value` FROM {$wpdb->options} WHERE `option_name` LIKE '%wlmember\_email\_queue\_%' AND `option_name` NOT LIKE 'p%wlmember\_email\_queue\_%' ORDER BY `option_name` ASC LIMIT {$limit}");

			$totalcnt = 0;
			$failedcnt = 0;
			$mailcnt = count($mails);
			$failed_arr = array();
			$date_sent = "";
			if ($mails) {
				// go through and send the emails
				$failed = array();
				foreach ((array) $mails AS $mail) {
					$name = $mail->option_name;
					$mail = maybe_unserialize($mail->option_value);
					if (strpos($mail[3], 'text/html') !== false) {
						$result = $this->SendHTMLMail($mail[0], $mail[1], $mail[2], $mail[3]);
					} else {
						$result = $this->SendMail($mail[0], $mail[1], $mail[2], $mail[3]);
					}

					// update total count of emails processed
					if ($result) { // if sent
						$totalcnt++;
					} else { // if failed
						//get the email broadcast id
						$email_id = substr($name, 0, strpos($name, 'wlmember'));
						//save it in an array to be used after
						$failed[$email_id][] = $mail[0];
					}
					delete_option($name);
				}
				//save each email broadcast id failed emails
				foreach ((array) $failed AS $key => $email_addresses) {
					//get the email broadcast previously saved failed email address
					$broadcast = $wpdb->get_row("SELECT * FROM " . $wpdb->prefix . "wlm_emailbroadcast WHERE id =" . $key);
					if (!is_null($broadcast)) {
						if ($broadcast->failed_address == '') {
							$failed_arr = (array) $email_addresses;
						} else {
							$failed_emails = explode(',', $broadcast->failed_address);
							$failed_arr = array_merge((array) $failed_emails, (array) $email_addresses);
						}
						$failedcnt += count($failed_arr);
						$failed_address = implode(',', $failed_arr);
						$q = "UPDATE " . $wpdb->prefix . "wlm_emailbroadcast SET failed_address='" . $failed_address . "' WHERE id =" . $key;
						if (!$wpdb->query($q)) {
							echo 'Error Saving Failed Emails';
						}
					}
				}
				// save last send date
				$date_sent = date("F j, Y, h:i:s A");
				$this->SaveOption('WLM_Last_Queue_Sent', $date_sent);
			}
			$log = "#SENDING QUEUE#=> #Limit:" . $limit . " #Query Count:" . $mailcnt . " #Sent:" . $totalcnt . " #Failed:" . $failedcnt . " #Last Queue Sent:" . $date_sent;
			$ret = $this->LogEmailBroadcastActivity($log);
			return $totalcnt;
		}

		/*
		 * Disables RSS Enclosures for non-authenticated feeds
		 */

		function RSSEnclosure($data) {
			$authenticatedfeed = $this->VerifyFeedKey(wlm_arrval($_GET, 'wpmfeedkey'));
			if ($authenticatedfeed) {
				return $data;
			} else {
				return '';
			}
		}

		/**
		 * Schedule the loading of attachments
		 */
		function ScheduleReloadAttachments() {
			wp_schedule_single_event(time(), 'wishlistmember_attachments_load');
			spawn_cron(time());
		}

		/**
		 * Load the attachments
		 */
		function ReloadAttachments() {
			$this->FileProtectLoadAttachments();
		}

		/*
		 *  Importing jQuery Framework
		 */

		function admin_scripts_and_styles() {
			global $wp_version;

			$basename = basename($_SERVER['PHP_SELF']);
			
			//We load only at WishList Member admin area only.
			if (wlm_arrval($_GET, 'page') == $this->MenuID) {
				wp_enqueue_script('jquery-ui-core');
				wp_enqueue_script('jquery-ui-draggable');
				wp_enqueue_script('jquery-ui-sortable');
				wp_enqueue_script('jquery-ui-tabs');
				wp_enqueue_script('jquery-ui-resizable');
				wp_enqueue_script('jquery-ui-dialog');

				wp_enqueue_script('Zero-Clipboard', $this->pluginURL . '/js/ZeroClipboard.js', array('jquery'), $this->Version);
				wp_localize_script('Zero-Clipboard', 'wlm_zeroclip', array('path' => $this->pluginURL . '/js/ZeroClipboard.swf'));
				wp_enqueue_script('jquery-ui-tooltip-plugin', $this->pluginURL . '/js/jquery.tooltip-plugin.js', array('jquery'), $this->Version);
				wp_enqueue_script('jquery-ui-tooltip-wlm', $this->pluginURL . '/js/jquery.tooltip.wlm.js', array('jquery'), $this->Version);
				wp_enqueue_style( 'wlm-font-awesome', '//netdna.bootstrapcdn.com/font-awesome/3.2.1/css/font-awesome.css');

				if (version_compare($wp_version, '3.3', '>=')) {
					// WP already includes full jQuery UI
					wp_enqueue_script('jquery-ui-datepicker');

					// We use updated theme for new jQuery
					wp_enqueue_style('wishlist_member_redmond_css', $this->pluginURL . '/css/redmond-1.8.17/jquery-ui-1.8.17.custom.css', array(), $this->Version);
				} else {
					wp_enqueue_script('jquuery-ui-datepicker', $this->pluginURL . '/js/jquery-ui-1.7.3.datepicker.min.js', array('jquery'), $this->Version);
					wp_enqueue_style('wishlist_member_redmond_css', $this->pluginURL . '/css/redmond/jquery-ui-1.8.4.custom.css', array(), $this->Version);
				}

				wp_enqueue_script('thickbox');
				wp_enqueue_script('wishlist_member_admin_main_js', $this->pluginURL . '/js/admin_main.js', array(), $this->Version);
				//for WLM feeds
				wp_localize_script( 'wishlist_member_admin_main_js', 'admin_main_js', array( 'wlm_feed_url' => admin_url('admin-ajax.php') ));				
				wp_enqueue_script('wishlist_member_admin_more_js', $this->pluginURL . '/js/admin_more.js', array(), $this->Version);

				if (wlm_arrval($_GET, 'wl') == 'settings' && wlm_arrval($_GET, 'mode') == 'regpage' && wlm_arrval($_GET, 'mode2') == 'custom') {
					wp_enqueue_script('json2');
					wp_enqueue_script('wishlist_member_custom_reg_form_js', $this->pluginURL . '/js/registration_form_backend.js', array(), $this->Version);
					wp_enqueue_style('wishlist_member_custom_reg_form_css', $this->pluginURL . '/css/registration_form_backend.css', array(), $this->Version);
					wp_enqueue_style('wishlist_member_custom_reg_form_frontend_css', $this->pluginURL . '/css/registration_form_frontend.css', array(), $this->Version);
				}

				wp_enqueue_style('thickbox');
				wp_enqueue_style('colors');
				
				if ( $wp_version >= 3.8 ) 
					wp_enqueue_style('wishlist_member_admin_main_css', $this->pluginURL . '/css/admin_main.css', array(), $this->Version);
				else
					wp_enqueue_style('wishlist_member_admin_main_css', $this->pluginURL . '/css/admin_main_3_7_below.css', array(), $this->Version);

				wp_enqueue_style('wishlist_member_admin_more_css', $this->pluginURL . '/css/admin_more.css', array(), $this->Version);
				wp_enqueue_style('wishlist_member_tooltip_css', $this->pluginURL . '/css/jquery.tooltip.css', array(), $this->Version);

				wp_enqueue_style('wishlist_member_admin_chosen_css', $this->pluginURL . '/css/chosen.css', array(), $this->Version);
				wp_enqueue_script('wishlist_member_admin_chosen_js', $this->pluginURL . '/js/chosen.jquery.js', array(), $this->Version);

				wp_enqueue_style('select2', $this->pluginURL . '/css/select2.css', array(), $this->Version);
				wp_enqueue_script('select2', $this->pluginURL . '/js/select2.min.js', array(), $this->Version);

			} elseif ($basename == 'categories.php' || $basename == 'post-new.php' || $basename == 'post.php') {
				wp_localize_script('Zero-Clipboard', 'wlm_zeroclip', array('path' => $this->pluginURL . '/js/ZeroClipboard.swf'));
				wp_enqueue_script('wishlist_member_admin_main_js', $this->pluginURL . '/js/admin_main.js', array(), $this->Version);
				wp_localize_script( 'wishlist_member_admin_main_js', 'admin_main_js', array( 'wlm_feed_url' => admin_url('admin-ajax.php') ));
				wp_enqueue_script('wishlist_member_admin_more_js', $this->pluginURL . '/js/admin_more.js', array(), $this->Version);

				wp_enqueue_style('select2', $this->pluginURL . '/css/select2.css', array(), $this->Version);
				wp_enqueue_script('select2', $this->pluginURL . '/js/select2.min.js', array(), $this->Version);
			}
		}

		function AnotherWidget_register_widgets() {
			// curl need to be installed
			register_widget('AnotherWidget');
		}

		/**
		 * Auto Remove From Feature hook
		 * @param integer $uid User ID
		 * @param array $newlevels New Membership Levels
		 */
		function DoAutoRemoveFrom($uid, $newlevels = '') {
			$newlevels = (array) $newlevels;
			$wlmuser = new WishListMemberUser($uid);
			$wlmuser->DoRemoveFrom($newlevels);
		}

		function DoSequentialForUser($id, $sync_membership = false) {
			static $wpm_levels = null;

			ignore_user_abort(true);



			$id = (int) $id;
			if(empty($id)) return;


			if(get_transient('wlm_is_doing_sequential_for_'.$id)) {
				return;

			}


			set_transient('wlm_is_doing_sequential_for_'.$id, 1, 60 * 60);

			if(empty($wpm_levels)) {
				$wpm_levels = $this->GetOption('wpm_levels');

			}

			$user_levels = new WishListMemberUser($id);
			if(!$user_levels->Sequential){
				return;

			}
			$user_levels = $user_levels->Levels;
			$original_levels = array_keys($user_levels);
			$processed = array();
			$time = time();

			do{
				$keep_going = false;
				foreach($user_levels AS $level_id => $user_level) {
					if($user_level->Active){
						if(!in_array($level_id, $processed)) {
							$processed[$level_id] = $level_id;
							$level_info = &$wpm_levels[$level_id];
							if(isset($wpm_levels[$level_info['upgradeTo']])) {
								if($level_info['upgradeSchedule'] == 'ondate') {
									$upgrade_date = $level_info['upgradeOnDate'];

								}else{
									$period = $level_info['upgradeAfterPeriod'] ? $level_info['upgradeAfterPeriod'] : 'days';
									$upgrade_date = strtotime($x='+'.$level_info['upgradeAfter'].' '.$period, $user_level->Timestamp);

								}
								if($upgrade_date && $time > $upgrade_date) {
									$keep_going = true;
									if($level_info['upgradeMethod'] == 'MOVE') {
										unset($processed[$level_id]);
										unset($user_levels[$level_id]);

									}
									if(!isset($user_levels[$level_info['upgradeTo']])) {
										$user_levels[$level_info['upgradeTo']] = (object) array(
											'Timestamp' => $upgrade_date,
											'TxnID' => $user_level->TxnID,
											'Active' => true
										);

									}

								}

							}

						}

					}

				}
			} while($keep_going);

			$this->SetMembershipLevels($id, array_keys($user_levels), null, true, true, true);

			$ts = array();
			$tx = array();
			foreach ($user_levels AS $level_id => $user_level) {
				$ts[$level_id] = $user_level->Timestamp;
				$tx[$level_id] = $user_level->TxnID;

			}

			$this->UserLevelTimestamps($id, $ts);
			$this->SetMembershipLevelTxnIDs($id, $tx);

			$seqlevels = array_keys($user_levels);
			$seqlevels_diff = array_diff($seqlevels, $original_levels);

			do_action('wlm_do_sequential_upgrade', $id, $seqlevels_diff, $seqlevels);


			if($sync_membership) {
				$this->SyncMembership();
			}


		}

		/**
		 * New and optimized Sequential upgrade
		 * - executes sequential upgrade for all users if called by cron
		 * - executes sequential upgrade for currently logged in user only if not called by cron
		 * @global object $wpdb
		 * @param int|array $user_ids
		 */
		function DoSequential($user_ids = '') {

			global $wpdb;
			ignore_user_abort(true);


			$wlm_is_doing_sequential_name = 'wlm_is_doing_sequential_' . $_SERVER['REMOTE_ADDR'];

			if (get_transient($wlm_is_doing_sequential_name) == 'yes')
				return;


			set_transient($wlm_is_doing_sequential_name, 'yes', 60 * 60 * 24);
			set_time_limit(60 * 60 * 12);

			if(is_int($user_ids) AND !empty($user_ids)){
				$user_ids = array($user_ids);
			}elseif(!is_array($user_ids)){
				$user_ids = array();
				$wpm_levels = (array) $this->GetOption('wpm_levels');

				$levels_for_sequential = array();
				foreach ($wpm_levels AS $level_id => $level) {
					if (is_int($level_id) && !empty($level['upgradeTo']) && !empty($wpm_levels[$level['upgradeTo']])) {
						if (!(!$level['upgradeTo'] || !$level['upgradeMethod'] || ($level['upgradeSchedule'] == 'ondate' && $level['upgradeOnDate'] < 1) || ($level['upgradeMethod'] == 'MOVE' && !((int) $level['upgradeAfter']) && empty($level['upgradeSchedule'])))) {

							$levels_for_sequential[] = $level_id;
						}
					}
				}
				if($levels_for_sequential) {
					$levels_for_sequential = "'".implode("','",$levels_for_sequential)."'";
					$user_ids = $wpdb->get_col("SELECT DISTINCT `user_id` FROM `{$this->Tables->user_options}` WHERE `option_name`='sequential' AND `option_value`='1' AND `user_id` IN (SELECT DISTINCT `user_id` FROM `{$this->Tables->userlevels}` WHERE `level_id` IN ({$levels_for_sequential}))");
				}

			}

			if(!empty($user_ids)) {
				$user_ids = array_chunk($user_ids, 5000);
				while($chunk = array_shift($user_ids)){
					wp_cache_flush();
					$this->PreLoad_UserLevelsMeta($chunk);
					while($user_id = array_shift($chunk)) {
						$this->DoSequentialForUser($user_id);
					}
				}

				$this->SyncMembership();
			}


			set_time_limit(ini_get('max_execution_time'));

			delete_transient($wlm_is_doing_sequential_name);


		}

		/**
		 * Notify Users with Incomplete Registration
		 * Called by WP-Cron
		 */
		function NotifyRegistration() {
			if ($this->GetOption('incomplete_notification') == 1) { //check if notification is enabled
				$incomplete_users = $this->GetIncompleteRegistrations(); //get users with incomplete registration

				foreach ($incomplete_users as $id => $user) {

					$first_notification = (int)$this->GetOption('incomplete_notification_first');
					$add_notification_count = (int)$this->GetOption('incomplete_notification_add') + 1;
					$add_notification_freq = (int)$this->GetOption('incomplete_notification_add_every');
					$incregnotification = (array)$user['wlm_incregnotification'];
					$send = false;
					$count = isset($incregnotification['count']) ? $incregnotification['count'] : 0;
					$lastsend = isset($incregnotification['lastsend']) ? $incregnotification['lastsend'] : time();

					$t_diff = (time() - $lastsend)/3600;
					$t_diff = floor($t_diff);

					if($count <= 0 && $t_diff >= $first_notification){
						$send = true;
					}elseif($count < $add_notification_count && $t_diff >= $add_notification_freq){
						$send = true;
					}

					if ($send) {
						$incregurl = $this->GetContinueRegistrationURL($user['email']); //get user's registration url
						$mlevel = $this->GetMembershipLevels($id, TRUE);

						$macros = array(
							'incregurl' => $incregurl,
							'memberlevel' => $mlevel,
						);
						$this->SendMail(trim($user['email']), $this->GetOption('incnotification_email_subject'), $this->GetOption('incnotification_email_message'), $macros);
						$incregnotification["count"] = $count + 1;
						$incregnotification["lastsend"] = time();
						update_user_meta($id, 'wlm_incregnotification',$incregnotification);
					}

				}
			}
		}

		/**
		 * Called by WP-Cron
		 */
		function ExpiringMembersNotification() {
			if ($this->GetOption('expiring_notification') == 1) {

				$expiring_users = $this->GetExpiringMembers();
				$wpm_levels = $this->GetOption('wpm_levels');
				foreach ($expiring_users as $u) {
					$user = new WishListMemberUser($u['user_id'], true);
					$usermeta = get_user_meta($user->ID);
					$userinfo = $user->UserInfo->data;
					$mlevel = $wpm_levels[$u['level_id']]['name'];


					$macros = array(
						'firstname' => $usermeta['first_name'][0],
						'lastname' => $usermeta['last_name'][0],
						'email' => $userinfo->user_email,
						'username' => $userinfo->user_login,
						'memberlevel' => $mlevel,
					);

					//find the correct expiration date
					foreach ($user->Levels as $l) {
						if ($l->Level_ID == $u['level_id']) {
							$macros['expirydate'] = date('M d, Y', $l->ExpiryDate);
						}
					}
					$this->SendMail($userinfo->user_email, $this->GetOption('expiringnotification_email_subject'), $this->GetOption('expiringnotification_email_message'), $macros);
				}
			}
		}

		/**
		 * Filter for wp_get_nav_menu_items
		 * Handles the hiding/showing of Menu items
		 *
		 * @global object $current_user
		 * @param array $items Array of menu items
		 * @return array Filtered array of menu items
		 */
		function OnlyListNavMenuItemsForLevel($items) {
			global $current_user;
			/*
			 * we only filter when only_show_content_for_level is enabled
			 * or if the current user is an administrator
			 */
			if ($this->GetOption('only_show_content_for_level') && !$GLOBALS['current_user']->caps['administrator']) {

				/* get all levels */
				$wpm_levels = $this->GetOption('wpm_levels');

				/*
				 * save $items to $orig
				 * and set $items to empty array
				 */
				$orig = $items;
				$items = array();

				/* if a user is logged in */
				if ($current_user->ID) {
					/* get all levels for this user */
					$levels = $this->GetMembershipLevels($current_user->ID, false, true);

					/* process content */
					$allcategories = $allpages = $allposts = false;
					foreach ($levels AS $level) {
						if (!$allcategories) {
							if (isset($wpm_levels[$level]['allcategories']))
								$allcategories = true;
						}
						if (!$allpages) {
							if (isset($wpm_levels[$level]['allpages']))
								$allpages = true;
						}
						if (!$allposts) {
							if (isset($wpm_levels[$level]['allposts']))
								$allposts = true;
						}
					}
					$categories = $pages = $posts = array();

					/* categories */
					if (!$allcategories)
						$categories = $this->GetMembershipContent('categories', $levels);

					/* pages */
					if (!$allpages)
						$pages = $this->GetMembershipContent('pages', $levels);

					/* posts */
					if (!$allposts)
						$posts = $this->GetMembershipContent('posts', $levels);

					/*
					 * go through each menu item and remove anything
					 * that the user does not have access to
					 */
					foreach ($orig AS $item) {
						if (in_array($item->object, $this->taxonomies)) {
							if ($allcategories OR !$this->CatProtected($item->object_id) OR in_array($item->object_id, $categories))
								$items[] = $item;
						}
						elseif ($item->object == 'page') {
							if ($allpages OR !$this->Protect($item->object_id) OR in_array($item->object_id, $pages))
								$items[] = $item;
						}
						elseif ($item->object == 'post') {
							if ($allposts OR !$this->Protect($item->object_id) OR in_array($item->object_id, $posts))
								$items[] = $item;
						}
						else {
							$items[] = $item;
						}
					}
					/* if a user is not logged in */
				} else {
					/*
					 * go through each menu item and
					 * remove all protected content
					 */
					foreach ($orig AS $item) {
						if (in_array($item->object, $this->taxonomies)) {
							if (!$this->CatProtected($item->object_id))
								$items[] = $item;
						}
						elseif ($item->object == 'page' || $item->object == 'post') {
							if (!$this->Protect($item->object_id))
								$items[] = $item;
						}
						else {
							$items[] = $item;
						}
					}
				}
				/*
				 * re-organize menus, make sure that
				 * hierarchy remains meaningful
				 */

				/* first we collect all IDs from $items to make it easier to search */
				$item_ids = array();
				foreach ($items AS $key => $item) {
					$item_ids[$item->ID] = $key;
				}

				/* next, we collect all parent IDs from $orig */
				$parent_ids = array();
				foreach ($orig AS $item) {
					$parent_ids[$item->ID] = $item->menu_item_parent;
				}

				/* then we walk through and fix the parent IDs if needed */
				for ($i = 0; $i < count($items); $i++) {
					$item = &$items[$i];
					$parent = $item->menu_item_parent;
					while (!isset($item_ids[$parent])) {
						$parent = $parent_ids[$parent];
						if ($parent == 0
						)
							break;
					}
					$item->menu_item_parent = $parent;
				}
			}
			/* return the filtered menu item */
			return $items;
		}

		/**
		 * TempEmailSanitize
		 * is a filter that hooks to sanitize_email
		 * and makes sure that our temp email address
		 * which we use for shopping cart integrations
		 * go through.
		 *
		 * @param string $email
		 * @return string
		 */
		function TempEmailSanitize($email) {
			if (
					wlm_arrval($_POST, 'orig_email') && wlm_arrval($_POST, 'email') == wlm_arrval($_POST, 'username') && wlm_arrval($_POST, 'email') == 'temp_' . md5(wlm_arrval($_POST, 'orig_email'))
			) {
				return $_POST['email'];
			}
			return $email;
		}

		function Plugin_Update_Notice($transient) {
			static $our_transient_response;

			if ($this->Plugin_Is_Latest()) {
				return $transient;
			}

			if (!$our_transient_response) {
				$package = $this->Plugin_Download_Url();
				if ($package === false)
					return $transient;

				$file = $this->PluginFile;

				$our_transient_response = array(
					$file => (object) array(
						'id' => 'wishlist-member-' . time(),
						'slug' => $this->PluginSlug,
						'new_version' => $this->Plugin_Latest_Version(),
						'url' => 'http://wordpress.org/extend/plugins/akismet/',
						'package' => $package
					)
				);
			}

			$transient->response = array_merge((array) $transient->response, (array) $our_transient_response);
			return $transient;
		}

		function Plugin_Info_Hook($res, $action, $args) {
			if ($res === false && $action == 'plugin_information' && $args->slug == 'wishlist-member') {
				$res = new stdClass();
				$res->name = $this->PluginInfo->Name;
				$res->slug = $this->PluginSlug;
				$res->version = $this->Plugin_Latest_Version();
				$res->author = $this->PluginInfo->Author;
				$res->author_profile = $this->PluginInfo->AuthorURI;
				$res->homepage = $this->PluginInfo->PluginURI;
				$res->requires = "3.0";
				$res->sections = array(
					'description' => '<p>WishList Member is a powerful solution for creating an online membership site â€“ all built using WordPress as the core content management system.</p>
										<p>Now itâ€™s easy to control access to your content, accept payments, manage your members and so much more! Read below for full feature descriptions, tutorial videos and examples of sites using WishList Member.</p>',
					'support' => '<p>Need help?  Click one of the links below.</p>
									<ul>
									<li><a href="http://wishlistproducts.com/support-options" target="_blank">Customer Support</a></li>
									<li><a href="http://wishlistproducts.com/videos" target="_blank">Video Tutorials</a></li>
									<li><a href="http://wishlistproducts.com/guides" target="_blank">Help Guide</a></li>
									<li><a href="http://wishlistproducts.com/faq" target="_blank">FAQ\'s</a></li>
									<li><a href="http://wishlistproducts.com/api" target="_blank">API Documents</a></li>
									<li><a href="http://wishlistproducts.com/release-notes" target="_blank">Release Notes</a></li>
									</ul>'
				);
				$res->download_link = 'http://google.com/download';
			}
			return $res;
		}

		function Pre_Upgrade($return, $plugin) {
			$plugin = (isset($plugin['plugin'])) ? $plugin['plugin'] : '';
			if ($plugin == $this->PluginFile) {
				$dir = sys_get_temp_dir() . '/' . 'WishListMember-Upgrade';

				$this->Recursive_Delete($dir);

				$this->Recursive_Copy($this->pluginDir . '/extensions', $dir . '/extensions');
				$this->Recursive_Copy($this->pluginDir . '/lang', $dir . '/lang');
			}
			return $return;
		}

		function Post_Upgrade($return, $plugin) {
			$plugin = (isset($plugin['plugin'])) ? $plugin['plugin'] : '';
			if ($plugin == $this->PluginFile) {
				$dir = sys_get_temp_dir() . '/' . 'WishListMember-Upgrade';

				$this->Recursive_Copy($this->pluginDir . '/extensions', $dir . '/extensions');
				$this->Recursive_Copy($this->pluginDir . '/lang', $dir . '/lang');

				$this->Recursive_Copy($dir . '/extensions', $this->pluginDir . '/extensions');
				$this->Recursive_Copy($dir . '/lang', $this->pluginDir . '/lang');

				$this->Recursive_Delete($dir);
			}
			return $return;
		}

		function OnlyShowCommentsForLevel($where) {
			$wpm_levels = $this->GetOption('wpm_levels');
			$id = 0;
			if (is_user_logged_in()) {
				$id = $GLOBALS['current_user']->ID;
			}
			if (isset($_GET['wpmfeedkey'])) {
				$wpmfeedkey = $_GET['wpmfeedkey'];
				$id = $this->VerifyFeedKey($wpmfeedkey);
			}
			if ($id) {
				if (current_user_can('activate_plugins')) {
					return $where;
				}
				$levels = $this->GetMembershipLevels($id, $names, true);
				foreach ($levels AS $level) {
					if ($wpm_levels[$level]['comments']) {
						return $where;
					}
				}
				$protected_comments = $this->GetMembershipContent('comments', $levels);
				$comments = array(0);
				foreach ($protected_comments AS $comment) {
					$comments = array_merge($comments, (array) $comment);
				}
				$comments = implode(',', array_map('wlm_abs_int', array_unique($comments)));
				$where .= ' AND comment_post_ID NOT IN (' . $comment . ') ';
			} else {
				$protected_comments = $this->GetMembershipContent('comments');
				$protect = $this->ProtectedIds();
				$protect[] = 0;
				foreach ($protected_comments AS $pc) {
					$protect = array_merge($protect, (array) $pc);
				}
				$protect = implode(',', array_map('wlm_abs_int', array_unique($protect)));
				$where .= ' AND comment_post_ID NOT IN (' . $protect . ') ';
			}
			return $where;
		}

		function frontend_scripts_and_styles() {
			$magicpage = is_page($this->MagicPage(false));
			$fallback = $magicpage | $this->IsFallBackURL(wlm_arrval($_GET, 'reg'));

			if (wlm_arrval($this, 'force_registrationform_scripts_and_styles') === true || $magicpage || $fallback) {
				wp_enqueue_script('jquery-ui-core');
				wp_enqueue_script('wishlist_member_regform_prefill', $this->pluginURL . '/js/regform_prefill.js', array(), $this->Version);
				wp_enqueue_script('thickbox');
				wp_enqueue_style('thickbox');
				wp_enqueue_script('tb_images', $this->pluginURL . '/js/thickbox_images.js', array(), $this->Version);

				if ($this->GetOption('FormVersion') == 'improved') {
					wp_enqueue_script('wishlist_member_improved_registration_js', $this->pluginURL . '/js/improved_registration_form_frontend.js', 'jquery-ui', $this->Version);
					wp_enqueue_style('wishlist_member_improved_registration_css', $this->pluginURL . '/css/improved_registration_form_frontend.css', 'jquery-ui', $this->Version);
				} else {
					wp_enqueue_style('wishlist_member_custom_reg_form_css', $this->pluginURL . '/css/registration_form_frontend.css', array(), $this->Version);
				}

				add_action('wp_print_scripts', array($this, 'regpage_form_data'));
			}
		}

		function regpage_form_data() {

			foreach ((array) $this->RegPageFormData AS $k => $v) {
				$this->RegPageFormData[$k] = @stripslashes((string) $v);
			}
			$data = array_diff((array) $this->RegPageFormData, array(''));

			//do not prefill temporary email
			foreach ($data as $k => $v) {
				if (stripos($v, '@temp.mail') !== false) {
					unset($data[$k]);
				}
			}
			$postdata = json_encode($data);
			if (!empty($data)) {
				echo <<<STRING
				<script type="text/javascript">
					var wlm_regform_values = eval({$postdata});
				</script>
STRING;
			}
		}

		function UpdateNag() {
			if (!$this->Plugin_Is_Latest()) {
				$latest_wpm_ver = $this->Plugin_Latest_Version();
				if (!$latest_wpm_ver) {
					$latest_wpm_ver = $this->Version;                                         
				}
                                
				global $current_user ;
				$user_id = $current_user->ID;
                                $dismiss_meta = 'dismiss_wlm_update_notice_' . $latest_wpm_ver;
				if ( !get_user_meta($user_id, $dismiss_meta ) && current_user_can( 'update_plugins' )) {				    				
				    echo "<div class='update-nag'>";
				    printf(__("The most current version of WishList Member is v%s.", 'wishlist-member'), $latest_wpm_ver);
				    echo " ";
				    echo "<a href='" . $this->Plugin_Update_Url() . "'>";
				    _e("Please update now. ", 'wishlist-member');
				    echo "</a> | ";
				    echo '<a href="' . add_query_arg( 'dismiss_notice', '0' ) . '"> Dismiss </a>';
				    echo "</div>";
				}
			}
		}
		
		function dismiss_wlm_update_notice() {
		
		    global $current_user ;
		    $user_id = $current_user->ID;

		    /* If user clicks to ignore the notice, add that to their user meta */
                    if (!$this->Plugin_Is_Latest()) {
			$latest_wpm_ver = $this->Plugin_Latest_Version();
			if (!$latest_wpm_ver) {
                            $latest_wpm_ver = $this->Version;                                         
			}
                   
                        $dismiss_meta = 'dismiss_wlm_update_notice_'. $latest_wpm_ver;
                        if ( isset($_GET['dismiss_notice']) && '0' == $_GET['dismiss_notice'] ) {
                            add_user_meta($user_id, $dismiss_meta, 'true', true);
                        }
                    }
		}

		function WLMUserSearch_Ajax() {
			require_once($this->pluginDir . '/core/UserSearch.php');
			$search = wlm_arrval($_POST, 'search');
			$search_by = trim(wlm_arrval($_POST, 'search_by'));
			$url = trim(wlm_arrval($_POST, 'url'));

			$search_results = array();
			switch($search_by){
				case 'by_level':
					if (empty($search)) {
						die();
					}
					$search_results = $this->MemberIDs($search);
					break;
				default:
					$search = trim($search);
					if (empty($search)) {
						die();
					}
					$search_results = new WishListMemberUserSearch($search);
					$search_results = $search_results->results;
			}
			if (count($search_results)) {
				$output = '';
				$alternate = '.';
				foreach ($search_results AS $uid) {
					$user = get_userdata($uid);
					$name = trim($user->user_firstname . ' ' . $user->user_lastname);
					if ($name == '')
						$name = $user->user_login;
					$alternate = $alternate ? '' : ' alternate';
					$output .= sprintf('<tr class="user_%2$d' . $alternate . '">
						<td class="num">%2$d</td><td>%3$s</td><td>%4$s</td><td>%5$s</td><td class="select_link"><a href="%1$s">[select]</a></tr>', $url . $uid, $uid, $name, $user->user_login, $user->user_email);
				}
				$output = '<table class="widefat"><thead><tr>
					<th class="num">ID</th>
					<th>Name</th>
					<th>Username</th>
					<th>Email</th>
					<th>&nbsp;</th>
					</tr></thead><tbody>' . $output . '</tbody></table>';
				echo $output;
			}
			die();
		}

		function WLM_PayPerPost_Search() {
			$func = wlm_arrval($_GET, 'callback');
			if($func) {
				$data = array();
				$limit = sprintf('%d,%d', $_POST['page'] - 1, $_POST['page_limit']);
				$data['posts'] = $this->GetPayPerPosts(array('ID','post_title','post_type'), false, $_POST['search'], $limit, $total, $query);
				$data['total'] = $total;
				$data['query'] = $query;
				printf('%s(%s)', $func, json_encode($data));
			}
			die();
		}

		function DashboardFeeds() {
			$maxitems = 2;
			$rss = fetch_feed('http://feeds.feedburner.com/wishlistmembernews');
			if (!is_wp_error($rss)) {
				$maxitems = $rss->get_item_quantity(2);
				$rss_items = $rss->get_items(0, $maxitems);
			}

			$date_now = strtotime("now");
			$rss_content = "";
			if ($maxitems > 0):
				// Loop through each feed item and display each item as a hyperlink.
				foreach ($rss_items as $item) :
					$item_date = $item->get_date('F d, Y');
					$date_diff = $date_now - strtotime($item_date);
					$date_diff = $date_diff/86400;
					//only show feeds less than 7 days old
					if($date_diff < 7){
						$item_title = $item->get_title();
						$item_content = $item->get_description();
						$item_permalink = $item->get_permalink();
						$rss_content .= <<<STR
						<li>
							<a href="{$item_permalink}" class="rsswidget">{$item_title}
							<span class="rss-date">{$item_date}</span></a>
							<div class="rssSummary">{$item_content}</div>
						</li>
STR;
					}
				endforeach;
		?>
		<?php
			endif;
			$rss_content = $rss_content != "" ? "<div class='rss-widget'><ul>{$rss_content}</ul></div>" : "";
			
			ob_start();
			echo $rss_content;
			echo ob_get_clean();
			die();
		}

		/**
		 * hook that adds additional levels
		 * if specified during integration
		 *
		 * used for upsells
		 */
		function Add_Additional_Levels() {
			$user = get_user_by('login', $_POST['username']);

			$additional_levels = $this->Get_UserMeta($user->ID, 'additional_levels');

			if (!is_array($additional_levels)) { // we assume $additional_levels is in simple CSV format if it's not an array
				$additional_levels = explode(',', $additional_levels);
				array_walk($additional_levels, create_function('&$var', '$var=trim($var);'));
			}

			/*
			 * each additional level can be passed as a tab-delimited string
			 * containing level, transaction id and timestamp so we go through
			 * each additional level and check for those
			 */
			$transaction_ids = array();
			$timestamps = array();

			foreach ($additional_levels AS &$additional_level) {
				list($additional_level, $transaction_id, $timestamp) = explode("\t", $additional_level);
				if (trim($transaction_id))
					$transaction_ids[$additional_level] = trim($transaction_id);
				if (trim($timestamp))
					$timestamps[$additional_level] = trim($timestamp);
			}
			unset($additional_level);

			if (!empty($additional_levels)) {
				$this->ValidateLevels($additional_levels, null, null, null, true);
				if (!empty($additional_levels)) {
					$levels = array_merge($additional_levels, $this->GetMembershipLevels($user->ID));

					$this->SetMembershipLevels($user->ID, $levels, true, null, true, true, true);

					$default_txn = $this->GetMembershipLevelsTxnID($user->ID, $_POST['wpm_id']);
					$default_ts = $this->Get_UserLevelMeta($user->ID, $_POST['wpm_id'], 'timestamp');

					$txn = array();
					$ts = array();

					foreach ($additional_levels AS $level) {
						$txn[$level] = empty($transaction_ids[$level]) ? $default_txn : $transaction_ids[$level];
						$ts[$level] = empty($timestamps[$level]) ? $default_ts : $timestamps[$level];
					}

					$this->SetMembershipLevelTxnIDs($user->ID, $txn);
					$this->UserLevelTimestamps($user->ID, $ts);
				}
				$this->Delete_UserMeta($user->ID, 'additional_levels');
			}
		}

		function Remove_Pending_To_Add_Autoresponder($id, $level, $type) {
			foreach ($level as $levels) {
				//checks if there's a flag for pending autoresponders
				if ($this->Get_UserLevelMeta($id, $levels, 'autoresponder_add_pending_admin_approval') || $this->Get_UserLevelMeta($id, $levels, 'autoresponder_add_pending_email_confirmation')) {
					$this->Delete_UserLevelMeta($id, $levels, $type);

					//if all flags are clear, add the member to the autoresponder list...
					if (!$this->Get_UserLevelMeta($id, $levels, 'autoresponder_add_pending_admin_approval') && !$this->Get_UserLevelMeta($id, $levels, 'autoresponder_add_pending_email_confirmation')) {
						$usr = $this->Get_UserData($id);
						if ($usr->ID) {
							$this->ARSubscribe($usr->first_name, $usr->last_name, $usr->user_email, $levels);
						}
					}
				}
			}
		}

		function UnsubscribeExpired() {

			require_once($this->pluginDir . '/core/UserSearch.php');

			$wp_user_search = new WishListMemberUserSearch($usersearch, $_GET['offset'], '', $ids, $sortby, $sortord, $howmany);

			foreach ((array) $wp_user_search->results AS $uid): $user = $this->Get_UserData($uid);
				$wlUser = new WishListMemberUser($user->ID);
				wlm_add_metadata($wlUser->Levels);

				foreach ($wlUser->Levels AS $level):
					if ($level->Expired) {
						echo $level->Level_ID;
						$this->ARUnsubscribe($user->first_name, $user->last_name, $user->user_email, $level->Level_ID);
					}
				endforeach;

			endforeach;
		}

		function PasswordHinting($error) {
				$user = get_user_by('login', $_POST['log']);
				$passwordhint = $this->Get_UserMeta($user->ID, 'wlm_password_hint');

				if ((trim($passwordhint) != "")) {
					if (preg_match("/The password you entered for the username/i", $error)) {
						$error .= "<br/ > <strong> Password Hint: </strong>" . $passwordhint;
					}
				}
			return $error;
		}
		function PasswordHintingEmail() {
			echo '<script>
				jQuery(document).ready(function() {

				   //resize the login form
				   jQuery("#login").css("width", "340px");
				   //remove p tag wrap on the get new password button
				   jQuery("#wp-submit").unwrap();

					jQuery("#wlpasswordhintsubmit").click(function() {
						jQuery("#wlpasswordhintsubmit").attr("disabled", true).val("Sending Pass Hint....");

						ajaxurl = "'.admin_url("admin-ajax.php").'";

						jQuery.post(
							ajaxurl,
							{
								action: "PasswordHintSubmit",
								user_login: jQuery("#user_login").val()
							},
							function(data,status){
								if(status!="success"){
									message = "Connection problem. Please check that you are connected to the internet.";
								} else if(data.error!="ok") {
									alert(data.error);
									jQuery("#wlpasswordhintsubmit").attr("disabled", false).val("Send Password Hint");
								} else {
									alert(data.message);
									jQuery("#wlpasswordhintsubmit").fadeOut();
								}
							},
							"json"
						);
						return false;
					});
				});

			</script>';
			echo '<input type="submit"  name="wlpasswordhintsubmit" id="wlpasswordhintsubmit" class="button button-large" value="Send Password Hint" />';

		}

		function PasswordHintSubmit() {

			header( "Content-Type: application/json" );
			if ( strpos( $_POST['user_login'], '@' ) ) {
					$user_data = get_user_by( 'email', trim( $_POST['user_login'] ) );
					if ( empty( $user_data ) ) {
						$error = 'There is no user registered with that email address.';
					}
			} else {
					$login = trim($_POST['user_login']);
				$user_data = get_user_by('login', $login);
			}

			if ( !$user_data ) {
				$error = 'Invalid username or e-mail.';
			} else {

					$user = new WishListMemberUser($user_data->data->ID, true);
					$userinfo = $user->UserInfo->data;
					$usermeta = get_user_meta($user_data->data->ID);

					$macros = array(
						'firstname' => $usermeta['first_name'][0],
						'lastname' => $usermeta['last_name'][0],
						'email' => $userinfo->user_email,
						'username' => $userinfo->user_login,
						'loginurl' => wp_login_url(),
						'passwordhint' => $this->Get_UserMeta($user_data->data->ID, 'wlm_password_hint')
						);

					if(!trim($macros['passwordhint'])) {
						$error = 'The Username/Email you entered does not have a Password Hint.';
					} else {
						$this->SendMail($user->UserInfo->user_email, $this->GetOption('password_hint_email_subject'), $this->GetOption('password_hint_email_message'), $macros);
						$message = 'Successfully submitted password hint, please check your email.';
						$error = 'ok';
					}
			}

			$response = json_encode( array( 'error' => $error, 'message' => $message ) );
			echo $response;
			exit;
		}

		public function UserRegisteredCleanup($uid, $data) {
			global $wpdb;
			if ($this->GetOption('enable_short_registration_links') == 1) {
				$results = $wpdb->get_results("SELECT ID, `option_name`,`option_value` FROM `{$this->Tables->options}` WHERE `option_value` like '%||{$data['email']}'");
				foreach ($results as $r) {
					$wpdb->delete($this->Tables->options, array('ID' => $r->ID));
				}
			}
			$this->SyncMembership();
		}

		//Deletes user's saved search in the options table
		function WLMDeleteSavedSearch_Ajax(){
			if(isset($_POST['option_name']) && !empty($_POST['option_name'])) {
				$this->DeleteOption($_POST['option_name']);
			}
			exit;
		}

		/**
		 * Pre-upgrade checking
		 */
		function Upgrade_Check() {
			if (basename($_SERVER['SCRIPT_NAME']) == 'update.php' && $_GET['action'] == 'upgrade-plugin' && $_GET['plugin'] == $this->PluginFile) {
				$check_result = trim($this->ReadURL(add_query_arg('check', '1', $this->Plugin_Download_Url()), 10, true, true));
				if ($check_result != 'allowed') {
					header('Location: ' . $check_result);
					exit;
				}
			}
		}

	}

}

// -----------------------------------------
// initiate our plugin class
if (class_exists('WishListMember')) {
	$WishListMemberInstance = new WishListMember(8901, 'WishListMember', 'WishList Member', 'WishList Member');
	$WishListMemberInstance->access_control = new WishListAcl();
	// add menus
	$WishListMemberInstance->AddMenu('settings', __('Settings', 'wishlist-member'), 'settings.php', true);
	$WishListMemberInstance->AddMenu('members', __('Members', 'wishlist-member'), 'members.php', true);
	$WishListMemberInstance->AddMenu('membershiplevels', __('Levels', 'wishlist-member'), 'membershiplevels.php', true);
	$WishListMemberInstance->AddMenu('managecontent', __('Content', 'wishlist-member'), 'membershiplevels.content.php', true);
	$WishListMemberInstance->AddMenu('sequential', __('Sequential Upgrade', 'wishlist-member'), 'sequential.php');
	$WishListMemberInstance->AddMenu('integration', __('Integration', 'wishlist-member'), 'integration.php', true);

	// display the apps tabs if external file contains a URL to the iframe
	if ( $WishListMemberInstance->Marketplace !== false && !empty( $WishListMemberInstance->Marketplace ) ) {
		$WishListMemberInstance->AddMenu('marketplace', __('Apps', 'wishlist-member'), 'marketplace.php', true);
	}

	// we display the extensions menu link if we have at least one extensions in our extensions folder
	if (count($WishListMemberInstance->extensions)) {
		$WishListMemberInstance->AddMenu('extensions', __('Extensions', 'wishlist-member'), 'extensions.php');
	}
}



// -----------------------------------------
// hook on to wordpress
if (isset($WishListMemberInstance)) {
	register_activation_hook(__FILE__, array(&$WishListMemberInstance, 'Activate'));
	register_deactivation_hook(__FILE__, array(&$WishListMemberInstance, 'Deactivate'));
	add_action('admin_head', array(&$WishListMemberInstance, 'AdminHead'), 1);
	if ($WishListMemberInstance->GetOption('LicenseStatus') == '1') {
		/* my hooks */
		// init
		add_action('init', array(&$WishListMemberInstance, 'Init'));
		add_action('admin_notices', array(&$WishListMemberInstance, 'HelpImproveNotification'));		
		add_action('admin_notices', array(&$WishListMemberInstance, 'WizardNotification'));
		add_action('admin_notices', array(&$WishListMemberInstance, 'UpdateNag'));
        add_action('admin_init', array(&$WishListMemberInstance, 'dismiss_wlm_update_notice'));


		// Loads Scripts
		add_action('admin_enqueue_scripts', array(&$WishListMemberInstance, 'admin_scripts_and_styles'), 9999999999);
		add_action('wp_enqueue_scripts', array(&$WishListMemberInstance, 'frontend_scripts_and_styles'), 9999999999);

		/* register widget when loading the WP core. only for wp2.8+ */
		if (version_compare($wp_version, '2.8', '>='))
			add_action('widgets_init', array(&$WishListMemberInstance, 'AnotherWidget_register_widgets'));


		// user handling
		add_action('delete_user', array(&$WishListMemberInstance, 'DeleteUser'));
		add_action('deleted_user', array(&$WishListMemberInstance, 'DeletedUser'));
		add_action('profile_update', array(&$WishListMemberInstance, 'ProfileUpdate'));

		// Content Handling
		add_action('admin_init', array(&$WishListMemberInstance, 'PreparePostPageOptions'), 1);
		add_action('wp_insert_post', array(&$WishListMemberInstance, 'SavePostPage'));

		// Miscellaneous
		add_action('wp_login', array(&$WishListMemberInstance, 'Login'));
		add_action('wp_logout', array(&$WishListMemberInstance, 'Logout'));
		add_action('retrieve_password', array(&$WishListMemberInstance, 'RetrievePassword'));
		add_action('wp_footer', array(&$WishListMemberInstance, 'Footer'));
		add_action('wp_head', array(&$WishListMemberInstance, 'WPHead'));

		// Password Hinting
		if ($WishListMemberInstance->GetOption('password_hinting')) {
			add_action('wp_ajax_nopriv_PasswordHintSubmit', array(&$WishListMemberInstance, 'PasswordHintSubmit'));
			add_filter('login_errors', array(&$WishListMemberInstance, 'PasswordHinting'));
			add_filter('lostpassword_form', array(&$WishListMemberInstance, 'PasswordHintingEmail'));
		}

		// excluded pages
		add_filter('wp_list_pages_excludes', array(&$WishListMemberInstance, 'ExcludePages'));

		// 404
		add_filter('404_template', array(&$WishListMemberInstance, 'The404'));
		// registration stuff
		add_filter('the_posts', array(&$WishListMemberInstance, 'RegistrationPage'));

		// template hooks
		//add_filter('archive_template', array(&$WishListMemberInstance, 'Process'));
		add_filter('taxonomy_template', array(&$WishListMemberInstance, 'Process'));
		add_filter('page_template', array(&$WishListMemberInstance, 'Process'));
		add_filter('single_template', array(&$WishListMemberInstance, 'Process'));
		add_filter('category_template', array(&$WishListMemberInstance, 'Process'));
		add_filter('tag_template', array(&$WishListMemberInstance, 'Process'));

		// auto insert more tag
		add_filter('the_posts', array(&$WishListMemberInstance, 'TheMore'));

		// feed link
		add_filter('feed_link', array(&$WishListMemberInstance, 'FeedLink'));

		// handling of private and register tags
		add_filter('the_content', array(&$WishListMemberInstance, 'TheContent'));
		add_filter('the_content_feed', array(&$WishListMemberInstance, 'TheContent'));

		// mail sender information
		add_filter('wp_mail_from', array(&$WishListMemberInstance, 'MailFrom'), 9999999);
		add_filter('wp_mail_from_name', array(&$WishListMemberInstance, 'MailFromName'), 9999999);

		// hooks for the "Only show content for each membership level" setting
		add_action('pre_get_posts', array(&$WishListMemberInstance, 'OnlyShowContentForLevel'));
		add_action('wp_list_pages_excludes', array(&$WishListMemberInstance, 'OnlyListPagesForLevel'));
		add_filter('list_terms_exclusions', array(&$WishListMemberInstance, 'OnlyListCatsForLevel'));
		add_filter('get_previous_post_where', array(&$WishListMemberInstance, 'OnlyShowPrevNextLinksForLevel'));
		add_filter('get_next_post_where', array(&$WishListMemberInstance, 'OnlyShowPrevNextLinksForLevel'));
		add_filter('wp_get_nav_menu_items', array(&$WishListMemberInstance, 'OnlyListNavMenuItemsForLevel'));
		add_filter('comment_feed_where', array(&$WishListMemberInstance, 'OnlyShowCommentsForLevel'));
		add_filter('the_comments', array(&$WishListMemberInstance, 'RecentComments'), 10, 2);

		add_action('edit_user_profile', array(&$WishListMemberInstance, 'ProfilePage'));
		add_action('show_user_profile', array(&$WishListMemberInstance, 'ProfilePage'));

		add_action('wishlistmember_email_queue', array(&$WishListMemberInstance, 'SendQueuedMail'));
		add_action('wishlistmember_sequential_upgrade', array(&$WishListMemberInstance, 'DoSequential'));
		add_action('wishlistmember_unsubscribe_expired', array(&$WishListMemberInstance, 'UnsubscribeExpired'));
		add_action('wishlistmember_check_scheduled_cancelations', array(&$WishListMemberInstance, 'CancelScheduledCancelations'));
		add_action('wishlistmember_check_level_cancelations', array(&$WishListMemberInstance, 'CancelScheduledLevels'));
		add_action('wishlistmember_registration_notification', array(&$WishListMemberInstance, 'NotifyRegistration'));
		add_action('wishlistmember_expring_members_notification', array(&$WishListMemberInstance, 'ExpiringMembersNotification'));
		add_action('wishlistmember_api_queue', array(&$WishListMemberInstance, 'ProcessApiQueue'));

		// hook for Scheduled User Levels
		add_action('wishlistmember_run_scheduled_user_levels', array(&$WishListMemberInstance, 'RunScheduledUserLevels'));


		//prevent deletion of post if its pay per post
		add_action('before_delete_post', array(&$WishListMemberInstance, 'CheckPostToDelete'));
		add_action('wp_trash_post', array(&$WishListMemberInstance, 'CheckPostToDelete'));

		// RSS Enclosures
		if ($WishListMemberInstance->GetOption('disable_rss_enclosures'))
			add_filter('rss_enclosure', array(&$WishListMemberInstance, 'RSSEnclosure'));

		// Attachments
		add_action('add_attachment', array(&$WishListMemberInstance, 'Add_Attachment'));
		add_action('edit_attachment', array(&$WishListMemberInstance, 'Add_Attachment'));
		add_action('edit_attachment', array(&$WishListMemberInstance, 'Update_Attachment_Protection'));
		add_action('delete_attachment', array(&$WishListMemberInstance, 'Delete_Attachment'));
		//add_filter('wishlistmember_attachments_load', array(&$WishListMemberInstance, 'ReloadAttachments'));
		// Auto Remove From Hooks
		add_action('wishlistmember_add_user_levels', array(&$WishListMemberInstance, 'DoAutoRemoveFrom'), 1, 2);
		add_action('wishlistmember_approve_user_levels', array(&$WishListMemberInstance, 'DoAutoRemoveFrom'), 1, 2);
		add_action('wishlistmember_confirm_user_levels', array(&$WishListMemberInstance, 'DoAutoRemoveFrom'), 1, 2);
		add_action('wishlistmember_uncancel_user_levels', array(&$WishListMemberInstance, 'DoAutoRemoveFrom'), 1, 2);

		add_action('wishlistmember_approve_user_levels', array(&$WishListMemberInstance, 'Remove_Pending_To_Add_Autoresponder'), 2, 3);
		add_action('wishlistmember_confirm_user_levels', array(&$WishListMemberInstance, 'Remove_Pending_To_Add_Autoresponder'), 2, 3);
		add_action('wishlistmember_user_registered', array(&$WishListMemberInstance, 'UserRegisteredCleanup'), 10, 3);

		// Temp email handling
		// note that our priority for this filter is ridiculously low to ensure that it runs last
		add_filter('sanitize_email', array(&$WishListMemberInstance, 'TempEmailSanitize'), 1234567890);

		add_filter('site_transient_update_plugins', array(&$WishListMemberInstance, 'Plugin_Update_Notice'));
		add_filter('plugins_api', array(&$WishListMemberInstance, 'Plugin_Info_Hook'), 10, 3);

		add_filter('upgrader_pre_install', array(&$WishListMemberInstance, 'Pre_Upgrade'), 10, 2);
		add_filter('upgrader_post_install', array(&$WishListMemberInstance, 'Post_Upgrade'), 10, 2);

		add_filter('rewrite_rules_array', array(&$WishListMemberInstance, 'RewriteRules'));

		add_action('wp_ajax_wlm_user_search', array(&$WishListMemberInstance, 'WLMUserSearch_Ajax'));
		add_action('wp_ajax_wlm_user_search', array(&$WishListMemberInstance, 'WLMUserSearch_Ajax'));
		add_action('wp_ajax_wlm_payperpost_search', array(&$WishListMemberInstance, 'WLM_PayPerPost_Search'));
		add_action('wp_ajax_wlm_feeds', array(&$WishListMemberInstance, 'DashboardFeeds'));
		add_action('wp_ajax_wlm_delete_saved_search', array(&$WishListMemberInstance, 'WLMDeleteSavedSearch_Ajax'));

		add_action('admin_init', array(&$WishListMemberInstance, 'Upgrade_Check'));

		add_action('wishlistmember_after_registration', array(&$WishListMemberInstance, 'Add_Additional_Levels'));

		// setup shopping carts
		include_once($WishListMemberInstance->pluginDir . '/lib/integration.shoppingcarts.php');
		foreach ($wishlist_member_shopping_carts AS $wlm_integration_file => $wlm_integration_data) {
			$WishListMemberInstance->LoadInitFile($wlm_integration_file);
			$WishListMemberInstance->RegisterSCIntegration($wlm_integration_data['optionname'], $wlm_integration_file, $wlm_integration_data['classname'], $wlm_integration_data['methodname']);

		}

		// setup autoresponders
		include_once($WishListMemberInstance->pluginDir . '/lib/integration.autoresponders.php');
		foreach ($wishlist_member_autoresponders AS $wlm_integration_file => $wlm_integration_data) {
			$WishListMemberInstance->LoadInitFile($wlm_integration_file);
			$WishListMemberInstance->RegisterARIntegration($wlm_integration_data['optionname'], $wlm_integration_file, $wlm_integration_data['classname'], $wlm_integration_data['methodname']);
		}

		// setup webinars
		include_once($WishListMemberInstance->pluginDir . '/lib/integration.webinars.php');
		foreach ($wishlist_member_webinars AS $wlm_integration_file => $wlm_integration_data) {
			$WishListMemberInstance->LoadInitFile($wlm_integration_file);
			$WishListMemberInstance->RegisterWebinarIntegration($wlm_integration_data['optionname'], $wlm_integration_file, $wlm_integration_data['classname']);
		}

		// setup other integrations
		$wishlist_member_other_integrations = (array) $WishListMemberInstance->GetOption('ActiveIntegrations');
		foreach ($wishlist_member_other_integrations AS $wlm_integration_file => $wlm_integration_status) {
			if ($wlm_integration_status) {
				include_once($WishListMemberInstance->pluginDir . '/lib/' . $wlm_integration_file);
			}
		}
	}
}
