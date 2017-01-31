//################################################################
//# This software is the unpublished, confidential, proprietary, 
//# intellectual property of zipperSNAP, LLC and may not be copied,
//# duplicated, retransmitted or used in any manner without
//# expressed written consent from zipperSNAP, LLC.
//# Copyright 2009 - Present, zipperSNAP, LLC.
//################################################################

$(document).ready(function () {

	$("#new_button").add("#new_outbound").add("#new_return").add("#shipment_button").click(function(){
	
		if ($("#label_saved").val() == 1) {
			var processorDiv = $("#shipment_window_wrapper").find(".processor_div").clone();
			var shipmentId = $("#shipment_id").val();
			$(processorDiv).find(".shipment_window_table").append("<tr><td width='16'></td><td>Loading previously saved label...</td></tr>");
			$(processorDiv).find(".shipment_message").html("");
			$.fancybox({
				'modal':true,
				'overlayColor':'#000',
				'content':processorDiv,
				'onComplete':function() {
					createLabelFile(shipmentId,processorDiv);
				}
			});
			return false;
		}
                $('.dimension').css("text-align","center").css("font-weight","bold");
		// when fancybox closes it discards the loaded div, so it won't have anything to reopen
		// need to clone the processor_div, then load it, so original will still be available
		var processorDiv = $("#new_shipment_window_wrapper").find(".processor_div").clone();

		$(processorDiv).find("input[name=ammo]").change(function() {
			$("#hazardous").val( ($(processorDiv).find("input[name=ammo]").is(":checked") ? "yes" : "no") );
		});
		
		$(processorDiv).find("input, select").click(function() { 
			$(this).closest("td").css("background-color","");
			$(processorDiv).find(".shipment_message").html("").slideUp('fast'); 
		});
		$(processorDiv).find(".cancel").click(function() {
			$(processorDiv).remove();
			$.fancybox.close();
		});
		$(processorDiv).find("input[name=address_type]:eq(0)").prop("checked", true);
		$(processorDiv).find("input[name=signature_required]:eq(0)").prop("checked", true); 
		$(processorDiv).find("input[name=address_type]").change(function() {
			if ($(processorDiv).find("input[name=address_type]:checked").val() == "BUS") {
				$(processorDiv).find(".address_label_row").slideDown('fast');
				$(processorDiv).find(".signature_requred_row").slideUp('fast');
			} else {
				$(processorDiv).find(".address_label_row").slideUp('fast');
				$(processorDiv).find(".signature_requred_row").slideDown('fast');
			}
		});

		$(processorDiv).find(".shipment_details_table").change(function(){

         $(processorDiv).find(".generate").prop('disabled',true).css('opacity', 0.5);
		});


  //     $(processorDiv).find(".shipment_details_table_Insurance").click(function(){
  //        $(processorDiv).find(".generate").prop('disabled',true).css('opacity', 0.5);
		// });


		$(processorDiv).find(".requires_signature").change(function() {
			if ($(processorDiv).find(".requires_ffl:checked").length > 0) {
				$(processorDiv).find(".ffl_number_row").show();
			} else {
				$(processorDiv).find(".ffl_number_row").hide();
			}
                        //Verification excluded based on the request - POBF-230
			/*if ($(processorDiv).find("input[name=handguns]").is(":checked")) {
				$(processorDiv).find(".address_type_row").hide();
			} else {
				$(processorDiv).find(".address_type_row").show();
			}*/
			/*if ($(processorDiv).find(".requires_signature:checked").length > 0) {
				$(processorDiv).find("input[name=signature_required]:eq(0)").prop("checked", true); 
				$(processorDiv).find("input[name=signature_required]:eq(1)").prop("disabled", true); 
			} else {
				$(processorDiv).find("input[name=signature_required]:eq(1)").prop("disabled", false);
				$(processorDiv).find("input[name=signature_required]:eq(1)").prop("checked", true); 
			}*/
			if ($(processorDiv).find("input[name=handguns]").is(":checked") && $(processorDiv).find("input[name=ammo]").is(":checked")) {
				$(processorDiv).find(".confirm").prop('disabled',true).css('opacity', 0.5);
				$(processorDiv).find(".get_rates_message").html("Handguns and ammo cannot be shipped together");
			} else {
				$(processorDiv).find(".confirm").prop('disabled',false).css('opacity', '');
				$(processorDiv).find(".get_rates_message").html("");
			}
/*sangeetha-Adult Signature enable/disable functionality*/
				if ($(processorDiv).find("input[name=handguns]").is(":checked") || $(processorDiv).find("input[name=ammo]").is(":checked") || $(processorDiv).find("input[name=longguns]").is(":checked")) {
				$(processorDiv).find("input[name=signature_required]:eq(0)").prop("checked", true);
				$(processorDiv).find(".rad").prop('checked',false);
				$(processorDiv).find(".rad").prop('disabled',true).css('opacity', 0.5);
				 
				
				} else {
				
				$(processorDiv).find(".rad").prop('disabled',false).css('opacity', '');
				$(processorDiv).find(".rad").prop('checked',true);
				
				}
		});

/*sangeetha-Adult Signature enable/disable functionality*/

		
		$(processorDiv).find(".search_button").click(function() {
			searchAddresses(processorDiv);
		});
		$(processorDiv).find("input[name=search_text]").keyup(function(e){
			if (e.which == 13 && $(this).val().length > 1) {
				searchAddresses(processorDiv);
			}
		});
		
		$(processorDiv).find(".calculate_insurance").click(function() {
			confirmAddress(processorDiv);
			calculateInsurance(processorDiv);
		});

		$(processorDiv).find("input[name=add_shipping_insurance]").change(function() {
			setInsuranceAmount(processorDiv);
		});

		if ($(this).attr('id') == "shipment_button") {
                        $(processorDiv).find("#dimension_label_row").remove();
			loadAddress(processorDiv,$("#address_id").val(),false);
			$(processorDiv).find("input[name=ship_weight]").val($("#shipping_weight").val());
			$(processorDiv).find(".shipment_defaults").show();
			$(processorDiv).find(".shipment_defaults").html("Shipping charge was " + formatCurrency($("#shipping_charge").val()) + " (" + $("#shipping_method").val() + ")" );
			
			if ($("#insurance_charge").val().length > 0) {
				$(processorDiv).find(".shipment_defaults").append("<br>Insurance charge was " + formatCurrency($("#insurance_charge").val()) );
			}
			
			if ($("#order_number").val().length > 0) {
				$(processorDiv).find(".shipment_window_heading").text("Generate Shipping Label for Order " + $("#order_number").val());
				$(processorDiv).find(".address_prompt").text("Or edit the customer's shipping address...");
			} else {
				$(processorDiv).find(".shipment_window_heading").text("Generate " + ($("#is_return_shipment").val() == 1 ? "Return" : "Outbound") + " Label");
				$(processorDiv).find(".address_prompt").text("Or edit the shipping address...");
			}
		} else {
                        
			$(processorDiv).find(".shipment_defaults").hide();
			if ($(this).attr('id') == "new_outbound") {
				$("#is_return_shipment").val(0);
				$(processorDiv).find(".shipment_window_heading").html("New Outbound Shipment &nbsp;&rarr;");
				$(processorDiv).find(".generate").button({label:'Generate Outbound Label'});
			} else {
				$("#is_return_shipment").val(1);
				$(processorDiv).find(".shipment_window_heading").html("New Return Shipment &nbsp;&larr;");
				$(processorDiv).find(".generate").button({label:'Generate Return Label'});                               
			}
		}

		$.fancybox({
			'modal':true,
			'overlayColor':'#000',
			'content':processorDiv,
			'onComplete':function(){
				$(processorDiv).find(".confirm").click(function() { 
					confirmAddress(processorDiv); 
				});
			}
		});
		return false;
	});
	if($("#shipping_method_id").val()=='1')
                {
                    $("#shipment_button").hide();
                }
                
	$("#reprint_button").click(function(){
		var shipmentId = $(this).data('shipment_id');
		// when fancybox closes it discards the loaded div, so it won't have anything to reopen
		// need to clone the processor_div, then load it, so original will still be available
		var processorDiv = $("#shipment_window_wrapper").find(".processor_div").clone();
		$(processorDiv).find(".shipment_window_table").append("<tr><td width='16'></td><td>Retrieving shipping and payment records...</td></tr>");
		$(processorDiv).find(".shipment_message").html("");
		$.fancybox({
			'modal':true,
			'overlayColor':'#000',
			'content':processorDiv,
			'onComplete':function(){
				confirmShipmentRecord(shipmentId,processorDiv);
			}
		});
		return false;
	});
	
});

