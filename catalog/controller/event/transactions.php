<?php

namespace Opencart\Catalog\Controller\Extension\Lovat\Event;
class Transactions extends \Opencart\System\Engine\Controller {

	private $module = 'module_transactions';

	public function init(&$route, &$args/*, &$output*/ ) {

		if ($settim = $this->config->get($this->module)){

			$statt = json_decode($settim, true)['status'];
			$stat_transactions = json_decode($settim, true)['module_transactions_status'];
			if ($statt == 0 || $stat_transactions == 0) {
				return;
			}
			
			$this->load->model('checkout/cart');
			$products = $this->model_checkout_cart->getProducts();

			if (isset($this->session->data['customer']['customer_group_id'])) {
				$customer_group_id = $this->session->data['customer']['customer_group_id'];
			}else{
				$customer_group_id = 1;
			}

			if (count($products) > 0) {

				$suma = 0;
				foreach ($products as $keyv => $valuev) {
					$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `tax_class_id` = '0' WHERE `product_id` = '" . $valuev['product_id'] . "'");
					$suma = $suma + $valuev['total'];
				}

				if ($suma == 0) {
					return;
				}

				$tax_lovat = $this->getLovatTax($suma);
			
				if (isset($tax_lovat['tax_rate']) && $tax_lovat['tax_rate'] != 0) {

					foreach ($products as $l_product) {						
					
						$query_check_tax_class = $this->db->query("SELECT * FROM `" . DB_PREFIX . "tax_class` WHERE `title` = 'Lovat'");
						if ($query_check_tax_class->num_rows > 0) {	
							$l_tax_class_id = $query_check_tax_class->row['tax_class_id'];
						}else{
							$l_tax_class = $this->db->query("INSERT INTO `" . DB_PREFIX . "tax_class` SET `title` = 'Lovat', `description` = 'Lovat', `date_modified` = NOW(), `date_added` = NOW()");
							$l_tax_class_id = $this->db->getLastId();
						}

						$query_check_geo_zone = $this->db->query("SELECT `geo_zone_id`  FROM `" . DB_PREFIX . "geo_zone` WHERE `name` = 'Lovat_zone' AND `description` = 'Lovat_zone' ");
						if ($query_check_geo_zone->num_rows > 0) {	
							$l_geo_zone_id = $query_check_geo_zone->row['geo_zone_id'];
						}else{	
							$this->db->query("INSERT INTO `" . DB_PREFIX . "geo_zone` SET `name` = 'Lovat_zone', `description` = 'Lovat_zone', `date_modified` = NOW(), `date_added` = NOW()");
							$l_geo_zone_id = $this->db->getLastId();
						}

						$l_country_id = 0;
						$query_check_country = $this->db->query("SELECT `country_id` FROM `" . DB_PREFIX . "country` WHERE `iso_code_3` = '" . $this->db->escape(trim($tax_lovat['taxable_jurisdiction'])) . "'");
						if ($query_check_country->num_rows > 0) {	
							$l_country_id = $query_check_country->row['country_id'];
						}

						$this->db->query("DELETE FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `country_id` = '" . (int) $l_country_id. "' AND `zone_id` = '0' AND `geo_zone_id` = '" . (int) $l_geo_zone_id. "'");	
						$this->db->query("INSERT INTO `" . DB_PREFIX . "zone_to_geo_zone` SET `country_id` = '" . (int) $l_country_id . "', `zone_id` = '0', `geo_zone_id` = '" . (int) $l_geo_zone_id. "', `date_modified` = NOW(), `date_added` = NOW()");

					
						$query_check_tax_rate = $this->db->query("SELECT * FROM `" . DB_PREFIX . "tax_rate` WHERE `name` = 'Tax' AND `type` = 'P' AND `rate` = '" . (int) $tax_lovat['tax_rate'] . "' AND `geo_zone_id` = '" . $l_geo_zone_id . "'");

						if ($query_check_tax_rate->num_rows > 0) {	
							$l_tax_rate_id = $query_check_tax_rate->row['tax_rate_id'];
						}else{				

							$l_tax_rate = $this->db->query("INSERT INTO `" . DB_PREFIX . "tax_rate` SET `geo_zone_id` = '" . $l_geo_zone_id  . "', `name` = 'Tax', `rate` = '" . (float)$tax_lovat['tax_rate'] . "', `type` = 'P', `date_modified` = NOW(), `date_added` = NOW()");
							$l_tax_rate_id = $this->db->getLastId();

							$this->db->query("INSERT INTO `" . DB_PREFIX . "tax_rate_to_customer_group` SET `tax_rate_id` = '" . $l_tax_rate_id . "', `customer_group_id` = '" . $customer_group_id . "'");
						}

						$query_check_tax_rule = $this->db->query("DELETE FROM `" . DB_PREFIX . "tax_rule` WHERE `tax_class_id` = '" . (int) $l_tax_class_id. "'");				

						$this->db->query("INSERT INTO `" . DB_PREFIX . "tax_rule` SET `tax_class_id` = '" . $l_tax_class_id . "', `tax_rate_id` = '" . $l_tax_rate_id . "', `based` = 'store', `priority` = '0'");

						$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `tax_class_id` = '" . $l_tax_class_id . "' WHERE `product_id` = '" . $l_product['product_id'] . "'");
					}

				}

			}
	
		}

			
	}

