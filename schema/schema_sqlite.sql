-- Schéma pour SQLite

-- Table des utilisateurs
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    display_name TEXT,
    role TEXT NOT NULL DEFAULT 'user', -- 'user' or 'admin'
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table des questionnaires
CREATE TABLE IF NOT EXISTS quizzes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    level TEXT,
    themes TEXT, -- JSON array stocké en TEXT
    question_count INTEGER NOT NULL,
    total_max_points INTEGER NOT NULL,
    source_url TEXT,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Table des questions
-- Le payload contient l'objet JSON complet de la question
CREATE TABLE IF NOT EXISTS questions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    quiz_id INTEGER NOT NULL,
    index_in_quiz INTEGER NOT NULL,
    payload TEXT NOT NULL, -- JSON de la question stocké en TEXT
    FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE,
    UNIQUE (quiz_id, index_in_quiz)
);

-- Table des tentatives de quiz par les utilisateurs
CREATE TABLE IF NOT EXISTS attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    quiz_id INTEGER NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME,
    score INTEGER,
    total_max INTEGER NOT NULL,
    status TEXT NOT NULL DEFAULT 'in_progress', -- 'in_progress', 'finished'
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes (id) ON DELETE CASCADE
);

-- Table des réponses données pour chaque tentative
CREATE TABLE IF NOT EXISTS attempt_answers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    attempt_id INTEGER NOT NULL,
    question_index INTEGER NOT NULL,
    selection TEXT NOT NULL, -- JSON de la sélection de l'utilisateur
    points_earned INTEGER NOT NULL,
    validated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (attempt_id) REFERENCES attempts (id) ON DELETE CASCADE,
    UNIQUE (attempt_id, question_index)
);

-- Index pour améliorer les performances
CREATE INDEX IF NOT EXISTS idx_quizzes_slug ON quizzes (slug);
CREATE INDEX IF NOT EXISTS idx_questions_quiz_id ON questions (quiz_id);
CREATE INDEX IF NOT EXISTS idx_attempts_user_id ON attempts (user_id);
CREATE INDEX IF NOT EXISTS idx_attempts_quiz_id ON attempts (quiz_id);
CREATE INDEX IF NOT EXISTS idx_attempt_answers_attempt_id ON attempt_answers (attempt_id);