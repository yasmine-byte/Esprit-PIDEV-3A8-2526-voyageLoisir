<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private RouterInterface $router) {}

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $session = $request->getSession();
        $session->remove('_security.main.target_path');
        $session->remove('_security.front.target_path');
        $session->remove('_security.main.failed_target_path');
        $session->remove('_security.front.failed_target_path');

        return new RedirectResponse($this->router->generate('app_home'));
    }
}