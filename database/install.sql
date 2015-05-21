-- MySQL dump 10.15  Distrib 10.0.18-MariaDB, for Linux (x86_64)
--
-- Host: 192.168.1.245    Database: coinkit_bitcoin_testnet3_import
-- ------------------------------------------------------
-- Server version	10.0.17-MariaDB-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounts` (
  `account_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) DEFAULT NULL,
  PRIMARY KEY (`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `accounts_addresses`
--

DROP TABLE IF EXISTS `accounts_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounts_addresses` (
  `account_address_id` int(11) NOT NULL AUTO_INCREMENT,
  `account` varchar(128) DEFAULT NULL,
  `address` varchar(128) DEFAULT NULL,
  `wallet_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`account_address_id`)
) ENGINE=InnoDB AUTO_INCREMENT=506 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `addresses`
--

DROP TABLE IF EXISTS `addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `addresses` (
  `address_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_id` int(11) DEFAULT NULL,
  `address` varchar(60) DEFAULT NULL,
  `ismine` tinyint(1) DEFAULT NULL,
  `isscript` tinyint(1) DEFAULT NULL,
  `pubkey` varchar(255) DEFAULT NULL,
  `iscompressed` tinyint(1) DEFAULT NULL,
  `archived` tinyint(4) DEFAULT '0',
  `type` varchar(8) DEFAULT NULL,
  `type_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`address_id`),
  UNIQUE KEY `address` (`address`),
  KEY `addr` (`address`(10))
) ENGINE=InnoDB AUTO_INCREMENT=13024 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `addresses_aliases`
--

DROP TABLE IF EXISTS `addresses_aliases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `addresses_aliases` (
  `alias_id` int(11) NOT NULL AUTO_INCREMENT,
  `target_address_id` int(11) NOT NULL,
  `address_id` int(11) NOT NULL,
  `secret_id` int(11) DEFAULT NULL,
  `archived` tinyint(4) DEFAULT '0',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`alias_id`),
  KEY `address_id` (`target_address_id`)
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `blocks`
--

DROP TABLE IF EXISTS `blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blocks` (
  `block_id` int(11) NOT NULL AUTO_INCREMENT,
  `hash` varchar(255) DEFAULT NULL,
  `size` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `version` double DEFAULT NULL,
  `merkleroot` varchar(255) DEFAULT NULL,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `nonce` int(11) DEFAULT NULL,
  `bits` varchar(16) DEFAULT NULL,
  `difficulty` double DEFAULT NULL,
  `previousblockhash` varchar(255) DEFAULT NULL,
  `nextblockhash` varchar(255) DEFAULT NULL,
  `previous_block_id` int(11) DEFAULT NULL,
  `next_block_id` int(11) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`block_id`),
  KEY `previous_block_id` (`previous_block_id`),
  KEY `next_block_id` (`next_block_id`),
  KEY `hash` (`hash`(20)),
  KEY `previousblockhash` (`hash`(20)),
  KEY `nextblockhash` (`hash`(20))
) ENGINE=InnoDB AUTO_INCREMENT=4986 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions` (
  `transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `amount` bigint(20) DEFAULT NULL,
  `blockhash` varchar(255) DEFAULT NULL,
  `txid` varchar(255) DEFAULT NULL,
  `inwallet` tinyint(1) DEFAULT NULL,
  `blocktime` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `last_updated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `fee` int(11) DEFAULT NULL,
  `block_id` int(11) DEFAULT '0',
  `version` int(11) DEFAULT NULL,
  `locktime` int(32) DEFAULT NULL,
  `time` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`transaction_id`),
  KEY `txid` (`txid`)
) ENGINE=InnoDB AUTO_INCREMENT=14675 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transactions_vins`
--

DROP TABLE IF EXISTS `transactions_vins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions_vins` (
  `vin_id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) DEFAULT NULL,
  `txid` varchar(255) DEFAULT NULL,
  `vout` int(11) DEFAULT NULL,
  `asm` varchar(255) DEFAULT NULL,
  `hex` varchar(255) DEFAULT NULL,
  `sequence` int(10) unsigned DEFAULT NULL,
  `vout_id` int(11) DEFAULT NULL,
  `coinbase` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`vin_id`),
  KEY `vout_id` (`vout_id`),
  KEY `transaction_id` (`transaction_id`)
) ENGINE=InnoDB AUTO_INCREMENT=19313 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transactions_vouts`
--

DROP TABLE IF EXISTS `transactions_vouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions_vouts` (
  `vout_id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) DEFAULT NULL,
  `value` bigint(20) DEFAULT NULL,
  `n` int(11) DEFAULT NULL,
  `hexgz` mediumblob DEFAULT NULL,
  `reqSigs` int(11) DEFAULT NULL,
  `type` varchar(64) DEFAULT NULL,
  `txid` varchar(255) DEFAULT NULL,
  `coinbase` varchar(255) DEFAULT NULL,
  `spentat` varchar(128) DEFAULT NULL,
  `spentat_transaction_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`vout_id`),
  UNIQUE KEY `txid_n` (`txid`,`n`),
  KEY `transaction_id` (`transaction_id`)
) ENGINE=InnoDB AUTO_INCREMENT=31001 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `transactions_vouts_addresses`
--

DROP TABLE IF EXISTS `transactions_vouts_addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions_vouts_addresses` (
  `vout_address_id` int(11) NOT NULL AUTO_INCREMENT,
  `vout_id` int(11) DEFAULT NULL,
  `address_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`vout_address_id`),
  KEY `address_id` (`address_id`),
  KEY `vout_id` (`vout_id`)
) ENGINE=InnoDB AUTO_INCREMENT=29913 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

drop function if exists sendingAddresses;
delimiter  ~

create function sendingAddresses (transactionId int)
  returns text reads sql data
  begin
    declare fini integer default 0;
    declare address varchar(100) default "";
    declare addresses text default "";

    declare transaction_vin_addresses cursor for
      select
        distinct addresses.address
      from addresses
      left join transactions_vouts_addresses on addresses.address_id = transactions_vouts_addresses.address_id
      left join transactions_vins on transactions_vouts_addresses.vout_id = transactions_vins.vout_id
      where transactions_vins.transaction_id = transactionId;

    declare continue handler
        for not found set fini = 1;

    open transaction_vin_addresses;

    address_loop: loop

      fetch transaction_vin_addresses into address;
      if fini = 1 then
          leave address_loop;
      end if;

      set addresses = concat(address,',',addresses);
    end loop address_loop;

    close transaction_vin_addresses;
    return substring(addresses,1,length(addresses)-1);
  end ~

delimiter ;
