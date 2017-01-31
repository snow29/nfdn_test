<?php

################################################################
# This software is the unpublished, confidential, proprietary, 
# intellectual property of zipperSNAP, LLC and may not be copied,
# duplicated, retransmitted or used in any manner without
# expressed written consent from zipperSNAP, LLC.
# Copyright 2009 - Present, zipperSNAP, LLC.
################################################################

$gProgramCode = "CHECKOUTPROCESS";
include_once "utilities.inc";
include_once "class.ecommerce_c.php";
function trackLog($message)
{
   $fp = fopen('/var/www/logs/transactionLog-'.date('Y-m-d').'.log', 'a');
   fwrite($fp, date('Y-m-d h:i:s a',time()).' '.$message."\r\n"); 
   fclose($fp);
}
trackLog("---------------------------------Order tracking started------------------------------------------");
trackLog("UserAgent".print_r($_SERVER['HTTP_USER_AGENT'],true));
trackLog("Remote Address".print_r($_SERVER['REMOTE_ADDR'],true));
trackLog(print_r($_POST,true));
$returnArray = array();
$ip_block = "66.228.134.162";

$subscriber= $_POST['subscribe'];

$cc_name = $_POST['cc_name'];
$cc_address = $_POST['cc_address'].", ".$_POST['cc_city'].", ".$_POST['cc_state'].",". $_POST['cc_zip'].", ".$_POST['cc_country']; 
$cc_phone = $_POST['phone_number'];

$cartTotal = $_POST['tax_charge']+$_POST['shipping_charge']+$_POST['additional_charge']+$_POST['subtotal']+$_POST['insurance_charge'];
$consumerInfo = $_POST['email_address'];
$shoppingCartId = $_SESSION["shopping_cart_id"];
$dealerId = getFieldFromId('dealer_id','shopping_carts','shopping_cart_id',$shoppingCartId);

$nameArray = explode(" ",trim($_POST['cc_name']));
$lastName = $nameArray[count($nameArray)-1];
$firstName = trim(str_replace($lastName,"",$_POST['cc_name']));

$addressArray = $_POST['cc_address'];
$address = substr($addressArray, 0, 30);

if (strpos($_SERVER['DOCUMENT_ROOT'],"sandbox") !== false ) {
	$returnArray['status'] = "error";
	$returnArray['message'] = "Sandbox checkout is disabled.";
        sendAlertToDealer('000',$dealerId,$consumerInfo,$cc_name,$cc_address,$cc_phone);
	echo json_encode($returnArray);
	exit;
}

if (count($_POST) < 10 || empty($_POST['order_total'])) {
	$returnArray['status'] = "error";
	$returnArray['message'] = "Cart error [001] - please try again.";
        sendAlertToDealer('001',$dealerId,$consumerInfo,$cc_name,$cc_address,$cc_phone);
	echo json_encode($returnArray);
	exit;
}


if ($_SERVER['REMOTE_ADDR'] == $ip_block) {
	$returnArray['status'] = "error";
	$returnArray['message'] = "Cart error - please try again.";
        sendAlertToDealer('007',$dealerId,$consumerInfo,$cc_name,$cc_address,$cc_phone);
	echo json_encode($returnArray);
	exit;
}
// we had an order come through that didn't include the cost of the actual items!
// this will check that at least the order_total is more than the sum of the other charges
if ($_POST['order_total'] <= $_POST['tax_charge'] + $_POST['shipping_charge']) {
	$returnArray['status'] = "error";
	$returnArray['message'] = "Cart error [002] - please try again.";
        sendAlertToDealer('002',$dealerId,$consumerInfo,$cc_name,$cc_address,$cc_phone);
	echo json_encode($returnArray);
	exit;
}


if (empty($shoppingCartId) || !is_numeric($shoppingCartId)) {
	$returnArray['status'] = "error";
	$returnArray['message'] = "Cart error [003] - please try again.";
        sendAlertToDealer('003',$dealerId,$consumerInfo,$cc_name,$cc_address,$cc_phone);
	echo json_encode($returnArray);
	exit;
}


if (empty($dealerId) || !is_numeric($dealerId) || $dealerId < 1) {
	$returnArray['status'] = "error";
	$returnArray['message'] = "Cart error [004] - please try again.";
        sendAlertToDealer('004',$dealerId,$consumerInfo,$cc_name,$cc_address,$cc_phone);
	echo json_encode($returnArray);
	exit;
}

// block if too many attempts -- helps prevent card fishing
$_SESSION['cart_info']['authorize_attempts']++;
if ($_SESSION['cart_info']['authorize_attempts'] > 30) {
	$returnArray['authorize_attempts'] = $_SESSION['cart_info']['authorize_attempts'];
	$returnArray['status'] = "error";
	$returnArray['message'] = "Cart error [005] - please contact customer service.";
        sendAlertToDealer('005',$dealerId,$consumerInfo,$cc_name,$cc_address,$cc_phone);
	echo json_encode($returnArray);
	exit;
}



// this will check that at least the order_total is equal to the total charges in the cart
/*if ($_POST['order_total'] !=  $cartTotal) {    
	$returnArray['status'] = "cost_error";
	$returnArray['message'] = "There is an error - please try again."; 
        sendAlertToDealer('006',$dealerId,$consumerInfo);
	echo json_encode($returnArray);
	exit;
}*/
function sendAlertToDealer($code,$dealerID,$consumerEmail,$cc_name,$cc_address,$cc_phone){
    //$dealerId = getFieldFromId('dealer_id','shopping_carts','shopping_cart_id',$shoppingCartId);
    $dealerName = getFieldFromId('domain_name','domain_names','dealer_id',$dealerID);
    $message = "";
    switch($code){        
         case "001":
            $message="No sufficient order Information - Order Total Missing";            
            break;
         case "002":
            $message="Order total doesn't include cost of actual items";            
            break;
         case "003":
            $message="Shopping Cart information is missing";            
            break;
         case "004":
            $message="Dealer information is missing";            
            break;
         case "005":
            $message="Checkout attempt exceeds the limit of 3 times";            
            break;
         case "006":
            $message="Order total doesn't include shipping/additional/tax charge";            
            break;
        case "007":
            $message="Blacklisted IP checkout not allowed";            
            break;
	default:
            $message="Sandbox checkout error"; 
            break;
            
    }
    $mail = new PHPMailer();
    $mail->IsMail();
    $mail->IsHTML(true);
    $mail->SetFrom("system@nfdnetwork.com", "NFDNetwork");
    $mail->AddReplyTo("system@nfdnetwork.com", "NFDNetwork");
    $mail->AddAddress("sangeetha.r@mstsolutions.com"); 
    #$mail->AddCC("kiruthiga@mstsolutions.com"); 
    $tags_open = "<html><body style='padding: 12px; font-family: Helvetica, Arial, sans-serif; font-size: 14px;'>";
    $intro  = "<h1 style='font-size: 21px;'>NFDN Shopping Cart Error Alert</h1><br><b>Consumer Email Id : </b>".$consumerEmail."<br/>";
    $error_info= "<br/><b>Error info : </b>". $code." - ". $message;
    $ip_info = "<br/><b> Client IP : </b>".$_SERVER['REMOTE_ADDR'];
    $cust_info = "<br/><b> Name: </b>".$cc_name;
    $contact_info = "<br/><b> Contact Info: </b>".$cc_address;
    $phone_num = "<br/><b>Phone No: </b>".$cc_phone;
    $tags_close = "</body></html>";
    $mail->Subject = "NFDN Shopping Cart Error Alert - ".$dealerName;					
    $mail->Body = $tags_open . "\n" . $intro . "\n" .$error_info."\n".$ip_info."\n".$cust_info."\n".$contact_info."\n".$phone_num."\n".$tags_close ;
    $mail->AltBody = "NFDN Shopping Cart Error Alert";
    $mail->Send();
}


