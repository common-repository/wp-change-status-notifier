<?php

/**
* Plugin Name: WP Status Change Notifier
* Description: Sends email notification about post status changes.
* Version: 1.0
* Author: fulippo <filippo.pisano@gmail.com>
*/

/*  
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


class WP_Status_Change_Notifier{
	
	/**
	 * Constructor
	 * @param void
	 * @return void
	 */
	public function __construct(){
		$this->init();
	}
	
	/**
	 * Init
	 * Bind WordPress hooks to plugin callbacks
	 * @param void
	 * @return void
	 */
	public function init(){
		register_activation_hook(__FILE__,array(&$this,'set_defaults'));
		add_action("admin_menu", array(&$this, "add_option_page"));
		add_filter("transition_post_status", array(&$this, "notify_status_change"), 10, 3);
		add_action('plugins_loaded', array(&$this,'add_textdomain'));
	}
	
	/**
	 * Add textdomain
	 * @param void
	 * @return void
	 */
	public function add_textdomain(){
		load_plugin_textdomain("wpscn", false, dirname( plugin_basename( __FILE__ ) ) . '/langs');	
	}
	
	/**
	 * Set Defaults
	 * options population on plugin activation
	 * @param void
	 * @return void
	 */
	public function set_defaults(){
		$users_query = new WP_User_Query( array(
			'fields' => 'all_with_meta',
			'role' => 'administrator',
			'orderby' => 'display_name'
		) );
		$administrators = $users_query->get_results();
		
		if(!empty($administrators)){
			$administrators_id = array();
			foreach($administrators as $administrator){
				$administrators_id[] = $administrator->ID;
			}
			
			$defaults = array(
				'notify_approved' => 1,
				'notify_declined' => 1,
				'notify_users' => implode(",",(array)$administrators_id)
			);
			update_option('status_change_notifier',$defaults);
		}
	}
	
	/**
	 * Add option page
	 * callback function that adds a call to options_page()
	 * @param void
	 * @return void
	 */
	public function add_option_page(){
		add_options_page(__('WP Change Status Notifications',"wpscn"), __('Posts status changes notifications',"wpscn"), 'edit_themes', 'status_notifier', array(&$this,'options_page'));
	}
	
	/**
	 * Options page
	 * plugin's options page
	 * @param void
	 * @return void
	 */
	public function options_page(){
		require_once dirname(__FILE__) . '/options-page.php';
	}
	
	/**
	 * Get WordPress roles with privileges
	 * returns an array with all the roles in the setup
	 * with edit_others_posts capability
	 * @param void
	 * @return array $roles
	 */
	private function get_roles_with_privileges(){
		
		global $wp_roles;
		$roles = array();
		
		foreach($wp_roles->roles as $role_name => $role){
			if($role['capabilities']['edit_others_posts']){
				$roles[$role_name] = $role['name'];
			}
		}
		
		return $roles;
	}
	
	/**
	 * Create subject
	 * returns a formatted string used in e-mail communications
	 * @param object $post
	 * @param string $subject
	 * @return string The formatted string
	 */
	private function create_subject($post, $subject){
		return sprintf("[%s] %s %s", get_bloginfo("blogname"), $post->post_title, $subject);
	}
	
	/**
	 * Create message
	 * returns a formatted string used in e-mail communications
	 * @param string $message Message to include in e-mail
	 * @param object $post
	 * @param wp_user object $contributor The author of the post
	 * @param wp_user object $modified_by WordPress user who last edited the post
	 * @return string Formatted message
	 */
	private function create_message($message, $post, $contributor, $modified_by, $status){
		
		$message = $message . "\n\n";
		$message.= __("Post: %s\n","wpscn");
		$message.= __("Edit: %s\n","wpscn");
		$message.= __("Created by: %s\n","wpscn");
		$message.= __("Last modify by: %s\n","wpscn");
		$message.= __("Post status changed to: %s\n","wpscn");
		
		$post_edit_link = get_admin_url(null, '/post.php?post='.$post->ID.'&action=edit');
		
		return sprintf($message, $post->post_title, $post_edit_link, $contributor->display_name, $modified_by->data->display_name, $status);
		
	}
	
	/**
	 * Notify status change
	 * the main function which controls posts status changes
	 * @param string $new_status 
	 * @param string $old_status
	 * @param object $post
	 * @return void
	 */
	public function notify_status_change($new_status, $old_status, $post) {
		global $current_user;
		
		$options = get_option("status_change_notifier");
		$contributor = get_userdata($post->post_author);
		$mail_headers = array("Content-Type"=> "text/html");
		
		/**
		 * Pending notifications
		 * send notifications to selected users
		 */
		if ($old_status != 'pending' && $new_status == 'pending' && !empty($options['notify_users'])){
				
			$users = get_users(array("include"=>$options['notify_users']));
			
			if(!empty($users)){
				foreach($users as $user){
					$subject = $this->create_subject($post, __("pending review","wpscn"));
					$message = __("A new post is in moderation queue","wpscn");
					$message = $this->create_message($message, $post, $contributor, $current_user, __("pending review","wpscn"));
					
					wp_mail($user->user_email, $subject, $message, $mail_headers);
				}
			}
		}
		elseif($old_status == 'pending' && $new_status == 'publish' && $current_user->ID!=$contributor->ID){
			if(!empty($options['notify_approved'])) {
				$subject = $this->create_subject($post, __("approved","wpscn"));
				$message = __("Your post has been approved","wpscn");
				$message = $this->create_message($message, $post, $contributor, $current_user, __("approved","wpscn"));
				wp_mail($contributor->user_email, $subject, $message, $mail_headers);
			}
		}
		elseif($old_status == 'pending' && $new_status == 'draft' && $current_user->ID!=$contributor->ID) {
			if(!empty($options['notify_declined'])) {
				$subject = $this->create_subject($post, __("rejected","wpscn"));
				$message = __("Your post has been rejected","wpscn");
				$message = $this->create_message($message, $post, $contributor, $current_user, __("rejected","wpscn"));
				wp_mail($contributor->user_email, $subject, $message, $mail_headers);
			}
		}
	}
}

new WP_Status_Change_Notifier;

/**
 * EOF
 */