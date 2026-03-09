<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\UserMenu;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('MultiSite Platform')
            ->setFaviconPath('favicon.ico')
            ->renderContentMaximized();
    }

    public function configureUserMenu(UserInterface $user): UserMenu
    {
        return parent::configureUserMenu($user)
            ->setName((string) $user)
            ->displayUserName(true)
            ->addMenuItems([
                MenuItem::linkToUrl('Logout', 'fa fa-sign-out', $this->generateUrl('admin_logout')),
            ]);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Content');
        yield MenuItem::linkTo(ArticleCrudController::class, 'Articles', 'fa fa-newspaper');
        yield MenuItem::linkTo(CategoryCrudController::class, 'Categories', 'fa fa-folder');
        yield MenuItem::linkTo(TagCrudController::class, 'Tags', 'fa fa-tags');
        yield MenuItem::linkTo(MediaCrudController::class, 'Media', 'fa fa-image');

        yield MenuItem::section('Automation');
        yield MenuItem::linkTo(ContentQueueCrudController::class, 'Content Queue', 'fa fa-clock');
        yield MenuItem::linkTo(ToolCrudController::class, 'Tools', 'fa fa-calculator');
        yield MenuItem::linkTo(RedirectCrudController::class, 'Redirects', 'fa fa-arrow-right');

        yield MenuItem::section('Settings');
        yield MenuItem::linkTo(SiteCrudController::class, 'Sites', 'fa fa-globe');

        yield MenuItem::section('Admin');
        yield MenuItem::linkTo(UserCrudController::class, 'Users', 'fa fa-users')
            ->setPermission('ROLE_SUPER_ADMIN');

        yield MenuItem::section('');
        yield MenuItem::linkToUrl('View Site', 'fa fa-external-link', '/');
    }
}
