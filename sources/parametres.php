<?php
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
	//
	//*************************************** API eedomus *********************************
	// Identifiants de l'API eeDomus
	$api_user = "XXXXXX"; //ici saisir api user
	$api_secret = "xxxxxxxxxxxxxxxx";  //ici saisir api secret
	$api_param = "111111"; //api de l'etat parametres
	
	//*************************************** Parametres bdd **************************
	//server MySQL
	$server='localhost';
	//MySQL login
	$sqllogin='root'; //ici saisir le user sql de phpmyadmin
	//MySQL password
	$sqlpass='xxxxxx'; //ici saisir le pass du user phpmyadmin
	//MySQL dataBase
	$dataBase='thermoLearn';
	
	//************************** Consigne par défaut en cas d'apprentissage insuffisant ****
	$consigneDefaut = '19.00';
	$consigneEco = '16.00';
	$consigneHG = '9.00';
	
	//**** Nb de phases/consignes identiques nécessaire pour être restitué en mode intelligent ****
	$representativite = 1;
?>