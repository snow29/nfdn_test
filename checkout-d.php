<?php
$gProgramCode = "CHECKOUT";
include_once "scpt/utilities.inc";
include_once "scpt/catalog_functions.inc";
function trackLog($message)
{
   $fp = fopen('/var/www/logs/transactionLog-'.date('Y-m-d').'.log', 'a');
   fwrite($fp, date('Y-m-d h:i:s a',time()).' '.$message."\r\n"); 
   fclose($fp);
}
trackLog("---------------- ORDER TRACKING AT CHECKOUT PAGE----------------");

if ($globalLoggedTimedOut == true) {
    //header("Location:http://".$dealerArray['dealer_url']);
}

//maintain same session id from dealer domain to secure NFDN domain
$sessionId = isset($_POST['sessionId']) ? $_POST['sessionId'] : $_SESSION['global_user_session_data'];


$shoppingCartDisabled = false;

//when sent from a dealer's domain, there will be a 'token' consisting 
//of the shopping cart id followed by a random string
if (!empty($_GET['token'])) {
    $tokenId = "";
    for ($i = 0; $i < strlen($_GET['token']); $i++) {
        $digit = substr($_GET['token'], $i, 1);
        if (is_numeric($digit)) {
            $tokenId .= $digit;
        } else {
            break;
        }
    }
    if (empty($tokenId)) {
        $shoppingCartDisabled = true;
    } else {
        $_SESSION["shopping_cart_id"] = getFieldFromId("shopping_cart_id", "shopping_carts", "shopping_cart_id", $tokenId);
    }
}
$token = $_GET['token'];
$shoppingCartId = $_SESSION["shopping_cart_id"];
$gDealerId = getFieldFromId('dealer_id', 'shopping_carts', 'shopping_cart_id', $shoppingCartId);

$dealerArray = getDealerInfo($gDealerId);

$_SESSION['global_user_session_data'] = getFieldFromId('session_id', 'sessions', 'session_id', $sessionId, 'dealer_id', $gDealerId);
$dealer_promotion_enabled = getFieldFromId('allow_dealer_promotion', 'dealers', 'dealer_id', $gDealerId);

if (isset($_GET['token']) && isset($_COOKIE['PHPSESSID'])) {
    setToken($token, $_COOKIE['PHPSESSID']);
    if (!checkToken($token, $_COOKIE['PHPSESSID'])) {
        header("Location:http://" . $dealerArray['dealer_url']);
    }
}




