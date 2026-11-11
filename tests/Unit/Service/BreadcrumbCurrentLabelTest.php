<?php

declare(strict_types=1);

namespace Nowo\BreadcrumbKitBundle\Tests\Unit\Service;

use Nowo\BreadcrumbKitBundle\Service\BreadcrumbCurrentLabel;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BreadcrumbCurrentLabelTest extends TestCase
{
    private RequestStack $stack;

    private Request $page;

    protected function setUp(): void
    {
        $this->page = self::routed('wiki_page', ['slug' => 'intro', 'object' => new stdClass()]);
        $this->stack = new RequestStack();
        $this->stack->push($this->page);
    }

    public function testNothingSetResolvesToNull(): void
    {
        self::assertNull($this->label()->currentLabelFor($this->page, 'default', ''));
        self::assertNull((new BreadcrumbCurrentLabel())->currentLabelFor($this->page, 'default', ''));
    }

    public function testLabelAppliesToTheTrailOfItsRoute(): void
    {
        $label = $this->label();
        $label->setCurrentLabel('Release notes');

        self::assertSame('Release notes', $label->currentLabelFor($this->page, 'default', '', 'en'));
        self::assertSame('Release notes', $label->currentLabelFor($this->page, 'admin', 'shop'), 'no restriction: any collection');
    }

    public function testOtherRouteOrOtherParametersKeepTheirLabels(): void
    {
        $label = $this->label();
        $label->setCurrentLabel('Intro');

        self::assertNull($label->currentLabelFor(self::routed('wiki_space', ['slug' => 'intro']), 'default', ''));
        self::assertNull($label->currentLabelFor(self::routed('wiki_page', ['slug' => 'other']), 'default', ''));
        self::assertNull($label->currentLabelFor(new Request(), 'default', ''), 'fragment without a route');
        self::assertSame('Intro', $label->currentLabelFor(self::routed('wiki_page', ['slug' => 'intro']), 'default', ''), 'same route and scalar params');
    }

    public function testCollectionAndContextKeyRestrictTheLabel(): void
    {
        $label = $this->label();
        $label->setCurrentLabel('Intro', null, [], 'default');
        self::assertSame('Intro', $label->currentLabelFor($this->page, 'default', 'any'));
        self::assertNull($label->currentLabelFor($this->page, 'sidebar', ''));

        $label->setCurrentLabel('Intro', null, [], null, 'wiki');
        self::assertSame('Intro', $label->currentLabelFor($this->page, 'sidebar', 'wiki'));
        self::assertNull($label->currentLabelFor($this->page, 'default', ''));
    }

    public function testSetOutsideARoutedRequestIsANoOp(): void
    {
        $label = new BreadcrumbCurrentLabel(new RequestStack());
        $label->setCurrentLabel('From a command');
        self::assertNull($label->currentLabelFor($this->page, 'default', ''));

        $stack = new RequestStack();
        $stack->push(new Request());
        $label = new BreadcrumbCurrentLabel($stack);
        $label->setCurrentLabel('Unrouted');
        self::assertNull($label->currentLabelFor(new Request(), 'default', ''));

        $label = new BreadcrumbCurrentLabel();
        $label->setCurrentLabel('No stack');
        self::assertNull($label->currentLabelFor($this->page, 'default', ''));
    }

    public function testSetInsideAFragmentIsScopedToTheFragmentRoute(): void
    {
        $fragment = self::routed('wiki_sidebar', []);
        $this->stack->push($fragment);
        $label = $this->label();
        $label->setCurrentLabel('Sidebar');

        self::assertSame('Sidebar', $label->currentLabelFor($fragment, 'default', ''));
        self::assertNull($label->currentLabelFor($this->page, 'default', ''));
    }

    public function testLabelIsNormalisedToOneLine(): void
    {
        $label = $this->label();

        $label->setCurrentLabel("  Line one\n\tline\u{2028}two \u{0007} ");
        self::assertSame('Line one line two', $this->resolve($label));

        $label->setCurrentLabel(str_repeat('é', BreadcrumbCurrentLabel::MAX_LENGTH + 10));
        self::assertSame(BreadcrumbCurrentLabel::MAX_LENGTH, mb_strlen((string) $this->resolve($label)));
    }

    public function testBidiAndZeroWidthCharactersAreRemoved(): void
    {
        $label = $this->label();
        $label->setCurrentLabel("Invoice\u{202E}fdp.exe\u{202C} \u{200B}2026\u{2066}x\u{2069}\u{FEFF}\u{00AD}");

        self::assertSame('Invoicefdp.exe 2026x', $this->resolve($label));
        self::assertSame('', BreadcrumbCurrentLabel::normalize("\u{200B}\u{200E}\u{200F}"));
    }

    public function testHtmlIsKeptAsTextForTheTemplateToEscape(): void
    {
        $label = $this->label();
        $label->setCurrentLabel('<script>alert(1)</script>');

        self::assertSame('<script>alert(1)</script>', $this->resolve($label));
    }

    public function testBlankNullOrInvalidUtf8LabelIsIgnoredAsAWhole(): void
    {
        $label = $this->label();

        foreach ([null, '', "  \n ", "Valid \xC3\x28 part"] as $value) {
            $label->setCurrentLabel('Title');
            $label->setCurrentLabel($value);
            self::assertNull($this->resolve($label));
        }
    }

    public function testStringableIsAccepted(): void
    {
        $label = $this->label();
        $label->setCurrentLabel(new class implements Stringable {
            public function __toString(): string
            {
                return 'From object';
            }
        });

        self::assertSame('From object', $this->resolve($label));
    }

    public function testClearForgetsTheLabel(): void
    {
        $label = $this->label();
        $label->setCurrentLabel('Title');
        $label->clearCurrentLabel();
        self::assertNull($this->resolve($label));

        $label->setCurrentLabel('Title');
        $label->reset();
        self::assertNull($this->resolve($label));
    }

    public function testStringIsNotTranslatedWithoutADomain(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::never())->method('trans');

        $label = $this->label($translator);
        $label->setCurrentLabel('wiki.page.title');
        self::assertSame('wiki.page.title', $this->resolve($label, 'es'));

        $label->setCurrentLabel('Key', '');
        self::assertSame('Key', $this->resolve($label, 'es'), 'empty domain: no translation');
    }

    public function testStringWithADomainIsTranslatedInTheTrailLocale(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())->method('trans')
            ->with('wiki.page.title', ['%name%' => 'Intro'], 'wiki', 'es')
            ->willReturn("Página\n Intro");

        $label = $this->label($translator);
        $label->setCurrentLabel('wiki.page.title', 'wiki', ['%name%' => 'Intro']);

        self::assertSame('Página Intro', $this->resolve($label, 'es'));
    }

    public function testDomainWithoutTranslatorKeepsTheKeyWithItsParameters(): void
    {
        $label = $this->label();
        $label->setCurrentLabel('Page %t%', 'wiki', ['%t%' => 'Intro']);
        self::assertSame('Page Intro', $this->resolve($label, 'es'));

        $label->setCurrentLabel(new TranslatableMessage('Page %t%', ['%t%' => 'Intro']));
        self::assertSame('Page Intro', $this->resolve($label));
    }

    public function testTranslatableIsTranslated(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->with('page.title', ['%t%' => 'X'], 'wiki', 'fr')->willReturn('Page X');

        $label = $this->label($translator);
        $label->setCurrentLabel(new TranslatableMessage('page.title', ['%t%' => 'X'], 'wiki'));

        self::assertSame('Page X', $this->resolve($label, 'fr'));
    }

    public function testTranslationToBlankKeepsTheItemLabel(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn("  \u{200B}");

        $label = $this->label($translator);
        $label->setCurrentLabel('key', 'wiki');

        self::assertNull($this->resolve($label));
    }

    public function testLabelOfAnotherMainRequestIsNeverApplied(): void
    {
        $label = $this->label();
        $label->setCurrentLabel('Page A');
        self::assertSame('Page A', $this->resolve($label));

        // Worker mode: the next request (same route and params) arrives and no boundary listener ran.
        $next = self::routed('wiki_page', ['slug' => 'intro']);
        $this->stack->pop();
        $this->stack->push($next);
        self::assertNull($label->currentLabelFor($next, 'default', ''));

        $this->stack->pop();
        $this->stack->push($this->page);
        self::assertNull($this->resolve($label), 'dropped once seen stale');
    }

    public function testLabelIsNotAppliedOnceTheRequestIsGone(): void
    {
        $label = $this->label();
        $label->setCurrentLabel('Page A');

        $this->stack->pop();
        self::assertNull($this->resolve($label));
    }

    public function testMainRequestBoundariesClearAndSubRequestsDoNot(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $label = $this->label();

        $label->setCurrentLabel('Title');
        $label->onKernelBoundary(new RequestEvent($kernel, Request::create('/f'), HttpKernelInterface::SUB_REQUEST));
        self::assertSame('Title', $this->resolve($label));

        $label->onKernelBoundary(new RequestEvent($kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST));
        self::assertNull($this->resolve($label));
    }

    public function testConsoleAndMessengerBoundariesClearThroughTheDispatcher(): void
    {
        $label = $this->label();
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($label);

        foreach ([
            ConsoleEvents::COMMAND,
            ConsoleEvents::TERMINATE,
            'Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent',
            'Symfony\Component\Messenger\Event\WorkerMessageHandledEvent',
            'Symfony\Component\Messenger\Event\WorkerMessageFailedEvent',
        ] as $eventName) {
            $label->setCurrentLabel('Title');
            $dispatcher->dispatch(new stdClass(), $eventName);
            self::assertNull($this->resolve($label), $eventName);
        }
    }

    public function testSubscribesToEveryBoundary(): void
    {
        $events = BreadcrumbCurrentLabel::getSubscribedEvents();

        foreach ([KernelEvents::REQUEST, KernelEvents::EXCEPTION, KernelEvents::TERMINATE] as $event) {
            self::assertSame('onKernelBoundary', $events[$event][0]);
        }
        self::assertArrayHasKey(ConsoleEvents::COMMAND, $events);
        self::assertArrayHasKey(ConsoleEvents::TERMINATE, $events);
    }

    private function label(?TranslatorInterface $translator = null): BreadcrumbCurrentLabel
    {
        return new BreadcrumbCurrentLabel($this->stack, $translator);
    }

    private function resolve(BreadcrumbCurrentLabel $label, ?string $locale = null): ?string
    {
        return $label->currentLabelFor($this->page, 'default', '', $locale);
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function routed(string $route, array $params): Request
    {
        $request = Request::create('/x');
        $request->attributes->set('_route', $route);
        $request->attributes->set('_route_params', $params);

        return $request;
    }
}
