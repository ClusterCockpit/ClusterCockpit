<?php

namespace App\Form;

use App\Entity\UserAccount;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserAccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username', TextType::class)
            ->add('plainPassword', RepeatedType::class, array(
                'type' => PasswordType::class,
                'first_options'  => array('label' => 'Password'),
                'second_options' => array('label' => 'Repeat Password'))
            )
            ->add('roles', ChoiceType::class, array(
                'choices' => [
                    'ROLE_API' => 'ROLE_API',
                    'ROLE_USER' => 'ROLE_USER',
                    'ROLE_ANALYST' => 'ROLE_ANALYST',
                    'ROLE_ADMIN' => 'ROLE_ADMIN',
                ],
                'multiple' => true,
                'expanded' => true,
                )
            )
            ->add('email', EmailType::class)
            ->add('isActive', CheckboxType::class)
            ->add('save', SubmitType::class, array('label' => 'Save changes'))
            ->add('cancel', SubmitType::class, array('label' => 'Cancel'))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => UserAccount::class,
        ]);
    }
}
