<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261120173234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE cita (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, inicio DATETIME NOT NULL, fin DATETIME NOT NULL, notas CLOB DEFAULT NULL, creada_en DATETIME NOT NULL, usuario_id INTEGER NOT NULL, CONSTRAINT FK_3E379A62DB38439E FOREIGN KEY (usuario_id) REFERENCES user (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CITA_USUARIO_INICIO ON cita (usuario_id, inicio)');
        $this->addSql('CREATE INDEX IDX_3E379A62DB38439E ON cita (usuario_id)');
        $this->addSql('CREATE TABLE user (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL ON user (email)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE cita');
        $this->addSql('DROP TABLE user');
    }
}
