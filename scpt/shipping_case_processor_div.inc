<?php
################################################################
# This software is the unpublished, confidential, proprietary, 
# intellectual property of zipperSNAP, LLC and may not be copied,
# duplicated, retransmitted or used in any manner without
# expressed written consent from zipperSNAP, LLC.
# Copyright 2009 - Present, zipperSNAP, LLC.
################################################################
?>
<div id="shipment_window_wrapper" style="display: none;">
	<div class="processor_div" style="min-width: 450px; min-height: 140px; position: relative;">
		<div style="position: absolute; top: 0; right: 0;"><img src="tmpl/fedex_truck.jpg" height="55"></div>
		<h1 class="shipment_window_heading">Generate <?php echo ($dataRow['is_return_shipment'] == 1 ? "Return" : "Outbound") ?> Label</h1>
		<table class="shipment_window_table" cellpadding="0" cellspacing="9" width="100%">
		</table>
		<div class="shipment_window_loader" style="text-align: center"><img src="tmpl/ajax-loader.gif"></div>
		<div class="shipment_message" style="display: none; text-align: center; margin: 12px 0; font-style: italic; color: #a53831;"></div>
		<div class="shipment_window_buttons" style="display: none; margin: 12px 0 0 0; padding: 6px 0 0 0; border-top: 1px dotted #aaaaaa; text-align: center;">
			<button class="authorize">Authorize Credit Card</button>
			<button class="cancel">Cancel</button>
			<button class="close">Close Window</button>
		</div>
	</div>
</div>

