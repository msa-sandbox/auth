<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251020191903 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create crm_exchange_token table for CRM API exchange tokens';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE crm_exchange_token 
            (
                id VARCHAR(36) NOT NULL,
                user_id INT NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
                INDEX IDX_A07FF80EA76ED395 (user_id), 
                INDEX idx_token_hash (token_hash),
                INDEX idx_expires_at (expires_at),
                PRIMARY KEY(id)
            )
                DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE crm_exchange_token ADD CONSTRAINT FK_A07FF80EA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE crm_exchange_token DROP FOREIGN KEY FK_A07FF80EA76ED395');
        $this->addSql('DROP TABLE crm_exchange_token');
    }
}
