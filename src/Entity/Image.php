<?php

namespace App\Entity;

use App\Repository\ImageRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ImageRepository::class)]
class Image
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $url_image = null;

    #[ORM\ManyToOne]
    #[Assert\NotNull(message: "La destination est obligatoire.")]
    private ?Destination $destination = null;

    public function getId(): ?int { return $this->id; }
    public function getUrlImage(): ?string { return $this->url_image; }
    public function setUrlImage(string $url_image): static { $this->url_image = $url_image; return $this; }
    public function getDestination(): ?Destination { return $this->destination; }
    public function setDestination(?Destination $destination): static { $this->destination = $destination; return $this; }
}
