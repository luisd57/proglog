<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create session tables (sessions, session_exercises, set_logs)';
    }

    public function up(Schema $schema): void
    {
        // Children reference their parents by plain UUID columns (no ORM
        // relations). Old Prisma semantics are enforced in repositories/handlers:
        // deleting a session cascades to its exercises and sets; deleting a
        // template sets sessions.template_id to NULL.
        // The matching FK constraints are added in Version20260805000000 - this
        // migration predates them and is left as it ran.
        $this->addSql('CREATE TABLE sessions (
            id UUID NOT NULL,
            template_id UUID DEFAULT NULL,
            started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_sessions_template_id ON sessions (template_id)');
        $this->addSql('CREATE INDEX idx_sessions_started_at ON sessions (started_at)');
        $this->addSql('CREATE INDEX idx_sessions_finished_at ON sessions (finished_at)');

        $this->addSql('CREATE TABLE session_exercises (
            id UUID NOT NULL,
            session_id UUID NOT NULL,
            exercise_id UUID NOT NULL,
            sort_order INT NOT NULL,
            notes TEXT DEFAULT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_session_exercises_session_id ON session_exercises (session_id)');
        $this->addSql('CREATE INDEX idx_session_exercises_exercise_id ON session_exercises (exercise_id)');

        $this->addSql('CREATE TABLE set_logs (
            id UUID NOT NULL,
            session_exercise_id UUID NOT NULL,
            set_number INT NOT NULL,
            weight_kg DOUBLE PRECISION NOT NULL,
            reps INT NOT NULL,
            is_warmup BOOLEAN NOT NULL DEFAULT FALSE,
            notes TEXT DEFAULT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_set_logs_session_exercise_id ON set_logs (session_exercise_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE set_logs');
        $this->addSql('DROP TABLE session_exercises');
        $this->addSql('DROP TABLE sessions');
    }
}
