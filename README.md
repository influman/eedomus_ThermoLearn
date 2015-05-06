# eedomus_ThermoLearn
Gestion d'un thermostat intelligent "nest-like" pour eedomus

ThermoLearn est un ensemble de scripts php et d'icônes permettant la gestion d'une thermostat intelligent pour la box 
domotique eedomus

Le thermoLearn, dans sa version actuelle, doit permettre de gérer l’apprentissage de trois zones de chauffage 
et de restituer automatiquement les consignes de température les plus adaptées aux habitudes du foyer, 
en fonction des jours de la semaine, et des saisons.

En voici les principes :

Le système a pour objectif final de fixer des consignes de température de manière autonome, de 1 à 3 consignes maximum.
Les parties « puissance/commutation » des chauffages ne sont pas gérées via le système d’apprentissage. 
Celui-ci ne fournit que les consignes de température au système « puissance », qui peut donc être hystérésis ou PID, ou DIY.
Le thermostat intelligent doit apprendre en continue en fonction des consignes fixées, 
soit manuellement, soit par règles existantes ou soit par lui-même.
Il a besoin d’un détecteur de présence par zone définie.
Il apprend en continue, même si ce n’est pas lui qui est utilisé pour fixer la consigne au final.

Le tutoriel d'installation et de paramétrage côté eedomus est détaillés ici :
http://www.domo-blog.fr/le-thermostat-intelligent-pour-eedomus/
