-- v1.1
CREATE TABLE IF NOT EXISTS `Users` (
  `ID` INT(7) UNSIGNED NOT NULL AUTO_INCREMENT,
  `Username` VARCHAR(15) NOT NULL,
  `Password` VARCHAR (40) NOT NULL,
  `Email` VARCHAR (100) NOT NULL,
  `Activated` TINYINT(1)  UNSIGNED NOT NULL DEFAULT '0',
  `Confirmation` CHAR(40) NOT NULL,
  `RegDate` INT(11) UNSIGNED NOT NULL,
  `LastLogin` INT(11) UNSIGNED NOT NULL DEFAULT '0',
  `GroupID` INT(2) UNSIGNED NOT NULL DEFAULT '1',
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
