<?php

namespace App\Entity;

use App\Repository\ReservationActiviteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Users;

#[ORM\Entity(repositoryClass: ReservationActiviteRepository::class)]
class ReservationActivite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_reservation')]
    private ?int $id = null;

    // ✅ FIX : DateTimeImmutable non-nullable
    #[ORM\Column(name: 'date_reservation', type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: "La date de réservation est obligatoire.")]
    #[Assert\GreaterThanOrEqual(
        value: "today",
        message: "La date de réservation ne peut pas être dans le passé."
    )]
    private \DateTimeImmutable $dateReservation;

    #[ORM\Column(name: 'nombre_personnes')]
    #[Assert\NotNull(message: "Le nombre de personnes est obligatoire.")]
    #[Assert\Positive(message: "Le nombre de personnes doit être supérieur à 0.")]
    private int $nombrePersonnes = 1;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank(message: "Le statut est obligatoire.")]
    #[Assert\Choice(
        choices: ['EN_ATTENTE', 'CONFIRMEE', 'ANNULEE'],
        message: "Le statut doit être EN_ATTENTE, CONFIRMEE ou ANNULEE."
    )]
    private string $statut = 'EN_ATTENTE';

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero(message: "Le total doit être positif ou nul.")]
    private string $total = '0.00';

    // ✅ FIX FK : name garde 'id_activite' pour la compatibilité BDD existante
    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(name: 'activite_id', referencedColumnName: 'id_activite', nullable: false)]
    #[Assert\NotNull(message: "L'activité est obligatoire.")]
    private ?Activite $activite = null;

    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Users $user = null;

    public function __construct()
    {
        $this->dateReservation = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getDateReservation(): \DateTimeImmutable { return $this->dateReservation; }
    public function setDateReservation(\DateTimeImmutable $dateReservation): static { $this->dateReservation = $dateReservation; return $this; }

    public function getNombrePersonnes(): int { return $this->nombrePersonnes; }
    public function setNombrePersonnes(int $nombrePersonnes): static { $this->nombrePersonnes = $nombrePersonnes; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getTotal(): string { return $this->total; }
    public function setTotal(string $total): static { $this->total = $total; return $this; }

    public function getActivite(): ?Activite { return $this->activite; }
    public function setActivite(?Activite $activite): static { $this->activite = $activite; return $this; }

    public function getUser(): ?Users { return $this->user; }
    public function setUser(?Users $user): static { $this->user = $user; return $this; }
}