	///////Lovat tax
	public function getLovatTax($l_total) {

		if (isset($this->session->data['shipping_address'])) {
			$l_country_id = $this->session->data['shipping_address']['country_id'];			
			$l_iso_code_3 = $this->session->data['shipping_address']['iso_code_3'];
			$arrival_zip = $this->session->data['shipping_address']['postcode'];
		
		} elseif (isset($this->session->data['payment_address'])) {
			$l_country_id = $this->session->data['payment_address']['country_id'];			
			$l_iso_code_3 = $this->session->data['payment_address']['iso_code_3'];
			$arrival_zip = $this->session->data['payment_address']['postcode'];
		}  else {
			$arrival_zip = '';
			$this->load->model('localisation/country');
			$dcountrie = $this->model_localisation_country->getCountries();
			foreach ($dcountrie as $keyc => $valuec) {
				if ($valuec['country_id'] == (int) $this->config->get('config_country_id')) {
					$l_iso_code_3 = $valuec['iso_code_3'];
				}
			}
			$l_country_id = (int)$this->config->get('config_country_id');
		}

		$query_setting = $this->db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE `code` = 'module_transactions'");	
		if ($query_setting->num_rows > 0) {
			$sett = json_decode($query_setting->row['value'], true);
			$s_url =  $sett['url'];
			$s_access_token =  $sett['access_token'];
			$s_module_transactions_status =  $sett['module_transactions_status'];
			
		}else{
			$s_url =  '';
			$s_access_token = '';
			$s_module_transactions_status = 0;
		}

		if ($s_module_transactions_status == 0) {
			return [];
		}

		$query_loc = $this->db->query("SELECT * FROM `" . DB_PREFIX . "location` ORDER BY `location_id` DESC LIMIT 1");

		if ($query_loc->num_rows ==0) {
			return [];
		}

		$departure_country = '';
		$departure_state = '';
		$departure_zip = '';
		if (isset($query_loc->row['address'])) {
			$departure_country = trim(explode(':', explode('|', $query_loc->row['address'])[0])[1]);
			$departure_state = trim(explode(':', explode('|', $query_loc->row['address'])[1])[1]);
			$departure_zip = trim(explode(':', explode('|', $query_loc->row['address'])[2])[1]);
		}
		

		$s_data = [
		    'transaction_id' => '123123',
		    'arrival_country' => $l_iso_code_3,
		    'arrival_zip' => $arrival_zip,
		    'currency' => $this->session->data['currency'],
		    'transaction_sum' => $l_total,
		    'departure_country' => $departure_country,
		    'departure_state' => $departure_state,
		    'departure_zip' => $departure_zip,
		];
		$s_data_string = json_encode($s_data);

		$l_url = $s_url.$s_access_token;

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $l_url);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("Accept: application/json"));
		curl_setopt($curl, CURLOPT_POSTFIELDS, $s_data_string);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

		$resp = curl_exec($curl);
		curl_close($curl);

		if ($resp) {
			return json_decode($resp, true);
		}else{
			return [];
		}
		
	}
///////////

	public function success(&$route, &$args/*, &$output */): void {
		
		if (isset($this->session->data['order_id'])) {			
			$query_order_product = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = '".(int)$this->session->data['order_id']."'");	
			if ($query_order_product->num_rows > 0) {
				foreach ($query_order_product->rows as $key => $value) {
					$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `tax_class_id` = '0' WHERE `product_id` = '" . $value['product_id'] . "'");
				}	
						
			}
		}
	}
			
 ////////////////

