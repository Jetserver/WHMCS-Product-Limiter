<?php

if (!defined("WHMCS"))
	die("This file cannot be accessed directly");

require_once(dirname(__FILE__) . '/functions.php');

function limit_purchase($vars)
{
	$errors = array();

	$lp = new limit_purchase;

	$pids = $lp->getLimitedProducts();
	$user_id = intval($_SESSION['uid']);

	if(sizeof($_SESSION['cart']['products']))
	{
		$counter = $delete = array();

		foreach($_SESSION['cart']['products'] as $i => $product_details)
		{
			if(in_array($product_details['pid'], array_keys($pids)))
			{
				if(!isset($counter[$product_details['pid']]))
				{
					$counter[$product_details['pid']] = 0;

					if($user_id)
					{
						$sql = "SELECT COUNT(id) as total_products
							FROM tblhosting
							WHERE userid = '{$user_id}'
							AND packageid = '{$product_details['pid']}'";
						$result = mysql_query($sql);
						$product = mysql_fetch_assoc($result);

						$counter[$product_details['pid']] = intval($product['total_products']);
					}
				}

				if($pids[$product_details['pid']]['limit'] <= intval($counter[$product_details['pid']]))
				{
					if(!isset($delete[$product_details['pid']]))
					{
						$sql = "SELECT name
							FROM tblproducts
							WHERE id = '{$product_details['pid']}'";
						$result = mysql_query($sql);
						$delete[$product_details['pid']] = mysql_fetch_assoc($result);
					}

					// if you want to automatically delete the unwanted products from the cart, remark the line below
					//unset($_SESSION['cart']['products'][$i]);
				}

				$counter[$product_details['pid']]++;
			}
		}

		foreach($delete as $product_id => $product_details)
		{
			$errors[] = str_replace('{PNAME}', $product_details['name'], $pids[$product_id]['error']);
		}
	}

	return $errors;
}

function limit_purchase_delete($vars)
{
	$sql = "DELETE
		FROM mod_limit_purchase
		WHERE product_id = '{$vars['pid']}'";
	mysql_query($sql);
}

add_hook('ShoppingCartValidateCheckout', 0, 'limit_purchase');
add_hook('ProductDelete', 0, 'limit_purchase_delete');

?>