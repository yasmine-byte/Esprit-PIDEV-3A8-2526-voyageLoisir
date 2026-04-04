<?php

namespace App\Entity;

use App\Repository\TransportRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TransportRepository::class)]
class Transport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(message: "Le type de transport est obligatoire.")]
    #[Assert\Choice(choices: ["Avion", "Bus", "Voiture", "Train"], message: "Type de transport invalide.")]
    private ?string $type_transport = null;

    #[ORM\ManyToOne(inversedBy: "transports")]
    #[Assert\NotNull(message: "La destination est obligatoire.")]
    private ?Destination $destination = null;

    public function getId(): ?int { return $this->id; }
    public function getTypeTransport(): ?string { return $this->type_transport; }
    public function setTypeTransport(?string $type_transport): static { $this->type_transport = $type_transport; return $this; }
    public function getDestination(): ?Destination { return $this->destination; }
    public function setDestination(?Destination $destination): static { $this->destination = $destination; return $this; }
}
