CREATE TABLE IF NOT EXISTS `presence` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`date_debut` datetime NOT NULL,
`date_fin` datetime NOT NULL,
`etat` varchar(30) NOT NULL,
`zone_id` int(11) NOT NULL,
`trt` int(11) NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=96 ;
ALTER TABLE `presence` ADD INDEX (`date_debut`);

CREATE TABLE IF NOT EXISTS `consigne` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`date_debut` datetime NOT NULL,
`date_fin` datetime NOT NULL,
`consigne` decimal(7,2) NOT NULL,
`zone_id` int(11) NOT NULL,
`trt` int(11) NOT NULL,
`jour` int(11) NOT NULL,
`mois` int(11) NOT NULL,
`saison` varchar(10) NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=96 ;
ALTER TABLE `consigne` ADD INDEX (`date_debut`);

CREATE TABLE IF NOT EXISTS `smoothPresence` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`date_debut` datetime NOT NULL,
`date_fin` datetime NOT NULL,
`etat` varchar(30) NOT NULL,
`zone_id` int(11) NOT NULL,
`trt` int(11) NOT NULL,
`jour` int(11) NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=96 ;
ALTER TABLE `smoothPresence` ADD INDEX (`date_debut`);

CREATE TABLE IF NOT EXISTS `IAConsigne` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`heure_debut` varchar(5)  NOT NULL,
`heure_fin` varchar(5)  NOT NULL,
`consigne` decimal(7,2) NOT NULL,
`zone_id` int(11) NOT NULL,
`trt` int(11) NOT NULL,
`jour` int(11) NOT NULL,
`saison` varchar(10) NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=96 ;
ALTER TABLE `IAConsigne` ADD INDEX (`heure_debut`);

CREATE TABLE IF NOT EXISTS `IAPresenceTmp` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`heure_debut` int(2) NOT NULL,
`heure_fin` int(2) NOT NULL,
`etat` varchar(30) NOT NULL,
`zone_id` int(11) NOT NULL,
`coef` int(11) NOT NULL,
`trt` int(11) NOT NULL,
`jour` int(11) NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=96 ;
ALTER TABLE `IAPresenceTmp` ADD INDEX (`heure_debut`);

CREATE TABLE IF NOT EXISTS `IAPresence` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`heure_debut` int(2) NOT NULL,
`heure_fin` int(2) NOT NULL,
`etat` varchar(30) NOT NULL,
`zone_id` int(11) NOT NULL,
`coef` int(11) NOT NULL,
`trt` int(11) NOT NULL,
`jour` int(11) NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=96 ;
ALTER TABLE `IAPresence` ADD INDEX (`heure_debut`);

CREATE TABLE IF NOT EXISTS `zone` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`zone` varchar(30) NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=96 ;

INSERT INTO `thermoLearn`.`zone` (`id`, `zone`) VALUES ('1', 'Zone #1');
INSERT INTO `thermoLearn`.`zone` (`id`, `zone`) VALUES ('2', 'Zone #2');
INSERT INTO `thermoLearn`.`zone` (`id`, `zone`) VALUES ('3', 'Zone #3');