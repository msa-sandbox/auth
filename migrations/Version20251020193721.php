<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251020193721 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create crm_refresh_token table for storing CRM refresh JWT metadata';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE crm_refresh_token 
            (
                id VARCHAR(36) NOT NULL,
                user_id INT NOT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                revoked TINYINT(1) NOT NULL, INDEX idx_crm_refresh_user (user_id),
                INDEX idx_crm_refresh_expires (expires_at),
                PRIMARY KEY(id)
            )
                DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE crm_refresh_token ADD CONSTRAINT FK_B6C244DEA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE crm_refresh_token DROP FOREIGN KEY FK_B6C244DEA76ED395');
        $this->addSql('DROP TABLE crm_refresh_token');
    }
}
