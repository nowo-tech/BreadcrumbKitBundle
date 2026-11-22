# Code inventory — 100% traceability

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/breadcrumb-kit-bundle`  
**Last audited**: 2026-11-22 (REQ sweeps: 2.13–2.20 additions mapped 2026-11-16; 2.23–2.24 and validator catalogues 2026-11-22)  
**Audit**: `git ls-files src | wc -l` → **137** (matches summary total; earlier category counts were recorded before the Twig views were split, the total now counts every tracked `src/` file)

## Symfony config

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/config/routing/dashboard.yaml` | Symfony config | FR-CTRL-001 |
| `Resources/config/services.yaml` | Symfony config | FR-DI-001, FR-FORM-003 |
| `Resources/config/services_dashboard.yaml` | Symfony config | FR-DI-001 |
| `Resources/config/services_profiler.yaml` | Symfony config | FR-DI-001 |

## Bundle & DI

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `DependencyInjection/BreadcrumbKitExtension.php` | Bundle & DI | FR-CFG-002, FR-UI-001, FR-FORM-003, FR-ASSET-002 |
| `DependencyInjection/Compiler/BreadcrumbInlineEditAccessLocatorPass.php` | Bundle & DI | FR-DI-002 |
| `DependencyInjection/Compiler/DashboardSecurityPass.php` | Bundle & DI | FR-SEC-001 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Bundle & DI | FR-TWIG-001 |
| `DependencyInjection/Compiler/CsrfProtectionPass.php` | Bundle & DI | FR-SEC-002 |
| `DependencyInjection/Configuration.php` | Bundle & DI | FR-CFG-001, FR-PHP-001 |
| `NowoBreadcrumbKitBundle.php` | Bundle & DI | FR-BUNDLE-001 |
| `Dashboard/DashboardChrome.php` | Bundle & DI | FR-TWIG-004 |

## Enums

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Enum/CssFramework.php` | Enums | FR-PHP-001 |
| `Enum/IconSet.php` | Enums | FR-PHP-001 |
| `Enum/ModalSize.php` | Enums | FR-PHP-001 |

## Security & contracts

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Contract/BreadcrumbInlineEditAccessCheckerInterface.php` | Security & contracts | FR-SVC-003 |
| `EventSubscriber/DashboardAccessSubscriber.php` | Security & contracts | FR-SEC-001, FR-WORKER-001 |
| `EventSubscriber/DashboardEntityManagerSubscriber.php` | Security & contracts | FR-WORKER-001 |
| `Worker/UnitOfWorkBoundaries.php` | Security & contracts | FR-WORKER-001 |
| `Worker/BreadcrumbOwnEntityManagerSubscriber.php` | Security & contracts | FR-WORKER-001 |
| `Security/BreadcrumbKitAccessCheckerInterface.php` | Security & contracts | FR-SEC-001 |
| `Security/ConfigurableBreadcrumbKitAccessChecker.php` | Security & contracts | FR-SEC-001 |
| `Security/BreadcrumbIconSanitizer.php` | Security & contracts | FR-SEC-003 |
| `Security/BreadcrumbPathPatternValidator.php` | Security & contracts | FR-SEC-004 |

## Entities & persistence

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Entity/BreadcrumbCollection.php` | Entities & persistence | FR-ORM-001 |
| `Entity/BreadcrumbItem.php` | Entities & persistence | FR-ORM-001 |
| `EventSubscriber/TablePrefixSubscriber.php` | Entities & persistence | FR-EVT-001 |
| `Repository/BreadcrumbCollectionRepository.php` | Entities & persistence | FR-ORM-002 |
| `Repository/BreadcrumbItemRepository.php` | Entities & persistence | FR-ORM-002 |
| `Transaction/BreadcrumbTransactionRunner.php` | Entities & persistence | FR-WORKER-001 (2.20, [HIGH-LOAD](../../docs/HIGH-LOAD.md)) |
| `Transaction/BreadcrumbFlushObserver.php` | Entities & persistence | FR-WORKER-001 |
| `Transaction/BreadcrumbRowLock.php` | Entities & persistence | FR-WORKER-001 |
| `EventSubscriber/StorageFailureSubscriber.php` | Entities & persistence | FR-WORKER-001 |
| `Doctrine/AfterCommitConnectionBehaviour.php` | Entities & persistence | FR-WORKER-001 |
| `Doctrine/AfterCommitConnectionDbal3.php` | Entities & persistence | FR-WORKER-001 |

## DTOs

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Dto/BreadcrumbCollapse.php` | DTOs | FR-DTO-001, 004 FR-001 |
| `Dto/BreadcrumbInlineEditContext.php` | DTOs | FR-DTO-001 |
| `Dto/BreadcrumbNode.php` | DTOs | FR-DTO-001 |
| `Dto/BreadcrumbTrailView.php` | DTOs | FR-DTO-001 |

