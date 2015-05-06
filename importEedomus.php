<?php

	// Import des fonctions communes
	include ('sources/fonctions.php');

	// Initialisation de la base de donnée
	$mysqli = new mysqli($server, $sqllogin, $sqlpass, $dataBase);
	$mysqli->set_charset("utf8");
	
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
		}
	}
	if (count($listeParametres) > 1) {
		$nbzones = 1;
		$nomZone[1] = $listeParametres[1]->description;
		$apiDetecteur[1] = substr($listeParametres[2]->description,0,strpos($listeParametres[2]->description, " "));
		$apiConsigne[1] = substr($listeParametres[3]->description,0,strpos($listeParametres[3]->description, " "));
		updateSQL("UPDATE `zone` SET `zone` = '".$nomZone[1]."' WHERE `id` = 1", $mysqli);
	}
	if (count($listeParametres) > 5) {
		$nbzones = 2;
		$nomZone[2] = $listeParametres[5]->description;
		$apiDetecteur[2] = substr($listeParametres[6]->description,0,strpos($listeParametres[6]->description, " "));
		$apiConsigne[2] = substr($listeParametres[7]->description,0,strpos($listeParametres[7]->description, " "));
		updateSQL("UPDATE `zone` SET `zone` = '".$nomZone[2]."' WHERE `id` = 2", $mysqli);
	}
	if (count($listeParametres) > 9) {
		$nbzones = 3;
		$nomZone[3] = $listeParametres[9]->description;
		$apiDetecteur[3] = substr($listeParametres[10]->description,0,strpos($listeParametres[10]->description, " "));
		$apiConsigne[3] = substr($listeParametres[11]->description,0,strpos($listeParametres[11]->description, " "));
		updateSQL("UPDATE `zone` SET `zone` = '".$nomZone[3]."' WHERE `id` = 3", $mysqli);
	}
	
	// Faire pour chaque zone	
	$izone = 1;
	foreach($nomZone as $zone) {
		// Recuperation du dernier historique du détecteur de présence
		$dateFinDernierImport = recuperationDateDernierImport("presence", $izone, 0, $mysqli);
		$paramDate = "";
		if(isset($dateFinDernierImport)) {
			$paramDate = "&start_date=".str_replace(" ","%20",$dateFinDernierImport);
		}
	
		$arrHistorique = null;
		do {
			$urlHistorique =  "https://api.eedomus.com/get?action=periph.history&periph_id=".$apiDetecteur[$izone]."&api_user=".$api_user."&api_secret=".$api_secret.$paramDate;
			$paramDate = "";
			$arrHistorique = json_decode(utf8_encode(file_get_contents($urlHistorique)));
			if(array_key_exists("body", $arrHistorique) && array_key_exists("history", $arrHistorique->body)) {
				$listeHistoriques = $arrHistorique->body->history;
			}
		
			if(isset($listeHistoriques)) {
				$premiereOccurenceTrouve = "false";
				$dateDebut = "";
				$dateFin = "";
				$etatDetecteur = "";
									
				// Trie du tableau
			    usort($listeHistoriques, "custom_sort");
					
				// Parcours de l'historique
				foreach ($listeHistoriques as $historique) {
					if($historique[0] != "Aucun mouvement") {
						if($dateDebut == "") {
							// Initialisation de la date de début et de fin
							$dateDebut = $historique[1];
							$dateFin = "";
							$etatDetecteur = $historique[0];
						}
						else {
							$dateFin = $historique[1];
						}
					}	
					else if ($historique[0] == "Aucun mouvement") {
						$dateFin = $historique[1];
					}
					// Insertion en base puis réinitialisation des variables
					if($dateDebut != "" && $dateFin != "") {				
						// Insertion des informations dans la bonne table
						if($etatDetecteur != "") {
							insertSQL("INSERT INTO `presence` (`date_debut`,`date_fin`,`etat`,`zone_id`,`trt`) VALUES ('".$dateDebut."','".$dateFin."','".$etatDetecteur."',".$izone.",1);", $mysqli);
						}
						if($historique[0] != "Aucun mouvement") {
							$dateDebut = $dateFin;
							$dateFin = "";
							$etatDetecteur = $historique[0];
						}
						// Sinon Remise à zéro des variables
						else {
							$dateDebut = "";
							$dateFin = "";
							$etatDetecteur = "";
						}
					}
					if($paramDate == "") {
						$paramDate = "&end_date=".str_replace(" ","%20",$historique[1]);
					}
				}
			}
		}
		while(isset($arrHistorique) && isset($arrHistorique->history_overflow) && $arrHistorique->history_overflow == 10000);
	
	
		// Recuperation de l'historique des valeurs de consigne seulement s'il s'agit de consigne manuelle
		// En effet, le système n'apprend pas les valeurs qu'il a lui-même définies.
		
		//if ($mode != 99) {
		$dateFinDernierImport = recuperationDateDernierImport("consigne", $izone, 0, $mysqli);
		
		$paramDate = "";
		if(isset($dateFinDernierImport)) {
			$paramDate = "&start_date=".str_replace(" ","%20",$dateFinDernierImport);
		}
		
		$arrHistorique = null;
		do {
			$urlHistorique =  "https://api.eedomus.com/get?action=periph.history&periph_id=".$apiConsigne[$izone]."&api_user=".$api_user."&api_secret=".$api_secret.$paramDate;
			$paramDate = "";
			$arrHistorique = json_decode(utf8_encode(file_get_contents($urlHistorique)));
			if(array_key_exists("body", $arrHistorique) && array_key_exists("history", $arrHistorique->body)) {
				$listeHistoriques = $arrHistorique->body->history;
			}
			
			if(isset($listeHistoriques)) {
				$premiereOccurenceTrouve = "false";
				$dateDebut = "";
				$dateFin = "";
				$valeurConsigne = "";
				$dateDetail = null;
				
				// Trie du tableau
			    usort($listeHistoriques, "custom_sort");
					
				// Parcours du résultat
				foreach ($listeHistoriques as $historique) {
					// On prend pas le dernier import de consigne
					if($dateFinDernierImport != $historique[1]) {
						// Initialisation de la date de début et de fin
						$dateDebut = $historique[1];
						$dateFin = "";
						// valeurs manuelles
						if ((strpos($historique[0],"c") === false) and (strpos($historique[0],"C") === false)) {
							if (strpos($historique[0],"-") === false) {
								$valeurConsigne = $historique[0];
								$typevaleur = "Auto";
							}
							else {
								$valeurConsigne = substr($historique[0],0,strpos($historique[0], "-"));
								$typevaleur = "Autofuture";
							}
						}
						else {
							$valeurConsigne = substr($historique[0],0,strpos($historique[0], " "));
							$typevaleur = "Manuel";
						}
					
						$dateFin = $historique[1];
						
						// Insertion en base puis réinitialisation des variables
						if($dateDebut != "" && $dateFin != "" && is_numeric($valeurConsigne))  {				
							// Insertion des informations dans la bonne table
							if($valeurConsigne != "") {
								$dateDetail = recupererJourMoisSaison($dateFin);
								insertSQL("INSERT INTO `consigne` (`date_debut`,`date_fin`,`consigne`,`zone_id`,`trt`,`jour`,`mois`,`saison`) VALUES ('".$dateDebut."','".$dateFin."','".$valeurConsigne."',".$izone.",1,".$dateDetail["jour"].",".$dateDetail["mois"].",'".$dateDetail["saison"]."');", $mysqli);
							}
							$dateDebut = "";
							$dateFin = "";
							$valeurConsigne = "";
							$testmanuel = "";
							$dateDetail = null;
						}
					}
					if($paramDate == "") {
						$paramDate = "&end_date=".str_replace(" ","%20",$historique[1]);
					}
				}
			}
		}
		while(isset($arrHistorique) && isset($arrHistorique->history_overflow) && $arrHistorique->history_overflow == 10000);
		
		
		$izone++;
	}
	
	/**
	 * Permet de trier le flux JSON de l'eedomus par date décroissante
	 * 
	 * @param $a
	 * @param $b
	 */
    function custom_sort($a,$b) {
         return strtotime($a[1])>strtotime($b[1]);
    }
	
	// Fermeture de l'instance de la base de donnée
	$mysqli->close();
	echo "1";
?>