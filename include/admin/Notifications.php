<?php

namespace gp\admin{

	defined('is_running') or die('Not an entry point...');

	class Notifications{

		public static		$notifications	= array();
		public static		$filters			= array();
		private static		$debug			= false;


		/**
		 * Outputs a list of notifications
		 * To be rendered in a gpabox
		 * The list output can be filtered by optional $_REQUEST['type'] value
		 *
		 */
		public static function ListNotifications(){
			global $langmessage;

			self::CheckNotifications();

			self::debug('$notifications = ' . pre(self::$notifications));

			echo '<div class="inline_box show-notifications-box">';



			$filter_list_by = '';
			if( isset($_REQUEST['type']) ){
				$filter_list_by = rawurldecode($_REQUEST['type']);
				self::Tabs($filter_list_by);
			}


			foreach( self::$notifications as $type => $notification ){


				if( empty($filter_list_by) ){
					$title = self::GetTitle($notification['title']);
					echo '<h3>' . $title . '</h3>';
				}elseif( $type != $filter_list_by ){
					continue;
				}


				echo '<table class="bordered full_width">';
				echo '<tbody>';
				echo '<tr>';
				echo '<th>' . $langmessage['Item'] . '</th>';
				echo '<th>' . $langmessage['options'] . '</th>';
				echo '<th style="text-align:right;">' . $langmessage['Visibility'] . '</th>';
				echo '</tr>';

				foreach( $notification['items'] as $item ){

					$tr_class	= '';
					$link_icon	= '<i class="fa fa-bell"></i>';
					$link_title	= $langmessage['Hide'];
					if( isset($item['priority']) && (int)$item['priority'] < 0 ){
						$tr_class	= ' class="notification-item-muted"';
						$link_icon	= '<i class="fa fa-bell-slash"></i>';
						$link_title	= $langmessage['Show'];
					}

					echo '<tr' . $tr_class . '>';
					echo 	'<td>' . $item['label']  . '</td>';
					echo 	'<td>' . $item['action'] . '</td>';

					echo 	'<td style="text-align:right;">';

					echo 	\gp\tool::Link(
								'Admin/Notifications/Manage',
								$link_icon,
								'cmd=toggle_priority'
									. '&id=' . rawurlencode($item['id'])
									. '&type=' . rawurlencode($filter_list_by),
								array(
									'title'		=> $link_title,
									'class'		=> 'toggle-notification',
									'data-cmd'	=> 'gpabox',
								)
							);

					echo	'</td>';

					echo '</tr>';
				}
				echo '</table>';
			}

			echo '<p>';
			echo '<button style="float:right;margin-right:0;" class="admin_box_close gpcancel">';
			echo $langmessage['Close'];
			echo '</button>';
			echo '</p>';

			echo '</div>';
		}

		/**
		 * Tabs
		 *
		 */
		public static function Tabs($filter_list_by){
			echo '<div class="layout_links">';
			foreach( self::$notifications as $type => $notification ){

				$class = '';

				if( $filter_list_by && $type == $filter_list_by ){
					$class = 'selected';
				}


				echo \gp\tool::Link(
					'Admin/Notifications',
					self::GetTitle($notification['title']) . ' ('.$notification['count'].')',
					'cmd=ShowNotifications&type=' . rawurlencode($type),
					array(
						'title'		=> self::GetTitle($notification['title']),
						'class'		=> $class,
						'style'		=> 'margin-right:0.5em;',
						'data-cmd'	=> 'gpabox',
					)
				);


			}
			echo '</div>';
		}


		/**
		 * Notification Title
		 */
		public static function GetTitle($title){
			global $langmessage;

			if( isset($langmessage[$title]) ){
				return $langmessage[$title];
			}
			return htmlspecialchars($title);
		}

		/**
		 * Manage Notifications
		 * Set display filters and priority for notification items by $_REQUEST
		 *
		 */
		public static function ManageNotifications(){
			global $page;

			$cmd = \gp\tool::GetCommand();

			switch( $cmd ){
				case 'toggle_priority':
					if( !empty($_REQUEST['id']) ){
						self::SetFilter($_REQUEST['id'], 'toggle_priority');
						self::UpdateNotifications(true);
						self::ListNotifications();
						return 'return';
					}
					break;

				case 'set_priority':
					if( !empty($_REQUEST['id']) &&
						isset($_REQUEST['new_priority']) &&
						is_numeric($_REQUEST['new_priority'])
						){
						self::SetFilter($_REQUEST['id'], 'set_priority', $_REQUEST['new_priority']);
						self::UpdateNotifications(true);
						self::ListNotifications();
						return 'return';
					}
					break;
			}

			$page->ajaxReplace = array();
			self::debug('Error: ManageNotifications - invalid command');

			return false;
		}