<div id="new_shipment_window_wrapper" style="display: none;">
	<div class="processor_div" style="position: relative;">
		<div style="position: absolute; top: 0; right: 0;"><img src="tmpl/fedex_truck.jpg" height="55"></div>
		<h1 class="shipment_window_heading">Create New Shipment</h1>
		<table class="shipment_address_table" cellpadding="0" cellspacing="3" width="100%"><tr>
		<td width="360" valign="top">

		<div style="margin: 0 0 6px 0; font-style: italic; font-weight: bold; color: #666;">Search for an address...</div>
		<table class="shipment_address_table" cellpadding="2" cellspacing="0">
			<tr>
				<td width="220" align="right"><input type="text" name="search_text" class="info" style="width: 180px;"></td>
				<td><button class="search_button">Search</button></td>
			</tr>
		</table>
		<div class="search_results" style="margin: 6px 0 12px 0;"></div>
		<div class="search_results_loader" style="display: none; margin: 6px 21px 12px; 0; text-align: center"><img src="tmpl/ajax-loader.gif"></div>
		<div class="search_message" style="display: none; text-align: center; margin: 12px 0; font-style: italic; color: #a53831;"></div>		
		
		<div class="address_prompt" style="margin: 0 0 6px 0; font-style: italic; font-weight: bold; color: #666;">Or enter a new address...</div>
		<table class="shipment_address_table" cellpadding="2" cellspacing="0">
			<tr>
				<td width="90" class="label">Name</td>
				<td align="left"><input type="text" name="ship_name" class="field info" style="width: 200px;" data-crc="<?php echo getCrc32('') ?>"></td>
			</tr>
			<tr class="address_label_row hidden-element">
				<td width="90" class="label">Attention</td>
				<td align="left"><input type="text" name="ship_address_label" class="field info" style="width: 200px;" data-crc="<?php echo getCrc32('') ?>"></td>
			</tr>
			<tr>
				<td class="label">Address</td>
				<td align="left"><input type="text" name="ship_address" class="field info" style="width: 200px;" data-crc="<?php echo getCrc32('') ?>"></td>
			</tr>
			<tr>
				<td class="label"></td>
				<td align="left">
					<table width="100%" cellpadding="0" cellspacing="0"><tr>
						<td class="label">Apartment, Suite or Unit #</td>
						<td align="right"><input type="text" name="ship_unit" class="field info" style="width: 50px;" data-crc="<?php echo getCrc32('') ?>"></td>
					</tr></table>
				</td>
			</tr>
			<tr>
				<td class="label">City</td>
				<td align="left"><input type="text" name="ship_city" class="field info" style="width: 200px;" data-crc="<?php echo getCrc32('') ?>"></td>
			</tr>
			<tr>
				<td class="label">State</td>
				<td align="left">
					<select id="ship_state" name="ship_state" class="field info" size="1" data-crc="<?php echo getCrc32('') ?>">
						<option value=''></option>
						<?php
							foreach ($stateArray as $state) {
								echo "<option value='$state'>$state</option>";
							}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<td class="label">Zip Code</td>
				<td align="left"><input type="text" name="ship_zip" class="field info" style="width: 100px;" data-crc="<?php echo getCrc32('') ?>"></td>
			</tr>
			<tr><td>&nbsp;</td><td></td></tr>
			<tr class="address_type_row">
				<td class="label">Address Type</td>
				<td>
                                <input type="radio" name="address_type" value="RES" id="incorrect_add"> Residential &nbsp; 
					<input type="radio" name="address_type" value="BUS"> Business					
				</td>
			</tr>
			<tr><td>&nbsp;</td><td></td></tr>
			<tr>
				<td class="label">Phone Number</td>
				<td align="left"><input type="text" name="ship_phone" class="field info" style="width: 200px;" data-crc="<?php echo getCrc32('') ?>"></td>
			</tr>
			<tr>
				<td class="label">Email Address</td>
				<td align="left"><input type="text" name="ship_email" class="field info" style="width: 200px;" data-crc="<?php echo getCrc32('') ?>"></td>
			</tr>
		</table>
		</td>
		<td width="360" valign="top">
			<div style="margin: 0 0 6px 0; font-style: italic; font-weight: bold; color: #666;">Package details...</div>
			<table class="shipment_details_table" cellpadding="3" cellspacing="0">
			<tr>
				<td class="label">Total Weight</td>
				<td align="left" colspan="2" style="width:100px"><input type="text" name="ship_weight" class="field info" style="width: 50px;"> lbs</td>
			</tr>
                        <tr id="dimension_label_row">
                            <td colspan="3" class="dimension">
                            <fieldset>
                              <legend>Dimensions</legend>
                                   <input type="text" name="length_label_row" class="field info" placeholder="Length" style="width: 46px;">
                                   <input type="text" name="width_label_row" class="field info" placeholder="Width" style="width: 46px;">
                                   <input type="text" name="height_label_row" class="field info" placeholder="Height" style="width: 46px;">       
                              <select name="inch_and_centimeter">
                                   <option value="IN">IN</option>
                                   <option value="CM">CM</option>
                              </select>  
                             </fieldset>
                            </td>
                        </tr>
			<tr>
				<td class="label">Package Includes</td>
				<td align="left" colspan="2">
					<input type='checkbox' name='handguns' value='Yes' class='requires_ffl requires_signature'  <?php echo ($shipHandguns > 0 ? "checked " : "") ?>> Handgun(s)<br>
					<input type='checkbox' name='longguns' value='Yes' class='requires_ffl requires_signature'  <?php echo ($shipFirearms > 0 ? "checked " : "") ?>'> Long Gun(s)<br> 
					<input type='checkbox' name='ammo'  value='Yes' class='requires_signature'  <?php echo ($shipAmmo > 0 ? "checked " : "") ?>> Ammunition<br>
					<input type='checkbox' name='other'  value='Yes' <?php echo ($shipItems > 0 ? "checked" : "") ?> > Other
				</td>
			</tr>
			<tr class="ffl_number_row<?php echo ($shipHandguns > 0 || $shipFirearms > 0 ? "" : " hidden-element") ?>">
				<td class="label">Transfer FFL #</td>
				<td align="left" colspan="2"><input type="text" name="ffl_number" class="field info" style="width: 150px;"></td>
			</tr>

			<tr class="signature_requred_row">

				<td class="label">Adult Signature</td>
				<td>
					<input type='radio' name='signature_required' id='required' class='asrreq' value='1' style="font-size: 11px"> Required &nbsp;									
				</td>

				<td>
				<input type='radio' name='signature_required' id='notrequired' class='rad' value='0'  style="font-size: 11px" <?php echo ($shipHandguns > 0 || $shipFirearms > 0 || $shipAmmo > 0 ? "disabled" : "checked") ?>> Not Required	
				</td>
			</tr>


			</table>

			<div class="shipment_defaults" style="margin: 9px 0; padding: 6px 0 0 0; color: #666; text-align: center; border-top: 1px dotted #aaaaaa;"></div>
						
			<div class="shipment_service hidden-element" style="margin: 9px 0; padding: 6px 0 0 0; border-top: 1px dotted #aaaaaa;">
			<div style="margin: 6px 0; font-style: italic; font-weight: bold; color: #666;">Available services - select one...</div>
			<table class="shipment_service_table" cellpadding="0" cellspacing="3">
			</table>
			</div>

			<div class="insurance_service hidden-element" style="margin: 9px 0; padding: 6px 0 0 0; border-top: 1px dotted #aaaaaa;">
			<div style="margin: 6px 0; font-style: italic; font-weight: bold; color: #666;">Optional insurance...</div>
			<table cellpadding="2" cellspacing="0" class="shipment_details_table_Insurance">
				<tr>
					<td class="label">Package Value $</td>
					<td align="left"><input type="text" name="package_value" class="field info numeric" style="width: 80px;"></td>
					<td><button class="calculate_insurance">Calculate</button></td>
				</tr>
			</table>
			
			<div class="add_insurance_option center hidden-element">
				<input type="checkbox" name="add_shipping_insurance">&nbsp;
				<span class="label">Add insurance</span>&nbsp;$
				<span class="insurance_amount_option"></span>
			</div>
			
			</div>

			<div class="shipment_address_buttons" style="margin: 12px 0; padding: 6px 0 0 0; border-top: 1px dotted #aaaaaa; text-align: center;">
				<button class="confirm">Confirm Address and Rates</button> &nbsp; 
				<button class="cancel">Cancel</button>
			</div>

			<div class="get_rates_message" style="text-align: center; margin: 12px 0; font-style: italic; color: #a53831;"></div>
		</td>
		</tr></table>

		<div class="shipment_message" style="display: none; text-align: center; margin: 12px 0; font-style: italic; color: #a53831;"></div>

		<div class="shipment_generate_buttons" style="display: none; margin: 12px 0; padding: 6px 0 0 0; border-top: 1px dotted #aaaaaa; text-align: center;">
			<button class="confirm">Reconfirm Address and Rates</button> &nbsp; 
			<button class="generate">Generate <?php echo ($dataRow['is_return_shipment'] == 1 ? "Return" : "Outbound") ?> Label</button> &nbsp; 
			<button class="cancel">Cancel</button>
		</div>
		
		<table class="shipment_window_table" cellpadding="0" cellspacing="9" width="100%">
		</table>
		<div class="shipment_window_loader" style="display: none; text-align: center"><img src="tmpl/ajax-loader.gif"></div>
		<input type="hidden" name="address_id" value="">
		<input type="hidden" name="dealer_name" value="<?php echo $dealerArray['dealer_name'] ?>">
		<input type="hidden" name="dealer_phone_number" value="<?php echo $dealerArray['phone_number'] ?>">
		<input type="hidden" name="dealer_address" value="<?php echo $dealerArray['dealer_address'] ?>">
		<input type="hidden" name="dealer_city" value="<?php echo $dealerArray['dealer_city'] ?>">
		<input type="hidden" name="dealer_state" value="<?php echo $dealerArray['dealer_state'] ?>">
		<input type="hidden" name="dealer_zip_code" value="<?php echo $dealerArray['dealer_zip_code'] ?>">

	</div>
</div>

