<?php

namespace App\Entity;

use Symfony\Component\Serializer\Annotation\Ignore;
use App\Repository\UsersRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

#[ORM\Entity(repositoryClass: UsersRepository::class)]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
#[Vich\Uploadable]
class Users implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Vich\UploadableField(mapping: 'user_avatar', fileNameProperty: 'avatarName')]
    private ?File $avatarFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarName = null;

    // ✅ FIX type mismatch : ?string → string
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le nom doit contenir au moins 2 caractères.')]
    #[Assert\Regex(pattern: '/^[A-Za-zÀ-ÿ\s]+$/', message: 'Le nom ne doit contenir que des lettres.')]
    private string $nom = '';

    // ✅ FIX type mismatch : ?string → string
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Le prénom doit contenir au moins 2 caractères.')]
    #[Assert\Regex(pattern: '/^[A-Za-zÀ-ÿ\s]+$/', message: 'Le prénom ne doit contenir que des lettres.')]
    private string $prenom = '';

    // ✅ FIX type mismatch : ?string → string
    #[ORM\Column(length: 150, unique: true)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "L'adresse email '{{ value }}' n'est pas valide.")]
    private string $email = '';

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(pattern: '/^[0-9]{8}$/', message: 'Le téléphone doit contenir exactement 8 chiffres.')]
    private ?string $telephone = null;

    // ✅ FIX Security + type mismatch : ?string → string
    #[ORM\Column(length: 255)]
    #[Ignore]
    private string $passwordHash = '';

    #[Ignore]
    private ?string $plainPassword = null;

    #[ORM\Column(name: 'is_active', nullable: true)]
    private ?bool $isActive = true;

    // ✅ FIX : DateTime → DateTimeImmutable non-nullable
    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    // ✅ FIX : DateTime → DateTimeImmutable nullable
    #[ORM\Column(name: 'updated_at', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isVerified = false;

    #[ORM\Column(length: 255, nullable: true)]
    #[Ignore]
    private ?string $verificationToken = null;

    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'no', fetch: 'EAGER')]
    #[ORM\JoinTable(
        name: 'users_role',
        joinColumns: [new ORM\JoinColumn(name: 'users_id', referencedColumnName: 'id')],
        inverseJoinColumns: [new ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id')]
    )]
    private Collection $roles;

    #[ORM\Column(length: 255, nullable: true)]
    #[Ignore]
    private ?string $resetToken = null;

    // ✅ FIX : DateTime → DateTimeImmutable
    #[ORM\Column(nullable: true)]
    #[Ignore]
    private ?\DateTimeImmutable $resetTokenExpiresAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Ignore]
    private ?array $faceDescriptor = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $telegramChatId = null;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
        $this->isActive = true;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __sleep(): array
    {
        return ['id', 'nom', 'prenom', 'email', 'passwordHash', 'isActive', 'roles'];
    }

    public function __wakeup(): void
    {
        $this->avatarFile = null;
        $this->avatarName = null;
    }

    public function getId(): ?int { return $this->id; }

    public function setAvatarFile(?File $avatarFile = null): void
    {
        $this->avatarFile = $avatarFile;
        if ($avatarFile !== null) { $this->updatedAt = new \DateTimeImmutable(); }
    }
    public function getAvatarFile(): ?File { return $this->avatarFile; }

    public function setAvatarName(?string $avatarName): void { $this->avatarName = $avatarName; }
    public function getAvatarName(): ?string { return $this->avatarName; }

    public function getNom(): string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getPrenom(): string { return $this->prenom; }
    public function setPrenom(string $prenom): static { $this->prenom = $prenom; return $this; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): static { $this->telephone = $telephone; return $this; }

    public function getPasswordHash(): string { return $this->passwordHash; }
    public function setPasswordHash(#[\SensitiveParameter] string $passwordHash): static { $this->passwordHash = $passwordHash; return $this; }

    public function getPlainPassword(): ?string { return $this->plainPassword; }
    public function setPlainPassword(#[\SensitiveParameter] ?string $plainPassword): static { $this->plainPassword = $plainPassword; return $this; }

    public function getFaceDescriptor(): ?array { return $this->faceDescriptor; }
    public function setFaceDescriptor(?array $faceDescriptor): self { $this->faceDescriptor = $faceDescriptor; return $this; }

    public function isActive(): ?bool { return $this->isActive; }
    public function setIsActive(?bool $isActive): static { $this->isActive = $isActive; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static { $this->updatedAt = $updatedAt; return $this; }

    public function getRolesCollection(): Collection { return $this->roles; }

    public function addRole(Role $role): static
    {
        if (!$this->roles->contains($role)) { $this->roles->add($role); }
        return $this;
    }

    public function removeRole(Role $role): static { $this->roles->removeElement($role); return $this; }

    public function getUserIdentifier(): string { return $this->email; }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];
        if ($this->email === 'rayenhafian72@gmail.com') { $roles[] = 'ROLE_ADMIN'; }
        foreach ($this->roles as $role) {
            if ($role->getName()) { $roles[] = $role->getName(); }
        }
        return array_unique($roles);
    }

    public function eraseCredentials(): void {}

    public function getPassword(): string { return $this->passwordHash; }

    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof Users) { return false; }
        return $this->email === $user->getEmail();
    }

    public function getTelegramChatId(): ?string { return $this->telegramChatId; }
    public function setTelegramChatId(?string $v): static { $this->telegramChatId = $v; return $this; }

    public function getResetToken(): ?string { return $this->resetToken; }
    public function setResetToken(#[\SensitiveParameter] ?string $resetToken): static { $this->resetToken = $resetToken; return $this; }

    public function getResetTokenExpiresAt(): ?\DateTimeImmutable { return $this->resetTokenExpiresAt; }
    public function setResetTokenExpiresAt(?\DateTimeImmutable $resetTokenExpiresAt): static { $this->resetTokenExpiresAt = $resetTokenExpiresAt; return $this; }

    public function isVerified(): ?bool { return $this->isVerified; }
    public function setIsVerified(?bool $isVerified): static { $this->isVerified = $isVerified; return $this; }

    public function getVerificationToken(): ?string { return $this->verificationToken; }
    public function setVerificationToken(#[\SensitiveParameter] ?string $token): static { $this->verificationToken = $token; return $this; }
}