if (empty($shoppingCartId)) {
    $shoppingCartDisabled = true;
    $dealerArray = getDealerInfo($gDealerId);
    $dealerArray['dealer_url'] = getFieldFromId('domain_name', 'domain_names', 'dealer_id', $gDealerId);
    $templateArray = getTemplateInfo($gDealerId, $_GET['tmp']);
} else {
    // set dealer id using shopping cart since domain is currently nfdnetwork.com
    $gDealerId = getFieldFromId('dealer_id', 'shopping_carts', 'shopping_cart_id', $shoppingCartId);
    $dealerArray = getDealerInfo($gDealerId);
    $dealerArray['dealer_url'] = getFieldFromId('domain_name', 'domain_names', 'dealer_id', $gDealerId);
    $dealerArray['gift_card_merchant_id'] = getFieldFromId('gift_card_merchant_id', 'dealers', 'dealer_id', $gDealerId);
    $giftCardEnabled = (empty($dealerArray['gift_card_merchant_id']) ? "" : "Y");

    $resultSet = executeQuery("select * from dealer_discounts where dealer_id = ? and internal_use_only = 0 and inactive = 0", $gDealerId);
    $discountEnabled = ($resultSet['row_count'] > 0 ? "Y" : "");

    $preferenceId = getFieldFromId('preference_id', 'preferences', 'preference_code', 'STOREPICKUPONLY');
    $dealerArray['store_pickup_only'] = getFieldFromId('preference_value', 'dealer_preferences', 'dealer_id', $gDealerId, 'preference_id = ' . $preferenceId);
    $preferenceId = getFieldFromId('preference_id', 'preferences', 'preference_code', 'SHIPONLY');
    $dealerArray['ship_orders_only'] = getFieldFromId('preference_value', 'dealer_preferences', 'dealer_id', $gDealerId, 'preference_id = ' . $preferenceId);

    $templateArray = getTemplateInfo($gDealerId, $_GET['tmp']);

    // make sure items in cart are still available
    $resultSet = executeQuery("select * from shopping_cart_items where shopping_cart_id = ?", $shoppingCartId);
    while ($row = getNextRow($resultSet)) {
        $quantity = 0;
        $productInactive = getFieldFromId('inactive', 'products', 'product_id', $row['product_id']);
        if ($productInactive == 0) {
            // is it a dealer product
            $query = "select * from dealer_product_data where product_data_type = 1 and dealer_id = ? and product_id = ?";
            $quantitySet = executeQuery($query, $gDealerId, $row['product_id']);
            if ($row = getNextRow($quantitySet)) {
                if ($row['inactive'] == 0) {
                    $quantity = (empty($row['quantity_in_stock']) ? 0 : $row['quantity_in_stock']);
                }
            } else {
                $quantitySet = executeQuery("select sum(quantity) as quantity from distributor_inventory where product_id = ? and distributor_id in (select distributor_id from dealer_distributors where dealer_id = ? and inactive = 0)", $row['product_id'], $gDealerId);
                if ($quantityRow = getNextRow($quantitySet)) {
                    $quantity = $quantityRow['quantity'];
                }
            }
        }
        if ($quantity < 1) {
            // to do: display a message if item is no longer available
            $deleteSet = executeQuery("delete from shopping_cart_items where shopping_cart_id = ? and product_id = ?", $shoppingCartId, $row['product_id']);
        }
    }

    include_once "scpt/class.shoppingcart.php";
    $shoppingCart = new ShoppingCart($gDealerId);
    $shoppingCartId = $shoppingCart->getShoppingCart($shoppingCartId);

    if (!array_key_exists('cart_info', $_SESSION)) {
        $_SESSION['cart_info'] = array();
        $_SESSION['cart_info']['authorize_attempts'] = 0;
    }

    $_SESSION['cart_info']['shipping_preference'] = "";
    $_SESSION['cart_info']['shipping_rate_preference'] = "express";

    if (empty($_SESSION['cart_info']['cc_exp_month'])) {
        $_SESSION['cart_info']['cc_exp_month'] = date('m');
    }
    if (empty($_SESSION['cart_info']['cc_exp_year'])) {
        $_SESSION['cart_info']['cc_exp_year'] = date('Y');
    }

    $itemsInCart = $shoppingCart->getShoppingCartItems();

    if (count($itemsInCart) < 1) {
        $shoppingCartDisabled = true;
    }

    #################
    // adding multiple location dealers
    $dealerLocations = array();
    $resultSet = executeQuery("select * from dealers where master_dealer_id = ? and inactive = 0", $gDealerId);
    while ($row = getNextRow($resultSet)) {
        $dealerLocations[] = array('location_id' => $row['dealer_id'], 'location_name' => getFieldFromId('company_name', 'contacts', 'contact_id', $row['contact_id']));
    }
    if (count($dealerLocations) > 0) {
        if (empty($_SESSION['cart_info']['location_id'])) {
            $_SESSION['cart_info']['location_id'] = $dealerLocations[0]['location_id'];
        }
        if (empty($_SESSION['cart_info']['location_name'])) {
            $_SESSION['cart_info']['location_name'] = $dealerLocations[0]['location_name'];
        }
        foreach ($dealerLocations as $dealerLocation) {
            if ($dealerLocation['location_id'] == $_SESSION['cart_info']['location_id']) {
                $dealerLocationId = $dealerLocation['location_id'];
                $dealerLocationName = $dealerLocation['location_name'];
                break;
            }
        }
    } else {
        $dealerLocationId = $gDealerId;
        $dealerLocationName = $dealerArray['dealer_city'] . ", " . $dealerArray['dealer_state'];
    }

    $resultSet = executeQuery("select * from dealer_distributors where dealer_id = ? and auto_order = 1 and inactive = 0", $dealerLocationId);
    //trackLog(print_r($resultSet,true));
    $autoOrderEnabled = ($resultSet['row_count'] > 0 ? "Y" : "");

    $dealerTaxRate = getFieldFromId('sales_tax_rate', 'dealers', 'dealer_id', $dealerLocationId);

    $checkoutDisabled = false;
    $checkResult = executeQuery("select * from dealers where dealer_id = ?", $dealerLocationId);
    $checkRow = getNextRow($checkResult);
    switch ($checkRow['transaction_processor_id']) {
        case 1: // CPN
            if (empty($checkRow['ssl_merchant_identifier']) || empty($checkRow['ssl_user_identifier']) || empty($checkRow['ssl_pin'])) {
                $checkoutDisabled = true;
            }
            break;
        case 2: // Authnet
            if (empty($checkRow['authnet_login_identifier']) || empty($checkRow['authnet_transaction_key'])) {
                $checkoutDisabled = true;
            }
            break;
        case 3: // eProcessing
            if (empty($checkRow['eprocessing_login_identifier']) || empty($checkRow['eprocessing_transaction_key'])) {
                $checkoutDisabled = true;
            }
            break;
        case 4: // Skipjack
            if (empty($checkRow['slipjack_serial_number']) || empty($checkRow['slipjack_development_serial_number'])) {
                $checkoutDisabled = true;
            }
            break;
    }

    $countryCheckoutEnabled = getFieldFromId('country_checkout_enabled', 'dealers', 'dealer_id', $gDealerId) == 1;
    $useNFDNShippingEnabled = (getFieldFromId('use_nfdn_shipping', 'dealers', 'dealer_id', $gDealerId) == 1 ? "Y" : "");
    $useDealerShippingEnabled = (getFieldFromId('use_dealer_shipping', 'dealers', 'dealer_id', $gDealerId) == 1 ? "Y" : "");
} // else empty($shoppingCartId)
if ((!isset($_SESSION['global_user_session_data']) || empty($_SESSION['global_user_session_data'])) && $dealerArray['enable_global_login'] == 1) {
    header("Location:http://" . $dealerArray['dealer_url']);
}
$provinceArray = array('AB', 'BC', 'MB', 'NB', 'NL', 'NT', 'NS', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT');

$returnArray = array();
$urlAction = $_GET['url_action'];
switch ($urlAction) {
    //below case is added to display satellite checkout address
    case "select_location":
        $selectLoco = $_POST['selectLoco'];

        $dealerlocArray = getDealerInfo($selectLoco);

        $locArray['status'] = "success";
        $locArray['dealer_name'] = $dealerlocArray['dealer_name'];
        $locArray['dealer_address'] = $dealerlocArray['dealer_address'];
        $locArray['dealer_city'] = $dealerlocArray['dealer_city'];
        $locArray['dealer_state'] = $dealerlocArray['dealer_state'];
        $locArray['dealer_zip_code'] = $dealerlocArray['dealer_zip_code'];

        $locArray['map_href_link'] = "https://maps.google.com/maps?q=" . str_replace(array(" ", "#"), "+", $dealerlocArray['dealer_address']) . "," . str_replace(" ", "+", $dealerlocArray['dealer_city']) . "," . $dealerlocArray['dealer_state'] . "+" . $dealerlocArray['dealer_zip_code'] . "&z=14&output=embed";


        echo json_encode($locArray);
        exit;
    case "get_states":
        echo json_encode($stateArray);
        exit;

    case "get_provinces":
        echo json_encode($provinceArray);
        exit;

    case "get_insurance":
        if (empty($_POST['item_value'])) {
            $returnArray['status'] = "Value required.";
        } else {
            $insuranceSet = array();
            $query = "select * from preferences where preference_code = ?";
            $insuranceSet['SHIPINSURANCE_MINIMUM_CHARGE'] = array();
            $insuranceSet['SHIPINSURANCE_INCREMENT'] = array();
            $insuranceSet['SHIPINSURANCE_INCREMENT_AMOUNT'] = array();

            foreach ($insuranceSet as $preferenceCode => $preferenceSet) {
                $resultSet = executeQuery($query, $preferenceCode);
                if ($row = getNextRow($resultSet)) {
                    $insuranceSet[$preferenceCode]['preference_id'] = $row['preference_id'];
                    $insuranceSet[$preferenceCode]['preference_value'] = getFieldFromId('preference_value', 'system_preferences', 'preference_id', $row['preference_id']);
                }
            }
            $minimumAmount = $insuranceSet['SHIPINSURANCE_MINIMUM_CHARGE']['preference_value'];
            $increment = $insuranceSet['SHIPINSURANCE_INCREMENT']['preference_value'];
            $incrementAmount = $insuranceSet['SHIPINSURANCE_INCREMENT_AMOUNT']['preference_value'];
            $calculatedAmount = number_format(floor($_POST['item_value'] / $increment) * $incrementAmount, 2);
            $insuranceAmount = max($minimumAmount, $calculatedAmount);
            $returnArray['insurance_amount'] = $insuranceAmount;
            $returnArray['status'] = "success";
        }
        echo json_encode($returnArray);
        exit;

    case "get_charges":
        $additionalChargeIds = array();
        $additionalCharge = 0;
        $additionalChargeNote = "";
        // does this dealer have any charges?
        $parameters = array();
        if ($dealerLocationId > 0) {
            $query = "select * from dealer_charges where dealer_id = ? ";
            $parameters[] = $_GET['dealer_id'];
        } else {
            $query = "select * from dealer_charges where dealer_id = ? ";
            $parameters[] = $_GET['dealer_id'];
        }
        $query .= "and internal_use_only = 0 and inactive = 0";
        $resultSet = executeQuery($query, $parameters);
        if ($resultSet['row_count'] > 0) {
            // get all shipping groups represented
            $shippingGroupsInCart = array();
            foreach ($itemsInCart as $itemSet) {
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
                    if ($_GET['hold'] == "true" && $row['apply_hold'] == 1) {
                        $methodQualifies = true;
                    }
                    if ($_GET['hold'] != "true" && $row['apply_ship'] == 1) {
                        $methodQualifies = true;
                    }
                }
                if ($itemQualifies && $methodQualifies) {
                    $additionalChargeIds[] = $row['dealer_charge_id'];
                    $additionalCharge += $row['amount'];
                    $additionalChargeNote .= (empty($additionalChargeNote) ? "" : "<br>") . $row['description'] . " ($" . $row['amount'] . ")";
                }
            }
        }
        $returnArray['additional_charge_ids'] = implode("|", $additionalChargeIds);
        $returnArray['additional_charge_note'] = $additionalChargeNote;
        $returnArray['additional_charge'] = $additionalCharge;
        $returnArray['status'] = "success";
        echo json_encode($returnArray);
        exit;

    case "get_shipping":
        // pull shipping rates for this dealer
        $preferenceCodes = array(
            'SHIPFIREARM1ST' => 'ship_firearm_1st',
            'SHIPFIREARMADDL' => 'ship_firearm_addl',
            'SHIPAMMO1ST' => 'ship_ammo_1st',
            'SHIPAMMOADDL' => 'ship_ammo_addl',
            'SHIPITEM1ST' => 'ship_item_1st',
            'SHIPITEMADDL' => 'ship_item_addl'
        );
        $shippingRates = array();
        foreach ($preferenceCodes as $preferenceCode => $preferenceField) {
            $preferenceId = getFieldFromId('preference_id', 'preferences', 'preference_code', $preferenceCode);
            $preferenceValue = getFieldFromId('preference_value', 'dealer_preferences', 'preference_id', $preferenceId, 'dealer_id = ' . $_GET['dealer_id']);
            if (empty($preferenceValue)) {
                $preferenceValue = 0; //getFieldFromId('preference_value','dealer_preferences','preference_id',$preferenceId,'dealer_id = 1');
            }
            $shippingRates[$preferenceField] = $preferenceValue;
        }
        $returnArray['shipping_amount'] = 0;
        if ($_POST['firearms'] + $_POST['handguns'] > 0) {
            $returnArray['shipping_amount'] += ($shippingRates['ship_firearm_1st'] + (($_POST['firearms'] + $_POST['handguns'] - 1) * $shippingRates['ship_firearm_addl']));
        }
        if ($_POST['ammo'] > 0) {
            $returnArray['shipping_amount'] += ($shippingRates['ship_ammo_1st'] + (($_POST['ammo'] - 1) * $shippingRates['ship_ammo_addl']));
        }
        if ($_POST['items'] > 0) {
            $returnArray['shipping_amount'] += ($shippingRates['ship_item_1st'] + (($_POST['items'] - 1) * $shippingRates['ship_item_addl']));
        }
        $returnArray['status'] = "success";
        echo json_encode($returnArray);
        exit;

    case 'guest':
        $_SESSION['cart_info']['checkout_type'] = "guest";
        $returnArray['status'] = "success";
        echo json_encode($returnArray);
        exit;

    case 'login':
        if (empty($_POST['user_name']) || empty($_POST['password'])) {
            $returnArray['status'] = "Username and password are required.";
        }
        $passwordSalt = getFieldFromId("password_salt", "users", "user_name", strtolower($_POST['user_name']));
        $resultSet = executeQuery("select * from users where user_name = ? and inactive = 0 and password = ?", strtolower($_POST['user_name']), md5($passwordSalt . $_POST['password']));
        if ($row = getNextRow($resultSet)) {
            executeQuery("update users set last_login = now() where user_id = ?", $row['user_id']);
            executeQuery("insert into security_log (log_id,security_log_type,user_name,ip_address,log_entry,entry_time,version) values " .
                    "(null,'LOGIN',?,?,'Log In succeeded',now(),1)", strtolower($_POST['user_name']), $_SERVER['REMOTE_ADDR']);
            login($row['user_id']);
            $returnArray['status'] = "login";
            $_SESSION['cart_info']['checkout_type'] = "account";
        } else {
            executeQuery("insert into security_log (log_id,security_log_type,user_name,ip_address,log_entry,entry_time,version) values " .
                    "(null,'LOGIN-FAILED',?,?,'Log In failed',now(),1)", strtolower($_POST['user_name']), $_SERVER['REMOTE_ADDR']);
            $returnArray['status'] = "The user name , password combination does not match. Try again.";
        }
        $returnArray['timeout'] = $globalLoggedTimedOut;
        echo json_encode($returnArray);
        exit;
    case 'forgot':
        if (empty($_POST['forgot_user_name'])) {
            $returnArray['status'] = "Username required";
        }
        $userName = $_POST['forgot_user_name'];
        $query = "select * from users where user_name = ? and locked = 0 and security_question_id = ? and answer_text = ?";
        $resultSet = executeQuery($query, $userName, $_POST['forgot_security_question_id'], $_POST['forgot_answer_text']);
        if ($userRow = getNextRow($resultSet)) {
            executeQuery("delete from forgot_data where user_id = ? or time_requested < (now() - interval 30 minute)", $userRow['user_id']);
            $forgotKey = md5(uniqid(rand(), true));
            $query = "insert into forgot_data (forgot_data_id,forgot_key,user_id,ip_address,time_requested,version) values (null,?,?,?,now(),1)";
            $forgotResultSet = executeQuery($query, $forgotKey, $userRow['user_id'], $_SERVER['REMOTE_ADDR']);
            $query = "insert into security_log (log_id,security_log_type,user_name,ip_address,log_entry,entry_time,version) values ";
            $query .= "(null,'FORGOT-PASSWORD',?,?,'Forgot password email sent',now(),1)";
            $SecurityResultSet = executeQuery($query, $userRow['user_name'], $_SERVER['REMOTE_ADDR']);

            $emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $userRow['contact_id']);
            if (empty($emailAddress)) {
                $returnArray['message'] = "Email address not found!";
                $returnArray['status'] = "fail";
                break;
            } else {
                // send the email
                $mail = new PHPMailer();
                $mail->IsMail();
                $mail->IsHTML(true);
                $mail->SetFrom("system@nfdnetwork.com", "NFDNetwork");
                $mail->AddReplyTo("system@nfdnetwork.com", "NFDNetwork");
                $mail->AddAddress($emailAddress);
                $mail->AddBCC("system@nfdnetwork.com", "NFDN Webmaster");
                $mail->Subject = "NFDN Password Reset Request";
                $tags_open = "<html><body style='padding: 12px; font-family: Helvetica, Arial, sans-serif; font-size: 14px;'>";
                $intro = "<h1 style='font-size: 21px;'>NFDN Password Reset Request</h1>";
                $details = array();
                $details[] = "<p>A password reset request for username <b>" . $userRow['user_name'] . "</b> was submitted on " . date('m/d/Y') . " at " . date('g:i a') . ".</p>";
                $details[] = "<p>Use the following link to reset your password...</p>";
                $details[] = "<p></p>";
                $details[] = "<p>http://" . $dealerArray['dealer_url'] . "/resetpassword.php?key=" . $forgotKey . "</p>";
                $details[] = "<p></p>";
                $details[] = "<p>This link is only active for 30 minutes and only from this computer.</p>";
                $details[] = "<p></p>";
                $details[] = "<p>If you did not authorize this reset request, please contact NFDN Customer Service.</p>";
                $tags_close = "</body></html>";
                $mail->Body = $tags_open . "\n" . $intro . "\n" . implode("\n", $details) . "\n" . $tags_close;
                $mail->AltBody = implode("\n", $details);

                if ($mail->Send()) {
                    $returnArray['message'] = "An email has been sent to the email address on file.<br>Follow the instructions in the email.";
                    $returnArray['status'] = "success";
                } else {
                    $returnArray['message'] = "There was an error sending an email -- please try again.";
                    $returnArray['status'] = "fail";
                }
            }
        } else {
            $returnArray['message'] = "Invalid Credentials. Please contact customer service.";
            $query = "insert into security_log (log_id,security_log_type,user_name,ip_address,log_entry,entry_time,version) values ";
            $query .= "(null,'FORGOT-PASSWORD-FAIL',?,?,'Forgot password failure',now(),1)";
            $resultSet = executeQuery($query, $userRow['user_name'], $_SERVER['REMOTE_ADDR']);
            $returnArray['status'] = "fail";
            # lock user out if too many password fails

            if (getFieldFromId("locked", "users", "user_name", $userName) != 1) {
                $resultSet = executeQuery("select count(*) from security_log where user_name = ? and security_log_type = 'FORGOT-PASSWORD-FAIL' and entry_time > (now() - interval 30 minute)", $_POST['forgot_user_name']);
                if ($row = getNextRow($resultSet)) {
                    if ($row[0] >= 15) {
                        executeQuery("update users set locked = 1 where user_name = ?", $_POST['forgot_user_name']);
                        executeQuery("insert into security_log (log_id,security_log_type,user_name,ip_address,log_entry,entry_time,version) values (null,'LOCKED',?,?,'User locked out because of too many failed forgot password attempts',now(),1)", $_POST['forgot_user_name'], $_SERVER['REMOTE_ADDR']);
                    }
                }
            }

            # if an ip address fails more than 15 times in 30 minutes, lock that IP address

            $resultSet = executeQuery("select count(*) from security_log where ip_address = ? and security_log_type = 'FORGOT-PASSWORD-FAIL' and entry_time > (now() - interval 30 minute)", $_SERVER['REMOTE_ADDR']);
            if ($row = getNextRow($resultSet)) {
                if ($row[0] >= 15) {
                    executeQuery("insert into rejected_ip_addresses (rejected_ip_address,ip_address,version) values (null,?,1)", $_SERVER['REMOTE_ADDR']);
                    executeQuery("insert into security_log (log_id,security_log_type,user_name,ip_address,log_entry,entry_time,version) values (null,'IP-REJECTED',?,?,'IP Address rejected because of too many failed forgot password attempts',now(),1)", $_POST['forgot_user_name'], $_SERVER['REMOTE_ADDR']);
                }
            }
        }
        echo json_encode($returnArray);
        exit;
    case 'account':
        if (empty($_POST['user_name']) || empty($_POST['password'])) {
            $returnArray['status'] = "Username and password are required.";
        }
        if (empty($_POST['email'])) {
            $returnArray['status'] = "Email required.";
        }
        $userName = $_POST['user_name'];
        $resultSet = executeQuery("select * from users where user_name = ?", $userName);
        if ($row = getNextRow($resultSet)) {
            $returnArray['message'] = "That user name is not available - please choose a different user name.";
            $returnArray['status'] = "fail";
        } else {
            startTransaction();
            //$resultSet = executeQuery("insert into contacts (contact_id,version) values (null,1)");
            $resultSet = executeQuery("insert into contacts (contact_id,email_address,version) values (null,?,1)", $_POST['email']);
            $contactId = $resultSet['insert_id'];
            if ($contactId > 0) {
                $passwordSalt = getRandomString();
                $resultSet = executeQuery("insert into users (user_id,dealer_id,contact_id,user_name,password_salt," .
                        "password,security_question_id,answer_text,date_created,version) values (null,?,?,?,?,?,?,?,now(),1)", $gDealerId, $contactId, $userName, $passwordSalt, md5($passwordSalt . $_POST['password']), $_POST['security_question_id'], $_POST['answer_text']);
                if (!empty($resultSet['sql_error'])) {
                    $returnArray['message'] = "Unable to create new account.";
                    $returnArray['status'] = "fail";
                    rollbackTransaction();
                } else {
                    $userId = $resultSet['insert_id'];
                    commitTransaction();
                    login($userId);
                    $returnArray['status'] = "success";
                    $_SESSION['cart_info']['checkout_type'] = "new_account";
                }
            } else {
                rollbackTransaction();
                $returnArray['message'] = "Unable to create new account.";
                $returnArray['status'] = "fail";
            }
        }
        echo json_encode($returnArray);
        exit;

    case 'quantity':
        if ($_POST['quantity'] == 0) {
            $shoppingCart->removeItem($_POST['product_id']);
        } else {
            $shoppingCart->addItem($_POST['product_id'], $_POST['quantity'], true);
        }
        $returnArray['status'] = "success";
        echo json_encode($returnArray);
        exit;

    case 'save':

        $_SESSION['cart_info']['tax_charge'] = $_POST['tax_charge'];
        $_SESSION['cart_info']['shipping_charge'] = $_POST['shipping_charge'];
        $_SESSION['cart_info']['additional_charge'] = $_POST['additional_charge'];
        $_SESSION['cart_info']['ship_to_bill'] = $_POST['ship_to_bill'];
        $_SESSION['cart_info']['ship_firearms'] = $_POST['ship_firearms'];
        $_SESSION['cart_info']['ship_nonfirearms'] = $_POST['ship_nonfirearms'];
        if (!empty($_POST['email'])) {
            $_SESSION['cart_info']['email_address'] = $_POST['email'];
        } else {
            $_SESSION['cart_info']['email_address'] = $_POST['email_address'];
        }
        $_SESSION['cart_info']['phone_number'] = $_POST['phone_number'];
        $_SESSION['cart_info']['notes'] = $_POST['notes'];
        $_SESSION['cart_info']['cc_name'] = $_POST['cc_name'];
        $_SESSION['cart_info']['cc_address'] = $_POST['cc_address'];
        $_SESSION['cart_info']['cc_city'] = $_POST['cc_city'];
        $_SESSION['cart_info']['cc_state'] = $_POST['cc_state'];
        $_SESSION['cart_info']['cc_zip'] = $_POST['cc_zip'];
        $_SESSION['cart_info']['cc_country'] = $_POST['cc_country'];
        $_SESSION['cart_info']['cc_exp_month'] = $_POST['cc_exp_month'];
        $_SESSION['cart_info']['cc_exp_year'] = $_POST['cc_exp_year'];
        $_SESSION['cart_info']['order_total'] = $_POST['order_total'];

        $_SESSION['cart_info']['gift_card_number'] = $_POST['gift_card_number'];
        $_SESSION['cart_info']['discount_code'] = $_POST['discount_code'];
        $_SESSION['cart_info']['add_shipping_insurance'] = $_POST['add_shipping_insurance'];

        $_SESSION['cart_info']['customer_shipping_address_validated'] = $_POST['customer_shipping_address_validated'];
        $_SESSION['cart_info']['ship_name'] = $_POST['ship_name'];
        $_SESSION['cart_info']['ship_address'] = $_POST['ship_address'];
        $_SESSION['cart_info']['ship_city'] = $_POST['ship_city'];
        $_SESSION['cart_info']['ship_state'] = $_POST['ship_state'];
        $_SESSION['cart_info']['ship_zip'] = $_POST['ship_zip'];
        $_SESSION['cart_info']['ship_country'] = $_POST['ship_country'];
        $_SESSION['cart_info']['ship_address_type'] = $_POST['customer_shipping_address_type'];

        $_SESSION['cart_info']['ffl_shipping_address_validated'] = $_POST['ffl_shipping_address_validated'];
        $_SESSION['cart_info']['ffl_name'] = $_POST['ffl_name'];
        $_SESSION['cart_info']['ffl_address'] = $_POST['ffl_address'];
        $_SESSION['cart_info']['ffl_city'] = $_POST['ffl_city'];
        $_SESSION['cart_info']['ffl_state'] = $_POST['ffl_state'];
        $_SESSION['cart_info']['ffl_zip'] = $_POST['ffl_zip'];
        $_SESSION['cart_info']['ffl_country'] = $_POST['ffl_country'];
        $_SESSION['cart_info']['ffl_phone'] = $_POST['ffl_phone'];
        $_SESSION['cart_info']['ffl_address_type'] = $_POST['ffl_shipping_address_type'];

        $returnArray['status'] = 'saved';

        echo json_encode($returnArray);
        exit;

    case 'location':
        $_SESSION['cart_info']['location_id'] = $_POST['location_id'];
        $_SESSION['cart_info']['location_name'] = $_POST['location_name'];
        $resultSet = executeQuery("select * from dealer_distributors where dealer_id = ? and auto_order = 1 and inactive = 0", $_POST['location_id']);
        $returnArray['auto_order_enabled'] = ($resultSet['row_count'] > 0 ? "Y" : "");
        $returnArray['tax_rate'] = getFieldFromId('sales_tax_rate', 'dealers', 'dealer_id', $_POST['location_id']);
        $returnArray['message'] = 'location updated';
        $returnArray['status'] = "success";
        echo json_encode($returnArray);
        exit;

    case 'get_discount':
        $discountCode = $_POST['discount_code'];
        $_SESSION['cart_info']['discount_code'] = "";
        $eligibleDiscounts = 0;
        if (empty($discountCode)) {
            $returnArray['message'] = "Invalid discount code.";
            $returnArray['status'] = "error";
        } else {
            $query = "select * from dealer_discounts where dealer_id = ? and discount_code = ? ";
            $query .= "and ((start_date <= now() or start_date is null) and (end_date >= now() or end_date is null))";
            $resultSet = executeQuery($query, $gDealerId, $discountCode);
            if ($resultSet['row_count'] > 0) {
                while ($row = getNextRow($resultSet)) {
                    $returnArray['minimum_purchase_amount'] = $row['minimum_purchase_amount']; // applyDiscount will use this
                    switch ($row['discount_type_id']) {
                        case 1: // product
                            foreach ($itemsInCart as $itemSet) {
                                $productId = $itemSet['product_id'];
                                if ($productId == $row['discount_type_value']) {
                                    if ($row['discount_amount'] > 0) {
                                        $returnArray['products'][$productId] = array("discount", $row['discount_amount']);
                                        $eligibleDiscounts++;
                                    } elseif ($row['override_amount'] > 0) {
                                        $returnArray['products'][$productId] = array("override", $row['override_amount']);
                                        $eligibleDiscounts++;
                                    } elseif ($row['discount_percent'] > 0) {
                                        $returnArray['products'][$productId] = array("percent", $row['discount_percent']);
                                        $eligibleDiscounts++;
                                    }
                                }
                            }
                            break;
                        case 2: // category
                            // any eligible products?  if so, add $returnArray['products'][$productId] array
                            break;
                        case 3: // department
                            // any eligible products?  if so, add $returnArray['products'][$productId] array
                            break;
                        case 4: // manufacturer
                            // any eligible products?  if so, add $returnArray['products'][$productId] array
                            break;
                        case 5: // cart
                            // $returnArray['cart'] = discount_amount OR discount_percent
                            break;
                        case 6: // shipping
                            // $returnArray['shipping'] = 1;
                            break;
                    }
                }
                if ($eligibleDiscounts > 0) {
                    $_SESSION['cart_info']['discount_code'] = $discountCode;
                    $returnArray['discounts'] = $eligibleDiscounts;
                    $returnArray['status'] = "success";
                } else {
                    $returnArray['message'] = "Order not eligible for this discount.";
                    $returnArray['status'] = "error";
                }
            } else {
                $returnArray['message'] = "Discount code note found.";
                $returnArray['status'] = "error";
            }
        }
        echo json_encode($returnArray);
        exit;
        
    case 'promotions':      
        $cartItems = explode("|",$_GET['cartItems']);
        $promotionDepartments = array();
        $promoDetail = array(); 
        unset($cartItems[0]);
        $promotionalChargeNote = "";
         if($dealer_promotion_enabled == 1){
             $resultSet = executeQuery("select * from dealer_promotions where dealer_id = ? and inactive = 0 and CURDATE() between start_date and end_date", $gDealerId);
             while($promotionRow = getNextRow($resultSet)){                 
                 $promotionDepartments[$promotionRow['dealer_promotion_id']]['description'] = $promotionRow['description'];
                 $promotionDepartments[$promotionRow['dealer_promotion_id']]['discount'] = $promotionRow['discount_rate'];
                 $promotionDepartments[$promotionRow['dealer_promotion_id']]['department_id'] = $promotionRow['department_id'];              
            }
             foreach($cartItems as $cartItem){
                 $ItemDetail = explode(":",$cartItem);
                 $cartItemCat = getFieldFromId("category_id","products","product_id",$ItemDetail[0]);
                 $cartItemDept = getFieldFromId("department_id","categories","category_id",$cartItemCat);
                 foreach($promotionDepartments as $promotion_id => $detail){
                     if($detail['department_id'] == $cartItemDept)                   {                  
                         $discountRate = round($ItemDetail[1]*$detail['discount'] /100,2);
                         if(!isset($promoDetail[$promotion_id]['description'])){
                             $promoDetail[$promotion_id]['description'] = $detail['description'];
                             $promoDetail[$promotion_id]['discount_amount'] = $discountRate;
                         }else{                                                  
                             $promoDetail[$promotion_id]['discount_amount'] = $promoDetail[$promotion_id]['discount_amount'] + $discountRate;                            
                         }                                           }
                    }                
             }
             foreach($promoDetail as $promotion_id => $detail){
                 $promotionalChargeNote .= (empty($promotionalChargeNote) ? "" : "<br>") . $detail['description'] . " : $" . $detail['discount_amount'] ;   
             }
             if($promotionalChargeNote == null){                      
                 $returnArray['status'] = "NA"; 
                 $returnArray['promotional_charge_note'] = 0;   
             }else {
                 $returnArray['promotional_charge_note'] = $promotionalChargeNote;        
                 $returnArray['status'] = "success";
             }
             
         }
         else {             
             $returnArray['status'] = "NoCharge";
         }
        
        echo json_encode($returnArray);
        exit;
        
    case 'timeout':
        $returnArray['timeout'] = $globalLoggedTimedOut;
        echo json_encode($returnArray);
        $globalLoggedTimedOut == true ? exit : '';
}

