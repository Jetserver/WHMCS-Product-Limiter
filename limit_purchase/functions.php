<?php

if (!defined("WHMCS"))
	die("This file cannot be accessed directly");

class limit_purchase
{
	var $config;

	function __construct()
	{
		$this->loadConfig();
	}

	function loadConfig()
	{
		$this->config = array();

		$sql = "SELECT *
			FROM mod_limit_purchase_config";
		$result = mysql_query($sql);

		while($config_details = mysql_fetch_assoc($result))
		{
			$this->config[$config_details['name']] = $config_details['value'];
		}
		mysql_free_result($result);
	}

	function setConfig($name, $value)
	{
		if(isset($this->config[$name]))
		{
			$sql = "UPDATE mod_limit_purchase_config
				SET value = '" . mysql_escape_string($value) . "'
				WHERE name = '" . mysql_escape_string($name) . "'";
			$result = mysql_query($sql);
		}
		else
		{
			$sql = "INSERT INTO mod_limit_purchase_config (`name`,`value`) VALUES
				('" . mysql_escape_string($name) . "','" . mysql_escape_string($value) . "')";
			$result = mysql_query($sql);
		}

		$this->config[$name] = $value;
	}

	function getLimitedProducts()
	{
		$output = array();

		$sql = "SELECT l.*
			FROM mod_limit_purchase as l
			INNER JOIN tblproducts as p
			ON p.id = l.product_id
			WHERE l.active = 1";
		$result = mysql_query($sql);

		while($limits = mysql_fetch_assoc($result))
		{
			$output[$limits['product_id']] = array('limit' => $limits['limit'], 'error' => $limits['error']);
		}
		mysql_free_result($result);

		return $output;
	}
}

?>