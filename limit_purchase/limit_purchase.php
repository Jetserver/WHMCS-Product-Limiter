<?php

if (!defined("WHMCS"))
	die("This file cannot be accessed directly");

function limit_purchase_config() 
{
	return array(
		"name" 		=> "Product Limiter",
		"description" 	=> "This addon allows you to limit the purchase of an products/services for each client",
		"version" 	=> "1.0.5",
		"author" 	=> "Idan Ben-Ezra",
		"language" 	=> "english",
	);
}

function limit_purchase_activate() 
{
   	$sql = "CREATE TABLE IF NOT EXISTS `mod_limit_purchase_config` (
			`name` varchar(255) NOT NULL,
			`value` text NOT NULL,
		PRIMARY KEY (`name`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8";
	$result = mysql_query($sql);

	if($result) 
	{
		$sql = "INSERT INTO mod_limit_purchase_config (`name`,`value`) VALUES
			('localkey', ''),
			('version_check', '0'),
			('version_new', '')";
		$result = mysql_query($sql);
	}
	else
	{
		$error[] = "Can't create the table `mod_limit_purchase_config`. SQL Error: " . mysql_error();
	}

   	$sql = "CREATE TABLE IF NOT EXISTS `mod_limit_purchase` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`product_id` int(11) NOT NULL DEFAULT '0',
			`limit` int(11) NOT NULL DEFAULT '0',
			`error` varchar(255) NOT NULL,
			`active` tinyint(1) NOT NULL DEFAULT '0',
		PRIMARY KEY (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1";
	$result = mysql_query($sql);

	if(!$result) $error[] = "Can't create the table `mod_limit_purchase`. SQL Error: " . mysql_error();

	if(sizeof($error))
	{
		limit_purchase_deactivate();
	}

	return array(
		'status'	=> sizeof($error) ? 'error' : 'success',
		'description'	=> sizeof($error) ? implode(" -> ", $error) : '',
	);
}

function limit_purchase_deactivate() 
{
	$sql = "DROP TABLE IF EXISTS `mod_limit_purchase`";
	$result = mysql_query($sql);

	if(!$result) $error[] = "Can't drop the table `mod_limit_purchase`. SQL Error: " . mysql_error();

	$sql = "DROP TABLE IF EXISTS `mod_limit_purchase_config`";
	$result = mysql_query($sql);

	if(!$result) $error[] = "Can't drop the table `mod_limit_purchase_config`. SQL Error: " . mysql_error();

	return array(
		'status'	=> sizeof($error) ? 'error' : 'success',
		'description'	=> sizeof($error) ? implode(" -> ", $error) : '',
	);
}

function limit_purchase_upgrade($vars) 
{
	if(version_compare($vars['version'], '1.0.1', '<'))
	{
	   	$sql = "CREATE TABLE IF NOT EXISTS `mod_limit_purchase_config` (
				`name` varchar(255) NOT NULL,
				`value` text NOT NULL,
			PRIMARY KEY (`name`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8";
		$result = mysql_query($sql);

		if($result) 
		{
			$sql = "INSERT INTO mod_limit_purchase_config (`name`,`value`) VALUES
				('localkey', ''),
				('version_check', '0'),
				('version_new', '')";
			$result = mysql_query($sql);
		}
	}
}

function limit_purchase_output($vars) 
{
	$modulelink = $vars['modulelink'];
	$version = $vars['version'];

	require_once(dirname(__FILE__) . '/functions.php');

	$lp = new limit_purchase;

	if($lp->config['version_check'] <= (time() - (60 * 60 * 24)))
	{
		$url = "http://clients.jetserver.net/version/limitpurchase.txt";

		$remote_version = file_get_contents($url);
		$remote_version = trim($remote_version);

		if($remote_version)
		{
			$lp->setConfig('version_new', $remote_version);
			$lp->config['version_new'] = $remote_version;
		}

		$lp->setConfig('version_check', time());
	}

	if(version_compare($version, $lp->config['version_new'], '<'))
	{
?>
		<div class="infobox">
			<strong><span class="title"><?php echo $vars['_lang']['newversiontitle']; ?></span></strong><br />
			<?php echo sprintf($vars['_lang']['newversiondesc'], $lp->config['version_new']); ?>
		</div>
<?php
	}

	$ids = $limits = array();

	$action 	= $_REQUEST['action'];
	$product_id 	= intval($_REQUEST['product_id']);
	$id 		= intval($_REQUEST['id']);
	$limit 		= intval($_REQUEST['limit']);
	$error 		= mysql_escape_string($_REQUEST['error']);
	$active 	= intval($_REQUEST['active']);

	$manage_details = array();

	switch($action)
	{
		case 'enable':
		case 'disable': 

			if($id)
			{
				$sql = "SELECT id
					FROM mod_limit_purchase
					WHERE id = '{$id}'";
				$result = mysql_query($sql);
				$limit_details = mysql_fetch_assoc($result);

				if($limit_details)
				{
					$sql = "UPDATE mod_limit_purchase
						SET active = " . ($action == 'disable' ? 0 : 1) . "
						WHERE id = '{$id}'";
					mysql_query($sql);

					$_SESSION['limit_purchase'] = array(
						'type'		=> 'success',
						'message'	=> $vars['_lang']['actionlimit' . ($action == 'disable' ? 'disabled' : 'enabled')],
					);
				}
				else
				{
					$_SESSION['limit_purchase'] = array(
						'type'		=> 'error',
						'message'	=> $vars['_lang']['actionnolimitid'],
					);
				}
			}
			else
			{
				$_SESSION['limit_purchase'] = array(
					'type'		=> 'error',
					'message'	=> $vars['_lang']['actionnolimitprovided'],
				);
			}

			header('Location: ' . $modulelink);
			exit;

		break;

		case 'add':

			if($product_id)
			{
				$sql = "SELECT id
					FROM tblproducts
					WHERE id = '{$product_id}'";
				$result = mysql_query($sql);
				$product_details = mysql_fetch_assoc($result);

				if($product_details)
				{
					$sql = "SELECT id
						FROM mod_limit_purchase
						WHERE product_id = '{$product_id}'";
					$result = mysql_query($sql);
					$limit_details = mysql_fetch_assoc($result);

					if(!$limit_details)
					{
						if($limit > 0 && $error)
						{
							$sql = "INSERT INTO mod_limit_purchase (`product_id`,`limit`,`error`,`active`) VALUES
								('{$product_id}','{$limit}','{$error}','" . ($active ? 1 : 0) . "')";
							mysql_query($sql);

							$_SESSION['limit_purchase'] = array(
								'type'		=> 'success',
								'message'	=> $vars['_lang']['actionadded'],
							);
						}
						else
						{
							$errors = array();

							if(!$limit) $errors[] = '&bull; ' . $vars['_lang']['limit'];
							if(!$error) $errors[] = '&bull; ' . $vars['_lang']['errormessage'];

							$_SESSION['limit_purchase'] = array(
								'type'		=> 'error',
								'message'	=> $vars['_lang']['actionfieldsreq'] . '<br />' . implode("<br />", $errors),
							);
						}
					}
					else
					{
						$_SESSION['limit_purchase'] = array(
							'type'		=> 'error',
							'message'	=> $vars['_lang']['actionlimitexists'],
						);
					}
				}
				else
				{
					$_SESSION['limit_purchase'] = array(
						'type'		=> 'error',
						'message'	=> $vars['_lang']['actionnoproductid'],
					);
				}
			}
			else
			{
				$_SESSION['limit_purchase'] = array(
					'type'		=> 'error',
					'message'	=> $vars['_lang']['actionselectproduct'],
				);
			}

			header('Location: ' . $modulelink);
			exit;
		break;

		case 'edit':

			if($id)
			{
				$sql = "SELECT id
					FROM mod_limit_purchase
					WHERE id = '{$id}'";
				$result = mysql_query($sql);
				$limit_details = mysql_fetch_assoc($result);

				if($limit_details)
				{
					if($product_id)
					{
						$sql = "SELECT id
							FROM tblproducts
							WHERE id = '{$product_id}'";
						$result = mysql_query($sql);
						$product_details = mysql_fetch_assoc($result);

						if($product_details)
						{
							if($limit > 0 && $error)
							{
								$sql = "UPDATE mod_limit_purchase 
									SET `product_id` = '{$product_id}', `limit` = '{$limit}', `error` = '{$error}', active = '" . ($active ? 1 : 0) . "'
									WHERE id = '{$id}'";
								mysql_query($sql);

								$_SESSION['limit_purchase'] = array(
									'type'		=> 'success',
									'message'	=> $vars['_lang']['actionlimitedited'],
								);
							}
							else
							{
								$errors = array();

								if(!$limit) $errors[] = '&bull; ' . $vars['_lang']['limit'];
								if(!$error) $errors[] = '&bull; ' . $vars['_lang']['errormessage'];

								$_SESSION['limit_purchase'] = array(
									'type'		=> 'error',
									'message'	=> $vars['_lang']['actionfieldsreq'] . '<br />' . implode("<br />", $errors),
								);
							}
						}
						else
						{
							$_SESSION['limit_purchase'] = array(
								'type'		=> 'error',
								'message'	=> $vars['_lang']['actionnoproductid'],
							);
						}
					}
					else
					{
						$_SESSION['limit_purchase'] = array(
							'type'		=> 'error',
							'message'	=> $vars['_lang']['actionselectproduct'],
						);
					}
				}
				else
				{
					$_SESSION['limit_purchase'] = array(
						'type'		=> 'error',
						'message'	=> $vars['_lang']['actionnolimitid'],
					);
				}
			}
			else
			{
				$_SESSION['limit_purchase'] = array(
					'type'		=> 'error',
					'message'	=> $vars['_lang']['actionnolimitprovided'],
				);
			}

			header('Location: ' . $modulelink);
			exit;
		break;

		case 'delete':

			if($id)
			{
				$sql = "SELECT id
					FROM mod_limit_purchase
					WHERE id = '{$id}'";
				$result = mysql_query($sql);
				$limit_details = mysql_fetch_assoc($result);

				if($limit_details)
				{
					$sql = "DELETE
						FROM mod_limit_purchase
						WHERE id = '{$id}'";
					mysql_query($sql);

					$_SESSION['limit_purchase'] = array(
						'type'		=> 'success',
						'message'	=> $vars['_lang']['actionlimitdeleted'],
					);
				}
				else
				{
					$_SESSION['limit_purchase'] = array(
						'type'		=> 'error',
						'message'	=> $vars['_lang']['actionnolimitid'],
					);
				}
			}
			else
			{
				$_SESSION['limit_purchase'] = array(
					'type'		=> 'error',
					'message'	=> $vars['_lang']['actionnolimitprovided'],
				);
			}

			header('Location: ' . $modulelink);
			exit;
		break;

		case 'manage':

			if($id)
			{
				$sql = "SELECT id
					FROM mod_limit_purchase
					WHERE id = '{$id}'";
				$result = mysql_query($sql);
				$limit_details = mysql_fetch_assoc($result);

				if($limit_details)
				{
					$sql = "SELECT *
						FROM mod_limit_purchase
						WHERE id = '{$id}'";
					$result = mysql_query($sql);
					$manage_details = mysql_fetch_assoc($result);
				}
				else
				{
					$_SESSION['limit_purchase'] = array(
						'type'		=> 'error',
						'message'	=> $vars['_lang']['actionnolimitid'],
					);
				}
			}
			else
			{
				$_SESSION['limit_purchase'] = array(
					'type'		=> 'error',
					'message'	=> $vars['_lang']['actionnolimitprovided'],
				);
			}

			if(isset($_SESSION['limit_purchase']))
			{
				header('Location: ' . $modulelink);
				exit;
			}
		break;
	}

	$sql = "SELECT *
		FROM mod_limit_purchase";
	$result = mysql_query($sql);

	while($row = mysql_fetch_assoc($result))
	{
		if($manage_details['product_id'] != $row['product_id'])
		{
			$sql = "SELECT name
				FROM tblproducts
				WHERE id = '{$row['product_id']}'";
			$result2 = mysql_query($sql);
			$product = mysql_fetch_assoc($result2);

			$ids[] = $row['product_id'];
			$limits[] = array_merge($row, array('product_details' => $product));
		}
	}
	mysql_free_result($result);

	if(isset($_SESSION['limit_purchase']))
	{
?>
		<div class="<?php echo $_SESSION['limit_purchase']['type']; ?>box">
			<strong><span class="title"><?php echo $vars['_lang']['info']; ?></span></strong><br />
			<?php echo $_SESSION['limit_purchase']['message']; ?>
		</div>
<?php
		unset($_SESSION['limit_purchase']);
	}

	$products = array();

	$sql = "SELECT id, name
		FROM tblproducts
		" . (sizeof($ids) ? "WHERE id NOT IN('" . implode("','", $ids) . "')" : '');
	$result = mysql_query($sql);

	while($product_details = mysql_fetch_assoc($result))
	{
		$products[] = $product_details;
	}
	mysql_free_result($result);

?>
	<h2><?php echo (sizeof($manage_details) ? $vars['_lang']['editlimit'] : $vars['_lang']['addlimit']); ?></h2>
	<form action="<?php echo $modulelink; ?>&amp;action=<?php echo (sizeof($manage_details) ? 'edit&amp;id=' . $manage_details['id'] : 'add'); ?>" method="post">

	<table width="100%" cellspacing="2" cellpadding="3" border="0" class="form">
	<tbody>
	<tr>
		<td width="15%" class="fieldlabel"><?php echo $vars['_lang']['product']; ?></td>
		<td class="fieldarea">
			<select name="product_id" class="form-control select-inline">
				<?php if(!sizeof($manage_details)) { ?>
				<option selected="selected" value="0"><?php echo $vars['_lang']['selectproduct']; ?></option>
				<?php } ?>
				<?php foreach($products as $product_details) { ?>
				<option<?php if($manage_details['product_id'] == $product_details['id']) { ?> selected="selected"<?php } ?> value="<?php echo $product_details['id']; ?>"><?php echo $product_details['name']; ?></option>
				<?php } ?>
			</select>
		</td>
	</tr>
	<tr>
		<td class="fieldlabel"><?php echo $vars['_lang']['limit']; ?></td>
		<td class="fieldarea"><input type="text" value="<?php echo $manage_details['limit']; ?>" size="5" name="limit" /> <?php echo $vars['_lang']['limitdesc']; ?></td>
	</tr>
	<tr>
		<td class="fieldlabel"><?php echo $vars['_lang']['errormessage']; ?></td>
		<td class="fieldarea"><input type="text" value="<?php echo $manage_details['error']; ?>" size="65" name="error" /><br /><?php echo $vars['_lang']['errormessagedesc']; ?></td>
	</tr>
	<tr>
		<td class="fieldlabel"><?php echo $vars['_lang']['active']; ?></td>
		<td class="fieldarea">
			<input type="radio" <?php if($manage_details['active']) { ?>checked="checked" <?php } ?>value="1" name="active" /> <?php echo $vars['_lang']['yes']; ?>
			<input type="radio" <?php if(!$manage_details['active']) { ?>checked="checked" <?php } ?>value="0" name="active" /> <?php echo $vars['_lang']['no']; ?>
		</td>
	</tr>
	</tbody>
	</table>

	<p align="center">
		<input type="submit" class="btn btn-primary" value="<?php echo (sizeof($manage_details) ? $vars['_lang']['save'] : $vars['_lang']['createlimitation']); ?>" />
		<?php if(sizeof($manage_details)) { ?>
			<a href="<?php echo $modulelink; ?>" class="btn btn-default"><?php echo $vars['_lang']['cancel']; ?></a>
		<?php } ?>
	</p>
	</form>

	<?php if(!sizeof($manage_details)) { ?>

	<div class="tablebg">

		<table width="100%" cellspacing="1" cellpadding="3" border="0" class="datatable">
		<tbody>
		<tr>
			<th><?php echo $vars['_lang']['product']; ?></th>
			<th><?php echo $vars['_lang']['limit']; ?></th>
			<th><?php echo $vars['_lang']['errormessage']; ?></th>
			<th width="20"></th>
			<th width="20"></th>
			<th width="20"></th>
		</tr>
		<?php foreach($limits as $limit_details) { ?>
		<tr>
			<td><?php echo $limit_details['product_details']['name']; ?></td>
			<td style="text-align: center;"><?php echo $limit_details['limit']; ?></td>
			<td><?php echo str_replace('{PNAME}', $limit_details['product_details']['name'], $limit_details['error']); ?></td>
			<td><a href="<?php echo $modulelink; ?>&amp;action=<?php echo ($row['active'] ? 'disable' : 'enable'); ?>&amp;id=<?php echo $limit_details['id']; ?>"><img src="images/icons/<?php echo ($limit_details['active'] ? 'tick.png' : 'disabled.png'); ?>" /></a></td>
			<td><a href="<?php echo $modulelink; ?>&amp;action=manage&amp;id=<?php echo $limit_details['id']; ?>"><img border="0" src="images/edit.gif" /></a></td>
			<td><a href="<?php echo $modulelink; ?>&amp;action=delete&amp;id=<?php echo $limit_details['id']; ?>"><img width="16" height="16" border="0" alt="Delete" src="images/delete.gif" /></a></td>
		</tr>
		<?php } ?>
		</tbody>
		</table>
	</div>

	<?php } ?>
<?php

}

?>