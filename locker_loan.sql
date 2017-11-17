-- phpMyAdmin SQL Dump
-- version 4.0.10deb1
-- http://www.phpmyadmin.net
--
-- Inang: localhost
-- Waktu pembuatan: 13 Nov 2017 pada 20.12
-- Versi Server: 5.5.58-0ubuntu0.14.04.1
-- Versi PHP: 5.5.9-1ubuntu4.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Basis data: `Senayan3`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `door`
--

CREATE TABLE IF NOT EXISTS `door` (
  `door_id` int(11) NOT NULL AUTO_INCREMENT,
  `locker_id` int(11) NOT NULL,
  `door_code` varchar(20) NOT NULL,
  `rack_number` int(11) DEFAULT NULL,
  `last_updates` datetime NOT NULL,
  PRIMARY KEY (`door_id`),
  UNIQUE KEY `door_code` (`door_code`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `door_loan`
--

CREATE TABLE IF NOT EXISTS `door_loan` (
  `loan_id` int(11) NOT NULL AUTO_INCREMENT,
  `door_code` varchar(20) DEFAULT NULL,
  `member_id` varchar(20) DEFAULT NULL,
  `loan_date` date NOT NULL,
  `is_lent` int(11) NOT NULL DEFAULT '0',
  `is_return` int(11) NOT NULL DEFAULT '0',
  `return_date` date DEFAULT NULL,
  PRIMARY KEY (`loan_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `locker`
--

CREATE TABLE IF NOT EXISTS `locker` (
  `locker_id` int(3) NOT NULL AUTO_INCREMENT,
  `locker_name` varchar(50) DEFAULT NULL,
  `rack_number` int(3) DEFAULT NULL,
  `rack_location` varchar(20) NOT NULL,
  `input_date` datetime NOT NULL,
  `last_update` datetime DEFAULT NULL,
  PRIMARY KEY (`locker_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Struktur dari tabel `locker_pattern`
--

CREATE TABLE IF NOT EXISTS `locker_pattern` (
  `pattern_id` int(2) NOT NULL AUTO_INCREMENT,
  `pattern_prefix` varchar(5) DEFAULT NULL,
  `pattern_zero` int(10) DEFAULT NULL,
  `pattern_suffix` varchar(5) DEFAULT NULL,
  `input_date` date DEFAULT NULL,
  `last_update` datetime DEFAULT NULL,
  PRIMARY KEY (`pattern_id`),
  UNIQUE KEY `pattern_prefix` (`pattern_prefix`,`pattern_suffix`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
