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

CREATE TABLE IF NOT EXISTS `mode` (
`id` int(11) NOT NULL AUTO_INCREMENT,
`zone_id` int(11) NOT NULL,
`actual_mode` varchar(30) NOT NULL,
`date_actual_mode` datetime NOT NULL,
`prec_mode` varchar(30) NOT NULL,
`date_prec_mode` datetime NOT NULL,
`last_export` datetime NOT NULL,
`last_learn` datetime NOT NULL,
`api_consigne` int(11) NOT NULL,
PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=96 ;
ALTER TABLE `mode` ADD INDEX (`zone_id`);

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

INSERT INTO `thermoLearnv1`.`zone` (`id`, `zone`) VALUES ('1', 'Zone #1');
INSERT INTO `thermoLearnv1`.`zone` (`id`, `zone`) VALUES ('2', 'Zone #2');
INSERT INTO `thermoLearnv1`.`zone` (`id`, `zone`) VALUES ('3', 'Zone #3');
INSERT INTO `thermoLearnv1`.`zone` (`id`, `zone`) VALUES ('4', 'Zone #4');
INSERT INTO `thermoLearnv1`.`zone` (`id`, `zone`) VALUES ('5', 'Zone #5');
INSERT INTO `thermoLearnv1`.`zone` (`id`, `zone`) VALUES ('6', 'Zone #6');
INSERT INTO `thermoLearnv1`.`zone` (`id`, `zone`) VALUES ('7', 'Zone #7');
INSERT INTO `thermoLearnv1`.`zone` (`id`, `zone`) VALUES ('8', 'Zone #8');