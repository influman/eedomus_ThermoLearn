<?php
	// ***************************************************************
	// *************************** THERMOLEARN V1 ********************
	// ****************************** InfluMan ***********************
	// ******************************  10/2017 ***********************

	date_default_timezone_set('Europe/Paris');	
	ini_set('memory_limit', '512M');
	
	// DEBUG : permet d'afficher les erreurs
	ini_set('display_errors', 1); 
	error_reporting(E_ALL); 
	
	//*************************************** ChangeLog *********************************
	// V0.00 : alpha fonctionnel
	// V0.01 : critère de représentativité en paramètre, 1 par défaut
	// V0.02 : correction import eedomus
	// V0.03 : suppression appel CURL, correction mineure 
	// V0.04 : optimisation phases de nuit
	// V1.00 : optimisation avec store eedomus
	//*************************************** API eedomus *********************************
	// Identifiants de l'API eeDomus
	$api_user = "XXXXXX"; //ici saisir api user
	$api_secret = "aaaaaaaaaaa";  //ici saisir api secret
	
	//*************************************** Parametres bdd **************************
	//server MySQL
	$sqlserver='localhost';
	//MySQL login
	$sqllogin='root'; //ici saisir le user sql
	//MySQL password
	$sqlpass='password'; //ici saisir le pass du user
	//MySQL dataBase
	$dataBase='thermoLearnv1';
	
	//************************** Consigne par défaut en cas d'apprentissage insuffisant ****
	$consigneDefaut = '19.00';
	$consigneEco = '16.00';
	$consigneHG = '9.00';
	
	//**** Nb de phases/consignes identiques nécessaire pour être restitué en mode intelligent ****
	$representativite = 1;

	// Initialisation de la base de donnée
	$mysqli = new mysqli($sqlserver, $sqllogin, $sqlpass, $dataBase);
	$mysqli->set_charset("utf8");
	
	// Lire consigne auto : http://[VAR1]/thermolearn.php?action=setpoint&type=actualize&zone=[VAR2]&param=[VAR3]&consigne_id=123456
	// /THERMOLEARN/SETPOINT[VAR2]
	// Fixer consigne manuelle : ?action=setpoint&type=set&zone=[VAR2]&param=[VAR3]&value=[VALUE]&consigne_id=123456
	
	// Lire mode : http://[VAR1]/thermolearn.php?action=setmode&type=actualize&zone=[VAR2]&param=[VAR3]
	// /THERMOLEARN/MODE[VAR2]
	// Fixer le mode : ?action=setmode&type=set&value=[VALUE]&zone=[VAR2]&param=[VAR3]


	$erreur_msg = "";    
     
    $xml ="<THERMOLEARN>";
	 
	
	// Lecture des arguments
    $action = "void";
    if (isset($_GET['action'])) {
       $action = $_GET['action']; 
       if ($action != "setmode" && $action != "setpoint" && $action != "void" && $action != "control" && $action != "purgeHisto" && $action != "resetLearn" && $action != "heatTime") {
             $action = "void";
             $erreur_msg .= "Parametre action invalide : setmode ou setpoint ou void.";
       } 
    }
 
   	$type = "actualize";
    if (isset($_GET['type'])) {
       	$type = $_GET['type']; 
       	if ($type != "actualize" && $type != "set") {
           	  	$erreur_msg .= "Parametre type invalide : actualize ou set.";
           	   	$action = "void";
       	}
    }
 
	$zone = 1;
    if (isset($_GET['zone'])) {
       	$zone = $_GET['zone']; 
       	if ($zone > 8 || $zone <= 0) {
       	      	$erreur_msg .= "Parametre zone invalide : 1 à 8.";
              	$action = "void";
       	}
    }
 
 	$value = 0;
    if (isset($_GET['value'])) {
        $value = $_GET['value']; 
    }
 
 	$param = array();
    if (isset($_GET['param'])) {
       	list($api_detect, $api_consigne) = explode(",", $_GET['param']); 
       	if ($api_detect == "" || $api_consigne == "") {
           	$erreur_msg .= "Parametre param invalide : code_api détecteur,code_api consigne";
           	$action = "void";
       	}
    }
 
	$api_thermoconsigne = 0;
    if (isset($_GET['consigne_id'])) {
        $api_thermoconsigne_arg = $_GET['consigne_id']; 
    }
     
    //******************************************** ACTIONS DU SCRIPT ************************************
    // void : on ne fait rien, restitution des éventuels messages d'erreur dans le XML
    if ($action == "void") {
        $xml .= "<ERROR>".$erreur_msg."</ERROR>";
    }
     
	$datejour = date("Y-m-d H:i:s");
	
	// restitution donnée de mode actuel
	$mode = false;
	
	if  ($action == "setmode" || $action == "setpoint") {
    $resultat = selectSQL("SELECT `actual_mode`, `date_actual_mode`, `prec_mode`, `date_prec_mode`, `last_export`, `last_learn`, `api_consigne`  FROM `mode` WHERE zone_id = ".$zone, $mysqli);
    while ($ligne = $resultat->fetch_assoc()) {
    	$mode = true;
        $donneesMode = $ligne; 
        $actual_mode = $donneesMode["actual_mode"];
        $date_actual_mode = $donneesMode["date_actual_mode"];
        $prec_mode = $donneesMode["prec_mode"];
        $date_prec_mode = $donneesMode["date_prec_mode"];   
        $last_export = $donneesMode["last_export"];
        $last_learn = $donneesMode["last_learn"];
		$api_thermoconsigne = $donneesMode["api_consigne"];
    }
	}
	//
         
         
	// action setmode
    // type actualize : donne le mode de gestion, le remet à intelligent si manuel expiré, lance l'export et l'apprentissage
    // type set : fixe le nouveau mode de gestion
    if ($action == "setmode") {
       	if ($type == "set") {
			$prec_mode = $actual_mode;
			$date_prec_mode = $date_actual_mode;
			$actual_mode = $value;
			$date_actual_mode = $datejour;
			if ($mode) {
				updateSQL("UPDATE `mode` SET `actual_mode` = '".$actual_mode."', `prec_mode` = '".$prec_mode."', `date_actual_mode` = '".$date_actual_mode."', `date_prec_mode` = '".$date_prec_mode."' WHERE `zone_id` = '".$zone."'", $mysqli);
			} else {
				$last_learn = $datejour;
				$last_export = $datejour;
				insertSQL("INSERT INTO `mode` (`zone_id`,`actual_mode`,`date_actual_mode`,`prec_mode`,`date_prec_mode`,`last_learn`,`last_export`,`api_consigne`) VALUES (".$zone.",'".$actual_mode."','".$date_actual_mode."','".$prec_mode."','".$date_prec_mode."','".$last_learn."','".$last_export."', ".$api_thermoconsigne.");", $mysqli);
			}
			$xml .= "<MODE".$zone.">".$actual_mode."</MODE".$zone.">";
		}
		if ($type == "actualize") {
			// polling du mode, gestion de l'expiration du manuel 3h, création en table si inexistant, vois si import et learn à faire
			$recalcul = false;
			if ($mode) {
				$prec_mode = $actual_mode;
				$date_prec_mode = $date_actual_mode;
				// voir si mode manuel-3h expiré, si oui, repassage en mode intelligent
				if ($actual_mode == 9 && heureEntreDate($date_actual_mode, $datejour) >= 3)  {
					$actual_mode = 99;
					$recalcul = true;
				}
				$date_actual_mode = $datejour;
				updateSQL("UPDATE `mode` SET `actual_mode` = '".$actual_mode."', `prec_mode` = '".$prec_mode."', `date_actual_mode` = '".$date_actual_mode."', `date_prec_mode` = '".$date_prec_mode."' WHERE `zone_id` = ".$zone, $mysqli);
				$xml .= "<MODE".$zone.">".$actual_mode."</MODE".$zone.">";
			} else {
				// si mode non trouvé, alors il est fixé à "0 - Manuel" la première fois
		        $actual_mode = 0;
				$date_actual_mode = $datejour;
				$prec_mode = "";
				$date_prec_mode = "";
				$last_learn = $datejour;
				$last_export = $datejour;
				insertSQL("INSERT INTO `mode` (`zone_id`,`actual_mode`,`date_actual_mode`,`prec_mode`,`date_prec_mode`,`last_learn`,`last_export`,`api_consigne`) VALUES (".$zone.",'".$actual_mode."','".$date_actual_mode."','".$prec_mode."','".$date_prec_mode."','".$last_learn."','".$last_export."',".$api_thermoconsigne.");", $mysqli);
				$xml .= "<MODE".$zone.">".$actual_mode."</MODE".$zone.">";
			}
			if ($mode && $api_thermoconsigne > 0) {
				if (heureEntreDate($last_export, $datejour) >= 8 || $recalcul) {
					importEedomus($zone, $api_detect, $api_thermoconsigne);	
					$last_export = $datejour;
					updateSQL("UPDATE `mode` SET `last_export` = '".$last_export."' WHERE `zone_id` = ".$zone, $mysqli);
				}
				if (heureEntreDate($last_learn, $datejour) >= 12 || $recalcul) {
					learnZone($zone);	
					$last_learn = $datejour;
					updateSQL("UPDATE `mode` SET `last_learn` = '".$last_learn."' WHERE `zone_id` = ".$zone, $mysqli);
				}
				
			}
		}
	}
			
	
	// SETPOINT / ACTUALIZE ==> <THERMOLEARN><SETPOINT1>consigne</SETPOINT1></THERMOLEARN>
	
	if ($action == "setpoint") {
        if ($type == "set") {	
			// mode intelligent et fixation manuelle de la consigne
			if ($mode && ($actual_mode == 99 || $actual_mode == 9)) {
				// SETPOINT / SET ==> passage en mode manuel 3h si mode était intelligent
				$prec_mode = $actual_mode;
				$date_prec_mode = $date_actual_mode;
				$actual_mode = 9;
				$date_actual_mode = $datejour;
				updateSQL("UPDATE `mode` SET `actual_mode` = '".$actual_mode."', `prec_mode` = '".$prec_mode."', `date_actual_mode` = '".$date_actual_mode."', `date_prec_mode` = '".$date_prec_mode."' WHERE `zone_id` = ".$zone, $mysqli);
			}
			synchroConsigne($zone, $value, $api_consigne);
			$xml .= "<SETPOINT".$zone.">".$value."</SETPOINT".$zone.">";
		}
		if ($type == "actualize") {
			if ($mode && $actual_mode == 99) {
				// mode intelligent, la consigne est fixée par le système
				if ($api_thermoconsigne == 0) {
					$thermoconsigne = thermoConsigne($zone, $api_thermoconsigne_arg);
				} else {
					$thermoconsigne = thermoConsigne($zone, $api_thermoconsigne);
				}
				$xml .= "<SETPOINT".$zone.">".$thermoconsigne."</SETPOINT".$zone.">";	
				synchroConsigne($zone, $thermoconsigne, $api_consigne);
			}
		}
		if ($api_thermoconsigne == 0 || $api_thermoconsigne != $api_thermoconsigne_arg) {
			// le code api de la consigne thermolearn n'a jamais été enregistré dans la table mode
			// ce code est censé être en argument de l'appel à ce script en action "setpoint"
			if ($mode) {
				updateSQL("UPDATE `mode` SET `api_consigne` = ".$api_thermoconsigne_arg." WHERE `zone_id` = '".$zone."'", $mysqli);
			} else {
				$actual_mode = 0;
				$date_actual_mode = $datejour;
				$prec_mode = "";
				$date_prec_mode = "";
				$last_learn = $datejour;
				$last_export = $datejour;
				insertSQL("INSERT INTO `mode` (`zone_id`,`actual_mode`,`date_actual_mode`,`prec_mode`,`date_prec_mode`,`last_learn`,`last_export`,`api_consigne`) VALUES (".$zone.",'".$actual_mode."','".$date_actual_mode."','".$prec_mode."','".$date_prec_mode."','".$last_learn."','".$last_export."', '".$api_thermoconsigne_arg."');", $mysqli);
			}
		}
	}
	
	if ($action == "purgeHisto") {
		list($annee,$mois,$jour,$h,$m,$s)=sscanf($datejour,"%d-%d-%d %d:%d:%d");
		$annee-= 1;
		$timestamp=mktime($h,$m,$s,$mois,$jour,$annee);
		$datepurge = date('Y-m-d H:i:s',$timestamp);
		echo "<p> ".$datepurge;
		updateSQL("DELETE FROM `consigne` WHERE date_debut < '".$datepurge."'", $mysqli);
		updateSQL("DELETE FROM `presence` WHERE date_debut < '".$datepurge."'", $mysqli);
		updateSQL("DELETE FROM `smoothPresence` WHERE date_debut < '".$datepurge."'", $mysqli);
	}
	
	if ($action == "resetLearn") {
		// Destruction des apprentissages réalisés
		updateSQL("DELETE FROM `IAConsigne`", $mysqli);
		updateSQL("DELETE FROM `IAPresence`", $mysqli);
		updateSQL("DELETE FROM `IAPresenceTmp`", $mysqli);
		updateSQL("DELETE FROM `smoothPresence`", $mysqli);
	}
	
	if ($action == "control") {
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
	
		if ($zone != "") {
			echo "<p>ZONE EN PARAMETRE : ".$zone;
		}
		else {
			echo "<p>ZONE EN PARAMETRE : KO";
		}
		if ($param != "") {
			echo "<p>API DETECTEUR EN PARAMETRE : ".$api_detect;
			echo "<p>API CHAUFFAGE EN PARAMETRE : ".$api_consigne;
		}
		else {
			echo "<p>API_EN PARAMETRE : KO";
		}
		echo "<p> CONSIGNE DEFAUT : ".$consigneDefaut;
		echo "<p> CONSIGNE ECO : ".$consigneEco;
		echo "<p> CONSIGNE HG : ".$consigneHG;
		$arrHistorique = null;
		do {
			$urlHistorique =  "https://api.eedomus.com/get?action=periph.history&periph_id=".$api_detect."&api_user=".$api_user."&api_secret=".$api_secret;
			$arrHistorique = json_decode(utf8_encode(file_get_contents($urlHistorique)));
			if(array_key_exists("body", $arrHistorique) && array_key_exists("history", $arrHistorique->body)) {
				echo "<p> Historique DETECTEUR Zone ".$zone." : OK";
			}
			else {
				echo "<p> Historique DETECTEUR Zone ".$zone." : Absent";
			}
		}
		while(isset($arrHistorique) && isset($arrHistorique->history_overflow) && $arrHistorique->history_overflow == 10000);
	
		$arrHistorique = null;
		do {
			$urlHistorique =  "https://api.eedomus.com/get?action=periph.history&periph_id=".$api_consigne."&api_user=".$api_user."&api_secret=".$api_secret;
			$paramDate = "";
			$arrHistorique = json_decode(utf8_encode(file_get_contents($urlHistorique)));
			if(array_key_exists("body", $arrHistorique) && array_key_exists("history", $arrHistorique->body)) {
				echo "<p> Historique CONSIGNE Zone ".$zone." : OK";
			}
			else {
				echo "<p> Historique CONSIGNE Zone ".$zone." : Absente";
			}
		}
		while(isset($arrHistorique) && isset($arrHistorique->history_overflow) && $arrHistorique->history_overflow == 10000);
	}
	
	
	$mysqli->close();
	$xml .= "</THERMOLEARN>";
	//header('text/xml');
	echo $xml;
 	//return $xml;	

	// *******************************************************************
	// Détermination de la thermoconsigne
	// *******************************************************************
	function thermoConsigne($izone, $apithermoconsigne) {
		global $mysqli;
		global $api_user;
		global $api_secret;
		global $representativite;
		global $consigneDefaut;
		global $consigneEco;
		global $consigneHG;

		//lui faut la liste des valeurs de la thermoconsigne

		$urlValues =  "https://api.eedomus.com/get?action=periph.value_list&periph_id=".$apithermoconsigne."&api_user=".$api_user."&api_secret=".$api_secret;
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
			$nouvelleconsigne = 0;
			$consignefuture = 0;
			$phasefuture = 0;
			// Restitution représentabilité de la phase de détection
			$resultat = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `etat`, `coef`, `jour` FROM `IAPresence` WHERE zone_id = ".$izone." AND jour = ".$dateDetail["jour"]." and heure_debut <= ".$heure." and heure_fin > ".$heure, $mysqli);
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

					$resultatPF = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `etat`, `coef`, `jour` FROM `IAPresence` WHERE zone_id = ".$izone." AND jour = ".$jourfutur." and heure_debut = ".$heurefuture, $mysqli);
					while ($lignePF = $resultatPF->fetch_assoc()) {
						$phasefuture = 1;
						if ($lignePF["coef"] < $representativite) {
							// phase future non representative
							$phasefuture = 0;
							$consignefuture = $consigneDefaut;

						}
					}
					
					// Restitution représentabilité de la consigne sur la phase de détection
					$resultatCS = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `consigne`, `trt`, `jour` FROM `IAConsigne` WHERE zone_id = ".$izone." AND jour = ".$dateDetail["jour"]." and heure_debut <= ".$heure." and heure_fin > ".$heure." and saison = '".$dateDetail["saison"]."'", $mysqli);
					while ($ligneCS = $resultatCS->fetch_assoc()) {
						if ($ligneCS["trt"] >= $representativite) {
							// consigne saison représentative
							$nouvelleconsigne = $ligneCS["consigne"];
							//echo "consigne saison representative";
						}
						else {
							// consigne saison non representative, recherche total annuel
							$resultatCT = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `consigne`, `trt`, `jour` FROM `IAConsigne` WHERE zone_id = ".$izone." AND jour = ".$dateDetail["jour"]." and heure_debut <= ".$heure." and heure_fin > ".$heure." and saison = 'Total'", $mysqli);
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
							$resultatCSF = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `consigne`, `trt`, `jour` FROM `IAConsigne` WHERE zone_id = ".$izone." AND jour = ".$jourfutur." and heure_debut = ".$heurefuture." and saison = '".$dateDetail["saison"]."'", $mysqli);
							while ($ligneCSF = $resultatCSF->fetch_assoc()) {
								if ($ligneCSF["trt"] >= $representativite) {
									// consigne future saison représentative
									$consignefuture = $ligneCSF["consigne"];
								}
								else {
									// consigne future saison non representative, recherche total annuel
									$resultatCTF = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `consigne`, `trt`, `jour` FROM `IAConsigne` WHERE zone_id = ".$izone." AND jour = ".$jourfutur." and heure_debut = ".$heurefuture." and saison = 'Total'", $mysqli);
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
		if (array_key_exists("K".$nouvelleconsigneS, $consignesauto)) {
	 		$valeurPeriph = $consignesauto["K".$nouvelleconsigneS];
	 		
		}
		if (array_key_exists("K".$nouvelleconsigneS, $consignesautofuture)) {
			if (array_key_exists("K".$consignefutureS, $consignesautofuture["K".$nouvelleconsigneS])) {
				$valeurPeriph = $consignesautofuture["K".$nouvelleconsigneS]["K".$consignefutureS];
			}
		}
		return $valeurPeriph;
		
	}



	// *******************************************************************
	// synchronise la consigne de la zone de chauffage à la thermoconsigne
	// *******************************************************************
	function synchroConsigne($izone, $consigne, $api_consigne) {
		global $mysqli;
		global $api_user;
		global $api_secret;

			if ($consigne < 100) {
				$setConsigne = $consigne;
			} 
			else if ($consigne < 200) {
				$setConsigne = $consigne - 100.00;
			}
			else if ($consigne >= 200)  {
				$valeur1 = substr($description,0,strpos($description, "-"));
				$setConsigne = 0;
				if (is_numeric($valeur1)) {
					$setConsigne = $valeur1;
				}
			}
			if ($setConsigne != 0) {
			
				$url ="https://api.eedomus.com/set?action=periph.value&periph_id=".$api_consigne."&value=".$setConsigne."&api_user=".$api_user."&api_secret=".$api_secret;
				$result = file_get_contents($url,false);
				//echo "<BR>".$url;
			}
	}

	// ***************************************************************
	// Import des données Eedomus
	// ***************************************************************
	function importEedomus($izone, $api_detect, $api_thermoconsigne) {

		global $mysqli;
		global $api_user;
		global $api_secret;
				

		// Recuperation du dernier historique du détecteur de présence
		$dateFinDernierImport = recuperationDateDernierImport("presence", $izone, 0, $mysqli);
		$paramDate = "";
		if(isset($dateFinDernierImport)) {
			$paramDate = "&start_date=".str_replace(" ","%20",$dateFinDernierImport);
		}
	
		$arrHistorique = null;
		do {
			$urlHistorique =  "https://api.eedomus.com/get?action=periph.history&periph_id=".$api_detect."&api_user=".$api_user."&api_secret=".$api_secret.$paramDate;
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
	
	
		$dateFinDernierImport = recuperationDateDernierImport("consigne", $izone, 0, $mysqli);
		$paramDate = "";
		if(isset($dateFinDernierImport)) {
			$paramDate = "&start_date=".str_replace(" ","%20",$dateFinDernierImport);
		}
		
		$arrHistorique = null;
		do {
			$urlHistorique =  "https://api.eedomus.com/get?action=periph.history&periph_id=".$api_thermoconsigne."&api_user=".$api_user."&api_secret=".$api_secret.$paramDate;
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

	// ***************************************************************
	// Fonction d'apprentissage de la zone
	// ***************************************************************
	
	function learnZone($izone) {
		$listejoursem = array(0, 1, 2, 3, 4, 5, 6);
		
		global $mysqli;

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
		$resultat = selectSQL("SELECT `id`, `date_debut`, `date_fin`, `etat`, `zone_id`, `trt` FROM `presence` WHERE zone_id = ".$izone." AND trt = 1 ORDER BY date_debut", $mysqli);
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
		$resultat = selectSQL("SELECT `id`, `date_debut`, `date_fin`, `etat`, `zone_id`, `trt`, `jour` FROM `smoothPresence` WHERE zone_id = ".$izone." AND trt = 1 ORDER BY date_debut", $mysqli);
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
		$resultat = selectSQL("SELECT `id`, `date_debut`, `date_fin`, `consigne`, `zone_id`, `trt`, `jour` FROM `consigne` WHERE zone_id = ".$izone." AND trt = 1 ORDER BY date_debut", $mysqli);
	
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
		$resultat = selectSQL("SELECT `id`, `date_debut`, `date_fin`, `etat`, `zone_id`, `trt`, `jour` FROM `smoothPresence` WHERE zone_id = ".$izone." AND etat = 'Presence' AND trt = 1 ORDER BY date_debut", $mysqli);
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
			$resultat = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `coef` FROM `IAPresenceTmp` WHERE zone_id = ".$izone." AND jour = ".$joursem." AND etat = 'Presence' ORDER BY heure_debut", $mysqli);
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
		$resultat = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `coef`, `jour` FROM `IAPresence` WHERE zone_id = ".$izone." AND etat = 'Presence' ORDER BY heure_debut", $mysqli);
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
		$resultat = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `coef`, `jour` FROM `IAPresenceTmp` WHERE zone_id = ".$izone." AND etat = 'Presence' ORDER BY heure_debut", $mysqli);
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
			$resultat = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `coef` FROM `IAPresence` WHERE zone_id = ".$izone." AND jour = ".$joursem." AND etat = 'Presence' ORDER BY heure_debut", $mysqli);
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
			$resultat = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `coef` FROM `IAPresence` WHERE zone_id = ".$izone." AND jour = ".$joursem." AND etat = 'Presence' ORDER BY heure_debut", $mysqli);
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
    		$resultat = selectSQL("SELECT `id`, `heure_debut`, `heure_fin`, `jour`, `etat`  FROM `IAPresence` WHERE zone_id = ".$izone." ORDER BY jour, heure_debut", $mysqli);
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
			$resultat2 = selectSQL("SELECT `id`, `date_debut`, `consigne`, `saison` FROM `consigne` WHERE zone_id = ".$izone." AND trt = 2 AND jour = ".$jour." ORDER BY date_debut", $mysqli);
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
		
    
    
    /**
	 * 
	 * Réalise en select en base de donnée et retourne le résultat
	 * 
	*/
	function selectSQL($requete, $mysqli) {
		global $sqlserver;
		global $sqllogin;
		global $sqlpass;
		global $dataBase;

		// Récupération des informations en base de donnée
		$resultat = $mysqli->query ($requete) or die ('Erreur SQL avec la requete : '.$requete.'<br />'.$mysqli->error.'<br />DATA :'.$sqlserver.' '.$sqllogin.' '.$dateBase.'<br />');
	
				
		// Retour du résultat brut
		return $resultat;
	}
	
	/**
	 * 
	 * Récupère la date de début min et la date de fin max en base de donnée
	 * 
	*/ 
	function recupererDateDebutEtDateFin($mysqli) {
		// Récupération des données de consommation en base
		$resultat = selectSQL("SELECT MIN(c.`date_debut`) as dateDebut, MAX(c.`date_fin`) as dateFin FROM `consigne` c", $mysqli);
	
		// Initialisation du tableau de retour
		$donnees;
		
		// Parcourt le résultat pour récupérer la date min et la date max
		while ($ligne = $resultat->fetch_assoc()) {
			$donnees["dateDebut"] = $ligne["dateDebut"];   
			$donnees["dateFin"] = $ligne["dateFin"];
		}
		
		return $donnees;
	}
	
	/**
	 * 
	 * Construit la liste des périphérique répartie par pièce
	 * Sert à l'affichage du menu
	 * 
	*/
	function construireListeZones($mysqli) {
		// Récupération des données en base
		$resultatZones = selectSQL("SELECT `id`, `zone` FROM `zone` order by id", $mysqli);
	
		// Initialisation du tableau de retour
		$donnees = null;
		
		// Parcourt le résultat
		while ($ligneZone = $resultatZones->fetch_assoc()) {
			// Initialisation de la clé pour le tableau
		    $cle = $ligneZone["id"];
		    
		    // Ajout du libelle de la zone
		    $donnees[$cle]["libelle"] = addslashes($ligneZone["zone"]);
		}
		
		return $donnees;
	}
	
	/**
	 * 
	 * Récupère les paramètre passés dans l'URL de chargement des graphiques et 
	 * construit une clause SQL avec ses paramètres afin de n'afficher que les 
	 * périphériques sélectionné dans le menu et sur la plage de date saisie
	 * 
	 * Elle prend en paramètre un booleen qui indique s'il s'agit du graphique
	 * pour les températures
	 * 
	*/ 
	
	/**
	 * 
	 * Vérifie si une date est valide au format passé en paramètre
	 * 
	*/ 
	function validateDate($date, $format = 'd/m/Y')	{
	    $d = DateTime::createFromFormat($format, $date);
	    return $d && $d->format($format) == $date;
	}
	
	
	
	/**
	 * Récupère la date de dernier import afin de ne pas dupliquer les imports
	 * 
	 * @param $periphId id du périphérique
	 * @return la date du dernier import
	 */
	function recuperationDateDernierImport($table, $zone_id, $trt, $mysqli) {
		if($trt == 0) {
			$requete = "SELECT MAX(`date_fin`) as `date_fin` FROM ".$table." WHERE `zone_id` = ".$zone_id;
		}
		else {
			$requete = "SELECT MAX(`date_fin`) as `date_fin` FROM ".$table." WHERE `zone_id` = ".$zone_id." AND `trt` = ".$trt;
		}
		$resultat = $mysqli->query ($requete) or die ('Erreur SQL avec la requete : '.$requete.'<br />'.$mysqli->error.'<br />');
		$ligne = $resultat->fetch_assoc();
		return $ligne["date_fin"];
	}
	
	function recuperationDatesDernierImport($table, $zone_id, $trt, $mysqli) {
		if($trt == 0) {
			$requete = "SELECT MAX(`date_fin`) as `date_fin` FROM ".$table." WHERE `zone_id` = ".$zone_id;
		}
		else {
			$requete = "SELECT MAX(`date_fin`) as `date_fin` FROM ".$table." WHERE `zone_id` = ".$zone_id." AND `trt` = ".$trt;
		}
		$resultat = $mysqli->query ($requete) or die ('Erreur SQL avec la requete : '.$requete.'<br />'.$mysqli->error.'<br />');
		$ligne = $resultat->fetch_assoc();
		$requete = "SELECT `id`, `date_debut`, `date_fin`, `etat` FROM ".$table." WHERE `date_fin` = '".$ligne["date_fin"]."'";
		$resultat2 = $mysqli->query ($requete) or die ('Erreur SQL avec la requete : '.$requete.'<br />'.$mysqli->error.'<br />');
		$ligne2 = $resultat2->fetch_assoc();
		return $ligne2;
	}
	
	
	function heureEntreDate($ddeb, $dfin) {
		$obj_datedebut = date_create($ddeb);
		$obj_datefin = date_create($dfin);
		$n = 0;
		for ($datex = clone $obj_datedebut; $datex->format('U') < $obj_datefin->format('U'); $datex->modify('+1 hour')) {
			$n++;
		}
		return $n;
	}
	
	function ajouterHeure($ddeb, $nbh) {
		list($annee,$mois,$jour,$h,$m,$s)=sscanf($ddeb,"%d-%d-%d %d:%d:%d");
		$h+= $nbh;
		$timestamp=mktime($h,$m,$s,$mois,$jour,$annee);
		return date('Y-m-d H:i:s',$timestamp);
	}
	
	/**
	 * Insert en base de donnée la requête en paramètre
	 * 
	 * @param $requete la requête à executer
	 * @param $mysqli instance de la base de donnée
	 */
	function insertSQL($requete, $mysqli) {
		$mysqli->query($requete) or die ('Erreur SQL avec la requete : '.$requete.'<br />'.$mysqli->error.'<br />');
	}
	
	function updateSQL($requete, $mysqli) {
		$mysqli->query($requete) or die ('Erreur SQL avec la requete : '.$requete.'<br />'.$mysqli->error.'<br />');
	}
	/**
	 * 
	 * Récupère la numéro jour, mois, et saison
	 * 
	*/ 
	function recupererJourMoisSaison($dateEntree) {
		$donneesDate = array("jour" => 0, "mois" => 0, "saison" => "Hiver");
		$donneesDate["jour"] = date("w", strtotime($dateEntree));
		$donneesDate["mois"] = date("n", strtotime($dateEntree));
		
		switch ($donneesDate["mois"]) {
			case 12:
			case 1:
			case 2:
		    	$donneesDate["saison"] = "Hiver";
				break;
			case 3:
			case 4:
			case 5:
				$donneesDate["saison"] = "Printemps";
				break;
			case 6:
			case 7:
			case 8:
				$donneesDate["saison"] = "Ete";
				break;
			case 9:
			case 10:
			case 11:
				$donneesDate["saison"] = "Automne";
				break;
		}
		return $donneesDate;
	}    
	
?>