<?php
/*
 *  This file is part of ClusterCockpit.
 *
 *  Copyright (c) 2021 Jan Eitzinger
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Security;
use Symfony\Bridge\Doctrine\Security\User\EntityUserProvider;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Ldap\Security\LdapBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\ParameterBagUtils;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\Configuration;
use App\Adapter\LdapAdapter;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{

    private $_em;
    private $httpUtils;
    private $userProvider;
    private $httpKernel;

    public function __construct(
        HttpUtils $httpUtils,
        EntityManagerInterface $em,
        EntityUserProvider $userProvider)
    {
        $this->httpUtils = $httpUtils;
        $this->userProvider = $userProvider;
        $this->_em = $em;
    }

    private function getCredentials(Request $request): array
    {
        $credentials = [];
        $credentials['csrf_token'] = ParameterBagUtils::getRequestParameterValue($request, '_csrf_token');
        $credentials['username'] = ParameterBagUtils::getParameterBagValue($request->request, '_username');
        $credentials['password'] = ParameterBagUtils::getParameterBagValue($request->request, '_password') ?? '';

        if (!\is_string($credentials['username']) && (!\is_object($credentials['username']) || !method_exists($credentials['username'], '__toString'))) {
            throw new BadRequestHttpException(sprintf('The key "%s" must be a string, "%s" given.', '_username',
                \gettype($credentials['username'])));
        }

        $credentials['username'] = trim($credentials['username']);

        if (\strlen($credentials['username']) > Security::MAX_USERNAME_LENGTH) {
            throw new BadCredentialsException('Invalid username.');
        }

        $request->getSession()->set(Security::LAST_USERNAME, $credentials['username']);

        return $credentials;
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->httpUtils->generateUri($request, 'security_login');
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST')
            && $this->httpUtils->checkRequestPath($request, 'security_login');
    }

    public function authenticate(Request $request): PassportInterface
    {
        $credentials = $this->getCredentials($request);

        $user = $this->userProvider->loadUserByIdentifier($credentials['username']);
        $dbPassword = $user->getPassword();
        $passport = new Passport(
            new UserBadge($credentials['username'], [$this->userProvider, 'loadUserByIdentifier']),
            new PasswordCredentials($credentials['password'])
        );

        if ( empty($dbPassword) ) {
            $configuration = new Configuration($this->_em);
            $dnString = $configuration->getValue('ldap_user_base');
            $searchDn = $configuration->getValue('ldap_user_bind');
            $searchPassword = getenv('LDAP_PW');
            $queryString = $configuration->getValue('ldap_user_filter');
            /* I tried many things as service Id, but I think it cannot work as there is no Factory for it */
            $passport->addBadge(new LdapBadge('app.ldap', $dnString));
            /* $passport->addBadge(new LdapBadge('ldap', $dnString, $searchDn, $searchPassword, $queryString)); */
        }
        $passport->addBadge(new CsrfTokenBadge('authenticate', $credentials['csrf_token']));

        return $passport;
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName): ?Response
    {
        return new RedirectResponse($this->httpUtils->generateUri($request, 'list_jobs'));
    }
}