if ($gLoggedIn) {
    $resultSet = executeQuery("select * from contacts where contact_id = (select contact_id from users where user_id = ?)", $gUserId);
    $contactRow = getNextRow($resultSet);
    if (!is_array($_SESSION['cart_info'])) {
        $_SESSION['cart_info'] = array();
    }
    if (!array_key_exists("cc_name", $_SESSION['cart_info'])) {
        $_SESSION['cart_info']['cc_name'] = trim($contactRow['first_name'] . " " . $contactRow['last_name']);
    }
    $prefilledFields = array("email_address" => "email_address", "phone_number" => "phone_number", "cc_address" => "address_1", "cc_city" => "city",
        "cc_state" => "state", "cc_zip" => "zip_code");
    foreach ($prefilledFields as $cartField => $contactField) {
        if (!array_key_exists($cartField, $_SESSION['cart_info'])) {
            $_SESSION['cart_info'][$cartField] = $contactRow[$contactField];
        }
    }
    $resultSet = executeQuery("select * from addresses where contact_id = ? and address_type_id = (select address_type_id from address_types where address_type_code = 'SHIP') order by address_id desc", $contactRow['contact_id']);
    if (!$addressRow = getNextRow($resultSet)) {
        $addressRow = array();
    }
    $prefilledFields = array("ship_name" => "full_name", "ship_address" => "address_1", "ship_city" => "city", "ship_state" => "state", "ship_zip" => "zip_code");
    foreach ($prefilledFields as $cartField => $contactField) {
        $_SESSION['cart_info'][$cartField] = $addressRow[$contactField];
    }
    $resultSet = executeQuery("select * from addresses where contact_id = ? and address_type_id = (select address_type_id from address_types where address_type_code = 'FFL') order by address_id desc", $contactRow['contact_id']);
    if (!$addressRow = getNextRow($resultSet)) {
        $addressRow = getNextRow($resultSet);
    }
    $prefilledFields = array("ffl_name" => "full_name", "ffl_address" => "address_1", "ffl_city" => "city", "ffl_state" => "state", "ffl_zip" => "zip_code", "ffl_phone" => "phone_number");
    foreach ($prefilledFields as $cartField => $contactField) {
        $_SESSION['cart_info'][$cartField] = $addressRow[$contactField];
    }
}

//POBF - 108, Meta tags
$metaTagArray = array();
$query = "select * from dealer_meta_tags where dealer_id = ? ";
$resultSet = executeQuery($query, $gDealerId);
while ($row = getNextRow($resultSet)) {
    $metaTagArray['title'] = $row['title'];
    $metaTagArray['description'] = $row['description'];
    $metaTagArray['keyword'] = $row['keyword'];
    $metaTagArray['istitleinchild'] = $row['istitleinchild'];
    $metaTagArray['isdescriptioninchild'] = $row['isdescriptioninchild'];
    $metaTagArray['iskeywordinchild'] = $row['iskeywordinchild'];
}
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta id="Viewport" name="viewport" content="width=device-width,initial-scale=1"> 
        <!-- POBF - 108, Meta Tag Update -->
        <title><?php echo ($metaTagArray['istitleinchild'] == '1' ? $metaTagArray['title'] : $metaTagArray['title']);
