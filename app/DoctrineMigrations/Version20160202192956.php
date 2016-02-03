<?php

namespace Application\Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160202192956 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE period DROP FOREIGN KEY FK_C5B81ECEBF396750');
        $this->addSql('ALTER TABLE period ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE period ADD CONSTRAINT FK_C5B81ECEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('CREATE INDEX IDX_C5B81ECEA76ED395 ON period (user_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() != 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE period DROP FOREIGN KEY FK_C5B81ECEA76ED395');
        $this->addSql('DROP INDEX IDX_C5B81ECEA76ED395 ON period');
        $this->addSql('ALTER TABLE period DROP user_id');
        $this->addSql('ALTER TABLE period ADD CONSTRAINT FK_C5B81ECEBF396750 FOREIGN KEY (id) REFERENCES user (id)');
    }
}
