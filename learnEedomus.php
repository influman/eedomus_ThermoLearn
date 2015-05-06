<?php

	// Import des fonctions communes
	include ('sources/fonctions.php');

	// Initialisation de la base de donnée
	$mysqli = new mysqli($server, $sqllogin, $sqlpass, $dataBase);
	$mysqli->set_charset("utf8");
	
	// Recuperation du nombre de zones via parametres eedomus
	$nbzones = 0;
	$izone = 0;
	$urlParametres =  "https://api.eedomus.com/get?action=periph.value_list&periph_id=".$api_param."&api_user=".$api_user."&api_secret=".$api_secret."";
	$arrParametres = json_decode(utf8_encode(file_get_contents($urlParametres)));
	$listeParametres = $arrParametres->body->values;
	if (count($listeParametres) > 1) {
		$nbzones = 1;
	}
	if (count($listeParametres) > 5) {
		$nbzones = 2;
	}
	if (count($listeParametres) > 9) {
		$nbzones = 3;
	}
	
	$listejoursem = array(0, 1, 2, 3, 4, 5, 6);
	
	// Faire pour chacune des zones
	for ($izone = 1; $izone <= $nbzones; $izone++) {
		
		//
		// Smoothing : arrondi des phases de présence/absence
		//
		
		// recherche de la derniere valeur de presence smoothed 
		$datesFinDernierSmooth = recuperationDatesDernierImport("smoothPresence", $izone, 0, $mysqli);
		$dateFinPrec = "";
		$dateDebut = "";
		$idPrec = "";
		$dateDetail = null;
		$etat = "";
	
		if(isset($datesFinDernierSmooth)) {
			$etat = $datesFinDernierSmooth["etat"];
			$dateFinPrec = $datesFinDernierSmooth["date_fin"];
			$dateDebut = $datesFinDernierSmooth["date_debut"];
			$idPrec = $datesFinDernierSmooth["id"];
			if ($etat == 'Absence') {
				updateSQL("DELETE FROM `smoothPresence` WHERE `id` = ".$idPrec, $mysqli); 
				$datesFinDernierSmooth = recuperationDatesDernierImport("smoothPresence", $izone, 0, $mysqli);
				$dateFinPrec = "";
				$dateDebut = "";
				$idPrec = "";
				$dateDetail = null;
				$etat = "";
				if(isset($datesFinDernierSmooth)) {
					$etat = $datesFinDernierSmooth["etat"];
					$dateFinPrec = $datesFinDernierSmooth["date_fin"];
					$dateDebut = $datesFinDernierSmooth["date_debut"];
					$idPrec = $datesFinDernierSmooth["id"];
				}
			}
		}
 
		// Récupération des données de presence importee apres le précedent smoothing
		$resultat = selectSQL("SELECT `id`, `date_debut`, `date_fin`, `etat`, `zone_id`, `trt` FROM `presence` WHERE zone_id = ".$izone." AND trt = 1 ORDER BY date_debut");
		$donneesImport;
		
		// Parcourt le résultat et lisse les valeurs.
		while ($ligne = $resultat->fetch_assoc()) {
			$donneesImport = $ligne; 
			// Les détections lues sont topée "trt = 2" pour ne pas être lues la prochaine fois
			updateSQL("UPDATE `presence` SET `trt` = '2' WHERE `id` = '".$donneesImport["id"]."'", $mysqli);
			
			// Arrondi des dates à l'heure fixe
			// Le début de phase est arrondi à l'heure inférieure
			// La fin de phase est arrondie à l'heure supérieure
			$donneesImport["date_debut"] = substr_replace($ligne["date_debut"],"00:00",14);
			$donneesImport["date_fin"] = substr_replace($ligne["date_fin"],"00:00",14);
			$donneesImport["date_fin"] = ajouterHeure($donneesImport["date_fin"], 1);
						
			// première lecture et aucun smooth dans l'historique
			if($dateFinPrec == "") {
				$dateDebut = $donneesImport["date_debut"];
				$dateFinPrec = $donneesImport["date_fin"];
			}
			else {
				// La phase n'est enregistrée que s'il y a au moins deux heures de non détection.
				if(heureEntreDate($dateFinPrec, $donneesImport["date_debut"]) > 1) {
					// on update la phase générée lors de la dernière exécution si elle doit être agrandie depuis
					if ($idPrec != "") {
						updateSQL("UPDATE `smoothPresence` SET `date_fin` = '".$dateFinPrec."' WHERE `id` = '".$idPrec."'", $mysqli);
						$idPrec = "";
					}
					else {
						// sinon insertion nouvelle phase de présence
						$dateDetail = recupererJourMoisSaison($dateDebut);
						insertSQL("INSERT INTO `smoothPresence` (`date_debut`,`date_fin`,`etat`,`zone_id`,`trt`,`jour`) VALUES ('".$dateDebut."','".$dateFinPrec."','Presence','".$donneesImport["zone_id"]."',1,".$dateDetail["jour"].");", $mysqli);
					}
					// insertion nouvelle phase d'absence
					$dateDetail = recupererJourMoisSaison($dateFinPrec);
					insertSQL("INSERT INTO `smoothPresence` (`date_debut`,`date_fin`,`etat`,`zone_id`,`trt`,`jour`) VALUES ('".$dateFinPrec."','".$donneesImport["date_debut"]."','Absence','".$donneesImport["zone_id"]."',1,".$dateDetail["jour"].");", $mysqli);
					$dateDebut = $donneesImport["date_debut"];
					$dateFinPrec = $donneesImport["date_fin"];
					$dateJour = 0;
					$dateMois = 0;
					$dateSaison = "";
					$dateDetail = null;
				}
				else {
					$dateFinPrec = $donneesImport["date_fin"];
				}
			}
				
		}
			
		if($dateFinPrec != "") {
			//ecriture derniere ligne lue
			$dateDetail = recupererJourMoisSaison($dateDebut);
			if ($idPrec != "") {
				updateSQL("UPDATE `smoothPresence` SET `date_fin` = '".$dateFinPrec."' WHERE `id` = '".$idPrec."'", $mysqli);
				$idPrec = "";
			}
			else {
				insertSQL("INSERT INTO `smoothPresence` (`date_debut`,`date_fin`,`etat`,`zone_id`,`trt`,`jour`) VALUES ('".$dateDebut."','".$dateFinPrec."','Presence','".$donneesImport["zone_id"]."',1,".$dateDetail["jour"].");", $mysqli);
			}
		}	
		
		// Eclatement des phases de plus de 23h - contrainte du système
		$resultat = selectSQL("SELECT `id`, `date_debut`, `date_fin`, `etat`, `zone_id`, `trt`, `jour` FROM `smoothPresence` WHERE zone_id = ".$izone." AND trt = 1 ORDER BY date_debut");
		$donneesImport;
		while ($ligne = $resultat->fetch_assoc()) {
			$eclatement = 0;
			$donneesImport = $ligne; 
			$dateDebut = $donneesImport["date_debut"];
			$dateFin = $donneesImport["date_fin"];
			if (heureEntreDate($dateDebut, $dateFin) > 23) {
				$eclatement = 1;
				updateSQL("DELETE FROM `smoothPresence` WHERE `id` = '".$donneesImport["id"]."'", $mysqli);
			}
			while (heureEntreDate($dateDebut, $dateFin) > 23) {
				$dateFin = ajouterHeure($dateDebut, 23);
				$dateDetail = recupererJourMoisSaison($dateDebut);
				insertSQL("INSERT INTO `smoothPresence` (`date_debut`,`date_fin`,`etat`,`zone_id`,`trt`,`jour`) VALUES ('".$dateDebut."','".$dateFin."','".$donneesImport["etat"]."','".$donneesImport["zone_id"]."',1,".$dateDetail["jour"].");", $mysqli);
				$dateDebut = $dateFin;
				$dateFin = $donneesImport["date_fin"];
			}
			if ($eclatement == 1) {
				$dateDetail = recupererJourMoisSaison($dateDebut);
				insertSQL("INSERT INTO `smoothPresence` (`date_debut`,`date_fin`,`etat`,`zone_id`,`trt`,`jour`) VALUES ('".$dateDebut."','".$dateFin."','".$donneesImport["etat"]."','".$donneesImport["zone_id"]."',1,".$dateDetail["jour"].");", $mysqli);
			}		
		}
		
		// Récupération des données de consigne apres le précedent smoothing
		// Il n'y a pas de phases de consigne. Seul la date de saisie de la consigne est arrondie
		$resultat = selectSQL("SELECT `id`, `date_debut`, `date_fin`, `consigne`, `zone_id`, `trt`, `jour` FROM `consigne` WHERE zone_id = ".$izone." AND trt = 1 ORDER BY date_debut");
	
		$donneesImport;
		// Parcourt le résultat et lisse les valeurs.
		while ($ligne = $resultat->fetch_assoc()) {
			$donneesImport = $ligne; 
			// Arrondi des dates à l'heure fixe
			$donneesImport["date_debut"] = substr_replace($ligne["date_debut"],"00:00",14);
			$donneesImport["date_fin"] = $donneesImport["date_debut"];
			updateSQL("UPDATE `consigne` SET `trt` = '2', `date_debut` = '".$donneesImport["date_debut"]."', `date_fin` = '".$donneesImport["date_fin"]."' WHERE `id` = '".$donneesImport["id"]."'", $mysqli);
		}	
	
		//
		// Apprentissage
		//
		
		// Tableaux des phases pour calcul moyenne des présences
		$listejoursem = array(0, 1, 2, 3, 4, 5, 6);
		$totalphase = array(1 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
				 	    	2 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							3 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							4 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							5 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							6 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							0 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0)
					 );
		$totalphasefin = array(1 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
				 	    	2 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							3 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							4 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							5 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							6 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							0 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0)
					 );
		
		$totalcoeff = array(1 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							2 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							3 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							4 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							5 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							6 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							0 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0)
			    );
		$totalconsigne = array(1 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							2 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							3 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							4 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							5 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),	
							6 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							0 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0)
			    );
		$joursem = 0;	
		
		// Recalcul des moyennes d'heures de presence par jour de la semaine
	    // Lecture des périodes de présence smoothed et non encore apprises.
		$resultat = selectSQL("SELECT `id`, `date_debut`, `date_fin`, `etat`, `zone_id`, `trt`, `jour` FROM `smoothPresence` WHERE zone_id = ".$izone." AND etat = 'Presence' AND trt = 1 ORDER BY date_debut");
		$donneesImport;
		while ($ligne = $resultat->fetch_assoc()) {
			$donneesImport = $ligne; 
			updateSQL("UPDATE `smoothPresence` SET `trt` = '2' WHERE `id` = '".$donneesImport["id"]."'", $mysqli);
			$joursem = $ligne["jour"];
			if ($joursem == 0) {
	 		$joursemprec = 6;
		 	}
			else {
				$joursemprec = $joursem - 1;
			}
			if ($joursem == 6) {
	 		$joursemsuiv = 0;
		 	}
			else {
				$joursemsuiv = $joursem + 1;
			}
			
			$heure = substr($ligne["date_debut"], 11,2);
			$heurefin = substr($ligne["date_fin"], 11,2);
			
			if ($heurefin == 0) {
				$heurefin = 24;
			}
			
			// Enregistrement des heures moyennes de début et fin, par jour de la semaine, et par phase de 3h.
			if ($heure == 21 or $heure == 22 or $heure == 23) {
				$totalphase[$joursem]["21-22-23"] = $totalphase[$joursem]["21-22-23"] + $heure;
				$totalcoeff[$joursem]["21-22-23"] = $totalcoeff[$joursem]["21-22-23"] + 1;
				if ($heure <= $heurefin) {
					$totalphasefin[$joursem]["21-22-23"] = $totalphasefin[$joursem]["21-22-23"] + $heurefin;
				} 
				else {
					$totalphasefin[$joursem]["21-22-23"] = $totalphasefin[$joursem]["21-22-23"] + 24;
					$totalphasefin[$joursemsuiv]["0-1"] = $totalphasefin[$joursemsuiv]["0-1"] + $heurefin;
					$totalcoeff[$joursemsuiv]["0-1"] = $totalcoeff[$joursemsuiv]["0-1"] + 1;
				}
			}
			else if ($heure == 0 or $heure == 1) {
				$totalphase[$joursem]["0-1"] = $totalphase[$joursem]["0-1"] + $heure;
				$totalcoeff[$joursem]["0-1"] = $totalcoeff[$joursem]["0-1"] + 1;
				if ($heure <= $heurefin) {
					$totalphasefin[$joursem]["0-1"] = $totalphasefin[$joursem]["0-1"] + $heurefin;	
				}
				else {
					$totalphasefin[$joursem]["0-1"] = $totalphasefin[$joursem]["0-1"] + 24;
					$totalphasefin[$joursemsuiv]["0-1"] = $totalphasefin[$joursemsuiv]["0-1"] + $heurefin;
					$totalcoeff[$joursemsuiv]["0-1"] = $totalcoeff[$joursemsuiv]["0-1"] + 1;
				}	
			}
			else if ($heure == 2 or $heure == 3) {
				$totalphase[$joursem]["2-3"] = $totalphase[$joursem]["2-3"] + $heure;
				$totalcoeff[$joursem]["2-3"] = $totalcoeff[$joursem]["2-3"] + 1;
				if ($heure <= $heurefin) {
					$totalphasefin[$joursem]["2-3"] = $totalphasefin[$joursem]["2-3"] + $heurefin;	
				}
				else {
					$totalphasefin[$joursem]["2-3"] = $totalphasefin[$joursem]["2-3"] + 24;
					$totalphasefin[$joursemsuiv]["0-1"] = $totalphasefin[$joursemsuiv]["0-1"] + $heurefin;
					$totalcoeff[$joursemsuiv]["0-1"] = $totalcoeff[$joursemsuiv]["0-1"] + 1;
				}	
			}
			else if ($heure == 4 or $heure == 5) {
				$totalphase[$joursem]["4-5"] = $totalphase[$joursem]["4-5"] + $heure;
				$totalcoeff[$joursem]["4-5"] = $totalcoeff[$joursem]["4-5"] + 1;
				if ($heure <= $heurefin) {
					$totalphasefin[$joursem]["4-5"] = $totalphasefin[$joursem]["4-5"] + $heurefin;	
				}
				else {
					$totalphasefin[$joursem]["4-5"] = $totalphasefin[$joursem]["4-5"] + 24;
					$totalphasefin[$joursemsuiv]["0-1"] = $totalphasefin[$joursemsuiv]["0-1"] + $heurefin;
					$totalcoeff[$joursemsuiv]["0-1"] = $totalcoeff[$joursemsuiv]["0-1"] + 1;
				}	
			}
			else if ($heure == 6 or $heure == 7 or $heure == 8) {
				$totalphase[$joursem]["6-7-8"] = $totalphase[$joursem]["6-7-8"] + $heure;
				$totalcoeff[$joursem]["6-7-8"] = $totalcoeff[$joursem]["6-7-8"] + 1;
				if ($heure <= $heurefin) {
					$totalphasefin[$joursem]["6-7-8"] = $totalphasefin[$joursem]["6-7-8"] + $heurefin;
				}
				else {
					$totalphasefin[$joursem]["6-7-8"] = $totalphasefin[$joursem]["6-7-8"] + 24;
					$totalphasefin[$joursemsuiv]["0-1"] = $totalphasefin[$joursemsuiv]["0-1"] + $heurefin;
					$totalcoeff[$joursemsuiv]["0-1"] = $totalcoeff[$joursemsuiv]["0-1"] + 1;
				}
			}
			else if ($heure == 9 or $heure == 10) {
				$totalphase[$joursem]["9-10"] = $totalphase[$joursem]["9-10"] + $heure;
				$totalcoeff[$joursem]["9-10"] = $totalcoeff[$joursem]["9-10"] + 1;
				if ($heure <= $heurefin) {
					$totalphasefin[$joursem]["9-10"] = $totalphasefin[$joursem]["9-10"] + $heurefin;
				}
				else {
					$totalphasefin[$joursem]["9-10"] = $totalphasefin[$joursem]["9-10"] + 24;
					$totalphasefin[$joursemsuiv]["0-1"] = $totalphasefin[$joursemsuiv]["0-1"] + $heurefin;
					$totalcoeff[$joursemsuiv]["0-1"] = $totalcoeff[$joursemsuiv]["0-1"] + 1;
				}
			}
			else if ($heure == 11 or $heure == 12 or $heure == 13) {
				$totalphase[$joursem]["11-12-13"] = $totalphase[$joursem]["11-12-13"] + $heure;
				$totalcoeff[$joursem]["11-12-13"] = $totalcoeff[$joursem]["11-12-13"] + 1;
				if ($heure <= $heurefin) {
					$totalphasefin[$joursem]["11-12-13"] = $totalphasefin[$joursem]["11-12-13"] + $heurefin;
				}
				else {
					$totalphasefin[$joursem]["11-12-13"] = $totalphasefin[$joursem]["11-12-13"] + 24;
					$totalphasefin[$joursemsuiv]["0-1"] = $totalphasefin[$joursemsuiv]["0-1"] + $heurefin;
					$totalcoeff[$joursemsuiv]["0-1"] = $totalcoeff[$joursemsuiv]["0-1"] + 1;
				}
			}
			else if ($heure == 14 or $heure == 15 or $heure == 16) {
				$totalphase[$joursem]["14-15-16"] = $totalphase[$joursem]["14-15-16"] + $heure;
				$totalcoeff[$joursem]["14-15-16"] = $totalcoeff[$joursem]["14-15-16"] + 1;
				if ($heure <= $heurefin) {
					$totalphasefin[$joursem]["14-15-16"] = $totalphasefin[$joursem]["14-15-16"] + $heurefin;
				}
				else {
					$totalphasefin[$joursem]["14-15-16"] = $totalphasefin[$joursem]["14-15-16"] + 24;
					$totalphasefin[$joursemsuiv]["0-1"] = $totalphasefin[$joursemsuiv]["0-1"] + $heurefin;
					$totalcoeff[$joursemsuiv]["0-1"] = $totalcoeff[$joursemsuiv]["0-1"] + 1;
				}
			}
			else if ($heure == 17 or $heure == 18 or $heure == 19 or $heure == 20) {
				$totalphase[$joursem]["17-18-19-20"] = $totalphase[$joursem]["17-18-19-20"] + $heure;
				$totalcoeff[$joursem]["17-18-19-20"] = $totalcoeff[$joursem]["17-18-19-20"] + 1;
				if ($heure <= $heurefin) {
					$totalphasefin[$joursem]["17-18-19-20"] = $totalphasefin[$joursem]["17-18-19-20"] + $heurefin;
				}
				else {
					$totalphasefin[$joursem]["17-18-19-20"] = $totalphasefin[$joursem]["17-18-19-20"] + 24;
					$totalphasefin[$joursemsuiv]["0-1"] = $totalphasefin[$joursemsuiv]["0-1"] + $heurefin;
					$totalcoeff[$joursemsuiv]["0-1"] = $totalcoeff[$joursemsuiv]["0-1"] + 1;
				}
			}
		}
		
		// Ecriture des nouvelles moyennes de phases par jour de la semaine	dans la table temporaire
		updateSQL("DELETE FROM `IAPresenceTmp` WHERE `zone_id` = ".$izone, $mysqli);
		foreach($listejoursem as $joursem) {
			if ($totalcoeff[$joursem]["21-22-23"] != 0) {
				$moyenne = round($totalphase[$joursem]["21-22-23"] / $totalcoeff[$joursem]["21-22-23"]);
				$moyennefin = round($totalphasefin[$joursem]["21-22-23"] / $totalcoeff[$joursem]["21-22-23"]);
				insertSQL("INSERT INTO `IAPresenceTmp` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$moyenne."','".$moyennefin."','Presence',".$izone.",1,1,".$joursem.");", $mysqli);
			}
			if ($totalcoeff[$joursem]["0-1"] != 0) {
				$moyenne = round($totalphase[$joursem]["0-1"] / $totalcoeff[$joursem]["0-1"]);
				$moyennefin = round($totalphasefin[$joursem]["0-1"] / $totalcoeff[$joursem]["0-1"]);
				insertSQL("INSERT INTO `IAPresenceTmp` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$moyenne."','".$moyennefin."','Presence',".$izone.",1,1,".$joursem.");", $mysqli);
			}
			if ($totalcoeff[$joursem]["2-3"] != 0) {
				$moyenne = round($totalphase[$joursem]["2-3"] / $totalcoeff[$joursem]["2-3"]);
				$moyennefin = round($totalphasefin[$joursem]["2-3"] / $totalcoeff[$joursem]["2-3"]);
				insertSQL("INSERT INTO `IAPresenceTmp` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$moyenne."','".$moyennefin."','Presence',".$izone.",1,1,".$joursem.");", $mysqli);
			}
			if ($totalcoeff[$joursem]["4-5"] != 0) {
				$moyenne = round($totalphase[$joursem]["4-5"] / $totalcoeff[$joursem]["4-5"]);
				$moyennefin = round($totalphasefin[$joursem]["4-5"] / $totalcoeff[$joursem]["4-5"]);
				insertSQL("INSERT INTO `IAPresenceTmp` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$moyenne."','".$moyennefin."','Presence',".$izone.",1,1,".$joursem.");", $mysqli);
			}
			if ($totalcoeff[$joursem]["6-7-8"] != 0) {
				$moyenne = round($totalphase[$joursem]["6-7-8"] / $totalcoeff[$joursem]["6-7-8"]);
				$moyennefin = round($totalphasefin[$joursem]["6-7-8"] / $totalcoeff[$joursem]["6-7-8"]);
				insertSQL("INSERT INTO `IAPresenceTmp` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$moyenne."','".$moyennefin."','Presence',".$izone.",1,1,".$joursem.");", $mysqli);
			}
			if ($totalcoeff[$joursem]["9-10"] != 0) {
				$moyenne = round($totalphase[$joursem]["9-10"] / $totalcoeff[$joursem]["9-10"]);
				$moyennefin = round($totalphasefin[$joursem]["9-10"] / $totalcoeff[$joursem]["9-10"]);
				insertSQL("INSERT INTO `IAPresenceTmp` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$moyenne."','".$moyennefin."','Presence',".$izone.",1,1,".$joursem.");", $mysqli);
			}
			if ($totalcoeff[$joursem]["11-12-13"] != 0) {
				$moyenne = round($totalphase[$joursem]["11-12-13"] / $totalcoeff[$joursem]["11-12-13"]);
				$moyennefin = round($totalphasefin[$joursem]["11-12-13"] / $totalcoeff[$joursem]["11-12-13"]);
				insertSQL("INSERT INTO `IAPresenceTmp` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$moyenne."','".$moyennefin."','Presence',".$izone.",1,1,".$joursem.");", $mysqli);
			}
			if ($totalcoeff[$joursem]["14-15-16"] != 0) {
				$moyenne = round($totalphase[$joursem]["14-15-16"] / $totalcoeff[$joursem]["14-15-16"]);
				$moyennefin = round($totalphasefin[$joursem]["14-15-16"] / $totalcoeff[$joursem]["14-15-16"]);
				insertSQL("INSERT INTO `IAPresenceTmp` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$moyenne."','".$moyennefin."','Presence',".$izone.",1,1,".$joursem.");", $mysqli);
			}
			if ($totalcoeff[$joursem]["17-18-19-20"] != 0) {
				$moyenne = round($totalphase[$joursem]["17-18-19-20"] / $totalcoeff[$joursem]["17-18-19-20"]);
				$moyennefin = round($totalphasefin[$joursem]["17-18-19-20"] / $totalcoeff[$joursem]["17-18-19-20"]);
				insertSQL("INSERT INTO `IAPresenceTmp` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$moyenne."','".$moyennefin."','Presence',".$izone.",1,1,".$joursem.");", $mysqli);
			}
		}
		
		// fusion des phases contigues de presence de la table temporaire
		foreach($listejoursem as $joursem) {
			$presenceDebut = 0;
			$presenceFinPrec = 0;
			$premierLecture = true;
			$idprec = 0;
			$fusionprec = 0;
			$coefprec = 0;
			$resultat = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `coef` FROM `IAPresenceTmp` WHERE zone_id = ".$izone." AND jour = ".$joursem." AND etat = 'Presence' ORDER BY heure_debut");
			$donneesImport;
			// Parcourt le résultat et lisse les valeurs.
			while ($ligne = $resultat->fetch_assoc()) {
				$donneesImport = $ligne; 
				// première lecture
				if($presenceFinPrec == 0) {
					$presenceDebut = $donneesImport["heure_debut"];
					$presenceFinPrec = $donneesImport["heure_fin"];
					$idprec = $donneesImport["id"];
					$coefprec = $donneesImport["coef"];
				}
				else {
					if ($donneesImport["heure_debut"] <= $presenceFinPrec) {
						$fusionprec = 1;
						$presenceFinPrec = $donneesImport["heure_fin"];
						updateSQL("DELETE FROM `IAPresenceTmp` WHERE `id` = ".$idprec, $mysqli);
						$idprec  = $donneesImport["id"];
						$coefprec = $donneesImport["coef"];
					}
					else {
						if ($fusionprec == 1) {
							updateSQL("UPDATE `IAPresenceTmp` SET `heure_debut` = ".$presenceDebut.", `heure_fin` = ".$presenceFinPrec." WHERE `id` = ".$idprec, $mysqli);
							$fusionprec = 0;
						}
						$presenceDebut = $donneesImport["heure_debut"];
						$presenceFinPrec = $donneesImport["heure_fin"];
						$idprec = $donneesImport["id"];
						$coefprec = $donneesImport["coef"];
					}
				}
			}
			if ($fusionprec == 1) {
				updateSQL("UPDATE `IAPresenceTmp` SET `heure_debut` = ".$presenceDebut.", `heure_fin` = ".$presenceFinPrec." WHERE `id` = ".$idprec, $mysqli);
			}
		}
		
		// Réalimentation du tableau de phase
		$totalphase = array(1 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
				 	    	2 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							3 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							4 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							5 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							6 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							0 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0)
					 );
		$totalphasefin = array(1 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
				 	    	2 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							3 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							4 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							5 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							6 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							0 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0)
					 );
		
		$totalcoeff = array(1 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							2 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							3 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							4 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							5 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							6 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0),
							0 => array("21-22-23" => 0, "0-1" => 0, "2-3" => 0, "4-5" => 0, "6-7-8" => 0, "9-10" => 0, "11-12-13" => 0, "14-15-16" => 0, "17-18-19-20" => 0)
				    );
			    	
		// recherche des précédentes moyennes calculées par phase (base existante d'apprentissage avec coefficient de représentabilité)
		$resultat = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `coef`, `jour` FROM `IAPresence` WHERE zone_id = ".$izone." AND etat = 'Presence' ORDER BY heure_debut");
		$donneesImport;
		while ($ligne = $resultat->fetch_assoc()) {
			$donneesImport = $ligne; 
			$joursem = $ligne["jour"];
			$coef = $ligne["coef"];
			$heure = substr($ligne["heure_debut"], 0, 2);
			$heurefin = substr($ligne["heure_fin"], 0, 2);
				
			if ($heure == 21 or $heure == 22 or $heure == 23) {
				$totalphase[$joursem]["21-22-23"] = $totalphase[$joursem]["21-22-23"] + $heure * $coef;
				$totalcoeff[$joursem]["21-22-23"] = $totalcoeff[$joursem]["21-22-23"] + $coef;
				$totalphasefin[$joursem]["21-22-23"] = $totalphasefin[$joursem]["21-22-23"] + $heurefin * $coef;
			}
			else if ($heure == 0 or $heure == 1) {
				$totalphase[$joursem]["0-1"] = $totalphase[$joursem]["0-1"] + $heure * $coef;
				$totalcoeff[$joursem]["0-1"] = $totalcoeff[$joursem]["0-1"] + $coef;
				$totalphasefin[$joursem]["0-1"] = $totalphasefin[$joursem]["0-1"] + $heurefin * $coef;
			}
			else if ($heure == 2 or $heure == 3) {
				$totalphase[$joursem]["2-3"] = $totalphase[$joursem]["2-3"] + $heure * $coef;
				$totalcoeff[$joursem]["2-3"] = $totalcoeff[$joursem]["2-3"] + $coef;
				$totalphasefin[$joursem]["2-3"] = $totalphasefin[$joursem]["2-3"] + $heurefin * $coef;
			}
			else if ($heure == 4 or $heure == 5) {
				$totalphase[$joursem]["4-5"] = $totalphase[$joursem]["4-5"] + $heure * $coef;
				$totalcoeff[$joursem]["4-5"] = $totalcoeff[$joursem]["4-5"] + $coef;
				$totalphasefin[$joursem]["4-5"] = $totalphasefin[$joursem]["4-5"] + $heurefin * $coef;
			}
			else if ($heure == 6 or $heure == 7 or $heure == 8) {
				$totalphase[$joursem]["6-7-8"] = $totalphase[$joursem]["6-7-8"] + $heure * $coef;
				$totalcoeff[$joursem]["6-7-8"] = $totalcoeff[$joursem]["6-7-8"] + $coef;
				$totalphasefin[$joursem]["6-7-8"] = $totalphasefin[$joursem]["6-7-8"] + $heurefin * $coef;
			}
			else if ($heure == 9 or $heure == 10) {
				$totalphase[$joursem]["9-10"] = $totalphase[$joursem]["9-10"] + $heure * $coef;
				$totalcoeff[$joursem]["9-10"] = $totalcoeff[$joursem]["9-10"] + $coef;
				$totalphasefin[$joursem]["9-10"] = $totalphasefin[$joursem]["9-10"] + $heurefin * $coef;
			}
			else if ($heure == 11 or $heure == 12 or $heure == 13) {
				$totalphase[$joursem]["11-12-13"] = $totalphase[$joursem]["11-12-13"] + $heure * $coef;
				$totalcoeff[$joursem]["11-12-13"] = $totalcoeff[$joursem]["11-12-13"] + $coef;
				$totalphasefin[$joursem]["11-12-13"] = $totalphasefin[$joursem]["11-12-13"] + $heurefin * $coef;
			}
			else if ($heure == 14 or $heure == 15 or $heure == 16) {
				$totalphase[$joursem]["14-15-16"] = $totalphase[$joursem]["14-15-16"] + $heure * $coef;
				$totalcoeff[$joursem]["14-15-16"] = $totalcoeff[$joursem]["14-15-16"] + $coef;
				$totalphasefin[$joursem]["14-15-16"] = $totalphasefin[$joursem]["14-15-16"] + $heurefin * $coef;
			}
			else if ($heure == 17 or $heure == 18 or $heure == 19 or $heure == 20) {
				$totalphase[$joursem]["17-18-19-20"] = $totalphase[$joursem]["17-18-19-20"] + $heure * $coef;
				$totalcoeff[$joursem]["17-18-19-20"] = $totalcoeff[$joursem]["17-18-19-20"] + $coef;
				$totalphasefin[$joursem]["17-18-19-20"] = $totalphasefin[$joursem]["17-18-19-20"] + $heurefin * $coef;
			}
		}
		
		// ajout des nouvelles moyennes
		$resultat = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `coef`, `jour` FROM `IAPresenceTmp` WHERE zone_id = ".$izone." AND etat = 'Presence' ORDER BY heure_debut");
		$donneesImport;
		while ($ligne = $resultat->fetch_assoc()) {
			$donneesImport = $ligne; 
			$joursem = $ligne["jour"];
			$coef = $ligne["coef"];
			
			$heure = substr($ligne["heure_debut"], 0, 2);
			$heurefin = substr($ligne["heure_fin"], 0, 2);
				
			if ($heure == 21 or $heure == 22 or $heure == 23) {
				$totalphase[$joursem]["21-22-23"] = $totalphase[$joursem]["21-22-23"] + $heure * $coef;
				$totalcoeff[$joursem]["21-22-23"] = $totalcoeff[$joursem]["21-22-23"] + $coef;
				$totalphasefin[$joursem]["21-22-23"] = $totalphasefin[$joursem]["21-22-23"] + $heurefin * $coef;
			}
			else if ($heure == 0 or $heure == 1) {
				$totalphase[$joursem]["0-1"] = $totalphase[$joursem]["0-1"] + $heure * $coef;
				$totalcoeff[$joursem]["0-1"] = $totalcoeff[$joursem]["0-1"] + $coef;
				$totalphasefin[$joursem]["0-1"] = $totalphasefin[$joursem]["0-1"] + $heurefin * $coef;
			}
			else if ($heure == 2 or $heure == 3) {
				$totalphase[$joursem]["2-3"] = $totalphase[$joursem]["2-3"] + $heure * $coef;
				$totalcoeff[$joursem]["2-3"] = $totalcoeff[$joursem]["2-3"] + $coef;
				$totalphasefin[$joursem]["2-3"] = $totalphasefin[$joursem]["2-3"] + $heurefin * $coef;
			}
			else if ($heure == 4 or $heure == 5) {
				$totalphase[$joursem]["4-5"] = $totalphase[$joursem]["4-5"] + $heure * $coef;
				$totalcoeff[$joursem]["4-5"] = $totalcoeff[$joursem]["4-5"] + $coef;
				$totalphasefin[$joursem]["4-5"] = $totalphasefin[$joursem]["4-5"] + $heurefin * $coef;
			}
			else if ($heure == 6 or $heure == 7 or $heure == 8) {
				$totalphase[$joursem]["6-7-8"] = $totalphase[$joursem]["6-7-8"] + $heure * $coef;
				$totalcoeff[$joursem]["6-7-8"] = $totalcoeff[$joursem]["6-7-8"] + $coef;
				$totalphasefin[$joursem]["6-7-8"] = $totalphasefin[$joursem]["6-7-8"] + $heurefin * $coef;
			}
			else if ($heure == 9 or $heure == 10) {
				$totalphase[$joursem]["9-10"] = $totalphase[$joursem]["9-10"] + $heure * $coef;
				$totalcoeff[$joursem]["9-10"] = $totalcoeff[$joursem]["9-10"] + $coef;
				$totalphasefin[$joursem]["9-10"] = $totalphasefin[$joursem]["9-10"] + $heurefin * $coef;
			}
			else if ($heure == 11 or $heure == 12 or $heure == 13) {
				$totalphase[$joursem]["11-12-13"] = $totalphase[$joursem]["11-12-13"] + $heure * $coef;
				$totalcoeff[$joursem]["11-12-13"] = $totalcoeff[$joursem]["11-12-13"] + $coef;
				$totalphasefin[$joursem]["11-12-13"] = $totalphasefin[$joursem]["11-12-13"] + $heurefin * $coef;
			}
			else if ($heure == 14 or $heure == 15 or $heure == 16) {
				$totalphase[$joursem]["14-15-16"] = $totalphase[$joursem]["14-15-16"] + $heure * $coef;
				$totalcoeff[$joursem]["14-15-16"] = $totalcoeff[$joursem]["14-15-16"] + $coef;
				$totalphasefin[$joursem]["14-15-16"] = $totalphasefin[$joursem]["14-15-16"] + $heurefin * $coef;
			}
			else if ($heure == 17 or $heure == 18 or $heure == 19 or $heure == 20) {
				$totalphase[$joursem]["17-18-19-20"] = $totalphase[$joursem]["17-18-19-20"] + $heure * $coef;
				$totalcoeff[$joursem]["17-18-19-20"] = $totalcoeff[$joursem]["17-18-19-20"] + $coef;
				$totalphasefin[$joursem]["17-18-19-20"] = $totalphasefin[$joursem]["17-18-19-20"] + $heurefin * $coef;
			}
		}
			
		// Recalcul et Ecriture des nouvelles moyennes de phases par jour de la semaine	
		updateSQL("DELETE FROM `IAPresence` WHERE `zone_id` = ".$izone, $mysqli);
		foreach($listejoursem as $joursem) {
			if ($totalcoeff[$joursem]["21-22-23"] != 0) {
				$moyenne = round($totalphase[$joursem]["21-22-23"] / $totalcoeff[$joursem]["21-22-23"]);
				$moyennefin = round($totalphasefin[$joursem]["21-22-23"] / $totalcoeff[$joursem]["21-22-23"]);
				$coef = $totalcoeff[$joursem]["21-22-23"];
				insertSQL("INSERT INTO `IAPresence` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$moyenne."','".$moyennefin."','Presence',".$izone.",".$coef.",1,".$joursem.");", $mysqli);
			}
			if ($totalcoeff[$joursem]["0-1"] != 0) {
				$moyenne = round($totalphase[$joursem]["0-1"] / $totalcoeff[$joursem]["0-1"]);
				$moyennefin = round($totalphasefin[$joursem]["0-1"] / $totalcoeff[$joursem]["0-1"]);
				$coef = $totalcoeff[$joursem]["0-1"];
				insertSQL("INSERT INTO `IAPresence` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$moyenne."','".$moyennefin."','Presence',".$izone.",".$coef.",1,".$joursem.");", $mysqli);
			}
			if ($totalcoeff[$joursem]["2-3"] != 0) {
				$moyenne = round($totalphase[$joursem]["2-3"] / $totalcoeff[$joursem]["2-3"]);
				$moyennefin = round($totalphasefin[$joursem]["2-3"] / $totalcoeff[$joursem]["2-3"]);
				$coef = $totalcoeff[$joursem]["2-3"];
				insertSQL("INSERT INTO `IAPresence` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$moyenne."','".$moyennefin."','Presence',".$izone.",".$coef.",1,".$joursem.");", $mysqli);
			}
			if ($totalcoeff[$joursem]["4-5"] != 0) {
				$moyenne = round($totalphase[$joursem]["4-5"] / $totalcoeff[$joursem]["4-5"]);
				$moyennefin = round($totalphasefin[$joursem]["4-5"] / $totalcoeff[$joursem]["4-5"]);
				$coef = $totalcoeff[$joursem]["4-5"];
				insertSQL("INSERT INTO `IAPresence` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$moyenne."','".$moyennefin."','Presence',".$izone.",".$coef.",1,".$joursem.");", $mysqli);
			}
			if ($totalcoeff[$joursem]["6-7-8"] != 0) {
				$moyenne = round($totalphase[$joursem]["6-7-8"] / $totalcoeff[$joursem]["6-7-8"]);
				$moyennefin = round($totalphasefin[$joursem]["6-7-8"] / $totalcoeff[$joursem]["6-7-8"]);
				$coef = $totalcoeff[$joursem]["6-7-8"];
				insertSQL("INSERT INTO `IAPresence` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$moyenne."','".$moyennefin."','Presence',".$izone.",".$coef.",1,".$joursem.");", $mysqli);
			}
			if ($totalcoeff[$joursem]["9-10"] != 0) {
				$moyenne = round($totalphase[$joursem]["9-10"] / $totalcoeff[$joursem]["9-10"]);
				$moyennefin = round($totalphasefin[$joursem]["9-10"] / $totalcoeff[$joursem]["9-10"]);
				$coef = $totalcoeff[$joursem]["9-10"];
				insertSQL("INSERT INTO `IAPresence` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$moyenne."','".$moyennefin."','Presence',".$izone.",".$coef.",1,".$joursem.");", $mysqli);
			}
			if ($totalcoeff[$joursem]["11-12-13"] != 0) {
				$moyenne = round($totalphase[$joursem]["11-12-13"] / $totalcoeff[$joursem]["11-12-13"]);
				$moyennefin = round($totalphasefin[$joursem]["11-12-13"] / $totalcoeff[$joursem]["11-12-13"]);
				$coef = $totalcoeff[$joursem]["11-12-13"];
				insertSQL("INSERT INTO `IAPresence` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$moyenne."','".$moyennefin."','Presence',".$izone.",".$coef.",1,".$joursem.");", $mysqli);
			}
			if ($totalcoeff[$joursem]["14-15-16"] != 0) {
				$moyenne = round($totalphase[$joursem]["14-15-16"] / $totalcoeff[$joursem]["14-15-16"]);
				$moyennefin = round($totalphasefin[$joursem]["14-15-16"] / $totalcoeff[$joursem]["14-15-16"]);
				$coef = $totalcoeff[$joursem]["14-15-16"];
				insertSQL("INSERT INTO `IAPresence` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$moyenne."','".$moyennefin."','Presence',".$izone.",".$coef.",1,".$joursem.");", $mysqli);
			}
			if ($totalcoeff[$joursem]["17-18-19-20"] != 0) {
				$moyenne = round($totalphase[$joursem]["17-18-19-20"] / $totalcoeff[$joursem]["17-18-19-20"]);
				$moyennefin = round($totalphasefin[$joursem]["17-18-19-20"] / $totalcoeff[$joursem]["17-18-19-20"]);
				$coef = $totalcoeff[$joursem]["17-18-19-20"];
				insertSQL("INSERT INTO `IAPresence` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$moyenne."','".$moyennefin."','Presence',".$izone.",".$coef.",1,".$joursem.");", $mysqli);
			}
		}
		
		
		// fusion des phases contigues de presence
		foreach($listejoursem as $joursem) {
			$presenceDebut = 0;
			$presenceFinPrec = 0;
			$premierLecture = true;
			$idprec = 0;
			$fusionprec = 0;
			$coefprec = 0;
			$resultat = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `coef` FROM `IAPresence` WHERE zone_id = ".$izone." AND jour = ".$joursem." AND etat = 'Presence' ORDER BY heure_debut");
			$donneesImport;
			// Parcourt le résultat et lisse les valeurs.
			while ($ligne = $resultat->fetch_assoc()) {
				$donneesImport = $ligne; 
				// première lecture
				if($presenceFinPrec == 0) {
					$presenceDebut = $donneesImport["heure_debut"];
					$presenceFinPrec = $donneesImport["heure_fin"];
					$idprec = $donneesImport["id"];
					$coefprec = $donneesImport["coef"];
				}
				else {
					if ($donneesImport["heure_debut"] <= $presenceFinPrec) {
						$fusionprec = 1;
						$presenceFinPrec = $donneesImport["heure_fin"];
						updateSQL("DELETE FROM `IAPresence` WHERE `id` = ".$idprec, $mysqli);
						$idprec  = $donneesImport["id"];
						$coefprec = $donneesImport["coef"];
					}
					else {
						if ($fusionprec == 1) {
							updateSQL("UPDATE `IAPresence` SET `heure_debut` = ".$presenceDebut.", `heure_fin` = ".$presenceFinPrec." WHERE `id` = ".$idprec, $mysqli);
							$fusionprec = 0;
						}
						$presenceDebut = $donneesImport["heure_debut"];
						$presenceFinPrec = $donneesImport["heure_fin"];
						$idprec = $donneesImport["id"];
						$coefprec = $donneesImport["coef"];
					}
				}
			}
			if ($fusionprec == 1) {
				updateSQL("UPDATE `IAPresence` SET `heure_debut` = ".$presenceDebut.", `heure_fin` = ".$presenceFinPrec." WHERE `id` = ".$idprec, $mysqli);
			}
		}
		
		// Recalcul des phases d'absence
		updateSQL("DELETE FROM `IAPresence` WHERE `etat` = 'Absence' AND zone_id = ".$izone, $mysqli);
		foreach($listejoursem as $joursem) {
			$absenceDebut = 0;
			$absenceFin = 0;
			$coef = 0;
			$premierLecture = true;
			$resultat = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `coef` FROM `IAPresence` WHERE zone_id = ".$izone." AND jour = ".$joursem." AND etat = 'Presence' ORDER BY heure_debut");
			$donneesImport;
			while ($ligne = $resultat->fetch_assoc()) {
				$donneesImport = $ligne; 
				$absenceFin = $donneesImport["heure_debut"];
				$coef = $donneesImport["coef"];
				if ($premierLecture && $donneesImport["heure_debut"] > 0) {
					$absenceDebut = 0;
					insertSQL("INSERT INTO `IAPresence` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$absenceDebut."','".$absenceFin."','Absence',".$izone.",".$coef.",1,".$joursem.");", $mysqli);
				}
				else if ($donneesImport["heure_debut"] > 0 && $absenceFin != $absenceDebut) {
					insertSQL("INSERT INTO `IAPresence` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$absenceDebut."','".$absenceFin."','Absence',".$izone.",".$coef.",1,".$joursem.");", $mysqli);
				}
				$premierLecture = false;
				$absenceDebut = $donneesImport["heure_fin"];
			}	
			if ($absenceDebut < 24) {
				if (isset($donneesImport)) {
					if ($donneesImport["heure_debut"] < $donneesImport["heure_fin"]) {
						$absenceFin = 24;
						insertSQL("INSERT INTO `IAPresence` (`heure_debut`,`heure_fin`,`etat`,`zone_id`,`coef`,`trt`,`jour`) VALUES ('".$absenceDebut."','".$absenceFin."','Absence',".$izone.",".$coef.",1,".$joursem.");", $mysqli);
					}
				}
			}
			
		}
	
		// Calcul des moyennes de consignes par phase
    	// Récupération de tout l'historique de données de consigne smooth
    	$totalConsigne = array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0);
		$totalHiver = array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0);
		$totalEte = array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0);
		$totalPrintemps = array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0);
		$totalAutomne = array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0);
		$totalConsigneCoef = array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0);
		$totalHiverCoef = array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0);
		$totalEteCoef = array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0);
		$totalPrintempsCoef = array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0);
		$totalAutomneCoef = array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0);
		$totalPrecAbsence = 0;
		$totalPrecPresence = 0;
		$totalPrecAll = 0;
		updateSQL("DELETE FROM `IAConsigne` WHERE zone_id = ".$izone, $mysqli);	
    	$resultat = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `jour`, `etat`  FROM `IAPresence` WHERE zone_id = ".$izone." ORDER BY jour, heure_debut");
		$donneesImport;
		while ($ligne = $resultat->fetch_assoc()) {
			$donneesImport = $ligne; 
			$heureFin = $donneesImport["heure_fin"];
			if ($heureFin == 0) {
				$heureFin = 24;
			}
			$heureDebut = $donneesImport["heure_debut"];
			$jour = $donneesImport["jour"];
			$etat =  $donneesImport["etat"];
			$resultat2 = selectSQL("SELECT `id`, `date_debut`, `consigne`, `saison` FROM `consigne` WHERE zone_id = ".$izone." AND trt = 2 AND jour = ".$jour." ORDER BY date_debut");
			$donneesImport2;
			
			while ($ligne2 = $resultat2->fetch_assoc()) {
				$donneesImport2 = $ligne2; 
				$heureConsigne = substr($donneesImport2["date_debut"], 11,2);
				if ($heureConsigne >= $heureDebut && $heureConsigne < $heureFin) {
					$totalConsigne[$jour] = $totalConsigne[$jour] + $donneesImport2["consigne"];
					$totalConsigneCoef[$jour] = $totalConsigneCoef[$jour] + 1;
					
					switch ($donneesImport2["saison"]) {
						case 'Hiver':
								$totalHiver[$jour] = $totalHiver[$jour] + $donneesImport2["consigne"];
								$totalHiverCoef[$jour] = $totalHiverCoef[$jour] + 1;
								break;
						case 'Ete':
								$totalEte[$jour] = $totalEte[$jour] + $donneesImport2["consigne"];
								$totalEteCoef[$jour] = $totalEteCoef[$jour] + 1;
								break;
						case 'Printemps':
								$totalPrintemps[$jour] = $totalPrintemps[$jour] + $donneesImport2["consigne"];
								$totalPrintempsCoef[$jour] = $totalPrintempsCoef[$jour] + 1;
								break;
						case 'Automne':
								$totalAutomne[$jour] = $totalAutomne[$jour] + $donneesImport2["consigne"];
								$totalAutomneCoef[$jour] = $totalAutomneCoef[$jour] + 1;
								break;
					}
				}
			}
			// arrondir à 0,5 près
			if ($totalConsigneCoef[$jour] != 0) {
				if (($totalConsigne[$jour] / $totalConsigneCoef[$jour]) < 16) {
					$totalConsigne[$jour] = round($totalConsigne[$jour] / $totalConsigneCoef[$jour]);
				}
				else {	
					$totalConsigne[$jour] = round(($totalConsigne[$jour] / $totalConsigneCoef[$jour]) * 2) / 2;
				}
				if ($etat == 'Presence') {
					$totalPrecPresence = $totalConsigne[$jour];
					//echo "<p>Jour ".$jour." Heure ".$heureDebut."-".$heureFin." Presence ".$totalPrecPresence;
				}
				else {
					$totalPrecAbsence = $totalConsigne[$jour];
				}
				$totalPrecAll = $totalConsigne[$jour];
			}
			else {
				$totalConsigneCoef[$jour] = 1;
				if ($etat == 'Presence') {
					$totalConsigne[$jour] = $totalPrecPresence;
				}
				else {
					$totalConsigne[$jour] = $totalPrecAbsence;
				}
				if ($heureDebut >= 0 && $heureDebut < 6) {
					$totalConsigne[$jour] = $totalPrecAll;
				}
			}
			if ($totalHiverCoef[$jour] != 0) {
				if (($totalHiver[$jour] / $totalHiverCoef[$jour]) < 16) {
					$totalHiver[$jour] = round($totalHiver[$jour] / $totalHiverCoef[$jour]);
				}
				else {
					$totalHiver[$jour] = round(($totalHiver[$jour] / $totalHiverCoef[$jour]) * 2) / 2;
				}
			}
			if ($totalEteCoef[$jour] != 0) {
				if (($totalEte[$jour] / $totalEteCoef[$jour]) < 16) {
					$totalEte[$jour] = round($totalEte[$jour] / $totalEteCoef[$jour]);
				}
				else {
					$totalEte[$jour] = round(($totalEte[$jour] / $totalEteCoef[$jour]) * 2) / 2;
				}
			}
			if ($totalPrintempsCoef[$jour] != 0) {
				if (($totalPrintemps[$jour] / $totalPrintempsCoef[$jour]) < 16) {
					$totalPrintemps[$jour] = round($totalPrintemps[$jour] / $totalPrintempsCoef[$jour]);
				}
				else {
					$totalPrintemps[$jour] = round(($totalPrintemps[$jour] / $totalPrintempsCoef[$jour]) * 2) / 2;
				}
			}
			if ($totalAutomneCoef[$jour] != 0) {
				if (($totalAutomne[$jour] / $totalAutomneCoef[$jour]) < 16) {
					$totalAutomne[$jour] = round($totalAutomne[$jour] / $totalAutomneCoef[$jour]);
				}
				else {
					$totalAutomne[$jour] = round(($totalAutomne[$jour] / $totalAutomneCoef[$jour]) * 2) / 2;
				}
			}
			insertSQL("INSERT INTO `IAConsigne` (`heure_debut`,`heure_fin`,`consigne`,`zone_id`,`trt`,`jour`,`saison`) VALUES ('".$heureDebut."','".$heureFin."',".$totalConsigne[$jour].",".$izone.",".$totalConsigneCoef[$jour].",".$jour.",'Total');", $mysqli);
			insertSQL("INSERT INTO `IAConsigne` (`heure_debut`,`heure_fin`,`consigne`,`zone_id`,`trt`,`jour`,`saison`) VALUES ('".$heureDebut."','".$heureFin."',".$totalHiver[$jour].",".$izone.",".$totalHiverCoef[$jour].",".$jour.",'Hiver');", $mysqli);
			insertSQL("INSERT INTO `IAConsigne` (`heure_debut`,`heure_fin`,`consigne`,`zone_id`,`trt`,`jour`,`saison`) VALUES ('".$heureDebut."','".$heureFin."',".$totalEte[$jour].",".$izone.",".$totalEteCoef[$jour].",".$jour.",'Ete');", $mysqli);
			insertSQL("INSERT INTO `IAConsigne` (`heure_debut`,`heure_fin`,`consigne`,`zone_id`,`trt`,`jour`,`saison`) VALUES ('".$heureDebut."','".$heureFin."',".$totalPrintemps[$jour].",".$izone.",".$totalPrintempsCoef[$jour].",".$jour.",'Printemps');", $mysqli);
			insertSQL("INSERT INTO `IAConsigne` (`heure_debut`,`heure_fin`,`consigne`,`zone_id`,`trt`,`jour`,`saison`) VALUES ('".$heureDebut."','".$heureFin."',".$totalAutomne[$jour].",".$izone.",".$totalAutomneCoef[$jour].",".$jour.",'Automne');", $mysqli);
			$totalConsigne[$jour] = 0;
			$totalConsigneCoef[$jour] = 0;
			$totalHiver[$jour] = 0;
			$totalHiverCoef[$jour] = 0;
			$totalEte[$jour] = 0;
			$totalEteCoef[$jour] = 0;
			$totalPrintemps[$jour] = 0;
			$totalPrintempsCoef[$jour] = 0;
			$totalAutomne[$jour] = 0;
			$totalAutomneCoef[$jour] = 0;
		}
	}	
		
        
	// Fermeture de l'instance de la base de donnée
	$mysqli->close();
	echo "1";
?>