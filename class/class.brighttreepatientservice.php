<?php
/*
	Class: BrighttreePatientService
*/

Class BrighttreePatientService {
		
	public $url;	
	public $username;
	public $password;
	public $xml_post_string='<?xml version="1.0" encoding="utf-8"?>
                            <soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/" xmlns:i="http://www.w3.org/2001/XMLSchema-instance">
                              <soap:Body xmlns:b="http://schemas.datacontract.org/2004/07/Brightree.ExternalAPI.CanonicalObjects.OrderEntry">
                                %s
                              </soap:Body>
                            </soap:Envelope>';
	
	function __construct($url,$username,$password) {
       $this->url=$url;
	   $this->username=$username;
	   $this->password=$password;
	}
	
	/*
	 ** Get Pateint By BrighttreeID
	*/

	function PatientFetchByBrightreeID($BrightreeID)
	{
		$SOAPAction="http://www.brightree.com/external/PatientService/IPatientService/PatientFetchByBrightreeID";
		$specific_post_string='<PatientFetchByBrightreeID xmlns="http://www.brightree.com/external/PatientService">
                                  <BrightreeID>'.$BrightreeID.'</BrightreeID> 
                                </PatientFetchByBrightreeID>';
		$xml_post_string=sprintf($this->xml_post_string,$specific_post_string);
		return $this->getResults($SOAPAction,$xml_post_string);
	}
	
	/**
	** function to update patient by BrighttreeID
	*/
	
	function PatientUpdate($BrightreeID,$Patient)
	{
		$SOAPAction="http://www.brightree.com/external/PatientService/IPatientService/PatientUpdate";
		$specific_post_string='<PatientUpdate xmlns="http://www.brightree.com/external/PatientService" >
									<BrightreeID>'.$BrightreeID.'</BrightreeID>'
									.$Patient.
                                '</PatientUpdate>';		
		$xml_post_string=sprintf($this->xml_post_string,$specific_post_string);
		return $this->getResults($SOAPAction,$xml_post_string);		
	}
	
	
	
	/*
	** Function to call cURL	
	*/
	function getResults($SOAPAction,$xml_post_string){
		$req = curl_init($this->url);
		$headers = array(
                        "Content-type: text/xml;charset=\"utf-8\"",
                        "Accept: text/json",
                        "Cache-Control: no-cache",
                        "Pragma: no-cache",
                        "SOAPAction: ".$SOAPAction, 
                        "Content-length: ".strlen($xml_post_string),
                    );
		curl_setopt_array($req, array(
			CURLOPT_RETURNTRANSFER => TRUE,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_USERPWD => "$this->username:$this->password",	
			CURLOPT_POST => 1,
			CURLOPT_POSTFIELDS => $xml_post_string,
			CURLOPT_HTTPHEADER => $headers,
		));	
		
		$result = curl_exec($req);
		$error = curl_errno($req);
		
		if($error){
			echo "ERROR: <pre>".print_r(curl_error($req), true)."</pre>";
			die();
		}
		else{
			return $result;
		}
	}
}