if (empty($metaTagArray['title'])) {
    echo $dealerArray['dealer_name'];
}
?></title>
        <meta name="description" content=<?php echo($metaTagArray['isdescriptioninchild'] == '1' ? "Checkout - " . $metaTagArray['description'] : $metaTagArray['description']);
if (empty($metaTagArray['description'])) {
    echo "Checkout - " . $dealerArray['dealer_name'] . " - " . $dealerArray['site_description'];
}
?>>
        <!-- POBF - 108, Meta Tag Update -->
        <link rel="stylesheet" href="templates/default/universal-styles-v5.css">
        <link rel="stylesheet" href="<?php echo $templateArray['path'] ?>/styles-v1.css">
        <link rel="stylesheet" href="<?php echo $templateArray['path'] ?>/checkout-d.css">
        <link rel="stylesheet" href="scpt/custom-theme/jquery-ui.css">
        <link rel="stylesheet" href="scpt/fancybox/jquery.fancybox.css">
       
        <style>
            #login_message { font-size: 11px; font-style: italic; color: #ae2c2c; text-align: center; display: none; }
            #account_create_message { font-size: 11px; font-style: italic; color: #ae2c2c; text-align: center; display: none; }
            #login_account, #cancel_login_account, #create_account, #cancel_create_account { font-size: 12px; }
            #process_loading { margin: 12px 0 0 0; text-align: center;}
            #process_close { display: none; }
            #process_message,.radio_items { margin: 12px; font-size: 15px; line-height: 27px; color: #666; font-style: italic; text-align: center;   }
            #additional_charge_note { padding: 3px 12px; color: #666; text-align: center; font-size: 11px; }
            #promotional_charge_note { padding: 3px 12px; color: #666; text-align: center; font-size: 11px; }
            #processor_div,#subscriber_window { width: 500px;text-align: center; }
            #faq_div { width: 500px;text-align: center; }
            .processor_div { font-family: Helvetica, Arial, sans-serif; font-size: 14px; color: #333;text-align: center;}
            .process_text { font-size: 15px; color: #666; }
            .confirm, .cancel { font-size: 12px }
            #welcomenote{
                float: right;vertical-align: middle;text-align: right;width: 100%; display: inline;font-family: Helvetica, Arial, sans-serif;letter-spacing: 0.75px; font-size: 14px;background: #2b4047;
            }
            span.radio_span {
             padding-right: 10px;
            }
            .anchor_text{font-size: 12px;border-bottom:0px;padding:0px;cursor: pointer;}
            .popup_head{text-align: center;}
            .fixed-dialog{position: fixed;top: 50px;left: 50px;z-index: 1100;}
            .ui-button-icon-only .ui-icon{left:0%;top:0%}
            .ui-widget-header{background: #a11300}
            .ui-dialog .ui-dialog-title{color:#fff;}
            .ui-widget-overlay { position: fixed;z-index: 1050;}
            .ui-dialog{z-index: 11000;}
            .error{text-align:center; position: absolute;} 
            .content{font-size: 14px;}
            .popup_detail{font-size: 14px;color: #8d3a1d;}
            #faq_close_btn{margin-left:46%;position: relative;}
        div#subscriber_window {
        background-image: url(templates/default/henry-subscribe.png);
        height: 400px;
        background-repeat: no-repeat;
        background-size: cover;
        width: 100%;
        background-position: -28px;
            }
        #sub_info_confirm {
                  -webkit-border-radius: 8;
                  -moz-border-radius: 8;
                  border-radius: 8px;
                  font-family: Arial;
                  color: #000000;
                  font-size: 14px;
                  font-weight: 500;
                  background: #f2f0f2;
                  padding: 0.3em 1.3em;
                  border: solid #D4D4D4 0.1em;
                  text-decoration: none;
                  position: relative;
              }

              #sub_info_confirm:before {
                  content: " ";
                  position: absolute;
                  top: -2px;
                  left: -2px;
                  right: -2px;
                  bottom: -2px;
                  border: .1em solid #B5B5B5;
                  border-radius: 9px;
              }


          .henry .ui-button-text
        {
            -webkit-border-radius: 7;
            -moz-border-radius: 7;
            border-radius: 7px;
            border: solid #D4D4D4 0.1em;    
            padding: 5px 25px !important;   
        }
          .henry span.ui-button-text:hover {
            border: 1px solid #B5B5B5;
        }
        .sub_close {
            position: absolute;
            right: 0;
            border: 1px solid #333;
            padding: 0px 2px;
            cursor: pointer;
        }
            .henry_img{position: absolute;bottom: 0px;right: 0px;}
            .henry{width: 50%;
        float: left;
        margin-top: 28%;
        position: relative;}
            .henry_radio{padding-right: 12x;margin: 12px;font-size: 15px; font-weight:500; line-height: 43px;color: #000; text-align: center;}
            .henry_popup_head{margin: 20px 0px 27px 62px; padding: 13px 0 8px 8px;font-size: 17px;color: #8d3a1d;border-bottom: 1px solid #999999;border-top: 1px solid #999999;text-align: center;width: 364px;}
            span.henry_radio_span{padding-right: 12px;}
            #subscriber_window input[type='radio'] {
            -webkit-appearance:none;
        width:15px;
            height:15px;
            border:1px solid darkgray;
            border-radius:50%;
            outline:none;
            box-shadow:0 0 1px 0px gray inset;
        }
    #subscriber_window input[type='radio']:hover {
        box-shadow:0 0 5px 0px orange inset;
        }
    #subscriber_window input[type='radio']:before {
        content:'';
        display:block;
        width:60%;
        height:60%;
        margin: 20% auto;    
        border-radius:50%;    
        }
    #subscriber_window input[type='radio']:checked:before {
        background:orange;
        }

        </style>

        <style type="text/css">
/* iPhone 6 ----------- */
@media screen and (min-width : 0px) and (max-width : 950px){
/* Styles */

.GearFire-Marketing-Skin-584ef790dd287 .floating_tab
    {
        postion:fixed!important;
    }
    .type4.interact_box
    {
      postion:fixed!important;
    }

}
</style>

        <script type="text/javascript">
            if (top != self) {
                top.location.href = self.location.href;
            }
        </script>
        <script src="scpt/jquery.js"></script>
        <script src="scpt/jquery-ui.js"></script>
        <script src="scpt/checkout-b.js"></script>
        <script src="scpt/fancybox/jquery.fancybox.js"></script>
        
        <?php if (file_exists($templateArray['path'] . "/custom_checkout.js")) { ?>
            <script src="<?php echo $templateArray['path'] ?>/custom_checkout.js"></script>
        <?php } ?>
        <!--[if lt IE 9]>
        <script src="scpt/modernizr-2.0.6.js"></script>
        <![endif]-->
        <?php include_once "scpt/google_code.inc"; ?>


<script type="text/javascript">
$(document).ready(function(){
    //alert(screen.width);
if(screen.width <= 800) {
$(function(){
if( /Android|webOS|BlackBerry|iPod touch|iTunes/i.test(navigator.userAgent)) {
   // alert("inner");
  

  var ww = ( $(window).width() < window.screen.width ) ? $(window).width() : window.screen.width; //get proper width
  var mw = 1000; // min width of site
  var ratio =  ww / mw; //calculate ratio
  if( ww < mw){ //smaller than minimum size
    $('#Viewport').attr('content', 'initial-scale=' + ratio + ', minimum-scale=' + ratio + ', user-scalable=yes, width=' + ww);
   //$('#Viewport').attr('content','initial-scale=1.0,maximum-scale=2, user-scalable=yes, width=' + ww);
  }else{ //regular size
   $('#Viewport').attr('content', 'initial-scale=1.0, maximum-scale=2, minimum-scale=1.0, user-scalable=yes, width=' + ww);
  }
}
});
} 
// else {

// }
});

</script>
  
    </head>
    <body>

    <!--  <script src="//istage.juststicky.com/js/embed/client.js/2116/interact_5841e556a4cec" id="interact_5841e556a4cec" data-text="Discuss this with Sticky Interact" data-unique="5841e556a4cec"></script> --> 

<!-- <script id="interact_58879c4560081" data-unique="58879c4560081" data-text="Discuss this with Sticky Interact" src="//interact.juststicky.com/js/embed/client.js/2113/interact_58879c4560081"></script> -->

<script src="//istage.juststicky.com/js/embed/client.js/2115/interact_588c116cf16db" id="interact_588c116cf16db" data-text="Discuss this with Sticky Interact" data-unique="588c116cf16db"></script>

        <?php
        if ($dealerArray['enable_global_login'] == 1 && (isset($_SESSION["global_user_session_data"]) && !empty($_SESSION["global_user_session_data"]))) {
            $globalUsername = getFieldFromId('session_data', 'sessions', 'session_id', $_SESSION["global_user_session_data"]);
            ?>
            <div id="welcomenote">
                <div class="wel-wrap">


    
                    <span class="welcome" style="vertical-align: middle; margin-right: 0px;color:#fff">Welcome, <?php echo $globalUsername; ?></span>                                       
                    <span style="color: #fff;" class="sep">&nbsp;&nbsp;|&nbsp;&nbsp;</span>
                    <a id="gLogout" href="http://<?php echo $dealerArray['dealer_url'] ?>/globallogout.php?zy=<?php echo $_SESSION['global_user_session_data']; ?>" style="color:#fff;float:right;vertical-align: bottom; margin-right: 10px;">Logout</a>
                </div>
            </div>
            <?php }
        ?>
        <input type="hidden" id="site_dealer_id" value="<?php echo $gDealerId ?>" />
<?php include_once (empty($templateArray['header']) ? "templates/default" : $templateArray['header']) . "/header.inc"; ?>

        <table cellspacing="0" cellpadding="0" width="100%"><tr>
                <td valign="top">
<?php include_once (empty($templateArray['checkout_sidebar']) ? "templates/default" : $templateArray['checkout_sidebar']) . "/checkout_sidebar.inc"; ?>
                </td>
                <td id="checkout_main">
                    <div class="checkout_content">


                        <input type="hidden" id="dealer_id" value="<?php echo $gDealerId ?>" class="info">
                        <input type="hidden" id="shopping_cart_id" value="<?php echo $shoppingCartId ?>" class="info">
                        <input type="hidden" id="g_php_self" value="<?php echo $_SERVER['PHP_SELF'] ?>" class="info">
                        <input type="hidden" id="financecheckout_self" value="financecheckout.php" class="info">
                        <input type="hidden" id="dealer_location_id" value="<?php echo $dealerLocationId ?>" class="info">
                        <input type="hidden" id="dealer_tax_rate" value="<?php echo $dealerTaxRate ?>" class="info">
                        <input type="hidden" id="dealer_name" value="<?php echo $dealerArray['dealer_name'] ?>" class="info">
                        <input type="hidden" id="dealer_address" value="<?php echo $dealerArray['dealer_address'] ?>" class="info">
                        <input type="hidden" id="dealer_city" value="<?php echo $dealerArray['dealer_city'] ?>" class="info">
                        <input type="hidden" id="dealer_state" value="<?php echo $dealerArray['dealer_state'] ?>" class="info">
                        <input type="hidden" id="dealer_zip_code" value="<?php echo $dealerArray['dealer_zip_code'] ?>" class="info">
                        <input type="hidden" id="dealer_phone_number" value="<?php echo $dealerArray['phone_number'] ?>" class="info">
                        <input type="hidden" id="dealer_url" value="<?php echo $dealerArray['dealer_url'] ?>" class="info">
                        <input type="hidden" id="shipping_charge" value="" class="info">
                        <input type="hidden" id="shipping_weight" value="" class="info">
                        <input type="hidden" id="express_shipping_code" value="" class="info">
                        <input type="hidden" id="ground_shipping_code" value="" class="info">
                        <input type="hidden" id="additional_charge" value="" class="info">
                        <input type="hidden" id="additional_charge_ids" value="" class="info">
                        <input type="hidden" id="tax_charge" value="" class="info">
                        <input type="hidden" id="order_total" value="" class="info">
                        <input type="hidden" id="order_total_a" value="" class="info">
                        <input type="hidden" id="gift_card_enabled" value="<?php echo $giftCardEnabled ?>" class="info">
                        <input type="hidden" id="token_info" value="<?php echo $token ?>" class="info">                        
                        <input type="hidden" id="nfdn_shipping_enabled" value="<?php echo $useNFDNShippingEnabled ?>" class="info">

                        <input type="hidden" id="dealer_shipping_enabled" value="<?php echo $useDealerShippingEnabled ?>" class="info">


                        <input type="hidden" id="nfdn_insurance_enabled" value="<?php echo $useNFDNShippingEnabled ?>" class="info">

                        <input type="hidden" id="dealer_insurance_enabled" value="<?php echo $useDealerShippingEnabled ?>" class="info">



                        <input type="hidden" id="auto_order_enabled" value="<?php echo $autoOrderEnabled ?>" class="info">
                        <input type="hidden" id="insurance_charge" value="" class="info">
                        <!-- Finance checkout process -->
                        <input type="hidden" id="commonwealth_finance_enabled" value="<?php echo $dealerArray['enable_commonwealth'] == 1 ? 1 : 0 ?>">
                        <input type="hidden" id="cw_min_purchase_amt" value="<?php echo (empty($dealerArray['cw_min_purchase_amt']) ? 0 : $dealerArray['cw_min_purchase_amt']); ?>">
                        <input type="hidden" id="logging_info" value="<?php echo ($gLoggedIn == 'true' ? 'true' : 'false'); ?>">
                        <input type="hidden" id="login_username" value="<?php echo $gUserName; ?>">
                        <input type="hidden" id="timeout" value="<?php echo $globalLoggedTimedOut; ?>">
                        <input type="hidden" id="isfinance" value="NO">
                        <input type="hidden" id="dealer_promotion_enabled" value="<?php echo $dealer_promotion_enabled; ?>">
                        <input type="hidden" id="taxableTotal" value="">
                        <!-- End -->
                        <div class="section_h1" style="padding:9px 3px 7px 6px;">Review Your Order
                            <div class="section_h1_link" style="top:10px;font-size:12px">
                                <?php if (!$gLoggedIn) { ?>
                                    [ <a href="#login_div" id="do_login" class="h1_link">Log In to Your Account</a> ] &nbsp; [ <a href="#account_div" id="do_create_account" class="h1_link">Create an Account</a> ]
                                    <?php
                                } else {
                                    echo "Welcome " . $gUserName . "! ";
                                    /* if ($_SESSION['cart_info']['checkout_type'] == "new_account") {
                                      echo " &nbsp;Your info will be saved.";
                                      } */
                                    ?>
                                    <a href="logout.php?token=<?php echo $token; ?>" style="color:#fff;font-size:12pt;">&nbsp;&nbsp;Logout</a>
<?php }
?>
                            </div>

                        </div>

                        <div class="section_pane">
                         

