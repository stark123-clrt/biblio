-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : ven. 27 juin 2025 à 00:32
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `mydb`
--

-- --------------------------------------------------------

--
-- Structure de la table `bookmarks`
--

CREATE TABLE `bookmarks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `book_id` int(11) DEFAULT NULL,
  `page_number` int(11) NOT NULL,
  `bookmark_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `bookmarks`
--

INSERT INTO `bookmarks` (`id`, `user_id`, `book_id`, `page_number`, `bookmark_name`, `created_at`) VALUES
(1, 2, 5, 21, 'Ici', '2025-06-26 21:28:47');

-- --------------------------------------------------------

--
-- Structure de la table `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `author` varchar(100) DEFAULT NULL,
  `cover_image` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `pages_count` int(11) DEFAULT NULL,
  `publication_year` int(11) DEFAULT NULL,
  `publisher` varchar(100) DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `views_count` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `books`
--

INSERT INTO `books` (`id`, `title`, `description`, `author`, `cover_image`, `file_path`, `pages_count`, `publication_year`, `publisher`, `is_featured`, `views_count`, `created_at`, `updated_at`) VALUES
(2, 'Bonjour Saint Esprit', 'Bonjour Saint Esprit de benny Hinn', 'Benny hinn', '../assets/uploads/covers/1750958044_Bonjour_Saint_Esprit.png', '../assets/uploads/books/1750958044_Bonjour_Saint_Esprit.pdf', 263, 2010, 'Benny hinn', 1, 2, '2025-06-26 17:14:04', '2025-06-26 18:53:41'),
(3, 'Comprendre vos droit d\'alliance', 'Comprendre vos droit d\'alliance avec kenneth', 'Kenneth E.Hagin', '../assets/uploads/covers/1750958455_Comprendre_vos_droit_d\'alliance.png', '../assets/uploads/books/1750958455_Comprendre_vos_droit_d\'alliance.pdf', 72, 1999, 'Kenneth E.Hagin', 1, 15, '2025-06-26 17:20:55', '2025-06-26 22:10:29'),
(4, 'La foi qui bouge vos problemes', 'La foi qui bouge vos problèmes avec Kenneth Hagin', 'Kenneth E.Hagin', '../assets/uploads/covers/1750959823_La_foi_qui_bouge_vos_problemes.png', '../assets/uploads/books/1750959823_La_foi_qui_bouge_vos_problemes.pdf', 139, 1981, 'Kenneth E.Hagin', 1, 10, '2025-06-26 17:43:43', '2025-06-26 21:58:39'),
(5, '4 SECRETS D\'UN MARIAGE RÉUSSI', '4 SECRETS D\'UN MARIAGE RÉUSSI Yvan Castanou', 'Yvan Castanou', '../assets/uploads/covers/1750960555_4_SECRETS_D\'UN_MARIAGE_RÉUSSI.png', '../assets/uploads/books/1750960555_4_SECRETS_D\'UN_MARIAGE_RÉUSSI.pdf', 144, 2020, 'Yvan Castanou', 1, 13, '2025-06-26 17:55:55', '2025-06-26 22:15:31');

-- --------------------------------------------------------

--
-- Structure de la table `book_categories`
--

CREATE TABLE `book_categories` (
  `book_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `book_categories`
--

INSERT INTO `book_categories` (`book_id`, `category_id`) VALUES
(2, 1),
(3, 1),
(4, 1),
(5, 1);

-- --------------------------------------------------------

--
-- Structure de la table `book_tags`
--

