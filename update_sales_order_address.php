<?php
require_once("class/class.brighttreesalesorderservice.php");
require_once('class/USPSAddressVerify.php');

$startdate = date('Y-m-d', strtotime("-1 days"));
//$startdate = '2015-01-01';
$enddate = date('Y-m-d');

//Initiate and set the username password provided from brighttree
$obj = new BrighttreeSalesOrderService("https://webservices.brightree.net/v0100-1501/OrderEntryService/SalesOrderService.svc","apiuser@GenevaWoodsSBX","gw2015!!");


$result = $obj->SalesOrderSearch($startdate,$enddate);

$xml = simplexml_load_string((string) $result);

$totalRecords = $xml->children('s',true)->children()->SalesOrderSearchResponse->children()->SalesOrderSearchResult->children('a',true)->TotalItemCount;
$records =$xml->children('s',true)->children()->SalesOrderSearchResponse->children()->SalesOrderSearchResult->children('a',true)->Items->children('b',true)->SalesOrderSearchResponse;


if($records && ( count($records) > 0)) {
	echo '<table border="1"><tr><th>Sales Order Brightree ID</th><th>Patient Name</th><th>Status</th><th>Results</th></tr>';
	foreach($records as $key => $record)
	{
		//echo '<pre>'.print_r($record, true).'</pre>';
		//get brightreeID
		$BrightreeID = (string) $record->children('b',true)->BrightreeID;
		
		//Get object of patient from brightree 
		$sales_order = $obj->SalesOrderFetchByBrightreeID($BrightreeID);
		
		$xml = simplexml_load_string((string) $sales_order);

		$sales_order =$xml->children('s',true)->children()->SalesOrderFetchByBrightreeIDResponse->children()->SalesOrderFetchByBrightreeIDResult
				->children('a',true)->Items->children('b',true)->SalesOrder;
				
		$Address = $sales_order->children('b',true)->DeliveryInfo->children('b',true)->Address->children('c',true); 
		
		//get sales order information
		$sales_order_status = (string) $record->children('b',true)->Status;
		$sales_order_patient = (string) $record->children('b',true)->Patient->children('c',true)->Value;
		$sales_order_CreateDate = (string) $record->children('b',true)->CreateDate;
		
		
		//get delivery address of patient
		$AddressLine1= (string) $Address->AddressLine1;
		$AddressLine2= (string) $Address->AddressLine2;
		$AddressLine3= (string) $Address->AddressLine3;
		$City= (string) $Address->City;
		$Country= (string) $Address->Country;
		$County= (string) $Address->County;
		$PostalCode= (string) $Address->PostalCode;
		$State= (string) $Address->State;
		
		//get taxzone of sales order
		$taxZoneID = $sales_order->children('b',true)->DeliveryInfo->children('b',true)->TaxZone->children('c',true)->ID; 
		$taxZoneValue = $sales_order->children('b',true)->DeliveryInfo->children('b',true)->TaxZone->children('c',true)->Value;
		
		//Initiate and set the username provided from usps
		$verify = new USPSAddressVerify('272REACH6842');

		// Create new address object and assign the properties
		// apartently the order you assign them is important so make sure
		// to set them as the example below
		$address = new USPSAddress;
		$address->setFirmName('');
		$address->setApt(trim($AddressLine2));
		$address->setAddress(trim($AddressLine1));
		$address->setCity(trim($City));
		$address->setState(trim($State));
		$address->setZip5(trim($PostalCode));
		$address->setZip4('');

		// Add the address object to the address verify class
		$verify->addAddress($address);

		// Perform the request and return result
		$verify->verify();

		$correctAddress= $verify->getArrayResponse();
		
		if($verify->isSuccess()) {	
			
			//Now Update the correct deliveryAddress in the sales order object		
				
				$sales_order->children('b',true)->DeliveryInfo->children('b',true)->Address->children('c',true)->AddressLine1 = (isset($correctAddress['AddressValidateResponse']['Address']['Address2'])) ? trim($correctAddress['AddressValidateResponse']['Address']['Address2']) : '';
				
				$sales_order->children('b',true)->DeliveryInfo->children('b',true)->Address->children('c',true)->AddressLine2 = (isset($correctAddress['AddressValidateResponse']['Address']['Address1'])) ? trim($correctAddress['AddressValidateResponse']['Address']['Address1']) : '';
				
				$sales_order->children('b',true)->DeliveryInfo->children('b',true)->Address->children('c',true)->City = trim($correctAddress['AddressValidateResponse']['Address']['City']);				
				
				$sales_order->children('b',true)->DeliveryInfo->children('b',true)->Address->children('c',true)->PostalCode =trim($correctAddress['AddressValidateResponse']['Address']['Zip5']);
				
				$sales_order->children('b',true)->DeliveryInfo->children('b',true)->Address->children('c',true)->State =trim($correctAddress['AddressValidateResponse']['Address']['State']);
			
				$sales_order->children('b',true)->DeliveryInfo->children('b',true)->TaxZone->children('c',true)->ID = '2';
			
				//$sales_order->children('b',true)->DeliveryInfo->children('b',true)->TaxZone->children('c',true)->Value = 'New Tax Zone';
								
				$SalesOrderObjXML= str_replace(
					array("<b:SalesOrder>","</b:SalesOrder>"),
					array("<SalesOrder>", "</SalesOrder>"),
					$sales_order->asXML()
				);
				
				
				
				// Update sales order object on brighttree			
				$resultxml = simplexml_load_string((string) $obj->SalesOrderUpdate($BrightreeID,$SalesOrderObjXML));	
				
				//show result				
				if( (bool) $resultxml->children('s',true)->children()->SalesOrderUpdateResponse->children()->SalesOrderUpdateResult->children('a',true)->Success)
				{
					echo "<td>$BrightreeID</td><td>$sales_order_patient</td><td>$sales_order_status</td><td>Updated successfully</td>";
				}else{
					echo "<td>$BrightreeID</td><td>$sales_order_patient</td><td>$sales_order_status</td><td>".$resultxml->children('s',true)->children()->PatientUpdateResponse->children()->PatientUpdateResult->children('a',true)->Messages."</td>";					
				}
				
		}else {
		  echo "<td>$BrightreeID</td><td>$sales_order_patient</td><td>$sales_order_status</td><td>Error : ". $verify->getErrorMessage()."</td>";		  
		}
		echo '</tr>';
		$verify = NULL;		
	}
		echo '</table>';
}else {
	echo 'No Sales Order Created b/w '.$startdate.' to '.$enddate;
}