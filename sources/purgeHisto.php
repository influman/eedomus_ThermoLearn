<?php

	// Import des fonctions communes
	include ('fonctions.php');

	// Initialisation de la base de donnée
	$mysqli = new mysqli($server, $sqllogin, $sqlpass, $dataBase);
	$mysqli->set_charset("utf8");
	
	$datejour = date("Y-m-d H:i:s");
	list($annee,$mois,$jour,$h,$m,$s)=sscanf($datejour,"%d-%d-%d %d:%d:%d");
	$annee-= 1;
	$timestamp=mktime($h,$m,$s,$mois,$jour,$annee);
	$datepurge = date('Y-m-d H:i:s',$timestamp);
	//echo "<p> ".$datepurge;
	updateSQL("DELETE FROM `consigne` WHERE date_debut < '".$datepurge."'", $mysqli);
	updateSQL("DELETE FROM `presence` WHERE date_debut < '".$datepurge."'", $mysqli);
	updateSQL("DELETE FROM `smoothPresence` WHERE date_debut < '".$datepurge."'", $mysqli);
	
	
	// Fermeture de l'instance de la base de donnée
	$mysqli->close();
	echo "1";
?>