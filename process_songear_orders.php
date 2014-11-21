<?php
require('includes/application_top.php');
?>


<?php
$hasHeaderRow = true;
$startRow = ($hasHeaderRow)? 1 : 0;
$newOrderStatus = 10;
$orderCount = 0;
$csvfile = null;
$accountNumber = null;
$accountPriceLvl = null;
$arrOrderIds = array();
$output = "";
$ignoreFile = false; //If for some reason there is a file that can't be deleted, just ignore it.

$ord_shipping_sql = "SELECT * FROM shipping WHERE 1 ORDER BY shipping_name";
$ord_shipping_query = my_db_query($ord_shipping_sql);
while($ord_shipping = my_db_fetch_array($ord_shipping_query)){
    $arrShipping[$ord_shipping['shipping_alias']] = $ord_shipping['shipping_id'];
    $arrShippingName[$ord_shipping['shipping_alias']] = $ord_shipping['shipping_name'];
}

$ord_countries_sql = "SELECT countries_id, countries_name, countries_iso_code_3, countries_number FROM countries order by countries_name";
$ord_countries_query = my_db_query($ord_countries_sql);
while($ord_countries = my_db_fetch_array($ord_countries_query)){
    $arrCountries[strtoupper($ord_countries['countries_name'])] = $ord_countries['countries_number'];
}

$arrFees = array();
$fees_sql = "SELECT * FROM fees ";
$fees_query = my_db_query($fees_sql);
while($fees = my_db_fetch_array($fees_query)){
    $arrFees[$fees['fees_name']]= $fees['fees_value'];
}

$arrRepCodes = array();
$rep_codes_sql = "SELECT * FROM rep_codes";
$rep_codes_query = my_db_query($rep_codes_sql);
while( $rep_codes = my_db_fetch_array($rep_codes_query) ){
    $arrRepCodes[$rep_codes['rep_name']] = $rep_codes['rep_code'];
}

$client_folders_sql = "SELECT * FROM accounts WHERE accounts_username in ('songear')";
$client_folders_query = my_db_query($client_folders_sql);

