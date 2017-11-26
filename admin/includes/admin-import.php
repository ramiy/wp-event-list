<?php
if(!defined('WP_ADMIN')) {
	exit;
}

require_once(EL_PATH.'includes/options.php');
require_once(EL_PATH.'admin/includes/admin-functions.php');
require_once(EL_PATH.'includes/events.php');

// This class handles all data for the admin new event page
class EL_Admin_Import {
	private static $instance;
	private $options;
	private $functions;
	private $events;
	private $import_data;
	private $example_file_path = EL_URL.'/files/events-import-example.csv';

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
		$this->functions = &EL_Admin_Functions::get_instance();
		$this->events = &EL_Events::get_instance();
		$this->add_metaboxes();
	}

	public function show_import() {
		if(!current_user_can('edit_posts')) {
			wp_die(__('You do not have sufficient permissions to access this page.'));
		}
		echo '
			<div class="wrap">
				<div id="icon-edit-pages" class="icon32"><br /></div>
				<h2>'.__('Import Events','event-list').'</h2>';
		// Review import
		if(isset($_FILES['el_import_file'])) {
			$this->show_import_review();
		}
		// Finish import (add events)
		elseif(isset($_POST['reviewed_events'])) {
			$import_error = $this->import_events();
			$this->show_import_finished($import_error);
		}
		// Import form
		else {
			$this->show_import_form();
		}
		echo '
			</div>';
	}

	private function show_import_form() {
		echo '
				<h3>'.__('Step','event-list').' 1: '.__('Set import file and options','event-list').'</h3>
				<form action="" id="el_import_upload" method="post" enctype="multipart/form-data">
					'.$this->functions->show_option_table('import').'<br />
					<input type="submit" name="button-upload-submit" id="button-upload-submit" class="button" value="'.sprintf(__('Proceed with Step %1$s','event-list'), '2').' &gt;&gt;" />
				</form>
				<br /><br />
				<h3>'.__('Example file','event-list').'</h4>
				<p>'.sprintf(__('You can download an example file %1$shere%2$s (CSV delimiter is a comma!)','event-list'), '<a href="'.$this->example_file_path.'">', '</a>').'</p>
				<p><em>'.__('Note','event-list').':</em> '.__('Do not change the column header and separator line (first two lines), otherwise the import will fail!','event-list').'</p>';
	}

	private function show_import_review() {
		$file = $_FILES['el_import_file']['tmp_name'];
		// check for file existence (upload failed?)
		if(!is_file($file)) {
			echo '<h3>'.__('Sorry, there has been an error.','event-list').'</h3>';
			echo __('The file does not exist, please try again.','event-list').'</p>';
			return;
		}

		// check for file extension (csv) first
		$file_parts = pathinfo($_FILES['el_import_file']['name']);
		if($file_parts['extension'] !== "csv") {
			echo '<h3>'.__('Sorry, there has been an error.','event-list').'</h3>';
			echo __('The file is not a CSV file.','event-list').'</p>';
			return;
		}

		// safe settings
		$this->safe_import_settings();

		// parse file
		$this->import_data = $this->parseImportFile($file);

		// parsing failed?
		if(is_wp_error($this->import_data)) {
			echo '<h3>'.__('Sorry, there has been an error.','event-list').'</h3>';
			echo '<p>' . esc_html($this->import_data->get_error_message()).'</p>';
			return;
		}

		// Check categories
		$not_available_cats = array();
		foreach($this->import_data as $event) {
			foreach($event['categories'] as $cat) {
				if(!$this->events->cat_exists($cat) && !in_array($cat, $not_available_cats)) {
					$not_available_cats[] = $cat;
				}
			}
		}

		// show review page
		echo '
			<h3>'.__('Step','event-list').' 2: '.__('Events review and additonal category selection','event-list').'</h3>';
		if(!empty($not_available_cats)) {
			echo '
				<div class="el-warning">'.__('Warning: The following category slugs are not available and will be removed from the imported events:','event-list').'
					<ul class="el-categories">';
			foreach($not_available_cats as $cat) {
				echo '<li><code>'.$cat.'</code></li>';
			}
			echo '</ul>
					'.__('If you want to keep these categories, please create these Categories first and do the import afterwards.','event-list').'</div>';
		}
		echo '
			<form method="POST" action="'.admin_url('edit.php?post_type=el_events&page=el_admin_import').'">)';
		wp_nonce_field('autosavenonce', 'autosavenonce', false, false);
		wp_nonce_field('closedpostboxesnonce', 'closedpostboxesnonce', false, false);
		wp_nonce_field('meta-box-order-nonce', 'meta-box-order-nonce', false, false);
		echo '
			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-2">
					<div id="post-body-content">';
		foreach($this->import_data as $event) {
			$this->show_event($event);
		}
		echo '
					</div>
					<div id="postbox-container-1" class="postbox-container">';
		do_meta_boxes('el-import', 'side', null);
		echo '
					</div>
				</div>
			</div>
			<input type="hidden" name="reviewed_events" id="reviewed_events" value="'.esc_html(json_encode($this->import_data)).'" />
			</form>';
	}

	private function show_import_finished($with_error) {
		if(empty($with_error)) {
			echo '
				<h3>'.__('Import with errors!','event-list').'</h3>
				'.__('Sorry, an error occurred during import!','event-list');
		}
		else {
			echo '
				<h3>'.__('Import successful!','event-list').'</h3>
				<a href="'.admin_url('edit.php?post_type=el_events').'">'.__('Go back to All Events','event-list').'</a>';
		}
	}

	private function show_event($event) {
		echo '
				<p>
				<span class="el-event-header">'.__('Title','event-list').':</span> <span class="el-event-data">'.$event['title'].'</span><br />
				<span class="el-event-header">'.__('Start Date','event-list').':</span> <span class="el-event-data">'.$event['startdate'].'</span><br />
				<span class="el-event-header">'.__('End Date','event-list').':</span> <span class="el-event-data">'.$event['enddate'].'</span><br />
				<span class="el-event-header">'.__('Time','event-list').':</span> <span class="el-event-data">'.$event['starttime'].'</span><br />
				<span class="el-event-header">'.__('Location','event-list').':</span> <span class="el-event-data">'.$event['location'].'</span><br />
				<span class="el-event-header">'.__('Description','event-list').':</span> <span class="el-event-data">'.$event['description'].'</span><br />
				<span class="el-event-header">'.__('Category slugs','event-list').':</span> <span class="el-event-data">'.implode(', ', $event['categories']).'</span>
				</p>';
	}

	/**
	 * @return WP_Error
	 */
	private function parseImportFile($file) {
		$delimiter = ',';
		$header = array('title', 'startdate', 'enddate', 'starttime', 'location', 'description', 'category_slugs');
		$separator = array('sep=,');

		// list of events to import
		$events = array();

		$file_handle = fopen($file, 'r');
		$lineNum = 0;
		$emptyLines = 0;
		while(!feof($file_handle)) {
			$line = fgetcsv($file_handle, 0);

			// skip empty lines
			if(empty($line)) {
				$emptyLines += 1;
				continue;
			}
			// check header
			if(empty($lineNum)) {
				// check optional separator line
				if($line === $separator) {
					$emptyLines += 1;
					continue;
				}
				// check header line
				elseif($line === $header || $line === array_slice($header,0,-1)) {
					$lineNum += 1;
					continue;
				}
				else {
					return new WP_Error('CSV_parse_error', sprintf(__('There was an error at line %1$s when reading this CSV file: Header line is missing or not correct!','event-list'), $lineNum+$emptyLines));
				}
			}
			// handle lines with events
			$events[] = array(
				'title'       => $line[0],
				'startdate'   => $line[1],
				'enddate'     => !empty($line[2]) ? $line[2] : $line[1],
				'starttime'   => $line[3],
				'location'    => $line[4],
				'description' => $line[5],
				'categories'  => isset($line[6]) ? explode('|', $line[6]) : array(),
			);
			$lineNum += 1;
		}
		//close file
		fclose($file_handle);
		return $events;
	}

	private function safe_import_settings() {
		foreach($this->options->options as $oname => $o) {
			// check used post parameters
			$ovalue = isset($_POST[$oname]) ? sanitize_text_field($_POST[$oname]) : '';

			if('import' == $o['section'] && !empty($ovalue)) {
				$this->options->set($oname, $ovalue);
			}
		}
	}

	public function add_metaboxes() {
		add_meta_box('event-publish', __('Import events','event-list'), array(&$this, 'render_publish_metabox'), 'el-import', 'side');
		add_meta_box('event-categories', __('Add additional categories','event-list'), array(&$this, 'render_category_metabox'),'el-import', 'side');
	}

	public function render_publish_metabox() {
		echo '
			<div class="submitbox">
				<div id="delete-action"><a href="?page=el_admin_main" class="submitdelete deletion">'.__('Cancel').'</a></div>
				<div id="publishing-action"><input type="submit" class="button button-primary button-large" name="import" value="'.__('Import','event-list').'" id="import"></div>
				<div class="clear"></div>
			</div>';
	}

	public function render_category_metabox($post, $metabox) {
		require_once(ABSPATH.'wp-admin/includes/meta-boxes.php');
		$dpost = get_default_post_to_edit('el-events');
		$box = array('args' => array('taxonomy' => 'el_eventcategory'));
		post_categories_meta_box($dpost, $box);
	}

	private function import_events() {
		// check used post parameters
		$reviewed_events = json_decode(stripslashes($_POST['reviewed_events']), true);
		if(empty($reviewed_events)) {
			return false;
		}
		$additional_cat_ids = isset($_POST['tax_input']['el_eventcategory']) && is_array($_POST['tax_input']['el_eventcategory']) ? array_map('sanitize_key', $_POST['tax_input']['el_eventcategory']) : array();
		$additional_cat_slugs = array();
		foreach($additional_cat_ids as $cat_id) {
			$cat = $this->events->get_cat_by_id($cat_id);
			if(!empty($cat)) {
				$additional_cat_slugs[] = $cat->slug;
			}
		}
		// Category handling
		foreach($reviewed_events as &$event_ref) {
			// Remove not available categories of import file
			foreach($event_ref['categories'] as $ckey => $cat_slug) {
				if(!$this->events->cat_exists($cat_slug)) {
					unset($event_ref['categories'][$ckey]);
				}
			}
			// Add the additionally specified categories to the event
			if(!empty($additional_cat_slugs)) {
				$event_ref['categories'] = array_unique(array_merge($event_ref['categories'], $additional_cat_slugs));
			}
		}
		$ret = array();
		require_once(EL_PATH.'includes/event.php');
		foreach($reviewed_events as $eventdata) {
			// check if dates have correct formats
			$startdate = DateTime::createFromFormat($this->options->get('el_import_date_format'), $eventdata['startdate']);
			$enddate = DateTime::createFromFormat($this->options->get('el_import_date_format'), $eventdata['enddate']);
			if($startdate instanceof DateTime) {
				$eventdata['startdate'] = $startdate->format('Y-m-d');
				if($enddate) {
					$eventdata['enddate'] = $enddate->format('Y-m-d');
				}
				else {
					$eventdata['enddate'] = '';
				}
				$ret[] = empty(EL_Event::safe($eventdata));
			}
			else {
				return false;
			}
		}
		// TODO: Improve error messages
		return $ret;
	}

	public function embed_import_scripts() {
		wp_enqueue_style('eventlist_admin_import', EL_URL.'admin/css/admin_import.css');
	}
}
?>
