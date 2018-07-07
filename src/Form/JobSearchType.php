<?php

namespace App\Form;

use App\Entity\JobSearch;
use App\Form\Type\DateTimePickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class JobSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('jobId', null, [
                'attr' => ['autofocus' => true],
                'label' => 'label.title',
            ])
            ->add('userId', null, [
                'label' => 'label.title',
            ])
            ->add('summary', TextareaType::class, [
                'label' => 'label.summary',
            ])
            ->add('content', null, [
                'attr' => ['rows' => 20],
                'label' => 'label.content',
            ])
            ->add('publishedAt', DateTimePickerType::class, [
                'label' => 'label.published_at',
            ])
            ->add('tags', TagsInputType::class, [
                'label' => 'label.tags',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => JobSearch::class,
        ]);
    }
}
