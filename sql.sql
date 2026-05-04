-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : ven. 30 mai 2025 à 11:51
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
-- Base de données : `rvd_medicale`
--

-- --------------------------------------------------------

--
-- Structure de la table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `admins`
--

INSERT INTO `admins` (`id`, `nom`, `email`, `mot_de_passe`, `created_at`) VALUES
(2, 'Admin SANTE TV', 'admin.santetv@estm.ac.ma', '$2y$10$2Rd0C0UOapctOVSU5jyoJ.g5/EOailbAQY6/VSSUMUGdu.yHnw1aq', '2025-05-18 00:24:44');

-- --------------------------------------------------------

--
-- Structure de la table `commentaires`
--

CREATE TABLE `commentaires` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contenu` text NOT NULL,
  `date_commentaire` datetime DEFAULT current_timestamp(),
  `statut` enum('en attente','validé','refusé') DEFAULT 'en attente'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `commentaires`
--

INSERT INTO `commentaires` (`id`, `nom`, `email`, `contenu`, `date_commentaire`, `statut`) VALUES
(4, 'awal', NULL, 'c\'est awal', '2025-05-27 11:25:58', 'validé');

-- --------------------------------------------------------

--
-- Structure de la table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `nom_expediteur` varchar(150) NOT NULL,
  `email_expediteur` varchar(255) NOT NULL,
  `sujet` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `date_soumission` timestamp NOT NULL DEFAULT current_timestamp(),
  `statut` enum('non lu','lu','répondu','archivé') DEFAULT 'non lu'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `nom_expediteur`, `email_expediteur`, `sujet`, `message`, `date_soumission`, `statut`) VALUES
(1, 'Abdourahamane Awal Abdoulmoumouni', 'abdourahamanea9wal@gmail.com', 'Annonce', 'salut a vous tres chere organisme ! Je suis Awal Abdourahamane. J\'aimerai juste vous feliciter pour votre aimable travail exceptionnelle, Merci !', '2025-05-25 08:47:24', 'non lu');

-- --------------------------------------------------------

--
-- Structure de la table `disponibilites_medecin`
--

CREATE TABLE `disponibilites_medecin` (
  `id` int(11) NOT NULL,
  `medecin_id` int(11) NOT NULL,
  `jour_semaine` tinyint(4) NOT NULL COMMENT '1=Lundi, 2=Mardi, ..., 7=Dimanche (Conforme à ISO-8601 DAYOFWEEK)',
  `heure_debut` time NOT NULL,
  `heure_fin` time NOT NULL,
  `type_plage` enum('travail','pause') DEFAULT 'travail'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `disponibilites_medecin`
--

INSERT INTO `disponibilites_medecin` (`id`, `medecin_id`, `jour_semaine`, `heure_debut`, `heure_fin`, `type_plage`) VALUES
(3, 3, 3, '08:30:00', '20:30:00', 'travail'),
(4, 3, 2, '08:30:00', '20:30:00', 'pause'),
(7, 3, 0, '07:00:00', '23:30:00', 'travail'),
(8, 3, 2, '21:00:00', '23:30:00', 'travail'),
(9, 3, 1, '07:30:00', '20:30:00', 'travail');

-- --------------------------------------------------------

--
-- Structure de la table `exceptions_horaires_medecin`
--

CREATE TABLE `exceptions_horaires_medecin` (
  `id` int(11) NOT NULL,
  `medecin_id` int(11) NOT NULL,
  `date_exception` date NOT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `type_exception` enum('non_travaille','indisponible','travail_exceptionnel','pause_exceptionnelle') NOT NULL DEFAULT 'non_travaille',
  `motif` varchar(255) DEFAULT NULL,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `medecins`
--

CREATE TABLE `medecins` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `specialite` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `document_justificatif` varchar(255) DEFAULT NULL,
  `valide` tinyint(1) DEFAULT 0 COMMENT '0=en attente, 1=validé',
  `photo` varchar(255) DEFAULT NULL,
  `horaires` text DEFAULT NULL COMMENT 'Description textuelle des horaires',
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `medecins`
--

INSERT INTO `medecins` (`id`, `nom`, `prenom`, `specialite`, `email`, `telephone`, `adresse`, `mot_de_passe`, `document_justificatif`, `valide`, `photo`, `horaires`, `latitude`, `longitude`, `created_at`) VALUES
(3, 'AB Karim', 'AB', 'Cardiologie', 'abdourahamanea8wal@gmail.com', '+212656629464', 'abdourahamanea8wal@gmail.com', '$2y$10$Zughs/RGNFbyz7EPUuUjcewp8jFZekhBi3UR1Cfc54F5bCqWlOxKy', 'uploads/documents_medecins/doc_ab_karim_ab_68291bda5fd5c2.68785397.pdf', 1, 'uploads/photos/user_3_6829cae41f4f44.74542665.jpg', '', 33.85467126, -5.58095049, '2025-05-17 23:29:30'),
(5, 'natsu', 'ameena', 'Dermatologie', 'adminab@estm.ac.ma', '+212656629464', 'a.awaladboulmoumouni@edu.uni.ac.ma', '$2y$10$7UdIMwP8w4EIRzVbsNALfeG7GJtyvhJ3Bbt4Bk0uvR05CP0ikCrmu', 'uploads/documents_medecins/doc_natsu_ameena_6829e804abd8f2.26165596.pdf', 1, 'uploads/photos/user_5_6829ea2c23d069.86359233.png', '', NULL, NULL, '2025-05-18 14:00:36'),
(6, 'Abdourahamane', 'ameena', 'Interniste', 'admin.santetv@estm.ac.marfreioer', '43145324534', '2334534', '$2y$10$9Yqz3IM41KLo29xb6sRxe.9o0ywJb6dPsWTPrd7piUX6Hz4fzxNBy', 'uploads/documents_medecins/doc_abdourahamane_ameena_683761995d2647.69357953.pdf', 0, NULL, NULL, NULL, NULL, '2025-05-28 19:18:49'),
(7, 'Awal', 'Hassan', 'Néphrologue', 'ab@gmail.com', '+43145324534', '435343', '$2y$10$QAs4qjvIn8qTHVWQ16mHiOZp1DxQP.RKrMVH1nhguu1wzIUJDBGOW', 'uploads/documents_medecins/doc_awal_hassan_683761f192d976.23656778.pdf', 0, NULL, NULL, NULL, NULL, '2025-05-28 19:20:17'),
(8, 'Awal Abdoulmoumouni', 'Hassan', 'Endocrinologie', 'ab@gmail.ma', '+43145324534', '655463', '$2y$10$R7ZBDo2cyoBemQ3akblh3ONLMNU8M/nMU/Xima109xx8LNBggAJxu', 'uploads/documents_medecins/doc_awal_abdoulmoumouni_hassan_683762c0889092.34257337.pdf', 0, NULL, NULL, NULL, NULL, '2025-05-28 19:23:44'),
(9, 'Brawe', 'Bouta', 'Néphrologue', 'admin.santetv@rgnklm.ma', '43145324534', '2424234', '$2y$10$fQyaoDpq8/GLe9FmcPJ9EeoZ/BXoGrXIT4Jqhc2KA737PbIOgeB6u', 'uploads/documents_medecins/doc_brawe_bouta_6837c9e0716ed0.99577671.pdf', 0, NULL, NULL, NULL, NULL, '2025-05-29 02:43:44'),
(10, 'Brawe', 'Buta', 'Gériatre', 'admin.santetv@rgnklmm.ma', '43145324534', '134254', '$2y$10$ANBKlrCqzoKQUwhDSaliOuPbQZgSLUqBkKNmyfsM8WbMHAFdWfFTK', 'uploads/documents_medecins/doc_brawe_buta_6837ca6e218079.14100277.pdf', 0, NULL, NULL, NULL, NULL, '2025-05-29 02:46:06');

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL COMMENT 'ID de l''expéditeur patient',
  `destinataire_id` int(11) NOT NULL COMMENT 'ID du médecin destinataire',
  `sujet_message` varchar(255) DEFAULT NULL,
  `contenu` text NOT NULL,
  `date_envoi` datetime DEFAULT current_timestamp(),
  `lu_par_medecin` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `messages`
--

INSERT INTO `messages` (`id`, `patient_id`, `destinataire_id`, `sujet_message`, `contenu`, `date_envoi`, `lu_par_medecin`) VALUES
(1, 4, 3, NULL, 'Salut', '2025-05-18 14:03:29', 1),
(2, 4, 5, NULL, 'Salut Mn', '2025-05-25 13:27:30', 1),
(3, 4, 3, 'Message depuis la plateforme SANTE TV', 'salut abdoul karim', '2025-05-25 19:24:48', 1),
(4, 4, 3, 'Message depuis la plateforme SANTE TV', 'hello Grand frere !', '2025-05-25 19:36:46', 1),
(5, 4, 3, 'Annonce', 'salut', '2025-05-30 00:47:00', 1);

-- --------------------------------------------------------

--
-- Structure de la table `notifications_patients`
--

CREATE TABLE `notifications_patients` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type_notification` enum('info','succes','erreur','rdv_confirme','rdv_annule','rdv_refuse','rappel_rdv') DEFAULT 'info',
  `lien` varchar(255) DEFAULT NULL,
  `details_rdv_id` int(11) DEFAULT NULL,
  `date_creation` timestamp NOT NULL DEFAULT current_timestamp(),
  `lu` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `notifications_patients`
