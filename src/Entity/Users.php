<?php

namespace App\Entity;

use App\Repository\UsersRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: UsersRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
class Users implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le nom doit contenir au moins 2 caractères.')]
    #[Assert\Regex(pattern: '/^[A-Za-zÀ-ÿ\s]+$/', message: 'Le nom ne doit contenir que des lettres.')]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le prénom doit contenir au moins 2 caractères.')]
    #[Assert\Regex(pattern: '/^[A-Za-zÀ-ÿ\s]+$/', message: 'Le prénom ne doit contenir que des lettres.')]
    private ?string $prenom = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "L'adresse email '{{ value }}' n'est pas valide.")]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(pattern: '/^[0-9]{8}$/', message: 'Le téléphone doit contenir exactement 8 chiffres.')]
    private ?string $telephone = null;

    #[ORM\Column(length: 255)]
    private ?string $passwordHash = null;

    // FIX : valeur par défaut true pour que tout nouvel utilisateur soit actif
    #[ORM\Column(name: 'is_active', nullable: true)]
    private ?bool $isActive = true;

    #[ORM\Column(name: 'created_at', nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(name: 'updated_at', nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'no', fetch: 'EAGER')]
    #[ORM\JoinTable(
        name: 'users_role',
        joinColumns: [new ORM\JoinColumn(name: 'users_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id')]
    )]
    private Collection $roles;

    public function __construct()
    {
        $this->roles     = new ArrayCollection();
        $this->isActive  = true;
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(string $prenom): static { $this->prenom = $prenom; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): static { $this->telephone = $telephone; return $this; }

    public function getPasswordHash(): ?string { return $this->passwordHash; }
    public function setPasswordHash(string $passwordHash): static { $this->passwordHash = $passwordHash; return $this; }

    public function isActive(): ?bool { return $this->isActive; }
    public function setIsActive(?bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): ?\DateTime { return $this->createdAt; }
    public function setCreatedAt(?\DateTime $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTime { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTime $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function getRolesCollection(): Collection { return $this->roles; }

    public function addRole(Role $role): static
    {
        if (!$this->roles->contains($role)) {
            $this->roles->add($role);
        }
        return $this;
    }

    public function removeRole(Role $role): static
    {
        $this->roles->removeElement($role);
        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];
        foreach ($this->roles as $role) {
            if ($role->getName()) {
                $roles[] = $role->getName();
            }
        }
        return array_unique($roles);
    }

    public function eraseCredentials(): void {}

    // FIX : cast en string pour éviter le retour null qui casse Symfony Security
    public function getPassword(): string
    {
        return (string) $this->passwordHash;
    }

    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof Users) {
            return false;
        }
        return $this->email === $user->getEmail();
    }
    #[ORM\Column(length: 100, nullable: true)]
private ?string $telegramChatId = null;

public function getTelegramChatId(): ?string { return $this->telegramChatId; }
public function setTelegramChatId(?string $v): static { $this->telegramChatId = $v; return $this; }
}
