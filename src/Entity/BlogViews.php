<?php

namespace App\Entity;

use App\Repository\BlogViewsRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BlogViewsRepository::class)]
class BlogViews
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Blog $blog = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userIdentifier = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $viewDate = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBlog(): ?Blog
    {
        return $this->blog;
    }

    public function setBlog(?Blog $blog): static
    {
        $this->blog = $blog;

        return $this;
    }

    public function getUserIdentifier(): ?string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(?string $userIdentifier): static
    {
        $this->userIdentifier = $userIdentifier;

        return $this;
    }

    public function getViewDate(): ?\DateTime
    {
        return $this->viewDate;
    }

    public function setViewDate(?\DateTime $viewDate): static
    {
        $this->viewDate = $viewDate;

        return $this;
    }
}
