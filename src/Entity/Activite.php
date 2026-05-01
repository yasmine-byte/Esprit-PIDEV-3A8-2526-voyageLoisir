<?php

namespace App\Entity;

use App\Repository\ActiviteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ActiviteRepository::class)]
#[ORM\Table(name: 'activite')]
class Activite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    // ✅ FIX : renommage id_activite → activite_id (convention Doctrine _id suffix)
    #[ORM\Column(name: 'id_activite')]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le nom est obligatoire.")]
    #[Assert\Length(min: 3, max: 100)]
    private string $nom = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\NotBlank(message: "La description est obligatoire.")]
    #[Assert\Length(min: 10)]
    private ?string $description = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\NotBlank(message: "Le type est obligatoire.")]
    #[Assert\Length(min: 3, max: 50)]
    private ?string $type = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotNull(message: "Le prix est obligatoire.")]
    #[Assert\Positive(message: "Le prix doit être supérieur à 0.")]
    private ?float $prix = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotNull(message: "La durée est obligatoire.")]
    #[Assert\Positive(message: "La durée doit être supérieure à 0.")]
    private ?int $duree = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(message: "Le lieu est obligatoire.")]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $lieu = null;

    #[ORM\Column(name: 'image_url', length: 255, nullable: true)]
    #[Assert\Url(message: "L'image doit être une URL valide.")]
    #[Assert\Length(max: 255)]
    private ?string $imageUrl = null;

    #[ORM\Column(name: 'ai_rating', nullable: true)]
    #[Assert\PositiveOrZero(message: "La note AI doit être positive ou nulle.")]
    private ?float $aiRating = null;

    // ✅ FIX : ajout de cascade="persist" pour aligner avec orphanRemoval=true
    #[ORM\OneToMany(mappedBy: 'activite', targetEntity: ReservationActivite::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $reservations;

    public function __construct()
    {
        $this->reservations = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getType(): ?string { return $this->type; }
    public function setType(?string $type): static { $this->type = $type; return $this; }

    public function getPrix(): ?float { return $this->prix; }
    public function setPrix(?float $prix): static { $this->prix = $prix; return $this; }

    public function getDuree(): ?int { return $this->duree; }
    public function setDuree(?int $duree): static { $this->duree = $duree; return $this; }

    public function getLieu(): ?string { return $this->lieu; }
    public function setLieu(?string $lieu): static { $this->lieu = $lieu; return $this; }

    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): static { $this->imageUrl = $imageUrl; return $this; }

    public function getAiRating(): ?float { return $this->aiRating; }
    public function setAiRating(?float $aiRating): static { $this->aiRating = $aiRating; return $this; }

    public function getReservations(): Collection { return $this->reservations; }

    public function addReservation(ReservationActivite $reservation): static
    {
        if (!$this->reservations->contains($reservation)) {
            $this->reservations->add($reservation);
            $reservation->setActivite($this);
        }
        return $this;
    }

    public function removeReservation(ReservationActivite $reservation): static
    {
        if ($this->reservations->removeElement($reservation)) {
            if ($reservation->getActivite() === $this) {
                $reservation->setActivite(null);
            }
        }
        return $this;
    }
}   