## Services

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Service/BreadcrumbExporter.php` | Services | FR-SVC-004 |
| `Service/BreadcrumbImporter.php` | Services | FR-SVC-004 |
| `Service/BreadcrumbInlineEditResolver.php` | Services | FR-SVC-003 |
| `Service/BreadcrumbLoader.php` | Services | FR-SVC-001, FR-SVC-005, FR-EVT-002 |
| `Service/BreadcrumbTrailPreview.php` | Services | FR-SVC-006 |
| `Service/BreadcrumbEntityManagerResetter.php` | Services | FR-WORKER-001 |
| `Service/BreadcrumbUrlResolver.php` | Services | FR-SVC-002 |
| `Service/BreadcrumbUrlResolverInterface.php` | Services | FR-SVC-002 |
| `Service/BreadcrumbCacheInvalidator.php` | Services | FR-SVC-001, FR-WORKER-001 |
| `Service/BreadcrumbCurrentLabel.php` | Services | 006 FR-001..FR-005, FR-WORKER-001 |
| `Service/BreadcrumbCurrentLabelInterface.php` | Services | 006 FR-001 |
| `Service/BreadcrumbCurrentLabelProviderInterface.php` | Services | 006 FR-001, FR-003 |
| `Service/ConfiguredLocales.php` | Services | FR-CFG-001 |

## Controllers — dashboard

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Controller/Dashboard/CollectionCrudController.php` | Controllers — dashboard | FR-CTRL-001 |
| `Controller/Dashboard/DashboardControllerTrait.php` | Controllers — dashboard | FR-CTRL-001 |
| `Controller/Dashboard/DashboardIndexController.php` | Controllers — dashboard | FR-CTRL-001 |
| `Controller/Dashboard/DashboardRedirectTrait.php` | Controllers — dashboard | FR-CTRL-001 |
| `Controller/Dashboard/ImportExportController.php` | Controllers — dashboard | FR-CTRL-001 |
| `Controller/Dashboard/ItemCrudController.php` | Controllers — dashboard | FR-CTRL-001 |
| `Dashboard/DashboardPages.php` | Controllers — dashboard | FR-CTRL-001, FR-UI-002 |

## Commands

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Command/ExportBreadcrumbsCommand.php` | Commands | FR-CLI-002 |
| `Command/GenerateBreadcrumbKitMigrationCommand.php` | Commands | FR-CLI-001 |
| `Command/ImportBreadcrumbsCommand.php` | Commands | FR-CLI-002 |
| `Command/PreviewBreadcrumbTrailCommand.php` | Commands | FR-CLI-003 |

## Events

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Event/BreadcrumbTrailBuiltEvent.php` | Events | FR-EVT-002 |

## Forms

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Form/BreadcrumbCollectionType.php` | Forms | FR-FORM-001, FR-FORM-003 |
| `Form/BreadcrumbItemType.php` | Forms | FR-FORM-001, FR-FORM-003 |
| `Form/BreadcrumbIconConstraint.php` | Forms | FR-FORM-004, FR-SEC-003 |
| `Form/BreadcrumbPathPatternConstraint.php` | Forms | FR-FORM-004, FR-SEC-004 |
| `Form/Dashboard/DashboardGetSearchType.php` | Forms | FR-FORM-001, FR-FORM-004 |
| `Form/Dashboard/DashboardPostDeleteType.php` | Forms | FR-FORM-001 |
| `Form/Dashboard/ImportBreadcrumbType.php` | Forms | FR-FORM-001, FR-FORM-003 |
| `Form/DataTransformer/JsonObjectTransformer.php` | Forms | FR-FORM-002 |
| `Form/DataTransformer/JsonStringListTransformer.php` | Forms | FR-FORM-002 |

## Twig PHP

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Twig/BreadcrumbExtension.php` | Twig PHP | FR-TWIG-001 |
| `Twig/BreadcrumbKitDashboardGlobalsExtension.php` | Twig PHP | FR-TWIG-001, FR-UI-001 |
| `Twig/BreadcrumbKitDashboardLinkExtension.php` | Twig PHP | FR-TWIG-001 |

## Profiler

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `DataCollector/BreadcrumbDataCollector.php` | Profiler | FR-PROF-001 |
| `Profiler/BreadcrumbProfilerRecorder.php` | Profiler | FR-PROF-002 |
| `polyfill-template-aware-data-collector.php` | Profiler | FR-PROF-002 |

