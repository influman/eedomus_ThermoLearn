<?php

	// Import des fonctions communes
	include ('sources/fonctions.php');

	// Recuperation des codes API des péripheriques Consigne
	$nbzones = 0;
	$urlParametres =  "https://api.eedomus.com/get?action=periph.value_list&periph_id=".$api_param."&api_user=".$api_user."&api_secret=".$api_secret;
	$arrParametres = json_decode(utf8_encode(file_get_contents($urlParametres)));
	$listeParametres = $arrParametres->body->values;
	$apiDetecteur = array();
	$apiConsigne = array();
	$nomZone = array();
	$mode = 0;
	if (count($listeParametres) > 0) {
		$apiMode = substr($listeParametres[0]->description,0,strpos($listeParametres[0]->description, " "));
		$urlValue =  "https://api.eedomus.com/get?action=periph.caract&periph_id=".$apiMode."&api_user=".$api_user."&api_secret=".$api_secret;
		$arrValue = json_decode(utf8_encode(file_get_contents($urlValue)));
		if(array_key_exists("body", $arrValue) && array_key_exists("last_value", $arrValue->body)) {
			$mode = $arrValue->body->last_value;
		}
	}
	if (count($listeParametres) > 1) {
		$nbzones = 1;
		$apiConsigne[1] = substr($listeParametres[3]->description,0,strpos($listeParametres[3]->description, " "));
		$apiSetConsigne[1] = substr($listeParametres[4]->description,0,strpos($listeParametres[4]->description, " "));
	}
	if (count($listeParametres) > 5) {
		$nbzones = 2;
		$apiConsigne[2] = substr($listeParametres[7]->description,0,strpos($listeParametres[7]->description, " "));
		$apiSetConsigne[2] = substr($listeParametres[8]->description,0,strpos($listeParametres[8]->description, " "));
	}
	if (count($listeParametres) > 9) {
		$nbzones = 3;
		$apiConsigne[3] = substr($listeParametres[11]->description,0,strpos($listeParametres[11]->description, " "));
		$apiSetConsigne[3] = substr($listeParametres[12]->description,0,strpos($listeParametres[12]->description, " "));
	}
	
	
	// Faire pour chaque zone	
	for ($izone = 1; $izone <= $nbzones; $izone++) {
			$consigne = 0;
			$urlValue =  "https://api.eedomus.com/get?action=periph.caract&periph_id=".$apiConsigne[$izone]."&api_user=".$api_user."&api_secret=".$api_secret;
			$arrValue = json_decode(utf8_encode(file_get_contents($urlValue)));
			if(array_key_exists("body", $arrValue) && array_key_exists("last_value", $arrValue->body)) {
				$consigne = $arrValue->body->last_value;
				$description = $arrValue->body->last_value_text;
			}
			if ($consigne < 100) {
				$setConsigne[$izone] = $consigne;
			} 
			else if ($consigne < 200) {
				$setConsigne[$izone] = $consigne - 100.00;
			}
			else if ($consigne >= 200)  {
				$valeur1 = substr($description,0,strpos($description, "-"));
				$setConsigne[$izone] = 0;
				if (is_numeric($valeur1)) {
					$setConsigne[$izone] = $valeur1;
				}
			}
			if ($setConsigne[$izone] != 0) {
				
				//$curl = curl_init();
				$url ="https://api.eedomus.com/set?action=periph.value&periph_id=".$apiSetConsigne[$izone]."&value=".$setConsigne[$izone]."&api_user=".$api_user."&api_secret=".$api_secret;
		        //curl_setopt($curl, CURLOPT_POST, 1);
				//curl_setopt($curl, CURLOPT_URL, $url);
    			//$result = curl_exec($curl);
				//curl_close($curl);
				$result = file_get_contents($url,false);
			}
	}			
?>