<?php

namespace App\Security;

use App\Service\ApiTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class ApiTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly ApiTokenManager $apiTokenManager,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return str_starts_with((string) $request->headers->get('Authorization'), 'Bearer ');
    }

    public function authenticate(Request $request): Passport
    {
        $plainToken = $this->extractToken($request);
        if ($plainToken === null) {
            throw new CustomUserMessageAuthenticationException('Token API manquant.');
        }

        $apiToken = $this->apiTokenManager->findValidToken($plainToken);
        if ($apiToken === null || $apiToken->getAnimator() === null) {
            throw new CustomUserMessageAuthenticationException('Token API invalide.');
        }

        $apiToken->markUsed();
        $this->entityManager->flush();

        return new SelfValidatingPassport(new UserBadge(
            $apiToken->getAnimator()->getUserIdentifier(),
            fn () => $apiToken->getAnimator(),
        ));
    }

    public function onAuthenticationSuccess(Request $request, $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => [
                'code' => 'unauthorized',
                'message' => $exception->getMessage(),
            ],
        ], Response::HTTP_UNAUTHORIZED);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new JsonResponse([
            'error' => [
                'code' => 'authentication_required',
                'message' => 'Authentification API requise.',
            ],
        ], Response::HTTP_UNAUTHORIZED);
    }

    private function extractToken(Request $request): ?string
    {
        $authorization = (string) $request->headers->get('Authorization');
        if (!str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($authorization, 7));

        return $token !== '' ? $token : null;
    }
}