///////////

	public function tran(&$route, &$args, &$output ) {		

		if (isset($this->request->get['lovat_transactions']) && (int)($this->request->get['lovat_transactions']) > 0) {

			$this->load->model('setting/setting');
			$data = [];
			$setts = $this->model_setting_setting->getValue('module_transactions');
			if (!empty($setts)) {
				$settings = json_decode($setts, true);
				if ((int)($settings['status']) == 0) {
					$this->response->addHeader('Content-Type: application/json');
					$data = ['error' => 'status-off'];
					$output = json_encode($data);
					return;
				}
			}else{
				$this->response->addHeader('Content-Type: application/json');
				$data = ['error' => 'setting-empty'];
				$output = json_encode($data);
				return;
			}			

			if (isset($this->request->get['from_date'])) {
				$from_date = date('Y-m-d H:i:s', strtotime( $this->request->get['from_date'] ));
				$f_date = " AND `date_added` >= '".$from_date."' ";
			}else{
				$f_date = "";
			}
			if (isset($this->request->get['to_date'])) {
				$to_date = date('Y-m-d H:i:s', strtotime( $this->request->get['to_date'] ));
				$t_date = " AND `date_added` <= '".$to_date."' ";
			}else{
				$t_date = "";
			}

			$limit = 100;
			$offset = 0;
			if (isset($this->request->get['page'])) {

				$offset = (int)($this->request->get['page']) * $limit;
			}

			$this->load->model('localisation/order_status');
			$order_statuses = $this->model_localisation_order_status->getOrderStatuses();	
			
			$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE `order_status_id` IN (".implode(', ', $settings['order_statuses_id']).") " . $f_date . $t_date . "  ORDER BY `order_id` ASC LIMIT ".$limit." OFFSET ".$offset);

			if ($query->num_rows > 0) {

				foreach ($query->rows as $key => $value) {
	//country, region
					
					if ((int)$value['shipping_zone_id'] > 0) {
						$query_country = $this->db->query("SELECT c.iso_code_3, z. name FROM `" . DB_PREFIX . "country` c  LEFT JOIN `" . DB_PREFIX . "zone` z ON (c.`country_id` = z.`country_id`) WHERE c.`country_id` = '". (int)$value['shipping_country_id'] . "' AND z.`zone_id` = '". (int)$value['shipping_zone_id'] . "' ");
					}else{
						$query_country = $this->db->query("SELECT iso_code_3 FROM `" . DB_PREFIX . "country` WHERE `country_id` = '". (int)$value['shipping_country_id'] . "' ");
					}
					
					$arrival_country = '';
					$region = '';
					if ($query_country->num_rows > 0) {
						$arrival_country = $query_country->row['iso_code_3'];
						if (isset($query_country->row['name'])) {
							$region = $query_country->row['name'].', ';
						}						
					}

	// departure data
					$query_loc = $this->db->query("SELECT * FROM `" . DB_PREFIX . "location` ORDER BY `location_id` DESC LIMIT 1");
					if ($query_loc->num_rows > 0) {
						$departure_country = trim(explode(':', explode('|', $query_loc->row['address'])[0])[1]);
						$departure_city = trim(explode(':', explode('|', $query_loc->row['address'])[1])[1]);
						$departure_zip = trim(explode(':', explode('|', $query_loc->row['address'])[2])[1]);
						$departure_address = trim(explode(':', explode('|', $query_loc->row['address'])[3])[1]);
					}else{
						$departure_country = '';
						$departure_city = '';
						$departure_zip = '';
						$departure_address = '';
					}

// order total data
				$query_loc_tax = $this->db->query("SELECT value FROM `" . DB_PREFIX . "order_total` WHERE `order_id` = '". (int)$value['order_id']."' AND `title` = 'Tax'"); 				
				if ($query_loc_tax->num_rows > 0 && isset($query_loc_tax->row['value'])) {
					$tax_amount = (float)$query_loc_tax->row['value'];			
					
				}else{
					$tax_amount = 0;	
				}	

				$tax_rate = 0;
				if ($tax_amount > 0) {
					$query_loc_sub = $this->db->query("SELECT value FROM `" . DB_PREFIX . "order_total` WHERE `order_id` = '". (int)$value['order_id']."' AND `title` = 'Sub-Total'"); 
					if ($query_loc_sub->num_rows > 0 && isset($query_loc_sub->row['value'])) {
						$tax_rate = ($tax_amount / (float)$query_loc_sub->row['value']) * 100;			
						
					}
				}			


	// order status
					$order_status = '';
					foreach ($order_statuses as $o_status) {
						if ((int)$value['order_status_id'] == (int)$o_status['order_status_id']) {
							$order_status = $o_status['name'];
						}					
					}

					$data['transactions_data'][] = [
													'transaction_id' =>$value['order_id'],
											 		'transaction_datetime' => $value['date_added'],
											  		'transaction_sum' => $value['total'],
											   		'currency' => $value['currency_code'],
											   		'transaction_status' => $order_status,
											    	'arrival_country' => $arrival_country,										     	
											     	'arrival_city' => $value['shipping_city'],										     	
											     	'arrival_zip' => $value['shipping_postcode'],										     	
											     	'arrival_address_line' => $region.$value['shipping_address_1'].', '.$value['shipping_address_2'],
											     	'departure_country' => $departure_country,
											     	'departure_city' => $departure_city,
											     	'departure_zip' => $departure_zip,
											     	'departure_address_line' => $departure_address,
											     	'tax_amount' => $tax_amount,
											     	'tax_rate' => $tax_rate,
											      	
											       ];
				}
				
				$data['page_size'] = $query->num_rows;
			}

			$this->response->addHeader('Content-Type: application/json');			
			$output = json_encode($data);
		
		}
	}


	///////////

	public function sippingl(&$route, &$args, &$output ): void {

		$this->load->model('checkout/cart');
		$products = $this->model_checkout_cart->getProducts();
		
		foreach ($products as $keyv => $valuev) {
			$this->db->query("UPDATE `" . DB_PREFIX . "product` SET `shipping` = '1' WHERE `product_id` = '" . $valuev['product_id'] . "'");
		}
		
		
	}
			
 ////////////////




}