// fraud prevention for shipped orders
if ($_POST['shipping_preference'] == "ship") { 

	// first check current customer blacklist
	$checkName = (empty($_POST['ship_name']) ? $_POST['ffl_name'] : $_POST['ship_name']);
	$checkAddress = (empty($_POST['ship_name']) ? substr($_POST['ffl_address'],0,12) : substr($_POST['ship_address'],0,12));
	$checkZip= (empty($_POST['ship_zip'])?substr($_POST['ffl_zip'],0,4):substr($_POST['ship_zip'],0,4));         
        $checkNameTrim=trim($checkName);     
	if (!empty($checkAddress) && !empty($checkNameTrim) ) {  
		$blackList = false;
		$parameters = array();
		$queryPart = array();
		$query="SELECT cb.customer_blacklist_id,
                cb.full_name,
                cb.address, 
                left(a.zip_code,5) as zipcode,
                cb.last_order_date,
                cb.last_order_id,
                cb.last_dealer_id,
                cb.last_attempt_date,
                cb.last_attempt_dealer_id,
                cb.version
                FROM customer_blacklist cb
                left join orders o on cb.last_order_id=o.order_id
                left join addresses a on o.shipping_address_id= a.address_id where"; 
		if ( strlen($checkName) > 7 ) {
			$queryPart[] = "cb.full_name = ?";
			$parameters[] = $checkName;
		}
		if (!empty($checkAddress)) {
			$queryPart[] = "cb.address like ?";
			$parameters[] = "%$checkAddress%";  
		}
                if (!empty($checkZip)){
                        $queryPart[] ="a.zip_code=?";
                        $parameters[] = $checkZip; 
                } 
		$resultSet = executeQuery($query . implode(" or ",$queryPart),$parameters);
		if ($row = getNextRow($resultSet)) {
			$blackList = true;
			 $query  = "update customer_blacklist set last_attempt_date = now(), last_attempt_dealer_id = ?, version = version + 1,zip_code = ? ";
                         $query .= "where customer_blacklist_id = ?";
                         $resultSet = executeQuery($query,$dealerId,$checkZip,$row['customer_blacklist_id']);   
		}
	
		if (!$blackList) {
			// any other orders using this name/address in the past 14 days?
			// exclude hold orders (shipping_method_id != 1)
			$parameters = array();
			$query  = "select orders.order_id, orders.order_number, orders.order_date, orders.dealer_id, billing.full_name, shipping.address_1 from orders ";
			$query .= "join addresses billing on billing.address_id = orders.billing_address_id ";
			$query .= "join addresses shipping on shipping.address_id = orders.shipping_address_id ";   
                        $query .= "where orders.shipping_method_id != 1 and orders.order_date > ? ";
			$parameters[] = date('Y-m-d',strtotime("-14 days"));  	        
			$parameters[] = "[[:<:]]" . $checkName . "[[:>:]]"; 
			$parameters[] = "[[:<:]]" . $checkName . "[[:>:]]";  
			if (empty($checkAddress)) {
				$query .= "and (billing.full_name regexp ? or shipping.full_name regexp ?)";                                         
			} else {
				$query .= "and (billing.full_name regexp ? or shipping.full_name regexp ? "; 
				$query .= "or billing.address_1 like ? or shipping.address_1 like ? "; 
                                $query .= "or billing.zip_code = ? or shipping.zip_code = ? ) ";            
				$parameters[] = "%$checkAddress%"; 
				$parameters[] = "%$checkAddress%"; 
                                $parameters[] = $checkZip;  
				$parameters[] = $checkZip;   
			} 
		    $query .= "group by dealer_id order by order_date desc";          
                    $resultSet = executeQuery($query,$parameters);                         
                    $orderCount = $resultSet['row_count'];                                
                    $sendAlert = false;  
                    if ($row = getNextRow($resultSet)) {
				if ($row['dealer_id'] != $dealerId) {
					$details = array();
					$sendAlert = true;
					if ($orderCount > 1) {
						$blackList = true; 
						$parameters = array();
						$query  = "insert into customer_blacklist (full_name,address,zip_code,last_order_date,last_order_id,last_dealer_id,version) ";
						$query .= "values (?,?,?,?,?,?,1)"; 	 					
						$insertSet = executeQuery($query,$checkName,$checkAddress,$checkZip,$row['order_date'],$row['order_id'],$row['dealer_id']); 
						$details[] = "<p>A customer has been added to the blacklist after attempting to purchase from more than two different dealers in the past 14 days.</p>";
						$details[] = "<p></p>";
						$details[] = "<p>Customer Name: $checkName</p>";
						$contactId = getFieldFromId('contact_id','dealers','dealer_id',$dealerId);
						$dealerName = getFieldFromId('company_name','contacts','contact_id',$contactId);
						$details[] = "<p>Current attempted checkout with $dealerName</p>";
						$contactId = getFieldFromId('contact_id','dealers','dealer_id',$row['dealer_id']);
						$dealerName = getFieldFromId('company_name','contacts','contact_id',$contactId);
						$details[] = "<p>Previous order " . $row['order_number'] . " with $dealerName on " . $row['order_date'] . "</p>";
						while ($row = getNextRow($resultSet)) {
							$contactId = getFieldFromId('contact_id','dealers','dealer_id',$row['dealer_id']);
							$dealerName = getFieldFromId('company_name','contacts','contact_id',$contactId);
							$details[] = "<p>Previous order " . $row['order_number'] . " with $dealerName on " . $row['order_date'] . "</p>";
						}
					} else {
						$details[] = "<p>A customer is placing an order while already having an order with a different dealer in the past 14 days.</p>";
						$details[] = "<p></p>";
						$details[] = "<p>Customer Name: $checkName</p>";
						$contactId = getFieldFromId('contact_id','dealers','dealer_id',$dealerId);
						$dealerName = getFieldFromId('company_name','contacts','contact_id',$contactId);
						$details[] = "<p>Current order being placed with $dealerName</p>";
						$contactId = getFieldFromId('contact_id','dealers','dealer_id',$row['dealer_id']);
						$dealerName = getFieldFromId('company_name','contacts','contact_id',$contactId);
						$details[] = "<p>Previous order " . $row['order_number'] . " with $dealerName on " . $row['order_date'] . "</p>";
					}
				}
				if ($sendAlert) {
					// send the email
					$mail = new PHPMailer();
					$mail->IsMail();
					$mail->IsHTML(true);
					$mail->SetFrom("system@nfdnetwork.com", "NFDNetwork");
					$mail->AddReplyTo("system@nfdnetwork.com", "NFDNetwork");
					$mail->AddAddress("jscott@nfdnetwork.com");
					$mail->AddBCC("system@nfdnetwork.com", "NFDN Webmaster");
					$mail->Subject = "NFDN Fraud Alert";			
					$tags_open = "<html><body style='padding: 12px; font-family: Helvetica, Arial, sans-serif; font-size: 14px;'>";
					$intro  = "<h1 style='font-size: 21px;'>NFDN Fraud Alert</h1>";
					$tags_close = "</body></html>";
					$mail->Body = $tags_open . "\n" . $intro . "\n" . implode("\n",$details) . "\n" . $tags_close;
					$mail->AltBody = implode("\n",$details);
					$mail->Send();
				}
			}
		}

		if ($blackList) {
			$returnArray['status'] = "error";
			$returnArray['message'] = "Cart error [999] - please contact customer service.";
			echo json_encode($returnArray);
			exit;
		}

	} else { // strlen($checkName) > 7 || !empty($checkAddress)
		$returnArray['status'] = "error";
		$returnArray['message'] = "Cart error [005.5] - please try again.";
		echo json_encode($returnArray);
		exit;
	}
}

