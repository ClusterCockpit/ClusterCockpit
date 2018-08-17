<?php
/*
 *  This file is part of ClusterCockpit.
 *
 *  Copyright (c) 2018 Jan Eitzinger
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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Guard\Authenticator\AbstractFormLoginAuthenticator;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Psr\Log\LoggerInterface;
use App\Adapter\LdapManager;

class LdapAuthenticator extends AbstractFormLoginAuthenticator
{
    use TargetPathTrait;

    private $_logger;
    private $_ldap;
    private $_security;
    private $_router;
    private $_passwordEncoder;
    private $_csrfTokenManager;

    public function __construct(
        LoggerInterface $logger,
        RouterInterface $router,
        UserPasswordEncoderInterface $passwordEncoder,
        CsrfTokenManagerInterface $csrfTokenManager,
        Security $security,
        LdapManager $ldap
    )
    {
        $this->_logger = $logger;
        $this->_ldap = $ldap;
        $this->_security = $security;
        $this->_router = $router;
        $this->_passwordEncoder = $passwordEncoder;
        $this->_csrfTokenManager = $csrfTokenManager;
    }

    public function supports(Request $request)
    {
        $this->_logger->info('SUPPORT');
        if ($this->_security->getUser()) {
            return false;
        }

        $isLoginSubmit = $request->getPathInfo() == '/login' && $request->isMethod('POST');

        if (!$isLoginSubmit) {
            return false;
        }

        return true;
    }

    public function getCredentials(Request $request)
    {
        $this->_logger->info('GET');

        $username = $request->request->get('_username');
        $password = $request->request->get('_password');
        $csrfToken = $request->request->get('_csrf_token');

        $credentials = array(
            'username' => $username,
            'password' => $password,
        );

        $this->_logger->info('getCredentials', $credentials);

        if (false === $this->_csrfTokenManager->isTokenValid(new CsrfToken('authenticate', $csrfToken))) {
            throw new InvalidCsrfTokenException('Invalid CSRF token.');
        }

        $request->getSession()->set(
            Security::LAST_USERNAME,
            $username
        );

        return $credentials;
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $this->_logger->info('USER', $credentials);
        $user = $userProvider->loadUserByUsername($credentials['username']);

        if ( empty($user) ){
            $this->_logger->info('NULL');
        } else {
            $this->_logger->info('USER', array($user->getUsername()));
        }

        return $user;
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        $this->_logger->info('CHECK');
        $username = $credentials['username'];
        $password = $credentials['password'];

        if ('' === (string) $password) {
            throw new BadCredentialsException('The presented password must not be empty.');
        }

        $dbPassword = $user->getPassword();

        if ( empty($dbPassword) ) {
            $this->_logger->info('LDAP');
            /* authenticate with ldap bind */
            try {
                $this->_ldap->bindUser($username, $password);
            } catch (ConnectionException $e) {
                throw new BadCredentialsException('Invalid credentials.');
            }
            return true;
        } else {
            $this->_logger->info('DB');
            /* authenticate with password */
            if ($this->_passwordEncoder->isPasswordValid($user, $password)) {
                return true;
            }
            return false;
        }

        return false;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        $this->_logger->info('SUCCESS');
        $targetPath = null;

        $targetPath = $this->getTargetPath($request->getSession(), $providerKey);

        if (!$targetPath) {
            $targetPath = $this->_router->generate('index');
        }

        return new RedirectResponse($targetPath);
    }

     public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        $url = $this->_router->generate('security_login');
        return new RedirectResponse($url);
    }

    public function supportsRememberMe()
    {
        return false;
    }

    protected function getLoginUrl()
    {
        $this->_logger->info('URL');
        return $this->_router->generate('security_login');
    }

}