--

INSERT INTO `notifications_patients` (`id`, `patient_id`, `message`, `type_notification`, `lien`, `details_rdv_id`, `date_creation`, `lu`) VALUES
(1, 4, 'Votre RDV du 19/05/2025 à 08:30 avec Dr. AB AB Karim a été confirmé.', 'rdv_confirme', NULL, NULL, '2025-05-18 12:09:31', 1),
(4, 4, 'Votre RDV du 18/05/2025 à 20:00 avec Dr. AB AB Karim a été confirmé.', 'rdv_confirme', NULL, 8, '2025-05-18 13:52:07', 1),
(5, 4, 'Votre RDV du 26/05/2025 à 08:00 avec Dr. AB AB Karim a été confirmé.', 'rdv_confirme', NULL, 9, '2025-05-25 15:24:26', 1);

-- --------------------------------------------------------

--
-- Structure de la table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `user_type` enum('patient','medecin','admin') NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `user_type`, `token`, `expires_at`, `used`, `created_at`) VALUES
(5, 'abdourahamanea9wal@gmail.com', 'patient', 'b887a870e7a129cc35bb9b23c19da9da2c7df5edfe170b3da2174e31d927d8ad', '2025-05-25 13:44:43', 1, '2025-05-25 10:44:43');

-- --------------------------------------------------------

