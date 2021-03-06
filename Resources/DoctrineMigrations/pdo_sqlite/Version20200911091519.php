<?php declare(strict_types=1);

namespace Application\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200911091519 extends AbstractMigration
{
    public function up(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');
        $this->addSql('ALTER TABLE form_submission ADD label VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE form_submission ADD deadline_date VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema) : void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'sqlite', 'Migration can only be executed safely on \'sqlite\'.');
        $this->addSql('ALTER TABLE form_submission DROP label');
        $this->addSql('ALTER TABLE form_submission DROP deadline_date');
    }
}
