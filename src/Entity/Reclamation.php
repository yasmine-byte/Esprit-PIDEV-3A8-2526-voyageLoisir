<?php

namespace App\Entity;

use App\Repository\ReclamationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReclamationRepository::class)]
class Reclamation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $userId = 0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?TypeAvis $type = null;

    #[ORM\ManyToOne]
    private ?Avis $avis = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire")]
    #[Assert\Length(min: 5, max: 255, minMessage: "Minimum 5 caracteres", maxMessage: "Maximum 255 caracteres")]
    private string $titre = '';

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Le contenu est obligatoire")]
    #[Assert\Length(min: 10, minMessage: "Minimum 10 caracteres")]
    private string $contenu = '';

    #[ORM\Column(length: 20)]
    private string $typeFeedback = '';

    #[ORM\Column(length: 20)]
    private string $statut = 'en_attente';

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: "La priorite est obligatoire")]
    #[Assert\Choice(choices: ['Basse', 'Moyenne', 'Haute', 'Urgente'])]
    private string $priorite = 'Moyenne';

    #[ORM\Column]
    private \DateTimeImmutable $dateCreation;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reponse = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 8, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 11, scale: 8, nullable: true)]
    private ?string $longitude = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'reservation_id', nullable: true)]
    private ?Reservation $reservation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'reservation_activite_id', referencedColumnName: 'id_reservation', nullable: true)]
    private ?ReservationActivite $reservationActivite = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUserId(): int { return $this->userId; }
    public function setUserId(int $userId): static { $this->userId = $userId; return $this; }

    public function getType(): ?TypeAvis { return $this->type; }
    public function setType(?TypeAvis $type): static { $this->type = $type; return $this; }

    public function getAvis(): ?Avis { return $this->avis; }
    public function setAvis(?Avis $avis): static { $this->avis = $avis; return $this; }

    public function getTitre(): string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }

    public function getContenu(): string { return $this->contenu; }
    public function setContenu(string $contenu): static { $this->contenu = $contenu; return $this; }

    public function getTypeFeedback(): string { return $this->typeFeedback; }
    public function setTypeFeedback(string $typeFeedback): static { $this->typeFeedback = $typeFeedback; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getPriorite(): string { return $this->priorite; }
    public function setPriorite(string $priorite): static { $this->priorite = $priorite; return $this; }

    public function getDateCreation(): \DateTimeImmutable { return $this->dateCreation; }
    public function setDateCreation(\DateTimeImmutable $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }

    public function getReponse(): ?string { return $this->reponse; }
    public function setReponse(?string $reponse): static { $this->reponse = $reponse; return $this; }

    public function getLatitude(): ?string { return $this->latitude; }
    public function setLatitude(?string $latitude): static { $this->latitude = $latitude; return $this; }

    public function getLongitude(): ?string { return $this->longitude; }
    public function setLongitude(?string $longitude): static { $this->longitude = $longitude; return $this; }

    public function getReservation(): ?Reservation { return $this->reservation; }
    public function setReservation(?Reservation $reservation): static { $this->reservation = $reservation; return $this; }

    public function getReservationActivite(): ?ReservationActivite { return $this->reservationActivite; }
    public function setReservationActivite(?ReservationActivite $reservationActivite): static { $this->reservationActivite = $reservationActivite; return $this; }
}