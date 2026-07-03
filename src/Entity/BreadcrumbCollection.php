<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;

/**
 * A named set of breadcrumb items (e.g. "admin", "public") with shared presentation options.
 */
#[ORM\Entity(repositoryClass: BreadcrumbCollectionRepository::class)]
#[ORM\Table(name: 'nowo_breadcrumb_collection')]
#[ORM\UniqueConstraint(name: 'uniq_breadcrumb_collection_code_context', columns: ['code', 'context_key'])]
class BreadcrumbCollection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.unusedType (Doctrine assigns id after persist) */
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $code = '';

    #[ORM\Column(name: 'context_key', length: 512, options: ['default' => ''])]
    private string $contextKey = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $homeIcon = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $separatorIcon = null;

    /**
     * Presentation hints: breakpoints, max visible crumbs, etc. (schema documented in docs).
     *
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $responsiveConfig = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $classList = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $classItem = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $classSeparator = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $classCurrent = null;

    /**
     * Key into nowo_breadcrumb_kit.inline_edit.access_services; null disables inline editor for this collection.
     */
    #[ORM\Column(name: 'inline_edit_access_key', length: 64, nullable: true)]
    private ?string $inlineEditAccessKey = null;

    /** @var Collection<int, BreadcrumbItem> */
    #[ORM\OneToMany(targetEntity: BreadcrumbItem::class, mappedBy: 'collection', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $items;

    public function __construct()
    {
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getContextKey(): string
    {
        return $this->contextKey;
    }

    public function setContextKey(string $contextKey): self
    {
        $this->contextKey = $contextKey;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getHomeIcon(): ?string
    {
        return $this->homeIcon;
    }

    public function setHomeIcon(?string $homeIcon): self
    {
        $this->homeIcon = $homeIcon;

        return $this;
    }

    public function getSeparatorIcon(): ?string
    {
        return $this->separatorIcon;
    }

    public function setSeparatorIcon(?string $separatorIcon): self
    {
        $this->separatorIcon = $separatorIcon;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getResponsiveConfig(): ?array
    {
        return $this->responsiveConfig;
    }

    /** @param array<string, mixed>|null $responsiveConfig */
    public function setResponsiveConfig(?array $responsiveConfig): self
    {
        $this->responsiveConfig = $responsiveConfig;

        return $this;
    }

    public function getClassList(): ?string
    {
        return $this->classList;
    }

    public function setClassList(?string $classList): self
    {
        $this->classList = $classList;

        return $this;
    }

    public function getClassItem(): ?string
    {
        return $this->classItem;
    }

    public function setClassItem(?string $classItem): self
    {
        $this->classItem = $classItem;

        return $this;
    }

    public function getClassSeparator(): ?string
    {
        return $this->classSeparator;
    }

    public function setClassSeparator(?string $classSeparator): self
    {
        $this->classSeparator = $classSeparator;

        return $this;
    }

    public function getClassCurrent(): ?string
    {
        return $this->classCurrent;
    }

    public function setClassCurrent(?string $classCurrent): self
    {
        $this->classCurrent = $classCurrent;

        return $this;
    }

    public function getInlineEditAccessKey(): ?string
    {
        return $this->inlineEditAccessKey;
    }

    public function setInlineEditAccessKey(?string $inlineEditAccessKey): self
    {
        $this->inlineEditAccessKey = $inlineEditAccessKey;

        return $this;
    }

    /** @return Collection<int, BreadcrumbItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(BreadcrumbItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setCollection($this);
        }

        return $this;
    }

    public function removeItem(BreadcrumbItem $item): self
    {
        $this->items->removeElement($item);

        return $this;
    }
}
