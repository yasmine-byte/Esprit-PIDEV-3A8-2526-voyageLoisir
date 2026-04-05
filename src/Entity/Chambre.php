<?php

namespace App\Entity;

use App\Repository\ChambreRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ChambreRepository::class)]
class Chambre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'no')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: "L'hébergement est obligatoire.")]
    private ?Hebergement $hebergement = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\NotBlank(message: "Le numéro de chambre est obligatoire.")]
    #[Assert\Length(max: 10, maxMessage: "Le numéro ne peut pas dépasser {{ limit }} caractères.")]
    private ?string $numero = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\NotBlank(message: "Le type de chambre est obligatoire.")]
    #[Assert\Choice(
        choices: ['simple', 'double', 'suite', 'familiale'],
        message: "Le type doit être : simple, double, suite ou familiale."
    )]
    private ?string $typeChambre = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\NotBlank(message: "Le prix par nuit est obligatoire.")]
    #[Assert\Positive(message: "Le prix doit être un nombre positif.")]
    private ?string $prixNuit = null;

    #[ORM\Column(nullable: true)]
    #[Assert\NotBlank(message: "La capacité est obligatoire.")]
    #[Assert\Positive(message: "La capacité doit être un nombre positif.")]
    #[Assert\LessThanOrEqual(value: 20, message: "La capacité ne peut pas dépasser {{ compared_value }} personnes.")]
    private ?int $capacite = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\NotBlank(message: "Les équipements sont obligatoires.")]
    private ?string $equipements = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getHebergement(): ?Hebergement
    {
        return $this->hebergement;
    }

    public function setHebergement(?Hebergement $hebergement): static
    {
        $this->hebergement = $hebergement;
        return $this;
    }

    public function getNumero(): ?string
    {
        return $this->numero;
    }

    public function setNumero(?string $numero): static
    {
        $this->numero = $numero;
        return $this;
    }

    public function getTypeChambre(): ?string
    {
        return $this->typeChambre;
    }

    public function setTypeChambre(?string $typeChambre): static
    {
        $this->typeChambre = $typeChambre;
        return $this;
    }

    public function getPrixNuit(): ?string
    {
        return $this->prixNuit;
    }

    public function setPrixNuit(?string $prixNuit): static
    {
        $this->prixNuit = $prixNuit;
        return $this;
    }

    public function getCapacite(): ?int
    {
        return $this->capacite;
    }

    public function setCapacite(?int $capacite): static
    {
        $this->capacite = $capacite;
        return $this;
    }

    public function getEquipements(): ?string
    {
        return $this->equipements;
    }

    public function setEquipements(?string $equipements): static
    {
        $this->equipements = $equipements;
        return $this;
    }
}