		/**
		 * Get active filters from the admin session
		 *
		 */
		public static function GetFilters(){
			global $gpAdmin;
			if( !empty($gpAdmin['notifications']['filters']) ){
				self::$filters = $gpAdmin['notifications']['filters'];
				return count(self::$filters);
			}
			return false;
		}



		/**
		 * Save filters to the admin session
		 *
		 */
		public static function SaveFilters(){
			global $gpAdmin;
			if( isset($gpAdmin['notifications']) && is_array($gpAdmin['notifications']) ){
				$gpAdmin['notifications']['filters'] = self::$filters;
			}else{
				$gpAdmin['notifications'] = array( 'filters' => self::$filters );
			}
		}



		/**
		 * Set a single filter
		 * @param string $id of notification
		 * @param string $do filter action
		 * @param string $val filter value
		 *
		 */
		public static function SetFilter($id, $do, $val=false){

			self::CheckNotifications();
			self::GetFilters();

			// check if id exists in notifications
			$id_exists = false;
			foreach( self::$notifications as $type => $notification ){
				foreach( $notification['items'] as $item ){
					if( isset($item['id']) && $item['id'] == $id ){
						$id_exists = true;
						break 2;
					}
				}
			}

			if( !$id_exists ){
				// notification id no longer exists, purge possible stray filter
				if( isset(self::$filters[$id]) ){
					unset(self::$filters[$id]);
					self::SaveFilters();
				}
				return;
			}

			switch( $do ){

				case 'clear_filter':
					unset(self::$filters[$id]);
					self::SaveFilters();
					return;

				case 'toggle_priority':
					if( isset(self::$filters[$id]['priority']) ){
						unset(self::$filters[$id]['priority']);
					}else{
						self::$filters[$id]['priority'] = -1;
					}
					self::SaveFilters();
					return;

				case 'set_priority':
					self::$filters[$id]['priority'] = (int)$val;
					self::SaveFilters();
					return;

			}

			self::debug(
					'Notifications SetFilter Error: unknown command "'
					. htmlspecialchars($do) . '"'
				);

			return false;
		}



		/**
		 * Apply filters to the notifications array
		 * Remove inapropriate items for users lacking permissions to deal with them
		 * Apply user defined (display) filters
		 *
		 */
		public static function ApplyFilters(){
			global $gpAdmin;

			self::GetFilters();


			// Remove items lacking user permissions and therefore cannot be dealt with anyway

			// debug / development
			if( $gpAdmin['granted'] != 'all' || $gpAdmin['editing'] != 'all' ){
				self::FilterType('debug','superuser');
			}

			// extra content draft
			if( !\gp\admin\Tools::HasPermission('Admin/Extra') ){
				self::FilterType('drafts','extra');
			}

			// theme update
			if( !\gp\admin\Tools::HasPermission('Admin_Theme_Content/Remote') ){
				self::FilterType('updates','theme');
			}

			// addon update
			if( !\gp\admin\Tools::HasPermission('Admin/Addons/Remote') ){
				self::FilterType('updates','plugin');
			}

			// core update
			if( !\gp\admin\Tools::HasPermission('Admin/Uninstall') ){ // can't find a permission for core updates so I use Uninstall
				self::FilterType('updates','core');
			}

			// page draft
			self::FilterCallback('drafts',function($item){
				if( $item['type'] == 'page' && !\gp\admin\Tools::CanEdit($item['title']) ){
					return true;
				}
				return false;
			});


			// private page
			self::FilterCallback('private_pages',function($item){
				if( !\gp\admin\Tools::CanEdit($item['title']) ){
					return true;
				}
				return false;
			});

			// apply user filters
			self::FilterUserDefined();
		}

		/**
		 * Apply user defined (display) filters
		 *
		 */
		public static function FilterUserDefined(){

			foreach( self::$notifications as $notification_type => $notification ){
				foreach( $notification['items'] as $itemkey => $item ){

					// apply user filters
					if( isset($item['id']) && isset(self::$filters[$item['id']]) ){
						self::$notifications[$notification_type]['items'][$itemkey] = self::$filters[$item['id']] + $item;
					}
				}
			}
		}


		/**
		 * Filter notifications matching a notification type and item type
		 *
		 */
		public static function FilterType( $notification_type, $item_type ){

			if( !isset(self::$notifications[$notification_type]) ){
				return;
			}

			foreach( self::$notifications[$notification_type]['items'] as $itemkey => $item ){

				if( $item['type'] !== $item_type ){
					continue;
				}

				unset(self::$notifications[$notification_type]['items'][$itemkey]);
			}

		}

