<?php
namespace Opencart\Admin\Model\Extension\Lovat\Module;
/**
 * 
 *
 * @package Opencart\Admin\Controller\Extension\Lovat\Module
 */
class Transactions extends \Opencart\System\Engine\Model {
	
	public function setSetting(string $code, $data, int $store_id = 0): void {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '" . (int)$store_id . "' AND `code` = '" . $this->db->escape($code) . "'");
		
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($code) . "', `value` = '" . $this->db->escape($data) . "'");
	}

	public function getSetting(string $code, int $store_id = 0): array {

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '" . (int)$store_id . "' AND `code` = '" . $this->db->escape($code) . "'");	

		//return $query;
		if ($query->num_rows > 0) {
			return json_decode($query->row['value'], true);
		}else{
			return [];
		}
		
	}


}
