<?php
namespace App\Form;

use App\Entity\ApiKey;
use App\Entity\UserAccount;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class ApiKeyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class)
            ->add('token', TextType::class)
            ->add('user', EntityType::class, array(
                'class' => UserAccount::class,
                'choice_label' => 'username'
            ))
            ->add('enabled', CheckboxType::class, array('label' => 'Active', 'required' => false))
            ->add('save', SubmitType::class, array('label' => 'Save changes'))
            ->add('cancel', SubmitType::class, array('label' => 'Cancel'));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => ApiKey::class,
        ));
    }
}

