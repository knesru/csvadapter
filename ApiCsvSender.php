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
