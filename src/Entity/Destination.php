<?php

namespace App\Entity;

use App\Repository\DestinationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DestinationRepository::class)]
class Destination
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le nom est obligatoire.")]
    #[Assert\Length(min: 2, max: 100, minMessage: "Min 2 caracteres.", maxMessage: "Max 100 caracteres.")]
    #[Assert\Regex(pattern: "/^[^0-9]+$/", message: "Les chiffres ne sont pas autorises.")]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "Le pays est obligatoire.")]
    #[Assert\Length(min: 2, max: 100, minMessage: "Min 2 caracteres.", maxMessage: "Max 100 caracteres.")]
    #[Assert\Regex(pattern: "/^[^0-9]+$/", message: "Les chiffres ne sont pas autorises.")]
    private ?string $pays = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(min: 20, max: 2000, minMessage: "Min 20 caracteres.", maxMessage: "Max 2000 caracteres.")]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?bool $statut = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\NotBlank(message: "La saison est obligatoire.")]
    #[Assert\Choice(choices: ["Printemps", "Ete", "Automne", "Hiver"], message: "Saison invalide.")]
    private ?string $meilleure_saison = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotNull(message: "La latitude est obligatoire.")]
    #[Assert\Range(min: -90, max: 90, notInRangeMessage: "Entre -90 et 90.")]
    private ?float $latitude = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotNull(message: "La longitude est obligatoire.")]
    #[Assert\Range(min: -180, max: 180, notInRangeMessage: "Entre -180 et 180.")]
    private ?float $longitude = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotNull(message: "Le nombre de visites est obligatoire.")]
    #[Assert\PositiveOrZero(message: "Doit etre zero ou positif.")]
    private ?int $nb_visites = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $video_path = null;

    #[ORM\ManyToOne]
    #[Assert\NotNull(message: "Le voyage est obligatoire.")]
    private ?Voyage $voyage = null;

    #[ORM\OneToMany(mappedBy: "destination", targetEntity: Image::class, cascade: ["persist", "remove"])]
    private Collection $images;

    #[ORM\OneToMany(mappedBy: "destination", targetEntity: Transport::class, cascade: ["persist", "remove"])]
    private Collection $transports;

    public function __construct()
    {
        $this->images     = new ArrayCollection();
        $this->transports = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }
    public function getPays(): ?string { return $this->pays; }
    public function setPays(string $pays): static { $this->pays = $pays; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function isStatut(): ?bool { return $this->statut; }
    public function setStatut(?bool $statut): static { $this->statut = $statut; return $this; }
    public function getMeilleureSaison(): ?string { return $this->meilleure_saison; }
    public function setMeilleureSaison(?string $v): static { $this->meilleure_saison = $v; return $this; }
    public function getLatitude(): ?float { return $this->latitude; }
    public function setLatitude(?float $latitude): static { $this->latitude = $latitude; return $this; }
    public function getLongitude(): ?float { return $this->longitude; }
    public function setLongitude(?float $longitude): static { $this->longitude = $longitude; return $this; }
    public function getNbVisites(): ?int { return $this->nb_visites; }
    public function setNbVisites(?int $nb_visites): static { $this->nb_visites = $nb_visites; return $this; }
    public function getVideoPath(): ?string { return $this->video_path; }
    public function setVideoPath(?string $video_path): static { $this->video_path = $video_path; return $this; }
    public function getVoyage(): ?Voyage { return $this->voyage; }
    public function setVoyage(?Voyage $voyage): static { $this->voyage = $voyage; return $this; }

    public function getImages(): Collection { return $this->images; }
    public function addImage(Image $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setDestination($this);
        }
        return $this;
    }
    public function removeImage(Image $image): static
    {
        if ($this->images->removeElement($image)) {
            if ($image->getDestination() === $this) $image->setDestination(null);
        }
        return $this;
    }

    public function getTransports(): Collection { return $this->transports; }
    public function addTransport(Transport $transport): static
    {
        if (!$this->transports->contains($transport)) {
            $this->transports->add($transport);
            $transport->setDestination($this);
        }
        return $this;
    }
    public function removeTransport(Transport $transport): static
    {
        if ($this->transports->removeElement($transport)) {
            if ($transport->getDestination() === $this) $transport->setDestination(null);
        }
        return $this;
    }
}