$tryDealerLocationId = ($dealerId == $_POST['dealer_location_id'] ? "" : $_POST['dealer_location_id']);
if (!empty($tryDealerLocationId) && is_numeric($tryDealerLocationId) && $tryDealerLocationId > 1) {
	$resultSet = executeQuery("select * from dealers where dealer_id = ? and master_dealer_id = ?",$tryDealerLocationId,$dealerId);
	if ($row = getNextRow($resultSet)) {
		$dealerLocationId = $row['dealer_id']; 
	} else {
		$dealerLocationId = "";
	}
} else {
	$dealerLocationId = "";
}

$shipFirearms = 0;
$shipHanduns = 0;
$shipAmmo = 0;
$shipItems = 0;
$orderItems = array();
$i = 0;
while (!empty($_POST['order_item_' . $i])) {
	$itemArray = explode("|",$_POST['order_item_' . $i]);
	$price = preg_replace("/([^0-9\\.])/i", "", $itemArray[2]);
//	$price = $itemArray[2];
	$shippingGroup = $itemArray[3];
	// we had some order items coming through with the order price set as the product id
	if ($itemArray[0] == (int) $price) {
		$returnArray['status'] = "error";
		$returnArray['message'] = "Cart error [006] - please try again.";
		echo json_encode($returnArray);
		exit;	
	}
	trackLog("price value:".$price);
	$orderItems[] = array("product_id"=>$itemArray[0],"quantity"=>$itemArray[1],"price"=>$price,"shipping_group"=>$shippingGroup);
	switch($shippingGroup) {
		case "firearms":
			$shipFirearms++;
			break;
		case "handguns":
			$shipHandguns++;
			break;
		case "ammo":
			$shipAmmo++;
			break;
		default:
			$shipItems++;
	}
	$i++;
}

// Code For Inventory Check For Sports South - Prior Inventory Check - Start

$auto_order=$_GET['auto_order'];



if($auto_order == 'Y') {
    
    // set the list of auto-order enabled distributors enabled for this dealer
    $getDealerDistributors = array();
    $query  = "select * from dealer_distributors left join distributors using (distributor_id) where dealer_id = ? ";
    $query .= "and auto_order = 1 and dealer_distributors.inactive = 0 order by priority";
    // if this is a dealer location, see if there are any dealer distributors
    $dealerLocationDistributorCount = 0;
    if (!empty($dealerLocationId)) {

            $resultSet = executeQuery($query,$dealerLocationId);
            $dealerLocationDistributorCount = $resultSet['row_count'];

    }
    if ($dealerLocationDistributorCount == 0) {

            $resultSet = executeQuery($query,$dealerId);

    }
    while ($row = getNextRow($resultSet)) {

            $getDealerDistributors[$row['distributor_id']] = $row;

    }
       foreach ($orderItems as $orderItem) {
        $productId = $orderItem['product_id'];
        $quantity = $orderItem['quantity'];
            foreach ($getDealerDistributors as $checkDistributorArray) {
                    // does this distributor have enough in stock to fulfill this order?
                    $query  = "select distributor_id,product_code,product_id,dealer_cost,quantity,allocated from distributor_inventory ";
                    $query .= "where product_id = ? and distributor_id = ? and quantity >= ?";
                    $productSet = executeQuery($query,$productId,$checkDistributorArray['distributor_id'],$quantity);
                    if ($productRow = getNextRow($productSet)) {
                            $flag=1;
                            if($checkDistributorArray['distributor_id'] == 6)
                            {
                                    // Inventory Check - Web Service Call for Sports South
                                   // $flag = priorInventoryCheck($dealerId,$productRow['product_code'],$quantity); 
								   $flag =1; 
                                    
                            }
                            if($flag != 6)
                            {
                            break; // this distributor has it, so don't check any more for this item
                            }
                    }
            }
	    // Check for Dealer Store Products
            $productDealerId = getFieldFromId('dealer_id','products','product_id',$productId);
            if ($productDealerId > 0) {
                $flag=1;
            }
	    // Check for product unavailability in the auto-order enabled Sports South Distributor
            if($flag == 6)
            { 
                    $returnArray['status'] = "error";
                    $returnArray['message'] = "The Product ID ".$productId." just ran out of stock - Please check back later";
                    echo json_encode($returnArray);
                    exit;
            }
    }                
}
 
// Prior Inventory Check - End
// Store Item Quantity Availability Check - Start

foreach ($orderItems as $orderedItem) {
        // if this is a store item, check for the availability of quantity_in_stock
	$productDealerId = getFieldFromId('dealer_id','products','product_id',$orderedItem['product_id']);
	if ($productDealerId > 0) {
                $resultSet = executeQuery("select quantity_in_stock from dealer_product_data where dealer_id = ? and product_id = ?", $dealerId, $orderedItem['product_id']);
                if ($dealerQuantity = getNextRow($resultSet)) {
                        // Check Ordered Quantity exceeds the available quantity
                        if($dealerQuantity['quantity_in_stock'] < $orderedItem['quantity'])
                        {
                                // Check the Product has some availability
                                if($dealerQuantity['quantity_in_stock'] > 0)
                                {
                                    $returnArray['status'] = "error"; 
                                    $returnArray['message'] = "The ordered quantity for product ID ".$orderedItem['product_id']." exceeds current availability. Please reduce your order size and try again.";
                                    echo json_encode($returnArray);
                                    exit;   
                                }
                                else 
                                {
                                    $returnArray['status'] = "error";
                                    $returnArray['message'] = "The Product ID ".$orderedItem['product_id']." just ran out of stock - Please check back later";
                                    echo json_encode($returnArray);
                                    exit;
                                }
                        }
                }
        }
}

// Store Item Quantity Availability Check - End
if ($_POST['shipping_preference'] == "ship") {
	$saveShippingAddress = ($_POST['customer_shipping_address_required'] == "Y" && $shipItems + $shipAmmo > 0);
	$saveFFLAddress = ($_POST['ffl_shipping_address_required'] && $shipFirearms + $shipHandguns > 0);
	$shippingRatePreference = (empty($_POST['shipping_rate_preference'])?"express":strtolower($_POST['shipping_rate_preference']));
	if ($shippingRatePreference == "express") {
		$shippingMethodCode = $_POST['express_shipping_code'];
	} else {
		$shippingMethodCode = $_POST['ground_shipping_code'];
	}
	$shippingMethodId = getFieldFromId('shipping_method_id','shipping_methods','description',$shippingMethodCode);

	if (empty($_POST['shipping_charge']) || empty($shippingMethodId) ) {
		$returnArray['status'] = "error";
		$returnArray['message'] = "Cart error [006] - please try again.";
		echo json_encode($returnArray);
		exit;
	}

} else {
	$saveShippingAddress = false;
	$saveFFLAddress = false;
	$shippingMethodId = getFieldFromId('shipping_method_id','shipping_methods','description','HOLD');
}
//Server side calculation for additional charge
$parameters = array();
$addCharge = array();
$additionalCharge = 0;
if ($dealerLocationId > 0) {
            $query = "select * from dealer_charges where (dealer_id = ? or dealer_id = ?) ";
            $parameters[] = $dealerLocationId;
            $parameters[] = $dealerId;
} else {    
            $query = "select * from dealer_charges where dealer_id = ? ";
            $parameters[] = $dealerId;
} 
        $query .= "and internal_use_only = 0 and inactive = 0";
        $resultSet = executeQuery($query, $parameters);
        if ($resultSet['row_count'] > 0) {
            // get all shipping groups represented
            $shippingGroupsInCart = array();
            foreach ($orderItems as $itemSet) {
                $productType = getShippingGroup($itemSet['product_id']);
                $shippingGroupsInCart[$productType] = 1;                
            }
             
            while ($row = getNextRow($resultSet)) {         
            	
                $itemQualifies = false;
                $methodQualifies = false;
                if (($shippingGroupsInCart['firearms'] == 1 || $shippingGroupsInCart['handguns'] == 1) && $row['apply_firearm'] == 1) {
                    $itemQualifies = true;
                }
                if ($shippingGroupsInCart['ammo'] == 1 && $row['apply_ammo'] == 1) {
                    $itemQualifies = true;
                }
				if ($shippingGroupsInCart['nfa'] == 1 && $row['apply_nfa'] == 1) {
                    $itemQualifies = true;
                }
                if ($shippingGroupsInCart['items'] == 1 && $row['apply_other'] == 1) {
                    $itemQualifies = true;
                }
               
                // if there are any qualifying items, further qualify based on method
                if ($itemQualifies) {
                    if ($_POST['shipping_preference'] == "hold" && $row['apply_hold'] == 1) {
                        $methodQualifies = true;
                    }
                    if ($_POST['shipping_preference'] == "ship" && $row['apply_ship'] == 1) {
                        $methodQualifies = true;
                    }
                }
               
                if ($itemQualifies && $methodQualifies) { 
                    //$additionalChargeIds[] = $row['dealer_charge_id'];
                    $newprice[]=$row['amount'];
					$description[]=$row['description'];
                    
                    $addCharge[$row['description']][] = $row['amount'];       
                    $additionalCharge = $additionalCharge + $row['amount'];
                }
            }
            
}
trackLog("Additional charges:");
trackLog("add charge".print_r($addCharge,true));


