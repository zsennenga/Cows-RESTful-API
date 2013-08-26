SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `cowsREST`
--
CREATE DATABASE IF NOT EXISTS `cowsREST` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `cowsREST`;

-- --------------------------------------------------------

--
-- Table structure for table `cowsrest`
--

DROP TABLE IF EXISTS `cowsrest`;
CREATE TABLE IF NOT EXISTS `cowsrest` (
  `publicKey` varchar(512) NOT NULL,
  `privateKey` varchar(512) NOT NULL,
  `sessionKey` varchar(512) NOT NULL,
  `cookieFile` varchar(1024) NOT NULL,
  `Comment` varchar(2048) NOT NULL,
  PRIMARY KEY (`publicKey`,`privateKey`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `cowsrestlog`
--

DROP TABLE IF EXISTS `cowsrestlog`;
CREATE TABLE IF NOT EXISTS `cowsrestlog` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` varchar(64) NOT NULL,
  `publicKey` varchar(128) NOT NULL,
  `route` varchar(128) NOT NULL,
  `method` varchar(16) NOT NULL,
  `params` varchar(1024) NOT NULL,
  `response` varchar(4096) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;
