<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ContentQueue;
use App\Enum\ContentQueueStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

class ContentQueueCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ContentQueue::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Queue Item')
            ->setEntityLabelInPlural('Content Queue')
            ->setDefaultSort(['priority' => 'DESC', 'createdAt' => 'ASC'])
            ->setSearchFields(['topic']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('site'))
            ->add(ChoiceFilter::new('status')->setChoices(array_combine(
                array_map(fn(ContentQueueStatus $s) => $s->value, ContentQueueStatus::cases()),
                array_map(fn(ContentQueueStatus $s) => $s->value, ContentQueueStatus::cases()),
            )));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnDetail();
        yield AssociationField::new('site')->setRequired(true);
        yield TextField::new('topic')->setColumns(12);
        yield AssociationField::new('targetCategory');
        yield IntegerField::new('targetWordCount')->setColumns(3)->hideOnIndex();
        yield TextField::new('promptTemplate')->setColumns(4)->hideOnIndex();
        yield IntegerField::new('priority')->setColumns(2);

        yield ChoiceField::new('status')
            ->setChoices(array_combine(
                array_map(fn(ContentQueueStatus $s) => $s->value, ContentQueueStatus::cases()),
                ContentQueueStatus::cases(),
            ))
            ->renderAsBadges([
                ContentQueueStatus::Pending->value => 'secondary',
                ContentQueueStatus::Processing->value => 'warning',
                ContentQueueStatus::Completed->value => 'success',
                ContentQueueStatus::Failed->value => 'danger',
            ]);

        yield AssociationField::new('resultArticle')->hideOnForm()->onlyOnDetail();
        yield TextareaField::new('errorMessage')->hideOnIndex()->hideOnForm();
        yield DateTimeField::new('createdAt')->hideOnForm();
        yield DateTimeField::new('processedAt')->hideOnForm();
    }
}