## Public assets

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/public/js/breadcrumb-collapse.js` | Public assets | FR-ASSET-001, 004 FR-005 |
| `Resources/public/js/breadcrumb-inline.js` | Public assets | FR-ASSET-001 |
| `Resources/public/js/dashboard.js` | Public assets | FR-ASSET-001 |

### External kit assets (not in this tree)

| Capability | Package | Spec IDs |
| --- | --- | --- |
| Semantic `nowo-ui.css` + Twig macros (`@NowoUiKitBundle/macros/ui.html.twig`) | [`nowo-tech/ui-kit-bundle`](https://github.com/nowo-tech/UiKitBundle) ^1.4 | FR-UI-001, FR-DEP-001 |
| `FormOptionsMerger` / `FormOptionsTrait` / profile merge | [`nowo-tech/form-kit-bundle`](https://github.com/nowo-tech/FormKitBundle) ^2.0 | FR-FORM-003, FR-DEP-001 |

BreadcrumbKit prepends FormKit profile `breadcrumb_kit` and may seed FormKit/UiKit `css_framework` (mapped) from `dashboard.*` when the host has not set those keys.

## Translations

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/translations/NowoBreadcrumbKitBundle.de.yaml` | Translations | FR-I18N-001 |
| `Resources/translations/NowoBreadcrumbKitBundle.en.yaml` | Translations | FR-I18N-001 |
| `Resources/translations/NowoBreadcrumbKitBundle.es.yaml` | Translations | FR-I18N-001 |
| `Resources/translations/NowoBreadcrumbKitBundle.fr.yaml` | Translations | FR-I18N-001 |
| `Resources/translations/NowoBreadcrumbKitBundle.it.yaml` | Translations | FR-I18N-001 |
| `Resources/translations/NowoBreadcrumbKitBundle.nl.yaml` | Translations | FR-I18N-001 |
| `Resources/translations/NowoBreadcrumbKitBundle.pt.yaml` | Translations | FR-I18N-001 |

## Twig views — public

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/_breadcrumb_collapse.html.twig` | Twig views — public | FR-TWIG-002, 004 FR-003 |
| `Resources/views/_breadcrumb_collapse_assets.html.twig` | Twig views — public | FR-TWIG-002, 004 FR-004 |
| `Resources/views/_breadcrumb_crumb.html.twig` | Twig views — public | FR-TWIG-002 |
| `Resources/views/breadcrumb.html.twig` | Twig views — public | FR-TWIG-002 |

## Twig views — profiler

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/Collector/_icon.svg.twig` | Twig views — profiler | FR-PROF-001 |
| `Resources/views/Collector/breadcrumb.html.twig` | Twig views — profiler | FR-PROF-001 |