//server side calculation for applying promotional charges
$promotionDepartments = array();
$promoDetail = array(); 
$promoCharge = array();
$promotionalCharge = 0;
$dealer_promotion_enabled = getFieldFromId('allow_dealer_promotion', 'dealers', 'dealer_id', $dealerId);
	     if($dealer_promotion_enabled == 1){
			 $resultSet = executeQuery("select * from dealer_promotions where dealer_id = ? and inactive = 0 and CURDATE() between start_date and end_date", $dealerId);
			 while($promotionRow = getNextRow($resultSet)){	
                // $currData = array();
                 $promotionDepartments[$promotionRow['department_id']]['description'] = $promotionRow['description'];
				 $promotionDepartments[$promotionRow['department_id']]['discount'] = $promotionRow['discount_rate'];
				 //promotionDepartments[] = $currData ;
		    }
			 foreach($orderItems as $itemSet){				 
				 $cartItemCat = getFieldFromId("category_id","products","product_id",$itemSet['product_id']);
				 $cartItemDept = getFieldFromId("department_id","categories","category_id",$cartItemCat);
				 foreach($promotionDepartments as $department_id => $detail){
					 if($department_id == $cartItemDept)					 {					
						 $discountRate = round(($itemSet['quantity']*$itemSet['price'])*$detail['discount'] /100,2);
				         if(!isset($promoDetail[$department_id]['description'])){
							 $promoDetail[$department_id]['description'] = $detail['description'];
							 $promoDetail[$department_id]['discount_amount'] = $discountRate;
						 }else{						 							 
							 $promoDetail[$department_id]['discount_amount'] = $promoDetail[$department_id]['discount_amount'] + $discountRate;							 
						 }						 					 }
					}				 
			 }
			 foreach($promoDetail as $department_id => $detail){
				 $promoCharge[$detail['description']] = $detail['discount_amount'];       
                 $promotionalCharge = $promotionalCharge + $detail['discount_amount'];
			     //$promotionalChargeNote .= (empty($promotionalChargeNote) ? "" : "<br>") . $detail['description'] . " : $" . $detail['discount_amount'] ;	
			 }		 
		 }




$orderTotal = $_POST['order_total_a']+ $additionalCharge - $promotionalCharge;
$ccAmount = 0;
$giftCardAmount = 0;
$giftCardBalance = 0;

// if there's a gift card number, get the gift card balance
if (!empty($_POST['gift_card_number'])) {

	$giftCardMerchantNumber	= getFieldFromId('gift_card_merchant_id','dealers','dealer_id',$dealerId); 
	// test uses 1800000003910
	if ($giftCardMerchantNumber == "1800000003910") {
		$giftCardNumber = '21'; // test card number is 21, live use $_POST['gift_card_number'];
		$login = 'psolut'; // test login is psolut, live use national99 
		$password = 'ar78t'; // test password is ar78t, live use firearm100
	} else {
		$giftCardNumber = $_POST['gift_card_number'];
		$login = 'national99';
		$password = 'firearm100';
	}
	$clerkNumber = '1';
	$soapClient = new SoapClient("https://wgchost.com/w3/service.asmx?WSDL");

	// first get gift card balance
	$ap_param = array( 
		'mnum'   =>  $giftCardMerchantNumber,
		'cn'     =>  $clerkNumber, 
		'id'     =>  $giftCardNumber, 
		'login'  =>  $login, 
		'pass'   =>  $password
	);
	try { 
	    $info = $soapClient->__call("Balance", array($ap_param));
		if (!is_numeric($info->BalanceResult)) {
			$returnArray['status'] = "error";
		    $returnArray['message'] = "Processing error - please check your gift card number.";
			echo json_encode($returnArray);
			exit;
		}
		$giftCardBalance = number_format($info->BalanceResult,2, '.', '');
		// if the gift card balance isn't enough to cover the entire order, and there is no credit card number, return an error
		if (empty($_POST['cc_number']) && $orderTotal > $giftCardBalance) {
			$returnArray['status'] = "gift_card_balance_error";
			$returnArray['gift_card_balance'] = $giftCardBalance;
			echo json_encode($returnArray);
			exit;
		}
	} catch (SoapFault $fault) {
		$returnArray['status'] = "error";
	    $returnArray['message'] = "Gift Card processing error: " . $fault->faultcode."-".$fault->faultstring;
		echo json_encode($returnArray);
		exit;
	}
}

