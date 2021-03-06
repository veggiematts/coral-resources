ALTER TABLE `_DATABASE_NAME_`.`Resource`
  CHANGE COLUMN `subscriptionStartDate` `currentStartDate` date default NULL,
  CHANGE COLUMN `subscriptionEndDate` `currentEndDate` date default NULL;

ALTER TABLE `_DATABASE_NAME_`.`ResourcePayment`
  ADD COLUMN `year` varchar(20) default NULL,
  ADD COLUMN `subscriptionStartDate` date default NULL,
  ADD COLUMN `subscriptionEndDate` date default NULL,
  ADD COLUMN `costDetailsID` int(11) default NULL,
  ADD COLUMN `costNote` text,
  ADD COLUMN `invoiceNum` varchar(20);

DROP TABLE IF EXISTS `_DATABASE_NAME_`.`CostDetails`;
CREATE TABLE `_DATABASE_NAME_`.`CostDetails` (
  `costDetailsID` int(11) NOT NULL AUTO_INCREMENT,
  `shortName` varchar(200) NOT NULL,
  PRIMARY KEY (`costDetailsID`)
) ENGINE=MyISAM AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

ALTER TABLE `_DATABASE_NAME_`.`ResourcePayment`
  DROP INDEX `Index_All`,
  ADD INDEX `Index_year`(`year`),
  ADD INDEX `Index_costDetailsID`(`costDetailsID`),
  ADD INDEX `Index_invoiceNum`(`invoiceNum`),
  ADD INDEX `Index_All`(`resourceID`, `fundName`, `year`, `costDetailsID`, `invoiceNum`);

ALTER TABLE `_DATABASE_NAME_`.`CostDetails`
  ADD INDEX `costDetailsID`(`costDetailsID`),
  ADD INDEX `Index_shortName`(`shortName`),
  ADD INDEX `Index_All`(`costDetailsID`, `shortName`);
