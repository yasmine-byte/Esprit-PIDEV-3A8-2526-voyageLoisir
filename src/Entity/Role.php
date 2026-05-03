<?php

namespace App\Entity;

use App\Repository\RoleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoleRepository::class)]
class Role
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // ✅ FIX : non-nullable string
    #[ORM\Column(length: 50)]
    private string $name = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    // ✅ FIX : DateTimeImmutable non-nullable
    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    // ✅ FIX : DateTimeImmutable nullable
    #[ORM\Column(name: 'updated_at', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToMany(targetEntity: Users::class, mappedBy: 'roles')]
    private Collection $no;

    public function __construct()
    {
        $this->no = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function getNo(): Collection { return $this->no; }

    public function addNo(Users $no): static
    {
        if (!$this->no->contains($no)) {
            $this->no->add($no);
            $no->addRole($this);
        }
        return $this;
    }

    public function removeNo(Users $no): static
    {
        if ($this->no->removeElement($no)) {
            $no->removeRole($this);
        }
        return $this;
    }
}