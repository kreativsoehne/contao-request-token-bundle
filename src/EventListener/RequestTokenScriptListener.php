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

namespace KreativSoehne\ContaoRequestTokenBundle\EventListener;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Puts a small script on every front end page that holds a form with a request token.
 *
 * Why it is needed: Contao 4.13 answers a request without cookies with an empty
 * request token, so that the page can be cached. A consent tool then sets its own
 * cookies with JavaScript, after the page has loaded. When the visitor sends the
 * form, those cookies go along, Contao now insists on the token, finds it empty
 * and answers "Invalid request token" (HTTP 400). Everybody who lands directly on
 * a page with a form is hit; nothing shows up in the log.
 *
 * Contao's own remedy, "always load from cache", asks for the token at the end of
 * the page. That request usually leaves before the consent tool has set its
 * cookie and gets an empty token as well. The script waits for the submit instead:
 * if the token is empty then, it fetches one from Contao's own endpoint
 * /_contao/request_token_script, which by now carries the same cookies as the
 * submit, and sends the form afterwards. The token is still checked by Contao.
 *
 * It runs before Contao's CSRF subscriber (-1006) and before the HTTP cache stores
 * the page, so a cached page carries the script as well.
 */
final class RequestTokenScriptListener
{
    /** Marks the script, so the listener never adds it twice. */
    public const ATTRIBUTE = 'data-request-token-refresh';

    private const SCRIPT = <<<'JS'
        (function () {
            var selector = 'input[name="REQUEST_TOKEN"], input[name$="[REQUEST_TOKEN]"]';
            document.addEventListener('submit', function (event) {
                var form = event.target;
                if (event.defaultPrevented || !(form instanceof HTMLFormElement) || 'post' !== form.method.toLowerCase() || form.dataset.requestToken) {
                    return;
                }
                var inputs = Array.prototype.slice.call(form.querySelectorAll(selector));
                if (0 === inputs.length || inputs.every(function (input) { return '' !== input.value; })) {
                    return;
                }
                event.preventDefault();
                var submitter = event.submitter || null;
                var script = document.createElement('script');
                script.src = '/_contao/request_token_script?' + Date.now();
                script.onload = script.onerror = function () {
                    script.parentNode.removeChild(script);
                    form.dataset.requestToken = 'loaded';
                    if (form.requestSubmit) {
                        form.requestSubmit(submitter && submitter.form === form ? submitter : undefined);
                    } else {
                        form.submit();
                    }
                };
                document.head.appendChild(script);
            });
        })();
        JS;

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || 'frontend' !== $event->getRequest()->attributes->get('_scope')) {
            return;
        }

        $response = $event->getResponse();

        if (!$this->isHtml($response)) {
            return;
        }

        $content = $response->getContent();

        if (!\is_string($content) || !$this->needsScript($content)) {
            return;
        }

        $position = \strripos($content, '</body>');

        if (false === $position) {
            return;
        }

        $content = \substr_replace($content, self::tag(), $position, 0);
        $response->setContent($content);

        if ($response->headers->has('Content-Length')) {
            $response->headers->set('Content-Length', (string) \strlen($content));
        }
    }

    public static function tag(): string
    {
        return '<script ' . self::ATTRIBUTE . '>' . self::SCRIPT . '</script>';
    }

    private function isHtml(Response $response): bool
    {
        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return false;
        }

        return \str_contains((string) $response->headers->get('Content-Type', 'text/html'), 'text/html');
    }

    private function needsScript(string $content): bool
    {
        return 1 === \preg_match('/name="(?:[^"]*\[)?REQUEST_TOKEN\]?"/', $content)
            && !\str_contains($content, self::ATTRIBUTE);
    }
}
