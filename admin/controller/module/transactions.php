<?php
namespace Opencart\Admin\Controller\Extension\Lovat\Module;
/**
 * Class Transactions
 *
 * @package Opencart\Admin\Controller\Extension\Lovat\Module
 */
class Transactions extends \Opencart\System\Engine\Controller {
	/**
	 * @return void
	 */
	
	private $module = 'module_lovat';
	private $description = 'Lovat tax';
	private $event = 'extension/lovat/event/transactions';
	private $path = 'extension/lovat/module/transactions';
	private $routes = array(
			'checkout'
		);

	public function index(): void {
		$this->load->language('extension/lovat/module/transactions');

		$this->document->setTitle($this->language->get('heading_title'));

		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/lovat/module/transactions', 'user_token=' . $this->session->data['user_token'])
		];

		$data['order_statuses'] = [];
		$this->load->model('localisation/order_status');
		$results = $this->model_localisation_order_status->getOrderStatuses();
		foreach ($results as $result) {
			$data['order_statuses'][] = [
				'order_status_id' => $result['order_status_id'],
				'name'            => $result['name'] . (($result['order_status_id'] == $this->config->get('config_order_status_id')) ? $this->language->get('text_default') : ''),
				
			];
		}

		$this->load->model('extension/lovat/module/transactions');

		$results = $this->model_extension_lovat_module_transactions->getSetting('module_transactions');
		
		// $data['url'] = 'https://dev.vatcompliance.co/api/1/tax_rate/';
		$data['url'] = 'https://merchant.vatcompliance.co/api/1/tax_rate/';
		$data['access_token'] = '';
		$data['order_statuses_id'] = [5,11];
		$data['module_transactions_status'] = 0;
		$data['status'] = 0;
		/*if (isset($results['url'])) {
			$data['url'] = $results['url'];
		}*/
		if (isset($results['access_token'])) {
			$data['access_token'] = $results['access_token'];
		}
		if (isset($results['order_statuses_id'])) {
			$data['order_statuses_id'] = $results['order_statuses_id'];
		}
		if (isset($results['module_transactions_status'])) {
			$data['module_transactions_status'] = $results['module_transactions_status'];
		}
		if (isset($results['status'])) {
			$data['status'] = $results['status'];
		} 

		$data['save'] = $this->url->link('extension/lovat/module/transactions.save', 'user_token=' . $this->session->data['user_token']);
		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/lovat/module/transactions', $data));
	}

	/**
	 * @return void
	 */
	public function save(): void {
		$this->load->language('extension/lovat/module/transactions');

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/lovat/module/transactions')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {
			$this->init();
			$this->load->model('extension/lovat/module/transactions');

			$this->model_extension_lovat_module_transactions->setSetting('module_transactions', json_encode($this->request->post));


			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function init(): void {
		
		$x = (VERSION >= '4.0.2') ? '.' : '|';
		$this->load->model('setting/event');
		$this->load->model('user/user_group');
		$groups = $this->model_user_user_group->getUserGroups();

		$this->model_setting_event->deleteEventByCode($this->module.'_checkout_confirm_confirm');
		$this->model_setting_event->addEvent([
			'code'			=> $this->module.'_checkout_confirm_confirm', 
			'description'	=> 'Lovat tax Event for checkout_confirm_confirm',
			'trigger'		=> 'catalog/controller/checkout/confirm.confirm/before',
			'action'		=> 'extension/lovat/event/transactions.init',
			'status'		=> true,
			'sort_order'	=> 1
		]);   
		foreach($groups as $group) {
			$this->model_user_user_group->addPermission($group['user_group_id'], 'access', 'extension/lovat/event/transactions.init');
		}


		$this->model_setting_event->deleteEventByCode($this->module.'_checkout_success');
		$this->model_setting_event->addEvent([
			'code'			=> $this->module.'_checkout_success', 
			'description'	=> 'Lovat tax Event for _checkout_success',
			'trigger'		=> 'catalog/controller/checkout/success/before',
			'action'		=> 'extension/lovat/event/transactions.success',
			'status'		=> true,
			'sort_order'	=> 1
		]);   
		foreach($groups as $group) {
			$this->model_user_user_group->addPermission($group['user_group_id'], 'access', 'extension/lovat/event/transactions.success');
		}

		$this->model_setting_event->deleteEventByCode($this->module.'_information_sitemap');
		$this->model_setting_event->addEvent([
			'code'			=> $this->module.'_information_sitemap', 
			'description'	=> 'Lovat tax Event for _information_sitemap',
			'trigger'		=> 'catalog/view/information/sitemap/after',
			'action'		=> 'extension/lovat/event/transactions.tran',
			'status'		=> true,
			'sort_order'	=> 1
		]);   
		foreach($groups as $group) {
			$this->model_user_user_group->addPermission($group['user_group_id'], 'access', 'extension/lovat/event/transactions.tran');
		}

		$this->model_setting_event->deleteEventByCode($this->module.'_checkout_cart_add');
		$this->model_setting_event->addEvent([
			'code'			=> $this->module.'_checkout_cart_add', 
			'description'	=> 'Lovat tax Event for _checkout_cart_add',
			'trigger'		=> 'catalog/controller/checkout/cart.add/after',
			'action'		=> 'extension/lovat/event/transactions.sippingl',
			'status'		=> true,
			'sort_order'	=> 1
		]);   
		foreach($groups as $group) {
			$this->model_user_user_group->addPermission($group['user_group_id'], 'access', 'extension/lovat/event/transactions.sippingl');
		}

		$this->model_setting_event->deleteEventByCode($this->module.'checkout_checkout');
		$this->model_setting_event->addEvent([
			'code'			=> $this->module.'checkout_checkout', 
			'description'	=> 'Lovat tax Event for checkout_checkout',
			'trigger'		=> 'catalog/controller/checkout/checkout/after',
			'action'		=> 'extension/lovat/event/transactions.init',
			'status'		=> true,
			'sort_order'	=> 1
		]);   
		foreach($groups as $group) {
			$this->model_user_user_group->addPermission($group['user_group_id'], 'access', 'extension/lovat/event/transactions.init');
		}


		$this->db->query("DELETE FROM `" . DB_PREFIX . "location` WHERE `name` = 'lovat'");	

		$this->db->query("INSERT INTO `" . DB_PREFIX . "location` SET `name` = 'lovat', `address` = '" . $this->db->escape('country: GBR | city: London | zip: 1232 | address: Peckham Road') . "', `telephone` = '123456789', `geocode` = '', `image` = '', `open` = '', `comment` = ''");

		$this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '1'  WHERE `key` = 'config_checkout_payment_address' AND `code` = 'config'");
		$this->db->query("UPDATE `" . DB_PREFIX . "setting` SET `value` = '1'  WHERE `key` = 'config_checkout_shipping_address' AND `code` = 'config'");
       
	}

	public function install(): void {
		if ($this->user->hasPermission('modify', $this->path)) {
			$this->init();
		}
	}

	public function uninstall(): void {
		if ($this->user->hasPermission('modify', $this->path)) {
			$this->load->model('setting/event');
			
			$this->model_setting_event->deleteEventByCode($this->module.'_checkout_confirm_confirm');
			$this->model_setting_event->deleteEventByCode($this->module.'_checkout_success');
			$this->model_setting_event->deleteEventByCode($this->module.'_information_sitemap');
			$this->model_setting_event->deleteEventByCode($this->module.'_checkout_cart_add');
			$this->model_setting_event->deleteEventByCode($this->module.'_checkout_checkout');
			
		}
	}
}