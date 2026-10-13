<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Support;

use Symfony\Bridge\Twig\AppVariable;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * Renders the dashboard templates with real UiKit macros, the real translation catalogue and
 * deterministic stubs for routing / CSRF / form helpers (no kernel, no database).
 *
 * Form helpers print markers ({@code <form-row name="code">}) so snapshots stay readable and
 * independent from the active Symfony form theme.
 */
final readonly class DashboardTemplateRenderer
{
    private Environment $twig;

    /**
     * @param array<string, string> $hostTemplates extra templates (name => source) resolvable before the bundle paths
     * @param array<string, mixed>  $globals       overrides for the bundle Twig globals
     */
    public function __construct(array $hostTemplates = [], array $globals = [], string $locale = 'en')
    {
        $root = \dirname(__DIR__, 2);
        $views = $root.'/src/Resources/views';

        $filesystem = new FilesystemLoader([], $root);
        $filesystem->addPath($views, 'NowoBreadcrumbKitBundle');
        $filesystem->addPath($views, '!NowoBreadcrumbKitBundle');
        $filesystem->addPath($root.'/vendor/nowo-tech/ui-kit-bundle/src/Resources/views', 'NowoUiKitBundle');

        $this->twig = new Environment(new ChainLoader([new ArrayLoader($hostTemplates), $filesystem]), [
            'strict_variables' => false,
            'autoescape' => 'html',
            'cache' => false,
        ]);

        $translator = new Translator($locale);
        $translator->addLoader('yaml', new YamlFileLoader());
        foreach (glob($root.'/src/Resources/translations/NowoBreadcrumbKitBundle.*.yaml') ?: [] as $file) {
            $fileLocale = explode('.', basename($file))[1];
            $translator->addResource('yaml', $file, $fileLocale, 'NowoBreadcrumbKitBundle');
        }
        $this->twig->addExtension(new TranslationExtension($translator));

        $requestStack = new RequestStack();
        $request = Request::create('/breadcrumb-kit-admin/collections');
        $request->setLocale($locale);
        $requestStack->push($request);
        $app = new AppVariable();
        $app->setRequestStack($requestStack);
        $this->twig->addGlobal('app', $app);

        foreach (array_merge([
            'nowo_breadcrumb_kit_layout_template' => '@NowoBreadcrumbKitBundle/dashboard/layout.html.twig',
            'nowo_breadcrumb_kit_css_framework' => 'bootstrap5',
            'nowo_breadcrumb_kit_icon_set' => 'bootstrap-icons',
            'nowo_ui_kit_css_framework' => 'bootstrap5',
        ], $globals) as $name => $value) {
            $this->twig->addGlobal($name, $value);
        }

        $safe = ['is_safe' => ['html']];
        $this->twig->addFunction(new TwigFunction('path', static fn (string $route, array $params = []): string => '/r/'.$route.([] === $params ? '' : '?'.http_build_query($params))));
        $this->twig->addFunction(new TwigFunction('asset', static fn (string $path, ?string $package = null): string => '/assets/'.($package ?? 'app').'/'.$path));
        $this->twig->addFunction(new TwigFunction('csrf_token', static fn (string $id): string => 'csrf-'.$id));
        $this->twig->addFunction(new TwigFunction('form_start', static fn (FormView $view, array $options = []): string => '<form-start name="'.$view->vars['name'].'" options="'.htmlspecialchars((string) json_encode($options), \ENT_QUOTES).'">', $safe));
        $this->twig->addFunction(new TwigFunction('form_end', static function (FormView $view, array $options = []): string {
            $view->setRendered();

            return '<form-end name="'.$view->vars['name'].'">';
        }, $safe));
        $this->twig->addFunction(new TwigFunction('form_rest', static fn (FormView $view): string => '<form-rest name="'.$view->vars['name'].'">', $safe));
        $this->twig->addFunction(new TwigFunction('form_widget', static function (FormView $view, array $options = []): string {
            $view->setRendered();

            return '<form-widget name="'.$view->vars['name'].'" options="'.htmlspecialchars((string) json_encode($options), \ENT_QUOTES).'">';
        }, $safe));
        $this->twig->addFunction(new TwigFunction('form_row', static function (FormView $view, array $options = []): string {
            $view->setRendered();

            return '<form-row name="'.$view->vars['name'].'"'.([] === $options ? '' : ' options="'.htmlspecialchars((string) json_encode($options), \ENT_QUOTES).'"').'>';
        }, $safe));
    }

    /**
     * @param list<string> $children
     */
    public static function form(string $name, array $children = []): FormView
    {
        $view = new FormView();
        $view->vars['name'] = $name;
        foreach ($children as $child) {
            $childView = new FormView($view);
            $childView->vars['name'] = $child;
            $view->children[$child] = $childView;
        }

        return $view;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }

    /**
     * Collapse whitespace between tags / runs so snapshots ignore indentation-only template edits.
     */
    public static function normalize(string $html): string
    {
        $html = (string) preg_replace('/\s+/', ' ', $html);
        $html = (string) preg_replace('/>\s+</', ">\n<", $html);
        $html = (string) preg_replace('/\s+>/', '>', $html);
        $html = (string) preg_replace('/="\s+/', '="', $html);
        $html = (string) preg_replace('/\s+"/', '"', $html);

        return trim($html)."\n";
    }
}
