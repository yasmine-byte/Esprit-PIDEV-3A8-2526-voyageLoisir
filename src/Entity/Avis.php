<?php

namespace App\Entity;

use App\Repository\AvisRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AvisRepository::class)]
class Avis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $userId = 0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "Le type est obligatoire")]
    private ?TypeAvis $type = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Le contenu est obligatoire")]
    #[Assert\Length(min: 10, minMessage: "Minimum 10 caracteres")]
    private string $contenu = '';

    #[ORM\Column]
    #[Assert\NotBlank]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: "Entre 1 et 5 etoiles")]
    private int $nbEtoiles = 1;

    #[ORM\Column(length: 20)]
    private string $statut = 'en_attente';

    #[ORM\Column]
    private \DateTimeImmutable $dateAvis;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reponse = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $sentimentLabel = null;

    #[ORM\Column(nullable: true)]
    private ?float $sentimentScore = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'reservation_id', nullable: true)]
    private ?Reservation $reservation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'reservation_activite_id', referencedColumnName: 'id_reservation', nullable: true)]
    private ?ReservationActivite $reservationActivite = null;

    public function __construct()
    {
        $this->dateAvis = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUserId(): int { return $this->userId; }
    public function setUserId(int $userId): static { $this->userId = $userId; return $this; }

    public function getType(): ?TypeAvis { return $this->type; }
    public function setType(?TypeAvis $type): static { $this->type = $type; return $this; }

    public function getContenu(): string { return $this->contenu; }
    public function setContenu(string $contenu): static { $this->contenu = $contenu; return $this; }

    public function getNbEtoiles(): int { return $this->nbEtoiles; }
    public function setNbEtoiles(int $nbEtoiles): static { $this->nbEtoiles = $nbEtoiles; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getDateAvis(): \DateTimeImmutable { return $this->dateAvis; }
    public function setDateAvis(\DateTimeImmutable $dateAvis): static { $this->dateAvis = $dateAvis; return $this; }

    public function getReponse(): ?string { return $this->reponse; }
    public function setReponse(?string $reponse): static { $this->reponse = $reponse; return $this; }

    public function getSentimentLabel(): ?string { return $this->sentimentLabel; }
    public function setSentimentLabel(?string $sentimentLabel): static { $this->sentimentLabel = $sentimentLabel; return $this; }

    public function getSentimentScore(): ?float { return $this->sentimentScore; }
    public function setSentimentScore(?float $sentimentScore): static { $this->sentimentScore = $sentimentScore; return $this; }

    public function getReservation(): ?Reservation { return $this->reservation; }
    public function setReservation(?Reservation $reservation): static { $this->reservation = $reservation; return $this; }

    public function getReservationActivite(): ?ReservationActivite { return $this->reservationActivite; }
    public function setReservationActivite(?ReservationActivite $reservationActivite): static { $this->reservationActivite = $reservationActivite; return $this; }
}