<?php if ($shoppingCartDisabled || count($itemsInCart) == 0) { ?>

                                <div style="text-align: center; margin: 120px; font-size: 18px; color: #999;">Shopping cart is empty</div>

                                <?php } else { ?>                                  

                                <table cellpadding="0" cellspacing="0" width="100%">
                                    <?php
                                    $ship_firearms = 0;
                                    $ship_handguns = 0;
                                    $ship_ammo = 0;
                                    $ship_items = 0;
                                    foreach ($itemsInCart as $itemSet) {
                                        $productArray = getProductInfoMicro($itemSet['product_id'], $gDealerId);
                                        showCheckoutItem($productArray, $itemSet['quantity'], $dealerArray['use_retail_price'], $gDealerId);
                                        $ship_firearms += ($productArray['available_quantity'] > 0 ? $productArray['is_firearm'] : 0);
                                        $ship_handguns += ($productArray['available_quantity'] > 0 ? ($productArray['department_id'] == 2 ? 1 : 0) : 0);
                                        $ship_ammo += ($productArray['available_quantity'] > 0 ? ($productArray['department_id'] == 9 ? 1 : 0) : 0);
                                    }
                                    $ship_items = ($productArray['available_quantity'] > 0 ? count($itemsInCart) - $ship_firearms - $ship_ammo : 0);
                                    ?>
                                </table>
                                <!--commonwealth financial notice --> 
                                <div id="financial_note_center" <?php echo ($dealerArray['enable_commonwealth'] == 1 && !empty($dealerArray['cw_min_purchase_amt']) ? "style='padding:7px;font-size:12px;color:#8d3a1d;cursor:pointer'" : "style='display: none;'"); ?>>                                                
                                </div>
                                <div id="additional_charge_note"></div>
                                <div id="promotional_charge_note" style="display:none;"></div>

                            <?php } // $shoppingCartDisabled  ?>

