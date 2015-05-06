<?php

	// Import des fonctions communes
	include ('fonctions.php');

	// Initialisation de la base de donnée
	//$mysqli = new mysqli($server, $sqllogin, $sqlpass, $dataBase);
	//$mysqli->set_charset("utf8");
	
	// contrôles des paramétres
	if ($api_user != "") {
		echo "<p>API_USER : ".$api_user;
	}
	else {
		echo "<p>API_USER : KO";
	}
	if ($api_secret != "") {
		echo "<P> API_SECRET : ".$api_secret;
	}
	else {
		echo "<P> API_SECRET : KO";
	}
	
	if ($api_param != "") {
		echo "<p>API_ETAT_PARAMETRE : ".$api_param;
	}
	else {
		echo "<p>API_ETAT_PARAMETRE : KO";
	}
	echo "<p> CONSIGNE DEFAUT : ".$consigneDefaut;
	echo "<p> CONSIGNE ECO : ".$consigneEco;
	echo "<p> CONSIGNE HG : ".$consigneHG;
	
	// Recuperation des codes API des péripheriques Detecteur et Consigne
	$nbzones = 0;
	$urlParametres =  "https://api.eedomus.com/get?action=periph.value_list&periph_id=".$api_param."&api_user=".$api_user."&api_secret=".$api_secret."";
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
			echo "<p>Mode : ".$mode;
		}
		else {
			echo "<p>Mode : KO";
		}
	}
	if (count($listeParametres) > 1) {
		$nbzones = 1;
		$nomZone[1] = $listeParametres[1]->description;
		$apiDetecteur[1] = substr($listeParametres[2]->description,0,strpos($listeParametres[2]->description, " "));
		$apiConsigne[1] = substr($listeParametres[3]->description,0,strpos($listeParametres[3]->description, " "));
		echo "<p>Zone ".$nbzones." - ".$nomZone[1]." - API DETECTEUR : ".$apiDetecteur[1]." - API CONSIGNE : ".$apiConsigne[1];
	}
	if (count($listeParametres) > 5) {
		$nbzones = 2;
		$nomZone[2] = $listeParametres[5]->description;
		$apiDetecteur[2] = substr($listeParametres[6]->description,0,strpos($listeParametres[6]->description, " "));
		$apiConsigne[2] = substr($listeParametres[7]->description,0,strpos($listeParametres[7]->description, " "));
		echo "<p>Zone ".$nbzones." - ".$nomZone[2]." - API DETECTEUR : ".$apiDetecteur[2]." - API CONSIGNE : ".$apiConsigne[2];
	}
	if (count($listeParametres) > 9) {
		$nbzones = 3;
		$nomZone[3] = $listeParametres[9]->description;
		$apiDetecteur[3] = substr($listeParametres[10]->description,0,strpos($listeParametres[10]->description, " "));
		$apiConsigne[3] = substr($listeParametres[11]->description,0,strpos($listeParametres[11]->description, " "));
		echo "<p>Zone ".$nbzones." - ".$nomZone[3]." - API DETECTEUR : ".$apiDetecteur[3]." - API CONSIGNE : ".$apiConsigne[3];
	}
	
	
	// Faire pour chaque zone	
	$izone = 1;
	foreach($nomZone as $zone) {
		
		$arrHistorique = null;
		do {
			$urlHistorique =  "https://api.eedomus.com/get?action=periph.history&periph_id=".$apiDetecteur[$izone]."&api_user=".$api_user."&api_secret=".$api_secret;
			$arrHistorique = json_decode(utf8_encode(file_get_contents($urlHistorique)));
			if(array_key_exists("body", $arrHistorique) && array_key_exists("history", $arrHistorique->body)) {
				echo "<p> Historique DETECTEUR Zone ".$izone." : OK";
			}
			else {
				echo "<p> Historique DETECTEUR Zone ".$izone." : Absent";
			}
		}
		while(isset($arrHistorique) && isset($arrHistorique->history_overflow) && $arrHistorique->history_overflow == 10000);
	
		$arrHistorique = null;
		do {
			$urlHistorique =  "https://api.eedomus.com/get?action=periph.history&periph_id=".$apiConsigne[$izone]."&api_user=".$api_user."&api_secret=".$api_secret;
			$paramDate = "";
			$arrHistorique = json_decode(utf8_encode(file_get_contents($urlHistorique)));
			if(array_key_exists("body", $arrHistorique) && array_key_exists("history", $arrHistorique->body)) {
				echo "<p> Historique CONSIGNE Zone ".$izone." : OK";
			}
			else {
				echo "<p> Historique CONSIGNE Zone ".$izone." : Absente";
			}
		}
		while(isset($arrHistorique) && isset($arrHistorique->history_overflow) && $arrHistorique->history_overflow == 10000);
		$izone++;
	}
	
	
?>