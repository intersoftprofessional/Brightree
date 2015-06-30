<?php
require_once("class/class.brighttreepatientservice.php");
require_once('class/USPSAddressVerify.php');
require_once('class/connection.php');

$startdate = date('Y-m-d', strtotime("-1 days"));
//$startdate ='2015-01-01';
$enddate = date('Y-m-d');

//Initiate and set the username password provided from brighttree
$obj = new BrighttreePatientService("https://webservices.brightree.net/v0100-1302/OrderEntryService/PatientService.svc","apiuser@GenevaWoodsSBX","gw2015!!");


$result = $obj->PatientSearch($startdate,$enddate);

$xml = simplexml_load_string((string) $result);
$totalRecords = $xml->children('s',true)->children()->PatientSearchResponse->children()->PatientSearchResult->children('a',true)->TotalItemCount;
$records =$xml->children('s',true)->children()->PatientSearchResponse->children()->PatientSearchResult->children('a',true)->Items->children('b',true)->PatientSearchResponse;

if($records && ( count($records) > 0)) {	
	echo '<table border="1"><tr><th>Patient ID</th><th>Patient Name</th><th>Results</th></tr>';
	//traverse to all records
	foreach($records as $key => $record)
	{
		echo '<tr>';
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
		
		//get name of patient				
		$patient_name_obj = $patient->children('b', true)->PatientGeneralInfo->children('b', true)->Name->children('c', true);
		$firstname= (string) $patient_name_obj->First;
		$lastname= (string) $patient_name_obj->Last;

		
		if($verify->isSuccess()) {
		
				//Now Update the correct deliveryAddress in the patient object		
				
				$patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->AddressLine1 = (isset($correctAddress['AddressValidateResponse']['Address']['Address2'])) ? trim($correctAddress['AddressValidateResponse']['Address']['Address2']) : '';
				$patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->AddressLine2 = (isset($correctAddress['AddressValidateResponse']['Address']['Address1'])) ? trim($correctAddress['AddressValidateResponse']['Address']['Address1']) : '';	
				$patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->City = trim($correctAddress['AddressValidateResponse']['Address']['City']);				
				$patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->PostalCode =trim($correctAddress['AddressValidateResponse']['Address']['Zip5']);
				$patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->State =trim($correctAddress['AddressValidateResponse']['Address']['State']);

				
				if($County) {
					//look for county in database				
					$result = mysql_query('select tax_code from county_taxzone_mapping where LOWER( county_taxzone_mapping.county ) = "'.strtolower($County).'"');
					

					if(! mysql_num_rows($result)) {
						//nil county value if does not exist in the database
						$patient->children('b',true)->PatientGeneralInfo->children('b',true)->DeliveryAddress->children('c',true)->County='';
					}
				}

				//unset unwanted objects from xml
				unset($patient->BrightreeID);
				unset($patient->ExternalID);

				$patientObjXML= str_replace(
					array("<b:Patient>","</b:Patient>"),
					array("<Patient>", "</Patient>"),
					$patient->asXML()
				);
				
				// Update patient object on brighttree			
				$resultxml = simplexml_load_string((string) $obj->PatientUpdate($BrightreeID,$patientObjXML));			
				
				//show result				
				if( (bool) $resultxml->children('s',true)->children()->PatientUpdateResponse->children()->PatientUpdateResult->children('a',true)->Success)
				{
					echo "<td>$BrightreeID</td><td>$firstname $lastname</td><td>Updated successfully</td>";
				}else{
					echo "<td>$BrightreeID</td><td>$firstname $lastname</td><td>".$resultxml->children('s',true)->children()->PatientUpdateResponse->children()->PatientUpdateResult->children('a',true)->Messages."</td>";					
				}				
				
		}else {
		  echo "<td>$BrightreeID</td><td>$firstname $lastname</td><td>Error : ". $verify->getErrorMessage()."</td>";		  
		}
		echo '</tr>';
		$verify = NULL;
	}
		echo '</table>';
}
else {
	echo 'No Patients Created b/w '.$startdate.' to '.$enddate;
}