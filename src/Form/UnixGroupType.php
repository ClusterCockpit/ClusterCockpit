<?php

namespace App\Form;

use App\Entity\UnixGroup;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UnixGroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('groupId', TextType::class, [
                'attr' => ['autofocus' => true],
                'label' => 'Group Id',
                 'required' => false,
                 'disabled' => true,
            ])
            ->add('organisation', TextType::class, [
                'label' => 'label.title',
                 'required' => false,
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Submit changes',
            ])
            ->add('contact', TextareaType::class, [
                'label' => 'label.summary',
                 'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => UnixGroup::class,
        ]);
    }
}
