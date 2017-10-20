# eedomus_ThermoLearn
Gestion d'un thermostat virtuel intelligent "nest-like" pour eedomus

Installation:
1/ Créer une base de donnée "thermoLearn" et importer le fichier "ddl.sql" pour la création des tables

2/ Le script "thermolearn.php" est à installer sur votre propre serveur web/php avec base MySQL (pas sur la box eedomus),
dans un dossier /thermoLearn

3/ Modifier le script thermolearn.php afin d'y renseigner vos paramètres d'accès à l'API eedomus et vos paramètres d'accès à la base de donnée "thermoLearn"

//*************************************** API eedomus *********************************
// Identifiants de l'API eeDomus
$api_user = "XXXXXX"; //ici saisir api user
$api_secret = "yyyyyyyyyyyyyyy";  //ici saisir api secret
//*************************************** Parametres bdd **************************
//server MySQL
$sqlserver='localhost';
//MySQL login
$sqllogin='root'; //ici saisir le user sql de phpmyadmin
//MySQL password
$sqlpass='password'; //ici saisir le pass du user phpmyadmin
//MySQL dataBase
$dataBase='thermoLearn'; //base à créer

4/ Sur le store eedomus, installer le plug-in "thermoLearn", en renseignant :
- l'ip d'accès au serveur php/mysql
- le numéro de la zone de chauffage contrôlée par ce plug-in (1 à 8).
- le détecteur de mouvement lié à cette zone
- la consigne réelle de Zone de Chauffage, qui sera syncrhonisée par le thermoLearn


****************************************************************************************************************
Le thermoLearn permet de gérer l’apprentissage de 8 zones de chauffage au maximum,
et de restituer automatiquement les consignes de température les plus adaptées aux habitudes du foyer, 
en fonction des jours de la semaine, et des saisons.

En voici les principes :

Le système a pour objectif final de fixer des consignes de température de manière autonome, de 1 à 8 consignes maximum.
Les parties « puissance/commutation » des chauffages ne sont pas gérées via le système d’apprentissage. 
Celui-ci ne fournit que les consignes de température au système « puissance », qui peut donc être hystérésis ou PID, ou DIY.
Le thermostat intelligent doit apprendre en continue en fonction des consignes fixées, 
soit manuellement, soit par règles existantes ou soit par lui-même.
Il a besoin d’un détecteur de présence par zone définie.
Il apprend en continue, même si ce n’est pas lui qui est utilisé pour fixer la consigne au final.

