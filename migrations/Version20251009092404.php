<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251009092404 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE organizer ADD file_name VARCHAR(255) DEFAULT NULL, DROP logo, CHANGE org_name org_name VARCHAR(255) NOT NULL, CHANGE contact contact VARCHAR(100) NOT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE date_created date_created DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE organizer ADD logo VARCHAR(255) NOT NULL, DROP file_name, CHANGE org_name org_name VARCHAR(100) NOT NULL, CHANGE contact contact VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(50) NOT NULL, CHANGE description description VARCHAR(255) NOT NULL, CHANGE date_created date_created DATETIME NOT NULL');
    }
}
