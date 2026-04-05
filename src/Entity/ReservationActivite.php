<?php

namespace App\Entity;

use App\Repository\ReservationActiviteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReservationActiviteRepository::class)]
class ReservationActivite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_reservation')]
    private ?int $id = null;

    #[ORM\Column(name: 'date_reservation', type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: "La date de réservation est obligatoire.")]
    #[Assert\GreaterThanOrEqual(
        value: "today",
        message: "La date de réservation ne peut pas être dans le passé."
    )]
    private ?\DateTime $dateReservation = null;

    #[ORM\Column(name: 'nombre_personnes')]
    #[Assert\NotNull(message: "Le nombre de personnes est obligatoire.")]
    #[Assert\Type(type: 'integer', message: "Le nombre de personnes doit être un entier.")]
    #[Assert\Positive(message: "Le nombre de personnes doit être supérieur à 0.")]
    private ?int $nombrePersonnes = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank(message: "Le statut est obligatoire.")]
    #[Assert\Choice(
        choices: ['EN_ATTENTE', 'CONFIRMEE', 'ANNULEE'],
        message: "Le statut doit être EN_ATTENTE, CONFIRMEE ou ANNULEE."
    )]
    private ?string $statut = null;

    #[ORM\Column]
    #[Assert\Type(type: 'numeric', message: "Le total doit être un nombre.")]
    #[Assert\PositiveOrZero(message: "Le total doit être positif ou nul.")]
    private ?float $total = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(name: 'id_activite', referencedColumnName: 'id_activite', nullable: false)]
    #[Assert\NotNull(message: "L'activité est obligatoire.")]
    private ?Activite $activite = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateReservation(): ?\DateTime
    {
        return $this->dateReservation;
    }

    public function setDateReservation(\DateTime $dateReservation): static
    {
        $this->dateReservation = $dateReservation;
        return $this;
    }

    public function getNombrePersonnes(): ?int
    {
        return $this->nombrePersonnes;
    }

    public function setNombrePersonnes(int $nombrePersonnes): static
    {
        $this->nombrePersonnes = $nombrePersonnes;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getTotal(): ?float
    {
        return $this->total;
    }

    public function setTotal(float $total): static
    {
        $this->total = $total;
        return $this;
    }

    public function getActivite(): ?Activite
    {
        return $this->activite;
    }

    public function setActivite(?Activite $activite): static
    {
        $this->activite = $activite;
        return $this;
    }
}