function calculateInsurance(processorDiv) {
        if(($(processorDiv).find("input[name=package_value]").val()) > 4000) 
        {
            $(processorDiv).find(".generate").prop('disabled',true).css('opacity', 0.5);
            error = showError("package_value","Declared Value cannot exceed $4,000",processorDiv);
        }
        else
        {
           $(processorDiv).find(".generate").prop('disabled',false).css('opacity', '');
        }
	if ($(processorDiv).find("input[name=package_value]").val() == "" || $(processorDiv).find("input[name=package_value]").val().length < 1) {
		error = showError("package_value","Enter the total insured value of this package",processorDiv);
		return false;
	}
	$.ajax({
		url: $("#g_php_self").val(),
		type: "POST",
		data: {'action':'get_insurance','item_value':$(processorDiv).find("input[name=package_value]").val()},
		success: function(returnArray) {
			if (returnArray['status'] == "success") {
				$(processorDiv).find(".add_insurance_option").show();
				$(processorDiv).find(".insurance_amount_option").text(parseFloat(returnArray['insurance_amount']).toFixed(2));
			} else {
				oops = showError("package_value","Error retrieving insurance cost",processorDiv);
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) {
			oops = showError("package_value","System error retrieving insurance cost",processorDiv);
		},
		dataType: "json"
	});

};

function setInsuranceAmount(processorDiv) {
	if ($(processorDiv).find("input[name=add_shipping_insurance]").is(":checked")) {
		$("#insurance_charge").val($(processorDiv).find(".insurance_amount_option").text());
                //added below line to assign value to insured_package_value
                $("#insured_package_value").val($(processorDiv).find("input[name=package_value]").val());
	} else {
		$("#insurance_charge").val("");
                //added below line to assign value to insured_package_value
                $("#insured_package_value").val("");
	}
}

function searchAddresses(processorDiv) {

	$(processorDiv).find(".shipment_message").html("").hide();

	if ($(processorDiv).find("input[name=search_text]").val() == "" || $(processorDiv).find("input[name=search_text]").val().length < 1) {
		$(processorDiv).find(".search_message").html("Enter something to search for").slideDown('fast');
		$(processorDiv).find("input[name=search_text]").focus(function(){ $(processorDiv).find(".search_message").slideUp('fast') });
		return false;
	}
	$(processorDiv).find(".search_results").html("");
	$(processorDiv).find(".search_button").prop('disabled',true).css('opacity', 0.5);
	$(processorDiv).find(".search_results_loader").slideDown('fast');
	$.ajax({
		url: $("#g_php_self").val(),
		type: "POST",
		data: {'action':'search_addresses','search_text':$(processorDiv).find("input[name=search_text]").val()},
		success: function(returnArray) {
			if (returnArray['status'] == 'success') {
				$(processorDiv).find(".search_message").html("").hide();
				switch(returnArray['address_count']) {
					case 0:
						$(processorDiv).find(".search_results_loader").slideUp('fast');
						$(processorDiv).find(".search_message").html("'" + $(processorDiv).find("input[name=search_text]").val() + "' not found").slideDown('fast');
						break;
					case 1:
						$(processorDiv).find("input[name=search_text]").val("");
						var addressRow = returnArray['address'].split('|');
						loadAddress(processorDiv,addressRow[0],true);
						break;
					default:
						$(processorDiv).find(".search_results_loader").slideUp('fast');
						$(processorDiv).find("input[name=search_text]").val("");
						// build a select element with results
						var theDiv = $(processorDiv).find(".search_results");
						$(theDiv).append("<select name='use_address' style='width: 310px;'>");
						$(theDiv).find("select[name=use_address]").append("<option value=''>Select...</option>");
						$.each(returnArray['addresses'], function(key,row) {
							var addressRow = row.split('|');
							$(theDiv).find("select[name=use_address]").append("<option value='" + addressRow[0] + "'>" + addressRow[1] + ": " + addressRow[3] + ", " + addressRow[4] + " " + addressRow[5] + "</option>");
						});
						$(theDiv).append("</select>");
						$(theDiv).find("select[name=use_address]").change(function() {
							$(theDiv).find("select[name=use_address]").remove();
							loadAddress(processorDiv,$(this).val(),true);
						});
						break;
				}
				$(processorDiv).find(".search_button").prop('disabled',false).css('opacity', '');
			} else {
				$(processorDiv).find(".search_results_loader").slideUp('fast');
				$(processorDiv).find(".search_message").html(returnArray['message'] + ". Please try again!").slideDown('fast');
				$(processorDiv).find(".search_button").prop('disabled',false).css('opacity', '');
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) {
    		$(processorDiv).find(".search_results_loader").slideUp('fast');
			$(processorDiv).find(".search_message").html("System Error: " + errorThrown + ". Please try again!").slideDown('fast');
			$(processorDiv).find(".search_button").prop('disabled',false).css('opacity', '');
		},
		dataType: "json"
	});
	return false;
}
	
function loadAddress(processorDiv,addressId,fromSearch) {

	$(processorDiv).find(".shipment_message").html("").hide();

	$(processorDiv).find(".search_button").prop('disabled',true).css('opacity', 0.5);
	$(processorDiv).find(".search_results_loader").slideDown('fast');
	$.ajax({
		url: $("#g_php_self").val(),
		type: "POST",
		data: {'action':'load_address','address_id':addressId},
		success: function(returnArray) {
			if (returnArray['status'] == 'success') {
				$(processorDiv).find(".search_results_loader").slideUp('fast');
				$(processorDiv).find("input[name=search_text]").val("");
				
				// returnArray['address'] = info from address record, plus phone and email from contact record
				// in case address record doesn't have this info yet
				var addressRow = returnArray['address'].split('|');
				
				if (!fromSearch) {
				// store address record values in crc for each field
					$(processorDiv).find("input[name=ship_name]").data('crc',getCrc32(addressRow[1]));
					$(processorDiv).find("input[name=ship_address_label]").data('crc',getCrc32(addressRow[2]));
					$(processorDiv).find("input[name=ship_address]").data('crc',getCrc32(addressRow[3]));
					$(processorDiv).find("input[name=ship_city]").data('crc',getCrc32(addressRow[4]));
					$(processorDiv).find("select[name=ship_state]").data('crc',getCrc32(addressRow[5]));
					$(processorDiv).find("input[name=ship_zip]").data('crc',getCrc32(addressRow[6]));
					$(processorDiv).find("input[name=ship_phone]").data('crc',getCrc32(addressRow[7]));
					$(processorDiv).find("input[name=ship_email]").data('crc',getCrc32(addressRow[8]));
					$(processorDiv).find("input[name=address_type]").data('crc',getCrc32(returnArray['address_type']));
				}
				
				// set field values				
				$(processorDiv).find("input[name=address_id]").val(addressRow[0]);
				$(processorDiv).find("input[name=ship_name]").val(addressRow[1]);
				$(processorDiv).find("input[name=ship_address_label]").val(addressRow[2]);
				$(processorDiv).find("input[name=ship_address]").val(addressRow[3]);
				$(processorDiv).find("input[name=ship_city]").val(addressRow[4]);
				$(processorDiv).find("select[name=ship_state]").val(addressRow[5]);
				$(processorDiv).find("input[name=ship_zip]").val(addressRow[6]);
				$(processorDiv).find("input[name=ship_phone]").val( (addressRow[7] == '' ? addressRow[10] : addressRow[7]) );
				$(processorDiv).find("input[name=ship_email]").val( (addressRow[8] == '' ? addressRow[11] : addressRow[8]) );
				if (returnArray['address_type'] == 'BUS') {
					$(processorDiv).find(".address_label_row").show();
					//$(processorDiv).find(".signature_requred_row").hide();
					$(processorDiv).find(".signature_requred_row").show();
					$(processorDiv).find("input[name=address_type]:eq(1)").prop("checked", true);
				} else {
					$(processorDiv).find(".address_label_row").hide();
					$(processorDiv).find(".signature_requred_row").show();
					$(processorDiv).find("input[name=address_type]:eq(0)").prop("checked", true);
				}
				$(processorDiv).find(".search_button").prop('disabled',false).css('opacity', '');
			} else {
				$(processorDiv).find(".search_results_loader").slideUp('fast');
				$(processorDiv).find(".search_message").html(returnArray['message'] + ". Please try again!").slideDown('fast');
				$(processorDiv).find(".search_button").prop('disabled',false).css('opacity', '');
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) {
    		$(processorDiv).find(".search_results_loader").slideUp('fast');
			$(processorDiv).find(".search_message").html("System Error: " + errorThrown + ". Please try again!").slideDown('fast');
			$(processorDiv).find(".search_button").prop('disabled',false).css('opacity', '');
		},
		dataType: "json"
	});
	return false;
}

function confirmAddress(processorDiv) {

	$(processorDiv).find(".shipment_message").html("").hide();
	$(processorDiv).find(".generate").unbind();

	var validated = validateFields(processorDiv);
	if (validated) {
		if ($(processorDiv).find(".shipment_service_table").find("tr").length > 0) {
			$(processorDiv).find(".shipment_service").slideUp('fast');
			$(processorDiv).find(".shipment_service_table").find("tr").remove();
		}
		$(processorDiv).find(".confirm").prop('disabled',true).css('opacity', 0.5);
		$(processorDiv).find(".shipment_window_loader").slideDown('fast');
		var postFields = { 'action':'validate' }
		postFields['address'] = $(processorDiv).find("input[name=ship_address]").val();
		postFields['unit'] = $(processorDiv).find("input[name=ship_unit]").val();
		postFields['city'] = $(processorDiv).find("input[name=ship_city]").val();
		postFields['state'] = $(processorDiv).find("select[name=ship_state]").val();
		postFields['zip'] = $(processorDiv).find("input[name=ship_zip]").val();
		$.ajax({
			url: "/scpt/fedex/validate_address.php",
			type: "POST",
			data: postFields,
			success: function(returnArray) {
				if (returnArray['status'] == 'success') {

					$(processorDiv).find(".generate").prop('disabled',false).css('opacity', '');
					
					if (typeof returnArray['debug'] != 'undefined') {
						alert(returnArray['debug']);
					} 

					if (jQuery.inArray(returnArray['DeliveryPointValidation'],["CONFIRMED","UNCONFIRMED","UNAVAILABLE"])!==-1 && (returnArray['ResidentialStatus']!=="NOT_APPLICABLE_TO_COUNTRY" )) {
						$(processorDiv).find("input[name=ship_address]").val(returnArray['StreetLines']);
						$(processorDiv).find("input[name=ship_unit]").val("");
						$(processorDiv).find("input[name=ship_city]").val(returnArray['City']);
						if(returnArray.hasOwnProperty('StateOrProvinceCode'))
                                                {
                                                    $(processorDiv).find("select[name=ship_state]").val(returnArray['StateOrProvinceCode']);
                                                }
                                                else
                                                {
                                                    $(processorDiv).find("select[name=ship_state]").val(postFields['state']);
                                                }
						$(processorDiv).find("input[name=ship_zip]").val(returnArray['PostalCode']);
						
						if (returnArray['ResidentialStatus'] == 'BUSINESS') 
                                                 {
							$(processorDiv).find(".address_label_row").show();
							$(processorDiv).find("input[name=address_type]:eq(1)").prop("checked", true);
                                                                                                    
						 }  
                                                else 
                                                {
							$(processorDiv).find(".address_label_row").show();
							$(processorDiv).find("input[name=address_type]:eq(0)").prop("checked", true);
                                                        $(processorDiv).find('#incorrect_add').val('RES');
						}                                                
						
					} else {
						$(processorDiv).find(".shipment_window_loader").slideUp('fast');
						$(processorDiv).find(".shipment_message").html("Unable to confirm address: " + returnArray['message'] + ". Rates not guaranteed.").slideDown('fast');
						$(processorDiv).find(".confirm").prop('disabled',false).css('opacity', '');
					}
					getShippingRates(processorDiv,returnArray['DeliveryPointValidation']);
				} else {
		    		$(processorDiv).find(".shipment_window_loader").slideUp('fast');
					$(processorDiv).find(".shipment_message").html(returnArray['message'] + ". Please try again!").slideDown('fast');
					$(processorDiv).find(".confirm").prop('disabled',false).css('opacity', '');
				}
			},
    		error: function(XMLHttpRequest, textStatus, errorThrown) {
	    		$(processorDiv).find(".shipment_window_loader").slideUp('fast');
				$(processorDiv).find(".shipment_message").html("System Error: " + errorThrown + ". Please try again!").slideDown('fast');
				$(processorDiv).find(".confirm").prop('disabled',false).css('opacity', '');
			},
			dataType: "json"
		});
	}
	return false;
}

function getShippingRates(processorDiv,addressStatus) {

	if (addressStatus == "CONFIRMED") {
		$(processorDiv).find(".shipment_message").html("").hide();
	}

	// get rates for the address specified
	var postFields = { 'action':'get_rates' }
	if ($("#is_return_shipment").val() == 1) {
		postFields['name'] = $(processorDiv).find("input[name=dealer_name]").val();
		postFields['address'] = $(processorDiv).find("input[name=dealer_address]").val();
		postFields['city'] = $(processorDiv).find("input[name=dealer_city]").val();
		postFields['state'] = $(processorDiv).find("input[name=dealer_state]").val();
		postFields['zip'] = $(processorDiv).find("input[name=dealer_zip_code]").val();
		postFields['phone_number'] = $(processorDiv).find("input[name=dealer_phone_number]").val();
		postFields['shipper_name'] = $(processorDiv).find("input[name=ship_name]").val();
		postFields['shipper_address'] = $(processorDiv).find("input[name=ship_address]").val();
		postFields['shipper_city'] = $(processorDiv).find("input[name=ship_city]").val();
		postFields['shipper_state'] = $(processorDiv).find("select[name=ship_state]").val();
		postFields['shipper_zip_code'] = $(processorDiv).find("input[name=ship_zip]").val();
		postFields['shipper_phone_number'] = $(processorDiv).find("input[name=ship_phone]").val();
	} else {
		postFields['shipper_name'] = $(processorDiv).find("input[name=dealer_name]").val();
		postFields['shipper_address'] = $(processorDiv).find("input[name=dealer_address]").val();
		postFields['shipper_city'] = $(processorDiv).find("input[name=dealer_city]").val();
		postFields['shipper_state'] = $(processorDiv).find("input[name=dealer_state]").val();
		postFields['shipper_zip_code'] = $(processorDiv).find("input[name=dealer_zip_code]").val();
		postFields['shipper_phone_number'] = $(processorDiv).find("input[name=dealer_phone_number]").val();
		postFields['name'] = $(processorDiv).find("input[name=ship_name]").val();
		postFields['address'] = $(processorDiv).find("input[name=ship_address]").val();
		postFields['city'] = $(processorDiv).find("input[name=ship_city]").val();
		postFields['state'] = $(processorDiv).find("select[name=ship_state]").val();
		postFields['zip'] = $(processorDiv).find("input[name=ship_zip]").val();
		postFields['phone_number'] = $(processorDiv).find("input[name=ship_phone]").val();
	}
	postFields['total_weight'] = $(processorDiv).find("input[name=ship_weight]").val();
        postFields['inch_and_centimeter'] = $(processorDiv).find("select[name=inch_and_centimeter]").val();
        postFields['length'] = $(processorDiv).find("input[name=length_label_row]").val();
        postFields['width'] = $(processorDiv).find("input[name=width_label_row]").val();
        postFields['height'] = $(processorDiv).find("input[name=height_label_row]").val();
	postFields['address_type'] = $(processorDiv).find("input[name=address_type]:checked").val();
	postFields['signature_required'] = parseFloat($(processorDiv).find("input[name=signature_required]:checked").val());
	//postFields['signature_required'] += $(processorDiv).find(".requires_signature:checked").length;
	postFields['hazardous'] = ($(processorDiv).find("input[name=ammo]").is(":checked") ? "yes" : "no");
	$.ajax({
		url: "/scpt/fedex/get_rates.php",
		type: "POST",
		async: false,
		data: postFields,
		success: function(returnArray) {
			if (returnArray['status'] == 'success') {
				// possible rates
				//"FIRST_OVERNIGHT","PRIORITY_OVERNIGHT","STANDARD_OVERNIGHT","FEDEX_2_DAY_AM","FEDEX_2_DAY","FEDEX_EXPRESS_SAVER",
				//"GROUND_HOME_DELIVERY","FEDEX_GROUND"
				
				$(processorDiv).find(".shipment_address_buttons").slideUp('fast');
	    		$(processorDiv).find(".shipment_window_loader").slideUp('fast');
				$(processorDiv).find(".shipment_service").slideDown('fast');
				
				var addRow = "";
				if (parseFloat(returnArray['PRIORITY_OVERNIGHT']) > 0 && $(processorDiv).find("input[name='ammo']:checked").length < 1) {
					addRow = "<tr><td align='right'><input type='radio' name='service_type' value='9'></td>";
					addRow += "<td width='180'>Priority Overnight</td>";
					addRow += "<td class='rate right'>" + formatCurrency(returnArray['PRIORITY_OVERNIGHT']) + "</td></tr>";
					$(processorDiv).find(".shipment_service_table").append(addRow);
				}
				if (parseFloat(returnArray['STANDARD_OVERNIGHT']) > 0 && $(processorDiv).find("input[name='ammo']:checked").length < 1) {
					addRow = "<tr><td align='right'><input type='radio' name='service_type' value='3'></td>";
					addRow += "<td width='180'>Standard Overnight</td>";
					addRow += "<td class='rate right'>" + formatCurrency(returnArray['STANDARD_OVERNIGHT']) + "</td></tr>";
					$(processorDiv).find(".shipment_service_table").append(addRow);
				}
				if (parseFloat(returnArray['FEDEX_EXPRESS_SAVER']) > 0 && $(processorDiv).find("input[name='handguns']:checked").length < 1 && $(processorDiv).find("input[name='ammo']:checked").length < 1) {
					addRow = "<tr><td align='right'><input type='radio' name='service_type' value='4'></td>";
					addRow += "<td width='180'>FedEx Express Saver</td>";
					addRow += "<td class='rate right'>" + formatCurrency(returnArray['FEDEX_EXPRESS_SAVER']) + "</td></tr>";
					$(processorDiv).find(".shipment_service_table").append(addRow);
				}
				if (parseFloat(returnArray['FEDEX_2_DAY']) > 0 && $(processorDiv).find("input[name='ammo']:checked").length < 1) {
					addRow = "<tr><td align='right'><input type='radio' name='service_type' value='6'></td>";
					addRow += "<td width='180'>FedEx 2 Day</td>";
					addRow += "<td class='rate right'>" + formatCurrency(returnArray['FEDEX_2_DAY']) + "</td></tr>";
					$(processorDiv).find(".shipment_service_table").append(addRow);
				}
				if (parseFloat(returnArray['FEDEX_GROUND']) > 0 && $(processorDiv).find("input[name=handguns]:checked").length < 1) {
					addRow = "<tr><td align='right'><input type='radio' name='service_type' value='7'></td>";
					addRow += "<td width='180'>FedEx Ground</td>";
					addRow += "<td class='rate right'>" + formatCurrency(returnArray['FEDEX_GROUND']) + "</td></tr>";
					$(processorDiv).find(".shipment_service_table").append(addRow);
				}
				if (parseFloat(returnArray['GROUND_HOME_DELIVERY']) > 0 && $(processorDiv).find("input[name=handguns]:checked").length < 1) {
					addRow = "<tr><td align='right'><input type='radio' name='service_type' value='8'></td>";
					addRow += "<td width='180'>Ground Home Delivery</td>";
					addRow += "<td class='rate right'>" + formatCurrency(returnArray['GROUND_HOME_DELIVERY']) + "</td></tr>";
					$(processorDiv).find(".shipment_service_table").append(addRow);
				}
				
				$(processorDiv).find("input, select").click(function() { 
					$(this).closest("td").css("background-color","");
					$(processorDiv).find(".shipment_message").slideUp('fast'); 
				});

				
					$(processorDiv).find(".insurance_service").slideDown('fast');
				
				
				$(processorDiv).find(".confirm").prop('disabled',false).css('opacity', '');		
				$(processorDiv).find(".shipment_generate_buttons").slideDown('fast');
				$(processorDiv).find(".generate").click(function(){ generateShipmentRecord(processorDiv); });
				
			} else {
	    		$(processorDiv).find(".shipment_window_loader").slideUp('fast');
				$(processorDiv).find(".shipment_message").html("Unable to retrieve rates. Please try again!").slideDown('fast');
				$(processorDiv).find(".confirm").prop('disabled',false).css('opacity', '');			
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) {
    		$(processorDiv).find(".shipment_window_loader").slideUp('fast');
			$(processorDiv).find(".shipment_message").html("System Error: " + errorThrown + ". Please try again!").slideDown('fast');
			$(processorDiv).find(".confirm").prop('disabled',false).css('opacity', '');
		},
		dataType: "json"
	});
	return false;
}

function generateShipmentRecord(processorDiv) {

	var shippingMethodId = 0;

	$(".shipment_service_table").find("input").each(function() {
		if ($(this).is(":checked")) {
			shippingMethodId = $(this).val();
		}
	});
		
	if (typeof shippingMethodId == "undefined" || shippingMethodId < 1) {
		$(processorDiv).find(".shipment_message").html("Select the FedEx service you will use.").slideDown('fast');
		return false;
	}	

	$(processorDiv).find(".generate").unbind();
	$(processorDiv).find(".generate").prop('disabled',true).css('opacity', 0.5);
	$(processorDiv).find(".shipment_window_loader").slideDown('fast');
	//var shippingMethodId = $(processorDiv).find("input[name=service_type]:checked").val();
	var shippingRate = $(processorDiv).find("input[name=service_type]:checked").closest("tr").find(".rate").text().replace("$","");
	var postFields = { 'action':'create_shipment_record' }
	postFields['shipment_id'] = $("#shipment_id").val();
	postFields['is_return_shipment'] = $("#is_return_shipment").val();
	var addressChanged = changesMade();
	postFields['address_changed'] = (addressChanged ? 1 : 0);
	postFields['address_id'] = $(processorDiv).find("input[name=address_id]").val(); // this was set if an address was loaded
	postFields['shipping_weight'] = $(processorDiv).find("input[name=ship_weight]").val();
	postFields['shipping_charge'] = shippingRate;
        postFields['insurance_charge']=$("#insurance_charge").val();
        //below line is added to post "insured_package_value"
        postFields['insured_package_value']=$("#insured_package_value").val();
	postFields['shipping_method_id'] = shippingMethodId;
	if ($(processorDiv).find("input[name=handguns]:checked").length > 0) {
		postFields['address_type'] = 'BUS';
	} else {
		postFields['address_type'] = $(processorDiv).find("input[name=address_type]:checked").val();
	}
	if ($(processorDiv).find(".requires_ffl:checked").length > 0) {
		postFields['ffl_number'] = $(processorDiv).find("input[name=ffl_number]").val();
	}
	postFields['signature_required'] = parseFloat($(processorDiv).find("input[name=signature_required]:checked").val());
	//postFields['signature_required'] += $(processorDiv).find(".requires_signature:checked").length;
	postFields['name'] = $(processorDiv).find("input[name=ship_name]").val();
	postFields['ship_address_label'] = $(processorDiv).find("input[name=ship_address_label]").val();
	postFields['address'] = $(processorDiv).find("input[name=ship_address]").val();
	postFields['city'] = $(processorDiv).find("input[name=ship_city]").val();
	postFields['state'] = $(processorDiv).find("select[name=ship_state]").val();
	postFields['zip'] = $(processorDiv).find("input[name=ship_zip]").val();
	postFields['phone_number'] = $(processorDiv).find("input[name=ship_phone]").val();
	postFields['email_address'] = $(processorDiv).find("input[name=ship_email]").val();
	$.ajax({
		url: $("#g_php_self").val(),
		type: "POST",
		async: false,
		data: postFields,
		success: function(returnArray) {
			if (returnArray['status'] == 'success') {
				var shipmentId = returnArray['shipment_id'];
				
				//
				$(processorDiv).html($("#shipment_window_wrapper").find(".processor_div").clone());
				$(processorDiv).find(".shipment_window_table").append("<tr><td width='16'></td><td>Retrieving shipping record...</td></tr>");
				$(processorDiv).find(".shipment_message").html("");
				
				// we want to reload the page on cancel after this point
				$(processorDiv).find(".cancel").click(function() {
					document.location = $("#g_php_self").val();
				});
				
				confirmShipmentRecord(shipmentId,processorDiv);
				//
				
			} else {
	    		$(processorDiv).find(".shipment_window_loader").slideUp('fast');
				$(processorDiv).find(".shipment_message").html(returnArray['message']).slideDown('fast');
				$(processorDiv).find(".generate").click(function(){ generateShipmentRecord(processorDiv); });
				$(processorDiv).find(".generate").prop('disabled',false).css('opacity', '');			
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) {
			$(processorDiv).find(".shipment_window_loader").slideUp('fast');
			$(processorDiv).find(".shipment_message").html("System Error: " + errorThrown).slideDown('fast');
			$(processorDiv).find(".generate").click(function(){ generateShipmentRecord(processorDiv); });
			$(processorDiv).find(".generate").prop('disabled',false).css('opacity', '');			
		},
		dataType: "json"
	});
	return false;
}

function validateFields(processorDiv) {
	var error = false;
	if (!error && ($(processorDiv).find("input[name=ship_name]").val() == "" || $(processorDiv).find("input[name=ship_name]").val().length < 1)) {
		error = showError("ship_name","Shipping name cannot be blank",processorDiv);
	}
	if (!error && ($(processorDiv).find("input[name=ship_address]").val() == "" || $(processorDiv).find("input[name=ship_address]").val().length < 1)) {
		error = showError("ship_address","Shipping address cannot be blank",processorDiv);
	}
	if (!error && ($(processorDiv).find("input[name=ship_city]").val() == "" || $(processorDiv).find("input[name=ship_city]").val().length < 1)) {
		error = showError("ship_city","Shipping city cannot be blank",processorDiv);
	}
	if (!error && ($(processorDiv).find("select[name=ship_state]").val() == "" || $(processorDiv).find("select[name=ship_state]").val().length < 1)) {
		error = showError("ship_state","Shipping state cannot be blank",processorDiv);
	}
	if (!error && ($(processorDiv).find("input[name=ship_zip]").val() == "" || $(processorDiv).find("input[name=ship_zip]").val().length < 1)) {
		error = showError("ship_zip","Shipping zip code cannot be blank",processorDiv);
	}
	if (!error && ($(processorDiv).find("input[name=ship_phone]").val() == "" || $(processorDiv).find("input[name=ship_phone]").val().length < 1)) {
		error = showError("ship_phone","Phone number cannot be blank",processorDiv);
	}
	if (!error && ($(processorDiv).find("input[name=ship_weight]").val() == "" || $(processorDiv).find("input[name=ship_weight]").val().length < 1)) {
		error = showError("ship_weight","Enter the total weight for this package",processorDiv);
	}	
        if (!error && $(processorDiv).find("input[name=length_label_row]").length && ($(processorDiv).find("input[name=length_label_row]").val() == "" || $(processorDiv).find("input[name=length_label_row]").val().length < 1)) {
		error = showError("length_label_row","Dimensions Required",processorDiv);
	}
        if (!error && $(processorDiv).find("input[name=width_label_row]").length && ($(processorDiv).find("input[name=width_label_row]").val() == "" || $(processorDiv).find("input[name=width_label_row]").val().length < 1)) {
		error = showError("width_label_row","Dimensions Required",processorDiv);
	}
        if (!error && $(processorDiv).find("input[name=height_label_row]").length && ($(processorDiv).find("input[name=height_label_row]").val() == "" || $(processorDiv).find("input[name=height_label_row]").val().length < 1)) {
		error = showError("height_label_row","Dimensions Required",processorDiv);
	}
        if (!error && $(processorDiv).find("select[name=inch_and_centimeter]").length && ($(processorDiv).find("select[name=inch_and_centimeter]").val() == "" || $(processorDiv).find("select[name=inch_and_centimeter]").val().length < 1)) {
		error = showError("inch_and_centimeter","Dimensions Required",processorDiv);
	}
	if (!error && isNaN($(processorDiv).find("input[name=ship_weight]").val()) ) {
		error = showError("ship_weight","Enter the total weight as a number",processorDiv);
	}
	if (!error && $(processorDiv).find(".requires_ffl:checked").length > 0 && $(processorDiv).find("input[name=ffl_number]").val().length < 1) {
		error = showError("ffl_number","FFL number cannot be blank",processorDiv);
	}
	if (error === true) {
		return false;
	} else {
		return true;
	}
}

function showError(field,message,processorDiv) {
	var theField = $(processorDiv).find("input[name=" + field + "]");
	if (theField.length < 1) {
		theField = $(processorDiv).find("select[name=" + field + "]");
		if (theField.length < 1) {
			theField = $(processorDiv).find("radio[name=" + field + "]");
		}
	}
	$(theField).closest("td").css("background-color","#e66464");
	$(processorDiv).find(".shipment_message").html(message).slideDown('fast');
	return true;
}

function confirmShipmentRecord(shipmentId,processorDiv) {

	$(processorDiv).find(".shipment_message").html("").hide();

	$.ajax({
		url: $("#g_php_self").val(),
		type: "POST",
		async: true,
		data: {'action':'confirm_shipping_record','shipment_id':shipmentId},
		success: function(returnArray) {
			if (returnArray['status'] == 'success') {
				$(processorDiv).find(".shipment_window_table").find("tr").last().find("td").eq(0).html("<img src='tmpl/icon_checkmark.gif'>");
				if (typeof returnArray['payment_reference'] == 'undefined' || returnArray['payment_reference'].length < 1) {
					$(processorDiv).find(".shipment_window_table").append("<tr><td width='16'></td><td>Authorize credit card payment of <b>$" + returnArray['shipping_charge'] + " + Insurance $" +  returnArray['insurance_charge'] + "</b> for this shipment?</td></tr>");
					$(processorDiv).find(".shipment_window_loader").slideUp('fast');
					$(processorDiv).find(".close").hide();
					$(processorDiv).find(".cancel").show().click(function() { 
						$.fancybox.close();
						$(processorDiv).remove();
					});
					$(processorDiv).find(".authorize").show().click(function() { 
						authorizeCharge(shipmentId,processorDiv);
					});
					$(processorDiv).find(".shipment_window_buttons").slideDown('fast');
				} else {
					if (returnArray['label_saved'] == 'yes') {
						$(processorDiv).find(".shipment_window_table").append("<tr><td width='16'></td><td>Loading previously saved label...</td></tr>");
					} else {
						$(processorDiv).find(".shipment_window_table").append("<tr><td width='16'></td><td>Connecting to FedEx server...</td></tr>");
					}
					createLabelFile(shipmentId,processorDiv);
				}
			} else {
				$(processorDiv).find(".shipment_window_table").find("tr").last().find("td").eq(0).html("<img src='tmpl/icon_alert.gif'>");
				$(processorDiv).find(".shipment_window_loader").slideUp('fast');
				$(processorDiv).find(".shipment_message").html("ERROR1: " + returnArray['message']).slideDown('fast');
				if (returnArray['status'] == 'payment_info_missing') {
					$(processorDiv).find(".shipment_message").after("<div style='text-align: center;'>Use <a href='dealerpaymentmaintenance.php'><b>Dealer Payment Info</b></a> to set up your payment account...</div>");
				}
				$(processorDiv).find(".authorize").hide();
				$(processorDiv).find(".cancel").hide();
				$(processorDiv).find(".close").show().click(function() { $.fancybox.close(); });
				$(processorDiv).find(".shipment_window_buttons").slideDown('fast');
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) {
			$(processorDiv).find(".order_window_loader").slideUp('fast');
			$(processorDiv).find(".shipment_message").html("System Error: " + errorThrown).slideDown('fast');
			$(processorDiv).find(".authorize").hide();
			$(processorDiv).find(".cancel").hide();
			$(processorDiv).find(".close").show().click(function() { $.fancybox.close(); });
			$(processorDiv).find(".order_window_buttons").slideDown('fast');
		},
		dataType: "json"
	});
	return false;
}

function authorizeCharge(shipmentId,processorDiv) {

	$(processorDiv).find(".shipment_message").html("").hide();

	$(processorDiv).find(".shipment_window_buttons").slideUp('fast');
	$(processorDiv).find(".authorize").hide();
	$(processorDiv).find(".cancel").hide();
	$(processorDiv).find(".shipment_window_loader").slideDown('fast');
	$(processorDiv).find(".shipment_window_table").find("tr").last().find("td").eq(0).html("<img src='tmpl/icon_checkmark.gif'>");
	$(processorDiv).find(".shipment_window_table").append("<tr><td width='16'></td><td>Processing credit card payment...</td></tr>");
	processPayment(shipmentId,processorDiv);
}

function processPayment(shipmentId,processorDiv) {

	$(processorDiv).find(".shipment_message").html("").hide();

	$.ajax({
		url: $("#g_php_self").val(),
		type: "POST",
		async: true,
		data: {'action':'process_payment','shipment_id':shipmentId},
		success: function(returnArray) {
			if (typeof returnArray['debug'] != 'undefined') {
				alert(returnArray['debug']);
			}
			if (returnArray['status'] == 'success') {
				$(processorDiv).find(".shipment_window_table").find("tr").last().find("td").eq(0).html("<img src='tmpl/icon_checkmark.gif'>");
				$(processorDiv).find(".shipment_window_table").append("<tr><td width='16'></td><td>Connecting to FedEx server...</td></tr>");
				$("#shipping_info").find("td").eq(3).html(returnArray['payment_date_time']);
				$("#shipping_info").find("td").eq(4).html(returnArray['payment_reference']);
				createLabelFile(shipmentId,processorDiv);
			} else {
				$(processorDiv).find(".shipment_window_table").find("tr").last().find("td").eq(0).html("<img src='tmpl/icon_alert.gif'>");
				$(processorDiv).find(".shipment_window_loader").hide();
				$(processorDiv).find(".shipment_message").html("ERROR2: " + returnArray['message']).slideDown('fast');
				$(processorDiv).find(".close").show().click(function() { $.fancybox.close(); });
				$(processorDiv).find(".shipment_window_buttons").slideDown('fast');
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) {
			$(processorDiv).find(".order_window_loader").slideUp('fast');
			$(processorDiv).find(".shipment_message").html("System Error: " + errorThrown).slideDown('fast');
			$(processorDiv).find(".close").show().click(function() { $.fancybox.close(); });
			$(processorDiv).find(".order_window_buttons").slideDown('fast');
		},
		dataType: "json"
	});
	return false;
}

function createLabelFile(shipmentId,processorDiv) {


	$(processorDiv).find(".shipment_message").html("").hide();

	$.ajax({
		url: $("#g_php_self").val(),
		type: "POST",
		async: true,
		data: {'action':'create_shipping_label','shipment_id':shipmentId,'hazardous':$("#hazardous").val() },
		success: function(returnArray) {
			if (typeof returnArray['debug'] != 'undefined') {
				alert(returnArray['debug']);
			}

			if (returnArray['status'] == 'success') {
				$(processorDiv).find(".shipment_window_table").find("tr").last().find("td").eq(0).html("<img src='tmpl/icon_checkmark.gif'>");
				$(processorDiv).find(".shipment_window_loader").slideUp('fast');
				$(processorDiv).find(".close").show().click(function() {
					$.fancybox.close();
					deletePDF(returnArray['file_name']);
				});
				if (returnArray['reprint'] != 'yes') {
					$("#shipping_info").find("td").eq(5).html(returnArray['tracking_number']);
					$("#shipping_info").find("td").eq(6).html(returnArray['delivery_date']);
					$("#shipment_button").button('option', 'label', 'Reprint Shipping Label');
					$(processorDiv).find(".shipment_window_table").append("<tr><td width='16'><img src='tmpl/icon_checkmark.gif'></td><td>Shipping label saved!</td></tr>");
					if (returnArray['tracking_number'].length > 0 && returnArray['is_return_shipment'] == 0) {
						$(processorDiv).find(".shipment_window_table").append("<tr><td width='16'></td><td>Sending shipping update to customer...</td></tr>");
						sendCustomerEmail(shipmentId,processorDiv);
					}
				}
				$(processorDiv).find(".authorize").hide();
				$(processorDiv).find(".cancel").hide();
				$(processorDiv).find(".shipment_window_buttons").slideDown('fast');
				window.open('filecache/' + returnArray['file_name'], '_blank');
			} else {
				$(processorDiv).find(".shipment_window_table").find("tr").last().find("td").eq(0).html("<img src='tmpl/icon_alert.gif'>");
				$(processorDiv).find(".shipment_window_loader").hide();
				$(processorDiv).find(".shipment_message").html("ERROR3: " + returnArray['message']).slideDown('fast');
				$(processorDiv).find(".authorize").hide();
				$(processorDiv).find(".cancel").hide();
				$(processorDiv).find(".close").show().click(function() { document.location = $("#g_php_self").val(); });
				$(processorDiv).find(".shipment_window_buttons").slideDown('fast');
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) {
			$(processorDiv).find(".order_window_loader").hide();
			$(processorDiv).find(".shipment_message").html("System Error: " + errorThrown).slideDown('fast');
			$(processorDiv).find(".authorize").hide();
			$(processorDiv).find(".cancel").hide();
			$(processorDiv).find(".close").show().click(function() { document.location = $("#g_php_self").val(); });
			$(processorDiv).find(".order_window_buttons").slideDown('fast');
		},
		dataType: "json"
	});
	return false;
}

function sendCustomerEmail(shipmentId,processorDiv) {
	$.ajax({
		url: $("#g_php_self").val() + "?ajax=true",
		type: "POST",
		data: {action:'send_email',shipment_id:shipmentId},
		success: function(returnArray) {
			if (returnArray['status'] == 'success') {
				$(processorDiv).find(".shipment_window_table").find("tr").last().find("td").eq(0).html("<img src='tmpl/icon_checkmark.gif'>");
			} else {
				$(processorDiv).find(".shipment_window_table").find("tr").last().find("td").eq(0).html("<img src='tmpl/icon_alert.gif'>");
				$(processorDiv).find(".shipment_message").html("ERROR4: " + returnArray['message']).slideDown('fast');
			}
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) {
			$(processorDiv).find(".shipment_window_table").find("tr").last().find("td").eq(0).html("<img src='tmpl/icon_alert.gif'>");
			$(processorDiv).find(".shipment_message").html("ERROR5: " + returnArray['message']).slideDown('fast');
		},
		dataType: "json"
	});
	return false
}

function deletePDF(fileName) {
	$.ajax({
		url: $("#g_php_self").val() + "?ajax=true",
		type: "POST",
		data: {action:'delete_pdf',file_name:fileName},
		success: function(returnArray) {
			document.location = $("#g_php_self").val();
		},
		error: function(XMLHttpRequest, textStatus, errorThrown) {
			alert("System Error: " + errorThrown);
		},
		dataType: "json"
	});
	return false
}