## Twig views — dashboard

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Resources/views/dashboard/_collection_form_partial.html.twig` | Twig views — dashboard | FR-TWIG-002, FR-UI-001 |
| `Resources/views/dashboard/_import_partial.html.twig` | Twig views — dashboard | FR-TWIG-002, FR-UI-001 |
| `Resources/views/dashboard/_item_form_partial.html.twig` | Twig views — dashboard | FR-TWIG-002, FR-UI-001 |
| `Resources/views/dashboard/collection/form.html.twig` | Twig views — dashboard | FR-TWIG-002 |
| `Resources/views/dashboard/collection/index.html.twig` | Twig views — dashboard | FR-TWIG-002, FR-UI-001 |
| `Resources/views/dashboard/import.html.twig` | Twig views — dashboard | FR-TWIG-002 |
| `Resources/views/dashboard/item/form.html.twig` | Twig views — dashboard | FR-TWIG-002 |
| `Resources/views/dashboard/item/index.html.twig` | Twig views — dashboard | FR-TWIG-002, FR-UI-001 |
| `Resources/views/dashboard/_dashboard_scripts.html.twig` | Twig views — dashboard | FR-TWIG-002, FR-ASSET-001 |
| `Resources/views/dashboard/_form_dialog.html.twig` | Twig views — dashboard | FR-TWIG-002, FR-UI-002 |
| `Resources/views/dashboard/_legacy_layout.html.twig` | Twig views — dashboard | FR-TWIG-004 |
| `Resources/views/dashboard/collection/delete.html.twig` | Twig views — dashboard | FR-TWIG-002, FR-UI-002 |
| `Resources/views/dashboard/item/delete.html.twig` | Twig views — dashboard | FR-TWIG-002, FR-UI-002 |

## Additions 2.13–2.20 (ports, after-commit events, test helpers)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Contract/BreadcrumbEditAccessCheckerInterface.php` | Additions 2.13–2.20 | FR-ARCH-001 |
| `Contract/BreadcrumbLinkAccessCheckerInterface.php` | Additions 2.13–2.20 | FR-SEC-005 |
| `DependencyInjection/Compiler/AfterCommitMiddlewarePass.php` | Additions 2.13–2.20 | FR-EVT-002 |
| `DependencyInjection/Compiler/BreadcrumbEntityManagerPass.php` | Additions 2.13–2.20 | FR-ORM-003 |
| `DependencyInjection/Compiler/LinkAccessCheckerPass.php` | Additions 2.13–2.20 | FR-SEC-005 |
| `Doctrine/AfterCommitConnection.php` | Additions 2.13–2.20 | FR-EVT-002 |
| `Doctrine/AfterCommitDriver.php` | Additions 2.13–2.20 | FR-EVT-002 |
| `Doctrine/AfterCommitMiddleware.php` | Additions 2.13–2.20 | FR-EVT-002 |
| `Doctrine/BreadcrumbChangeOutbox.php` | Additions 2.13–2.20 | FR-EVT-002 |
| `Doctrine/ExcludedEntityNamespaceDriver.php` | Additions 2.13–2.20 | FR-ORM-003 |
| `Doctrine/TransactionObserverInterface.php` | Additions 2.13–2.20 | FR-EVT-002 |
| `Dto/BreadcrumbEditContext.php` | Additions 2.13–2.20 | FR-ARCH-001 |
| `Dto/BreadcrumbRouteContext.php` | Additions 2.13–2.20 | FR-ARCH-001 |
| `Event/BreadcrumbCollectionChangedEvent.php` | Additions 2.13–2.20 | FR-EVT-002 |
| `Service/BreadcrumbLoaderInterface.php` | Additions 2.13–2.20 | FR-ARCH-001, FR-SVC-001 |
| `Service/BreadcrumbParamMap.php` | Additions 2.13–2.20 | FR-SVC-005 |
| `Service/BreadcrumbRequestContextFactory.php` | Additions 2.13–2.20 | FR-ARCH-001 |
| `Testing/BreadcrumbRouteCoverageTrait.php` | Additions 2.13–2.20 | FR-TEST-001 |
| `Testing/BreadcrumbRows.php` | Additions 2.13–2.20 | FR-TEST-001 |
| `Testing/NonPageRouteFilter.php` | Additions 2.13–2.20 | FR-TEST-001 |

## Additions 2.23–2.24 and validator messages (sweep 2026-11-22)

| Source file | Spec section | Requirement IDs |
| --- | --- | --- |
| `Dashboard/HeaderActionsPlacement.php` | Additions 2.23–2.24 | FR-UI-003 |
| `DependencyInjection/Compiler/ConflictResponsePass.php` | Additions 2.23–2.24 | FR-WORKER-002 |
| `Resources/translations/validators.de.yaml` | Additions 2.23–2.24 | FR-I18N-002 |
| `Resources/translations/validators.en.yaml` | Additions 2.23–2.24 | FR-I18N-002 |
| `Resources/translations/validators.es.yaml` | Additions 2.23–2.24 | FR-I18N-002 |
| `Resources/translations/validators.fr.yaml` | Additions 2.23–2.24 | FR-I18N-002 |
| `Resources/translations/validators.it.yaml` | Additions 2.23–2.24 | FR-I18N-002 |
| `Resources/translations/validators.nl.yaml` | Additions 2.23–2.24 | FR-I18N-002 |
| `Resources/translations/validators.pt.yaml` | Additions 2.23–2.24 | FR-I18N-002 |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| Symfony config | 4 | 4 |
| Bundle & DI | 8 | 8 |
| Enums | 3 | 3 |
| Security & contracts | 7 | 7 |
| Entities & persistence | 5 | 5 |
| DTOs | 4 | 4 |
| Services | 10 | 10 |
| Controllers — dashboard | 7 | 7 |
| Commands | 4 | 4 |
| Events | 1 | 1 |
| Forms | 9 | 9 |
| Twig PHP | 3 | 3 |
| Profiler | 3 | 3 |
| Public assets | 3 | 3 |
| Translations | 7 | 7 |
| Twig views — public | 4 | 4 |
| Twig views — profiler | 2 | 2 |
| Twig views — dashboard | 13 | 13 |
| Additions 2.13–2.20 | 20 | 20 |
| Additions 2.23–2.24 and validator messages | 9 | 9 |
| **Total production sources** | **137** | **137** |
