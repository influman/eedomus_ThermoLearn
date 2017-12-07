**Gestion d'un thermostat virtuel intelligent "nest-like" pour eedomus**  
  
*Installation:*  
1/ Télécharger le script thermolearn.php et le fichier ddl.sql depuis le GitHub  https://github.com/influman/eedomus_ThermoLearn  
2/ Créer une base de donnée "thermoLearnv1" sur votre serveur MySql et importer le fichier "ddl.sql" pour la création des tables (via phpMyAdmin par exemple)  
3/ Le script "thermolearn.php" est à installer sur votre  serveur web/php (pas sur la box eedomus donc),
dans un dossier /thermoLearn  
4/ Modifier le script thermolearn.php afin d'y renseigner vos paramètres d'accès à l'API eedomus et vos paramètres d'accès à la base de donnée "thermoLearn"  
  
    //*************************************** API eedomus *********************************  
    // Identifiants de l'API eeDomus  
    $api_user = "XXXXXX"; //ici saisir api user  
    $api_secret = "yyyyyyyyyyyyyyy";  //ici saisir api secret  
    //*************************************** Parametres bdd **************************  
    //server MySQL  
    $sqlserver='localhost';  
    //MySQL login  
    $sqllogin='root'; //ici saisir le user sql
    //MySQL password  
    $sqlpass='password'; //ici saisir le pass du user
    //MySQL dataBase  
    $dataBase='thermoLearnv1'; //base à créer  
  	
5/ Sur le store eedomus, installer le plug-in "thermoLearn", en renseignant :  
- l'ip d'accès au serveur php/mysql avec le chemin  
- le numéro de la zone de chauffage contrôlée par ce plug-in (1 à 8).  
- le code API du détecteur de mouvement lié à cette zone  
- le code API de la consigne réelle de Zone de Chauffage (ou votre propre consigne), qui sera synchronisée par le thermoLearn  
Le script thermolearn_call.php est alors installé sur votre box. Il permet d'appeler le script déporté, et d'y passer les arguments nécessaires.  
  
  
****************************************************************************************************************  
Le thermostat intélligent thermoLearn permet de gérer l'apprentissage de 8 zones de chauffage au maximum,  
et de restituer automatiquement les consignes de température les plus adaptées aux habitudes du foyer,  
en fonction des jours de la semaine, et des saisons.  
  
En voici les principes :  
  
Le systême a pour objectif final de fixer des consignes de température de manière autonome, de 1 à 8 consignes maximum.  
Les parties "puissance/commutation" des chauffages ne sont pas gérées via le systême d'apprentissage.   
Celui-ci ne fournit que les consignes de température au systême "puissance", qui peut donc être hystérésis ou PID, ou DIY.  
Le thermostat intelligent doit apprendre en continue en fonction des consignes fixées manuellement par les utilisateurs,   
soit manuellement, soit par règles existantes.  
Il a besoin d'un détecteur de présence par zone définie.  
Il apprend en continue, même si ce n'est pas lui qui est utilisé pour fixer la consigne au final.  
  
Positionner d'abord le thermoLearn en mode User, et sélectionner manuellement les consignes thermoLearn pendant une à deux semaines.  
Positionner ensuite le thermoLean en mode Smart, il sélectionnera alors lui-même les consignes.  
Lorsque la consigne déterminée automatiquement ne vous convient pas, il suffit de sélectionner manuellement la nouvelle consigne,  
et le thermoLearn apprendra, puis repassera en Smart automatiquement 3h plus tard.  
Au fil des jours, l'apprentissage s'affine et optimise votre consommmation, conformément à vos habitudes de confort.  
  
Le thermoLearn synchronise sa consigne avec une consigne eedomus (consigne Zone de Chauffage ou votre propre état de consigne).  
Cette consigne est à donner en paramètre via son code API.  
Il faut s'assurer que toutes les valeurs possibles de la consigne thermoLearn existent dans la consigne Zone de Chauffage, les rajouter le cas échéant.  

  