--
-- Structure de la table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `sexe` enum('Homme','Femme','Autre') DEFAULT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `patients`
--

INSERT INTO `patients` (`id`, `nom`, `prenom`, `email`, `telephone`, `adresse`, `date_naissance`, `sexe`, `mot_de_passe`, `photo`, `created_at`) VALUES
(4, 'Awal Abdoulmoumouni', 'Abdourahamane', 'abdourahamanea9wal@gmail.com', NULL, 'abdourahamanea9wal@gmail.com', '2002-03-10', 'Homme', '$2y$10$U/4ymkPb.x/J5yOB0ceo8uU1U2zD2tskoPXNsIL7EybC1PFAqg8.i', 'uploads/photos/user_4_68322bab1194c2.51114506.jpg', '2025-05-18 11:58:13'),
(5, 'natsu', 'ameena', 'a.awaladboulmoumouni@edu.uni.ac.ma', NULL, 'a.awaladboulmoumouni@edu.uni.ac.ma', '2003-04-23', 'Femme', '$2y$10$udv.eA1nRP5obm0tfKJdiOi.eCA3JliwipJ/AU7sv3gN52GsC.U9u', 'uploads/photos/user_5_68322c01954307.44761414.png', '2025-05-18 13:56:07'),
(6, 'Awal', 'Hassan', 'a.awaladbouuni@edu.uni.ac.ma', NULL, '34343', '2005-03-12', 'Homme', '$2y$10$cYnlSx.ueQ0hfLOTWUmafehNlTux/d2Z0/ZSWI4T9xQIgyDDG69ga', NULL, '2025-05-28 19:15:56'),
(7, 'Dambou', 'Doura', 'ab@gmail.ac.ma', NULL, 'ab@gmail.com', '2004-03-21', 'Homme', '$2y$10$0hvzitM5ThKnl9ReG8pCMe9NVejwZdLunPf0Ux96Z451mUN52gRE.', NULL, '2025-05-29 10:59:05');

-- --------------------------------------------------------

--
-- Structure de la table `rendez_vous`
--

