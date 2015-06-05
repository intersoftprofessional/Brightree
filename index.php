<?php
require_once("class/class.brighttreepatientservice.php");
require_once('class/USPSAddressVerify.php');

//Initiate and set the username password provided from brighttree
$obj = new BrighttreePatientService("https://webservices.brightree.net/v0100-1302/OrderEntryService/PatientService.svc","apiuser@GenevaWoodsSBX","gw2015!!");


$result = $obj->PatientSearch('2014-11-03','2015-01-01');

$xml = simplexml_load_string((string) $result);
$totalRecords = $xml->children('s',true)->children()->PatientSearchResponse->children()->PatientSearchResult->children('a',true)->TotalItemCount;
$records =$xml->children('s',true)->children()->PatientSearchResponse->children()->PatientSearchResult->children('a',true)->Items->children('b',true)->PatientSearchResponse;

//Initiate and set the username provided from usps
$verify = new USPSAddressVerify('272REACH6842');

//traverse to all records
foreach($records as $key => $record)
{
	//get brightreeID
	$BrightreeID = (string) $record->children('b',true)->BrightreeID;
	
	//Get object of patient from brightree 
	$patient = $obj->PatientFetchByBrightreeID($BrightreeID);

	$xml = simplexml_load_string((string) $patient);

	$patient =$xml->children('s',true)->children()->PatientFetchByBrightreeIDResponse->children()->PatientFetchByBrightreeIDResult
			->children('a',true)->Items->children('b',true)->Patient;



	//get delivery address of patient
	$AddressLine1= (string) $patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->AddressLine1;
	$AddressLine2= (string) $patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->AddressLine2;
	$AddressLine3= (string) $patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->AddressLine3;
	$City= (string) $patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->City;
	$Country= (string) $patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->Country;
	$County= (string) $patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->County;
	$PostalCode= (string) $patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->PostalCode;
	$State= (string) $patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->State;


	// Create new address object and assign the properties
	// apartently the order you assign them is important so make sure
	// to set them as the example below
	$address = new USPSAddress;
	$address->setFirmName('');
	$address->setApt($AddressLine2);
	$address->setAddress($AddressLine1);
	$address->setCity($City);
	$address->setState($State);
	$address->setZip5($PostalCode);
	$address->setZip4('');

	// Add the address object to the address verify class
	$verify->addAddress($address);

	// Perform the request and return result
	$verify->verify();

	$correctAddress= $verify->getArrayResponse();

	if($verify->isSuccess()) {
			//Now Update the correct deliveryAddress in the patient object		
			
			$patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->AddressLine1 = $correctAddress['AddressValidateResponse']['Address']['Address2'];
			$patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->AddressLine2 = $correctAddress['AddressValidateResponse']['Address']['Address1'];	
			$patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->City =$correctAddress['AddressValidateResponse']['Address']['City'];				
			$patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->PostalCode =$correctAddress['AddressValidateResponse']['Address']['Zip5'];
			$patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->State =$correctAddress['AddressValidateResponse']['Address']['State'];


			//unset unwanted objects from xml
			unset($patient->BrightreeID);
			unset($patient->ExternalID);

			$patientObjXML= str_replace(
				array("<b:Patient>","</b:Patient>"),
				array("<Patient>", "</Patient>"),
				$patient->asXML()
			);

			// Update patient object on brighttree
			echo $obj->PatientUpdate($BrightreeID,$patientObjXML);
			
	}else {
	  echo 'Error with BrighttreeID '.$BrightreeID.' : ' . $verify->getErrorMessage();
	}	
}