		/**
		 * Filter notifications matching a notification type with a callback
		 *
		 */
		public static function FilterCallback( $notification_type, $callback ){
			if( !isset(self::$notifications[$notification_type]) ){
				return;
			}
			foreach( self::$notifications[$notification_type]['items'] as $itemkey => $item ){
				if( $callback($item) === true ){
					unset(self::$notifications[$notification_type]['items'][$itemkey]);
				}
			}
		}


		/**
		* Aggregate all sources of notifications
		* @return array array of notifications
		*
		*/
		public static function CheckNotifications(){

			$notifications = array();

			$drafts = \gp\tool\Files::GetDrafts();
			if( count($drafts) > 0 ){
				$notifications['drafts'] = array(
					'title'			=> 'Working Drafts',
					'badge_bg'		=> '#329880',
					'badge_color'	=> '#fff',
					'items'			=> $drafts,
				);
			}

			$private_pages = \gp\tool\Files::GetPrivatePages();
			if( count($private_pages) > 0 ){
				$notifications['private_pages'] = array(
					'title'			=> 'Private Pages',
					'badge_bg'		=> '#ad5f45',
					'badge_color'	=> '#fff',
					'items'			=> $private_pages,
				);
			}

			if( count(\gp\Admin\Tools::$new_versions) > 0 ){
				$notifications['updates'] = array(
					'title'			=> 'updates',
					'badge_bg'		=> '#3153b7',
					'badge_color'	=> '#fff',
					'items'			=> self::GetUpdatesNotifications(),
				);
			}


			$debug_notices = self::GetDebugNotifications();
			if( !empty($debug_notices) ){
				$notifications['debug'] = array(
					'title'			=> 'Development',
					'badge_bg'		=> '#ff8c00',
					'badge_color'	=> '#000',
					'items'			=> $debug_notices,
				);
			}


			$notifications	= \gp\tool\Plugins::Filter('Notifications', array($notifications));

			self::$notifications = $notifications;
			self::ApplyFilters();


			// remove empty, count items, and get priority
			foreach( self::$notifications as $notification_type => &$notification ){

				if( empty(self::$notifications[$notification_type]['items']) ){
					unset(self::$notifications[$notification_type]);
					continue;
				}


				$count		= 0;
				$priority	= 0;
				foreach( $notification['items'] as $item ){
					if( isset($item['priority']) && is_numeric($item['priority']) && $item['priority'] > 0 ){
						$priority = max( (int)$item['priority'], $priority );
						$count++;
					}
				}

				$notification['count']		= $count;
				$notification['priority']	= $priority;

			}

			// sort by priority
			uasort(self::$notifications,function($a,$b){
				return strnatcmp($b['priority'],$a['priority']);
			});

		}




		/**
		* Get Notifications
		* Outputs a Notifications panelgroup
		* @param boolean $in_panel if panelgroup shall be rendered in admin menu
		*
		*/
		public static function GetNotifications($in_panel=true){
			global $langmessage, $gpAdmin;

			self::CheckNotifications();

			if( count(self::$notifications) < 1 ){
				return;
			}

			$total_count			= 0;
			$main_badge_style		= '';
			$links 					= array();
			$expand_class			= ''; // expand_child_click
			$badge_format			= ' <span class="dashboard-badge">(%2$d)</b>';
			$panel_class			= '';
			$default_style			= ['badge_bg'=>'transparent','color'=>'#fff'];

			if( $in_panel ){
				$badge_format		= ' <b class="admin-panel-badge" style="%1$s">%2$d</b>';
				$expand_class		= 'expand_child';
				$panel_class		= 'admin-panel-notifications';
			}



			ob_start();
			foreach(self::$notifications as $type => $notification ){

				if( empty($notification['items']) ){
					self::debug('notification => items subarray mising or empty');
					continue;
				}


				$total_count		+= $notification['count'];
				$title				= self::GetTitle($notification['title']);
				$badge_html			= '';
				$badge_style		= '';
				$notification		+= $default_style;


				if( $notification['count'] > 0 ){
					$badge_style		= 'background-color:'.$notification['badge_bg'].';color:'.$notification['badge_color'].';';
					$badge_html			= sprintf($badge_format, $badge_style, $notification['count']);
				}


				echo '<li class="' . $expand_class . '">';
				echo \gp\tool::Link(
						'Admin/Notifications',
						$title . $badge_html,
						'cmd=ShowNotifications&type=' . rawurlencode($type),
						array(
							'title'		=> $notification['count'] . ' ' . $title,
							'class'		=> 'admin-panel-notification', // . '-' . rawurlencode($type)',
							'data-cmd'	=> 'gpabox',
						)
					);
				echo '</li>';

				if( empty($main_badge_style) ){
					$main_badge_style	= $badge_style;
				}
			}

			$links = ob_get_clean();

			$panel_label	= $langmessage['Notifications'];

			$badge_html		= $total_count > 0 ?
								'<b class="admin-panel-badge" style="' . $main_badge_style . '">' . $total_count . '</b>' :
								'';

			\gp\Admin\Tools::_AdminPanelLinks(
				$in_panel,
				$links,
				$panel_label,
				'fa fa-bell',
				'notifications',
				$panel_class,	// new param 'class'
				$badge_html		// new param 'badge'
			);

		}


