<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly.

require_once(ABSPATH . '/wp-admin/includes/class-wp-list-table.php');

if (!class_exists('PiePluginListTable')) {
	class PiePluginListTable extends \Wp_List_Table {
		private $plugin_paths;
		private $plugin_states;

		public function __construct($plugin_paths=[], $plugin_states=[]) {
			parent::__construct([
				'singular' => __('PIE Plugin', 'sp'), //singular name of the listed records
				'plural' => __('PIE Plugins', 'sp'), //plural name of the listed records
				'ajax' => false //should this table support ajax?
			]);
			$this->plugin_paths = $plugin_paths;
			$this->plugin_states = $plugin_states;
		}

		public function wp_list_table_data($orderby="", $order="") {
			$data = [];
			foreach($this->plugin_paths as $slug => $path) {
				$plugin_data = get_plugin_data($path.'/'.$slug.'.php');
				$data[] = [
					"plugin_name"=>$plugin_data['Name'],
					"plugin-description"=>$plugin_data['Description'],
					"plugin-data"=>$plugin_data,
					"plugin_state"=>$this->plugin_states[$slug],
					'plugin-slug'=>$slug
				];
			}
			if ($orderby == 'plugin_name') {
				if ($order == 'desc') {
					usort($data, function($a, $b) {
						return $b<=>$a;
					});
				} else {
					usort($data, function($a, $b) {
						return $a<=>$b;
					});
				}
			}
			return $data;
		}

		public function prepare_items() {
			$orderby = isset($_GET['orderby']) ? trim($_GET['orderby']) : '';
			$order = isset($_GET['order']) ? trim($_GET['order']) : '';

			$this->items = $this->wp_list_table_data($orderby, $order);

			$columns = $this->get_columns();
			$hidden = ['plugin-data', 'plugin_state'];
			$sorted = [
				"plugin_name"=>["plugin_name", false]
			];
			$this->_column_headers = [$columns, $hidden, $sorted];
		}

		public function get_columns() {
			$columns = [
				"plugin_name"=>"Plugin",
				"plugin-description"=>"Description",
				"plugin-data"=>"Plugin Data",
				"plugin_state"=>"Plugin State"
			];
			return $columns;
		}

		public function column_plugin_name($item) {
			$output = sprintf('<p class="table-plugin-name %s">%s</p>', ($item['plugin_state']?'active':'inactive'), $item['plugin_name']);
			$button_name = 'plugin-'.($item['plugin_state']?'deactivate':'activate');
			$button_text = $item['plugin_state']?'Deactivate':'Activate';
			$output .= sprintf('<button class="table-plugin-activation-toggle" name="%s" value="%s">%s</button>', $button_name, $item["plugin-slug"], $button_text);

			return $output;
		}

		public function column_default($item, $column_name) {
			switch($column_name) {
				case 'plugin-description':
					return $item[$column_name];
				default: return 'No Data Found';
			}
		}
	}
	$table = new PiePluginListTable($this->plugin_paths, $this->plugin_states);
	$table->prepare_items();
	$table->display();
}
