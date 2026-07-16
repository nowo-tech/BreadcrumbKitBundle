# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/breadcrumb-kit-bundle`  
**Last audited**: 2026-07-07

## Symfony config

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Core DI | FR-DI-001 |
| `Resources/config/services_dashboard.yaml` | Dashboard services | FR-DI-001 |
| `Resources/config/services_profiler.yaml` | Profiler services | FR-DI-001 |
| `Resources/config/routing/dashboard.yaml` | Dashboard routes | FR-CTRL-001 |

## Bundle & DI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `NowoBreadcrumbKitBundle.php` | Bundle entry | FR-BUNDLE-001 |
| `DependencyInjection/Configuration.php` | Config tree | FR-CFG-001 |
| `DependencyInjection/BreadcrumbKitExtension.php` | DI extension | FR-CFG-002 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Twig paths | FR-TWIG-001 |
| `DependencyInjection/Compiler/BreadcrumbInlineEditAccessLocatorPass.php` | Inline edit locator | FR-DI-002 |

## Contract

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Contract/BreadcrumbInlineEditAccessCheckerInterface.php` | Inline edit ACL | FR-SVC-003 |

## Entities & persistence

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Entity/BreadcrumbCollection.php` | Collection entity | FR-ORM-001 |
| `Entity/BreadcrumbItem.php` | Item entity | FR-ORM-001 |
| `Repository/BreadcrumbCollectionRepository.php` | Collection repo | FR-ORM-002 |
| `Repository/BreadcrumbItemRepository.php` | Item repo | FR-ORM-002 |
| `EventSubscriber/TablePrefixSubscriber.php` | Table prefix | FR-EVT-001 |

## DTOs

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Dto/BreadcrumbNode.php` | Crumb node | FR-DTO-001 |
| `Dto/BreadcrumbTrailView.php` | Trail view | FR-DTO-001 |
| `Dto/BreadcrumbInlineEditContext.php` | Inline edit ctx | FR-DTO-001 |

## Services

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Service/BreadcrumbLoader.php` | Trail loader | FR-SVC-001 |
| `Service/BreadcrumbUrlResolver.php` | URL resolver | FR-SVC-002 |
| `Service/BreadcrumbUrlResolverInterface.php` | URL contract | FR-SVC-002 |
| `Service/BreadcrumbInlineEditResolver.php` | Inline edit | FR-SVC-003 |
| `Service/BreadcrumbExporter.php` | Export | FR-SVC-004 |
| `Service/BreadcrumbImporter.php` | Import | FR-SVC-004 |

## Controllers — dashboard

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Controller/Dashboard/DashboardIndexController.php` | Dashboard home | FR-CTRL-001 |
| `Controller/Dashboard/CollectionCrudController.php` | Collection CRUD | FR-CTRL-001 |
| `Controller/Dashboard/ItemCrudController.php` | Item CRUD | FR-CTRL-001 |
| `Controller/Dashboard/ImportExportController.php` | Import/export | FR-CTRL-001 |
| `Controller/Dashboard/DashboardControllerTrait.php` | Shared controller | FR-CTRL-001 |
| `Controller/Dashboard/DashboardRedirectTrait.php` | Redirect helper | FR-CTRL-001 |

## Forms

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Form/BreadcrumbCollectionType.php` | Collection form | FR-FORM-001 |
| `Form/BreadcrumbItemType.php` | Item form | FR-FORM-001 |
| `Form/Dashboard/DashboardGetSearchType.php` | Search form | FR-FORM-001 |
| `Form/Dashboard/DashboardPostDeleteType.php` | Delete confirm | FR-FORM-001 |
| `Form/Dashboard/ImportBreadcrumbType.php` | Import upload | FR-FORM-001 |
| `Form/DataTransformer/JsonObjectTransformer.php` | JSON object | FR-FORM-002 |
| `Form/DataTransformer/JsonStringListTransformer.php` | JSON string list | FR-FORM-002 |