		/**
		* Get a Notification array for debugging / development relevant information
		* @return array single notification containing items
		*
		*/
		public static function GetDebugNotifications(){
			global $langmessage;

			$debug_note = array();

			if( ini_get('display_errors') ){
				$label = '<strong>ini_set(display_errors,' . htmlspecialchars(ini_get('display_errors')) . ')</em></strong>';
				$debug_note[] = array(
					'type'		=> 'any_user',
					'label'		=> $label,

					// Adding the server name to the hashed value makes sure the item id will change when moving the site (e.g. when going live)
					// Thus possible set hide filters will invalidate and the warning will show up again.
					'id'		=> hash('crc32b', $label . \gp\tool::ServerName()),

					'priority'	=> 500, // that's a high priority
					'action'	=> 'edit gpconfig.php or notify administrator! <br/>This should only be enabled in exceptional cases.',
				);
			}

			if( defined('gpdebug') && \gpdebug ){
				$label = 'gpdebug is enabled';
				$debug_note[] = array(
					'type'		=> 'superuser',
					'label'		=> $label,
					'id'		=> hash('crc32b', $label . \gp\tool::ServerName()),
					'priority'	=> 75,
					'action'	=> 'edit gpconfig.php',
				);
			}

			if( defined('create_css_sourcemaps') && \create_css_sourcemaps ){
				$label = 'create_css_sourcemaps is enabled';
				$debug_note[] = array(
					'type'		=> 'superuser',
					'label'		=> $label,
					'id'		=> hash('crc32b', $label . \gp\tool::ServerName()),
					'priority'	=> 75,
					'action'	=> 'edit gpconfig.php',
				);
			}

			return $debug_note;
		}



		/**
		* Convert $new_versions to notification array
		* @return array single notification containing items
		*
		*/
		public static function GetUpdatesNotifications(){
			global $langmessage;

			$updates = array();

			if( gp_remote_update && isset(\gp\Admin\Tools::$new_versions['core']) ){
				$label = \CMS_NAME . ' ' . \gp\Admin\Tools::$new_versions['core'];
				$updates[] = array(
					'type'		=> 'cms_core',
					'label'		=> $label,
					'id'		=> hash('crc32b', $label),
					'priority'	=> 60,
					'action'	=> '<a href="' . \gp\tool::GetDir('/include/install/update.php') . '">'
										. $langmessage['upgrade'] . '</a>',
				);
			}

			foreach(\gp\Admin\Tools::$new_versions as $addon_id => $new_addon_info){

				if( !is_numeric($addon_id) ){
					continue;
				}

				$label		= $new_addon_info['name'] . ':  ' . $new_addon_info['version'];
				$url		= \gp\admin\Tools::RemoteUrl( $new_addon_info['type'] );

				if( $url === false ){
					continue;
				}
				$updates[] = array(
					'type'		=> $new_addon_info['type'],
					'label'		=> $label,
					'id'		=> hash('crc32b', $label),
					'priority'	=> 60,
					'action'	=> '<a href="' . $url . '/' . $addon_id . '" data-cmd="remote">'
										. $langmessage['upgrade'] . '</a>',
				);

			}

			return $updates;
		}



		/**
		* Update Notifications
		* update the Notifications panelgroup in Admin Menu via AJAX
		* @param $ajax_include boolean if panelgroup shall be added to $page->ajaxReplace or die with a single js callback
		*
		*/
		public static function UpdateNotifications($ajax_include=false){
			global $page;

			if( !\gp\admin\Tools::HasPermission('Admin/Notifications') ){
				return;
			}

			ob_start();
			self::GetNotifications();
			$panelgroup = ob_get_clean();

			if( $ajax_include ){
				if( !is_array($page->ajaxReplace) ){
					$page->ajaxReplace = array();
				}
				$page->ajaxReplace[] = array('replace', '.admin-panel-notifications', $panelgroup);
				return;
			}

			echo \gp\tool\Output\Ajax::Callback();
			echo '([';
			echo '{DO:"replace",SELECTOR:".admin-panel-notifications",CONTENT:' . \gp\tool::JsonEncode($panelgroup) . '}';
			echo ']);';
			die();
		}

		public static function debug($msg){
			self::$debug && debug($msg);
		}


	}
}
