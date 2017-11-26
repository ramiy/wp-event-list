<?php
if(!defined('WP_ADMIN')) {
	exit;
}

require_once( EL_PATH.'includes/options.php' );

// This class handles all available admin pages
class EL_Admin {
	private static $instance;
	private $options;

	public static function &get_instance() {
		// Create class instance if required
		if(!isset(self::$instance)) {
			self::$instance = new self();
		}
		// Return class instance
		return self::$instance;
	}

	private function __construct() {
		$this->options = &EL_Options::get_instance();
		// Register actions
		add_action('admin_init', array(&$this, 'sync_post_categories'), 11);
		add_action('current_screen', array(&$this, 'register_events_post_type_mods'));
		add_action('admin_head', array(&$this, 'add_dashboard_styles'));
		add_action('admin_menu', array(&$this, 'register_pages'));
		add_filter('dashboard_glance_items', array($this, 'add_events_to_glance'));
	}

	public function sync_post_categories() {
		// Register syncing actions if enabled.
		// Has to be done after Options::register_options, so that $this->options->get returns the correct value.
		if(1 == $this->options->get('el_sync_cats')) {
			add_action('create_category', array(&$this, 'action_add_category'));
			add_action('edit_category', array(&$this, 'action_edit_category'));
			add_action('delete_category', array(&$this, 'action_delete_category'));
		}
	}

	public function register_events_post_type_mods($current_screen) {
		// load file depending on current screen
		if(current_user_can('edit_posts')) {
			switch($current_screen->id) {
				// Main/all events page
				case 'edit-el_events':
					require_once(EL_PATH.'admin/includes/admin-main.php');
					EL_Admin_Main::get_instance();
					break;
				// New/edit event page
				case 'el_events':
					require_once(EL_PATH.'admin/includes/admin-new.php');
					EL_Admin_new::get_instance();
					break;
			}
		}
	}

	/**
	 * Add and register all admin pages in the admin menu
	 */
	public function register_pages() {
		// Categories subpage
//		$page = add_submenu_page('el_admin_main', __('Event List Categories','event-list'), __('Categories','event-list'), 'manage_categories', 'el_admin_categories', array(&$this, 'show_categories_page'));
//		add_action('admin_print_scripts-'.$page, array(&$this, 'embed_categories_scripts'));

		// Settings subpage
		$page = add_submenu_page('edit.php?post_type=el_events', __('Event List Settings','event-list'), __('Settings','event-list'), 'manage_options', 'el_admin_settings', array(&$this, 'show_settings_page'));
		add_action('admin_print_scripts-'.$page, array(&$this, 'embed_settings_scripts'));

		// About subpage
		$page = add_submenu_page('edit.php?post_type=el_events', __('About Event List','event-list'), __('About','event-list'), 'edit_posts', 'el_admin_about', array(&$this, 'show_about_page'));
		add_action('admin_print_scripts-'.$page, array(&$this, 'embed_about_scripts'));

		// Import page (invisible in menu, but callable by import button on admin main page)
		$page = add_submenu_page(null, null, null, 'edit_posts', 'el_admin_import', array(&$this, 'show_import_page'));
		add_action('admin_print_scripts-'.$page, array(&$this, 'embed_import_scripts'));
	}

	public function add_dashboard_styles() {
		if(current_user_can('edit_posts') && 'dashboard' === get_current_screen()->base) {
			echo '<style>#dashboard_right_now .el-events-count:before {content: "\f508"}</style>';
		}
	}

	public function add_events_to_glance() {
		if(current_user_can('edit_posts')) {
			require_once(EL_PATH.'includes/events.php');
			$num_events = EL_Events::get_instance()->get_num_events();
			$text = sprintf(_n('%s Event','%s Events',$num_events,'event-list'), number_format_i18n($num_events));
			if(current_user_can(get_post_type_object('el_events')->cap->edit_posts)) {
				return array('<a class="el-events-count" href="'.admin_url('admin.php?page=el_admin_main').'">'.$text.'</a>');
			}
			else {
				return array($text);
			}

		}
	}

	public function show_categories_page() {
		require_once(EL_PATH.'admin/includes/admin-categories.php');
		EL_Admin_Categories::get_instance()->show_categories();
	}

	public function embed_categories_scripts() {
		require_once(EL_PATH.'admin/includes/admin-categories.php');
		EL_Admin_Categories::get_instance()->embed_categories_scripts();
	}

	public function show_settings_page() {
		require_once(EL_PATH.'admin/includes/admin-settings.php');
		EL_Admin_Settings::get_instance()->show_settings();
	}

	public function embed_settings_scripts() {
		require_once(EL_PATH.'admin/includes/admin-settings.php');
		EL_Admin_Settings::get_instance()->embed_settings_scripts();
	}

	public function show_about_page() {
		require_once(EL_PATH.'admin/includes/admin-about.php');
		EL_Admin_About::get_instance()->show_about();
	}

	public function embed_about_scripts() {
		require_once(EL_PATH.'admin/includes/admin-about.php');
		EL_Admin_About::get_instance()->embed_about_scripts();
	}

	public function show_import_page() {
		require_once(EL_PATH.'admin/includes/admin-import.php');
		EL_Admin_Import::get_instance()->show_import();
	}

	public function embed_import_scripts() {
		require_once(EL_PATH.'admin/includes/admin-import.php');
		EL_Admin_Import::get_instance()->embed_import_scripts();
	}

	public function action_add_category($cat_id) {
		require_once(EL_PATH.'includes/categories.php');
		EL_Categories::get_instance()->add_post_category($cat_id);
	}

	public function action_edit_category($cat_id) {
		require_once(EL_PATH.'includes/categories.php');
		EL_Categories::get_instance()->edit_post_category($cat_id);
	}

	public function action_delete_category($cat_id) {
		require_once(EL_PATH.'includes/categories.php');
		EL_Categories::get_instance()->delete_post_category($cat_id);
	}
}
?>
