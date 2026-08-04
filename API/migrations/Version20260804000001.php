<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create workout template tables (workout_templates, template_exercises)';
    }

    public function up(Schema $schema): void
    {
        // No FKs (kit rule): template_exercises reference their template and
        // catalog exercise by plain UUID columns; cascade/SET NULL semantics
        // are enforced in repositories and handlers.
        $this->addSql('CREATE TABLE workout_templates (
            id UUID NOT NULL,
            name VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            archived_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_workout_templates_sort_order ON workout_templates (sort_order)');
        $this->addSql('CREATE INDEX idx_workout_templates_archived_at ON workout_templates (archived_at)');

        $this->addSql('CREATE TABLE template_exercises (
            id UUID NOT NULL,
            template_id UUID NOT NULL,
            exercise_id UUID NOT NULL,
            sort_order INT NOT NULL,
            target_sets INT DEFAULT NULL,
            target_reps INT DEFAULT NULL,
            rest_seconds INT DEFAULT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_template_exercises_template_id ON template_exercises (template_id)');
        $this->addSql('CREATE INDEX idx_template_exercises_exercise_id ON template_exercises (exercise_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE template_exercises');
        $this->addSql('DROP TABLE workout_templates');
    }
}
