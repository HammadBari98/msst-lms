<?php
// Idempotent schema setup for the teacher-review feature. Safe to call on
// every request; only does work the first time (or when migrating off the
// old fixed rating_* columns from the feature's first version).
function ensure_review_schema(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS review_criteria (
            id INT NOT NULL AUTO_INCREMENT,
            label VARCHAR(150) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teacher_reviews (
            id INT NOT NULL AUTO_INCREMENT,
            student_user_id INT NOT NULL,
            teacher_user_id INT NOT NULL,
            comments TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_student_teacher (student_user_id, teacher_user_id),
            KEY idx_teacher (teacher_user_id),
            CONSTRAINT fk_tr_student FOREIGN KEY (student_user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_tr_teacher FOREIGN KEY (teacher_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teacher_review_ratings (
            id INT NOT NULL AUTO_INCREMENT,
            review_id INT NOT NULL,
            criteria_id INT NOT NULL,
            rating TINYINT(1) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_review_criteria (review_id, criteria_id),
            KEY idx_criteria (criteria_id),
            CONSTRAINT fk_trr_review FOREIGN KEY (review_id) REFERENCES teacher_reviews(id) ON DELETE CASCADE,
            CONSTRAINT fk_trr_criteria FOREIGN KEY (criteria_id) REFERENCES review_criteria(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Seed the original 5 criteria on a fresh install
    $criteria_count = (int)$pdo->query("SELECT COUNT(*) FROM review_criteria")->fetchColumn();
    if ($criteria_count === 0) {
        $defaults = [
            'Clarity of Explanation',
            'Subject Knowledge',
            'Engagement & Interaction',
            'Helpfulness & Support',
            'Quality of Feedback on Assignments',
        ];
        $ins = $pdo->prepare("INSERT INTO review_criteria (label, sort_order) VALUES (?, ?)");
        foreach ($defaults as $i => $label) {
            $ins->execute([$label, $i + 1]);
        }
    }

    // Migrate off the old fixed rating_* columns (first version of this feature)
    // into the new per-criteria rows, then drop them.
    $legacy_col_labels = [
        'rating_clarity' => 'Clarity of Explanation',
        'rating_knowledge' => 'Subject Knowledge',
        'rating_engagement' => 'Engagement & Interaction',
        'rating_helpfulness' => 'Helpfulness & Support',
        'rating_feedback_quality' => 'Quality of Feedback on Assignments',
    ];
    $cols = $pdo->query("SHOW COLUMNS FROM teacher_reviews")->fetchAll(PDO::FETCH_COLUMN);
    $present_legacy_cols = array_intersect(array_keys($legacy_col_labels), $cols);

    if (!empty($present_legacy_cols)) {
        $get_id = $pdo->prepare("SELECT id FROM review_criteria WHERE label = ? LIMIT 1");
        $criteria_ids = [];
        foreach ($present_legacy_cols as $col) {
            $get_id->execute([$legacy_col_labels[$col]]);
            $criteria_ids[$col] = $get_id->fetchColumn();
        }

        $rows = $pdo->query("SELECT id, " . implode(', ', $present_legacy_cols) . " FROM teacher_reviews")->fetchAll(PDO::FETCH_ASSOC);
        $ins_rating = $pdo->prepare("INSERT IGNORE INTO teacher_review_ratings (review_id, criteria_id, rating) VALUES (?, ?, ?)");
        foreach ($rows as $row) {
            foreach ($present_legacy_cols as $col) {
                if ($criteria_ids[$col] && $row[$col] !== null) {
                    $ins_rating->execute([$row['id'], $criteria_ids[$col], $row[$col]]);
                }
            }
        }

        foreach ($present_legacy_cols as $col) {
            try { $pdo->exec("ALTER TABLE teacher_reviews DROP COLUMN $col"); } catch (PDOException $e) {}
        }
    }
}
