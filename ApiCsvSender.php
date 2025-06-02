<?php

declare(strict_types=1);

namespace App\Infrastructure\Entity;

use App\Domain\Model\Application;
use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Mapping\Table;

#[Entity]
#[Table(name: 'applications')]
class ApplicationEntity
{
    #[Id]
    #[Column(type: 'integer')]
    private int $id;

    #[Column(type: 'string', length: 20)]
    private string $status;

    #[Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    #[Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[Column(type: 'integer')]
    private int $retryCount = 0;

    public function __construct(Application $application)
    {
        $this->id = $application->getId();
        $this->status = $application->getStatus();
        $this->createdAt = $application->getCreatedAt();
        $this->updatedAt = $application->getUpdatedAt();
        $this->errorMessage = $application->getErrorMessage();
        $this->retryCount = $application->getRetryCount();
    }

    public function toDomain(): Application
    {
        return new Application(
            $this->id,
            $this->status,
            $this->createdAt,
            $this->updatedAt,
            $this->errorMessage,
            $this->retryCount
        );
    }

    public function updateFromDomain(Application $application): void
    {
        $this->status = $application->getStatus();
        $this->updatedAt = $application->getUpdatedAt();
        $this->errorMessage = $application->getErrorMessage();
        $this->retryCount = $application->getRetryCount();
    }
} 

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Version\MigrationFactory;

#[MigrationFactory]
final class Version20240320000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create applications table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE applications (
            id INT NOT NULL,
            status VARCHAR(20) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            error_message TEXT DEFAULT NULL,
            retry_count INT NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        )');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE applications');
    }
} 
===
        "symfony/dotenv": "7.2.*",
        "symfony/flex": "^2",
        "symfony/framework-bundle": "7.2.*",
        "symfony/runtime": "7.2.*",
        "symfony/yaml": "7.2.*",
        "php-amqplib/php-amqplib": "^3.6",
        "doctrine/orm": "^2.17",
        "symfony/http-client": "7.2.*",
        "symfony/monolog-bundle": "^3.10"
    },
    "require-dev": {
        "symfony/maker-bundle": "^1.52",
        "symfony/phpunit-bridge": "^7.2"
    },
    "config": {
        "allow-plugins": {
            "php-http/discovery": true,
            "symfony/flex": true,
            "symfony/runtime": true
        },
        "bump-after-update": true,
        "sort-packages": true
    },
