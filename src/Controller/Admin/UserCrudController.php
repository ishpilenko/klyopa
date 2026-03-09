<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $crud
            ->setEntityLabelInSingular('User')
            ->setEntityLabelInPlural('Users')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['name', 'email'])
            ->setPaginatorPageSize(30);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();

        yield TextField::new('name');

        yield EmailField::new('email');

        yield TextField::new('plainPassword', 'Password')
            ->setFormType(PasswordType::class)
            ->setFormTypeOptions(['attr' => ['autocomplete' => 'new-password']])
            ->setRequired(Crud::PAGE_NEW === $pageName)
            ->onlyOnForms()
            ->setHelp(Crud::PAGE_EDIT === $pageName ? 'Leave blank to keep current password.' : '');

        yield ChoiceField::new('roles')
            ->setChoices([
                'Admin'       => 'ROLE_ADMIN',
                'Super Admin' => 'ROLE_SUPER_ADMIN',
            ])
            ->allowMultipleChoices()
            ->renderExpanded()
            ->hideOnIndex();

        yield BooleanField::new('isActive');

        yield DateTimeField::new('createdAt')
            ->hideOnForm()
            ->onlyOnDetail();
    }

    public function persistEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->encodePassword($entityInstance);
        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, mixed $entityInstance): void
    {
        $this->encodePassword($entityInstance);
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function encodePassword(mixed $entity): void
    {
        if (!$entity instanceof User) {
            return;
        }

        $plain = $entity->getPlainPassword();

        if ($plain !== null && $plain !== '') {
            $entity->setPassword($this->passwordHasher->hashPassword($entity, $plain));
            $entity->eraseCredentials();
        }
    }
}
