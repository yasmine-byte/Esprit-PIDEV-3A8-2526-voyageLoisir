<?php

namespace App\Entity;

use App\Repository\VoyageRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Users;
use App\Entity\Destination;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: VoyageRepository::class)]
class Voyage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: "date", nullable: true)]
    #[Assert\NotNull(message: "La date de depart est obligatoire.")]
    #[Assert\GreaterThanOrEqual("today", message: "La date de depart ne peut pas etre dans le passe.")]
    private ?\DateTimeInterface $date_depart = null;

    #[ORM\Column(type: "date", nullable: true)]
    #[Assert\NotNull(message: "La date d arrivee est obligatoire.")]
    #[Assert\GreaterThan(propertyPath: "date_depart", message: "L arrivee doit etre apres le depart.")]
    private ?\DateTimeInterface $date_arrivee = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(message: "Le point de depart est obligatoire.")]
    #[Assert\Length(min: 2, max: 100, minMessage: "Min 2 caracteres.", maxMessage: "Max 100 caracteres.")]
    #[Assert\Regex(pattern: "/^[^0-9]+$/", message: "Les chiffres ne sont pas autorises.")]
    private ?string $point_depart = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(message: "Le point d arrivee est obligatoire.")]
    #[Assert\Length(min: 2, max: 100, minMessage: "Min 2 caracteres.", maxMessage: "Max 100 caracteres.")]
    #[Assert\Regex(pattern: "/^[^0-9]+$/", message: "Les chiffres ne sont pas autorises.")]
    private ?string $point_arrivee = null;

    #[ORM\Column(type: "float", nullable: true)]
    #[Assert\NotNull(message: "Le prix est obligatoire.")]
    #[Assert\PositiveOrZero(message: "Le prix ne peut pas etre negatif.")]
    #[Assert\LessThanOrEqual(value: 99999, message: "Max 99 999 EUR.")]
    private ?float $prix = null;

    #[ORM\ManyToOne(targetEntity: Destination::class, inversedBy: "voyages")]
    #[ORM\JoinColumn(nullable: true, onDelete: "SET NULL")]
    private ?Destination $destination = null;

    #[ORM\OneToMany(mappedBy: "voyage", targetEntity: Transport::class, cascade: ["persist", "remove"])]
    private Collection $transports;

    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: "SET NULL")]
    private ?Users $created_by = null;

    /**
     * Tous les users qui ont réservé ce voyage (chacun indépendamment).
     */
    #[ORM\ManyToMany(targetEntity: Users::class)]
    #[ORM\JoinTable(name: "voyage_reservations")]
    private Collection $reservedByUsers;

    public function __construct()
    {
        $this->transports      = new ArrayCollection();
        $this->reservedByUsers = new ArrayCollection();
    }

    // ----------------------------------------------------------------
    // Identifiant
    // ----------------------------------------------------------------

    public function getId(): ?int
    {
        return $this->id;
    }

    // ----------------------------------------------------------------
    // Dates
    // ----------------------------------------------------------------

    public function getDateDepart(): ?\DateTimeInterface
    {
        return $this->date_depart;
    }

    public function setDateDepart(?\DateTimeInterface $d): static
    {
        $this->date_depart = $d;
        return $this;
    }

    public function getDateArrivee(): ?\DateTimeInterface
    {
        return $this->date_arrivee;
    }

    public function setDateArrivee(?\DateTimeInterface $d): static
    {
        $this->date_arrivee = $d;
        return $this;
    }

    // ----------------------------------------------------------------
    // Trajet
    // ----------------------------------------------------------------

    public function getPointDepart(): ?string
    {
        return $this->point_depart;
    }

    public function setPointDepart(?string $v): static
    {
        $this->point_depart = $v;
        return $this;
    }

    public function getPointArrivee(): ?string
    {
        return $this->point_arrivee;
    }

    public function setPointArrivee(?string $v): static
    {
        $this->point_arrivee = $v;
        return $this;
    }

    // ----------------------------------------------------------------
    // Prix
    // ----------------------------------------------------------------

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(?float $v): static
    {
        $this->prix = $v;
        return $this;
    }

    // ----------------------------------------------------------------
    // Destination
    // ----------------------------------------------------------------

    public function getDestination(): ?Destination
    {
        return $this->destination;
    }

    public function setDestination(?Destination $destination): static
    {
        $this->destination = $destination;
        return $this;
    }

    // ----------------------------------------------------------------
    // Transports
    // ----------------------------------------------------------------

    public function getTransports(): Collection
    {
        return $this->transports;
    }

    public function addTransport(Transport $transport): static
    {
        if (!$this->transports->contains($transport)) {
            $this->transports->add($transport);
            $transport->setVoyage($this);
        }
        return $this;
    }

    public function removeTransport(Transport $transport): static
    {
        if ($this->transports->removeElement($transport)) {
            if ($transport->getVoyage() === $this) {
                $transport->setVoyage(null);
            }
        }
        return $this;
    }

    // ----------------------------------------------------------------
    // Créateur du voyage
    // ----------------------------------------------------------------

    public function getCreatedBy(): ?Users
    {
        return $this->created_by;
    }

    public function setCreatedBy(?Users $u): static
    {
        $this->created_by = $u;
        return $this;
    }

    // ----------------------------------------------------------------
    // Réservations (ManyToMany — chaque user réserve indépendamment)
    // ----------------------------------------------------------------

    /**
     * Retourne tous les users qui ont réservé ce voyage.
     */
    public function getReservedByUsers(): Collection
    {
        return $this->reservedByUsers;
    }

    /**
     * Ajoute une réservation pour un user (si pas déjà réservé).
     */
    public function addReservation(Users $user): static
    {
        if (!$this->reservedByUsers->contains($user)) {
            $this->reservedByUsers->add($user);
        }
        return $this;
    }

    /**
     * Annule la réservation d'un user.
     */
    public function removeReservation(Users $user): static
    {
        $this->reservedByUsers->removeElement($user);
        return $this;
    }

    /**
     * Vérifie si un user précis a réservé ce voyage.
     * Utiliser dans Twig : voyage.isReservedByUser(app.user)
     */
    public function isReservedByUser(Users $user): bool
    {
        return $this->reservedByUsers->contains($user);
    }

    /**
     * Compatibilité : indique si au moins un user a réservé ce voyage.
     * Utile pour l'affichage admin (colonne "Réservé par").
     */
    public function getReservedBy(): ?Users
    {
        return $this->reservedByUsers->first() ?: null;
    }
    #[ORM\Column(type: "boolean", options: ["default" => false])]
private bool $paid = false;

public function isPaid(): bool { return $this->paid; }
public function setPaid(bool $paid): static { $this->paid = $paid; return $this; }
}