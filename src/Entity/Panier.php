<?php

namespace App\Entity;

use App\Repository\PanierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PanierRepository::class)]
class Panier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(mappedBy: 'panier', cascade: ['persist', 'remove'])]
    private ?User $user = null;

    /**
     * @var Collection<int, Ajouter>
     */
    #[ORM\OneToMany(targetEntity: Ajouter::class, mappedBy: 'panier', orphanRemoval: true)]
    private Collection $ajouters;

    public function __construct()
    {
        $this->ajouters = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        // unset the owning side of the relation if necessary
        if ($user === null && $this->user !== null) {
            $this->user->setPanier(null);
        }

        // set the owning side of the relation if necessary
        if ($user !== null && $user->getPanier() !== $this) {
            $user->setPanier($this);
        }

        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, Ajouter>
     */
    public function getAjouters(): Collection
    {
        return $this->ajouters;
    }

    public function addAjouter(Ajouter $ajouter): static
    {
        if (!$this->ajouters->contains($ajouter)) {
            $this->ajouters->add($ajouter);
            $ajouter->setPanier($this);
        }

        return $this;
    }

    public function removeAjouter(Ajouter $ajouter): static
    {
        if ($this->ajouters->removeElement($ajouter)) {
            // set the owning side to null (unless already changed)
            if ($ajouter->getPanier() === $this) {
                $ajouter->setPanier(null);
            }
        }

        return $this;
    }
}
