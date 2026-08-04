<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260805000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add foreign key constraints matching the cascade behaviour in the repositories/handlers';
    }

    public function up(Schema $schema): void
    {
        // Referential integrity belongs in the database. The repositories keep
        // their hand-rolled cascades (they own the identity map); these are the
        // backstop for the rows those cascades miss. No ORM relations: entities
        // still reference other aggregates by ID value objects.

        // Deleting a template deletes its lines (DoctrineWorkoutTemplateRepository::delete).
        $this->addSql('ALTER TABLE template_exercises
            ADD CONSTRAINT fk_template_exercises_template_id
            FOREIGN KEY (template_id) REFERENCES workout_templates (id) ON DELETE CASCADE');

        // An exercise in use may not be deleted (DeleteExerciseHandler guards this).
        $this->addSql('ALTER TABLE template_exercises
            ADD CONSTRAINT fk_template_exercises_exercise_id
            FOREIGN KEY (exercise_id) REFERENCES exercises (id) ON DELETE RESTRICT');

        // Deleting a session deletes its exercises (DoctrineSessionRepository::delete).
        $this->addSql('ALTER TABLE session_exercises
            ADD CONSTRAINT fk_session_exercises_session_id
            FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE session_exercises
            ADD CONSTRAINT fk_session_exercises_exercise_id
            FOREIGN KEY (exercise_id) REFERENCES exercises (id) ON DELETE RESTRICT');

        // Deleting a session exercise deletes its sets (removeExerciseWithSets).
        $this->addSql('ALTER TABLE set_logs
            ADD CONSTRAINT fk_set_logs_session_exercise_id
            FOREIGN KEY (session_exercise_id) REFERENCES session_exercises (id) ON DELETE CASCADE');

        // Deleting a template detaches its sessions (DeleteTemplateHandler).
        $this->addSql('ALTER TABLE sessions
            ADD CONSTRAINT fk_sessions_template_id
            FOREIGN KEY (template_id) REFERENCES workout_templates (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sessions DROP CONSTRAINT fk_sessions_template_id');
        $this->addSql('ALTER TABLE set_logs DROP CONSTRAINT fk_set_logs_session_exercise_id');
        $this->addSql('ALTER TABLE session_exercises DROP CONSTRAINT fk_session_exercises_exercise_id');
        $this->addSql('ALTER TABLE session_exercises DROP CONSTRAINT fk_session_exercises_session_id');
        $this->addSql('ALTER TABLE template_exercises DROP CONSTRAINT fk_template_exercises_exercise_id');
        $this->addSql('ALTER TABLE template_exercises DROP CONSTRAINT fk_template_exercises_template_id');
    }
}