CREATE TABLE `rendez_vous` (
  `id` int(11) NOT NULL,
  `medecin_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `date_rdv` date NOT NULL,
  `heure_rdv` time NOT NULL,
  `statut` enum('en attente','confirmé','annulé','refusé','terminé') DEFAULT 'en attente',
  `motif_rdv` text DEFAULT NULL,
  `motif_annulation` text DEFAULT NULL,
  `vue_par_patient` tinyint(1) DEFAULT 0,
  `vue_par_medecin` tinyint(1) DEFAULT 0,
  `supprime_par_patient` tinyint(1) NOT NULL DEFAULT 0,
  `supprime_par_medecin` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `rendez_vous`
--

INSERT INTO `rendez_vous` (`id`, `medecin_id`, `patient_id`, `date_rdv`, `heure_rdv`, `statut`, `motif_rdv`, `motif_annulation`, `vue_par_patient`, `vue_par_medecin`, `supprime_par_patient`, `supprime_par_medecin`, `created_at`, `updated_at`) VALUES
(7, 3, 4, '2025-05-19', '21:00:00', 'en attente', NULL, NULL, 0, 1, 0, 0, '2025-05-18 13:49:14', '2025-05-18 13:51:30'),
(8, 3, 4, '2025-05-18', '20:00:00', 'confirmé', NULL, NULL, 1, 1, 0, 1, '2025-05-18 13:50:34', '2025-05-25 08:38:08'),
(9, 3, 4, '2025-05-26', '08:00:00', 'confirmé', NULL, NULL, 1, 1, 0, 0, '2025-05-25 06:32:14', '2025-05-25 15:24:47');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_unique_admins` (`email`);

--
-- Index pour la table `commentaires`
--
ALTER TABLE `commentaires`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_statut_date_commentaire` (`statut`,`date_commentaire`);

--
-- Index pour la table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contact_email_exp` (`email_expediteur`),
  ADD KEY `idx_contact_statut_date_soumission` (`statut`,`date_soumission`);

--
-- Index pour la table `disponibilites_medecin`
--
ALTER TABLE `disponibilites_medecin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_medecin_jour_dispo` (`medecin_id`,`jour_semaine`);

--
-- Index pour la table `exceptions_horaires_medecin`
--
ALTER TABLE `exceptions_horaires_medecin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_medecin_date_exception_horaires` (`medecin_id`,`date_exception`);

--
-- Index pour la table `medecins`
--
ALTER TABLE `medecins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_unique_medecins` (`email`),
  ADD KEY `idx_specialite_valide` (`specialite`,`valide`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_patient_id_messages` (`patient_id`),
  ADD KEY `idx_destinataire_id_messages` (`destinataire_id`),
  ADD KEY `idx_destinataire_date_messages` (`destinataire_id`,`date_envoi`);

--
-- Index pour la table `notifications_patients`
--
ALTER TABLE `notifications_patients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `details_rdv_id` (`details_rdv_id`);

--
-- Index pour la table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_token_resets` (`token`),
  ADD KEY `idx_email_type_expires_resets` (`email`,`user_type`,`expires_at`);

--
-- Index pour la table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_unique_patients` (`email`);

--
-- Index pour la table `rendez_vous`
--
ALTER TABLE `rendez_vous`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uc_medecin_datetime_statut` (`medecin_id`,`date_rdv`,`heure_rdv`,`statut`) COMMENT 'Pourrait être affiné pour permettre plusieurs rdv annulés/refusés au même créneau mais un seul actif',
  ADD KEY `idx_medecin_id_rdv` (`medecin_id`),
  ADD KEY `idx_patient_id_rdv` (`patient_id`),
  ADD KEY `idx_medecin_date_heure_rdv` (`medecin_id`,`date_rdv`,`heure_rdv`),
  ADD KEY `idx_patient_date_heure_rdv` (`patient_id`,`date_rdv`,`heure_rdv`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `commentaires`
--
ALTER TABLE `commentaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `disponibilites_medecin`
--
ALTER TABLE `disponibilites_medecin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT pour la table `exceptions_horaires_medecin`
--
ALTER TABLE `exceptions_horaires_medecin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `medecins`
--
ALTER TABLE `medecins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `notifications_patients`
--
ALTER TABLE `notifications_patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `rendez_vous`
--
ALTER TABLE `rendez_vous`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `disponibilites_medecin`
--
ALTER TABLE `disponibilites_medecin`
  ADD CONSTRAINT `disponibilites_medecin_ibfk_1` FOREIGN KEY (`medecin_id`) REFERENCES `medecins` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `exceptions_horaires_medecin`
--
ALTER TABLE `exceptions_horaires_medecin`
  ADD CONSTRAINT `exceptions_horaires_medecin_ibfk_1` FOREIGN KEY (`medecin_id`) REFERENCES `medecins` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_medecin` FOREIGN KEY (`destinataire_id`) REFERENCES `medecins` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_messages_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `notifications_patients`
--
ALTER TABLE `notifications_patients`
  ADD CONSTRAINT `notifications_patients_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_patients_ibfk_2` FOREIGN KEY (`details_rdv_id`) REFERENCES `rendez_vous` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `rendez_vous`
--
ALTER TABLE `rendez_vous`
  ADD CONSTRAINT `fk_rendez_vous_medecin` FOREIGN KEY (`medecin_id`) REFERENCES `medecins` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_rendez_vous_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
