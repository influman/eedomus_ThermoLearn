<?php

	// Import des fonctions communes
	include ('fonctions.php');

	// Initialisation de la base de donnée
	$mysqli = new mysqli($server, $sqllogin, $sqlpass, $dataBase);
	$mysqli->set_charset("utf8");
	
	// Destruction des apprentissages réalisés
	updateSQL("DELETE FROM `IAConsigne`", $mysqli);
	updateSQL("DELETE FROM `IAPresence`", $mysqli);
	updateSQL("DELETE FROM `IAPresenceTmp`", $mysqli);
	updateSQL("DELETE FROM `smoothPresence`", $mysqli);
	    
	// Fermeture de l'instance de la base de donnée
	$mysqli->close();
	echo "1";
?>