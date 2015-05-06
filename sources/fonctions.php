<?php 

	/**
	 * 
	 * Regroupe les fonctions communes � l'ensemble de l'application 
	 * 
	 */ 

	// Import des param�tres
	include ('parametres.php');

	/**
	 * 
	 * R�alise en select en base de donn�e et retourne le r�sultat
	 * 
	*/
	function selectSQL($requete) {
		// R�cup�ration des variables globales
		global $server;
		global $sqllogin;
		global $sqlpass;
		global $dataBase;
		
		// Initialisation de la base de donn�e
		$mysqli = new mysqli($server, $sqllogin, $sqlpass, $dataBase);
		$mysqli->set_charset("utf8");
		
		// R�cup�ration des informations en base de donn�e
		$resultat = $mysqli->query ($requete) or die ('Erreur SQL avec la requete : '.$requete.'<br />'.$mysqli->error.'<br />');
	
		// Fermeture de l'instance de la base de donn�e
		$mysqli->close();
		
		// Retour du r�sultat brut
		return $resultat;
	}
	
	/**
	 * 
	 * R�cup�re la date de d�but min et la date de fin max en base de donn�e
	 * 
	*/ 
	function recupererDateDebutEtDateFin() {
		// R�cup�ration des donn�es de consommation en base
		$resultat = selectSQL("SELECT MIN(c.`date_debut`) as dateDebut, MAX(c.`date_fin`) as dateFin FROM `consigne` c");
	
		// Initialisation du tableau de retour
		$donnees;
		
		// Parcourt le r�sultat pour r�cup�rer la date min et la date max
		while ($ligne = $resultat->fetch_assoc()) {
			$donnees["dateDebut"] = $ligne["dateDebut"];   
			$donnees["dateFin"] = $ligne["dateFin"];
		}
		
		return $donnees;
	}
	
	/**
	 * 
	 * Construit la liste des p�riph�rique r�partie par pi�ce
	 * Sert � l'affichage du menu
	 * 
	*/
	function construireListeZones() {
		// R�cup�ration des donn�es en base
		$resultatZones = selectSQL("SELECT `id`, `zone` FROM `zone` order by id");
	
		// Initialisation du tableau de retour
		$donnees = null;
		
		// Parcourt le r�sultat
		while ($ligneZone = $resultatZones->fetch_assoc()) {
			// Initialisation de la cl� pour le tableau
		    $cle = $ligneZone["id"];
		    
		    // Ajout du libelle de la zone
		    $donnees[$cle]["libelle"] = addslashes($ligneZone["zone"]);
		}
		
		return $donnees;
	}
	
	/**
	 * 
	 * R�cup�re les param�tre pass�s dans l'URL de chargement des graphiques et 
	 * construit une clause SQL avec ses param�tres afin de n'afficher que les 
	 * p�riph�riques s�lectionn� dans le menu et sur la plage de date saisie
	 * 
	 * Elle prend en param�tre un booleen qui indique s'il s'agit du graphique
	 * pour les temp�ratures
	 * 
	*/ 
	
	/**
	 * 
	 * V�rifie si une date est valide au format pass� en param�tre
	 * 
	*/ 
	function validateDate($date, $format = 'd/m/Y')	{
	    $d = DateTime::createFromFormat($format, $date);
	    return $d && $d->format($format) == $date;
	}
	
	
	
	/**
	 * R�cup�re la date de dernier import afin de ne pas dupliquer les imports
	 * 
	 * @param $periphId id du p�riph�rique
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
	 * Insert en base de donn�e la requ�te en param�tre
	 * 
	 * @param $requete la requ�te � executer
	 * @param $mysqli instance de la base de donn�e
	 */
	function insertSQL($requete, $mysqli) {
		$mysqli->query($requete) or die ('Erreur SQL avec la requete : '.$requete.'<br />'.$mysqli->error.'<br />');
	}
	
	function updateSQL($requete, $mysqli) {
		$mysqli->query($requete) or die ('Erreur SQL avec la requete : '.$requete.'<br />'.$mysqli->error.'<br />');
	}
	/**
	 * 
	 * R�cup�re la num�ro jour, mois, et saison
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