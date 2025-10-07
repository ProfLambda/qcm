-- Schéma pour MySQL

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(100),
  `role` VARCHAR(50) NOT NULL DEFAULT 'user', -- 'user' or 'admin'
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des questionnaires
CREATE TABLE IF NOT EXISTS `quizzes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `title` VARCHAR(255) NOT NULL,
  `level` VARCHAR(100),
  `themes` JSON,
  `question_count` INT NOT NULL,
  `total_max_points` INT NOT NULL,
  `source_url` VARCHAR(2048),
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des questions
-- Le payload contient l'objet JSON complet de la question
CREATE TABLE IF NOT EXISTS `questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `quiz_id` INT NOT NULL,
  `index_in_quiz` INT NOT NULL,
  `payload` JSON NOT NULL,
  FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE,
  UNIQUE (`quiz_id`, `index_in_quiz`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des tentatives de quiz par les utilisateurs
CREATE TABLE IF NOT EXISTS `attempts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `quiz_id` INT NOT NULL,
  `started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `finished_at` DATETIME,
  `score` INT,
  `total_max` INT NOT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'in_progress', -- 'in_progress', 'finished'
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`quiz_id`) REFERENCES `quizzes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des réponses données pour chaque tentative
CREATE TABLE IF NOT EXISTS `attempt_answers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `attempt_id` INT NOT NULL,
  `question_index` INT NOT NULL,
  `selection` JSON NOT NULL, -- JSON de la sélection de l'utilisateur
  `points_earned` INT NOT NULL,
  `validated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`attempt_id`) REFERENCES `attempts` (`id`) ON DELETE CASCADE,
  UNIQUE (`attempt_id`, `question_index`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index pour améliorer les performances
CREATE INDEX idx_quizzes_slug ON quizzes (slug);
CREATE INDEX idx_questions_quiz_id ON questions (quiz_id);
CREATE INDEX idx_attempts_user_id ON attempts (user_id);
CREATE INDEX idx_attempts_quiz_id ON attempts (quiz_id);
CREATE INDEX idx_attempt_answers_attempt_id ON attempt_answers (attempt_id);