if ($giftCardBalance > 0) {
	if ($giftCardBalance >= $orderTotal) {
		$giftCardAmount = $orderTotal;
	} else {
		$giftCardAmount = $giftCardBalance;
	}
	$ccAmount = $orderTotal - $giftCardAmount;
} else {
	$ccAmount = $orderTotal;
}
$returnArray['gift_card_amount'] = $giftCardAmount;
$returnArray['cc_amount'] = $ccAmount;
$ccNumber = $_POST['cc_number'];
$parameterList = array();
$parameterList['shopping_cart_id'] = $shoppingCartId;
$parameterList['shipping_method_id'] = $shippingMethodId;
$parameterList['full_name'] = $_POST['cc_name'];
$parameterList['address_1'] = $_POST['cc_address'];
$parameterList['city'] = $_POST['cc_city'];
$parameterList['state'] = $_POST['cc_state'];
$parameterList['zip_code'] = $_POST['cc_zip'];
$parameterList['country_code'] = $_POST['cc_country'];
$parameterList['phone_number'] = $_POST['phone_number'];
$parameterList['email_address'] = $_POST['email_address'];
$parameterList['notes'] = $_POST['notes'];
$parameterList['shipping_charge'] = $_POST['shipping_charge'];
$parameterList['shipping_weight'] = $_POST['shipping_weight'];
$parameterList['insurance_charge'] = $_POST['insurance_charge'];
$parameterList['tax_charge'] = $_POST['tax_charge'];
$isErrorTransaction = false;
if ($ccAmount > 0 && !empty($ccNumber)) {
        trackLog("Payment transaction started ............");
	$processorDealerId = (empty($dealerLocationId) ? $dealerId : $dealerLocationId);
	$transactionProcessor = getFieldFromId('transaction_processor_id','dealers','dealer_id',$processorDealerId);
        trackLog("Payment Processor : ".$transactionProcessor);
	switch($transactionProcessor) {
		case 1:
			$eCommerce = new eCommerce($dealerId,$dealerLocationId);
			$parameterList['ssl_card_number'] = $ccNumber;
			$parameterList['ssl_exp_date'] = $_POST['cc_exp_month'] . substr($_POST['cc_exp_year'],2);
			$parameterList['ssl_amount'] = $ccAmount;
			$parameterList['ssl_avs_address'] = $address;
			$parameterList['ssl_avs_zip'] = $_POST['cc_zip'];
			$parameterList['ssl_cvv2cvc2'] = $_POST['cc_code'];
			$parameterList['ssl_city'] = $_POST['cc_city'];
			$parameterList['ssl_email'] = $_POST['email_address'];
			$parameterList['ssl_first_name'] = $firstName;
			$parameterList['ssl_last_name'] = $lastName;
			$parameterList['ssl_phone'] = $_POST['phone_number'];
			$parameterList['ssl_state'] = $_POST['cc_state'];
			$parameterList['ssl_ship_to_first_name'] = $firstName;
			$parameterList['ssl_ship_to_last_name'] = $lastName;
			$parameterList['ssl_ship_to_address_1'] = $_POST['ship_address'];
			$parameterList['ssl_ship_to_city'] = $_POST['ship_city'];
			$parameterList['ssl_ship_to_state'] = $_POST['ship_state'];
			$parameterList['ssl_ship_to_phone'] = $_POST['phone_number'];
			$parameterList['ssl_ship_to_zip'] = $_POST['ship_zip'];
			break;
		case 2:
			$eCommerce = new eCommerceAuthnet($dealerId,$dealerLocationId);
			$parameterList['x_first_name'] = $firstName;
			$parameterList['x_last_name'] = $lastName;
			$parameterList['x_card_num'] = $ccNumber;
			$parameterList['x_card_code'] = $_POST['cc_code'];
			$parameterList['x_exp_date'] = $_POST['cc_exp_month'] . substr($_POST['cc_exp_year'],2);
			$parameterList['x_amount'] = $ccAmount;
			$parameterList['x_address'] = $_POST['cc_address'];
			$parameterList['x_city'] = $_POST['cc_city'];
			$parameterList['x_state'] = $_POST['cc_state'];
			$parameterList['x_zip'] = $_POST['cc_zip'];
			$parameterList['x_phone'] = $_POST['phone_number'];
			$parameterList['x_email'] = $_POST['email_address'];
			$parameterList['x_ship_to_first_name'] = $firstName;
			$parameterList['x_ship_to_last_name'] = $lastName;
			$parameterList['x_ship_to_address'] = (empty($_POST['ship_address'])?$_POST['cc_address']:$_POST['ship_address']);
			$parameterList['x_ship_to_city'] = (empty($_POST['ship_city'])?$_POST['cc_city']:$_POST['ship_city']);
			$parameterList['x_ship_to_state'] = (empty($_POST['ship_state'])?$_POST['cc_state']:$_POST['ship_state']);
			$parameterList['x_ship_to_zip'] = (empty($_POST['ship_zip'])?$_POST['cc_zip']:$_POST['ship_zip']);
			$parameterList['x_ship_to_country'] = (empty($_POST['ship_country']) ? "USA" : $_POST['ship_country']);
			$parameterList['x_tax'] = (empty($_POST['tax_charge'])? "0.00" : $_POST['tax_charge']);
			$parameterList['x_shipping'] = (empty($_POST['shipping_charge'])?"0.00":$_POST['shipping_charge']);
			$parameterList['x_freight'] = (empty($_POST['shipping_charge'])?"0.00":$_POST['shipping_charge']);
			$parameterList['x_duty'] = "0.00";
			break;
		case 3:
			$eCommerce = new eCommerceEProcessing($dealerId,$dealerLocationId);
			$parameterList['x_first_name'] = $firstName;
			$parameterList['x_last_name'] = $lastName;
			$parameterList['x_card_num'] = $ccNumber;
			$parameterList['x_card_code'] = $_POST['cc_code'];
			$parameterList['x_exp_date'] = $_POST['cc_exp_month'] . substr($_POST['cc_exp_year'],2);
			$parameterList['x_amount'] = $ccAmount;
			$parameterList['x_address'] = $_POST['cc_address'];
			$parameterList['x_city'] = $_POST['cc_city'];
			$parameterList['x_state'] = $_POST['cc_state'];
			$parameterList['x_zip'] = $_POST['cc_zip'];
			$parameterList['x_phone'] = $_POST['phone_number'];
			$parameterList['x_email'] = $_POST['email_address'];
			$parameterList['x_ship_to_first_name'] = $firstName;
			$parameterList['x_ship_to_last_name'] = $lastName;
			$parameterList['x_ship_to_address'] = $_POST['ship_address'];
			$parameterList['x_ship_to_city'] = $_POST['ship_city'];
			$parameterList['x_ship_to_state'] = $_POST['ship_state'];
			$parameterList['x_ship_to_zip'] = $_POST['ship_zip'];
			break;
		case 4:
			$ccAmount=number_format($ccAmount,2);
			$eCommerce = new eCommerceSlipjack($dealerId,$dealerLocationId);
			$parameterList['x_sjname'] = $_POST['cc_name'];
			$parameterList['x_accountnumber'] = $ccNumber;
			$parameterList['x_cvv2'] = $_POST['cc_code'];
			$parameterList['x_month'] = $_POST['cc_exp_month'];
			$parameterList['x_year'] = $_POST['cc_exp_year'];
			$parameterList['x_transactionamount'] = $ccAmount;
			$parameterList['x_streetaddress'] = $_POST['cc_address'];
			$parameterList['x_city'] = $_POST['cc_city'];
			$parameterList['x_state'] = $_POST['cc_state'];
			$parameterList['x_zipcode'] = $_POST['cc_zip'];
			$parameterList['x_email'] = $_POST['email_address'];
			$parameterList['x_shiptophone'] = $_POST['phone_number'];
			$parameterList['x_ordernumber'] = strtoupper(chr(rand(97,122))) . "-" . rand(); // just needs a unique id
			$parameterList['x_orderstring'] = "123~None~0.00~0~N~||1"; // required, but can be dummy text
			$parameterList['x_shiptoname'] = $_POST['ship_name'];
			$parameterList['x_shiptostreetaddress'] = $_POST['ship_address'];
			$parameterList['x_shiptocity'] = $_POST['ship_city'];
			$parameterList['x_shiptostate'] = $_POST['ship_state'];
			$parameterList['x_shiptozipcode'] = $_POST['ship_zip']; 
			break;
		default:
			$returnArray['status'] = 'error';
			$returnArray['message'] = $eCommerce->getErrorMessage();
			echo json_encode($returnArray);
			exit;	
	}
	trackLog("Transaction parameters");
        trackLog(print_r($parameterList,true));
	$sslTestMode = getFieldFromId('ssl_test_mode','dealers','dealer_id',$processorDealerId);
        trackLog("Test Mode : ".$sslTestMode);
	if ($sslTestMode == 1 && $transactionProcessor == 1) {
		$eCommerce->setTestMode(true);
	}
	
	if ($eCommerce->chargeCard($parameterList)) {
		$reponseArray = $eCommerce->getResponseArray();
		$returnArray['status'] = 'authorized';
                trackLog("Payment authorized.. : Response: ".print_r($reponseArray,true));
	} else {
		$returnArray['status'] = 'error';
                $isErrorTransaction = true;
		$returnArray['message'] = $eCommerce->getErrorMessage();
                trackLog("Payment authorization failed.. : Response: ".print_r($returnArray,true));
		//echo json_encode($returnArray);
		//exit;
	}
        
}

