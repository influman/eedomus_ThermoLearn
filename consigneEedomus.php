<?php

	// Import des fonctions communes
	include ('sources/fonctions.php');

	// Initialisation de la base de donnée
	$mysqli = new mysqli($server, $sqllogin, $sqlpass, $dataBase);
	$mysqli->set_charset("utf8");
	
	
	// Recuperation des codes API des péripheriques Detecteur et Consigne
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
	}
	if (count($listeParametres) > 5) {
		$nbzones = 2;
		$apiConsigne[2] = substr($listeParametres[7]->description,0,strpos($listeParametres[7]->description, " "));
	}
	if (count($listeParametres) > 9) {
		$nbzones = 3;
		$apiConsigne[3] = substr($listeParametres[11]->description,0,strpos($listeParametres[11]->description, " "));
	}
	
	// Faire pour chaque zone	
	for ($izone = 1; $izone <= $nbzones; $izone++) {
	
		//echo "<p> Zone : ".$izone;
		$urlValues =  "https://api.eedomus.com/get?action=periph.value_list&periph_id=".$apiConsigne[$izone]."&api_user=".$api_user."&api_secret=".$api_secret;
		$arrValues= json_decode(utf8_encode(file_get_contents($urlValues)));
		if(array_key_exists("body", $arrValues) && array_key_exists("values", $arrValues->body)) {
			$listeValues = $arrValues->body->values;
		}
		$nbconsignes = 0;
		$nbconsignesauto = 0;
		$nbconsignesautofuture = 0;
		$consignes = array();
		$consignesauto = array();
		$consignesautofuture = array();
		if(isset($listeValues)) {
			//echo "<p> Tab Consignes ".$izone." : ";
			
			foreach ($listeValues as $valeurs) {
				// création du tableau des valeurs de consignes manuelles possibles
				$valeurdesc = $valeurs -> description;
				if ($valeurs -> value < 100) {
					$nbconsignes++;
					$consignes["K".$valeurs -> value] = $valeurs -> value;
					//echo $consignes[$nbconsignes].", ";
				} 
				else if ($valeurs -> value < 200) {
					$nbconsignesauto++;
					$cle = "K".($valeurs -> value - 100.00);
					$consignesauto[$cle] = $valeurs -> value;
					//echo $cle." -> ".$consignesauto[$cle].", ";
				}
				else if ($valeurs -> value >= 200)  {
					$nbconsignesautofuture++;
					$valeur1 = substr($valeurdesc,0,strpos($valeurdesc, "-"));
					$valeur2 = substr($valeurdesc,strpos($valeurdesc, "-") + 1);
					if (is_numeric($valeur1) and is_numeric($valeur2)) {
						$consignesautofuture["K".$valeur1]["K".$valeur2] = $valeurs -> value;
						//echo $valeur1." -> ".$valeur2." -> ".$consignesautofuture["K".$valeur1]["K".$valeur2].", ";
					}
				}
			}
			if ($nbconsignesauto == 0) {
				$consignesauto = $consignes;
			}
			
		}
			
			
		// Mode absence prolongée
		//*
		//*
		$longueAbsence = 0;
		//  Vérification de la durée d'absence prolongée d'une zone
		//  Si plus de 18h d'absence, mode Eco
		//  Si plus de 36h, mode Hors-Gel
		//* 
		// Recherche dernier enregistrement de présence dans smoothPresence
		// Calcul durée depuis dernière présence
		// recherche de la derniere valeur de presence
		$dateFinPrec = recuperationDateDernierImport("presence", $izone, 0, $mysqli);
		if(isset($dateFinPrec)) {
			$dateJour = date("Y-m-d H:i:s");
			if (heureEntreDate($dateFinPrec, $dateJour) > 18)  {
				$longueAbsence = 1;
				$nouvelleconsigne = $consigneEco;
				$consignefuture = $consigneEco;
				if (heureEntreDate($dateFinPrec, $dateJour) > 36)  {
					$nouvelleconsigne = $consigneHG;
					$consignefuture = $consigneHG;
				}
				//echo "Longue Absence : ".$nouvelleconsigne;
			}
		}
		
		if ($longueAbsence == 0) {
		// Mode normal : recherche de la consigne moyenne pour la phase en cours du jour de la saison
		//*
		//*
		// Recherche heure de début de phase en cours
		// Part du principe que l'heure du serveur php est calée sur celle de l'api eedomus lors de l'import/learn
			$now = time();
			$heure = date("H", $now);
			$dateDetail = recupererJourMoisSaison(date("Y-m-d H:i:s"));
			//echo "<p> Jour ".date("Y-m-d H:i:s")." ".$dateDetail["jour"]." ".$heure;
			$nouvelleconsigne = 0;
			$consignefuture = 0;
			$phasefuture = 0;
			// Restitution représentabilité de la phase de détection
			$resultat = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `etat`, `coef`, `jour` FROM `IAPresence` WHERE zone_id = ".$izone." AND jour = ".$dateDetail["jour"]." and heure_debut <= ".$heure." and heure_fin > ".$heure);
			while ($ligne = $resultat->fetch_assoc()) {
				if ($ligne["coef"] >= $representativite) {
					// phase de detection représentative, recherche de la phase future

					$heurefuture = $ligne["heure_fin"];
					$jourfutur = $ligne["jour"];
					if ($heurefuture == 24) {
						$heurefuture = 0;
						$jourfutur++;
					}
					if ($jourfutur > 6) {
						$jourfutur = 0;
					}
					// Attention, le chgt de saison n'est pas géré pour la consigne future...donc l'affichage anticipée n'est pas 100% fiable.

					$resultatPF = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `etat`, `coef`, `jour` FROM `IAPresence` WHERE zone_id = ".$izone." AND jour = ".$jourfutur." and heure_debut = ".$heurefuture);
					while ($lignePF = $resultatPF->fetch_assoc()) {
						$phasefuture = 1;
						if ($lignePF["coef"] < $representativite) {
							// phase future non representative
							$phasefuture = 0;
							$consignefuture = $consigneDefaut;

						}
					}
					
					// Restitution représentabilité de la consigne sur la phase de détection
					$resultatCS = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `consigne`, `trt`, `jour` FROM `IAConsigne` WHERE zone_id = ".$izone." AND jour = ".$dateDetail["jour"]." and heure_debut <= ".$heure." and heure_fin > ".$heure." and saison = '".$dateDetail["saison"]."'");
					while ($ligneCS = $resultatCS->fetch_assoc()) {
						if ($ligneCS["trt"] >= $representativite) {
							// consigne saison représentative
							$nouvelleconsigne = $ligneCS["consigne"];
							//echo "consigne saison representative";
						}
						else {
							// consigne saison non representative, recherche total annuel
							$resultatCT = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `consigne`, `trt`, `jour` FROM `IAConsigne` WHERE zone_id = ".$izone." AND jour = ".$dateDetail["jour"]." and heure_debut <= ".$heure." and heure_fin > ".$heure." and saison = 'Total'");
							while ($ligneCT = $resultatCT->fetch_assoc()) {
								if ($ligneCT["trt"] >= $representativite) {
									// consigne total représentative
									$nouvelleconsigne = $ligneCT["consigne"];
								}
								else {
									// consigne total non representative - apprentissage insuffisant - valeur par défaut 19°C
									$nouvelleconsigne = $consigneDefaut;
								}
							}
						}
						if ($nouvelleconsigne > 0 && $phasefuture == 1) {
							$resultatCSF = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `consigne`, `trt`, `jour` FROM `IAConsigne` WHERE zone_id = ".$izone." AND jour = ".$jourfutur." and heure_debut = ".$heurefuture." and saison = '".$dateDetail["saison"]."'");
							while ($ligneCSF = $resultatCSF->fetch_assoc()) {
								if ($ligneCSF["trt"] >= $representativite) {
									// consigne future saison représentative
									$consignefuture = $ligneCSF["consigne"];
								}
								else {
									// consigne future saison non representative, recherche total annuel
									$resultatCTF = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `consigne`, `trt`, `jour` FROM `IAConsigne` WHERE zone_id = ".$izone." AND jour = ".$jourfutur." and heure_debut = ".$heurefuture." and saison = 'Total'");
									while ($ligneCTF = $resultatCTF->fetch_assoc()) {
										if ($ligneCTF["trt"] >= $representativite) {
											// consignefuture total représentative
											$consignefuture = $ligneCTF["consigne"];
										}
										else {
											// consigne future total non representative - apprentissage insuffisant - valeur par défaut 19°C
											$consignefuture = $consigneDefaut;
										}	
									}
								}
							}
						} // fin consigne future ou consigne = 0
						else {
							$nouvelleconsigne = $consigneDefaut;
							$consignefuture = $consigneDefaut;
						}
					} // fin consigne
				} 
				else {
					// phase non représentative - apprentissage insuffisant - valeur par défaut 19°C
					$nouvelleconsigne = $consigneDefaut;
					$consignefuture = $consigneDefaut;
					
				} // fin phase representative
			} // fin lecture phase	
		} // fin mode normal
		
		// fixation de la consigne sur le périphérique eedomus
		$valeurPeriph = "";
		$nouvelleconsigneS = rtrim($nouvelleconsigne, "0"); 
		$consignefutureS = rtrim($consignefuture, "0");
		$nouvelleconsigneS = rtrim($nouvelleconsigneS, "."); 
		$consignefutureS = rtrim($consignefutureS, ".");
		//echo "<p> ".$dateDetail["jour"]." ".$nouvelleconsigneS." ".$consignefutureS;
		if (array_key_exists("K".$nouvelleconsigneS, $consignesauto)) {
	 		$valeurPeriph = $consignesauto["K".$nouvelleconsigneS];
	 		
		}
		if (array_key_exists("K".$nouvelleconsigneS, $consignesautofuture)) {
			if (array_key_exists("K".$consignefutureS, $consignesautofuture["K".$nouvelleconsigneS])) {
				$valeurPeriph = $consignesautofuture["K".$nouvelleconsigneS]["K".$consignefutureS];
			}
		}
		if ($valeurPeriph != "") {
				//$curl = curl_init();
				$url ="https://api.eedomus.com/set?action=periph.value&periph_id=".$apiConsigne[$izone]."&value=".$valeurPeriph."&api_user=".$api_user."&api_secret=".$api_secret;
		        //curl_setopt($curl, CURLOPT_POST, 1);
				//curl_setopt($curl, CURLOPT_URL, $url);
    			//$result = curl_exec($curl);
				//curl_close($curl);
				$result = file_get_contents($url,false);
		}
	}
	
	// Fermeture de l'instance de la base de donnée
	$mysqli->close();
	echo "1";
?>