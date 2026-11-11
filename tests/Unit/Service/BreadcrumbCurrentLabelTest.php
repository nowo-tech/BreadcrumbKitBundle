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
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class BreadcrumbCurrentLabelTest extends TestCase
{
    public function testNothingSetResolvesToNull(): void
    {
        self::assertNull((new BreadcrumbCurrentLabel())->resolve());
    }

    public function testLabelOutsideHttpResolvesWhileNoRequestRuns(): void
    {
        $label = new BreadcrumbCurrentLabel(new RequestStack());
        $label->setCurrentLabel('Release notes');

        self::assertSame('Release notes', $label->resolve('en'));
    }

    public function testLabelIsNormalisedToOneLine(): void
    {
        $label = new BreadcrumbCurrentLabel();

        $label->setCurrentLabel("  Line one\n\tline\u{2028}two \u{0007} ");
        self::assertSame('Line one line two', $label->resolve());

        $label->setCurrentLabel(str_repeat('é', BreadcrumbCurrentLabel::MAX_LENGTH + 10));
        self::assertSame(BreadcrumbCurrentLabel::MAX_LENGTH, mb_strlen((string) $label->resolve()));
    }

    public function testHtmlIsKeptAsTextForTheTemplateToEscape(): void
    {
        $label = new BreadcrumbCurrentLabel();
        $label->setCurrentLabel('<script>alert(1)</script>');

        self::assertSame('<script>alert(1)</script>', $label->resolve());
    }

    public function testBlankNullOrInvalidUtf8RestoresTheItemLabel(): void
    {
        $label = new BreadcrumbCurrentLabel();

        foreach ([null, '', "  \n ", "\xC3\x28"] as $value) {
            $label->setCurrentLabel('Title');
            $label->setCurrentLabel($value);
            self::assertNull($label->resolve());
        }
    }

    public function testStringableIsAccepted(): void
    {
        $label = new BreadcrumbCurrentLabel();
        $label->setCurrentLabel(new class implements Stringable {
            public function __toString(): string
            {
                return 'From object';
            }
        });

        self::assertSame('From object', $label->resolve());
    }

    public function testClearForgetsTheLabel(): void
    {
        $label = new BreadcrumbCurrentLabel();
        $label->setCurrentLabel('Title');
        $label->clearCurrentLabel();

        self::assertNull($label->resolve());

        $label->setCurrentLabel('Title');
        $label->reset();
        self::assertNull($label->resolve());
    }

    public function testStringIsNotTranslatedWithoutADomain(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::never())->method('trans');

        $label = new BreadcrumbCurrentLabel(null, $translator);
        $label->setCurrentLabel('wiki.page.title');

        self::assertSame('wiki.page.title', $label->resolve('es'));
    }

    public function testStringWithADomainIsTranslatedInTheTrailLocale(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())->method('trans')
            ->with('wiki.page.title', ['%name%' => 'Intro'], 'wiki', 'es')
            ->willReturn("Página\n Intro");

        $label = new BreadcrumbCurrentLabel(null, $translator);
        $label->setCurrentLabel('wiki.page.title', 'wiki', ['%name%' => 'Intro']);

        self::assertSame('Página Intro', $label->resolve('es'));
    }

    public function testEmptyDomainMeansNoTranslation(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::never())->method('trans');

        $label = new BreadcrumbCurrentLabel(null, $translator);
        $label->setCurrentLabel('Key', '');

        self::assertSame('Key', $label->resolve('es'));
    }

    public function testDomainWithoutTranslatorKeepsTheText(): void
    {
        $label = new BreadcrumbCurrentLabel();
        $label->setCurrentLabel('wiki.page.title', 'wiki');

        self::assertSame('wiki.page.title', $label->resolve('es'));
    }

    public function testTranslatableIsTranslated(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->with('page.title', ['%t%' => 'X'], 'wiki', 'fr')->willReturn('Page X');

        $label = new BreadcrumbCurrentLabel(null, $translator);
        $label->setCurrentLabel(new TranslatableMessage('page.title', ['%t%' => 'X'], 'wiki'));

        self::assertSame('Page X', $label->resolve('fr'));
    }

    public function testTranslatableWithoutTranslator(): void
    {
        $label = new BreadcrumbCurrentLabel();
        $label->setCurrentLabel(new TranslatableMessage('page.title'));
        self::assertSame('page.title', $label->resolve());

        $label->setCurrentLabel(new class implements TranslatableInterface {
            public function trans(TranslatorInterface $translator, ?string $locale = null): string
            {
                return 'never';
            }
        });
        self::assertNull($label->resolve(), 'not Stringable, no translator: no text');
    }

    public function testTranslationToBlankKeepsTheItemLabel(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('  ');

        $label = new BreadcrumbCurrentLabel(null, $translator);
        $label->setCurrentLabel('key', 'wiki');

        self::assertNull($label->resolve());
    }

    public function testLabelOfAnotherMainRequestIsNeverApplied(): void
    {
        $stack = new RequestStack();
        $first = Request::create('/a');
        $stack->push($first);

        $label = new BreadcrumbCurrentLabel($stack);
        $label->setCurrentLabel('Page A');
        self::assertSame('Page A', $label->resolve());

        // Worker mode: the next request arrives and no boundary listener ran.
        $stack->pop();
        $stack->push(Request::create('/b'));
        self::assertNull($label->resolve());

        $stack->pop();
        $stack->push($first);
        self::assertNull($label->resolve(), 'dropped once seen stale');
    }

    public function testLabelSetDuringARequestIsNotAppliedOutsideIt(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/a'));
        $label = new BreadcrumbCurrentLabel($stack);
        $label->setCurrentLabel('Page A');

        $stack->pop();
        self::assertNull($label->resolve());
    }

    public function testLabelSetOutsideHttpIsNotAppliedInsideARequest(): void
    {
        $stack = new RequestStack();
        $label = new BreadcrumbCurrentLabel($stack);
        $label->setCurrentLabel('From a command');

        $stack->push(Request::create('/a'));
        self::assertNull($label->resolve());
    }

    public function testSubRequestsShareTheLabelOfTheirMainRequest(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/a'));
        $label = new BreadcrumbCurrentLabel($stack);
        $label->setCurrentLabel('Page A');

        $stack->push(Request::create('/_fragment'));
        self::assertSame('Page A', $label->resolve());
    }

    public function testMainRequestBoundariesClearAndSubRequestsDoNot(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $label = new BreadcrumbCurrentLabel();

        $label->setCurrentLabel('Title');
        $label->onKernelBoundary(new RequestEvent($kernel, Request::create('/f'), HttpKernelInterface::SUB_REQUEST));
        self::assertSame('Title', $label->resolve());

        $label->onKernelBoundary(new RequestEvent($kernel, Request::create('/'), HttpKernelInterface::MAIN_REQUEST));
        self::assertNull($label->resolve());
    }

    public function testConsoleAndMessengerBoundariesClearThroughTheDispatcher(): void
    {
        $label = new BreadcrumbCurrentLabel();
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
            self::assertNull($label->resolve(), $eventName);
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
}