<?php if ($checkoutDisabled === true || $shoppingCartDisabled === true) { ?>

                                <div style="text-align: center; margin: 120px; font-size: 18px; color: #999;">Checkout disabled</div>

                            <?php } else { ?>

                                <?php
                                if (count($dealerLocations) > 0) {
                                    echo "<table><tr><td class='section_text'>Please select one of our store locations:</td><td>";
                                    echo "<select class='field' name='dealer_location_select' id='dealer_location_select'>";
                                    foreach ($dealerLocations as $dealerLocation) {
                                        echo "<option value='" . $dealerLocation['location_id'] . "'" . ($dealerLocation['location_id'] == $dealerLocationId ? " selected" : "") . ">";
                                        echo $dealerLocation['location_name'];
                                        echo "</option>";
                                    }
                                    echo "</select>";
                                    echo "</td></tr></table>";
                                }
                                ?>

                                <?php if ($dealerArray['store_pickup_only'] == 1) { ?>
                                    <div class="section_head">1. This order is for in-store pickup:</div>
                                    <input type="hidden" name="shipping_preference" id="shipping_preference" value="hold" class="info">
    <?php } else { ?>
                                    <div class="section_head">1. Select your shipping preference:&nbsp;
                                        <select id="shipping_preference" name="shipping_preference" class="field info">
                                            <option value="" <?php echo (empty($_SESSION['cart_info']['shipping_preference']) ? "" : "disabled") ?>>Select...</option>
                                            <option value="hold"<?php echo ($_SESSION['cart_info']['shipping_preference'] == "hold" ? " selected" : "") . ($dealerArray['ship_orders_only'] == 1 ? " disabled" : ""); ?>>In-store Pickup</option>
                                            <option value="ship"<?php echo ($_SESSION['cart_info']['shipping_preference'] == "ship" ? " selected" : "") ?>>Ship this order</option>
                                        </select>

                                        <?php
                                        $query = "select * from dealer_preferences where dealer_id = ? and preference_id = ";
                                        $query .= "(select preference_id from preferences where preference_code = 'CHECKOUT_FULFILLMENT_NOTE')";
                                        $resultSet = executeQuery($query, $GLOBALS['gDealerId']);
                                        if ($row = getNextRow($resultSet)) {
                                            echo "<div style='margin: 6px 21px; font-size: 11px;'>" . $row['preference_value'] . "</div>";
                                        }
                                        ?>

                                    </div>
    <?php } ?>


                                <!-- #hold_info will be shown by jQuery when shipping preference is set to hold -->
                                <div id="hold_info_div" style="margin: 6px 0 0 0;"<?php echo ($_SESSION['cart_info']['shipping_preference'] == "hold" || $dealerArray['store_pickup_only'] == 1 ? "" : " class='hidden'") ?>>
                                    <table cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td width="12">&nbsp;</td>
                                            <td valign="top" width="254">
                                                <?php
                                                if (count($dealerLocations) > 0) {
                                                    echo "<div class='select_loc_div'>";
                                                    echo "</div>";
                                                    echo "<a href='' id='get_map' style='display: block; margin: 6px 0;'><i>get map &rarr;</i></a>";
                                                } else {
                                                    echo $dealerArray['dealer_name'] . "<br>";
                                                    echo $dealerArray['dealer_address'] . "<br>";
                                                    echo $dealerArray['dealer_city'] . ", " . $dealerArray['dealer_state'] . " " . $dealerArray['dealer_zip_code'] . "<br>";
                                                    echo "<a href='https://maps.google.com/maps?q=" . str_replace(array(" ", "#"), "+", $dealerArray['dealer_address']) . "," . str_replace(" ", "+", $dealerArray['dealer_city']) . "," . $dealerArray['dealer_state'] . "+" . $dealerArray['dealer_zip_code'] . "&z=14&output=embed' id='get_map' style='display: block; margin: 6px 0;'><i>get map &rarr;</i></a>";
                                                }
                                                ?>
                                            </td>
                                            <td valign="top">
                                                <table cellpadding="0" cellspacing="0">
                                                    <tr>
                                                        <td width="26" valign="top"><img src="/tmpl/info.jpg" width="20"></td>
                                                        <td valign="top">
                                                            We will contact you when your order is available for pickup.
                                                        </td>
                                                    </tr>
                                                    <tr id="handgun_note"<?php echo ($ship_handguns > 0 ? "" : " class='hidden'") ?>>
                                                        <td><img src="tmpl/alert.gif" width="20">&nbsp;&nbsp;</td>
                                                        <td>A valid in-state drivers license is required to pick up handguns.</td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </div>  

                                <!-- #shipping_info_div will be shown by jQuery when shipping preference is set to ship -->
                                <div id="ship_info_div"<?php echo ($_SESSION['cart_info']['shipping_preference'] == "ship" ? "" : " class='hidden'") ?>>
                                    <table width="100%"><tr>
                                            <td valign="top">
                                                <div id="customer_shipping_address_display">
                                                    <?php
                                                    if ($_SESSION['cart_info']['customer_shipping_address_validated'] == "CONFIRMED") {
                                                        echo "<b>Shipping Address:</b><br>";
                                                        echo $_SESSION['cart_info']['ship_name'] . "<br>";
                                                        echo $_SESSION['cart_info']['ship_address'] . "<br>";
                                                        echo $_SESSION['cart_info']['ship_city'] . ", " . $_SESSION['cart_info']['ship_state'] . " " . $_SESSION['cart_info']['ship_zip'];
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td valign="top">
                                                <div id="ffl_shipping_address_display">
                                                    <?php
                                                    if ($_SESSION['cart_info']['ffl_shipping_address_validated'] == "CONFIRMED") {
                                                        echo "<b>Transfer Address:</b><br>";
                                                        echo $_SESSION['cart_info']['ffl_name'] . "<br>";
                                                        echo $_SESSION['cart_info']['ffl_address'] . "<br>";
                                                        echo $_SESSION['cart_info']['ffl_city'] . ", " . $_SESSION['cart_info']['ffl_state'] . " " . $_SESSION['cart_info']['ffl_zip'] . "<br>";
                                                        echo $_SESSION['cart_info']['ffl_phone'];
                                                    }
                                                    ?>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="#shipping_info" id="get_shipping" style="padding: 18px;"><button>Change Address</button></a>
                                            </td>
                                        </tr></table>

                                    <div style="display: block; margin: 6px 18px;" id="shipping_method">
                                        <table cellpaddingshipping_rate_preference="0" cellspacing="0">
                                            <tr>
                                                <td><b>Select delivery method &rarr;</b> &nbsp;</td>
                                                <td>
                                                    <input type="radio" name="shipping_rate_preference" value="express"<?php echo ($_SESSION['cart_info']['shipping_rate_preference'] == "express" ? " checked" : "") ?> class="info">&nbsp;
                                                    Express&nbsp; <span id="express_shipping_amount"></span>&nbsp; &nbsp;
                                                </td>
                                                <td>
                                                    <input type="radio" name="shipping_rate_preference" value="ground"<?php echo ($_SESSION['cart_info']['shipping_rate_preference'] == "ground" ? " checked" : "") ?> class="info">
                                                    Ground&nbsp; <span id="ground_shipping_amount"></span>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                    <?php if ($useNFDNShippingEnabled == "Y" || $useDealerShippingEnabled == "Y" ) { ?>
                                        <div style="display: block; margin: 6px 14px;">
                                            <input type="checkbox" name="add_shipping_insurance" id="add_shipping_insurance" value="Y"<?php echo ($_SESSION['cart_info']['add_shipping_insurance'] == "Y" ? " checked" : "") ?> class="info">
                                            Add shipping insurance: <span id="insurance_amount_option"></span>
                                        </div>
                                    <?php } ?>
                                </div>

                                <div id="contact_info">
                                    <div class="section_head">2. Enter your contact info for this order:</div>
                                    <table cellpadding="0" cellspacing="3"><tr>
                                            <td valign="top">
                                                <table cellpadding="0" cellspacing="3">
                                                    <tr>
                                                        <td width="50" align="right" class="label">Email:&nbsp;</td>
                                                        <td><input type="text" id="email_address" name="email_address" class="field info" style="width: 160px;" value="<?php echo $_SESSION['cart_info']['email_address']; ?>"></td>
                                                        <!--<td><input type="text" id="email_address" name="email_address" class="field info" style="width: 160px;" value="<?php //echo $_POST['email'];  ?>"></td>-->
                                                    </tr>
                                                    <tr>
                                                        <td align="right" class="label">Phone:&nbsp;</td>
                                                        <td><input type="text" id="phone_number" name="phone_number" class="field info" style="width: 160px;" value="<?php echo $_SESSION['cart_info']['phone_number']; ?>"></td>
                                                    </tr>
                                                    <?php
                                                    $resultSet = executeQuery("select * from custom_fields where dealer_id = ? order by sort_order,description", $gDealerId);
                                                    while ($row = getNextRow($resultSet)) {
                                                        ?>
                                                        <tr>
                                                            <td align="right" class="label"><?php echo htmlspecialchars($row['description'], ENT_QUOTES, "UTF-8") ?>:&nbsp;</td>
                                                            <td><input type="text" id="custom_field_<?php echo $row['custom_field_id'] ?>" name="custom_field_<?php echo $row['custom_field_id'] ?>" class="custom-field field info<?php echo ($row['required'] == 1 ? " required" : "") ?>" style="width: 150px;" value="<?php echo $_SESSION['cart_info']['custom_field_' . $row['custom_field_id']]; ?>"></td>
                                                        </tr>
                                                    <?php } ?>
                                                </table>
                                            </td>
                                            <td width="21">&nbsp;</td>
                                            <td>
                                                <div>Any special instructions?</div>
                                                <textarea class="field info" id="notes" name="notes" style="width: 220px; height: 36px;"><?php echo $_SESSION['cart_info']['notes']; ?></textarea>
                                            </td>
                                        </tr></table>
                                </div>

                                <div id="payment_info">
                                    <div class="section_head">3. Enter your name and credit card billing address:</div>
                                    <?php if (!$countryCheckoutEnabled) { ?>
                                        <div id="ship_to_bill_show" class="section_text hidden">
                                            <table><tr>
                                                    <td align="center">
                                                        <input type="checkbox" id="billing_is_shipping" name="billing_is_shipping"<?php echo ($_SESSION['cart_info']['billing_is_shipping'] == "Y" ? " checked" : "") ?> value="Y" class="info">  Copy my shipping address to my billing address
                                                    </td>
                                                </tr></table>
                                        </div>
                                    <?php } ?>

                                    <table cellpadding="0" cellspacing="3">
                                        <tr>
                                            <td width="160" align="right" class="label">Name:&nbsp;</td>
                                            <td><input type="text" id="cc_name" name="cc_name" class="field info" style="width: 220px;" value="<?php echo $_SESSION['cart_info']['cc_name']; ?>"></td>
                                        </tr>
                                        <tr>
                                            <td align="right" class="label">Address:&nbsp;</td>
                                            <td><input type="text" id="cc_address" name="cc_address" class="field info" style="width: 220px;" value="<?php echo $_SESSION['cart_info']['cc_address']; ?>"></td>
                                        </tr>
                                        <tr>
                                            <td align="right" class="label">City:&nbsp;</td>
                                            <td><input type="text" id="cc_city" name="cc_city" class="field info" style="width: 220px;" value="<?php echo $_SESSION['cart_info']['cc_city']; ?>"></td>
                                        </tr>
                                        <?php if ($countryCheckoutEnabled) { ?>
                                            <tr>
                                                <td align="right" class="label">Country:&nbsp;</td>
                                                <td>
                                                    <table cellpadding="0" cellspacing="0"><tr>
                                                            <td><input type="radio" name="cc_country" class="field info" value="USA"<?php echo ($_SESSION['cart_info']['cc_country'] != "CAN" ? " checked" : "") ?>>&nbsp;</td><td><img src="tmpl/us-flag-icon.png"></td>
                                                            <td>&nbsp;&nbsp;&nbsp;</td>
                                                            <td><input type="radio" name="cc_country" class="field info" value="CAN"<?php echo ($_SESSION['cart_info']['cc_country'] == "CAN" ? " checked" : "") ?>>&nbsp;</td><td><img src="tmpl/canada-flag-icon.png"></td>
                                                        </tr></table>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                        <tr>
                                            <td align="right" class="label"><?php echo ($_SESSION['cart_info']['cc_country'] == "CAN" ? "Province" : "State") ?>:&nbsp;</td>
                                            <td>
                                                <select id="cc_state" name="cc_state" class="field info" size="1">
                                                    <option value=''></option>
                                                    <?php
                                                    if ($_SESSION['cart_info']['cc_country'] == "CAN") {
                                                        foreach ($provinceArray as $province) {
                                                            echo "<option value='$province'" . ($_SESSION['cart_info']['cc_state'] == $province ? " selected" : "") . ">$province</option>";
                                                        }
                                                    } else {
                                                        foreach ($stateArray as $state) {
                                                            echo "<option value='$state'" . ($_SESSION['cart_info']['cc_state'] == $state ? " selected" : "") . ">$state</option>";
                                                        }
                                                    }
                                                    ?>                                  
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td align="right" class="label"><?php echo ($_SESSION['cart_info']['cc_country'] == "CAN" ? "Postal" : "Zip") ?> Code:&nbsp;</td>
                                            <td><input type="text" id="cc_zip" name="cc_zip" class="field info" style="width: 100px;" value="<?php echo $_SESSION['cart_info']['cc_zip']; ?>"></td>
                                        </tr>
                                        <tr><td></td><td><div style="height: 3px; margin: 0 0 3px 0; border-bottom: 1px dotted #666;"></div></td></tr>
                                        <tr>
                                            <td width="170" align="right" class="label">Card Number:&nbsp;</td>
                                            <td><input type="text" id="cc_number" name="cc_number" class="field digits info" style="width: 180px;" value="<?php echo $cc_number; ?>"></td>
                                        </tr>
                                        <tr>
                                            <td align="right" class="label">Expiration Date:&nbsp;</td>
                                            <td>
                                                <select id="cc_exp_month" name="cc_exp_month" size="1" class="field info">
                                                    <?php
                                                    for ($i = 1; $i < 13; $i++) {
                                                        $month = date('m', strtotime(date('y') . "-" . $i . "-1"));
                                                        echo "<option value='$month'" . ($_SESSION['cart_info']['cc_exp_month'] == $month ? " selected" : "") . ">$month</option>";
                                                    }
                                                    ?>
                                                </select>
                                                <select id="cc_exp_year" name="cc_exp_year" size="1" class="field info">
                                                    <?php
                                                    for ($i = date('Y'); $i < date('Y') + 10; $i++) {
                                                        echo "<option value='$i'" . ($_SESSION['cart_info']['cc_exp_year'] == $i ? " selected" : "") . ">$i</option>";
                                                    }
                                                    ?>
                                                </select>
                                            </td>
                                        <tr>
                                            <td align="right" class="label">Security Code:&nbsp;</td>
                                            <td><input type="text" id="cc_code" name="cc_code" class="field digits info" size="3" value="<?php echo $cc_code; ?>"></td>
                                        </tr>
                                    </table>

                                   <!--  <script src="//interact.juststicky.com/js/embed/client.js/2115/interact_585457d2d08ed" id="interact_585457d2d08ed" data-text="Discuss this with Sticky Interact" data-unique="585457d2d08ed"></script> -->

                                </div>

                            <?php } // $checkoutDisabled   ?>

                        </div> <!-- class = section pane -->
                        <div id="disclaimerContent"></div>
                    </div> <!-- class = checkout_content -->
                </td>
                <td id="checkout_right">
                   <div class="section_h1" style="padding:9px 3px 7px 6px;">Place Your Order</div>
                   
                    <div class="section_pane">

                        <table cellpadding="0" cellspacing="0">
                            <tr>
                                <td width="100" align="right" class="label">Item Total: &nbsp;</td>
                                <td width="90" align="right"><div id="subtotal" class="calculated"><img src="tmpl/price-loader.gif"></div></td>
                            </tr>
                            <tr id="promo_display" class="hidden">
                                <td align="right" style="font-size: 12px;color: #777;font-weight: bold;padding: 13px 6px 10px 0;
                                        line-height: 0;">Promo Discount: &nbsp;</td>
                                <td align="right"><div id="promotional_charge_display" style="font:12px;" class="calculated"><img src="tmpl/price-loader.gif"></div></td>
                            </tr>
                            <tr>
                                <td align="right" class="label">Shipping: &nbsp;</td>
                                <td align="right"><div id="shipping_charge_display" class="calculated"><img src="tmpl/price-loader.gif"></div></td>
                            </tr>
                            <tr id="shipping_insurance_row" class="hidden">
                                <td align="right" class="label">Insurance: &nbsp;</td>
                                <td align="right"><div id="insurance_charge_display" class="calculated"><img src="tmpl/price-loader.gif"></div></td>
                            </tr>
                            <tr>
                                <td align="right" class="label">Tax: &nbsp;</td>
                                <td align="right"><div id="tax_charge_display" class="calculated"><img src="tmpl/price-loader.gif"></div></td>
                            </tr>
                            <tr id="additional_charge_row" class="hidden">
                                <td align="right" class="label">* Charges: &nbsp;</td>
                                <td align="right"><div id="additional_charge_display" class="calculated"><img src="tmpl/price-loader.gif"></div></td>
                            </tr>
                            <tr>
                                <td align="right" class="label">Order Total: &nbsp;</td>
                                <td align="right">
                                    <div id="total_display" class="order_total calculated"><img src="tmpl/price-loader.gif"></div>
                                </td>
                            </tr>
                        </table>
                     <!--     <style type="text/css">
                            .promolabel
                            {
                                        font-size: 12px;
                                        color: #777;
                                        font-weight: bold;
                                        padding: 13px 6px 10px 0;
                                        line-height: 0;
                            }
                        </style> -->
                        <!--commonwealth financial notice -->                        
                        <div id="financial_note_right" <?php echo ($dealerArray['enable_commonwealth'] == 1 && !empty($dealerArray['cw_min_purchase_amt']) ? "style='padding:7px;font-size:12px;color:#8d3a1d;font-weight:bold;'" : "style='display: none;'") ?>>                                    
                            <hr>
                            <p class="fn_text1"></p>  
                            <p class="fn_text2"></p> 
                            <div style="padding:0 0 10px 10px; text-align: center;">
                                <button id="apply_finance_right_btn"class="apply_finance">Apply Now</button>
                                <button id="display_faqs">FAQs</button>
                            </div>
                        </div>
                        <!-- <div id="gift_card_content"<?php //echo ($giftCardEnabled == "Y" ? "" : " style='display: none;'") ?>>
                            <div id="gift_card_link">
                                Have an NFDN Mall Gift Card?
                            </div>

                            <div id="gift_card_input"<?php echo (empty($_SESSION['cart_info']['gift_card_number']) ? " style='display: none'" : "") ?>>
                                <table cellpadding="0" cellspacing="2">
                                    <tr>
                                        <td align="right" class="label" width="60">Card #:&nbsp; </td>
                                        <td><input type="text" id="gift_card_number" name="gift_card_number" class="gift_card_field digits info" style="width: 130px;" value="<?php echo $_SESSION['cart_info']['gift_card_number']; ?>"></td>
                                    </tr>
                                    <tr>
                                        <td align="right" class="label"></td>
                                        <td align="left"><button id="apply_gift_card_button">Apply Gift Card</button>
                                            <input type="hidden" id="gift_card_amount" class="info">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>     -->
                               <div style="border-bottom:1px solid #aaaaaa"></div>             

                        <div id="discount_content"<?php echo ($discountEnabled == "Y" ? "" : " style='display: none;'") ?>>
                            <div id="discount_link">
                                Have a Discount or Coupon Code?
                            </div>

                            <div id="discount_input"<?php echo (empty($_SESSION['cart_info']['discount_code']) ? " style='display: none'" : "") ?>>
                                <table cellpadding="0" cellspacing="2">
                                    <tr>
                                        <td align="right" class="label" width="60">Code:&nbsp; </td>
                                        <td><input type="text" id="discount_code" name="discount_code" class="discount_field info" style="width: 130px;" value="<?php echo $_SESSION['cart_info']['discount_code']; ?>"></td>
                                    </tr>
                                    <tr>
                                        <td align="right" class="label"></td>
                                        <td align="left"><button id="apply_discount_button">Apply Code</button>
                                            <input type="hidden" id="discount_amount" class="info">
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div id="discount_message" class="error" style="margin: 6px 0 0 0; text-align: center; display: none;"></div>
                        </div>                      

                        <div id="policies_input">
                            <table>
                                <tr>
                                    <td valign="top"><input type="checkbox" name="agree" id="agree">&nbsp;</td>
                                    <td><label for="agree" class="fine_print">
                                            <?php
                                            if (file_exists($templateArray['path'] . "/custom_checkout_agree.inc")) {
                                                include_once $templateArray['path'] . "/custom_checkout_agree.inc";
                                            } else {
                                                ?>
                                                I have read and agree to the <br>
                                                <a href="http://<?php echo $dealerArray['dealer_url']; ?>/content.php?page=policies&return=checkout" id="read_store_policies">store policies</a> and I understand that the sale of certain firearms and accessories may be restricted in <a href='http://<?php echo $dealerArray['dealer_url'] ?>/content.php?page=regulations&return=checkout' id='read_state_regulations'>my state or area</a>.
                                            <?php } ?>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div class="center"><button id="authorize_button"<?php echo ($shoppingCartDisabled ? " disabled" : "") ?>>Place Your Order</button></div>
                        <div class="error" id="errormsg" style="float:left;"></div>
                        <div style="margin: 12px 0 0 0; text-align: center;"><img src="tmpl/cclogos.gif"></div>

                </td>
            </tr>
            <tr>
            <td>
            
                <div id="error_tip">
                <div id="error_tip_blip"><img src="tmpl/error_blip.png"></div>
                <div id="error_tip_contents"><span id="error_tip_message"></span></div>
                </div>


            
                </td>
            </tr>
      


            </table>
    


        <?php include_once (empty($templateArray['footer']) ? "templates/default" : $templateArray['footer']) . "/footer.inc"; ?>

       
            
            <a href="#subscriber_window" id="do_cart"></a>
                <a href="#processor_div" id="do_process"></a>
            <!--- check if subcriber option enabled and store value---->
            <?php $resultSet = executeQuery("select * from subscription where manufacturer_id='205' and inactive=0");
            if ($row = getNextRow($resultSet)) {
            $statusId=$row['status'];
            } ?>
            <input type="hidden" id="subcriber_status" name="subcriber_status" value="<?php echo ($statusId == 0 ? "enabled" : "disabled") ?>" class="info"/>
            
        <div style="display: none;">
            <div id="shipping_info" class="processor_div" style="min-width: 340px;">
                <table cellpadding="0" cellspacing="12"><tr>
                        <td id="customer_shipping_address" valign="top">
                            <input type="hidden" id="customer_shipping_address_required" name="customer_shipping_address_required" value="<?php echo ($ship_items + $ship_ammo > 0 ? "Y" : ""); ?>" class="info">
                            <input type="hidden" id="customer_shipping_address_validated" name="customer_shipping_address_validated" value="<?php echo $_SESSION['cart_info']['customer_shipping_address_validated']; ?>" class="info">
                            <input type="hidden" id="customer_shipping_address_type" name="customer_shipping_address_type" value="<?php echo $_SESSION['cart_info']['customer_shipping_address_type']; ?>" class="info">
                            <div class="popup_head left">Enter your shipping address:</div>
                            <table cellpadding="0" cellspacing="3">
                                <tr>
                                    <td width="80" align="right" class="label">Name:</td>
                                    <td align="left"><input type="text" id="ship_name" name="ship_name" class="field info" style="width: 200px;" value="<?php echo $_SESSION['cart_info']['ship_name']; ?>"></td>
                                </tr>
                                <tr>
                                    <td align="right" class="label">Address:</td>
                                    <td align="left"><input type="text" id="ship_address" name="ship_address" class="field info" style="width: 200px;" value="<?php echo $_SESSION['cart_info']['ship_address']; ?>"></td>
                                </tr>
                                <tr>
                                    <td align="right" class="label">City:</td>
                                    <td align="left"><input type="text" id="ship_city" name="ship_city" class="field info" style="width: 200px;" value="<?php echo $_SESSION['cart_info']['ship_city']; ?>"></td>
                                </tr>
                                <?php if ($countryCheckoutEnabled) { ?>
                                    <tr>
                                        <td align="right" class="label">Country:&nbsp;</td>
                                        <td align="left">
                                            <table cellpadding="0" cellspacing="0"><tr>
                                                    <td><input type="radio" name="ship_country" class="field info" value="USA"<?php echo ($_SESSION['cart_info']['ship_country'] != "CAN" ? " checked" : "") ?>>&nbsp;</td><td><img src="tmpl/us-flag-icon.png"></td>
                                                    <td>&nbsp;&nbsp;&nbsp;</td>
                                                    <td><input type="radio" name="ship_country" class="field info" value="CAN"<?php echo ($_SESSION['cart_info']['ship_country'] == "CAN" ? " checked" : "") ?>>&nbsp;</td><td><img src="tmpl/canada-flag-icon.png"></td>
                                                </tr></table>
                                        </td>
                                    </tr>
                                <?php } ?>
                                <tr>
                                    <td align="right" class="label">State:</td>
                                    <td align="left">
                                        <select id="ship_state" name="ship_state" class="field info" size="1">
                                            <option value=''></option>
                                            <?php
                                            if ($_SESSION['cart_info']['ship_country'] == "CAN") {
                                                foreach ($provinceArray as $province) {
                                                    echo "<option value='$province'" . ($_SESSION['cart_info']['ship_state'] == $province ? " selected" : "") . ">$province</option>";
                                                }
                                            } else {
                                                foreach ($stateArray as $state) {
                                                    echo "<option value='$state'" . ($_SESSION['cart_info']['ship_state'] == $state ? " selected" : "") . ">$state</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="right" class="label"><?php echo ($_SESSION['cart_info']['ship_country'] == "CAN" ? "Postal" : "Zip") ?> Code:</td>
                                    <td align="left"><input type="text" id="ship_zip" name="ship_zip" class="field info" style="width: 100px;" value="<?php echo $_SESSION['cart_info']['ship_zip']; ?>"></td>
                                </tr>
                            </table>
                            <div id="customer_shipping_confirmed"></div>
                        </td>
                        <td id="ffl_shipping_address" valign="top">
                            <input type="hidden" id="ffl_shipping_address_required" name="ffl_shipping_address_required" value="<?php echo ($ship_firearms + $ship_handguns > 0 ? "Y" : ""); ?>" class="info">
                            <input type="hidden" id="ffl_shipping_address_validated" name="ffl_shipping_address_validated" value="<?php echo $_SESSION['cart_info']['ffl_shipping_address_validated']; ?>" class="info">
                            <input type="hidden" id="ffl_shipping_address_type" name="ffl_shipping_address_type" value="<?php echo $_SESSION['cart_info']['ffl_shipping_address_type']; ?>" class="info">
                            <div class="popup_head left">Enter dealer info for firearms transfer:</div>
                            <table cellpadding="0" cellspacing="3">
                                <tr>
                                    <td width="100" align="right" class="label">Dealer Name:</td>
                                    <td align="left"><input type="text" id="ffl_name" name="ffl_name" class="field info" style="width: 200px;" value="<?php echo $_SESSION['cart_info']['ffl_name']; ?>"></td>
                                </tr>
                                <tr>
                                    <td align="right" class="label">Address:</td>
                                    <td align="left"><input type="text" id="ffl_address" name="ffl_address" class="field info" style="width: 200px;" value="<?php echo $_SESSION['cart_info']['ffl_address']; ?>"></td>
                                </tr>
                                <tr>
                                    <td align="right" class="label">City:</td>
                                    <td align="left"><input type="text" id="ffl_city" name="ffl_city" class="field info" style="width: 200px;" value="<?php echo $_SESSION['cart_info']['ffl_city']; ?>"></td>
                                </tr>
                                <?php if ($countryCheckoutEnabled) { ?>
                                    <tr>
                                        <td align="right" class="label">Country:&nbsp;</td>
                                        <td align="left">
                                            <table cellpadding="0" cellspacing="0"><tr>
                                                    <td align="left"><input type="radio" name="ffl_country" class="field info" value="USA"<?php echo ($_SESSION['cart_info']['ffl_country'] != "CAN" ? " checked" : "") ?>>&nbsp;</td><td><img src="tmpl/us-flag-icon.png"></td>
                                                    <td>&nbsp;&nbsp;&nbsp;</td>
                                                    <td align="left"><input type="radio" name="ffl_country" class="field info" value="CAN"<?php echo ($_SESSION['cart_info']['ffl_country'] == "CAN" ? " checked" : "") ?>>&nbsp;</td><td><img src="tmpl/canada-flag-icon.png"></td>
                                                </tr></table>
                                        </td>
                                    </tr>
                                <?php } ?>
                                <tr>
                                    <td align="right" class="label">State:</td>
                                    <td align="left">
                                        <select id="ffl_state" name="ffl_state" class="field info" size="1">
                                            <option value=''></option>
                                            <?php
                                            if ($_SESSION['cart_info']['ffl_country'] == "CAN") {
                                                foreach ($provinceArray as $province) {
                                                    echo "<option value='$province'" . ($_SESSION['cart_info']['ffl_state'] == $province ? " selected" : "") . ">$province</option>";
                                                }
                                            } else {
                                                foreach ($stateArray as $state) {
                                                    echo "<option value='$state'" . ($_SESSION['cart_info']['ffl_state'] == $state ? " selected" : "") . ">$state</option>";
                                                }
                                            }
                                            ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="right" class="label"><?php echo ($_SESSION['cart_info']['ffl_country'] == "CAN" ? "Postal" : "Zip") ?> Code:</td>
                                    <td align="left"><input type="text" id="ffl_zip" name="ffl_zip" class="field info" style="width: 100px;" value="<?php echo $_SESSION['cart_info']['ffl_zip']; ?>"></td>
                                </tr>
                                <tr>
                                    <td align="right" class="label">Dealer Phone:</td>
                                    <td align="left"><input type="text" id="ffl_phone" name="ffl_phone" class="field info" style="width: 220px;" value="<?php echo $_SESSION['cart_info']['ffl_phone']; ?>"></td>
                                </tr>
                            </table>
                            <div id="ffl_shipping_confirmed"></div>
                        </td>
                    </tr></table>
                <div id="address_controls" style="text-align: center; margin: 0 12px; padding: 9px 0; border-top: 1px dotted #aaaaaa; white-space: nowrap;">
                    <button id="shipping_info_confirm" class="confirm">Confirm Address</button>&nbsp;
                    <span class="hidden"><button id="shipping_info_override" class="confirm">Use Unconfirmed</button>&nbsp;</span>
                    <button id="shipping_info_cancel" class="cancel">Cancel</button>
                </div>
                <div id="address_loading"><img src="tmpl/ajax-loader-bar.gif"></div>
                <div id="shipping_message" class="error"></div>
            </div>
        </div>

        <div style="display: none;">
            <div id="google_map">
            </div>
        </div>       
             <!--Subscriber Window -->  
                <div style="display: none;">
                    <div id="subscriber_window" class="processor_div" style="min-width: 500px;">
            <div class="sub_close">&#10006;</div>
                <div class="henry">
                <div class="henry_radio">                     
                <span class="henry_radio_span"><input type="radio" name="subscribe" id="subscribe" value="yes" checked="">Yes</span>
                        <span class="henry_radio_span"><input type="radio" name="subscribe" id="subscribe" value="no">No</span>
                </div>
                    <button id="sub_info_confirm">Continue</button>   
                </div>   
                </div>
            </div>
    
          <div style="display: none;">
            <div id="processor_div" class="processor_div">
                <div class="popup_head">Processing Your Order</div>
                <div id="process_loading"><img src="tmpl/ajax-loader-bar.gif"></div>
                <div id="process_message"></div>
                <div id="process_close"><button id="process_close_button" class="cancel">Continue</button></div>    
            </div>
        </div>
        <div style="display: none;">
            <div id="quantity_alert_div" class="processor_div">
                <div class="popup_text">Your order exceeds our weight limit, please remove some items to reduce the weight</div>
                <button id="quantity_alert_btn" onClick="$.fancybox.close();" class="cancel">OK</button>
            </div>
        </div>

        <div style="display: none;">
            <div id="shipping_items_alert" class="processor_div">
                <div class="popup_text">Firearms and Ammunition cannot be shipped together. Please complete checkout with these items separately.</div>
                <button id="shipping_alert_btn" onClick="$.fancybox.close();" class="cancel">OK</button>
            </div>
        </div>

        <div style="display: none;">
            <div id="login_div" class="processor_div" >
                <div class="popup_head">Log in to your account</div>
                <table>
                    <tr>
                        <td class="label" width="90" align="right">Username:&nbsp;</td>
                        <td><input type="text" id="user_name" name="user_name" class="field" style="width: 150px;"></td>
                        <td width="21">&nbsp;</td>
                    </tr>
                    <tr>
                        <td class="label" align="right">Password:&nbsp;</td>
                        <td><input type="password" id="password" name="password" class="field" style="width: 150px;"></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td align="left">
                            <button id="login_account">Log In</button>&nbsp;&nbsp;
                            <button id="cancel_login_account" class="cancel">Cancel</button>
                        </td>
                        <td></td>
                    </tr>
                    <tr>

                        <td align="center" colspan="3" style="padding: 9px;">
                            <a href="#forgot_password_div" id="popup_forgot_password"><span class="anchor_text popup_head" style="font-size:12px; border:0;">Forgot Password?</span></a>
                            &nbsp;&nbsp;&nbsp;&nbsp;
                            <a id="popup_create_account" ><span class="anchor_text popup_head" style="font-size:12px;border:0;">Create an account</span></a>
                        </td>

                    </tr>
                </table>
                <div class="error"></div>
            </div>
        </div>   
        <!-- Finance login div with password reset option-->
        <div style="display: none;" > 
            <div  id="forgot_password_div" class="processor_div" style="width:375px;"> 
                <div class="popup_head">Reset your password</div>
                <table>
                    <tr>
                        <td class='label' width='160' align="right"><label for="forgot_user_name">Username: </label></td>
                        <td><input type="text" name="forgot_user_name" style="float:left;" class="field" id="forgot_user_name" size="20" value="" /></td>
                    </tr>
                    <tr>
                        <td class="label" align="right"><label for="security_question_id">Security Question: </label></td>
                        <td>
                            <select style="float:left;" id="forgot_security_question_id" class="field" name="forgot_security_question_id" class="field" size="1">

                                <?php
                                $resultSet = executeQuery("select * from security_questions where internal_use_only = 0 and inactive = 0  order by sort_order,security_question");
                                echo "<option value='select'>Select</option>";
                                while ($row = getNextRow($resultSet)) {

                                    echo "<option value='" . $row['security_question_id'] . "'>" . $row['security_question'] . "</option>";
                                }
                                ?>                                  
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td class='label' width='160' align="right"><label for="answer_text">Answer: </label></td>
                        <td><input type="text" name="forgot_answer_text" class="field" id="forgot_answer_text" size="30" value="" /></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td align="left">
                            <button id="confirm_reset_password"  class="confirm">Reset Password</button>&nbsp;&nbsp;
                            <button id="cancel_reset_password" class="cancel" >Cancel</button>
                        </td>
                        <td></td>
                    </tr>
                </table>
                <div class="error"></div>
            </div>
        </div> 


        <div style="display: none;"> 
            <div id="reset_info" style="height:65px;">
                <div class="popup_head" style="border-bottom:0px;text-align: center;"> Reset password link is sent to your registered email address.</div>
                <button onClick="$.fancybox.close();" style="margin-left:46%; height:23px;position: relative; font-size:11px;"  class="cancel">OK</button>
            </div>
        </div>


        <?php include_once "scpt/dealer_tracking_code.inc";?> 
        <!-- End Finance login div with password reset option-->
        <div style="display: none;">
            <div id="account_div" class="processor_div">
                <div class="popup_head">Create your new customer account</div>
                <table>
                    <tr>
                        <td width="120" align="right" class="label">User Name:&nbsp;</td>
                        <td align="left"><input type="text" id="user_name_create" name="user_name_create" class="field" style="width: 150px;"></td>
                    </tr>
                    <tr>
                        <td align="right" class="label">Password:&nbsp;</td>
                        <td align="left"><input type="password" id="password_create" name="password_create" class="field" style="width: 150px;"></td>
                    </tr>
                    <tr>
                        <td align="right" class="label">Password Confirm:&nbsp;</td>
                        <td align="left"><input type="password" id="password2" name="password2" class="field" style="width: 150px;"></td>
                    </tr>
                    <tr>
                        <td align="right" class="label">Email:&nbsp;</td>
                        <td align="left"><input type="text" id="finance_emails" name="finance_emails" class="field" style="width: 150px;"></td>
                    </tr>     
                    <tr>
                        <td align="right" class="label">Security Question:&nbsp;</td>
                        <td align="left"><select class="field" id="security_question_id" name="security_question_id">
                                <?php
                                $resultSet = executeQuery("select * from security_questions where internal_use_only = 0 and inactive = 0 order by sort_order,security_question");
                                echo "<option value=''>Select</option>";
                                while ($row = getNextRow($resultSet)) {
                                    echo "<option value='" . $row['security_question_id'] . "'>" . htmlspecialchars($row['security_question'], ENT_QUOTES, 'UTF-8') . "</option>";
                                }
                                ?>
                            </select></td>
                    </tr>
                    <tr>
                        <td align="right" class="label">Answer:&nbsp;</td>
                        <td align="left"><input type="text" id="answer_text" name="answer_text" class="field" style="width: 150px;"></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td align="left">
                            <button id="create_account">Create Your Account</button>&nbsp;&nbsp;
                            <button id="cancel_create_account" class="cancel">Cancel</button>
                        </td>
                    </tr>
                </table>
                <div class="error"></div>
                <div class="fine_print" style="margin: 6px 0 0 0; padding: 6px 0 0 0; text-align: center; border-top: 1px dotted #aaaaaa;">The shipping and billing info you provide for this order<br>will be saved in your secure customer account<br>for faster checkout next time.</div>
            </div>
        </div>


        <script> // For loading finance disclaimer content
            $(function() {
                if ($("#commonwealth_finance_enabled").val() == 1)
                    $("#disclaimerContent").load("cw_disclaimer.htm");
            });
             
            //prevent back start
            if (location.hash == '#index') {
                history.pushState(null, '', '#home');
                window.onhashchange = function() {
                    if (location.hash == '#index') {
                        history.pushState(null, '', '#home');
                    }
                }
            }
            else if (location.hash == "#home") {
                history.pushState(null, '', '#index');
                window.onhashchange = function() {
                    if (location.hash == '#home') {
                        history.pushState(null, '', '#index');
                    }
                }
            }
            //prevent back end

            $(document).ready(function(){
                $("#sub_info_confirm").removeClass();
                $("#sub_info_confirm").html("Continue");
            });
        </script>
        <?php        
        include_once "finance_faqs.htm"; 
     
        ?>

    </body>
</html>


<script type="text/javascript">

var defaultcheck = 1;
$(window).scroll(function (event) {
    var scroll = $(window).scrollTop();
    if(defaultcheck == 1) {
        if(scroll < 15) {
            if(scroll != 0) {   
                // alert("IF" +scroll);
                $('.type4.interact_box').css({"margin-top":"200px"});
            }
            defaultcheck = 0;
        } else {
            if(scroll != 0) {   
                //alert("Else" + scroll);
                $('.type4.interact_box').css({"margin-top":"0"});
            }
            defaultcheck = 0;
        }
        defaultcheck = defaultcheck + 1;
    }   
});

 if( /iPhone|iPod|iPad/i.test(navigator.userAgent) ) {
var ww = ( $(window).width() < window.screen.width ) ? $(window).width() : window.screen.width; //get proper width
var mw = 1100; // min width of site
var ratio =  ww / mw; //calculate ratio
if( ww < mw){ //smaller than minimum size
    //alert('Theme applied');
    //alert(ww);
    //$('#Viewport').attr('content', 'initial-scale=0, minimum-scale=0, user-scalable=yes, width=' + (ww-100));
     //$('#Viewport').attr('content','initial-scale=' + ratio + ', maximum-scale=' + (ratio*10) + ', minimum-scale=0.3, user-scalable=yes');

     $('#Viewport').attr('content','initial-scale=' + ratio + ', maximum-scale=' + (ratio*2) + ', minimum-scale=0.3, user-scalable=yes');
    //$('#Viewport').attr('content','width=device-width; initial-scale=0.5, minimum-scale=0');
}
//$('#Viewport').attr('content','initial-scale=1.0, user-scalable=yes');
}

</script>

<?php

function showCheckoutItem($productArray, $quantity, $useRetailPrice = 0, $dealerId) {
    echo "<tr class='item_row'>\n";
    echo "<td width='130' align='center' class='list'>\n";
    if (empty($productArray['thumbnail_image_id'])) {
        echo "<img src='tmpl/no_image.jpg'>";
    } else {
        echo "<img src='imagedb/image" . $productArray['thumbnail_image_id'] . "-" . getImageHashCode($productArray['thumbnail_image_id']) . ".jpg' class='small_thumbnail_image'>";
    }
    echo "\n</td>\n";
    echo "<td class='list'>\n";
    $productArray['department_id'] = getFieldFromId('department_id', 'categories', 'category_id', $productArray['category_id']);
    $productLinkName = getFieldFromId("link_name", "products", "product_id", $productArray['product_id']);
    $productLinkUrl = (empty($productLinkName) ? "/catalog_detail.php?product_id=" . $productArray['product_id'] : "/product." . $productLinkName);
    echo "<div class='checkout_item_title'><a href='http://" . getFieldFromId('domain_name', 'domain_names', 'dealer_id', $dealerId) . $productLinkUrl . "' class='item_link'>" . $productArray['description'] . "</a></div>\n";

    //echo "<div class='checkout_item_title'><a href='http://" . getFieldFromId('domain_name','domain_names','dealer_id',$dealerId) . "/catalog_detail.php?product_id={$productArray['product_id']}' class='item_link'>" . $productArray['description'] . "</a></div>\n";
    if (!empty($productArray['upc'])) {
        echo "<span class='label'>UPC:</span> " . $productArray['upc'] . "<br>";
    }
    echo "<span class='label'>Product ID:</span> {$productArray['product_id']}<br>\n";

    if ($productArray['available_quantity'] == 0) {
        echo "<span class='label'>Quantity:</span> <input type='text' id='quantity_{$productArray['product_id']}' class='quantity' value='0' data-product-id='{$productArray['product_id']}' readonly> ";
        echo "&nbsp;&nbsp;";
        echo "<button class='remove' data-product-id='{$productArray['product_id']}'>Remove</button>\n";
        echo "<div class='item_content alert'><i>Availability Update: this item is currently unavailable.</i></div>\n";
    } else {


        $displayPrice = 0;
        // at this point we know the retail_price and the override_price
        // override trumps everything, use_retail_price trumps calculated price
        if ($productArray['override_price'] > 0 && $productArray['override_price'] >= $productArray['map_price']) {
            $displayPrice = $productArray['override_price'];
        } else {
            //if ($useRetailPrice && $productArray['retail_price'] > 0 && $productArray['override_price'] >= $productArray['map_price']) {
            if ($useRetailPrice && $productArray['retail_price'] > 0 ) {
                $displayPrice = $productArray['retail_price'];
            } else {
                if ($productArray['live_price'] != 1) {
                    if ($productArray['distributor_id'] > 0) {
                        $displayPrice = getDisplayPrice($productArray);
                    } else {
                       // $displayPrice = $productArray['dealer_cost'];

              $displayPrice = preg_replace('/[ ,]+/', '-', trim($productArray['dealer_cost'])); 

                    }
                }
            }
        }

if ($productArray['live_price'] == 1 && empty($displayPrice)) {


           
            echo "<div class='checkout_price' id='live_price_{$productArray['product_id']}' data-department-id ='{$productArray['department_id']}' data-product-id='{$productArray['product_id']}' data-distributor_id='{$productArray['distributor_id']}' data-product_price='~' >";
            
//echo "~";
            
        } else {
             //$prodprice=number_format($displayPrice, 2);
            $prodprice=$displayPrice;
            echo "<div class='checkout_price' id='live_price_{$productArray['product_id']}' data-department-id ='{$productArray['department_id']}' data-product-id='{$productArray['product_id']}' data-distributor_id='{$productArray['distributor_id']}' data-product_price='{$prodprice}'>";
            echo "$" . number_format($displayPrice, 2);
        }

        
        // if ($productArray['live_price'] == 1 && empty($displayPrice)) {
        //     echo "~";
        // } else {
        //     echo "$" . number_format($displayPrice, 2);
        // }


        echo "</div>\n";

        echo "<span class='label'>Quantity:</span> <input type='text' class='quantity' id='quantity_{$productArray['product_id']}' value='" . min($quantity, $productArray['available_quantity']) . "' data-product-id='{$productArray['product_id']}'> ";
        echo "&nbsp;&nbsp;";
        echo "<button class='update' data-product-id='{$productArray['product_id']}'>Update</button>";
        echo "&nbsp;&nbsp;";
        echo "<button class='remove' data-product-id='{$productArray['product_id']}'>Remove</button>\n";
        echo "<div class='quantity_changed alert hidden'><i>Quantity Alert: your quantity has changed due to availability.</i></div>\n";
        if ($productArray['low_quantity'] && !$productArray['store_item']) {
            echo "<div class='item_content alert'><i>ALERT: This item has a high probability of being back-ordered. Please contact us to check availability prior to placing your order.</i></div>\n";
        }
        $productArray['shipping_group'] = getShippingGroup($productArray['product_id']);
    }

    // if no shipping_weight stored, find average weight for this category
    if (empty($productArray['shipping_weight']) || $productArray['shipping_weight'] == 0) {
        $resultSet = executeQuery("select avg(shipping_weight) as shipping_weight from products where category_id = ?", $productArray['category_id']);
        if ($row = getNextRow($resultSet)) {
            $productArray['shipping_weight'] = round($row['shipping_weight'], 2);
        }
        // if it's still empty, use arbitrary default weight
        if (empty($productArray['shipping_weight'])) {
            $productArray['shipping_weight'] = 5;
        }
    }
    
    echo "<input type='hidden' class='non_taxable' name='non_taxable_{$productArray['product_id']}' id='non_taxable_{$productArray['product_id']}' value='{$productArray['non_taxable']}'>\n";
    echo "<input type='hidden' class='shipping_group' name='shipping_group_{$productArray['product_id']}' id='shipping_group_{$productArray['product_id']}' value='{$productArray['shipping_group']}'>\n";
    echo "<input type='hidden' class='shipping_weight' name='shipping_weight_{$productArray['product_id']}' id='shipping_weight_{$productArray['product_id']}' value='{$productArray['shipping_weight']}'>\n";
    echo "<input type='hidden' class='available_quantity' name='available_quantity_{$productArray['product_id']}' id='available_quantity_{$productArray['product_id']}' value='{$productArray['available_quantity']}'>\n";
    echo "<input type='hidden' class='product_category' name='product_category_{$productArray['product_id']}' id='product_category_{$productArray['product_id']}' value='{$productArray['category_id']}'>\n";
    echo "<input type='hidden' class='product_department' name='product_department_{$productArray['product_id']}' id='product_department_{$productArray['product_id']}' value='{$productArray['department_id']}'>\n";
    
    echo "</td>\n";
    echo "</tr>\n";
}
?>