//POBF-527 //removed for Gift card
// if we got this far, it means the credit card transaction was either uneccessary or approved
// and that a gift card, if present, has enough balance for the gift card amount
// if (!empty($giftCardNumber) && $giftCardAmount > 0) {
//      trackLog("Gift card processing started...");
// 	$ap_param = array( 
// 		'mnum'   =>  $giftCardMerchantNumber,
// 		'cn'     =>  $clerkNumber, 
// 		'id'     =>  $giftCardNumber,
// 		'amount' =>  $giftCardAmount,
// 		'login'  =>  $login,
// 		'pass'   =>  $password
// 	);
// 	try {
// 	    $info = $soapClient->__call("Sale", array($ap_param));
// 		if (!is_numeric($info->SaleResult)) {
// 			$returnArray['status'] = "error";
// 		        $returnArray['message'] = "Gift Card Processing error - please contact customer service.";
//                         trackLog("Gift card processing error1..".print_r($returnArray,true));
// 			echo json_encode($returnArray);
// 			exit;
// 		}
// 	    $parameterList['gift_card_number'] = $giftCardNumber;
// 	    $parameterList['gift_card_amount'] = $giftCardAmount;
// 	    $parameterList['gift_card_balance'] = $info->SaleResult;
//             trackLog("Gift card params..".print_r($parameterList,true));
// 	} catch (SoapFault $fault) {
// 		$returnArray['status'] = "error";
// 	        $returnArray['message'] = "Gift Card processing error: " . $fault->faultcode . "-" . $fault->faultstring;
//                 trackLog("Gift card processing error2..".print_r($returnArray,true));
// 		echo json_encode($returnArray);
// 		exit;
// 	}
// }

// now create all the order and contact records
//startTransaction();

if ($gLoggedIn) {
        trackLog("Logged in user");
	$contactId = getFieldFromId('contact_id','users','user_id',$gUserId);
	
	$resultSet = executeQuery("update contacts set first_name = ?, last_name = ?, address_1 = ?, address_2 = ?, city = ?, state = ?, zip_code = ?, country_code = ?, " .
		"phone_number = ?, email_address = ?, version = version + 1 where contact_id = ?",$firstName,$lastName,$parameterList['address_1'],$parameterList['address_2'],
		$parameterList['city'],$parameterList['state'],$parameterList['zip_code'],$parameterList['country_code'],$parameterList['phone_number'],
		$parameterList['email_address'],$contactId);
			//To check subscriber already exist and insert
	if($subscriber=='yes')
	{
		$subscriberesultSet = executeQuery("select contact_id from subscribers where mail_address=?",$parameterList['email_address']);
		if($subscriberesultSet['row_count'] == 0) {
			executeQuery("insert into subscribers (contact_id,dealer_id,mail_address) values (?,?,?)",$contactId,$dealerId,$parameterList['email_address']);
		}
	}

	// check for, get or create shipping address
	if ($saveShippingAddress) {
		//$addressTypeId = getFieldFromId("address_type_id","address_types","address_type_code","SHIP");
		$addressTypeCode = (empty($_POST['customer_shipping_address_type']) ? "RES" : substr($_POST['customer_shipping_address_type'],1,3));
		$addressTypeId = getFieldFromId("address_type_id","address_types","address_type_code",$addressTypeCode);		
		$resultSet = executeQuery("select * from addresses where (address_type_id is null or address_type_id in " .
			"(select address_type_id from address_types where address_type_code <> 'FFL')) and " .
			"full_name <=> ? and address_1 <=> ? and address_2 is null and city <=> ? and state <=> ? and zip_code <=> ? and " .
			"phone_number <=> ? and contact_id = ?",$_POST['ship_name'],$_POST['ship_address'],
			$_POST['ship_city'],$_POST['ship_state'],$_POST['ship_zip'],
			$_POST['phone_number'],$contactId);
		if ($row = getNextRow($resultSet)) {
			$parameterList['shipping_address_id'] = $row['address_id'];
		} else {
			$resultSet = executeQuery("insert into addresses (address_id,contact_id,full_name,address_label,address_1,address_2," .
				"city,state,zip_code,country_code,phone_number,email_address,address_type_id,version) values (null,?,?,'Ship To',?,null,?,?,?,?,?,?,?,1)",
				$contactId,$_POST['ship_name'],$_POST['ship_address'],$_POST['ship_city'],
				$_POST['ship_state'],$_POST['ship_zip'],$_POST['ship_country'],$_POST['phone_number'],$parameterList['email_address'],$addressTypeId);
			$parameterList['shipping_address_id'] = $resultSet['insert_id'];
		}
	}

	// check for, get or create ffl address
	if ($saveFFLAddress) {
		$addressTypeId = getFieldFromId("address_type_id","address_types","address_type_code","FFL");
		$resultSet = executeQuery("select * from addresses where address_type_id in (select address_type_id from address_types where address_type_code = 'FFL') " .
			"and full_name <=> ? and address_1 <=> ? and address_2 is null and city <=> ? and state <=> ? and " .
			"zip_code <=> ? and phone_number <=> ? and contact_id = ?",$_POST['ffl_name'],
			$_POST['ffl_address'],$_POST['ffl_city'],$_POST['ffl_state'],
			$_POST['ffl_zip'],$_POST['ffl_phone'],$contactId);
		if ($row = getNextRow($resultSet)) {
			$parameterList['ffl_address_id'] = $row['address_id'];
		} else {
			$resultSet = executeQuery("insert into addresses (address_id,contact_id,full_name,address_label,address_1,address_2," .
				"city,state,zip_code,country_code,phone_number,address_type_id,version) values (null,?,?,?,?,null,?,?,?,?,?,?,1)",
				$contactId,$_POST['ffl_name'],$parameterList['full_name'],$_POST['ffl_address'],$_POST['ffl_city'],
				$_POST['ffl_state'],$_POST['ffl_zip'],$_POST['ship_country'],$_POST['ffl_phone'],
				$addressTypeId);
			$parameterList['ffl_address_id'] = $resultSet['insert_id'];
		}
	}

} else { // if ($gLoggedIn)
        trackLog("Guest checkout");
	# guest checkout, so create contact records
	$resultSet = executeQuery("insert into contacts (contact_id,dealer_id,first_name,last_name,address_1,city,state,zip_code,country_code,email_address,phone_number,version) values (" .
		"null,?,?,?,?,?,?,?,?,?,?,1)",$dealerId,$firstName,$lastName,$parameterList['address_1'],
		$parameterList['city'],$parameterList['state'],$parameterList['zip_code'],$parameterList['country_code'],$parameterList['email_address'],
		$parameterList['phone_number']);
	$contactId = $resultSet['insert_id'];

	if($subscriber=='yes')
	{
		$subscriberesultSet = executeQuery("select contact_id from subscribers where mail_address=?",$parameterList['email_address']);
		if($subscriberesultSet['row_count'] == 0) {
			executeQuery("insert into subscribers (contact_id,dealer_id,mail_address) values (?,?,?)",$contactId,$dealerId,$parameterList['email_address']);
		}
	}

	# create shipping address record
	if ($saveShippingAddress) {
		//$addressTypeId = getFieldFromId("address_type_id","address_types","address_type_code","SHIP");
		$addressTypeCode = (empty($_POST['customer_shipping_address_type']) ? "RES" : substr($_POST['customer_shipping_address_type'],1,3));
		$addressTypeId = getFieldFromId("address_type_id","address_types","address_type_code",$addressTypeCode);
		$resultSet = executeQuery("insert into addresses (address_id,contact_id,full_name,address_label,address_1,address_2," .
			"city,state,zip_code,country_code,phone_number,email_address,address_type_id,version) values (null,?,?,'Ship To',?,null,?,?,?,?,?,?,?,1)",
			$contactId,$_POST['ship_name'],$_POST['ship_address'],$_POST['ship_city'],
			$_POST['ship_state'],$_POST['ship_zip'],$_POST['ship_country'],$_POST['phone_number'],$parameterList['email_address'],$addressTypeId);
		$parameterList['shipping_address_id'] = $resultSet['insert_id'];
	}

	# create ffl address record
	if ($saveFFLAddress) {
		$addressTypeId = getFieldFromId("address_type_id","address_types","address_type_code","FFL");
		$resultSet = executeQuery("insert into addresses (address_id,contact_id,full_name,address_label,address_1,address_2," .
			"city,state,zip_code,country_code,phone_number,address_type_id,version) values (null,?,?,?,?,null,?,?,?,?,?,?,1)",
			$contactId,$_POST['ffl_name'],$parameterList['full_name'],$_POST['ffl_address'],$_POST['ffl_city'],
			$_POST['ffl_state'],$_POST['ffl_zip'],$_POST['ship_country'],$_POST['ffl_phone'],
			$addressTypeId);
		$parameterList['ffl_address_id'] = $resultSet['insert_id'];
	}
} // if ($gLoggedIn)

