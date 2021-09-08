<?php

namespace pie_plugin_manager;

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
			$output = '<p>'.$item['plugin_name'].'</p><br>';
			if ($item['plugin_state']) {
				$output .= '<button name="plugin-deactivate" value="' . $item["plugin-slug"] . '">Deactivate</button>';
			} else {
				$output .= '<button name="plugin-activate" value="' . $item["plugin-slug"] . '">Activate</button>';
			}

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
}
