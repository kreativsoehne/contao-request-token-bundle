<?php

declare(strict_types=1);

/*
 * This file is part of the kreativsoehne/contao-request-token-bundle package.
 *
 * (c) Kreativ&Söhne GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KreativSoehne\ContaoRequestTokenBundle\Tests\Unit;

use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use KreativSoehne\ContaoRequestTokenBundle\ContaoManager\Plugin;
use KreativSoehne\ContaoRequestTokenBundle\ContaoRequestTokenBundle;
use KreativSoehne\ContaoRequestTokenBundle\DependencyInjection\ContaoRequestTokenExtension;
use KreativSoehne\ContaoRequestTokenBundle\EventListener\RequestTokenScriptListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class RequestTokenScriptListenerTest extends TestCase
{
    private const FORM = '<html><body><form method="post"><input type="hidden" name="REQUEST_TOKEN" value=""><button>Senden</button></form></body></html>';

    public function testAFrontEndPageWithAFormGetsTheScriptBeforeTheClosingBody(): void
    {
        $response = $this->respond(self::FORM);

        $content = (string) $response->getContent();
        $this->assertSame(1, \substr_count($content, RequestTokenScriptListener::ATTRIBUTE));
        $this->assertStringEndsWith(RequestTokenScriptListener::tag() . '</body></html>', $content);
        $this->assertStringContainsString('/_contao/request_token_script?', $content);
    }

    public function testAFormWhoseTokenIsAlreadyFilledGetsTheScriptToo(): void
    {
        // The same page goes out with and without a token, depending on the cookies of the
        // request; the script decides in the browser, at the moment of the submit.
        $response = $this->respond(\str_replace('value=""', 'value="abc"', self::FORM));

        $this->assertStringContainsString(RequestTokenScriptListener::ATTRIBUTE, (string) $response->getContent());
    }

    public function testAPrefixedTokenFieldCounts(): void
    {
        $response = $this->respond(\str_replace('name="REQUEST_TOKEN"', 'name="login[REQUEST_TOKEN]"', self::FORM));

        $this->assertStringContainsString(RequestTokenScriptListener::ATTRIBUTE, (string) $response->getContent());
    }

    public function testAPageWithoutAFormStaysAsItIs(): void
    {
        $page = '<html><body><p>Kein Formular</p></body></html>';

        $this->assertSame($page, $this->respond($page)->getContent());
    }

    public function testTheScriptIsAddedOnlyOnce(): void
    {
        $once = (string) $this->respond(self::FORM)->getContent();

        $this->assertSame($once, $this->respond($once)->getContent());
    }

    public function testTheBackendIsLeftAlone(): void
    {
        $this->assertSame(self::FORM, $this->respond(self::FORM, 'backend')->getContent());
    }

    public function testARequestOutsideContaoIsLeftAlone(): void
    {
        $this->assertSame(self::FORM, $this->respond(self::FORM, null)->getContent());
    }

    public function testASubRequestIsLeftAlone(): void
    {
        $this->assertSame(self::FORM, $this->respond(self::FORM, 'frontend', HttpKernelInterface::SUB_REQUEST)->getContent());
    }

    public function testOtherContentTypesAreLeftAlone(): void
    {
        $response = new Response(self::FORM, Response::HTTP_OK, ['Content-Type' => 'application/json']);
        $this->dispatch($response);

        $this->assertSame(self::FORM, $response->getContent());
    }

    public function testAFileIsLeftAlone(): void
    {
        $response = new BinaryFileResponse(__FILE__);
        $this->dispatch($response);

        $this->assertFalse($response->getContent());
    }

    public function testAPageWithoutBodyIsLeftAlone(): void
    {
        $fragment = '<form method="post"><input type="hidden" name="REQUEST_TOKEN" value=""></form>';

        $this->assertSame($fragment, $this->respond($fragment)->getContent());
    }

    public function testTheContentLengthFollowsTheNewContent(): void
    {
        $response = new Response(self::FORM, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8', 'Content-Length' => (string) \strlen(self::FORM)]);
        $this->dispatch($response);

        $this->assertSame((string) \strlen((string) $response->getContent()), $response->headers->get('Content-Length'));
    }

    public function testTheExtensionRegistersTheListenerLateInTheResponse(): void
    {
        $container = new ContainerBuilder();
        (new ContaoRequestTokenExtension())->load([], $container);

        $tags = $container->getDefinition(RequestTokenScriptListener::class)->getTag('kernel.event_listener');
        $this->assertSame([['event' => 'kernel.response', 'priority' => -100]], $tags);
    }

    public function testThePluginLoadsTheBundleAfterTheCore(): void
    {
        $configs = (new Plugin())->getBundles($this->createStub(ParserInterface::class));

        $this->assertCount(1, $configs);
        $this->assertInstanceOf(BundleConfig::class, $configs[0]);
        $this->assertSame(ContaoRequestTokenBundle::class, $configs[0]->getName());
        $this->assertSame(['Contao\CoreBundle\ContaoCoreBundle'], $configs[0]->getLoadAfter());
    }

    private function respond(string $content, ?string $scope = 'frontend', int $type = HttpKernelInterface::MAIN_REQUEST): Response
    {
        $response = new Response($content, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
        $this->dispatch($response, $scope, $type);

        return $response;
    }

    private function dispatch(Response $response, ?string $scope = 'frontend', int $type = HttpKernelInterface::MAIN_REQUEST): void
    {
        $request = new Request();

        if (null !== $scope) {
            $request->attributes->set('_scope', $scope);
        }

        (new RequestTokenScriptListener())(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, $type, $response));
    }
}
