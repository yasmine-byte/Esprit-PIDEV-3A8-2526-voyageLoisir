<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Ignore;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    private ?Hebergement $hebergement = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $clientNom = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $clientTel = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $clientEmail = null;

    // ✅ FIX : DateTimeImmutable
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(nullable: true)]
    private ?int $nbNuits = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $total = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $statut = null;

    // ✅ FIX : DateTimeImmutable non-nullable avec constructeur
    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    // ✅ FIX SECURITY : fcmToken protégé
    #[ORM\Column(length: 1024, nullable: true)]
    #[Ignore]
    private ?string $fcmToken = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getHebergement(): ?Hebergement { return $this->hebergement; }
    public function setHebergement(?Hebergement $hebergement): static { $this->hebergement = $hebergement; return $this; }

    public function getClientNom(): ?string { return $this->clientNom; }
    public function setClientNom(?string $clientNom): static { $this->clientNom = $clientNom; return $this; }

    public function getClientTel(): ?string { return $this->clientTel; }
    public function setClientTel(?string $clientTel): static { $this->clientTel = $clientTel; return $this; }

    public function getClientEmail(): ?string { return $this->clientEmail; }
    public function setClientEmail(?string $clientEmail): static { $this->clientEmail = $clientEmail; return $this; }

    public function getDateDebut(): ?\DateTimeImmutable { return $this->dateDebut; }
    public function setDateDebut(?\DateTimeImmutable $dateDebut): static { $this->dateDebut = $dateDebut; return $this; }

    public function getDateFin(): ?\DateTimeImmutable { return $this->dateFin; }
    public function setDateFin(?\DateTimeImmutable $dateFin): static { $this->dateFin = $dateFin; return $this; }

    public function getNbNuits(): ?int { return $this->nbNuits; }
    public function setNbNuits(?int $nbNuits): static { $this->nbNuits = $nbNuits; return $this; }

    public function getTotal(): ?string { return $this->total; }
    public function setTotal(?string $total): static { $this->total = $total; return $this; }

    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(?string $statut): static { $this->statut = $statut; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getFcmToken(): ?string { return $this->fcmToken; }
    public function setFcmToken(#[\SensitiveParameter] ?string $fcmToken): static { $this->fcmToken = $fcmToken; return $this; }
}