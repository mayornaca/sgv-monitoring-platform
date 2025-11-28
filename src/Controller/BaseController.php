<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Psr\Log\LoggerInterface;

/**
 * Base controller for Symfony 6.4 with dependency injection
 * All controllers should extend this instead of AbstractController
 */
abstract class BaseController extends AbstractController
{
    protected ?EntityManagerInterface $entityManager = null;
    protected ?FormFactoryInterface $formFactory = null;
    protected ?Environment $twig = null;
    protected ?RouterInterface $router = null;
    protected ?TranslatorInterface $translator = null;
    protected ?AuthorizationCheckerInterface $authChecker = null;
    protected ?TokenStorageInterface $tokenStorage = null;
    protected ?UserPasswordHasherInterface $passwordHasher = null;
    protected ?RequestStack $requestStack = null;
    protected ?SessionInterface $session = null;
    protected ?LoggerInterface $logger = null;

    /**
     * @required
     */
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @required
     */
    public function setFormFactory(FormFactoryInterface $formFactory): void
    {
        $this->formFactory = $formFactory;
    }

    /**
     * @required
     */
    public function setTwig(Environment $twig): void
    {
        $this->twig = $twig;
    }

    /**
     * @required
     */
    public function setRouter(RouterInterface $router): void
    {
        $this->router = $router;
    }

    /**
     * @required
     */
    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    /**
     * @required
     */
    public function setAuthChecker(AuthorizationCheckerInterface $authChecker): void
    {
        $this->authChecker = $authChecker;
    }

    /**
     * @required
     */
    public function setTokenStorage(TokenStorageInterface $tokenStorage): void
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * @required
     */
    public function setPasswordHasher(UserPasswordHasherInterface $passwordHasher): void
    {
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * @required
     */
    public function setRequestStack(RequestStack $requestStack): void
    {
        $this->requestStack = $requestStack;
        $request = $requestStack->getCurrentRequest();
        if ($request && $request->hasSession()) {
            $this->session = $request->getSession();
        }
    }

    /**
     * @required
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Get current request
     */
    protected function getCurrentRequest()
    {
        return $this->requestStack?->getCurrentRequest();
    }

    /**
     * Get current user
     */
    protected function getCurrentUser()
    {
        $token = $this->tokenStorage?->getToken();
        return $token?->getUser();
    }

    /**
     * Check if user has role
     */
    protected function isGranted($attributes, $subject = null): bool
    {
        return $this->authChecker?->isGranted($attributes, $subject) ?? false;
    }

    /**
     * Create form
     */
    protected function createFormBuilder(mixed $data = null, array $options = []): \Symfony\Component\Form\FormBuilderInterface
    {
        return $this->formFactory?->createBuilder('Symfony\Component\Form\Extension\Core\Type\FormType', $data, $options) 
            ?? parent::createFormBuilder($data, $options);
    }

    /**
     * Render template
     */
    protected function renderView(string $view, array $parameters = []): string
    {
        return $this->twig?->render($view, $parameters) ?? '';
    }

    /**
     * Generate URL
     */
    protected function generateUrl(string $route, array $parameters = [], int $referenceType = RouterInterface::ABSOLUTE_PATH): string
    {
        return $this->router?->generate($route, $parameters, $referenceType) ?? '';
    }

    /**
     * Translate message
     */
    protected function trans(string $id, array $parameters = [], string $domain = null, string $locale = null): string
    {
        return $this->translator?->trans($id, $parameters, $domain, $locale) ?? $id;
    }

    /**
     * Add flash message
     */
    protected function addFlash(string $type, $message): void
    {
        $this->session?->getFlashBag()->add($type, $message);
    }

    /**
     * Get Doctrine repository
     */
    protected function getRepository(string $entityClass)
    {
        return $this->entityManager?->getRepository($entityClass);
    }

    /**
     * Persist and flush entity
     */
    protected function save($entity): void
    {
        if ($this->entityManager) {
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
        }
    }

    /**
     * Remove and flush entity
     */
    protected function remove($entity): void
    {
        if ($this->entityManager) {
            $this->entityManager->remove($entity);
            $this->entityManager->flush();
        }
    }
}
