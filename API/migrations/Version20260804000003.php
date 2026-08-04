<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260804000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create measurements table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE measurements (
            id UUID NOT NULL,
            type VARCHAR(20) NOT NULL,
            value DOUBLE PRECISION NOT NULL,
            measured_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX idx_measurements_type_measured_at ON measurements (type, measured_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE measurements');
    }
}