## Twig PHP

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Twig/BreadcrumbExtension.php` | breadcrumb() | FR-TWIG-001 |
| `Twig/BreadcrumbKitDashboardGlobalsExtension.php` | Dashboard globals | FR-TWIG-001 |
| `Twig/BreadcrumbKitDashboardLinkExtension.php` | Dashboard links | FR-TWIG-001 |

## Profiler

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `DataCollector/BreadcrumbDataCollector.php` | Web Profiler panel | FR-PROF-001 |
| `Profiler/BreadcrumbProfilerRecorder.php` | Request recorder | FR-PROF-002 |
| `polyfill-template-aware-data-collector.php` | Collector polyfill | FR-PROF-002 |

## Public assets

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/public/js/dashboard.js` | Dashboard JS | FR-ASSET-001 |

## Translations

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/translations/NowoBreadcrumbKitBundle.en.yaml` | English | FR-I18N-001 |
| `Resources/translations/NowoBreadcrumbKitBundle.es.yaml` | Spanish | FR-I18N-001 |
| `Resources/translations/NowoBreadcrumbKitBundle.de.yaml` | German | FR-I18N-001 |
| `Resources/translations/NowoBreadcrumbKitBundle.fr.yaml` | French | FR-I18N-001 |
| `Resources/translations/NowoBreadcrumbKitBundle.it.yaml` | Italian | FR-I18N-001 |
| `Resources/translations/NowoBreadcrumbKitBundle.nl.yaml` | Dutch | FR-I18N-001 |
| `Resources/translations/NowoBreadcrumbKitBundle.pt.yaml` | Portuguese | FR-I18N-001 |

## Twig views — public

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/breadcrumb.html.twig` | Trail markup | FR-TWIG-002 |
| `Resources/views/_breadcrumb_crumb.html.twig` | Single crumb | FR-TWIG-002 |

## Twig views — profiler

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/Collector/breadcrumb.html.twig` | Profiler panel | FR-PROF-001 |
| `Resources/views/Collector/_icon.svg.twig` | Profiler icon | FR-PROF-001 |

## Twig views — dashboard

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/dashboard/layout.html.twig` | Dashboard layout | FR-TWIG-002 |
| `Resources/views/dashboard/base.html.twig` | Base template | FR-TWIG-002 |
| `Resources/views/dashboard/collection/index.html.twig` | Collection list | FR-TWIG-002 |
| `Resources/views/dashboard/collection/form.html.twig` | Collection form | FR-TWIG-002 |
| `Resources/views/dashboard/item/index.html.twig` | Item list | FR-TWIG-002 |
| `Resources/views/dashboard/item/form.html.twig` | Item form | FR-TWIG-002 |
| `Resources/views/dashboard/import.html.twig` | Import page | FR-TWIG-002 |
| `Resources/views/dashboard/_collection_form_partial.html.twig` | Collection modal | FR-TWIG-002 |
| `Resources/views/dashboard/_item_form_partial.html.twig` | Item modal | FR-TWIG-002 |
| `Resources/views/dashboard/_import_partial.html.twig` | Import partial | FR-TWIG-002 |
| `Resources/views/dashboard/_dashboard_modals.html.twig` | Modal shell | FR-TWIG-002 |
| `Resources/views/dashboard/_icons.html.twig` | Icon partial | FR-TWIG-002 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| Symfony config | 4 | 4 |
| Bundle & DI | 5 | 5 |
| Contract | 1 | 1 |
| Entities & persistence | 5 | 5 |
| DTOs | 3 | 3 |
| Services | 6 | 6 |
| Controllers | 6 | 6 |
| Forms | 7 | 7 |
| Twig PHP | 3 | 3 |
| Profiler | 3 | 3 |
| Public assets | 1 | 1 |
| Translations | 7 | 7 |
| Twig public | 2 | 2 |
| Twig profiler | 2 | 2 |
| Twig dashboard | 12 | 12 |
| **Total production sources** | **67** | **67** |
