<?php

namespace App\Entity;

use App\Repository\VoyageRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Users;
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
    private ?string $point_depart = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(message: "Le point d arrivee est obligatoire.")]
    #[Assert\Length(min: 2, max: 100, minMessage: "Min 2 caracteres.", maxMessage: "Max 100 caracteres.")]
    private ?string $point_arrivee = null;

    #[ORM\Column(type: "float", nullable: true)]
    #[Assert\NotNull(message: "Le prix est obligatoire.")]
    #[Assert\PositiveOrZero(message: "Le prix ne peut pas etre negatif.")]
    #[Assert\LessThanOrEqual(value: 99999, message: "Max 99 999 EUR.")]
    private ?float $prix = null;

    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: "SET NULL")]
    private ?Users $created_by = null;

    #[ORM\ManyToOne(targetEntity: Users::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: "SET NULL")]
    private ?Users $reserved_by = null;

    public function getId(): ?int { return $this->id; }
    public function getDateDepart(): ?\DateTimeInterface { return $this->date_depart; }
    public function setDateDepart(?\DateTimeInterface $d): static { $this->date_depart = $d; return $this; }
    public function getDateArrivee(): ?\DateTimeInterface { return $this->date_arrivee; }
    public function setDateArrivee(?\DateTimeInterface $d): static { $this->date_arrivee = $d; return $this; }
    public function getPointDepart(): ?string { return $this->point_depart; }
    public function setPointDepart(?string $v): static { $this->point_depart = $v; return $this; }
    public function getPointArrivee(): ?string { return $this->point_arrivee; }
    public function setPointArrivee(?string $v): static { $this->point_arrivee = $v; return $this; }
    public function getPrix(): ?float { return $this->prix; }
    public function setPrix(?float $v): static { $this->prix = $v; return $this; }

    public function getCreatedBy(): ?Users { return $this->created_by; }
    public function setCreatedBy(?Users $u): static { $this->created_by = $u; return $this; }

    public function getReservedBy(): ?Users { return $this->reserved_by; }
    public function setReservedBy(?Users $u): static { $this->reserved_by = $u; return $this; }
}
