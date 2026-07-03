<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbItemRepository;

/**
 * One crumb: matches a route name + static param subset; parent links form the trail order (no sibling ordering field).
 */
#[ORM\Entity(repositoryClass: BreadcrumbItemRepository::class)]
#[ORM\Table(name: 'nowo_breadcrumb_item')]
class BreadcrumbItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.unusedType (Doctrine assigns id after persist) */
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: BreadcrumbCollection::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?BreadcrumbCollection $collection = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?BreadcrumbItem $parent = null;

    #[ORM\Column(length: 255)]
    private string $routeName = '';

    /**
     * Params that must match the current request for this row to apply (exact values).
     *
     * @var array<string, scalar|null>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $staticRouteParams = null;

    /**
     * Route parameter names copied from the current request when generating the link.
     *
     * @var list<string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $dynamicParamKeys = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $linkEnabled = true;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $label = null;

    /**
     * Per-locale labels (locale => string).
     *
     * @var array<string, string>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $translations = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $icon = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCollection(): ?BreadcrumbCollection
    {
        return $this->collection;
    }

    public function setCollection(?BreadcrumbCollection $collection): self
    {
        $this->collection = $collection;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function getRouteName(): string
    {
        return $this->routeName;
    }

    public function setRouteName(string $routeName): self
    {
        $this->routeName = $routeName;

        return $this;
    }

    /** @return array<string, scalar|null>|null */
    public function getStaticRouteParams(): ?array
    {
        return $this->staticRouteParams;
    }

    /** @param array<string, scalar|null>|null $staticRouteParams */
    public function setStaticRouteParams(?array $staticRouteParams): self
    {
        $this->staticRouteParams = $staticRouteParams;

        return $this;
    }

    /** @return list<string>|null */
    public function getDynamicParamKeys(): ?array
    {
        return $this->dynamicParamKeys;
    }

    /** @param list<string>|null $dynamicParamKeys */
    public function setDynamicParamKeys(?array $dynamicParamKeys): self
    {
        $this->dynamicParamKeys = $dynamicParamKeys;

        return $this;
    }

    public function isLinkEnabled(): bool
    {
        return $this->linkEnabled;
    }

    public function setLinkEnabled(bool $linkEnabled): self
    {
        $this->linkEnabled = $linkEnabled;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /** @return array<string, string>|null */
    public function getTranslations(): ?array
    {
        return $this->translations;
    }

    /** @param array<string, string>|null $translations */
    public function setTranslations(?array $translations): self
    {
        $this->translations = $translations;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function resolveLabel(string $locale, ?string $defaultLocale): string
    {
        $translations = $this->translations ?? [];
        if (isset($translations[$locale]) && '' !== $translations[$locale]) {
            return $translations[$locale];
        }
        if (null !== $defaultLocale && isset($translations[$defaultLocale]) && '' !== $translations[$defaultLocale]) {
            return $translations[$defaultLocale];
        }
        if (null !== $this->label && '' !== $this->label) {
            return $this->label;
        }

        return '';
    }
}
