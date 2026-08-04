<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create exercises table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE exercises (
            id UUID NOT NULL,
            name VARCHAR(255) NOT NULL,
            primary_muscles JSON NOT NULL,
            secondary_muscles JSON NOT NULL,
            equipment VARCHAR(100) DEFAULT NULL,
            category VARCHAR(100) DEFAULT NULL,
            instructions TEXT DEFAULT NULL,
            is_custom BOOLEAN NOT NULL DEFAULT FALSE,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_exercises_name ON exercises (name)');
        $this->addSql('CREATE INDEX idx_exercises_is_custom ON exercises (is_custom)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE exercises');
    }
}
