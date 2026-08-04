<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create profiles table (singleton row, id = 1)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE profiles (
            id INT NOT NULL,
            sex VARCHAR(10) DEFAULT NULL,
            birth_date DATE DEFAULT NULL,
            default_rest_seconds INT NOT NULL DEFAULT 120,
            height_cm DOUBLE PRECISION DEFAULT NULL,
            PRIMARY KEY(id)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE profiles');
    }
}