# check to see if there are any custom fields
foreach ($_POST as $fieldName => $fieldData) {
	if (substr($fieldName,0,strlen("custom_field_")) == "custom_field_") {
		$customFieldId = substr($fieldName,strlen("custom_field_"));
		if (!is_numeric($customFieldId)) {
			continue;
		}
		$customFieldId = getFieldFromId("custom_field_id","custom_fields","custom_field_id",$customFieldId,"dealer_id = " . makeNumberParameter($dealerId));
		if (empty($customFieldId)) {
			continue;
		}
		executeQuery("delete from custom_contact_data where contact_id = ? and custom_field_id = ?",$contactId,$customFieldId);
		executeQuery("insert into custom_contact_data (custom_contact_data_id,contact_id,custom_field_id,text_data,version) values (null,?,?,?,1)",$contactId,$customFieldId,$fieldData);
	}
}

// create an order record
if (empty($parameterList['shipping_charge'])) {
	$parameterList['shipping_charge'] = 0;
}
if (empty($parameterList['tax_charge'])) {
	$parameterList['tax_charge'] = 0;
}
$orderMethodId = getFieldFromId('order_method_id','order_methods','order_method_code','WEB');
$transactionResult ="";
if(!$isErrorTransaction){
    $orderStatusId = getFieldFromId('order_status_id','order_status','order_status_code','PURCHASED');
    $transactionResult = $returnArray['status'];
}
else{
    $orderStatusId = getFieldFromId('order_status_id','order_status','order_status_code','TRANSACTION_FAILED');
     $transactionResult = $returnArray['message'];
}
$addressTypeId = getFieldFromId("address_type_id","address_types","address_type_code","BILL");

$resultSet = executeQuery("select * from addresses where (address_type_id is null or address_type_id in (select address_type_id from address_types where address_type_code <> 'FFL')) " .
	"and full_name <=> ? and address_1 <=> ? and address_2 <=> ? and city <=> ? and state = ? and zip_code <=> ? and phone_number <=> ? and contact_id = ?",
	$parameterList['full_name'],$parameterList['address_1'],$parameterList['address_2'],$parameterList['city'],$parameterList['state'],
	$parameterList['zip_code'],$parameterList['phone_number'],$contactId);
if ($row = getNextRow($resultSet)) {
	$billingAddressId = $row['address_id'];
} else {
	$resultSet = executeQuery("insert into addresses (address_id,contact_id,full_name,address_label,address_1,address_2," .
		"city,state,zip_code,country_code,phone_number,address_type_id,version) values (null,?,?,'Bill To',?,?,?,?,?,?,?,?,1)",$contactId,
		$parameterList['full_name'],$parameterList['address_1'],$parameterList['address_2'],$parameterList['city'],$parameterList['state'],
		$parameterList['zip_code'],$parameterList['country_code'],$parameterList['phone_number'],$addressTypeId);
	$billingAddressId = $resultSet['insert_id'];
}

while (true) {
	$orderNumber = 1000;
	$resultSet = executeQuery("select max(order_number) as order_number from orders where dealer_id = ?",$dealerId);
	if ($row = getNextRow($resultSet)) {
		if (!empty($row['order_number'])) {
			$orderNumber = $row['order_number'] + 1;
		}
	}
        // For click through Added advertising_id column
	$resultSet = executeQuery("insert into orders (order_id,dealer_id,location_id,order_number,contact_id,order_method_id,ffl_address_id,billing_address_id," .
		"shipping_method_id,shipping_address_id,ip_address,order_date,date_completed,shipping_charge,insurance_charge,tax_charge," .
		"order_status_id,notes,print_notes,payment_transaction_result,deleted,version,advertising_id) values " .
		"(null,?,?,?,?,?,?,?,?,?,?,now(),?,?,?,?,?,?,?,?,0,1,?)",$dealerId,$dealerLocationId,$orderNumber,$contactId,$orderMethodId,
		$parameterList['ffl_address_id'],$billingAddressId,$parameterList['shipping_method_id'],$parameterList['shipping_address_id'],
		$_SERVER['REMOTE_ADDR'],$parameterList['date_completed'],$parameterList['shipping_charge'],$parameterList['insurance_charge'],$parameterList['tax_charge'],
		$orderStatusId,$parameterList['notes'],$parameterList['print_notes'],$transactionResult,$_GET['ad-link']);
	if ($resultSet['sql_error_number'] != 1062) {
		break;
	}
}
$orderId = $resultSet['insert_id'];
trackLog("Order with ID ".$orderId." generated.");

//Insert additional charge

if (count($addCharge) > 0) {
foreach($addCharge as $idx => $description){
	foreach ($description as $value) {
    $chargeResult = executeQuery("insert into order_charges (order_charge_id,order_id,description,amount,version) values " .
				"(null,?,?,?,1)",$orderId,$idx,$value); 
}
}
}
trackLog("addd charge".print_r($chargeResult,true));

if (count($promoCharge) > 0) {
foreach($promoCharge as $idx => $description){
    $chargeResult = executeQuery("insert into order_promo_charges (order_promo_charge_id,order_id,description,amount,version) values " .
				"(null,?,?,?,1)",$orderId,$idx,$description); 
}
}
trackLog(print_r($chargeResult,true));
// create order_items for this order
foreach ($orderItems as $orderItem) {
	$query  = "insert into order_items (order_item_id,order_id,product_id,quantity,order_price,order_status_id,";
	$query .= "anticipated_ship_date,date_shipped,deleted,version) values (null,?,?,?,?,?,null,null,0,1)";
	$resultSet = executeQuery($query,$orderId,$orderItem['product_id'],$orderItem['quantity'],$orderItem['price'],$orderStatusId);

	trackLog("order item".print_r($resultSet,true));
	// if this is a store item, update the quantity_in_stock
	// $productArray = getProductInfoMin($orderItem['product_id'],$dealerId);
	$productDealerId = getFieldFromId('dealer_id','products','product_id',$orderItem['product_id']);
	//if ($productArray['store_item']) { 
	if ($productDealerId > 0 && !$isErrorTransaction) {
		$resultSet = executeQuery("update dealer_product_data set quantity_in_stock = quantity_in_stock - ? where product_id = ? and dealer_id = ?",$orderItem['quantity'],$orderItem['product_id'],$dealerId);
	}
}

executeQuery("delete from shopping_cart_items where shopping_cart_id = ?",$shoppingCartId);

if ($returnArray['status'] == 'authorized') {
	switch($transactionProcessor) {
		case 1:
			$parameterList['transaction_identifier'] = $reponseArray['ssl_txn_id'];
			$parameterList['authorization_code'] = $reponseArray['ssl_approval_code'];
			break;
		case 2:
			$parameterList['transaction_identifier'] = $reponseArray[6];
			$parameterList['authorization_code'] = $reponseArray[4];
			break;
		case 3:
			$parameterList['transaction_identifier'] = $reponseArray[6];
			$parameterList['authorization_code'] = $reponseArray[4];
			break;
		case 4:
			$parameterList['transaction_identifier'] = $reponseArray[6];
			$parameterList['authorization_code'] = $reponseArray[7];
			break;
	}
	$parameterList['reference_number'] = substr($ccNumber,0,2) . "********" . substr($ccNumber,-4);

	//create an order payment
	$resultSet = executeQuery("insert into order_payments (order_payment_id,order_id,payment_date,settlement_date,payment_method_id," .
			"reference_number,amount,transaction_identifier,authorization_code,version) values (null,?,now(),now(),1,?,?,?,?,1)",$orderId,$parameterList['reference_number'],$ccAmount,$parameterList['transaction_identifier'],$parameterList['authorization_code']);
}
if ($returnArray['status'] == 'error') {
            $resultSet = executeQuery("insert into order_payments (order_payment_id,order_id,payment_date,settlement_date,payment_method_id," .
			"amount,version) values (null,?,now(),now(),1,?,1)",$orderId,$ccAmount);
}
if ($giftCardAmount > 0) {
	$resultSet = executeQuery("insert into order_payments (order_payment_id,order_id,payment_date,settlement_date,payment_method_id," .
		"reference_number,amount,transaction_identifier,authorization_code,version) values (null,?,now(),now(),2,?,?,?,'WGC',1)",$orderId,$giftCardNumber,$giftCardAmount,$giftCardMerchantNumber);
}