while($client_folders = my_db_fetch_array($client_folders_query)){
	$d = 'orderpull/' . $client_folders['accounts_folder_name'];
	$accountNumber = $client_folders['accounts_number'];
	$clientPrefix = $client_folders['accounts_prefix'];
	$accountPriceLvl = $client_folders['accounts_price_level'];

	$arrReps = array();
	$reps_sql = "SELECT * FROM reps r WHERE r.accounts_number = " . $accountNumber;
	$reps_query = my_db_query($reps_sql);
	$reps = my_db_fetch_array($reps_query);

	foreach(array_diff(scandir($d), array('.','..')) as $f) {
		if(is_file($d.'/'.$f)) {
			$csvfile = $d.'/'.$f;
			$arrLines = file($d.'/'.$f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
			$output .= "Processing file: " . $d.$f . "   at   ". date("y-m-d h:i:s") ."<br>";
			$output .= "CSV File has ".count($arrLines)." rows (including the header row)";

			for($i = $startRow; $i < count($arrLines); $i++) {
				$arrLine = explode(",", $arrLines[$i]);

				$ord_add_sql = "";
				$ord_product_add_sql = "";
				$product_size_adjusted = ( strlen(str_replace('"','',$arrLine[13])) > 0 ) ? str_replace('"','',$arrLine[13]) : "NA";

				$csv = array(
				'customer_name'	=> str_replace('"','',$arrLine[4]),
				'customer_address1'	=> str_replace('"','',$arrLine[5]),
				'customer_address2'	=> str_replace('"','',$arrLine[6]),
				'customer_intl_phone' => '',
				'customer_city'	=> str_replace('"','',$arrLine[7]),
				'customer_state'	=> str_replace('"','',$arrLine[8]),
				'customer_zip' => str_replace('"','',$arrLine[9]),
				'customer_country' => str_replace('"','',$arrLine[10]),
				'customer_country_number' => $arrCountries[strtoupper(str_replace('"','',$arrLine[10]))],
				'customer_shipping_method' => $arrShippingName[str_replace('"','',$arrLine[17])],
				'customer_shipping_id' => $arrShipping[str_replace('"','',$arrLine[17])],
				'customer_invoice_number' => str_replace('"','',$arrLine[1]),
				'purchase_date'	=>  date("y-m-d h:i:s"),
				'accounts_number' => $accountNumber,
				'purchase_order_number'	=> strtoupper($clientPrefix).date("mdyHis"),
				'order_comments' => '',
				'order_status' => $newOrderStatus,
				'dropship_fee' => str_replace('"','',$arrFees['Drop Ship']),
				'handling_fee' => str_replace('"','',$arrFees['Handling']),
				'isRush' => number_format(0, 2),
				'rush_fee'	=> number_format(0, 2),
				'misc_desc'	=> '',
				'rep1_name'	=> $reps['field_rep'],
				'rep1_code'	=> $arrRepCodes[$reps['field_rep']],
				'rep2_name'	=> $reps['inside_rep'],
				'rep2_code'	=> $arrRepCodes[$reps['inside_rep']],
				'rep3_name'	=> $reps['field_group'],
				'rep3_code'	=> $arrRepCodes[$reps['field_group']],
				'rep4_name'	=> $reps['national_group'],
				'rep4_code'	=> $arrRepCodes[$reps['national_group']],
				'rep5_name'	=> $reps['national_rep'],
				'rep5_code'	=> $arrRepCodes[$reps['national_rep']],
				'rep6_name'	=> $reps['sales_mgr'],
				'rep6_code'	=> $arrRepCodes[$reps['sales_mgr']],
				'product_model' => str_replace('"','',$arrLine[12]),
				'design_description' =>  str_replace('"','',$arrLine[11]),
				'product_name' => '[ ' . str_replace('"','',$arrLine[12]) . ' ] ' .  str_replace('"','',$arrLine[11]),
				'product_category' => substr(str_replace('"','',$arrLine[12]), 0, 3),
				'product_size' => substr(str_replace('"','',$arrLine[12]), 0, 3) . ' - ' . $product_size_adjusted,
				'product_quantity' => str_replace('"','',$arrLine[14])
				);

				$check_for_order_sql = sprintf("SELECT * FROM orders WHERE accounts_number = '%s' AND customer_invoice_number = '%s' ", 
										mysql_real_escape_string($csv['accounts_number']), 
										mysql_real_escape_string($csv['customer_invoice_number']) );
				$check_for_order_query = my_db_query($check_for_order_sql);
				$orderInfo = my_db_fetch_array($check_for_order_query);
				$ord_add_insert_id = $orderInfo['order_id'];

				//If orderId is found before the order is initally entered then we have a rogue
				// order file, so ignore it;
				if ($i == $startRow && isset($orderInfo['order_id'])) {
					$ignoreFile = true;
					echo "******  FOUND ROGUE ORDER FILE *******<BR>";
					echo "******  MANUALLY REMOVE FILE: " . $csvfile;
				}

				if (!$ignoreFile && !isset($orderInfo['order_id'])) {

					$ord_add_sql = sprintf("INSERT INTO orders (
						customer_name, customer_address1, customer_address2, customer_intl_phone, customer_city, 
						customer_state, customer_zip, customer_country, customer_country_number, customer_shipping_method, 
						customer_shipping_id, customer_invoice_number, purchase_date, accounts_number, purchase_order_number, 
						order_comments, order_status, dropship_fee, handling_fee, isRush, 
						rush_fee, misc_desc, rep1_name, rep1_code, rep2_name, 
						rep2_code, rep3_name, rep3_code, rep4_name, rep4_code, 
						rep5_name, rep5_code, rep6_name, rep6_code
						) VALUES (
		          		'%s', '%s', '%s', '%s', '%s', 
		          		'%s', '%s', '%s', %d, '%s', 
		          		'%s', '%s', '%s', '%s', '%s', 
		          		'%s', '%s', %01.2f, %01.2f, %d, 
		          		%01.2f, '%s', '%s', '%s', '%s', 
		          		'%s', '%s', '%s', '%s', '%s', 
		          		'%s', '%s', '%s', '%s' )", 

						mysql_real_escape_string($csv['customer_name']),
						mysql_real_escape_string($csv['customer_address1']),
						mysql_real_escape_string($csv['customer_address2']),
						mysql_real_escape_string($csv['customer_intl_phone']),
						mysql_real_escape_string($csv['customer_city']),

						mysql_real_escape_string($csv['customer_state']),
						mysql_real_escape_string($csv['customer_zip']),
						mysql_real_escape_string($csv['customer_country']),
						intval($csv['customer_country_number']),
						mysql_real_escape_string($csv['customer_shipping_method']),

						mysql_real_escape_string($csv['customer_shipping_id']),
						mysql_real_escape_string($csv['customer_invoice_number']),
						mysql_real_escape_string($csv['purchase_date']),
						mysql_real_escape_string($csv['accounts_number']),
						mysql_real_escape_string($csv['purchase_order_number']),

						mysql_real_escape_string($csv['order_comments']),
						mysql_real_escape_string($csv['order_status']),
						floatval($csv['dropship_fee']),
						floatval($csv['handling_fee']),
						intval($csv['isRush']),

						floatval($csv['rush_fee']),
						mysql_real_escape_string($csv['misc_desc']),
						mysql_real_escape_string($csv['rep1_name']),
						mysql_real_escape_string($csv['rep1_code']),
						mysql_real_escape_string($csv['rep2_name']),

						mysql_real_escape_string($csv['rep2_code']),
						mysql_real_escape_string($csv['rep3_name']),
						mysql_real_escape_string($csv['rep3_code']),
						mysql_real_escape_string($csv['rep4_name']),
						mysql_real_escape_string($csv['rep4_code']),

						mysql_real_escape_string($csv['rep5_name']),
						mysql_real_escape_string($csv['rep5_code']),
						mysql_real_escape_string($csv['rep6_name']),
						mysql_real_escape_string($csv['rep6_code']) );

					$temp = my_db_query($ord_add_sql);

					$ord_add_insert_id = my_db_insert_id();
					$arrOrderIds[] = $ord_add_insert_id;
					$orderCount++;
					$output .= "<br><br>" .mysql_real_escape_string($csv['customer_name']) . "   [".mysql_real_escape_string($csv['customer_invoice_number'])."]<br>";
				}


				if (!$ignoreFile && isset($ord_add_insert_id)) {
					$arrPriceRequest = array();
					$arrPriceRequest["price"] = getPriceBySize($accountNumber, $csv['product_model'], $product_size_adjusted);
					$arrPriceRequest["product_size"] = $product_size_adjusted;
					$arrPriceRequest["productModel"] = $csv['product_model'];
					$arrPriceRequest["priceLvl"] = $accountPriceLvl;
					$arrPriceRequest["accounts_number"] = $accountNumber; 
					$arrPriceRequest["discount"] = checkForDiscount($arrPriceRequest);

					if ( isset($arrPriceRequest["discount"]) && strlen($arrPriceRequest["discount"]) > 0) {
						$productPrice = $arrPriceRequest["discount"];
					} else {
						$productPrice = $arrPriceRequest["price"];
					} 

					$ord_product_add_sql = sprintf("INSERT INTO orders_products (
						order_id, order_product_quantity, order_product_size, 
						order_product_name, order_product_model, order_product_charge
						) VALUES (%d, %d, '%s', '%s', '%s', %01.2f)", 
						$ord_add_insert_id,
						intval($csv['product_quantity']),
						mysql_real_escape_string($csv['product_size']),
						mysql_real_escape_string($csv['product_name']),
						mysql_real_escape_string($csv['product_model']),
						number_format($productPrice, 2)
					);

					if ($orderInfo['order_comments']  == null ) {
						$orderInfo['order_comments']  = "FTP Order";
					}
					my_db_query($ord_product_add_sql);
					$productDescription = $orderInfo['order_comments'] . "<br>" . $csv['product_quantity'] . ' X ' . 'Product Model: ' .$csv['product_model'] . ' / Size: ' . $csv['product_size'];
					
					$order_update_comments = "UPDATE orders set order_comments='".$productDescription."' where order_id=".$ord_add_insert_id;
					my_db_query($order_update_comments);

					$output .= intval($csv['product_quantity'])." X ".mysql_real_escape_string($csv['product_size']) ." / ". mysql_real_escape_string($csv['product_model']). "<br>";
				}

			}			
		}
	}

	if (!$ignoreFile && $orderCount > 0) {
		$output .= "<br><br>" . $orderCount . " UNIQUE ORDERS PROCESSED.";
		chmod($csvfile, 0777);
		
		
		for($i = 0; $i < count($arrOrderIds); $i++) {
			my_mail_order($arrOrderIds[$i], $accountNumber);
		}

		if (unlink ($csvfile)) {
			$output .= "<br> Attempt to deleted the CSV file was successful";
		} else {
			$output .= "<br> Attempt to deleted the CSV file has failed";
		}

	    $headers  = 'MIME-Version: 1.0' . "\r\n";
	    $headers .= 'From: Kerusso Drop Shipping <kds@kerusso.com>' . "\r\n";
		mail("haciendadad@yahoo.com","FTP Order Processed",$output,$headers);
	}


}

?>
