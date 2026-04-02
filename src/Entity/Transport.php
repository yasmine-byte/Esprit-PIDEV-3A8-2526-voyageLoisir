<?php

namespace App\Entity;

use App\Repository\TransportRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransportRepository::class)]
class Transport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $type_transport = null;

    #[ORM\ManyToOne]
    private ?Destination $destination = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypeTransport(): ?string
    {
        return $this->type_transport;
    }

    public function setTypeTransport(?string $type_transport): static
    {
        $this->type_transport = $type_transport;

        return $this;
    }

    public function getDestination(): ?Destination
    {
        return $this->destination;
    }

    public function setDestination(?Destination $destination): static
    {
        $this->destination = $destination;

        return $this;
    }
}