// create a shipment record?
if ($shippingMethodId > 1) { // 1 = hold, anthying else is ship
	$useNFDNShipping = getFieldFromId('use_nfdn_shipping','dealers','dealer_id',$dealerId);
	//if ($useNFDNShipping == 1) {
		$query  = "insert into shipments ";
		$query .= "(dealer_id,order_id,shipping_method_id,address_id,shipping_charge,insurance_charge,shipping_weight,signature_required,date_created,version) ";
		$query .= "values (?,?,?,?,?,?,?,?,now(),1)";
		$parameters = array();
		$parameters[] = $dealerId;
		$parameters[] = $orderId;
		$parameters[] = $shippingMethodId;
		$parameters[] = (empty($parameterList['shipping_address_id']) ? $parameterList['ffl_address_id'] : $parameterList['shipping_address_id']);
		$parameters[] = $parameterList['shipping_charge'];
		$parameters[] = $parameterList['insurance_charge'];
		$parameters[] = $parameterList['shipping_weight'];
		$parameters[] = ($shipFirearms + $shipHandguns + $shipHandguns > 0 ? 1 : 0);
		$insertSet = executeQuery($query,$parameters);
	//}
}

trackLog("-------- Shipping Charges --------");
trackLog("FedexEnabled : ". $useNFDNShipping);

if ($returnArray['status'] != 'error') {
        trackLog("Transaction Success");
	//commitTransaction();
	foreach ($_SESSION['cart_info'] as $arrayKey => $arrayValue) {
		unset($_SESSION['cart_info'][$arrayKey]);
	}
	unset($_SESSION['cart_info']);
	$returnArray['status'] = 'authorized';
	$returnArray['order_id'] = $orderId;
	$returnArray['token'] = $orderId . chr( rand(97,122) ) . md5(uniqid(rand(),true)); // mask order_id
} else {
        trackLog("Transaction Error");
	//rollbackTransaction();
	$content = "Order error: " . $dealerId;
	$to      = "system@nfdnetwork.com";
	$subject = "Order Error";
	$header  = "From: webadmin@nfdnetwork.com\r\n";     
	$header .= "Reply-To: webadmin@nfdnetwork.com\r\n";
	$header .= "X-Mailer: DT_formmail";
	mail($to, $subject, $content, $header);
}
// if(isset($_POST['isApruvd']) && ($_POST['isApruvd'] == 'true')) {

//  					$validationInsert = "insert into payment_validation (order_id, payment_validation_transaction_id, risk_score, status) ";
// 	$validationInsert .= "values (?,?,?,?)";
// 	$ValidationSet = executeQuery($validationInsert, $orderId, $resultArray['Created']['transaction_id'], $resultArray['Created']['risk_score'], $resultArray['Created']['status']);


// 			}

			

/*
if(isset($_POST['isApruvd']) && ($_POST['isApruvd'] == 'true')) {

$apruvdObj = new apruvdClass();
$OAuthMemcacheKey = "OAuth_Access_Token";   	
if (class_exists(Memcache) && $memcache = new Memcache()) {	
	foreach ($gMemcacheServers as $server) {
		$memcache->addServer($server);
	}            
	if($memcache->get($OAuthMemcacheKey)) {		
		$OAuthAccessToken = $memcache->get($OAuthMemcacheKey);
		trackLog("in memcache : " . $OAuthAccessToken);
	}	 
	else {
		$OAuthAccessToken = $apruvdObj->generateAccessToken($login, $password);
		$memcache->set($OAuthMemcacheKey, $OAuthAccessToken, false, 86400); 
		trackLog("not in memcache : " . $OAuthAccessToken); 
	}       
}

//Array for Payment information

$apruvdVariablesArr = array(
	"total" => $ccAmount,
	"currency" => "USD",
	"billing_first_name" => $firstName,
	"billing_address_1" => $_POST['cc_address'],
	"billing_postal" => $_POST['cc_zip'],
	"billing_country" => (empty($_POST['cc_country']) ? "USA" : $_POST['cc_country']),
	"type" => "Online",
	"mode" => "test",	
	"billing_last_name" => $lastName,
	"billing_company" => $dealerName,
	"billing_address_2" => '',
	"billing_city" => $_POST['cc_city'],
	"billing_state" => $_POST['cc_state'],
	"billing_region" => '',
	"billing_phone" => $_POST['phone_number'],
	"email" => $_POST['email_address'],
	"ip" => $ip_info,
	"shipping_first_name" => $firstName,
	"shipping_last_name" => $lastName,
	"shipping_company" => $dealerName,
	"shipping_address_1" => (empty($_POST['ship_address']) ? $_POST['cc_address'] : $_POST['ship_address']),
	"shipping_address_2" => '',
	"shipping_city" => (empty($_POST['ship_city']) ? $_POST['cc_city'] : $_POST['ship_city']),
	"shipping_postal" => (empty($_POST['ship_zip']) ? $_POST['cc_zip'] : $_POST['ship_zip']),
	"shipping_state" => (empty($_POST['ship_state']) ? $_POST['cc_state'] : $_POST['ship_state']),
	"shipping_region" => '',
	"shipping_country" => (empty($_POST['ship_country']) ? "USA" : $_POST['ship_country']),
	"first_six" => substr($ccNumber,0,6),
	"last_four" => substr($ccNumber,-4),
	"cvv" => '',
	"avs" => '',
	"invoice_id" => $orderId,
	"additional_1" => $dealerName,
	"additional_2" => $dealerId,
	"additional_3" => '',
	"ip" => $_SERVER['REMOTE_ADDR'],
	
);
	
$apruvdVariables = http_build_query($apruvdVariablesArr);

if($returnArray['status'] == 'authorized') {
	trackLog("-------------------------- ch1 --------------------------");
	$OAuthAccessToken = '';	
	if (isset($_SESSION['apruvd']['OAuthToken']) && !empty($_SESSION['apruvd']['OAuthToken'])) {
		
		$OAuthAccessToken = $_SESSION['apruvd']['OAuthToken'];
	} 
	$apruvdObj = new apruvdClass($OAuthAccessToken);
	$validationResult = $apruvdObj->validatePayment($apruvdVariables);
	$resultArray = json_decode($validationResult, true);
	trackLog(print_r($resultArray, true));
	$validationInsert = "insert into payment_validation (order_id, payment_validation_transaction_id, risk_score, status) ";
	$validationInsert .= "values (?,?,?,?)";
	$ValidationSet = executeQuery($validationInsert, $orderId, $resultArray['Created']['transaction_id'], $resultArray['Created']['risk_score'], $resultArray['Created']['status']);
}
}

*/
if($auto_order == 'Y' && $returnArray['status'] == 'authorized') {
	trackLog("-------------------------- ch2--------------------------");
	include_once "checkout_order-e.inc";
}

if($returnArray['status'] == 'authorized') {
	trackLog("-------------------------- ch3 --------------------------");
	include_once "prior_checkoutcomplete.inc";
	include_once "ignite_distributor_quantity.inc";
	$orderedItems = processIgnitequantity($orderId,$orderItems,$dealerId);
}

trackLog(print_r($ValidationSet, true));
trackLog(print_r($returnArray, true));

trackLog("-------------------------- Process Ended --------------------------");
echo json_encode($returnArray);
?>