CREATE TABLE `book_tags` (
  `book_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `parent_id`, `created_at`, `updated_at`) VALUES
(1, 'Théologie', 'Livres sur la doctrine et les études théologiques', NULL, '2025-04-30 18:56:00', '2025-04-30 18:56:00'),
(2, 'Dévotion', 'Livres pour la croissance spirituelle personnelle', NULL, '2025-04-30 18:56:00', '2025-04-30 18:56:00'),
(3, 'Histoire de l\'Église', 'Livres sur l\'histoire du christianisme', NULL, '2025-04-30 18:56:00', '2025-04-30 18:56:00'),
(4, 'Biographies', 'Biographies de personnages bibliques et de chrétiens importants', NULL, '2025-04-30 18:56:00', '2025-04-30 18:56:00'),
(5, 'Études bibliques', 'Commentaires et études des livres de la Bible', NULL, '2025-04-30 18:56:00', '2025-04-30 18:56:00');

-- --------------------------------------------------------

--
-- Structure de la table `comments`
--

CREATE TABLE `comments` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `book_id` int(11) DEFAULT NULL,
  `comment_text` text NOT NULL,
  `rating` int(11) NOT NULL,
  `is_validated` tinyint(1) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `comments`
--

INSERT INTO `comments` (`id`, `user_id`, `book_id`, `comment_text`, `rating`, `is_validated`, `is_featured`, `created_at`, `updated_at`) VALUES
(1, 2, 3, 'j\'aime beaucoup ces livres', 5, 1, 0, '2025-06-26 18:28:50', '2025-06-26 18:32:57'),
(2, 2, 4, 'c\'etais puissant en vrai', 5, 1, 0, '2025-06-26 18:43:56', '2025-06-26 18:43:56'),
(3, 2, 5, 'Colle', 5, 1, 0, '2025-06-26 21:19:59', '2025-06-26 21:19:59');

-- --------------------------------------------------------

--
-- Structure de la table `notes`
--

CREATE TABLE `notes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `book_id` int(11) DEFAULT NULL,
  `page_number` int(11) DEFAULT NULL,
  `note_text` text NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `notes`
--

INSERT INTO `notes` (`id`, `user_id`, `book_id`, `page_number`, `note_text`, `created_at`, `updated_at`) VALUES
(1, 2, 5, 3, 'La foi des Elus', '2025-06-26 20:10:42', '2025-06-26 20:10:42'),
(2, 2, 4, 33, 'Jésus a fait une chose étonnante ici', '2025-06-26 20:56:39', '2025-06-26 21:52:54');

-- --------------------------------------------------------

--
-- Structure de la table `reading_history`
--

CREATE TABLE `reading_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `book_id` int(11) DEFAULT NULL,
  `action` enum('started','continued','finished') NOT NULL,
  `page_number` int(11) DEFAULT NULL,
  `timestamp` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `reading_history`
--

INSERT INTO `reading_history` (`id`, `user_id`, `book_id`, `action`, `page_number`, `timestamp`) VALUES
(22, 2, 5, 'continued', 1, '2025-06-26 18:33:57'),
(23, 2, 5, 'continued', 2, '2025-06-26 18:34:02'),
(24, 2, 5, 'continued', 3, '2025-06-26 18:34:05'),
(25, 2, 4, 'continued', 1, '2025-06-26 18:43:15'),
(26, 2, 4, 'continued', 2, '2025-06-26 18:43:16'),
(27, 2, 4, 'continued', 3, '2025-06-26 18:43:19'),
(28, 2, 4, 'continued', 4, '2025-06-26 18:53:07'),
(29, 2, 4, 'continued', 5, '2025-06-26 18:53:09'),
(30, 4, 5, 'continued', 1, '2025-06-26 20:22:55'),
(31, 4, 5, 'continued', 2, '2025-06-26 20:23:02'),
(32, 4, 5, 'continued', 3, '2025-06-26 20:23:07'),
(33, 4, 5, 'continued', 4, '2025-06-26 20:23:17'),
(34, 3, 5, 'continued', 1, '2025-06-26 20:23:18'),
(35, 3, 5, 'continued', 2, '2025-06-26 20:23:26'),
(36, 4, 5, 'continued', 5, '2025-06-26 20:23:30'),
(37, 4, 5, 'continued', 6, '2025-06-26 20:23:30'),
(38, 4, 5, 'continued', 7, '2025-06-26 20:23:31'),
(39, 4, 5, 'continued', 8, '2025-06-26 20:23:31'),
(40, 4, 5, 'continued', 9, '2025-06-26 20:23:31'),
(41, 4, 5, 'continued', 10, '2025-06-26 20:23:31'),
(42, 3, 5, 'continued', 3, '2025-06-26 20:23:31'),
(43, 4, 5, 'continued', 11, '2025-06-26 20:23:32'),
(44, 4, 5, 'continued', 12, '2025-06-26 20:23:32'),
(45, 4, 5, 'continued', 13, '2025-06-26 20:23:32'),
(46, 4, 5, 'continued', 14, '2025-06-26 20:23:32'),
(47, 4, 5, 'continued', 15, '2025-06-26 20:23:32'),
(48, 4, 5, 'continued', 16, '2025-06-26 20:23:32'),
(49, 4, 5, 'continued', 17, '2025-06-26 20:23:32'),
(50, 4, 5, 'continued', 18, '2025-06-26 20:23:32'),
(51, 4, 5, 'continued', 19, '2025-06-26 20:23:32'),
(52, 4, 5, 'continued', 20, '2025-06-26 20:23:32'),
(53, 4, 5, 'continued', 21, '2025-06-26 20:23:33'),
(54, 4, 5, 'continued', 22, '2025-06-26 20:23:33'),
(55, 3, 5, 'continued', 4, '2025-06-26 20:23:33'),
(56, 4, 5, 'continued', 23, '2025-06-26 20:23:33'),
(57, 4, 5, 'continued', 24, '2025-06-26 20:23:33'),
(58, 4, 5, 'continued', 25, '2025-06-26 20:23:33'),
(59, 4, 5, 'continued', 26, '2025-06-26 20:23:34'),
(60, 4, 5, 'continued', 27, '2025-06-26 20:23:34'),
(61, 4, 5, 'continued', 28, '2025-06-26 20:23:34'),
(62, 4, 5, 'continued', 29, '2025-06-26 20:23:34'),
(63, 4, 5, 'continued', 30, '2025-06-26 20:23:34'),
(64, 4, 5, 'continued', 31, '2025-06-26 20:23:34'),
(65, 4, 5, 'continued', 32, '2025-06-26 20:23:34'),
(66, 4, 5, 'continued', 33, '2025-06-26 20:23:34'),
(67, 4, 5, 'continued', 34, '2025-06-26 20:23:34'),
(68, 4, 5, 'continued', 35, '2025-06-26 20:23:34'),
(69, 4, 5, 'continued', 37, '2025-06-26 20:23:34'),
(70, 4, 5, 'continued', 39, '2025-06-26 20:23:34'),
(71, 4, 5, 'continued', 42, '2025-06-26 20:23:34'),
(72, 4, 5, 'continued', 43, '2025-06-26 20:23:34'),
(73, 4, 5, 'continued', 44, '2025-06-26 20:23:35'),
(74, 4, 5, 'continued', 45, '2025-06-26 20:23:35'),
(75, 4, 5, 'continued', 46, '2025-06-26 20:23:35'),
(76, 4, 5, 'continued', 47, '2025-06-26 20:23:35'),
(77, 4, 5, 'continued', 48, '2025-06-26 20:23:35'),
(78, 4, 5, 'continued', 49, '2025-06-26 20:23:35'),
(79, 4, 5, 'continued', 50, '2025-06-26 20:23:35'),
(80, 4, 5, 'continued', 51, '2025-06-26 20:23:36'),
(81, 4, 5, 'continued', 53, '2025-06-26 20:23:36'),
(82, 4, 5, 'continued', 56, '2025-06-26 20:23:36'),
(83, 4, 5, 'continued', 57, '2025-06-26 20:23:36'),
(84, 4, 5, 'continued', 60, '2025-06-26 20:23:36'),
(85, 4, 5, 'continued', 61, '2025-06-26 20:23:36'),
(86, 4, 5, 'continued', 62, '2025-06-26 20:23:36'),
(87, 4, 5, 'continued', 63, '2025-06-26 20:23:36'),
(88, 4, 5, 'continued', 64, '2025-06-26 20:23:37'),
(89, 4, 5, 'continued', 65, '2025-06-26 20:23:37'),
(90, 4, 5, 'continued', 67, '2025-06-26 20:23:37'),
(91, 4, 5, 'continued', 69, '2025-06-26 20:23:37'),
(92, 4, 5, 'continued', 66, '2025-06-26 20:23:37'),
(93, 4, 5, 'continued', 68, '2025-06-26 20:23:37'),
(94, 4, 5, 'continued', 70, '2025-06-26 20:23:37'),
(95, 4, 5, 'continued', 71, '2025-06-26 20:23:37'),
(96, 4, 5, 'continued', 74, '2025-06-26 20:23:38'),
(97, 4, 5, 'continued', 75, '2025-06-26 20:23:38'),
(98, 4, 5, 'continued', 76, '2025-06-26 20:23:38'),
(99, 4, 5, 'continued', 79, '2025-06-26 20:23:38'),
(100, 4, 5, 'continued', 78, '2025-06-26 20:23:38'),
(101, 4, 5, 'continued', 81, '2025-06-26 20:23:38'),
(102, 4, 5, 'continued', 80, '2025-06-26 20:23:38'),
(103, 4, 5, 'continued', 82, '2025-06-26 20:23:38'),
(104, 4, 5, 'continued', 83, '2025-06-26 20:23:38'),
(105, 4, 5, 'continued', 84, '2025-06-26 20:23:39'),
(106, 4, 5, 'continued', 85, '2025-06-26 20:23:39'),
(107, 4, 5, 'continued', 87, '2025-06-26 20:23:39'),
(108, 4, 5, 'continued', 86, '2025-06-26 20:23:39'),
(109, 4, 5, 'continued', 88, '2025-06-26 20:23:39'),
(110, 4, 5, 'continued', 89, '2025-06-26 20:23:39'),
(111, 4, 5, 'continued', 91, '2025-06-26 20:23:39'),
(112, 4, 5, 'continued', 92, '2025-06-26 20:23:39'),
(113, 4, 5, 'continued', 94, '2025-06-26 20:23:39'),
(114, 4, 5, 'continued', 95, '2025-06-26 20:23:39'),
(115, 4, 5, 'continued', 96, '2025-06-26 20:23:39'),
(116, 4, 5, 'continued', 100, '2025-06-26 20:23:39'),
(117, 4, 5, 'continued', 101, '2025-06-26 20:23:39'),
(118, 4, 5, 'continued', 102, '2025-06-26 20:23:39'),
(119, 4, 5, 'continued', 103, '2025-06-26 20:23:39'),
(120, 4, 5, 'continued', 105, '2025-06-26 20:23:39'),
(121, 4, 5, 'continued', 106, '2025-06-26 20:23:40'),
(122, 4, 5, 'continued', 107, '2025-06-26 20:23:40'),
(123, 4, 5, 'continued', 108, '2025-06-26 20:23:41'),
(124, 4, 5, 'continued', 109, '2025-06-26 20:23:41'),
(125, 4, 5, 'continued', 110, '2025-06-26 20:23:41'),
(126, 4, 5, 'continued', 111, '2025-06-26 20:23:41'),
(127, 4, 5, 'continued', 113, '2025-06-26 20:23:41'),
(128, 4, 5, 'continued', 115, '2025-06-26 20:23:41'),
(129, 4, 5, 'continued', 116, '2025-06-26 20:23:41'),
(130, 4, 5, 'continued', 119, '2025-06-26 20:23:41'),
(131, 4, 5, 'continued', 120, '2025-06-26 20:23:41'),
(132, 4, 5, 'continued', 122, '2025-06-26 20:23:41'),
(133, 4, 5, 'continued', 123, '2025-06-26 20:23:41'),
(134, 4, 5, 'continued', 124, '2025-06-26 20:23:41'),
(135, 4, 5, 'continued', 125, '2025-06-26 20:23:41'),
(136, 4, 5, 'continued', 126, '2025-06-26 20:23:41'),
(137, 4, 5, 'continued', 127, '2025-06-26 20:23:42'),
(138, 4, 5, 'continued', 128, '2025-06-26 20:23:43'),
(139, 4, 5, 'continued', 129, '2025-06-26 20:23:43'),
(140, 4, 5, 'continued', 130, '2025-06-26 20:23:43'),
(141, 4, 5, 'continued', 131, '2025-06-26 20:23:43'),
(142, 4, 5, 'continued', 133, '2025-06-26 20:23:43'),
(143, 4, 5, 'continued', 135, '2025-06-26 20:23:43'),
(144, 4, 5, 'continued', 138, '2025-06-26 20:23:43'),
(145, 4, 5, 'continued', 139, '2025-06-26 20:23:43'),
(146, 4, 5, 'continued', 140, '2025-06-26 20:23:43'),
(147, 4, 5, 'continued', 141, '2025-06-26 20:23:43'),
(148, 4, 5, 'continued', 142, '2025-06-26 20:23:43'),
(149, 4, 5, 'continued', 143, '2025-06-26 20:23:44'),
(150, 3, 5, 'continued', 5, '2025-06-26 20:23:44'),
(151, 4, 5, 'continued', 144, '2025-06-26 20:23:44'),
(152, 3, 5, 'continued', 6, '2025-06-26 20:24:15'),
(153, 3, 5, 'continued', 7, '2025-06-26 20:24:40'),
(154, 3, 5, 'continued', 8, '2025-06-26 20:24:41'),
(155, 3, 5, 'continued', 9, '2025-06-26 20:24:42'),
(156, 3, 5, 'continued', 10, '2025-06-26 20:24:43'),
(157, 5, 5, 'continued', 1, '2025-06-26 20:31:08'),
(158, 5, 5, 'continued', 2, '2025-06-26 20:31:13'),
(159, 5, 5, 'continued', 3, '2025-06-26 20:31:22'),
(160, 5, 5, 'continued', 4, '2025-06-26 20:31:33'),
(161, 5, 5, 'continued', 5, '2025-06-26 20:31:35'),
(162, 5, 5, 'continued', 6, '2025-06-26 20:31:36'),
(163, 5, 5, 'continued', 7, '2025-06-26 20:31:36'),
(164, 5, 5, 'continued', 8, '2025-06-26 20:31:37'),
(165, 5, 5, 'continued', 9, '2025-06-26 20:31:37'),
(166, 2, 4, 'continued', 6, '2025-06-26 20:37:35'),
(167, 2, 4, 'continued', 7, '2025-06-26 20:37:39'),
(168, 2, 4, 'continued', 8, '2025-06-26 20:37:40'),
(169, 2, 4, 'continued', 9, '2025-06-26 20:38:18'),
(170, 2, 4, 'continued', 10, '2025-06-26 20:38:18'),
(171, 2, 4, 'continued', 11, '2025-06-26 20:38:18'),
(172, 2, 4, 'continued', 12, '2025-06-26 20:38:19'),
(173, 2, 4, 'continued', 13, '2025-06-26 20:38:37'),
(174, 2, 4, 'continued', 14, '2025-06-26 20:38:37'),
(175, 2, 4, 'continued', 15, '2025-06-26 20:38:37'),
(176, 2, 4, 'continued', 16, '2025-06-26 20:38:37'),
(177, 2, 4, 'continued', 17, '2025-06-26 20:38:38'),
(178, 2, 4, 'continued', 18, '2025-06-26 20:39:02'),
(179, 2, 4, 'continued', 19, '2025-06-26 20:39:05'),
(180, 2, 4, 'continued', 20, '2025-06-26 20:39:10'),
(181, 2, 4, 'continued', 21, '2025-06-26 20:39:12'),
(182, 2, 4, 'continued', 22, '2025-06-26 20:39:37'),
(183, 2, 4, 'continued', 23, '2025-06-26 20:39:40'),
(184, 2, 4, 'continued', 24, '2025-06-26 20:39:52'),
(185, 2, 4, 'continued', 25, '2025-06-26 20:39:58'),
(186, 2, 4, 'continued', 26, '2025-06-26 20:39:59'),
(187, 2, 4, 'continued', 27, '2025-06-26 20:40:19'),
(188, 2, 4, 'continued', 28, '2025-06-26 20:40:33'),
(189, 2, 4, 'continued', 29, '2025-06-26 20:40:35'),
(190, 2, 4, 'continued', 30, '2025-06-26 20:40:38'),
(191, 2, 4, 'continued', 31, '2025-06-26 20:40:39'),
(192, 2, 4, 'continued', 32, '2025-06-26 20:40:59'),
(193, 2, 4, 'continued', 33, '2025-06-26 20:41:01'),
(194, 2, 4, 'continued', 34, '2025-06-26 20:57:21'),
(195, 2, 4, 'continued', 35, '2025-06-26 20:57:21'),
(196, 2, 4, 'continued', 36, '2025-06-26 20:57:27'),
(197, 2, 4, 'continued', 37, '2025-06-26 20:57:51'),
(198, 2, 5, 'continued', 4, '2025-06-26 20:58:45'),
(199, 2, 5, 'continued', 5, '2025-06-26 20:58:47'),
(200, 2, 5, 'continued', 6, '2025-06-26 21:05:14'),
(201, 2, 5, 'continued', 7, '2025-06-26 21:15:11'),
(202, 2, 5, 'continued', 8, '2025-06-26 21:15:11'),
(203, 2, 5, 'continued', 9, '2025-06-26 21:15:12'),
(204, 2, 5, 'continued', 10, '2025-06-26 21:15:12'),
(205, 2, 5, 'continued', 11, '2025-06-26 21:15:12'),
(206, 2, 5, 'continued', 12, '2025-06-26 21:15:12'),
(207, 2, 5, 'continued', 13, '2025-06-26 21:22:29'),
(208, 2, 5, 'continued', 14, '2025-06-26 21:22:31'),
(209, 2, 5, 'continued', 15, '2025-06-26 21:24:59'),
(210, 2, 5, 'continued', 16, '2025-06-26 21:25:05'),
(211, 2, 5, 'continued', 17, '2025-06-26 21:25:11'),
(212, 2, 5, 'continued', 18, '2025-06-26 21:25:14'),
(213, 2, 5, 'continued', 19, '2025-06-26 21:25:14'),
(214, 2, 5, 'continued', 20, '2025-06-26 21:25:15'),
(215, 2, 5, 'continued', 21, '2025-06-26 21:25:15'),
(216, 2, 5, 'continued', 22, '2025-06-26 21:29:04'),
(217, 2, 5, 'continued', 23, '2025-06-26 21:29:19'),
(218, 2, 5, 'continued', 24, '2025-06-26 21:31:10'),
(219, 2, 5, 'continued', 25, '2025-06-26 21:31:51'),
(220, 2, 5, 'continued', 26, '2025-06-26 21:34:07'),
(221, 2, 5, 'continued', 27, '2025-06-26 21:35:01'),
(222, 2, 5, 'continued', 28, '2025-06-26 21:35:02'),
(223, 2, 5, 'continued', 29, '2025-06-26 21:38:48'),
(224, 2, 5, 'continued', 30, '2025-06-26 21:38:51'),
(225, 2, 5, 'continued', 31, '2025-06-26 21:42:00'),
(226, 2, 5, 'continued', 32, '2025-06-26 21:42:08'),
(227, 2, 5, 'continued', 33, '2025-06-26 21:42:46'),
(228, 2, 5, 'continued', 34, '2025-06-26 21:42:49'),
(229, 2, 3, 'continued', 1, '2025-06-26 21:53:55'),
(230, 2, 3, 'continued', 2, '2025-06-26 21:53:57'),
(231, 2, 3, 'continued', 3, '2025-06-26 21:53:58'),
(232, 2, 3, 'continued', 4, '2025-06-26 21:54:02'),
(233, 2, 3, 'continued', 5, '2025-06-26 21:54:05'),
(234, 2, 3, 'continued', 6, '2025-06-26 21:54:08'),
(235, 2, 3, 'continued', 7, '2025-06-26 21:54:09'),
(236, 2, 3, 'continued', 8, '2025-06-26 21:54:09'),
(237, 2, 3, 'continued', 9, '2025-06-26 21:54:42'),
(238, 2, 3, 'continued', 10, '2025-06-26 21:54:44'),
(239, 6, 4, 'continued', 1, '2025-06-26 21:58:45'),
(240, 2, 3, 'continued', 11, '2025-06-26 22:00:22'),
(241, 2, 3, 'continued', 12, '2025-06-26 22:00:28'),
(242, 2, 3, 'continued', 13, '2025-06-26 22:00:28'),
(243, 2, 3, 'continued', 14, '2025-06-26 22:00:38'),
(244, 2, 3, 'continued', 15, '2025-06-26 22:13:47');

-- --------------------------------------------------------

--
-- Structure de la table `reading_sessions`
--

CREATE TABLE `reading_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `book_id` int(11) DEFAULT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `start_page` int(11) DEFAULT NULL,
  `end_page` int(11) DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tags`
--

CREATE TABLE `tags` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `role` enum('admin','user') DEFAULT 'user',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `first_name`, `last_name`, `profile_picture`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(2, 'Christ', '$2y$10$i2fdNcMyXtG2mEKSRimTbe42qOk2qP71/.L2WhCAQ8GLq5hF2.jOm', 'ondiyochristian10@gmail.com', 'CHRISTIAN', 'ONDIYO', '../assets/uploads/profiles/2_1750963939.jpeg', 'admin', 1, '2025-06-26 23:38:10', '2025-04-30 20:03:54', '2025-06-26 21:38:10'),
(3, 'PERSY13', '$2y$10$wqID0t/ofoPIy1e0giKRru0Hvoa4BVvw6FLPlfKow6sOCrVFJlL4K', 'menayameperside3@gmail.com', 'Perside', 'MENAYAME MANSANGA', NULL, 'user', 1, '2025-06-26 22:21:14', '2025-06-26 20:20:40', '2025-06-26 20:21:14'),
(4, '~M. Samir~', '$2y$10$I1Gen/LG5aGLFin96GXVCO/sH039Nf4XKVyXrXsvevkO9tUiU5I2O', 'samirnzamba069@gmail.com', 'Samir', 'NZAMBA', NULL, 'user', 1, '2025-06-26 22:22:13', '2025-06-26 20:21:48', '2025-06-26 20:22:13'),
(5, 'christopher', '$2y$10$xLGOAY.Z8CTNLkt.9jmfieY0p4zr2qhs6c03ZuVF7Q6plRtRGUq/q', 'christopherondiyo0@gmail.com', 'christopher', 'ONDIYO', NULL, 'user', 1, '2025-06-26 22:28:15', '2025-06-26 20:27:28', '2025-06-26 20:28:15'),
(6, 'Jean_nzamba', '$2y$10$w8hAtWgDHIKn17QnpolSquVko7kgXUPJW7mGu8TubW0vt2QNhzKru', 'albertzamba9o@gmail.com', 'NZAMBA', 'Jean claud', NULL, 'user', 1, '2025-06-26 23:58:13', '2025-06-26 21:57:55', '2025-06-26 21:58:13');

-- --------------------------------------------------------

--
-- Structure de la table `user_library`
--

CREATE TABLE `user_library` (
  `user_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `last_page_read` int(11) DEFAULT 1,
  `is_favorite` tinyint(1) DEFAULT 0,
  `added_at` timestamp NULL DEFAULT current_timestamp(),
  `last_read_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `user_library`
--

INSERT INTO `user_library` (`user_id`, `book_id`, `last_page_read`, `is_favorite`, `added_at`, `last_read_at`) VALUES
(2, 3, 15, 0, '2025-06-26 21:53:24', '2025-06-26 22:14:06'),
(2, 4, 34, 0, '2025-06-26 18:42:54', '2025-06-26 22:13:37'),
(2, 5, 30, 1, '2025-06-26 18:33:56', '2025-06-26 22:30:49'),
(3, 5, 10, 0, '2025-06-26 20:23:16', '2025-06-26 20:24:43'),
(4, 5, 144, 0, '2025-06-26 20:22:41', '2025-06-26 20:23:52'),
(5, 5, 9, 0, '2025-06-26 20:31:07', '2025-06-26 20:31:41'),
(6, 4, 1, 0, '2025-06-26 21:58:42', '2025-06-26 21:58:45');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `bookmarks`
--
ALTER TABLE `bookmarks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Index pour la table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `book_categories`
--
ALTER TABLE `book_categories`
  ADD PRIMARY KEY (`book_id`,`category_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Index pour la table `book_tags`
--
ALTER TABLE `book_tags`
  ADD PRIMARY KEY (`book_id`,`tag_id`),
  ADD KEY `tag_id` (`tag_id`);

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Index pour la table `comments`
--
ALTER TABLE `comments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Index pour la table `notes`
--
ALTER TABLE `notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Index pour la table `reading_history`
--
ALTER TABLE `reading_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Index pour la table `reading_sessions`
--
ALTER TABLE `reading_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `book_id` (`book_id`);

--
-- Index pour la table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Index pour la table `user_library`
--
ALTER TABLE `user_library`
  ADD PRIMARY KEY (`user_id`,`book_id`),
  ADD KEY `book_id` (`book_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `bookmarks`
--
ALTER TABLE `bookmarks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `comments`
--
ALTER TABLE `comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `notes`
--
ALTER TABLE `notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `reading_history`
--
ALTER TABLE `reading_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=245;

--
-- AUTO_INCREMENT pour la table `reading_sessions`
--
ALTER TABLE `reading_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `bookmarks`
--
ALTER TABLE `bookmarks`
  ADD CONSTRAINT `bookmarks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookmarks_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `book_categories`
--
ALTER TABLE `book_categories`
  ADD CONSTRAINT `book_categories_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `book_categories_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `book_tags`
--
ALTER TABLE `book_tags`
  ADD CONSTRAINT `book_tags_ibfk_1` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `book_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `comments`
--
ALTER TABLE `comments`
  ADD CONSTRAINT `comments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `comments_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `notes`
--
ALTER TABLE `notes`
  ADD CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notes_ibfk_2` FOREIGN KEY (`book_id`) REFERENCES `books` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
